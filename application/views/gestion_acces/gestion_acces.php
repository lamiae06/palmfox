<?php
session_start();
require "../../../config/configuration.php";
// Verify database connection is established
if (!isset($conn) || !$conn) {
    die("Database connection failed. Please check your configuration file.");
}
// 1. Sécurité : Seul le Super Admin a le droit d'accéder à cette page
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    header("Location: ../login/login.php");
    exit;
}

$id_admin = $_SESSION['user_id'];
$username_admin = $_SESSION['user_nom'] ?? 'Admin';

// Extraction des initiales pour l'avatar
$initiales = strtoupper(substr($username_admin, 0, 2));

// 2. Traitement d'Ajout d'un Employé
if (isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password']; 
    $role = $_POST['role'];

    // HACHAGE DU MOT DE PASSE AVANT LE STOCKAGE
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $query = "INSERT INTO utilisateurs (username, email, password, role) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ssss", $username, $email, $hashed_password, $role);
    
    if (mysqli_stmt_execute($stmt)) {
        $new_user_id = mysqli_insert_id($conn);
        
        $pages = ['commande', 'livraison', 'produit', 'client'];
        foreach ($pages as $page) {
            $query_p = "INSERT INTO permissions (id_user, nom_page, acces_autorise) VALUES (?, ?, 0)";
            $stmt_p = mysqli_prepare($conn, $query_p);
            mysqli_stmt_bind_param($stmt_p, "is", $new_user_id, $page);
            mysqli_stmt_execute($stmt_p);
        }

        $action = "A ajouté l'employé : " . $username . " avec le rôle " . $role;
        $query_h = "INSERT INTO historique_actions (id_user, action_effectuee) VALUES (?, ?)";
        $stmt_h = mysqli_prepare($conn, $query_h);
        mysqli_stmt_bind_param($stmt_h, "is", $id_admin, $action);
        mysqli_stmt_execute($stmt_h);

        header("Location: gestion_acces.php?success=1");
        exit;
    }
}


if (isset($_POST['update_permissions'])) {
    $id_emp = $_POST['id_user'];
    $pages = ['commande', 'livraison', 'produit', 'client'];
    
    foreach ($pages as $page) {
        $status = isset($_POST['perms'][$id_emp][$page]) ? 1 : 0;
        
        $query_up = "UPDATE permissions SET acces_autorise = ? WHERE id_user = ? AND nom_page = ?";
        $stmt_up = mysqli_prepare($conn, $query_up);
        mysqli_stmt_bind_param($stmt_up, "iis", $status, $id_emp, $page);
        mysqli_stmt_execute($stmt_up);
    }

    $action = "A modifié les permissions de l'utilisateur ID: " . $id_emp;
    $query_h = "INSERT INTO historique_actions (id_user, action_effectuee) VALUES (?, ?)";
    $stmt_h = mysqli_prepare($conn, $query_h);
    mysqli_stmt_bind_param($stmt_h, "is", $id_admin, $action);
    mysqli_stmt_execute($stmt_h);

    header("Location: gestion_acces.php?success=2");
    exit;
}

