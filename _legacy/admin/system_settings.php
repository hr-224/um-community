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
requireLogin();

// Check permission
if (!isAdmin() && !hasPermission('admin.settings')) {
    header('Location: /index?error=unauthorized');
    exit();
}

$conn = getDBConnection();
$is_admin = isAdmin();

// Handle AJAX save request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['ajax_save'])) {
    header('Content-Type: application/json');
    
    $key = $_POST['key'] ?? '';
    $value = $_POST['value'] ?? '';
    
    if (!empty($key)) {
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->bind_param("sss", $key, $value, $value);
        $result = $stmt->execute();
        $stmt->close();
        
        if ($result) {
            logAudit('update_setting', 'system', null, "Updated setting: $key");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid key']);
    }
    $conn->close();
    exit;
}

// Handle logo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['upload_logo'])) {
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'];
        $file_type = $_FILES['logo']['type'];
        $file_size = $_FILES['logo']['size'];
        
        if (in_array($file_type, $allowed_types) && $file_size <= 2 * 1024 * 1024) {
            // Server-side image validation
            if (!validateUploadedImage($_FILES['logo']['tmp_name'], $file_type)) {
                $error = 'Invalid image file. The file appears to be corrupted or not a real image.';
            } else {
                $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/logos/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            
            // Delete old logo
            $old_logo = getSetting('community_logo', '');
            if ($old_logo && file_exists($_SERVER['DOCUMENT_ROOT'] . $old_logo)) {
                @unlink($_SERVER['DOCUMENT_ROOT'] . $old_logo);
            }
            
            $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $filename = 'logo_' . time() . '.' . $ext;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $filepath)) {
                // Sanitize SVG files
                if ($file_type === 'image/svg+xml') {
                    sanitizeSVG($filepath);
                }
                $logo_path = '/uploads/logos/' . $filename;
                $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('community_logo', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->bind_param("ss", $logo_path, $logo_path);
                $stmt->execute();
                $stmt->close();
                logAudit('upload_logo', 'system', null, 'Uploaded community logo');
            }
            }
        }
    }
    header('Location: system_settings');
    exit;
}

// Handle logo removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['remove_logo'])) {
    $old_logo = getSetting('community_logo', '');
    if ($old_logo && file_exists($_SERVER['DOCUMENT_ROOT'] . $old_logo)) {
        @unlink($_SERVER['DOCUMENT_ROOT'] . $old_logo);
    }
    $conn->query("DELETE FROM system_settings WHERE setting_key = 'community_logo'");
    logAudit('remove_logo', 'system', null, 'Removed community logo');
    header('Location: system_settings');
    exit;
}

// Handle Discord webhook save (manual)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['save_discord'])) {
    $webhook_url = trim($_POST['discord_webhook_url'] ?? '');
    
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('discord_webhook_url', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param("ss", $webhook_url, $webhook_url);
    $stmt->execute();
    $stmt->close();
    
    logAudit('update_settings', 'system', null, 'Updated Discord webhook URL');
    header('Location: system_settings?saved=discord');
    exit;
}

// Test Discord webhook (admin/logs)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['test_webhook'])) {
    $result = sendDiscordWebhook('test', [
        'title' => 'Test Notification',
        'message' => 'This is a test notification from ' . getCommunityName(),
        'type' => 'info'
    ]);
    header('Location: system_settings?webhook=' . ($result ? 'success' : 'fail'));
    exit;
}

// Test Discord webhook (applications)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['test_webhook_apps'])) {
    $result = sendDiscordWebhook('application', [
        'title' => '📋 Test Application Notification',
        'message' => 'This is a test from the Applications webhook.',
        'type' => 'info',
        'test' => true
    ]);
    header('Location: system_settings?webhook_apps=' . ($result ? 'success' : 'fail'));
    exit;
}

// Check if quick_links table exists (needed for migrations)
$ql_table_exists = $conn->query("SHOW TABLES LIKE 'quick_links'")->num_rows > 0;

