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
requireAdmin();

$conn = getDBConnection();
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken()) {
    $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
    $maintenance_message = trim($_POST['maintenance_message'] ?? '');
    
    // Update settings
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('maintenance_mode', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $val = $maintenance_mode ? '1' : '0';
    $stmt->bind_param("ss", $val, $val);
    $stmt->execute();
    $stmt->close();
    
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('maintenance_message', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param("ss", $maintenance_message, $maintenance_message);
    $stmt->execute();
    $stmt->close();
    
    $message = 'Maintenance settings updated!';
    logAudit('maintenance_toggle', 'system', 0, 'Maintenance mode: ' . ($maintenance_mode ? 'ON' : 'OFF'));
}

// Get current settings
$maintenance_mode = false;
$maintenance_message = 'We are currently performing scheduled maintenance. Please check back shortly.';

$result = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('maintenance_mode', 'maintenance_message')");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        if ($row['setting_key'] === 'maintenance_mode') {
            $maintenance_mode = $row['setting_value'] === '1';
        } elseif ($row['setting_key'] === 'maintenance_message') {
            $maintenance_message = $row['setting_value'];
        }
    }
}

$conn->close();

$theme = getThemeColors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Mode - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
    <style>
        .maintenance-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 24px;
        }
        
        @media (max-width: 900px) {
            .maintenance-grid { grid-template-columns: 1fr; }
        }
        
        .status-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 32px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .status-card.active {
            border-color: rgba(239, 68, 68, 0.5);
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, var(--bg-card) 50%);
        }
        
        .status-card.active::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--danger), #f97316);
        }
        
        .status-card.inactive {
            border-color: rgba(16, 185, 129, 0.5);
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, var(--bg-card) 50%);
        }
        
        .status-card.inactive::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--success), #34d399);
        }
        
        .status-icon {
            font-size: 64px;
            margin-bottom: 16px;
            display: block;
        }
        
        .status-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .status-description {
            color: var(--text-muted);
            font-size: 14px;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: 13px;
            margin-top: 16px;
        }
        
        .status-badge.active {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .status-badge.inactive {
            background: rgba(16, 185, 129, 0.2);
            color: #4ade80;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .status-badge .pulse {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        .status-badge.active .pulse { background: var(--danger); }
        .status-badge.inactive .pulse { background: var(--success); }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
        
        .config-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 24px;
        }
        
        .config-card h3 {
            font-size: 16px;
            color: var(--text-primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .toggle-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: var(--radius-md);
            margin-bottom: 20px;
        }
        
        .toggle-info h4 {
            color: var(--text-primary);
            font-size: 14px;
            margin-bottom: 4px;
        }
        
        .toggle-info p {
            color: var(--text-muted);
            font-size: 12px;
        }
        
        .toggle-switch {
            position: relative;
            width: 56px;
            height: 28px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--bg-hover);
            border-radius: 28px;
            transition: 0.3s;
        }
        
        .toggle-slider::before {
            content: '';
            position: absolute;
            width: 22px;
            height: 22px;
            left: 3px;
            bottom: 3px;
            background: white;
            border-radius: 50%;
            transition: 0.3s;
        }
        
        .toggle-switch input:checked + .toggle-slider {
            background: linear-gradient(135deg, var(--danger), #f97316);
        }
        
        .toggle-switch input:checked + .toggle-slider::before {
            transform: translateX(28px);
        }
        
        .form-group label {
            color: var(--text-secondary);
        }
        
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .preview-section {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
        }
        
        .preview-section h4 {
            color: var(--text-muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }
        
        .preview-box {
            background: rgba(0, 0, 0, 0.3);
            border-radius: var(--radius-md);
            padding: 20px;
            text-align: center;
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .sidebar-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 24px;
            margin-bottom: 24px;
        }
        
        .sidebar-card h3 {
            font-size: 14px;
            color: var(--text-primary);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-item .label {
            color: var(--text-muted);
        }
        
        .info-item .value {
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .quick-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .quick-action-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .quick-action-btn:hover {
            background: var(--bg-elevated);
            border-color: var(--border-medium);
            color: var(--text-primary);
        }
        
        .quick-action-btn .icon {
            font-size: 20px;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>🔧 Maintenance Mode</h1>
    </div>
    
    <?php if ($message) showToast($message, 'success'); ?>
    
    <div class="maintenance-grid">
        <div class="main-content">
            <!-- Status Card -->
            <div class="status-card <?php echo $maintenance_mode ? 'active' : 'inactive'; ?>">
                <span class="status-icon"><?php echo $maintenance_mode ? '🚧' : '✅'; ?></span>
                <div class="status-title">
                    Maintenance Mode is <?php echo $maintenance_mode ? 'Active' : 'Inactive'; ?>
                </div>
                <div class="status-description">
                    <?php if ($maintenance_mode): ?>
                        Only administrators can access the site. Regular users see the maintenance message.
                    <?php else: ?>
                        The site is operating normally. All users have full access.
                    <?php endif; ?>
                </div>
                <span class="status-badge <?php echo $maintenance_mode ? 'active' : 'inactive'; ?>">
                    <span class="pulse"></span>
                    <?php echo $maintenance_mode ? 'Site Offline' : 'Site Online'; ?>
                </span>
            </div>
            
            <!-- Configuration Card -->
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                
                <div class="config-card">
                    <h3>⚙️ Configuration</h3>
                    
                    <div class="toggle-container">
                        <div class="toggle-info">
                            <h4>Enable Maintenance Mode</h4>
                            <p>When enabled, only admins can access the site</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="maintenance_mode" <?php echo $maintenance_mode ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label>📝 Maintenance Message</label>
                        <textarea name="maintenance_message" class="form-control" placeholder="Enter the message users will see during maintenance..."><?php echo htmlspecialchars($maintenance_message); ?></textarea>
                        <small>This message will be displayed to non-admin users when they try to access the site.</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">💾 Save Settings</button>
                    
                    <div class="preview-section">
                        <h4>Preview</h4>
                        <div class="preview-box">
                            <div style="font-size: 32px; margin-bottom: 12px;">🛠️</div>
                            <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 8px;">Under Maintenance</div>
                            <div id="preview-message"><?php echo htmlspecialchars($maintenance_message); ?></div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="sidebar">
            <!-- Info Card -->
            <div class="sidebar-card">
                <h3>ℹ️ Information</h3>
                <div class="info-item">
                    <span class="label">Current Status</span>
                    <span class="value"><?php echo $maintenance_mode ? '🔴 Offline' : '🟢 Online'; ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Admin Access</span>
                    <span class="value">Always Allowed</span>
                </div>
                <div class="info-item">
                    <span class="label">User Access</span>
                    <span class="value"><?php echo $maintenance_mode ? 'Blocked' : 'Allowed'; ?></span>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="sidebar-card">
                <h3>🚀 Quick Actions</h3>
                <div class="quick-actions">
                    <a href="/admin/backup" class="quick-action-btn">
                        <span class="icon">💾</span>
                        <span>Database Backup</span>
                    </a>
                    <a href="/cron/scheduled_tasks" class="quick-action-btn">
                        <span class="icon">⏰</span>
                        <span>Scheduled Tasks</span>
                    </a>
                    <a href="/admin/system_settings" class="quick-action-btn">
                        <span class="icon">⚙️</span>
                        <span>System Settings</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Live preview
document.querySelector('textarea[name="maintenance_message"]').addEventListener('input', function() {
    document.getElementById('preview-message').textContent = this.value || 'Enter a message...';
});
</script>

</body>
</html>
