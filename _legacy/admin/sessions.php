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
$user_id = $_SESSION['user_id'];
$message = '';

// Handle session revocation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'revoke') {
        $session_id = intval($_POST['session_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM active_sessions WHERE id = ?");
        $stmt->bind_param("i", $session_id);
        if ($stmt->execute()) {
            $message = 'Session revoked.';
            logAudit('session_revoke', 'session', $session_id, 'Revoked user session');
        }
        $stmt->close();
    } elseif ($action === 'revoke_all_user') {
        $target_user = intval($_POST['target_user'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM active_sessions WHERE user_id = ?");
        $stmt->bind_param("i", $target_user);
        if ($stmt->execute()) {
            $message = 'All sessions for user revoked.';
            logAudit('session_revoke_all', 'user', $target_user, 'Revoked all sessions for user');
        }
        $stmt->close();
    } elseif ($action === 'cleanup') {
        // Remove sessions older than 30 days
        $conn->query("DELETE FROM active_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $message = 'Old sessions cleaned up.';
    }
}

// Get active sessions
$sessions = $conn->query("
    SELECT s.*, u.username
    FROM active_sessions s
    JOIN users u ON s.user_id = u.id
    ORDER BY s.last_activity DESC
    LIMIT 200
")->fetch_all(MYSQLI_ASSOC);

// Get session stats
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_sessions,
        COUNT(DISTINCT user_id) as unique_users,
        SUM(CASE WHEN last_activity > DATE_SUB(NOW(), INTERVAL 15 MINUTE) THEN 1 ELSE 0 END) as active_now
    FROM active_sessions
")->fetch_assoc();

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
    <title>Session Management - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
    <style>
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-top: 24px; margin-bottom: 24px; }
        .stat-box { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px; text-align: center; }
        .stat-value { font-size: 28px; font-weight: 700; color: var(--text-primary); }
        .stat-label { font-size: 13px; color: var(--text-muted); margin-top: 4px; }
        .active-indicator { display: inline-block; width: 8px; height: 8px; background: var(--success); border-radius: 50%; margin-right: 6px; }
        .inactive-indicator { display: inline-block; width: 8px; height: 8px; background: #6b7280; border-radius: 50%; margin-right: 6px; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>🔐 Session Management</h1>
        <form method="POST" style="margin:0;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
            <input type="hidden" name="action" value="cleanup">
            <button type="submit" class="btn btn-secondary">🧹 Cleanup Old Sessions</button>
        </form>
    </div>
    
    <?php if ($message) showToast($message, 'success'); ?>
    
    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-value"><?php echo $stats['total_sessions'] ?? 0; ?></div>
            <div class="stat-label">Total Sessions</div>
        </div>
        <div class="stat-box">
            <div class="stat-value"><?php echo $stats['unique_users'] ?? 0; ?></div>
            <div class="stat-label">Unique Users</div>
        </div>
        <div class="stat-box">
            <div class="stat-value" style="color:var(--success);"><?php echo $stats['active_now'] ?? 0; ?></div>
            <div class="stat-label">Active Now</div>
        </div>
    </div>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Device</th>
                    <th>IP Address</th>
                    <th>Last Activity</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessions as $s): 
                    $is_active = strtotime($s['last_activity']) > strtotime('-15 minutes');
                ?>
                <tr>
                    <td>
                        <span class="<?php echo $is_active ? 'active-indicator' : 'inactive-indicator'; ?>"></span>
                        <?php echo htmlspecialchars($s['username']); ?>
                    </td>
                    <td><?php echo htmlspecialchars(parseUserAgent($s['user_agent'] ?? '')); ?></td>
                    <td><code><?php echo htmlspecialchars($s['ip_address'] ?? '—'); ?></code></td>
                    <td><?php echo date('M j, g:i A', strtotime($s['last_activity'])); ?></td>
                    <td><?php echo date('M j, g:i A', strtotime($s['created_at'])); ?></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Revoke this session?');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                            <input type="hidden" name="action" value="revoke">
                            <input type="hidden" name="session_id" value="<?php echo $s['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Revoke</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
