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
$data = json_decode(file_get_contents("php://input"), true);
$conversationId = intval($data['conversation_id'] ?? 0);

// Vérifie que la conversation appartient bien à l'utilisateur connecté
$check = mysqli_prepare($conn, "SELECT id_conversation FROM conversation WHERE id_conversation = ? AND id_user = ?");
mysqli_stmt_bind_param($check, "ii", $conversationId, $userId);
mysqli_stmt_execute($check);
if (!mysqli_stmt_get_result($check)->fetch_assoc()) {
    http_response_code(403);
    echo json_encode(["error" => "Accès refusé"]);
    exit;
}

// Suppression (les messages liés partent automatiquement grâce à ON DELETE CASCADE)
$stmt = mysqli_prepare($conn, "DELETE FROM conversation WHERE id_conversation = ?");
mysqli_stmt_bind_param($stmt, "i", $conversationId);
mysqli_stmt_execute($stmt);

echo json_encode(["success" => true]);