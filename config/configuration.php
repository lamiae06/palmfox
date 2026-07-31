<?php
// =========================================================================
// 0. AFFICHAGE DES ERREURS PHP (DEBUG) - A DESACTIVER EN PRODUCTION
// =========================================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Tableau global qui va stocker tous les messages d'erreurs/debug
$GLOBALS['app_debug_log'] = [];

/**
 * Ajoute une ligne au journal de debug affichable en frontend.
 */
function logDebug($message, $type = 'info') {
    $GLOBALS['app_debug_log'][] = [
        'type'    => $type, // info | success | error
        'message' => $message,
        'time'    => date('H:i:s'),
    ];
}

/**
 * Chargeur minimaliste de fichier .env (Évite d'installer des dépendances lourdes)
 */
function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        logDebug("Fichier .env introuvable à l'emplacement : " . realpath($filePath), 'error');
        return false;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignorer les commentaires
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Séparer la clé de la valeur au premier signe '='
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Supprimer d'éventuels guillemets autour de la valeur
            $value = trim($value, '"\'');

            // Enregistrer dans l'environnement PHP
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
    return true;
}

// Charger le fichier .env depuis le répertoire racine
$envPath = dirname(__DIR__) . '/.env';
loadEnv($envPath); 

/**
 * Affiche joliment toutes les erreurs/infos accumulées dans $GLOBALS['app_debug_log'].
 */
function afficherErreurs() {
    if (empty($GLOBALS['app_debug_log'])) {
        return;
    }

    echo '<div style="font-family:monospace;max-width:900px;margin:15px auto;padding:15px;
                border:1px solid #ccc;border-radius:8px;background:#fafafa;">';
    echo '<strong style="display:block;margin-bottom:8px;">🔍 Journal de debug</strong>';

    foreach ($GLOBALS['app_debug_log'] as $entry) {
        $color = match ($entry['type']) {
            'error'   => '#c0392b',
            'success' => '#27ae60',
            default   => '#2c3e50',
        };
        $bg = match ($entry['type']) {
            'error'   => '#fdecea',
            'success' => '#eafaf1',
            default   => '#eef2f7',
        };

        echo '<div style="padding:6px 10px;margin-bottom:4px;border-radius:5px;
                    background:' . $bg . ';color:' . $color . ';font-size:13px;">';
        echo '[' . htmlspecialchars($entry['time']) . '] ' . htmlspecialchars($entry['message']);
        echo '</div>';
    }

    echo '</div>';
}

// =========================================================================
// 1. CONFIGURATION DE LA BASE DE DONNÉES (ADAPTÉE .ENV)
// =========================================================================
$host     = getenv('DB_HOST') ?: "localhost";
$user     = getenv('DB_USER') ?: "root";
$password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : "";
$database = getenv('DB_DATABASE') ?: "palmfox";

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    logDebug("Erreur de connexion à la base de données : " . mysqli_connect_error(), 'error');
    die("Erreur de connexion : " . mysqli_connect_error());
} else {
    logDebug("Connexion à la base de données réussie.", 'success');
}
mysqli_set_charset($conn, "utf8");


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =========================================================================
// 2. FONCTIONS DE SÉCURITÉ ET ENVOI DE MAIL (ADAPTÉES .ENV)
// =========================================================================
define('ENCRYPTION_KEY', getenv('ENCRYPTION_KEY') ?: 'MaCleSecreteParDefaut!');
define('ENCRYPTION_METHOD', getenv('ENCRYPTION_METHOD') ?: 'AES-256-CBC');

/**
 * Chiffre l'e-mail pour pouvoir le passer de manière sécurisée dans l'URL.
 */
