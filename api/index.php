<?php
// Set session cookie parameters specifically to root path
session_set_cookie_params(0, '/');
session_start();
use PHPMailer\PHPMailer\PHPMailer;
require_once 'config.php';
require_once 'func_email.php';
require_once 'facebook_capi.php'; // Include CAPI Helper
use PHPMailer\PHPMailer\Exception;

// Decode JSON input from the request body
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? null;

// --- Authentication Handler ---save_parser_settings
if (in_array($action, ['register', 'login', 'logout', 'check_session', 'resend_verification', 'verify_code', 'request_password_reset', 'perform_password_reset', 'google_login_url'])) {
    handle_auth($action, $input, $pdo);
}

// --- Authenticated User Actions ---
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    // This is a whitelist of actions that DO NOT require a logged-in user.
    $public_actions = ['register', 'login', 'check_session', 'resend_verification', 'verify_code', 'load_user_data', 'request_password_reset', 'perform_password_reset', 'track_visitor'];

    if (!in_array($action, $public_actions)) {
        // If the action is not in our public whitelist, block it.
        json_response(['error' => 'Authentication required.'], 401);
    }
    // For public actions, we allow the script to continue.
}

switch ($action) {
    case 'track_visitor':
        track_visitor($user_id, $input, $pdo);
        break;
    case 'add_or_update_store':
        add_or_update_store($user_id, $input, $pdo);
        break;
    case 'delete_store':
        delete_store($user_id, $input, $pdo);
        break;
    case 'save_parse':
        save_history($user_id, 'parses', $input, $pdo);
        break;
    case 'get_history':
        get_user_history($user_id, $input, $pdo);
        break;
    case 'create_order':
        create_order($user_id, $input, $pdo);
        break;
    case 'update_profile':
        update_profile($user_id, $input, $pdo);
        break;
    case 'parse_with_ai':
        parseWithAi($user_id, $input, $pdo);
        break;
    case 'correct_addresses_with_ai':
        correct_addresses_with_ai($user_id, $input, $pdo);
        break;
    case 'correct_address_ai': // <-- ADD THIS NEW CASE
        correct_single_address_with_ai($user_id, $input, $pdo);
        break;
    // ADD THIS NEW CASE
    case 'autocomplete_address':
        autocomplete_address($input, $pdo);
        break;
    case 'local_db_autocomplete':
        local_db_autocomplete($input, $pdo);
        break;
    case 'check_fraud_risk':
        runFraudCheckOnBestServer($user_id, $input, $pdo);
        //check_fraud_risk($user_id, $input, $pdo);
        break;
    // --- NEW SUBSCRIPTION ENDPOINTS ---
    case 'load_user_data': // Usage: Dashboard (Aliases get_subscription_data)
    case 'get_subscription_data':
        // AUTO-MIGRATE: Ensure extra limit columns exist to prevent SQL errors
        try {
            $stmt_check = $pdo->query("SHOW COLUMNS FROM users LIKE 'extra_order_limit'");
            if ($stmt_check->rowCount() == 0) {
                $pdo->exec("ALTER TABLE users ADD COLUMN extra_order_limit INT DEFAULT 0 AFTER monthly_order_count");
                $pdo->exec("ALTER TABLE users ADD COLUMN extra_ai_parsed_limit INT DEFAULT 0 AFTER monthly_ai_parsed_count");
            }

            // AUTO-MIGRATE: Alert Columns
            $stmt_check_alerts = $pdo->query("SHOW COLUMNS FROM users LIKE 'alert_usage_order_75'");
            if ($stmt_check_alerts->rowCount() == 0) {
                $pdo->exec("ALTER TABLE users ADD COLUMN alert_usage_order_75 TINYINT DEFAULT 0");
                $pdo->exec("ALTER TABLE users ADD COLUMN alert_usage_order_90 TINYINT DEFAULT 0");
                $pdo->exec("ALTER TABLE users ADD COLUMN alert_usage_ai_75 TINYINT DEFAULT 0");
                $pdo->exec("ALTER TABLE users ADD COLUMN alert_usage_ai_90 TINYINT DEFAULT 0");
            }
        } catch (Exception $e) { /* Ignore errors if columns exist or permissions fail */
        }

        $stmt_user = $pdo->prepare("
            SELECT u.plan_id, p.name as plan_name, p.order_limit_daily, 
                   (p.order_limit_monthly + IFNULL(u.extra_order_limit, 0)) as order_limit_monthly, 
                   (p.ai_parsing_limit + IFNULL(u.extra_ai_parsed_limit, 0)) as ai_parsing_limit,
                   p.bulk_parse_limit,
                   p.can_parse_ai, p.can_autocomplete, p.can_check_risk, p.can_correct_address, p.can_show_ads,
                   u.can_manual_parse,
                   u.plan_expiry_date, u.daily_order_count, u.monthly_order_count, u.monthly_ai_parsed_count,
                   u.last_selected_store_id, u.parser_settings
            FROM users u
            JOIN plans p ON u.plan_id = p.id
            WHERE u.id = ?
        ");
        $stmt_user->execute([$user_id]);
        $data = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            // Re-fetch Gemini API Key to ensure persistence
            $stmt_key = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'gemini_api_key'");
            $data['geminiApiKey'] = $stmt_key->fetchColumn();

            $stmt_spam = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'ai_spam_char_limit'");
            $raw_spam = $stmt_spam->fetchColumn();
            $data['aiSpamCharLimit'] = is_numeric($raw_spam) ? (int) $raw_spam : 2000;

            // Fetch AI Bulk Parse Limit from Plan
            // Fallback to 30 if null
            $data['aiBulkParseLimit'] = !empty($data['bulk_parse_limit']) ? $data['bulk_parse_limit'] : 30;

            $data['permissions'] = [
                'can_parse_ai' => (bool) ($data['can_parse_ai'] ?? false),
                'can_autocomplete' => (bool) ($data['can_autocomplete'] ?? false),
                'can_check_risk' => (bool) ($data['can_check_risk'] ?? false),
                'can_correct_address' => (bool) ($data['can_correct_address'] ?? false),
                'can_show_ads' => (bool) ($data['can_show_ads'] ?? false),
                'can_manual_parse' => (bool) ($data['can_manual_parse'] ?? false)
            ];

            // Fetch user's stores
            $stmt_stores = $pdo->prepare("SELECT id, store_name, courier_type, credentials FROM stores WHERE user_id = ?");
            $stmt_stores->execute([$user_id]);
            $stores_arr = $stmt_stores->fetchAll(PDO::FETCH_ASSOC);
            $stores_obj = [];
            foreach ($stores_arr as $store) {
                $creds = json_decode($store['credentials'], true) ?: [];
                $stores_obj[$store['id']] = array_merge($creds, [
                    'store_name' => $store['store_name'],
                    'storeName' => $store['store_name'], // For compatibility
                    'courier_type' => $store['courier_type'],
                    'courierType' => $store['courier_type'] // For compatibility
                ]);
            }
            $data['stores'] = $stores_obj;
            $data['lastSelectedStoreId'] = $data['last_selected_store_id'];
            $data['parserSettings'] = json_decode($data['parser_settings'] ?? '[]', true);

            // Fetch help content
            $stmt_help = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'help_content'");
            $data['helpContent'] = $stmt_help->fetchColumn() ?: '';
        }

        json_response($data);
        break;

    case 'get_available_plans':
        $stmt = $pdo->query("SELECT id, name, price, description, validity_days, order_limit_daily, order_limit_monthly, ai_parsing_limit, bulk_parse_limit FROM plans WHERE is_active = 1 AND price > 0 ORDER by name ASC");
        json_response($stmt->fetchAll());
        break;

    case 'get_active_payment_methods':
        $stmt = $pdo->query("SELECT id, name, account_details, instructions FROM payment_methods WHERE is_active = 1");
        json_response($stmt->fetchAll());
        break;

    case 'submit_purchase_request':
        $plan_id = $input['planId'];
        $method_id = $input['methodId'];
        $sender = $input['senderNumber'];
        $trx_id = $input['transactionId'];

        // Get plan price
        $stmt_plan = $pdo->prepare("SELECT price FROM plans WHERE id = ?");
        $stmt_plan->execute([$plan_id]);
        $price = $stmt_plan->fetchColumn();

        $stmt = $pdo->prepare("INSERT INTO subscriptions (user_id, plan_id, payment_method_id, sender_number, transaction_id, amount_paid) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $plan_id, $method_id, $sender, $trx_id, $price]);

        // Notify admin
        sendAdminPurchaseNotification($user_id, $plan_id, $price, $sender, $trx_id);

        // --- CAPI Integration (Purchase) ---
        $eventId = 'purchase_' . uniqid('', true);

        // Fetch user details for CAPI
        $stmt_u = $pdo->prepare("SELECT email, display_name FROM users WHERE id = ?");
        $stmt_u->execute([$user_id]);
        $userCapi = $stmt_u->fetch();

        // Fetch Plan Name
        $stmt_pn = $pdo->prepare("SELECT name FROM plans WHERE id = ?");
        $stmt_pn->execute([$plan_id]);
        $planName = $stmt_pn->fetchColumn();

        $userDataCapi = [
            'em' => $userCapi['email'] ?? '',
            'fn' => explode(' ', $userCapi['display_name'] ?? '')[0]
        ];
        $customData = [
            'value' => $price,
            'currency' => 'BDT',
            'content_name' => $planName,
            'content_type' => 'product',
            'content_ids' => [$plan_id]
        ];
        sendFacebookCAPIEvent('Purchase', $userDataCapi, $customData, $eventId);
        // -----------------------------------

        json_response(['success' => true, 'message' => 'Your request has been submitted and is pending review.', 'eventId' => $eventId]);
        break;
    // In the main switch($action) block in api/index.php
    case 'get_my_subscriptions':
        $stmt = $pdo->prepare("
            SELECT s.created_at, s.amount_paid, s.status, p.name as plan_name, pm.name as payment_method_name
            FROM subscriptions s
            JOIN plans p ON s.plan_id = p.id
            JOIN payment_methods pm ON s.payment_method_id = pm.id
            WHERE s.user_id = ?
            ORDER BY s.created_at ASC
        ");
        $stmt->execute([$user_id]);
        json_response($stmt->fetchAll());
        break;

    // Add this case inside your main switch ($data->action)
    case 'save_parser_settings':
        if (!isset($_SESSION['user_id'])) {
            json_response(['error' => 'Authentication required.'], 401);
        }

        // Check if settings is set and is an array
        // --- FIX: Use $input['settings'] ---
        if (isset($input['settings']) && is_array($input['settings'])) {
            $settingsJson = json_encode($input['settings']);
        } else {
            // If client sends bad data, save an empty array string
            $settingsJson = '[]';
        }

        $stmt = $pdo->prepare("UPDATE users SET parser_settings = ? WHERE id = ?");
        $stmt->execute([$settingsJson, $_SESSION['user_id']]);

        json_response(['success' => true, 'message' => 'Parser settings saved.']);
        break;

    case 'check_duplicate_phones':
        $phones = $input['phones'] ?? [];
        if (empty($phones) || !is_array($phones)) {
            json_response([]);
        }
        // Normalize phone numbers
        $normalized = array_map(function ($p) {
            $p = preg_replace('/[\\s-]/', '', $p);
            if (str_starts_with($p, '+88'))
                $p = substr($p, 3);
            elseif (str_starts_with($p, '88'))
                $p = substr($p, 2);
            return $p;
        }, $phones);

        $placeholders = implode(',', array_fill(0, count($normalized), '?'));
        $stmt = $pdo->prepare("SELECT phone, customer_name, courier_type, order_id, tracking_id, created_at FROM successful_orders WHERE user_id = ? AND phone IN ($placeholders) ORDER BY created_at DESC");
        $stmt->execute(array_merge([$user_id], $normalized));
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group by phone number
        $duplicates = [];
        foreach ($results as $row) {
            $duplicates[$row['phone']] = $row; // Keep most recent
        }
        json_response($duplicates);
        break;
}

// --- Function Implementations ---

function handle_auth($action, $input, $pdo)
{
    switch ($action) {
        case 'register':
            $email = filter_var($input['email'], FILTER_VALIDATE_EMAIL);
            $password = $input['password'];
            $display_name = trim($input['display_name'] ?? '');

            if (!$email || strlen($password) < 6) {
                json_response(['error' => 'Invalid email or password (min 6 chars).'], 400);
            }
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT); // 6-Digit Code

            try {
                // Modified INSERT to include display_name
                $stmt = $pdo->prepare("INSERT INTO users (email, password, display_name, verification_token, plan_id) VALUES (?, ?, ?, ?, 1)");
                $stmt->execute([$email, $hashed_password, $display_name, $code]);

                // Call the new reusable function
                sendVerificationCodeEmail($email, $code, $pdo);

                json_response(['success' => 'Registration successful. Please check your email for the verification code.']);
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) { // Duplicate entry
                    json_response(['error' => 'Email already exists.'], 409);
                }
                json_response(['error' => 'Database error.'], 500);
            }
            break;

        case 'verify_code':
            $email = filter_var($input['email'], FILTER_VALIDATE_EMAIL);
            $code = trim($input['code']);

            $stmt = $pdo->prepare("SELECT id, display_name, is_admin, first_login FROM users WHERE email = ? AND verification_token = ?");
            $stmt->execute([$email, $code]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Verify User
                $pdo->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?")->execute([$user['id']]);

                // Auto-Login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['is_admin'] = (bool) $user['is_admin'];

                // Send Welcome Email if first login
                if (isset($user['first_login']) && $user['first_login'] == 1) {
                    if (sendWelcomeEmail($email, $user['display_name'], $pdo)) {
                        $pdo->prepare("UPDATE users SET first_login = 0 WHERE id = ?")->execute([$user['id']]);
                    }
                }

                json_response(['success' => true, 'loggedIn' => true]);
            } else {
                json_response(['error' => 'Invalid verification code.'], 400);
            }
            break;

        // In api/index.php
        case 'login':
            // Auto-Migrate: first_login column
            try {
                $stmt_check = $pdo->query("SHOW COLUMNS FROM users LIKE 'first_login'");
                if ($stmt_check->rowCount() == 0) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN first_login TINYINT DEFAULT 1");
                }
            } catch (Exception $e) {
            }

            $email = filter_var($input['email'], FILTER_VALIDATE_EMAIL);
            $password = $input['password'];

            $stmt = $pdo->prepare("SELECT id, email, password, display_name AS displayName, is_premium, is_verified, is_admin, first_login FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                if (!$user['is_verified']) {
                    // Auto-resend verification code
                    $verification_token = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
                    $pdo->prepare("UPDATE users SET verification_token = ? WHERE id = ?")->execute([$verification_token, $user['id']]);
                    sendVerificationCodeEmail($email, $verification_token, $pdo);

                    json_response(['error' => 'Email not verified. A new code has been sent.', 'notVerified' => true, 'email' => $email], 403);
                }

                // Clean old sessions
                session_unset();
                session_destroy();
                session_set_cookie_params(0, '/');
                session_start();

                // Success! Set session variables.
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['is_admin'] = (bool) $user['is_admin'];

                // --- CAPI Integration (Login) ---
                $eventId = 'login_' . uniqid('', true);
                $userDataCapi = [
                    'em' => $user['email'],
                    'fn' => explode(' ', $user['displayName'])[0] ?? ''
                ];
                sendFacebookCAPIEvent('Login', $userDataCapi, [], $eventId);
                // --------------------------------

                json_response([
                    'loggedIn' => true,
                    'user' => $user,
                    'eventId' => $eventId
                ]);

                // Send Welcome Email if first login
                if ($user['first_login'] == 1) {
                    if (sendWelcomeEmail($email, $user['displayName'], $pdo)) {
                        $pdo->prepare("UPDATE users SET first_login = 0 WHERE id = ?")->execute([$user['id']]);
                    }
                }
            } else {
                json_response(['error' => 'Invalid credentials.'], 401);
            }
            break;

        case 'logout':
            session_destroy();
            json_response(['success' => true]);
            break;

        case 'check_session':
            if (isset($_SESSION['user_id'])) {
                // This is the crucial part: it ALWAYS queries the database for fresh data.
                $stmt = $pdo->prepare("SELECT id, email, display_name AS displayName, is_premium, plan_id FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();

                if ($user) {
                    // User is valid and we have the latest data.
                    json_response(['loggedIn' => true, 'user' => $user]);
                } else {
                    // User in session was deleted, destroy the invalid session.
                    session_destroy();
                    json_response(['loggedIn' => false]);
                }
            } else {
                json_response(['loggedIn' => false]);
            }
            break;

        case 'resend_verification':
            $email = filter_var($input['email'], FILTER_VALIDATE_EMAIL);
            if (!$email) {
                json_response(['error' => 'Invalid email provided.'], 400);
            }

            $stmt = $pdo->prepare("SELECT id, is_verified FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && !$user['is_verified']) {
                $token = bin2hex(random_bytes(32));

                $update_stmt = $pdo->prepare("UPDATE users SET verification_token = ? WHERE id = ?");
                $update_stmt->execute([$token, $user['id']]);

                // Call the new reusable function
                sendVerificationEmail($email, $token);
            }

            json_response(['success' => 'If an account with that email exists and requires verification, a new link has been sent.']);
            break;
        // Inside handle_auth() function in api/index.php
        case 'request_password_reset':
            $email = filter_var($input['email'], FILTER_VALIDATE_EMAIL);
            if (!$email)
                json_response(['error' => 'Invalid email.'], 400);

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expires = (new DateTime())->modify('+1 hour')->format('Y-m-d H:i:s');

                $update_stmt = $pdo->prepare("UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?");
                $update_stmt->execute([$token, $expires, $user['id']]);

                sendPasswordResetEmail($email, $token);
            }

            // Always return success to prevent user enumeration
            json_response(['success' => 'If an account with that email exists, a reset link has been sent.']);
            break;

        case 'perform_password_reset':
            $token = $input['token'] ?? '';
            $password = $input['password'] ?? '';
            if (empty($token) || strlen($password) < 6) {
                json_response(['error' => 'Invalid token or password.'], 400);
            }

            $stmt = $pdo->prepare("SELECT id FROM users WHERE password_reset_token = ? AND password_reset_expires > NOW()");
            $stmt->execute([$token]);
            $user = $stmt->fetch();

            if ($user) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $update_stmt = $pdo->prepare("UPDATE users SET password = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE id = ?");
                $update_stmt->execute([$hashed_password, $user['id']]);
                json_response(['success' => true]);
            } else {
                json_response(['error' => 'Invalid or expired reset token.'], 400);
            }
            break;

        case 'google_login_url':
            // Fetch ID from DB
            $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'google_client_id'");
            $db_client_id = $stmt->fetchColumn();

            // Fallback to constant if DB value is empty
            $client_id = !empty($db_client_id) ? $db_client_id : (defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '');

            if (empty($client_id) || $client_id === 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com') {
                json_response(['error' => 'Google Login is not configured by admin.'], 500);
            }

            $params = [
                'client_id' => $client_id,
                'redirect_uri' => GOOGLE_REDIRECT_URI,
                'response_type' => 'code',
                'scope' => 'email profile',
                'access_type' => 'online',
                'prompt' => 'select_account'
            ];
            $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
            json_response(['url' => $url]);
            break;

    }
}

