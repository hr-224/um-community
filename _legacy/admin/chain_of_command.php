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

// Get users and departments
$users = $conn->query("SELECT id, username FROM users WHERE is_approved = 1 ORDER BY username")->fetch_all(MYSQLI_ASSOC);
$departments = $conn->query("SELECT id, name FROM departments ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $target_user = intval($_POST['user_id'] ?? 0);
        $department_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
        $position_title = trim($_POST['position_title'] ?? '');
        $reports_to = !empty($_POST['reports_to']) ? intval($_POST['reports_to']) : null;
        $display_order = intval($_POST['display_order'] ?? 0);
        
        if ($target_user && $position_title) {
            $stmt = $conn->prepare("INSERT INTO chain_of_command (user_id, department_id, position_title, reports_to, display_order) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisii", $target_user, $department_id, $position_title, $reports_to, $display_order);
            if ($stmt->execute()) {
                $message = 'Position added to chain of command!';
                logAudit('coc_add', 'chain_of_command', $stmt->insert_id, "Added to COC: $position_title");
            }
            $stmt->close();
        }
    } elseif ($action === 'update') {
        $coc_id = intval($_POST['coc_id'] ?? 0);
        $position_title = trim($_POST['position_title'] ?? '');
        $reports_to = !empty($_POST['reports_to']) ? intval($_POST['reports_to']) : null;
        $display_order = intval($_POST['display_order'] ?? 0);
        
        $stmt = $conn->prepare("UPDATE chain_of_command SET position_title = ?, reports_to = ?, display_order = ? WHERE id = ?");
        $stmt->bind_param("siii", $position_title, $reports_to, $display_order, $coc_id);
        if ($stmt->execute()) {
            $message = 'Position updated!';
        }
        $stmt->close();
    } elseif ($action === 'delete') {
        $coc_id = intval($_POST['coc_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM chain_of_command WHERE id = ?");
        $stmt->bind_param("i", $coc_id);
        if ($stmt->execute()) {
            $message = 'Position removed from chain of command.';
        }
        $stmt->close();
    }
}

// Get chain of command entries
$entries = $conn->query("
    SELECT coc.*, u.username, d.name as department_name, sup.username as reports_to_name
    FROM chain_of_command coc
    JOIN users u ON coc.user_id = u.id
    LEFT JOIN departments d ON coc.department_id = d.id
    LEFT JOIN users sup ON coc.reports_to = sup.id
    ORDER BY coc.department_id, coc.display_order ASC
")->fetch_all(MYSQLI_ASSOC);

$conn->close();

$theme = getThemeColors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chain of Command - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>🏛️ Chain of Command</h1>
        <button class="btn btn-primary" onclick="document.getElementById('addModal').style.display='flex'">+ Add Position</button>
    </div>
    
    <?php if ($message) showToast($message, 'success'); ?>
    <?php if ($error) showToast($error, 'error'); ?>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Order</th>
                    <th>User</th>
                    <th>Position</th>
                    <th>Department</th>
                    <th>Reports To</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $e): ?>
                <tr>
                    <td><?php echo $e['display_order']; ?></td>
                    <td><?php echo htmlspecialchars($e['username']); ?></td>
                    <td><strong><?php echo htmlspecialchars($e['position_title']); ?></strong></td>
                    <td><?php echo htmlspecialchars($e['department_name'] ?: 'Leadership'); ?></td>
                    <td><?php echo htmlspecialchars($e['reports_to_name'] ?: '—'); ?></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this position?');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="coc_id" value="<?php echo $e['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header"><h3>Add to Chain of Command</h3><button class="modal-close" onclick="document.getElementById('addModal').style.display='none'">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>User *</label>
                <select name="user_id" class="form-control" required>
                    <option value="">Select user...</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Position Title *</label>
                <input type="text" name="position_title" class="form-control" required placeholder="e.g., Chief of Police, Captain, etc.">
            </div>
            <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" class="form-control">
                        <option value="">— Leadership —</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Reports To</label>
                    <select name="reports_to" class="form-control">
                        <option value="">— None —</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Display Order</label>
                <input type="number" name="display_order" class="form-control" value="0" min="0">
                <small style="color:var(--text-muted);">Lower numbers appear first</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Position</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
