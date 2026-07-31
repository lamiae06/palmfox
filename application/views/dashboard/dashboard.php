<?php
session_start();
require "../../../config/configuration.php";

/*
   1. RÉCUPÉRATION DES STATISTIQUES (KPI)
    */

// Compter le nombre total de clients
$res_clients = mysqli_query($conn, "SELECT COUNT(*) as total FROM client");
$row_clients = mysqli_fetch_assoc($res_clients);
$total_clients = $row_clients['total'];

// Compter le nombre de commandes
$res_commandes = mysqli_query($conn, "SELECT COUNT(*) as total FROM commande");
$row_commandes = mysqli_fetch_assoc($res_commandes);
$total_commandes = $row_commandes['total'];

// Compter le nombre de livraisons (depuis la table 'livraison' directement)
$res_livraisons = mysqli_query($conn, "SELECT COUNT(*) as total FROM livraison");
if ($res_livraisons) {
    $row_livraisons = mysqli_fetch_assoc($res_livraisons);
    $total_livraisons = $row_livraisons['total'];
} else {
    $total_livraisons = 0; // Sécurité si la table ou le statut n'est pas encore configuré
}

// Valeur du stock (Valeur statique ou calculée selon votre cours)
$valeur_stock = "2.4M DA";

/*
   2. RÉCUPÉRATION DES COMMANDES RÉCENTES
   */
// On récupère les 4 dernières commandes de la base de données
$query_recentes = "SELECT * FROM commande ORDER BY id_commande DESC LIMIT 4";
$result_recentes = mysqli_query($conn, $query_recentes);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PalmFox - Tableau de bord</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="../includes/sidebar_header.css">
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="../includes/chatbot_widget.css">
</head>

<body>
   
   
    <?php include "../includes/sidebar.php"; ?>   

    <main class="main-content">
        <div class="topbar">
            <a href="#" class="user-profile">
                <div class="user-avatar">AM</div>
                <h5>Admin</h5>
            </a>
        </div>

        <header class="page-header">
            <div>
                <h1>Tableau de bord</h1>
                <p class="page-subtitle" id="dashboardDate"></p>
            </div>
        </header>
 
        <section class="cards-dashboard-grid">

            <div class="card-kpi">
                <div class="card-kpi-info">
                    <span class="card-kpi-title">Clients Actifs</span>
                    <h2 class="card-kpi-value"><?= $total_clients; ?></h2>
                    <span class="card-kpi-sub">Total enregistrés</span>
                </div>
                <div class="card-kpi-side">
                    <div class="card-kpi-icon icon-clients">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <span class="trend-badge trend-up"><i class="fa-solid fa-arrow-trend-up"></i> 8%</span>
                </div>
            </div>

            <div class="card-kpi">
                <div class="card-kpi-info">
                    <span class="card-kpi-title">Commandes</span>
                    <h2 class="card-kpi-value"><?= $total_commandes; ?></h2>
                    <span class="card-kpi-sub">Ce mois-ci</span>
                </div>
                <div class="card-kpi-side">
                    <div class="card-kpi-icon icon-commandes">
                        <i class="fa-solid fa-cart-shopping"></i>
                    </div>
                    <span class="trend-badge trend-up"><i class="fa-solid fa-arrow-trend-up"></i> 12%</span>
                </div>
            </div>

            <div class="card-kpi">
                <div class="card-kpi-info">
                    <span class="card-kpi-title">Livraisons</span>
                    <h2 class="card-kpi-value"><?= $total_livraisons; ?></h2>
                    <span class="card-kpi-sub">En cours / livrées</span>
                </div>
                <div class="card-kpi-side">
                    <div class="card-kpi-icon icon-livraisons">
                        <i class="fa-solid fa-truck"></i>
                    </div>
                    <span class="trend-badge trend-down"><i class="fa-solid fa-arrow-trend-down"></i> 3%</span>
                </div>
            </div>

            <div class="card-kpi">
                <div class="card-kpi-info">
                    <span class="card-kpi-title">Valeur Stock</span>
                    <h2 class="card-kpi-value font-mono"><?= $valeur_stock; ?></h2>
                    <span class="card-kpi-sub">Valorisation actuelle</span>
                </div>
                <div class="card-kpi-side">
                    <div class="card-kpi-icon icon-stock">
                        <i class="fa-solid fa-box"></i>
                    </div>
                    <span class="trend-badge trend-up"><i class="fa-solid fa-arrow-trend-up"></i> 5%</span>
                </div>
            </div>

        </section>

        <section class="table-card">
            <div class="table-card-header">
                <h2>Commandes récentes</h2>
                <a href="../commande/commande.php" class="btn-view-all">Voir tout</a>
            </div>

            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Référence</th>
                            <th>Client</th>
                            <th>Produit</th>
                            <th>Qté</th>
                            <th>Statut</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody id="commandesTableBody">
                        <?php 
                        if ($result_recentes && mysqli_num_rows($result_recentes) > 0) {
                            while ($cmd = mysqli_fetch_assoc($result_recentes)) { 
                                $classe_statut = "encours";
                                if(strtolower($cmd['statut']) == "livrée" || strtolower($cmd['statut']) == "livree") $classe_statut = "livree";
                                if(strtolower($cmd['statut']) == "annulée" || strtolower($cmd['statut']) == "annulee") $classe_statut = "annulee";
                            ?>
                                <tr>
                                    <td class="ref-cell"><?= htmlspecialchars($cmd['reference'] ?? $cmd['id_commande']); ?></td>
                                    <td><?= htmlspecialchars($cmd['client'] ?? 'Client'); ?></td>
                                    <td><?= htmlspecialchars($cmd['produit'] ?? 'Produit'); ?></td>
                                    <td><?= htmlspecialchars($cmd['quantite'] ?? '0'); ?></td>
                                    <td>
                                        <span class="badge-status status-<?= $classe_statut; ?>">
                                            <?= htmlspecialchars($cmd['statut']); ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($cmd['date'] ?? ''); ?></td>
                                </tr>
                            <?php 
                            } 
                        } else { 
                        ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: #94a3b8; padding: 20px;">
                                    Aucune commande récente disponible.
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>
    <script src="dashboard.js"></script>
      <?php include "../includes/chatbot_widget.php"; ?>
    <script src="../includes/chatbot_widget.js"></script>
</body>
</html>