// --- Authenticated User Actions ---
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    if (!in_array($action, ['register', 'login', 'check_session', 'resend_verification', 'google_login_url'])) {
        json_response(['error' => 'Authentication required.'], 401);
    }
    exit;
}


function add_or_update_store($user_id, $input, $pdo)
{
    // Logic to add or update a store
    $store_name = $input['storeName'];
    $courier_type = $input['courierType'];
    $credentials = json_encode($input['credentials']);
    $editing_id = $input['editingId'] ?? null;

    // --- AUTO-MIGRATE: Ensure courier_type supports 'redx' (Convert ENUM to VARCHAR) ---
    try {
        $stmt_col = $pdo->query("SHOW COLUMNS FROM stores LIKE 'courier_type'");
        $col_info = $stmt_col->fetch(PDO::FETCH_ASSOC);
        if ($col_info && stripos($col_info['Type'], 'enum') !== false) {
            // It's an ENUM, convert to VARCHAR to support Redx and future couriers
            $pdo->exec("ALTER TABLE stores MODIFY COLUMN courier_type VARCHAR(50) NOT NULL");
        }
    } catch (Exception $e) { /* Ignore if already varchar or permission denied */
    }
    // ----------------------------------------------------------------------------------

    if ($editing_id) {
        $stmt = $pdo->prepare("UPDATE stores SET store_name = ?, courier_type = ?, credentials = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$store_name, $courier_type, $credentials, $editing_id, $user_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO stores (user_id, store_name, courier_type, credentials) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $store_name, $courier_type, $credentials]);
    }
    json_response(['success' => true]);
}

/**
 * Parses text input and returns structured JSON.
 * Currently returns minimal dummy data for testing, like load_user_data.
 *
 * @param int $user_id The ID of the logged-in user
 * @param array $input Input data containing 'rawText'
 * @return void Outputs JSON response and exits
 */
/**
 * Parse text input with Gemini AI API.
 *
 * @param int $user_id Logged-in user ID
 * @param array $input Input array containing 'rawText'
 * @param PDO $pdo Database connection
 * @return void Outputs JSON response
 */
function parseWithAi($user_id, $input, $pdo)
{
    // Increase limits for large AI processing
    set_time_limit(300); // 5 Minutes
    ini_set('memory_limit', '512M');

    // --- 1. Check premium access & limits ---
    $stmt_user_plan = $pdo->prepare("
        SELECT 
            p.can_parse_ai, 
            p.order_limit_daily, 
            p.order_limit_monthly,
            p.ai_parsing_limit,
            p.validity_days,
            u.plan_expiry_date, 
            u.daily_order_count, 
            u.monthly_order_count,
            u.monthly_ai_parsed_count,
            u.last_reset_date,
            u.extra_order_limit,
            u.extra_ai_parsed_limit,
            u.email,
            u.alert_usage_ai_75,
            u.alert_usage_ai_90
        FROM users u 
        JOIN plans p ON u.plan_id = p.id 
        WHERE u.id = ?
    ");
    $stmt_user_plan->execute([$user_id]);
    $user_data = $stmt_user_plan->fetch(PDO::FETCH_ASSOC);

    // --- RESET LOGIC (Duplicated from create_order) ---
    $today = date('Y-m-d');
    if ($user_data['last_reset_date'] != $today) {
        $should_reset_monthly = false;
        $last_reset = new DateTime($user_data['last_reset_date'] ?? '1970-01-01');
        $days_since_reset = $last_reset->diff(new DateTime())->days;
        if ($user_data['validity_days'] > 1 && $days_since_reset >= $user_data['validity_days']) {
            $should_reset_monthly = true;
        }

        // Prepare update
        $updates = ['daily_order_count' => 0];
        $sql_set = "daily_order_count = 0";

        if ($should_reset_monthly) {
            $updates['monthly_order_count'] = 0;
            $updates['monthly_ai_parsed_count'] = 0;
            $sql_set .= ", monthly_order_count = 0, monthly_ai_parsed_count = 0, alert_usage_order_75 = 0, alert_usage_order_90 = 0, alert_usage_ai_75 = 0, alert_usage_ai_90 = 0";
            // Update local var for immediate check
            $user_data['monthly_ai_parsed_count'] = 0;
        }

        $sql_set .= ", last_reset_date = ?";
        $pdo->prepare("UPDATE users SET $sql_set WHERE id = ?")->execute([$today, $user_id]);
    }
    // --- END RESET LOGIC ---

    if (!$user_data || !$user_data['can_parse_ai']) {
        json_response(['error' => 'AI features are not available on your current plan.'], 403);
    }

    // Check Plan Expiry
    if (!empty($user_data['plan_expiry_date'])) {
        try {
            if (new DateTime($user_data['plan_expiry_date']) < new DateTime()) {
                json_response(['error' => 'Your subscription plan has expired. Please upgrade.'], 403);
            }
        } catch (Exception $e) { /* Ignore invalid date format issues */
        }
    }

    // Check Order Limits (Block parsing if limit reached)
    // Use Extra Limits if available
    $effective_monthly_limit = $user_data['order_limit_monthly'] + ($user_data['extra_order_limit'] ?? 0);
    $effective_ai_limit = $user_data['ai_parsing_limit'] + ($user_data['extra_ai_parsed_limit'] ?? 0);

    if ($user_data['order_limit_daily'] > 0 && $user_data['daily_order_count'] >= $user_data['order_limit_daily']) {
        json_response(['error' => 'Daily order limit reached. You cannot parse more parcels today.'], 403);
    }
    if ($effective_monthly_limit > 0 && $user_data['monthly_order_count'] >= $effective_monthly_limit) {
        json_response(['error' => 'Monthly order limit reached. You cannot parse more parcels this month.'], 403);
    }
    // New AI Limit Check
    if ($effective_ai_limit > 0 && $user_data['monthly_ai_parsed_count'] >= $effective_ai_limit) {
        json_response(['error' => 'Monthly AI parsing limit reached (' . $effective_ai_limit . '). Upgrade plan.'], 403);
    }

    // --- 2. Get Gemini API key (MODIFIED BLOCK) ---
    $stmt_settings = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'gemini_api_key'");
    $stmt_settings->execute();
    $gemini_api_keys_str = $stmt_settings->fetchColumn();
    if (!$gemini_api_keys_str) {
        json_response(['error' => 'The Gemini API key has not been set by the administrator.'], 500);
    }
    // Explode the string into an array of keys, trimming whitespace and removing empty entries
    $keys = array_filter(array_map('trim', explode(',', $gemini_api_keys_str)));
    if (empty($keys)) {
        json_response(['error' => 'Gemini API keys are configured incorrectly in the admin panel.'], 500);
    }
    // Select one random key from the array
    $gemini_api_key = $keys[array_rand($keys)];
    // --- END OF MODIFICATION ---



    if (!$gemini_api_key) {
        json_response(['error' => 'The Gemini API key has not been set by the administrator.'], 500);
    }

    // --- 3. Get raw text ---
    $raw_text = trim($input['rawText'] ?? '');
    if (empty($raw_text)) {
        json_response(['error' => 'No text was provided for parsing.'], 400);
    }
    // --- 4. Validation: Plan Limit & Anti-Spam ---
    // Fetch User's Plan for Bulk Limit
    $stmt_plan = $pdo->prepare("SELECT p.bulk_parse_limit FROM users u JOIN plans p ON u.plan_id = p.id WHERE u.id = ?");
    $stmt_plan->execute([$user_id]);
    $plan_limit = $stmt_plan->fetchColumn();
    $max_parcels = is_numeric($plan_limit) && $plan_limit > 0 ? (int) $plan_limit : 30; // Default 30 if null

    // Count blocks (parcels) separated by empty lines
    $blocks = preg_split('/\n\s*\n/', $raw_text, -1, PREG_SPLIT_NO_EMPTY);

    // Filter out blocks that are likely just separators (e.g. "=====")
    $final_blocks = [];
    foreach ($blocks as $b) {
        $trimmed_b = trim($b);
        if (empty($trimmed_b) || preg_match('/^=+$/', $trimmed_b))
            continue;

        // Anti-Spam Check: Max characters per block
        // Fetch Limit dynamically
        $stmt_spam = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'ai_spam_char_limit'");
        $raw_spam = $stmt_spam->fetchColumn();
        $spam_limit = is_numeric($raw_spam) ? (int) $raw_spam : 2000;

        if (mb_strlen($trimmed_b) > $spam_limit) {
            json_response(['error' => "Spam Detected: One or more blocks contain excessively large text (>$spam_limit characters)."], 400);
        }
        $final_blocks[] = $b;
    }

    $parcel_count = count($final_blocks);

    if ($parcel_count > $max_parcels) {
        json_response(['error' => "Input too large. Your plan allows max $max_parcels parcels per request. You submitted $parcel_count."], 400);
    }

    // --- 5. Define Parser Instructions ---
    $parser_instructions_str = <<<EOT
**Input Structure:**
- **Separation:** distinct parcels are separated by **EMPTY LINE BREAKS**.
- **Block Content:** Each block between empty lines represents ONE customer.
- **Chaotic Order:** Inside a block, lines can be in ANY order (e.g., Phone first, or Address first).

**Fields to Extract:**
1. **recipient_phone** (MANDATORY): 11-digit BD number (01xxxxxxxxx).
2. **recipient_address** (MANDATORY): Full address. Merge lines if needed. Complete partial addresses (add District/Thana).
3. **cod_amount** (MANDATORY): Product Price/COD. Look for numbers like 500, 1200.00.
4. **recipient_name** (Optional): Customer name.
5. **order_id** (Optional): Invoice/Order ID.
6. **item_description** (Optional): Product Name/Description.
7. **note** (Optional): Instructions.
EOT;

    // --- 5. Batch Processing Logic ---
    $chunk_size = 10; // Increased to 10 as requested, using Text-based output for stability
    $chunks = array_chunk($final_blocks, $chunk_size);
    $all_parses = [];
    $total_chunks = count($chunks);

    // Increase execution time 
    set_time_limit(180);

    // Fetch Settings
    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('ai_retry_count', 'admin_error_email', 'app_name')");
    $settings_map = array_column($stmt_settings->fetchAll(), 'setting_value', 'setting_key');
    $retry_count_setting = isset($settings_map['ai_retry_count']) ? (int) $settings_map['ai_retry_count'] : 2;
    $admin_email = $settings_map['admin_error_email'] ?? '';
    $app_name = $settings_map['app_name'] ?? 'CourierPlus';

    foreach ($chunks as $index => $chunk_blocks) {
        $chunk_text = implode("\n\n", $chunk_blocks);

        // --- NEW TEXT-BASED PROMPT ---
        $prompt = <<<EOT
You are an API that converts unstructured Bengali/English courier order text into a structured text format.
$parser_instructions_str

**Fields to Extract:**
- recipient_name
- recipient_phone (11-digit BD Phone 01xxxxxxxxx)
- recipient_address (Full address)
- cod_amount (Numeric price)
- order_id
- item_description
- note

**Critical Rules:**
1. **PROCESS ALL BLOCKS:** Output one block for **EVERY** input block.
2. **STRICT BLOCK ISOLATION:** Do not mix data.
3. **Missing Fields:** If missing, leave the value empty.
4. **Use '====' Separator:** Separate each parcel block strictly with a line containing only "====".
5. **Output Format:**
For each parcel, output exactly these lines:
Name: <name>
Phone: <phone>
Address: <address>
Price: <amount>
OID: <order_id>
Item: <item>
Note: <note>
====

**Input text to process:**
---
$chunk_text
---
EOT;

        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=' . $gemini_api_key;
        $api_body = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['responseMimeType' => 'text/plain'] // Changed to text/plain
        ];

        // --- Call Gemini API (Retry Logic) ---
        $success = false;
        $last_error = '';

        for ($attempt = 0; $attempt <= $retry_count_setting; $attempt++) {
            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($api_body));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);

            $response_body = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($http_code === 200 && $response_body) {
                $json_data = json_decode($response_body, true);
                $ai_text_response = $json_data['candidates'][0]['content']['parts'][0]['text'] ?? '';

                if ($ai_text_response) {
                    // --- PROGRAMMATIC TEXT PARSING ---
                    $parsed_result = [];
                    // Split by separator '===='
                    $raw_blocks = explode('====', $ai_text_response);

                    foreach ($raw_blocks as $block_str) {
                        $block_str = trim($block_str);
                        if (empty($block_str))
                            continue;

                        $parcel = [
                            'recipient_name' => null,
                            'recipient_phone' => null,
                            'recipient_address' => null,
                            'cod_amount' => 0,
                            'order_id' => null,
                            'item_description' => null,
                            'note' => null
                        ];

                        $lines = explode("\n", $block_str);
                        foreach ($lines as $line) {
                            $line = trim($line);
                            if (strpos($line, ':') !== false) {
                                [$key, $val] = explode(':', $line, 2);
                                $key = strtolower(trim($key));
                                $val = trim($val);
                                if ($val === 'null' || $val === 'N/A')
                                    $val = null;

                                switch ($key) {
                                    case 'name':
                                        $parcel['recipient_name'] = $val;
                                        break;
                                    case 'phone':
                                        $parcel['recipient_phone'] = $val;
                                        break;
                                    case 'address':
                                        $parcel['recipient_address'] = $val;
                                        break;
                                    case 'price':
                                        $parcel['cod_amount'] = (float) preg_replace('/[^0-9.]/', '', $val);
                                        break;
                                    case 'oid':
                                        $parcel['order_id'] = $val;
                                        break;
                                    case 'item':
                                        $parcel['item_description'] = $val;
                                        break;
                                    case 'note':
                                        $parcel['note'] = $val;
                                        break;
                                }
                            }
                        }
                        // Only add if at least one field is present (to avoid empty separator noise)
                        if ($parcel['recipient_phone'] || $parcel['recipient_address'] || $parcel['cod_amount']) {
                            $parsed_result[] = $parcel;
                        }
                    }

                    if (!empty($parsed_result)) {
                        $all_parses = array_merge($all_parses, $parsed_result);
                        $success = true;
                        break;
                    } else {
                        $last_error = "Parsed 0 blocks from text response in chunk " . ($index + 1);
                        file_put_contents('ai_error_log.txt', date('Y-m-d H:i:s') . " - Text Parse Fail: $ai_text_response\n", FILE_APPEND);
                    }
                } else {
                    $last_error = "Empty AI response in chunk " . ($index + 1);
                }
            } else {
                $last_error = "HTTP $http_code: " . ($curl_error ?: $response_body);
            }

            if ($attempt < $retry_count_setting)
                sleep(1);
        }

        if (!$success) {
            // --- PARTIAL SUCCESS LOGIC ---
            // Instead of failing HARD (500), we log the error and CONTINUE to the next chunk.
            // This ensures mapped data is not lost if one batch fails.
            $error_msg = "Batch Processing Failed at Chunk " . ($index + 1) . ". $last_error";
            file_put_contents('ai_error_log.txt', date('Y-m-d H:i:s') . " - $error_msg\n", FILE_APPEND);

            // Optional: Notify Admin via Email (Silent Background)
            if (!empty($admin_email)) {
                require_once 'src/Exception.php';
                require_once 'src/PHPMailer.php';
                require_once 'src/SMTP.php';
                $mail = new PHPMailer\PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = SMTP_HOST;
                    $mail->SMTPAuth = true;
                    $mail->Username = SMTP_USER;
                    $mail->Password = SMTP_PASS;
                    $mail->SMTPSecure = (SMTP_SECURE === 'tls') ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port = SMTP_PORT;

                    $mail->setFrom(SMTP_FROM_EMAIL, $app_name . ' System');
                    $mail->addAddress($admin_email);
                    $mail->isHTML(true);
                    $mail->Subject = 'URGENT: AI Parsing Chunk Failed on ' . $app_name;
                    $mail->Body = "<h3>Partial Failure Alert</h3><p>$error_msg</p>";
                    $mail->send();
                } catch (Exception $e) {
                }
            }
            // CONTINUING TO NEXT CHUNK
            continue;
        }
    }

    // --- 6. Handle Response Data ---
    if (empty($all_parses)) {
        // If ALL chunks failed, then we return error.
        json_response(['error' => "AI Processing Failed. No data could be extracted from any batch. Last Error: $last_error"], 500);
    }

    // --- 7. Post-Processing & Update Usage ---
    foreach ($all_parses as &$parse) {
        // Ensure random 8-digit integer for missing Order IDs
        if (empty($parse['order_id'])) {
            $parse['order_id'] = mt_rand(10000000, 99999999);
        }

        // Default COD to 0 if missing (User wants to result in Warning, not Error on frontend)
        if (!isset($parse['cod_amount']) || $parse['cod_amount'] === null || $parse['cod_amount'] === '') {
            $parse['cod_amount'] = 0;
        }
    }
    unset($parse); // Break reference

    $count = count($all_parses);
    $pdo->prepare("UPDATE users SET monthly_ai_parsed_count = monthly_ai_parsed_count + ? WHERE id = ?")->execute([$count, $user_id]);

    // Usage Alerts Logic
    if ($effective_ai_limit > 0) {
        $new_ai_usage = $user_data['monthly_ai_parsed_count'] + $count;
        $ai_percent = ($new_ai_usage / $effective_ai_limit) * 100;
        $alert_col = '';

        if ($ai_percent >= 90 && empty($user_data['alert_usage_ai_90'])) {
            $alert_col = 'alert_usage_ai_90';
        } elseif ($ai_percent >= 75 && empty($user_data['alert_usage_ai_75'])) {
            $alert_col = 'alert_usage_ai_75';
        }

        if ($alert_col) {
            $pdo->prepare("UPDATE users SET $alert_col = 1 WHERE id = ?")->execute([$user_id]);
            // Email sending is good practice but optional if strict logic failing, keeping it simpler for now
        }
    }

    json_response(['parses' => $all_parses]);
}



