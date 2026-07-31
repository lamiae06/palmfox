<?php
// Démarrage de la session pour maintenir l'utilisateur connecté
session_start(); //[cite: 1]

// Si l'utilisateur est déjà connecté, on le redirige directement
if (isset($_SESSION['user_role'])) { //[cite: 1]
    if ($_SESSION['user_role'] === 'super_admin') { //[cite: 1]
        header("Location: ../gestion_acces/gestion_acces.php"); //[cite: 1]
        exit(); //[cite: 1]
    } else {
        header("Location: ../dashboard/dashboard.php"); //[cite: 1]
        exit(); //[cite: 1]
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PalmFox - Connexion</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="login.css">
</head>
<body>

    <div class="login-wrapper">
        <div class="login-panel">
            <div class="login-panel-inner">
                
                <!-- Retour à l'accueil à la racine de PALMFOX_STAGE -->
                <a href="../index.php" style="text-decoration: none; color: #9a6633; font-size: 13px; display: inline-flex; align-items: center; gap: 5px; margin-bottom: 20px;">
                    <i class="fa-solid fa-arrow-left"></i> Retour à l'accueil
                </a>

                <div class="login-logo">
                    <i class="fa-solid fa-cubes"></i>
                    <span>PalmFox</span>
                </div>

                <form action="process_login.php" method="POST">
                    
                    <div class="form-group">
                        <label>Adresse Email</label>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-envelope"></i>
                            <input type="email" name="email" placeholder="exemple@palmfox.com" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <label>Mot de passe</label>
                            <!-- Lien vers mot de passe oublié (dossier frère 'password') -->
                            <a href="../password/motdepassoublier.php" id="btnForgot" class="forgot-link">Mot de passe oublié ?</a>
                        </div>
                        <div class="input-with-icon">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" name="password" id="loginPassword" placeholder="••••••••" required>
                        </div>
                    </div>

                    <p class="form-error" id="loginError" style="color: #dc2626; font-size: 13px; margin-bottom: 15px; min-height: 16px;">
                        <?php 
                        if (isset($_GET['error'])) { //[cite: 1]
                            if ($_GET['error'] == 1) { //[cite: 1]
                                echo "Identifiants incorrects."; //[cite: 1]
                            } elseif ($_GET['error'] === 'blocked') { //[cite: 1]
                                echo "Ce compte a été bloqué. Veuillez contacter un administrateur."; //[cite: 1]
                            }
                        }
                        ?>
                    </p>

                    <button type="submit" class="btn-login">
                        Se connecter <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </form>
            </div>
        </div>

        <div class="welcome-panel">
            <div class="welcome-decor"></div>
            <div class="welcome-content">
                <i class="fa-solid fa-cubes welcome-icon"></i>
                <h2>Bienvenue sur PalmFox</h2>
                <p>Votre plateforme de gestion commerciale.</p>
            </div>
        </div>
    </div>

</body>
</html>