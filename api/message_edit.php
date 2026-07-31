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
$messageId = intval($data['message_id'] ?? 0);

$stmt = mysqli_prepare($conn, "
    SELECT m.id_conversation, m.date_envoi 
    FROM message m
    JOIN conversation c ON m.id_conversation = c.id_conversation
    WHERE m.id_message = ? AND c.id_user = ?
");
mysqli_stmt_bind_param($stmt, "ii", $messageId, $userId);
mysqli_stmt_execute($stmt);
$row = mysqli_stmt_get_result($stmt)->fetch_assoc();

if (!$row) {
    http_response_code(403);
    echo json_encode(["error" => "Accès refusé"]);
    exit;
}

$del = mysqli_prepare($conn, "DELETE FROM message WHERE id_conversation = ? AND date_envoi >= ?");
mysqli_stmt_bind_param($del, "is", $row['id_conversation'], $row['date_envoi']);
mysqli_stmt_execute($del);

echo json_encode(["success" => true, "conversation_id" => $row['id_conversation']]);