/**
 * Takes an array of addresses, sends them to Gemini AI for correction and structuring.
 * This version uses a more precise prompt to focus only on correction and formatting.
 */
function correct_addresses_with_ai($user_id, $input, $pdo)
{
    // 1. Check if the user's plan has AI permissions
    $stmt_user_plan = $pdo->prepare("SELECT p.can_parse_ai FROM users u JOIN plans p ON u.plan_id = p.id WHERE u.id = ?");
    $stmt_user_plan->execute([$user_id]);
    if (!$stmt_user_plan->fetchColumn()) {
        json_response(['error' => 'AI features are not available on your current plan.'], 403);
    }

    // --- 2. Get Gemini API key (MODIFIED BLOCK) ---
    $stmt_settings = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'gemini_api_key'");
    $stmt_settings->execute();
    $gemini_api_keys_str = $stmt_settings->fetchColumn();
    if (!$gemini_api_keys_str) {
        json_response(['error' => 'The Gemini API key has not been set by the administrator.'], 500);
    }
    // Explode the string into an array of keys, trimming whitespace and removing empty entries
    $keys = array_filter(array_map('trim', explode(',', $gemini_api_keys_str)));
    if (empty($keys)) {
        json_response(['error' => 'Gemini API keys are configured incorrectly in the admin panel.'], 500);
    }
    // Select one random key from the array
    $gemini_api_key = $keys[array_rand($keys)];
    // --- END OF MODIFICATION ---

    $addresses = $input['addresses'] ?? [];
    if (empty($addresses)) {
        json_response(['error' => 'No addresses were provided for correction.'], 400);
    }

    // REFINED PROMPT: This prompt is more direct and includes negative constraints
    // to improve accuracy and prevent the AI from adding information that isn't there.
    $prompt = "Complete and Correct this address show only address output no other texts in fomat of address, thana/upazila, District. Either bangla or English according to input" . json_encode($addresses, JSON_UNESCAPED_UNICODE);

    $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=' . $gemini_api_key;
    $api_body = ['contents' => [['parts' => [['text' => $prompt]]]]];

    // --- 5. Send request to Gemini API ---
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($api_body));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response_body = curl_exec($ch);

    // --- 6. cURL error check ---
    if (curl_errno($ch)) {
        $curl_error = curl_error($ch);
        curl_close($ch);
        json_response(['error' => 'cURL Error: Could not connect to Gemini API.', 'details' => $curl_error], 500);
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 400) {
        json_response(['error' => 'The AI API returned an error.', 'details' => json_decode($response_body, true)], $http_code);
    }

    // --- 7. Parse AI response ---
    $response_data = json_decode($response_body, true);
    $ai_text_response = $response_data['candidates'][0]['content']['parts'][0]['text'] ?? '[]';

    $addresses = array_map('trim', explode("\n", $ai_text_response));

    // Return as JSON
    json_response($addresses);
}



