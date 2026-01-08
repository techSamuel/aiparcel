<?php
require_once 'api/config.php';
session_start();
header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

try {
    // Fetch user plan data
    $stmt_user = $pdo->prepare("
        SELECT u.plan_id, p.name as plan_name, p.order_limit_daily, 
               (p.order_limit_monthly + IFNULL(u.extra_order_limit, 0)) as order_limit_monthly, 
               (p.ai_parsing_limit + IFNULL(u.extra_ai_parsed_limit, 0)) as ai_parsing_limit,
               p.bulk_parse_limit,
               p.can_parse_ai, p.can_autocomplete, p.can_check_risk, p.can_correct_address, p.can_show_ads,
               u.can_manual_parse,
               u.plan_expiry_date, u.daily_order_count, u.monthly_order_count, u.monthly_ai_parsed_count
        FROM users u
        JOIN plans p ON u.plan_id = p.id
        WHERE u.id = ?
    ");
    $stmt_user->execute([$user_id]);
    $data = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        echo json_encode(['error' => 'User not found', 'user_id' => $user_id]);
        exit;
    }

    // Fetch stores
    $stmt_stores = $pdo->prepare("SELECT id, store_name, courier_type FROM stores WHERE user_id = ?");
    $stmt_stores->execute([$user_id]);
    $stores_arr = $stmt_stores->fetchAll(PDO::FETCH_ASSOC);
    $stores_obj = [];
    foreach ($stores_arr as $store) {
        $stores_obj[$store['id']] = [
            'store_name' => $store['store_name'],
            'courier_type' => $store['courier_type']
        ];
    }
    $data['stores'] = $stores_obj;
    $data['stores_count'] = count($stores_arr);
    $data['stores_raw'] = $stores_arr;

    echo json_encode($data, JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
}
