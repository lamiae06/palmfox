<?php 
    require "../../../config/configuration.php";

    if(isset($_POST["id_livraison"])) {
        $id_livraison = (int) $_POST["id_livraison"];
        if (empty($id_livraison)) {
            die("Missing id");
        }

         $sql = "DELETE FROM  livraison
         WHERE id_Livraison = ?";

            $stmt = mysqli_prepare($conn, $sql);

            if (!$stmt) {
                die("An error occurred: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($stmt, "i", $id_livraison);

            $ok = mysqli_stmt_execute($stmt);
            
            if (!$ok) {
                die("An error occurred: " . mysqli_stmt_error($stmt));
            }

            mysqli_stmt_close($stmt);
            mysqli_close($conn);

    }

    header("Location: livraisons.php");
    exit()
?>