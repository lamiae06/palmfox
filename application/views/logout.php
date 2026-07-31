<?php
/**
 * Script de déconnexion
 * Ce fichier permet de terminer la session utilisateur en toute sécurité.
 */

// 1. Démarrer la session pour pouvoir y accéder
session_start();

// 2. Supprimer toutes les variables de session
$_SESSION = array();

// 3. Effacer le cookie de session si nécessaire (sécurité supplémentaire)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Détruire la session côté serveur
session_destroy();

// 5. Rediriger l'utilisateur vers la page de connexion ou l'accueil
header("Location: index.php");
exit();
?>