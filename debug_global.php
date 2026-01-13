<?php
require_once 'api/config.php';

header('Content-Type: text/plain');

echo "--- Debugging Global History Logic ---\n";

try {
    $type = 'parses';
    echo "Querying table: $type\n";

    $sql = "
        SELECT t.*, u.email as userEmail 
        FROM $type t 
        JOIN users u ON t.user_id = u.id 
        ORDER BY t.timestamp DESC LIMIT 50
    ";

    echo "SQL: $sql\n\n";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Count: " . count($results) . "\n";
    if (count($results) > 0) {
        echo "First Record:\n";
        print_r($results[0]);
    } else {
        echo "No records found.\n";

        // Debug: Check table contents separately
        echo "\n--- Checking 'parses' table directly ---\n";
        $stmt2 = $pdo->query("SELECT * FROM parses LIMIT 5");
        $parses = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        echo "Parses count: " . count($parses) . "\n";
        if (count($parses) > 0)
            print_r($parses[0]);

        echo "\n--- Checking 'users' table sample ---\n";
        // Check if user_id from parse exists
        if (count($parses) > 0) {
            $uid = $parses[0]['user_id'];
            echo "Checking user_id: $uid\n";
            $stmt3 = $pdo->prepare("SELECT id, email FROM users WHERE id = ?");
            $stmt3->execute([$uid]);
            print_r($stmt3->fetch(PDO::FETCH_ASSOC));
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