// Auto-create quick_links table if missing
if (!$ql_table_exists) {
    $conn->query("CREATE TABLE IF NOT EXISTS quick_links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        url VARCHAR(255) NOT NULL,
        icon VARCHAR(50) DEFAULT '🔗',
        sort_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    $conn->query("INSERT INTO quick_links (title, url, icon, sort_order) VALUES
        ('Request LOA', '/user/loa', '📅', 1),
        ('LOA Calendar', '/user/loa_calendar', '📆', 2),
        ('Messages', '/user/messages', '✉️', 3),
        ('Announcements', '/user/announcements', '📢', 4)
    ");
    $ql_table_exists = true;
}

// Handle quick link creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['add_quick_link']) && $ql_table_exists) {
    $title = trim($_POST['link_title']);
    $url = trim($_POST['link_url']);
    $icon = trim($_POST['link_icon']) ?: '🔗';
    
    // Get max sort order
    $max_order = safeQueryValue($conn, "SELECT MAX(sort_order) as max_order FROM quick_links", 'max_order', 0);
    $sort_order = intval($max_order) + 1;
    
    $stmt = $conn->prepare("INSERT INTO quick_links (title, url, icon, sort_order) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $title, $url, $icon, $sort_order);
    $stmt->execute();
    $stmt->close();
    
    logAudit('create_quick_link', 'quick_link', $conn->insert_id, "Created quick link: $title");
    header('Location: system_settings?tab=quicklinks&saved=link');
    exit;
}

// Handle quick link update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['update_quick_link']) && $ql_table_exists) {
    $id = intval($_POST['link_id']);
    $title = trim($_POST['link_title']);
    $url = trim($_POST['link_url']);
    $icon = trim($_POST['link_icon']) ?: '🔗';
    $is_active = isset($_POST['link_active']) ? 1 : 0;
    
    $stmt = $conn->prepare("UPDATE quick_links SET title = ?, url = ?, icon = ?, is_active = ? WHERE id = ?");
    $stmt->bind_param("sssii", $title, $url, $icon, $is_active, $id);
    $stmt->execute();
    $stmt->close();
    
    logAudit('update_quick_link', 'quick_link', $id, "Updated quick link: $title");
    header('Location: system_settings?tab=quicklinks&saved=link');
    exit;
}

// Handle quick link deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['delete_quick_link']) && $ql_table_exists) {
    $id = intval($_POST['link_id']);
    
    // Get title for audit log
    $stmt = $conn->prepare("SELECT title FROM quick_links WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $link = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $stmt = $conn->prepare("DELETE FROM quick_links WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    logAudit('delete_quick_link', 'quick_link', $id, "Deleted quick link: " . ($link['title'] ?? 'Unknown'));
    header('Location: system_settings?tab=quicklinks&saved=deleted');
    exit;
}

// Handle quick link reordering
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['reorder_quick_links']) && $ql_table_exists) {
    $order = $_POST['link_order'] ?? [];
    if (!empty($order)) {
        foreach ($order as $position => $id) {
            $id = intval($id);
            $pos = intval($position) + 1;
            $stmt_ql = $conn->prepare("UPDATE quick_links SET sort_order = ? WHERE id = ?");
            $stmt_ql->bind_param("ii", $pos, $id);
            $stmt_ql->execute();
            $stmt_ql->close();
        }
        logAudit('reorder_quick_links', 'quick_link', 0, "Reordered quick links");
    }
    header('Location: system_settings?tab=quicklinks&saved=reorder');
    exit;
}

