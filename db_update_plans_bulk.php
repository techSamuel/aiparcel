<?php
require_once 'api/config.php';

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM plans LIKE 'bulk_parse_limit'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE plans ADD COLUMN bulk_parse_limit INT DEFAULT 30 AFTER ai_parsing_limit");
        echo "Column 'bulk_parse_limit' added successfully.<br>";
    } else {
        echo "Column 'bulk_parse_limit' already exists.<br>";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>