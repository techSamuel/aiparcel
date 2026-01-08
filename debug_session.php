<?php
require_once 'api/config.php';
session_start();
header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;

echo json_encode([
    'user_id' => $user_id,
    'session' => $_SESSION
], JSON_PRETTY_PRINT);
