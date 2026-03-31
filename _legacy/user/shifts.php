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

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle shift signup/cancel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken()) {
    $shift_id = intval($_POST['shift_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($action === 'signup') {
        // Check if shift exists and has slots
        $stmt = $conn->prepare("SELECT s.*, (SELECT COUNT(*) FROM shift_signups WHERE shift_id = s.id) as current_signups FROM shifts s WHERE s.id = ?");
        $stmt->bind_param("i", $shift_id);
        $stmt->execute();
        $shift = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($shift && ($shift['max_slots'] == 0 || $shift['current_signups'] < $shift['max_slots'])) {
            $stmt = $conn->prepare("INSERT IGNORE INTO shift_signups (shift_id, user_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $shift_id, $user_id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $message = 'Successfully signed up for shift!';
                logAudit('shift_signup', 'shift', $shift_id, 'Signed up for shift: ' . $shift['title']);
            }
            $stmt->close();
        } else {
            $error = 'This shift is full or no longer available.';
        }
    } elseif ($action === 'cancel') {
        $stmt = $conn->prepare("DELETE FROM shift_signups WHERE shift_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $shift_id, $user_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $message = 'Shift signup cancelled.';
            logAudit('shift_cancel', 'shift', $shift_id, 'Cancelled shift signup');
        }
        $stmt->close();
    }
}

// Get upcoming shifts
$stmt = $conn->prepare("
    SELECT s.*, d.name as department_name, u.username as created_by_name,
           (SELECT COUNT(*) FROM shift_signups WHERE shift_id = s.id) as signup_count,
           (SELECT COUNT(*) FROM shift_signups WHERE shift_id = s.id AND user_id = ?) as user_signed_up
    FROM shifts s
    LEFT JOIN departments d ON s.department_id = d.id
    LEFT JOIN users u ON s.created_by = u.id
    WHERE s.shift_date >= CURDATE()
    ORDER BY s.shift_date ASC, s.start_time ASC
    LIMIT 50
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$shifts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get user's signed up shifts
$stmt = $conn->prepare("
    SELECT s.*, d.name as department_name
    FROM shifts s
    JOIN shift_signups ss ON s.id = ss.shift_id
    LEFT JOIN departments d ON s.department_id = d.id
    WHERE ss.user_id = ? AND s.shift_date >= CURDATE()
    ORDER BY s.shift_date ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$my_shifts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

$theme = getThemeColors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shift Calendar - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
    <style>
        .shifts-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
        .shift-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px; }
        .shift-date { font-size: 13px; color: var(--text-muted); margin-bottom: 8px; }
        .shift-title { font-size: 18px; font-weight: 600; color: var(--text-primary); margin-bottom: 8px; }
        .shift-time { display: flex; align-items: center; gap: 8px; color: var(--text-secondary); margin-bottom: 12px; }
        .shift-department { display: inline-block; padding: 4px 10px; background: var(--accent); color: var(--text-primary); border-radius: var(--radius-lg); font-size: 12px; margin-bottom: 12px; }
        .shift-slots { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
        .slots-bar { flex: 1; height: 8px; background: var(--bg-elevated); border-radius: 4px; overflow: hidden; }
        .slots-fill { height: 100%; background: var(--accent); border-radius: 4px; transition: width 0.3s; }
        .slots-text { font-size: 12px; color: var(--text-muted); white-space: nowrap; }
        .shift-actions { display: flex; gap: 10px; }
        .btn-signup { flex: 1; padding: 10px; border: none; border-radius: var(--radius-sm); font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-signup.available { background: var(--accent); color: var(--text-primary); }
        .btn-signup.signed-up { background: var(--success); color: var(--text-primary); }
        .btn-signup.full { background: var(--bg-elevated); color: var(--text-muted); cursor: not-allowed; }
        .btn-cancel { padding: 10px 16px; background: rgba(239,68,68,0.2); color: var(--danger); border: none; border-radius: var(--radius-sm); cursor: pointer; }
        .my-shifts { margin-bottom: 30px; }
        .my-shift-item { display: flex; justify-content: space-between; align-items: center; padding: 16px; background: var(--bg-card); border-radius: var(--radius-md); margin-bottom: 10px; }
        .section-header { font-size: 20px; font-weight: 600; color: var(--text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        @media (max-width: 768px) {
            .shifts-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>📅 Shift Calendar</h1>
    </div>
    
    <?php if ($message) showToast($message, 'success'); ?>
    <?php if ($error) showToast($error, 'error'); ?>
    
    <?php if (!empty($my_shifts)): ?>
    <div class="my-shifts">
        <div class="section-header">✅ My Upcoming Shifts</div>
        <?php foreach ($my_shifts as $shift): ?>
        <div class="my-shift-item">
            <div>
                <strong><?php echo htmlspecialchars($shift['title']); ?></strong>
                <div style="font-size: 13px; color: var(--text-muted);">
                    <?php echo date('D, M j', strtotime($shift['shift_date'])); ?> • 
                    <?php echo date('g:i A', strtotime($shift['start_time'])); ?> - <?php echo date('g:i A', strtotime($shift['end_time'])); ?>
                </div>
            </div>
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                <input type="hidden" name="shift_id" value="<?php echo $shift['id']; ?>">
                <input type="hidden" name="action" value="cancel">
                <button type="submit" class="btn-cancel" onclick="return confirm('Cancel this shift signup?');">Cancel</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <div class="section-header">📋 Available Shifts</div>
    
    <?php if (empty($shifts)): ?>
        <div class="empty-state">
            <p>No upcoming shifts scheduled.</p>
        </div>
    <?php else: ?>
    <div class="shifts-grid">
        <?php foreach ($shifts as $shift): 
            $slots_percent = $shift['max_slots'] > 0 ? min(100, ($shift['signup_count'] / $shift['max_slots']) * 100) : 0;
            $is_full = $shift['max_slots'] > 0 && $shift['signup_count'] >= $shift['max_slots'];
            $is_signed_up = $shift['user_signed_up'] > 0;
        ?>
        <div class="shift-card">
            <div class="shift-date"><?php echo date('l, F j, Y', strtotime($shift['shift_date'])); ?></div>
            <div class="shift-title"><?php echo htmlspecialchars($shift['title']); ?></div>
            <div class="shift-time">
                🕐 <?php echo date('g:i A', strtotime($shift['start_time'])); ?> - <?php echo date('g:i A', strtotime($shift['end_time'])); ?>
            </div>
            <?php if ($shift['department_name']): ?>
                <div class="shift-department"><?php echo htmlspecialchars($shift['department_name']); ?></div>
            <?php endif; ?>
            <?php if ($shift['description']): ?>
                <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 12px;"><?php echo htmlspecialchars($shift['description']); ?></p>
            <?php endif; ?>
            
            <div class="shift-slots">
                <div class="slots-bar">
                    <div class="slots-fill" style="width: <?php echo $slots_percent; ?>%"></div>
                </div>
                <span class="slots-text">
                    <?php echo $shift['signup_count']; ?><?php echo $shift['max_slots'] > 0 ? '/' . $shift['max_slots'] : ''; ?> signed up
                </span>
            </div>
            
            <div class="shift-actions">
                <form method="POST" style="flex: 1; margin: 0;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                    <input type="hidden" name="shift_id" value="<?php echo $shift['id']; ?>">
                    <?php if ($is_signed_up): ?>
                        <input type="hidden" name="action" value="cancel">
                        <button type="submit" class="btn-signup signed-up">✓ Signed Up</button>
                    <?php elseif ($is_full): ?>
                        <button type="button" class="btn-signup full" disabled>Shift Full</button>
                    <?php else: ?>
                        <input type="hidden" name="action" value="signup">
                        <button type="submit" class="btn-signup available">Sign Up</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
