<?php
require_once 'api/config.php';
$token = htmlspecialchars($_GET['token'] ?? '');
try {
    $pdo_seo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $stmt_settings = $pdo_seo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key = 'app_name'");
    $settings = array_column($stmt_settings->fetchAll(), 'setting_value', 'setting_key');
    $appName = htmlspecialchars($settings['app_name'] ?? 'CourierPlus');
} catch (PDOException $e) { $appName = 'CourierPlus'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo $appName; ?></title>
    <style>
        :root { --primary-color: #d72129; --secondary-color: #4a4a4a; --light-gray: #f4f7f9; --white: #fff; --dark-gray: #333; --border-color: #dfe6ee; --success-color: #28a745; --error-color: #dc3545; }
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: var(--light-gray); color: var(--dark-gray); margin: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; box-sizing: border-box; }
        .container { background-color: var(--white); padding: 40px; border-radius: 12px; box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08); width: 100%; max-width: 450px; }
        h1 { font-size: 28px; color: var(--primary-color); text-align: center; margin-bottom: 10px; }
        p { text-align: center; color: var(--secondary-color); margin-bottom: 30px; }
        .auth-form { display: flex; flex-direction: column; gap: 20px; }
        .auth-form input { width: 100%; padding: 14px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 16px; box-sizing: border-box; }
        button { flex: 1; padding: 15px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; border: 1px solid var(--primary-color); background-color: var(--primary-color); color: var(--white); }
        button:disabled { background-color: #e57373; cursor: not-allowed; }
        .message { text-align: center; padding: 10px; border-radius: 6px; margin-top: 15px; font-weight: 500; font-size: 14px; }
        .success { background-color: #e9f7ec; color: var(--success-color); }
        .error { background-color: #fbebee; color: var(--error-color); }
        .loader { display: none; margin: 0 auto; border: 2px solid #f3f3f3; border-top: 2px solid var(--white); border-radius: 50%; width: 16px; height: 16px; animation: spin 1s linear infinite; vertical-align: middle; margin-left: 8px; display: none; }
        button:disabled .loader { display: inline-block; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="container">
        <div id="reset-view">
            <h1>Set a New Password</h1>
            <p>Please enter and confirm your new password below.</p>
            <div class="auth-form">
                <input type="hidden" id="resetToken" value="<?php echo $token; ?>">
                <input type="password" id="newPassword" placeholder="New Password" required>
                <input type="password" id="confirmPassword" placeholder="Confirm New Password" required>
                <button id="submitResetBtn">Reset Password <span class="loader"></span></button>
                <div id="reset-message" class="message" style="display:none;"></div>
                <a id="loginRedirectLink" href="index.php" style="text-align:center; display:none; margin-top: 15px;">Back to Login</a>
            </div>
        </div>
    </div>
    <script>
        document.getElementById('submitResetBtn').addEventListener('click', async () => {
            const btn = document.getElementById('submitResetBtn');
            const messageEl = document.getElementById('reset-message');
            const token = document.getElementById('resetToken').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const loginLink = document.getElementById('loginRedirectLink');

            if (!token) { return showMessage('Invalid or missing reset token.', 'error'); }
            if (newPassword.length < 6) { return showMessage('Password must be at least 6 characters.', 'error'); }
            if (newPassword !== confirmPassword) { return showMessage('Passwords do not match.', 'error'); }

            btn.disabled = true;

            try {
                const response = await fetch('api/index.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'perform_password_reset', token, password: newPassword })
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.error);
                showMessage('Password has been reset successfully!', 'success');
                loginLink.style.display = 'block';
            } catch (error) {
                showMessage(error.message, 'error');
                btn.disabled = false;
            }
        });

        function showMessage(text, type) {
            const el = document.getElementById('reset-message');
            el.textContent = text;
            el.className = `message ${type}`;
            el.style.display = 'block';
        }
    </script>
</body>
</html>