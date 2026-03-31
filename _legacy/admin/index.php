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
if (!isAdmin() && !hasPermission('admin.users')) {
    header('Location: /index?error=unauthorized');
    exit();
}

$conn = getDBConnection();
$message = '';
$is_admin = isAdmin();
$can_manage = $is_admin || hasPermission('admin.users');

// Search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Handle bulk actions (admin only for now - these are sensitive operations)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['bulk_action']) && $can_manage) {
    $action = $_POST['bulk_action'];
    $user_ids = $_POST['user_ids'] ?? [];
    
    if (!empty($user_ids)) {
        // Sanitize all IDs
        $safe_ids = array_map('intval', $user_ids);
        $placeholders = implode(',', array_fill(0, count($safe_ids), '?'));
        $types = str_repeat('i', count($safe_ids));
        
        switch ($action) {
            case 'approve':
                // Get user emails before approving
                $emailStmt = $conn->prepare("SELECT id, email, username FROM users WHERE id IN ($placeholders)");
                $emailStmt->bind_param($types, ...$safe_ids);
                $emailStmt->execute();
                $usersToApprove = $emailStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $emailStmt->close();
                
                $stmt = $conn->prepare("UPDATE users SET is_approved = TRUE WHERE id IN ($placeholders)");
                $stmt->bind_param($types, ...$safe_ids);
                $stmt->execute();
                $stmt->close();
                
                foreach ($user_ids as $uid) {
                    createNotification($uid, 'Account Approved', 'Your account has been approved! You can now log in.', 'success');
                }
                
                // Send approval emails
                foreach ($usersToApprove as $userData) {
                    if (!empty($userData['email'])) {
                        try {
                            sendAccountApprovedEmail($userData['email'], $userData['username']);
                        } catch (Exception $e) {
                            error_log("Failed to send approval email to {$userData['email']}: " . $e->getMessage());
                        }
                    }
                }
                
                logAudit('bulk_approve_users', 'user', null, "Approved " . count($user_ids) . " users");
                $message = count($user_ids) . ' user(s) approved!';
                break;
            
            case 'deny':
                $stmt = $conn->prepare("DELETE FROM users WHERE id IN ($placeholders)");
                $stmt->bind_param($types, ...$safe_ids);
                $stmt->execute();
                $stmt->close();
                logAudit('bulk_deny_users', 'user', null, "Denied " . count($user_ids) . " users");
                $message = count($user_ids) . ' user(s) denied and deleted!';
                break;
            
            case 'delete':
                $current_uid = $_SESSION['user_id'];
                $stmt = $conn->prepare("DELETE FROM users WHERE id IN ($placeholders) AND id != ?");
                $params = array_merge($safe_ids, [$current_uid]);
                $stmt->bind_param($types . 'i', ...$params);
                $stmt->execute();
                $stmt->close();
                logAudit('bulk_delete_users', 'user', null, "Deleted " . count($user_ids) . " users");
                $message = count($user_ids) . ' user(s) deleted!';
                break;
                
            case 'make_admin':
                $stmt = $conn->prepare("UPDATE users SET is_admin = TRUE WHERE id IN ($placeholders)");
                $stmt->bind_param($types, ...$safe_ids);
                $stmt->execute();
                $stmt->close();
                logAudit('bulk_make_admin', 'user', null, "Made " . count($user_ids) . " users admin");
                $message = count($user_ids) . ' user(s) promoted to admin!';
                break;
        }
    }
}

