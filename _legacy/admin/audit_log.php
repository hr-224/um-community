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
requireLogin();

// Check permission
if (!isAdmin() && !hasPermission('admin.audit')) {
    header('Location: /index?error=unauthorized');
    exit();
}

$conn = getDBConnection();

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Filters
$filter_user = isset($_GET['user']) ? intval($_GET['user']) : 0;
$filter_action = isset($_GET['action']) ? trim($_GET['action']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$where = [];
$params = [];
$types = '';

if ($filter_user > 0) {
    $where[] = "a.user_id = ?";
    $params[] = $filter_user;
    $types .= 'i';
}

if (!empty($filter_action)) {
    $where[] = "a.action LIKE ?";
    $params[] = "%$filter_action%";
    $types .= 's';
}

if (!empty($search)) {
    $where[] = "(a.details LIKE ? OR u.username LIKE ? OR a.ip_address LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'sss';
}

$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$count_query = "SELECT COUNT(*) as total FROM audit_log a LEFT JOIN users u ON a.user_id = u.id $where_clause";
if (!empty($params)) {
    $stmt = $conn->prepare($count_query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $count_row = $stmt->get_result()->fetch_assoc();
    $total = $count_row['total'] ?? 0;
    $stmt->close();
} else {
    $total = safeQueryCount($conn, $count_query, 'total');
}

$total_pages = ceil($total / $per_page);

// Get logs
$query = "
    SELECT a.*, u.username 
    FROM audit_log a 
    LEFT JOIN users u ON a.user_id = u.id 
    $where_clause 
    ORDER BY a.created_at DESC 
    LIMIT $per_page OFFSET $offset
";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $logs = $stmt->get_result();
} else {
    $logs = $conn->query($query);
}

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $export_query = "SELECT a.*, u.username FROM audit_log a LEFT JOIN users u ON a.user_id = u.id $where_clause ORDER BY a.created_at DESC LIMIT 5000";
    if (!empty($params)) {
        $export_stmt = $conn->prepare($export_query);
        $export_stmt->bind_param($types, ...$params);
        $export_stmt->execute();
        $export_result = $export_stmt->get_result();
    } else {
        $export_result = $conn->query($export_query);
    }
    $rows = [];
    while ($r = $export_result->fetch_assoc()) {
        $rows[] = [$r['created_at'], $r['username'] ?? 'System', $r['action'], $r['target_type'] ?? '', $r['details'] ?? '', $r['ip_address'] ?? ''];
    }
    exportCSV('audit_log_' . date('Y-m-d') . '.csv', ['Date', 'User', 'Action', 'Target', 'Details', 'IP'], $rows);
}

