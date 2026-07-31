const activeLink = document.querySelector('.sidebar-nav a[href*="dashboard.php"]');
    if (activeLink) {
        activeLink.classList.add('active');
    }
document.addEventListener("DOMContentLoaded", () => {
    // DATE : On garde cette ligne simple pour afficher dynamiquement la date du jour
    const today = new Date();
    const dateElement = document.getElementById("dashboardDate");
    
    if (dateElement) {
        dateElement.textContent = today.toLocaleDateString("fr-FR", { 
            weekday: "long", 
            year: "numeric", 
            month: "long", 
            day: "numeric" 
        });
    }
});