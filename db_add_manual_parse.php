<?php
require_once 'api/config.php';
try {
    // Check if column exists first to avoid error on re-run
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'can_manual_parse'");
    if ($stmt->fetch()) {
        echo "Column 'can_manual_parse' already exists.";
    } else {
        $pdo->exec("ALTER TABLE users ADD COLUMN can_manual_parse TINYINT(1) DEFAULT 0");
        echo "Column 'can_manual_parse' added successfully.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>