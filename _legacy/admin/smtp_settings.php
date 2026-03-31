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
if (!defined('UM_EMAIL_LOADED')) { require_once __DIR__ . '/../includes/email.php'; }
requireAdmin(); // Only admins can access SMTP settings

$conn = getDBConnection();
$message = '';
$error = '';

// Handle test email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['test_email'])) {
    $test_to = trim($_POST['test_email_address']);
    if (filter_var($test_to, FILTER_VALIDATE_EMAIL)) {
        $test_body = "<p style=\"margin: 0 0 16px 0;\">This is a test email from <strong>" . htmlspecialchars(getCommunityName()) . "</strong>.</p>"
                   . "<p style=\"margin: 0 0 16px 0;\">If you're reading this, your SMTP settings are configured correctly and emails are being delivered successfully! ✅</p>"
                   . "<table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" width=\"100%\" style=\"margin: 20px 0; background: rgba(34,197,94,0.08); border-radius: var(--radius-md); border: 1px solid rgba(34,197,94,0.2);\">"
                   . "<tr><td style=\"padding: 16px 20px; text-align: center;\">"
                   . "<p style=\"margin: 0; color: #22c55e; font-weight: 600; font-size: 14px;\">✓ SMTP Connection Successful</p>"
                   . "<p style=\"margin: 6px 0 0 0; color: #94a3b8; font-size: 12px;\">Sent at " . date('Y-m-d H:i:s T') . "</p>"
                   . "</td></tr></table>";
        $result = sendEmail($test_to, 'SMTP Test - ' . getCommunityName(), $test_body, [
            'icon' => '✉️',
            'header_title' => 'SMTP Test Email',
            'footer_note' => 'This was a test email triggered from the admin panel.'
        ]);
        if ($result) {
            $message = 'Test email sent successfully! Check your inbox.';
        } else {
            $error = 'Failed to send test email. Please check your SMTP settings.';
        }
    } else {
        $error = 'Please enter a valid email address.';
    }
}

// Handle SMTP configuration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['save_smtp'])) {
    $host = trim($_POST['smtp_host']);
    $port = intval($_POST['smtp_port']);
    $username = trim($_POST['smtp_username']);
    $password = $_POST['smtp_password'];
    $from_email = trim($_POST['smtp_from_email']);
    $from_name = trim($_POST['smtp_from_name']);
    $encryption = $_POST['smtp_encryption'];
    
    // Check if settings exist
    $existing = $conn->query("SELECT id FROM smtp_settings LIMIT 1");
    $existingRow = $existing ? $existing->fetch_assoc() : null;
    
    if ($existingRow) {
        // Update existing
        $stmt = $conn->prepare("UPDATE smtp_settings SET smtp_host = ?, smtp_port = ?, smtp_username = ?, smtp_password = ?, smtp_from_email = ?, smtp_from_name = ?, smtp_encryption = ?, is_active = TRUE WHERE id = ?");
        $stmt->bind_param("sisssssi", $host, $port, $username, $password, $from_email, $from_name, $encryption, $existingRow['id']);
    } else {
        // Insert new
        $stmt = $conn->prepare("INSERT INTO smtp_settings (smtp_host, smtp_port, smtp_username, smtp_password, smtp_from_email, smtp_from_name, smtp_encryption, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, TRUE)");
        $stmt->bind_param("sisssss", $host, $port, $username, $password, $from_email, $from_name, $encryption);
    }
    
    if ($stmt && $stmt->execute()) {
        logAudit('update_smtp', 'settings', 1, 'Updated SMTP settings');
        $_SESSION['smtp_message'] = 'SMTP settings saved successfully!';
        header('Location: smtp_settings');
        exit;
    } else {
        $error = 'Failed to save SMTP settings: ' . ($stmt ? $stmt->error : $conn->error);
    }
    if ($stmt) $stmt->close();
}

// Check for session message
if (isset($_SESSION['smtp_message'])) {
    $message = $_SESSION['smtp_message'];
    unset($_SESSION['smtp_message']);
}

// Get current settings
$settingsResult = $conn->query("SELECT * FROM smtp_settings WHERE is_active = TRUE LIMIT 1");
$settings = $settingsResult ? $settingsResult->fetch_assoc() : null;
if (!$settings) {
    // Try without is_active filter (in case of data issue)
    $settingsResult = $conn->query("SELECT * FROM smtp_settings LIMIT 1");
    $settings = $settingsResult ? $settingsResult->fetch_assoc() : [];
}

