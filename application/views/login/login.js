/*login.js — page de connexion (front-end, sans backend)
 À remplacer plus tard par un vrai appel fetch() vers login.php
qui vérifie les identifiants en base de données.*/

document.addEventListener("DOMContentLoaded", () => {

    const form = document.getElementById("loginForm");
    const errorEl = document.getElementById("loginError");
    const togglePassword = document.getElementById("toggleLoginPassword");
    const passwordInput = document.getElementById("loginPassword");

    // AFFICHER / MASQUER LE MOT DE PASSE 
    togglePassword.addEventListener("click", () => {
        const isHidden = passwordInput.type === "password";
        passwordInput.type = isHidden ? "text" : "password";
        togglePassword.querySelector("i").classList.toggle("fa-eye");
        togglePassword.querySelector("i").classList.toggle("fa-eye-slash");
    });

    //SOUMISSION DU FORMULAIRE
    form.addEventListener("submit", (e) => {
        e.preventDefault();
        errorEl.textContent = "";

        const user = document.getElementById("loginUser").value.trim();
        const password = passwordInput.value;

        if (!user || !password) {
            errorEl.textContent = "Merci de remplir tous les champs.";
            return;
        }

        // Ici : envoyer { user, password } au backend PHP (login.php)
        // pour vérifier les identifiants en base de données.
        // Pour l'instant, connexion simulée : redirection directe.
        window.location.href = "dashboard.php";
    });

});