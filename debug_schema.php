<?php
require_once 'api/config.php';

header('Content-Type: text/plain');

try {
    $stmt = $pdo->prepare("DESCRIBE parses");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $col) {
        echo $col['Field'] . ": " . $col['Type'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
