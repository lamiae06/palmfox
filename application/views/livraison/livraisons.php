<?php 
session_start();
   require "../../../config/configuration.php";

    $sql = "SELECT * FROM livraison ORDER BY id_Livraison DESC";
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        die("An error occurred: " . mysqli_error($conn));
    }

    $sql = "SELECT id_commande FROM commande";
    $ids_commande = mysqli_query($conn, $sql);

    if (!$ids_commande) {
        die("An error occurred: " . mysqli_error($conn));
    }
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PalmFox - Gestion des Livraisons</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../includes/sidebar_header.css">
    <link rel="stylesheet" href="livraisons.css">
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
                <h1>Suivi des Livraisons</h1>
                <p class="page-subtitle">Suivez l'état d'expédition de vos commandes clients.</p>
            </div>
            <button class="btn-primary" onclick="openModal('add')">
                <i class="fa-solid fa-plus"></i> Nouvelle Livraison
            </button>
        </header>

        <section class="table-card">
            <div class="table-card-header">
                <h2>Liste des Livraisons</h2>
                <div class="table-actions">
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" placeholder="Rechercher par livraison ou commande...">
                    </div>
                    <button class="btn-secondary" onclick="toggleFilterOptions()">
                        <i class="fa-solid fa-sliders"></i> Filtrer
                    </button>
                </div>
            </div>

            <div class="filter-options-panel" id="filterPanel">
                <div class="filter-group">
                    <label for="filterStatus">Statut :</label>
                    <select id="filterStatus" class="filter-control">
                        <option value="all">Toutes les étapes</option>
                        <option value="pending">En cours</option>
                        <option value="done">Livrée / Facturée</option>
                    </select>
                </div>
                <div class="filter-actions-inline">
                    <button class="btn-primary-small">Appliquer</button>
                </div>
            </div>

            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Code Livraison</th>
                            <th>ID Commande</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (mysqli_num_rows($result) === 0) { ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 40px; color: #8a94a6;">
                                    <i class="fa-solid fa-truck" style="font-size: 28px; margin-bottom: 12px; display: block; color: #9aa2b1;"></i>
                                    Aucune livraison enregistrée pour le moment.
                                </td>
                            </tr>
                        <?php } else { 
                            while ($row = mysqli_fetch_assoc($result)) { ?>
                            <tr>
                                <td><strong>LIV-<?= $row["id_Livraison"] ?></strong></td>
                                <td>CMD<?= $row["id_commande"] ?></td>
                                <td>
                                    <?php if ($row["Statut"] === "Livrée / Facturée") { ?>
                                        <span class="badge badge-success">Livrée / Facturée</span>
                                    <?php } else { ?>
                                        <span class="badge badge-warning">En cours</span>
                                    <?php } ?>
                                </td>
                                <td class="action-btns">
                                    <button class="btn-icon btn-edit" 
                                            onclick="openModal('edit', '<?= $row['id_Livraison'] ?>', '<?= $row['id_commande'] ?>', '<?= htmlspecialchars($row['Statut'], ENT_QUOTES, 'UTF-8') ?>')">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button class="btn-icon btn-delete" onclick="openDeleteModal(<?= $row['id_Livraison'] ?>)">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php } 
                        } ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <div class="modal-overlay" id="deliveryModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="modalTitle">Nouvelle Livraison</h3>
                <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form class="modal-body" action="ajouter_modifier_livraison.php" method="POST">
                <input type="hidden" name="deliveryId" id="deliveryId" value="">
                
                <div class="form-group">
                    <label>ID Commande</label>
                    <select id="formCommande" name="formCommande" class="form-control" required>
                        <?php 
                        mysqli_data_seek($ids_commande, 0); 
                        while ($rowCmd = mysqli_fetch_assoc($ids_commande)) { ?>
                            <option value="<?= $rowCmd["id_commande"] ?>">CMD<?= $rowCmd["id_commande"] ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Statut de la Livraison</label>
                    <select id="formStatus" name="formStatus" class="form-control" required>
                        <option value="En cours">En cours</option>
                        <option value="Livrée / Facturée">Livrée / Facturée</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Annuler</button>
                    <button type="submit" id="formSubmitBtn" class="btn-primary">Valider</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="deleteModal">
        <div class="modal-box modal-box-sm delete-box">
            <div class="delete-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
            <h3>Confirmer la suppression</h3>
            <p class="delete-text">
                Voulez-vous vraiment supprimer la livraison : <br><strong id="deleteDeliveryName">[Code]</strong> ?
            </p>
            <div class="delete-actions">
                <form action="delete_livraison.php" method="POST">
                    <input type="hidden" id="id_livraison" name="id_livraison" value="">
                    <button type="button" class="btn-secondary" onclick="closeDeleteModal()">Annuler</button>
                    <button type="submit" class="btn-danger-modal">Oui, supprimer</button>
                </form>
            </div>
        </div>
    </div>

    
<script src="livraisons.js"></script>
 <?php include "../includes/chatbot_widget.php"; ?>
    <script src="../includes/chatbot_widget.js"></script>
</body>
</html>

<?php 
    mysqli_free_result($result);
    mysqli_free_result($ids_commande);
    mysqli_close($conn);
?>