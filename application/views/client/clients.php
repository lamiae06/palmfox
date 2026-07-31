<?php
session_start();
require "../../../config/configuration.php";

// SUPPRIMER UN CLIENT 
if (isset($_POST['action_delete'])) {
    $id_client = (int)$_POST['id_client'];

    // 1. Supprimer d'abord les contacts (clé étrangère)
   

    // 2. Supprimer le client
    $query_del_client = "DELETE FROM client WHERE id_client = $id_client";
    mysqli_query($conn, $query_del_client);

    header("Location: clients.php");
    exit;
}

// --- ACTION : AJOUTER OU MODIFIER UN CLIENT ---
if (isset($_POST['action_save'])) {
    $id_client = isset($_POST['id_client']) ? (int)$_POST['id_client'] : 0;
    
    // Nettoyage des données simples
    $nom = mysqli_real_escape_string($conn, trim($_POST['nom']));
    $telephone = mysqli_real_escape_string($conn, trim($_POST['telephone']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $commentaire = mysqli_real_escape_string($conn, trim($_POST['commentaire']));
    $mode_paiement = mysqli_real_escape_string($conn, $_POST['modePaiement']);
    $condition_paiement = mysqli_real_escape_string($conn, $_POST['conditionPaiement']);

    if ($nom !== '' && $telephone !== '') {
        if ($id_client > 0) {
            // Modification
            $query = "UPDATE client SET 
                        nom = '$nom', 
                        telephone = '$telephone', 
                        email = '$email', 
                        commentaire = '$commentaire', 
                        mode_paiement = '$mode_paiement', 
                        condition_paiement = '$condition_paiement' 
                      WHERE id_client = $id_client";
            mysqli_query($conn, $query);
        } else {
            // Insertion
            $query = "INSERT INTO client (nom, telephone, email, commentaire, mode_paiement, condition_paiement) 
                      VALUES ('$nom', '$telephone', '$email', '$commentaire', '$mode_paiement', '$condition_paiement')";
            mysqli_query($conn, $query);
            $id_client = mysqli_insert_id($conn);
        }

        // --- Gestion des contacts associés ---
        // On nettoie d'abord les anciens contacts si c'est une modification
        $query_clean = "DELETE FROM contact WHERE id_client = $id_client";
        mysqli_query($conn, $query_clean);

        // Récupération des tableaux de contacts depuis le formulaire
        if (isset($_POST['contacts']) && is_array($_POST['contacts'])) {
            $noms_contacts = $_POST['contacts']['nom'] ?? [];
            $telephones_contacts = $_POST['contacts']['telephone'] ?? [];
            $emails_contacts = $_POST['contacts']['email'] ?? [];

            for ($i = 0; $i < count($noms_contacts); $i++) {
                $c_nom = mysqli_real_escape_string($conn, trim($noms_contacts[$i]));
                $c_tel = mysqli_real_escape_string($conn, trim($telephones_contacts[$i]));
                $c_email = mysqli_real_escape_string($conn, trim($emails_contacts[$i]));

                // On n'insère que si au moins un champ est rempli
                if ($c_nom !== '' || $c_tel !== '' || $c_email !== '') {
                    $query_contact = "INSERT INTO contact (nom, telephone, email, id_client) 
                                      VALUES ('$c_nom', '$c_tel', '$c_email', $id_client)";
                    mysqli_query($conn, $query_contact);
                }
            }
        }
    }
    header("Location: clients.php");
    exit;
}

// --- Variables de recherche et filtres (Méthode GET) ---
$search = isset($_GET['searchInput']) ? mysqli_real_escape_string($conn, trim($_GET['searchInput'])) : '';
$filter_paiement = isset($_GET['filterPaiement']) ? mysqli_real_escape_string($conn, $_GET['filterPaiement']) : '';
$filter_condition = isset($_GET['filterCondition']) ? mysqli_real_escape_string($conn, $_GET['filterCondition']) : '';

// Construction dynamique de la clause WHERE
$where_clauses = [];
if ($search !== '') {
    $where_clauses[] = "(nom LIKE '%$search%' OR telephone LIKE '%$search%' OR email LIKE '%$search%')";
}
if ($filter_paiement !== '') {
    $where_clauses[] = "mode_paiement = '$filter_paiement'";
}
if ($filter_condition !== '') {
    $where_clauses[] = "condition_paiement = '$filter_condition'";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Requête principale pour récupérer la liste affichée
$query_clients = "SELECT * FROM client $where_sql ORDER BY id_client DESC";
$result_clients = mysqli_query($conn, $query_clients);
$total_displayed = mysqli_num_rows($result_clients);

// --- Calculs pour les cartes de statistiques ---
$res_stat_total = mysqli_query($conn, "SELECT COUNT(*) as total FROM client");
$row_stat_total = mysqli_fetch_assoc($res_stat_total);
$total_clients = $row_stat_total['total'];

$res_stat_contacts = mysqli_query($conn, "SELECT COUNT(*) as total FROM contact");
$row_stat_contacts = mysqli_fetch_assoc($res_stat_contacts);
$total_contacts = $row_stat_contacts['total'];

/* PRÉPARATION DU MODE ÉDITION VIA L'URL */
  
$edit_client = null;
$edit_contacts = [];
if (isset($_GET['edit'])) {
    $id_edit = (int)$_GET['edit'];
    $res_edit = mysqli_query($conn, "SELECT * FROM client WHERE id_client = $id_edit");
    if ($row_edit = mysqli_fetch_assoc($res_edit)) {
        $edit_client = $row_edit;
        
        // Récupérer les contacts du client à modifier
        $res_edit_contacts = mysqli_query($conn, "SELECT * FROM contact WHERE id_client = $id_edit");
        while ($row_contact = mysqli_fetch_assoc($res_edit_contacts)) {
            $edit_contacts[] = $row_contact;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PalmFox - Gestion des Clients</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../includes/sidebar_header.css">
    <link rel="stylesheet" href="clients.css">
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
                <h1>Gestion des Clients</h1>
                <p class="page-subtitle">Consultez et gérez vos clients.</p>
            </div>
            <button class="btn-primary" id="btnAddClient">
                <i class="fa-solid fa-plus"></i> Nouveau Client
            </button>
        </header>

        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-total"><i class="fa-solid fa-users"></i></div>
                <div class="stat-info">
                    <span class="stat-label">CLIENTS</span>
                    <span class="stat-value"><?= $total_clients; ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-actifs"><i class="fa-solid fa-user-check"></i></div>
                <div class="stat-info">
                    <span class="stat-label">ACTIFS</span>
                    <span class="stat-value"><?= $total_clients; ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-inactifs"><i class="fa-solid fa-user-xmark"></i></div>
                <div class="stat-info">
                    <span class="stat-label">INACTIFS</span>
                    <span class="stat-value">0</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-contacts"><i class="fa-solid fa-address-book"></i></div>
                <div class="stat-info">
                    <span class="stat-label">CONTACTS</span>
                    <span class="stat-value"><?= $total_contacts; ?></span>
                </div>
            </div>
        </section>

        <section class="table-card">
            <div class="table-card-header">
                <h2>Liste des Clients</h2>
                <form method="GET" action="clients.php" class="table-actions">
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" name="searchInput" placeholder="Rechercher un client..." value="<?= htmlspecialchars($search); ?>">
                    </div>
                    <input type="hidden" name="filterPaiement" value="<?= htmlspecialchars($filter_paiement); ?>">
                    <input type="hidden" name="filterCondition" value="<?= htmlspecialchars($filter_condition); ?>">
                    
                    <button type="button" id="btnFilter" class="btn-secondary">
                        <i class="fa-solid fa-filter"></i> Filtrer 
                        <?= ($filter_paiement !== '' || $filter_condition !== '') ? '(Actif)' : ''; ?>
                    </button>
                    <?php if($search !== '' || $filter_paiement !== '' || $filter_condition !== ''): ?>
                        <a href="clients.php" class="btn-secondary" style="text-decoration:none; text-align:center;">Réinitialiser</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID Spécifique</th>
                            <th>Nom</th>
                            <th>Téléphone</th>
                            <th>Email</th>
                            <th>Commentaire</th>
                            <th>Mode de paiement</th>
                            <th>Condition de paiement</th>
                            <th>Contacts</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $index = 1;
                        while ($row = mysqli_fetch_assoc($result_clients)) { 
                            $id_c = $row['id_client'];
                        ?>
                            <tr>
                                <td><?= $index++; ?></td>
                                <td><?= htmlspecialchars($row['nom']); ?></td>
                                <td><?= htmlspecialchars($row['telephone']); ?></td>
                                <td><?= htmlspecialchars($row['email']); ?></td>
                                <td><?= htmlspecialchars($row['commentaire']); ?></td>
                                <td><?= htmlspecialchars($row['mode_paiement']); ?></td>
                                <td><?= htmlspecialchars($row['condition_paiement']); ?></td>
                                <td>
                                    <?php
                                    // Requête imbriquée simple pour lister les contacts du client en cours
                                    $res_contacts = mysqli_query($conn, "SELECT nom, email, telephone FROM contact WHERE id_client = $id_c");
                                    if (mysqli_num_rows($res_contacts) > 0) {
                                        while ($c = mysqli_fetch_assoc($res_contacts)) {
                                            $lbl = $c['nom'] ?: ($c['email'] ?: $c['telephone']);
                                            echo '<span class="contact-tag">' . htmlspecialchars($lbl) . '</span> ';
                                        }
                                    } else {
                                        echo '<span style="color:#94a3b8">Aucun</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <a href="clients.php?edit=<?= $id_c; ?>" class="edit btn-edit-link" title="Modifier">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                    <button type="button" class="delete btn-trigger-delete" data-id="<?= $id_c; ?>" data-nom="<?= htmlspecialchars($row['nom']); ?>" title="Supprimer">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_displayed === 0): ?>
                <p class="empty-state" id="emptyState" style="display:block;">Aucun client trouvé. Cliquez sur « Nouveau Client » pour en ajouter un.</p>
            <?php endif; ?>
        </section>
    </main>

    <div class="modal-overlay <?= $edit_client ? 'active' : ''; ?>" id="clientModal">
        <div class="modal-box modal-box-lg">
            <div class="modal-header">
                <h2 id="modalTitle"><?= $edit_client ? 'Modifier un Client' : 'Ajouter un Client'; ?></h2>
                <button type="button" class="modal-close" id="closeModal">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form id="clientForm" method="POST" action="clients.php" class="modal-body two-col">
                <input type="hidden" name="id_client" id="clientId" value="<?= $edit_client ? $edit_client['id_client'] : ''; ?>">

                <div class="form-group">
                    <label>Nom du client</label>
                    <input type="text" id="nom" name="nom" value="<?= $edit_client ? htmlspecialchars($edit_client['nom']) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label>Téléphone</label>
                    <input type="text" id="telephone" name="telephone" value="<?= $edit_client ? htmlspecialchars($edit_client['telephone']) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="email" name="email" value="<?= $edit_client ? htmlspecialchars($edit_client['email']) : ''; ?>">
                </div>

                <div class="form-group full-width">
                    <label>Commentaire</label>
                    <textarea id="commentaire" name="commentaire"><?= $edit_client ? htmlspecialchars($edit_client['commentaire']) : ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label>Mode de paiement</label>
                    <select id="modePaiement" name="modePaiement">
                        <option value="">Choisir</option>
                        <option <?= $edit_client && $edit_client['mode_paiement'] === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                        <option <?= $edit_client && $edit_client['mode_paiement'] === 'Virement' ? 'selected' : ''; ?>>Virement</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Condition de paiement</label>
                    <select id="conditionPaiement" name="conditionPaiement">
                        <option value="">Choisir</option>
                        <option <?= $edit_client && $edit_client['condition_paiement'] === 'À la livraison' ? 'selected' : ''; ?>>À la livraison</option>
                        <option <?= $edit_client && $edit_client['condition_paiement'] === '30 jours' ? 'selected' : ''; ?>>30 jours</option>
                        <option <?= $edit_client && $edit_client['condition_paiement'] === '60 jours' ? 'selected' : ''; ?>>60 jours</option>
                    </select>
                </div>

                <div class="contacts-section">
                    <div class="contacts-heading">
                        <h3>Contacts</h3>
                        <button type="button" class="btn-add-contact" id="btnAddContact">
                            <i class="fa-solid fa-plus"></i> Ajouter un contact
                        </button>
                    </div>
                    <div id="contactsContainer">
                        <?php 
                        if ($edit_client && count($edit_contacts) > 0) {
                            foreach ($edit_contacts as $tc) { ?>
                                <div class="contact-item">
                                    <div class="form-group">
                                        <label>Nom du contact</label>
                                        <input type="text" name="contacts[nom][]" value="<?= htmlspecialchars($tc['nom']); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Téléphone du contact</label>
                                        <input type="text" name="contacts[telephone][]" value="<?= htmlspecialchars($tc['telephone']); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Email du contact</label>
                                        <input type="email" name="contacts[email][]" value="<?= htmlspecialchars($tc['email']); ?>">
                                    </div>
                                    <button type="button" class="btn-remove-contact" title="Supprimer ce contact" onclick="this.parentElement.remove();">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                               </div>
                            <?php }
                        } else { ?>
                            <div class="contact-item">
                                <div class="form-group">
                                    <label>Nom du contact</label>
                                    <input type="text" name="contacts[nom][]">
                                </div>
                                <div class="form-group">
                                    <label>Téléphone du contact</label>
                                    <input type="text" name="contacts[telephone][]">
                                </div>
                                <div class="form-group">
                                    <label>Email du contact</label>
                                    <input type="email" name="contacts[email][]">
                                </div>
                                <button type="button" class="btn-remove-contact" title="Supprimer ce contact" onclick="this.parentElement.remove();">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        <?php } ?>
                    </div>
                </div>

                <div class="modal-footer">
                    <a href="clients.php" class="btn-secondary" style="text-decoration:none; text-align:center; line-height:35px;">Annuler</a>
                    <button type="submit" name="action_save" class="btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="filterModal">
        <div class="modal-box">
            <div class="modal-header">
                <h2>Filtrer les clients</h2>
                <button type="button" class="modal-close" id="closeFilter">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form method="GET" action="clients.php" class="modal-body">
                <input type="hidden" name="searchInput" value="<?= htmlspecialchars($search); ?>">

                <div class="form-group">
    <label>Mode de paiement</label>
    <select id="modePaiement" name="modePaiement">
        <option value="">Choisir</option>
        <option value="Cash" <?= $edit_client && $edit_client['mode_paiement'] === 'Cash' ? 'selected' : ''; ?>>Cash</option>
        <option value="Virement" <?= $edit_client && $edit_client['mode_paiement'] === 'Virement' ? 'selected' : ''; ?>>Virement</option>
    </select>
</div>

                <div class="form-group">
    <label>Condition de paiement</label>
    <select id="conditionPaiement" name="conditionPaiement">
        <option value="">Choisir</option>
        <option value="À la livraison" <?= $edit_client && $edit_client['condition_paiement'] === 'À la livraison' ? 'selected' : ''; ?>>À la livraison</option>
        <option value="30 jours" <?= $edit_client && $edit_client['condition_paiement'] === '30 jours' ? 'selected' : ''; ?>>30 jours</option>
        <option value="60 jours" <?= $edit_client && $edit_client['condition_paiement'] === '60 jours' ? 'selected' : ''; ?>>60 jours</option>
    </select>
</div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" id="cancelFilter">Annuler</button>
                    <button type="submit" class="btn-primary">Appliquer</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="deleteModal">
        <form method="POST" action="clients.php" class="delete-modal">
            <input type="hidden" name="id_client" id="deleteClientId">

            <div class="delete-icon">
                <i class="fa-solid fa-exclamation"></i>
            </div>

            <h2>Confirmer la suppression</h2>
            <p>Voulez-vous vraiment supprimer le client : <strong id="deleteClientName"></strong> ?</p>

            <div class="delete-actions">
                <button type="button" id="cancelDelete" class="btn-cancel-delete">Annuler</button>
                <button type="submit" name="action_delete" class="btn-confirm-delete">Oui, supprimer</button>
            </div>
        </form>
    </div>

    <script src="clients.js"></script>
    <?php include "../includes/chatbot_widget.php"; ?>
<script src="../includes/chatbot_widget.js"></script>
</body>
</html>