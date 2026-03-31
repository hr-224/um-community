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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $event_date = $_POST['event_date'] ?? '';
        $start_time = $_POST['start_time'] ?: null;
        $end_time = $_POST['end_time'] ?: null;
        $location = trim($_POST['location'] ?? '');
        $is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;
        
        if ($title && $event_date) {
            $stmt = $conn->prepare("INSERT INTO events (title, description, event_date, start_time, end_time, location, is_mandatory, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssii", $title, $description, $event_date, $start_time, $end_time, $location, $is_mandatory, $user_id);
            if ($stmt->execute()) {
                $message = 'Event created successfully!';
                logAudit('event_create', 'event', $stmt->insert_id, "Created event: $title");
            }
            $stmt->close();
        } else {
            $error = 'Please fill in all required fields.';
        }
    } elseif ($action === 'delete') {
        $event_id = intval($_POST['event_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
        $stmt->bind_param("i", $event_id);
        if ($stmt->execute()) {
            $message = 'Event deleted.';
            logAudit('event_delete', 'event', $event_id, 'Deleted event');
        }
        $stmt->close();
    }
}

// Get events
$events = $conn->query("
    SELECT e.*, u.username as created_by_name,
           (SELECT COUNT(*) FROM event_rsvps WHERE event_id = e.id AND status = 'attending') as attending,
           (SELECT COUNT(*) FROM event_rsvps WHERE event_id = e.id AND status = 'maybe') as maybe
    FROM events e
    LEFT JOIN users u ON e.created_by = u.id
    ORDER BY e.event_date DESC
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
    <title>Manage Events - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>📆 Manage Events</h1>
        <button class="btn btn-primary" onclick="document.getElementById('createModal').style.display='flex'">+ Create Event</button>
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
                    <th>Location</th>
                    <th>RSVPs</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event): ?>
                <tr>
                    <td><?php echo date('M j, Y', strtotime($event['event_date'])); ?></td>
                    <td>
                        <?php echo htmlspecialchars($event['title']); ?>
                        <?php if ($event['is_mandatory']): ?><span style="color:var(--danger);font-size:11px;"> (Mandatory)</span><?php endif; ?>
                    </td>
                    <td><?php echo $event['start_time'] ? date('g:i A', strtotime($event['start_time'])) : '—'; ?></td>
                    <td><?php echo htmlspecialchars($event['location'] ?: '—'); ?></td>
                    <td><?php echo $event['attending']; ?> going, <?php echo $event['maybe']; ?> maybe</td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this event?');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
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
            <h3>Create Event</h3>
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
                <textarea name="description" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label>Date *</label>
                    <input type="date" name="event_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Start Time</label>
                    <input type="time" name="start_time" class="form-control">
                </div>
                <div class="form-group">
                    <label>End Time</label>
                    <input type="time" name="end_time" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label>Location</label>
                <input type="text" name="location" class="form-control" placeholder="e.g., Discord, In-Game, etc.">
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="is_mandatory"> Mandatory Event</label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('createModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Event</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
