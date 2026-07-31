<?php 
session_start();
    require "../../../config/configuration.php";

    $sql = "SELECT * FROM produit ORDER BY id_produit DESC";
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        die("An error occurred: " . mysqli_error($conn));
    }  
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PalmFox - Gestion des Produits</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../includes/sidebar_header.css">
    
    <link rel="stylesheet" href="produits.css">
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
                <h1>Catalogue des Produits</h1>
                <p class="page-subtitle">Gérez vos produits et suivez votre stock.</p>
            </div>
            <button class="btn-primary" onclick="openModal('add')">
                <i class="fa-solid fa-plus"></i> Ajouter un produit
            </button>
        </header>

        <section class="table-card">
            <div class="table-card-header">
                <h2>Liste des Produits</h2>
                <div class="table-actions">
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" placeholder="Rechercher par nom, code ou réf...">
                    </div>
                    <button class="btn-secondary" onclick="toggleFilterOptions()">
                        <i class="fa-solid fa-sliders"></i> Filtrer
                    </button>
                </div>
            </div>

            <div class="filter-options-panel" id="filterPanel">
                <div class="filter-group">
                    <label for="filterStock">État du Stock :</label>
                    <select id="filterStock" class="filter-control">
                        <option value="all">Tous les produits</option>
                        <option value="instock">En stock (Suffisant)</option>
                        <option value="lowstock">Stock faible</option>
                        <option value="out">Rupture de stock</option>
                    </select>
                </div>
                <div class="filter-actions-inline">
                    <button class="btn-primary-small" id="btnApplyFiltre">Appliquer</button>
                </div>
            </div>

            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Code</th>
                            <th>Référence</th>
                            <th>Description</th>
                            <th>Stock</th>
                            <th>Image / PDF</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (mysqli_num_rows($result) === 0) { ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: #8a94a6;">
                                    <i class="fa-solid fa-box-open" style="font-size: 28px; margin-bottom: 12px; display: block; color: #9aa2b1;"></i>
                                    Aucun produit n'est disponible dans le catalogue pour le moment.
                                </td>
                            </tr>
                        <?php } else { 
                            while ($row = mysqli_fetch_assoc($result)) { 
                                $fichier = $row["Image_pdf"]; 
                                $est_pdf = (strtolower(pathinfo($fichier, PATHINFO_EXTENSION)) === 'pdf');
                            ?>
                            <tr>
                                <td><?= $row["id_produit"] ?></td>
                                <td><strong><?= $row["code"] ?></strong></td>
                                <td><?= $row["reference"] ?></td>
                                <td><?= $row["description"] ?></td>
                                
                                <td>
                                    <?php if ($row["quantite_stock"] <= 0) { ?>
                                        <span class="badge badge-danger">Rupture</span>
                                    <?php } elseif ($row["quantite_stock"] <= 5) { ?>
                                        <span class="badge badge-warning"><?= $row["quantite_stock"] ?> unités (Faible)</span>
                                    <?php } else { ?>
                                        <span class="badge badge-success"><?= $row["quantite_stock"] ?> unités</span>
                                    <?php } ?>
                                </td>
                                
                                <td>
                                    <?php if (!empty($fichier)) { ?>
                                        <?php if ($est_pdf) { ?>
                                            <a href="../../public/uploads/<?= $fichier ?>" target="_blank" style="text-decoration: none; display: block;">
                                                <canvas class="pdf-thumbnail" data-pdf-url="../../public/uploads/<?= $fichier ?>" style="width: 40px; height: 50px; border-radius: 4px; border: 1px solid #e6e0d8; object-fit: cover;"></canvas>
                                                <span style="font-size: 10px; color: #e74c3c; display: block; text-align: center; font-weight: bold;">Voir PDF</span>
                                            </a>
                                        <?php } else { ?>
                                            <a href="../../public/uploads/<?= $fichier ?>" target="_blank">
                                                <img src="../../public/uploads/<?= $fichier ?>" alt="Aperçu" style="width: 40px; height: 40px; border-radius: 6px; object-fit: cover; border: 1px solid #e6e0d8;">
                                            </a>
                                        <?php } ?>
                                    <?php } else { ?>
                                        <span style="color: #9aa2b1; font-style: italic; font-size: 12px;">Aucun fichier</span>
                                    <?php } ?>
                                </td>
                                
                                <td class="action-btns">
                                    <button class="btn-icon btn-edit"
                                            data-mode="edit"
                                            data-id="<?= $row['id_produit'] ?>"
                                            data-code="<?= htmlspecialchars($row['code'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-reference="<?= htmlspecialchars($row['reference'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-description="<?= htmlspecialchars($row['description'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-stock="<?= $row['quantite_stock'] ?>"
                                            data-fichier="<?= htmlspecialchars($fichier, ENT_QUOTES, 'UTF-8') ?>"
                                            onclick="setupEditModal(this)">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button class="btn-icon btn-delete" onclick="openDeleteModal(<?= $row['id_produit'] ?> ,'<?= htmlspecialchars(addslashes($row['reference']), ENT_QUOTES, 'UTF-8') ?>')">
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

    <div class="modal-overlay" id="productModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="modalTitle">Ajouter un Nouveau Produit</h3>
                <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form class="modal-body" action="Ajouter_modifier_produit.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Code Produit</label>
                    <input type="text" id="formCode" name="formCode" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Référence</label>
                    <input type="text" id="formReference" name="formReference" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea id="formDescription" name="formDescription" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Image ou Document PDF</label>
                    <input type="file" id="formImage" name="formImage" class="form-control" accept=".png, .jpg, .jpeg, .pdf">
                </div>
                <div class="form-group">
                    <label>Quantité en Stock</label>
                    <input type="number" id="formQty" name="formQty" class="form-control" min="0" required>
                </div>
                
                <input type="hidden" name="produit_id" id="produit_id" value="">
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Annuler</button>
                    <button type="submit" id="formSubmitBtn" class="btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="deleteModal">
        <div class="modal-box modal-box-sm delete-box">
            <div class="delete-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
            <h3>Confirmer la suppression</h3>
            <p class="delete-text">
                Voulez-vous vraiment supprimer le produit : <br><strong id="deleteProductName">[Nom]</strong> ?
            </p>
            <div class="delete-actions">
                <form action="delete_produit.php" method="post">
                    <input type="hidden" name="produit_id_delete" id="produit_id_delete" value="">
                    <button type="button" class="btn-secondary" onclick="closeDeleteModal()">Annuler</button>
                    <button type="submit" class="btn-danger-modal">Oui, supprimer</button>
                </form>
            </div>
        </div>
    </div>

    <script src="produits.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>
    <?php include "../includes/chatbot_widget.php"; ?>
    <script src="../includes/chatbot_widget.js"></script>
</body>
</html>

<?php 
    mysqli_free_result($result);
    mysqli_close($conn);
?>