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
$conversationId = intval($_GET['id'] ?? 0);

$check = mysqli_prepare($conn, "SELECT id_conversation FROM conversation WHERE id_conversation = ? AND id_user = ?");
mysqli_stmt_bind_param($check, "ii", $conversationId, $userId);
mysqli_stmt_execute($check);
if (!mysqli_stmt_get_result($check)->fetch_assoc()) {
    http_response_code(403);
    echo json_encode(["error" => "Accès refusé"]);
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT id_message, role, contenu FROM message WHERE id_conversation = ? ORDER BY date_envoi ASC");
mysqli_stmt_bind_param($stmt, "i", $conversationId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$messages = [];
while ($row = mysqli_fetch_assoc($result)) {
    $messages[] = $row;
}

echo json_encode(["messages" => $messages]);