function delete_store($user_id, $input, $pdo)
{
    $store_id = $input['id'];
    $stmt = $pdo->prepare("DELETE FROM stores WHERE id = ? AND user_id = ?");
    $stmt->execute([$store_id, $user_id]);
    json_response(['success' => true]);
}

function get_user_history($user_id, $input, $pdo)
{
    $type = $input['type']; // 'parses' or 'orders'
    if (!in_array($type, ['parses', 'orders'])) {
        json_response(['error' => 'Invalid history type'], 400);
    }

    $stmt = $pdo->prepare("SELECT * FROM $type WHERE user_id = ? ORDER BY timestamp DESC LIMIT 25");
    $stmt->execute([$user_id]);
    $history = $stmt->fetchAll();

    // The frontend JS needs timestamp as an object for toDate().toLocaleString(), but PHP sends a string.
    // The JS will need a minor adjustment to handle the string format.
    json_response($history);
}

function save_history($user_id, $type, $input, $pdo)
{
    if (!in_array($type, ['parses', 'orders']))
        return;

    if ($type === 'parses') {
        $stmt = $pdo->prepare("INSERT INTO parses (user_id, method, data) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $input['method'], json_encode($input['data'])]);
    }
    // Order history is saved within create_order function
    json_response(['success' => true]);
}

function update_profile($user_id, $input, $pdo)
{
    if (isset($input['displayName'])) {
        $stmt = $pdo->prepare("UPDATE users SET display_name = ? WHERE id = ?");
        $stmt->execute([$input['displayName'], $user_id]);
    }
    if (isset($input['password']) && strlen($input['password']) >= 6) {
        $hashed_password = password_hash($input['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $user_id]);
    }
    if (isset($input['lastSelectedStoreId'])) {
        $stmt = $pdo->prepare("UPDATE users SET last_selected_store_id = ? WHERE id = ?");
        $stmt->execute([$input['lastSelectedStoreId'], $user_id]);
    }
    json_response(['success' => true]);
}

function create_order($user_id, $input, $pdo)
{
    // --- AUTO-MIGRATE: Create successful_orders table for duplicate detection ---
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS successful_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            phone VARCHAR(20) NOT NULL,
            customer_name VARCHAR(255),
            courier_type VARCHAR(50) NOT NULL,
            order_id VARCHAR(100),
            tracking_id VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_phone (user_id, phone)
        )");
    } catch (Exception $e) { /* Ignore if exists */
    }


    // --- START: ORDER LIMIT VALIDATION ---
    $today = date('Y-m-d');

    // 1. Get user's current plan and usage stats
    $stmt_user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $user = $stmt_user->fetch();

    $stmt_plan = $pdo->prepare("SELECT * FROM plans WHERE id = ?");
    $stmt_plan->execute([$user['plan_id']]);
    $plan = $stmt_plan->fetch();

    if (!$plan) {
        json_response(['error' => 'You do not have an active subscription plan.'], 403);
    }

    // 2. Check for plan expiry
    if ($user['plan_expiry_date'] && $user['plan_expiry_date'] < $today) {
        // You could also downgrade them to the free plan here
        json_response(['error' => 'Your subscription plan has expired. Please renew or upgrade.'], 403);
    }

    // 3. Check and reset counters if a new day/month has started
    if ($user['last_reset_date'] != $today) {
        // Always reset daily counter
        $user['daily_order_count'] = 0;

        // Check if a new billing cycle has started for monthly plans
        $last_reset = new DateTime($user['last_reset_date'] ?? '1970-01-01');
        $expiry = new DateTime($user['plan_expiry_date']);
        $days_since_reset = $last_reset->diff(new DateTime())->days;

        if ($plan['validity_days'] > 1 && $days_since_reset >= $plan['validity_days']) {
            $user['monthly_order_count'] = 0;
            $user['monthly_ai_parsed_count'] = 0;
            $user['alert_usage_order_75'] = 0;
            $user['alert_usage_order_90'] = 0;
            $user['alert_usage_ai_75'] = 0;
            $user['alert_usage_ai_90'] = 0;
            $pdo->prepare("UPDATE users SET monthly_order_count = 0, monthly_ai_parsed_count = 0, alert_usage_order_75 = 0, alert_usage_order_90 = 0, alert_usage_ai_75 = 0, alert_usage_ai_90 = 0, last_reset_date = ? WHERE id = ?")->execute([$today, $user_id]);
        } else {
            $pdo->prepare("UPDATE users SET last_reset_date = ? WHERE id = ?")->execute([$today, $user_id]);
        }
    }

    // 4. Enforce limits
    $order_count_in_request = count($input['orders']);

    if ($plan['order_limit_daily']) {
        if (($user['daily_order_count'] + $order_count_in_request) > $plan['order_limit_daily']) {
            json_response(['error' => "Your daily limit is {$plan['order_limit_daily']} orders. This request exceeds it."], 403);
        }
    }

    if ($plan['order_limit_monthly']) {
        // Effective Limit = Plan Limit + Extra Limit
        $effective_limit = $plan['order_limit_monthly'] + ($user['extra_order_limit'] ?? 0);
        if (($user['monthly_order_count'] + $order_count_in_request) > $effective_limit) {
            json_response(['error' => "Your monthly limit is {$effective_limit} orders. This request exceeds it."], 403);
        }
    }
    // --- END: ORDER LIMIT VALIDATION ---



    $store_id = $input['storeId'];
    $orders = $input['orders'];
    $is_bulk = count($orders) > 1;

    // Fetch store credentials
    $stmt = $pdo->prepare("SELECT courier_type, credentials FROM stores WHERE id = ? AND user_id = ?");
    $stmt->execute([$store_id, $user_id]);
    $store = $stmt->fetch();
    if (!$store) {
        json_response(['error' => 'Store not found.'], 404);
    }
    // *** START MODIFICATION: Save this store as the user's last preference ***
    $stmt_update_user = $pdo->prepare("UPDATE users SET last_selected_store_id = ? WHERE id = ?");
    $stmt_update_user->execute([$store_id, $user_id]);
    // *** END MODIFICATION ***
    $credentials = json_decode($store['credentials'], true);
    $courier_type = $store['courier_type'];

    $final_response = null;
    $final_payload = null;

    try {
        if ($courier_type === 'steadfast') {
            $baseUrl = 'https://portal.packzy.com/api/v1';
            $endpoint = $is_bulk ? $baseUrl . '/create_order/bulk-order' : $baseUrl . '/create_order';

            $mapOrder = fn($order_data, $index) => [
                'invoice' => $order_data['orderId'] ?? "CPlus-" . time() . "-$index",
                'recipient_name' => $order_data['customerName'],
                'recipient_phone' => $order_data['phone'],
                'cod_amount' => (float) $order_data['amount'],
                'recipient_address' => $order_data['address'],
                'item_description' => $order_data['productName'],
                'note' => $order_data['note'] ?? ''
            ];

            // --- *** THE FIX IS HERE *** ---
            if ($is_bulk) {
                // For bulk orders, the 'data' value must be a JSON-encoded STRING.
                $mapped_orders = array_map($mapOrder, $orders, array_keys($orders));
                $payload_as_php_array = ['data' => json_encode($mapped_orders)];
            } else {
                // For single orders, the payload is just the mapped order object.
                $payload_as_php_array = $mapOrder($orders[0], 0);
            }

            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload_as_php_array)); // This final encode is still needed
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Api-Key: ' . $credentials['apiKey'],
                'Secret-Key: ' . $credentials['secretKey'],
                'Content-Type: ' . 'application/json'
            ]);

            $response_body = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $final_response = json_decode($response_body, true);

            if ($http_code >= 400) {
                throw new Exception($final_response['message'] ?? $response_body);
            }
        } elseif ($courier_type === 'pathao') {
            // Pathao API Proxy logic
            $PATHAO_BASE_URL = "https://api-hermes.pathao.com/aladdin/api/v1";

            // 1. Get Token
            $ch = curl_init("$PATHAO_BASE_URL/issue-token");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'client_id' => $credentials['clientId'],
                'client_secret' => $credentials['clientSecret'],
                'username' => $credentials['username'],
                'password' => $credentials['password'],
                'grant_type' => 'password'
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
            $token_res_body = curl_exec($ch);
            curl_close($ch);
            $token_data = json_decode($token_res_body, true);
            if (!isset($token_data['access_token'])) {
                throw new Exception('Pathao authentication failed.');
            }
            $access_token = $token_data['access_token'];

            // 2. Create Order
            $mapOrder = fn($p) => ['store_id' => (int) $credentials['storeId'], 'merchant_order_id' => $p['orderId'] ?? "CPlus-" . time(), 'recipient_name' => $p['customerName'] ?? '', 'recipient_phone' => $p['phone'] ?? '', 'recipient_address' => $p['address'] ?? '', 'amount_to_collect' => (int) ($p['amount'] ?? 0), 'item_quantity' => 1, 'item_weight' => 0.5, 'item_description' => $p['productName'] ?? 'N/A', 'delivery_type' => 48, 'item_type' => 2];

            $final_payload = $is_bulk ? ['orders' => array_map($mapOrder, $orders)] : $mapOrder($orders[0]);
            $endpoint = $is_bulk ? "$PATHAO_BASE_URL/orders/bulk" : "$PATHAO_BASE_URL/orders";

            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($final_payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer $access_token",
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            $order_res_body = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $final_response = json_decode($order_res_body, true);

            if ($http_code >= 400) {
                throw new Exception($final_response['message'] ?? $order_res_body);
            }
        } elseif ($courier_type === 'redx') {
            // REDX API Logic
            $REDX_BASE_URL = "https://openapi.redx.com.bd/v1.0.0-beta";
            $access_token = $credentials['token'];

            // Get Gemini Key for Area AI
            $stmt_key = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'gemini_api_key'");
            $stmt_key->execute();
            $keys_str = $stmt_key->fetchColumn();
            $gemini_key = null;
            if ($keys_str) {
                $k_arr = explode(',', $keys_str);
                $gemini_key = trim($k_arr[array_rand($k_arr)]);
            }

            $success_count = 0;
            $responses = [];

            foreach ($orders as $order) {
                try {
                    // 1. Resolve Area ID & Name
                    $area_data = matchRedxAreaWithAI($order['address'], $gemini_key, $access_token);
                    if (!$area_data || !isset($area_data['id']))
                        throw new Exception("Could not resolve Area ID for address: " . $order['address']);

                    $area_id = $area_data['id'];
                    $area_name = $area_data['name'];

                    // 2. Prepare Payload
                    $payload = [
                        'customer_name' => $order['customerName'],
                        'customer_phone' => $order['phone'],
                        'delivery_area' => $area_name,
                        'delivery_area_id' => (int) $area_id,
                        'customer_address' => $order['address'],
                        'merchant_invoice_id' => $order['orderId'] ?? "CX-" . uniqid(),
                        'cash_collection_amount' => (string) ($order['amount'] ?? 0),
                        'parcel_weight' => "500", // Default 500g
                        'instruction' => $order['note'] ?? '',
                        'value' => (string) ($order['amount'] ?? 0),
                        'parcel_details_json' => [
                            [
                                'name' => $order['productName'] ?? 'General Item',
                                'category' => 'General',
                                'value' => (int) ($order['amount'] ?? 0)
                            ]
                        ]
                    ];

                    $ch = curl_init("$REDX_BASE_URL/parcel");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'API-ACCESS-TOKEN: Bearer ' . $access_token,
                        'Content-Type: application/json'
                    ]);
                    $res = curl_exec($ch);
                    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);


                    $res_json = json_decode($res, true);
                    $responses[] = [
                        'order_id' => $payload['merchant_invoice_id'],
                        'status' => $code,
                        'response' => $res_json,
                        'debug_area' => $area_data
                    ];

                    if ($code >= 200 && $code < 300)
                        $success_count++;

                } catch (Exception $e) {
                    $responses[] = ['error' => $e->getMessage()];
                }
            }
            $final_response = ['bg_process' => true, 'results' => $responses, 'success_count' => $success_count];
            // Redx doesn't allow bulk in one call per user request sample, so we loop.
            // We set final_response to report all.
        }

        // Save to history on success
        $stmt_save = $pdo->prepare("INSERT INTO orders (user_id, store_id, request_payload, api_response) VALUES (?, ?, ?, ?)");
        $stmt_save->execute([$user_id, $store_id, json_encode($final_payload), json_encode($final_response)]);

        // --- START: SAVE SUCCESSFUL ORDERS FOR DUPLICATE DETECTION ---
        $stmt_log = $pdo->prepare("INSERT INTO successful_orders (user_id, phone, customer_name, courier_type, order_id, tracking_id) VALUES (?, ?, ?, ?, ?, ?)");

        if ($courier_type === 'steadfast') {
            // Handle both single and bulk responses
            $data_arr = isset($final_response['data']) && is_array($final_response['data'])
                ? $final_response['data']
                : (isset($final_response['consignment']) ? [$final_response['consignment']] : []);
            foreach ($data_arr as $item) {
                if (!empty($item['recipient_phone']) && !empty($item['consignment_id'])) {
                    $stmt_log->execute([$user_id, $item['recipient_phone'], $item['recipient_name'] ?? '', $courier_type, $item['invoice'] ?? '', $item['tracking_code'] ?? $item['consignment_id']]);
                }
            }
        } elseif ($courier_type === 'pathao') {
            // Handle single Pathao response
            if (isset($final_response['data']['consignment_id'])) {
                $order_data = $orders[0];
                $stmt_log->execute([$user_id, $order_data['phone'], $order_data['customerName'], $courier_type, $final_response['data']['merchant_order_id'] ?? '', $final_response['data']['consignment_id']]);
            }
        } elseif ($courier_type === 'redx' && isset($final_response['results'])) {
            // Handle Redx bg_process results
            foreach ($final_response['results'] as $idx => $result) {
                if ($result['status'] >= 200 && $result['status'] < 300 && isset($result['response']['tracking_id'])) {
                    $order_data = $orders[$idx] ?? [];
                    $stmt_log->execute([$user_id, $order_data['phone'] ?? '', $order_data['customerName'] ?? '', $courier_type, $result['order_id'] ?? '', $result['response']['tracking_id']]);
                }
            }
        }
        // --- END: SAVE SUCCESSFUL ORDERS FOR DUPLICATE DETECTION ---

        // --- START: INCREMENT ORDER COUNTERS ---
        $pdo->prepare("UPDATE users SET daily_order_count = daily_order_count + ?, monthly_order_count = monthly_order_count + ? WHERE id = ?")
            ->execute([$order_count_in_request, $order_count_in_request, $user_id]);
        // --- END: INCREMENT ORDER COUNTERS ---

        // --- Usage Alerts (Order) ---
        $effective_limit = $plan['order_limit_monthly'] + ($user['extra_order_limit'] ?? 0);
        if ($effective_limit > 0) {
            $new_count = $user['monthly_order_count'] + $order_count_in_request;
            $percent = ($new_count / $effective_limit) * 100;
            $alert_col = '';
            $subject = '';

            if ($percent >= 90 && empty($user['alert_usage_order_90'])) {
                $alert_col = 'alert_usage_order_90';
                $subject = 'Action Required: 90% Order Limit Reached';
            } elseif ($percent >= 75 && empty($user['alert_usage_order_75'])) {
                $alert_col = 'alert_usage_order_75';
                $subject = 'Usage Alert: 75% Order Limit Reached';
            }

            if ($alert_col) {
                $pdo->prepare("UPDATE users SET $alert_col = 1 WHERE id = ?")->execute([$user_id]);
                $msg = "<p>You have used <strong>" . number_format($percent) . "%</strong> of your monthly order limit.</p><p>Used: $new_count / $effective_limit</p>";
                $html = wrapInEmailTemplate($subject, $msg, $pdo);
                if (!empty($user['email'])) {
                    sendSystemEmail($user['email'], $subject, $html);
                }
            }
        }

        json_response($final_response);

    } catch (Exception $e) {
        json_response(['error' => $e->getMessage()], 500);
    }
}


