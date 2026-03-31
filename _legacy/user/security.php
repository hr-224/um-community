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
// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config.php';
if (!defined('UM_FUNCTIONS_LOADED')) { require_once __DIR__ . '/../includes/functions.php'; }
if (!defined('UM_EMAIL_LOADED')) { require_once __DIR__ . '/../includes/email.php'; }
if (file_exists(__DIR__ . '/../includes/discord.php')) { require_once __DIR__ . '/../includes/discord.php'; }
requireLogin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Check if Discord is available
$discordEnabled = function_exists('isDiscordConfigured') && isDiscordConfigured();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken()) {
    $action = $_POST['action'] ?? '';
    $isHtmx = isHtmxRequest();
    
    // Handle Discord unlink
    if ($action === 'unlink_discord') {
        if (unlinkDiscordAccount($conn, $user_id)) {
            toast('Discord account unlinked successfully.', 'success');
        } else {
            toast('Failed to unlink Discord account.', 'error');
        }
        header('Location: /user/security');
        exit;
    }
    
    // Update notification settings
    elseif ($action === 'update_settings') {
        $settings = [
            'email_on_new_device' => isset($_POST['email_on_new_device']),
            'email_on_new_ip' => isset($_POST['email_on_new_ip']),
            'email_on_new_location' => isset($_POST['email_on_new_location']),
            'email_on_failed_attempts' => isset($_POST['email_on_failed_attempts']),
            'require_2fa_new_device' => isset($_POST['require_2fa_new_device'])
        ];
        
        if (updateUserSecuritySettings($user_id, $settings)) {
            logAudit('security_settings_update', 'user', $user_id, 'Updated security notification settings');
            if ($isHtmx) {
                htmxResponse('Security settings updated!', 'success');
                exit;
            }
            $message = 'Security settings updated successfully!';
        } else {
            if ($isHtmx) {
                htmxResponse('Failed to update settings.', 'error');
                http_response_code(400);
                exit;
            }
            $error = 'Failed to update settings.';
        }
    }
    
    // Remove trusted device
    elseif ($action === 'remove_device') {
        $device_id = intval($_POST['device_id'] ?? 0);
        $tableCheck = $conn->query("SHOW TABLES LIKE 'trusted_devices'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $stmt = $conn->prepare("DELETE FROM trusted_devices WHERE id = ? AND user_id = ?");
            if ($stmt) {
                $stmt->bind_param("ii", $device_id, $user_id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    logAudit('trusted_device_removed', 'user', $user_id, "Removed trusted device ID: $device_id");
                    if ($isHtmx) {
                        htmxResponse('Device removed successfully.', 'success');
                        // Return empty to remove the element
                        exit;
                    }
                    $message = 'Device removed successfully.';
                }
                $stmt->close();
            }
        }
    }
    
    // Trust a device
    elseif ($action === 'trust_device') {
        $device_id = intval($_POST['device_id'] ?? 0);
        $tableCheck = $conn->query("SHOW TABLES LIKE 'trusted_devices'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE trusted_devices SET is_trusted = 1 WHERE id = ? AND user_id = ?");
            if ($stmt) {
                $stmt->bind_param("ii", $device_id, $user_id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    logAudit('trusted_device_added', 'user', $user_id, "Trusted device ID: $device_id");
                    if ($isHtmx) {
                        htmxResponse('Device marked as trusted.', 'success');
                        header('HX-Refresh: true');
                        exit;
                    }
                    $message = 'Device marked as trusted.';
                }
                $stmt->close();
            }
        }
    }
    
    // Dismiss alert
    elseif ($action === 'dismiss_alert') {
        $alert_id = intval($_POST['alert_id'] ?? 0);
        $tableCheck = $conn->query("SHOW TABLES LIKE 'security_alerts'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE security_alerts SET is_resolved = 1, resolved_by = ?, resolved_at = NOW() WHERE id = ? AND user_id = ?");
            if ($stmt) {
                $stmt->bind_param("iii", $user_id, $alert_id, $user_id);
                $stmt->execute();
                $stmt->close();
                if ($isHtmx) {
                    htmxResponse('Alert dismissed.', 'success');
                    // Return empty to remove the element
                    exit;
                }
                $message = 'Alert dismissed.';
            }
        }
    }
    
    // Clear all alerts
    elseif ($action === 'clear_all_alerts') {
        $tableCheck = $conn->query("SHOW TABLES LIKE 'security_alerts'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE security_alerts SET is_resolved = 1, resolved_by = ?, resolved_at = NOW() WHERE user_id = ? AND is_resolved = 0");
            if ($stmt) {
                $stmt->bind_param("ii", $user_id, $user_id);
                $stmt->execute();
                $cleared = $stmt->affected_rows;
                $stmt->close();
                logAudit('all_alerts_cleared', 'user', $user_id, "Cleared $cleared security alerts");
                if ($isHtmx) {
                    htmxResponse("Cleared $cleared alert(s).", 'success');
                    header('HX-Refresh: true');
                    exit;
                }
                $message = "Cleared $cleared alert(s).";
            }
        }
    }
    
    // Remove all devices except current
    elseif ($action === 'remove_all_devices') {
        $tableCheck = $conn->query("SHOW TABLES LIKE 'trusted_devices'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $current_device_hash = generateDeviceHash($_SERVER['HTTP_USER_AGENT'] ?? '');
            $stmt = $conn->prepare("DELETE FROM trusted_devices WHERE user_id = ? AND device_hash != ?");
            if ($stmt) {
                $stmt->bind_param("is", $user_id, $current_device_hash);
                $stmt->execute();
                $removed = $stmt->affected_rows;
                $stmt->close();
                logAudit('all_devices_removed', 'user', $user_id, "Removed $removed trusted devices");
                if ($isHtmx) {
                    htmxResponse("Removed $removed device(s).", 'success');
                    header('HX-Refresh: true');
                    exit;
                }
                $message = "Removed $removed device(s). Only your current device remains.";
            }
        }
    }
}

