<?php
// --- ADDED: Start of new PHP block for SEO ---
require_once 'api/config.php'; // We need this for the APP_URL constant

try {
    // This is a direct PDO connection just to get branding for SEO tags.
    // It's separate from the API calls in JavaScript.
    $pdo_seo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo_seo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt_settings = $pdo_seo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('app_name', 'app_logo_url')");
    $settings = array_column($stmt_settings->fetchAll(), 'setting_value', 'setting_key');

    $appName = htmlspecialchars($settings['app_name'] ?? 'AiParcel');
    $appLogoUrl = htmlspecialchars(APP_URL . '/' . ($settings['app_logo_url'] ?? ''));
    $appUrl = htmlspecialchars(APP_URL);
    $appDescription = htmlspecialchars("The smart, multi-courier parcel entry system designed to streamline your shipping process. Parse orders with AI, manage stores, and create shipments efficiently with {$appName}.");

} catch (PDOException $e) {
    // Set default values if the database connection fails
    $appName = 'AiParcel';
    $appLogoUrl = '';
    $appUrl = '';
    $appDescription = 'A smart, multi-courier parcel entry system to streamline your shipping process.';
}
// --- ADDED: End of new PHP block ---
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title id="appTitle"><?php echo $appName; ?> - Smart Parcel Entry System</title>
    <link rel="icon" type="image/png" href="api/uploads/favicon.png">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

    <meta name="description" content="<?php echo $appDescription; ?>">
    <meta name="keywords" content="courier, parcel, shipping, automation, steadfast, pathao, e-commerce, bangladesh">
    <link rel="canonical" href="<?php echo $appUrl; ?>/index.php">

    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo $appUrl; ?>/">
    <meta property="og:title" content="<?php echo $appName; ?> - Smart Parcel Entry">
    <meta property="og:description" content="<?php echo $appDescription; ?>">
    <meta property="og:image" content="<?php echo $appLogoUrl; ?>">

    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?php echo $appUrl; ?>/">
    <meta property="twitter:title" content="<?php echo $appName; ?> - Smart Parcel Entry">
    <meta property="twitter:description" content="<?php echo $appDescription; ?>">
    <meta property="twitter:image" content="<?php echo $appLogoUrl; ?>">
    <!-- Google Tag Manager -->
    <script>(function (w, d, s, l, i) {
            w[l] = w[l] || []; w[l].push({
                'gtm.start':
                    new Date().getTime(), event: 'gtm.js'
            }); var f = d.getElementsByTagName(s)[0],
                j = d.createElement(s), dl = l != 'dataLayer' ? '&l=' + l : ''; j.async = true; j.src =
                    'https://www.googletagmanager.com/gtm.js?id=' + i + dl; f.parentNode.insertBefore(j, f);
        })(window, document, 'script', 'dataLayer', 'GTM-TFZ57WW5');</script>
    <!-- End Google Tag Manager -->
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-6670095067396212"
        crossorigin="anonymous"></script>

    <style>
        :root {
            --primary-color: #24999B;
            --secondary-color: ##23B778;
            --light-gray: #f4f7f9;
            --white: #fff;
            --dark-gray: #333;
            --border-color: #dfe6ee;
            --success-color: #28a745;
            --error-color: #dc3545;
            --info-color: #007bff;
            --pathao-color: #E64427;
        }

        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        /* --- MODIFIED: Simplified body styles for dynamic view switching --- */
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--light-gray);
            color: var(--dark-gray);
            margin: 0;
            min-height: 100vh;
            box-sizing: border-box;
        }

        /* --- ADDED: Body classes for view state management --- */
        body.show-app {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        /* --- ADDED: Landing Page Styles --- */
        #landing-view {
            display: block;
            /* Show landing page by default */
            width: 100%;
            max-width: none;
            background-color: var(--white);
            text-align: center;
        }

        .landing-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .landing-header {
            background-color: var(--white);
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .landing-header .landing-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-area h1 {
            font-size: 24px;
            color: var(--primary-color);
            margin: 0;
        }

        .primary-btn {
            background-color: var(--primary-color);
            color: var(--white);
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .primary-btn:hover {
            background-color: #1f8082;
            /* Darker shade of primary */
        }

        .hero-section {
            padding: 80px 0;
            background-color: var(--light-gray);
        }

        .hero-section h2 {
            font-size: 42px;
            color: var(--dark-gray);
            margin-bottom: 20px;
            line-height: 1.3;
        }

        .hero-section p {
            font-size: 18px;
            color: #555;
            max-width: 700px;
            margin: 0 auto 30px;
        }

        .large-btn {
            padding: 15px 30px;
            font-size: 18px;
        }

        .features-section {
            padding: 70px 0;
        }

        .features-section h3 {
            font-size: 32px;
            margin-bottom: 50px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }

        .feature-card {
            background-color: #fdfdfd;
            padding: 30px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            text-align: left;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.07);
        }

        .feature-icon {
            font-size: 40px;
            margin-bottom: 15px;
        }

        .feature-card h4 {
            font-size: 20px;
            margin: 0 0 10px 0;
            color: var(--primary-color);
        }

        .feature-card p {
            font-size: 15px;
            line-height: 1.6;
            margin: 0;
        }

        .landing-footer {
            background-color: var(--dark-gray);
            color: var(--light-gray);
            padding: 20px 0;
            margin-top: 50px;
        }

        /* --- End of Landing Page Styles --- */

        /* --- Modern Auth Container Styles --- */
        .auth-background {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(-45deg, #1a3a3a, #24999B, #23B778, #16a085);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            padding: 20px;
        width: 100%;
            box-sizing: border-box;
        }

        @keyframes gradientShift {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        .container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 50px 40px;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            width: 100%;
            max-width: 440px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        #auth-view h1,
        #verification-view h1 {
            font-size: 32px;
            color: var(--dark-gray);
            text-align: center;
            margin-bottom: 8px;
            font-weight: 700;
        }

        #auth-view p,
        #verification-view p {
            text-align: center;
            color: #6b7280;
            margin-bottom: 30px;
            font-size: 15px;
        }

        .auth-form {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .input-group {
            position: relative;
        }

        .input-group .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 18px;
        }

        .auth-form input,
        .profile-form input {
            width: 100%;
            padding: 16px 16px 16px 48px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 16px;
            box-sizing: border-box;
            transition: all 0.3s ease;
            background: #f9fafb;
        }

        .auth-form input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(36, 153, 155, 0.1);
        }

        .auth-buttons {
            display: flex;
            gap: 12px;
            margin-top: 8px;
        }

        .auth-buttons button {
            flex: 1;
            padding: 16px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            border: 2px solid var(--primary-color);
            transition: all 0.3s ease;
        }

        #loginBtn {
            background: linear-gradient(135deg, var(--primary-color), #1f8082);
            color: var(--white);
            border: none;
        }

        #loginBtn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(36, 153, 155, 0.3);
        }

        #registerBtn {
            background-color: var(--white);
            color: var(--primary-color);
        }

        #registerBtn:hover {
            background-color: #f0fafa;
        }

        /* Social Login Divider */
        .auth-divider {
            display: flex;
            align-items: center;
            margin: 24px 0;
            color: #9ca3af;
            font-size: 14px;
        }

        .auth-divider::before,
        .auth-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5e7eb;
        }

        .auth-divider span {
            padding: 0 16px;
        }

        /* Google Sign-In Button */
        .google-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            padding: 14px 20px;
            background: #fff;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 500;
            color: #374151;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .google-btn:hover {
            background: #f9fafb;
            border-color: #d1d5db;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .google-btn svg {
            width: 20px;
            height: 20px;
        }

        .google-btn .loader {
            width: 18px;
            height: 18px;
            border-width: 2px;
            margin: 0;
        }

        #app-view {
            display: none;
        }

        .app-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .app-header h1 {
            font-size: 24px;
            color: var(--primary-color);
            margin: 0;
        }

        .app-header #logoutBtn {
            padding: 8px 16px;
            background: #fbebee;
            color: var(--primary-color);
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
        }

        .app-nav {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .app-nav button {
            flex-grow: 1;
            padding: 10px;
            background: var(--white);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .app-nav button:hover {
            background-color: var(--light-gray);
            border-color: var(--secondary-color);
        }

        .section h2 {
            font-size: 18px;
            margin-bottom: 15px;
            color: var(--dark-gray);
            border-left: 3px solid var(--primary-color);
            padding-left: 10px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--secondary-color);
            font-size: 14px;
        }

        textarea,
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 16px;
            box-sizing: border-box;
        }

        .parsing-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        #createOrderBtn,
        .parsing-buttons button {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }

        #parseAndAutocompleteBtn {
            background-color: #16a085;
            color: white;
        }

        #createOrderBtn {
            background-color: var(--primary-color);
            color: white;
            margin-top: 15px;
            font-size: 18px;
        }

        #parseWithAIBtn {
            background-color: #8e44ad;
            color: white;
        }

        #parseWithAIBtn:disabled {
            background-color: #9b59b6;
            cursor: not-allowed;
            opacity: 0.7;
        }

        #parseLocallyBtn {
            background-color: var(--info-color);
            color: white;
        }

        .summary-results {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }

        .summary-item {
            flex: 1;
            padding: 10px;
            background-color: var(--light-gray);
            border-radius: 5px;
            border: 1px solid var(--border-color);
            font-size: 14px;
        }

        .summary-item span {
            font-weight: bold;
            color: var(--info-color);
        }

        #parsedDataContainer {
            margin-top: 10px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 300px;
            overflow-y: auto;
            padding: 5px;
            background: #fcfcfc;
            border: 1px solid var(--border-color);
            border-radius: 6px;
        }

        .parcel-card {
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 15px;
            background-color: var(--white);
            display: grid;
            grid-template-columns: 1fr;
            gap-y: 10px;
        }

        .parcel-card .details {
            font-size: 14px;
            line-height: 1.5;
            word-break: break-word;
        }

        .parcel-card strong {
            color: var(--dark-gray);
        }

        .parcel-actions {
            grid-column: 1 / -1;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 10px;
        }

        .remove-btn {
            background: transparent;
            border: none;
            color: var(--error-color);
            cursor: pointer;
            font-weight: bold;
            font-size: 24px;
            opacity: 0.6;
            padding: 0 5px;
        }

        .remove-btn:hover {
            opacity: 1;
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
            color: var(--success-color);
        }

        .error {
            background-color: #fbebee;
            color: var(--error-color);
        }

        .loader {
            display: none;
            margin: 20px auto;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 25px;
            border-radius: 8px;
            width: 90%;
            max-width: 700px;
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
            color: var(--primary-color);
        }

        .close-btn {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .store-management {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .form-group input,
        .form-group button,
        .form-group select {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            font-size: 14px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
        }

        .form-group button {
            background-color: var(--success-color);
            color: white;
            border: none;
            cursor: pointer;
        }

        #storeList {
            list-style-type: none;
            padding: 0;
            margin: 0;
            max-height: 200px;
            overflow-y: auto;
        }

        #storeList li {
            background: var(--light-gray);
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 8px;
            font-size: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .store-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .edit-store-btn {
            padding: 4px 10px;
            font-size: 12px;
            background-color: var(--info-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .delete-store-btn {
            background: transparent;
            border: none;
            color: var(--error-color);
            cursor: pointer;
            font-weight: bold;
            font-size: 20px;
            opacity: 0.6;
        }

        .courier-badge {
            font-size: 10px;
            font-weight: 600;
            padding: 3px 6px;
            border-radius: 4px;
            color: white;
            margin-left: 8px;
        }

        .courier-badge.steadfast {
            background-color: var(--primary-color);
        }

        .courier-badge.pathao {
            background-color: var(--pathao-color);
        }

        #parserFields {
            list-style: none;
            padding: 0;
            max-height: 250px;
            overflow-y: auto;
            margin-bottom: 20px;
        }

        #parserFields li {
            padding: 8px 12px;
            background: var(--light-gray);
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 14px;
            cursor: grab;
            user-select: none;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .field-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .delete-field-btn {
            background: transparent;
            border: none;
            color: var(--error-color);
            cursor: pointer;
            font-weight: bold;
            font-size: 20px;
            opacity: 0.6;
        }

        .available-fields-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
        }

        .available-field-tile {
            background-color: #e9ecef;
            border: 1px solid #ced4da;
            color: #495057;
            padding: 6px 12px;
            border-radius: 16px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .available-field-tile:hover {
            background-color: #dee2e6;
        }

        .plan-status {
            background-color: var(--light-gray);
            border: 1px solid var(--border-color);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            text-align: center;
        }

        .plan-status h3 {
            margin: 0 0 10px 0;
            color: var(--dark-gray);
        }

        .plan-status p {
            margin: 5px 0;
            font-size: 14px;
        }

        .plan-status strong {
            color: var(--primary-color);
        }

        .progress-bar {
            background-color: #e9ecef;
            border-radius: 5px;
            overflow: hidden;
            height: 10px;
            margin-top: 5px;
        }

        .progress-bar-inner {
            background-color: var(--success-color);
            height: 100%;
            transition: width 0.5s ease;
        }

        #upgrade-modal .modal-content {
            max-width: 500px;
        }

        .plans-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 20px;
        }

        .plan-option {
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .plan-option.selected {
            border-color: var(--primary-color);
            background-color: #fef5f5;
        }

        .plan-option:hover {
            border-color: #fab3b6;
        }

        .plan-option h4 {
            margin: 0 0 5px 0;
        }

        .plan-option p {
            font-size: 14px;
            margin: 0;
            color: var(--secondary-color);
        }

        .plan-option pre {
            white-space: pre-wrap;
            font-family: inherit;
            font-size: 14px;
            color: var(--secondary-color);
        }

        .payment-methods-container,
        #payment-details-form {
            display: none;
        }

        #payment-instructions {
            background: #f0f0f0;
            padding: 10px;
            border-radius: 5px;
            font-size: 14px;
            line-height: 1.5;
            color: var(--dark-gray);
        }

        .check-risk-btn {
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 500;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: auto;
        }

        .check-risk-btn:disabled {
            background-color: #bdc3c7;
            cursor: not-allowed;
        }

        .fraud-results-container {
            grid-column: 1 / -1;
            margin-top: 10px;
        }

        .fraud-results-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            text-align: center;
        }

        .fraud-results-table th,
        .fraud-results-table td {
            border: 1px solid var(--border-color);
            padding: 5px;
        }

        .fraud-results-table th {
            background-color: var(--light-gray);
            font-weight: 600;
        }

        /* Styles for Upgraded Modal */
        .upgrade-step {
            display: none;
        }

        /* Hide all steps by default */
        .plan-option-details {
            font-size: 13px;
            color: #555;
            margin-top: 10px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 5px;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }

        .btn-back {
            background: none;
            border: none;
            color: var(--primary-color);
            cursor: pointer;
            font-weight: 500;
            margin-top: 20px;
        }

        #submit-payment-btn .loader {
            /* Loader for the submit button */
            display: inline-block;
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--white);
            border-radius: 50%;
            width: 16px;
            height: 16px;
            animation: spin 1s linear infinite;
            vertical-align: middle;
            margin-left: 8px;
            display: none;
            /* Hidden by default */
        }

        .auth-buttons button .loader,
        #requestResetBtn .loader {
            display: none;
            width: 16px;
            height: 16px;
            border-width: 2px;
            vertical-align: middle;
            margin-left: 8px;
        }

        .auth-buttons button:disabled .loader,
        #requestResetBtn:disabled .loader {
            display: inline-block;
        }

        /* --- ADDED: Chat Widget Styles --- */
        .chat-widget-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 1000;
        }

        .chat-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            color: white;
            text-decoration: none;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            transition: transform 0.2s ease;
        }

        .chat-btn svg {
            width: 24px;
            height: 24px;
            fill: currentColor;
        }

        .chat-btn:hover {
            transform: scale(1.1);
        }

        .whatsapp-btn {
            background-color: #25D366;
        }

        .messenger-btn {
            background-color: #0084FF;
        }


        .toggle-switch-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .toggle-switch-container label {
            font-weight: 500;
            color: var(--primary-color);
            margin-bottom: 0;
            /* Override default label margin */
            font-size: 14px;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            /* Reduced width */
            height: 28px;
            /* Reduced height */
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 28px;
            /* Fully rounded */
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 20px;
            /* Reduced handle size */
            width: 20px;
            /* Reduced handle size */
            left: 4px;
            /* Adjusted position */
            bottom: 4px;
            /* Adjusted position */
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: var(--primary-color);
        }

        input:checked+.slider:before {
            transform: translateX(22px);
            /* Adjusted transform */
        }


        /* --- End of Chat Widget Styles --- */
        @media (max-width: 768px) {
            .store-management {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {

            .auth-buttons,
            .parsing-buttons {
                flex-direction: column;
            }

            .app-nav {
                justify-content: center;
            }

            .hero-section h2 {
                font-size: 32px;
            }

            .logo-area h1 {
                display: none;
            }
        }
    </style>
</head>

<body>
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-TFZ57WW5" height="0" width="0"
            style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    <!-- ADDED: Landing page view -->
    <div id="landing-view">
        <header class="landing-header">
            <div class="landing-container">
                <div class="logo-area">
                    <img id="landingLogo" src="" alt="Logo" style="max-height: 50px;">
                    <h1 id="landingTitle"><?php echo $appName; ?></h1>
                </div>
                <nav>
                    <button id="show-login-btn" class="primary-btn">লগইন / রেজিস্টার</button>
                </nav>
            </div>
        </header>

        <main>
            <section class="hero-section">
                <div class="landing-container">
                    <h2>আপনার সব কুরিয়ার পার্সেল এক জায়গায় পরিচালনা করুন, আরও দ্রুত ও সহজভাবে।</h2>
                    <p>AiParcel ব্যবহার করে স্বয়ংক্রিয়ভাবে পার্সেলের তথ্য আলাদা করুন, সময় বাঁচান, এবং একাধিক কুরিয়ার
                        কোম্পানির জন্য একসাথে পার্সেল তৈরি করুন নিমিষে।</p>
                    <button id="show-login-btn-hero" class="primary-btn large-btn">এখনই শুরু করুন</button>
                </div>
            </section>

            <section class="features-section">
                <div class="landing-container">
                    <h3>আমাদের প্রধান বৈশিষ্ট্যসমূহ</h3>
                    <div class="features-grid">
                        <div class="feature-card">
                            <div class="feature-icon">📦</div>
                            <h4>একাধিক কুরিয়ার সাপোর্ট</h4>
                            <p>স্টেডফাস্ট, পাঠাও এবং অন্যান্য জনপ্রিয় কুরিয়ার কোম্পানির জন্য সহজেই পার্সেল তৈরি করুন।
                            </p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">🤖</div>
                            <h4>AI দ্বারা পার্সেল পার্সিং</h4>
                            <p>শুধু কাস্টমারের তথ্য পেস্ট করুন, আমাদের AI স্বয়ংক্রিয়ভাবে নাম, ঠিকানা, ফোন নম্বর বের
                                করে দেবে।</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">📈</div>
                            <h4>ডেলিভারি হিস্টোরি</h4>
                            <p>আপনার সকল পার্সেলের বর্তমান অবস্থা এবং পূর্বের সকল রেকর্ড ট্র্যাক করুন সহজেই।</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">🏪</div>
                            <h4>একাধিক স্টোর ম্যানেজমেন্ট</h4>
                            <p>আপনার ভিন্ন ভিন্ন অনলাইন স্টোরের জন্য আলাদা প্রোফাইল তৈরি ও পরিচালনা করুন।</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">🛡️</div>
                            <h4>ঝুঁকি যাচাই</h4>
                            <p>পার্সেল পাঠানোর আগে কাস্টমারের পূর্বের ডেলিভারি সফলতার হার যাচাই করে আপনার ব্যবসার ঝুঁকি
                                কমান।</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">💳</div>
                            <h4>সাবস্ক্রিপশন সিস্টেম</h4>
                            <p>আপনার প্রয়োজন অনুযায়ী বিভিন্ন প্রিমিয়াম প্ল্যান বেছে নিন এবং আরও বেশি সুবিধা উপভোগ
                                করুন।</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">⚙️</div>
                            <h4>কাস্টমাইজড পার্সিং অপশন</h4>
                            <p>আপনার পার্সেল তথ্যের বিন্যাস অনুযায়ী পার্সার সেট করুন এবং নির্ভুলভাবে ডেটা এন্ট্রি করুন।
                            </p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">⚡️</div>
                            <h4>দ্রুত বাল্ক এন্ট্রি</h4>
                            <p>এক ক্লিকে শত শত পার্সেল এন্ট্রি করুন এবং আপনার মূল্যবান সময় বাঁচান।</p>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="landing-footer">
            <p>&copy; <?php echo date("Y"); ?> <span id="footerAppName"><?php echo $appName; ?></span>. সর্বস্বত্ব
                সংরক্ষিত।</p>
        </footer>
    </div>

    <!-- MODIFIED: Auth container with modern animated background -->
    <div class="auth-background" id="auth-container" style="display: none;">
        <div class="container">
            <div id="auth-view">
                <img id="authLogo" src="" alt="Logo" style="display: block; margin: 0 auto 20px; max-height: 80px;">
                <h1 id="authTitle">Welcome Back!</h1>
                <p>Sign in to manage your parcels efficiently</p>
                <div class="auth-form">
                    <div class="input-group">
                        <span class="input-icon">✉️</span>
                        <input type="email" id="email" placeholder="Email Address" required>
                    </div>
                    <div class="input-group">
                        <span class="input-icon">🔒</span>
                        <input type="password" id="password" placeholder="Password" required>
                    </div>
                    <div class="auth-buttons">
                        <button id="loginBtn">Login <span class="loader"></span></button>
                        <button id="registerBtn">Register <span class="loader"></span></button>
                    </div>

                    <div class="auth-divider">
                        <span>or continue with</span>
                    </div>

                    <button type="button" id="googleLoginBtn" class="google-btn">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path fill="#4285F4"
                                d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                            <path fill="#34A853"
                                d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                            <path fill="#FBBC05"
                                d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
                            <path fill="#EA4335"
                                d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                        </svg>
                        Sign in with Google
                        <span class="loader" style="display:none;"></span>
                    </button>

                    <a href="#" id="forgotPasswordLink"
                        style="text-align: center; display: block; margin-top: 16px; color: var(--primary-color); font-size: 14px;">Forgot
                        Password?</a>
                    <div id="auth-message" class="message" style="display:none;"></div>
                </div>
            </div>



            <div id="verification-view" style="display:none;">
                <h1>Verify Your Email</h1>
                <p>A verification link has been sent to your email address. Please check your inbox (and spam folder)
                    and
                    click the link to activate your account.</p>
                <button id="resendVerificationBtn"
                    style="width:100%; padding: 12px; background: var(--primary-color); color: white; border: none; border-radius: 6px; cursor: pointer;">Resend
                    Verification Email<span class="loader"></span></button>
                <div id="verification-message" class="message" style="display:none;"></div>
            </div>

            <div id="reset-password-request-view" style="display:none;">
                <h1>Reset Password</h1>
                <p>Enter your email address and we'll send you a link to reset your password.</p>
                <div class="auth-form">
                    <input type="email" id="resetEmail" placeholder="Your Email Address" required>
                    <div class="auth-buttons" style="flex-direction: column;">
                        <button id="requestResetBtn">Send Reset Link <span class="loader"></span></button>
                        <button id="backToLoginBtn"
                            style="background:none; border:none; color:var(--primary-color); padding: 10px 0;">&larr;
                            Back
                            to Login</button>
                    </div>
                    <div id="reset-request-message" class="message" style="display:none;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- App View - Separate from auth container -->
    <div id="app-view"
        style="display: none; width: 100%; max-width: 900px; margin: 0 auto; padding: 20px; background: var(--white); border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
        <header class="app-header">
            <div style="display: flex; align-items: center; gap: 15px;">
                <img id="dashboardLogo" src="" alt="Logo" style="max-height: 50px;">
                <h1 id="dashboardTitle" style="margin:0;">Welcome, <span id="userInfo">User</span></h1>
            </div>
            <button id="logoutBtn">Logout</button>
        </header>
        <nav class="app-nav">
            <button id="openProfileModalBtn">Profile Settings</button>
            <button id="openStoreModalBtn">Manage Stores</button>
            <button id="openSettingsModalBtn">Parser Settings</button>
            <button id="openHistoryModalBtn">History</button>
            <button id="openUpgradeModalBtn"
                style="background-color: var(--success-color); color: white; border-color: var(--success-color);">Upgrade
                Plan</button>
            <button id="openSubscriptionHistoryModalBtn">Subscription History</button>
            <button id="openHelpModalBtn">Help & Guide</button>
        </nav>

        <div class="plan-status" id="plan-status-view" style="display:none;"></div>

        <div class="section">
            <h2>Create New Parcel(s)</h2>
            <div>
                <label for="storeSelector">Select Store for this Batch</label>
                <select id="storeSelector">
                    <option>Please add a store first</option>
                </select>
            </div>
            <div class="toggle-switch-container">
                <label for="autoParseToggle">Enable Smart Auto-Parsing:</label>
                <label class="toggle-switch">
                    <input type="checkbox" id="autoParseToggle" checked>
                    <span class="slider"></span>
                </label>
            </div>
            <div style="margin-top:15px;">
                <label for="rawText">Paste All Parcel Info Here</label>
                <textarea id="rawText" rows="12"
                    placeholder="Paste single or multiple parcel details here..."></textarea>
                <div class="parsing-buttons">
                    <button id="parseWithAIBtn">Parse with AI </button>
                    <button id="parseLocallyBtn">Parse Locally</button>
                    <button id="parseAndAutocompleteBtn">Parse & Autocomplete</button>
                    <button id="checkAllRiskBtn" style="background-color: #e67e22;">Check All Risks</button>
                </div>
            </div>
            <label style="margin-top: 15px;">Parsing Summary</label>
            <div class="summary-results">
                <div class="summary-item">Parcels Parsed: <span id="parcelCount">0</span></div>
                <div class="summary-item">Total COD: <span id="totalCod">0 BDT</span></div>
            </div>
            <label style="margin-top: 15px;">Review Parsed Parcels</label>
            <div id="parsedDataContainer"></div>
            <button id="createOrderBtn">Create Order(s)</button>
            <div class="loader" id="loader"></div>
            <label style="margin-top: 15px;">API Response</label>
            <pre id="apiResponse"
                style="background: var(--light-gray); padding: 10px; border-radius: 6px; min-height: 50px; white-space: pre-wrap; word-break: break-all;">API response will appear here.</pre>
        </div>
    </div>
    </div>


    <div id="store-modal" class="modal"></div>
    <div id="settings-modal" class="modal"></div>
    <div id="history-modal" class="modal"></div>
    <div id="profile-modal" class="modal"></div>
    <div id="details-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="details-title">Details</h2><span class="close-btn">&times;</span>
            </div>
            <pre id="details-content"></pre>
        </div>
    </div>

    <div id="upgrade-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="upgrade-modal-title">Upgrade Your Plan</h2>
                <span class="close-btn">&times;</span>
            </div>

            <div id="upgrade-step-1" class="upgrade-step">
                <h4>1. Select a Plan</h4>
                <div id="plans-container" class="plans-container">Loading plans...</div>
            </div>

            <div id="upgrade-step-2" class="upgrade-step">
                <h4>2. Select Payment Method</h4>
                <div id="payment-methods-container" class="plans-container">Loading...</div>
                <button class="btn-back" data-target-step="1">&larr; Back to Plans</button>
            </div>

            <div id="upgrade-step-3" class="upgrade-step">
                <h4>3. Enter Payment Details</h4>
                <div id="payment-summary"
                    style="margin-bottom: 15px; padding: 15px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px;">
                    <h5 style="margin-top: 0; margin-bottom: 10px; font-size: 16px; color: #333;">Order Summary</h5>
                    <div style="font-size: 14px; line-height: 1.6;">
                        <p style="margin: 0;"><strong>Plan:</strong> <span id="summary-plan-name"></span></p>
                        <p style="margin: 0;"><strong>Amount to Pay:</strong> <span id="summary-plan-price"></span>
                            BDT
                        </p>
                        <p style="margin: 5px 0 0 0;"><strong>Pay to Number:</strong></p>
                        <pre id="summary-payment-details"
                            style="background-color: #fff; padding: 10px; border-radius: 4px; border: 1px solid #ced4da; margin-top: 5px; white-space: pre-wrap;"></pre>
                    </div>
                </div>
                <p id="payment-instructions"></p>
                <div class="form-group" style="gap: 5px; margin-top: 10px;">
                    <label for="sender-number">Your Sender Number</label>
                    <input type="text" id="sender-number" placeholder="The number you paid from">
                </div>
                <div class="form-group" style="gap: 5px;">
                    <label for="transaction-id">Transaction ID (TrxID)</label>
                    <input type="text" id="transaction-id" placeholder="Payment Transaction ID">
                </div>
                <button id="submit-payment-btn"
                    style="width: 100%; padding: 12px; font-size: 16px; background-color: var(--success-color); color: white; border: none; cursor: pointer; border-radius: 6px;">
                    Submit for Verification <span class="loader"></span>
                </button>
                <button class="btn-back" data-target-step="2">&larr; Back to Payment Methods</button>
            </div>

            <div id="upgrade-message" class="message" style="display:none;"></div>
        </div>
    </div>


    <div id="help-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>How to Use This Site</h2>
                <span class="close-btn">&times;</span>
            </div>
            <div id="help-content-container" style="max-height: 70vh; overflow-y: auto; padding-right: 15px;">
            </div>
        </div>
    </div>

    <div id="subscription-history-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>My Subscription History</h2>
                <span class="close-btn">&times;</span>
            </div>
            <div class="table-container">
                <table id="subscription-history-table" class="display" style="width:100%"></table>
            </div>
        </div>
    </div>

    <div class="chat-widget-container">
        <a href="https://wa.me/8801886626868" class="chat-btn whatsapp-btn" target="_blank" title="Chat on WhatsApp">
            <svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                <path
                    d="M16.001 2C8.27 2 2 8.27 2 16.001s6.27 14.001 14.001 14.001 14.001-6.27 14.001-14.001S23.732 2 16.001 2zm6.602 20.29c-.313.913-1.468 1.63-2.138 1.693-.521.05-1.139.063-3.235-.742-2.387-.913-4.223-2.67-5.89-4.708-1.782-2.178-3.004-4.83-3.045-4.947-.042-.117-.92-1.229-.92-2.234 0-.962.519-1.475.694-1.67.175-.194.389-.25.563-.25.175 0 .35.013.5.025.263.025.426.038.65.413.262.45.875 2.112.95 2.274.075.163.15.35.038.563-.112.213-.175.325-.312.475-.138.15-.275.313-.388.425-.112.113-.237.25-.112.488.125.237.563.95 1.125 1.575.763.85 1.626 1.488 2.59 1.963.775.375 1.2.412 1.525.35.325-.063.875-.413 1.113-.7.237-.288.45-.6.712-.8.3-.213.563-.113.888.062.325.175 2.113 1 2.475 1.175.363.175.613.263.7.4.088.137.025.824-.288 1.737z" />
            </svg>
        </a>
        <a href="https://m.me/quantumtechsoft" class="chat-btn messenger-btn" target="_blank" title="Chat on Messenger">
            <svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                <path
                    d="M16 2.001c-7.72 0-14 5.46-14 12.19C2 20.3 6.02 24.8 11.23 26.83c.3.11.57.17.84.17.6 0 1.1-.3 1.38-.8l.4-1.2A11.9 11.9 0 0 1 16 23.4c5.52 0 10-3.9 10-8.62s-4.48-8.78-10-8.78zm.88 12.86l-2.45 2.1-5.5-4.8 10.6-4.2c.4-.16.7.3.4.6l-3.05 6.3zM21.1 19.3s.4.5.1.8c-.3.3-.9.3-1.2.1l-3.2-2.1-2.1 1.8c-.4.4-1.1.4-1.4.1-.3-.3-.2-.9.1-1.2l6.1-5.3c.4-.3 1 .1 1 .6l-2.6 5.3 2.1 1.5.1-.1z" />
            </svg>
        </a>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script type="text/javascript" charset="utf8"
        src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
        // --- GLOBAL STATE & CONSTANTS ---
        let userCourierStores = {};
        let geminiApiKey = null;
        let isPremiumUser = false;
        let currentUser = null;
        let userPermissions = {};
        let currentParserFields = [];
        let helpContent = '';
        const DEFAULT_PARSER_FIELDS = [

            { id: 'customerName', label: 'Customer Name', required: true },
            { id: 'phone', label: 'Phone', required: true },
            { id: 'address', label: 'Address', required: true },
            { id: 'amount', label: 'Amount', required: true },
            { id: 'productName', label: 'Product Name', required: false },
            { id: 'note', label: 'Note', required: false },
            { id: 'orderId', label: 'OrderID', required: false }
        ];

        // --- DOM ELEMENT REFS ---
        const authView = document.getElementById('auth-view');
        const appView = document.getElementById('app-view');
        const verificationView = document.getElementById('verification-view');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const loginBtn = document.getElementById('loginBtn');
        const registerBtn = document.getElementById('registerBtn');
        const logoutBtn = document.getElementById('logoutBtn');
        const userInfo = document.getElementById('userInfo');
        const authMessage = document.getElementById('auth-message');
        const storeSelector = document.getElementById('storeSelector');
        const rawTextInput = document.getElementById('rawText');
        const parsedDataContainer = document.getElementById('parsedDataContainer');
        const parcelCountSpan = document.getElementById('parcelCount');
        const totalCodSpan = document.getElementById('totalCod');
        const createOrderBtn = document.getElementById('createOrderBtn');
        const loader = document.getElementById('loader');
        const apiResponseDiv = document.getElementById('apiResponse');
        const parseLocallyBtn = document.getElementById('parseLocallyBtn');
        const parseWithAIBtn = document.getElementById('parseWithAIBtn');
        const parseAndAutocompleteBtn = document.getElementById('parseAndAutocompleteBtn');
        const checkAllRiskBtn = document.getElementById('checkAllRiskBtn');

        const openStoreModalBtn = document.getElementById('openStoreModalBtn');
        const openSettingsModalBtn = document.getElementById('openSettingsModalBtn');
        const openHistoryModalBtn = document.getElementById('openHistoryModalBtn');
        const openProfileModalBtn = document.getElementById('openProfileModalBtn');
        const openUpgradeModalBtn = document.getElementById('openUpgradeModalBtn');

        const resendVerificationBtn = document.getElementById('resendVerificationBtn');
        const planStatusView = document.getElementById('plan-status-view');


        // At the top of the script with other DOM refs
        const openSubscriptionHistoryModalBtn = document.getElementById('openSubscriptionHistoryModalBtn');
        const forgotPasswordLink = document.getElementById('forgotPasswordLink');
        const backToLoginBtn = document.getElementById('backToLoginBtn');
        const requestResetBtn = document.getElementById('requestResetBtn');
        const resetPasswordRequestView = document.getElementById('reset-password-request-view');

        // --- HELPER FUNCTIONS ---
        async function apiCall(action, body = {}) {
            try {
                const response = await fetch('api/index.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action, ...body }),
                    credentials: 'same-origin'
                });
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.error || `HTTP error! status: ${response.status}`);
                }
                return data;
            } catch (error) {
                console.error('API Call Error:', action, error);
                throw error;
            }
        }


        function displayApiResponse(data) {
            const responseContainer = $('#apiResponse');
            responseContainer.empty().show().removeClass('message success error');

            // Case 1: Steadfast Bulk Success (DataTable)
            if (data && data.status === 200 && Array.isArray(data.data) && data.data.length > 0) {
                let tableHtml = `
                <p class="message success" style="display:block;">Successfully created ${data.data.length} Steadfast order(s).</p>
                <table id="api-response-datatable" class="display api-response-table" style="width:100%"></table>
            `;
                responseContainer.html(tableHtml);

                $('#api-response-datatable').DataTable({
                    data: data.data,
                    columns: [
                        { title: "Invoice", data: "invoice" },
                        { title: "Consignment ID", data: "consignment_id" },
                        { title: "Tracking Code", data: "tracking_code" },
                        { title: "Recipient", data: "recipient_name" },
                        { title: "Phone", data: "recipient_phone" },
                        { title: "Address", data: "recipient_address" },
                        { title: "COD Amount", data: "cod_amount" },
                        { title: "Note", data: "note" },
                        { title: "Status", data: "status", render: data => `<span class="status-success">${data}</span>` }
                    ],
                    destroy: true, pageLength: 5, lengthChange: true, searching: true,
                    scrollX: true
                });
            }
            // Case 2: NEW - Steadfast Single Order Success (DataTable)
            else if (data && data.status === 200 && data.consignment && data.consignment.consignment_id) {
                let tableHtml = `
                <p class="message success" style="display:block;">${data.message || 'Successfully created 1 Steadfast order.'}</p>
                <table id="api-response-datatable" class="display api-response-table" style="width:100%"></table>
            `;
                responseContainer.html(tableHtml);

                // DataTable needs an array, so we wrap the single 'consignment' object in []
                $('#api-response-datatable').DataTable({
                    data: [data.consignment],
                    columns: [
                        { title: "Invoice", data: "invoice" },
                        { title: "Consignment ID", data: "consignment_id" },
                        { title: "Tracking Code", data: "tracking_code" },
                        { title: "Recipient", data: "recipient_name" },
                        { title: "Phone", data: "recipient_phone" },
                        { title: "Address", data: "recipient_address" },
                        { title: "COD Amount", data: "cod_amount" },
                        { title: "Status", data: "status", render: data => `<span class="status-success">${data}</span>` },
                        { title: "Note", data: "note" },
                        { title: "Created At", data: "created_at", render: data => new Date(data).toLocaleString() }
                    ],
                    destroy: true, pageLength: 5, lengthChange: false, searching: false,
                    scrollX: true
                });
            }
            // Case 3: Pathao Single Order Success (DataTable)
            else if (data && data.code === 200 && data.type === 'success' && data.data && data.data.consignment_id) {
                let tableHtml = `
                <p class="message success" style="display:block;">Successfully created 1 Pathao order.</p>
                <table id="api-response-datatable" class="display api-response-table" style="width:100%"></table>
            `;
                responseContainer.html(tableHtml);

                $('#api-response-datatable').DataTable({
                    data: [data.data],
                    columns: [
                        { title: "Consignment ID", data: "consignment_id" },
                        { title: "Merchant Order ID", data: "merchant_order_id" },
                        { title: "Order Status", data: "order_status" },
                        { title: "Delivery Fee", data: "delivery_fee" }
                    ],
                    destroy: true, pageLength: 5, lengthChange: false, searching: false
                });
            }
            // Case 4: Pathao Bulk Order Accepted (Notification Message)
            else if (data && data.code === 202 && data.type === 'success') {
                let messageHtml = `<p class="message success" style="display:block; text-align:left; line-height: 1.6;">
                <strong style="font-size: 16px;">Request Accepted</strong><br>${data.message}
             </p>`;
                responseContainer.html(messageHtml);
            }
            // Case 5: Fallback for Errors and other formats
            else {
                let type = (data && (data.error || (data.type && data.type !== 'success'))) ? 'error' : 'success';
                responseContainer.html(`<pre>${JSON.stringify(data, null, 2)}</pre>`);
                responseContainer.addClass(`message ${type}`);
            }
        }

        const showMessage = (element, text, type, duration = 5000) => {
            element.textContent = text;
            element.className = `message ${type}`;
            element.style.display = 'block';
            setTimeout(() => { element.style.display = 'none'; }, duration);
        };

        // --- AUTH & INITIALIZATION ---
        // MODIFIED: DOMContentLoaded listener to handle view switching
        document.addEventListener('DOMContentLoaded', async () => {
            const landingView = document.getElementById('landing-view');
            const authContainer = document.getElementById('auth-container');

            // Check for Google OAuth success
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('google_login') === 'success') {
                // Clean URL without reloading
                window.history.replaceState({}, document.title, window.location.pathname);
            }

            try {
                const session = await apiCall('check_session');
                if (session.loggedIn && session.user) {
                    currentUser = session.user;
                    isPremiumUser = session.user.plan_id > 1;

                    document.body.className = 'show-app'; // Set class
                    landingView.style.display = 'none';
                    authContainer.style.display = 'none'; // Hide auth container
                    appView.style.display = 'block'; // Show app view explicitly
                    await renderAppView();
                } else {
                    const publicSettings = await apiCall('load_user_data');
                    applyBranding(publicSettings);
                    document.body.className = 'show-landing'; // Set class
                    landingView.style.display = 'block';
                    authContainer.style.display = 'none';
                    appView.style.display = 'none';
                }
            } catch (e) {
                try {
                    const publicSettings = await apiCall('load_user_data');
                    applyBranding(publicSettings);
                } catch (settingsError) { console.error("Could not load branding settings.", settingsError); }

                document.body.className = 'show-landing'; // Set class
                landingView.style.display = 'block';
                document.getElementById('auth-container').style.display = 'none';
                document.getElementById('app-view').style.display = 'none';
            }
        });

        // --- ADDED: Landing page button listeners ---
        function showAuthPage() {
            document.body.className = 'show-app'; // Set class
            document.getElementById('landing-view').style.display = 'none';
            document.getElementById('auth-container').style.display = 'flex';
            document.getElementById('app-view').style.display = 'none';
            renderAuthView();
        }

        document.getElementById('show-login-btn').addEventListener('click', showAuthPage);
        document.getElementById('show-login-btn-hero').addEventListener('click', showAuthPage);


        loginBtn.addEventListener('click', async () => {
            loginBtn.disabled = true;
            try {
                const data = await apiCall('login', { email: emailInput.value, password: passwordInput.value });
                if (data.loggedIn && data.user) {
                    currentUser = data.user;
                    isPremiumUser = data.user.plan_id > 1;
                    document.getElementById('auth-container').style.display = 'none'; // Hide auth
                    document.getElementById('app-view').style.display = 'block'; // Show app
                    await renderAppView();
                }
            } catch (error) {
                if (error.message.includes('Email not verified')) {
                    authView.style.display = 'none';
                    verificationView.style.display = 'block';
                } else { showMessage(authMessage, error.message, 'error'); }
            } finally {
                loginBtn.disabled = false;
            }
        });

        registerBtn.addEventListener('click', () => handleAuthAction('register', 'Registration successful. Please check your email.'));
        resendVerificationBtn.addEventListener('click', () => handleAuthAction('resend_verification', 'If an account exists, a new verification link has been sent.'));

        // --- Google Sign-In Handler ---
        document.getElementById('googleLoginBtn').addEventListener('click', async () => {
            const googleBtn = document.getElementById('googleLoginBtn');
            const loader = googleBtn.querySelector('.loader');

            googleBtn.disabled = true;
            loader.style.display = 'inline-block';

            try {
                const data = await apiCall('google_login_url');
                if (data.url) {
                    window.location.href = data.url;
                } else {
                    showMessage(authMessage, 'Failed to get Google login URL', 'error');
                }
            } catch (error) {
                showMessage(authMessage, 'Google Sign-In failed: ' + error.message, 'error');
                googleBtn.disabled = false;
                loader.style.display = 'none';
            }
        });

        async function handleAuthAction(action, successMessage) {
            const btn = (action === 'register') ? registerBtn : resendVerificationBtn;
            const msgEl = (action === 'register') ? authMessage : document.getElementById('verification-message');

            btn.disabled = true;
            try {
                await apiCall(action, { email: emailInput.value, password: passwordInput.value });
                showMessage(msgEl, successMessage, 'success');
                if (action === 'register' && window.dataLayer) {
                    window.dataLayer.push({
                        'event': 'sign_up'
                    });
                }
            } catch (error) {
                showMessage(authMessage, error.message, 'error');
            } finally {
                btn.disabled = false;
            }
        }

        logoutBtn.addEventListener('click', async () => {
            await apiCall('logout');
            window.location.reload();
        });


        // Add these new listeners anywhere with other event listeners

        // --- Password Reset Flow ---
        forgotPasswordLink.addEventListener('click', (e) => {
            e.preventDefault();
            authView.style.display = 'none';
            verificationView.style.display = 'none';
            resetPasswordRequestView.style.display = 'block';
        });

        backToLoginBtn.addEventListener('click', (e) => {
            e.preventDefault();
            resetPasswordRequestView.style.display = 'none';
            authView.style.display = 'block';
        });

        requestResetBtn.addEventListener('click', async () => {
            const email = document.getElementById('resetEmail').value;
            if (!email) return;
            requestResetBtn.disabled = true;
            try {
                const result = await apiCall('request_password_reset', { email: email });
                showMessage(document.getElementById('reset-request-message'), result.success, 'success');
            } catch (error) {
                showMessage(document.getElementById('reset-request-message'), error.message, 'error');
            } finally {
                requestResetBtn.disabled = false;
            }
        });

        // --- Subscription History Modal ---
        openSubscriptionHistoryModalBtn.addEventListener('click', async () => {
            $('#subscription-history-modal').show();

            // Check if DataTable is already initialized
            if ($.fn.DataTable.isDataTable('#subscription-history-table')) {
                $('#subscription-history-table').DataTable().ajax.reload();
                return;
            }

            $('#subscription-history-table').DataTable({
                destroy: true,
                processing: true,
                ajax: (data, callback, settings) => {
                    apiCall('get_my_subscriptions')
                        .then(res => callback({ data: res }))
                        .catch(err => {
                            console.error("Failed to load subscription history:", err);
                            callback({ data: [] }); // Return empty data on error
                        });
                },
                columns: [
                    { title: "Date", data: "created_at", render: d => new Date(d).toLocaleString() },
                    { title: "Plan", data: "plan_name" },
                    { title: "Amount", data: "amount_paid" },
                    { title: "Payment Method", data: "payment_method_name" },
                    {
                        title: "Status", data: "status", render: function (data) {
                            let color = 'grey';
                            if (data === 'approved') color = 'green';
                            if (data === 'rejected') color = 'red';
                            return `<span style="color: ${color}; font-weight: bold; text-transform: capitalize;">${data}</span>`;
                        }
                    }
                ],
                order: [[0, 'desc']]
            });
        });

        // Add this to the event listeners for your other modals to ensure they also have close functionality
        $('#subscription-history-modal .close-btn').on('click', function () {
            $('#subscription-history-modal').hide();
        });


        // --- VIEW RENDERING ---
        function renderAuthView() {
            authView.style.display = 'block';
            appView.style.display = 'none';
            verificationView.style.display = 'none';
        }

        // MODIFIED: applyBranding now updates landing page elements as well
        function applyBranding(settings) {
            const appName = settings.appName || 'AiParcel';
            const logoUrl = settings.appLogoUrl ? `${settings.appLogoUrl}` : '';

            document.title = `${appName} - Parcel Entry`;

            // Landing Page
            $('#landingLogo').attr('src', logoUrl).toggle(!!logoUrl);
            $('#landingTitle').text(appName);
            $('#footerAppName').text(appName);

            // Auth View
            $('#authLogo').attr('src', logoUrl).toggle(!!logoUrl);
            $('#authTitle').text(`Welcome to ${appName}`);

            // Dashboard View
            $('#dashboardLogo').attr('src', logoUrl).toggle(!!logoUrl);
        }

        async function renderAppView() {
            authView.style.display = 'none';
            verificationView.style.display = 'none';
            appView.style.display = 'block';
            userInfo.textContent = currentUser.displayName || currentUser.email;

            const data = await apiCall('load_user_data');
            userCourierStores = data.stores;
            geminiApiKey = data.geminiApiKey;
            userPermissions = data.permissions || {}; // Store permissions
            helpContent = data.helpContent || '<p>No help guide has been set up by the administrator.</p>';

            applyBranding(data);

            currentUser.lastSelectedStoreId = data.lastSelectedStoreId;

            loadUserStores();
            updateFeatureVisibilityBasedOnPlan();

            await renderPlanStatus();

            currentParserFields = (data.parserSettings !== null && typeof data.parserSettings !== 'undefined')
                ? data.parserSettings  // Use saved settings (even if it's an empty [])
                : [...DEFAULT_PARSER_FIELDS];
            renderParserFields();
        }



        // --- DYNAMIC CONTENT & MODALS ---
        function injectModalContent() {
            $('#store-modal').html(`<div class="modal-content"><div class="modal-header"><h2>Store Management</h2><span class="close-btn">&times;</span></div><div class="store-management"><div class="add-store-container form-group"><h3>Add / Edit Store</h3><input type="hidden" id="editingStoreId"><select id="courierTypeSelector"><option value="steadfast">Steadfast</option><option value="pathao">Pathao</option></select><input type="text" id="storeName" placeholder="Store Name"><div id="steadfast-fields"><input type="password" id="newApiKey" placeholder="Steadfast API Key"><input type="password" id="newSecretKey" placeholder="Steadfast Secret Key"></div><div id="pathao-fields" style="display:none; flex-direction:column; gap:10px;"><input type="text" id="pathaoClientId" placeholder="Pathao Client ID"><input type="text" id="pathaoClientSecret" placeholder="Pathao Client Secret"><input type="text" id="pathaoUsername" placeholder="Pathao Username (Email)"><input type="password" id="pathaoPassword" placeholder="Pathao Password"><input type="number" id="pathaoStoreId" placeholder="Pathao Store ID"></div><button id="addStoreBtn" style="margin-top:10px;">Add Store</button></div><div class="store-list-container"><h3>Your Saved Stores</h3><ul id="storeList"></ul></div></div><div id="store-message" class="message" style="display:none;"></div></div>`);
            $('#settings-modal').html(`<div class="modal-content"><div class="modal-header"><h2>Local Parser Settings</h2><span class="close-btn">&times;</span></div><div id="parserSettings"><h4>Active Fields (Drag to reorder)</h4><ul id="parserFields"></ul><div id="availableFieldsWrapper"><h4>Available Fields</h4><div class="available-fields-container" id="availableFields"></div></div><div class="instructions-bn" style="margin-top:20px; font-size: 14px; line-height: 1.6;"><h4>How to use Parser Settings</h4><ul><li>Arrange the fields above by dragging them into the same order as your pasted text lines.</li><li>Check 'Required' if a line must exist for the parcel to be valid.</li><li>When pasting multiple parcels, separate each one with a blank line.</li></ul></div></div></div>`);
            $('#history-modal').html(`<div class="modal-content"><div class="modal-header"><h2>History</h2><span class="close-btn">&times;</span></div><div class="history-tabs" style="display:flex; border-bottom:1px solid var(--border-color); margin-bottom:15px;"><button id="parseHistoryTabBtn" class="active" style="padding:10px 15px; border:none; background:none; cursor:pointer; font-size:16px; font-weight:500;">Parse History</button><button id="orderHistoryTabBtn" style="padding:10px 15px; border:none; background:none; cursor:pointer; font-size:16px; font-weight:500;">Order History</button></div><div id="parseHistoryContent" class="history-content active" style="max-height:400px; overflow-y:auto;"></div><div id="orderHistoryContent" class="history-content" style="display:none; max-height:400px; overflow-y:auto;"></div></div>`);
            $('#profile-modal').html(`<div class="modal-content"><div class="modal-header"><h2>Profile Settings</h2><span class="close-btn">&times;</span></div><div class="profile-form"><h3>Update Your Profile</h3><div class="form-group" style="gap:5px;"><label>Display Name</label><input type="text" id="updateNameInput" placeholder="Enter your name"><button id="updateNameBtn">Update Name</button></div><hr style="margin: 20px 0;"><div class="form-group" style="gap:5px;"><label>New Password</label><input type="password" id="updatePasswordInput" placeholder="Enter a new password"><button id="updatePasswordBtn">Update Password</button></div></div><div id="profile-message" class="message" style="display:none;"></div></div>`);
            $('.modal .close-btn').on('click', function () { $(this).closest('.modal').hide(); });
            $('.modal').on('click', function (e) { if (e.target === this) $(this).hide(); });
        }
        injectModalContent();

        // --- CORE APP FUNCTIONS ---
        async function renderPlanStatus() {
            try {
                const status = await apiCall('get_subscription_data');
                isPremiumUser = status.plan_id > 1; // Update global premium status
                // updateAIButtonState(); // Re-evaluate button state based on new status
                updateFeatureVisibilityBasedOnPlan();

                let usageHTML = '';
                if (status.order_limit_monthly) {
                    const percentage = Math.min((status.monthly_order_count / status.order_limit_monthly) * 100, 100);
                    usageHTML = `<p>Orders this cycle: <strong>${status.monthly_order_count} / ${status.order_limit_monthly}</strong></p>
                               <div class="progress-bar"><div class="progress-bar-inner" style="width:${percentage}%"></div></div>`;
                } else if (status.order_limit_daily) {
                    usageHTML = `<p>Orders today: <strong>${status.daily_order_count} / ${status.order_limit_daily}</strong></p>`;
                }
                planStatusView.innerHTML = `<h3>Current Plan: <strong>${status.plan_name}</strong></h3>${usageHTML}<p>Expires on: <strong>${status.plan_expiry_date ? new Date(status.plan_expiry_date).toLocaleDateString() : 'N/A'}</strong></p>`;
                planStatusView.style.display = 'block';
            } catch (e) {
                planStatusView.innerHTML = `<p class="error">${e.message}</p>`;
                planStatusView.style.display = 'block';
            }
        }

        function updateAIButtonState() {
            if (isPremiumUser && geminiApiKey) {
                parseWithAIBtn.disabled = false;
                parseWithAIBtn.textContent = "Parse with AI";
            } else {
                parseWithAIBtn.disabled = true;
                parseWithAIBtn.textContent = "Parse with AI 🔒";
                parseWithAIBtn.title = "This is a premium feature.";
            }
        }

        function updateFeatureVisibilityBasedOnPlan() {
            // Correctly toggle "Parse with AI" button based on plan
            const canParseAI = userPermissions.can_parse_ai && geminiApiKey;
            $('#parseWithAIBtn').toggle(canParseAI);

            // Toggle "Parse & Autocomplete" button
            $('#parseAndAutocompleteBtn').toggle(userPermissions.can_autocomplete);

            // Toggle "Check All Risks" button
            $('#checkAllRiskBtn').toggle(userPermissions.can_check_risk);

            // Note: The individual check risk and correct address buttons are handled in the createParcelCard function
        }

        function updateFeatureVisibility(settings) {
            $('#parseWithAIBtn').toggle(settings.showAiParseButton !== '0');
            $('#parseAndAutocompleteBtn').toggle(settings.showAutocompleteButton !== '0');
        }

        function loadUserStores() {
            $('#storeList, #storeSelector').empty();
            if (Object.keys(userCourierStores).length === 0) {
                $('#storeList').html('<li>No stores found.</li>');
                $('#storeSelector').html(`<option value="">Please add a store first</option>`);
                return;
            }
            for (const id in userCourierStores) {
                const store = userCourierStores[id];
                $('#storeList').append(`<li><span>${store.storeName} <span class="courier-badge ${store.courierType}">${store.courierType}</span></span><div class="store-actions"><button class="edit-store-btn" data-id="${id}">Edit</button><button class="delete-store-btn" data-id="${id}">&times;</button></div></li>`);
                $('#storeSelector').append(`<option value="${id}">${store.storeName}</option>`);
            }
            if (currentUser.lastSelectedStoreId && userCourierStores[currentUser.lastSelectedStoreId]) {
                storeSelector.value = currentUser.lastSelectedStoreId;
            }
            updateCreateOrderButtonText();
        }

        function updateCreateOrderButtonText() {
            const selectedStoreId = storeSelector.value;
            if (selectedStoreId && userCourierStores[selectedStoreId]) {
                const courierType = userCourierStores[selectedStoreId].courierType;
                createOrderBtn.textContent = `Create ${courierType.charAt(0).toUpperCase() + courierType.slice(1)} Order(s)`;
            } else {
                createOrderBtn.textContent = 'Create Order(s)';
            }
        }

        function updateSummary() {
            const parcelCards = $('.parcel-card');
            let totalCod = 0;
            parcelCards.each(function () {
                totalCod += Number(JSON.parse($(this).data('orderData')).amount) || 0;
            });
            parcelCountSpan.textContent = parcelCards.length;
            totalCodSpan.textContent = `${totalCod} BDT`;
        }



        function createParcelCard(parcelData) {
            // --- NEW: Handle both parsers' field names ---
            const customerName = parcelData.customerName || 'N/A';
            // Use 'phone' from settings-parser OR 'customerPhone' from auto-parser
            const phone = parcelData.phone || parcelData.customerPhone || 'N/A';
            // Use 'address' from settings-parser OR 'customerAddress' from auto-parser
            const address = parcelData.address || parcelData.customerAddress || 'N/A';
            const orderId = parcelData.orderId || 'N/A';
            const amount = parcelData.amount || 0;
            const productName = parcelData.productName || parcelData.item_description || 'N/A';
            const note = parcelData.note || 'N/A';
            // --- END NEW ---

            // Use the original parcelData for the data-attribute
            const card = $(`<div class="parcel-card"></div>`).data('orderData', JSON.stringify(parcelData));

            // Use the unified phone variable for checking
            const phoneForCheck = (phone || '').replace(/\s+/g, '');
            const isPhoneValid = /^01[3-9]\d{8}$/.test(phoneForCheck);

            const checkRiskDisabled = !isPhoneValid || !userPermissions.can_check_risk;
            const checkRiskTitle = !userPermissions.can_check_risk ? 'This is a premium feature.' : 'Check customer risk';

            const correctAddressDisabled = !userPermissions.can_correct_address;
            const correctAddressTitle = !userPermissions.can_correct_address ? 'This is a premium feature.' : 'Correct Address with AI';

            // Use the new unified variables
            card.html(`
                <div class="details">
                    <strong>${customerName}</strong> (${phone})<br>
                    Address: <span class="address-text">${address}</span><br>
                    OrderID: ${orderId} | COD: <strong>${amount} BDT</strong> | Item: ${productName}
                </div>
                <div class="parcel-actions">
                    <button class="check-risk-btn" data-phone="${phoneForCheck}" ${checkRiskDisabled ? 'disabled' : ''} title="${checkRiskTitle}">Check Risk</button>
                    <button class="correct-address-btn" ${correctAddressDisabled ? 'disabled' : ''} title="${correctAddressTitle}">Correct Address 🤖 AI</button>
                    <button class="remove-btn">&times;</button>
                </div>
                <div class="fraud-results-container" style="display: none;"></div>
            `);
            parsedDataContainer.appendChild(card[0]);
        }

        // --- EVENT LISTENERS ---
        // ... (other listeners)

        $('#parsedDataContainer').on('click', '.correct-address-btn', function () {
            correctSingleAddress(this);
        });

        async function correctSingleAddress(buttonElement) {
            const $button = $(buttonElement);
            const $card = $button.closest('.parcel-card');
            const $addressTextSpan = $card.find('.address-text');

            let parcelData = JSON.parse($card.data('orderData'));

            // Handles both local parsing ('address') and AI parsing ('recipient_address')
            let addressKey;
            if (parcelData.address) {
                addressKey = 'address';
            } else if (parcelData.customerAddress) {
                addressKey = 'customerAddress';
            } else if (parcelData.recipient_address) {
                addressKey = 'recipient_address';
            }

            const originalAddress = addressKey ? parcelData[addressKey] : null;

            if (!originalAddress) {
                alert('No address to correct.');
                return;
            }

            $button.prop('disabled', true).text('Correcting...');

            try {
                const result = await apiCall('correct_address_ai', { address: originalAddress });

                if (result.corrected_address) {
                    // Update parcelData object
                    parcelData[addressKey] = result.corrected_address;

                    // Update the data attribute on the card
                    $card.data('orderData', JSON.stringify(parcelData));

                    // Update the displayed text
                    $addressTextSpan.text(result.corrected_address);

                    $button.text('Corrected ✔️');
                } else {
                    throw new Error("AI did not return a corrected address.");
                }

            } catch (error) {
                alert(`Error correcting address: ${error.message}`);
                $button.text('Correction Failed');
            } finally {
                setTimeout(() => {
                    $button.prop('disabled', false).html('Correct Address 🤖');
                }, 3000);
            }
        }

        function identifyAndParseOrder(orderText) {

            // 1. Define rules with confidence scores
            const fieldRules = [
                // --- HIGH CONFIDENCE (90-100) ---
                { field: 'customerPhone', regex: /^(\+88\s*)?01[3-9](\d[\s-]*){8}$/, score: 100 }, // Catches +88 01... and 01...-...
                { field: 'amount', regex: /^\s*(BDT|৳|Tk|Cash|\$|€|£)?\s*\d+([\.,]\d{1,2})?(\/-)?\s*(BDT|৳|Tk)?\s*$/i, score: 90 }, // Catches currency, decimals, /-
                { field: 'orderId', regex: /(order|id|ref)[\s:-]+[A-Za-z0-9-]+/i, score: 90 }, // Catches "Order: 123"

                // --- MEDIUM-HIGH CONFIDENCE (80-89) ---
                { field: 'orderId', regex: /^[A-Za-z]+[A-Za-z0-9-]{4,40}$/, score: 85 }, // Catches "ORD-123"
                { field: 'orderId', regex: /^[A-Za-z0-9-]{12,40}$/, score: 80 }, // Catches long numeric/alpha IDs

                // --- MEDIUM CONFIDENCE (50-79) ---
                { field: 'customerName', regex: /(^মোঃ|Md\.|Mr\.|Mrs\.|Miss)/i, score: 60 },
                { field: 'customerAddress', regex: /(House|Road|Block|Sector|Holding|Village|Para)[\s:-]+\S+/i, score: 55 },
                { field: 'productName', regex: /(\d[\sx\*].*[\u0980-\u09FFa-z])|([\u0980-\u09FFa-z].*\d)|(পিস|ইঞ্ছি|kg|gm|pce|টি|pc)/i, score: 50 }, // Catches "1x" or "50 pc"
                { field: 'customerAddress', regex: /(সদ|রোড|গ্রাম|থানা|জেলা|বাসা|হোল্ডিং|union|dist|upazila|road|house)/i, score: 50 },

                // --- LOW CONFIDENCE (20-49) ---
                { field: 'customerAddress', regex: /\d+.*,/i, score: 45 }, // Catches "House 12, Road 5..."
                { field: 'note', regex: /(deliver|fast|quick|call|please|tomorrow|urgent|ASAP|^\.$)/i, score: 40 },
                { field: 'customerAddress', regex: /[A-Za-z\u0980-\u09FF].*[,].*/i, score: 30 }, // Catches addresses with commas
                { field: 'customerName', regex: /^[A-Za-z\u0980-\u09FF\s\.-]+$/i, score: 20 }, // Fallback for name, allows dots
            ];

            // --- Initialize ---
            const parsedData = {
                orderId: null,
                customerName: null,
                productName: null,
                amount: null,
                customerAddress: null,
                customerPhone: null,
                note: null
            };

            let lines = orderText.split('\n')
                .map(line => line.trim())
                .filter(line => line.length > 0);

            // 2. Score all lines against all rules
            let lineScores = [];
            for (let i = 0; i < lines.length; i++) {
                const line = lines[i];
                for (const rule of fieldRules) {

                    // --- MODIFICATION: Normalize line *before* testing number-based rules ---
                    let lineToTest = line;
                    if (rule.field === 'customerPhone' || rule.field === 'amount') {
                        // We test a normalized version (e.g., "017...")
                        // This uses your existing global normalizePhoneNumber function
                        lineToTest = normalizePhoneNumber(line);
                    }

                    if (rule.regex.test(lineToTest)) {
                        // --- END OF MODIFICATION ---

                        lineScores.push({
                            lineIndex: i,
                            field: rule.field,
                            score: rule.score,
                            line: line // --- We still store the *original* line
                        });
                    }
                }
            }

            // 3. Sort by highest score first
            lineScores.sort((a, b) => b.score - a.score);

            // 4. Assign best, unique matches
            const assignedLines = new Set();
            const assignedFields = new Set();

            for (const scoreInfo of lineScores) {
                if (!assignedLines.has(scoreInfo.lineIndex) && !assignedFields.has(scoreInfo.field)) {

                    // --- Apply cleaning logic based on field ---
                    if (scoreInfo.field === 'amount') {
                        const cleanAmount = scoreInfo.line.replace(/[^0-9\.]/g, ''); // Remove currency, commas, etc.
                        parsedData.amount = parseFloat(cleanAmount); // Use parseFloat for decimals
                    } else if (scoreInfo.field === 'customerPhone') {
                        const cleanPhone = scoreInfo.line.replace(/[\s-]/g, ''); // Remove spaces and dashes
                        // Also apply normalization
                        parsedData.customerPhone = normalizePhoneNumber(cleanPhone);
                    } else {
                        // Assign all other fields as-is
                        parsedData[scoreInfo.field] = scoreInfo.line;
                    }
                    // --- End of cleaning logic ---

                    assignedLines.add(scoreInfo.lineIndex);
                    assignedFields.add(scoreInfo.field);
                }
            }

            // 5. Handle leftovers (Fallbacks for unassigned lines)
            for (let i = 0; i < lines.length; i++) {
                if (!assignedLines.has(i)) {
                    const line = lines[i];

                    // Check empty slots in order of likelihood
                    if (!parsedData.customerAddress) {
                        parsedData.customerAddress = line;
                    } else if (!parsedData.productName) {
                        parsedData.productName = line;
                    } else {
                        // If all else fails, add to note
                        parsedData.note = (parsedData.note ? parsedData.note + '\n' : '') + line;
                    }
                }
            }

            return parsedData;
        }

        function createParcelCardAI(parcelData) {
            // Ensure parsedDataContainer exists
            const parsedDataContainer = document.getElementById('parsedDataContainer');
            if (!parsedDataContainer) return;

            // Clean phone number
            const phoneForCheck = (parcelData.recipient_phone || '').replace(/\s+/g, '');
            const isPhoneValid = /^01[3-9]\d{8}$/.test(phoneForCheck);

            // Check risk button state
            const checkRiskDisabled = !isPhoneValid || !isPremiumUser;
            const checkRiskTitle = !isPremiumUser ? 'This is a premium feature.' : 'Check customer risk';

            // Create card element
            const card = document.createElement('div');
            card.className = 'parcel-card';
            card.dataset.orderData = JSON.stringify(parcelData);

            card.innerHTML = `
        <div class="details">
            <strong>${parcelData.recipient_name || 'N/A'}</strong> (${parcelData.recipient_phone || 'N/A'})<br>
            Address: ${parcelData.recipient_address || 'N/A'}<br>
            OrderID: ${parcelData.order_id || 'N/A'} | COD: <strong>${parcelData.cod_amount || 0} BDT</strong> | Item: ${parcelData.item_description || 'N/A'}
        </div>
        <div class="parcel-actions">
            <button class="check-risk-btn" data-phone="${phoneForCheck}" ${checkRiskDisabled ? 'disabled' : ''} title="${checkRiskTitle}">Check Risk</button>
            <button class="remove-btn">&times;</button>
        </div>
        <div class="fraud-results-container" style="display: none;"></div>
    `;

            // Append to container
            parsedDataContainer.appendChild(card);
        }


        async function checkFraudRisk(buttonElement) {
            const phoneNumber = buttonElement.dataset.phone;
            if (!phoneNumber) return;

            const card = buttonElement.closest('.parcel-card');
            const resultsContainer = card.querySelector('.fraud-results-container');

            buttonElement.disabled = true;
            buttonElement.textContent = 'Checking...';
            resultsContainer.style.display = 'block';
            resultsContainer.innerHTML = '<div class="loader" style="display:block; margin: 10px auto; height: 20px; width: 20px;"></div>';

            try {
                const data = await apiCall('check_fraud_risk', { phone: phoneNumber });

                // --- 1. Calculate Total Delivery Success Ratio ---
                let totalOrders = 0;
                let totalDelivered = 0;
                data.forEach(courier => {
                    totalOrders += parseInt(courier.orders) || 0;
                    totalDelivered += parseInt(courier.delivered) || 0;
                });

                const successRatio = totalOrders > 0 ? ((totalDelivered / totalOrders) * 100).toFixed(1) : 0;

                let ratioColor = '#27ae60'; // Green for good ratio
                if (successRatio < 80) ratioColor = '#f39c12'; // Yellow for medium ratio
                if (successRatio < 60) ratioColor = '#c0392b'; // Red for bad ratio

                // --- 2. Build the detailed table HTML (for the hidden view) ---
                let tableHTML = `<table class="fraud-results-table"><thead><tr><th>Courier</th><th>Orders</th><th>Delivered</th><th>Cancelled</th><th>Cancel Rate</th></tr></thead><tbody>`;
                data.forEach(courier => {
                    tableHTML += `<tr><td>${courier.courier}</td><td>${courier.orders}</td><td>${courier.delivered}</td><td>${courier.cancelled}</td><td>${courier.cancel_rate}</td></tr>`;
                });
                tableHTML += '</tbody></table>';

                // --- 3. Create the new combined HTML structure ---
                const uniqueId = `details-${phoneNumber}-${Date.now()}`; // Unique ID for the toggleable div
                const finalHTML = `
                    <div style="padding: 8px; border: 1px solid #ddd; border-radius: 5px; background: #f9f9f9;">
                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                            <span style="font-weight: 600; font-size: 13px;">
                                Delivery Success Ratio: 
                                <strong style="color: ${ratioColor}; font-size: 15px;">${successRatio}%</strong>
                            </span>
                            <button class="toggle-details-btn btn-secondary btn-sm" data-target="#${uniqueId}" style="white-space: nowrap;">Show Details</button>
                        </div>
                        <div id="${uniqueId}" style="display: none; margin-top: 10px; border-top: 1px solid #eee; padding-top: 10px;">
                            ${tableHTML}
                        </div>
                    </div>
                `;

                resultsContainer.innerHTML = finalHTML;
                buttonElement.textContent = 'Checked';

                // --- 4. Attach Event Listener to the new "Show Details" button ---
                resultsContainer.querySelector('.toggle-details-btn').addEventListener('click', function () {
                    const targetSelector = this.getAttribute('data-target');
                    const detailsDiv = resultsContainer.querySelector(targetSelector);
                    if (detailsDiv) {
                        const isHidden = detailsDiv.style.display === 'none';
                        detailsDiv.style.display = isHidden ? 'block' : 'none';
                        this.textContent = isHidden ? 'Hide Details' : 'Show Details';
                    }
                });

            } catch (error) {
                resultsContainer.innerHTML = `<p class="message error" style="display:block; text-align:left; padding: 8px;">Error: ${error.message}</p>`;
                buttonElement.textContent = 'Check Failed';
            }
        }

        // --- EVENT LISTENERS ---
        // --- REPLACED: createOrderBtn listener to normalize data before sending ---
        createOrderBtn.addEventListener('click', async () => {
            const storeId = storeSelector.value;
            if (!storeId || !userCourierStores[storeId]) return alert('Please select a valid store.');

            // --- START OF MODIFICATION ---
            // Get all orders and normalize them to the format your API expects
            const orders = $('.parcel-card').map((i, el) => {
                const parcelData = JSON.parse($(el).data('orderData'));

                // Create a new, clean order object
                // Map all possible field names (from all 3 parsers) to the "Settings" format
                const cleanOrder = {
                    customerName: parcelData.customerName || parcelData.recipient_name,
                    phone: parcelData.phone || parcelData.customerPhone || parcelData.recipient_phone,
                    address: parcelData.address || parcelData.customerAddress || parcelData.recipient_address,
                    amount: parcelData.amount || parcelData.cod_amount,
                    productName: parcelData.productName || parcelData.item_description,
                    note: parcelData.note,
                    orderId: parcelData.orderId || parcelData.order_id
                };

                // Filter out any null/undefined fields to send clean data
                Object.keys(cleanOrder).forEach(key => {
                    if (cleanOrder[key] === null || typeof cleanOrder[key] === 'undefined') {
                        delete cleanOrder[key];
                    }
                });

                return cleanOrder; // Return the new, normalized object

            }).get();
            // --- END OF MODIFICATION ---

            if (orders.length === 0) return alert('No parcels to create.');

            loader.style.display = 'block'; createOrderBtn.disabled = true;
            try {
                const responseData = await apiCall('create_order', { storeId, orders });
                // apiResponseDiv.textContent = JSON.stringify(responseData, null, 2);
                // apiResponseDiv.className = 'success message';
                // apiResponseDiv.style.display = 'block';
                displayApiResponse(responseData);
                await renderPlanStatus();
            } catch (error) {
                // apiResponseDiv.textContent = `Error: ${error.message}`;
                // apiResponseDiv.className = 'error message';
                // apiResponseDiv.style.display = 'block';
                // MODIFIED: Pass the error object to displayApiResponse
                displayApiResponse({ error: error.message, status: 'error' });
            } finally {
                loader.style.display = 'none';
                createOrderBtn.disabled = false;
            }
        });

        $('#parsedDataContainer').on('click', '.remove-btn', function () {
            $(this).closest('.parcel-card').remove();
            updateSummary();
        }).on('click', '.check-risk-btn', function () {
            checkFraudRisk(this);
        });

        checkAllRiskBtn.addEventListener('click', async () => {
            const allCheckButtons = parsedDataContainer.querySelectorAll('.check-risk-btn:not(:disabled)');
            if (allCheckButtons.length === 0) {
                alert('No parcels to check or all risks have been checked already.');
                return;
            }
            for (const button of allCheckButtons) {
                checkFraudRisk(button);
                await new Promise(res => setTimeout(res, 500)); // Delay
            }
        });

        storeSelector.addEventListener('change', updateCreateOrderButtonText);

        // --- MODAL & UI EVENT LISTENERS ---
        openStoreModalBtn.addEventListener('click', () => $('#store-modal').show());
        openSettingsModalBtn.addEventListener('click', () => $('#settings-modal').show());
        openHistoryModalBtn.addEventListener('click', () => { $('#history-modal').show(); $('#parseHistoryTabBtn').trigger('click'); });
        openProfileModalBtn.addEventListener('click', () => {
            $('#updateNameInput').val(currentUser.displayName || '');
            $('#updatePasswordInput').val('');
            $('#profile-modal').show();
        });

        // Store Modal Logic
        $('#store-modal').on('click', '#addStoreBtn', async function () {
            const payload = {
                editingId: $('#editingStoreId').val() || null,
                storeName: $('#storeName').val(),
                courierType: $('#courierTypeSelector').val(),
                credentials: $('#courierTypeSelector').val() === 'pathao' ? {
                    clientId: $('#pathaoClientId').val(), clientSecret: $('#pathaoClientSecret').val(),
                    username: $('#pathaoUsername').val(), password: $('#pathaoPassword').val(), storeId: $('#pathaoStoreId').val()
                } : { apiKey: $('#newApiKey').val(), secretKey: $('#newSecretKey').val() }
            };
            try {
                await apiCall('add_or_update_store', payload);
                const data = await apiCall('load_user_data');
                userCourierStores = data.stores; loadUserStores();
                $('#storeName, #newApiKey, #newSecretKey, #pathaoClientId, #pathaoClientSecret, #pathaoUsername, #pathaoPassword, #pathaoStoreId').val('');
                $('#editingStoreId').val(''); $(this).text('Add Store');
                showMessage(document.getElementById('store-message'), 'Store saved.', 'success');
            } catch (e) { showMessage(document.getElementById('store-message'), e.message, 'error'); }
        }).on('click', '.edit-store-btn', function () {
            const store = userCourierStores[$(this).data('id')];
            $('#editingStoreId').val($(this).data('id'));
            $('#storeName').val(store.storeName);
            $('#courierTypeSelector').val(store.courierType).trigger('change');
            if (store.courierType === 'pathao') {
                $('#pathaoClientId').val(store.clientId); $('#pathaoClientSecret').val(store.clientSecret);
                $('#pathaoUsername').val(store.username); $('#pathaoPassword').val(store.password); $('#pathaoStoreId').val(store.storeId);
            } else {
                $('#newApiKey').val(store.apiKey); $('#newSecretKey').val(store.secretKey);
            }
            $('#addStoreBtn').text('Update Store');
        }).on('click', '.delete-store-btn', async function () {
            if (confirm('Are you sure?')) {
                await apiCall('delete_store', { id: $(this).data('id') });
                const data = await apiCall('load_user_data');
                userCourierStores = data.stores; loadUserStores();
            }
        }).on('change', '#courierTypeSelector', function () {
            $('#pathao-fields').toggle($(this).val() === 'pathao');
            $('#steadfast-fields').toggle($(this).val() !== 'pathao');
        });

        // History Modal Logic
        $('#history-modal').on('click', '.history-tabs button', function () {
            $(this).addClass('active').siblings().removeClass('active');
            const target = $(this).attr('id').replace('TabBtn', 'Content');
            $(`#${target}`).show().siblings('.history-content').hide();
            if (target === 'parseHistoryContent') loadHistory('parses', '#parseHistoryContent');
            else loadHistory('orders', '#orderHistoryContent');
        });

        async function loadHistory(type, container) {
            $(container).html('Loading...');
            try {
                const history = await apiCall('get_history', { type });
                if (!history || history.length === 0) {
                    $(container).html("No history found."); return;
                }
                $(container).empty();
                history.forEach(item => {
                    const date = new Date(item.timestamp).toLocaleString();
                    let title = '';
                    if (type === 'parses') title = `Method: ${item.method} | ${JSON.parse(item.data).length} items`;
                    else title = `Store: ${userCourierStores[item.store_id]?.storeName || 'N/A'}`;

                    $(container).append(`<div class="history-item"><div><p>${date}</p><p><strong>${title}</strong></p></div><button class="details-btn" data-type="${type}" data-item='${JSON.stringify(item)}'>Details</button></div>`);
                });
            } catch (e) { $(container).html(`<p class="error">Could not load history.</p>`); }
        }

        $('#history-modal').on('click', '.details-btn', function () {
            const item = JSON.parse($(this).attr('data-item'));
            const content = $(this).data('type') === 'parses' ? JSON.parse(item.data) : { Request: JSON.parse(item.request_payload), Response: JSON.parse(item.api_response) };
            $('#details-title').text('Details');
            $('#details-content').text(JSON.stringify(content, null, 2));
            $('#details-modal').show();
        });

        // Profile Modal Logic
        $('#profile-modal').on('click', '#updateNameBtn', async () => {
            const newName = $('#updateNameInput').val().trim();
            if (newName) {
                try {
                    await apiCall('update_profile', { displayName: newName });
                    userInfo.textContent = newName; currentUser.displayName = newName;
                    showMessage(document.getElementById('profile-message'), 'Name updated!', 'success');
                } catch (e) { showMessage(document.getElementById('profile-message'), e.message, 'error'); }
            }
        }).on('click', '#updatePasswordBtn', async () => {
            const newPassword = $('#updatePasswordInput').val();
            if (newPassword.length >= 6) {
                try {
                    await apiCall('update_profile', { password: newPassword });
                    $('#updatePasswordInput').val('');
                    showMessage(document.getElementById('profile-message'), 'Password updated!', 'success');
                } catch (e) { showMessage(document.getElementById('profile-message'), e.message, 'error'); }
            } else { showMessage(document.getElementById('profile-message'), 'Password must be at least 6 characters.', 'error'); }
        });

        // Upgrade Modal Logic
        let selectedPlan = null;
        let availablePlans = [];
        let selectedMethod = null;
        let availableMethods = [];
        // In index.php <script>

        openUpgradeModalBtn.addEventListener('click', async () => {
            $('#upgrade-modal').show();
            showUpgradeStep(1);
            $('#upgrade-message').hide();
            $('#sender-number, #transaction-id').val('');

            const $plansContainer = $('#plans-container').html('Loading plans...');
            try {
                const plans = await apiCall('get_available_plans');
                availablePlans = plans; // Store plans globally
                $plansContainer.empty();
                if (plans.length === 0) {
                    $plansContainer.html('<p>No upgrade plans are currently available.</p>');
                    return;
                }
                plans.forEach(plan => {
                    const planCard = `
                        <div class="plan-option" data-plan-id="${plan.id}">
                            <h4>${plan.name} - ${plan.price} BDT</h4>
                            <p>${plan.description}</p>
                            <div class="plan-option-details">
                                <span>🗓️ Validity: <strong>${plan.validity_days} days</strong></span>
                                <span>📦 Monthly Limit: <strong>${plan.order_limit_monthly || 'Unlimited'}</strong></span>
                                <span>📅 Daily Limit: <strong>${plan.order_limit_daily || 'Unlimited'}</strong></span>
                            </div>
                        </div>`;
                    $plansContainer.append(planCard);
                });
            } catch (e) { $plansContainer.html(`<p class="error">Could not load plans.</p>`); }
        });

        // In index.php <script>

        $('#plans-container').on('click', '.plan-option', async function () {
            const planId = $(this).data('plan-id');
            selectedPlan = availablePlans.find(p => p.id == planId); // Store the selected plan object
            if (!selectedPlan) return;

            showUpgradeStep(2);

            const $paymentContainer = $('#payment-methods-container').html('Loading...');
            try {
                const methods = await apiCall('get_active_payment_methods');
                availableMethods = methods; // Store methods globally
                $paymentContainer.empty();
                if (methods.length === 0) {
                    $paymentContainer.html('<p>No payment methods available.</p>');
                    return;
                }
                methods.forEach(method => $paymentContainer.append(`<div class="plan-option" data-method-id="${method.id}" data-instructions="${method.instructions}"><h4 style="margin:0;">${method.name}</h4><pre>${method.account_details}</pre></div>`));
            } catch (e) { $paymentContainer.html(`<p class="error">Could not load payment methods.</p>`); }
        });

        // In index.php <script>

        $('#payment-methods-container').on('click', '.plan-option', function () {
            const methodId = $(this).data('method-id');
            selectedMethod = availableMethods.find(m => m.id == methodId); // Store the selected method object
            if (!selectedMethod) return;

            // Populate the summary details in Step 3
            if (selectedPlan) {
                $('#summary-plan-name').text(selectedPlan.name);
                $('#summary-plan-price').text(selectedPlan.price);
            }
            $('#summary-payment-details').text(selectedMethod.account_details);

            // Populate the instructions
            $('#payment-instructions').html($(this).data('instructions').replace(/\n/g, '<br>') || 'Please enter your payment details below.');

            showUpgradeStep(3);
        });

        // --- Upgrade Modal Logic ---
        // let selectedPlanId = null, selectedMethodId = null;

        function showUpgradeStep(step) {
            $('#upgrade-modal .upgrade-step').hide();
            $(`#upgrade-step-${step}`).show();
        }

        openUpgradeModalBtn.addEventListener('click', async () => {
            // Reset the modal to the first step every time it's opened
            $('#upgrade-modal').show();
            showUpgradeStep(1);
            $('#upgrade-message').hide();
            $('#sender-number, #transaction-id').val('');

            const $plansContainer = $('#plans-container').html('Loading plans...');
            try {
                const plans = await apiCall('get_available_plans');
                $plansContainer.empty();
                if (plans.length === 0) {
                    $plansContainer.html('<p>No upgrade plans are currently available.</p>');
                    return;
                }
                // Render plans with all details
                plans.forEach(plan => {
                    const planCard = `
                        <div class="plan-option" data-plan-id="${plan.id}">
                            <h4>${plan.name} - ${plan.price} BDT</h4>
                            <p>${plan.description}</p>
                            <div class="plan-option-details">
                                <span>🗓️ Validity: <strong>${plan.validity_days} days</strong></span>
                                <span>📦 Monthly Limit: <strong>${plan.order_limit_monthly || 'Unlimited'}</strong></span>
                                <span>📅 Daily Limit: <strong>${plan.order_limit_daily || 'Unlimited'}</strong></span>
                            </div>
                        </div>`;
                    $plansContainer.append(planCard);
                });
            } catch (e) { $plansContainer.html(`<p class="error">Could not load plans.</p>`); }
        });

        // Event handler for clicking a plan
        $('#plans-container').on('click', '.plan-option', async function () {
            selectedPlanId = $(this).data('plan-id');
            showUpgradeStep(2);

            const $paymentContainer = $('#payment-methods-container').html('Loading...');
            try {
                const methods = await apiCall('get_active_payment_methods');
                $paymentContainer.empty();
                if (methods.length === 0) {
                    $paymentContainer.html('<p>No payment methods available.</p>');
                    return;
                }
                methods.forEach(method => $paymentContainer.append(`<div class="plan-option" data-method-id="${method.id}" data-instructions="${method.instructions}"><h4 style="margin:0;">${method.name}</h4><pre>${method.account_details}</pre></div>`));
            } catch (e) { $paymentContainer.html(`<p class="error">Could not load payment methods.</p>`); }
        });

        // Event handler for clicking a payment method
        $('#payment-methods-container').on('click', '.plan-option', function () {
            selectedMethodId = $(this).data('method-id');
            $('#payment-instructions').html($(this).data('instructions').replace(/\n/g, '<br>') || 'Please enter your payment details below.');
            showUpgradeStep(3);
        });

        // Event handler for the "Back" buttons
        $('#upgrade-modal').on('click', '.btn-back', function () {
            const targetStep = $(this).data('target-step');
            showUpgradeStep(targetStep);
        });

        // Event handler for the final submission
        $('#submit-payment-btn').on('click', async function () {
            const senderNumber = $('#sender-number').val().trim();
            const transactionId = $('#transaction-id').val().trim();
            if (!selectedPlanId || !selectedMethodId || !senderNumber || !transactionId) {
                return showMessage(document.getElementById('upgrade-message'), 'Please fill all fields.', 'error');
            }

            const $button = $(this);
            const $loader = $button.find('.loader');

            $button.prop('disabled', true);
            $loader.show();

            try {
                const result = await apiCall('submit_purchase_request', { planId: selectedPlanId, methodId: selectedMethodId, senderNumber, transactionId });
                showMessage(document.getElementById('upgrade-message'), result.message, 'success', 8000);
                // --- ADD THIS BLOCK FOR GTM ---
                // We must find the selectedPlan object to get its details
                const planId = selectedPlanId; // from the outer scope
                const selectedPlan = availablePlans.find(p => p.id == planId); // 'availablePlans' is your global array

                if (selectedPlan && window.dataLayer) {
                    window.dataLayer.push({
                        event: 'purchase',
                        currency: 'BDT',
                        value: selectedPlan.price,
                        items: [{
                            item_id: selectedPlan.id,
                            item_name: selectedPlan.name,
                            price: selectedPlan.price,
                            quantity: 1
                        }]
                    });
                }
                // --- END OF GTM CODE ---
                setTimeout(() => $('#upgrade-modal').hide(), 4000);
            } catch (e) {
                showMessage(document.getElementById('upgrade-message'), e.message, 'error');
            } finally {
                $button.prop('disabled', false);
                $loader.hide();
            }
        });

        // --- PARSER SETTINGS FUNCTIONS ---
        // MODIFIED: Saves settings to the database via API
        async function saveParserSettings() {
            try {
                // Save the current state of the global variable
                await apiCall('save_parser_settings', { settings: currentParserFields });
                // console.log("Parser settings saved to DB.");
            } catch (error) {
                console.error("Failed to save parser settings:", error);
                showMessage(authMessage, 'Failed to save parser settings.', 'error');
            }
        }

        function renderParserFields() {
            const parserFieldsContainer = document.getElementById('parserFields');
            const availableFieldsContainer = document.getElementById('availableFields');
            if (!parserFieldsContainer || !availableFieldsContainer) return;

            parserFieldsContainer.innerHTML = '';
            availableFieldsContainer.innerHTML = '';

            currentParserFields.forEach(field => {
                const li = document.createElement('li');
                li.dataset.id = field.id;
                li.draggable = true;
                li.innerHTML = `<span>${field.label}</span><div class="field-controls"><label><input type="checkbox" ${field.required ? 'checked' : ''}> Required</label><button class="delete-field-btn">&times;</button></div>`;
                li.querySelector('input[type="checkbox"]').addEventListener('change', (e) => {
                    // 1. Update the global variable
                    const fieldId = e.target.closest('li').dataset.id;
                    const field = currentParserFields.find(f => f.id === fieldId);
                    if (field) {
                        field.required = e.target.checked;
                    }
                    // 2. Save the updated global variable
                    saveParserSettings();
                });
                li.querySelector('.delete-field-btn').addEventListener('click', () => {
                    currentParserFields = currentParserFields.filter(f => f.id !== field.id);
                    saveParserSettings();
                    renderParserFields();

                });
                parserFieldsContainer.appendChild(li);
            });

            const activeFieldIds = new Set(currentParserFields.map(f => f.id));
            const availableFields = DEFAULT_PARSER_FIELDS.filter(df => !activeFieldIds.has(df.id));
            availableFields.forEach(field => {
                const tile = document.createElement('button');
                tile.className = 'available-field-tile';
                tile.textContent = field.label;
                tile.dataset.id = field.id;
                tile.addEventListener('click', () => {
                    const fieldToAdd = DEFAULT_PARSER_FIELDS.find(f => f.id === field.id);
                    if (fieldToAdd) {
                        currentParserFields.push(fieldToAdd);
                        saveParserSettings();
                        renderParserFields();

                    }
                });
                availableFieldsContainer.appendChild(tile);
            });
            setupDragAndDrop();
            updateRawTextPlaceholder();
            document.getElementById('autoParseToggle').addEventListener('change', () => {
                updateRawTextPlaceholder();
            });
        }


        // --- MODIFIED: New function to update the textarea placeholder dynamically ---
        function updateRawTextPlaceholder() {
            const rawTextInput = document.getElementById('rawText');
            if (!rawTextInput) return; // Make sure the element exists

            // --- NEW: Check the state of the auto-parse toggle ---
            const useAutoParsing = document.getElementById('autoParseToggle').checked;
            let placeholderText = "";

            if (useAutoParsing) {
                // --- Placeholder text for Smart Auto-Parsing ---
                placeholderText = [
                    "Smart Auto-Parsing চালু আছে। তুমি যেকোনো ক্রমে ফিল্ড পেস্ট করতে পারো।",
                    "Example:",
                    "Customer Name",
                    "01xxxxxxxxx",
                    "Product Name",
                    "500",
                    "Full Address",
                    "Note (Optional)"
                ].join('\n');

            } else {
                // --- Original placeholder text based on Parser Settings ---
                let placeholderLines = currentParserFields.map(field => {
                    let line = field.label; // e.g., "Customer Name"
                    if (!field.required) {
                        line += " (Optional)"; // e.g., "Note (Optional)"
                    }
                    return line;
                });
                placeholderText = placeholderLines.join('\n');
                placeholderText += "\n\n(Parser Settings চালু আছে। ফিল্ডগুলো অবশ্যই এই নির্দিষ্ট ক্রমে থাকতে হবে।)";
            }

            // Add the multi-parcel instruction to both
            placeholderText += "\n\n(একাধিক পার্সেল আলাদা করতে প্রতিটি পার্সেলের মাঝে **একটি ফাঁকা লাইন (খালি লাইন)** দিন।)";

            rawTextInput.placeholder = placeholderText;
        }

        // --- ADDED: New function to update the textarea placeholder ---
        // function updateRawTextPlaceholder() {
        //     const rawTextInput = document.getElementById('rawText');
        //     if (!rawTextInput) return; // Make sure the element exists

        //     // Create an array of placeholder lines from the parser fields
        //     let placeholderLines = currentParserFields.map(field => {
        //         let line = field.label; // e.g., "Customer Name"
        //         if (!field.required) {
        //             line += " (Optional)"; // e.g., "Note (Optional)"
        //         }
        //         return line;
        //     });

        //     // Join the lines with a newline character
        //     let placeholderText = placeholderLines.join('\n');

        //     placeholderText += "\n\n(একাধিক পার্সেল আলাদা করতে প্রতিটি পার্সেলের মাঝে **একটি ফাঁকা লাইন (খালি লাইন)** দিন।)";

        //     // Set the textarea's placeholder

        //     // Add a final instruction
        //     placeholderText += "\n(তুমি parser settings থেকে ইনপুট কাস্টমাইজ করতে পারবে — অর্থাৎ, তুমি নির্ধারণ করতে পারবে ইনপুট কীভাবে পড়বে বা আলাদা করবে।)";


        //     rawTextInput.placeholder = placeholderText;
        // }

        function setupDragAndDrop() {
            const parserFieldsContainer = document.getElementById('parserFields');
            if (!parserFieldsContainer) return;
            const fields = parserFieldsContainer.querySelectorAll('li');
            fields.forEach(field => {
                field.addEventListener('dragstart', () => field.classList.add('dragging'));
                field.addEventListener('dragend', () => {
                    field.classList.remove('dragging');

                    // 1. Update the global 'currentParserFields' variable based on new DOM order
                    const container = document.getElementById('parserFields');
                    const newFields = Array.from(container.querySelectorAll('li')).map(li => {
                        const fieldId = li.dataset.id;
                        const isRequired = li.querySelector('input[type="checkbox"]').checked;
                        // Find the original field data to preserve all properties (label, etc.)
                        const originalField = currentParserFields.find(f => f.id === fieldId) || DEFAULT_PARSER_FIELDS.find(f => f.id === fieldId);
                        return { ...originalField, id: fieldId, required: isRequired };
                    });

                    currentParserFields = newFields; // Set the global variable

                    // 2. Save the updated global variable
                    saveParserSettings();
                });
            });
            parserFieldsContainer.addEventListener('dragover', e => {
                e.preventDefault();
                const afterElement = getDragAfterElement(parserFieldsContainer, e.clientY);
                const dragging = document.querySelector('.dragging');
                if (dragging) {
                    if (afterElement == null) {
                        parserFieldsContainer.appendChild(dragging);
                    } else {
                        parserFieldsContainer.insertBefore(dragging, afterElement);
                    }
                }
            });
        }

        function getDragAfterElement(container, y) {
            const draggableElements = [...container.querySelectorAll('li:not(.dragging)')];
            return draggableElements.reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                } else {
                    return closest;
                }
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        }

        // --- PARSING LOGIC ---
        parseLocallyBtn.addEventListener('click', () => {
            const rawText = rawTextInput.value.trim();
            if (!rawText) return showMessage(authMessage, "Please paste text.", "error");

            // Split by one or more empty lines
            const parcelBlocks = rawText.split(/\n\s*\n/).filter(b => b.trim());
            if (parcelBlocks.length === 0) return showMessage(authMessage, "No valid parcels found.", "error");

            parsedDataContainer.innerHTML = '';
            let allParsedData = [];

            // Check the state of the new toggle
            const useAutoParsing = document.getElementById('autoParseToggle').checked;

            parcelBlocks.forEach(block => {
                let parcelData;
                let isValid = true;

                if (useAutoParsing) {
                    // --- USE NEW AUTO-PARSING LOGIC ---
                    parcelData = identifyAndParseOrder(block);
                    // Assume valid if the parser found anything
                    if (Object.values(parcelData).every(v => v === null)) {
                        isValid = false;
                    }

                } else {
                    // --- USE OLD PARSER-SETTINGS LOGIC ---
                    const lines = block.split('\n').map(l => l.trim());
                    parcelData = {}; // Must initialize

                    currentParserFields.forEach((field, index) => {
                        if (lines[index]) {
                            if (field.id === 'phone') {
                                // Apply normalization only to the phone field
                                parcelData[field.id] = normalizePhoneNumber(lines[index]);
                            } else {
                                // Assign all other fields normally
                                parcelData[field.id] = lines[index];
                            }
                        } else if (field.required) {
                            isValid = false;
                        }
                    });
                }

                if (isValid) {
                    allParsedData.push(parcelData);
                    // createParcelCard will now handle both data formats
                    createParcelCard(parcelData);
                }
            });

            updateSummary();
            // Update the method name for analytics
            apiCall('save_parse', { method: useAutoParsing ? 'Auto-Local' : 'Local-Settings', data: allParsedData });
        });

        parseWithAIBtn.addEventListener('click', async () => {
            const rawText = rawTextInput.value.trim();
            if (!rawText) return;
            loader.style.display = 'block';
            $('.parsing-buttons button').prop('disabled', true);
            parsedDataContainer.innerHTML = '';
            try {
                const results = await apiCall('parse_with_ai', { rawText });
                if (!Array.isArray(results)) throw new Error("AI did not return a valid list.");
                console.log(results);
                results.forEach(p => createParcelCardAI(p));
                updateSummary();
                apiCall('save_parse', { method: 'AI', data: results });
            } catch (error) {
                showMessage(authMessage, `AI Error: ${error.message}`, 'error');
            } finally {
                loader.style.display = 'none';
                $('.parsing-buttons button').prop('disabled', false);
                //updateAIButtonState();
                //updateFeatureVisibilityBasedOnPlan();
            }
        });

        function normalizePhoneNumber(phoneStr) {
            if (!phoneStr) return phoneStr;

            const digitMap = {
                '০': '0', '১': '1', '২': '2', '৩': '3', '৪': '4',
                '৫': '5', '৬': '6', '৭': '7', '৮': '8', '৯': '9'
            };

            // 1. Convert Bangla digits to English
            let normalized = phoneStr.replace(/[০-৯]/g, (match) => digitMap[match]);

            // 2. Remove all spaces (e.g., "+88 01...")
            normalized = normalized.replace(/\s+/g, '');

            // 3. Remove '+88' prefix
            if (normalized.startsWith('+88')) {
                normalized = normalized.substring(3);
            }

            if (normalized.startsWith('88')) {
                normalized = normalized.substring(2);
            }

            return normalized;
        }

        parseAndAutocompleteBtn.addEventListener('click', async () => {
            const rawText = rawTextInput.value.trim();
            if (!rawText) return;
            loader.style.display = 'block';
            $('.parsing-buttons button').prop('disabled', true);
            parsedDataContainer.innerHTML = '';
            try {
                const parcelBlocks = rawText.split(/\n\s*\n/).filter(b => b.trim());
                const allParsedData = parcelBlocks.map(block => {
                    const lines = block.split('\n').map(l => l.trim());
                    const parcelData = {};
                    currentParserFields.forEach((field, index) => {
                        if (lines[index]) parcelData[field.id] = lines[index];
                    });
                    return parcelData;
                });

                const autocompletePromises = allParsedData.map(async (parcel) => {
                    if (parcel.address) {
                        try {
                            const result = await apiCall('autocomplete_address', { address: parcel.address });
                            parcel.address = result.places[0]?.address || parcel.address;
                        } catch (e) { console.error("Autocomplete failed for address:", parcel.address); }
                    }
                });
                await Promise.all(autocompletePromises);

                allParsedData.forEach(p => createParcelCard(p));
                updateSummary();
                apiCall('save_parse', { method: 'Autocomplete', data: allParsedData });
            } catch (error) {
                showMessage(authMessage, `Parsing Error: ${error.message}`, 'error');
            } finally {
                loader.style.display = 'none';
                $('.parsing-buttons button').prop('disabled', false);
                // updateAIButtonState();
                updateFeatureVisibilityBasedOnPlan();
            }
        });

        $('#openHelpModalBtn').on('click', function () {
            $('#help-content-container').html(helpContent);
            $('#help-modal').show();
        });
    </script>
</body>

</html>