<?php
/**
 * ============================================================
 *  Ultimate Mods – FiveM Community Manager
 * ============================================================
 *
 *  Description:
 *  This file is part of the Ultimate Mods FiveM Community Manager,
 *  a commercial web-based management system designed for FiveM
 *  roleplay communities. The system provides tools for department
 *  management, user administration, applications, announcements,
 *  internal messaging, scheduling, and other community operations.
 *
 *  Copyright:
 *  Copyright © 2026 Ultimate Mods LLC.
 *  All Rights Reserved.
 *
 *  License & Usage:
 *  This software is licensed, not sold. Unauthorized copying,
 *  modification, redistribution, resale, sublicensing, or
 *  reverse engineering of this file or any portion of the
 *  Ultimate Mods FiveM Community Manager is strictly prohibited
 *  without prior written permission from Ultimate Mods LLC.
 *
 *  This file may only be used as part of a valid, purchased
 *  Ultimate Mods license and in accordance with the applicable
 *  license agreement.
 *
 *  Website:
 *  https://ultimate-mods.com/
 *
 * ============================================================
 */
// Check if system is installed
if (!file_exists('../config.php')) {
    header('Location: /install/');
    exit();
}

require_once '../config.php';
if (!defined('UM_FUNCTIONS_LOADED')) { require_once __DIR__ . '/../includes/functions.php'; }
if (!defined('UM_EMAIL_LOADED')) { require_once __DIR__ . '/../includes/email.php'; }
if (!defined('TOAST_LOADED')) { require_once __DIR__ . '/../includes/toast.php'; define('TOAST_LOADED', true); }
if (file_exists(__DIR__ . '/../includes/discord.php')) { require_once __DIR__ . '/../includes/discord.php'; }

// Check if Discord is available
$discordEnabled = function_exists('isDiscordConfigured') && isDiscordConfigured();
$discordSettings = $discordEnabled ? getDiscordSettings() : ['allow_login' => false];

// Double-check installation
if (!defined('INSTALLED')) {
    if (file_exists('../install/index.php')) {
        header('Location: /install/');
        exit();
    }
}

// Restore session from Discord login token if present (handles session loss after OAuth redirect)
if (!isLoggedIn() && function_exists('checkLoginToken')) {
    checkLoginToken();
}

if (isLoggedIn()) {
    header('Location: ../');
    exit();
}

// Clear any pending 2FA data if user returns to login
if (isset($_SESSION['pending_2fa_user_id'])) {
    unset($_SESSION['pending_2fa_user_id']);
    unset($_SESSION['pending_2fa_username']);
    unset($_SESSION['pending_2fa_is_admin']);
    unset($_SESSION['pending_2fa_is_approved']);
    unset($_SESSION['pending_2fa_must_change']);
}

$error = '';
$success = '';

