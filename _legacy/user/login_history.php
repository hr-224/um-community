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
requireLogin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get login history
$stmt = $conn->prepare("
    SELECT * FROM login_history 
    WHERE user_id = ? 
    ORDER BY login_at DESC 
    LIMIT 50
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

function parseUserAgent($ua) {
    $browser = 'Unknown';
    $os = 'Unknown';
    
    if (preg_match('/Firefox/i', $ua)) $browser = 'Firefox';
    elseif (preg_match('/Chrome/i', $ua)) $browser = 'Chrome';
    elseif (preg_match('/Safari/i', $ua)) $browser = 'Safari';
    elseif (preg_match('/Edge/i', $ua)) $browser = 'Edge';
    elseif (preg_match('/MSIE|Trident/i', $ua)) $browser = 'IE';
    
    if (preg_match('/Windows/i', $ua)) $os = 'Windows';
    elseif (preg_match('/Mac/i', $ua)) $os = 'macOS';
    elseif (preg_match('/Linux/i', $ua)) $os = 'Linux';
    elseif (preg_match('/Android/i', $ua)) $os = 'Android';
    elseif (preg_match('/iPhone|iPad/i', $ua)) $os = 'iOS';
    
    return "$browser on $os";
}

$theme = getThemeColors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login History - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
    <style>
        .history-list { display: flex; flex-direction: column; gap: 12px; }
        .history-item { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; }
        .history-item.failed { border-left: 3px solid var(--danger); }
        .history-info { display: flex; align-items: center; gap: 16px; }
        .history-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; }
        .history-icon.success { background: rgba(16,185,129,0.2); color: var(--success); }
        .history-icon.failed { background: rgba(239,68,68,0.2); color: var(--danger); }
        .history-details h4 { color: var(--text-primary); margin: 0 0 4px; font-size: 14px; }
        .history-details p { color: var(--text-muted); margin: 0; font-size: 12px; }
        .history-meta { text-align: right; }
        .history-date { color: var(--text-secondary); font-size: 14px; }
        .history-time { color: var(--text-muted); font-size: 12px; }
        .security-tip { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px; margin-bottom: 24px; }
        .security-tip h3 { color: var(--text-primary); margin: 0 0 8px; font-size: 16px; }
        .security-tip p { color: var(--text-secondary); margin: 0; font-size: 14px; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>🔐 Login History</h1>
    </div>
    
    <div class="security-tip">
        <h3>🛡️ Security Tip</h3>
        <p>Review your login history regularly. If you see any logins you don't recognize, change your password immediately and consider enabling two-factor authentication.</p>
    </div>
    
    <?php if (empty($history)): ?>
        <div class="empty-state"><p>No login history available.</p></div>
    <?php else: ?>
    <div class="history-list">
        <?php foreach ($history as $h): ?>
        <div class="history-item <?php echo !$h['success'] ? 'failed' : ''; ?>">
            <div class="history-info">
                <div class="history-icon <?php echo $h['success'] ? 'success' : 'failed'; ?>">
                    <?php echo $h['success'] ? '✓' : '✗'; ?>
                </div>
                <div class="history-details">
                    <h4><?php echo $h['success'] ? 'Successful Login' : 'Failed Login Attempt'; ?></h4>
                    <p>
                        <?php echo htmlspecialchars(parseUserAgent($h['user_agent'] ?? '')); ?> • 
                        IP: <?php echo htmlspecialchars($h['ip_address'] ?? 'Unknown'); ?>
                        <?php if (!$h['success'] && $h['failure_reason']): ?>
                            • Reason: <?php echo htmlspecialchars($h['failure_reason']); ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="history-meta">
                <div class="history-date"><?php echo date('M j, Y', strtotime($h['login_at'])); ?></div>
                <div class="history-time"><?php echo date('g:i A', strtotime($h['login_at'])); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
