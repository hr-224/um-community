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
    
    if ($action === 'create') {
        $trainee_id = intval($_POST['trainee_id'] ?? 0);
        $mentor_id = intval($_POST['mentor_id'] ?? 0);
        $department_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
        $notes = trim($_POST['notes'] ?? '');
        
        if ($trainee_id && $mentor_id && $trainee_id !== $mentor_id) {
            $stmt = $conn->prepare("INSERT INTO mentorships (trainee_id, mentor_id, department_id, notes) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiis", $trainee_id, $mentor_id, $department_id, $notes);
            if ($stmt->execute()) {
                $message = 'Mentorship created!';
                logAudit('mentorship_create', 'mentorship', $stmt->insert_id, 'Created mentorship');
                createNotification($trainee_id, 'FTO Assigned', 'You have been assigned a Field Training Officer.', 'info');
                createNotification($mentor_id, 'Trainee Assigned', 'You have been assigned a new trainee.', 'info');
            }
            $stmt->close();
        } else {
            $error = 'Invalid selection.';
        }
    } elseif ($action === 'update_status') {
        $mentorship_id = intval($_POST['mentorship_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        
        if (in_array($status, ['active', 'completed', 'cancelled'])) {
            $completed_at = $status === 'completed' ? date('Y-m-d H:i:s') : null;
            $stmt = $conn->prepare("UPDATE mentorships SET status = ?, completed_at = ? WHERE id = ?");
            $stmt->bind_param("ssi", $status, $completed_at, $mentorship_id);
            if ($stmt->execute()) {
                $message = 'Status updated!';
            }
            $stmt->close();
        }
    } elseif ($action === 'add_note') {
        $mentorship_id = intval($_POST['mentorship_id'] ?? 0);
        $note = trim($_POST['note'] ?? '');
        
        if ($mentorship_id && $note) {
            $stmt = $conn->prepare("INSERT INTO mentorship_notes (mentorship_id, note, created_by) VALUES (?, ?, ?)");
            $stmt->bind_param("isi", $mentorship_id, $note, $user_id);
            if ($stmt->execute()) {
                $message = 'Note added!';
            }
            $stmt->close();
        }
    }
}

// Get mentorships
$mentorships = $conn->query("
    SELECT m.*, 
           t.username as trainee_name, 
           f.username as mentor_name,
           d.name as department_name,
           (SELECT COUNT(*) FROM mentorship_notes WHERE mentorship_id = m.id) as note_count
    FROM mentorships m
    JOIN users t ON m.trainee_id = t.id
    JOIN users f ON m.mentor_id = f.id
    LEFT JOIN departments d ON m.department_id = d.id
    ORDER BY m.status = 'active' DESC, m.started_at DESC
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
    <title>FTO/Mentorship - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
    <style>
        .status-active { color: var(--success); }
        .status-completed { color: #3b82f6; }
        .status-cancelled { color: var(--danger); }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>👨‍🏫 FTO / Mentorship Tracking</h1>
        <button class="btn btn-primary" onclick="document.getElementById('createModal').style.display='flex'">+ Assign FTO</button>
    </div>
    
    <?php if ($message) showToast($message, 'success'); ?>
    <?php if ($error) showToast($error, 'error'); ?>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Trainee</th>
                    <th>FTO/Mentor</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th>Started</th>
                    <th>Notes</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mentorships as $m): ?>
                <tr>
                    <td><?php echo htmlspecialchars($m['trainee_name']); ?></td>
                    <td><?php echo htmlspecialchars($m['mentor_name']); ?></td>
                    <td><?php echo htmlspecialchars($m['department_name'] ?: '—'); ?></td>
                    <td><span class="status-<?php echo $m['status']; ?>"><?php echo ucfirst($m['status']); ?></span></td>
                    <td><?php echo date('M j, Y', strtotime($m['started_at'])); ?></td>
                    <td><?php echo $m['note_count']; ?></td>
                    <td>
                        <?php if ($m['status'] === 'active'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="mentorship_id" value="<?php echo $m['id']; ?>">
                            <button type="submit" name="status" value="completed" class="btn btn-sm btn-success">Complete</button>
                            <button type="submit" name="status" value="cancelled" class="btn btn-sm btn-danger">Cancel</button>
                        </form>
                        <?php endif; ?>
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
        <div class="modal-header"><h3>Assign FTO/Mentor</h3><button class="modal-close" onclick="document.getElementById('createModal').style.display='none'">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
            <input type="hidden" name="action" value="create">
            <div class="form-group"><label>Trainee *</label>
                <select name="trainee_id" class="form-control" required>
                    <option value="">Select trainee...</option>
                    <?php foreach ($users as $u): ?><option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>FTO/Mentor *</label>
                <select name="mentor_id" class="form-control" required>
                    <option value="">Select mentor...</option>
                    <?php foreach ($users as $u): ?><option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Department</label>
                <select name="department_id" class="form-control">
                    <option value="">— None —</option>
                    <?php foreach ($departments as $d): ?><option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="document.getElementById('createModal').style.display='none'">Cancel</button><button type="submit" class="btn btn-primary">Assign</button></div>
        </form>
    </div>
</div>

</body>
</html>
