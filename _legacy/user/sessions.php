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

// Handle session revocation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'revoke') {
        $session_id = intval($_POST['session_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM active_sessions WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $session_id, $user_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $message = 'Session revoked.';
        }
        $stmt->close();
    } elseif ($action === 'revoke_all') {
        $current_token = $_SESSION['session_token'] ?? '';
        $stmt = $conn->prepare("DELETE FROM active_sessions WHERE user_id = ? AND session_token != ?");
        $stmt->bind_param("is", $user_id, $current_token);
        if ($stmt->execute()) {
            $message = 'All other sessions revoked.';
        }
        $stmt->close();
    }
}

// Get user's active sessions
$current_token = $_SESSION['session_token'] ?? '';
$stmt = $conn->prepare("SELECT * FROM active_sessions WHERE user_id = ? ORDER BY last_activity DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get login history
$stmt = $conn->prepare("SELECT * FROM login_history WHERE user_id = ? ORDER BY login_at DESC LIMIT 20");
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
    <title>Sessions - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
    <style>
        .session-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; }
        .session-card.current { border-color: var(--success); }
        .session-info h4 { color: var(--text-primary); margin: 0 0 8px 0; font-size: 15px; display: flex; align-items: center; gap: 8px; }
        .session-meta { font-size: 13px; color: var(--text-muted); }
        .current-badge { background: var(--success); color: var(--text-primary); font-size: 10px; padding: 2px 8px; border-radius: var(--radius-md); }
        .history-table { width: 100%; border-collapse: collapse; }
        .history-table th, .history-table td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border); }
        .history-table th { color: var(--text-muted); font-weight: 500; font-size: 13px; }
        .status-success { color: var(--success); }
        .status-failed { color: var(--danger); }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>🔐 Sessions & Security</h1>
    </div>
    
    <?php if ($message) showToast($message, 'success'); ?>
    
    <h2 style="font-size:18px;color:#fff;margin-bottom:16px;">Active Sessions</h2>
    
    <?php if (count($sessions) > 1): ?>
    <form method="POST" style="margin-bottom:20px;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
        <input type="hidden" name="action" value="revoke_all">
        <button type="submit" class="btn btn-danger" onclick="return confirm('Sign out of all other devices?');">Sign Out All Other Sessions</button>
    </form>
    <?php endif; ?>
    
    <?php foreach ($sessions as $session): 
        $is_current = $session['session_token'] === $current_token;
    ?>
    <div class="session-card <?php echo $is_current ? 'current' : ''; ?>">
        <div class="session-info">
            <h4>
                <?php echo parseUserAgent($session['user_agent']); ?>
                <?php if ($is_current): ?><span class="current-badge">Current</span><?php endif; ?>
            </h4>
            <div class="session-meta">
                IP: <?php echo htmlspecialchars($session['ip_address']); ?> • 
                Last active: <?php echo date('M j, Y g:i A', strtotime($session['last_activity'])); ?>
            </div>
        </div>
        <?php if (!$is_current): ?>
        <form method="POST" style="margin:0;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
            <input type="hidden" name="action" value="revoke">
            <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
            <button type="submit" class="btn btn-sm btn-danger">Revoke</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    
    <?php if (empty($sessions)): ?>
        <div class="empty-state"><p>No active sessions tracked.</p></div>
    <?php endif; ?>
    
    <h2 style="font-size:18px;color:#fff;margin:30px 0 16px 0;">Login History</h2>
    
    <?php if (empty($history)): ?>
        <div class="empty-state"><p>No login history available.</p></div>
    <?php else: ?>
    <div class="table-container">
        <table class="history-table">
            <thead>
                <tr><th>Date</th><th>IP Address</th><th>Device</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($history as $h): ?>
                <tr>
                    <td><?php echo date('M j, Y g:i A', strtotime($h['login_at'])); ?></td>
                    <td><?php echo htmlspecialchars($h['ip_address']); ?></td>
                    <td><?php echo parseUserAgent($h['user_agent']); ?></td>
                    <td class="<?php echo $h['success'] ? 'status-success' : 'status-failed'; ?>">
                        <?php echo $h['success'] ? '✓ Success' : '✗ Failed'; ?>
                        <?php if ($h['failure_reason']): ?><br><small style="color:var(--text-muted);"><?php echo htmlspecialchars($h['failure_reason']); ?></small><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
