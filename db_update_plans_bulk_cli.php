<?php
// Manual connection for CLI
$host = '127.0.0.1';
$db = 'u374415227_aiparcel';
$user = 'u374415227_aiparcel';
$pass = '$zUuj;eX1By';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "Combined successfully via 127.0.0.1.\n";

    $stmt = $pdo->query("SHOW COLUMNS FROM plans LIKE 'bulk_parse_limit'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE plans ADD COLUMN bulk_parse_limit INT DEFAULT 30 AFTER ai_parsing_limit");
        echo "Column 'bulk_parse_limit' added successfully.\n";
    } else {
        echo "Column 'bulk_parse_limit' already exists.\n";
    }
} catch (\PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>