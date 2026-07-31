<?php
session_start();
// 1. INCLUSION DE VOTRE CONFIGURATION
require "../../../config/configuration.php";

// 2. RÉCUPÉRATION DES STATISTIQUES EN MYSQLI
$stats_query = mysqli_query($conn, "SELECT 
    SUM(CASE WHEN statut = 'Confirmée' THEN 1 ELSE 0 END) as confirmees,
    SUM(CASE WHEN statut = 'En attente' THEN 1 ELSE 0 END) as attente,
    SUM(CASE WHEN statut = 'Annulée' THEN 1 ELSE 0 END) as annulees,
    SUM(CASE WHEN statut = 'Livrée' THEN 1 ELSE 0 END) as livrees
FROM commande");
$stats = mysqli_fetch_assoc($stats_query);

// 3. RÉCUPÉRATION DES CLIENTS ET PRODUITS
$liste_clients = mysqli_query($conn, "SELECT id_client, nom FROM client ORDER BY nom ASC");
$liste_produits = mysqli_query($conn, "SELECT id_produit, reference, quantite_stock FROM produit ORDER BY reference ASC");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PalmFox - Gestion des Commandes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../includes/sidebar_header.css">
    <link rel="stylesheet" href="commande.css">
    <link rel="stylesheet" href="../includes/chatbot_widget.css">
</head>
<body>
   
 <?php include "../includes/sidebar.php"; ?>
    <div class="main-content">
        <div class="topbar">
            <a class="user-profile">
                <div class="user-avatar">AD</div>
                <div><h5>Admin</h5><p style="margin:0; font-size:11px; color:#8a94a6;">Gestionnaire</p></div>
            </a>
        </div>

        <header class="page-header">
            <div>
                <h1>Gestion des Commandes</h1>
                <p class="page-subtitle">Suivi des commandes, livraisons et stock.</p>
            </div>
            <button class="btn-primary" id="btnOpenAddCommande">
                <i class="fa-solid fa-plus"></i> Ajouter une Commande
            </button>
        </header>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-pending"><i class="fa-solid fa-clock"></i></div>
                <div class="stat-info">
                    <span class="stat-label">En attente</span>
                    <span class="stat-value"><?= intval($stats['attente'] ?? 0); ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-confirmed"><i class="fa-solid fa-check"></i></div>
                <div class="stat-info">
                    <span class="stat-label">Confirmées</span>
                    <span class="stat-value"><?= intval($stats['confirmees'] ?? 0); ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-delivered"><i class="fa-solid fa-truck-ramp-box"></i></div>
                <div class="stat-info">
                    <span class="stat-label">Livrées</span>
                    <span class="stat-value"><?= intval($stats['livrees'] ?? 0); ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-cancelled"><i class="fa-solid fa-xmark"></i></div>
                <div class="stat-info">
                    <span class="stat-label">Annulées</span>
                    <span class="stat-value"><?= intval($stats['annulees'] ?? 0); ?></span>
                </div>
            </div>
        </div>

        <div class="table-container" style="background:#fff; border-radius:14px; padding:24px; box-shadow:0 1px 3px rgba(20,24,40,0.06); margin-top:20px;">
            <div class="table-actions" style="margin-bottom: 18px;">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="searchInput" placeholder="Rechercher une commande...">
                </div>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>N° Commande</th>
                        <th>Client</th>
                        <th>Produits commandés</th>
                        <th>Délai</th>
                        <th>Date Livraison</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="commandesTableBody">
                    <?php
                    $query = "SELECT c.id_commande, cl.nom AS nom_client, c.delai, c.date_livraison, c.statut,
                                     GROUP_CONCAT(CONCAT(co.Quantite_produit, 'x ', p.reference) SEPARATOR ', ') AS produits_commandes
                              FROM commande c
                              JOIN client cl ON c.id_client = cl.id_client
                              LEFT JOIN concerner co ON c.id_commande = co.id_commande
                              LEFT JOIN produit p ON co.id_produit = p.id_produit
                              GROUP BY c.id_commande
                              ORDER BY c.id_commande DESC";
                    $result = mysqli_query($conn, $query);
                    while ($row = mysqli_fetch_assoc($result)) {
                        $badgeClass = '';
                        switch ($row['statut']) {
                            case 'Confirmée': $badgeClass = 'badge-confirmee'; break;
                            case 'En attente': $badgeClass = 'badge-attente'; break;
                            case 'Annulée': $badgeClass = 'badge-annulee'; break;
                            case 'Livrée': $badgeClass = 'badge-livree'; break;
                        }
                        $dateF = '—';
                        if (!empty($row['date_livraison'])) {
                            $dp = explode('-', $row['date_livraison']);
                            $dateF = $dp[2] . '/' . $dp[1] . '/' . $dp[0];
                        }
                        echo "<tr data-id='{$row['id_commande']}'>";
                        echo "<td><strong>CMD" . str_pad($row['id_commande'], 3, '0', STR_PAD_LEFT) . "</strong></td>";
                        echo "<td>" . htmlspecialchars($row['nom_client']) . "</td>";
                        echo "<td style='color:#5c6470;'>" . htmlspecialchars($row['produits_commandes'] ?? 'Aucun produit') . "</td>";
                        echo "<td>" . $row['delai'] . "</td>";
                        echo "<td>" . $dateF . "</td>";
                        echo "<td><span class='badge {$badgeClass}'>" . htmlspecialchars($row['statut']) . "</span></td>";
                        echo "<td>
                                <button class='action-btn edit' data-action='edit' data-id='{$row['id_commande']}'><i class='fa-solid fa-pen'></i></button>
                                <button class='action-btn delete' data-action='delete' data-id='{$row['id_commande']}'><i class='fa-solid fa-trash'></i></button>
                              </td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal-overlay" id="commandeModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="modalTitle">Ajouter une Nouvelle Commande</h3>
                <button class="modal-close" id="btnCloseModal"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form id="commandeForm">
                <input type="hidden" id="commandeId">
                <div class="modal-body">
                    <label for="clientSelect">Client</label>
                    <select id="clientSelect" required>
                        <option value="">-- Choisir un client --</option>
                        <?php while($c = mysqli_fetch_assoc($liste_clients)): ?>
                            <option value="<?= $c['id_client']; ?>"><?= htmlspecialchars($c['nom']); ?></option>
                        <?php endwhile; ?>
                    </select>

                   <label style="margin-top: 12px; font-weight:700;">Ajouter un produit</label>
<div style="display: flex; gap: 8px; align-items: stretch; margin-bottom: 4px;">
    <select id="produitSelect" style="flex: 2; margin: 0;">
        <option value="">-- Choisir un produit --</option>
        <?php mysqli_data_seek($liste_produits, 0); ?>
        <?php while($p = mysqli_fetch_assoc($liste_produits)): ?>
            <option value="<?= $p['id_produit']; ?>" data-stock="<?= $p['quantite_stock']; ?>" data-ref="<?= htmlspecialchars($p['reference']); ?>">
                <?= htmlspecialchars($p['reference']); ?> (Stock: <?= $p['quantite_stock']; ?>)
            </option>
        <?php endwhile; ?>
    </select>
    
    <input type="number" id="quantiteInput" min="1" value="1" placeholder="Qté" style="width: 80px; margin: 0;">
    
    <button type="button" id="btnAddProductRow" style="background: #8a5a30; color: #fff; border: none; padding: 0 16px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center;">
        <i class="fa-solid fa-plus"></i>
    </button>
</div>
                    <small id="stockInfoText" style="display:block; color:#8a94a6; margin-bottom:12px;">Sélectionnez un produit pour voir son stock.</small>

                    <label style="font-weight:700;">Produits dans cette commande :</label>
                    <div style="border: 1px solid #e6e0d8; border-radius: 8px; max-height: 120px; overflow-y: auto; margin-bottom: 12px; background: #fff;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                            <thead style="background: #f7f8fb; border-bottom: 1px solid #e6e0d8;">
                                <tr>
                                    <th style="padding: 6px; text-align: left; color:#8a94a6;">Produit</th>
                                    <th style="padding: 6px; width: 60px; color:#8a94a6;">Qté</th>
                                    <th style="padding: 6px; width: 40px; text-align: center; color:#8a94a6;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="modalProductsTableBody">
                                </tbody>
                        </table>
                    </div>

                    <label for="delaiInput">Délai</label>
                    <input type="text" id="delaiInput" placeholder="ex: 7 jours" required>

                    <label for="dateLivraisonInput">Date de livraison</label>
                    <input type="date" id="dateLivraisonInput" required>

                    <label for="statutSelect">Statut</label>
                    <select id="statutSelect" required>
                        <option value="En attente">En attente</option>
                        <option value="Confirmée">Confirmée</option>
                        <option value="Livrée">Livrée</option>
                        <option value="Annulée">Annulée</option>
                    </select>

                    <p class="form-error" id="formError"></p>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-secondary" id="btnCancelModal">Annuler</button>
                    <button type="submit" class="btn-primary">Enregistrer la commande</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="deleteModal">
        <div class="modal-box modal-box-sm">
            <div class="delete-box">
                <i class="fa-solid fa-triangle-exclamation delete-icon"></i>
                <h3>Confirmer la suppression</h3>
                <p class="delete-text">Voulez-vous vraiment supprimer définitivement la commande :<br><strong id="deleteCmdId"></strong> ?</p>
                <div class="delete-actions">
                    <button class="btn-secondary" id="btnCancelDelete">Annuler</button>
                    <button class="btn-danger-modal" id="btnConfirmDelete">Oui, supprimer</button>
                </div>
            </div>
        </div>
    </div>

    <script src="commande.js"></script>
      <?php include "../includes/chatbot_widget.php"; ?>
    <script src="../includes/chatbot_widget.js"></script>
</body>
</html>