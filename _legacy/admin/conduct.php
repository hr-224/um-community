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
if (!isAdmin() && !hasAnyPermission(['conduct.view', 'conduct.manage'])) {
    header('Location: /index?error=unauthorized');
    exit();
}

$conn = getDBConnection();
$message = '';
$is_admin = isAdmin();
$can_manage = $is_admin || hasPermission('conduct.manage');

// Handle record creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['create_record']) && $can_manage) {
    $user_id = intval($_POST['user_id']);
    $type = $_POST['type'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $severity = $_POST['severity'];
    $dept_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
    $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    $issued_by = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("INSERT INTO conduct_records (user_id, type, title, description, severity, department_id, issued_by, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssis", $user_id, $type, $title, $description, $severity, $dept_id, $issued_by, $expires_at);
    $stmt->execute();
    
    // Notify user
    $type_labels = ['commendation' => 'Commendation', 'warning' => 'Warning', 'disciplinary' => 'Disciplinary Action', 'note' => 'Note'];
    createNotification($user_id, 'New ' . $type_labels[$type], "You have received a new $type: $title", $type === 'commendation' ? 'success' : 'warning');
    
    logAudit('create_conduct_record', 'conduct', $stmt->insert_id, "Created $type for user $user_id: $title");
    $message = ucfirst($type) . ' created successfully!';
    $stmt->close();
}

// Handle record deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['delete_record']) && $can_manage) {
    $id = intval($_POST['record_id']);
    $stmt = $conn->prepare("DELETE FROM conduct_records WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    logAudit('delete_conduct_record', 'conduct', $id, 'Deleted conduct record');
    $message = 'Record deleted!';
}

