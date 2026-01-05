<?php
// db_update.php - Run this once to update your database schema
require_once 'api/config.php';

echo "<h1>AiParcel Database Update</h1>";

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<p>Connected to database successfully.</p>";

    // 1. Add display_name to users table
    try {
        // Check if column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'display_name'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN display_name VARCHAR(255) AFTER email");
            echo "<p style='color:green'>✅ Added 'display_name' column to users table.</p>";
        } else {
            echo "<p style='color:blue'>ℹ️ 'display_name' column already exists.</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color:red'>❌ Error adding display_name: " . $e->getMessage() . "</p>";
    }

    // 2. Add google_client_id and google_client_secret to settings
    $settings_to_add = [
        'google_client_id' => '',
        'google_client_secret' => ''
    ];

    foreach ($settings_to_add as $key => $default_value) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            if ($stmt->fetchColumn() == 0) {
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
                $stmt->execute([$key, $default_value]);
                echo "<p style='color:green'>✅ Added setting '{$key}'.</p>";
            } else {
                echo "<p style='color:blue'>ℹ️ Setting '{$key}' already exists.</p>";
            }
        } catch (PDOException $e) {
            echo "<p style='color:red'>❌ Error adding setting {$key}: " . $e->getMessage() . "</p>";
        }
    }

    // 3. Ensure google_id is in users table (from previous step, just to be safe)
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'google_id'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN google_id VARCHAR(255) NULL UNIQUE AFTER email");
            echo "<p style='color:green'>✅ Added 'google_id' column to users table.</p>";
        } else {
            echo "<p style='color:blue'>ℹ️ 'google_id' column already exists.</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color:red'>❌ Error adding google_id: " . $e->getMessage() . "</p>";
    }

    echo "<h3>Update Complete! You can now use the new features.</h3>";
    echo "<p><a href='admin.php#settings'>Go to Admin Settings to configure Google OAuth</a></p>";

} catch (PDOException $e) {
    echo "<h1>Database Connection Failed</h1>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>