/**
 * Sends a verification email to a user.
 * @param string $email The recipient's email address.
 * @param string $token The verification token.
 * @return bool True on success, false on failure.
 */
function sendVerificationEmail($email, $token)
{
    global $pdo; // Make PDO available inside the function

    // Fetch latest app settings
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('app_name', 'app_logo_url')");
    $settings = array_column($stmt->fetchAll(), 'setting_value', 'setting_key');
    $appName = $settings['app_name'] ?? 'CourierPlus';
    $logoUrl = ($settings['app_logo_url'] ?? '') ? APP_URL . '/' . $settings['app_logo_url'] : '';

    require_once 'src/Exception.php';
    require_once 'src/PHPMailer.php';
    require_once 'src/SMTP.php';

    $mail = new PHPMailer(true);
    $verification_link = APP_URL . "/verify.php?token=$token";

    // Modern HTML Email Template
    $body = file_get_contents('email_template.html');
    $body = str_replace('{{appName}}', $appName, $body);
    $body = str_replace('{{logoUrl}}', $logoUrl, $body);
    $body = str_replace('{{verificationLink}}', $verification_link, $body);

    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = (SMTP_SECURE === 'tls') ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, $appName); // Use dynamic app name
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Account on ' . $appName; // Use dynamic app name
        $mail->Body = $body;
        $mail->AltBody = "Please copy and paste this link into your browser to verify: $verification_link";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error for {$email}: {$mail->ErrorInfo}");
        return false;
    }
}


function sendAdminPurchaseNotification($user_id, $plan_id, $amount, $sender, $trx_id)
{
    global $pdo;

    // Fetch required data
    $stmt_user = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $user_email = $stmt_user->fetchColumn();

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
    $body = file_get_contents('admin_purchase_notification.html');

    // Replace placeholders
    $replacements = [
        '{{appName}}' => $appName,
        '{{logoUrl}}' => $logoUrl,
        '{{userEmail}}' => $user_email,
        '{{planName}}' => $plan_name,
        '{{amountPaid}}' => $amount,
        '{{senderNumber}}' => $sender,
        '{{transactionId}}' => $trx_id,
        '{{adminLink}}' => APP_URL . '/admin.php#subscriptions'
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
        $mail->addAddress(ADMIN_NOTIFICATION_EMAIL);
        $mail->isHTML(true);
        $mail->Subject = 'New Subscription Purchase Request on ' . $appName;
        $mail->Body = $body;
        $mail->send();
    } catch (Exception $e) {
        error_log("Admin notification mailer error: {$mail->ErrorInfo}");
    }
}

function sendPasswordResetEmail($email, $token)
{
    global $pdo;

    // Fetch latest app settings
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('app_name', 'app_logo_url')");
    $settings = array_column($stmt->fetchAll(), 'setting_value', 'setting_key');
    $appName = $settings['app_name'] ?? 'CourierPlus';
    $logoUrl = ($settings['app_logo_url'] ?? '') ? APP_URL . '/' . $settings['app_logo_url'] : '';

    require_once 'src/Exception.php';
    require_once 'src/PHPMailer.php';
    require_once 'src/SMTP.php';

    $mail = new PHPMailer(true);
    $reset_link = APP_URL . "/reset-password.php?token=$token";

    // Use the new password reset template
    $body = file_get_contents('password_reset_template.html');
    $body = str_replace('{{appName}}', $appName, $body);
    $body = str_replace('{{logoUrl}}', $logoUrl, $body);
    $body = str_replace('{{resetLink}}', $reset_link, $body);

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
        $mail->Subject = 'Password Reset Request for ' . $appName;
        $mail->Body = $body;
        $mail->AltBody = "Please copy and paste this link to reset your password: $reset_link";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Password Reset Mailer Error for {$email}: {$mail->ErrorInfo}");
        return false;
    }
}




// In api/index.php, REPLACE the existing autocomplete_address function with this one.

function autocomplete_address($input, $pdo)
{
    $address_query = $input['address'] ?? '';
    if (empty($address_query)) {
        json_response(['places' => []]);
    }

    // 1. Get service choice and relevant API key from settings
// --- 1. Get service choice and relevant API key from settings (MODIFIED BLOCK) ---
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('autocomplete_service', 'google_maps_api_key', 'barikoi_api_key')");
    $settings = array_column($stmt->fetchAll(), 'setting_value', 'setting_key');

    $service = $settings['autocomplete_service'] ?? 'barikoi';
    $api_key = null;
    $url = '';

    if ($service === 'google') {
        $api_key = $settings['google_maps_api_key'] ?? null;
        if ($api_key) {
            // NOTE: The user did not ask for multiple Google keys, so this part is unchanged.
            $url = "https://maps.googleapis.com/maps/api/place/autocomplete/json?input=" . urlencode($address_query) . "&key=" . $api_key . "&components=country:bd";
        }
    } else { // It's Barikoi
        $barikoi_api_keys_str = $settings['barikoi_api_key'] ?? null;
        if ($barikoi_api_keys_str) {
            $keys = array_filter(array_map('trim', explode(',', $barikoi_api_keys_str)));
            if (!empty($keys)) {
                // Select one random key from the array
                $api_key = $keys[array_rand($keys)];
                $url = "https://barikoi.xyz/v2/api/search/autocomplete/place?api_key=" . $api_key . "&q=" . urlencode($address_query);
            }
        }
    }

    if (!$api_key) {
        json_response(['error' => 'The API key for the selected service (' . ucfirst($service) . ') is not set or is configured incorrectly in the admin panel.'], 500);
    }
    // --- END OF MODIFICATION ---

    // 2. Build URL and call the selected API
    if ($service === 'google') {
        $url = "https://maps.googleapis.com/maps/api/place/autocomplete/json?input=" . urlencode($address_query) . "&key=" . $api_key . "&components=country:bd";
    } else {
        $url = "https://barikoi.xyz/v2/api/search/autocomplete/place?api_key=" . $api_key . "&q=" . urlencode($address_query);
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response_body = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response_body, true);

    // 3. NEW: Check for API-specific errors and report them
    if ($service === 'google' && isset($data['status']) && !in_array($data['status'], ['OK', 'ZERO_RESULTS'])) {
        json_response(['error' => 'Google Maps API Error: ' . ($data['error_message'] ?? $data['status'])], 500);
    }
    if ($service === 'barikoi' && isset($data['status']) && $data['status'] != 200) {
        json_response(['error' => 'Barikoi API Error: ' . ($data['message'] ?? 'Unknown Error')], 500);
    }

    // 4. Standardize the response to a single format
    $final_address = null;
    if ($service === 'google' && !empty($data['predictions'])) {
        $final_address = $data['predictions'][0]['description'];
    } elseif ($service === 'barikoi' && !empty($data['places'])) {
        $final_address = $data['places'][0]['address'];
    }

    if ($final_address) {
        json_response(['places' => [['address' => $final_address]]]);
    } else {
        json_response(['places' => []]);
    }
}





