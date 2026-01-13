<?php
require_once 'api/config.php';
header('Content-Type: application/json');

$uid = $_GET['uid'] ?? 1;

echo "Checking for User ID: $uid \n\n";

try {
    // 1. Check User Existence
    $stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmtUser->execute([$uid]);
    $user = $stmtUser->fetch();
    if (!$user) {
        die("User ID $uid not found in users table.");
    }
    echo "User found: " . $user['email'] . "\n\n";

    // 2. Check Parses
    $stmt = $pdo->prepare("SELECT * FROM parses WHERE user_id = ? ORDER BY timestamp DESC LIMIT 20");
    $stmt->execute([$uid]);
    $parses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Parse Count: " . count($parses) . "\n";
    if (count($parses) > 0) {
        echo "Sample Parse[0]: \n";
        print_r($parses[0]);
    } else {
        echo "No parses found for this user.\n";

        // Check filtering/count
        $count = $pdo->query("SELECT COUNT(*) FROM parses WHERE user_id = $uid")->fetchColumn();
        echo "Total count in DB for $uid: $count\n";
    }

    // 3. Check Orders
    // 4. Test Insert (To debug Save Issues)
    echo "\n--- Test Insert ---\n";
    try {
        $stmtTest = $pdo->prepare("INSERT INTO parses (user_id, method, data) VALUES (?, ?, ?)");
        $testData = json_encode([['test' => 'data', 'time' => time()]]);
        $stmtTest->execute([$uid, 'DEBUG_TEST', $testData]);
        echo "Insert Test: SUCCESS. A new record was added.\n";
    } catch (Exception $e) {
        echo "Insert Test: FAILED. Error: " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
