// --- Tabbed Auth Logic ---
let currentAuthMode = 'login'; // 'login' or 'register'
const authActionBtn = document.getElementById('authActionBtn'); // New combined button
const fullNameInput = document.getElementById('fullName'); // New field
const confirmPasswordInput = document.getElementById('confirmPassword'); // New field
const authMessage = document.getElementById('auth-message');
const emailInput = document.getElementById('email');
const passwordInput = document.getElementById('password');
const authView = document.getElementById('auth-view');
const verificationView = document.getElementById('verification-view');
const resendVerificationBtn = document.getElementById('resendVerificationBtn');

// Initialize Tab Listeners
document.getElementById('tabLogin').addEventListener('click', () => switchAuthMode('login'));
document.getElementById('tabRegister').addEventListener('click', () => switchAuthMode('register'));

function switchAuthMode(mode) {
    currentAuthMode = mode;
    document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.register-field').forEach(f => f.style.display = (mode === 'register') ? 'block' : 'none');

    if (mode === 'login') {
        document.getElementById('tabLogin').classList.add('active');
        document.getElementById('authActionBtn').innerHTML = 'Login <span class="loader"></span>';
        document.getElementById('tabRegister').style.color = '#95a5a6';
        document.getElementById('tabLogin').style.color = 'var(--dark-gray)';
    } else {
        document.getElementById('tabRegister').classList.add('active');
        document.getElementById('authActionBtn').innerHTML = 'Register <span class="loader"></span>';
        document.getElementById('tabLogin').style.color = '#95a5a6';
        document.getElementById('tabRegister').style.color = 'var(--dark-gray)';
    }
    if (authMessage) authMessage.style.display = 'none'; // Clear messages
}

// Unified Auth Action Handler
if (authActionBtn) {
    authActionBtn.addEventListener('click', async () => {
        const email = emailInput.value;
        const password = passwordInput.value;

        if (!email || !password) {
            showMessage(authMessage, 'Please fill in all required fields', 'error');
            return;
        }

        authActionBtn.disabled = true;

        try {
            if (currentAuthMode === 'login') {
                // --- LOGIN LOGIC ---
                const data = await apiCall('login', { email, password });
                if (data.loggedIn) {
                    // Reload to let PHP router handle the dashboard view
                    window.location.reload();
                }
            } else {
                // --- REGISTER LOGIC ---
                const fullName = fullNameInput.value;
                const confirmPass = confirmPasswordInput.value;

                if (!fullName) { throw new Error("Full Name is required."); }
                if (password !== confirmPass) { throw new Error("Passwords do not match."); }
                if (password.length < 6) { throw new Error("Password must be at least 6 characters."); }

                await handleAuthAction('register', 'Registration successful. Please check your email.', {
                    email, password, display_name: fullName
                });
            }
        } catch (error) {
            if (error.message.includes('Email not verified')) {
                authView.style.display = 'none';
                verificationView.style.display = 'block';
            } else {
                showMessage(authMessage, error.message, 'error');
            }
        } finally {
            authActionBtn.disabled = false;
        }
    });
}

// Helper to accept data payload
async function handleAuthAction(action, successMessage, payload = {}) {
    const body = (action === 'register') ? payload : { email: emailInput.value };
    if (action === 'resend_verification' && !body.email) body.email = emailInput.value;

    try {
        await apiCall(action, body);
        const msgEl = (action === 'register') ? authMessage : document.getElementById('verification-message');
        showMessage(msgEl, successMessage, 'success');
        if (action === 'register') {
            authView.style.display = 'none';
            verificationView.style.display = 'block';
        }
    } catch (error) {
        const msgEl = (action === 'register') ? authMessage : document.getElementById('verification-message');
        showMessage(msgEl, error.message, 'error');
        throw error;
    }
}

if (resendVerificationBtn) {
    resendVerificationBtn.addEventListener('click', () => handleAuthAction('resend_verification', 'A new verification code has been sent to your email.'));
}

const submitVerificationBtn = document.getElementById('submitVerificationBtn');
const backToLoginFromVerify = document.getElementById('backToLoginFromVerify');

if (submitVerificationBtn) {
    submitVerificationBtn.addEventListener('click', async () => {
        const email = emailInput.value;
        const code = document.getElementById('verificationCode').value;
        const msgEl = document.getElementById('verification-message');

        if (!code || code.length !== 6) {
            showMessage(msgEl, 'Please enter a valid 6-digit code.', 'error');
            return;
        }

        submitVerificationBtn.disabled = true;
        try {
            const data = await apiCall('verify_code', { email, code });

            if (data.loggedIn) {
                showMessage(msgEl, 'Verification successful! Logging you in...', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                // Fallback if backend doesn't return loggedIn
                showMessage(msgEl, 'Verification successful! Please login.', 'success');
                setTimeout(() => {
                    verificationView.style.display = 'none';
                    authView.style.display = 'block';
                    switchAuthMode('login');
                }, 2000);
            }

        } catch (error) {
            showMessage(msgEl, error.message, 'error');
            submitVerificationBtn.disabled = false;
        }
    });
}

if (backToLoginFromVerify) {
    backToLoginFromVerify.addEventListener('click', () => {
        verificationView.style.display = 'none';
        authView.style.display = 'block';
        switchAuthMode('login');
    });
}

// --- Google Sign-In Handler ---
const googleLoginBtn = document.getElementById('googleLoginBtn');
if (googleLoginBtn) {
    googleLoginBtn.addEventListener('click', async () => {
        const loader = googleLoginBtn.querySelector('.loader');

        googleLoginBtn.disabled = true;
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
            googleLoginBtn.disabled = false;
            loader.style.display = 'none';
        }
    });
}

// --- Password Reset Flow ---
const forgotPasswordLink = document.getElementById('forgotPasswordLink');
const backToLoginBtn = document.getElementById('backToLoginBtn');
const requestResetBtn = document.getElementById('requestResetBtn');
const resetPasswordRequestView = document.getElementById('reset-password-request-view');

if (forgotPasswordLink) {
    forgotPasswordLink.addEventListener('click', (e) => {
        e.preventDefault();
        authView.style.display = 'none';
        verificationView.style.display = 'none';
        resetPasswordRequestView.style.display = 'block';
    });
}

if (backToLoginBtn) {
    backToLoginBtn.addEventListener('click', (e) => {
        e.preventDefault();
        resetPasswordRequestView.style.display = 'none';
        authView.style.display = 'block';
    });
}

if (requestResetBtn) {
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
}
