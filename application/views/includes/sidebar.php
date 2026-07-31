<?php
// Sécurité : On lance la session uniquement si elle n'est pas déjà active sur la page hôte
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!-- Bouton hamburger flottant unique : visible sur mobile ou quand la sidebar est complètement masquée sur desktop -->
<button id="mobileSidebarToggle" class="mobile-sidebar-toggle" type="button" aria-label="Ouvrir le menu" aria-expanded="false">
    <i class="fa-solid fa-bars"></i>
</button>

<!-- Fond sombre affiché derrière la sidebar quand elle est ouverte -->
<div id="sidebarOverlay" class="sidebar-overlay"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <button id="sidebarToggle" class="sidebar-toggle-btn" type="button" aria-label="Réduire / agrandir le menu">
            <i class="fa-solid fa-bars"></i>
        </button>
        <i class="fa-solid fa-cubes"></i>
        <span class="logo-text">PalmFox</span>
    </div>
    <nav class="sidebar-nav">
        <a href="../dashboard/dashboard.php" class="nav-item">
            <i class="fa-solid fa-chart-pie"></i> <span>Dashboard</span>
        </a>
        <a href="../client/clients.php" class="nav-item">
            <i class="fa-solid fa-users"></i> <span>Clients</span>
        </a>
        <a href="../produit/produits.php" class="nav-item">
            <i class="fa-solid fa-box-open"></i> <span>Produits</span>
        </a>
        <a href="../commande/commande.php" class="nav-item">
            <i class="fa-solid fa-cart-shopping"></i> <span>Commandes</span>
        </a>
        <a href="../livraison/livraisons.php" class="nav-item">
            <i class="fa-solid fa-truck"></i> <span>Livraisons</span>
        </a>
        <a href="../rapport_chatbot/rapport_chatbot.php" class="nav-item">
            <i class="fa-solid fa-chart-line"></i> <span>Rapport Chatbot</span>
        </a>

        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'super_admin'): ?>
            <a href="../gestion_acces/gestion_acces.php" class="nav-item">
                <i class="fa-solid fa-user-lock"></i> <span>Gestion Accès</span>
            </a>
        <?php endif; ?>

        <a href="../logout.php" class="nav-item nav-logout">
            <i class="fa-solid fa-right-from-bracket"></i> <span>Déconnexion</span>
        </a>
    </nav>
</aside>

<script>
(function() {
    const sidebar         = document.getElementById("sidebar");
    const toggleBtn       = document.getElementById("sidebarToggle");
    const mobileToggleBtn = document.getElementById("mobileSidebarToggle");
    const overlay         = document.getElementById("sidebarOverlay");

    const isMobile = () => window.matchMedia("(max-width: 768px)").matches;

    // Restauration de l'état réduit sur desktop
    if (!isMobile() && localStorage.getItem("sidebarCollapsed") === "true") {
        sidebar.classList.add("collapsed");
    }

    function openMobileSidebar() {
        sidebar.classList.add("mobile-open");
        overlay.classList.add("active");
        mobileToggleBtn.setAttribute("aria-expanded", "true");
        document.body.style.overflow = "hidden";
    }

    function closeMobileSidebar() {
        sidebar.classList.remove("mobile-open");
        overlay.classList.remove("active");
        mobileToggleBtn.setAttribute("aria-expanded", "false");
        document.body.style.overflow = "";
    }

    mobileToggleBtn.addEventListener("click", function() {
        if (sidebar.classList.contains("mobile-open")) {
            closeMobileSidebar();
        } else {
            openMobileSidebar();
        }
    });

    overlay.addEventListener("click", closeMobileSidebar);

    toggleBtn.addEventListener("click", function() {
        if (isMobile()) {
            closeMobileSidebar();
            return;
        }
        sidebar.classList.toggle("collapsed");
        localStorage.setItem("sidebarCollapsed", sidebar.classList.contains("collapsed"));
    });

    sidebar.querySelectorAll(".nav-item").forEach(function(link) {
        link.addEventListener("click", function() {
            if (isMobile()) closeMobileSidebar();
        });
    });

    document.addEventListener("keydown", function(e) {
        if (e.key === "Escape") closeMobileSidebar();
    });
})();
</script>