function local_db_autocomplete($input, $pdo)
{
    $original_query = trim($input['address'] ?? '');

    return json_response(['address' => $original_query]); // Disable this line to enable local_db_autocomplete

    if (empty($original_query)) {
        json_response(['address' => $original_query]);
        return;
    }

    // Tokenize the input string into words, removing common noise
    $tokens = preg_split('/[\s,]+/', $original_query, -1, PREG_SPLIT_NO_EMPTY);
    if (empty($tokens)) {
        json_response(['address' => $original_query]);
        return;
    }

    // --- Language Detection: Check the whole string for any English characters ---
    $is_english = (bool) preg_match('/[a-zA-Z]/', $original_query);

    // --- Build Query based on language ---
    $whereClauses = [];
    $bindings = [];
    $search_columns = $is_english
        ? ['up.name', 'u.name', 'd.name']
        : ['up.bengali_name', 'u.bengali_name', 'd.bengali_name'];

    foreach ($tokens as $token) {
        $searchTerm = '%' . $token . '%';
        foreach ($search_columns as $column) {
            $whereClauses[] = "$column LIKE ?";
            $bindings[] = $searchTerm;
        }
    }

    $sql = "
        SELECT DISTINCT
            up.name as upazila,
            up.bengali_name as bn_upazila,
            d.name as district,
            d.bengali_name as bn_district
        FROM upazilas up
        JOIN districts d ON up.district_id = d.id
        LEFT JOIN unions u ON u.upazila_id = up.id
        WHERE " . implode(' OR ', $whereClauses) . "
        LIMIT 100
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($bindings);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($results)) {
        json_response(['address' => $original_query]);
        return;
    }

    // --- Advanced Fuzzy Scoring Logic ---
    $bestMatch = null;
    $highestScore = 0;

    foreach ($results as $row) {
        $currentScore = 0;

        // Prepare database names for comparison
        $db_upazila = $is_english ? strtolower($row['upazila']) : $row['bn_upazila'];
        $db_district = $is_english ? strtolower($row['district']) : $row['bn_district'];

        foreach ($tokens as $token) {
            $input_token = $is_english ? strtolower($token) : $token;

            // Calculate similarity score (lower is better). We are using Levenshtein distance.
            $dist_upazila = levenshtein($input_token, $db_upazila);
            $dist_district = levenshtein($input_token, $db_district);

            // Give points based on how close the match is. Perfect match gets high score.
            // This rewards better spelling.
            if ($dist_upazila <= 2) {
                // A perfect match (dist 0) gets 50 points, a 1-typo match gets 25, a 2-typo match gets 12.5
                $currentScore += (50 / ($dist_upazila + 1));
            }
            if ($dist_district <= 2) {
                $currentScore += (50 / ($dist_district + 1));
            }
        }

        if ($currentScore > $highestScore) {
            $highestScore = $currentScore;
            $bestMatch = $row;
        }
    }

    // --- Address Construction based on Confidence Score ---
    // A score over 75 is a high-confidence match (likely found both upazila and district).
    if ($bestMatch && $highestScore > 75) {
        $completedAddress = $bestMatch['upazila'] . ', ' . $bestMatch['district'];
        json_response(['address' => $completedAddress]);
    } else {
        // If no confident match, return the original text.
        json_response(['address' => $original_query]);
    }
}


function runFraudCheckOnRandomServer($user_id, $input, $pdo)
{

    $servers = [
        'check_fraud_risk', //https://fraud-checker.storex.com.bd/
        'processFraudCheckRequest', //https://fraudchecker.link/free-fraud-checker-bd/
        'checkFraudRiskElite' //https://elitemart.com.bd/fraud-check
    ];

    $randomServer = $servers[array_rand($servers)]; // randomly pick one
    $randomServer($user_id, $input, $pdo); // call it

    $randomServer = rand(0, 2);


}



function runFraudCheckOnBestServer($user_id, $input, $pdo)
{
    // --- 1. Check premium access first (common for all servers) ---
    $stmt_user_plan = $pdo->prepare("SELECT p.can_check_risk FROM users u JOIN plans p ON u.plan_id = p.id WHERE u.id = ?");
    $stmt_user_plan->execute([$user_id]);
    if (!$stmt_user_plan->fetchColumn()) {
        json_response(['error' => 'Risk checking is not available on your current plan.'], 403);
    }

    $phone = $input['phone'] ?? null;
    if (!$phone || !preg_match('/^01[3-9]\d{8}$/', $phone)) {
        json_response(['error' => 'Invalid or missing phone number.'], 400);
    }

    $servers = [
        [
            'url' => 'https://fraud-checker.storex.com.bd/',
            'function' => 'tryFraudCheckStorex'
        ],
        /* Temporarily disabled: Returns N/A for major couriers
        [
            'url' => 'https://fraudchecker.link/free-fraud-checker-bd/',
            'function' => 'tryFraudCheckLink'
        ],
        [
            'url' => 'https://elitemart.com.bd/fraud-check',
            'function' => 'tryFraudCheckElite'
        ],
        */
        [
            'url' => 'https://onecodesoft.com/fraudchecker',
            'function' => 'tryFraudCheckOnecodesoft'
        ],
        [
            'url' => 'https://www.bdcommerce.app/tools/delivery-fraud-check/',
            'function' => 'tryFraudCheckBDCommerce'
        ],
    ];

    // Helper: test server latency
    function getLatency($url, $timeout = 2.0)
    {
        $start = microtime(true);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode >= 200 && $httpCode < 500) {
            return (microtime(true) - $start) * 1000.0;
        }
        return null;
    }

    // Measure all servers and sort by latency
    $results = [];
    foreach ($servers as $server) {
        $lat = getLatency($server['url']);
        $results[] = ['server' => $server, 'latency' => $lat ?? INF];
    }
    usort($results, fn($a, $b) => $a['latency'] <=> $b['latency']);



    // Try each server in order until one succeeds with trustworthy data
    $lastError = 'All fraud check servers failed.';
    $suspiciousData = null;
    $suspiciousServer = null;

    foreach ($results as $result) {
        $server = $result['server'];
        $functionName = $server['function'];

        error_log("Trying fraud check server: {$server['url']} (latency: {$result['latency']} ms)");

        if (function_exists($functionName)) {
            try {
                // Use output buffering to capture any stray PHP warnings/errors
                ob_start();
                $data = @$functionName($phone); // Suppress warnings
                $strayOutput = ob_get_clean();

                if (!empty($strayOutput)) {
                    error_log("Server {$server['url']} produced stray output: " . substr($strayOutput, 0, 200));
                }

                // Validate the response is a valid array with courier data
                if (!is_array($data)) {
                    $lastError = 'Server returned invalid data type';
                    error_log("Server {$server['url']} returned non-array: " . gettype($data));
                    continue;
                }

                if (isset($data['error'])) {
                    $lastError = $data['error'];
                    error_log("Server {$server['url']} returned error: $lastError");
                    continue;
                }

                if (empty($data)) {
                    $lastError = 'Server returned empty data';
                    error_log("Server {$server['url']} returned empty array");
                    continue;
                }

                // Check if result looks suspicious (0% success on all)
                if (isSuspiciousResult($data)) {
                    error_log("Server {$server['url']} returned suspicious data (0% success). Verifying with next server...");
                    // Save this result in case all servers return the same
                    if ($suspiciousData === null) {
                        $suspiciousData = $data;
                        $suspiciousServer = $server['url'];
                    }
                    continue; // Try next server to verify
                }

                // Validate data structure has proper courier fields
                $firstItem = $data[0] ?? null;
                if (!$firstItem || !isset($firstItem['courier'])) {
                    $lastError = 'Server returned malformed courier data';
                    error_log("Server {$server['url']} returned data without 'courier' field");
                    continue;
                }

                // Success with trustworthy data!
                json_response($data);
                return;

            } catch (Throwable $e) {
                // Catch all errors including TypeError, Error, etc.
                $lastError = $e->getMessage();
                error_log("Server {$server['url']} exception: $lastError");
                continue;
            }
        } else {
            error_log("Function {$functionName} not found");
        }
    }

    // If we have suspicious data but no trustworthy data, return the suspicious data
    // (it might be accurate - customer really has 0% success)
    if ($suspiciousData !== null) {
        error_log("Returning suspicious data from {$suspiciousServer} - all servers returned similar results");
        json_response($suspiciousData);
        return;
    }

    // All servers failed
    json_response(['error' => $lastError], 502);
}

// --- Fraud Check Helper Functions (return data instead of outputting JSON) ---

function tryFraudCheckStorex($phone)
{
    $base = 'https://fraud-checker.storex.com.bd';
    $homepage = $base . '/';
    $apiUrl = $base . '/api/fraud-checker-direct?phone=' . urlencode($phone);
    $cookieFile = tempnam(sys_get_temp_dir(), 'fcd_ck_');

    $commonOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/141.0.0.0',
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_ENCODING => "",
        CURLOPT_TIMEOUT => 30,
    ];

    // GET homepage for session cookie
    $ch = curl_init($homepage);
    curl_setopt_array($ch, $commonOptions);
    curl_exec($ch);
    curl_close($ch);

    // GET API
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, $commonOptions);
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    @unlink($cookieFile);

    if ($code != 200 || !$html) {
        return ['error' => "Storex server returned HTTP $code"];
    }

    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $rows = $xpath->query("//div[contains(@class, 'space-y-2')]/div[contains(@class, 'grid-cols-5')]");

    $courier_data = [];
    foreach ($rows as $row) {
        $img = $xpath->query(".//img", $row)->item(0);
        $cols = $xpath->query(".//p", $row);
        $courier_data[] = [
            'courier' => $img ? $img->getAttribute('alt') : 'unknown',
            'orders' => trim($cols->item(0)->textContent ?? '0'),
            'delivered' => trim($cols->item(1)->textContent ?? '0'),
            'cancelled' => trim($cols->item(2)->textContent ?? '0'),
            'cancel_rate' => trim($cols->item(3)->textContent ?? '0%'),
            'server' => $base
        ];
    }

    return empty($courier_data) ? ['error' => 'No data found on Storex'] : $courier_data;
}

function tryFraudCheckLink($phone)
{
    $url = 'https://fraudchecker.link/free-fraud-checker-bd/';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['phone' => $phone]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code != 200 || !$html) {
        return ['error' => "FraudChecker.link returned HTTP $code"];
    }

    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $rows = $xpath->query('//th[contains(text(), "কুরিয়ার")]/ancestor::table/tbody/tr');

    $courier_data = [];
    foreach ($rows as $row) {
        $cells = $row->getElementsByTagName('td');
        if ($cells->length >= 4) {
            $courierNode = $row->getElementsByTagName('b');
            $courierName = $courierNode->length > 0 ? trim($courierNode->item(0)->nodeValue) : trim($cells->item(0)->textContent);

            $orders = (int) trim($cells->item(1)->nodeValue);
            $delivered = (int) trim($cells->item(2)->nodeValue);
            $cancelled = (int) trim($cells->item(3)->nodeValue);
            $total = $delivered + $cancelled;
            $cancelRate = $total > 0 ? round(($cancelled / $total) * 100, 2) : 0;

            $courier_data[] = [
                'courier' => $courierName ?: 'N/A',
                'orders' => $orders,
                'delivered' => $delivered,
                'cancelled' => $cancelled,
                'cancel_rate' => $cancelRate . '%',
                'server' => $url
            ];
        }
    }

    return empty($courier_data) ? ['error' => 'No data found on FraudChecker.link'] : $courier_data;
}

function tryFraudCheckElite($phone)
{
    // Elite uses same endpoint as Link but different parsing might be needed
    // For now, try the same approach
    return tryFraudCheckLink($phone);
}

function tryFraudCheckOnecodesoft($phone)
{
    $url = 'https://onecodesoft.com/fraudchecker?phone=' . urlencode($phone);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code != 200 || !$html) {
        return ['error' => "Onecodesoft returned HTTP $code"];
    }

    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    // Find the table with class 'modern-table'
    $rows = $xpath->query("//table[contains(@class, 'modern-table')]/tbody/tr");

    $courier_data = [];
    foreach ($rows as $row) {
        $cells = $row->getElementsByTagName('td');
        if ($cells->length >= 5) {
            // Get courier name (first cell)
            $courierCell = $cells->item(0);
            $courier_name = trim($courierCell->textContent);

            // Get stats from other cells using data-label attributes
            $orders = 0;
            $delivered = 0;
            $cancelled = 0;
            $cancelRate = '0%';

            foreach ($cells as $cell) {
                $label = $cell->getAttribute('data-label');
                $value = trim($cell->textContent);

                switch ($label) {
                    case 'মোট অর্ডার':
                        $orders = (int) preg_replace('/[^0-9]/', '', $value);
                        break;
                    case 'সফল':
                        $delivered = (int) preg_replace('/[^0-9]/', '', $value);
                        break;
                    case 'বাতিল':
                        $cancelled = (int) preg_replace('/[^0-9]/', '', $value);
                        break;
                    case 'রিটার্ন রেট':
                        $cancelRate = $value;
                        break;
                }
            }

            // Skip Pathao Rating row or any row without proper courier name
            if (empty($courier_name) || stripos($courier_name, 'rating') !== false) {
                continue;
            }

            $courier_data[] = [
                'courier' => $courier_name,
                'orders' => $orders,
                'delivered' => $delivered,
                'cancelled' => $cancelled,
                'cancel_rate' => $cancelRate,
                'server' => 'https://onecodesoft.com/fraudchecker'
            ];
        }
    }

    return empty($courier_data) ? ['error' => 'No data found on Onecodesoft'] : $courier_data;
}


