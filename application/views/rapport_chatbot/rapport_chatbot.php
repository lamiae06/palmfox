<?php
session_start();
require "../../../config/configuration.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? null;
$username = $_SESSION['user_nom'] ?? 'Utilisateur';
$estSuperAdmin = ($userRole === 'super_admin');

// ============================================================
// UTILITAIRE : normaliser le texte (identique à chat.php)
// ============================================================
function normaliser($texte) {
    $texte = mb_strtolower($texte, 'UTF-8');
    $remplacements = ['à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
                      'î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c'];
    return strtr($texte, $remplacements);
}

// ============================================================
// Catégoriser un message utilisateur : type d'action + module concerné
// ============================================================
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

// ============================================================
// Construire les statistiques pour un ensemble de messages
// ============================================================
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
        $typesPct[] = ['label' => $k, 'count' => $v, 'pct' => $total > 0 ? round($v / $total * 100) : 0];
    }
    $modulesPct = [];
    foreach ($modules as $k => $v) {
        $modulesPct[] = ['label' => $k, 'count' => $v, 'pct' => $total > 0 ? round($v / $total * 100) : 0];
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

    return [
        'total' => $total,
        'types' => $typesPct,
        'modules' => $modulesPct,
        'topQuestions' => $topQuestions
    ];
}

