<?php
header('Content-Type: application/json');

// 1. Inclusion de ta configuration de base de données
require "../../../config/configuration.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID manquant.']);
    exit;
}

$id_commande = intval($_GET['id']);

try {
    // 2. Récupérer les informations principales de la commande
    $queryCmd = "SELECT id_commande, id_client, delai, date_livraison, statut FROM commande WHERE id_commande = ?";
    $stmtCmd = mysqli_prepare($conn, $queryCmd);
    mysqli_stmt_bind_param($stmtCmd, "i", $id_commande);
    mysqli_stmt_execute($stmtCmd);
    $resultCmd = mysqli_stmt_get_result($stmtCmd);
    $commande = mysqli_fetch_assoc($resultCmd);

    if (!$commande) {
        echo json_encode(['success' => false, 'message' => 'Commande introuvable.']);
        exit;
    }

    // 3. Récupérer les produits associés à cette commande (pour le panier virtuel)
    $queryProd = "SELECT co.id_produit, p.reference, co.Quantite_produit AS quantite 
                  FROM concerner co
                  JOIN produit p ON co.id_produit = p.id_produit
                  WHERE co.id_commande = ?";
    $stmtProd = mysqli_prepare($conn, $queryProd);
    mysqli_stmt_bind_param($stmtProd, "i", $id_commande);
    mysqli_stmt_execute($stmtProd);
    $resultProd = mysqli_stmt_get_result($stmtProd);
    
    $produits = [];
    while ($row = mysqli_fetch_assoc($resultProd)) {
        $produits[] = [
            'id_produit' => $row['id_produit'],
            'reference'  => $row['reference'],
            'quantite'   => intval($row['quantite'])
        ];
    }

    // 4. On renvoie le tout au format attendu par le JavaScript de la modale
    echo json_encode([
        'success' => true,
        'commande' => $commande,
        'produits' => $produits
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération : ' . $e->getMessage()
    ]);
}
exit;