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

// Mark notification as read
if (isset($_GET['mark_read'])) {
    $notif_id = intval($_GET['mark_read']);
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notif_id, $user_id);
    $stmt->execute();
    $stmt->close();
    header('Location: notifications');
    exit();
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    header('Location: notifications');
    exit();
}

// Get notifications
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <?php include '../includes/styles.php'; ?>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .container {
            max-width: 1000px;
        }
        
        .section {
            animation: fadeIn 0.6s ease;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            padding-bottom: 16px;
            border-bottom: none;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .section-header h2 {
            color: var(--text-primary);
            font-size: 24px;
            font-weight: 700;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            background: var(--accent);
            color: white;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 4px 15px var(--shadow-color, rgba(88, 101, 242, 0.4));
        }
        
        .btn:hover { 
            transform: translateY(-2px);
            box-shadow: 0 8px 25px var(--shadow-color, rgba(88, 101, 242, 0.6));
        }
        
        .notif-item {
            padding: 24px;
            border-bottom: 1px solid var(--bg-card);
            display: flex;
            gap: 20px;
            transition: all 0.3s ease;
            border-radius: var(--radius-md);
            margin-bottom: 8px;
            animation: slideIn 0.4s ease;
            animation-fill-mode: both;
        }
        
        .notif-item:nth-child(1) { animation-delay: 0.1s; }
        .notif-item:nth-child(2) { animation-delay: 0.15s; }
        .notif-item:nth-child(3) { animation-delay: 0.2s; }
        .notif-item:nth-child(4) { animation-delay: 0.25s; }
        .notif-item:nth-child(5) { animation-delay: 0.3s; }
        
        .notif-item:hover { 
            background: var(--bg-card);
            transform: translateX(4px);
        }
        
        .notif-item.unread {
            background: var(--accent-muted);
            border-left: 4px solid var(--accent);
            box-shadow: 0 4px 20px rgba(88, 101, 242, 0.2);
        }
        
        .notif-item.unread .notif-icon {
            animation: pulse 2s infinite;
        }
        
        .notif-icon {
            font-size: 32px;
            flex-shrink: 0;
            filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.3));
        }
        
        .notif-content {
            flex: 1;
        }
        
        .notif-title {
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
            font-size: 16px;
        }
        
        .notif-message {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 8px;
            line-height: 1.5;
        }
        
        .notif-time {
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 500;
        }
        
        .notif-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .mark-read {
            color: var(--accent);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            background: var(--accent-muted);
            border: 1px solid rgba(88, 101, 242, 0.3);
            transition: all 0.3s ease;
        }
        
        .mark-read:hover {
            background: var(--accent-muted);
            transform: translateY(-1px);
        }
        
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--text-muted);
        }
        
        .empty-state h3 {
            font-size: 24px;
            margin-bottom: 12px;
            color: var(--text-secondary);
        }
        
        .empty-state p {
            font-size: 16px;
        }
        
        @media (max-width: 768px) {
            .container { padding: 0 16px; }
            .notif-item { flex-direction: column; }
            .section { padding: 24px; }
            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <?php $current_page = 'notifications'; include '../includes/navbar.php'; ?>

    <div class="container">
        <div class="section">
            <div class="section-header">
                <h2>Your Notifications</h2>
                <a href="?mark_all_read" class="btn">Mark All as Read</a>
            </div>

            <?php if ($notifications->num_rows > 0): ?>
                <?php while ($notif = $notifications->fetch_assoc()): ?>
                    <div class="notif-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                        <div class="notif-icon">
                            <?php
                            $icons = [
                                'success' => '✅',
                                'warning' => '⚠️',
                                'error' => '❌',
                                'info' => 'ℹ️'
                            ];
                            echo $icons[$notif['type']] ?? '📬';
                            ?>
                        </div>
                        <div class="notif-content">
                            <div class="notif-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                            <div class="notif-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                            <div class="notif-time"><?php echo date('M j, Y g:i A', strtotime($notif['created_at'])); ?></div>
                        </div>
                        <?php if (!$notif['is_read']): ?>
                            <div class="notif-actions">
                                <a href="?mark_read=<?php echo $notif['id']; ?>" class="mark-read">Mark as read</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <h3>No notifications yet</h3>
                    <p>You're all caught up!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>