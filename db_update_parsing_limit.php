<?php
require_once 'api/config.php';

try {
    // Add ai_parsing_limit to plans
    $pdo->exec("ALTER TABLE plans ADD COLUMN ai_parsing_limit INT DEFAULT 0 AFTER order_limit_daily");
    echo "Added ai_parsing_limit to plans table.\n";
} catch (Exception $e) {
    echo "Column ai_parsing_limit likely exists or error: " . $e->getMessage() . "\n";
}

try {
    // Add monthly_ai_parsed_count to users
    $pdo->exec("ALTER TABLE users ADD COLUMN monthly_ai_parsed_count INT DEFAULT 0 AFTER monthly_order_count");
    echo "Added monthly_ai_parsed_count to users table.\n";
} catch (Exception $e) {
    echo "Column monthly_ai_parsed_count likely exists or error: " . $e->getMessage() . "\n";
}
echo "Database update complete.\n";