// Get filter parameters
$filter_type = $_GET['type'] ?? '';
$filter_user = $_GET['user'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;

// Build query with prepared statement params
$where = "1=1";
$params = [];
$types = '';

if ($filter_type) {
    $where .= " AND c.type = ?";
    $params[] = $filter_type;
    $types .= 's';
}
if ($filter_user) {
    $where .= " AND c.user_id = ?";
    $params[] = intval($filter_user);
    $types .= 'i';
}
if ($search) {
    $where .= " AND (c.title LIKE ? OR u.username LIKE ?)";
    $search_term = '%' . $search . '%';
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

// Count total
$count_sql = "SELECT COUNT(*) as cnt FROM conduct_records c JOIN users u ON c.user_id = u.id WHERE $where";
$count_stmt = $conn->prepare($count_sql);
if ($params) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$count_row = $count_stmt->get_result()->fetch_assoc();
$total = $count_row['cnt'] ?? 0;
$count_stmt->close();

$base_url = '?type=' . urlencode($filter_type) . '&user=' . urlencode($filter_user) . '&search=' . urlencode($search);
$pagination = getPagination($total, $per_page, $page, $base_url);

// Get records
$sql = "SELECT c.*, u.username, d.name as dept_name, i.username as issuer_name
    FROM conduct_records c
    JOIN users u ON c.user_id = u.id
    LEFT JOIN departments d ON c.department_id = d.id
    JOIN users i ON c.issued_by = i.id
    WHERE $where
    ORDER BY c.created_at DESC
    LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $pagination['offset'];
$types .= 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$records = $stmt->get_result();
$stmt->close();

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $export_params = array_slice($params, 0, -2);
    $export_types = substr($types, 0, -2);
    $export_sql = "SELECT c.*, u.username, d.name as dept_name, i.username as issuer_name
        FROM conduct_records c JOIN users u ON c.user_id = u.id
        LEFT JOIN departments d ON c.department_id = d.id
        JOIN users i ON c.issued_by = i.id WHERE $where ORDER BY c.created_at DESC";
    $export_stmt = $conn->prepare($export_sql);
    if ($export_params) $export_stmt->bind_param($export_types, ...$export_params);
    $export_stmt->execute();
    $export_result = $export_stmt->get_result();
    $rows = [];
    while ($r = $export_result->fetch_assoc()) {
        $rows[] = [$r['username'], $r['type'], $r['title'], $r['dept_name'] ?? 'N/A', $r['issuer_name'], $r['created_at']];
    }
    $export_stmt->close();
    exportCSV('conduct_records_' . date('Y-m-d') . '.csv', ['Member', 'Type', 'Title', 'Department', 'Issued By', 'Date'], $rows);
}

// Get users for dropdown
$users = $conn->query("SELECT id, username FROM users WHERE is_approved = TRUE ORDER BY username");
$departments = $conn->query("SELECT id, name FROM departments ORDER BY name");

// Get statistics
$stats = $conn->query("
    SELECT 
        SUM(type = 'commendation') as commendations,
        SUM(type = 'warning') as warnings,
        SUM(type = 'disciplinary') as disciplinary,
        SUM(type = 'note') as notes
    FROM conduct_records WHERE is_active = TRUE
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conduct Records - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <?php include '../includes/styles.php'; ?>
    <style>
        .container { max-width: 1400px; }
        .message {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2));
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #4ade80;
            padding: 16px 24px;
            border-radius: var(--radius-md);
            margin-bottom: 24px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--bg-elevated);
            padding: 24px;
            border-radius: var(--radius-lg);
            text-align: center;
        }
        .stat-value { font-size: 36px; font-weight: 800; }
        .stat-label { font-size: 14px; color: var(--text-muted); margin-top: 4px; }
        .stat-commendation .stat-value { color: #4ade80; }
        .stat-warning .stat-value { color: #f0b232; }
        .stat-disciplinary .stat-value { color: #f87171; }
        .stat-note .stat-value { color: #93c5fd; }
        .grid { display: grid; grid-template-columns: 380px 1fr; gap: 24px; }
        @media (max-width: 1024px) { .grid { grid-template-columns: 1fr; } .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        .section {
            background: var(--bg-card);
            
            border: 1px solid var(--bg-elevated);
            padding: 32px;
            border-radius: var(--radius-lg);
        }
        .section h2 {
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 2px solid rgba(88, 101, 242, 0.3);
            font-size: 20px;
        }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--bg-elevated);
            border-radius: var(--radius-md);
            font-size: 14px;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: rgba(88, 101, 242, 0.5);
        }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary { background: var(--accent); color: white; }
        .btn-primary:hover { transform: translateY(-2px); }
        .btn-danger { background: linear-gradient(135deg, var(--danger), #dc2626); color: white; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .filters {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .filters select, .filters input {
            padding: 10px 16px;
            border: 1px solid var(--bg-elevated);
            border-radius: var(--radius-sm);
            background: var(--bg-card);
            color: var(--text-primary);
            font-size: 14px;
        }
        .record-card {
            background: var(--bg-elevated);
            border: 1px solid var(--bg-elevated);
            border-radius: var(--radius-md);
            padding: 20px;
            margin-bottom: 12px;
        }
        .record-card.commendation { border-left: 4px solid var(--success); }
        .record-card.warning { border-left: 4px solid #f59e0b; }
        .record-card.disciplinary { border-left: 4px solid var(--danger); }
        .record-card.note { border-left: 4px solid #3b82f6; }
        .record-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
        .record-title { font-weight: 700; font-size: 16px; }
        .record-meta { font-size: 12px; color: var(--text-muted); margin-bottom: 8px; }
        .record-desc { color: var(--text-secondary); font-size: 14px; line-height: 1.5; }
        .badge {
            padding: 4px 10px;
            border-radius: var(--radius-lg);
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-commendation { background: rgba(16, 185, 129, 0.2); color: #4ade80; }
        .badge-warning { background: rgba(245, 158, 11, 0.2); color: #f0b232; }
        .badge-disciplinary { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .badge-note { background: rgba(59, 130, 246, 0.2); color: #93c5fd; }
        .badge-minor { background: rgba(156, 163, 175, 0.2); color: #d1d5db; }
        .badge-moderate { background: rgba(251, 191, 36, 0.2); color: #f0b232; }
        .badge-major { background: rgba(249, 115, 22, 0.2); color: #fdba74; }
        .badge-critical { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .empty-state { text-align: center; padding: 60px; color: var(--text-muted); }
    </style>
</head>
<body>
    <?php $current_page = 'admin_conduct'; include '../includes/navbar.php'; ?>

    <div class="container">
        <?php showPageToasts(); ?>

        <div class="stats-grid">
            <div class="stat-card stat-commendation">
                <div class="stat-value"><?php echo $stats['commendations'] ?? 0; ?></div>
                <div class="stat-label">Commendations</div>
            </div>
            <div class="stat-card stat-warning">
                <div class="stat-value"><?php echo $stats['warnings'] ?? 0; ?></div>
                <div class="stat-label">Warnings</div>
            </div>
            <div class="stat-card stat-disciplinary">
                <div class="stat-value"><?php echo $stats['disciplinary'] ?? 0; ?></div>
                <div class="stat-label">Disciplinary</div>
            </div>
            <div class="stat-card stat-note">
                <div class="stat-value"><?php echo $stats['notes'] ?? 0; ?></div>
                <div class="stat-label">Notes</div>
            </div>
        </div>

        <div class="grid">
            <div class="section <?php echo !$can_manage ? 'permission-locked' : ''; ?>">
                <h2>Add Record</h2>
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <div class="form-group">
                        <label>Member *</label>
                        <select name="user_id" required>
                            <option value="">Select Member</option>
                            <?php while ($user = $users->fetch_assoc()): ?>
                                <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Type *</label>
                        <select name="type" required>
                            <option value="commendation">🏆 Commendation</option>
                            <option value="warning">⚠️ Warning</option>
                            <option value="disciplinary">🚨 Disciplinary Action</option>
                            <option value="note">📝 Note</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Title *</label>
                        <input type="text" name="title" required placeholder="Brief title">
                    </div>
                    <div class="form-group">
                        <label>Description *</label>
                        <textarea name="description" required placeholder="Detailed description..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Severity</label>
                        <select name="severity">
                            <option value="minor">Minor</option>
                            <option value="moderate">Moderate</option>
                            <option value="major">Major</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Department (optional)</label>
                        <select name="department_id">
                            <option value="">No specific department</option>
                            <?php $departments->data_seek(0); while ($dept = $departments->fetch_assoc()): ?>
                                <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Expires On (optional)</label>
                        <input type="date" name="expires_at">
                    </div>
                    <button type="submit" name="create_record" class="btn btn-primary" style="width: 100%;">Add Record</button>
                </form>
                <?php if (!$can_manage): ?>
                <?php permissionLockOverlay('You need the "Manage Conduct Records" permission to add records.'); ?>
                <?php endif; ?>
            </div>

            <div class="section">
                <h2>Records</h2>
                
                <form method="GET" class="filters">
                    <select name="type" onchange="this.form.submit()">
                        <option value="">All Types</option>
                        <option value="commendation" <?php echo $filter_type === 'commendation' ? 'selected' : ''; ?>>Commendations</option>
                        <option value="warning" <?php echo $filter_type === 'warning' ? 'selected' : ''; ?>>Warnings</option>
                        <option value="disciplinary" <?php echo $filter_type === 'disciplinary' ? 'selected' : ''; ?>>Disciplinary</option>
                        <option value="note" <?php echo $filter_type === 'note' ? 'selected' : ''; ?>>Notes</option>
                    </select>
                    <input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['export' => 'csv']))); ?>" class="btn-export">📥 Export CSV</a>
                </form>

                <?php if ($records->num_rows > 0): ?>
                    <?php while ($record = $records->fetch_assoc()): ?>
                        <div class="record-card <?php echo $record['type']; ?>">
                            <div class="record-header">
                                <div>
                                    <div class="record-title"><?php echo htmlspecialchars($record['title']); ?></div>
                                    <span class="badge badge-<?php echo $record['type']; ?>"><?php echo strtoupper($record['type']); ?></span>
                                    <span class="badge badge-<?php echo $record['severity']; ?>"><?php echo strtoupper($record['severity']); ?></span>
                                </div>
                                <?php if ($can_manage): ?>
                                <form method="POST" onsubmit="return confirm('Delete this record?')">
                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="record_id" value="<?php echo $record['id']; ?>">
                                    <button type="submit" name="delete_record" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                                <?php else: ?>
                                <?php lockedButton('Delete', 'Manage permission required'); ?>
                                <?php endif; ?>
                            </div>
                            <div class="record-meta">
                                <strong><?php echo htmlspecialchars($record['username']); ?></strong> • 
                                Issued by <?php echo htmlspecialchars($record['issuer_name']); ?> • 
                                <?php echo date('M j, Y', strtotime($record['created_at'])); ?>
                                <?php if ($record['dept_name']): ?> • <?php echo htmlspecialchars($record['dept_name']); ?><?php endif; ?>
                                <?php if ($record['expires_at']): ?> • Expires: <?php echo date('M j, Y', strtotime($record['expires_at'])); ?><?php endif; ?>
                            </div>
                            <div class="record-desc"><?php echo nl2br(htmlspecialchars($record['description'])); ?></div>
                        </div>
                    <?php endwhile; ?>
                    <?php echo renderPagination($pagination); ?>
                <?php else: ?>
                    <div class="empty-state">No records found</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
