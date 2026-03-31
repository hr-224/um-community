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
if (!defined('TOAST_LOADED')) { require_once __DIR__ . '/../includes/toast.php'; define('TOAST_LOADED', true); }

// Check if there's a pending 2FA verification
if (!isset($_SESSION['pending_2fa_user_id'])) {
    header('Location: /auth/login');
    exit();
}

$error = '';
$user_id = $_SESSION['pending_2fa_user_id'];

// TOTP functions (same as in two_factor.php)
function getOTP($secret, $time = null) {
    if ($time === null) $time = time();
    $time = floor($time / 30);
    
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $binary = '';
    foreach (str_split($secret) as $char) {
        $binary .= str_pad(decbin(strpos($chars, $char)), 5, '0', STR_PAD_LEFT);
    }
    $key = '';
    for ($i = 0; $i + 8 <= strlen($binary); $i += 8) {
        $key .= chr(bindec(substr($binary, $i, 8)));
    }
    
    $time = str_pad(pack('N', $time), 8, "\0", STR_PAD_LEFT);
    $hash = hash_hmac('sha1', $time, $key, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $code = (
        ((ord($hash[$offset]) & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8) |
        (ord($hash[$offset + 3]) & 0xFF)
    ) % 1000000;
    
    return str_pad($code, 6, '0', STR_PAD_LEFT);
}

function verifyOTP($secret, $code, $window = 1) {
    $time = time();
    for ($i = -$window; $i <= $window; $i++) {
        if (getOTP($secret, $time + ($i * 30)) === $code) {
            return true;
        }
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken()) {
        $error = 'Invalid request. Please try again.';
    } else {
        $code = trim($_POST['code'] ?? '');
        $use_backup = isset($_POST['use_backup']);
        
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT secret, backup_codes FROM two_factor_codes WHERE user_id = ? AND is_enabled = 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $tfa = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $verified = false;
        
        if ($tfa) {
            if ($use_backup) {
                // Check backup code
                $backup_codes = json_decode($tfa['backup_codes'], true) ?: [];
                $code_upper = strtoupper($code);
                if (in_array($code_upper, $backup_codes)) {
                    $verified = true;
                    // Remove used backup code
                    $backup_codes = array_diff($backup_codes, [$code_upper]);
                    $new_codes = json_encode(array_values($backup_codes));
                    $update = $conn->prepare("UPDATE two_factor_codes SET backup_codes = ? WHERE user_id = ?");
                    $update->bind_param("si", $new_codes, $user_id);
                    $update->execute();
                    $update->close();
                }
            } else {
                // Verify TOTP code
                $verified = verifyOTP($tfa['secret'], $code);
            }
        }
        
        if ($verified) {
            // Complete login
            session_regenerate_id(true);
            $_SESSION['user_id'] = $_SESSION['pending_2fa_user_id'];
            $_SESSION['username'] = $_SESSION['pending_2fa_username'];
            $_SESSION['is_admin'] = $_SESSION['pending_2fa_is_admin'];
            $_SESSION['is_approved'] = $_SESSION['pending_2fa_is_approved'];
            
            // Clear pending 2FA data
            unset($_SESSION['pending_2fa_user_id']);
            unset($_SESSION['pending_2fa_username']);
            unset($_SESSION['pending_2fa_is_admin']);
            unset($_SESSION['pending_2fa_is_approved']);
            
            // Track login
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $location = function_exists('getLocationFromIP') ? getLocationFromIP($ip) : '';
            
            $histStmt = $conn->prepare("INSERT INTO login_history (user_id, ip_address, user_agent, location, success) VALUES (?, ?, ?, ?, 1)");
            if ($histStmt) {
                $histStmt->bind_param("isss", $_SESSION['user_id'], $ip, $ua, $location);
                $histStmt->execute();
                $histStmt->close();
            }
            
            // Detect and process login anomalies
            if (function_exists('detectLoginAnomalies')) {
                $anomalies = detectLoginAnomalies($_SESSION['user_id'], $ip, $ua);
                if (!empty($anomalies)) {
                    processLoginAnomalies($_SESSION['user_id'], $anomalies, $ip, $ua);
                }
            }
            
            // Create session
            $session_token = bin2hex(random_bytes(32));
            $_SESSION['session_token'] = $session_token;
            $sessStmt = $conn->prepare("INSERT INTO active_sessions (user_id, session_token, ip_address, user_agent) VALUES (?, ?, ?, ?)");
            if ($sessStmt) {
                $sessStmt->bind_param("isss", $_SESSION['user_id'], $session_token, $ip, $ua);
                $sessStmt->execute();
                $sessStmt->close();
            }
            
            logAudit('user_login', 'user', $_SESSION['user_id'], 'User logged in with 2FA');
            
            // Check if must change password
            if (!empty($_SESSION['pending_2fa_must_change'])) {
                unset($_SESSION['pending_2fa_must_change']);
                $_SESSION['must_change_password'] = true;
                $conn->close();
                header('Location: /auth/setup_account');
                exit();
            }
            
            $conn->close();
            header('Location: /');
            exit();
        } else {
            $error = $use_backup ? 'Invalid backup code.' : 'Invalid verification code.';
        }
        
        $conn->close();
    }
}

$colors = getThemeColors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
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
            --radius-sm: 6px;
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
        .container {
            background: var(--bg-card);
            border: 1px solid var(--border);
            padding: 40px;
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 400px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            position: relative;
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
        h1 { color: var(--text-primary); font-size: 22px; font-weight: 700; text-align: center; margin-bottom: 8px; letter-spacing: -0.02em; }
        .subtitle { color: var(--text-muted); text-align: center; margin-bottom: 28px; font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; color: var(--text-secondary); margin-bottom: 8px; font-size: 13px; font-weight: 600; }
        input[type="text"] {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            background: var(--bg-input);
            color: var(--text-primary);
            font-size: 18px;
            text-align: center;
            letter-spacing: 8px;
            font-family: inherit;
        }
        input[type="text"]:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-muted); }
        input[type="text"]::placeholder { letter-spacing: normal; color: var(--text-muted); }
        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            background: var(--accent);
            color: var(--text-primary);
            margin-bottom: 12px;
        }
        .btn:hover { opacity: 0.9; }
        .btn-secondary {
            background: var(--bg-elevated);
            border: 1px solid var(--border);
        }
        .error {
            background: rgba(239,68,68,0.2);
            border: 1px solid var(--danger);
            color: #f87171;
            padding: 12px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            text-align: center;
        }
        .divider {
            text-align: center;
            color: var(--text-muted);
            margin: 20px 0;
            font-size: 13px;
        }
        .back-link {
            display: block;
            text-align: center;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 14px;
            margin-top: 20px;
        }
        .back-link:hover { color: var(--text-primary); }
        .icon { font-size: 48px; text-align: center; margin-bottom: 20px; }
        
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
        <div class="icon">🔐</div>
        <h1>Two-Factor Authentication</h1>
        <p class="subtitle">Enter the code from your authenticator app</p>
        
        <?php showPageToasts(); ?>
        
        <form method="POST" id="totpForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
            <div class="form-group">
                <label>Verification Code</label>
                <input type="text" name="code" maxlength="8" placeholder="000000" autocomplete="off" autofocus required>
            </div>
            <button type="submit" class="btn">Verify</button>
        </form>
        
        <div class="divider">— or —</div>
        
        <form method="POST" id="backupForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
            <input type="hidden" name="use_backup" value="1">
            <div class="form-group">
                <label>Backup Code</label>
                <input type="text" name="code" maxlength="8" placeholder="XXXXXXXX" autocomplete="off">
            </div>
            <button type="submit" class="btn btn-secondary">Use Backup Code</button>
        </form>
        
        <a href="/auth/login" class="back-link">← Back to Login</a>
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
