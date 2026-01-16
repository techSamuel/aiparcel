<?php
// Ensure this file is only accessed within the application
if (!defined('APP_URL')) {
    exit('Direct access not allowed');
}
?>
<div class="auth-background" id="auth-container">
    <div class="container">
        <div id="auth-view">
            <img id="authLogo" src="<?php echo $appLogoUrl; ?>" alt="Logo"
                style="<?php echo $appLogoUrl ? 'display: block;' : 'display: none;'; ?> margin: 0 auto 20px; max-height: 80px;">
            <h1 id="authTitle">Welcome to
                <?php echo $appName; ?>
            </h1>
            <p>Sign in to manage your parcels efficiently</p>
            <!-- Tab Switching -->
            <div style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e1e8ed;">
                <button id="tabLogin" class="auth-tab active"
                    style="flex:1; padding: 10px; background:none; border:none; border-bottom: 2px solid transparent; cursor:pointer; font-weight:600; font-size:16px;">Login</button>
                <button id="tabRegister" class="auth-tab"
                    style="flex:1; padding: 10px; background:none; border:none; border-bottom: 2px solid transparent; cursor:pointer; color:#95a5a6; font-size:16px;">Register</button>
            </div>

            <div class="auth-form">
                <!-- Register Only Fields -->
                <div class="input-group register-field" style="display:none;">
                    <span class="input-icon">👤</span>
                    <input type="text" id="fullName" placeholder="Full Name">
                </div>

                <div class="input-group">
                    <span class="input-icon">✉️</span>
                    <input type="email" id="email" placeholder="Email Address" required>
                </div>
                <div class="input-group">
                    <span class="input-icon">🔒</span>
                    <input type="password" id="password" placeholder="Password" required>
                </div>

                <!-- Register Only Fields -->
                <div class="input-group register-field" style="display:none;">
                    <span class="input-icon">🔐</span>
                    <input type="password" id="confirmPassword" placeholder="Confirm Password">
                </div>

                <button id="authActionBtn"
                    style="width: 100%; padding: 12px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; border: none; background-color: var(--primary-color); color: white; margin-top: 10px;">
                    Login <span class="loader"></span>
                </button>

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
            <p>Please enter the 6-digit code sent to your email address to activate your account.</p>

            <div class="auth-form">
                <div class="input-group">
                    <span class="input-icon">🔢</span>
                    <input type="text" id="verificationCode" placeholder="123456" maxlength="6"
                        style="text-align: center; font-size: 18px; letter-spacing: 4px; font-weight: bold;">
                </div>

                <button id="submitVerificationBtn"
                    style="width: 100%; padding: 12px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; border: none; background-color: var(--primary-color); color: white; margin-top: 10px;">
                    Verify Code <span class="loader"></span>
                </button>
            </div>

            <p style="margin-top: 20px; font-size: 14px; color: #666;">Didn't receive the code?</p>
            <p style="font-size: 13px; color: #888; margin-top: 5px;">Please check your spam or junk folder. (আপনার
                স্প্যাম বা জাঙ্ক ফোল্ডার চেক করুন।)</p>
            <button id="resendVerificationBtn"
                style="background: none; border: none; color: var(--primary-color); cursor: pointer; text-decoration: underline; font-weight: 500;">
                Resend Verification Code <span class="loader"></span>
            </button>

            <div id="verification-message" class="message" style="display:none;"></div>

            <button id="backToLoginFromVerify"
                style="background:none; border:none; color:var(--dark-gray); margin-top: 20px; cursor: pointer;">&larr;
                Back to Login</button>
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
<script src="assets/js/utils.js?v=<?php echo time(); ?>"></script>
<script src="assets/js/auth.js?v=<?php echo time(); ?>"></script>
<?php
// Allow server-side switching to register tab if queried
if (isset($_GET['mode']) && $_GET['mode'] === 'register') {
    echo '<script>document.addEventListener("DOMContentLoaded", () => switchAuthMode("register"));</script>';
}
?>