// Check for registration redirect
if (isset($_GET['registered'])) {
    if ($_GET['registered'] == '2') {
        $success = 'Registration successful! You can now log in.';
    } else {
        $success = 'Registration successful! Your account is pending admin approval. You will be notified when approved.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRFToken();
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        // Check rate limiting
        $rate_error = checkLoginRateLimit($username);
        if ($rate_error) {
            $error = $rate_error;
        } else {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                if (!empty($user['is_suspended'])) {
                    $error = 'Your account has been suspended. Please contact an administrator.';
                } elseif ($user['is_approved']) {
                    // Check if 2FA is enabled for this user
                    $tfaStmt = $conn->prepare("SELECT is_enabled, secret FROM two_factor_codes WHERE user_id = ? AND is_enabled = 1");
                    $has_2fa = false;
                    if ($tfaStmt) {
                        $tfaStmt->bind_param("i", $user['id']);
                        $tfaStmt->execute();
                        $tfaResult = $tfaStmt->get_result();
                        if ($tfaResult && $tfaResult->num_rows > 0) {
                            $has_2fa = true;
                            $tfa_data = $tfaResult->fetch_assoc();
                        }
                        $tfaStmt->close();
                    }
                    
                    if ($has_2fa) {
                        // Store pending login data and redirect to 2FA verification
                        $_SESSION['pending_2fa_user_id'] = $user['id'];
                        $_SESSION['pending_2fa_username'] = $user['username'];
                        $_SESSION['pending_2fa_is_admin'] = $user['is_admin'];
                        $_SESSION['pending_2fa_is_approved'] = $user['is_approved'];
                        $_SESSION['pending_2fa_must_change'] = !empty($user['must_change_password']);
                        $stmt->close();
                        $conn->close();
                        header('Location: /auth/verify_2fa');
                        exit();
                    }
                    
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['is_admin'] = $user['is_admin'];
                    $_SESSION['is_approved'] = $user['is_approved'];
                    
                    clearLoginAttempts($username);
                    logAudit('user_login', 'user', $user['id'], 'User logged in');
                    
                    // Track login history
                    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $location = function_exists('getLocationFromIP') ? getLocationFromIP($ip) : '';
                    
                    $histStmt = $conn->prepare("INSERT INTO login_history (user_id, ip_address, user_agent, location, success) VALUES (?, ?, ?, ?, 1)");
                    if ($histStmt) {
                        $histStmt->bind_param("isss", $user['id'], $ip, $ua, $location);
                        $histStmt->execute();
                        $histStmt->close();
                    }
                    
                    // Detect and process login anomalies
                    if (function_exists('detectLoginAnomalies')) {
                        $anomalies = detectLoginAnomalies($user['id'], $ip, $ua);
                        if (!empty($anomalies)) {
                            processLoginAnomalies($user['id'], $anomalies, $ip, $ua);
                        }
                    }
                    
                    // Create active session record
                    $session_token = bin2hex(random_bytes(32));
                    $_SESSION['session_token'] = $session_token;
                    $sessStmt = $conn->prepare("INSERT INTO active_sessions (user_id, session_token, ip_address, user_agent) VALUES (?, ?, ?, ?)");
                    if ($sessStmt) {
                        $sessStmt->bind_param("isss", $user['id'], $session_token, $ip, $ua);
                        $sessStmt->execute();
                        $sessStmt->close();
                    }
                    
                    // Check if user must change their password (new account from application)
                    if (!empty($user['must_change_password'])) {
                        $_SESSION['must_change_password'] = true;
                        header('Location: ../auth/setup_account');
                        exit();
                    }
                    
                    header('Location: ../');
                    exit();
                } else {
                    $error = 'Your account is pending approval.';
                }
            } else {
                recordFailedLogin($username);
                $error = 'Invalid username or password.';
            }
        } else {
            recordFailedLogin($username);
            $error = 'Invalid username or password.';
        }
        
        $stmt->close();
        $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <?php 
    $colors = getThemeColors();
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>(function(){var m=document.cookie.match(/\bum_theme=([^;]+)/);document.documentElement.setAttribute('data-theme',m?m[1]:'dark');})();</script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --bg-base: #0a0a0a;
            --bg-card: #141414;
            --bg-elevated: #1a1a1a;
            --bg-input: #0f0f0f;
            --border: #2a2a2a;
            --accent: <?php echo $colors['primary'] ?? '#5865F2'; ?>;
            --accent-hover: #4752c4;
            --accent-muted: rgba(88, 101, 242, 0.15);
            --success: #23a559;
            --danger: #da373c;
            --text-primary: #f2f3f5;
            --text-secondary: #b5bac1;
            --text-muted: #80848e;
            --radius-md: 10px;
            --radius-lg: 14px;
        }
        [data-theme="light"] {
            --bg-base: #f0f2f5;
            --bg-card: #ffffff;
            --bg-elevated: #eaecf0;
            --bg-input: #fafafa;
            --border: #e0e2e8;
            --text-primary: #060607;
            --text-secondary: #313338;
            --text-muted: #4e5058;
        }
        .theme-icon-dark { display: inline; }
        .theme-icon-light { display: none; }
        [data-theme="light"] .theme-icon-dark { display: none; }
        [data-theme="light"] .theme-icon-light { display: inline; }
        .theme-transitioning, .theme-transitioning * {
            transition: background-color 0.25s ease, color 0.25s ease, border-color 0.25s ease !important;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .logo-section {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .logo-icon {
            width: 56px;
            height: 56px;
            background: var(--accent);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin: 0 auto 16px;
            box-shadow: 0 8px 24px rgba(88, 101, 242, 0.3);
        }
        
        .logo-icon img {
            width: 36px;
            height: 36px;
            border-radius: var(--radius-sm);
            object-fit: contain;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-base);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            -webkit-font-smoothing: antialiased;
        }
        
        /* Background grid pattern */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: 
                linear-gradient(rgba(88, 101, 242, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(88, 101, 242, 0.03) 1px, transparent 1px);
            background-size: 60px 60px;
            pointer-events: none;
        }
        
        /* Accent glow */
        body::after {
            content: '';
            position: fixed;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(88, 101, 242, 0.15) 0%, transparent 70%);
            top: -300px;
            right: -300px;
            pointer-events: none;
        }
        
        .login-container {
            background: var(--bg-card);
            border: 1px solid var(--border);
            padding: 40px;
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 1;
            animation: fadeIn 0.5s ease;
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.5);
        }
        
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--accent);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        
        .login-logo {
            width: 56px;
            height: 56px;
            background: var(--accent);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 8px 24px rgba(88, 101, 242, 0.3);
        }
        
        .login-logo img {
            width: 36px;
            height: 36px;
            border-radius: var(--radius-sm);
            object-fit: contain;
        }
        
        .login-logo-emoji {
            font-size: 28px;
        }
        
        h1 {
            color: var(--text-primary);
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }
        
        .subtitle {
            text-align: center;
            color: var(--text-muted);
            margin-bottom: 32px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 13px;
        }
        
        input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            font-size: 14px;
            font-family: inherit;
            background: var(--bg-input);
            color: var(--text-primary);
            transition: all 0.2s ease;
        }
        
        input::placeholder {
            color: var(--text-muted);
        }
        
        input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-muted);
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 15px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(88, 101, 242, 0.35);
            margin-top: 8px;
        }
        
        .btn:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(88, 101, 242, 0.4);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn-discord {
            background: #5865F2;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 16px;
        }
        
        .btn-discord:hover {
            background: #4752c4;
        }
        
        .btn-discord svg {
            width: 20px;
            height: 20px;
        }
        
        .divider {
            display: flex;
            align-items: center;
            gap: 16px;
            margin: 24px 0;
            color: var(--text-muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }
        
        .error {
            background: rgba(218, 55, 60, 0.15);
            color: #f87171;
            padding: 14px 16px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            border-left: 3px solid var(--danger);
            font-size: 14px;
            animation: fadeIn 0.3s ease;
        }
        
        .success {
            background: rgba(35, 165, 89, 0.15);
            color: #4ade80;
            padding: 14px 16px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            border-left: 3px solid var(--success);
            font-size: 14px;
            animation: fadeIn 0.3s ease;
        }
        
        .register-link {
            text-align: center;
            margin-top: 28px;
            color: var(--text-muted);
            font-size: 14px;
        }
        
        .register-link a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .register-link a:hover {
            color: var(--text-primary);
        }
        
        .forgot-link {
            text-align: right;
            margin-top: -12px;
            margin-bottom: 20px;
        }
        
        .forgot-link a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 13px;
            transition: color 0.2s ease;
        }
        
        .forgot-link a:hover {
            color: var(--accent);
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 32px 24px;
            }
            h1 {
                font-size: 22px;
            }
        }
        
        /* Discord OAuth Button */
        .oauth-divider {
            display: flex;
            align-items: center;
            margin: 24px 0;
            color: var(--text-muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .oauth-divider::before,
        .oauth-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }
        .oauth-divider span {
            padding: 0 16px;
        }
        .btn-discord {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 14px 20px;
            background: #5865F2;
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(88, 101, 242, 0.35);
        }
        .btn-discord:hover {
            background: #4752C4;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(88, 101, 242, 0.4);
        }
        .btn-discord svg {
            flex-shrink: 0;
        }
    </style>
</head>
<body>
    <?php 
    $logo_path = getSetting('community_logo', '');
    $has_logo = !empty($logo_path) && file_exists($_SERVER['DOCUMENT_ROOT'] . $logo_path);
    ?>
    <div class="login-container">
        <div class="logo-section">
            <?php if ($has_logo): ?>
                <div class="logo-icon" style="background: var(--bg-elevated);">
                    <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo">
                </div>
            <?php else: ?>
                <div class="logo-icon">🎮</div>
            <?php endif; ?>
            <h1><?php echo htmlspecialchars(COMMUNITY_NAME); ?></h1>
            <p class="subtitle">Sign in to your account</p>
        </div>
        
        <?php if ($success) showToast($success, 'success'); ?>
        <?php if ($error) showToast($error, 'error'); ?>
        
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Enter your username" required autofocus>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>
            
            <button type="submit" class="btn">Sign In</button>
        </form>
        
        <?php if ($discordEnabled && $discordSettings['allow_login']): ?>
        <div class="oauth-divider">
            <span>or continue with</span>
        </div>
        
        <a href="/auth/discord.php?action=login" class="btn-discord">
            <svg width="20" height="20" viewBox="0 0 71 55" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M60.1045 4.8978C55.5792 2.8214 50.7265 1.2916 45.6527 0.41542C45.5603 0.39851 45.468 0.440769 45.4204 0.525289C44.7963 1.6353 44.105 3.0834 43.6209 4.2216C38.1637 3.4046 32.7345 3.4046 27.3892 4.2216C26.905 3.0581 26.1886 1.6353 25.5617 0.525289C25.5141 0.443589 25.4218 0.40133 25.3294 0.41542C20.2584 1.2888 15.4057 2.8186 10.8776 4.8978C10.8384 4.9147 10.8048 4.9429 10.7825 4.9795C1.57795 18.7309 -0.943561 32.1443 0.293408 45.3914C0.299005 45.4562 0.335386 45.5182 0.385761 45.5576C6.45866 50.0174 12.3413 52.7249 18.1147 54.5195C18.2071 54.5477 18.305 54.5139 18.3638 54.4378C19.7295 52.5728 20.9469 50.6063 21.9907 48.5383C22.0523 48.4172 21.9935 48.2735 21.8676 48.2256C19.9366 47.4931 18.0979 46.6 16.3292 45.5858C16.1893 45.5041 16.1781 45.304 16.3068 45.2082C16.679 44.9293 17.0513 44.6391 17.4067 44.3461C17.471 44.2926 17.5606 44.2813 17.6362 44.3151C29.2558 49.6202 41.8354 49.6202 53.3179 44.3151C53.3935 44.2785 53.4831 44.2898 53.5502 44.3433C53.9057 44.6363 54.2779 44.9293 54.6529 45.2082C54.7816 45.304 54.7732 45.5041 54.6333 45.5858C52.8646 46.6197 51.0259 47.4931 49.0921 48.2228C48.9662 48.2707 48.9102 48.4172 48.9718 48.5383C50.0383 50.6034 51.2557 52.5699 52.5959 54.435C52.6519 54.5139 52.7526 54.5477 52.845 54.5195C58.6464 52.7249 64.529 50.0174 70.6019 45.5576C70.6551 45.5182 70.6887 45.459 70.6943 45.3942C72.1747 30.0791 68.2147 16.7757 60.1968 4.9823C60.1772 4.9429 60.1437 4.9147 60.1045 4.8978ZM23.7259 37.3253C20.2276 37.3253 17.3451 34.1136 17.3451 30.1693C17.3451 26.225 20.1717 23.0133 23.7259 23.0133C27.308 23.0133 30.1626 26.2532 30.1099 30.1693C30.1099 34.1136 27.2680 37.3253 23.7259 37.3253ZM47.3178 37.3253C43.8196 37.3253 40.9371 34.1136 40.9371 30.1693C40.9371 26.225 43.7680 23.0133 47.3178 23.0133C50.9003 23.0133 53.7545 26.2532 53.7018 30.1693C53.7018 34.1136 50.9003 37.3253 47.3178 37.3253Z" fill="currentColor"/>
            </svg>
            Login with Discord
        </a>
        <?php endif; ?>
        
        <div class="register-link">
            Don't have an account? <a href="register">Register here</a><br>
            <a href="forgot_password" style="color: var(--text-muted); font-size: 13px;">Forgot your password?</a>
        </div>
    </div>
<?php renderToasts(); ?>
    <div style="position:fixed;top:16px;right:16px;z-index:9999;">
        <button onclick="toggleTheme()" title="Toggle dark/light mode" style="background:var(--bg-card);border:1px solid var(--border);border-radius:8px;width:40px;height:40px;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;color:var(--text-muted);">
            <span class="theme-icon-dark">🌙</span>
            <span class="theme-icon-light">☀️</span>
        </button>
    </div>
    <script>
    function toggleTheme() {
        var root = document.documentElement;
        var newTheme = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
        root.classList.add('theme-transitioning');
        root.setAttribute('data-theme', newTheme);
        document.cookie = 'um_theme=' + newTheme + '; path=/; max-age=31536000; SameSite=Lax';
        setTimeout(function() { root.classList.remove('theme-transitioning'); }, 300);
    }
    </script>
</body>
</html>