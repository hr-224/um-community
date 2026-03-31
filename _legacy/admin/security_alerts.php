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
if (!defined('UM_EMAIL_LOADED')) { require_once __DIR__ . '/../includes/email.php'; }
requireAdmin();

$conn = getDBConnection();
$message = '';

// Check if security_alerts table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'security_alerts'");
$table_exists = ($tableCheck && $tableCheck->num_rows > 0);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && $table_exists) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'resolve') {
        $alert_id = intval($_POST['alert_id'] ?? 0);
        $stmt = $conn->prepare("UPDATE security_alerts SET is_resolved = 1, resolved_by = ?, resolved_at = NOW() WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $_SESSION['user_id'], $alert_id);
            $stmt->execute();
            $stmt->close();
            $message = 'Alert resolved.';
        }
    }
    
    if ($action === 'resolve_all') {
        $stmt = $conn->prepare("UPDATE security_alerts SET is_resolved = 1, resolved_by = ?, resolved_at = NOW() WHERE is_resolved = 0");
        if ($stmt) {
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            $message = "Resolved $affected alerts.";
        }
    }
}

// Filters
$filter_severity = $_GET['severity'] ?? '';
$filter_type = $_GET['type'] ?? '';
$filter_user = $_GET['user'] ?? '';
$filter_resolved = $_GET['resolved'] ?? 'unresolved';

// Build query
$where = [];
$params = [];
$types = '';

if ($filter_severity) {
    $where[] = "sa.severity = ?";
    $params[] = $filter_severity;
    $types .= 's';
}
if ($filter_type) {
    $where[] = "sa.alert_type = ?";
    $params[] = $filter_type;
    $types .= 's';
}
if ($filter_user) {
    $where[] = "(u.username LIKE ? OR u.email LIKE ?)";
    $params[] = "%$filter_user%";
    $params[] = "%$filter_user%";
    $types .= 'ss';
}
if ($filter_resolved === 'unresolved') {
    $where[] = "sa.is_resolved = 0";
} elseif ($filter_resolved === 'resolved') {
    $where[] = "sa.is_resolved = 1";
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get alerts (only if table exists)
$alerts = [];
$stats = [
    'total' => 0,
    'unresolved' => 0,
    'critical' => 0,
    'today' => 0
];

if ($table_exists) {
    $sql = "SELECT sa.*, u.username, u.email, r.username as resolved_by_username
            FROM security_alerts sa
            JOIN users u ON sa.user_id = u.id
            LEFT JOIN users r ON sa.resolved_by = r.id
            $where_sql
            ORDER BY sa.created_at DESC
            LIMIT 100";

    if ($params) {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $alerts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    } else {
        $result = $conn->query($sql);
        $alerts = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    // Get stats
    $result = $conn->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_resolved = 0 THEN 1 ELSE 0 END) as unresolved,
        SUM(CASE WHEN severity = 'critical' AND is_resolved = 0 THEN 1 ELSE 0 END) as critical,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today
        FROM security_alerts");
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row) {
            $stats = $row;
        }
    }
}

$conn->close();

