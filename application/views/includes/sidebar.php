<?php
// Sécurité : On lance la session uniquement si elle n'est pas déjà active sur la page hôte
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<aside class="sidebar">
    <div class="sidebar-logo">
        <button id="sidebarToggle" class="sidebar-toggle-btn" type="button">
            <i class="fa-solid fa-bars"></i>
        </button>
        <i class="fa-solid fa-cubes"></i>
        <span>PalmFox</span>
    </div>
    <nav class="sidebar-nav">
        <a href="../dashboard/dashboard.php" class="nav-item">
            <i class="fa-solid fa-chart-pie"></i> Dashboard
        </a>
        <a href="../client/clients.php" class="nav-item">
            <i class="fa-solid fa-users"></i> Clients
        </a>
        <a href="../produit/produits.php" class="nav-item">
            <i class="fa-solid fa-box-open"></i> Produits
        </a>
        <a href="../commande/commande.php" class="nav-item">
            <i class="fa-solid fa-cart-shopping"></i> Commandes
        </a>
        <a href="../livraison/livraisons.php" class="nav-item">
            <i class="fa-solid fa-truck"></i> Livraisons
        </a>

        <a href="../rapport_chatbot/rapport_chatbot.php" class="nav-item">
            <i class="fa-solid fa-chart-line"></i> Rapport Chatbot
        </a>

        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'super_admin'): ?>
            <a href="../gestion_acces/gestion_acces.php" class="nav-item">
                <i class="fa-solid fa-user-lock"></i> Gestion Accès
            </a>
        <?php endif; ?>



        <a href="../logout.php" class="nav-item nav-logout">
            <i class="fa-solid fa-right-from-bracket"></i> Déconnexion
        </a>
    </nav>
</aside>

<script>
(function() {
    const sidebar = document.querySelector(".sidebar");
    const toggleBtn = document.getElementById("sidebarToggle");

    if (localStorage.getItem("sidebarCollapsed") === "true") {
        sidebar.classList.add("collapsed");
    }

    toggleBtn.addEventListener("click", function() {
        sidebar.classList.toggle("collapsed");
        localStorage.setItem("sidebarCollapsed", sidebar.classList.contains("collapsed"));
    });
})();
</script>
