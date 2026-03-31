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

// Handle RSVP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken()) {
    $event_id = intval($_POST['event_id'] ?? 0);
    $status = $_POST['status'] ?? 'attending';
    
    if (in_array($status, ['attending', 'maybe', 'not_attending'])) {
        $stmt = $conn->prepare("INSERT INTO event_rsvps (event_id, user_id, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = ?, responded_at = NOW()");
        $stmt->bind_param("iiss", $event_id, $user_id, $status, $status);
        if ($stmt->execute()) {
            $message = 'RSVP updated!';
        }
        $stmt->close();
    }
}

// Get upcoming events
$stmt = $conn->prepare("
    SELECT e.*, u.username as created_by_name,
           (SELECT COUNT(*) FROM event_rsvps WHERE event_id = e.id AND status = 'attending') as attending_count,
           (SELECT COUNT(*) FROM event_rsvps WHERE event_id = e.id AND status = 'maybe') as maybe_count,
           (SELECT status FROM event_rsvps WHERE event_id = e.id AND user_id = ?) as user_rsvp
    FROM events e
    LEFT JOIN users u ON e.created_by = u.id
    WHERE e.event_date >= CURDATE()
    ORDER BY e.event_date ASC, e.start_time ASC
    LIMIT 50
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

$theme = getThemeColors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
    <style>
        .events-list { display: flex; flex-direction: column; gap: 20px; }
        .event-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 24px; display: grid; grid-template-columns: auto 1fr auto; gap: 24px; align-items: start; }
        .event-date-box { background: var(--accent); border-radius: var(--radius-md); padding: 16px 20px; text-align: center; min-width: 80px; }
        .event-date-month { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; }
        .event-date-day { font-size: 32px; font-weight: 700; line-height: 1; }
        .event-date-weekday { font-size: 11px; opacity: 0.8; margin-top: 4px; }
        .event-content { flex: 1; }
        .event-title { font-size: 20px; font-weight: 600; color: var(--text-primary); margin-bottom: 8px; display: flex; align-items: center; gap: 10px; }
        .event-mandatory { background: var(--danger); color: var(--text-primary); font-size: 10px; padding: 3px 8px; border-radius: var(--radius-lg); font-weight: 600; }
        .event-meta { display: flex; flex-wrap: wrap; gap: 16px; color: var(--text-muted); font-size: 14px; margin-bottom: 12px; }
        .event-description { color: var(--text-secondary); line-height: 1.6; }
        .event-rsvp { display: flex; flex-direction: column; gap: 10px; min-width: 140px; }
        .rsvp-stats { font-size: 13px; color: var(--text-muted); margin-bottom: 8px; }
        .rsvp-buttons { display: flex; flex-direction: column; gap: 6px; }
        .rsvp-btn { padding: 8px 16px; border: none; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.2s; background: var(--bg-elevated); color: var(--text-secondary); }
        .rsvp-btn:hover { background: var(--bg-hover); }
        .rsvp-btn.active-attending { background: var(--success); color: var(--text-primary); }
        .rsvp-btn.active-maybe { background: #f59e0b; color: var(--text-primary); }
        .rsvp-btn.active-not_attending { background: var(--danger); color: var(--text-primary); }
        @media (max-width: 768px) {
            .event-card { grid-template-columns: 1fr; }
            .event-date-box { justify-self: start; display: flex; gap: 12px; align-items: center; padding: 12px 16px; }
            .event-date-day { font-size: 24px; }
            .event-rsvp { flex-direction: row; flex-wrap: wrap; }
            .rsvp-buttons { flex-direction: row; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>📆 Community Events</h1>
    </div>
    
    <?php if ($message) showToast($message, 'success'); ?>
    
    <?php if (empty($events)): ?>
        <div class="empty-state">
            <p>No upcoming events scheduled.</p>
        </div>
    <?php else: ?>
    <div class="events-list">
        <?php foreach ($events as $event): ?>
        <div class="event-card">
            <div class="event-date-box">
                <div class="event-date-month"><?php echo date('M', strtotime($event['event_date'])); ?></div>
                <div class="event-date-day"><?php echo date('j', strtotime($event['event_date'])); ?></div>
                <div class="event-date-weekday"><?php echo date('D', strtotime($event['event_date'])); ?></div>
            </div>
            
            <div class="event-content">
                <div class="event-title">
                    <?php echo htmlspecialchars($event['title']); ?>
                    <?php if ($event['is_mandatory']): ?>
                        <span class="event-mandatory">MANDATORY</span>
                    <?php endif; ?>
                </div>
                <div class="event-meta">
                    <?php if ($event['start_time']): ?>
                        <span>🕐 <?php echo date('g:i A', strtotime($event['start_time'])); ?><?php echo $event['end_time'] ? ' - ' . date('g:i A', strtotime($event['end_time'])) : ''; ?></span>
                    <?php endif; ?>
                    <?php if ($event['location']): ?>
                        <span>📍 <?php echo htmlspecialchars($event['location']); ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($event['description']): ?>
                    <div class="event-description"><?php echo nl2br(htmlspecialchars($event['description'])); ?></div>
                <?php endif; ?>
            </div>
            
            <div class="event-rsvp">
                <div class="rsvp-stats">
                    ✓ <?php echo $event['attending_count']; ?> attending
                    <?php if ($event['maybe_count'] > 0): ?> • <?php echo $event['maybe_count']; ?> maybe<?php endif; ?>
                </div>
                <form method="POST" class="rsvp-buttons">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                    <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                    <button type="submit" name="status" value="attending" class="rsvp-btn <?php echo $event['user_rsvp'] === 'attending' ? 'active-attending' : ''; ?>">Going</button>
                    <button type="submit" name="status" value="maybe" class="rsvp-btn <?php echo $event['user_rsvp'] === 'maybe' ? 'active-maybe' : ''; ?>">Maybe</button>
                    <button type="submit" name="status" value="not_attending" class="rsvp-btn <?php echo $event['user_rsvp'] === 'not_attending' ? 'active-not_attending' : ''; ?>">Can't Go</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
