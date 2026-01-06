<?php
require_once 'api/config.php';

try {
    // Add extra_order_limit column
    $pdo->exec("ALTER TABLE users ADD COLUMN extra_order_limit INT DEFAULT 0 AFTER monthly_order_count");
    echo "Added extra_order_limit column.\n";
} catch (PDOException $e) {
    echo "extra_order_limit column might already exist or error: " . $e->getMessage() . "\n";
}

try {
    // Add extra_ai_parsed_limit column
    $pdo->exec("ALTER TABLE users ADD COLUMN extra_ai_parsed_limit INT DEFAULT 0 AFTER monthly_ai_parsed_count");
    echo "Added extra_ai_parsed_limit column.\n";
} catch (PDOException $e) {
    echo "extra_ai_parsed_limit column might already exist or error: " . $e->getMessage() . "\n";
}

echo "Database update completed successfully.";
?>