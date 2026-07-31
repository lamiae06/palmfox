<?php
header('Content-Type: application/json');

// Inclusion de votre fichier de configuration avec le bon nom
require "../../../config/configuration.php";

// Récupération des données JSON envoyées par le JavaScript (fetch)
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['action'])) {
    echo json_encode(['success' => false, 'message' => 'Données invalides ou incomplètes.']);
    exit;
}

$action = $data['action'];

// ==========================================
// ACTION 1 : SUPPRESSION D'UNE COMMANDE
// ==========================================
if ($action === 'delete') {
    $id_commande = intval($data['id_commande']);

    // Début de la transaction mysqli
    mysqli_begin_transaction($conn);

    try {
        // 1. Supprimer d'abord les lignes liées dans la table d'association 'concerner'
        $query1 = "DELETE FROM concerner WHERE id_commande = ?";
        $stmt1 = mysqli_prepare($conn, $query1);
        mysqli_stmt_bind_param($stmt1, "i", $id_commande);
        mysqli_stmt_execute($stmt1);

        // 2. Supprimer la commande principale
        $query2 = "DELETE FROM commande WHERE id_commande = ?";
        $stmt2 = mysqli_prepare($conn, $query2);
        mysqli_stmt_bind_param($stmt2, "i", $id_commande);
        mysqli_stmt_execute($stmt2);

        mysqli_commit($conn);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression : ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================
// ACTION 2 : ENREGISTRER (AJOUT / MODIFICATION)
// ==========================================
if ($action === 'save') {
    $id_commande = !empty($data['id_commande']) ? intval($data['id_commande']) : null;
    $id_client = intval($data['id_client']);
    $delai = trim($data['delai']);
    $date_livraison = $data['date_livraison'];
    $statut = $data['statut'];
    $produits = $data['produits']; // Tableau contenant les {id_produit, quantite}

    if (empty($produits) || !is_array($produits)) {
        echo json_encode(['success' => false, 'message' => 'Veuillez ajouter au moins un produit à la commande.']);
        exit;
    }

    mysqli_begin_transaction($conn);

    try {
        if ($id_commande) {
            // --- MODE MODIFICATION ---
            // Mise à jour de la table principale 'commande'
            $queryUpdate = "UPDATE commande SET id_client = ?, delai = ?, date_livraison = ?, statut = ? WHERE id_commande = ?";
            $stmtUpdate = mysqli_prepare($conn, $queryUpdate);
            mysqli_stmt_bind_param($stmtUpdate, "isssi", $id_client, $delai, $date_livraison, $statut, $id_commande);
            mysqli_stmt_execute($stmtUpdate);

            // Nettoyage des anciens produits de cette commande dans 'concerner'
            $queryClear = "DELETE FROM concerner WHERE id_commande = ?";
            $stmtClear = mysqli_prepare($conn, $queryClear);
            mysqli_stmt_bind_param($stmtClear, "i", $id_commande);
            mysqli_stmt_execute($stmtClear);
        } else {
            // --- MODE AJOUT ---
            // Insertion de la nouvelle commande globale
            $queryInsert = "INSERT INTO commande (id_client, delai, date_livraison, statut) VALUES (?, ?, ?, ?)";
            $stmtInsert = mysqli_prepare($conn, $queryInsert);
            mysqli_stmt_bind_param($stmtInsert, "isss", $id_client, $delai, $date_livraison, $statut);
            mysqli_stmt_execute($stmtInsert);
            
            // Récupération de l'ID généré automatiquement pour cette commande
            $id_commande = mysqli_insert_id($conn);
        }

        // Préparation des requêtes pour le panier de produits
        $queryLine = "INSERT INTO concerner (id_commande, id_produit, Quantite_produit) VALUES (?, ?, ?)";
        $stmtLine = mysqli_prepare($conn, $queryLine);

        // Préparation de la requête de mise à jour des stocks
        $queryStock = "UPDATE produit SET quantite_stock = quantite_stock - ? WHERE id_produit = ?";
        $stmtStock = mysqli_prepare($conn, $queryStock);

        foreach ($produits as $prod) {
            $id_p = intval($prod['id_produit']);
            $qty = intval($prod['quantite']);

            // 1. Insertion dans la table d'association
            mysqli_stmt_bind_param($stmtLine, "iii", $id_commande, $id_p, $qty);
            mysqli_stmt_execute($stmtLine);

            // 2. Déduction automatique du stock si la commande est Confirmée ou Livrée
            if ($statut === 'Confirmée' || $statut === 'Livrée') {
                mysqli_stmt_bind_param($stmtStock, "ii", $qty, $id_p);
                mysqli_stmt_execute($stmtStock);
            }
        }

        mysqli_commit($conn);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => 'Erreur technique : ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action non reconnue.']);
exit;
?>