// 👇 4. NOUVEAU : TRAITEMENT DE SUPPRESSION D'UN UTILISATEUR 👇
if (isset($_POST['delete_user'])) {
    $id_emp_to_delete = $_POST['id_user_delete'];

    // On supprime d'abord ses permissions (clé étrangère)
    $query_del_perms = "DELETE FROM permissions WHERE id_user = ?";
    $stmt_del_perms = mysqli_prepare($conn, $query_del_perms);
    mysqli_stmt_bind_param($stmt_del_perms, "i", $id_emp_to_delete);
    mysqli_stmt_execute($stmt_del_perms);

    // Ensuite on supprime l'utilisateur
    $query_del_user = "DELETE FROM utilisateurs WHERE id_user = ?";
    $stmt_del_user = mysqli_prepare($conn, $query_del_user);
    mysqli_stmt_bind_param($stmt_del_user, "i", $id_emp_to_delete);
    
    if (mysqli_stmt_execute($stmt_del_user)) {
        // Enregistrement dans les logs
        $action = "A supprimé l'utilisateur ID: " . $id_emp_to_delete;
        $query_h = "INSERT INTO historique_actions (id_user, action_effectuee) VALUES (?, ?)";
        $stmt_h = mysqli_prepare($conn, $query_h);
        mysqli_stmt_bind_param($stmt_h, "is", $id_admin, $action);
        mysqli_stmt_execute($stmt_h);

        header("Location: gestion_acces.php?success=3");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PalmFox - Gestion des Accès</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="../includes/sidebar_header.css">
    <link rel="stylesheet" href="gestion_acces.css">
    <link rel="stylesheet" href="../includes/chatbot_widget.css">
</head>
<body>

 <?php include "../includes/sidebar.php"; ?>

    <div class="main-content">
        
        <div class="topbar">
            <div class="user-profile">
                <div class="user-avatar"><?= $initiales ?></div>
                <h5><?= htmlspecialchars($username_admin) ?></h5>
            </div>
        </div>

        <header class="page-header">
            <div>
                <h1>Gestion des Accès</h1>
                <p class="page-subtitle">Configurez les comptes de vos collaborateurs et ajustez leurs droits d'accès.</p>
            </div>
        </header>

        <?php if(isset($_GET['success'])): ?>
            <div class="alert-success-panel">
                <i class="fa-solid fa-circle-check"></i> 
                <?php 
                    if($_GET['success'] == 1) echo "Nouvel employé créé avec succès !";
                    elseif($_GET['success'] == 2) echo "Permissions mises à jour avec succès !";
                    elseif($_GET['success'] == 3) echo "Utilisateur supprimé avec succès !";
                ?>
            </div>
        <?php endif; ?>

        <div class="table-card" style="margin-bottom: 24px;">
            <div class="table-card-header">
                <h2 class="section-title-custom"><i class="fa-solid fa-user-plus"></i> Ajouter un nouvel employé</h2>
            </div>
            
            <form method="POST" action="">
                <div class="form-grid-custom">
                    <div class="form-group-custom">
                        <label class="label-custom">Nom d'utilisateur</label>
                        <input class="input-custom" type="text" name="username" placeholder="Ex: amine_dev" required>
                    </div>
                    <div class="form-group-custom">
                        <label class="label-custom">Email</label>
                        <input class="input-custom" type="email" name="email" placeholder="amine@palmfox.com" required>
                    </div>
                    <div class="form-group-custom">
                        <label class="label-custom">Mot de passe</label>
                        <input class="input-custom" type="password" name="password" placeholder="••••••••" required>
                    </div>
                    <div class="form-group-custom">
                        <label class="label-custom">Rôle</label>
                        <select class="select-custom" name="role" required>
                            <option value="commercial">Commercial</option>
                            <option value="gestionnaire">Gestionnaire</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group-custom action-btn-container">
                        <button type="submit" name="add_user" class="btn-primary-custom">
                            <i class="fa-solid fa-check"></i> Créer le compte
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-card" style="margin-bottom: 24px;">
            <div class="table-card-header">
                <h2 class="section-title-custom"><i class="fa-solid fa-sliders"></i> Droits d'accès aux modules</h2>
            </div>

            <?php
            $query_u = "SELECT * FROM utilisateurs WHERE role != 'super_admin' ORDER BY id_user DESC";
            $res_u = mysqli_query($conn, $query_u);
            
            if(mysqli_num_rows($res_u) == 0) {
                echo "<p style='color:#8a94a6; font-size:14px; text-align:center; padding:20px;'>Aucun collaborateur enregistré pour le moment.</p>";
            }

            while($user = mysqli_fetch_assoc($res_u)) {
                $uid = $user['id_user'];
                
                $query_p = "SELECT * FROM permissions WHERE id_user = $uid";
                $res_p = mysqli_query($conn, $query_p);
                $perms = [];
                while($p = mysqli_fetch_assoc($res_p)) {
                    $perms[$p['nom_page']] = $p['acces_autorise'];
                }
            ?>
                <div class="employee-block-row">
                    
                    <div class="employee-block-header">
                        <div class="employee-info-meta">
                            <span class="emp-name"><?= htmlspecialchars($user['username']) ?></span>
                            <span class="emp-role-badge"><?= ucfirst($user['role']) ?></span>
                        </div>
                        
                        <div class="employee-actions-buttons" style="display: flex; gap: 8px;">
                            
                            <button type="submit" form="form-perm-<?= $uid ?>" name="update_permissions" class="btn-save-permissions">
                                <i class="fa-solid fa-floppy-disk"></i> Enregistrer
                            </button>
                            
                            <form method="POST" action="" onsubmit="return confirm('Voulez-vous vraiment supprimer définitivement cet employé ?');" style="margin:0;">
                                <input type="hidden" name="id_user_delete" value="<?= $uid ?>">
                                <button type="submit" name="delete_user" class="btn-delete-employee">
                                    <i class="fa-solid fa-trash"></i> Supprimer
                                </button>
                            </form>
                            
                        </div>
                    </div>
                    
                    <form method="POST" action="" id="form-perm-<?= $uid ?>">
                        <input type="hidden" name="id_user" value="<?= $uid ?>">
                        <div class="table-scroll">
                            <table class="data-table-custom">
                                <thead>
                                    <tr>
                                        <th>Page Commandes</th>
                                        <th>Page Livraisons</th>
                                        <th>Page Produits</th>
                                        <th>Page Clients</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <label class="checkbox-container">
                                                <input type="checkbox" name="perms[<?= $uid ?>][commande]" <?= isset($perms['commande']) && $perms['commande'] == 1 ? 'checked' : '' ?>>
                                                <span>Autorisé</span>
                                            </label>
                                        </td>
                                        <td>
                                            <label class="checkbox-container">
                                                <input type="checkbox" name="perms[<?= $uid ?>][livraison]" <?= isset($perms['livraison']) && $perms['livraison'] == 1 ? 'checked' : '' ?>>
                                                <span>Autorisé</span>
                                            </label>
                                        </td>
                                        <td>
                                            <label class="checkbox-container">
                                                <input type="checkbox" name="perms[<?= $uid ?>][produit]" <?= isset($perms['produit']) && $perms['produit'] == 1 ? 'checked' : '' ?>>
                                                <span>Autorisé</span>
                                            </label>
                                        </td>
                                        <td>
                                            <label class="checkbox-container">
                                                <input type="checkbox" name="perms[<?= $uid ?>][client]" <?= isset($perms['client']) && $perms['client'] == 1 ? 'checked' : '' ?>>
                                                <span>Autorisé</span>
                                            </label>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
            <?php } ?>
        </div>

        <div class="table-card">
            <div class="table-card-header">
                <h2 class="section-title-custom"><i class="fa-solid fa-clock-rotate-left"></i> Historique d'activité</h2>
            </div>
            <div class="logs-container-custom">
                <?php
                $query_logs = "SELECT h.*, u.username FROM historique_actions h JOIN utilisateurs u ON h.id_user = u.id_user ORDER BY h.date_action DESC LIMIT 10";
                $res_logs = mysqli_query($conn, $query_logs);
                if(mysqli_num_rows($res_logs) == 0) {
                     echo "<p style='color:#8a94a6; font-size:13px; margin:0;'>Aucun log d'action disponible.</p>";
                }
                while($log = mysqli_fetch_assoc($res_logs)) {
                    echo "<div class='log-row-item'>";
                    echo "<span class='log-timestamp'>[" . $log['date_action'] . "]</span> ";
                    echo "<span class='log-actor'>" . htmlspecialchars($log['username']) . "</span> : " . htmlspecialchars($log['action_effectuee']);
                    echo "</div>";
                }
                ?>
            </div>
        </div>

    </div>
    <script>
        // Force l'onglet "Gestion Accès" à s'allumer dans la sidebar commune
        const activeLink = document.querySelector('.sidebar-nav a[href*="gestion_acces.php"]');
        if (activeLink) {
            activeLink.classList.add('active');
        }
    </script>
     <?php include "../includes/chatbot_widget.php"; ?>
    <script src="../includes/chatbot_widget.js"></script>
</body>
</html>