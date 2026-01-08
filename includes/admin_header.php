<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: admin_login.php');
    exit;
}
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AiParcel - Admin</title>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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

        .profile-btn {
            background: none;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }

        .page-content {
            padding: 30px;
        }

        .card {
            background: var(--white);
            border-radius: 8px;
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 25px;
        }

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

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            font-weight: 600;
            display: block;
            margin-bottom: 5px;
        }

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

        .close-btn {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
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

        .btn-view-details {
            background-color: var(--info);
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 5px;
            cursor: pointer;
            color: var(--white);
            border: none;
        }
    </style>
</head>

<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="sidebar-header">AiParcel</div>
            <ul class="sidebar-nav">
                <li><a href="admin_dashboard.php"
                        class="<?php echo ($currentPage == 'admin_dashboard.php') ? 'active' : ''; ?>"><svg fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                            </path>
                        </svg><span>Dashboard</span></a></li>
                <li><a href="admin_users.php"
                        class="<?php echo ($currentPage == 'admin_users.php') ? 'active' : ''; ?>"><svg fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M15 21a6 6 0 00-9-5.197M15 11a3 3 0 11-6 0 3 3 0 016 0z">
                            </path>
                        </svg><span>Users</span></a></li>
                <li><a href="admin_parses.php"
                        class="<?php echo ($currentPage == 'admin_parses.php') ? 'active' : ''; ?>"><svg fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                            </path>
                        </svg><span>Parsed History</span></a></li>
                <li><a href="admin_orders.php"
                        class="<?php echo ($currentPage == 'admin_orders.php') ? 'active' : ''; ?>"><svg fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2-2h8a1 1 0 001-1z"></path>
                        </svg><span>Order History</span></a></li>
                <li style="border-top: 1px solid #34495e; margin-top: 10px; padding-top: 10px;">
                    <a href="admin_plans.php"
                        class="<?php echo ($currentPage == 'admin_plans.php') ? 'active' : ''; ?>"><svg fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                            </path>
                        </svg><span>Plans</span></a>
                </li>
                <li>
                    <a href="admin_payment_methods.php"
                        class="<?php echo ($currentPage == 'admin_payment_methods.php') ? 'active' : ''; ?>"><svg
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z">
                            </path>
                        </svg><span>Payments</span></a>
                </li>
                <li>
                    <a href="admin_subscriptions.php"
                        class="<?php echo ($currentPage == 'admin_subscriptions.php') ? 'active' : ''; ?>"><svg
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v.01">
                            </path>
                        </svg><span>Subscriptions</span></a>
                </li>
                <li>
                    <a href="admin_visitors.php"
                        class="<?php echo ($currentPage == 'admin_visitors.php') ? 'active' : ''; ?>"><svg fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                            </path>
                        </svg><span>Visitors</span></a>
                </li>
                <li><a href="admin_settings.php"
                        class="<?php echo ($currentPage == 'admin_settings.php') ? 'active' : ''; ?>"><svg fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
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
                <h1 id="view-title">
                    <?php echo str_replace('Admin ', '', str_replace('.php', '', ucfirst(str_replace('_', ' ', $currentPage)))); ?>
                </h1>
                <div class="profile"><button class="profile-btn" id="profileBtn"><span
                            id="adminDisplayName">Admin</span></button></div>
            </header>
            <div class="page-content">