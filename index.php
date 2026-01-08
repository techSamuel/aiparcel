<?php
// Main Router File for AiParcel

// Start Session
session_set_cookie_params(0, '/');
session_start();

// Configuration
require_once 'api/config.php';

// Branding & Settings Defaults
$appName = "AiParcel";
$appLogoUrl = "https://i.ibb.co/6803876/Ai-Parcel-Logo-Full.png";
$seoTitle = "";
$seoDescription = "";
$seoImageUrl = "";
$enableSocialPlugins = false;
$facebookPageId = "";
$useSimpleFbBtn = false;
$whatsappNumber = "";

try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('app_name', 'app_logo_url', 'seo_title', 'seo_description', 'seo_image_url', 'enable_social_plugins', 'facebook_page_id', 'use_simple_fb_btn', 'whatsapp_number')");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['setting_value'])) {
            switch ($row['setting_key']) {
                case 'app_name':
                    $appName = $row['setting_value'];
                    break;
                case 'app_logo_url':
                    $appLogoUrl = $row['setting_value'];
                    break;
                case 'seo_title':
                    $seoTitle = $row['setting_value'];
                    break;
                case 'seo_description':
                    $seoDescription = $row['setting_value'];
                    break;
                case 'seo_image_url':
                    $seoImageUrl = $row['setting_value'];
                    break;
                case 'enable_social_plugins':
                    $enableSocialPlugins = ($row['setting_value'] == '1');
                    break;
                case 'facebook_page_id':
                    $facebookPageId = $row['setting_value'];
                    break;
                case 'use_simple_fb_btn':
                    $useSimpleFbBtn = ($row['setting_value'] == '1');
                    break;
                case 'whatsapp_number':
                    $whatsappNumber = $row['setting_value'];
                    break;
            }
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
    <title><?php echo $seoTitle ? htmlspecialchars($seoTitle) : "$appName - Smart Parcel Management"; ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($seoDescription); ?>">

    <!-- Open Graph / Facebook / WhatsApp Preview -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo $seoTitle ? htmlspecialchars($seoTitle) : $appName; ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($seoDescription); ?>">
    <?php
    $finalSeoImageUrl = $seoImageUrl;
    if ($seoImageUrl && strpos($seoImageUrl, 'http') !== 0 && defined('APP_URL') && strpos($seoImageUrl, 'api/') === 0) {
        $finalSeoImageUrl = rtrim(APP_URL, '/') . '/' . $seoImageUrl;
    }
    ?>
    <?php if ($finalSeoImageUrl): ?>
        <meta property="og:image" content="<?php echo htmlspecialchars($finalSeoImageUrl); ?>">
    <?php endif; ?>

    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($appLogoUrl); ?>">
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

    <!-- Social Media Plugins -->
    <?php if ($enableSocialPlugins): ?>
        <!-- Facebook -->
        <?php if ($facebookPageId): ?>
            <?php if (is_numeric($facebookPageId) && !$useSimpleFbBtn): ?>
                <!-- Messenger Chat Plugin (Requires Numeric Page ID) -->
                <div id="fb-root"></div>
                <div id="fb-customer-chat" class="fb-customerchat"></div>
                <script>
                    var chatbox = document.getElementById('fb-customer-chat');
                    chatbox.setAttribute("page_id", "<?php echo htmlspecialchars($facebookPageId); ?>");
                    chatbox.setAttribute("attribution", "biz_inbox");
                    window.fbAsyncInit = function () {
                        FB.init({ xfbml: true, version: 'v18.0' });
                    };
                    (function (d, s, id) {
                        var js, fjs = d.getElementsByTagName(s)[0];
                        if (d.getElementById(id)) return;
                        js = d.createElement(s); js.id = id;
                        js.src = 'https://connect.facebook.net/en_US/sdk/xfbml.customerchat.js';
                        fjs.parentNode.insertBefore(js, fjs);
                    }(document, 'script', 'facebook-jssdk'));
                </script>
            <?php else: ?>
                <!-- Simple Facebook Link (Force Simple Link or Non-numeric ID) -->
                <?php
                $fbLink = is_numeric($facebookPageId) ? "https://m.me/$facebookPageId" : $facebookPageId;
                if (strpos($fbLink, 'http') !== 0 && !is_numeric($facebookPageId)) {
                    $fbLink = "https://$facebookPageId";
                }
                ?>
                <a href="<?php echo htmlspecialchars($fbLink); ?>" target="_blank"
                    style="position:fixed; bottom:90px; left:20px; z-index:9999; background:#0084FF; color:white; width:50px; height:50px; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 10px rgba(0,0,0,0.3); transition: transform 0.3s;"
                    onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'"
                    title="Message Us on Facebook">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                    </svg>
                </a>
            <?php endif; ?>
        <?php else: ?>
            <!-- Fallback if Enabled but No ID: Direct Link to QuantumTechSoft -->
            <a href="https://www.facebook.com/quantumtechsoft" target="_blank"
                style="position:fixed; bottom:90px; left:20px; z-index:9999; background:#0084FF; color:white; width:50px; height:50px; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 10px rgba(0,0,0,0.3); transition: transform 0.3s;"
                onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'" title="Message Us">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                </svg>
            </a>
        <?php endif; ?>

        <!-- WhatsApp Floating Button -->
        <?php if ($whatsappNumber): ?>
            <a href="https://wa.me/<?php echo str_replace(['+', ' ', '-'], '', htmlspecialchars($whatsappNumber)); ?>"
                target="_blank"
                style="position:fixed; bottom:20px; left:20px; z-index:9999; background:#25D366; color:white; width:50px; height:50px; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 10px rgba(0,0,0,0.3); transition: transform 0.3s;"
                onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'"
                title="Chat on WhatsApp">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                </svg>
            </a>
        <?php endif; ?>
    <?php else: ?>
        <!-- Hardcoded Fallback: Direct Link to QuantumTechSoft -->
        <a href="https://www.facebook.com/quantumtechsoft" target="_blank"
            style="position:fixed; bottom:90px; left:20px; z-index:9999; background:#0084FF; color:white; width:50px; height:50px; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 10px rgba(0,0,0,0.3); transition: transform 0.3s;"
            onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'" title="Message Us">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path
                    d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
            </svg>
        </a>
    <?php endif; ?>

</body>

</html>