function tryFraudCheckBDCommerce($phone)
{
    // Ensure phone has +88 prefix
    if (strpos($phone, '+88') !== 0) {
        $phone = '+88' . preg_replace('/^\+88/', '', $phone);
    }

    $url = 'https://www.bdcommerce.app/tools/delivery-fraud-check/' . urlencode($phone);

    // Output buffering handled by orchestrator, but good practice to be clean
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code != 200 || !$html) {
        return ['error' => "BDCommerce returned HTTP $code"];
    }

    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    // Look for rows that look like courier data
    // Based on limited view, likely divs with flex/grid or table rows
    // Let's look for known courier names and traverse relative to them
    // Find all data rows. They seem to use border-b class for separation
    // Added 'justify-between' to avoid matching parent containers or footers
    $rows = $xpath->query("//div[contains(@class, 'border-b') and contains(@class, 'justify-between')]");

    $courier_data = [];

    foreach ($rows as $row) {
        // 1. Identify Courier from Image Source
        $imgNode = $xpath->query(".//img", $row)->item(0);
        if (!$imgNode)
            continue;

        $src = $imgNode->getAttribute('src') . $imgNode->getAttribute('srcset');
        $courierName = null;

        if (stripos($src, 'pathao') !== false)
            $courierName = 'Pathao';
        elseif (stripos($src, 'redx') !== false)
            $courierName = 'RedX';
        elseif (stripos($src, 'steadfast') !== false)
            $courierName = 'Steadfast';
        elseif (stripos($src, 'paperfly') !== false)
            $courierName = 'Paperfly';
        elseif (stripos($src, 'ecourier') !== false)
            $courierName = 'eCourier';
        elseif (stripos($src, 'parceldex') !== false)
            $courierName = 'Parceldex';
        elseif (stripos($src, 'carrybee') !== false)
            $courierName = 'Carrybee';

        if (!$courierName)
            continue; // Skip unknown rows

        // 2. Extract Stats based on classes
        $totalNode = $xpath->query(".//span[contains(@class, 'text-center')]", $row)->item(0);
        $cancelledNode = $xpath->query(".//span[contains(@class, 'text-danger')]", $row)->item(0);
        $deliveredNode = $xpath->query(".//span[contains(@class, 'text-secondary')]", $row)->item(0);

        // Sanitize and Validate Inputs
        $rawOrders = $totalNode ? trim($totalNode->textContent) : '0';
        $rawCancelled = $cancelledNode ? trim($cancelledNode->textContent) : '0';
        $rawDelivered = $deliveredNode ? trim($deliveredNode->textContent) : '0';

        // Filter out phone numbers/TINs (e.g. if length > 5, it's garbage)
        if (strlen($rawOrders) > 5 || strlen($rawCancelled) > 5)
            continue;

        $orders = (int) $rawOrders;
        $cancelled = (int) $rawCancelled;
        $delivered = (int) $rawDelivered;

        if ($orders > 0 || $delivered > 0 || $cancelled > 0) {
            $courier_data[] = [
                'courier' => $courierName,
                'orders' => $orders,
                'delivered' => $delivered,
                'cancelled' => $cancelled,
                'cancel_rate' => ($orders > 0 ? round(($cancelled / $orders) * 100) : 0) . '%',
                'server' => "https://www.bdcommerce.app"
            ];
        }
    }

    return empty($courier_data) ? ['error' => 'No data found on BDCommerce'] : $courier_data;
}




/**
 * Helper function to check if result looks suspicious (all 0% success).
 * If a server returns data where NO courier shows any delivery success, it might be a false negative or bad data.
 * We treat this as 'suspicious' to trigger a retry on another server.
 */
function isSuspiciousResult($data)
{
    if (!is_array($data) || empty($data)) {
        return true;
    }

    foreach ($data as $courier) {
        // If any courier has delivered > 0, it's trustworthy
        $delivered = is_numeric($courier['delivered']) ? (int) $courier['delivered'] : 0;
        if ($delivered > 0) {
            return false;
        }
    }
    // All couriers have 0 delivered - suspicious
    return true;
}


/**
 * Fetches and parses fraud check data for a given phone number.
 * @param array $input The input data containing the 'phone' number.
 * @return void Outputs JSON response with parsed data or an error.
 * 
 */
function check_fraud_risk($user_id, $input, $pdo)
{

    // --- 1. Check premium access ---
    $stmt_user_plan = $pdo->prepare("SELECT p.can_check_risk FROM users u JOIN plans p ON u.plan_id = p.id WHERE u.id = ?");
    $stmt_user_plan->execute([$user_id]);
    if (!$stmt_user_plan->fetchColumn()) {
        json_response(['error' => 'Risk checking is not available on your current plan.'], 403);
    }

    $phone = $input['phone'] ?? null;

    // Basic validation
    if (!$phone || !preg_match('/^01[3-9]\d{8}$/', $phone)) {
        json_response(['error' => 'Invalid or missing phone number.'], 400);
    }

    $base = 'https://fraud-checker.storex.com.bd';
    $homepage = $base . '/';
    $apiUrl = $base . '/api/fraud-checker-direct?phone=' . urlencode($phone);

    // Temporary cookie file for automatic cookie handling
    $cookieFile = tempnam(sys_get_temp_dir(), 'fcd_ck_');

    // Common cURL options
    $commonOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HEADER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36',
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_ENCODING => "", // auto decompress gzip/deflate/brotli
        CURLOPT_TIMEOUT => 60,
    ];

    // 1) GET homepage to obtain session cookie
    $ch = curl_init($homepage);
    curl_setopt_array($ch, $commonOptions + [
        CURLOPT_HTTPHEADER => [
            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "Referer: $base/"
        ]
    ]);
    curl_exec($ch); // ignore response
    curl_close($ch);

    // 2) GET direct API with phone number using the same cookie
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, $commonOptions + [
        CURLOPT_HTTPHEADER => [
            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "Referer: $base/"
        ]
    ]);
    $html_content = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Remove temporary cookie file
    @unlink($cookieFile);

    if ($http_code != 200 || !$html_content) {
        json_response(['error' => 'Failed to fetch data from the fraud checker service.'], 502);
    }

    // Parse HTML using DOMDocument + DOMXPath
    $dom = new DOMDocument();
    @$dom->loadHTML($html_content);
    $xpath = new DOMXPath($dom);

    $courier_data = [];

    // Adjust the XPath according to the actual HTML structure in Postman
    $rows = $xpath->query("//div[contains(@class, 'space-y-2')]/div[contains(@class, 'grid-cols-5')]");

    foreach ($rows as $row) {
        $img = $xpath->query(".//img", $row)->item(0);
        $courier_name = $img ? $img->getAttribute('alt') : 'unknown';

        $cols = $xpath->query(".//p", $row);
        $courier_data[] = [
            'courier' => $courier_name,
            'orders' => trim($cols->item(0)->textContent ?? '0'),
            'delivered' => trim($cols->item(1)->textContent ?? '0'),
            'cancelled' => trim($cols->item(2)->textContent ?? '0'),
            'cancel_rate' => trim($cols->item(3)->textContent ?? '0%'),
            'server' => $base
        ];
    }

    if (empty($courier_data)) {
        json_response(['error' => 'No courier data found. The customer may be new.'], 404);
    }

    // Return structured JSON
    json_response($courier_data);
}


