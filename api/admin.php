<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
session_start();
require_once 'config.php';

// Decode JSON input from the request body if it exists
$input = json_decode(file_get_contents('php://input'), true);

// Determine the action. If it's a FormData request (like save_settings), 'action' will be in $_POST.
// If it's a JSON request, it will be in the decoded $input.
$action = $_POST['action'] ?? $input['action'] ?? $_GET['action'] ?? null;





// NEW: If the action is NOT 'login', then perform the security check.
// This allows the login request to be processed.
if ($action !== 'login') {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        json_response(['error' => 'Permission Denied. Session may have expired.'], 403);
    }
}



switch ($action) {
    case 'login':
        handle_login();
        break;
    case 'get_stats':
        handle_get_stats();
        break;
    case 'list_users':
        handle_list_users();
        break;
    case 'get_global_history':
        handle_get_global_history();
        break;
    case 'get_user_details':
        handle_get_user_details();
        break;
    case 'get_plans':
        handle_get_plans();
        break;
    case 'save_plan':
        handle_save_plan();
        break;
    case 'delete_plan':
        handle_delete_plan();
        break;
    case 'get_payment_methods':
        handle_get_payment_methods();
        break;
    case 'save_payment_method':
        handle_save_payment_method();
        break;
    case 'delete_payment_method':
        handle_delete_payment_method();
        break;
    case 'get_subscription_orders':
        handle_get_subscription_orders();
        break;
    case 'update_subscription_status':
        update_subscription_status();
        break;
    case 'update_user_role':
        handle_update_user_role();
        break;
    case 'get_settings':
        handle_get_settings();
        break;
    case 'save_settings':
        handle_save_settings();
        break;
    case 'update_profile':
        handle_update_profile();
        break;
    default:
        json_response(['error' => 'Invalid action'], 400);
}


