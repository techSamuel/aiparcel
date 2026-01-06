<?php
// api/cron.php - Daily Maintenance Script
ob_start(); // Buffer output to prevent JSON corruption from PHPMailer warnings
// Run this script once every 24 hours via Cron Job or similar scheduler
// Example Cron: 0 0 * * * php /path/to/api/cron.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/func_email.php';

header('Content-Type: application/json');

$today = date('Y-m-d');
$log = [];

try {
    // --- 0. Schema Self-Check (Auto-Migrate) ---
    // Ensure all required columns exist to prevent crashes
    try {
        $stmt_check = $pdo->query("SHOW COLUMNS FROM users LIKE 'extra_order_limit'");
        if ($stmt_check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN extra_order_limit INT DEFAULT 0 AFTER monthly_order_count");
            $pdo->exec("ALTER TABLE users ADD COLUMN extra_ai_parsed_limit INT DEFAULT 0 AFTER monthly_ai_parsed_count");
        }
        $stmt_check_alerts = $pdo->query("SHOW COLUMNS FROM users LIKE 'alert_usage_order_75'");
        if ($stmt_check_alerts->rowCount() == 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN alert_usage_order_75 TINYINT DEFAULT 0");
            $pdo->exec("ALTER TABLE users ADD COLUMN alert_usage_order_90 TINYINT DEFAULT 0");
            $pdo->exec("ALTER TABLE users ADD COLUMN alert_usage_ai_75 TINYINT DEFAULT 0");
            $pdo->exec("ALTER TABLE users ADD COLUMN alert_usage_ai_90 TINYINT DEFAULT 0");
        }
    } catch (Exception $mig_err) { /* Ignore migration errors, proceed */
    }

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
            $sent = sendSystemEmail($user['email'], $subject, $html);

            $log[] = "Demoted User ID {$user['id']} to Free. Email Sent: " . ($sent ? 'Yes' : 'No');
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
            $sent = sendSystemEmail($user['email'], $subject, $html);
            $log[] = "Sent $days-day warning to User ID {$user['id']}. Email Sent: " . ($sent ? 'Yes' : 'No');
        }
    }

    // --- 3. Usage Alerts (75% / 90%) Failsafe ---
    $stmt_usage = $pdo->query("
        SELECT u.id, u.email, u.display_name, 
               u.monthly_order_count, u.extra_order_limit, u.monthly_ai_parsed_count, u.extra_ai_parsed_limit,
               u.alert_usage_order_75, u.alert_usage_order_90, u.alert_usage_ai_75, u.alert_usage_ai_90,
               p.order_limit_monthly, p.ai_parsing_limit
        FROM users u
        JOIN plans p ON u.plan_id = p.id
        WHERE u.alert_usage_order_90 = 0 OR u.alert_usage_ai_90 = 0 OR u.alert_usage_order_75 = 0 OR u.alert_usage_ai_75 = 0
    ");

    while ($row = $stmt_usage->fetch(PDO::FETCH_ASSOC)) {
        // --- Order Usage ---
        $total_order_limit = $row['order_limit_monthly'] + $row['extra_order_limit'];
        if ($total_order_limit > 0) {
            $order_percent = ($row['monthly_order_count'] / $total_order_limit) * 100;

            // 75% Order Alert
            if ($order_percent >= 75 && $row['alert_usage_order_75'] == 0) {
                $subject = "Usage Alert: 75% Order Limit Reached";
                $msg = "<p>Hello " . htmlspecialchars($row['display_name']) . ",</p><p>You have used <strong>75%</strong> of your monthly order limit.</p><p><a href='" . APP_URL . "'>Check Dashboard</a></p>";
                $html = wrapInEmailTemplate($subject, $msg, $pdo);
                $sent = sendSystemEmail($row['email'], $subject, $html);
                if ($sent) {
                    $pdo->prepare("UPDATE users SET alert_usage_order_75 = 1 WHERE id = ?")->execute([$row['id']]);
                    $log[] = "Sent 75% Order Alert to User ID {$row['id']}.";
                }
            }
            // 90% Order Alert
            if ($order_percent >= 90 && $row['alert_usage_order_90'] == 0) {
                $subject = "Usage Alert: 90% Order Limit Reached";
                $msg = "<p>Hello " . htmlspecialchars($row['display_name']) . ",</p><p>You have used <strong>90%</strong> of your monthly order limit. Please upgrade soon.</p><p><a href='" . APP_URL . "'>Upgrade Now</a></p>";
                $html = wrapInEmailTemplate($subject, $msg, $pdo);
                $sent = sendSystemEmail($row['email'], $subject, $html);
                if ($sent) {
                    $pdo->prepare("UPDATE users SET alert_usage_order_90 = 1 WHERE id = ?")->execute([$row['id']]);
                    $log[] = "Sent 90% Order Alert to User ID {$row['id']}.";
                }
            }
        }

        // --- AI Usage ---
        $total_ai_limit = $row['ai_parsing_limit'] + $row['extra_ai_parsed_limit'];
        if ($total_ai_limit > 0) {
            $ai_percent = ($row['monthly_ai_parsed_count'] / $total_ai_limit) * 100;

            // 75% AI Alert
            if ($ai_percent >= 75 && $row['alert_usage_ai_75'] == 0) {
                $subject = "Usage Alert: 75% AI Parsing Limit Reached";
                $msg = "<p>Hello " . htmlspecialchars($row['display_name']) . ",</p><p>You have used <strong>75%</strong> of your monthly AI parsing limit.</p><p><a href='" . APP_URL . "'>Check Dashboard</a></p>";
                $html = wrapInEmailTemplate($subject, $msg, $pdo);
                $sent = sendSystemEmail($row['email'], $subject, $html);
                if ($sent) {
                    $pdo->prepare("UPDATE users SET alert_usage_ai_75 = 1 WHERE id = ?")->execute([$row['id']]);
                    $log[] = "Sent 75% AI Alert to User ID {$row['id']}.";
                }
            }
            // 90% AI Alert
            if ($ai_percent >= 90 && $row['alert_usage_ai_90'] == 0) {
                $subject = "Usage Alert: 90% AI Parsing Limit Reached";
                $msg = "<p>Hello " . htmlspecialchars($row['display_name']) . ",</p><p>You have used <strong>90%</strong> of your monthly AI parsing limit.</p><p><a href='" . APP_URL . "'>Upgrade Now</a></p>";
                $html = wrapInEmailTemplate($subject, $msg, $pdo);
                $sent = sendSystemEmail($row['email'], $subject, $html);
                if ($sent) {
                    $pdo->prepare("UPDATE users SET alert_usage_ai_90 = 1 WHERE id = ?")->execute([$row['id']]);
                    $log[] = "Sent 90% AI Alert to User ID {$row['id']}.";
                }
            }
        }
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
