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

// Get departments
$departments = $conn->query("SELECT id, name FROM departments ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $shift_date = $_POST['shift_date'] ?? '';
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';
        $max_slots = intval($_POST['max_slots'] ?? 0);
        $department_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
        
        if ($title && $shift_date && $start_time && $end_time) {
            $stmt = $conn->prepare("INSERT INTO shifts (title, description, shift_date, start_time, end_time, max_slots, department_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssiii", $title, $description, $shift_date, $start_time, $end_time, $max_slots, $department_id, $user_id);
            if ($stmt->execute()) {
                $message = 'Shift created successfully!';
                logAudit('shift_create', 'shift', $stmt->insert_id, "Created shift: $title");
            }
            $stmt->close();
        } else {
            $error = 'Please fill in all required fields.';
        }
    } elseif ($action === 'delete') {
        $shift_id = intval($_POST['shift_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM shifts WHERE id = ?");
        $stmt->bind_param("i", $shift_id);
        if ($stmt->execute()) {
            $message = 'Shift deleted.';
            logAudit('shift_delete', 'shift', $shift_id, 'Deleted shift');
        }
        $stmt->close();
    }
}

// Get shifts
$shifts = $conn->query("
    SELECT s.*, d.name as department_name, u.username as created_by_name,
           (SELECT COUNT(*) FROM shift_signups WHERE shift_id = s.id) as signup_count
    FROM shifts s
    LEFT JOIN departments d ON s.department_id = d.id
    LEFT JOIN users u ON s.created_by = u.id
    ORDER BY s.shift_date DESC, s.start_time DESC
    LIMIT 100
")->fetch_all(MYSQLI_ASSOC);

$conn->close();

$theme = getThemeColors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Shifts - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>📅 Manage Shifts</h1>
        <button class="btn btn-primary" onclick="document.getElementById('createModal').style.display='flex'">+ Create Shift</button>
    </div>
    
    <?php if ($message) showToast($message, 'success'); ?>
    <?php if ($error) showToast($error, 'error'); ?>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Title</th>
                    <th>Time</th>
                    <th>Department</th>
                    <th>Signups</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($shifts as $shift): ?>
                <tr>
                    <td><?php echo date('M j, Y', strtotime($shift['shift_date'])); ?></td>
                    <td><?php echo htmlspecialchars($shift['title']); ?></td>
                    <td><?php echo date('g:i A', strtotime($shift['start_time'])); ?> - <?php echo date('g:i A', strtotime($shift['end_time'])); ?></td>
                    <td><?php echo htmlspecialchars($shift['department_name'] ?: '—'); ?></td>
                    <td><?php echo $shift['signup_count']; ?><?php echo $shift['max_slots'] > 0 ? '/' . $shift['max_slots'] : ''; ?></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this shift?');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="shift_id" value="<?php echo $shift['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Modal -->
<div id="createModal" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Create Shift</h3>
            <button class="modal-close" onclick="document.getElementById('createModal').style.display='none'">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label>Date *</label>
                    <input type="date" name="shift_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Start Time *</label>
                    <input type="time" name="start_time" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>End Time *</label>
                    <input type="time" name="end_time" class="form-control" required>
                </div>
            </div>
            <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label>Max Slots (0 = unlimited)</label>
                    <input type="number" name="max_slots" class="form-control" value="0" min="0">
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" class="form-control">
                        <option value="">— All —</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('createModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Shift</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
