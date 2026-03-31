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
    
    if ($action === 'assign') {
        $target_user = intval($_POST['user_id'] ?? 0);
        $callsign = strtoupper(trim($_POST['callsign'] ?? ''));
        $department_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
        
        if ($target_user && $callsign) {
            // Check if callsign is already in use
            $stmt = $conn->prepare("SELECT id FROM callsigns WHERE callsign = ? AND user_id != ?");
            $stmt->bind_param("si", $callsign, $target_user);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = 'This callsign is already assigned to another user.';
            } else {
                $stmt = $conn->prepare("INSERT INTO callsigns (user_id, callsign, department_id, assigned_by) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE callsign = ?, department_id = ?, assigned_by = ?, assigned_at = NOW()");
                $stmt->bind_param("isiisii", $target_user, $callsign, $department_id, $user_id, $callsign, $department_id, $user_id);
                if ($stmt->execute()) {
                    $message = 'Callsign assigned!';
                    logAudit('callsign_assign', 'callsign', $target_user, "Assigned callsign: $callsign");
                }
            }
            $stmt->close();
        }
    } elseif ($action === 'remove') {
        $callsign_id = intval($_POST['callsign_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM callsigns WHERE id = ?");
        $stmt->bind_param("i", $callsign_id);
        if ($stmt->execute()) {
            $message = 'Callsign removed.';
        }
        $stmt->close();
    }
}

// Get all callsigns
$callsigns = $conn->query("
    SELECT c.*, u.username, d.name as department_name, a.username as assigned_by_name
    FROM callsigns c
    JOIN users u ON c.user_id = u.id
    LEFT JOIN departments d ON c.department_id = d.id
    LEFT JOIN users a ON c.assigned_by = a.id
    ORDER BY c.callsign ASC
")->fetch_all(MYSQLI_ASSOC);

$conn->close();

$theme = getThemeColors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Callsign Management - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
    <style>
        .callsign-badge { display: inline-block; padding: 4px 12px; background: var(--accent); border-radius: 6px; font-family: monospace; font-weight: 700; font-size: 14px; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>📻 Callsign Management</h1>
        <button class="btn btn-primary" onclick="document.getElementById('assignModal').style.display='flex'">+ Assign Callsign</button>
    </div>
    
    <?php if ($message) showToast($message, 'success'); ?>
    <?php if ($error) showToast($error, 'error'); ?>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Callsign</th>
                    <th>User</th>
                    <th>Department</th>
                    <th>Assigned By</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($callsigns as $c): ?>
                <tr>
                    <td><span class="callsign-badge"><?php echo htmlspecialchars($c['callsign']); ?></span></td>
                    <td><?php echo htmlspecialchars($c['username']); ?></td>
                    <td><?php echo htmlspecialchars($c['department_name'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($c['assigned_by_name'] ?: 'System'); ?></td>
                    <td><?php echo date('M j, Y', strtotime($c['assigned_at'])); ?></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this callsign?');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="callsign_id" value="<?php echo $c['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Assign Modal -->
<div id="assignModal" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Assign Callsign</h3>
            <button class="modal-close" onclick="document.getElementById('assignModal').style.display='none'">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
            <input type="hidden" name="action" value="assign">
            <div class="form-group">
                <label>User *</label>
                <select name="user_id" required>
                    <option value="">Select user...</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Callsign *</label>
                <input type="text" name="callsign" required placeholder="e.g., 1-A-12" style="text-transform:uppercase; font-family:monospace;">
            </div>
            <div class="form-group">
                <label>Department</label>
                <select name="department_id">
                    <option value="">— None —</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end; padding-top: 8px; border-top: 1px solid var(--border); margin-top: 16px;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('assignModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">Assign</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
