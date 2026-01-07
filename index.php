<?php
// Main Router File for AiParcel

// Start Session
session_set_cookie_params(0, '/');
session_start();

// Configuration
require_once 'api/config.php';

// Branding
$appName = "AiParcel";
$appLogoUrl = "https://i.ibb.co/6803876/Ai-Parcel-Logo-Full.png";

try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('app_name', 'app_logo_url')");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'app_name' && !empty($row['setting_value'])) {
            $appName = $row['setting_value'];
        }
        if ($row['setting_key'] === 'app_logo_url' && !empty($row['setting_value'])) {
            $appLogoUrl = $row['setting_value'];
        }
    }
} catch (Exception $e) {
    // Fallback to defaults
}

// Page Router Logic
$page = isset($_GET['page']) ? $_GET['page'] : '';
$isLoggedIn = isset($_SESSION['user_id']);

// Force dashboard if logged in and trying to access auth/landing (unless action is logout, which is handled in JS/API)
// But for cleaner URL handling:
if ($isLoggedIn && ($page === 'auth' || $page === 'landing' || $page === '')) {
    header('Location: ?page=dashboard');
    exit;
} elseif (!$isLoggedIn && $page === '') {
    $page = 'landing';
} elseif (!$isLoggedIn && $page === 'dashboard') {
    // Redirect to login if trying to access dashboard while logged out
    header('Location: ?page=auth');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $appName; ?> - Smart Parcel Management</title>
    <link rel="icon" type="image/png" href="<?php echo $appLogoUrl; ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

    <!-- Facebook Pixel Code -->
    <?php if (defined('FACEBOOK_PIXEL_ID') && FACEBOOK_PIXEL_ID && FACEBOOK_PIXEL_ID !== 'YOUR_PIXEL_ID_HERE'): ?>
        <script>
            ! function (f, b, e, v, n, t, s) {
                if (f.fbq) return;
                n = f.fbq = function () {
                    n.callMethod ?
                        n.callMethod.apply(n, arguments) : n.queue.push(arguments)
                };
                if (!f._fbq) f._fbq = n;
                n.push = n;
                n.loaded = !0;
                n.version = '2.0';
                n.queue = [];
                t = b.createElement(e);
                t.async = !0;
                t.src = v;
                s = b.getElementsByTagName(e)[0];
                s.parentNode.insertBefore(t, s)
            }(window, document, 'script',
                'https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', '<?php echo FACEBOOK_PIXEL_ID; ?>');
            fbq('track', 'PageView');
        </script>
        <noscript><img height="1" width="1" style="display:none"
                src="https://www.facebook.com/tr?id=<?php echo FACEBOOK_PIXEL_ID; ?>&ev=PageView&noscript=1" /></noscript>
    <?php endif; ?>
    <!-- End Facebook Pixel Code -->

    <!-- Dependencies -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
</head>

<body class="<?php echo ($page === 'dashboard') ? 'show-app' : ''; ?>">

    <?php
    switch ($page) {
        case 'dashboard':
            if ($isLoggedIn) {
                // Pass minimal user info to JS to avoid instant API call
                $currentUserData = [
                    'id' => $_SESSION['user_id'],
                    'email' => $_SESSION['email'],
                    'displayName' => $_SESSION['display_name'] ?? $_SESSION['email']
                ];
                echo "<script>const PRELOADED_USER = " . json_encode($currentUserData) . ";</script>";
                include 'views/dashboard.php';
            } else {
                include 'views/auth.php';
            }
            break;

        case 'auth':
            include 'views/auth.php';
            break;

        case 'landing':
        default:
            include 'views/landing.php';
            break;
    }
    ?>

</body>

</html>