$current_page = 'admin_smtp';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Settings - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <?php include '../includes/styles.php'; ?>
    <style>
        .container {
            max-width: 900px;
        }
        
        .info-box {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(37, 99, 235, 0.1) 100%);
            border-left: 4px solid var(--accent);
            padding: 20px;
            margin-bottom: 28px;
            border-radius: var(--radius-md);
        }
        
        .info-box h4 {
            color: #93c5fd;
            margin-bottom: 12px;
            font-size: 16px;
            font-weight: 700;
        }
        
        .info-box p {
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .help-text {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 6px;
        }
        
        .btn-row {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        
        .btn-row .btn {
            flex: 1;
        }
        
        .test-section {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
        }
        
        .test-section h3 {
            font-size: 18px;
            margin-bottom: 16px;
        }
        
        .test-form {
            display: flex;
            gap: 12px;
        }
        
        .test-form input {
            flex: 1;
        }
        
        .test-form .btn {
            width: auto;
            white-space: nowrap;
        }
        
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .test-form { flex-direction: column; }
            .btn-row { flex-direction: column; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container">
        <?php if ($message) showToast($message, 'success'); ?>
        
        <?php if ($error) showToast($error, 'error'); ?>

        <div class="section">
            <h2>📧 Email Server Configuration</h2>
            
            <div class="info-box">
                <h4>Built-in SMTP Protocol</h4>
                <p>Configure your email server details below to enable email notifications for password resets, LOA approvals, and other system events.</p>
            </div>

            <form method="POST">
                    <?php echo csrfField(); ?>
                <div class="form-group">
                    <label>SMTP Host *</label>
                    <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>" required placeholder="smtp.gmail.com">
                    <div class="help-text">Your email server address (e.g., smtp.gmail.com, smtp.office365.com)</div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>SMTP Port *</label>
                        <input type="number" name="smtp_port" value="<?php echo $settings['smtp_port'] ?? 587; ?>" required>
                        <div class="help-text">Common: 587 (TLS), 465 (SSL), 25 (none)</div>
                    </div>

                    <div class="form-group">
                        <label>Encryption *</label>
                        <select name="smtp_encryption" required>
                            <option value="tls" <?php echo ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                            <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                            <option value="none" <?php echo ($settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : ''; ?>>None</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>SMTP Username *</label>
                    <input type="text" name="smtp_username" value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>" required>
                    <div class="help-text">Usually your full email address</div>
                </div>

                <div class="form-group">
                    <label>SMTP Password *</label>
                    <input type="password" name="smtp_password" value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>" required>
                    <div class="help-text">Your email account password or app-specific password</div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>From Email *</label>
                        <input type="email" name="smtp_from_email" value="<?php echo htmlspecialchars($settings['smtp_from_email'] ?? ''); ?>" required placeholder="noreply@yourserver.com">
                        <div class="help-text">Email address in the "From" field</div>
                    </div>

                    <div class="form-group">
                        <label>From Name *</label>
                        <input type="text" name="smtp_from_name" value="<?php echo htmlspecialchars($settings['smtp_from_name'] ?? ''); ?>" required placeholder="Community Name">
                        <div class="help-text">Name in the "From" field</div>
                    </div>
                </div>

                <button type="submit" name="save_smtp" class="btn btn-primary btn-block">💾 Save SMTP Settings</button>
            </form>
            
            <div class="test-section">
                <h3>🧪 Test Email</h3>
                <p style="color: var(--text-muted); margin-bottom: 16px;">Send a test email to verify your SMTP configuration.</p>
                <form method="POST" class="test-form">
                    <?php echo csrfField(); ?>
                    <input type="email" name="test_email_address" placeholder="Enter email address..." required>
                    <button type="submit" name="test_email" class="btn btn-secondary">📤 Send Test</button>
                </form>
                <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--bg-elevated);">
                    <a href="/admin/email_preview" style="display: inline-flex; align-items: center; gap: 8px; color: var(--accent); text-decoration: none; font-size: 14px; font-weight: 500;">
                        📧 Preview Email Templates →
                    </a>
                    <p style="color: var(--text-muted); margin: 6px 0 0 0; font-size: 12px;">See how all email templates look before sending.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>