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

// Get user's departments
$stmt = $conn->prepare("SELECT d.id, d.name FROM departments d JOIN roster r ON d.id = r.department_id WHERE r.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_depts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'start') {
        $log_type = trim($_POST['log_type'] ?? 'Patrol');
        $description = trim($_POST['description'] ?? '');
        $department_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
        
        $stmt = $conn->prepare("INSERT INTO patrol_logs (user_id, log_type, description, started_at, department_id) VALUES (?, ?, ?, NOW(), ?)");
        $stmt->bind_param("issi", $user_id, $log_type, $description, $department_id);
        if ($stmt->execute()) {
            $message = 'Activity started!';
            logAudit('patrol_start', 'patrol_log', $stmt->insert_id, "Started: $log_type");
        }
        $stmt->close();
    } elseif ($action === 'end') {
        $log_id = intval($_POST['log_id'] ?? 0);
        $stmt = $conn->prepare("UPDATE patrol_logs SET ended_at = NOW(), duration_minutes = TIMESTAMPDIFF(MINUTE, started_at, NOW()) WHERE id = ? AND user_id = ? AND ended_at IS NULL");
        $stmt->bind_param("ii", $log_id, $user_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $message = 'Activity ended!';
            logAudit('patrol_end', 'patrol_log', $log_id, 'Ended activity');
        }
        $stmt->close();
    } elseif ($action === 'manual') {
        $log_type = trim($_POST['log_type'] ?? 'Patrol');
        $description = trim($_POST['description'] ?? '');
        $department_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
        $started_at = $_POST['started_at'] ?? '';
        $ended_at = $_POST['ended_at'] ?? '';
        
        if ($started_at && $ended_at) {
            $stmt = $conn->prepare("INSERT INTO patrol_logs (user_id, log_type, description, started_at, ended_at, duration_minutes, department_id) VALUES (?, ?, ?, ?, ?, TIMESTAMPDIFF(MINUTE, ?, ?), ?)");
            $stmt->bind_param("issssssi", $user_id, $log_type, $description, $started_at, $ended_at, $started_at, $ended_at, $department_id);
            if ($stmt->execute()) {
                $message = 'Activity logged!';
                logAudit('patrol_manual', 'patrol_log', $stmt->insert_id, "Manual log: $log_type");
            }
            $stmt->close();
        } else {
            $error = 'Please provide start and end times.';
        }
    }
}

