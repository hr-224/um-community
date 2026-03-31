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
ob_start(); // Prevent "headers already sent" errors
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to users
ini_set('log_errors', 1); // Log errors instead

require_once '../config.php';
if (!defined('UM_FUNCTIONS_LOADED')) { require_once __DIR__ . '/../includes/functions.php'; }
if (!defined('UM_EMAIL_LOADED')) { require_once __DIR__ . '/../includes/email.php'; }
if (!defined('TOAST_LOADED')) { require_once __DIR__ . '/../includes/toast.php'; define('TOAST_LOADED', true); }
if (file_exists(__DIR__ . '/../includes/discord.php')) { require_once __DIR__ . '/../includes/discord.php'; }

// Check if Discord is available
$discordEnabled = function_exists('isDiscordConfigured') && isDiscordConfigured();
$discordSettings = $discordEnabled ? getDiscordSettings() : ['allow_registration' => false];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken()) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        // Rate limit registration attempts (10 per hour per IP)
        $rate_ok = true;
        try {
            $rate_ok = checkRateLimit('register', $ip, 10, 60);
        } catch (Exception $e) {
            // If rate limit check fails, allow registration to proceed
            $rate_ok = true;
        }
        
        if (!$rate_ok) {
            $error = 'Too many registration attempts. Please try again later.';
        } else {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $discord_id = trim($_POST['discord_id'] ?? '');

            if (empty($username) || empty($email) || empty($password)) {
                $error = 'All required fields must be filled out.';
            } elseif ($password !== $confirm_password) {
                $error = 'Passwords do not match.';
            } else {
                // Validate password against policy
                $password_errors = validatePassword($password);
                if (!empty($password_errors)) {
                    $error = implode(' ', $password_errors);
                } else {
                // Try to record rate limit action
                try {
                    recordRateLimitAction('register', $ip);
                } catch (Exception $e) {
                    // Ignore rate limit recording errors
                }
                
                $conn = getDBConnection();
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Check for existing email
                $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                if (!$checkStmt) {
                    $error = 'Registration failed. Please try again.';
                } else {
                    $checkStmt->bind_param("s", $email);
                    $checkStmt->execute();
                    $checkStmt->store_result();

                    if ($checkStmt->num_rows > 0) {
                        $error = 'Email is already registered. Please use another email.';
                        $checkStmt->close();
                        $conn->close();
                    } else {
                        $checkStmt->close();
                        
                        // Insert new user
                        $stmt = $conn->prepare("INSERT INTO users (username, email, password, discord_id, is_approved) VALUES (?, ?, ?, ?, ?)");
                        if (!$stmt) {
                            $error = 'Registration failed. Please try again.';
                            $conn->close();
                        } else {
                            $require_approval = getSetting('registration_require_approval', '1');
                            $auto_approve = ($require_approval === '0') ? 1 : 0;
                            $stmt->bind_param("ssssi", $username, $email, $hashed_password, $discord_id, $auto_approve);

                            if ($stmt->execute()) {
                                $new_user_id = $stmt->insert_id;
                                $stmt->close();
                                $conn->close();
                                
                                // Try to log audit
                                try {
                                    logAudit('user_registration', 'user', $new_user_id, "User registered: $username" . ($auto_approve ? ' (auto-approved)' : ' (pending approval)'));
                                } catch (Exception $e) {
                                    // Ignore audit log errors
                                }
                                
                                // Send appropriate welcome email
                                try {
                                    if ($auto_approve) {
                                        sendWelcomeEmail($email, $username);
                                    } else {
                                        sendPendingApprovalEmail($email, $username);
                                    }
                                } catch (Exception $e) {
                                    // Don't fail registration if email fails
                                    error_log("Failed to send registration email: " . $e->getMessage());
                                }
                                
                                // Clear output buffer before redirect
                                ob_end_clean();
                                
                                // Redirect to login with appropriate message
                                if ($auto_approve) {
                                    header('Location: /auth/login?registered=2');
                                } else {
                                    header('Location: /auth/login?registered=1');
                                }
                                exit;
                            } else {
                                if ($conn->errno === 1062) {
                                    $error = 'Username or email already exists.';
                                } else {
                                    $error = 'Registration failed. Please try again.';
                                }
                                $stmt->close();
                                $conn->close();
                            }
                        }
                    }
                }
                } // Close password validation else block
            }
        }
    } catch (Exception $e) {
        $error = 'An unexpected error occurred. Please try again.';
    } catch (Error $e) {
        $error = 'An unexpected error occurred. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<?php $colors = getThemeColors(); ?>
<script>(function(){var m=document.cookie.match(/\bum_theme=([^;]+)/);document.documentElement.setAttribute('data-theme',m?m[1]:'dark');})();</script>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    :root {
        --bg-base: #0a0a0a;
        --bg-card: #161616;
        --bg-elevated: #1c1c1c;
        --bg-input: #1a1a1a;
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
    
    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        background: var(--bg-base);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        -webkit-font-smoothing: antialiased;
        color: var(--text-primary);
    }
    
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
    
    body::after {
        content: '';
        position: fixed;
        width: 600px;
        height: 600px;
        background: radial-gradient(circle, rgba(88, 101, 242, 0.08) 0%, transparent 70%);
        top: -200px;
        right: -200px;
        pointer-events: none;
    }
    
    .register-container {
        background: var(--bg-card);
        border: 1px solid var(--border);
        padding: 40px;
        border-radius: var(--radius-lg);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
        width: 100%;
        max-width: 440px;
        position: relative;
        z-index: 1;
        animation: fadeIn 0.5s ease;
    }
    
    .register-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--accent), #7c3aed);
        border-radius: var(--radius-lg) var(--radius-lg) 0 0;
    }
    
    .logo-section {
        text-align: center;
        margin-bottom: 28px;
    }
    
    .logo-icon {
        width: 56px;
        height: 56px;
        background: linear-gradient(135deg, var(--accent), #7c3aed);
        border-radius: 14px;
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
        object-fit: contain;
    }
    
    h1 { 
        color: var(--text-primary); 
        font-size: 22px;
        font-weight: 700;
        margin-bottom: 8px;
        letter-spacing: -0.02em;
    }
    
    .subtitle {
        color: var(--text-muted);
        font-size: 14px;
    }
    
    .form-group { 
        margin-bottom: 20px;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
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
    
    .error { 
        background: rgba(218, 55, 60, 0.15);
        color: #f87171;
        padding: 14px 16px;
        border-radius: var(--radius-md);
        margin-bottom: 20px;
        border-left: 3px solid var(--danger);
        font-size: 14px;
    }
    
    .success { 
        background: rgba(35, 165, 89, 0.15);
        color: #4ade80;
        padding: 14px 16px;
        border-radius: var(--radius-md);
        margin-bottom: 20px;
        border-left: 3px solid var(--success);
        font-size: 14px;
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
    }
    
    .register-link a:hover {
        text-decoration: underline;
    }
    
    /* Discord OAuth */
    .oauth-divider {
        display: flex;
        align-items: center;
        gap: 16px;
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
    
    @media (max-width: 480px) {
        .register-container {
            padding: 32px 24px;
        }
        h1 {
            font-size: 22px;
        }
        .form-row {
            grid-template-columns: 1fr;
        }
    }
</style>
</head>
<body>
<?php 
$logo_path = getSetting('community_logo', '');
$has_logo = !empty($logo_path) && file_exists($_SERVER['DOCUMENT_ROOT'] . $logo_path);
?>
<div class="register-container">
    <div class="logo-section">
        <?php if ($has_logo): ?>
            <div class="logo-icon" style="background: var(--bg-elevated);">
                <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo">
            </div>
        <?php else: ?>
            <div class="logo-icon">🎮</div>
        <?php endif; ?>
        <h1><?php echo htmlspecialchars(COMMUNITY_NAME); ?></h1>
        <p class="subtitle">Create your account</p>
        <?php if (getSetting('registration_require_approval', '1') === '1'): ?>
            <p style="font-size: 12px; color: var(--text-muted); margin-top: 8px;">Registration requires staff approval</p>
        <?php endif; ?>
    </div>

    <?php showPageToasts(); ?>

    <form method="POST" action="">
        <?php echo csrfField(); ?>
        <div class="form-group">
            <label>Username *</label>
            <input type="text" name="username" placeholder="Choose a username" required>
        </div>

        <div class="form-group">
            <label>Email *</label>
            <input type="email" name="email" placeholder="your@email.com" required>
        </div>

        <div class="form-group">
            <label>Discord ID <span style="font-weight: 400; color: var(--text-muted);">(optional)</span></label>
            <input type="text" name="discord_id" placeholder="username#0000">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="password" placeholder="Min 8 characters" required>
            </div>
            <div class="form-group">
                <label>Confirm Password *</label>
                <input type="password" name="confirm_password" placeholder="Re-enter password" required>
            </div>
        </div>

        <button type="submit" class="btn">Create Account</button>
    </form>

    <?php if ($discordEnabled && $discordSettings['allow_registration']): ?>
    <div class="oauth-divider">
        <span>or register with</span>
    </div>
    
    <a href="/auth/discord.php?action=register" class="btn-discord">
        <svg width="20" height="20" viewBox="0 0 71 55" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M60.1045 4.8978C55.5792 2.8214 50.7265 1.2916 45.6527 0.41542C45.5603 0.39851 45.468 0.440769 45.4204 0.525289C44.7963 1.6353 44.105 3.0834 43.6209 4.2216C38.1637 3.4046 32.7345 3.4046 27.3892 4.2216C26.905 3.0581 26.1886 1.6353 25.5617 0.525289C25.5141 0.443589 25.4218 0.40133 25.3294 0.41542C20.2584 1.2888 15.4057 2.8186 10.8776 4.8978C10.8384 4.9147 10.8048 4.9429 10.7825 4.9795C1.57795 18.7309 -0.943561 32.1443 0.293408 45.3914C0.299005 45.4562 0.335386 45.5182 0.385761 45.5576C6.45866 50.0174 12.3413 52.7249 18.1147 54.5195C18.2071 54.5477 18.305 54.5139 18.3638 54.4378C19.7295 52.5728 20.9469 50.6063 21.9907 48.5383C22.0523 48.4172 21.9935 48.2735 21.8676 48.2256C19.9366 47.4931 18.0979 46.6 16.3292 45.5858C16.1893 45.5041 16.1781 45.304 16.3068 45.2082C16.679 44.9293 17.0513 44.6391 17.4067 44.3461C17.471 44.2926 17.5606 44.2813 17.6362 44.3151C29.2558 49.6202 41.8354 49.6202 53.3179 44.3151C53.3935 44.2785 53.4831 44.2898 53.5502 44.3433C53.9057 44.6363 54.2779 44.9293 54.6529 45.2082C54.7816 45.304 54.7732 45.5041 54.6333 45.5858C52.8646 46.6197 51.0259 47.4931 49.0921 48.2228C48.9662 48.2707 48.9102 48.4172 48.9718 48.5383C50.0383 50.6034 51.2557 52.5699 52.5959 54.435C52.6519 54.5139 52.7526 54.5477 52.845 54.5195C58.6464 52.7249 64.529 50.0174 70.6019 45.5576C70.6551 45.5182 70.6887 45.459 70.6943 45.3942C72.1747 30.0791 68.2147 16.7757 60.1968 4.9823C60.1772 4.9429 60.1437 4.9147 60.1045 4.8978ZM23.7259 37.3253C20.2276 37.3253 17.3451 34.1136 17.3451 30.1693C17.3451 26.225 20.1717 23.0133 23.7259 23.0133C27.308 23.0133 30.1626 26.2532 30.1099 30.1693C30.1099 34.1136 27.2680 37.3253 23.7259 37.3253ZM47.3178 37.3253C43.8196 37.3253 40.9371 34.1136 40.9371 30.1693C40.9371 26.225 43.7680 23.0133 47.3178 23.0133C50.9003 23.0133 53.7545 26.2532 53.7018 30.1693C53.7018 34.1136 50.9003 37.3253 47.3178 37.3253Z" fill="currentColor"/>
        </svg>
        Register with Discord
    </a>
    <?php endif; ?>

    <div class="register-link">
        Already have an account? <a href="login">Login here</a>
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