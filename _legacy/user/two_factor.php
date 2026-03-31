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
requireLogin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';
$show_setup = false;
$secret = '';
$backup_codes = [];

// Simple TOTP implementation
function generateSecret($length = 16) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $chars[random_int(0, 31)];
    }
    return $secret;
}

function getOTP($secret, $time = null) {
    if ($time === null) $time = time();
    $time = floor($time / 30);
    
    // Decode base32
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $binary = '';
    foreach (str_split($secret) as $char) {
        $binary .= str_pad(decbin(strpos($chars, $char)), 5, '0', STR_PAD_LEFT);
    }
    $key = '';
    for ($i = 0; $i + 8 <= strlen($binary); $i += 8) {
        $key .= chr(bindec(substr($binary, $i, 8)));
    }
    
    // Calculate HMAC
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

function generateBackupCodes($count = 8) {
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $codes[] = strtoupper(bin2hex(random_bytes(4)));
    }
    return $codes;
}

// Check current 2FA status
$stmt = $conn->prepare("SELECT * FROM two_factor_codes WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$tfa = $stmt->get_result()->fetch_assoc();
$stmt->close();

$is_enabled = $tfa && $tfa['is_enabled'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'setup') {
        // Generate new secret
        $secret = generateSecret();
        $backup_codes = generateBackupCodes();
        
        $stmt = $conn->prepare("INSERT INTO two_factor_codes (user_id, secret, backup_codes) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE secret = ?, backup_codes = ?, is_enabled = 0");
        $backup_json = json_encode($backup_codes);
        $stmt->bind_param("issss", $user_id, $secret, $backup_json, $secret, $backup_json);
        $stmt->execute();
        $stmt->close();
        
        $show_setup = true;
    } elseif ($action === 'verify') {
        $code = preg_replace('/\s+/', '', $_POST['code'] ?? '');
        
        $stmt = $conn->prepare("SELECT secret FROM two_factor_codes WHERE user_id = ? AND is_enabled = 0");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $pending = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($pending && verifyOTP($pending['secret'], $code)) {
            $stmt = $conn->prepare("UPDATE two_factor_codes SET is_enabled = 1 WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
            
            $message = 'Two-factor authentication enabled successfully!';
            $is_enabled = true;
            logAudit('2fa_enable', 'user', $user_id, 'Enabled 2FA');
        } else {
            $error = 'Invalid verification code. Please try again.';
            $show_setup = true;
            $secret = $pending['secret'] ?? '';
        }
    } elseif ($action === 'disable') {
        $password = $_POST['password'] ?? '';
        
        // Verify password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($user && password_verify($password, $user['password'])) {
            $stmt = $conn->prepare("DELETE FROM two_factor_codes WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
            
            $message = 'Two-factor authentication disabled.';
            $is_enabled = false;
            $tfa = null;
            logAudit('2fa_disable', 'user', $user_id, 'Disabled 2FA');
        } else {
            $error = 'Incorrect password.';
        }
    }
}

// Get secret for QR display if in setup mode
if ($show_setup && !$secret) {
    $stmt = $conn->prepare("SELECT secret, backup_codes FROM two_factor_codes WHERE user_id = ? AND is_enabled = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $pending = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $secret = $pending['secret'] ?? '';
    $backup_codes = json_decode($pending['backup_codes'] ?? '[]', true);
}

// Get username for QR code
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$conn->close();

$community_name = urlencode(getCommunityName());
$username = urlencode($user['username'] ?? 'User');
$otpauth_url = "otpauth://totp/{$community_name}:{$username}?secret={$secret}&issuer={$community_name}";

$theme = getThemeColors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <?php include __DIR__ . '/../includes/styles.php'; ?>
    <style>
        .tfa-status { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 24px; margin-bottom: 24px; text-align: center; }
        .tfa-status.enabled { border-color: var(--success); }
        .tfa-status.disabled { border-color: #f59e0b; }
        .status-icon { font-size: 48px; margin-bottom: 12px; }
        .status-text { font-size: 18px; font-weight: 600; color: var(--text-primary); margin-bottom: 8px; }
        .status-desc { color: var(--text-muted); }
        .setup-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 24px; }
        .setup-step { margin-bottom: 24px; }
        .setup-step h3 { color: var(--text-primary); font-size: 16px; margin-bottom: 12px; }
        .secret-box { background: rgba(0,0,0,0.3); padding: 16px; border-radius: var(--radius-sm); font-family: monospace; font-size: 18px; letter-spacing: 2px; text-align: center; color: var(--text-primary); margin-bottom: 12px; word-break: break-all; }
        .backup-codes { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-top: 12px; }
        .backup-code { background: rgba(0,0,0,0.3); padding: 8px; border-radius: 4px; font-family: monospace; text-align: center; }
        .qr-placeholder { background: #fff; width: 200px; height: 200px; margin: 0 auto 16px; border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; color: #000; font-size: 12px; text-align: center; padding: 16px; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>🔐 Two-Factor Authentication</h1>
    </div>
    
    <?php if ($message) showToast($message, 'success'); ?>
    <?php if ($error) showToast($error, 'error'); ?>
    
    <?php if (!$show_setup): ?>
    <div class="tfa-status <?php echo $is_enabled ? 'enabled' : 'disabled'; ?>">
        <div class="status-icon"><?php echo $is_enabled ? '🛡️' : '⚠️'; ?></div>
        <div class="status-text">Two-Factor Authentication is <?php echo $is_enabled ? 'Enabled' : 'Disabled'; ?></div>
        <div class="status-desc"><?php echo $is_enabled ? 'Your account is protected with an additional layer of security.' : 'Add an extra layer of security to your account.'; ?></div>
    </div>
    
    <?php if ($is_enabled): ?>
    <div class="setup-card">
        <h3 style="color:#fff;margin-bottom:16px;">Disable Two-Factor Authentication</h3>
        <p style="color:var(--text-secondary);margin-bottom:16px;">To disable 2FA, enter your password below.</p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
            <input type="hidden" name="action" value="disable">
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to disable 2FA?');">Disable 2FA</button>
        </form>
    </div>
    <?php else: ?>
    <div class="setup-card">
        <h3 style="color:#fff;margin-bottom:16px;">Enable Two-Factor Authentication</h3>
        <p style="color:var(--text-secondary);margin-bottom:16px;">Use an authenticator app like Google Authenticator, Authy, or 1Password to add an extra layer of security.</p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
            <input type="hidden" name="action" value="setup">
            <button type="submit" class="btn btn-primary">Set Up 2FA</button>
        </form>
    </div>
    <?php endif; ?>
    
    <?php else: ?>
    <!-- Setup Flow -->
    <div class="setup-card">
        <div class="setup-step">
            <h3>Step 1: Scan QR Code or Enter Secret</h3>
            <p style="color:var(--text-secondary);margin-bottom:16px;">Open your authenticator app and scan the QR code, or manually enter the secret key.</p>
            <div id="qrcode" style="background:#fff;padding:16px;border-radius:12px;display:inline-block;margin-bottom:16px;"></div>
            <script>
                new QRCode(document.getElementById("qrcode"), {
                    text: <?php echo json_encode($otpauth_url); ?>,
                    width: 180,
                    height: 180,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.M
                });
            </script>
            <div class="secret-box"><?php echo htmlspecialchars($secret); ?></div>
            <p style="color:var(--text-muted);font-size:12px;text-align:center;">Manual entry: Add a new account, select "Enter key manually", and use this secret.</p>
        </div>
        
        <div class="setup-step">
            <h3>Step 2: Save Backup Codes</h3>
            <p style="color:var(--text-secondary);margin-bottom:12px;">Save these backup codes in a safe place. You can use them to access your account if you lose your authenticator.</p>
            <div class="backup-codes">
                <?php foreach ($backup_codes as $code): ?>
                    <div class="backup-code"><?php echo htmlspecialchars($code); ?></div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="setup-step">
            <h3>Step 3: Verify Setup</h3>
            <p style="color:var(--text-secondary);margin-bottom:12px;">Enter the 6-digit code from your authenticator app to verify setup.</p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                <input type="hidden" name="action" value="verify">
                <div class="form-group">
                    <input type="text" name="code" class="form-control" placeholder="000000" maxlength="6" pattern="\d{6}" required style="font-size:24px;letter-spacing:8px;text-align:center;max-width:200px;">
                </div>
                <button type="submit" class="btn btn-primary">Verify & Enable</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
