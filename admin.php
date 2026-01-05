<?php
session_start();
// This simple check determines the initial view state (login vs. dashboard)
// The JavaScript will handle the actual session validation via an API call.
$is_logged_in_as_admin = (isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AiParcel - Admin Dashboard</title>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <style>
        :root {
            --primary-bg: #f4f7f9;
            --sidebar-bg: #2c3e50;
            --header-bg: #ffffff;
            --primary-text: #34495e;
            --sidebar-text: #ecf0f1;
            --accent-color: #3498db;
            --white: #fff;
            --border-color: #e1e8ed;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --success: #27ae60;
            --danger: #c0392b;
            --warning: #f39c12;
            --info: #3498db;
        }

        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--primary-bg);
            color: var(--primary-text);
            font-size: 14px;
        }

        .dashboard-layout {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 260px;
            background-color: var(--sidebar-bg);
            color: var(--sidebar-text);
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            display: flex;
            flex-direction: column;
            z-index: 100;
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            border-bottom: 1px solid #34495e;
        }

        .sidebar-nav {
            flex-grow: 1;
            list-style: none;
            margin-top: 20px;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            color: var(--sidebar-text);
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.2s;
        }

        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background-color: #34495e;
        }

        .sidebar-nav a svg {
            width: 20px;
            height: 20px;
        }

        .sidebar-footer {
            padding: 20px;
        }

        #logoutBtn {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            background-color: var(--danger);
            color: var(--white);
        }

        /* Main Content Styles */
        .main-content {
            margin-left: 260px;
            width: calc(100% - 260px);
            display: flex;
            flex-direction: column;
        }

        .header {
            background-color: var(--header-bg);
            height: 70px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 30px;
            border-bottom: 1px solid var(--border-color);
        }

        #view-title {
            font-size: 22px;
            font-weight: 600;
            color: var(--primary-text);
        }

        .profile {
            position: relative;
        }

        .profile-btn {
            background: none;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-btn span {
            font-weight: 600;
        }

        .page-content {
            padding: 30px;
        }

        .view {
            display: none;
        }

        .view.active {
            display: block;
            animation: fadeIn 0.5s;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .card {
            background: var(--white);
            border-radius: 8px;
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 25px;
        }

        /* Dashboard Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }

        .stat-card {
            background: var(--white);
            border-radius: 8px;
            padding: 20px;
            box-shadow: var(--shadow);
            border-left: 5px solid var(--accent-color);
        }

        .stat-card h3 {
            font-size: 16px;
            color: #7f8c8d;
            margin-bottom: 10px;
            font-weight: 500;
        }

        .stat-card p {
            font-size: 32px;
            font-weight: 700;
        }

        .stat-card.users {
            border-color: var(--info);
        }

        .stat-card.parses {
            border-color: var(--success);
        }

        .stat-card.orders {
            border-color: var(--warning);
        }

        /* General Table Styles */
        .table-container {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .data-table th,
        .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }

        .data-table th {
            background-color: #ecf0f1;
            font-size: 12px;
            text-transform: uppercase;
        }

        .data-table .actions button {
            border: none;
            color: var(--white);
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
        }

        .btn-view-details {
            background-color: var(--info);
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
            color: var(--white);
            border: none;
        }

        .btn-view-details:hover {
            transform: translateY(-1px);
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
        }

        @media (max-width: 1200px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            font-weight: 600;
            display: block;
            margin-bottom: 5px;
        }

        input[type="email"],
        input[type="text"],
        input[type="number"],
        input[type="password"],
        textarea,
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 14px;
        }

        .btn-primary {
            background-color: var(--accent-color);
            color: var(--white);
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }

        .btn-secondary {
            background-color: #7f8c8d;
            color: var(--white);
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }

        .btn-success {
            background-color: var(--success);
            color: var(--white);
        }

        .btn-danger {
            background-color: var(--danger);
            color: var(--white);
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .login-container {
            width: 100%;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: var(--primary-bg);
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

        .login-form-section h1 {
            font-size: 28px;
            margin-bottom: 10px;
            color: var(--sidebar-bg);
        }

        .login-form-section p {
            font-size: 16px;
            color: #7f8c8d;
            margin-bottom: 30px;
        }

        .message {
            text-align: center;
            padding: 10px;
            border-radius: 6px;
            margin-top: 15px;
            font-weight: 500;
            font-size: 14px;
        }

        .success {
            background-color: #e9f7ec;
            color: var(--success);
        }

        .error {
            background-color: #fbebee;
            color: var(--danger);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.6);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 25px;
            border-radius: 8px;
            width: 90%;
            max-width: 800px;
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 15px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-header h2 {
            margin: 0;
            font-size: 20px;
            color: var(--primary-text);
        }

        .close-btn {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
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

    <div class="login-container" id="admin-login-view-wrapper" <?php if ($is_logged_in_as_admin)
        echo 'style="display:none;"'; ?>>
        <div class="login-panel">
            <div class="login-form-section">
                <h1>Admin Access</h1>
                <p>Welcome back. Please sign in to continue.</p>
                <div class="form-group">
                    <input type="email" id="adminEmail" required placeholder="Email Address">
                </div>
                <div class="form-group">
                    <input type="password" id="adminPassword" required placeholder="Password">
                </div>
                <button id="adminLoginBtn" class="btn-primary" style="width: 100%; padding: 15px;">Login
                    Securely</button>
                <div id="admin-auth-message" class="message" style="display:none;"></div>
            </div>
            <div class="login-branding-section"
                style="background: linear-gradient(45deg, #34495e, #2c3e50); color: white; display:flex; flex-direction:column; justify-content:center; padding: 50px; text-align:center;">
                <h2>AiParcel</h2>
                <p>The central hub for managing your entire courier operation with efficiency and control.</p>
            </div>
        </div>
    </div>

    <div class="dashboard-layout" id="app-view" <?php if (!$is_logged_in_as_admin)
        echo 'style="display:none;"'; ?>>
        <aside class="sidebar">
            <div class="sidebar-header">AiParcel</div>
            <ul class="sidebar-nav">
                <li><a href="#dashboard" class="nav-link active" data-title="Dashboard"><svg fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                            </path>
                        </svg><span>Dashboard</span></a></li>
                <li><a href="#users" class="nav-link" data-title="Users"><svg fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M15 21a6 6 0 00-9-5.197M15 11a3 3 0 11-6 0 3 3 0 016 0z">
                            </path>
                        </svg><span>Users</span></a></li>
                <li><a href="#parses" class="nav-link" data-title="Parsed History"><svg fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                            </path>
                        </svg><span>Parsed History</span></a></li>
                <li><a href="#orders" class="nav-link" data-title="Order History"><svg fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2-2h8a1 1 0 001-1z"></path>
                        </svg><span>Order History</span></a></li>
                <li style="border-top: 1px solid #34495e; margin-top: 10px; padding-top: 10px;">
                    <a href="#plans" class="nav-link" data-title="Subscription Plans"><svg fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                            </path>
                        </svg><span>Plans</span></a>
                </li>
                <li>
                    <a href="#payment-methods" class="nav-link" data-title="Payment Methods"><svg fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z">
                            </path>
                        </svg><span>Payments</span></a>
                </li>
                <li>
                    <a href="#subscriptions" class="nav-link" data-title="Subscription Orders"><svg fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v.01">
                            </path>
                        </svg><span>Subscriptions</span></a>
                </li>
                <li><a href="#settings" class="nav-link" data-title="Settings"><svg fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                            </path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg><span>Settings</span></a></li>
            </ul>
            <div class="sidebar-footer"><button id="logoutBtn">Logout</button></div>
        </aside>

        <main class="main-content">
            <header class="header">
                <h1 id="view-title">Dashboard</h1>
                <div class="profile"><button class="profile-btn" id="profileBtn"><span
                            id="adminDisplayName">Admin</span></button></div>
            </header>

            <div class="page-content">
                <div id="dashboard-view" class="view active">
                    <div class="stats-grid">
                        <div class="stat-card users">
                            <h3>Total Users</h3>
                            <p id="total-users-stat">0</p>
                        </div>
                        <div class="stat-card parses">
                            <h3>Total Parses</h3>
                            <p id="total-parses-stat">0</p>
                        </div>
                        <div class="stat-card orders">
                            <h3>Total Orders</h3>
                            <p id="total-orders-stat">0</p>
                        </div>
                    </div>
                </div>
                <div id="users-view" class="view">
                    <div class="card">
                        <div class="table-container">
                            <table class="data-table display" id="users-table" style="width:100%"></table>
                        </div>
                    </div>
                </div>
                <div id="parses-view" class="view">
                    <div class="card">
                        <div class="table-container">
                            <table class="data-table display" id="parses-table" style="width:100%"></table>
                        </div>
                    </div>
                </div>
                <div id="orders-view" class="view">
                    <div class="card">
                        <div class="table-container">
                            <table class="data-table display" id="orders-table" style="width:100%"></table>
                        </div>
                    </div>
                </div>

                <div id="plans-view" class="view">
                    <div class="grid-2">
                        <div class="card">
                            <h3 id="plan-form-title">Add New Plan</h3>
                            <form id="plan-form">
                                <input type="hidden" id="plan-id">
                                <div class="form-group"><label for="plan-name">Plan Name</label><input type="text"
                                        id="plan-name" required></div>
                                <div class="form-group"><label for="plan-price">Price (BDT)</label><input type="number"
                                        id="plan-price" step="0.01" required></div>
                                <div class="form-group"><label for="plan-limit-monthly">Monthly Order
                                        Limit</label><input type="number" id="plan-limit-monthly"
                                        placeholder="Leave blank for none"></div>
                                <div class="form-group"><label for="plan-limit-daily">Daily Order Limit</label><input
                                        type="number" id="plan-limit-daily" placeholder="Leave blank for none"></div>
                                <div class="form-group"><label for="plan-validity">Validity (Days)</label><input
                                        type="number" id="plan-validity" required></div>
                                <div class="form-group"><label for="plan-description">Description</label><textarea
                                        id="plan-description" rows="3"></textarea></div>
                                <div class="form-group"
                                    style="border-top: 1px solid var(--border-color); padding-top: 15px;">
                                    <label>Feature Permissions</label>
                                    <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 5px;">
                                        <label
                                            style="cursor:pointer; font-weight: normal; display:flex; align-items:center; gap: 8px;">
                                            <input type="checkbox" id="plan-can-parse-ai"> Enable "Parse with AI"
                                        </label>
                                        <label
                                            style="cursor:pointer; font-weight: normal; display:flex; align-items:center; gap: 8px;">
                                            <input type="checkbox" id="plan-can-correct-address"> Enable "Correct
                                            Address with AI"
                                        </label>
                                        <label
                                            style="cursor:pointer; font-weight: normal; display:flex; align-items:center; gap: 8px;">
                                            <input type="checkbox" id="plan-can-autocomplete"> Enable "Parse &
                                            Autocomplete"
                                        </label>
                                        <label
                                            style="cursor:pointer; font-weight: normal; display:flex; align-items:center; gap: 8px;">
                                            <input type="checkbox" id="plan-can-check-risk"> Enable "Check Risk"
                                        </label>
                                        <label
                                            style="cursor:pointer; font-weight: normal; display:flex; align-items:center; gap: 8px;">
                                            <input type="checkbox" id="plan-can-show-ads"> Enable Ezoic Ads
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group"><label
                                        style="display:flex; align-items:center; gap: 8px; font-weight: normal;"><input
                                            type="checkbox" id="plan-is-active" checked> Active</label></div>
                                <button type="submit" class="btn-primary">Save Plan</button>
                                <button type="button" id="clear-plan-form" class="btn-secondary">Clear</button>
                            </form>
                        </div>
                        <div class="card">
                            <h3>Existing Plans</h3>
                            <div class="table-container">
                                <table id="plans-table" class="data-table display" style="width:100%"></table>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="payment-methods-view" class="view">
                    <div class="grid-2">
                        <div class="card">
                            <h3 id="payment-method-form-title">Add New Payment Method</h3>
                            <form id="payment-method-form">
                                <input type="hidden" id="payment-method-id">
                                <div class="form-group"><label for="payment-method-name">Method Name</label><input
                                        type="text" id="payment-method-name" required></div>
                                <div class="form-group"><label for="payment-method-details">Account
                                        Details</label><textarea id="payment-method-details" rows="3"
                                        required></textarea></div>
                                <div class="form-group"><label
                                        for="payment-method-instructions">Instructions</label><textarea
                                        id="payment-method-instructions" rows="3"></textarea></div>
                                <div class="form-group"><label
                                        style="display:flex; align-items:center; gap: 8px; font-weight: normal;"><input
                                            type="checkbox" id="payment-method-is-active" checked> Active</label></div>
                                <button type="submit" class="btn-primary">Save Method</button>
                                <button type="button" id="clear-payment-method-form"
                                    class="btn-secondary">Clear</button>
                            </form>
                        </div>
                        <div class="card">
                            <h3>Active Payment Methods</h3>
                            <div class="table-container">
                                <table id="payment-methods-table" class="data-table display" style="width:100%"></table>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="subscriptions-view" class="view">
                    <div class="card">
                        <h3>Subscription Purchase Requests</h3>
                        <div class="table-container">
                            <table id="subscriptions-table" class="data-table display" style="width:100%"></table>
                        </div>
                    </div>
                </div>

                <div id="settings-view" class="view">
                    <div class="card settings-form">
                        <h2>Application Settings</h2>
                        <form id="settings-form">
                            <div class="form-group"><label for="appName">Application Name</label><input type="text"
                                    id="appName"></div>
                            <div class="form-group">
                                <label for="appLogoFile">Application Logo</label>
                                <img id="logoPreview" src="" alt="Logo Preview"
                                    style="max-height: 100px; margin-bottom: 10px; border: 1px solid var(--border-color); padding: 5px; border-radius: 5px; display: block;">
                                <input type="file" id="appLogoFile" accept="image/*">
                            </div>
                            <hr style="margin: 20px 0;">
                            <div class="form-group"><label for="geminiApiKey">Gemini API Key</label><textarea
                                    type="password" id="geminiApiKey"></textarea></div>
                            <div class="form-group"><label for="barikoiApiKey">Barikoi API Key</label><textarea
                                    type="password" id="barikoiApiKey"></textarea></div>
                            <div class="form-group"><label for="googleMapsApiKey">Google Maps API Key</label><input
                                    type="password" id="googleMapsApiKey"></div>
                            <div class="form-group"><label for="googleMapsApiKey">Google Maps API Key</label><input
                                    type="password" id="googleMapsApiKey"></div>

                            <div class="form-group"
                                style="border-top: 1px solid var(--border-color); padding-top: 20px;">
                                <label>Google OAuth Configuration (for Login)</label>
                                <div class="form-group"><label for="googleClientId">Google Client ID</label><input
                                        type="text" id="googleClientId"></div>
                                <div class="form-group"><label for="googleClientSecret">Google Client
                                        Secret</label><input type="password" id="googleClientSecret"></div>
                            </div>

                            <div class="form-group"
                                style="border-top: 1px solid var(--border-color); padding-top: 20px;">
                                <label for="ezoicPlaceholderId">Ezoic Placeholder ID</label>
                                <input type="text" id="ezoicPlaceholderId" placeholder="e.g., 101">
                            </div>
                            <div class="form-group"
                                style="border-top: 1px solid var(--border-color); padding-top: 20px;">
                                <label for="autocompleteService">Address Autocomplete Service</label>
                                <select id="autocompleteService">
                                    <option value="barikoi">Barikoi (Recommended for Bangladesh)</option>
                                    <option value="google">Google Maps</option>
                                </select>
                            </div>
                            <div class="form-group"
                                style="border-top: 1px solid var(--border-color); padding-top: 20px;">
                                <label>Button Visibility</label>
                                <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 5px;">
                                    <label
                                        style="cursor:pointer; font-weight: normal; display:flex; align-items:center; gap: 8px;"><input
                                            type="checkbox" id="showAiParseButton"> Show "Parse with AI" Button</label>
                                    <label
                                        style="cursor:pointer; font-weight: normal; display:flex; align-items:center; gap: 8px;"><input
                                            type="checkbox" id="showAutocompleteButton"> Show "Parse & Autocomplete"
                                        Button</label>
                                </div>
                            </div>
                            <div class="form-group"
                                style="border-top: 1px solid var(--border-color); padding-top: 20px;">
                                <label for="helpContent">Help Modal Content (HTML/CSS Allowed)</label>
                                <textarea id="helpContent" rows="15"
                                    placeholder="Enter the HTML and CSS for your help guide here..."></textarea>
                            </div>
                            <button type="submit" class="btn-primary" style="margin-top: 10px;">Save Settings</button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="user-details-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="user-details-title">User Details</h2><span class="close-btn">&times;</span>
            </div>
            <div id="user-details-content"></div>
        </div>
    </div>
    <div id="admin-profile-modal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2>Admin Profile Settings</h2>
                <span class="close-btn">&times;</span>
            </div>
            <div class="form-group">
                <label for="adminDisplayNameInput">Display Name</label>
                <input type="text" id="adminDisplayNameInput" placeholder="Enter your display name">
                <button id="updateAdminNameBtn" class="btn-primary" style="width:100%; margin-top:10px;">Update
                    Name</button>
            </div>
            <hr style="margin: 20px 0;">
            <div class="form-group">
                <label for="adminPasswordInput">New Password (leave blank to keep current)</label>
                <input type="password" id="adminPasswordInput" placeholder="Enter a new password">
                <button id="updateAdminPasswordBtn" class="btn-primary" style="width:100%; margin-top:10px;">Update
                    Password</button>
            </div>
            <div id="admin-profile-message" class="message" style="display:none; margin-top: 15px;"></div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            let currentUser = null;
            const dataCache = {};
            const $loginWrapper = $('#admin-login-view-wrapper');
            const $appView = $('#app-view');
            const $adminDisplayName = $('#adminDisplayName');

            async function apiCall(action, body = {}, url = 'api/admin.php') { // Changed this line
                let response;
                try {
                    response = await fetch(url, { // And changed this line
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action, ...body })
                    });

                    if (response.status === 401 || response.status === 403) {
                        handleLogout();
                        throw new Error('Permission Denied or Session Expired.');
                    }

                    const responseText = await response.text();

                    if (!responseText) {
                        // This case is unlikely if the server is running but could happen.
                        throw new Error('Server returned an empty response.');
                    }

                    try {
                        const data = JSON.parse(responseText);
                        if (!response.ok) {
                            throw new Error(data.error || `API error with status ${response.status}`);
                        }
                        return data;
                    } catch (jsonError) {
                        // This catches the "Unexpected end of JSON input" if responseText is not valid JSON
                        console.error("Failed to parse JSON. Server response:", responseText);
                        throw new Error('Server returned an invalid (non-JSON) response. Check the browser console for details.');
                    }

                } catch (error) {
                    console.error('API Call Error:', action, error);
                    alert(`Error during '${action}': ${error.message}`);
                    throw error;
                }
            }


            // --- Admin Profile Modal Logic ---

            // Show the modal when the profile button is clicked
            $('#profileBtn').on('click', function () {
                // Populate the form with the currently logged-in admin's data
                $('#adminDisplayNameInput').val(currentUser.displayName || '');
                $('#adminPasswordInput').val(''); // Always clear password field
                $('#admin-profile-message').hide();
                $('#admin-profile-modal').show();
            });

            // Handle the "Update Name" button click
            $('#updateAdminNameBtn').on('click', async function () {
                const newName = $('#adminDisplayNameInput').val();
                try {
                    await apiCall('update_profile', { displayName: newName });
                    // Update the global state and the header display
                    currentUser.displayName = newName;
                    $('#adminDisplayName').text(newName || currentUser.email);
                    showMessage(document.getElementById('admin-profile-message'), 'Display name updated successfully!', 'success');
                } catch (e) {
                    showMessage(document.getElementById('admin-profile-message'), 'Error updating name: ' + e.message, 'error');
                }
            });

            // Handle the "Update Password" button click
            $('#updateAdminPasswordBtn').on('click', async function () {
                const newPassword = $('#adminPasswordInput').val();
                if (newPassword.length > 0 && newPassword.length < 6) {
                    showMessage(document.getElementById('admin-profile-message'), 'Password must be at least 6 characters.', 'error');
                    return;
                }
                try {
                    await apiCall('update_profile', { password: newPassword });
                    $('#adminPasswordInput').val(''); // Clear the field after success
                    showMessage(document.getElementById('admin-profile-message'), 'Password updated successfully!', 'success');
                } catch (e) {
                    showMessage(document.getElementById('admin-profile-message'), 'Error updating password: ' + e.message, 'error');
                }
            });


            // --- MODAL & USER DETAILS LOGIC ---

            // Listener for the user details button
            // Listener for the user details button
            $('#users-table').on('click', '.btn-view-details', async function () {
                const uid = $(this).data('uid');
                const $modal = $('#user-details-modal');
                const $modalContent = $('#user-details-content');

                $modalContent.html('<p>Loading user details...</p>');
                $modal.show();

                try {
                    const details = await apiCall('get_user_details', { uid });

                    // 1. Prepare the HTML structure (same as before)
                    const planInfo = details.plan || {};
                    const modalHtml = `
                    <div class="details-modal-section">
                        <h4>Plan Information</h4>
                        <div class="plan-info">
                            <strong>Plan:</strong> ${planInfo.plan_name || 'N/A'}<br>
                            <strong>Expires On:</strong> ${planInfo.plan_expiry_date || 'N/A'}
                        </div>
                    </div>
                    <div class="details-modal-section">
                        <h4>Stores (${details.stores.length})</h4>
                        <table id="stores-details-table" class="display details-table" style="width:100%"></table>
                    </div>
                    <div class="details-modal-section">
                        <h4>Recent Parses (${details.parses.length})</h4>
                        <table id="parses-details-table" class="display details-table" style="width:100%"></table>
                    </div>
                    <div class="details-modal-section">
                        <h4>Recent Orders (${details.orders.length})</h4>
                        <table id="orders-details-table" class="display details-table" style="width:100%"></table>
                    </div>
                `;
                    $modalContent.html(modalHtml);

                    // 2. Initialize DataTables with NEW column definitions

                    // Stores Table (unchanged)
                    $('#stores-details-table').DataTable({
                        destroy: true,
                        data: details.stores,
                        columns: [
                            { title: 'Store Name', data: 'store_name' },
                            { title: 'Courier', data: 'courier_type' }
                        ],
                        pageLength: 5,
                        lengthChange: false,
                        searching: false
                    });

                    // Initialize Parses Table (MODIFIED)
                    $('#parses-details-table').DataTable({
                        destroy: true,
                        data: details.parses,
                        columns: [
                            { title: 'Date', data: 'timestamp', render: data => new Date(data).toLocaleString() },
                            { title: 'Method', data: 'method' },
                            // MODIFIED: This now shows the full formatted JSON data
                            {
                                title: 'Parsed Data', data: 'data', render: data => {
                                    try {
                                        const jsonData = JSON.parse(data);
                                        const prettyJson = JSON.stringify(jsonData, null, 2);
                                        // Escape HTML to be safe
                                        const escapedJson = $('<div/>').text(prettyJson).html();
                                        return `<pre style="white-space: pre-wrap; word-break: break-all; max-width: 450px; max-height: 200px; overflow-y: auto; background: #f9f9f9; border: 1px solid #eee; padding: 5px; margin: 0;">${escapedJson}</pre>`;
                                    } catch (e) {
                                        return String(data);
                                    }
                                }
                            }
                        ],
                        pageLength: 5,
                        lengthChange: false,
                        searching: false,
                        order: [[0, 'desc']]
                    });

                    // Initialize Orders Table (MODIFIED)
                    $('#orders-details-table').DataTable({
                        destroy: true,
                        data: details.orders,
                        columns: [
                            { title: 'Date', data: 'timestamp', render: data => new Date(data).toLocaleString() },
                            { title: 'Store ID', data: 'store_id' },
                            // MODIFIED: This now shows the full API response
                            {
                                title: 'API Response', data: 'api_response', render: data => {
                                    try {
                                        const resp = JSON.parse(data);
                                        let status, message;

                                        if (resp.status === 'success' || resp.message === 'Order created successfully') {
                                            status = '<strong style="color:green;">Success</strong>';
                                        } else {
                                            status = '<strong style="color:red;">Failed</strong>';
                                        }

                                        message = resp.message || resp.error || JSON.stringify(resp);

                                        if (typeof message === 'object') {
                                            message = JSON.stringify(message, null, 2);
                                        }

                                        const escapedMessage = $('<div/>').text(message).html();
                                        return `${status}<br><pre style="white-space: pre-wrap; word-break: break-all; max-width: 450px; margin-top: 5px; font-size: 12px; background: #f9f9f9; border: 1px solid #eee; padding: 5px; margin: 0;">${escapedMessage}</pre>`;
                                    } catch (e) {
                                        const escapedData = $('<div/>').text(String(data)).html();
                                        return `<pre style="white-space: pre-wrap; word-break: break-all; max-width: 450px; margin: 0;">${escapedData}</pre>`;
                                    }
                                }
                            }
                        ],
                        pageLength: 5,
                        lengthChange: false,
                        searching: false,
                        order: [[0, 'desc']]
                    });

                } catch (error) {
                    $modalContent.html('<p class="error">Failed to load user details. Please try again.</p>');
                }
            });

            // General modal close button functionality
            $('.modal .close-btn').on('click', function () {
                $(this).closest('.modal').hide();
            });

            // Close modal if clicking on the background overlay
            $('.modal').on('click', function (e) {
                if (e.target === this) {
                    $(this).hide();
                }
            });

            // General modal close button functionality
            $('.modal .close-btn').on('click', function () {
                $(this).closest('.modal').hide();
            });

            // Close modal if clicking on the background overlay
            $('.modal').on('click', function (e) {
                if (e.target === this) {
                    $(this).hide();
                }
            });

            // General modal close button functionality
            $('.modal .close-btn').on('click', function () {
                $(this).closest('.modal').hide();
            });

            // Close modal if clicking on the background overlay
            $('.modal').on('click', function (e) {
                if (e.target === this) {
                    $(this).hide();
                }
            });


            if ($appView.css('display') !== 'none') {
                fetch('api/index.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'check_session' }) })
                    .then(res => res.json()).then(session => {
                        if (session.loggedIn && session.user) {
                            currentUser = session.user;
                            initializeDashboard();
                        }
                    });
            }

            $('#adminLoginBtn').on('click', async () => {
                try {
                    const user = await apiCall('login', { email: $('#adminEmail').val(), password: $('#adminPassword').val() });
                    currentUser = user;
                    $loginWrapper.hide();
                    $appView.css('display', 'flex');
                    initializeDashboard();
                } catch (err) {
                    $('#admin-auth-message').text(err.message).addClass('error').show();
                }
            });

            const handleLogout = () => apiCall('logout', {}, 'api/index.php').then(() => window.location.reload());
            $('#logoutBtn').on('click', handleLogout);

            function initializeDashboard() {
                $adminDisplayName.text(currentUser.displayName || currentUser.email);
                $('.nav-link').on('click', function (e) {
                    e.preventDefault();
                    window.location.hash = $(this).attr('href');
                });
                $(window).on('hashchange', handleRouteChange).trigger('hashchange');
            }

            function handleRouteChange() {
                const hash = window.location.hash || '#dashboard';
                $('.nav-link').removeClass('active').filter(`[href="${hash}"]`).addClass('active');
                $('.view').removeClass('active').filter(hash.replace('#', '#') + '-view').addClass('active');
                $('#view-title').text($('.nav-link.active').data('title'));
                loadDataForView(hash.substring(1));
            }

            function loadDataForView(viewId) {
                const $table = $(`#${viewId}-table`);
                if (dataCache[viewId] && viewId !== 'dashboard') {
                    if ($.fn.DataTable.isDataTable($table)) $table.DataTable().ajax.reload(null, false);
                    return;
                }
                switch (viewId) {
                    case 'dashboard': loadDashboardStats(); break;
                    case 'users': loadAllUsers(); break;
                    case 'parses': loadGlobalHistory('parses'); break;
                    case 'orders': loadGlobalHistory('orders'); break;
                    case 'settings': loadSettings(); break;
                    case 'plans': loadPlans(); break;
                    case 'payment-methods': loadPaymentMethods(); break;
                    case 'subscriptions': loadSubscriptionOrders(); break;
                }
                if (viewId !== 'dashboard') dataCache[viewId] = true;
            }

            async function loadDashboardStats() {
                const stats = await apiCall('get_stats');
                $('#total-users-stat').text(stats.userCount);
                $('#total-parses-stat').text(stats.parseCount);
                $('#total-orders-stat').text(stats.orderCount);
            }

            function loadAllUsers() {
                $('#users-table').DataTable({
                    destroy: true,
                    ajax: (d, cb) => apiCall('list_users').then(res => cb({ data: res })),
                    columns: [
                        { title: "Email", data: "email" }, { title: "Plan", data: "plan_name" },
                        { title: "Verified", data: "is_verified", render: d => d ? '✅' : '❌' },
                        { title: "Actions", data: "id", orderable: false, render: d => `<button class="btn-view-details btn-sm" data-uid="${d}">Details</button>` }
                    ]
                });
            }

            function loadGlobalHistory(type) {
                // UPDATED 'cols' variable
                const cols = (type === 'parses') ?
                    [
                        { title: "Date", data: "timestamp", render: d => new Date(d).toLocaleString() },
                        { title: "User", data: "userEmail" },
                        { title: "Method", data: "method" },
                        // NEW: Added item count column
                        {
                            title: "Items", data: "data", orderable: false, render: data => {
                                try { return JSON.parse(data).length; } catch (e) { return '?'; }
                            }
                        }
                    ] :
                    [
                        { title: "Date", data: "timestamp", render: d => new Date(d).toLocaleString() },
                        { title: "User", data: "userEmail" },
                        { title: "Store ID", data: "store_id" },
                        // NEW: Added status column
                        {
                            title: 'Status', data: 'api_response', orderable: false, render: data => {
                                try {
                                    const resp = JSON.parse(data);
                                    return (resp.status === 'success' || resp.message === 'Order created successfully') ? '<span style="color:green;">Success</span>' : '<span style="color:red;">Failed</span>';
                                } catch (e) { return 'Unknown'; }
                            }
                        }
                    ];

                $(`#${type}-table`).DataTable({
                    destroy: true,
                    ajax: (d, cb) => apiCall('get_global_history', { type }).then(res => cb({ data: res })),
                    columns: cols,
                    order: [[0, 'desc']]
                });
            }

            function loadPlans() {
                $('#plans-table').DataTable({
                    destroy: true,
                    ajax: (d, cb) => apiCall('get_plans').then(res => cb({ data: res })),
                    columns: [
                        { title: "Name", data: "name" },
                        { title: "Price", data: "price" },
                        { title: "Active", data: "is_active", render: d => d == 1 ? 'Yes' : 'No' },
                        // ADD THESE HIDDEN COLUMNS TO MAKE THE DATA AVAILABLE
                        { data: "order_limit_monthly", visible: false },
                        { data: "order_limit_daily", visible: false },
                        { data: "validity_days", visible: false },
                        { data: "description", visible: false },
                        // ---
                        { title: "Actions", data: "id", orderable: false, render: d => `<button class="edit-plan-btn btn-sm btn-primary" data-id="${d}">Edit</button> <button class="delete-plan-btn btn-sm btn-danger" data-id="${d}">Delete</button>` }
                    ]
                });
            }

            $('#plan-form').on('submit', async function (e) {
                e.preventDefault();
                await apiCall('save_plan', {
                    id: $('#plan-id').val() || null,
                    name: $('#plan-name').val(),
                    price: $('#plan-price').val(),
                    order_limit_monthly: $('#plan-limit-monthly').val() || null,
                    order_limit_daily: $('#plan-limit-daily').val() || null,
                    validity_days: $('#plan-validity').val(),
                    description: $('#plan-description').val(),
                    is_active: $('#plan-is-active').is(':checked') ? 1 : 0,
                    can_parse_ai: $('#plan-can-parse-ai').is(':checked') ? 1 : 0,
                    can_autocomplete: $('#plan-can-autocomplete').is(':checked') ? 1 : 0,
                    can_check_risk: $('#plan-can-check-risk').is(':checked') ? 1 : 0,
                    can_correct_address: $('#plan-can-correct-address').is(':checked') ? 1 : 0,
                    can_show_ads: $('#plan-can-show-ads').is(':checked') ? 1 : 0
                });
                this.reset();
                $('#plan-id').val('');
                $('#plan-form-title').text('Add New Plan');
                $('#plans-table').DataTable().ajax.reload();
            });

            $('#clear-plan-form').on('click', () => { $('#plan-form')[0].reset(); $('#plan-id').val(''); $('#plan-form-title').text('Add New Plan'); });

            $('#plans-table').on('click', '.edit-plan-btn', function () {
                const data = $('#plans-table').DataTable().row($(this).parents('tr')).data();

                $('#plan-id').val(data.id);
                $('#plan-name').val(data.name);
                $('#plan-price').val(data.price);
                $('#plan-limit-monthly').val(data.order_limit_monthly);
                $('#plan-limit-daily').val(data.order_limit_daily);
                $('#plan-validity').val(data.validity_days);
                $('#plan-description').val(data.description);
                $('#plan-is-active').prop('checked', data.is_active == 1);
                $('#plan-can-parse-ai').prop('checked', data.can_parse_ai == 1);
                $('#plan-can-autocomplete').prop('checked', data.can_autocomplete == 1);
                $('#plan-can-check-risk').prop('checked', data.can_check_risk == 1);
                $('#plan-can-correct-address').prop('checked', data.can_correct_address == 1);
                $('#plan-can-show-ads').prop('checked', data.can_show_ads == 1);

                $('#plan-form-title').text('Edit Plan');
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }).on('click', '.delete-plan-btn', async function () {
                if (confirm('Are you sure?')) {
                    await apiCall('delete_plan', { id: $(this).data('id') });
                    $('#plans-table').DataTable().ajax.reload();
                }
            });

            function loadPaymentMethods() {
                $('#payment-methods-table').DataTable({
                    destroy: true,
                    ajax: (d, cb) => apiCall('get_payment_methods').then(res => cb({ data: res })),
                    columns: [
                        { title: "Name", data: "name" },
                        { title: "Active", data: "is_active", render: d => d == 1 ? 'Yes' : 'No' },
                        // ADD THESE TWO HIDDEN COLUMNS TO MAKE THE DATA AVAILABLE
                        { data: 'account_details', visible: false },
                        { data: 'instructions', visible: false },
                        // ---
                        { title: "Actions", data: "id", orderable: false, render: d => `<button class="edit-pm-btn btn-sm btn-primary" data-id="${d}">Edit</button> <button class="delete-pm-btn btn-sm btn-danger" data-id="${d}">Delete</button>` }
                    ]
                });
            }

            $('#payment-method-form').on('submit', async function (e) {
                e.preventDefault();
                await apiCall('save_payment_method', {
                    id: $('#payment-method-id').val() || null, name: $('#payment-method-name').val(),
                    account_details: $('#payment-method-details').val(), instructions: $('#payment-method-instructions').val(),
                    is_active: $('#payment-method-is-active').is(':checked') ? 1 : 0
                });
                this.reset(); $('#payment-method-id').val(''); $('#payment-method-form-title').text('Add New Method');
                $('#payment-methods-table').DataTable().ajax.reload();
            });

            $('#clear-payment-method-form').on('click', () => { $('#payment-method-form')[0].reset(); $('#payment-method-id').val(''); $('#payment-method-form-title').text('Add New Method'); });

            $('#payment-methods-table').on('click', '.edit-pm-btn', function () {
                const data = $('#payment-methods-table').DataTable().row($(this).parents('tr')).data();

                // Explicitly set the value for each form field for clarity and correctness
                $('#payment-method-id').val(data.id);
                $('#payment-method-name').val(data.name);
                $('#payment-method-details').val(data.account_details);
                $('#payment-method-instructions').val(data.instructions); // This now works
                $('#payment-method-is-active').prop('checked', data.is_active == 1);

                $('#payment-method-form-title').text('Edit Method');
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }).on('click', '.delete-pm-btn', async function () {
                if (confirm('Are you sure?')) {
                    await apiCall('delete_payment_method', { id: $(this).data('id') });
                    $('#payment-methods-table').DataTable().ajax.reload();
                }
            });

            function loadSubscriptionOrders() {
                $('#subscriptions-table').DataTable({
                    destroy: true,
                    ajax: (d, cb) => apiCall('get_subscription_orders').then(res => cb({ data: res })),
                    columns: [
                        { title: "Date", data: "created_at", render: d => new Date(d).toLocaleString() },
                        { title: "User", data: "user_email" }, { title: "Plan", data: "plan_name" },
                        { title: "Details", data: null, render: (d, t, r) => `From: ${r.sender_number}<br>TrxID: ${r.transaction_id}` },
                        { title: "Status", data: "status" },
                        {
                            title: "Actions", data: "id", orderable: false, render: (d, t, r) =>
                                (r.status === 'pending') ? `<button class="approve-sub-btn btn-sm btn-success" data-id="${d}">Approve</button> <button class="reject-sub-btn btn-sm btn-danger" data-id="${d}">Reject</button>` : 'Completed'
                        }
                    ],
                    order: [[0, 'desc']]
                });
            }

            $('#subscriptions-table').on('click', '.approve-sub-btn, .reject-sub-btn', async function () {
                const status = $(this).hasClass('approve-sub-btn') ? 'approved' : 'rejected';
                if (confirm(`Are you sure you want to ${status} this?`)) {
                    // Get the DataTable instance
                    const table = $('#subscriptions-table').DataTable();

                    // 1. Update the status in the database
                    await apiCall('update_subscription_status', { id: $(this).data('id'), status });

                    // 2. Manually fetch the fresh, updated list of all subscriptions
                    const updatedData = await apiCall('get_subscription_orders');

                    // 3. Clear the table, add the new data, and redraw it
                    table.clear().rows.add(updatedData).draw();
                }
            });

            async function loadSettings() {
                const result = await apiCall('get_settings');
                $('#appName').val(result.appName || '');
                $('#logoPreview').attr('src', result.appLogoUrl ? `../${result.appLogoUrl}` : '').toggle(!!result.appLogoUrl);
                $('#geminiApiKey').val(result.geminiApiKey || '');
                $('#barikoiApiKey').val(result.barikoiApiKey || '');
                $('#googleMapsApiKey').val(result.googleMapsApiKey || '');
                $('#googleClientId').val(result.googleClientId || '');
                $('#googleClientSecret').val(result.googleClientSecret || '');
                $('#autocompleteService').val(result.autocompleteService || 'barikoi');
                $('#showAiParseButton').prop('checked', result.showAiParseButton == '1');
                $('#showAutocompleteButton').prop('checked', result.showAutocompleteButton == '1');
                $('#ezoicPlaceholderId').val(result.ezoicPlaceholderId || ''); // Add this
                $('#helpContent').val(result.helpContent || '');
            }

            $('#settings-form').on('submit', async function (e) {
                e.preventDefault();
                const formData = new FormData();
                formData.append('action', 'save_settings');
                formData.append('appName', $('#appName').val());
                formData.append('geminiApiKey', $('#geminiApiKey').val());
                formData.append('barikoiApiKey', $('#barikoiApiKey').val());
                formData.append('googleMapsApiKey', $('#googleMapsApiKey').val());
                formData.append('googleClientId', $('#googleClientId').val());
                formData.append('googleClientSecret', $('#googleClientSecret').val());
                formData.append('autocompleteService', $('#autocompleteService').val());
                formData.append('showAiParseButton', $('#showAiParseButton').is(':checked') ? '1' : '0');
                formData.append('showAutocompleteButton', $('#showAutocompleteButton').is(':checked') ? '1' : '0');
                formData.append('ezoicPlaceholderId', $('#ezoicPlaceholderId').val()); // Add this
                formData.append('helpContent', $('#helpContent').val());

                const logoFile = $('#appLogoFile')[0].files[0];
                if (logoFile) formData.append('appLogoFile', logoFile);

                try {
                    await fetch('api/admin.php', { method: 'POST', body: formData });
                    alert('Settings saved!');
                    loadSettings();
                } catch (e) {
                    alert('Error saving settings: ' + e.message);
                }
            });
        });
    </script>
</body>

</html>