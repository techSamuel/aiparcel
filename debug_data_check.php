<?php
require_once 'api/config.php';
header('Content-Type: text/plain');

try {
    echo "--- Users ---\n";
    $stmt = $pdo->query("SELECT id, email, display_name FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $u) {
        echo "ID: {$u['id']} | Email: {$u['email']}\n";

        $stmt_p = $pdo->prepare("SELECT COUNT(*) FROM parses WHERE user_id = ?");
        $stmt_p->execute([$u['id']]);
        $p_count = $stmt_p->fetchColumn();
        echo "  -> Parses Count: $p_count\n";

        if ($p_count > 0) {
            $stmt_last = $pdo->prepare("SELECT id, method, LENGTH(data) as data_len, timestamp FROM parses WHERE user_id = ? ORDER BY timestamp DESC LIMIT 1");
            $stmt_last->execute([$u['id']]);
            $last = $stmt_last->fetch(PDO::FETCH_ASSOC);
            echo "  -> Last Parse: ID {$last['id']} ({$last['method']}) @ {$last['timestamp']} (Data Len: {$last['data_len']})\n";
        }
    }

    echo "\n--- Parses Table Total ---\n";
    $stmt_all = $pdo->query("SELECT COUNT(*) FROM parses");
    echo "Total Parses: " . $stmt_all->fetchColumn() . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
