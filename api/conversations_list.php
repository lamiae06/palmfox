<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . "/../config/configuration.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Non connecté"]);
    exit;
}

$userId = $_SESSION['user_id'];

$query = "SELECT id_conversation, titre, date_creation 
          FROM conversation 
          WHERE id_user = ? 
          ORDER BY date_creation DESC";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$conversations = [];
while ($row = mysqli_fetch_assoc($result)) {
    $conversations[] = $row;
}

echo json_encode(["conversations" => $conversations]);