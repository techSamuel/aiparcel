<?php
require_once 'api/config.php';

// --- 1. Fetch Branding Settings ---
try {
    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('app_name', 'app_logo_url')");
    $settings = array_column($stmt_settings->fetchAll(), 'setting_value', 'setting_key');
    $appName = $settings['app_name'] ?? 'CourierPlus';
    $logoUrl = $settings['app_logo_url'] ?? '';
} catch (PDOException $e) {
    // Set defaults if the database fails
    $appName = 'CourierPlus';
    $logoUrl = '';
}

// --- 2. Initialize Message Variables ---
$status_title = '';
$status_message = '';
$is_error = false;
$token = $_GET['token'] ?? null;

// --- 3. Process Verification Token ---
if ($token) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE verification_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            $update_stmt = $pdo->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?");
            $update_stmt->execute([$user['id']]);
            
            $status_title = "Email Verified!";
            $status_message = "Your account has been successfully activated. You can now log in to your dashboard.";
        } else {
            $is_error = true;
            $status_title = "Invalid Link";
            $status_message = "This verification link is invalid or has already been used. Please try logging in or registering again.";
        }
    } catch (PDOException $e) {
        $is_error = true;
        $status_title = "Database Error";
        $status_message = "An unexpected error occurred while verifying your account. Please try again later.";
    }
} else {
    $is_error = true;
    $status_title = "No Token Provided";
    $status_message = "The verification link is missing a required token.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Verification - <?php echo htmlspecialchars($appName); ?></title>
    <style>
        :root {
            --primary-color: #d72129;
            --secondary-color: #4a4a4a;
            --light-gray: #f4f7f9;
            --white: #fff;
            --success-color: #28a745;
            --error-color: #dc3545;
        }
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--light-gray);
            color: var(--secondary-color);
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            box-sizing: border-box;
        }
        .container {
            background-color: var(--white);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            width: 100%;
            max-width: 500px;
            text-align: center;
        }
        .logo {
            max-height: 50px;
            margin-bottom: 30px;
        }
        h1 {
            font-size: 28px;
            margin-bottom: 15px;
            /* Change color based on success or error */
            color: <?php echo $is_error ? 'var(--error-color)' : 'var(--success-color)'; ?>;
        }
        p {
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .login-button {
            display: inline-block;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            background-color: var(--primary-color);
            color: var(--white);
            border: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($logoUrl): ?>
            <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="<?php echo htmlspecialchars($appName); ?> Logo" class="logo">
        <?php endif; ?>

        <h1><?php echo htmlspecialchars($status_title); ?></h1>
        <p><?php echo htmlspecialchars($status_message); ?></p>

        <?php if (!$is_error): ?>
            <a href="index.php" class="login-button">Proceed to Login</a>
        <?php endif; ?>
    </div>
</body>
</html>