function crypterEmail($email) {
    $iv_length = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    $iv = random_bytes($iv_length);
    $encrypted = openssl_encrypt($email, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
    return bin2hex($iv) . '::' . base64_encode($encrypted);
}

/**
 * Déchiffre la clé reçue depuis l'URL pour retrouver l'e-mail d'origine.
 */
function decrypterEmail($cle_cryptee) {
    $parts = explode('::', $cle_cryptee);
    if (count($parts) !== 2) {
        logDebug("Déchiffrement échoué : format de clé invalide.", 'error');
        return false;
    }
    $iv = hex2bin($parts[0]);
    $encrypted = base64_decode($parts[1]);
    $result = openssl_decrypt($encrypted, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);

    if ($result === false) {
        logDebug("Déchiffrement échoué : " . (openssl_error_string() ?: 'raison inconnue'), 'error');
    }

    return $result;
}

/**
 * Envoi direct de mail via Socket SMTP (Gmail) sans bibliothèque externe (ADAPTÉ .ENV).
 */
function envoyerEmailSMTP($destinataire, $sujet, $messageHTML, $nomExpediteur = "PalmFox Security") {
    $smtp_serveur = "ssl://smtp.gmail.com";
    $port = 465;

    // Récupération sécurisée depuis le fichier .env
    $username = getenv('SMTP_USER') ?: "votre_email_par_defaut@gmail.com";
    $password = getenv('SMTP_PASS') ?: "votre_mot_de_passe_par_defaut";

    logDebug("Tentative de connexion à $smtp_serveur:$port ...");

    $socket = @stream_socket_client("$smtp_serveur:$port", $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        logDebug("Échec de connexion au serveur SMTP. Code $errno : $errstr", 'error');
        return false;
    }
    logDebug("Connexion au serveur SMTP établie.", 'success');

    // Lit une réponse SMTP, et journalise le code/texte reçu.
    $lireReponse = function ($socket, $etape) {
        $reponse = "";
        while ($ligne = fgets($socket, 512)) {
            $reponse .= $ligne;
            if (substr($ligne, 3, 1) === " ") {
                break;
            }
        }
        $code = substr(trim($reponse), 0, 3);
        logDebug("[$etape] Réponse serveur ($code) : " . trim($reponse));
        return $reponse;
    };

    $reponse = $lireReponse($socket, "CONNEXION");
    if (substr($reponse, 0, 3) !== "220") {
        logDebug("Le serveur n'a pas répondu 220 à la connexion.", 'error');
        fclose($socket);
        return false;
    }

    fwrite($socket, "EHLO localhost\r\n");
    $lireReponse($socket, "EHLO");

    fwrite($socket, "AUTH LOGIN\r\n");
    $reponse = $lireReponse($socket, "AUTH LOGIN");
    if (substr($reponse, 0, 3) !== "334") {
        logDebug("Le serveur n'accepte pas AUTH LOGIN.", 'error');
        fclose($socket);
        return false;
    }

    fwrite($socket, base64_encode($username) . "\r\n");
    $reponse = $lireReponse($socket, "USERNAME");
    if (substr($reponse, 0, 3) !== "334") {
        logDebug("Nom d'utilisateur SMTP refusé.", 'error');
        fclose($socket);
        return false;
    }

    fwrite($socket, base64_encode($password) . "\r\n");
    $auth_res = $lireReponse($socket, "PASSWORD");

    if (strpos($auth_res, "235") === false) {
        logDebug("Authentification SMTP échouée. Vérifie l'identifiant et le mot de passe d'application Gmail.", 'error');
        fclose($socket);
        return false;
    }
    logDebug("Authentification SMTP réussie.", 'success');

    fwrite($socket, "MAIL FROM: <$username>\r\n");
    $reponse = $lireReponse($socket, "MAIL FROM");
    if (substr($reponse, 0, 3) !== "250") {
        logDebug("L'expéditeur a été refusé par le serveur.", 'error');
        fclose($socket);
        return false;
    }

    fwrite($socket, "RCPT TO: <$destinataire>\r\n");
    $reponse = $lireReponse($socket, "RCPT TO");
    if (substr($reponse, 0, 3) !== "250" && substr($reponse, 0, 3) !== "251") {
        logDebug("Le destinataire '$destinataire' a été refusé par le serveur.", 'error');
        fclose($socket);
        return false;
    }

    fwrite($socket, "DATA\r\n");
    $reponse = $lireReponse($socket, "DATA");
    if (substr($reponse, 0, 3) !== "354") {
        logDebug("Le serveur n'accepte pas le contenu (DATA).", 'error');
        fclose($socket);
        return false;
    }

    $sujet_encode = "=?UTF-8?B?" . base64_encode($sujet) . "?=";
    $nom_exp_encode = "=?UTF-8?B?" . base64_encode($nomExpediteur) . "?=";

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: $nom_exp_encode <$username>\r\n";
    $headers .= "To: <$destinataire>\r\n";
    $headers .= "Subject: $sujet_encode\r\n";
    $headers .= "Date: " . date('r') . "\r\n";

    fwrite($socket, $headers . "\r\n" . $messageHTML . "\r\n.\r\n");
    $reponse = $lireReponse($socket, "ENVOI FINAL");

    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    if (substr($reponse, 0, 3) !== "250") {
        logDebug("Le serveur a refusé le message final. Le mail n'a probablement pas été envoyé.", 'error');
        return false;
    }

    logDebug("Email envoyé avec succès à $destinataire.", 'success');
    return true;
}


// =========================================================================
// 3. VÉRIFICATION AUTOMATIQUE DES ACCÈS ET SÉCURITÉ
// =========================================================================
$nom_page_actuelle = basename($_SERVER['PHP_SELF'], '.php');
$pages_protegees = ['dashboard', 'commande', 'livraisons', 'produits', 'clients', 'produit', 'client', 'livraison', 'chatbot'];

if (in_array($nom_page_actuelle, $pages_protegees)) {

    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login/login.php");
        exit;
    }

    if ($_SESSION['user_role'] !== 'super_admin') {

        $id_user_connecte = $_SESSION['user_id'];
        $nom_db_page = rtrim($nom_page_actuelle, 's');

        if ($nom_page_actuelle !== 'dashboard' && $nom_page_actuelle !== 'chatbot') {
            $query_perm = "SELECT acces_autorise FROM permissions WHERE id_user = ? AND nom_page = ?";
            $stmt_perm = mysqli_prepare($conn, $query_perm);

            if (!$stmt_perm) {
                logDebug("Erreur de préparation de la requête de permission : " . mysqli_error($conn), 'error');
            } else {
                mysqli_stmt_bind_param($stmt_perm, "is", $id_user_connecte, $nom_db_page);
                mysqli_stmt_execute($stmt_perm);
                $result_perm = mysqli_stmt_get_result($stmt_perm);
                $perm = mysqli_fetch_assoc($result_perm);

                if (!$perm || $perm['acces_autorise'] != 1) {
                    header("Location: ../dashboard/dashboard.php?error=access_denied");
                    exit;
                }
            }
        }

        $query_all_perms = "SELECT nom_page, acces_autorise FROM permissions WHERE id_user = ?";
        $stmt_all = mysqli_prepare($conn, $query_all_perms);

        if (!$stmt_all) {
            logDebug("Erreur de préparation de la requête des permissions globales : " . mysqli_error($conn), 'error');
        } else {
            mysqli_stmt_bind_param($stmt_all, "i", $id_user_connecte);
            mysqli_stmt_execute($stmt_all);
            $res_all = mysqli_stmt_get_result($stmt_all);

            $pages_bloquees = [];
            while ($row = mysqli_fetch_assoc($res_all)) {
                if ($row['acces_autorise'] == 0) {
                    $pages_bloquees[] = $row['nom_page'];
                }
            }

            if (!empty($pages_bloquees)) {
                echo "<style>";
                foreach ($pages_bloquees as $page) {
                    echo ".sidebar-nav a[href*='{$page}'] { display: none !important; }";
                }
                echo "</style>";
            }
        }
    }
}
?>