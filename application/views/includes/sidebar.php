<?php
// Sécurité : On lance la session uniquement si elle n'est pas déjà active sur la page hôte
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!-- Bouton hamburger flottant : toujours visible sur mobile, même quand la sidebar est fermée -->
<button id="mobileSidebarToggle" class="mobile-sidebar-toggle" type="button" aria-label="Ouvrir le menu" aria-expanded="false">
    <i class="fa-solid fa-bars"></i>
</button>

<!-- Fond sombre affiché derrière la sidebar quand elle est ouverte sur mobile -->
<div id="sidebarOverlay" class="sidebar-overlay"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <button id="sidebarToggle" class="sidebar-toggle-btn" type="button" aria-label="Réduire / agrandir le menu">
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
    const sidebar          = document.getElementById("sidebar");
    const toggleBtn        = document.getElementById("sidebarToggle");
    const mobileToggleBtn  = document.getElementById("mobileSidebarToggle");
    const overlay          = document.getElementById("sidebarOverlay");

    const isMobile = () => window.matchMedia("(max-width: 768px)").matches;

    // --- Etat "réduit" (desktop uniquement), mémorisé entre les visites ---
    if (!isMobile() && localStorage.getItem("sidebarCollapsed") === "true") {
        sidebar.classList.add("collapsed");
    }

    function openMobileSidebar() {
        sidebar.classList.add("mobile-open");
        overlay.classList.add("active");
        mobileToggleBtn.setAttribute("aria-expanded", "true");
        document.body.style.overflow = "hidden"; // évite le scroll derrière l'overlay
    }

    function closeMobileSidebar() {
        sidebar.classList.remove("mobile-open");
        overlay.classList.remove("active");
        mobileToggleBtn.setAttribute("aria-expanded", "false");
        document.body.style.overflow = "";
    }

    // Bouton hamburger flottant (mobile) : ouvre/ferme la sidebar en overlay
    mobileToggleBtn.addEventListener("click", function() {
        if (sidebar.classList.contains("mobile-open")) {
            closeMobileSidebar();
        } else {
            openMobileSidebar();
        }
    });

    // Clic sur le fond sombre = fermeture
    overlay.addEventListener("click", closeMobileSidebar);

    // Bouton hamburger dans le logo : réduit/agrandit sur desktop, ferme sur mobile
    toggleBtn.addEventListener("click", function() {
        if (isMobile()) {
            closeMobileSidebar();
            return;
        }
        sidebar.classList.toggle("collapsed");
        localStorage.setItem("sidebarCollapsed", sidebar.classList.contains("collapsed"));
    });

    // Ferme automatiquement le menu mobile après le clic sur un lien
    sidebar.querySelectorAll(".nav-item").forEach(function(link) {
        link.addEventListener("click", function() {
            if (isMobile()) closeMobileSidebar();
        });
    });

    // Touche Échap = fermeture sur mobile
    document.addEventListener("keydown", function(e) {
        if (e.key === "Escape") closeMobileSidebar();
    });

    // Remet tout à zéro si on redimensionne la fenêtre (évite un état "coincé")
    window.addEventListener("resize", function() {
        if (!isMobile()) {
            closeMobileSidebar();
        }
    });
})();
</script>