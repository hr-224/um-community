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

// Handle announcement creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['create_announcement'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $type = $_POST['type'];
    $target_type = $_POST['target_type'];
    $target_dept = !empty($_POST['target_department_id']) ? intval($_POST['target_department_id']) : null;
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
    $starts_at = !empty($_POST['starts_at']) ? $_POST['starts_at'] : null;
    $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    $author_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("INSERT INTO announcements (title, content, type, target_type, target_department_id, author_id, is_pinned, starts_at, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssiisss", $title, $content, $type, $target_type, $target_dept, $author_id, $is_pinned, $starts_at, $expires_at);
    $stmt->execute();
    
    logAudit('create_announcement', 'announcement', $stmt->insert_id, "Created announcement: $title");
    
    sendDiscordWebhook('announcement', [
        'title' => 'New Announcement: ' . $title,
        'message' => substr(strip_tags($content), 0, 200),
        'type' => $type === 'urgent' ? 'warning' : 'info'
    ]);
    
    $message = 'Announcement created successfully!';
    $stmt->close();
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['delete_announcement'])) {
    $id = intval($_POST['announcement_id']);
    $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    logAudit('delete_announcement', 'announcement', $id, 'Deleted announcement');
    $message = 'Announcement deleted!';
}

// Handle toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['toggle_announcement'])) {
    $id = intval($_POST['announcement_id']);
    $stmt = $conn->prepare("UPDATE announcements SET is_active = NOT is_active WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $message = 'Announcement status updated!';
}

$announcements = $conn->query("
    SELECT a.*, u.username as author_name, d.name as dept_name
    FROM announcements a
    JOIN users u ON a.author_id = u.id
    LEFT JOIN departments d ON a.target_department_id = d.id
    ORDER BY a.is_pinned DESC, a.created_at DESC
");

$departments = $conn->query("SELECT id, name FROM departments ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - <?php echo COMMUNITY_NAME; ?></title>
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
        .grid { display: grid; grid-template-columns: 400px 1fr; gap: 24px; }
        @media (max-width: 1024px) { .grid { grid-template-columns: 1fr; } }
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
        .form-group { margin-bottom: 20px; }
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
        .form-group textarea { min-height: 150px; resize: vertical; }
        .checkbox-label { display: flex; align-items: center; gap: 10px; cursor: pointer; }
        .checkbox-label input { width: auto; }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary {
            background: var(--accent);
            color: white;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(88, 101, 242, 0.4); }
        .btn-danger { background: linear-gradient(135deg, var(--danger), #dc2626); color: white; }
        .btn-sm { padding: 8px 16px; font-size: 13px; }
        .announcement-card {
            background: var(--bg-elevated);
            border: 1px solid var(--bg-elevated);
            border-radius: var(--radius-lg);
            padding: 24px;
            margin-bottom: 16px;
        }
        .announcement-card.pinned { border-color: rgba(251, 191, 36, 0.3); background: rgba(251, 191, 36, 0.05); }
        .announcement-card.inactive { opacity: 0.5; }
        .announcement-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .announcement-title { font-size: 18px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .announcement-meta { font-size: 12px; color: var(--text-muted); margin-bottom: 12px; }
        .announcement-content { color: var(--text-secondary); line-height: 1.6; margin-bottom: 16px; }
        .announcement-actions { display: flex; gap: 8px; }
        .badge {
            padding: 4px 10px;
            border-radius: var(--radius-lg);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-info { background: rgba(59, 130, 246, 0.2); color: #93c5fd; }
        .badge-warning { background: rgba(251, 191, 36, 0.2); color: #f0b232; }
        .badge-urgent { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .badge-maintenance { background: rgba(168, 85, 247, 0.2); color: #d8b4fe; }
        .empty-state { text-align: center; padding: 60px; color: var(--text-muted); }
    </style>
</head>
<body>
    <?php $current_page = 'admin_announcements'; include '../includes/navbar.php'; ?>

    <div class="container">
        <?php showPageToasts(); ?>

        <div class="grid">
            <div class="section">
                <h2>Create Announcement</h2>
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <div class="form-group">
                        <label>Title *</label>
                        <input type="text" name="title" required placeholder="Announcement title">
                    </div>
                    <div class="form-group">
                        <label>Content *</label>
                        <textarea name="content" required placeholder="Announcement content..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select name="type">
                            <option value="info">ℹ️ Information</option>
                            <option value="warning">⚠️ Warning</option>
                            <option value="urgent">🚨 Urgent</option>
                            <option value="maintenance">🔧 Maintenance</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Target Audience</label>
                        <select name="target_type" id="target_type" onchange="toggleDeptSelect()">
                            <option value="all">All Users</option>
                            <option value="department">Specific Department</option>
                            <option value="admins">Admins Only</option>
                        </select>
                    </div>
                    <div class="form-group" id="dept_select_group" style="display: none;">
                        <label>Department</label>
                        <select name="target_department_id">
                            <option value="">Select Department</option>
                            <?php while ($dept = $departments->fetch_assoc()): ?>
                                <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Start Date (optional)</label>
                        <input type="datetime-local" name="starts_at">
                    </div>
                    <div class="form-group">
                        <label>Expiry Date (optional)</label>
                        <input type="datetime-local" name="expires_at">
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_pinned"> Pin this announcement
                        </label>
                    </div>
                    <button type="submit" name="create_announcement" class="btn btn-primary" style="width: 100%;">Create Announcement</button>
                </form>
            </div>

            <div class="section">
                <h2>All Announcements</h2>
                <?php if ($announcements->num_rows > 0): ?>
                    <?php while ($ann = $announcements->fetch_assoc()): ?>
                        <div class="announcement-card <?php echo $ann['is_pinned'] ? 'pinned' : ''; ?> <?php echo !$ann['is_active'] ? 'inactive' : ''; ?>">
                            <div class="announcement-header">
                                <div class="announcement-title">
                                    <?php if ($ann['is_pinned']): ?>📌<?php endif; ?>
                                    <?php echo htmlspecialchars($ann['title']); ?>
                                    <span class="badge badge-<?php echo $ann['type']; ?>"><?php echo strtoupper($ann['type']); ?></span>
                                </div>
                            </div>
                            <div class="announcement-meta">
                                By <?php echo htmlspecialchars($ann['author_name']); ?> • 
                                <?php echo date('M j, Y g:i A', strtotime($ann['created_at'])); ?> •
                                Target: <?php echo $ann['target_type'] === 'department' ? htmlspecialchars($ann['dept_name']) : ucfirst($ann['target_type']); ?>
                                <?php if (!$ann['is_active']): ?> • <strong>INACTIVE</strong><?php endif; ?>
                            </div>
                            <div class="announcement-content">
                                <?php echo nl2br(htmlspecialchars(substr($ann['content'], 0, 300))); ?>
                                <?php if (strlen($ann['content']) > 300): ?>...<?php endif; ?>
                            </div>
                            <div class="announcement-actions">
                                <form method="POST" style="display: inline;">
                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="announcement_id" value="<?php echo $ann['id']; ?>">
                                    <button type="submit" name="toggle_announcement" class="btn btn-sm" style="background: var(--bg-elevated);">
                                        <?php echo $ann['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this announcement?')">
                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="announcement_id" value="<?php echo $ann['id']; ?>">
                                    <button type="submit" name="delete_announcement" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">No announcements yet</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleDeptSelect() {
            const targetType = document.getElementById('target_type').value;
            document.getElementById('dept_select_group').style.display = targetType === 'department' ? 'block' : 'none';
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>
