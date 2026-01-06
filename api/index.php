<?php
// Set session cookie parameters specifically to root path
session_set_cookie_params(0, '/');
session_start();
use PHPMailer\PHPMailer\PHPMailer;
require_once 'config.php';
use PHPMailer\PHPMailer\Exception;

// Decode JSON input from the request body
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? null;

// --- Authentication Handler ---save_parser_settings
if (in_array($action, ['register', 'login', 'logout', 'check_session', 'resend_verification', 'request_password_reset', 'perform_password_reset', 'google_login_url'])) {
    handle_auth($action, $input, $pdo);
}

// --- Authenticated User Actions ---
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    // This is a whitelist of actions that DO NOT require a logged-in user.
    $public_actions = ['register', 'login', 'check_session', 'resend_verification', 'load_user_data', 'request_password_reset', 'perform_password_reset'];

    if (!in_array($action, $public_actions)) {
        // If the action is not in our public whitelist, block it.
        json_response(['error' => 'Authentication required.'], 401);
    }
    // For public actions, we allow the script to continue.
}

switch ($action) {
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
    case 'get_subscription_data':
        $stmt_user = $pdo->prepare("
            SELECT u.plan_id, p.name as plan_name, p.order_limit_daily, p.order_limit_monthly,
                   u.plan_expiry_date, u.daily_order_count, u.monthly_order_count
            FROM users u
            JOIN plans p ON u.plan_id = p.id
            WHERE u.id = ?
        ");
        $stmt_user->execute([$user_id]);
        json_response($stmt_user->fetch());
        break;

    case 'get_available_plans':
        $stmt = $pdo->query("SELECT id, name, price, description, validity_days, order_limit_daily, order_limit_monthly FROM plans WHERE is_active = 1 AND price > 0 ORDER by name ASC");
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

        json_response(['success' => true, 'message' => 'Your request has been submitted and is pending review.']);
        break;
    // In the main switch($action) block in api/index.php
    case 'get_my_subscriptions':
        $stmt = $pdo->prepare("
            SELECT s.created_at, s.amount_paid, s.status, p.name as plan_name, pm.name as payment_method_name
            FROM subscriptions s
            JOIN plans p ON s.plan_id = p.id
            JOIN payment_methods pm ON s.payment_method_id = pm.id
            WHERE s.user_id = ?
            ORDER BY s.created_at DESC
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
}

// --- Function Implementations ---

function handle_auth($action, $input, $pdo)
{
    switch ($action) {
        case 'register':
            $email = filter_var($input['email'], FILTER_VALIDATE_EMAIL);
            $password = $input['password'];
            $display_name = trim($input['display_name'] ?? ''); // New field

            if (!$email || strlen($password) < 6) {
                json_response(['error' => 'Invalid email or password (min 6 chars).'], 400);
            }
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $token = bin2hex(random_bytes(32));

            try {
                // Modified INSERT to include display_name
                $stmt = $pdo->prepare("INSERT INTO users (email, password, display_name, verification_token, plan_id) VALUES (?, ?, ?, ?, 1)");
                $stmt->execute([$email, $hashed_password, $display_name, $token]);

                // Call the new reusable function
                sendVerificationEmail($email, $token);

                json_response(['success' => 'Registration successful. Please check your email to verify your account.']);
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) { // Duplicate entry
                    json_response(['error' => 'Email already exists.'], 409);
                }
                json_response(['error' => 'Database error.'], 500);
            }
            break;

        // In api/index.php
        case 'login':
            $email = filter_var($input['email'], FILTER_VALIDATE_EMAIL);
            $password = $input['password'];

            $stmt = $pdo->prepare("SELECT id, email, password, display_name AS displayName, is_premium, is_verified, is_admin FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                if (!$user['is_verified']) {
                    json_response(['error' => 'Email not verified.', 'notVerified' => true, 'email' => $email], 403);
                }

                // Success! Set session variables.
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['is_admin'] = (bool) $user['is_admin'];

                json_response([
                    'loggedIn' => true,
                    'user' => $user
                ]);
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
                $stmt = $pdo->prepare("SELECT id, email, display_name AS displayName, is_premium FROM users WHERE id = ?");
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

if ($action === 'load_user_data') {
    // Pass the user_id if it exists, otherwise pass null
    load_user_data($_SESSION['user_id'] ?? null, $pdo);
}

// --- Authenticated User Actions ---
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    if (!in_array($action, ['register', 'login', 'check_session', 'resend_verification', 'google_login_url'])) {
        json_response(['error' => 'Authentication required.'], 401);
    }
    exit;
}


function load_user_data($user_id, $pdo)
{
    $response = [];

    // Only fetch user-specific data if the user is logged in
    if ($user_id) {
        // Load stores
        $stmt_stores = $pdo->prepare("SELECT id, store_name, courier_type, credentials FROM stores WHERE user_id = ?");
        $stmt_stores->execute([$user_id]);
        $stores = [];
        while ($row = $stmt_stores->fetch()) {
            $stores[$row['id']] = [
                'storeName' => $row['store_name'],
                'courierType' => $row['courier_type']
            ] + json_decode($row['credentials'], true);
        }
        $response['stores'] = $stores;

        // NEW: Load user data and their plan permissions
        $stmt_user = $pdo->prepare("
            SELECT 
                u.last_selected_store_id, 
                u.parser_settings, -- <-- ADD THIS
                p.can_parse_ai, 
                p.can_autocomplete, 
                p.can_check_risk, 
                p.can_correct_address, 
                p.can_show_ads
            FROM users u
            LEFT JOIN plans p ON u.plan_id = p.id
            WHERE u.id = ?
        ");
        $stmt_user->execute([$user_id]);
        $userData = $stmt_user->fetch(PDO::FETCH_ASSOC);

        $response['lastSelectedStoreId'] = $userData['last_selected_store_id'] ?? null;
        $response['permissions'] = [
            'can_parse_ai' => (bool) ($userData['can_parse_ai'] ?? false),
            'can_autocomplete' => (bool) ($userData['can_autocomplete'] ?? false),
            'can_check_risk' => (bool) ($userData['can_check_risk'] ?? false),
            'can_correct_address' => (bool) ($userData['can_correct_address'] ?? false),
            'can_show_ads' => (bool) ($userData['can_show_ads'] ?? false)
        ];

        // --- THIS IS THE FIX ---
        // It now sends 'null' if the DB is NULL,
        // and sends '[]' if the DB has '[]'
        if (!empty($userData['parser_settings'])) {
            $response['parserSettings'] = json_decode($userData['parser_settings'], true);
        } else {
            $response['parserSettings'] = []; // Send empty array if null
        }
        // --- END OF FIX ---

    }

    // Load Global Settings (this part is always fetched)
    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings = array_column($stmt_settings->fetchAll(), 'setting_value', 'setting_key');

    $response += [
        'geminiApiKey' => $settings['gemini_api_key'] ?? null,
        'appName' => $settings['app_name'] ?? 'CourierPlus',
        'appLogoUrl' => $settings['app_logo_url'] ?? '',
        'ezoicPlaceholderId' => $settings['ezoic_placeholder_id'] ?? null,
        'helpContent' => $settings['help_content'] ?? ''
    ];

    json_response($response);
}


function add_or_update_store($user_id, $input, $pdo)
{
    // Logic to add or update a store
    $store_name = $input['storeName'];
    $courier_type = $input['courierType'];
    $credentials = json_encode($input['credentials']);
    $editing_id = $input['editingId'] ?? null;

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
    // --- 1. Check premium access & limits ---
    $stmt_user_plan = $pdo->prepare("
        SELECT 
            p.can_parse_ai, 
            p.order_limit_daily, 
            p.order_limit_monthly,
            u.plan_expiry_date, 
            u.daily_order_count, 
            u.monthly_order_count
        FROM users u 
        JOIN plans p ON u.plan_id = p.id 
        WHERE u.id = ?
    ");
    $stmt_user_plan->execute([$user_id]);
    $user_data = $stmt_user_plan->fetch(PDO::FETCH_ASSOC);

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
    if ($user_data['order_limit_daily'] > 0 && $user_data['daily_order_count'] >= $user_data['order_limit_daily']) {
        json_response(['error' => 'Daily order limit reached. You cannot parse more parcels today.'], 403);
    }
    if ($user_data['order_limit_monthly'] > 0 && $user_data['monthly_order_count'] >= $user_data['order_limit_monthly']) {
        json_response(['error' => 'Monthly order limit reached. You cannot parse more parcels this month.'], 403);
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
    // Limit input to approx 50 parcels (15000 chars)
    if (strlen($raw_text) > 15000) {
        json_response(['error' => 'Input too large. Maximum 50 parcels allowed per request.'], 400);
    }

    // --- 4. Build prompt ---
    $prompt = <<<EOT
You are an expert parcel data extractor.
**Task:** Extract parcel data from the input text.

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

**Critical Rules:**
1. **Mandatory Fields:** If a block lacks a Phone, Address, OR Price, try your best to infer them from context.
2. **One Block = One Parcel:** Do not split a block separated by empty lines into multiple parcels.
3. **Clustering:** If empty lines are missing, use the "Mandatory Fields" (Phone/Address/Price) to identify where one parcel ends and the next begins.

**Examples:**

**Input:**
370.00
Cox's Bazar Moheshkhali.
01344980362
Size 2 pcs

**Output:**
[{"recipient_phone": "01344980362", "recipient_address": "Moheshkhali, Cox's Bazar", "cod_amount": 370, "item_description": "Size 2 pcs", "recipient_name": null, "order_id": null, "note": null}]

**Input text to process:**
---
$raw_text
---
EOT;


    $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=' . $gemini_api_key;
    // Optimization: Use native JSON mode for faster and stricter output
    $api_body = [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['responseMimeType' => 'application/json']
    ];

    // --- 5. Send request to Gemini API ---

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

    $parsed_json = json_decode(str_replace(['```json', '```'], '', $ai_text_response), true);

    // --- 8. Return parsed JSON ---
    // Wrap in 'parses' key as frontend expects { parses: [...] }
    json_response(['parses' => is_array($parsed_json) ? $parsed_json : []]);
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
    json_response(['success' => true]);
}

function create_order($user_id, $input, $pdo)
{



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
            $pdo->prepare("UPDATE users SET last_reset_date = ? WHERE id = ?")->execute([$today, $user_id]);
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
        if (($user['monthly_order_count'] + $order_count_in_request) > $plan['order_limit_monthly']) {
            json_response(['error' => "Your monthly limit is {$plan['order_limit_monthly']} orders. This request exceeds it."], 403);
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
        }

        // Save to history on success
        $stmt_save = $pdo->prepare("INSERT INTO orders (user_id, store_id, request_payload, api_response) VALUES (?, ?, ?, ?)");
        $stmt_save->execute([$user_id, $store_id, json_encode($final_payload), json_encode($final_response)]);

        // --- START: INCREMENT ORDER COUNTERS ---
        $pdo->prepare("UPDATE users SET daily_order_count = daily_order_count + ?, monthly_order_count = monthly_order_count + ? WHERE id = ?")
            ->execute([$order_count_in_request, $order_count_in_request, $user_id]);
        // --- END: INCREMENT ORDER COUNTERS ---

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
        [
            'url' => 'https://fraudchecker.link/free-fraud-checker-bd/',
            'function' => 'tryFraudCheckLink'
        ],
        [
            'url' => 'https://elitemart.com.bd/fraud-check',
            'function' => 'tryFraudCheckElite'
        ],
        [
            'url' => 'https://onecodesoft.com/fraudchecker',
            'function' => 'tryFraudCheckOnecodesoft'
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

    // Helper function to check if result looks suspicious (all 0% success)
    function isSuspiciousResult($data)
    {
        if (!is_array($data) || empty($data))
            return true;
        foreach ($data as $courier) {
            // If any courier has delivered > 0, it's not suspicious
            $delivered = is_numeric($courier['delivered']) ? (int) $courier['delivered'] : 0;
            if ($delivered > 0)
                return false;
        }
        // All couriers have 0 delivered - this is suspicious
        return true;
    }

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
                $data = @$functionName($phone); // Suppress warnings

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
        return ['error' => 'Storex server returned empty response'];
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
    curl_close($ch);

    if (!$html) {
        return ['error' => 'FraudChecker.link returned empty response'];
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
            $orders = (int) trim($cells->item(1)->nodeValue);
            $delivered = (int) trim($cells->item(2)->nodeValue);
            $cancelled = (int) trim($cells->item(3)->nodeValue);
            $total = $delivered + $cancelled;
            $cancelRate = $total > 0 ? round(($cancelled / $total) * 100, 2) : 0;

            $courier_data[] = [
                'courier' => $courierNode->length > 0 ? trim($courierNode->item(0)->nodeValue) : 'N/A',
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
    curl_close($ch);

    if (!$html) {
        return ['error' => 'Onecodesoft returned empty response'];
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
    if (empty(trim($address))) {
        json_response(['error' => 'No address was provided for correction.'], 400);
    }

    // 3. Refined prompt for a single address
    $prompt = "You are an expert address corrector for Bangladesh. Your task is to correct and complete the following address. Format the output as a single, clean line: Full Address, Thana/Upazila, District. Do not add any extra text, labels, or markdown formatting. Just return the corrected address string. The input language can be English or Bengali; match the output language to the input language.\n\nInput Address: \"" . $address . "\"\n\nCorrected Address:";

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
        // Clean up the response, removing potential markdown, labels, or extra newlines.
        $cleaned_address = trim(str_replace(['`', '*', '\n', 'Corrected Address:', 'Address:'], '', $corrected_address));
        json_response(['corrected_address' => $cleaned_address]);
    } else {
        json_response(['error' => 'Failed to parse corrected address from AI response.'], 500);
    }
}


