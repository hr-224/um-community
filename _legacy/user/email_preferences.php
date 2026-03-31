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

// Get or create preferences
$stmt = $conn->prepare("SELECT * FROM user_email_preferences WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$prefs = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$prefs) {
    // Create default preferences
    $stmt = $conn->prepare("INSERT INTO user_email_preferences (user_id) VALUES (?)");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    
    $prefs = [
        'weekly_activity_report' => 1,
        'monthly_activity_report' => 1,
        'certification_expiry_alerts' => 1,
        'shift_reminders' => 1,
        'event_reminders' => 1,
        'announcement_notifications' => 1
    ];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken()) {
    $weekly = isset($_POST['weekly_activity_report']) ? 1 : 0;
    $monthly = isset($_POST['monthly_activity_report']) ? 1 : 0;
    $cert_alerts = isset($_POST['certification_expiry_alerts']) ? 1 : 0;
    $shift_reminders = isset($_POST['shift_reminders']) ? 1 : 0;
    $event_reminders = isset($_POST['event_reminders']) ? 1 : 0;
    $announcements = isset($_POST['announcement_notifications']) ? 1 : 0;
    
    $stmt = $conn->prepare("UPDATE user_email_preferences SET weekly_activity_report = ?, monthly_activity_report = ?, certification_expiry_alerts = ?, shift_reminders = ?, event_reminders = ?, announcement_notifications = ? WHERE user_id = ?");
    $stmt->bind_param("iiiiiii", $weekly, $monthly, $cert_alerts, $shift_reminders, $event_reminders, $announcements, $user_id);
    $stmt->execute();
    $stmt->close();
    
    $message = "Email preferences saved!";
    
    // Refresh prefs
    $stmt = $conn->prepare("SELECT * FROM user_email_preferences WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $prefs = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$conn->close();
$theme = getThemeColors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Preferences - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
    <style>
        .pref-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 24px; max-width: 600px; margin: 0 auto; }
        .pref-group { border-bottom: 1px solid var(--border); padding: 16px 0; }
        .pref-group:last-child { border-bottom: none; }
        .pref-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; }
        .pref-info h4 { margin: 0 0 4px 0; font-size: 15px; color: var(--text-primary); }
        .pref-info p { margin: 0; font-size: 13px; color: var(--text-muted); }
        .toggle { position: relative; width: 50px; height: 26px; }
        .toggle input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--bg-elevated); transition: 0.3s; border-radius: 26px; }
        .toggle-slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: 0.3s; border-radius: 50%; }
        .toggle input:checked + .toggle-slider { background-color: var(--accent); }
        .toggle input:checked + .toggle-slider:before { transform: translateX(24px); }
        .section-title { font-size: 12px; text-transform: uppercase; color: var(--text-muted); letter-spacing: 1px; margin-bottom: 8px; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header" style="justify-content: center;">
        <h1>📧 Email Preferences</h1>
    </div>
    
    <?php if ($message) showToast($message, 'success'); ?>
    
    <div class="pref-card">
        <form method="POST">
            <?php echo csrfField(); ?>
            
            <div class="pref-group">
                <div class="section-title">Activity Reports</div>
                
                <div class="pref-item">
                    <div class="pref-info">
                        <h4>Weekly Activity Report</h4>
                        <p>Receive a summary of your activity each week</p>
                    </div>
                    <label class="toggle">
                        <input type="checkbox" name="weekly_activity_report" <?php echo $prefs['weekly_activity_report'] ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                
                <div class="pref-item">
                    <div class="pref-info">
                        <h4>Monthly Activity Report</h4>
                        <p>Receive a detailed monthly summary</p>
                    </div>
                    <label class="toggle">
                        <input type="checkbox" name="monthly_activity_report" <?php echo $prefs['monthly_activity_report'] ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
            
            <div class="pref-group">
                <div class="section-title">Alerts & Reminders</div>
                
                <div class="pref-item">
                    <div class="pref-info">
                        <h4>Certification Expiry Alerts</h4>
                        <p>Get notified when your certifications are expiring</p>
                    </div>
                    <label class="toggle">
                        <input type="checkbox" name="certification_expiry_alerts" <?php echo $prefs['certification_expiry_alerts'] ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                
                <div class="pref-item">
                    <div class="pref-info">
                        <h4>Shift Reminders</h4>
                        <p>Receive reminders for upcoming shifts</p>
                    </div>
                    <label class="toggle">
                        <input type="checkbox" name="shift_reminders" <?php echo $prefs['shift_reminders'] ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                
                <div class="pref-item">
                    <div class="pref-info">
                        <h4>Event Reminders</h4>
                        <p>Get notified about upcoming events</p>
                    </div>
                    <label class="toggle">
                        <input type="checkbox" name="event_reminders" <?php echo $prefs['event_reminders'] ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
            
            <div class="pref-group">
                <div class="section-title">Notifications</div>
                
                <div class="pref-item">
                    <div class="pref-info">
                        <h4>Announcement Notifications</h4>
                        <p>Receive emails for new announcements</p>
                    </div>
                    <label class="toggle">
                        <input type="checkbox" name="announcement_notifications" <?php echo $prefs['announcement_notifications'] ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 16px;">Save Preferences</button>
        </form>
    </div>
</div>
</body>
</html>
