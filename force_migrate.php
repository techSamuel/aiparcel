<?php
// Force Migration Script
// Access this via browser: http://localhost/your-project/force_migrate.php

require_once 'api/config.php';

header('Content-Type: text/plain');

echo "Attempting migration...\n";
echo "DB Host: " . DB_HOST . "\n";
echo "DB Name: " . DB_NAME . "\n";

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM plans LIKE 'bulk_parse_limit'");
    if ($stmt->rowCount() > 0) {
        echo "[INFO] Column 'bulk_parse_limit' ALREADY EXISTS.\n";
    } else {
        echo "[ACTION] Column missing. Attempting to add...\n";
        $pdo->exec("ALTER TABLE plans ADD COLUMN bulk_parse_limit INT DEFAULT 30 AFTER ai_parsing_limit");
        echo "[SUCCESS] Column 'bulk_parse_limit' ADDED successfully.\n";
    }

    // Check for other fields just in case
    $stmt2 = $pdo->query("SHOW COLUMNS FROM plans LIKE 'ai_parsing_limit'");
    if ($stmt2->rowCount() == 0) {
        echo "[ACTION] 'ai_parsing_limit' missing. Adding...\n";
        $pdo->exec("ALTER TABLE plans ADD COLUMN ai_parsing_limit INT DEFAULT 100 AFTER order_limit_daily");
    }

} catch (PDOException $e) {
    echo "[ERROR] Database Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "[ERROR] General Error: " . $e->getMessage() . "\n";
}

echo "Done.";
?>