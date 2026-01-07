<?php
// Set Timezone to GMT+6 (Asia/Dhaka)
date_default_timezone_set('Asia/Dhaka');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'u374415227_aiparcel');
define('DB_PASS', '$zUuj;eX1By');
define('DB_NAME', 'u374415227_aiparcel');

// Email Verification URL
define('APP_URL', 'https://courier.aiparcel.site'); // Change this to your domain



// SMTP Email Configuration
define('SMTP_HOST', 'smtp.hostinger.com');      // Your SMTP server (e.g., smtp.gmail.com)
define('SMTP_USER', 'admin@aiparcel.site');     // Your SMTP username
define('SMTP_PASS', 'h+0;hFrpR');    // Your SMTP password
define('SMTP_PORT', 465);                     // Use 465 for SSL or 587 for TLS
define('SMTP_SECURE', 'ssl');                 // Use 'ssl' or 'tls'
define('SMTP_FROM_EMAIL', 'admin@aiparcel.site'); // The "From" email address
define('SMTP_FROM_NAME', 'AiParcel');      // The "From" name
// --- END OF NEW SECTION ---

define('ADMIN_NOTIFICATION_EMAIL', 'rodalsoft@gmail.com'); // Email for purchase notifications

// Google OAuth Configuration
// Client ID and Secret are now managed via the Admin Panel -> Settings
define('GOOGLE_REDIRECT_URI', APP_URL . '/api/google-auth.php');


// Establish database connection using PDO
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET time_zone = '+06:00';");
} catch (PDOException $e) {
    die("ERROR: Could not connect. " . $e->getMessage());
}

// Helper function to send JSON responses
function json_response($data, $status_code = 200)
{
    // Clear any stray output that might corrupt the JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}