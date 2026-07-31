<?php
require_once __DIR__ . '/../../../config/configuration.php';

$erreur = "";
$succes = "";
$email_valide = null;
$cle = $_GET['cle'] ?? ($_POST['cle'] ?? '');

if (empty($cle)) {
    $erreur = "Lien invalide ou expiré.";
} else {
    $email_valide = decrypterEmail($cle);

    if (!$email_valide) {
        $erreur = "Lien invalide ou expiré.";
    } else {
        $query = "SELECT id_user FROM utilisateurs WHERE email = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $email_valide);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        if (!$user) {
            $erreur = "Lien invalide ou compte introuvable.";
            $email_valide = null;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $email_valide) {
    $nouveau_mdp  = $_POST['password'] ?? '';
    $confirmation = $_POST['password_confirm'] ?? '';

    if (strlen($nouveau_mdp) < 6) {
        $erreur = "Le mot de passe doit contenir au moins 6 caractères.";
    } elseif ($nouveau_mdp !== $confirmation) {
        $erreur = "Les deux mots de passe ne correspondent pas.";
    } else {
        $mdp_hache = password_hash($nouveau_mdp, PASSWORD_DEFAULT);

        $update = "UPDATE utilisateurs SET password = ? WHERE email = ?";
        $stmt_update = mysqli_prepare($conn, $update);
        // ENREGISTREMENT DU MOT DE PASSE HACHÉ
        mysqli_stmt_bind_param($stmt_update, "ss", $mdp_hache, $email_valide);

        if (mysqli_stmt_execute($stmt_update)) {
            $succes = "Votre mot de passe a été modifié avec succès.";
            $email_valide = null;
        } else {
            $erreur = "Une erreur est survenue. Veuillez réessayer.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Réinitialiser le mot de passe - PalmFox</title>
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

            <h1>Nouveau mot de passe</h1>
            <p class="login-subtitle">Choisissez un mot de passe sécurisé pour votre compte.</p>

            <?php if ($erreur): ?>
                <div class="form-error"><?= htmlspecialchars($erreur) ?></div>
            <?php endif; ?>

            <?php if ($succes): ?>
                <div class="form-error" style="color:#2e7d32;"><?= htmlspecialchars($succes) ?></div>
                <p style="text-align:center; margin-top:10px;">
                    <a class="login-forgot" href="../login/login.php">Se connecter →</a>
                </p>
            <?php endif; ?>

            <?php if ($email_valide && !$succes): ?>
                <form method="POST" action="">
                    <input type="hidden" name="cle" value="<?= htmlspecialchars($cle) ?>">

                    <div class="form-group">
                        <label for="password">Nouveau mot de passe</label>
                        <div class="input-with-icon password-field">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" id="password" name="password" placeholder="••••••••" required minlength="6">
                            <button type="button" class="toggle-eye" onclick="togglePassword('password','eye1')">
                                <i class="fa-solid fa-eye" id="eye1"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password_confirm">Confirmer le mot de passe</label>
                        <div class="input-with-icon password-field">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" id="password_confirm" name="password_confirm" placeholder="••••••••" required minlength="6">
                            <button type="button" class="toggle-eye" onclick="togglePassword('password_confirm','eye2')">
                                <i class="fa-solid fa-eye" id="eye2"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-login">
                        <i class="fa-solid fa-check"></i>
                        Réinitialiser le mot de passe
                    </button>
                </form>
            <?php endif; ?>

            <?php if (!$email_valide && !$succes): ?>
                <p style="text-align:center; margin-top:10px;">
                    <a class="login-forgot" href="motdepassoublier.php">← Demander un nouveau lien</a>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="welcome-panel">
        <div class="welcome-decor"></div>
        <div class="welcome-content">
            <div class="welcome-icon"><i class="fa-solid fa-shield-halved"></i></div>
            <h2>Sécurisez votre compte</h2>
            <p>Choisissez un mot de passe unique d'au moins 6 caractères pour protéger votre accès.</p>
            <div class="welcome-footer">
                <span>Besoin d'aide ?</span>
                <a href="mailto:admin@palmfox.com">admin@palmfox.com</a>
            </div>
        </div>
    </div>

</div>

<script>
function togglePassword(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>
</body>
</html>