function handle_update_profile()
{
    global $pdo, $input;

    // The user ID comes from the session, ensuring admins can only edit their own profile
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        json_response(['error' => 'Authentication required.'], 401);
    }

    if (isset($input['displayName'])) {
        $stmt = $pdo->prepare("UPDATE users SET display_name = ? WHERE id = ?");
        $stmt->execute([trim($input['displayName']), $user_id]);
    }

    if (isset($input['password']) && strlen($input['password']) >= 6) {
        $hashed_password = password_hash($input['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $user_id]);
    }

    json_response(['success' => true]);
}


function handle_login()
{
    global $pdo, $input;

    $email = filter_var($input['email'], FILTER_VALIDATE_EMAIL);
    $password = $input['password'] ?? '';

    if (!$email || empty($password)) {
        json_response(['error' => 'Email and password are required.'], 400);
    }

    $stmt = $pdo->prepare("SELECT id, password, display_name, email, is_admin FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password']) && $user['is_admin']) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['is_admin'] = true;

        json_response([
            'success' => true,
            'uid' => $user['id'],
            'email' => $user['email'],
            'displayName' => $user['display_name']
        ]);
    } else {
        json_response(['error' => 'Invalid admin credentials.'], 401);
    }
}

function handle_get_stats()
{
    global $pdo;
    $stmt_users = $pdo->query("SELECT COUNT(*) FROM users");
    $stmt_parses = $pdo->query("SELECT COUNT(*) FROM parses");
    $stmt_orders = $pdo->query("SELECT COUNT(*) FROM orders");
    json_response([
        'userCount' => $stmt_users->fetchColumn(),
        'parseCount' => $stmt_parses->fetchColumn(),
        'orderCount' => $stmt_orders->fetchColumn()
    ]);
}

function handle_list_users()
{
    global $pdo;
    try {
        $stmt = $pdo->query("
            SELECT u.id, u.email, u.display_name, u.is_verified, p.name as plan_name 
            FROM users u
            LEFT JOIN plans p ON u.plan_id = p.id
        ");
        json_response($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) {
        json_response(['error' => 'Database query for users failed.', 'details' => $e->getMessage()], 500);
    }
}

function handle_get_global_history()
{
    global $pdo, $input;
    $type = $input['type'];
    if (!in_array($type, ['parses', 'orders']))
        json_response(['error' => 'Invalid type'], 400);

    $stmt = $pdo->prepare("
        SELECT t.*, u.email as userEmail 
        FROM $type t 
        JOIN users u ON t.user_id = u.id 
        ORDER BY t.timestamp DESC LIMIT 50
    ");
    $stmt->execute();
    json_response($stmt->fetchAll());
}

function handle_get_user_details()
{
    global $pdo, $input;
    $target_uid = $input['uid'];
    $details = [];

    $stmt_stores = $pdo->prepare("SELECT * FROM stores WHERE user_id = ? LIMIT 20");
    $stmt_stores->execute([$target_uid]);
    $details['stores'] = $stmt_stores->fetchAll();

    $stmt_parses = $pdo->prepare("SELECT * FROM parses WHERE user_id = ? ORDER BY timestamp DESC LIMIT 20");
    $stmt_parses->execute([$target_uid]);
    $details['parses'] = $stmt_parses->fetchAll();

    $stmt_orders = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY timestamp DESC LIMIT 20");
    $stmt_orders->execute([$target_uid]);
    $details['orders'] = $stmt_orders->fetchAll();

    $stmt_plan = $pdo->prepare("
        SELECT u.plan_id, p.name as plan_name, u.plan_expiry_date, u.can_manual_parse 
        FROM users u 
        LEFT JOIN plans p ON u.plan_id = p.id 
        WHERE u.id = ?
    ");
    $stmt_plan->execute([$target_uid]);
    $details['plan'] = $stmt_plan->fetch(); // Contains plan info AND user-specific overrides like can_manual_parse

    json_response($details);
}

function handle_get_plans()
{
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM plans");
    json_response($stmt->fetchAll());
}

// In api/admin.php

function handle_save_plan()
{
    global $pdo, $input;
    $id = $input['id'] ?? null;

    // Prepare data, including new permissions
    // Prepare data, including new permissions
    $params = [
        $input['name'],
        $input['price'],
        $input['order_limit_monthly'],
        $input['order_limit_daily'],
        $input['ai_parsing_limit'] ?? 0, // NEW field
        $input['validity_days'],
        $input['description'],
        $input['is_active'],
        $input['can_parse_ai'] ?? 0,
        $input['can_autocomplete'] ?? 0,
        $input['can_check_risk'] ?? 0,
        $input['can_correct_address'] ?? 0,
        $input['can_show_ads'] ?? 0
    ];

    if ($id) {
        $sql = "UPDATE plans SET name=?, price=?, order_limit_monthly=?, order_limit_daily=?, ai_parsing_limit=?, validity_days=?, description=?, is_active=?, can_parse_ai=?, can_autocomplete=?, can_check_risk=?, can_correct_address=?, can_show_ads=? WHERE id=?";
        $params[] = $id;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        $sql = "INSERT INTO plans (name, price, order_limit_monthly, order_limit_daily, ai_parsing_limit, validity_days, description, is_active, can_parse_ai, can_autocomplete, can_check_risk, can_correct_address, can_show_ads) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
    json_response(['success' => true]);
}

function handle_delete_plan()
{
    global $pdo, $input;
    $stmt = $pdo->prepare("DELETE FROM plans WHERE id = ?");
    $stmt->execute([$input['id']]);
    json_response(['success' => true]);
}

function handle_get_payment_methods()
{
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM payment_methods");
    json_response($stmt->fetchAll());
}

function handle_save_payment_method()
{
    global $pdo, $input;
    $id = $input['id'] ?? null;
    if ($id) {
        $stmt = $pdo->prepare("UPDATE payment_methods SET name=?, account_details=?, instructions=?, is_active=? WHERE id=?");
        $stmt->execute([$input['name'], $input['account_details'], $input['instructions'], $input['is_active'], $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO payment_methods (name, account_details, instructions, is_active) VALUES (?, ?, ?, ?)");
        $stmt->execute([$input['name'], $input['account_details'], $input['instructions'], $input['is_active']]);
    }
    json_response(['success' => true]);
}

function handle_delete_payment_method()
{
    global $pdo, $input;
    $stmt = $pdo->prepare("DELETE FROM payment_methods WHERE id = ?");
    $stmt->execute([$input['id']]);
    json_response(['success' => true]);
}

function handle_get_subscription_orders()
{
    global $pdo;
    $stmt = $pdo->query("
        SELECT s.*, u.email as user_email, p.name as plan_name, pm.name as payment_method_name
        FROM subscriptions s
        JOIN users u ON s.user_id = u.id
        JOIN plans p ON s.plan_id = p.id
        JOIN payment_methods pm ON s.payment_method_id = pm.id
        ORDER BY s.created_at DESC
    ");
    json_response($stmt->fetchAll());
}


function update_subscription_status()
{
    global $pdo, $input; // <-- ADD THIS LINE

    $sub_id = $input['id'];
    $status = $input['status']; // 'approved' or 'rejected'

    $pdo->beginTransaction();
    try {
        // ... the rest of the function remains the same
        // Update the subscription record
        $stmt = $pdo->prepare("UPDATE subscriptions SET status = ? WHERE id = ?");
        $stmt->execute([$status, $sub_id]);

        // If approved, update the user's plan
        if ($status === 'approved') {
            // Get subscription and plan details
            $stmt_sub = $pdo->prepare("SELECT user_id, plan_id FROM subscriptions WHERE id = ?");
            $stmt_sub->execute([$sub_id]);
            $sub = $stmt_sub->fetch();

            $stmt_plan = $pdo->prepare("SELECT validity_days FROM plans WHERE id = ?");
            $stmt_plan->execute([$sub['plan_id']]);
            $plan = $stmt_plan->fetch();

            // Calculate new expiry date
            $expiry_date = (new DateTime())->modify('+' . $plan['validity_days'] . ' days')->format('Y-m-d');

            // Update user record
            $stmt_user = $pdo->prepare("UPDATE users SET plan_id = ?, plan_expiry_date = ?, monthly_order_count = 0, daily_order_count = 0, last_reset_date = CURDATE() WHERE id = ?");
            $stmt_user->execute([$sub['plan_id'], $expiry_date, $sub['user_id']]);

            // Send confirmation email to user
            $stmt_user_email = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $stmt_user_email->execute([$sub['user_id']]);
            $user_email = $stmt_user_email->fetchColumn();

            // json_response(['plan_id' => $sub['plan_id']]);

            // Assuming PHPMailer is set up correctly in config.php or similar
            sendSubscriptionConfirmationEmail($user_email, $sub['plan_id'], $expiry_date);

        }
        $pdo->commit();
        json_response(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        json_response(['error' => 'Failed to update status: ' . $e->getMessage()], 500);
    }
}

function handle_update_user_role()
{
    global $pdo, $input;
    $target_uid = $input['uid'];

    // Check if updating can_manual_parse
    if (isset($input['canManualParse'])) {
        $can_manual_parse = (int) $input['canManualParse'];
        $stmt = $pdo->prepare("UPDATE users SET can_manual_parse = ? WHERE id = ?");
        $stmt->execute([$can_manual_parse, $target_uid]);
    }

    // Legacy support for is_premium if needed, though mostly moved to plans now.
    if (isset($input['isPremium'])) {
        $is_premium = (bool) $input['isPremium'];
        $stmt = $pdo->prepare("UPDATE users SET is_premium = ? WHERE id = ?");
        $stmt->execute([$is_premium, $target_uid]);
    }

    json_response(['success' => true]);
}

function handle_get_settings()
{
    global $pdo;
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    foreach ($stmt->fetchAll() as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    json_response([
        'geminiApiKey' => $settings['gemini_api_key'] ?? '',
        'barikoiApiKey' => $settings['barikoi_api_key'] ?? '',
        'googleMapsApiKey' => $settings['google_maps_api_key'] ?? '',
        'googleClientId' => $settings['google_client_id'] ?? '',
        'googleClientSecret' => $settings['google_client_secret'] ?? '',
        'autocompleteService' => $settings['autocomplete_service'] ?? 'barikoi',
        'showAiParseButton' => $settings['show_ai_parse_button'] ?? '1',
        'showAutocompleteButton' => $settings['show_autocomplete_button'] ?? '1',
        'appName' => $settings['app_name'] ?? 'CourierPlus',
        'appLogoUrl' => $settings['app_logo_url'] ?? '',
        'ezoicPlaceholderId' => $settings['ezoic_placeholder_id'] ?? '', // Add this
        'helpContent' => $settings['help_content'] ?? '' // Add this line
    ]);
}

function handle_save_settings()
{
    global $pdo;
    $pdo->beginTransaction();
    try {
        $settings_to_save = [
            'app_name' => $_POST['appName'] ?? 'CourierPlus',
            'gemini_api_key' => $_POST['geminiApiKey'] ?? '',
            'barikoi_api_key' => $_POST['barikoiApiKey'] ?? '',
            'google_maps_api_key' => $_POST['googleMapsApiKey'] ?? '',
            'google_client_id' => $_POST['googleClientId'] ?? '',
            'google_client_secret' => $_POST['googleClientSecret'] ?? '',
            'autocomplete_service' => $_POST['autocompleteService'] ?? 'barikoi',
            'show_ai_parse_button' => $_POST['showAiParseButton'] ?? '1',
            'show_autocomplete_button' => $_POST['showAutocompleteButton'] ?? '1',
            'ezoic_placeholder_id' => $_POST['ezoicPlaceholderId'] ?? '', // Add this
            'help_content' => $_POST['helpContent'] ?? ''
        ];

        if (isset($_FILES['appLogoFile']) && $_FILES['appLogoFile']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir))
                mkdir($upload_dir, 0755, true);
            $file_extension = pathinfo($_FILES['appLogoFile']['name'], PATHINFO_EXTENSION);
            $new_filename = 'logo.' . $file_extension;
            $upload_file = $upload_dir . $new_filename;
            if (move_uploaded_file($_FILES['appLogoFile']['tmp_name'], $upload_file)) {
                $settings_to_save['app_logo_url'] = 'api/' . $upload_file;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value = :value");
        foreach ($settings_to_save as $key => $value) {
            $stmt->execute(['key' => $key, 'value' => $value]);
        }

        $pdo->commit();
        json_response(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        json_response(['error' => 'Failed to save settings: ' . $e->getMessage()], 500);
    }
}


// --- NEW HELPER FUNCTION FOR SUBSCRIPTION EMAIL ---
function sendSubscriptionConfirmationEmail($email, $plan_id, $expiry_date)
{
    global $pdo;

    // Fetch required data
    $stmt_plan = $pdo->prepare("SELECT name FROM plans WHERE id = ?");
    $stmt_plan->execute([$plan_id]);
    $plan_name = $stmt_plan->fetchColumn();

    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('app_name', 'app_logo_url')");
    $settings = array_column($stmt_settings->fetchAll(), 'setting_value', 'setting_key');
    $appName = $settings['app_name'] ?? 'CourierPlus';
    $logoUrl = ($settings['app_logo_url'] ?? '') ? APP_URL . '/' . $settings['app_logo_url'] : '';

    require_once 'src/Exception.php';
    require_once 'src/PHPMailer.php';
    require_once 'src/SMTP.php';

    $mail = new PHPMailer(true);

    // Load the new HTML template
    $body = file_get_contents('user_subscription_activated.html');

    // Replace placeholders
    $replacements = [
        '{{appName}}' => $appName,
        '{{logoUrl}}' => $logoUrl,
        '{{planName}}' => $plan_name,
        '{{expiryDate}}' => date("F j, Y", strtotime($expiry_date)), // Format date nicely
        '{{dashboardLink}}' => APP_URL
    ];
    $body = str_replace(array_keys($replacements), array_values($replacements), $body);

    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = (SMTP_SECURE === 'tls') ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, $appName);
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Your Subscription on ' . $appName . ' is Active!';
        $mail->Body = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error for subscription confirmation {$email}: {$mail->ErrorInfo}");
        return false;
    }
}

// ALSO, update the call to this function in the 'update_subscription_status' function:
// from: sendSubscriptionConfirmationEmail($user_email, $sub['plan_id']);
// to:   sendSubscriptionConfirmationEmail($user_email, $sub['plan_id'], $expiry_date);