function processFraudCheckRequest($user_id, $input, $pdo)
{
    // --- 1. Input Validation ---
    $stmt_user_plan = $pdo->prepare("SELECT p.can_check_risk FROM users u JOIN plans p ON u.plan_id = p.id WHERE u.id = ?");
    $stmt_user_plan->execute([$user_id]);
    if (!$stmt_user_plan->fetchColumn()) {
        json_response(['error' => 'Risk checking is not available on your current plan.'], 403);
    }

    $phone = $input['phone'] ?? null;

    // Basic validation
    if (!$phone || !preg_match('/^01[3-9]\d{8}$/', $phone)) {
        json_response(['error' => 'Invalid or missing phone number.'], 400);
    }

    if (!$phone || !preg_match('/^01[3-9]\d{8}$/', $phone)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or missing phone number.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- 2. cURL Request ---
    $url = 'https://fraudchecker.link/free-fraud-checker-bd/';
    $postData = http_build_query(['phone' => $phone]);
    $headers = [
        'Authority: fraudchecker.link',
        'Content-Type: application/x-www-form-urlencoded',
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $serverResponse = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || empty($serverResponse)) {
        http_response_code(502);
        echo json_encode(['error' => 'Failed to fetch data from the fraud checker service.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- 3. Data Extraction (HTML Parsing) ---
    $courier_data = [];
    $dom = new DOMDocument();
    @$dom->loadHTML($serverResponse);
    $xpath = new DOMXPath($dom);
    $rows = $xpath->query('//th[contains(text(), "কুরিয়ার")]/ancestor::table/tbody/tr');

    if ($rows->length > 0) {
        foreach ($rows as $row) {
            $cells = $row->getElementsByTagName('td');
            if ($cells->length >= 4) {
                $courierNode = $row->getElementsByTagName('b');
                $courier_name = $courierNode->length > 0 ? trim($courierNode->item(0)->nodeValue) : 'N/A';

                $orders = (int) trim($cells->item(1)->nodeValue);
                $delivered = (int) trim($cells->item(2)->nodeValue);
                $cancelled = (int) trim($cells->item(3)->nodeValue);

                $total = $delivered + $cancelled;

                if ($total > 0) {
                    $cancelRate = round(($cancelled / $total) * 100, 2);
                } else {
                    $cancelRate = 0;
                }

                $courier_data[] = [
                    'courier' => trim($courier_name),
                    'orders' => $orders,
                    'delivered' => $delivered,
                    'cancelled' => $cancelled,
                    'cancel_rate' => $cancelRate . '%',
                    'server' => $url
                ];



            }
        }
    }

    // --- 4. Final JSON Response ---
    if (empty($courier_data)) {
        http_response_code(404);
        echo json_encode(['error' => 'No courier data found. The customer may be new.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(200);
    echo json_encode($courier_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}


function checkFraudRiskElite($user_id, $input, $pdo)
{

    $stmt_user_plan = $pdo->prepare("SELECT p.can_check_risk FROM users u JOIN plans p ON u.plan_id = p.id WHERE u.id = ?");
    $stmt_user_plan->execute([$user_id]);
    if (!$stmt_user_plan->fetchColumn()) {
        json_response(['error' => 'Risk checking is not available on your current plan.'], 403);
    }



    $phone = $input['phone'] ?? null;

    // --- 1. Input Validation ---
    if (!$phone || !preg_match('/^01[3-9]\d{8}$/', $phone)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid or missing phone number. Please provide a phone number in the URL, e.g., ?phone=01...'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- 2. cURL Request ---
    $url = 'https://fraudchecker.link/free-fraud-checker-bd/';
    $postData = http_build_query(['phone' => $phone]);
    $headers = [
        'Authority: fraudchecker.link',
        'Content-Type: application/x-www-form-urlencoded',
        'Origin: https://fraudchecker.link', // Adding Origin header for robustness
        'Referer: https://fraudchecker.link/free-fraud-checker-bd/', // Adding Referer header
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $serverResponse = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || empty($serverResponse)) {
        http_response_code(502); // Bad Gateway
        echo json_encode(['error' => 'Failed to fetch data from the fraud checker service.', 'details' => $curlError], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- 3. Data Extraction (HTML Parsing) ---
    $courier_data = [];
    $dom = new DOMDocument();
    // Suppress warnings from potentially malformed HTML
    @$dom->loadHTML($serverResponse);
    $xpath = new DOMXPath($dom);

    // This XPath finds the table by looking for a header cell containing "কুরিয়ার"
    $rows = $xpath->query('//th[contains(text(), "কুরিয়ার")]/ancestor::table/tbody/tr');

    if ($rows->length > 0) {
        foreach ($rows as $row) {
            $cells = $row->getElementsByTagName('td');
            // Ensure the row has the expected number of cells
            if ($cells->length >= 4) {
                // The courier name is inside a <b> tag within the first cell
                $courierNode = $cells->item(0)->getElementsByTagName('b');
                $courier_name = $courierNode->length > 0 ? trim($courierNode->item(0)->nodeValue) : 'N/A';

                // Extract and sanitize numeric data
                $orders = (int) trim($cells->item(1)->nodeValue);
                $delivered = (int) trim($cells->item(2)->nodeValue);
                $cancelled = (int) trim($cells->item(3)->nodeValue);

                // Calculate total and cancel rate
                $total = $delivered + $cancelled;
                $cancelRate = 0;
                if ($total > 0) {
                    $cancelRate = round(($cancelled / $total) * 100, 2);
                }

                // Add the structured data to our array
                $courier_data[] = [
                    'courier' => $courier_name,
                    'orders' => $orders,
                    'delivered' => $delivered,
                    'cancelled' => $cancelled,
                    'cancel_rate' => $cancelRate . '%',
                    'server' => $url
                ];
            }
        }
    }

    // --- 4. Final JSON Response ---
    if (empty($courier_data)) {
        http_response_code(404); // Not Found
        echo json_encode(['error' => 'No courier data found. The customer may be new or data is unavailable.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(200); // OK
    echo json_encode($courier_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}


/**
 * Corrects a single address string using the Gemini AI API.
 */
function correct_single_address_with_ai($user_id, $input, $pdo)
{
    // 1. Check if the user's plan has AI permissions
    $stmt_user_plan = $pdo->prepare("SELECT p.can_correct_address FROM users u JOIN plans p ON u.plan_id = p.id WHERE u.id = ?");
    $stmt_user_plan->execute([$user_id]);
    if (!$stmt_user_plan->fetchColumn()) {
        json_response(['error' => 'Address Correction is not available on your current plan.'], 403);
    }

    // --- 2. Get Gemini API key (MODIFIED BLOCK) ---
    $stmt_settings = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'gemini_api_key'");
    $stmt_settings->execute();
    $gemini_api_keys_str = $stmt_settings->fetchColumn();
    if (!$gemini_api_keys_str) {
        json_response(['error' => 'The Gemini API key has not been set by the administrator.'], 500);
    }
    // Explode the string into an array of keys, trimming whitespace and removing empty entries
    $keys = array_filter(array_map('trim', explode(',', $gemini_api_keys_str)));
    if (empty($keys)) {
        json_response(['error' => 'Gemini API keys are configured incorrectly in the admin panel.'], 500);
    }
    // Select one random key from the array
    $gemini_api_key = $keys[array_rand($keys)];
    // --- END OF MODIFICATION ---

    $address = $input['address'] ?? '';
    $clean_input_address = trim($address);

    if (empty($clean_input_address)) {
        json_response(['error' => 'No address was provided for correction.'], 400);
    }

    // --- BACKEND PROTECTION: Deduplication / Caching ---
    // 1. Ensure Table Exists
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_address_corrections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            address_hash VARCHAR(64),
            original_address TEXT,
            corrected_address TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_hash (user_id, address_hash)
        )");
    } catch (Exception $e) {
    }

    // 2. Check for recent cached correction (e.g., last 30 days)
    $address_hash = hash('sha256', mb_strtolower($clean_input_address)); // Normalize case
    $stmt_check = $pdo->prepare("SELECT corrected_address FROM ai_address_corrections WHERE user_id = ? AND address_hash = ? AND created_at > (NOW() - INTERVAL 30 DAY) LIMIT 1");
    $stmt_check->execute([$user_id, $address_hash]);
    $cached_address = $stmt_check->fetchColumn();

    if ($cached_address) {
        // Return cached result immediately (No AI usage, No cost)
        json_response(['corrected_address' => $cached_address, 'cached' => true]);
    }

    // 3. Refined prompt for a single address
    $prompt = "You are an expert address corrector for Bangladesh. Your task is to correct and complete the following address. Format the output as a single, clean line: Full Address, Thana/Upazila, District. Do not add any extra text, labels, or markdown formatting. Just return the corrected address string. The input language can be English or Bengali; match the output language to the input language.\n\nInput Address: \"" . $clean_input_address . "\"\n\nCorrected Address:";

    $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=' . $gemini_api_key;
    $api_body = ['contents' => [['parts' => [['text' => $prompt]]]]];

    // 4. Send request to Gemini API
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($api_body));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response_body = curl_exec($ch);

    if (curl_errno($ch)) {
        $curl_error = curl_error($ch);
        curl_close($ch);
        json_response(['error' => 'cURL Error: Could not connect to Gemini API.', 'details' => $curl_error], 500);
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 400) {
        $error_details = json_decode($response_body, true);
        $error_msg = $error_details['error']['message'] ?? 'Unknown API error';
        json_response(['error' => 'The AI API returned an error: ' . $error_msg], $http_code);
    }

    // 5. Parse and clean the AI response
    $response_data = json_decode($response_body, true);
    $corrected_address = $response_data['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if ($corrected_address) {
        // Clean up the response
        $cleaned_address = trim(str_replace(['`', '*', '\n', 'Corrected Address:', 'Address:'], '', $corrected_address));

        // --- SAVE TO CACHE ---
        try {
            $stmt_save = $pdo->prepare("INSERT INTO ai_address_corrections (user_id, address_hash, original_address, corrected_address) VALUES (?, ?, ?, ?)");
            $stmt_save->execute([$user_id, $address_hash, $clean_input_address, $cleaned_address]);
        } catch (Exception $e) {
        }

        json_response(['corrected_address' => $cleaned_address, 'cached' => false]);
    } else {
        json_response(['error' => 'Failed to parse corrected address from AI response.'], 500);
    }
}



/**
 * Helper to match address to Redx Area ID using AI.
 */
function matchRedxAreaWithAI($address, $gemini_api_key, $redx_access_token)
{
    $cacheFile = __DIR__ . '/cache/redx_areas.json';
    $areas = [];

    // 1. Load or Fetch Full Area List (Keep existing Cache Logic)
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400 * 7)) {
        $areas = json_decode(file_get_contents($cacheFile), true);
    } else {
        $ch = curl_init("https://openapi.redx.com.bd/v1.0.0-beta/areas");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['API-ACCESS-TOKEN: Bearer ' . $redx_access_token]);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 200) {
            $json = json_decode($res, true);
            if (!empty($json['areas'])) {
                $areas = $json['areas'];
                if (!is_dir(__DIR__ . '/cache'))
                    mkdir(__DIR__ . '/cache', 0777, true);
                file_put_contents($cacheFile, json_encode($areas));
            }
        }
    }

    if (empty($areas))
        throw new Exception("Redx Area List could not be loaded.");

    // 2. AI Step 1: Extract District and Upazila
    // We explicitly ask for standard English spelling.
    $prompt_district = "Analyze this address: '$address'. Extract the 'District Name' and 'Upazila Name' (or Thana/Area) of Bangladesh in standard English Spelling. 
    IMPORTANT: Use legacy spellings for Districts: 'Chittagong' (NOT Chattogram), 'Comilla' (NOT Cumilla), 'Barisal' (NOT Barishal), 'Jessore' (NOT Jashore), 'Bogra' (NOT Bogura). 
    Return JSON: {\"district\": \"DistrictName\", \"upazila\": \"UpazilaName\"}";

    $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=' . $gemini_api_key;
    $api_body = ['contents' => [['parts' => [['text' => $prompt_district]]]], 'generationConfig' => ['responseMimeType' => 'application/json']];

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($api_body));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $res = curl_exec($ch);
    curl_close($ch);

    $district_name = 'Dhaka';
    $upazila_name = '';
    $json = json_decode($res, true);
    if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
        $raw = $json['candidates'][0]['content']['parts'][0]['text'];
        $clean = str_replace(['```json', '```'], '', $raw);
        $extracted = json_decode($clean, true);
        if (!empty($extracted['district']))
            $district_name = trim($extracted['district']);
        if (!empty($extracted['upazila']))
            $upazila_name = trim($extracted['upazila']);
    }

    // --- CODE FIX: Redx Specific District Mapping (Handling Typos/Legacy) ---
    // Maps Standard English -> Redx Database Value
    $redx_district_map = [
        'narsingdi' => 'Norshingdi', // Redx Typo
        'chattogram' => 'Chittagong', // Legacy
        'cumilla' => 'Comilla',
        'barishal' => 'Barisal',
        'jashore' => 'Jessore',
        'bogura' => 'Bogra',
        'cox\'s bazar' => "Cox's Bazar",
        'chapainawabganj' => 'Chapainawabganj',
        'mylensingh' => 'Mymensingh'
    ];
    $normalized_input = strtolower($district_name);
    if (isset($redx_district_map[$normalized_input])) {
        $district_name = $redx_district_map[$normalized_input];
    }
    // ------------------------------------------------------------------------

    // 3. Local Filter: Filter Cached List by District AND Upazila
    $candidates = array_filter($areas, function ($area) use ($district_name) {
        // District Filter (High Priority)
        return strcasecmp(trim($area['district_name']), $district_name) === 0;
    });

    if (empty($candidates)) {
        // Fallback District Fuzzy
        $candidates = array_filter($areas, function ($area) use ($district_name) {
            return stripos($area['district_name'], $district_name) !== false;
        });
    }

    if (empty($candidates)) {
        throw new Exception("No Redx areas found locally for district: '$district_name'.");
    }

    // Filter/Score by Upazila if available
    if (!empty($upazila_name)) {
        $upazila_candidates = [];
        foreach ($candidates as $area) {
            // Match Upazila with 'name' field
            if (stripos($area['name'], $upazila_name) !== false) {
                $upazila_candidates[] = $area;
            }
        }
        // If we found matches with Upazila, use them. Otherwise keep the district list.
        if (!empty($upazila_candidates)) {
            $candidates = $upazila_candidates;
        }
    }

    // 4. AI Step 2: Match Address against Candidates
    $simple_areas = array_map(fn($a) => [
        'id' => $a['id'],
        'name' => $a['name'],
        'post_code' => $a['post_code']
    ], array_slice($candidates, 0, 50));

    $prompt_match = "Address: '$address'. (AI Extracted: District='$district_name', Upazila='$upazila_name')\nSelect the exact matching Area from this list: " . json_encode($simple_areas) . ".\nReturn JSON: {\"id\": <numeric_id>, \"name\": \"<area_name>\"}";

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['contents' => [['parts' => [['text' => $prompt_match]]]], 'generationConfig' => ['responseMimeType' => 'application/json']]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $match_res = curl_exec($ch);
    curl_close($ch);

    $match_json = json_decode($match_res, true);
    if (isset($match_json['candidates'][0]['content']['parts'][0]['text'])) {
        $raw_match = $match_json['candidates'][0]['content']['parts'][0]['text'];
        $clean_match = str_replace(['```json', '```'], '', $raw_match);
        $match_data = json_decode($clean_match, true);
        if (!empty($match_data['id']))
            return ['id' => $match_data['id'], 'name' => $match_data['name'] ?? "Area " . $match_data['id']];
    }

    // Fallback: Use the first candidate from the district
    $first = reset($candidates);
    if ($first) {
        return ['id' => $first['id'], 'name' => $first['name']];
    }

    throw new Exception("Could not resolve Area ID with AI.");
}

// --- VISITOR TRACKING ---
function track_visitor($user_id, $input, $pdo)
{
    // Auto-migrate: Create visitors table if not exists
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS visitors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45),
            location VARCHAR(255),
            user_agent TEXT,
            start_time DATETIME,
            duration_millis INT DEFAULT 0,
            visit_count INT DEFAULT 1,
            user_id INT DEFAULT NULL,
            email VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_ip_ua (ip_address, user_agent(100))
        )");
    } catch (Exception $e) { /* Ignore if exists */
    }

    $ip = $input['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $location = $input['location'] ?? 'Unknown';
    $user_agent = $input['userAgent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $duration = (int) ($input['durationMillis'] ?? 0);
    $updateOnly = !empty($input['updateOnly']); // Flag for duration-only updates

    // Check if visitor exists (by IP + User Agent)
    // For updateOnly, we use server IP since client doesn't send it
    $lookup_ip = $updateOnly ? ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') : $ip;

    $stmt = $pdo->prepare("SELECT id, visit_count FROM visitors WHERE ip_address = ? AND user_agent = ? LIMIT 1");
    $stmt->execute([$lookup_ip, $user_agent]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if ($updateOnly) {
            // Duration-only update - don't modify visit_count, IP, or location
            $update_stmt = $pdo->prepare("UPDATE visitors SET duration_millis = ? WHERE id = ?");
            $update_stmt->execute([$duration, $existing['id']]);
            json_response(['success' => true, 'visitorId' => $existing['id'], 'isNew' => false]);
        } else {
            // Full update (new visit from same visitor)
            $new_count = $existing['visit_count'] + 1;
            $update_stmt = $pdo->prepare("UPDATE visitors SET 
                start_time = NOW(), 
                duration_millis = ?,
                visit_count = ?,
                user_id = COALESCE(?, user_id),
                email = COALESCE(?, email),
                location = ?
                WHERE id = ?");

            // Get email if user is logged in
            $email = null;
            if ($user_id) {
                $email_stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
                $email_stmt->execute([$user_id]);
                $email = $email_stmt->fetchColumn();
            }

            $update_stmt->execute([$duration, $new_count, $user_id, $email, $location, $existing['id']]);
            json_response(['success' => true, 'visitorId' => $existing['id'], 'isNew' => false]);
        }
    } else {
        // Create new visitor (only if not updateOnly)
        if ($updateOnly) {
            // Can't update what doesn't exist, return silently
            json_response(['success' => false, 'message' => 'Visitor not found']);
            return;
        }

        $email = null;
        if ($user_id) {
            $email_stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $email_stmt->execute([$user_id]);
            $email = $email_stmt->fetchColumn();
        }

        $insert_stmt = $pdo->prepare("INSERT INTO visitors (ip_address, location, user_agent, start_time, duration_millis, user_id, email) VALUES (?, ?, ?, NOW(), ?, ?, ?)");
        $insert_stmt->execute([$ip, $location, $user_agent, $duration, $user_id, $email]);
        $visitor_id = $pdo->lastInsertId();
        json_response(['success' => true, 'visitorId' => $visitor_id, 'isNew' => true]);
    }
}