// Get user's security settings
$security_settings = getUserSecuritySettings($user_id);

// Get trusted devices
$trusted_devices = [];
$result = $conn->query("SHOW TABLES LIKE 'trusted_devices'");
if ($result->num_rows > 0) {
    $stmt = $conn->prepare("SELECT * FROM trusted_devices WHERE user_id = ? ORDER BY last_seen DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $devices_result = $stmt->get_result();
    while ($row = $devices_result->fetch_assoc()) {
        $trusted_devices[] = $row;
    }
    $stmt->close();
}

// Get current device hash
$current_device_hash = generateDeviceHash($_SERVER['HTTP_USER_AGENT'] ?? '');

// Get recent security alerts
$security_alerts = [];
$result = $conn->query("SHOW TABLES LIKE 'security_alerts'");
if ($result->num_rows > 0) {
    $stmt = $conn->prepare("SELECT * FROM security_alerts WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $alerts_result = $stmt->get_result();
    while ($row = $alerts_result->fetch_assoc()) {
        $security_alerts[] = $row;
    }
    $stmt->close();
}

// Count unresolved alerts
$unresolved_count = 0;
foreach ($security_alerts as $alert) {
    if (!$alert['is_resolved']) $unresolved_count++;
}

// Get Discord link status
$discordConfigured = function_exists('isDiscordConfigured') && isDiscordConfigured();
$stmt = $conn->prepare("SELECT discord_user_id, discord_username, discord_discriminator, discord_avatar, discord_linked_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$discordData = $stmt->get_result()->fetch_assoc();
$stmt->close();
$hasDiscordLinked = !empty($discordData['discord_user_id']);

$conn->close();

$colors = getThemeColors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Settings - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <?php include '../includes/styles.php'; ?>
    <style>
        .container { max-width: 1000px; margin: 0 auto; padding: 30px 20px; }
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-size: 28px; font-weight: 800; color: var(--text-primary); margin: 0 0 8px; display: flex; align-items: center; gap: 12px; }
        .page-header p { color: var(--text-muted); margin: 0; }
        
        .grid { display: grid; grid-template-columns: 1fr; gap: 24px; }
        @media (min-width: 768px) { .grid { grid-template-columns: 1fr 1fr; } }
        
        .card {
            background: var(--bg-elevated);
            border-radius: var(--radius-lg);
            padding: 24px;
            border: 1px solid var(--bg-elevated);
        }
        .card-full { grid-column: 1 / -1; }
        .card h2 { font-size: 18px; color: var(--text-primary); margin: 0 0 20px; display: flex; align-items: center; gap: 10px; }
        .card h2 .icon { font-size: 24px; }
        
        .setting-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid var(--bg-elevated);
        }
        .setting-row:last-child { border-bottom: none; }
        .setting-info h3 { font-size: 14px; color: var(--text-primary); margin: 0 0 4px; }
        .setting-info p { font-size: 12px; color: var(--text-muted); margin: 0; }
        
        .toggle {
            position: relative;
            width: 48px;
            height: 26px;
        }
        .toggle input { opacity: 0; width: 0; height: 0; }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background: var(--bg-elevated);
            transition: 0.3s;
            border-radius: 26px;
        }
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background: #fff;
            transition: 0.3s;
            border-radius: 50%;
        }
        .toggle input:checked + .toggle-slider { background: var(--accent); }
        .toggle input:checked + .toggle-slider:before { transform: translateX(22px); }
        
        .device-list { display: flex; flex-direction: column; gap: 12px; }
        .device-item {
            background: var(--bg-primary);
            border-radius: var(--radius-md);
            padding: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--bg-elevated);
        }
        .device-item.current { border-color: var(--accent); background: rgba(102,126,234,0.1); }
        .device-info { flex: 1; }
        .device-name { font-weight: 600; color: var(--text-primary); margin: 0 0 4px; display: flex; align-items: center; gap: 8px; }
        .device-name .badge { font-size: 10px; padding: 2px 8px; border-radius: var(--radius-md); background: var(--accent); color: var(--text-primary); font-weight: 600; }
        .device-name .trusted { background: #22c55e; }
        .device-meta { font-size: 12px; color: var(--text-muted); }
        .device-actions { display: flex; gap: 8px; }
        
        .alert-list { display: flex; flex-direction: column; gap: 12px; }
        .alert-item {
            background: var(--bg-primary);
            border-radius: var(--radius-md);
            padding: 16px;
            border-left: 4px solid;
        }
        .alert-item.severity-low { border-color: #3b82f6; }
        .alert-item.severity-medium { border-color: #f59e0b; }
        .alert-item.severity-high { border-color: var(--danger); }
        .alert-item.severity-critical { border-color: #dc2626; background: rgba(220,38,38,0.1); }
        .alert-item.resolved { opacity: 0.6; }
        .alert-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
        .alert-type { font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 8px; }
        .alert-time { font-size: 12px; color: var(--text-muted); }
        .alert-message { color: var(--text-secondary); font-size: 14px; margin-bottom: 8px; }
        .alert-details { font-size: 12px; color: var(--text-muted); }
        .alert-actions { margin-top: 12px; }
        
        .btn {
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .btn-primary { background: var(--accent); color: var(--text-primary); }
        .btn-danger { background: rgba(239,68,68,0.2); color: var(--danger); border: 1px solid rgba(239,68,68,0.3); }
        .btn-ghost { background: var(--bg-elevated); color: var(--text-primary); }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }
        
        .empty-state { text-align: center; padding: 40px 20px; color: var(--text-muted); }
        .empty-state .icon { font-size: 48px; margin-bottom: 16px; opacity: 0.5; }
        
        .message { padding: 14px 18px; border-radius: var(--radius-md); margin-bottom: 20px; }
        .message-success { background: rgba(34,197,94,0.15); border: 1px solid rgba(34,197,94,0.3); color: #22c55e; }
        .message-error { background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3); color: var(--danger); }
        
        .unresolved-badge {
            background: var(--danger);
            color: var(--text-primary);
            font-size: 11px;
            padding: 2px 8px;
            border-radius: var(--radius-md);
            margin-left: 8px;
        }
        
        .section-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--bg-elevated);
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>🛡️ Security Settings</h1>
            <p>Manage your account security and login notifications</p>
        </div>
        
        <?php showPageToasts(); ?>
        
        <div class="grid">
            <!-- Notification Settings -->
            <div class="card">
                <h2><span class="icon">🔔</span> Login Notifications</h2>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                    <input type="hidden" name="action" value="update_settings">
                    
                    <div class="setting-row">
                        <div class="setting-info">
                            <h3>New Device Alert</h3>
                            <p>Get notified when a new device logs in</p>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" name="email_on_new_device" <?php echo $security_settings['email_on_new_device'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="setting-row">
                        <div class="setting-info">
                            <h3>New IP Address Alert</h3>
                            <p>Get notified when login from a new IP</p>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" name="email_on_new_ip" <?php echo $security_settings['email_on_new_ip'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="setting-row">
                        <div class="setting-info">
                            <h3>New Location Alert</h3>
                            <p>Get notified when login from a new location</p>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" name="email_on_new_location" <?php echo $security_settings['email_on_new_location'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="setting-row">
                        <div class="setting-info">
                            <h3>Failed Login Attempts</h3>
                            <p>Get notified after multiple failed attempts</p>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" name="email_on_failed_attempts" <?php echo $security_settings['email_on_failed_attempts'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="section-actions">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </div>
                </form>
            </div>
            
            <!-- Quick Stats -->
            <div class="card">
                <h2><span class="icon">📊</span> Security Overview</h2>
                <div class="setting-row">
                    <div class="setting-info">
                        <h3>Recognized Devices</h3>
                        <p>Devices that have logged into your account</p>
                    </div>
                    <span style="font-size: 24px; font-weight: 700; color: var(--accent);"><?php echo count($trusted_devices); ?></span>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <h3>Trusted Devices</h3>
                        <p>Devices you've marked as trusted</p>
                    </div>
                    <span style="font-size: 24px; font-weight: 700; color: #22c55e;"><?php echo count(array_filter($trusted_devices, fn($d) => $d['is_trusted'])); ?></span>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <h3>Unresolved Alerts</h3>
                        <p>Security alerts requiring attention</p>
                    </div>
                    <span style="font-size: 24px; font-weight: 700; color: <?php echo $unresolved_count > 0 ? 'var(--danger)' : '#22c55e'; ?>;"><?php echo $unresolved_count; ?></span>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <h3>Two-Factor Auth</h3>
                        <p>Additional account protection</p>
                    </div>
                    <a href="/user/two_factor" class="btn btn-ghost btn-sm">Manage 2FA</a>
                </div>
            </div>
            
            <!-- Discord Account Link -->
            <div class="card">
                <h2><span class="icon">🎮</span> Discord Account</h2>
                
                <?php if ($hasDiscordLinked): ?>
                    <div class="discord-linked-info" style="display: flex; align-items: center; gap: 16px; padding: 16px; background: rgba(88, 101, 242, 0.1); border-radius: var(--radius-md); margin-bottom: 16px;">
                        <?php 
                        $avatarUrl = function_exists('getDiscordAvatarUrl') ? getDiscordAvatarUrl(
                            $discordData['discord_user_id'],
                            $discordData['discord_avatar'],
                            $discordData['discord_discriminator'] ?? '0'
                        ) : 'https://cdn.discordapp.com/embed/avatars/0.png';
                        ?>
                        <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Discord Avatar" style="width: 48px; height: 48px; border-radius: 50%; border: 2px solid #5865F2;">
                        <div>
                            <div style="font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($discordData['discord_username']); ?></div>
                            <div style="font-size: 12px; color: var(--text-muted);">
                                Linked <?php echo date('M j, Y', strtotime($discordData['discord_linked_at'])); ?>
                            </div>
                        </div>
                        <span style="margin-left: auto; background: rgba(88, 101, 242, 0.3); color: #5865F2; padding: 4px 12px; border-radius: var(--radius-lg); font-size: 12px; font-weight: 600;">Connected</span>
                    </div>
                    
                    <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">
                        You can use Discord to log in to your account. Unlinking will require you to use your password.
                    </p>
                    
                    <form method="POST" onsubmit="return confirm('Are you sure you want to unlink your Discord account?');">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                        <input type="hidden" name="action" value="unlink_discord">
                        <button type="submit" class="btn btn-danger">Unlink Discord</button>
                    </form>
                <?php elseif ($discordConfigured): ?>
                    <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">
                        Link your Discord account to enable one-click login and sync your profile.
                    </p>
                    
                    <a href="/auth/discord.php?action=link" class="btn-discord" style="display: inline-flex; align-items: center; gap: 10px; padding: 12px 20px; background: #5865F2; color: white; border-radius: var(--radius-md); font-weight: 600; text-decoration: none; transition: all 0.3s;">
                        <svg width="20" height="20" viewBox="0 0 71 55" fill="currentColor">
                            <path d="M60.1045 4.8978C55.5792 2.8214 50.7265 1.2916 45.6527 0.41542C45.5603 0.39851 45.468 0.440769 45.4204 0.525289C44.7963 1.6353 44.105 3.0834 43.6209 4.2216C38.1637 3.4046 32.7345 3.4046 27.3892 4.2216C26.905 3.0581 26.1886 1.6353 25.5617 0.525289C25.5141 0.443589 25.4218 0.40133 25.3294 0.41542C20.2584 1.2888 15.4057 2.8186 10.8776 4.8978C10.8384 4.9147 10.8048 4.9429 10.7825 4.9795C1.57795 18.7309 -0.943561 32.1443 0.293408 45.3914C0.299005 45.4562 0.335386 45.5182 0.385761 45.5576C6.45866 50.0174 12.3413 52.7249 18.1147 54.5195C18.2071 54.5477 18.305 54.5139 18.3638 54.4378C19.7295 52.5728 20.9469 50.6063 21.9907 48.5383C22.0523 48.4172 21.9935 48.2735 21.8676 48.2256C19.9366 47.4931 18.0979 46.6 16.3292 45.5858C16.1893 45.5041 16.1781 45.304 16.3068 45.2082C16.679 44.9293 17.0513 44.6391 17.4067 44.3461C17.471 44.2926 17.5606 44.2813 17.6362 44.3151C29.2558 49.6202 41.8354 49.6202 53.3179 44.3151C53.3935 44.2785 53.4831 44.2898 53.5502 44.3433C53.9057 44.6363 54.2779 44.9293 54.6529 45.2082C54.7816 45.304 54.7732 45.5041 54.6333 45.5858C52.8646 46.6197 51.0259 47.4931 49.0921 48.2228C48.9662 48.2707 48.9102 48.4172 48.9718 48.5383C50.0383 50.6034 51.2557 52.5699 52.5959 54.435C52.6519 54.5139 52.7526 54.5477 52.845 54.5195C58.6464 52.7249 64.529 50.0174 70.6019 45.5576C70.6551 45.5182 70.6887 45.459 70.6943 45.3942C72.1747 30.0791 68.2147 16.7757 60.1968 4.9823C60.1772 4.9429 60.1437 4.9147 60.1045 4.8978ZM23.7259 37.3253C20.2276 37.3253 17.3451 34.1136 17.3451 30.1693C17.3451 26.225 20.1717 23.0133 23.7259 23.0133C27.308 23.0133 30.1626 26.2532 30.1099 30.1693C30.1099 34.1136 27.2680 37.3253 23.7259 37.3253ZM47.3178 37.3253C43.8196 37.3253 40.9371 34.1136 40.9371 30.1693C40.9371 26.225 43.7680 23.0133 47.3178 23.0133C50.9003 23.0133 53.7545 26.2532 53.7018 30.1693C53.7018 34.1136 50.9003 37.3253 47.3178 37.3253Z"/>
                        </svg>
                        Link Discord Account
                    </a>
                <?php else: ?>
                    <div style="padding: 16px; background: var(--bg-primary); border-radius: var(--radius-md); border: 1px solid var(--bg-elevated);">
                        <p style="font-size: 13px; color: var(--text-muted); margin: 0;">
                            <span style="color: #5865F2;">🎮</span> Discord login is not yet configured. Contact an administrator to enable Discord integration.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Trusted Devices -->
            <div class="card card-full">
                <h2><span class="icon">💻</span> Your Devices</h2>
                
                <?php if (empty($trusted_devices)): ?>
                    <div class="empty-state">
                        <div class="icon">📱</div>
                        <p>No devices recorded yet. They'll appear here after your next login.</p>
                    </div>
                <?php else: ?>
                    <div class="device-list">
                        <?php foreach ($trusted_devices as $device): ?>
                            <?php $is_current = ($device['device_hash'] === $current_device_hash); ?>
                            <div class="device-item <?php echo $is_current ? 'current' : ''; ?>" id="device-<?php echo $device['id']; ?>">
                                <div class="device-info">
                                    <div class="device-name">
                                        <?php echo htmlspecialchars($device['device_name'] ?: 'Unknown Device'); ?>
                                        <?php if ($is_current): ?>
                                            <span class="badge">Current</span>
                                        <?php endif; ?>
                                        <?php if ($device['is_trusted']): ?>
                                            <span class="badge trusted">Trusted</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="device-meta">
                                        📍 <?php echo htmlspecialchars($device['location'] ?: 'Unknown location'); ?> •
                                        🌐 <?php echo htmlspecialchars($device['last_ip']); ?> •
                                        🕐 Last seen: <?php echo date('M j, Y g:i A', strtotime($device['last_seen'])); ?>
                                    </div>
                                </div>
                                <div class="device-actions">
                                    <?php if (!$device['is_trusted']): ?>
                                        <form method="POST" style="display:inline;" >
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                                            <input type="hidden" name="action" value="trust_device">
                                            <input type="hidden" name="device_id" value="<?php echo intval($device['id']); ?>">
                                            <button type="submit" class="btn btn-ghost btn-sm">Trust</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (!$is_current): ?>
                                        <form method="POST" style="display:inline;" hx-confirm="Remove this device?" hx-target="#device-<?php echo $device['id']; ?>" hx-swap="outerHTML">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                                            <input type="hidden" name="action" value="remove_device">
                                            <input type="hidden" name="device_id" value="<?php echo intval($device['id']); ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (count($trusted_devices) > 1): ?>
                        <div class="section-actions">
                            <form method="POST" hx-confirm="Remove all devices except your current one? You will need to re-authenticate on other devices.">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                                <input type="hidden" name="action" value="remove_all_devices">
                                <button type="submit" class="btn btn-danger">Remove All Other Devices</button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <!-- Security Alerts -->
            <div class="card card-full">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
                    <h2 style="margin: 0;">
                        <span class="icon">⚠️</span> Security Alerts
                        <?php if ($unresolved_count > 0): ?>
                            <span class="unresolved-badge"><?php echo $unresolved_count; ?> unresolved</span>
                        <?php endif; ?>
                    </h2>
                    <?php if (!empty($security_alerts)): ?>
                        <form method="POST" style="display: inline;" hx-confirm="Clear all security alerts? This will mark all alerts as resolved.">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                            <input type="hidden" name="action" value="clear_all_alerts">
                            <button type="submit" class="btn btn-ghost btn-sm" style="color: var(--danger);">🗑️ Clear All</button>
                        </form>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($security_alerts)): ?>
                    <div class="empty-state">
                        <div class="icon">✅</div>
                        <p>No security alerts. Your account is secure!</p>
                    </div>
                <?php else: ?>
                    <?php 
                    $total_alerts = count($security_alerts);
                    $show_initial = 5;
                    ?>
                    <div class="alert-list">
                        <?php foreach ($security_alerts as $index => $alert): ?>
                            <?php
                            $type_icons = [
                                'new_device' => '💻',
                                'new_ip' => '🌐',
                                'new_location' => '📍',
                                'suspicious_time' => '🕐',
                                'failed_attempts' => '🔒',
                                'impossible_travel' => '✈️'
                            ];
                            $type_labels = [
                                'new_device' => 'New Device',
                                'new_ip' => 'New IP Address',
                                'new_location' => 'New Location',
                                'suspicious_time' => 'Unusual Time',
                                'failed_attempts' => 'Failed Attempts',
                                'impossible_travel' => 'Impossible Travel'
                            ];
                            $icon = $type_icons[$alert['alert_type']] ?? '⚠️';
                            $label = $type_labels[$alert['alert_type']] ?? $alert['alert_type'];
                            $details = json_decode($alert['details'], true) ?: [];
                            $hidden = $index >= $show_initial ? 'style="display: none;"' : '';
                            ?>
                            <div class="alert-item severity-<?php echo $alert['severity']; ?> <?php echo $alert['is_resolved'] ? 'resolved' : ''; ?>" <?php echo $hidden; ?> data-alert-index="<?php echo $index; ?>" id="alert-<?php echo $alert['id']; ?>">
                                <div class="alert-header">
                                    <span class="alert-type"><?php echo $icon; ?> <?php echo $label; ?></span>
                                    <span class="alert-time"><?php echo date('M j, Y g:i A', strtotime($alert['created_at'])); ?></span>
                                </div>
                                <div class="alert-message">
                                    <?php
                                    switch ($alert['alert_type']) {
                                        case 'new_device':
                                            echo 'Login from new device: ' . htmlspecialchars($details['device'] ?? 'Unknown');
                                            break;
                                        case 'new_ip':
                                            echo 'Login from new IP: ' . htmlspecialchars($details['new_ip'] ?? $alert['ip_address']);
                                            break;
                                        case 'new_location':
                                            echo 'Login from new location: ' . htmlspecialchars($details['new_location'] ?? $alert['location']);
                                            break;
                                        case 'failed_attempts':
                                            echo 'Successful login after ' . ($details['failed_count'] ?? 'multiple') . ' failed attempts';
                                            break;
                                        case 'impossible_travel':
                                            echo 'Login from ' . htmlspecialchars($details['to_location'] ?? 'unknown') . ' after being in ' . htmlspecialchars($details['from_location'] ?? 'unknown');
                                            break;
                                        default:
                                            echo htmlspecialchars($alert['details']);
                                    }
                                    ?>
                                </div>
                                <div class="alert-details">
                                    IP: <?php echo htmlspecialchars($alert['ip_address']); ?> • 
                                    Location: <?php echo htmlspecialchars($alert['location'] ?: 'Unknown'); ?>
                                    <?php if ($alert['is_resolved']): ?>
                                        • <span style="color: #22c55e;">✓ Resolved</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!$alert['is_resolved']): ?>
                                    <div class="alert-actions">
                                        <form method="POST" style="display:inline;" hx-target="#alert-<?php echo $alert['id']; ?>" hx-swap="outerHTML">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                                            <input type="hidden" name="action" value="dismiss_alert">
                                            <input type="hidden" name="alert_id" value="<?php echo intval($alert['id']); ?>">
                                            <button type="submit" class="btn btn-ghost btn-sm">Dismiss</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($total_alerts > $show_initial): ?>
                        <div style="text-align: center; margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--bg-elevated);">
                            <button type="button" class="btn btn-ghost" id="showMoreAlerts" onclick="toggleMoreAlerts()">
                                Show <?php echo $total_alerts - $show_initial; ?> More Alerts ▼
                            </button>
                        </div>
                        <script>
                        function toggleMoreAlerts() {
                            const btn = document.getElementById('showMoreAlerts');
                            const hiddenAlerts = document.querySelectorAll('.alert-item[data-alert-index]');
                            const isExpanded = btn.dataset.expanded === 'true';
                            
                            hiddenAlerts.forEach((alert, i) => {
                                if (i >= <?php echo $show_initial; ?>) {
                                    alert.style.display = isExpanded ? 'none' : 'block';
                                }
                            });
                            
                            if (isExpanded) {
                                btn.textContent = 'Show <?php echo $total_alerts - $show_initial; ?> More Alerts ▼';
                                btn.dataset.expanded = 'false';
                            } else {
                                btn.textContent = 'Show Less ▲';
                                btn.dataset.expanded = 'true';
                            }
                        }
                        </script>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
