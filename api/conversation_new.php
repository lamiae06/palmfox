<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . "/../config/configuration.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Non connecté"]);
    exit;
}

echo json_encode(["success" => true]);