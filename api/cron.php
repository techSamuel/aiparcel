<?php
// api/cron.php - Daily Maintenance Script
// Run this script once every 24 hours via Cron Job or similar scheduler
// Example Cron: 0 0 * * * php /path/to/api/cron.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/func_email.php';

header('Content-Type: application/json');

$today = date('Y-m-d');
$log = [];

try {
    // --- 1. Auto-Demotion (Expired Plans) ---
    // Find Free Plan ID
    $stmt_free = $pdo->prepare("SELECT id FROM plans WHERE name = 'Free' LIMIT 1");
    $stmt_free->execute();
    $free_plan_id = $stmt_free->fetchColumn();

    if ($free_plan_id) {
        // Find users expired BEFORE today and NOT Free
        $stmt_expired = $pdo->prepare("SELECT id, email, display_name FROM users WHERE plan_expiry_date < ? AND plan_id != ?");
        $stmt_expired->execute([$today, $free_plan_id]);
        $expired_users = $stmt_expired->fetchAll();

        foreach ($expired_users as $user) {
            // Demote to Free, Reset EVERYTHING (Usage, Extra Limits, Alerts)
            $stmt_update = $pdo->prepare("UPDATE users SET 
                plan_id = ?, 
                plan_expiry_date = NULL,
                monthly_order_count = 0, 
                monthly_ai_parsed_count = 0, 
                daily_order_count = 0, 
                extra_order_limit = 0, 
                extra_ai_parsed_limit = 0, 
                alert_usage_order_75 = 0, 
                alert_usage_order_90 = 0, 
                alert_usage_ai_75 = 0, 
                alert_usage_ai_90 = 0, 
                last_reset_date = ? 
                WHERE id = ?");
            $stmt_update->execute([$free_plan_id, $today, $user['id']]);

            // Send Email
            $subject = "Your Subscription has Expired";
            $msg = "<p>Your subscription plan validity has expired.</p>
                    <p>You have been automatically moved to the <strong>Free Plan</strong>.</p>
                    <p>To continue using premium limits and features, please <a href='" . APP_URL . "'>upgrade your plan</a>.</p>";
            $html = wrapInEmailTemplate($subject, $msg, $pdo);
            sendSystemEmail($user['email'], $subject, $html);

            $log[] = "Demoted User ID {$user['id']} to Free.";
        }
    }

    // --- 2. Expiry Warnings (7, 3, 1 Days) ---
    $warning_days = [7, 3, 1];
    foreach ($warning_days as $days) {
        $target_date = date('Y-m-d', strtotime("+$days days"));
        // Find users expiring exactly on target date and NOT Free
        $stmt_warn = $pdo->prepare("SELECT id, email, display_name, plan_expiry_date FROM users WHERE plan_expiry_date = ? AND plan_id != ?");
        $stmt_warn->execute([$target_date, $free_plan_id]);
        $users_to_warn = $stmt_warn->fetchAll();

        foreach ($users_to_warn as $user) {
            $subject = "Action Required: Plan Expires in $days Days";
            $msg = "<p>Hello " . htmlspecialchars($user['display_name']) . ",</p>
                     <p>This is a reminder that your subscription plan will expire on <strong>" . date('d/m/Y', strtotime($user['plan_expiry_date'])) . "</strong> ($days days remaining).</p>
                     <p>Please renew your subscription now to avoid service interruption or demotion to the Free plan.</p>
                     <p><a href='" . APP_URL . "' class='button'>Renew Subscription</a></p>";
            $html = wrapInEmailTemplate($subject, $msg, $pdo);
            sendSystemEmail($user['email'], $subject, $html);
            $log[] = "Sent $days-day warning to User ID {$user['id']}.";
        }
    }

    echo json_encode(['success' => true, 'log' => $log]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
