<?php
/**
 * Google OAuth Handler
 * Handles the OAuth callback from Google and creates/logs in users
 */

session_start();
require_once 'config.php';

// Check if we have an authorization code
if (!isset($_GET['code'])) {
    // No code, redirect to login with error
    header('Location: ' . APP_URL . '/index.php?error=google_auth_failed');
    exit;
}

$code = $_GET['code'];

// Exchange authorization code for access token
$tokenUrl = 'https://oauth2.googleapis.com/token';
// Fetch settings from DB
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('google_client_id', 'google_client_secret')");
$settings = array_column($stmt->fetchAll(), 'setting_value', 'setting_key');

$db_client_id = $settings['google_client_id'] ?? '';
$db_client_secret = $settings['google_client_secret'] ?? '';

// Fallback logic
$client_id = !empty($db_client_id) ? $db_client_id : (defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '');
$client_secret = !empty($db_client_secret) ? $db_client_secret : (defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : '');

if (empty($client_id) || empty($client_secret)) {
    die('Google Auth is not configured.');
}

$tokenData = [
    'code' => $code,
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $tokenUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$tokenResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch); // Capture error
curl_close($ch);

if ($httpCode !== 200) {
    // Debugging: Log the error
    error_log("Google Token Exchange Failed. HTTP Code: " . $httpCode);
    error_log("Response: " . $tokenResponse);
    error_log("Curl Error: " . $curlError);

    // Extract meaningful error
    $errData = json_decode($tokenResponse, true);
    $debugMsg = $curlError ? "Curl: $curlError" : ($errData['error_description'] ?? $errData['error'] ?? 'Unknown_token_error');

    header('Location: ' . APP_URL . '/index.php?error=google_token_failed&details=' . urlencode($debugMsg));
    exit;
}

$tokenInfo = json_decode($tokenResponse, true);

if (!isset($tokenInfo['access_token'])) {
    header('Location: ' . APP_URL . '/index.php?error=google_token_invalid');
    exit;
}

$accessToken = $tokenInfo['access_token'];

// Get user info from Google
$userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $userInfoUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$userResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    header('Location: ' . APP_URL . '/index.php?error=google_userinfo_failed');
    exit;
}

$googleUser = json_decode($userResponse, true);

if (!isset($googleUser['email']) || !isset($googleUser['id'])) {
    header('Location: ' . APP_URL . '/index.php?error=google_user_invalid');
    exit;
}

$googleId = $googleUser['id'];
$email = $googleUser['email'];
$displayName = $googleUser['name'] ?? explode('@', $email)[0];

try {
    // Check if user exists by google_id
    $stmt = $pdo->prepare("SELECT id, email, display_name, is_premium, is_admin FROM users WHERE google_id = ?");
    $stmt->execute([$googleId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Existing Google user - log them in
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['is_admin'] = (bool) $user['is_admin'];
        header('Location: ' . APP_URL . '/index.php?google_login=success');
        exit;
    }

    // Check if user exists by email (link Google account to existing)
    $stmt = $pdo->prepare("SELECT id, email, display_name, is_premium, is_admin, google_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingUser) {
        // User exists with this email - link Google account
        $updateStmt = $pdo->prepare("UPDATE users SET google_id = ?, is_verified = 1 WHERE id = ?");
        $updateStmt->execute([$googleId, $existingUser['id']]);

        $_SESSION['user_id'] = $existingUser['id'];
        $_SESSION['is_admin'] = (bool) $existingUser['is_admin'];
        header('Location: ' . APP_URL . '/index.php?google_login=success');
        exit;
    }

    // New user - create account
    $stmt = $pdo->prepare("INSERT INTO users (email, google_id, display_name, is_verified, plan_id) VALUES (?, ?, ?, 1, 1)");
    $stmt->execute([$email, $googleId, $displayName]);
    $newUserId = $pdo->lastInsertId();

    $_SESSION['user_id'] = $newUserId;
    $_SESSION['is_admin'] = false;
    header('Location: ' . APP_URL . '/index.php?google_login=success&new_user=1');
    exit;

} catch (PDOException $e) {
    error_log("Google Auth Error: " . $e->getMessage());
    header('Location: ' . APP_URL . '/index.php?error=google_db_error');
    exit;
}
?>