// Handle reset to default quick links
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['reset_quick_links']) && $ql_table_exists) {
    // Delete all existing quick links
    $conn->query("DELETE FROM quick_links");
    
    // Insert defaults
    $conn->query("INSERT INTO quick_links (title, url, icon, sort_order) VALUES
        ('Request LOA', '/user/loa', '📅', 1),
        ('LOA Calendar', '/user/loa_calendar', '📆', 2),
        ('Messages', '/user/messages', '✉️', 3),
        ('Announcements', '/user/announcements', '📢', 4)
    ");
    
    logAudit('reset_quick_links', 'quick_link', 0, "Reset quick links to defaults");
    header('Location: system_settings?tab=quicklinks&saved=reset');
    exit;
}

// Get quick links for display
$quick_links = $ql_table_exists ? $conn->query("SELECT * FROM quick_links ORDER BY sort_order ASC, id ASC") : null;

// Get current settings
$settings = [];
$result = $conn->query("SELECT setting_key, setting_value FROM system_settings");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Set defaults
$defaults = [
    'community_name' => 'My Community',
    'community_logo' => '',
    'discord_webhook_url' => '',
    'discord_webhook_enabled' => '0',
    'email_notifications_enabled' => '1',
    'auto_loa_return' => '1',
    'motm_enabled' => '1',
    'applications_enabled' => '1',
    'registration_require_approval' => '1'
];

foreach ($defaults as $key => $value) {
    if (!isset($settings[$key])) $settings[$key] = $value;
}

$current_page = 'admin_settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <?php include '../includes/styles.php'; ?>
    <style>
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }
        
        .settings-grid .section.full-width {
            grid-column: 1 / -1;
        }
        
        @media (max-width: 900px) {
            .settings-grid { grid-template-columns: 1fr; gap: 16px; }
            .branding-grid { grid-template-columns: 1fr !important; }
        }
        
        .color-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .color-row label {
            flex: 1;
            margin: 0;
            font-size: 14px;
        }
        
        
        .color-row input[type="text"] {
            width: 90px;
            text-align: center;
            font-family: monospace;
            flex-shrink: 0;
        }
        
        .toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px;
            background: var(--bg-card);
            border-radius: var(--radius-md);
            margin-bottom: 12px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .toggle-row:hover {
            background: var(--bg-hover);
        }
        
        .toggle-row-info {
            flex: 1;
        }
        
        .toggle-row-title {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 2px;
        }
        
        .toggle-row-desc {
            font-size: 12px;
            color: var(--text-muted);
        }
        
        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            width: 48px;
            height: 26px;
            flex-shrink: 0;
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
            background: var(--bg-elevated);
            border-radius: 26px;
            transition: 0.3s;
        }
        
        .toggle-slider::before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background: white;
            border-radius: 50%;
            transition: 0.3s;
        }
        
        .toggle-switch input:checked + .toggle-slider {
            background: var(--accent);
        }
        
        .toggle-switch input:checked + .toggle-slider::before {
            transform: translateX(22px);
        }
        
        .preview-box {
            padding: 20px;
            border-radius: var(--radius-md);
            text-align: center;
            margin-top: 12px;
        }
        
        .preview-box h4 {
            font-size: 16px;
            margin-bottom: 4px;
            color: white;
        }
        
        .preview-box p {
            font-size: 13px;
            color: var(--text-secondary);
        }
        
        .logo-area {
            text-align: center;
            padding: 24px;
            border: 2px dashed var(--border);
            border-radius: var(--radius-md);
            margin-bottom: 16px;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-sizing: border-box;
            width: 100%;
        }
        
        .logo-area:hover {
            border-color: var(--accent);
        }
        
        .logo-area img {
            max-width: 100%;
            max-height: 150px;
            object-fit: contain;
            display: block;
        }
        
        .logo-preview {
            max-width: 120px;
            max-height: 120px;
            border-radius: var(--radius-md);
            margin-bottom: 12px;
        }
        
        .save-indicator {
            display: inline-block;
            padding: 4px 12px;
            background: rgba(16, 185, 129, 0.2);
            color: #4ade80;
            border-radius: var(--radius-lg);
            font-size: 12px;
            font-weight: 600;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .save-indicator.show {
            opacity: 1;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid rgba(88, 101, 242, 0.3);
        }
        
        .section-header h2 {
            margin: 0;
            border: none;
            padding: 0;
        }
        
        /* Quick Links Management */
        .quick-links-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .quick-link-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            transition: all 0.2s;
        }
        
        .quick-link-item:hover {
            background: var(--bg-hover);
        }
        
        .quick-link-item.inactive {
            opacity: 0.6;
        }
        
        .quick-link-item.dragging {
            background: var(--accent-muted);
            border-color: var(--accent);
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
        }
        
        .drag-handle {
            cursor: grab;
            color: var(--text-muted);
            font-size: 18px;
            padding: 4px;
            user-select: none;
        }
        
        .drag-handle:active {
            cursor: grabbing;
        }
        
        .link-preview {
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            color: white;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .link-url {
            flex: 1;
            font-family: monospace;
            font-size: 13px;
            color: var(--text-muted);
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .link-status {
            flex-shrink: 0;
        }
        
        .link-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }
        
        @media (max-width: 768px) {
            .quick-link-item {
                flex-wrap: wrap;
            }
            .link-url {
                order: 5;
                width: 100%;
                margin-top: 8px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container">
        <?php if (isset($_GET['saved'])): ?>
            <?php showToast("Settings saved successfully!", 'success'); ?>
        <?php endif; ?>
        
        <?php if (isset($_GET['webhook']) && in_array($_GET['webhook'], ['success', 'fail'], true)): ?>
            <?php 
            if ($_GET['webhook'] === 'success') {
                showToast('Admin webhook test sent successfully!', 'success');
            } else {
                showToast('Failed to send admin webhook. Check your URL.', 'error');
            }
            ?>
        <?php endif; ?>
        
        <?php if (isset($_GET['webhook_apps']) && in_array($_GET['webhook_apps'], ['success', 'fail'], true)): ?>
            <?php 
            if ($_GET['webhook_apps'] === 'success') {
                showToast('Applications webhook test sent successfully!', 'success');
            } else {
                showToast('Failed to send applications webhook. Check your URL.', 'error');
            }
            ?>
        <?php endif; ?>

        <div class="settings-grid">
            <!-- Community Settings (Combined) -->
            <div class="section full-width">
                <div class="section-header">
                    <h2>🏢 Community Settings</h2>
                    <span class="save-indicator" id="communityIndicator">✓ Saved</span>
                </div>
                
                <div class="form-group">
                    <label>Community Name</label>
                    <input type="text" id="community_name" value="<?php echo htmlspecialchars($settings['community_name']); ?>" data-key="community_name">
                </div>
                
                <div class="branding-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <div style="min-width: 0; overflow: hidden;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px;">Community Logo</label>
                        <div class="logo-area">
                            <?php if (!empty($settings['community_logo']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $settings['community_logo'])): ?>
                                <img src="<?php echo htmlspecialchars($settings['community_logo']); ?>" alt="Logo" class="logo-preview"><br>
                                <div style="display: flex; gap: 8px; justify-content: center; margin-top: 8px;">
                                    <form method="POST" enctype="multipart/form-data" style="display: inline;">
                                        <?php echo csrfField(); ?>
                                        <input type="file" name="logo" id="logo_change" accept="image/*" style="display: none;" onchange="this.form.submit()">
                                        <input type="hidden" name="upload_logo" value="1">
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('logo_change').click()">Change</button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Remove the logo?')">
                                        <?php echo csrfField(); ?>
                                        <button type="submit" name="remove_logo" class="btn btn-danger btn-sm">Remove</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div style="font-size: 40px; margin-bottom: 8px;">🖼️</div>
                                <p style="color: var(--text-muted); margin-bottom: 12px; font-size: 14px;">No logo uploaded</p>
                                <form method="POST" enctype="multipart/form-data">
                                    <?php echo csrfField(); ?>
                                    <input type="file" name="logo" id="logo_upload" accept="image/*" style="display: none;" onchange="this.form.submit()">
                                    <input type="hidden" name="upload_logo" value="1">
                                    <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('logo_upload').click()">Upload Logo</button>
                                </form>
                                <p style="color: var(--text-muted); margin-top: 8px; font-size: 11px;">PNG, JPG, GIF, WebP, SVG • Max 2MB</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Discord Integration -->
            <div class="section">
                <div class="section-header">
                    <h2>🔔 Discord Integration</h2>
                    <span class="save-indicator" id="discordIndicator">✓ Saved</span>
                </div>
                
                <div class="toggle-row" onclick="toggleFeature('discord_webhook_enabled', this)">
                    <div class="toggle-row-info">
                        <div class="toggle-row-title">Enable Discord Notifications</div>
                        <div class="toggle-row-desc">Send notifications to your Discord server</div>
                    </div>
                    <label class="toggle-switch" onclick="event.stopPropagation()">
                        <input type="checkbox" id="discord_webhook_enabled" <?php echo $settings['discord_webhook_enabled'] === '1' ? 'checked' : ''; ?> onchange="saveToggle('discord_webhook_enabled', this.checked, 'discordIndicator')">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                
                <form method="POST" id="discordForm">
                    <?php echo csrfField(); ?>
                    <div class="form-group">
                        <label>Admin Logs Webhook URL</label>
                        <input type="url" name="discord_webhook_url" id="discord_webhook_url" value="<?php echo htmlspecialchars($settings['discord_webhook_url']); ?>" placeholder="https://discord.com/api/webhooks/...">
                        <small>Used for admin actions, user management, system events</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Applications Webhook URL <span style="color: var(--text-muted); font-weight: normal;">(Optional)</span></label>
                        <input type="url" name="discord_webhook_applications_url" id="discord_webhook_applications_url" value="<?php echo htmlspecialchars($settings['discord_webhook_applications_url'] ?? ''); ?>" placeholder="https://discord.com/api/webhooks/...">
                        <small>Separate webhook for application submissions and decisions. Falls back to admin webhook if empty.</small>
                    </div>
                    
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <button type="button" onclick="saveDiscordUrls()" class="btn btn-primary">💾 Save URLs</button>
                        <button type="submit" name="test_webhook" class="btn btn-secondary">🧪 Test Admin Webhook</button>
                        <button type="submit" name="test_webhook_apps" class="btn btn-secondary">🧪 Test Apps Webhook</button>
                    </div>
                </form>
            </div>
            
            <!-- Features -->
            <div class="section">
                <div class="section-header">
                    <h2>⚡ Features</h2>
                    <span class="save-indicator" id="featuresIndicator">✓ Saved</span>
                </div>
                
                <div class="toggle-row" onclick="toggleFeature('email_notifications_enabled', this)">
                    <div class="toggle-row-info">
                        <div class="toggle-row-title">Email Notifications</div>
                        <div class="toggle-row-desc">Send email alerts for important events</div>
                    </div>
                    <label class="toggle-switch" onclick="event.stopPropagation()">
                        <input type="checkbox" id="email_notifications_enabled" <?php echo $settings['email_notifications_enabled'] === '1' ? 'checked' : ''; ?> onchange="saveToggle('email_notifications_enabled', this.checked)">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                
                <div class="toggle-row" onclick="toggleFeature('auto_loa_return', this)">
                    <div class="toggle-row-info">
                        <div class="toggle-row-title">Auto-Return from LOA</div>
                        <div class="toggle-row-desc">Automatically set users to active when LOA ends</div>
                    </div>
                    <label class="toggle-switch" onclick="event.stopPropagation()">
                        <input type="checkbox" id="auto_loa_return" <?php echo $settings['auto_loa_return'] === '1' ? 'checked' : ''; ?> onchange="saveToggle('auto_loa_return', this.checked)">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                
                <div class="toggle-row" onclick="toggleFeature('motm_enabled', this)">
                    <div class="toggle-row-info">
                        <div class="toggle-row-title">Member of the Month</div>
                        <div class="toggle-row-desc">Enable recognition and awards system</div>
                    </div>
                    <label class="toggle-switch" onclick="event.stopPropagation()">
                        <input type="checkbox" id="motm_enabled" <?php echo $settings['motm_enabled'] === '1' ? 'checked' : ''; ?> onchange="saveToggle('motm_enabled', this.checked)">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                
                <div class="toggle-row" onclick="toggleFeature('applications_enabled', this)">
                    <div class="toggle-row-info">
                        <div class="toggle-row-title">Public Applications</div>
                        <div class="toggle-row-desc">Allow public applications to join departments</div>
                    </div>
                    <label class="toggle-switch" onclick="event.stopPropagation()">
                        <input type="checkbox" id="applications_enabled" <?php echo $settings['applications_enabled'] === '1' ? 'checked' : ''; ?> onchange="saveToggle('applications_enabled', this.checked)">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                
                <div class="toggle-row" onclick="toggleFeature('registration_require_approval', this)">
                    <div class="toggle-row-info">
                        <div class="toggle-row-title">Require Registration Approval</div>
                        <div class="toggle-row-desc">New members must be approved by staff before accessing the community. When off, registration is open to everyone.</div>
                    </div>
                    <label class="toggle-switch" onclick="event.stopPropagation()">
                        <input type="checkbox" id="registration_require_approval" <?php echo $settings['registration_require_approval'] === '1' ? 'checked' : ''; ?> onchange="saveToggle('registration_require_approval', this.checked)">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
            
            <!-- Quick Links Management -->
            <div class="section full-width">
                <div class="section-header">
                    <h2>🔗 Dashboard Quick Links</h2>
                    <div style="display: flex; gap: 8px;">
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Reset all quick links to defaults? This will delete any custom links.')">
                    <?php echo csrfField(); ?>
                            <button type="submit" name="reset_quick_links" class="btn btn-sm" style="background: var(--bg-elevated);">🔄 Reset to Defaults</button>
                        </form>
                        <button type="button" class="btn btn-sm btn-primary" onclick="showAddLinkModal()">➕ Add Link</button>
                    </div>
                </div>
                
                <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 20px;">
                    Manage the quick access buttons shown in the welcome section of the dashboard. Drag to reorder.
                </p>
                
                <?php if ($quick_links && $quick_links->num_rows > 0): ?>
                <form method="POST" id="reorderForm">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="reorder_quick_links" value="1">
                    <div class="quick-links-list" id="quickLinksList">
                        <?php while ($link = $quick_links->fetch_assoc()): ?>
                        <div class="quick-link-item <?php echo !$link['is_active'] ? 'inactive' : ''; ?>" data-id="<?php echo $link['id']; ?>">
                            <input type="hidden" name="link_order[]" value="<?php echo $link['id']; ?>">
                            <div class="drag-handle">⋮⋮</div>
                            <div class="link-preview" style="background: var(--accent);">
                                <?php echo htmlspecialchars($link['icon'] . ' ' . $link['title']); ?>
                            </div>
                            <div class="link-url"><?php echo htmlspecialchars($link['url']); ?></div>
                            <div class="link-status">
                                <?php if ($link['is_active']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Inactive</span>
                                <?php endif; ?>
                            </div>
                            <div class="link-actions">
                                <button type="button" class="btn btn-sm" style="background: var(--bg-elevated);" onclick="editLink(<?php echo htmlspecialchars(json_encode($link)); ?>)">✏️ Edit</button>
                                <button type="button" class="btn btn-sm btn-danger" onclick="deleteLink(<?php echo $link['id']; ?>, '<?php echo htmlspecialchars(addslashes($link['title'])); ?>')">🗑️</button>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <button type="submit" class="btn btn-secondary" id="saveOrderBtn" style="margin-top: 16px; display: none;">💾 Save Order</button>
                </form>
                <?php else: ?>
                <div class="empty-state" style="padding: 40px;">
                    <p>No quick links configured. Add some to help users navigate quickly!</p>
                    <button type="button" class="btn btn-primary" onclick="showAddLinkModal()" style="margin-top: 16px;">➕ Add Quick Link</button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Add/Edit Quick Link Modal -->
    <div class="modal" id="linkModal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3 id="linkModalTitle">Add Quick Link</h3>
                <button class="modal-close" onclick="closeLinkModal()">&times;</button>
            </div>
            <form method="POST" id="linkForm">
                    <?php echo csrfField(); ?>
                <input type="hidden" name="add_quick_link" id="linkActionInput" value="1">
                <input type="hidden" name="link_id" id="link_id">
                
                <div class="form-group">
                    <label>Icon (Emoji)</label>
                    <input type="text" name="link_icon" id="link_icon" placeholder="📌" maxlength="10" style="width: 80px;">
                    <small>Use an emoji as the button icon</small>
                </div>
                
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="link_title" id="link_title" required placeholder="e.g. Roster">
                </div>
                
                <div class="form-group">
                    <label>URL *</label>
                    <input type="text" name="link_url" id="link_url" required placeholder="/user/roster or https://...">
                    <small>Internal paths start with / or use full URLs for external links</small>
                </div>
                
                <div class="form-group" id="activeToggleGroup" style="display: none;">
                    <label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                        <input type="checkbox" name="link_active" id="link_active" checked style="width: auto;">
                        <span>Link is active and visible</span>
                    </label>
                </div>
                
                <div class="form-group" style="margin-top: 24px;">
                    <button type="submit" id="linkSubmitBtn" class="btn btn-primary btn-block">Add Link</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Confirmation Form (hidden) -->
    <form method="POST" id="deleteLinkForm" style="display: none;">
                    <?php echo csrfField(); ?>
        <input type="hidden" name="link_id" id="delete_link_id">
        <input type="hidden" name="delete_quick_link" value="1">
    </form>

    <script>
        // CSRF token for AJAX calls
        const csrfToken = '<?php echo htmlspecialchars(generateCSRFToken()); ?>';
        
        // Auto-save function
        function saveSetting(key, value, indicatorId) {
            const indicator = document.getElementById(indicatorId);
            
            fetch('system_settings', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ajax_save=1&csrf_token=' + encodeURIComponent(csrfToken) + '&key=' + encodeURIComponent(key) + '&value=' + encodeURIComponent(value)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success && indicator) {
                    indicator.classList.add('show');
                    setTimeout(() => indicator.classList.remove('show'), 2000);
                }
            })
            .catch(err => console.error('Save failed:', err));
        }
        
        // Toggle feature helper
        function toggleFeature(id, row) {
            const checkbox = document.getElementById(id);
            checkbox.checked = !checkbox.checked;
            saveToggle(id, checkbox.checked);
        }
        
        // Save toggle (checkbox) - indicator defaults to featuresIndicator
        function saveToggle(key, checked, indicator) {
            saveSetting(key, checked ? '1' : '0', indicator || 'featuresIndicator');
        }
        
        // Community name - save on blur
        document.getElementById('community_name').addEventListener('blur', function() {
            saveSetting('community_name', this.value, 'communityIndicator');
        });
        
        // Save both Discord webhook URLs
        function saveDiscordUrls() {
            const adminUrl = document.getElementById('discord_webhook_url').value;
            const appsUrl = document.getElementById('discord_webhook_applications_url').value;
            
            // Save admin webhook URL
            saveSetting('discord_webhook_url', adminUrl, 'discordIndicator');
            
            // Save applications webhook URL
            setTimeout(() => {
                saveSetting('discord_webhook_applications_url', appsUrl, 'discordIndicator');
            }, 100);
        }
        
        // Quick Links Management
        function showAddLinkModal() {
            document.getElementById('linkModalTitle').textContent = 'Add Quick Link';
            document.getElementById('linkForm').reset();
            document.getElementById('link_id').value = '';
            document.getElementById('linkActionInput').name = 'add_quick_link';
            document.getElementById('linkSubmitBtn').textContent = 'Add Link';
            document.getElementById('activeToggleGroup').style.display = 'none';
            document.getElementById('link_active').checked = true;
            openModal('linkModal');
        }

        function editLink(link) {
            document.getElementById('linkModalTitle').textContent = 'Edit Quick Link';
            document.getElementById('link_id').value = link.id;
            document.getElementById('link_icon').value = link.icon;
            document.getElementById('link_title').value = link.title;
            document.getElementById('link_url').value = link.url;
            document.getElementById('link_active').checked = link.is_active == 1;
            document.getElementById('linkActionInput').name = 'update_quick_link';
            document.getElementById('linkSubmitBtn').textContent = 'Save Changes';
            document.getElementById('activeToggleGroup').style.display = 'block';
            openModal('linkModal');
        }

        function closeLinkModal() {
            closeModal('linkModal');
        }

        function deleteLink(id, title) {
            if (confirm('Delete the quick link "' + title + '"? This cannot be undone.')) {
                document.getElementById('delete_link_id').value = id;
                document.getElementById('deleteLinkForm').submit();
            }
        }
        
        // Drag and drop reordering
        let draggedItem = null;
        
        document.querySelectorAll('.quick-link-item').forEach(item => {
            item.addEventListener('dragstart', handleDragStart);
            item.addEventListener('dragend', handleDragEnd);
            item.addEventListener('dragover', handleDragOver);
            item.addEventListener('drop', handleDrop);
            item.setAttribute('draggable', 'true');
        });
        
        function handleDragStart(e) {
            draggedItem = this;
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        }
        
        function handleDragEnd(e) {
            this.classList.remove('dragging');
            document.querySelectorAll('.quick-link-item').forEach(item => {
                item.classList.remove('drag-over');
            });
            draggedItem = null;
        }
        
        function handleDragOver(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            
            if (this !== draggedItem) {
                const list = document.getElementById('quickLinksList');
                const items = [...list.querySelectorAll('.quick-link-item')];
                const currentIndex = items.indexOf(this);
                const draggedIndex = items.indexOf(draggedItem);
                
                if (currentIndex > draggedIndex) {
                    this.parentNode.insertBefore(draggedItem, this.nextSibling);
                } else {
                    this.parentNode.insertBefore(draggedItem, this);
                }
                
                // Show save button
                document.getElementById('saveOrderBtn').style.display = 'block';
            }
        }
        
        function handleDrop(e) {
            e.preventDefault();
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>