// Single user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && $can_manage) {
    if (isset($_POST['approve_user'])) {
        $user_id = intval($_POST['user_id']);
        
        // Get user email before approving
        $userStmt = $conn->prepare("SELECT email, username FROM users WHERE id = ?");
        $userStmt->bind_param("i", $user_id);
        $userStmt->execute();
        $userData = $userStmt->get_result()->fetch_assoc();
        $userStmt->close();
        
        $stmt = $conn->prepare("UPDATE users SET is_approved = TRUE WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        
        createNotification($user_id, 'Account Approved', 'Your account has been approved! You can now log in.', 'success');
        logAudit('approve_user', 'user', $user_id, 'Approved user');
        
        // Send approval email
        if ($userData && !empty($userData['email'])) {
            try {
                sendAccountApprovedEmail($userData['email'], $userData['username']);
            } catch (Exception $e) {
                error_log("Failed to send approval email: " . $e->getMessage());
            }
        }
        
        $message = 'User approved successfully!';
    } elseif (isset($_POST['deny_user'])) {
        $user_id = intval($_POST['user_id']);
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        logAudit('deny_user', 'user', $user_id, 'Denied user');
        $message = 'User denied and deleted.';
    } elseif (isset($_POST['delete_user'])) {
        $user_id = intval($_POST['user_id']);
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        logAudit('delete_user', 'user', $user_id, 'Deleted user');
        $message = 'User deleted successfully.';
    } elseif (isset($_POST['make_admin'])) {
        $user_id = intval($_POST['user_id']);
        $stmt = $conn->prepare("UPDATE users SET is_admin = TRUE WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        createNotification($user_id, 'Admin Privileges Granted', 'You have been granted administrator privileges.', 'success');
        logAudit('make_admin', 'user', $user_id, 'Made user admin');
        $message = 'User promoted to admin.';
    } elseif (isset($_POST['remove_admin'])) {
        $user_id = intval($_POST['user_id']);
        $stmt = $conn->prepare("UPDATE users SET is_admin = FALSE WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        createNotification($user_id, 'Admin Privileges Removed', 'Your administrator privileges have been removed.', 'warning');
        logAudit('remove_admin', 'user', $user_id, 'Removed admin privileges');
        $message = 'Admin privileges removed.';
    } elseif (isset($_POST['add_to_roster'])) {
        $user_id = intval($_POST['user_id']);
        $dept_id = intval($_POST['department_id']);
        $rank_id = intval($_POST['rank_id']);
        $badge = $_POST['badge_number'];
        $callsign = $_POST['callsign'];
        $joined = $_POST['joined_date'];
        $is_primary = isset($_POST['is_primary']) ? 1 : 0;
        
        $stmt = $conn->prepare("INSERT INTO roster (user_id, department_id, rank_id, badge_number, callsign, joined_date, is_primary) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiisssi", $user_id, $dept_id, $rank_id, $badge, $callsign, $joined, $is_primary);
        $stmt->execute();
        
        createNotification($user_id, 'Added to Department', 'You have been added to a department roster.', 'info');
        logAudit('add_to_roster', 'roster', $stmt->insert_id, "Added user to roster");
        $message = 'User added to roster successfully!';
        $stmt->close();
    } elseif (isset($_POST['remove_from_roster'])) {
        $roster_id = intval($_POST['roster_id']);
        $stmt = $conn->prepare("DELETE FROM roster WHERE id = ?");
        $stmt->bind_param("i", $roster_id);
        $stmt->execute();
        $stmt->close();
        logAudit('remove_from_roster', 'roster', $roster_id, 'Removed from roster');
        $message = 'User removed from roster.';
    } elseif (isset($_POST['suspend_user'])) {
        $user_id = intval($_POST['user_id']);
        $reason = trim($_POST['suspend_reason'] ?? 'No reason provided');
        if ($user_id != $_SESSION['user_id']) {
            $stmt = $conn->prepare("UPDATE users SET is_suspended = TRUE, suspended_reason = ?, suspended_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $reason, $user_id);
            $stmt->execute();
            $stmt->close();
            createNotification($user_id, 'Account Suspended', 'Your account has been suspended. Reason: ' . $reason, 'danger');
            logAudit('suspend_user', 'user', $user_id, "Suspended user. Reason: $reason");
            $message = 'User suspended successfully.';
        }
    } elseif (isset($_POST['unsuspend_user'])) {
        $user_id = intval($_POST['user_id']);
        $stmt = $conn->prepare("UPDATE users SET is_suspended = FALSE, suspended_reason = NULL, suspended_at = NULL WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        createNotification($user_id, 'Account Reinstated', 'Your account suspension has been lifted.', 'success');
        logAudit('unsuspend_user', 'user', $user_id, 'Reinstated user');
        $message = 'User reinstated successfully.';
    }
}

// Get pending users
$pending_users = $conn->query("SELECT * FROM users WHERE is_approved = FALSE ORDER BY created_at DESC");

// Get approved users with search and pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;

$count_sql = "SELECT COUNT(*) as cnt FROM users WHERE is_approved = TRUE";
$search_sql = "";
$search_params = [];
$search_types = '';

if (!empty($search)) {
    $search_term = '%' . $search . '%';
    $search_sql = " AND (username LIKE ? OR email LIKE ? OR discord_id LIKE ? OR discord_user_id LIKE ?)";
    $search_params = [$search_term, $search_term, $search_term, $search_term];
    $search_types = 'ssss';
}

$count_stmt = $conn->prepare($count_sql . $search_sql);
if ($search_params) $count_stmt->bind_param($search_types, ...$search_params);
$count_stmt->execute();
$count_row = $count_stmt->get_result()->fetch_assoc();
$total_users = $count_row['cnt'] ?? 0;
$count_stmt->close();

$base_url = '?search=' . urlencode($search);
$user_pagination = getPagination($total_users, $per_page, $page, $base_url);

$users_sql = "SELECT * FROM users WHERE is_approved = TRUE" . $search_sql . " ORDER BY username ASC LIMIT ? OFFSET ?";
$all_params = array_merge($search_params, [$per_page, $user_pagination['offset']]);
$all_types = $search_types . 'ii';

$users_stmt = $conn->prepare($users_sql);
$users_stmt->bind_param($all_types, ...$all_params);
$users_stmt->execute();
$approved_users = $users_stmt->get_result();
$users_stmt->close();

// CSV Export for user list
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $export_sql = "SELECT u.*, GROUP_CONCAT(DISTINCT CONCAT(d.abbreviation, ' - ', rk.rank_name) SEPARATOR ', ') as departments
        FROM users u LEFT JOIN roster r ON u.id = r.user_id LEFT JOIN departments d ON r.department_id = d.id 
        LEFT JOIN ranks rk ON r.rank_id = rk.id WHERE u.is_approved = TRUE" . $search_sql . " GROUP BY u.id ORDER BY u.username";
    $export_stmt = $conn->prepare($export_sql);
    if ($search_params) $export_stmt->bind_param($search_types, ...$search_params);
    $export_stmt->execute();
    $export_result = $export_stmt->get_result();
    $rows = [];
    while ($r = $export_result->fetch_assoc()) {
        $rows[] = [$r['username'], $r['email'], $r['discord_user_id'] ?? $r['discord_id'] ?? '', $r['departments'] ?? 'None', $r['is_admin'] ? 'Yes' : 'No', $r['created_at']];
    }
    $export_stmt->close();
    exportCSV('members_' . date('Y-m-d') . '.csv', ['Username', 'Email', 'Discord', 'Departments', 'Admin', 'Joined'], $rows);
}

// Get all departments
$departments = $conn->query("SELECT * FROM departments ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <?php include '../includes/styles.php'; ?>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .container {
            max-width: 1600px;
        }
        
        .message {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(5, 150, 105, 0.2) 100%);
            
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #d1fae5;
            padding: 18px 24px;
            border-radius: var(--radius-lg);
            margin-bottom: 24px;
            animation: slideIn 0.5s ease;
            box-shadow: 0 8px 32px rgba(16, 185, 129, 0.2);
        }
        
        .section {
            background: var(--bg-card);
            
            border: 1px solid var(--bg-elevated);
            padding: 36px;
            border-radius: var(--radius-lg);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            margin-bottom: 32px;
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
        
        .search-bar {
            margin-bottom: 24px;
            display: flex;
            gap: 12px;
        }
        
        .search-bar input {
            flex: 1;
            padding: 14px 18px;
            border: 2px solid var(--bg-elevated);
            border-radius: var(--radius-md);
            font-size: 15px;
            background: var(--bg-card);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }
        
        .search-bar input:focus {
            outline: none;
            border-color: rgba(88, 101, 242, 0.5);
            background: var(--bg-elevated);
        }
        
        .search-bar button {
            padding: 14px 28px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 600;
            box-shadow: 0 4px 15px var(--shadow-color, rgba(88, 101, 242, 0.4));
            transition: all 0.3s ease;
        }
        
        .search-bar button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px var(--shadow-color, rgba(88, 101, 242, 0.6));
        }
        
        .bulk-actions {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .bulk-actions select {
            padding: 10px 40px 10px 16px;
            border: 2px solid var(--bg-elevated);
            border-radius: var(--radius-md);
            background-color: var(--bg-card);
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23ffffff' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 12px;
            color: var(--text-primary);
            font-weight: 500;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            cursor: pointer;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        
        .bulk-actions select:focus {
            outline: none;
            border-color: var(--primary, var(--accent));
            box-shadow: 0 0 0 3px rgba(88, 101, 242, 0.2);
        }
        
        .bulk-actions select option {
            background: #1a1a2e;
            color: var(--text-primary);
            padding: 10px;
        }
        
        .bulk-actions button {
            padding: 10px 20px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 600;
            box-shadow: 0 4px 15px var(--shadow-color, rgba(88, 101, 242, 0.4));
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .bulk-actions button:hover {
            transform: translateY(-2px);
        }
        
        .table-wrapper {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid var(--border);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: var(--bg-elevated);
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
        
        td {
            padding: 16px;
            border-bottom: 1px solid var(--border);
        }
        
        tbody tr:last-child td {
            border-bottom: none;
        }
        
        tbody tr:hover td { background: var(--bg-elevated); }
        
        .action-buttons {
            white-space: nowrap;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-right: 6px;
        }
        
        .btn:hover { transform: translateY(-2px); }
        
        .btn-approve { 
            background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }
        
        .btn-deny { 
            background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }
        
        .btn-delete { 
            background: linear-gradient(135deg, #991b1b 0%, #7f1d1d 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(153, 27, 27, 0.4);
        }
        
        .btn-admin { 
            background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.4);
        }
        
        .btn-add { 
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
        }
        
        .admin-badge {
            background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
            color: white;
            padding: 4px 12px;
            border-radius: var(--radius-md);
            font-size: 11px;
            font-weight: 700;
            margin-left: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        
        .close {
            font-size: 32px;
            font-weight: bold;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.3s ease;
            line-height: 1;
        }
        
        .close:hover { 
            color: var(--text-primary);
        }
        
        @media (max-width: 768px) {
            .container { padding: 0 16px; }
            table { font-size: 13px; }
            th, td { padding: 12px; }
            .section { padding: 24px; }
        }
    </style>
</head>
<body>
    <?php $current_page = 'admin_home'; include '../includes/navbar.php'; ?>

    <div class="container">
        <?php showPageToasts(); ?>

        <div class="section">
            <h2>⏳ Pending User Approvals</h2>
            <?php if ($pending_users->num_rows > 0): ?>
                <form method="POST" id="bulkPendingForm">
                    <?php echo csrfField(); ?>
                    <div class="bulk-actions">
                        <select name="bulk_action" required>
                            <option value="">Bulk Actions...</option>
                            <option value="approve">Approve Selected</option>
                            <option value="deny">Deny Selected</option>
                        </select>
                        <button type="submit" onclick="return confirm('Apply bulk action to selected users?')">Apply</button>
                    </div>
                    
                    <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAllPending"></th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Discord ID</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $pending_users->fetch_assoc()): ?>
                                <tr>
                                    <td><input type="checkbox" name="user_ids[]" value="<?php echo $user['id']; ?>" class="pending-checkbox"></td>
                                    <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['discord_user_id'] ?? $user['discord_id'] ?? '-'); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                    <td class="action-buttons">
                                        <button type="button" class="btn btn-approve" 
                                                onclick="quickApprove(<?php echo $user['id']; ?>, this)">✓ Approve</button>
                                        <button type="button" class="btn btn-deny" 
                                                onclick="if(confirm('Deny and delete this user?')) quickDeny(<?php echo $user['id']; ?>, this)">✗ Deny</button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    </div>
                </form>
                
                <!-- Hidden forms for individual approve/deny actions (outside the bulk form) -->
                <form method="POST" id="quickApproveForm" style="display:none;">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="user_id" id="approveUserId">
                    <input type="hidden" name="approve_user" value="1">
                </form>
                <form method="POST" id="quickDenyForm" style="display:none;">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="user_id" id="denyUserId">
                    <input type="hidden" name="deny_user" value="1">
                </form>
                
                <script>
                function quickApprove(userId, btn) {
                    document.getElementById('approveUserId').value = userId;
                    btn.disabled = true;
                    btn.textContent = '...';
                    document.getElementById('quickApproveForm').submit();
                }
                function quickDeny(userId, btn) {
                    document.getElementById('denyUserId').value = userId;
                    btn.disabled = true;
                    btn.textContent = '...';
                    document.getElementById('quickDenyForm').submit();
                }
                </script>
            <?php else: ?>
                <div class="empty-state">No pending approvals</div>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>👥 User Management</h2>
            
            <form method="GET" class="search-bar">
                <input type="text" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary btn-sm">Search</button>
                <a href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['export' => 'csv']))); ?>" class="btn-export">📥 Export CSV</a>
            </form>
            
            <?php if ($approved_users->num_rows > 0): ?>
                <form method="POST" id="bulkUserForm">
                    <?php echo csrfField(); ?>
                    <div class="bulk-actions">
                        <select name="bulk_action" required>
                            <option value="">Bulk Actions...</option>
                            <option value="delete">Delete Selected</option>
                            <option value="make_admin">Make Admin</option>
                        </select>
                        <button type="submit" onclick="return confirm('Apply bulk action to selected users?')">Apply</button>
                    </div>
                    
                    <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAllUsers"></th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Discord ID</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $approved_users->fetch_assoc()): ?>
                                <tr>
                                    <td><input type="checkbox" name="user_ids[]" value="<?php echo $user['id']; ?>" class="user-checkbox" <?php echo $user['id'] == $_SESSION['user_id'] ? 'disabled' : ''; ?>></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                        <?php if ($user['is_admin']): ?>
                                            <span class="admin-badge">ADMIN</span>
                                        <?php endif; ?>
                                        <?php if (!empty($user['is_suspended'])): ?>
                                            <span class="admin-badge" style="background: linear-gradient(135deg, var(--danger), #dc2626);" title="<?php echo htmlspecialchars($user['suspended_reason'] ?? ''); ?>">SUSPENDED</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['discord_user_id'] ?? $user['discord_id'] ?? '-'); ?></td>
                                    <td>
                                        <?php
                                        $stmt_rc = $conn->prepare("SELECT COUNT(*) as count FROM roster WHERE user_id = ?");
                                        $stmt_rc->bind_param("i", $user['id']);
                                        $stmt_rc->execute();
                                        $roster_check = $stmt_rc->get_result()->fetch_assoc();
                                        $in_roster = ($roster_check['count'] ?? 0) > 0;
                                        $stmt_rc->close();
                                        echo $in_roster ? '✓ In Roster' : 'Not Assigned';
                                        ?>
                                    </td>
                                    <td class="action-buttons">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>" class="row-user-id">
                                            <button type="button" class="btn btn-add" onclick="openAddToRoster(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">+ Roster</button>
                                            
                                            <?php if ($user['is_admin']): ?>
                                                <button type="button" class="btn btn-deny" onclick="if(confirm('Remove admin privileges?')) submitUserAction(<?php echo $user['id']; ?>, 'remove_admin')">Remove Admin</button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-admin" onclick="submitUserAction(<?php echo $user['id']; ?>, 'make_admin')">Make Admin</button>
                                            <?php endif; ?>
                                            
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <?php if (!empty($user['is_suspended'])): ?>
                                                    <button type="button" class="btn btn-approve" onclick="if(confirm('Reinstate this user?')) submitUserAction(<?php echo $user['id']; ?>, 'unsuspend_user')">🔓 Unsuspend</button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-warning" onclick="openSuspendModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">🔒 Suspend</button>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-delete" onclick="if(confirm('Delete this user permanently?')) submitUserAction(<?php echo $user['id']; ?>, 'delete_user')">Delete</button>
                                            <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    </div>
                </form>
                <form method="POST" id="userActionForm" style="display: none;">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="user_id" id="actionUserId">
                    <input type="hidden" name="action_type" id="actionType">
                </form>
                <script>
                function submitUserAction(userId, actionType) {
                    document.getElementById('actionUserId').value = userId;
                    const form = document.getElementById('userActionForm');
                    // Add the action as a named field
                    let actionInput = form.querySelector('input[name="' + actionType + '"]');
                    if (!actionInput) {
                        actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = actionType;
                        actionInput.value = '1';
                        form.appendChild(actionInput);
                    }
                    form.submit();
                }
                </script>
                
                <?php echo renderPagination($user_pagination); ?>
            <?php else: ?>
                <div class="empty-state">No users found</div>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>📋 Roster Management</h2>
            <?php
            $roster_query = "
                SELECT r.*, u.username, d.name as dept_name, d.abbreviation, rk.rank_name
                FROM roster r
                JOIN users u ON r.user_id = u.id
                JOIN departments d ON r.department_id = d.id
                JOIN ranks rk ON r.rank_id = rk.id
                ORDER BY d.name, rk.rank_order, u.username
            ";
            $all_roster = $conn->query($roster_query);
            ?>
            <?php if ($all_roster->num_rows > 0): ?>
                <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Department</th>
                            <th>Rank</th>
                            <th>Badge</th>
                            <th>Callsign</th>
                            <th>Status</th>
                            <th>Primary</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($member = $all_roster->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($member['username']); ?></strong></td>
                                <td><?php echo htmlspecialchars($member['abbreviation']); ?></td>
                                <td><?php echo htmlspecialchars($member['rank_name']); ?></td>
                                <td><?php echo htmlspecialchars($member['badge_number'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($member['callsign'] ?? '-'); ?></td>
                                <td><?php echo strtoupper($member['status']); ?></td>
                                <td><?php echo $member['is_primary'] ? '✓' : ''; ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                    <?php echo csrfField(); ?>
                                        <input type="hidden" name="roster_id" value="<?php echo $member['id']; ?>">
                                        <button type="submit" name="remove_from_roster" class="btn btn-deny" onclick="return confirm('Remove from roster?')">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                </div>
            <?php else: ?>
                <div class="empty-state">No roster entries</div>
            <?php endif; ?>
        </div>
    </div>

    <div id="addRosterModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add to Roster</h3>
                <span class="close" onclick="closeModal('addRosterModal')">&times;</span>
            </div>
            <form method="POST">
                    <?php echo csrfField(); ?>
                <input type="hidden" name="user_id" id="modal_user_id">
                
                <div class="form-group">
                    <label>User</label>
                    <input type="text" id="modal_username" readonly style="background: var(--bg-elevated); cursor: not-allowed;">
                </div>
                
                <div class="form-group">
                    <label>Department *</label>
                    <select name="department_id" id="department_select" required onchange="loadRanks()">
                        <option value="">Select Department</option>
                        <?php
                        $departments->data_seek(0);
                        while ($dept = $departments->fetch_assoc()):
                        ?>
                            <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Rank *</label>
                    <select name="rank_id" id="rank_select" required>
                        <option value="">Select Department First</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Badge Number</label>
                    <input type="text" name="badge_number" placeholder="Optional">
                </div>
                
                <div class="form-group">
                    <label>Callsign</label>
                    <input type="text" name="callsign" placeholder="Optional">
                </div>
                
                <div class="form-group">
                    <label>Joined Date</label>
                    <input type="date" name="joined_date" value="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" name="is_primary" checked style="width: auto; margin-right: 10px;"> Primary Department
                    </label>
                </div>
                
                <button type="submit" name="add_to_roster" class="btn btn-add" style="width: 100%; padding: 14px; margin-top: 8px;">Add to Roster</button>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('selectAllPending')?.addEventListener('change', function() {
            document.querySelectorAll('.pending-checkbox').forEach(cb => cb.checked = this.checked);
        });
        
        document.getElementById('selectAllUsers')?.addEventListener('change', function() {
            document.querySelectorAll('.user-checkbox:not(:disabled)').forEach(cb => cb.checked = this.checked);
        });
        
        function openAddToRoster(userId, username) {
            document.getElementById('modal_user_id').value = userId;
            document.getElementById('modal_username').value = username;
            openModal('addRosterModal');
        }

        function loadRanks() {
            const deptId = document.getElementById('department_select').value;
            const rankSelect = document.getElementById('rank_select');
            
            if (!deptId) {
                rankSelect.innerHTML = '<option value="">Select Department First</option>';
                return;
            }
            
            fetch(`../api/get_ranks?dept_id=${deptId}`)
                .then(response => response.json())
                .then(ranks => {
                    rankSelect.innerHTML = '<option value="">Select Rank</option>';
                    ranks.forEach(rank => {
                        rankSelect.innerHTML += `<option value="${rank.id}">${rank.rank_name}</option>`;
                    });
                })
                .catch(error => {
                    console.error('Error loading ranks:', error);
                    rankSelect.innerHTML = '<option value="">Error loading ranks</option>';
                });
        }

        function openSuspendModal(userId, username) {
            document.getElementById('suspend_user_id').value = userId;
            document.getElementById('suspend_username').textContent = username;
            document.getElementById('suspend_reason').value = '';
            openModal('suspendModal');
        }

        function closeSuspendModal() {
            closeModal('suspendModal');
        }
    </script>

    <!-- Suspend User Modal -->
    <div id="suspendModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>🔒 Suspend User: <span id="suspend_username"></span></h3>
                <span class="close" onclick="closeSuspendModal()">&times;</span>
            </div>
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="user_id" id="suspend_user_id">
                <div class="form-group">
                    <label>Reason for Suspension</label>
                    <textarea name="suspend_reason" id="suspend_reason" rows="3" placeholder="Enter reason for suspension..."></textarea>
                </div>
                <button type="submit" name="suspend_user" class="btn btn-danger" style="width: 100%;" onclick="return confirm('Are you sure you want to suspend this user?')">Confirm Suspension</button>
            </form>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>