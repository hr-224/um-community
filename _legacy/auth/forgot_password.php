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
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config.php';
if (!defined('UM_FUNCTIONS_LOADED')) { require_once __DIR__ . '/../includes/functions.php'; }
if (!defined('UM_EMAIL_LOADED')) { require_once __DIR__ . '/../includes/email.php'; }
if (!defined('TOAST_LOADED')) { require_once __DIR__ . '/../includes/toast.php'; define('TOAST_LOADED', true); }

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken()) {
    try {
        $email = trim($_POST['email']);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        if (empty($email)) {
            $error = 'Please enter your email address.';
        } else {
            // Check rate limit with error handling
            $rate_ok = true;
            try {
                $rate_ok = checkRateLimit('password_reset', $ip, 5, 60);
            } catch (Exception $e) {
                $rate_ok = true; // Allow if rate limit check fails
            }
            
            if (!$rate_ok) {
                // Don't reveal rate limiting - show same message as success
                $message = 'If that email exists, password reset instructions have been sent.';
            } else {
                // Try to record rate limit action
                try {
                    recordRateLimitAction('password_reset', $ip);
                } catch (Exception $e) {
                    // Ignore errors
                }
                
                $conn = getDBConnection();
                $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ? AND is_approved = TRUE");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    $stmt->close();
                    
                    // Generate reset token
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
                    $stmt->bind_param("ssi", $token, $expires, $user['id']);
                    $stmt->execute();
                    $stmt->close();
                    $conn->close();
                    
                    // Send email
                    $reset_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://" . $_SERVER['HTTP_HOST'] . "/auth/reset_password?token=$token";
                    $subject = "Password Reset Request - " . getCommunityName();
                    $body = "<p style=\"margin: 0 0 16px 0;\">Hello <strong>{$user['username']}</strong>,</p>"
                          . "<p style=\"margin: 0 0 16px 0;\">We received a request to reset your password for your " . htmlspecialchars(getCommunityName()) . " account.</p>"
                          . "<p style=\"margin: 0 0 16px 0;\">Click the button below to set a new password. This link will expire in <strong>1 hour</strong>.</p>"
                          . "<p style=\"margin: 20px 0 0 0; padding: 16px; background: var(--bg-card); border-radius: var(--radius-sm); border-left: 3px solid #f59e0b; font-size: 13px; color: #94a3b8;\">If you didn't request this reset, you can safely ignore this email. Your password will remain unchanged.</p>";
                    
                    if (sendEmail($email, $subject, $body, [
                        'icon' => '🔐',
                        'header_title' => 'Password Reset',
                        'cta_text' => 'Reset My Password',
                        'cta_url' => $reset_link,
                        'footer_note' => 'This link expires in 1 hour. If the button doesn\'t work, copy and paste this URL into your browser: ' . htmlspecialchars($reset_link)
                    ])) {
                        $message = 'Password reset instructions have been sent to your email.';
                        try {
                            logAudit('password_reset_request', 'user', $user['id'], 'Requested password reset');
                        } catch (Exception $e) {
                            // Ignore audit errors
                        }
                    } else {
                        $error = 'Unable to send email. Please contact an administrator.';
                    }
                } else {
                    // Don't reveal if email exists
                    $stmt->close();
                    $conn->close();
                    $message = 'If that email exists, password reset instructions have been sent.';
                }
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
    <title>Forgot Password - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <?php $colors = getThemeColors(); ?>
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
            --accent: <?php echo $colors['primary'] ?? '#5865F2'; ?>;
            --accent-hover: #4752c4;
            --accent-muted: rgba(88, 101, 242, 0.15);
            --success: #23a559;
            --danger: #da373c;
            --text-primary: #f2f3f5;
            --text-secondary: #b5bac1;
            --text-muted: #80848e;
            --text-faint: #4e5058;
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
            --text-faint: #80848e;
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
        
        .container {
            background: var(--bg-card);
            border: 1px solid var(--border);
            padding: 40px;
            border-radius: var(--radius-lg);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 1;
            animation: fadeIn 0.5s ease;
        }
        
        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent), #7c3aed);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        
        h1 {
            color: var(--text-primary);
            font-size: 24px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }
        
        .subtitle {
            text-align: center;
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 24px;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        label {
            display: block;
            margin-bottom: 10px;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 14px;
        }
        
        input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid var(--bg-elevated);
            border-radius: var(--radius-md);
            font-size: 15px;
            transition: all 0.3s ease;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        input::placeholder {
            color: var(--text-faint);
        }
        
        input:focus {
            outline: none;
            border-color: var(--accent);
            background: var(--bg-elevated);
            box-shadow: 0 0 0 4px var(--accent-muted);
        }
        
        .btn {
            width: 100%;
            padding: 16px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 24px var(--accent-muted);
            margin-top: 8px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px var(--accent-muted);
        }
        
        .error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2) 0%, rgba(220, 38, 38, 0.2) 100%);
            color: #f87171;
            padding: 16px 20px;
            border-radius: var(--radius-md);
            margin-bottom: 24px;
            border-left: 4px solid var(--danger);
            font-size: 14px;
            animation: fadeIn 0.4s ease;
        }
        
        .success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(5, 150, 105, 0.2) 100%);
            color: #4ade80;
            padding: 16px 20px;
            border-radius: var(--radius-md);
            margin-bottom: 24px;
            border-left: 4px solid var(--success);
            font-size: 14px;
            animation: fadeIn 0.4s ease;
        }
        
        .back-link {
            text-align: center;
            margin-top: 28px;
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .back-link a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 700;
            transition: all 0.3s ease;
            padding: 4px 8px;
            border-radius: 6px;
        }
        
        .back-link a:hover {
            color: var(--text-primary);
            background: var(--accent-muted);
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 32px 24px;
            }
            h1 {
                font-size: 26px;
            }
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
    <div class="container">
        <h1>🔒 Forgot Password</h1>
        <p class="subtitle">Enter your email to reset your password</p>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="your@email.com" required autofocus>
            </div>
            
            <button type="submit" class="btn">Send Reset Link</button>
        </form>
        
        <div class="back-link">
            <a href="login">← Back to Login</a>
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