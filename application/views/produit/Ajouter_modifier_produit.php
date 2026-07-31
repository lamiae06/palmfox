<?php 
    require "../../../config/configuration.php";

    if (isset($_POST["produit_id"]) && isset($_POST["formCode"]) && isset($_POST["formReference"]) && isset($_POST["formDescription"]) && isset($_POST["formQty"])) {
        $produit_id = (int) $_POST["produit_id"];
        $code = htmlspecialchars($_POST["formCode"]);
        $reference = htmlspecialchars($_POST["formReference"]);
        $desc = htmlspecialchars($_POST["formDescription"]);
        $q_stock = (int) $_POST["formQty"];

        $image = "";

        // Traitement de l'upload de fichier réel
        if (isset($_FILES["formImage"]) && $_FILES["formImage"]["error"] === 0) {
            $allowed_extensions = ["jpg", "jpeg", "png", "pdf"];
            $filename = $_FILES["formImage"]["name"];
            $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($file_ext, $allowed_extensions)) {
                $new_filename = uniqid("prod_", true) . "." . $file_ext;
                $upload_dir = "../../public/uploads/";
                $destination = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES["formImage"]["tmp_name"], $destination)) {
                    $image = $new_filename;
                }
            }
        }

        // MODE : AJOUT
        if (!$produit_id) {
            if (empty($code) || empty($reference) || $_POST["formQty"] === "" || $q_stock < 0) {
                header("Location: produits.php");
                exit();
            }

            $sql = "INSERT INTO produit (reference, code, description, quantite_stock, Image_pdf) 
            VALUES(?, ?, ?, ?, ?)";

            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) { die("An error occurred: " . mysqli_error($conn)); }
            
            mysqli_stmt_bind_param($stmt, "sssis", $reference, $code, $desc, $q_stock, $image);
            $ok = mysqli_stmt_execute($stmt);
            if (!$ok) { die("An error occurred: " . mysqli_stmt_error($stmt)); }
            
            mysqli_stmt_close($stmt);
            mysqli_close($conn);

        // MODE : MODIFICATION
        } else {
            if (empty($code) || empty($reference) || $_POST["formQty"] === "" || $q_stock < 0) {
                header("Location: produits.php");
                exit();
            }

            // Si aucun nouveau fichier n'est chargé, on garde l'ancien nom de fichier
            if (empty($image)) {
                $query_old = "SELECT Image_pdf FROM produit WHERE id_produit = ?";
                $stmt_old = mysqli_prepare($conn, $query_old);
                mysqli_stmt_bind_param($stmt_old, "i", $produit_id);
                mysqli_stmt_execute($stmt_old);
                mysqli_stmt_bind_result($stmt_old, $old_image);
                mysqli_stmt_fetch($stmt_old);
                mysqli_stmt_close($stmt_old);
                
                $image = $old_image; 
            }

            $sql = "UPDATE produit 
            SET reference = ?, code = ?, description = ?, quantite_stock = ?, Image_pdf = ? 
            WHERE id_produit = ?";

            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) { die("An error occurred: " . mysqli_error($conn)); }
            
            mysqli_stmt_bind_param($stmt, "sssisi", $reference, $code, $desc, $q_stock, $image, $produit_id);
            $ok = mysqli_stmt_execute($stmt);
            if (!$ok) { die("An error occurred: " . mysqli_stmt_error($stmt)); }

            mysqli_stmt_close($stmt);
            mysqli_close($conn);
        }
    }

    header("Location: produits.php");
    exit();
?>