// ============================================================
// Récupération des données base de données
// ============================================================
function recupererMessages($conn, $idUserFiltre = null) {
    if ($idUserFiltre) {
        $stmt = mysqli_prepare($conn, "
            SELECT m.contenu, c.id_user
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
            SELECT m.contenu, c.id_user
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

function compterConversations($conn, $idUserFiltre = null) {
    if ($idUserFiltre) {
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as t FROM conversation WHERE id_user = ?");
        mysqli_stmt_bind_param($stmt, "i", $idUserFiltre);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
    } else {
        $res = mysqli_query($conn, "SELECT COUNT(*) as t FROM conversation");
    }
    return mysqli_fetch_assoc($res)['t'] ?? 0;
}

$mesMessages = recupererMessages($conn, $userId);
$mesStats = calculerStats($mesMessages);
$mesConversations = compterConversations($conn, $userId);

$statsGlobales = null;
$statsParEmploye = [];
if ($estSuperAdmin) {
    $tousMessages = recupererMessages($conn, null);
    $statsGlobales = calculerStats($tousMessages);
    $totalConversations = compterConversations($conn, null);

    $resUsers = mysqli_query($conn, "SELECT id_user, username, role FROM utilisateurs ORDER BY username ASC");
    while ($u = mysqli_fetch_assoc($resUsers)) {
        $msgsEmploye = array_values(array_filter($tousMessages, function($m) use ($u) {
            return $m['id_user'] == $u['id_user'];
        }));
        if (count($msgsEmploye) === 0) continue;
        $statsParEmploye[] = [
            'username' => $u['username'],
            'role' => $u['role'],
            'stats' => calculerStats($msgsEmploye),
            'conversations' => compterConversations($conn, $u['id_user'])
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PalmFox - Rapport Chatbot</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="../includes/sidebar_header.css">
    <link rel="stylesheet" href="rapport_chatbot.css">
    <link rel="stylesheet" href="../includes/chatbot_widget.css">
</head>
<body>

    <?php include "../includes/sidebar.php"; ?>

    <main class="main-content">
        <!-- Topbar correctement positionnée à droite -->
        <div class="topbar">
            <div class="user-profile">
                <div class="user-avatar"><?= strtoupper(substr($username, 0, 2)) ?></div>
                <h5><?= htmlspecialchars($username) ?></h5>
            </div>
        </div>

        <header class="page-header">
            <div>
                <h1>Rapport du Chatbot</h1>
                <p class="page-subtitle">Analyse des conversations et des tendances d'utilisation.</p>
            </div>
        </header>

        <?php if ($estSuperAdmin): ?>
        <!-- ===================== VUE GLOBALE (super_admin) ===================== -->
        <section class="rapport-section">
            <h2><i class="fa-solid fa-globe"></i> Vue globale (tous les employés)</h2>

            <div class="rapport-cards">
                <div class="rapport-card">
                    <div class="valeur"><?= $totalConversations ?></div>
                    <div class="label">Conversations</div>
                </div>
                <div class="rapport-card">
                    <div class="valeur"><?= $statsGlobales['total'] ?></div>
                    <div class="label">Messages envoyés</div>
                </div>
                <div class="rapport-card">
                    <div class="valeur"><?= count($statsParEmploye) ?></div>
                    <div class="label">Employés actifs</div>
                </div>
            </div>

            <?php if ($statsGlobales['total'] > 0): ?>
                <h3>Répartition par type d'action</h3>
                <?php foreach ($statsGlobales['types'] as $t): ?>
                    <div class="barre-pct">
                        <div class="nom"><?= htmlspecialchars($t['label']) ?></div>
                        <div class="fond"><div class="rempli" style="width:<?= $t['pct'] ?>%"></div></div>
                        <div class="pct"><?= $t['pct'] ?>%</div>
                    </div>
                <?php endforeach; ?>

                <h3 style="margin-top:20px;">Répartition par module</h3>
                <?php foreach ($statsGlobales['modules'] as $m): ?>
                    <div class="barre-pct">
                        <div class="nom"><?= htmlspecialchars($m['label']) ?></div>
                        <div class="fond"><div class="rempli" style="width:<?= $m['pct'] ?>%"></div></div>
                        <div class="pct"><?= $m['pct'] ?>%</div>
                    </div>
                <?php endforeach; ?>

                <?php if (count($statsGlobales['topQuestions']) > 0): ?>
                    <h3 style="margin-top:20px;">Questions les plus fréquentes</h3>
                    <ul class="top-questions">
                        <?php foreach ($statsGlobales['topQuestions'] as $q): ?>
                            <li>
                                <span><?= htmlspecialchars($q['texte']) ?></span>
                                <span class="badge-count"><?= $q['count'] ?>x</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <button class="btn-ia" id="btnIaGlobal" data-scope="global" style="margin-top: 15px;">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> Générer une analyse IA
                </button>
                <div class="analyse-ia-resultat" id="iaResultGlobal" style="display:none;"></div>
            <?php else: ?>
                <p class="vide">Aucune activité enregistrée pour le moment.</p>
            <?php endif; ?>
        </section>

        <!-- ===================== DÉTAIL PAR EMPLOYÉ (super_admin) ===================== -->
        <section class="rapport-section">
            <h2><i class="fa-solid fa-users"></i> Détail par employé</h2>

            <?php if (count($statsParEmploye) === 0): ?>
                <p class="vide">Aucun employé n'a encore utilisé le chatbot.</p>
            <?php endif; ?>

            <?php foreach ($statsParEmploye as $idx => $emp): ?>
                <div class="employe-block">
                    <div class="employe-header" onclick="document.getElementById('empDetail<?= $idx ?>').classList.toggle('open')">
                        <div>
                            <span class="employe-nom"><?= htmlspecialchars($emp['username']) ?></span>
                            <span class="employe-role"><?= htmlspecialchars($emp['role']) ?></span>
                        </div>
                        <div style="font-size:13px;color:#8a94a6;">
                            <?= $emp['stats']['total'] ?> message(s) · <?= $emp['conversations'] ?> conversation(s)
                            <i class="fa-solid fa-chevron-down" style="margin-left:8px;"></i>
                        </div>
                    </div>
                    <div class="employe-detail" id="empDetail<?= $idx ?>">
                        <?php foreach ($emp['stats']['types'] as $t): ?>
                            <div class="barre-pct">
                                <div class="nom"><?= htmlspecialchars($t['label']) ?></div>
                                <div class="fond"><div class="rempli" style="width:<?= $t['pct'] ?>%"></div></div>
                                <div class="pct"><?= $t['pct'] ?>%</div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($emp['stats']['topQuestions']) > 0): ?>
                            <ul class="top-questions" style="margin-top:10px;">
                                <?php foreach ($emp['stats']['topQuestions'] as $q): ?>
                                    <li>
                                        <span><?= htmlspecialchars($q['texte']) ?></span>
                                        <span class="badge-count"><?= $q['count'] ?>x</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <!-- ===================== MON ACTIVITÉ (tout le monde) ===================== -->
        <section class="rapport-section">
            <h2><i class="fa-solid fa-user"></i> Mon activité (privée)</h2>
            <p style="font-size:13px;color:#8a94a6;margin-top:-8px;">Ces statistiques ne concernent que vos propres conversations avec le chatbot.</p>

            <div class="rapport-cards">
                <div class="rapport-card">
                    <div class="valeur"><?= $mesConversations ?></div>
                    <div class="label">Conversations</div>
                </div>
                <div class="rapport-card">
                    <div class="valeur"><?= $mesStats['total'] ?></div>
                    <div class="label">Messages envoyés</div>
                </div>
            </div>

            <?php if ($mesStats['total'] > 0): ?>
                <h3>Répartition par type d'action</h3>
                <?php foreach ($mesStats['types'] as $t): ?>
                    <div class="barre-pct">
                        <div class="nom"><?= htmlspecialchars($t['label']) ?></div>
                        <div class="fond"><div class="rempli" style="width:<?= $t['pct'] ?>%"></div></div>
                        <div class="pct"><?= $t['pct'] ?>%</div>
                    </div>
                <?php endforeach; ?>

                <h3 style="margin-top:20px;">Répartition par module</h3>
                <?php foreach ($mesStats['modules'] as $m): ?>
                    <div class="barre-pct">
                        <div class="nom"><?= htmlspecialchars($m['label']) ?></div>
                        <div class="fond"><div class="rempli" style="width:<?= $m['pct'] ?>%"></div></div>
                        <div class="pct"><?= $m['pct'] ?>%</div>
                    </div>
                <?php endforeach; ?>

                <?php if (count($mesStats['topQuestions']) > 0): ?>
                    <h3 style="margin-top:20px;">Mes questions les plus répétées</h3>
                    <ul class="top-questions">
                        <?php foreach ($mesStats['topQuestions'] as $q): ?>
                            <li>
                                <span><?= htmlspecialchars($q['texte']) ?></span>
                                <span class="badge-count"><?= $q['count'] ?>x</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <button class="btn-ia" id="btnIaPerso" data-scope="perso" style="margin-top: 15px;">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> Générer une analyse IA
                </button>
                <div class="analyse-ia-resultat" id="iaResultPerso" style="display:none;"></div>
            <?php else: ?>
                <p class="vide">Vous n'avez pas encore utilisé le chatbot.</p>
            <?php endif; ?>
        </section>
    </main>

    <script>
        // Colorer le lien actif dans la sidebar
        const activeLink = document.querySelector('.sidebar-nav a[href*="rapport_chatbot.php"]');
        if (activeLink) {
            activeLink.classList.add('active');
        }

        function genererAnalyseIA(scope, btnId, resultId) {
            const btn = document.getElementById(btnId);
            const result = document.getElementById(resultId);
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Génération en cours...';
            result.style.display = 'block';
            result.textContent = '';

            fetch('../../../api/rapport_ia.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ scope: scope })
            })
            .then(res => res.json())
            .then(data => {
                result.textContent = data.analyse || "Impossible de générer l'analyse.";
            })
            .catch(() => {
                result.textContent = "Erreur de connexion avec le serveur.";
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-wand-magic-sparkles"></i> Régénérer l\'analyse IA';
            });
        }

        const btnGlobal = document.getElementById('btnIaGlobal');
        if (btnGlobal) btnGlobal.addEventListener('click', () => genererAnalyseIA('global', 'btnIaGlobal', 'iaResultGlobal'));

        const btnPerso = document.getElementById('btnIaPerso');
        if (btnPerso) btnPerso.addEventListener('click', () => genererAnalyseIA('perso', 'btnIaPerso', 'iaResultPerso'));
    </script>

    <?php include "../includes/chatbot_widget.php"; ?>
    <script src="../includes/chatbot_widget.js"></script>
</body>
</html>