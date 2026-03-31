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
require_once '../includes/permissions_ui.php';
requireLogin();

// Check permission
if (!isAdmin() && !hasAnyPermission(['activity.view', 'activity.manage', 'activity.log'])) {
    header('Location: /index?error=unauthorized');
    exit();
}

$conn = getDBConnection();
$message = '';
$error = '';
$is_admin = isAdmin();
$can_manage = $is_admin || hasPermission('activity.manage');
$can_log = $is_admin || hasPermission('activity.log') || hasPermission('activity.manage');

// Handle logging activity
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['log_activity']) && $can_log) {
    $user_id = $is_admin || $can_manage ? intval($_POST['user_id']) : $_SESSION['user_id'];
    $dept_id = intval($_POST['department_id']);
    $activity_type_id = !empty($_POST['activity_type_id']) ? intval($_POST['activity_type_id']) : null;
    $activity_date = $_POST['activity_date'];
    $start_time = !empty($_POST['start_time']) ? $_POST['start_time'] : null;
    $end_time = !empty($_POST['end_time']) ? $_POST['end_time'] : null;
    $duration = intval($_POST['duration_minutes']);
    $description = trim($_POST['description']);
    $notes = trim($_POST['notes'] ?? '');
    
    // Calculate duration from times if provided
    if ($start_time && $end_time) {
        $start = new DateTime($start_time);
        $end = new DateTime($end_time);
        $diff = $start->diff($end);
        $duration = ($diff->h * 60) + $diff->i;
    }
    
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, department_id, activity_type_id, activity_date, start_time, end_time, duration_minutes, description, notes, status) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("iiisssisss", $user_id, $dept_id, $activity_type_id, $activity_date, $start_time, $end_time, $duration, $description, $notes);
    
    if ($stmt->execute()) {
        logAudit('log_activity', 'activity_log', $stmt->insert_id, "Logged activity: $description");
        $message = 'Activity logged successfully!';
    } else {
        $error = 'Failed to log activity.';
    }
    $stmt->close();
}

// Handle verifying activity
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['verify_activity']) && $can_manage) {
    $activity_id = intval($_POST['activity_id']);
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE activity_logs SET status = ?, verified_by = ?, verified_at = NOW() WHERE id = ?");
    $verified_by = $_SESSION['user_id'];
    $stmt->bind_param("sii", $status, $verified_by, $activity_id);
    $stmt->execute();
    
    logAudit('verify_activity', 'activity_log', $activity_id, "Activity $status");
    $message = 'Activity ' . $status . '!';
    $stmt->close();
}

// Handle creating activity type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['create_activity_type']) && $is_admin) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $dept_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
    $icon = $_POST['icon'] ?? '📋';
    $color = $_POST['color'] ?? '#6B7280';
    $points = floatval($_POST['points_value']);
    
    $stmt = $conn->prepare("INSERT INTO activity_types (name, description, department_id, icon, color, points_value) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssissd", $name, $description, $dept_id, $icon, $color, $points);
    
    if ($stmt->execute()) {
        logAudit('create_activity_type', 'activity_type', $stmt->insert_id, "Created activity type: $name");
        $message = 'Activity type created!';
    }
    $stmt->close();
}

