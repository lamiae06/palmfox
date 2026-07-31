<?php
require_once __DIR__ . '/../../../config/configuration.php';

$message = "";
$erreur = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Veuillez entrer une adresse e-mail valide.";
        $erreur = true;
    } else {
        $query = "SELECT id_user, username FROM utilisateurs WHERE email = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        // Message générique dans tous les cas : on ne révèle jamais si l'email existe
        $message = "Si cette adresse e-mail est associée à un compte, un lien de réinitialisation vient de vous être envoyé.";

        if ($user) {
            $cle_cryptee = crypterEmail($email);

            $protocole  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
            $base_url   = $protocole . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/');
            // CORRECTION ICI : Le lien pointe bien vers reset.php et non reset-password.php
            $lien_reset = $base_url . "/reset.php?cle=" . urlencode($cle_cryptee);

            $sujet = "Réinitialisation de votre mot de passe - PalmFox";
            $contenuHTML = "
                <div style='font-family: Arial, sans-serif; max-width:600px; margin:auto;'>
                    <h2>Bonjour " . htmlspecialchars($user['username']) . ",</h2>
                    <p>Vous avez demandé la réinitialisation de votre mot de passe sur PalmFox.</p>
                    <p style='text-align:center; margin:25px 0;'>
                        <a href='" . $lien_reset . "' style='background:#8a5a30;color:#fff;padding:12px 24px;text-decoration:none;border-radius:8px;display:inline-block;'>Réinitialiser mon mot de passe</a>
                    </p>
                    <p>Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet e-mail.</p>
                    <p>— L'équipe PalmFox</p>
                </div>
            ";

            envoyerEmailSMTP($email, $sujet, $contenuHTML);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mot de passe oublié - PalmFox</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="password.css">
</head>
<body>
<div class="login-wrapper">

    <div class="login-panel">
        <div class="login-panel-inner">
            <div class="login-logo">
                <i class="fa-solid fa-leaf"></i>
                PalmFox
            </div>

            <h1>Mot de passe oublié</h1>
            <p class="login-subtitle">Entrez votre e-mail, nous vous enverrons un lien de réinitialisation.</p>

            <?php if ($message): ?>
                <div class="form-error" style="<?= $erreur ? '' : 'color:#2e7d32;' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Adresse e-mail</label>
                    <div class="input-with-icon">
                        <i class="fa-solid fa-envelope"></i>
                        <input type="email" id="email" name="email" placeholder="vous@exemple.com" required>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fa-solid fa-paper-plane"></i>
                    Envoyer le lien de réinitialisation
                </button>
            </form>

            <div class="login-divider"><span>ou</span></div>
            <p style="text-align:center;">
                <a class="login-forgot" href="../login/login.php">← Retour à la connexion</a>
            </p>
        </div>
    </div>

    <div class="welcome-panel">
        <div class="welcome-decor"></div>
        <div class="welcome-content">
            <div class="welcome-icon"><i class="fa-solid fa-key"></i></div>
            <h2>Ça arrive à tout le monde</h2>
            <p>Pas d'inquiétude, la réinitialisation de votre mot de passe ne prend qu'une minute.</p>
            <div class="welcome-footer">
                <span>Besoin d'aide ?</span>
                <a href="mailto:admin@palmfox.com">admin@palmfox.com</a>
            </div>
        </div>
    </div>

</div>
</body>
</html>