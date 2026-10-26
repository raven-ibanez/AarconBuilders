<?php
/**
 * Aarcon Builders - Minimalist Black & White Sign In Portal
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

// If already authenticated, redirect to appropriate destination
if (is_logged_in()) {
    header('Location: ' . (is_admin() ? 'admin.php' : 'index.php'));
    exit;
}

$flash = get_flash();
$appName = get_app_name();
$appTagline = function_exists('get_system_setting') ? get_system_setting('company_tagline', APP_TAGLINE) : APP_TAGLINE;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In &bull; <?= e($appName) ?></title>
    
    <!-- Fonts: Plus Jakarta Sans & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="img/logo.png">

    <!-- Base Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        :root {
            --bw-bg: #f5f5f7;
            --bw-card: rgba(255, 255, 255, 0.76);
            --bw-border: rgba(0, 0, 0, 0.08);
            --bw-border-dark: #dc2626;
            --bw-text-main: #1d1d1f;
            --bw-text-muted: #86868b;
            --bw-text-light: #a1a1a6;
            --bw-black: #dc2626;
            --bw-black-hover: #b91c1c;
            --bw-error: #ff3b30;
            --bw-error-bg: rgba(255, 59, 48, 0.1);
            --bw-error-border: rgba(255, 59, 48, 0.22);
            --bw-success: #34c759;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body.login-body-bw {
            background-color: var(--bw-bg);
            background-image: 
                radial-gradient(circle at 50% 0%, rgba(15, 23, 42, 0.03) 0%, transparent 70%),
                linear-gradient(rgba(15, 23, 42, 0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(15, 23, 42, 0.02) 1px, transparent 1px);
            background-size: 100% 100%, 32px 32px, 32px 32px;
            color: var(--bw-text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            font-family: var(--font-main);
            -webkit-font-smoothing: antialiased;
        }

        .login-container {
            width: 100%;
            max-width: 380px;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Minimal Brand Header */
        .brand-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .brand-logo-img {
            width: 52px;
            height: 52px;
            object-fit: contain;
            display: block;
            margin: 0 auto 0.85rem;
            border-radius: 50%;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
        }

        .brand-name {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--bw-text-main);
            letter-spacing: -0.02em;
        }

        .brand-desc {
            font-size: 0.82rem;
            color: var(--bw-text-muted);
            margin-top: 0.2rem;
        }

        /* Apple Liquid Glass Card */
        .auth-card {
            background: rgba(255, 255, 255, 0.78);
            -webkit-backdrop-filter: blur(28px) saturate(190%);
            backdrop-filter: blur(28px) saturate(190%);
            border: 1px solid rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            padding: 2.25rem 2rem;
            box-shadow: 0 16px 40px -10px rgba(0, 0, 0, 0.07), inset 0 1px 0 rgba(255, 255, 255, 1);
        }

        .auth-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--bw-text-main);
            letter-spacing: -0.01em;
            margin-bottom: 0.25rem;
        }

        .auth-subtitle {
            font-size: 0.82rem;
            color: var(--bw-text-muted);
            margin-bottom: 1.5rem;
        }

        /* Alert */
        .alert-box {
            padding: 0.75rem 0.9rem;
            border-radius: 8px;
            font-size: 0.82rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            line-height: 1.4;
        }

        .alert-danger-bw {
            background: var(--bw-error-bg);
            border: 1px solid var(--bw-error-border);
            color: var(--bw-error);
        }

        .alert-info-bw {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            color: var(--bw-text-main);
        }

        /* Form Group */
        .form-group-bw {
            margin-bottom: 1.25rem;
            position: relative;
        }

        .form-label-bw {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--bw-text-main);
            margin-bottom: 0.4rem;
        }

        .input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .form-input-bw {
            width: 100%;
            height: 42px;
            background: rgba(255, 255, 255, 0.75);
            -webkit-backdrop-filter: blur(12px);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            padding: 0 38px 0 12px;
            font-size: 0.92rem;
            color: var(--bw-text-main);
            font-family: inherit;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.03);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .form-input-bw:focus {
            outline: none;
            background: #ffffff;
            border-color: #dc2626;
            box-shadow: 0 0 0 3.5px rgba(220, 38, 38, 0.2);
        }

        .form-input-bw::placeholder {
            color: #94a3b8;
            font-size: 0.88rem;
        }

        /* Validation States */
        .form-input-bw.is-invalid {
            border-color: var(--bw-error) !important;
            background-color: #fffafb;
        }

        .form-input-bw.is-invalid:focus {
            box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.15) !important;
        }

        .form-input-bw.is-valid {
            border-color: #0f172a;
        }

        .validation-icon {
            position: absolute;
            right: 12px;
            pointer-events: none;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .validation-icon.icon-valid {
            color: #0f172a;
        }

        .validation-icon.icon-invalid {
            color: var(--bw-error);
        }

        .validation-feedback {
            font-size: 0.75rem;
            margin-top: 0.35rem;
            color: var(--bw-error);
            display: none;
            line-height: 1.3;
        }

        .validation-feedback.show {
            display: block;
        }

        /* Password Toggle */
        .pwd-toggle-btn {
            position: absolute;
            right: 10px;
            background: none;
            border: none;
            color: var(--bw-text-muted);
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.15s ease;
        }

        .pwd-toggle-btn:hover {
            color: var(--bw-text-main);
        }

        /* Apple Liquid Glass Submit Button */
        .btn-submit-bw {
            width: 100%;
            height: 44px;
            background: linear-gradient(180deg, #dc2626 0%, #b91c1c 100%);
            color: #ffffff;
            border: 1px solid rgba(220, 38, 38, 0.35);
            border-radius: 980px;
            font-size: 0.92rem;
            font-weight: 600;
            letter-spacing: -0.01em;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            box-shadow: 0 3px 12px rgba(220, 38, 38, 0.32), inset 0 1px 0 rgba(255, 255, 255, 0.3);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            margin-top: 1.25rem;
            font-family: inherit;
        }

        .btn-submit-bw:hover {
            background: linear-gradient(180deg, #ef4444 0%, #dc2626 100%);
            box-shadow: 0 5px 16px rgba(220, 38, 38, 0.42);
            transform: translateY(-0.5px);
        }

        .btn-submit-bw:active {
            transform: scale(0.98);
        }

        .btn-submit-bw:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .page-copyright {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.75rem;
            color: var(--bw-text-light);
        }
    </style>
</head>
<body class="login-body-bw">

    <div class="login-container">
        
        <!-- Brand Header -->
        <div class="brand-header">
            <img src="img/logo.png" alt="<?= e($appName) ?> Logo" class="brand-logo-img">
            <h1 class="brand-name"><?= e($appName) ?></h1>
            <p class="brand-desc"><?= e($appTagline) ?></p>
        </div>

        <!-- Auth Card -->
        <div class="auth-card">
            <h2 class="auth-title">Sign In</h2>
            <p class="auth-subtitle">Enter your account credentials to continue</p>

            <!-- Flash Alert -->
            <?php if ($flash): ?>
                <div class="alert-box <?= $flash['type'] === 'danger' || $flash['type'] === 'warning' ? 'alert-danger-bw' : 'alert-info-bw' ?>">
                    <span style="display: flex; align-items: center;">
                        <?php if ($flash['type'] === 'danger' || $flash['type'] === 'warning'): ?>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        <?php else: ?>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <?php endif; ?>
                    </span>
                    <span style="flex: 1;"><?= e($flash['message']) ?></span>
                </div>
            <?php endif; ?>

            <form action="api/actions.php" method="POST" id="authForm" novalidate>
                <input type="hidden" name="action" value="login">

                <!-- Username Field with Real-Time Validation -->
                <div class="form-group-bw">
                    <label class="form-label-bw" for="usernameInput">
                        <span>Username</span>
                        <span id="validationStatus" style="font-size: 0.7rem; font-weight: 500; color: var(--bw-text-muted);"></span>
                    </label>
                    <div class="input-wrap">
                        <input 
                            type="text" 
                            name="username" 
                            id="usernameInput" 
                            class="form-input-bw" 
                            placeholder="e.g. admin" 
                            required 
                            autofocus 
                            autocomplete="username"
                            spellcheck="false"
                            autocapitalize="none"
                        >
                        <!-- Valid Status Icon -->
                        <span class="validation-icon icon-valid" id="iconValid" title="Valid username format">
                            <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                        <!-- Invalid Status Icon -->
                        <span class="validation-icon icon-invalid" id="iconInvalid" title="Invalid username format">
                            <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </div>
                    <div class="validation-feedback" id="usernameFeedback"></div>
                </div>

                <!-- Password Field -->
                <div class="form-group-bw">
                    <label class="form-label-bw" for="passwordInput">
                        <span>Password</span>
                    </label>
                    <div class="input-wrap">
                        <input 
                            type="password" 
                            name="password" 
                            id="passwordInput" 
                            class="form-input-bw" 
                            placeholder="Enter password" 
                            required 
                            autocomplete="current-password"
                        >
                        <button type="button" class="pwd-toggle-btn" id="togglePwdBtn" aria-label="Toggle password view" title="Show/Hide Password">
                            <svg id="pwdEyeSvg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-submit-bw" id="btnSubmit">
                    <span>Sign In</span>
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </button>

                <!-- Demo Quick Fill Buttons -->
                <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--bw-border); font-size: 0.78rem;">
                    <div style="font-weight: 600; color: var(--bw-text-muted); margin-bottom: 0.5rem; text-align: center;">System Role Logins:</div>
                    <div style="display: flex; gap: 0.35rem; justify-content: center; flex-wrap: wrap;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="quickFill('admin', 'admin123')" style="font-size: 0.75rem; padding: 3px 8px;">Admin</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="quickFill('accountant', 'accountant123')" style="font-size: 0.75rem; padding: 3px 8px;">Lead Accountant</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="page-copyright">
            &copy; <?= date('Y') ?> <?= e($appName) ?>
        </div>

    </div>

    <!-- Client-Side Username Validation & Interactive Engine -->
    <script>
        const usernameInput = document.getElementById('usernameInput');
        const usernameFeedback = document.getElementById('usernameFeedback');
        const iconValid = document.getElementById('iconValid');
        const iconInvalid = document.getElementById('iconInvalid');
        const validationStatus = document.getElementById('validationStatus');
        const btnSubmit = document.getElementById('btnSubmit');
        const authForm = document.getElementById('authForm');
        const passwordInput = document.getElementById('passwordInput');

        // Regex: 3 to 30 characters, letters, numbers, underscores, dots, hyphens (or email)
        const usernameRegex = /^[a-zA-Z0-9_.-]{3,30}$/;
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        function validateUsername(showFeedback = true) {
            const val = usernameInput.value.trim();

            if (val === '') {
                if (showFeedback) {
                    showError('Username is required.');
                } else {
                    resetValidation();
                }
                return false;
            }

            if (val.length < 3) {
                if (showFeedback) {
                    showError('Username must be at least 3 characters.');
                }
                return false;
            }

            if (val.includes(' ')) {
                if (showFeedback) {
                    showError('Username cannot contain spaces.');
                }
                return false;
            }

            if (!usernameRegex.test(val) && !emailRegex.test(val)) {
                if (showFeedback) {
                    showError('Only letters, numbers, dots, dashes, and underscores allowed.');
                }
                return false;
            }

            // Valid
            showSuccess();
            return true;
        }

        function showError(msg) {
            usernameInput.classList.remove('is-valid');
            usernameInput.classList.add('is-invalid');
            iconValid.style.display = 'none';
            iconInvalid.style.display = 'flex';
            usernameFeedback.textContent = msg;
            usernameFeedback.classList.add('show');
            if (validationStatus) validationStatus.textContent = '';
        }

        function showSuccess() {
            usernameInput.classList.remove('is-invalid');
            usernameInput.classList.add('is-valid');
            iconInvalid.style.display = 'none';
            iconValid.style.display = 'flex';
            usernameFeedback.textContent = '';
            usernameFeedback.classList.remove('show');
            if (validationStatus) validationStatus.textContent = 'Valid';
        }

        function resetValidation() {
            usernameInput.classList.remove('is-invalid', 'is-valid');
            iconValid.style.display = 'none';
            iconInvalid.style.display = 'none';
            usernameFeedback.textContent = '';
            usernameFeedback.classList.remove('show');
            if (validationStatus) validationStatus.textContent = '';
        }

        // Live validation listeners
        usernameInput.addEventListener('input', () => {
            if (usernameInput.value.length >= 3) {
                validateUsername(true);
            } else if (usernameInput.value.length > 0) {
                showError('Username must be at least 3 characters.');
            } else {
                resetValidation();
            }
        });

        usernameInput.addEventListener('blur', () => {
            validateUsername(true);
        });

        // Form submission guard
        authForm.addEventListener('submit', (e) => {
            const isUserValid = validateUsername(true);
            const isPassValid = passwordInput.value.trim().length > 0;

            if (!isUserValid) {
                e.preventDefault();
                usernameInput.focus();
                return;
            }

            if (!isPassValid) {
                e.preventDefault();
                passwordInput.focus();
                return;
            }

            // Clean submission state
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<span style="display:inline-block; width:14px; height:14px; border:2px solid #ffffff; border-top-color:transparent; border-radius:50%; animation:spin 0.6s linear infinite; margin-right:6px;"></span> Signing in...';
        });

        // Password Show/Hide Toggle
        const togglePwdBtn = document.getElementById('togglePwdBtn');
        const pwdEyeSvg = document.getElementById('pwdEyeSvg');
        if (togglePwdBtn && passwordInput) {
            togglePwdBtn.addEventListener('click', () => {
                const isPwd = passwordInput.type === 'password';
                passwordInput.type = isPwd ? 'text' : 'password';
                if (isPwd) {
                    pwdEyeSvg.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
                } else {
                    pwdEyeSvg.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
                }
            });
        }

        // Quick Fill Helper for Demo / Testing
        function quickFill(u, p) {
            usernameInput.value = u;
            passwordInput.value = p;
            validateUsername(true);
        }
    </script>
</body>
</html>
