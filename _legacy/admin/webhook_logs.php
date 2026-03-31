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

// Handle clear logs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken()) {
    if ($_POST['action'] === 'clear_old') {
        $conn->query("DELETE FROM webhook_logs WHERE sent_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $message = 'Old logs cleared.';
    } elseif ($_POST['action'] === 'clear_all') {
        $conn->query("TRUNCATE TABLE webhook_logs");
        $message = 'All logs cleared.';
    }
}

// Get webhook logs
$logs = $conn->query("
    SELECT * FROM webhook_logs 
    ORDER BY sent_at DESC 
    LIMIT 200
")->fetch_all(MYSQLI_ASSOC);

// Get stats
$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(success = 1) as successful,
        SUM(success = 0) as failed
    FROM webhook_logs
    WHERE sent_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
")->fetch_assoc();

$conn->close();

$theme = getThemeColors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webhook Logs - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
    <style>
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-top: 24px; margin-bottom: 24px; }
        .stat-box { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px; text-align: center; }
        .stat-value { font-size: 28px; font-weight: 700; color: var(--text-primary); }
        .stat-label { font-size: 13px; color: var(--text-muted); margin-top: 4px; }
        .status-success { color: var(--success); }
        .status-failed { color: var(--danger); }
        .payload-preview { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; color: var(--text-muted); }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>🔗 Webhook Logs</h1>
        <div style="display:flex;gap:10px;">
            <form method="POST" style="margin:0;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                <input type="hidden" name="action" value="clear_old">
                <button type="submit" class="btn btn-secondary">Clear Old (30d+)</button>
            </form>
            <form method="POST" style="margin:0;" onsubmit="return confirm('Clear ALL webhook logs?');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                <input type="hidden" name="action" value="clear_all">
                <button type="submit" class="btn btn-danger">Clear All</button>
            </form>
        </div>
    </div>
    
    <?php if ($message) showToast($message, 'success'); ?>
    
    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-value"><?php echo $stats['total'] ?? 0; ?></div>
            <div class="stat-label">Last 7 Days</div>
        </div>
        <div class="stat-box">
            <div class="stat-value" style="color:var(--success);"><?php echo $stats['successful'] ?? 0; ?></div>
            <div class="stat-label">Successful</div>
        </div>
        <div class="stat-box">
            <div class="stat-value" style="color:var(--danger);"><?php echo $stats['failed'] ?? 0; ?></div>
            <div class="stat-label">Failed</div>
        </div>
    </div>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Response</th>
                    <th>Error</th>
                    <th>Sent At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo htmlspecialchars($log['webhook_type']); ?></td>
                    <td class="<?php echo $log['success'] ? 'status-success' : 'status-failed'; ?>">
                        <?php echo $log['success'] ? '✓ Success' : '✗ Failed'; ?>
                        <?php if ($log['response_code']): ?><span style="opacity:0.7;"> (<?php echo $log['response_code']; ?>)</span><?php endif; ?>
                    </td>
                    <td class="payload-preview"><?php echo htmlspecialchars(substr($log['response_body'] ?? '', 0, 100)); ?></td>
                    <td style="color:var(--danger);font-size:12px;"><?php echo htmlspecialchars($log['error_message'] ?? '—'); ?></td>
                    <td><?php echo date('M j, g:i A', strtotime($log['sent_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
