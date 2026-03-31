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
requireLogin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Mark as read
if (isset($_GET['read'])) {
    $ann_id = intval($_GET['read']);
    $stmt = $conn->prepare("INSERT IGNORE INTO announcement_reads (announcement_id, user_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $ann_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

// Get user's departments
$depts = [];
$stmt = $conn->prepare("SELECT department_id FROM roster WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$deptResult = $stmt->get_result();
while ($row = $deptResult->fetch_assoc()) {
    $depts[] = $row['department_id'];
}
$deptList = empty($depts) ? '0' : implode(',', $depts);
$is_admin = isAdmin() ? 1 : 0;

// Get announcements
$announcements = $conn->query("
    SELECT a.*, u.username as author_name, d.name as dept_name,
           (SELECT COUNT(*) FROM announcement_reads WHERE announcement_id = a.id AND user_id = $user_id) as is_read
    FROM announcements a
    JOIN users u ON a.author_id = u.id
    LEFT JOIN departments d ON a.target_department_id = d.id
    WHERE a.is_active = TRUE 
    AND (a.starts_at IS NULL OR a.starts_at <= NOW())
    AND (a.expires_at IS NULL OR a.expires_at > NOW())
    AND (
        a.target_type = 'all' 
        OR (a.target_type = 'department' AND a.target_department_id IN ($deptList))
        OR (a.target_type = 'admins' AND $is_admin = 1)
    )
    ORDER BY a.is_pinned DESC, a.created_at DESC
");

$current_page = 'announcements';
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
        .container { max-width: 900px; }
        
        .announcement {
            background: var(--bg-card);
            border: 1px solid var(--bg-elevated);
            border-radius: var(--radius-lg);
            padding: 28px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .announcement.pinned { border-color: rgba(251, 191, 36, 0.3); background: rgba(251, 191, 36, 0.05); }
        .announcement.unread { border-left: 4px solid var(--accent); }
        .announcement.urgent { border-color: rgba(239, 68, 68, 0.3); background: rgba(239, 68, 68, 0.05); }
        
        .announcement-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
        .announcement-title { font-size: 20px; font-weight: 700; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        
        .announcement-meta { font-size: 13px; color: var(--text-muted); margin-bottom: 16px; }
        .announcement-content { line-height: 1.7; color: var(--bg-elevated); white-space: pre-wrap; }
        
        .badge { padding: 4px 12px; border-radius: var(--radius-lg); font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-info { background: rgba(59, 130, 246, 0.2); color: #93c5fd; }
        .badge-warning { background: rgba(251, 191, 36, 0.2); color: #f0b232; }
        .badge-urgent { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .badge-maintenance { background: rgba(168, 85, 247, 0.2); color: #d8b4fe; }
        .badge-unread { background: var(--accent-muted); color: #93c5fd; }
        
        .mark-read { font-size: 13px; color: var(--accent); text-decoration: none; }
        .mark-read:hover { text-decoration: underline; }
        
        .empty-state { text-align: center; padding: 80px; color: var(--text-muted); }
        .empty-state h3 { font-size: 24px; margin-bottom: 12px; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container">
        <?php if ($announcements->num_rows > 0): ?>
            <?php while ($ann = $announcements->fetch_assoc()): ?>
                <div class="announcement <?php echo $ann['is_pinned'] ? 'pinned' : ''; ?> <?php echo !$ann['is_read'] ? 'unread' : ''; ?> <?php echo $ann['type'] === 'urgent' ? 'urgent' : ''; ?>">
                    <div class="announcement-header">
                        <div class="announcement-title">
                            <?php if ($ann['is_pinned']): ?>📌<?php endif; ?>
                            <?php echo htmlspecialchars($ann['title']); ?>
                            <span class="badge badge-<?php echo $ann['type']; ?>"><?php echo strtoupper($ann['type']); ?></span>
                            <?php if (!$ann['is_read']): ?><span class="badge badge-unread">NEW</span><?php endif; ?>
                        </div>
                        <?php if (!$ann['is_read']): ?>
                            <a href="?read=<?php echo $ann['id']; ?>" class="mark-read">Mark as read</a>
                        <?php endif; ?>
                    </div>
                    <div class="announcement-meta">
                        Posted by <?php echo htmlspecialchars($ann['author_name']); ?> • 
                        <?php echo date('F j, Y \a\t g:i A', strtotime($ann['created_at'])); ?>
                        <?php if ($ann['dept_name']): ?> • For: <?php echo htmlspecialchars($ann['dept_name']); ?><?php endif; ?>
                    </div>
                    <div class="announcement-content"><?php echo nl2br(htmlspecialchars($ann['content'])); ?></div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <h3>No Announcements</h3>
                <p>There are no announcements at this time.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php $conn->close(); ?>
