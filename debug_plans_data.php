<?php
require_once 'api/config.php';
header('Content-Type: text/plain');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "--- Plans Table Data ---\n";
    $stmt = $pdo->query("SELECT id, name, bulk_parse_limit FROM plans");
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($plans as $plan) {
        echo "ID: " . $plan['id'] . " | Name: " . $plan['name'] . " | Bulk Limit: " . ($plan['bulk_parse_limit'] ?? 'NULL') . "\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>