<?php
session_start(); //[cite: 2]

// Correction du chemin d'inclusion de la configuration selon votre structure :
// Depuis 'application/views/login/process_login.php' :
// - ../ remonte à 'views'
// - ../../ remonte à 'application'
// - ../../../ remonte à la racine 'PALMFOX_STAGE'
require_once __DIR__ . "/../../../config/configuration.php"; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') { //[cite: 2]
    $email = trim($_POST['email']); //[cite: 2]
    $password = $_POST['password']; //[cite: 2]

    // Recherche de l'utilisateur par e-mail
    $query = "SELECT * FROM utilisateurs WHERE email = ?"; //[cite: 2]
    $stmt = mysqli_prepare($conn, $query); //[cite: 2]
    
    if ($stmt) { //[cite: 2]
        mysqli_stmt_bind_param($stmt, "s", $email); //[cite: 2]
        mysqli_stmt_execute($stmt); //[cite: 2]
        $result = mysqli_stmt_get_result($stmt); //[cite: 2]

        $user = mysqli_fetch_assoc($result);

        // ==== DEBUG TEMPORAIRE - A SUPPRIMER APRES DIAGNOSTIC ====
        // Utilisation : login.php avec ?debug=1 dans le formulaire, ou ajoutez &debug=1 a l'action du form
        if (isset($_GET['debug']) || isset($_POST['debug'])) {
            if (!$user) {
                die("DEBUG: Aucun utilisateur trouvé pour l'email '" . htmlspecialchars($email) . "'. Vérifiez l'email en base (espace, casse, faute de frappe).");
            }
            $hash = $user['password'];
            die("DEBUG: Utilisateur trouvé -> " . htmlspecialchars($user['email']) .
                " | Statut: " . htmlspecialchars($user['statut'] ?? 'non défini') .
                " | Hash en base: " . htmlspecialchars($hash) .
                " | password_verify() = " . (password_verify($password, $hash) ? 'TRUE' : 'FALSE') .
                " | Format hash bcrypt valide : " . ((str_starts_with($hash, '$2y$') || str_starts_with($hash, '$2b$') || str_starts_with($hash, '$2a$')) ? 'OUI' : 'NON -> PROBLEME: le mot de passe en base n\'est pas un hash password_hash() valide (probablement du texte en clair ou un autre algo comme MD5/SHA1)'));
        }
        // ==== FIN DEBUG ====

        if ($user) { //[cite: 2]
            // Vérification si le compte n'est pas bloqué
            if (isset($user['statut']) && $user['statut'] === 'bloque') { //[cite: 2]
                header("Location: login.php?error=blocked"); //[cite: 2]
                exit; //[cite: 2]
            }

            // Utilisation de password_verify pour valider le hash
            if (password_verify($password, $user['password'])) { //[cite: 2]
                // Enregistrement des données en session
                $_SESSION['user_id'] = $user['id_user']; //[cite: 2]
                $_SESSION['user_nom'] = $user['username']; //[cite: 2]
                $_SESSION['user_role'] = $user['role']; //[cite: 2]

                // Redirection selon le rôle vers les bons dossiers frères de 'login'
                if ($user['role'] === 'super_admin') { //[cite: 2]
                    header("Location: ../gestion_acces/gestion_acces.php"); //[cite: 2]
                    exit(); //[cite: 2]
                } else {
                    header("Location: ../dashboard/dashboard.php"); //[cite: 2]
                    exit(); //[cite: 2]
                }
            }
        }
        
        // Si l'utilisateur n'existe pas ou si le mot de passe est faux
        header("Location: login.php?error=1"); //[cite: 2]
        exit; //[cite: 2]
    } else {
        die("Erreur SQL : " . mysqli_error($conn)); //[cite: 2]
    }
} else {
    header("Location: login.php"); //[cite: 2]
    exit; //[cite: 2]
}
?>