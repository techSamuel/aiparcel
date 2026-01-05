<?php
/**
 * AiParcel Configuration File
 * 
 * Copy this file to config.php and fill in your actual values.
 * NEVER commit config.php to version control!
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

// Application URL (no trailing slash)
define('APP_URL', 'https://your-domain.com');

// SMTP Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
define('SMTP_PORT', 587);
define('SMTP_FROM_NAME', 'AiParcel');

// Google OAuth Configuration
// Client ID and Secret are managed via Admin Panel
define('GOOGLE_REDIRECT_URI', APP_URL . '/api/google-auth.php');

// AI API Keys (if applicable)
define('OPENAI_API_KEY', 'your-openai-api-key');
define('GEMINI_API_KEY', 'your-gemini-api-key');

// Database Connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}
