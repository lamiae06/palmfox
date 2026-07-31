document.addEventListener("DOMContentLoaded", function() {
    const API_BASE = "application/controllers";

    const chatbotBubble = document.getElementById("chatbotBubble");
    const chatbotWindow = document.getElementById("chatbotWindow");
    const closeChatbotWidget = document.getElementById("closeChatbotWidget");

    if (chatbotBubble && chatbotWindow) {
        chatbotBubble.addEventListener("click", () => chatbotWindow.classList.toggle("hidden"));
    }
    if (closeChatbotWidget && chatbotWindow) {
        closeChatbotWidget.addEventListener("click", () => chatbotWindow.classList.add("hidden"));
    }

    const statsBtn = document.getElementById("openStatsBtn");
    const statsOverlay = document.getElementById("statsOverlay");
    const statsCloseBtn = document.getElementById("closeStatsModal");
    const statsBody = document.getElementById("statsModalBody");

    const STATS_COULEURS_MODULE = {
        client: '#4c8c8c', commande: '#9a6633', produit: '#6f9e5c',
        livraison: '#7d6bb0', employe: '#b0524c', general: '#b7a690'
    };
    const STATS_COULEURS_TYPE = {
        consultation: '#4c8c9c', ajout: '#6f9e5c', modification: '#9a6633',
        suppression: '#b0524c', autre: '#b7a690', hors_sujet: '#d8cdb9'
    };
    const STATS_LABELS_TYPE = {
        consultation: 'Consultations', ajout: 'Ajouts', modification: 'Modifications',
        suppression: 'Suppressions', autre: 'Autres', hors_sujet: 'Hors sujet'
    };
    const STATS_LABELS_MODULE = {
        client: 'Clients', commande: 'Commandes', produit: 'Produits',
        livraison: 'Livraisons', employe: 'Employés', general: 'Général'
    };

    let statsChartTendance = null;
    let statsChartType = null;
    let statsChartModule = null;
    let chartJsLoadingPromise = null;

    const CHARTJS_SOURCES = [
        "https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js",
        "https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"
    ];

    function chargerScript(src) {
        return new Promise((resolve, reject) => {
            const script = document.createElement("script");
            script.src = src;
            script.onload = () => resolve();
            script.onerror = () => reject(new Error("Échec du chargement : " + src));
            document.head.appendChild(script);
        });
    }

    function chargerChartJs() {
        if (window.Chart) return Promise.resolve();
        if (chartJsLoadingPromise) return chartJsLoadingPromise;

        chartJsLoadingPromise = chargerScript(CHARTJS_SOURCES[0])
            .catch(() => chargerScript(CHARTJS_SOURCES[1]))
            .catch(() => {
                chartJsLoadingPromise = null;
                throw new Error("Impossible de charger Chart.js");
            });
        return chartJsLoadingPromise;
    }

    async function ouvrirStats() {
        if (!statsOverlay || !statsBody) return;
        statsOverlay.classList.add("open");
        statsBody.innerHTML = '<div class="stats-empty">Chargement des statistiques…</div>';

        try {
            await chargerChartJs();

            const res = await fetch(`${API_BASE}/chat.php`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ action: "stats" })
            });
            const data = await res.json();

            afficherStats(data);
        } catch (err) {
            console.error("Erreur chargement statistiques :", err);
            statsBody.innerHTML = '<div class="stats-empty">Impossible de charger les statistiques pour le moment.</div>';
        }
    }

    function fermerStats() {
        if (statsOverlay) statsOverlay.classList.remove("open");
    }

    function afficherStats(data) {
        if (!data || !data.total_messages) {
            statsBody.innerHTML = '<div class="stats-empty">Aucune conversation enregistrée sur les 30 derniers jours.</div>';
            return;
        }

        statsBody.innerHTML = `
            <div class="stats-cards">
                <div class="stats-card"><div class="stats-num">${data.total_conversations}</div><div class="stats-lbl">Conversations</div></div>
                <div class="stats-card"><div class="stats-num">${data.total_messages}</div><div class="stats-lbl">Messages envoyés</div></div>
            </div>

            <div class="stats-section-title"><span class="stats-dot"></span>Activité récente</div>
            <div class="stats-chart-card"><canvas id="statsTendanceChart" height="130"></canvas></div>

            <div class="stats-section-title"><span class="stats-dot"></span>Répartition des demandes</div>
            <div class="stats-charts-row">
                <div class="stats-chart-card">
                    <canvas id="statsTypeChart" height="150"></canvas>
                    <div class="stats-legend" id="statsLegendType"></div>
                </div>
                <div class="stats-chart-card">
                    <canvas id="statsModuleChart" height="150"></canvas>
                    <div class="stats-legend" id="statsLegendModule"></div>
                </div>
            </div>
        `;

        if (statsChartTendance) statsChartTendance.destroy();
        statsChartTendance = new Chart(document.getElementById("statsTendanceChart"), {
            type: "bar",
            data: {
                labels: data.tendance_par_jour.map(d => d.jour),
                datasets: [{
                    data: data.tendance_par_jour.map(d => d.total),
                    backgroundColor: "#9a6633",
                    borderRadius: 4,
                    maxBarThickness: 10
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: {
                    x: { display: false },
                    y: { beginAtZero: true, ticks: { stepSize: 1, color: "#8a7563", font: { size: 10 } }, grid: { color: "#f0e6d8" } }
                }
            }
        });

        if (statsChartType) statsChartType.destroy();
        statsChartType = new Chart(document.getElementById("statsTypeChart"), {
            type: "doughnut",
            data: {
                labels: data.repartition_par_type.map(d => STATS_LABELS_TYPE[d.label] || d.label),
                datasets: [{
                    data: data.repartition_par_type.map(d => d.total),
                    backgroundColor: data.repartition_par_type.map(d => STATS_COULEURS_TYPE[d.label] || "#ccc"),
                    borderWidth: 2,
                    borderColor: "#fff"
                }]
            },
            options: { plugins: { legend: { display: false } }, cutout: "62%" }
        });
        document.getElementById("statsLegendType").innerHTML = data.repartition_par_type.map(d =>
            `<span><i style="background:${STATS_COULEURS_TYPE[d.label] || '#ccc'}"></i>${STATS_LABELS_TYPE[d.label] || d.label}</span>`
        ).join("");

        if (statsChartModule) statsChartModule.destroy();
        statsChartModule = new Chart(document.getElementById("statsModuleChart"), {
            type: "doughnut",
            data: {
                labels: data.repartition_par_module.map(d => STATS_LABELS_MODULE[d.label] || d.label),
                datasets: [{
                    data: data.repartition_par_module.map(d => d.total),
                    backgroundColor: data.repartition_par_module.map(d => STATS_COULEURS_MODULE[d.label] || "#ccc"),
                    borderWidth: 2,
                    borderColor: "#fff"
                }]
            },
            options: { plugins: { legend: { display: false } }, cutout: "62%" }
        });
        document.getElementById("statsLegendModule").innerHTML = data.repartition_par_module.map(d =>
            `<span><i style="background:${STATS_COULEURS_MODULE[d.label] || '#ccc'}"></i>${STATS_LABELS_MODULE[d.label] || d.label}</span>`
        ).join("");
    }

    if (statsBtn) statsBtn.addEventListener("click", ouvrirStats);
    if (statsCloseBtn) statsCloseBtn.addEventListener("click", fermerStats);
    if (statsOverlay) {
        statsOverlay.addEventListener("click", (e) => {
            if (e.target === statsOverlay) fermerStats();
        });
    }
});
