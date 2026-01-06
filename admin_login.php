<?php
session_start();
if (isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    header('Location: admin_dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AiParcel - Admin Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-bg: #f4f7f9;
            --sidebar-bg: #2c3e50;
            --white: #fff;
            --accent-color: #3498db;
            --danger: #c0392b;
            --success: #27ae60;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--primary-bg);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .login-panel {
            width: 100%;
            max-width: 900px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin: 20px;
        }

        .login-form-section {
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-branding-section {
            background: linear-gradient(45deg, #34495e, #2c3e50);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 50px;
            text-align: center;
        }

        h1 {
            font-size: 28px;
            margin-bottom: 10px;
            color: var(--sidebar-bg);
        }

        h2 {
            margin-top: 0;
        }

        p {
            font-size: 16px;
            color: #7f8c8d;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        input {
            width: 100%;
            padding: 10px;
            border: 1px solid #e1e8ed;
            border-radius: 5px;
            box-sizing: border-box;
        }

        .btn-primary {
            background-color: var(--accent-color);
            color: var(--white);
            border: none;
            padding: 15px;
            width: 100%;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
        }

        .message {
            text-align: center;
            padding: 10px;
            border-radius: 6px;
            margin-top: 15px;
            font-weight: 500;
            display: none;
        }

        .error {
            background-color: #fbebee;
            color: var(--danger);
        }

        @media (max-width: 900px) {
            .login-panel {
                grid-template-columns: 1fr;
                max-width: 450px;
            }

            .login-branding-section {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="login-panel">
        <div class="login-form-section">
            <h1>Admin Access</h1>
            <p>Welcome back. Please sign in to continue.</p>
            <div class="form-group"><input type="email" id="adminEmail" required placeholder="Email Address"></div>
            <div class="form-group"><input type="password" id="adminPassword" required placeholder="Password"></div>
            <button id="adminLoginBtn" class="btn-primary">Login Securely</button>
            <div id="admin-auth-message" class="message"></div>
        </div>
        <div class="login-branding-section">
            <h2>AiParcel</h2>
            <p>The central hub for managing your entire courier operation with efficiency and control.</p>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        $('#adminLoginBtn').on('click', async () => {
            const email = $('#adminEmail').val();
            const password = $('#adminPassword').val();
            $('#admin-auth-message').hide();

            try {
                const response = await fetch('api/admin.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'login', email, password })
                });
                const data = await response.json();
                if (data.success) {
                    window.location.href = 'admin_dashboard.php';
                } else {
                    $('#admin-auth-message').text(data.error || 'Login failed').addClass('error').show();
                }
            } catch (err) {
                $('#admin-auth-message').text('Connection error').addClass('error').show();
            }
        });
    </script>
</body>

</html>