include '../includes/navbar.php';
$colors = getThemeColors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Alerts - Admin - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <?php include '../includes/styles.php'; ?>
    <style>
        .container { max-width: 1400px; margin: 0 auto; padding: 30px 20px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 16px; }
        .page-header h1 { font-size: 28px; font-weight: 800; color: var(--text-primary); margin: 0; display: flex; align-items: center; gap: 12px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card {
            background: var(--bg-elevated);
            border-radius: var(--radius-md);
            padding: 20px;
            text-align: center;
            border: 1px solid var(--bg-elevated);
        }
        .stat-value { font-size: 32px; font-weight: 800; color: var(--text-primary); }
        .stat-label { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
        .stat-card.danger .stat-value { color: var(--danger); }
        .stat-card.warning .stat-value { color: #f59e0b; }
        .stat-card.success .stat-value { color: #22c55e; }
        
        .filters {
            background: var(--bg-elevated);
            border-radius: var(--radius-md);
            padding: 20px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: flex-end;
        }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-group label { font-size: 12px; color: var(--text-muted); }
        .filter-group select, .filter-group input {
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--bg-hover);
            background: var(--bg-elevated);
            color: var(--text-primary);
            font-size: 14px;
            min-width: 150px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .btn-primary { background: var(--accent); color: var(--text-primary); }
        .btn-danger { background: var(--danger); color: var(--text-primary); }
        .btn-ghost { background: var(--bg-elevated); color: var(--text-primary); }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }
        
        .alert-table { width: 100%; border-collapse: collapse; }
        .alert-table th, .alert-table td { padding: 14px 16px; text-align: left; }
        .alert-table th { background: var(--bg-primary); color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        .alert-table tr { border-bottom: 1px solid var(--bg-elevated); }
        .alert-table tr:hover { background: var(--bg-primary); }
        .alert-table td { color: var(--text-primary); font-size: 14px; }
        
        .severity-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: var(--radius-lg);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .severity-low { background: rgba(59,130,246,0.2); color: #3b82f6; }
        .severity-medium { background: rgba(245,158,11,0.2); color: #f59e0b; }
        .severity-high { background: rgba(239,68,68,0.2); color: var(--danger); }
        .severity-critical { background: #dc2626; color: var(--text-primary); }
        
        .type-badge { font-size: 18px; margin-right: 8px; }
        
        .resolved-badge { color: #22c55e; font-size: 12px; }
        
        .user-info { display: flex; flex-direction: column; gap: 2px; }
        .user-name { font-weight: 600; }
        .user-email { font-size: 12px; color: var(--text-muted); }
        
        .message { padding: 14px 18px; border-radius: var(--radius-md); margin-bottom: 20px; background: rgba(34,197,94,0.15); border: 1px solid rgba(34,197,94,0.3); color: #22c55e; }
        
        .empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
        .empty-state .icon { font-size: 64px; margin-bottom: 16px; opacity: 0.5; }
        
        .table-container {
            background: var(--bg-elevated);
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid var(--bg-elevated);
        }
        
        .details-cell { max-width: 300px; }
        .details-text { font-size: 12px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1>🚨 Security Alerts</h1>
            <?php if (($stats['unresolved'] ?? 0) > 0): ?>
                <form method="POST" onsubmit="return confirm('Resolve all unresolved alerts?');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                    <input type="hidden" name="action" value="resolve_all">
                    <button type="submit" class="btn btn-ghost">Resolve All (<?php echo $stats['unresolved']; ?>)</button>
                </form>
            <?php endif; ?>
        </div>
        
        <?php showPageToasts(); ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total'] ?? 0; ?></div>
                <div class="stat-label">Total Alerts</div>
            </div>
            <div class="stat-card <?php echo ($stats['unresolved'] ?? 0) > 0 ? 'warning' : 'success'; ?>">
                <div class="stat-value"><?php echo $stats['unresolved'] ?? 0; ?></div>
                <div class="stat-label">Unresolved</div>
            </div>
            <div class="stat-card <?php echo ($stats['critical'] ?? 0) > 0 ? 'danger' : ''; ?>">
                <div class="stat-value"><?php echo $stats['critical'] ?? 0; ?></div>
                <div class="stat-label">Critical</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['today'] ?? 0; ?></div>
                <div class="stat-label">Today</div>
            </div>
        </div>
        
        <form method="GET" class="filters">
            <div class="filter-group">
                <label>Severity</label>
                <select name="severity">
                    <option value="">All Severities</option>
                    <option value="low" <?php echo $filter_severity === 'low' ? 'selected' : ''; ?>>Low</option>
                    <option value="medium" <?php echo $filter_severity === 'medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="high" <?php echo $filter_severity === 'high' ? 'selected' : ''; ?>>High</option>
                    <option value="critical" <?php echo $filter_severity === 'critical' ? 'selected' : ''; ?>>Critical</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Type</label>
                <select name="type">
                    <option value="">All Types</option>
                    <option value="new_device" <?php echo $filter_type === 'new_device' ? 'selected' : ''; ?>>New Device</option>
                    <option value="new_ip" <?php echo $filter_type === 'new_ip' ? 'selected' : ''; ?>>New IP</option>
                    <option value="new_location" <?php echo $filter_type === 'new_location' ? 'selected' : ''; ?>>New Location</option>
                    <option value="failed_attempts" <?php echo $filter_type === 'failed_attempts' ? 'selected' : ''; ?>>Failed Attempts</option>
                    <option value="impossible_travel" <?php echo $filter_type === 'impossible_travel' ? 'selected' : ''; ?>>Impossible Travel</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Status</label>
                <select name="resolved">
                    <option value="unresolved" <?php echo $filter_resolved === 'unresolved' ? 'selected' : ''; ?>>Unresolved</option>
                    <option value="resolved" <?php echo $filter_resolved === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                    <option value="all" <?php echo $filter_resolved === 'all' ? 'selected' : ''; ?>>All</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Search User</label>
                <input type="text" name="user" value="<?php echo htmlspecialchars($filter_user); ?>" placeholder="Username or email...">
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="security_alerts.php" class="btn btn-ghost">Reset</a>
        </form>
        
        <?php if (empty($alerts)): ?>
            <div class="empty-state">
                <div class="icon">✅</div>
                <h3>No Alerts Found</h3>
                <p>No security alerts match your filters.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="alert-table">
                    <thead>
                        <tr>
                            <th>Severity</th>
                            <th>Type</th>
                            <th>User</th>
                            <th>Details</th>
                            <th>IP / Location</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alerts as $alert): ?>
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
                                'new_ip' => 'New IP',
                                'new_location' => 'New Location',
                                'suspicious_time' => 'Unusual Time',
                                'failed_attempts' => 'Failed Attempts',
                                'impossible_travel' => 'Impossible Travel'
                            ];
                            $details = json_decode($alert['details'], true) ?: [];
                            ?>
                            <tr>
                                <td>
                                    <span class="severity-badge severity-<?php echo $alert['severity']; ?>">
                                        <?php echo ucfirst($alert['severity']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="type-badge"><?php echo $type_icons[$alert['alert_type']] ?? '⚠️'; ?></span>
                                    <?php echo $type_labels[$alert['alert_type']] ?? $alert['alert_type']; ?>
                                </td>
                                <td>
                                    <div class="user-info">
                                        <span class="user-name"><?php echo htmlspecialchars($alert['username']); ?></span>
                                        <span class="user-email"><?php echo htmlspecialchars($alert['email']); ?></span>
                                    </div>
                                </td>
                                <td class="details-cell">
                                    <?php
                                    switch ($alert['alert_type']) {
                                        case 'new_device':
                                            echo htmlspecialchars($details['device'] ?? 'Unknown device');
                                            break;
                                        case 'failed_attempts':
                                            echo ($details['failed_count'] ?? '?') . ' failed attempts';
                                            break;
                                        case 'impossible_travel':
                                            echo htmlspecialchars(($details['from_location'] ?? '?') . ' → ' . ($details['to_location'] ?? '?'));
                                            break;
                                        case 'new_ip':
                                            $newIp = $details['new_ip'] ?? $details['ip'] ?? 'Unknown';
                                            $prevIp = $details['previous_ip'] ?? $details['old_ip'] ?? null;
                                            echo '<div style="font-size: 12px;">New: <strong>' . htmlspecialchars($newIp) . '</strong></div>';
                                            if ($prevIp) {
                                                echo '<div style="font-size: 11px; color: var(--text-muted);">Previous: ' . htmlspecialchars($prevIp) . '</div>';
                                            }
                                            break;
                                        case 'new_location':
                                            $newLoc = $details['new_location'] ?? $details['location'] ?? 'Unknown';
                                            $prevLoc = $details['previous_location'] ?? null;
                                            echo '<div style="font-size: 12px;">' . htmlspecialchars($newLoc) . '</div>';
                                            if ($prevLoc) {
                                                echo '<div style="font-size: 11px; color: var(--text-muted);">From: ' . htmlspecialchars($prevLoc) . '</div>';
                                            }
                                            break;
                                        case 'suspicious_activity':
                                            echo htmlspecialchars($details['reason'] ?? $details['description'] ?? 'Suspicious activity detected');
                                            break;
                                        default:
                                            // Parse JSON if it looks like JSON, otherwise show as text
                                            if (is_array($details) && !empty($details)) {
                                                $output = [];
                                                foreach ($details as $key => $value) {
                                                    if (!is_array($value)) {
                                                        $label = ucwords(str_replace('_', ' ', $key));
                                                        $output[] = "$label: " . htmlspecialchars($value);
                                                    }
                                                }
                                                echo '<span class="details-text" title="' . implode(', ', $output) . '">' . implode(', ', array_slice($output, 0, 2)) . '</span>';
                                            } else {
                                                echo '<span class="details-text" title="' . htmlspecialchars($alert['details']) . '">' . htmlspecialchars(substr($alert['details'], 0, 50)) . '</span>';
                                            }
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($alert['ip_address']); ?></div>
                                    <div style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($alert['location'] ?: 'Unknown'); ?></div>
                                </td>
                                <td>
                                    <?php echo date('M j, g:i A', strtotime($alert['created_at'])); ?>
                                </td>
                                <td>
                                    <?php if ($alert['is_resolved']): ?>
                                        <span class="resolved-badge">
                                            ✓ Resolved
                                            <?php if ($alert['resolved_by_username']): ?>
                                                <br><small>by <?php echo htmlspecialchars($alert['resolved_by_username']); ?></small>
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #f59e0b;">● Active</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$alert['is_resolved']): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                                            <input type="hidden" name="action" value="resolve">
                                            <input type="hidden" name="alert_id" value="<?php echo intval($alert['id']); ?>">
                                            <button type="submit" class="btn btn-ghost btn-sm">Resolve</button>
                                        </form>
                                    <?php endif; ?>
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
