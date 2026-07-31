<?php 
    require "../../../config/configuration.php";

    if (isset($_POST["deliveryId"]) && isset($_POST["formCommande"]) && isset($_POST["formStatus"])) {
        $id_livraison = (int) $_POST["deliveryId"];
        $id_commande = (int) $_POST["formCommande"];
        $status = $_POST["formStatus"];

        if (empty($id_commande) || empty($status)) {
                header("Location: livraisons.php");
                exit();
        }

        if (!$id_livraison) {

            $sql = "INSERT INTO livraison (Statut, id_commande) 
            VALUES(?, ?)";

            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) { die("An error occurred: " . mysqli_error($conn)); }
            
            mysqli_stmt_bind_param($stmt, "si", $status, $id_commande);
            $ok = mysqli_stmt_execute($stmt);
            if (!$ok) { die("An error occurred: " . mysqli_stmt_error($stmt)); }
            
            mysqli_stmt_close($stmt);
            mysqli_close($conn);
        } else  {
            $sql = "UPDATE livraison 
            SET  Statut	 = ?, id_commande = ?
            WHERE id_Livraison = ?";

            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) { die("An error occurred: " . mysqli_error($conn)); }
            
            mysqli_stmt_bind_param($stmt, "sii", $status, $id_commande, $id_livraison);
            $ok = mysqli_stmt_execute($stmt);
            if (!$ok) { die("An error occurred: " . mysqli_stmt_error($stmt)); }

            mysqli_stmt_close($stmt);
            mysqli_close($conn);
        }
    }

    header("Location: livraisons.php");
    exit();
    
?>