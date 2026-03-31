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
require_once '../config.php';
if (!defined('UM_FUNCTIONS_LOADED')) { require_once __DIR__ . '/../includes/functions.php'; }
if (!defined('UM_EMAIL_LOADED')) { require_once __DIR__ . '/../includes/email.php'; }
if (!defined('TOAST_LOADED')) { require_once __DIR__ . '/../includes/toast.php'; define('TOAST_LOADED', true); }

// Must be logged in AND have must_change_password flag
if (!isset($_SESSION['user_id']) || empty($_SESSION['must_change_password'])) {
    header('Location: ../');
    exit();
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get current user info
$stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken()) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $new_username = trim($_POST['new_username'] ?? '');
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($new_username) || empty($new_password)) {
            $error = 'Username and password are required.';
        } elseif (strlen($new_username) < 3 || strlen($new_username) > 50) {
            $error = 'Username must be between 3 and 50 characters.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $new_username)) {
            $error = 'Username can only contain letters, numbers, and underscores.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            $password_errors = validatePassword($new_password);
            if (!empty($password_errors)) {
                $error = implode(' ', $password_errors);
            } else {
            // Check if username is already taken (by someone else)
            $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt_check->bind_param("si", $new_username, $user_id);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                $error = 'That username is already taken. Please choose another.';
            } else {
                // Update credentials and clear the must_change_password flag
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_update = $conn->prepare("UPDATE users SET username = ?, password = ?, must_change_password = FALSE WHERE id = ?");
                $stmt_update->bind_param("ssi", $new_username, $hashed, $user_id);
                $stmt_update->execute();
                $stmt_update->close();
                
                // Update session
                $_SESSION['username'] = $new_username;
                unset($_SESSION['must_change_password']);
                
                logAudit('account_setup', 'user', $user_id, 'User completed initial account setup');
                
                // Send confirmation email
                $community = getCommunityName();
                $site_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
                $conf_body = "<p style=\"margin: 0 0 16px 0;\">Hi <strong>" . htmlspecialchars($new_username) . "</strong>,</p>"
                    . "<p style=\"margin: 0 0 20px 0;\">Your account has been set up successfully. Your new username is <strong style=\"font-family: monospace;\">" . htmlspecialchars($new_username) . "</strong>.</p>"
                    . "<p style=\"margin: 0; color: #94a3b8;\">If you did not make this change, please contact an administrator immediately.</p>";
                sendEmail($user['email'], "Account Setup Complete - " . $community, $conf_body, [
                    'icon' => '✅',
                    'header_title' => 'Account Ready!',
                    'cta_text' => 'Go to Dashboard',
                    'cta_url' => $site_url,
                    'preheader' => 'Your account setup is complete.'
                ]);
                
                header('Location: ../');
                exit();
            }
            $stmt_check->close();
            } // Close password validation else
        }
    }
}

// Theme colors
$theme = function_exists('getThemeColors') ? getThemeColors() : [
    'primary' => 'var(--accent)', 'secondary' => 'var(--accent-hover)',
    
    'bg_image' => ''
];
$community_name = function_exists('getCommunityName') ? getCommunityName() : 'Community';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Up Your Account - <?php echo htmlspecialchars($community_name); ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo defined('FAVICON_PATH') ? FAVICON_PATH : '/favicon.ico'; ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>(function(){var m=document.cookie.match(/\bum_theme=([^;]+)/);document.documentElement.setAttribute('data-theme',m?m[1]:'dark');})();</script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --bg-base: #0a0a0a;
            --bg-card: #161616;
            --bg-elevated: #1c1c1c;
            --bg-input: #1a1a1a;
            --border: #2a2a2a;
            --accent: #5865F2;
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

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-base);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-primary);
            padding: 24px;
            -webkit-font-smoothing: antialiased;
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
        
        .setup-container {
            width: 100%;
            max-width: 460px;
        }
        .setup-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 40px;
            position: relative;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
        }
        .setup-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent), #7c3aed);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        .setup-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--accent), #7c3aed);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin: 0 auto 20px;
            box-shadow: 0 8px 24px rgba(88, 101, 242, 0.3);
        }
        h1 {
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }
        .subtitle {
            text-align: center;
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 28px;
            line-height: 1.5;
        }
        .current-info {
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 16px;
            margin-bottom: 24px;
            font-size: 13px;
            color: var(--text-muted);
        }
        .current-info strong { color: var(--text-secondary); }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-secondary);
        }
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--bg-elevated);
            border-radius: var(--radius-md);
            background: var(--bg-card);
            color: var(--text-primary);
            font-size: 15px;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: <?php echo $theme['primary']; ?>;
        }
        .form-group .hint {
            font-size: 11px;
            color: var(--bg-elevated);
            margin-top: 6px;
        }
        .error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
            padding: 14px 16px;
            border-radius: var(--radius-md);
            font-size: 14px;
            margin-bottom: 20px;
        }
        .btn-setup {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, <?php echo $theme['primary']; ?>, <?php echo $theme['secondary']; ?>);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 8px;
        }
        .btn-setup:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(88, 101, 242, 0.3);
        }
        .warning-note {
            background: rgba(251, 191, 36, 0.08);
            border: 1px solid rgba(251, 191, 36, 0.2);
            border-radius: var(--radius-md);
            padding: 12px 16px;
            margin-top: 20px;
            font-size: 12px;
            color: rgba(251, 191, 36, 0.8);
            text-align: center;
        }
    
    
    /* Custom Scrollbar */
    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }
    ::-webkit-scrollbar-track {
        background: var(--bg-card);
    }
    ::-webkit-scrollbar-thumb {
        background: var(--border);
        border-radius: 4px;
    }
    ::-webkit-scrollbar-thumb:hover {
        background: var(--bg-elevated);
    }
    </style>
</head>
<body>
    <?php showPageToasts(); ?>
    <div class="setup-container">
        <div class="setup-card">
            <div class="setup-icon">🔐</div>
            <h1>Set Up Your Account</h1>
            <p class="subtitle">Please choose a username and password to complete your account setup.</p>

            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="current-info">
                Logged in as: <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                &nbsp;·&nbsp; Email: <strong><?php echo htmlspecialchars($user['email']); ?></strong>
            </div>

            <form method="POST" action="">
                <?php echo csrfField(); ?>

                <div class="form-group">
                    <label for="new_username">Username</label>
                    <input type="text" id="new_username" name="new_username"
                           value="<?php echo htmlspecialchars($_POST['new_username'] ?? $user['username']); ?>"
                           placeholder="Choose a username" required autofocus
                           minlength="3" maxlength="50" pattern="[a-zA-Z0-9_]+">
                    <div class="hint">3–50 characters. Letters, numbers, and underscores only.</div>
                </div>

                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password"
                           placeholder="Choose a strong password" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           placeholder="Re-enter your password" required>
                </div>

                <button type="submit" class="btn-setup">Complete Setup</button>
            </form>

            <div class="warning-note">
                You must complete this setup before accessing the site.
            </div>
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