// Get users for filter
$users = $conn->query("SELECT id, username FROM users WHERE is_approved = TRUE ORDER BY username");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <?php include '../includes/styles.php'; ?>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .container {
            max-width: 1600px;
        }
        
        .section {
            animation: fadeIn 0.6s ease;
        }
        
        .section h2 {
            color: var(--text-primary);
            margin-bottom: 28px;
            padding-bottom: 16px;
            border-bottom: 2px solid rgba(88, 101, 242, 0.3);
            font-size: 24px;
            font-weight: 700;
        }
        
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        
        .filters select,
        .filters input {
            padding: 12px 18px;
            border: 2px solid var(--bg-elevated);
            border-radius: var(--radius-md);
            font-size: 14px;
            background: var(--bg-card);
            color: var(--text-primary);
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .filters select option {
            background: #1a1a2e;
            color: var(--text-primary);
            padding: 10px;
        }
        
        .filters select:focus,
        .filters input:focus {
            outline: none;
            border-color: var(--accent);
            background: var(--bg-elevated);
        }
        
        .filters button {
            padding: 12px 24px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            line-height: 1.2;
            transition: all 0.3s ease;
        }
        
        .filters button:hover { 
            transform: translateY(-2px);
            box-shadow: 0 4px 15px var(--shadow-color, rgba(88, 101, 242, 0.4));
        }
        
        .filters a {
            padding: 12px 24px;
            background: rgba(107, 114, 128, 0.3);
            color: white;
            text-decoration: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 14px;
            line-height: 1.2;
            transition: all 0.3s ease;
            border: 1px solid var(--bg-elevated);
        }
        
        .filters a:hover {
            background: rgba(107, 114, 128, 0.5);
            transform: translateY(-2px);
        }
        
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }
        
        th {
            background: var(--bg-elevated);
            padding: 16px;
            text-align: left;
            font-weight: 700;
            color: var(--text-primary);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        th:first-child { border-radius: var(--radius-md) 0 0 12px; }
        th:last-child { border-radius: 0 12px 12px 0; }
        
        td {
            padding: 14px 16px;
            background: var(--bg-elevated);
            border-top: 1px solid var(--bg-card);
            border-bottom: 1px solid var(--bg-card);
            font-size: 14px;
        }
        
        td:first-child {
            border-left: 1px solid var(--bg-card);
            border-radius: var(--radius-md) 0 0 12px;
        }
        
        td:last-child {
            border-right: 1px solid var(--bg-card);
            border-radius: 0 12px 12px 0;
        }
        
        tr:hover td { background: var(--accent-muted); }
        
        .action-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: var(--radius-md);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .action-create { 
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(5, 150, 105, 0.2) 100%);
            color: #4ade80;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .action-update { 
            background: linear-gradient(135deg, rgba(251, 191, 36, 0.2) 0%, rgba(245, 158, 11, 0.2) 100%);
            color: #f0b232;
            border: 1px solid rgba(251, 191, 36, 0.3);
        }
        
        .action-delete { 
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2) 0%, rgba(220, 38, 38, 0.2) 100%);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .action-login { 
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.2) 0%, rgba(37, 99, 235, 0.2) 100%);
            color: #93c5fd;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        
        .pagination {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 28px;
            flex-wrap: wrap;
        }
        
        .pagination a {
            padding: 10px 18px;
            background: var(--bg-card);
            border: 2px solid var(--bg-elevated);
            border-radius: var(--radius-md);
            text-decoration: none;
            color: var(--text-primary);
            font-weight: 600;
            transition: background 0.3s ease, border-color 0.3s ease, transform 0.3s ease;
        }
        
        .pagination a:hover { 
            background: var(--accent-muted);
            border-color: var(--accent);
            transform: translateY(-2px);
        }
        
        .pagination a.active {
            background: var(--accent);
            color: white;
            border-color: transparent;
            box-shadow: 0 4px 15px var(--shadow-color, rgba(88, 101, 242, 0.4));
        }
        
        @media (max-width: 768px) {
            table { font-size: 12px; }
            th, td { padding: 10px; }
            .filters { flex-direction: column; }
            .filters select,
            .filters input,
            .filters button,
            .filters a { width: 100%; }
            .container { padding: 0 16px; }
            .section { padding: 24px; }
        }
    </style>
</head>
<body>
    <?php $current_page = 'admin_audit'; include '../includes/navbar.php'; ?>

    <div class="container">
        <div class="section">
            <h2>System Audit Trail</h2>
            
            <form method="GET" class="filters" style="flex-wrap: wrap; gap: 8px;">
                <select name="user" style="min-width: 200px;">
                    <option value="0">All Users</option>
                    <?php while ($user = $users->fetch_assoc()): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $filter_user == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['username']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                
                <input type="text" name="action" placeholder="Filter by action..." value="<?php echo htmlspecialchars($filter_action); ?>">
                <input type="text" name="search" placeholder="Search details, user, IP..." value="<?php echo htmlspecialchars($search); ?>">
                
                <button type="submit" class="btn btn-primary btn-sm">Apply Filters</button>
                <a href="audit_log" class="btn-clear">Clear</a>
                <a href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['export' => 'csv']))); ?>" class="btn-export" title="Export all logs to CSV">📥 Export CSV</a>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Target</th>
                        <th>Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($log = $logs->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></strong></td>
                            <td>
                                <?php
                                $action_class = 'action-create';
                                if (strpos($log['action'], 'update') !== false || strpos($log['action'], 'edit') !== false) {
                                    $action_class = 'action-update';
                                } elseif (strpos($log['action'], 'delete') !== false) {
                                    $action_class = 'action-delete';
                                } elseif (strpos($log['action'], 'login') !== false) {
                                    $action_class = 'action-login';
                                }
                                ?>
                                <span class="action-badge <?php echo $action_class; ?>">
                                    <?php echo htmlspecialchars($log['action']); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                if ($log['target_type']) {
                                    echo htmlspecialchars($log['target_type']) . ' #' . $log['target_id'];
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($log['details'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&user=<?php echo $filter_user; ?>&action=<?php echo urlencode($filter_action); ?>">← Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&user=<?php echo $filter_user; ?>&action=<?php echo urlencode($filter_action); ?>" 
                           class="<?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&user=<?php echo $filter_user; ?>&action=<?php echo urlencode($filter_action); ?>">Next →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>