// Check for active log
$stmt = $conn->prepare("SELECT * FROM patrol_logs WHERE user_id = ? AND ended_at IS NULL ORDER BY started_at DESC LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$active_log = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get recent logs
$stmt = $conn->prepare("
    SELECT pl.*, d.name as department_name 
    FROM patrol_logs pl 
    LEFT JOIN departments d ON pl.department_id = d.id 
    WHERE pl.user_id = ? 
    ORDER BY pl.started_at DESC 
    LIMIT 50
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get stats for this week
$stmt = $conn->prepare("SELECT SUM(duration_minutes) as total_minutes, COUNT(*) as total_logs FROM patrol_logs WHERE user_id = ? AND started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND ended_at IS NOT NULL");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$conn->close();

$theme = getThemeColors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
    <style>
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-box { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px; text-align: center; }
        .stat-value { font-size: 28px; font-weight: 700; color: var(--text-primary); }
        .stat-label { font-size: 13px; color: var(--text-muted); margin-top: 4px; }
        .active-log { background: linear-gradient(135deg, rgba(16,185,129,0.2), rgba(16,185,129,0.1)); border: 1px solid var(--success); border-radius: var(--radius-md); padding: 24px; margin-bottom: 24px; }
        .active-log-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .active-indicator { display: flex; align-items: center; gap: 8px; color: var(--success); font-weight: 600; }
        .pulse { width: 10px; height: 10px; background: var(--success); border-radius: 50%; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .timer { font-size: 32px; font-weight: 700; color: var(--text-primary); font-family: monospace; }
        .log-form { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 24px; margin-bottom: 24px; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 16px; }
        .logs-table { width: 100%; border-collapse: collapse; }
        .logs-table th, .logs-table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border); }
        .logs-table th { color: var(--text-muted); font-weight: 500; font-size: 13px; }
        .log-type-badge { display: inline-block; padding: 4px 10px; background: var(--bg-card); border-radius: var(--radius-lg); font-size: 12px; }
        .duration { font-weight: 600; color: var(--text-primary); }
        .tabs { display: flex; gap: 8px; margin-bottom: 20px; }
        .tab { padding: 10px 20px; background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-sm); cursor: pointer; color: var(--text-secondary); }
        .tab.active { background: var(--accent); border-color: transparent; color: var(--text-primary); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        @media (max-width: 768px) {
            .logs-table { font-size: 13px; }
            .logs-table th, .logs-table td { padding: 10px 8px; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>📋 Activity Logs</h1>
    </div>
    
    <?php if ($message) showToast($message, 'success'); ?>
    <?php if ($error) showToast($error, 'error'); ?>
    
    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-value"><?php echo $stats['total_logs'] ?? 0; ?></div>
            <div class="stat-label">Activities This Week</div>
        </div>
        <div class="stat-box">
            <div class="stat-value"><?php echo $stats['total_minutes'] ? round($stats['total_minutes'] / 60, 1) : 0; ?>h</div>
            <div class="stat-label">Hours This Week</div>
        </div>
    </div>
    
    <?php if ($active_log): ?>
    <div class="active-log">
        <div class="active-log-header">
            <div class="active-indicator"><span class="pulse"></span> Activity In Progress</div>
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                <input type="hidden" name="action" value="end">
                <input type="hidden" name="log_id" value="<?php echo $active_log['id']; ?>">
                <button type="submit" class="btn btn-danger">End Activity</button>
            </form>
        </div>
        <div style="color: var(--text-secondary); margin-bottom: 8px;"><?php echo htmlspecialchars($active_log['log_type']); ?></div>
        <div class="timer" id="timer" data-start="<?php echo strtotime($active_log['started_at']); ?>">00:00:00</div>
        <?php if ($active_log['description']): ?>
            <div style="color: var(--text-muted); margin-top: 12px; font-size: 14px;"><?php echo htmlspecialchars($active_log['description']); ?></div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    
    <div class="tabs">
        <div class="tab active" onclick="showTab('quick')">Quick Start</div>
        <div class="tab" onclick="showTab('manual')">Manual Entry</div>
    </div>
    
    <div id="tab-quick" class="tab-content active">
        <form method="POST" class="log-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
            <input type="hidden" name="action" value="start">
            <div class="form-row">
                <div class="form-group">
                    <label>Activity Type</label>
                    <select name="log_type" class="form-control">
                        <option value="Patrol">Patrol</option>
                        <option value="Training">Training</option>
                        <option value="Meeting">Meeting</option>
                        <option value="Administrative">Administrative</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <?php if (!empty($user_depts)): ?>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" class="form-control">
                        <option value="">-- None --</option>
                        <?php foreach ($user_depts as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Notes (optional)</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Brief description of your activity..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary">▶️ Start Activity</button>
        </form>
    </div>
    
    <div id="tab-manual" class="tab-content">
        <form method="POST" class="log-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
            <input type="hidden" name="action" value="manual">
            <div class="form-row">
                <div class="form-group">
                    <label>Activity Type</label>
                    <select name="log_type" class="form-control">
                        <option value="Patrol">Patrol</option>
                        <option value="Training">Training</option>
                        <option value="Meeting">Meeting</option>
                        <option value="Administrative">Administrative</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <?php if (!empty($user_depts)): ?>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" class="form-control">
                        <option value="">-- None --</option>
                        <?php foreach ($user_depts as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Start Time</label>
                    <input type="datetime-local" name="started_at" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>End Time</label>
                    <input type="datetime-local" name="ended_at" class="form-control" required>
                </div>
            </div>
            <div class="form-group">
                <label>Notes (optional)</label>
                <textarea name="description" class="form-control" rows="2"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Save Entry</button>
        </form>
    </div>
    <?php endif; ?>
    
    <h2 style="margin-top: 30px; margin-bottom: 16px; font-size: 18px; color: var(--text-primary);">Recent Activity</h2>
    
    <?php if (empty($logs)): ?>
        <div class="empty-state"><p>No activity logs yet.</p></div>
    <?php else: ?>
    <div style="overflow-x: auto;">
    <table class="logs-table">
        <thead>
            <tr>
                <th>Type</th>
                <th>Date</th>
                <th>Time</th>
                <th>Duration</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><span class="log-type-badge"><?php echo htmlspecialchars($log['log_type']); ?></span></td>
                <td><?php echo date('M j, Y', strtotime($log['started_at'])); ?></td>
                <td><?php echo date('g:i A', strtotime($log['started_at'])); ?><?php echo $log['ended_at'] ? ' - ' . date('g:i A', strtotime($log['ended_at'])) : ' (active)'; ?></td>
                <td class="duration"><?php echo $log['duration_minutes'] ? floor($log['duration_minutes']/60) . 'h ' . ($log['duration_minutes']%60) . 'm' : '—'; ?></td>
                <td style="color: var(--text-muted); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($log['description'] ?: '—'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<script>
function showTab(tab) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelector('.tab[onclick*="'+tab+'"]').classList.add('active');
    document.getElementById('tab-'+tab).classList.add('active');
}

// Timer
const timerEl = document.getElementById('timer');
if (timerEl) {
    const startTime = parseInt(timerEl.dataset.start) * 1000;
    function updateTimer() {
        const elapsed = Date.now() - startTime;
        const hours = Math.floor(elapsed / 3600000);
        const mins = Math.floor((elapsed % 3600000) / 60000);
        const secs = Math.floor((elapsed % 60000) / 1000);
        timerEl.textContent = [hours, mins, secs].map(n => n.toString().padStart(2, '0')).join(':');
    }
    updateTimer();
    setInterval(updateTimer, 1000);
}
</script>
</body>
</html>
