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
    
    if ($action === 'create_badge') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? '🏅');
        $color = $_POST['color'] ?? '#fbbf24';
        $rarity = $_POST['rarity'] ?? 'common';
        
        if ($name) {
            $stmt = $conn->prepare("INSERT INTO badges (name, description, icon, color, rarity) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $description, $icon, $color, $rarity);
            if ($stmt->execute()) {
                $message = 'Badge created!';
                logAudit('badge_create', 'badge', $stmt->insert_id, "Created badge: $name");
            }
            $stmt->close();
        }
    } elseif ($action === 'award_badge') {
        $badge_id = intval($_POST['badge_id'] ?? 0);
        $target_user = intval($_POST['target_user'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        
        if ($badge_id && $target_user) {
            $stmt = $conn->prepare("INSERT IGNORE INTO user_badges (user_id, badge_id, awarded_by, reason) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiis", $target_user, $badge_id, $user_id, $reason);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $message = 'Badge awarded!';
                logAudit('badge_award', 'user_badge', $stmt->insert_id, "Awarded badge to user $target_user");
                createNotification($target_user, 'Badge Awarded!', 'You have been awarded a new badge!', 'success');
            } else {
                $error = 'User already has this badge.';
            }
            $stmt->close();
        }
    } elseif ($action === 'delete_badge') {
        $badge_id = intval($_POST['badge_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM badges WHERE id = ?");
        $stmt->bind_param("i", $badge_id);
        $stmt->execute();
        $stmt->close();
        $message = 'Badge deleted.';
    } elseif ($action === 'revoke') {
        $ub_id = intval($_POST['ub_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM user_badges WHERE id = ?");
        $stmt->bind_param("i", $ub_id);
        $stmt->execute();
        $stmt->close();
        $message = 'Badge revoked.';
    }
}

// Get badges
$badges = $conn->query("
    SELECT b.*, (SELECT COUNT(*) FROM user_badges WHERE badge_id = b.id) as award_count
    FROM badges b ORDER BY b.name
")->fetch_all(MYSQLI_ASSOC);

// Get users for awarding
$users = $conn->query("SELECT id, username FROM users WHERE is_approved = 1 ORDER BY username")->fetch_all(MYSQLI_ASSOC);

// Get recent awards
$recent_awards = $conn->query("
    SELECT ub.*, b.name as badge_name, b.icon, u.username, a.username as awarded_by_name
    FROM user_badges ub
    JOIN badges b ON ub.badge_id = b.id
    JOIN users u ON ub.user_id = u.id
    LEFT JOIN users a ON ub.awarded_by = a.id
    ORDER BY ub.awarded_at DESC LIMIT 20
")->fetch_all(MYSQLI_ASSOC);

$conn->close();

$rarity_colors = [
    'common' => '#9ca3af',
    'uncommon' => '#22c55e', 
    'rare' => '#3b82f6',
    'epic' => '#a855f7',
    'legendary' => '#f59e0b'
];

$theme = getThemeColors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Badges - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
    <style>
        .badges-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 16px; margin-bottom: 30px; }
        .badge-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px; text-align: center; }
        .badge-icon { font-size: 48px; margin-bottom: 12px; }
        .badge-name { font-size: 16px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px; }
        .badge-rarity { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
        .badge-count { font-size: 12px; color: var(--text-muted); }
        .badge-actions { margin-top: 12px; display: flex; gap: 8px; justify-content: center; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>🏅 Badges & Medals</h1>
        <div style="display:flex;gap:10px;">
            <button class="btn btn-secondary" onclick="document.getElementById('awardModal').style.display='flex'">Award Badge</button>
            <button class="btn btn-primary" onclick="document.getElementById('createModal').style.display='flex'">+ Create Badge</button>
        </div>
    </div>
    
    <?php if ($message) showToast($message, 'success'); ?>
    <?php if ($error) showToast($error, 'error'); ?>
    
    <h2 style="font-size:18px;color:#fff;margin-bottom:16px;">All Badges</h2>
    <div class="badges-grid">
        <?php foreach ($badges as $badge): ?>
        <div class="badge-card">
            <div class="badge-icon"><?php echo $badge['icon']; ?></div>
            <div class="badge-name"><?php echo htmlspecialchars($badge['name']); ?></div>
            <div class="badge-rarity" style="color:<?php echo $rarity_colors[$badge['rarity']] ?? '#9ca3af'; ?>">
                <?php echo ucfirst($badge['rarity']); ?>
            </div>
            <div class="badge-count"><?php echo $badge['award_count']; ?> awarded</div>
            <div class="badge-actions">
                <form method="POST" style="margin:0;" onsubmit="return confirm('Delete this badge?');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                    <input type="hidden" name="action" value="delete_badge">
                    <input type="hidden" name="badge_id" value="<?php echo $badge['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <h2 style="font-size:18px;color:#fff;margin-bottom:16px;">Recent Awards</h2>
    <div class="table-container">
        <table>
            <thead><tr><th>Badge</th><th>User</th><th>Awarded By</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($recent_awards as $award): ?>
                <tr>
                    <td><?php echo $award['icon']; ?> <?php echo htmlspecialchars($award['badge_name']); ?></td>
                    <td><?php echo htmlspecialchars($award['username']); ?></td>
                    <td><?php echo htmlspecialchars($award['awarded_by_name'] ?: 'System'); ?></td>
                    <td><?php echo date('M j, Y', strtotime($award['awarded_at'])); ?></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Revoke this badge?');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                            <input type="hidden" name="action" value="revoke">
                            <input type="hidden" name="ub_id" value="<?php echo $award['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Revoke</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Badge Modal -->
<div id="createModal" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header"><h3>Create Badge</h3><button class="modal-close" onclick="document.getElementById('createModal').style.display='none'">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
            <input type="hidden" name="action" value="create_badge">
            <div class="form-row" style="display:grid;grid-template-columns:1fr 80px;gap:12px;">
                <div class="form-group"><label>Name *</label><input type="text" name="name" class="form-control" required></div>
                <div class="form-group"><label>Icon</label><input type="text" name="icon" class="form-control" value="🏅" style="text-align:center;font-size:24px;"></div>
            </div>
            <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
            <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group"><label>Color</label><input type="color" name="color" value="#fbbf24" class="form-control"></div>
                <div class="form-group"><label>Rarity</label>
                    <select name="rarity" class="form-control">
                        <option value="common">Common</option>
                        <option value="uncommon">Uncommon</option>
                        <option value="rare">Rare</option>
                        <option value="epic">Epic</option>
                        <option value="legendary">Legendary</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="document.getElementById('createModal').style.display='none'">Cancel</button><button type="submit" class="btn btn-primary">Create</button></div>
        </form>
    </div>
</div>

<!-- Award Badge Modal -->
<div id="awardModal" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header"><h3>Award Badge</h3><button class="modal-close" onclick="document.getElementById('awardModal').style.display='none'">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
            <input type="hidden" name="action" value="award_badge">
            <div class="form-group"><label>Badge *</label>
                <select name="badge_id" class="form-control" required>
                    <option value="">Select badge...</option>
                    <?php foreach ($badges as $b): ?><option value="<?php echo $b['id']; ?>"><?php echo $b['icon']; ?> <?php echo htmlspecialchars($b['name']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>User *</label>
                <select name="target_user" class="form-control" required>
                    <option value="">Select user...</option>
                    <?php foreach ($users as $u): ?><option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Reason</label><textarea name="reason" class="form-control" rows="2" placeholder="Why is this badge being awarded?"></textarea></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="document.getElementById('awardModal').style.display='none'">Cancel</button><button type="submit" class="btn btn-primary">Award</button></div>
        </form>
    </div>
</div>

</body>
</html>
