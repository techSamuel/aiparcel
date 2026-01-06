<?php
require 'api/config.php';
header('Content-Type: text/plain');

$email = 'admin@aiparcel.com'; // Default admin? Or user provided?
// I'll just list all admins.
echo "Checking Admins...\n";

try {
    $stmt = $pdo->query("SELECT id, email, display_name, is_admin, password FROM users WHERE is_admin = 1");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($admins as $user) {
        echo "ID: " . $user['id'] . "\n";
        echo "Email: " . $user['email'] . "\n";
        echo "Is Admin: " . $user['is_admin'] . " (Type: " . gettype($user['is_admin']) . ")\n";
        echo "Password Hash Length: " . strlen($user['password']) . "\n";
        echo "-----------------\n";
    }
} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage();
}
?>