// Get filter parameters
$filter_dept = $_GET['dept'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_user = $_GET['user'] ?? '';
$filter_date_from = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
$filter_date_to = $_GET['to'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;

// Build activity query with prepared statements
$where_clauses = ["al.activity_date BETWEEN ? AND ?"];
$params = [$filter_date_from, $filter_date_to];
$types = 'ss';

if ($filter_dept) {
    $where_clauses[] = "al.department_id = ?";
    $params[] = intval($filter_dept);
    $types .= 'i';
}
if ($filter_status) {
    $where_clauses[] = "al.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}
if ($filter_user) {
    $where_clauses[] = "al.user_id = ?";
    $params[] = intval($filter_user);
    $types .= 'i';
}
if ($search) {
    $where_clauses[] = "(u.username LIKE ? OR al.notes LIKE ?)";
    $search_term = '%' . $search . '%';
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

$where_sql = implode(' AND ', $where_clauses);

// Count for pagination
$count_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM activity_logs al JOIN users u ON al.user_id = u.id WHERE $where_sql");
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$count_row = $count_stmt->get_result()->fetch_assoc();
$total_activities = $count_row['cnt'] ?? 0;
$count_stmt->close();

$base_url = '?dept=' . urlencode($filter_dept) . '&status=' . urlencode($filter_status) . '&user=' . urlencode($filter_user) . '&from=' . urlencode($filter_date_from) . '&to=' . urlencode($filter_date_to) . '&search=' . urlencode($search);
$act_pagination = getPagination($total_activities, $per_page, $page, $base_url);

$act_sql = "SELECT al.*, u.username, d.name as dept_name, d.abbreviation, at.name as type_name, at.icon, at.color,
                            v.username as verified_by_name
                            FROM activity_logs al
                            JOIN users u ON al.user_id = u.id
                            JOIN departments d ON al.department_id = d.id
                            LEFT JOIN activity_types at ON al.activity_type_id = at.id
                            LEFT JOIN users v ON al.verified_by = v.id
                            WHERE $where_sql
                            ORDER BY al.activity_date DESC, al.created_at DESC
                            LIMIT ? OFFSET ?";
$all_params = array_merge($params, [$per_page, $act_pagination['offset']]);
$all_types = $types . 'ii';

$act_stmt = $conn->prepare($act_sql);
$act_stmt->bind_param($all_types, ...$all_params);
$act_stmt->execute();
$activities = $act_stmt->get_result();
$act_stmt->close();

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $export_sql = "SELECT al.*, u.username, d.name as dept_name, at.name as type_name
        FROM activity_logs al JOIN users u ON al.user_id = u.id
        JOIN departments d ON al.department_id = d.id LEFT JOIN activity_types at ON al.activity_type_id = at.id
        WHERE $where_sql ORDER BY al.activity_date DESC";
    $export_stmt = $conn->prepare($export_sql);
    $export_stmt->bind_param($types, ...$params);
    $export_stmt->execute();
    $export_result = $export_stmt->get_result();
    $rows = [];
    while ($r = $export_result->fetch_assoc()) {
        $rows[] = [$r['username'], $r['dept_name'], $r['type_name'] ?? 'N/A', $r['activity_date'], $r['duration_minutes'], $r['status'], $r['notes'] ?? ''];
    }
    $export_stmt->close();
    exportCSV('activity_log_' . date('Y-m-d') . '.csv', ['Member', 'Department', 'Type', 'Date', 'Duration (min)', 'Status', 'Notes'], $rows);
}

// Get activity types
$activity_types = $conn->query("SELECT * FROM activity_types WHERE is_active = TRUE ORDER BY name");

// Get departments and users for filters
$departments = $conn->query("SELECT id, name, abbreviation FROM departments ORDER BY name");
$users = $conn->query("SELECT id, username FROM users WHERE is_approved = TRUE ORDER BY username");

// Stats for the period
$stats_stmt = $conn->prepare("SELECT 
    COUNT(*) as total_entries,
    SUM(duration_minutes) as total_minutes,
    SUM(CASE WHEN status = 'verified' THEN duration_minutes ELSE 0 END) as verified_minutes,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    COUNT(DISTINCT user_id) as unique_users
    FROM activity_logs 
    WHERE activity_date BETWEEN ? AND ?");
$stats_stmt->bind_param("ss", $filter_date_from, $filter_date_to);
$stats_stmt->execute();
$period_stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

// Get user's own roster entries for department selection
$user_depts_stmt = $conn->prepare("SELECT r.department_id, d.name, d.abbreviation 
                            FROM roster r 
                            JOIN departments d ON r.department_id = d.id 
                            WHERE r.user_id = ?");
$user_depts_stmt->bind_param("i", $_SESSION['user_id']);
$user_depts_stmt->execute();
$user_depts = $user_depts_stmt->get_result();
$user_depts_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logging - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <?php include '../includes/styles.php'; ?>
    <style>
        .stats-row { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 24px; }
        @media (max-width: 900px) { .stats-row { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 600px) { .stats-row { grid-template-columns: repeat(2, 1fr); } }
        .stat-card { background: var(--bg-card); border-radius: var(--radius-lg); padding: 20px; text-align: center; }
        .stat-value { font-size: 28px; font-weight: 800; color: var(--text-primary); }
        .stat-label { font-size: 11px; color: var(--text-muted); margin-top: 4px; }
        
        .filters { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; align-items: flex-end; }
        .filters .form-group { margin-bottom: 0; }
        .filters input, .filters select { padding: 10px 14px; border: 2px solid var(--bg-elevated); border-radius: var(--radius-md); background: var(--bg-card); color: var(--text-primary); }
        .filters input:focus, .filters select:focus { outline: none; border-color: var(--accent); }
        
        .activity-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            background: var(--bg-elevated);
            border: 1px solid var(--bg-card);
            border-radius: var(--radius-md);
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        .activity-row:hover { background: var(--bg-card); }
        .activity-row.pending { border-left: 3px solid #f59e0b; }
        .activity-row.verified { border-left: 3px solid var(--success); }
        .activity-row.rejected { border-left: 3px solid var(--danger); opacity: 0.6; }
        
        .activity-info { display: flex; align-items: center; gap: 16px; }
        .activity-icon { font-size: 28px; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: var(--radius-md); background: var(--bg-card); }
        .activity-details h4 { font-size: 14px; margin-bottom: 4px; }
        .activity-meta { font-size: 12px; color: var(--text-muted); }
        
        .activity-actions { display: flex; align-items: center; gap: 12px; }
        .duration { font-size: 18px; font-weight: 700; color: var(--text-primary); min-width: 80px; text-align: right; }
        
        .badge { padding: 4px 10px; border-radius: var(--radius-lg); font-size: 11px; font-weight: 600; }
        .badge-pending { background: rgba(251, 191, 36, 0.2); color: #f0b232; }
        .badge-verified { background: rgba(16, 185, 129, 0.2); color: #4ade80; }
        .badge-rejected { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        
        .btn { padding: 10px 20px; border: none; border-radius: var(--radius-md); cursor: pointer; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: var(--accent); color: white; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn-success { background: linear-gradient(135deg, var(--success), #059669); color: white; }
        .btn-danger { background: linear-gradient(135deg, var(--danger), #dc2626); color: white; }
        
        
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 10px 14px; border: 2px solid var(--bg-elevated);
            border-radius: var(--radius-md); background: var(--bg-card); color: var(--text-primary);
        }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--accent); }
        
        .message { background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2)); border: 1px solid rgba(16, 185, 129, 0.3); color: #4ade80; padding: 16px; border-radius: var(--radius-md); margin-bottom: 20px; }
        
        .tabs { display: flex; gap: 8px; margin-bottom: 24px; }
        .tab { padding: 12px 24px; border-radius: var(--radius-md); background: var(--bg-card); color: var(--text-secondary); cursor: pointer; font-weight: 600; }
        .tab:hover { background: var(--bg-elevated); }
        .tab.active { background: var(--accent); color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>
    <?php $current_page = 'admin_activity'; include '../includes/navbar.php'; ?>
    
    <div class="container">
        <?php showPageToasts(); ?>
        
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-value"><?php echo $period_stats['total_entries'] ?? 0; ?></div>
                <div class="stat-label">Total Entries</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format(($period_stats['total_minutes'] ?? 0) / 60, 1); ?></div>
                <div class="stat-label">Total Hours</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format(($period_stats['verified_minutes'] ?? 0) / 60, 1); ?></div>
                <div class="stat-label">Verified Hours</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="<?php echo ($period_stats['pending_count'] ?? 0) > 0 ? 'color: #f0b232; -webkit-text-fill-color: #f0b232;' : ''; ?>"><?php echo $period_stats['pending_count'] ?? 0; ?></div>
                <div class="stat-label">Pending Review</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $period_stats['unique_users'] ?? 0; ?></div>
                <div class="stat-label">Active Members</div>
            </div>
        </div>
        
        <div class="tabs">
            <div class="tab active" onclick="showTab('logs')">📋 Activity Logs</div>
            <div class="tab" onclick="showTab('types')">⚙️ Activity Types <?php if (!$can_manage): ?><span style="opacity: 0.5;">🔒</span><?php endif; ?></div>
        </div>
        
        <!-- Activity Logs Tab -->
        <div class="tab-content active" id="tab-logs">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
                <h2>Activity Logs</h2>
                <?php if ($can_log): ?>
                <button class="btn btn-primary" onclick="openLogModal()">+ Log Activity</button>
                <?php else: ?>
                <?php lockedButton('+ Log Activity', 'Log Activity permission required'); ?>
                <?php endif; ?>
            </div>
            
            <form method="GET" class="filters">
                <div class="form-group">
                    <label>From</label>
                    <input type="date" name="from" value="<?php echo $filter_date_from; ?>">
                </div>
                <div class="form-group">
                    <label>To</label>
                    <input type="date" name="to" value="<?php echo $filter_date_to; ?>">
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="dept">
                        <option value="">All</option>
                        <?php $departments->data_seek(0); while ($d = $departments->fetch_assoc()): ?>
                        <option value="<?php echo $d['id']; ?>" <?php echo $filter_dept == $d['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['abbreviation']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All</option>
                        <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="verified" <?php echo $filter_status === 'verified' ? 'selected' : ''; ?>>Verified</option>
                        <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <?php if ($can_manage): ?>
                <div class="form-group">
                    <label>User</label>
                    <select name="user">
                        <option value="">All</option>
                        <?php $users->data_seek(0); while ($u = $users->fetch_assoc()): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $filter_user == $u['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['username']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['export' => 'csv']))); ?>" class="btn-export">📥 Export CSV</a>
            </form>
            
            <div class="section" style="margin-top: 8px;">
                <div class="search-bar" style="margin-bottom: 16px;">
                    <form method="GET" style="display: flex; gap: 8px; flex: 1; flex-wrap: wrap;">
                        <input type="hidden" name="dept" value="<?php echo htmlspecialchars($filter_dept); ?>">
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
                        <input type="hidden" name="user" value="<?php echo htmlspecialchars($filter_user); ?>">
                        <input type="hidden" name="from" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                        <input type="hidden" name="to" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                        <input type="search" name="search" placeholder="Search by username or notes..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1; min-width: 200px;">
                        <button type="submit" class="btn btn-primary btn-sm">Search</button>
                        <?php if ($search): ?><a href="?dept=<?php echo urlencode($filter_dept); ?>&status=<?php echo urlencode($filter_status); ?>&user=<?php echo urlencode($filter_user); ?>&from=<?php echo urlencode($filter_date_from); ?>&to=<?php echo urlencode($filter_date_to); ?>" class="btn btn-sm" style="background: var(--bg-elevated);">Clear</a><?php endif; ?>
                    </form>
                </div>
                <?php if ($activities->num_rows > 0): ?>
                    <?php while ($act = $activities->fetch_assoc()): ?>
                    <div class="activity-row <?php echo $act['status']; ?>">
                        <div class="activity-info">
                            <div class="activity-icon" style="<?php if ($act['color']): ?>background: <?php echo $act['color']; ?>20;<?php endif; ?>">
                                <?php echo $act['icon'] ?? '📋'; ?>
                            </div>
                            <div class="activity-details">
                                <h4><?php echo htmlspecialchars($act['username']); ?> - <?php echo htmlspecialchars($act['abbreviation']); ?></h4>
                                <div class="activity-meta">
                                    <?php echo date('M j, Y', strtotime($act['activity_date'])); ?>
                                    <?php if ($act['start_time'] && $act['end_time']): ?>
                                        • <?php echo date('g:i A', strtotime($act['start_time'])); ?> - <?php echo date('g:i A', strtotime($act['end_time'])); ?>
                                    <?php endif; ?>
                                    <?php if ($act['type_name']): ?> • <?php echo htmlspecialchars($act['type_name']); ?><?php endif; ?>
                                    <?php if ($act['description']): ?><br><?php echo htmlspecialchars($act['description']); ?><?php endif; ?>
                                    <?php if ($act['verified_by_name']): ?><br><span style="color: #4ade80;">Verified by <?php echo htmlspecialchars($act['verified_by_name']); ?></span><?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="activity-actions">
                            <div class="duration"><?php echo number_format($act['duration_minutes'] / 60, 1); ?>h</div>
                            <span class="badge badge-<?php echo $act['status']; ?>"><?php echo strtoupper($act['status']); ?></span>
                            <?php if ($act['status'] === 'pending'): ?>
                                <?php if ($can_manage): ?>
                                <form method="POST" style="display: inline;">
                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="activity_id" value="<?php echo $act['id']; ?>">
                                    <input type="hidden" name="status" value="verified">
                                    <button type="submit" name="verify_activity" class="btn btn-sm btn-success">✓</button>
                                </form>
                                <form method="POST" style="display: inline;">
                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="activity_id" value="<?php echo $act['id']; ?>">
                                    <input type="hidden" name="status" value="rejected">
                                    <button type="submit" name="verify_activity" class="btn btn-sm btn-danger">✕</button>
                                </form>
                                <?php else: ?>
                                <?php lockedActions('Manage Activity permission required'); ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                    <?php echo renderPagination($act_pagination); ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-muted);">No activity logs found for this period</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Activity Types Tab -->
        <div class="tab-content" id="tab-types">
            <?php if ($can_manage): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>Activity Types</h2>
                <button class="btn btn-primary" onclick="openTypeModal()">+ Add Type</button>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px;">
                <?php $activity_types->data_seek(0); while ($at = $activity_types->fetch_assoc()): ?>
                <div class="activity-row" style="border-left: 3px solid <?php echo $at['color']; ?>;">
                    <div class="activity-info">
                        <div class="activity-icon" style="background: <?php echo $at['color']; ?>20;"><?php echo $at['icon']; ?></div>
                        <div class="activity-details">
                            <h4><?php echo htmlspecialchars($at['name']); ?></h4>
                            <div class="activity-meta"><?php echo $at['points_value']; ?> points</div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="section permission-locked" style="min-height: 200px;">
                <h2>Activity Types</h2>
                <p style="color: var(--text-muted);">Configure activity types and their point values.</p>
                <?php permissionLockOverlay('You need the "Manage Activity" permission to manage activity types.'); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Log Activity Modal -->
    <div class="modal" id="logModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Log Activity</h3>
                <button class="modal-close" onclick="closeModal('logModal')">&times;</button>
            </div>
            <form method="POST">
                    <?php echo csrfField(); ?>
                <?php if ($can_manage): ?>
                <div class="form-group">
                    <label>Member *</label>
                    <select name="user_id" required>
                        <?php $users->data_seek(0); while ($u = $users->fetch_assoc()): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $u['id'] == $_SESSION['user_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['username']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <?php else: ?>
                <input type="hidden" name="user_id" value="<?php echo $_SESSION['user_id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Department *</label>
                    <select name="department_id" required>
                        <?php if ($user_depts->num_rows > 0): ?>
                            <?php while ($ud = $user_depts->fetch_assoc()): ?>
                            <option value="<?php echo $ud['department_id']; ?>"><?php echo htmlspecialchars($ud['name']); ?></option>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <?php $departments->data_seek(0); while ($d = $departments->fetch_assoc()): ?>
                            <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Activity Type</label>
                    <select name="activity_type_id">
                        <option value="">Other</option>
                        <?php $activity_types->data_seek(0); while ($at = $activity_types->fetch_assoc()): ?>
                        <option value="<?php echo $at['id']; ?>"><?php echo $at['icon']; ?> <?php echo htmlspecialchars($at['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Date *</label>
                    <input type="date" name="activity_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label>Start Time</label>
                        <input type="time" name="start_time" onchange="calculateDuration()">
                    </div>
                    <div class="form-group">
                        <label>End Time</label>
                        <input type="time" name="end_time" onchange="calculateDuration()">
                    </div>
                    <div class="form-group">
                        <label>Duration (min) *</label>
                        <input type="number" name="duration_minutes" id="duration_input" min="1" value="60" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Description *</label>
                    <input type="text" name="description" required placeholder="Brief description of activity">
                </div>
                
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="2" placeholder="Additional details..."></textarea>
                </div>
                
                <button type="submit" name="log_activity" class="btn btn-primary" style="width: 100%;">Log Activity</button>
            </form>
        </div>
    </div>
    
    <!-- Add Activity Type Modal -->
    <?php if ($is_admin): ?>
    <div class="modal" id="typeModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Activity Type</h3>
                <button class="modal-close" onclick="closeModal('typeModal')">&times;</button>
            </div>
            <form method="POST">
                    <?php echo csrfField(); ?>
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label>Icon</label>
                        <input type="text" name="icon" value="📋" maxlength="5">
                    </div>
                    <div class="form-group">
                        <label>Color</label>
                        <input type="color" name="color" value="#6B7280" style="height: 42px;">
                    </div>
                    <div class="form-group">
                        <label>Points</label>
                        <input type="number" name="points_value" step="0.25" value="1.00">
                    </div>
                </div>
                <div class="form-group">
                    <label>Department (optional)</label>
                    <select name="department_id">
                        <option value="">All Departments</option>
                        <?php $departments->data_seek(0); while ($d = $departments->fetch_assoc()): ?>
                        <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="2"></textarea>
                </div>
                <button type="submit" name="create_activity_type" class="btn btn-primary" style="width: 100%;">Create Type</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
        function showTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            event.target.classList.add('active');
            document.getElementById('tab-' + tab).classList.add('active');
        }
        
        function openLogModal() { document.getElementById('logModal').classList.add('active'); }
        function openTypeModal() { document.getElementById('typeModal').classList.add('active'); }
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }
        
        function calculateDuration() {
            const start = document.querySelector('input[name="start_time"]').value;
            const end = document.querySelector('input[name="end_time"]').value;
            if (start && end) {
                const startDate = new Date('2000-01-01 ' + start);
                const endDate = new Date('2000-01-01 ' + end);
                let diff = (endDate - startDate) / 60000;
                if (diff < 0) diff += 24 * 60;
                document.getElementById('duration_input').value = Math.round(diff);
            }
        }
        
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) this.classList.remove('active');
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
