<?php 
    require "../../../config/configuration.php";

    if(isset($_POST["produit_id_delete"])) {
        $produit_id = (int) $_POST["produit_id_delete"];
        if (empty($produit_id)) {
            die("Missing id");
        }

         $sql = "DELETE FROM  produit
         WHERE id_produit = ?";

            $stmt = mysqli_prepare($conn, $sql);

            if (!$stmt) {
                die("An error occurred: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($stmt, "i", $produit_id);

            $ok = mysqli_stmt_execute($stmt);
            
            if (!$ok) {
                die("An error occurred: " . mysqli_stmt_error($stmt));
            }

            mysqli_stmt_close($stmt);
            mysqli_close($conn);

    }

    header("Location: produits.php");
    exit()
?>