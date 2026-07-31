<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . "/../config/configuration.php";
require_once __DIR__ . "/ollama_helper.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Non connecté"]);
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? null;
session_write_close();

$data = json_decode(file_get_contents("php://input"), true);
$scope = $data['scope'] ?? 'perso'; // 'global' ou 'perso'

// Seul le super_admin peut demander l'analyse "globale"
if ($scope === 'global' && $userRole !== 'super_admin') {
    http_response_code(403);
    echo json_encode(["error" => "Accès refusé"]);
    exit;
}

// ============================================================
// UTILITAIRE : normaliser le texte (identique à chat.php)
// ============================================================
function normaliser($texte) {
    $texte = mb_strtolower($texte, 'UTF-8');
    $remplacements = ['à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
                      'î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c'];
    return strtr($texte, $remplacements);
}

function categoriserMessage($msgNorm) {
    if (preg_match('/\b(ajoute|ajouter|creer|crée)\b/u', $msgNorm)) {
        $type = 'Ajout';
    } elseif (preg_match('/\b(modifie|change|mettre a jour|mettre à jour)\b/u', $msgNorm)) {
        $type = 'Modification';
    } elseif (preg_match('/\b(supprime|supprimer)\b/u', $msgNorm)) {
        $type = 'Suppression';
    } elseif (preg_match('/\b(combien|nombre)\b/u', $msgNorm)) {
        $type = 'Statistique';
    } elseif (preg_match('/\bcomment\b/u', $msgNorm)) {
        $type = 'Aide / FAQ';
    } else {
        $type = 'Autre';
    }

    if (strpos($msgNorm, 'client') !== false) {
        $module = 'Client';
    } elseif (strpos($msgNorm, 'produit') !== false || strpos($msgNorm, 'stock') !== false) {
        $module = 'Produit';
    } elseif (strpos($msgNorm, 'commande') !== false) {
        $module = 'Commande';
    } elseif (strpos($msgNorm, 'livraison') !== false) {
        $module = 'Livraison';
    } elseif (strpos($msgNorm, 'employe') !== false || strpos($msgNorm, 'utilisateur') !== false
              || strpos($msgNorm, 'acces') !== false || strpos($msgNorm, 'permission') !== false) {
        $module = 'Gestion Accès';
    } else {
        $module = 'Autre';
    }

    return [$type, $module];
}

function calculerStats($messages) {
    $total = count($messages);
    $types = [];
    $modules = [];
    $questionsCount = [];

    foreach ($messages as $m) {
        $norm = normaliser($m['contenu']);
        [$type, $module] = categoriserMessage($norm);
        $types[$type] = ($types[$type] ?? 0) + 1;
        $modules[$module] = ($modules[$module] ?? 0) + 1;
        $questionsCount[$norm] = ($questionsCount[$norm] ?? 0) + 1;
    }

    arsort($types);
    arsort($modules);
    arsort($questionsCount);

    $typesPct = [];
    foreach ($types as $k => $v) {
        $typesPct[] = ['label' => $k, 'pct' => $total > 0 ? round($v / $total * 100) : 0];
    }
    $modulesPct = [];
    foreach ($modules as $k => $v) {
        $modulesPct[] = ['label' => $k, 'pct' => $total > 0 ? round($v / $total * 100) : 0];
    }

    $topQuestions = [];
    $i = 0;
    foreach ($questionsCount as $normText => $count) {
        if ($count < 2) continue;
        if ($i >= 5) break;
        foreach ($messages as $m) {
            if (normaliser($m['contenu']) === $normText) {
                $topQuestions[] = ['texte' => $m['contenu'], 'count' => $count];
                break;
            }
        }
        $i++;
    }

    return ['total' => $total, 'types' => $typesPct, 'modules' => $modulesPct, 'topQuestions' => $topQuestions];
}

function recupererMessages($conn, $idUserFiltre = null) {
    if ($idUserFiltre) {
        $stmt = mysqli_prepare($conn, "
            SELECT m.contenu
            FROM message m
            JOIN conversation c ON m.id_conversation = c.id_conversation
            WHERE m.role = 'user' AND c.id_user = ?
            ORDER BY m.id_message ASC
        ");
        mysqli_stmt_bind_param($stmt, "i", $idUserFiltre);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
    } else {
        $res = mysqli_query($conn, "
            SELECT m.contenu
            FROM message m
            JOIN conversation c ON m.id_conversation = c.id_conversation
            WHERE m.role = 'user'
            ORDER BY m.id_message ASC
        ");
    }
    $messages = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $messages[] = $row;
    }
    return $messages;
}

// ============================================================
// Construire les statistiques selon la portée demandée
// ============================================================
$messages = ($scope === 'global') ? recupererMessages($conn, null) : recupererMessages($conn, $userId);
$stats = calculerStats($messages);

if ($stats['total'] === 0) {
    echo json_encode(["analyse" => "Pas encore assez de données pour générer une analyse. Utilisez le chatbot pour commencer à accumuler des statistiques."]);
    exit;
}

// ============================================================
// Construire un résumé factuel à donner à l'IA (jamais de chiffre inventé)
// ============================================================
$typesTexte = [];
foreach ($stats['types'] as $t) {
    $typesTexte[] = "{$t['label']}: {$t['pct']}%";
}
$modulesTexte = [];
foreach ($stats['modules'] as $m) {
    $modulesTexte[] = "{$m['label']}: {$m['pct']}%";
}
$questionsTexte = [];
foreach ($stats['topQuestions'] as $q) {
    $questionsTexte[] = "\"{$q['texte']}\" (posée {$q['count']} fois)";
}

$resumeFactuel = "Nombre total de messages analysés : {$stats['total']}. " .
    "Répartition par type d'action : " . implode(', ', $typesTexte) . ". " .
    "Répartition par module concerné : " . implode(', ', $modulesTexte) . ". " .
    (count($questionsTexte) > 0
        ? "Questions les plus répétées : " . implode(' ; ', $questionsTexte) . "."
        : "Aucune question n'a été répétée plusieurs fois.");

$portee = ($scope === 'global')
    ? "l'ensemble des employés utilisant le chatbot de l'application PalmFox"
    : "l'utilisateur actuellement connecté (analyse de sa propre activité avec le chatbot)";

$systemPrompt = "Tu résumes l'utilisation d'un chatbot d'entreprise (PalmFox : clients, produits, commandes, " .
    "livraisons), pour $portee. Écris 3 phrases MAXIMUM, en français, basées UNIQUEMENT sur ces chiffres réels " .
    "(n'en invente aucun autre) : $resumeFactuel";

$modeleUtilise = "llama3.2:1b"; // modèle léger, adapté à une génération rapide sur CPU

$reponse = ollamaChat($modeleUtilise, [
    ["role" => "system", "content" => $systemPrompt],
    ["role" => "user", "content" => "Génère l'analyse."]
], ["num_predict" => 100]); // limite stricte la longueur générée, pour garantir une réponse rapide

if (isset($reponse['error'])) {
    echo json_encode(["analyse" => $reponse['error']]);
} else {
    echo json_encode(["analyse" => $reponse['content']]);
}
?>
