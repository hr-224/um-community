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
$error = '';

$target_user_id = intval($_GET['user_id'] ?? 0);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $target = intval($_POST['target_user'] ?? 0);
        $note = trim($_POST['note'] ?? '');
        
        if ($target && $note) {
            $stmt = $conn->prepare("INSERT INTO admin_notes (user_id, note, created_by) VALUES (?, ?, ?)");
            $stmt->bind_param("isi", $target, $note, $user_id);
            if ($stmt->execute()) {
                $message = 'Note added!';
                logAudit('admin_note_add', 'admin_note', $stmt->insert_id, "Added admin note for user $target");
            }
            $stmt->close();
        }
    } elseif ($action === 'delete') {
        $note_id = intval($_POST['note_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM admin_notes WHERE id = ?");
        $stmt->bind_param("i", $note_id);
        if ($stmt->execute()) {
            $message = 'Note deleted.';
        }
        $stmt->close();
    }
}

// Get users for dropdown
$users = $conn->query("SELECT id, username FROM users ORDER BY username")->fetch_all(MYSQLI_ASSOC);

// Build query based on filter
$where = "";
$params = [];
$types = "";

if ($target_user_id) {
    $where = "WHERE an.user_id = ?";
    $params[] = $target_user_id;
    $types = "i";
}

$sql = "SELECT an.*, u.username as target_username, c.username as created_by_name
        FROM admin_notes an
        JOIN users u ON an.user_id = u.id
        JOIN users c ON an.created_by = c.id
        $where
        ORDER BY an.created_at DESC
        LIMIT 100";

if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $notes = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

$conn->close();

$theme = getThemeColors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Notes - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
    <style>
        .note-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px; margin-bottom: 16px; }
        .note-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px; }
        .note-user { font-weight: 600; color: var(--text-primary); }
        .note-meta { font-size: 12px; color: var(--text-muted); }
        .note-content { color: var(--text-secondary); line-height: 1.6; white-space: pre-wrap; }
        .filter-bar { display: flex; gap: 12px; margin-bottom: 24px; align-items: end; }
        .filter-bar .form-group { margin: 0; flex: 1; max-width: 300px; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>📝 Admin Notes</h1>
        <button class="btn btn-primary" onclick="document.getElementById('addModal').style.display='flex'">+ Add Note</button>
    </div>
    
    <?php if ($message) showToast($message, 'success'); ?>
    <?php if ($error) showToast($error, 'error'); ?>
    
    <form class="filter-bar" method="GET">
        <div class="form-group">
            <label>Filter by User</label>
            <select name="user_id" class="form-control" onchange="this.form.submit()">
                <option value="">All Users</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?php echo $u['id']; ?>" <?php echo $target_user_id == $u['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['username']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
    
    <?php if (empty($notes)): ?>
        <div class="empty-state"><p>No admin notes found.</p></div>
    <?php else: ?>
        <?php foreach ($notes as $note): ?>
        <div class="note-card">
            <div class="note-header">
                <div>
                    <div class="note-user">Re: <?php echo htmlspecialchars($note['target_username']); ?></div>
                    <div class="note-meta">By <?php echo htmlspecialchars($note['created_by_name']); ?> on <?php echo date('M j, Y g:i A', strtotime($note['created_at'])); ?></div>
                </div>
                <form method="POST" onsubmit="return confirm('Delete this note?');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="note_id" value="<?php echo $note['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                </form>
            </div>
            <div class="note-content"><?php echo htmlspecialchars($note['note']); ?></div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Add Note Modal -->
<div id="addModal" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header"><h3>Add Admin Note</h3><button class="modal-close" onclick="document.getElementById('addModal').style.display='none'">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>User *</label>
                <select name="target_user" class="form-control" required>
                    <option value="">Select user...</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $target_user_id == $u['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['username']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Note *</label>
                <textarea name="note" class="form-control" rows="4" required placeholder="This note is only visible to admins..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Note</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
