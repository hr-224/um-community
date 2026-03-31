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
if (!defined('UM_EMAIL_LOADED')) { require_once __DIR__ . '/../includes/email.php'; }
require_once '../includes/permissions_ui.php';
requireLogin();

// Check permission - same as conduct since awards are part of conduct records
if (!isAdmin() && !hasAnyPermission(['conduct.view', 'conduct.manage'])) {
    header('Location: /index?error=unauthorized');
    exit();
}

$conn = getDBConnection();
$message = '';
$error = '';
$is_admin = isAdmin();
$can_manage = $is_admin || hasPermission('conduct.manage');

// Create award
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['create_award']) && $can_manage) {
    try {
        $user_id = intval($_POST['user_id']);
        $award_type = $_POST['award_type'];
        $custom_name = trim($_POST['custom_award_name'] ?? '');
        $description = trim($_POST['description']);
        $month = intval($_POST['month']);
        $year = intval($_POST['year']);
        $dept_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
        
        $stmt = $conn->prepare("INSERT INTO recognition_awards (user_id, award_type, custom_award_name, description, month, year, department_id, awarded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssiiii", $user_id, $award_type, $custom_name, $description, $month, $year, $dept_id, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $insert_id = $stmt->insert_id;
            $stmt->close();
            
            // Notify user - wrapped in try/catch
            try {
                $award_names = ['motm' => 'Member of the Month', 'excellence' => 'Excellence Award', 'dedication' => 'Dedication Award', 'teamwork' => 'Teamwork Award', 'custom' => $custom_name];
                createNotification($user_id, 'Congratulations! 🏆', "You have received the {$award_names[$award_type]} award!", 'success');
            } catch (Exception $e) {
                // Notification failed but award was created
            }
            
            // Log audit
            logAudit('create_award', 'award', $insert_id, "Created award for user $user_id");
            
            $message = 'Award created successfully!';
        } else {
            $error = 'Failed to create award.';
            $stmt->close();
        }
    } catch (Exception $e) {
        $error = 'An error occurred while creating the award. Please try again.';
    }
}

// Delete award
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['delete_award']) && $can_manage) {
    $id = intval($_POST['award_id']);
    $stmt = $conn->prepare("DELETE FROM recognition_awards WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $message = 'Award deleted!';
}

// Handle nomination
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['review_nomination']) && $can_manage) {
    $nom_id = intval($_POST['nomination_id']);
    $action = $_POST['action'];
    if (in_array($action, ['approved', 'denied'])) {
        $stmt = $conn->prepare("UPDATE recognition_nominations SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $action, $nom_id);
        $stmt->execute();
        $stmt->close();
    }
    $message = 'Nomination ' . $action . '!';
}

// Get awards
$awards = $conn->query("
    SELECT a.*, u.username, d.name as dept_name, au.username as awarded_by_name
    FROM recognition_awards a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN departments d ON a.department_id = d.id
    JOIN users au ON a.awarded_by = au.id
    WHERE a.is_active = TRUE
    ORDER BY a.year DESC, a.month DESC, a.created_at DESC
    LIMIT 50
");

// Get pending nominations
$nominations_result = @$conn->query("
    SELECT n.*, u.username as nominee_name, nom.username as nominator_name, d.name as dept_name
    FROM recognition_nominations n
    JOIN users u ON n.nominee_id = u.id
    JOIN users nom ON n.nominator_id = nom.id
    LEFT JOIN departments d ON n.department_id = d.id
    WHERE n.status = 'pending'
    ORDER BY n.created_at DESC
");

$users = $conn->query("SELECT id, username FROM users WHERE is_approved = TRUE ORDER BY username");
$departments = $conn->query("SELECT id, name FROM departments ORDER BY name");

$current_month = date('n');
$current_year = date('Y');

$current_page = 'admin_recognition';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recognition & Awards - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <?php include '../includes/styles.php'; ?>
    <style>
        .grid { display: grid; grid-template-columns: 380px 1fr; gap: 24px; }
        @media (max-width: 1024px) { .grid { grid-template-columns: 1fr; } }
        
        .award-card {
            background: linear-gradient(135deg, rgba(251, 191, 36, 0.1), rgba(245, 158, 11, 0.05));
            border: 1px solid rgba(251, 191, 36, 0.2);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 12px;
            position: relative;
            overflow: hidden;
        }
        
        .award-card::before {
            content: '🏆';
            position: absolute;
            top: -10px;
            right: -10px;
            font-size: 60px;
            opacity: 0.1;
            pointer-events: none;
            z-index: 0;
        }
        
        .award-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }
        
        .award-header form {
            position: relative;
            z-index: 2;
        }
        
        .award-recipient {
            font-weight: 700;
            font-size: 18px;
            color: #f0b232;
        }
        
        .award-type {
            font-size: 14px;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .award-meta {
            font-size: 12px;
            color: var(--text-muted);
        }
        
        .award-desc {
            font-size: 14px;
            color: var(--text-secondary);
            margin-top: 8px;
        }
        
        .nomination-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 20px;
            margin-bottom: 12px;
        }
        
        .nomination-header { margin-bottom: 8px; }
        .nomination-name { font-weight: 700; font-size: 16px; }
        .nomination-meta { font-size: 12px; color: var(--text-muted); }
        
        .nomination-reason {
            font-size: 14px;
            color: var(--text-secondary);
            margin: 12px 0;
            padding: 12px;
            background: var(--bg-elevated);
            border-radius: var(--radius-sm);
        }
        
        .nomination-actions {
            display: flex;
            gap: 8px;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container">
        <?php if ($message) showToast($message, 'success'); ?>
        
        <?php if ($error) showToast($error, 'error'); ?>

        <div class="grid">
            <div>
                <div class="section <?php echo !$can_manage ? 'permission-locked' : ''; ?>">
                    <h2>🏆 Give Award</h2>
                    <form method="POST">
                    <?php echo csrfField(); ?>
                        <div class="form-group">
                            <label>Recipient *</label>
                            <select name="user_id" required>
                                <option value="">Select Member</option>
                                <?php while ($user = $users->fetch_assoc()): ?>
                                    <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Award Type *</label>
                            <select name="award_type" required onchange="toggleCustomName(this.value)">
                                <option value="motm">🌟 Member of the Month</option>
                                <option value="excellence">⭐ Excellence Award</option>
                                <option value="dedication">💪 Dedication Award</option>
                                <option value="teamwork">🤝 Teamwork Award</option>
                                <option value="custom">✨ Custom Award</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="custom_name_group" style="display: none;">
                            <label>Custom Award Name</label>
                            <input type="text" name="custom_award_name" placeholder="Award name">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Month</label>
                                <select name="month">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?php echo $m; ?>" <?php echo $m == $current_month ? 'selected' : ''; ?>>
                                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Year</label>
                                <select name="year">
                                    <?php for ($y = $current_year; $y >= $current_year - 2; $y--): ?>
                                        <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Department (optional)</label>
                            <select name="department_id">
                                <option value="">Community-wide</option>
                                <?php while ($dept = $departments->fetch_assoc()): ?>
                                    <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Description / Reason *</label>
                            <textarea name="description" required placeholder="Why is this person being recognized?"></textarea>
                        </div>
                        
                        <button type="submit" name="create_award" class="btn btn-primary btn-block">🏆 Give Award</button>
                    </form>
                    <?php if (!$can_manage): ?>
                    <?php permissionLockOverlay('You need the "Manage Conduct Records" permission to give awards.'); ?>
                    <?php endif; ?>
                </div>

                <?php if ($nominations_result && $nominations_result->num_rows > 0): ?>
                <div class="section">
                    <h2>Pending Nominations</h2>
                    <?php while ($nom = $nominations_result->fetch_assoc()): ?>
                        <div class="nomination-card">
                            <div class="nomination-header">
                                <div class="nomination-name"><?php echo htmlspecialchars($nom['nominee_name']); ?></div>
                                <span class="badge badge-warning"><?php echo strtoupper($nom['award_type']); ?></span>
                            </div>
                            <div class="nomination-meta">
                                Nominated by <?php echo htmlspecialchars($nom['nominator_name']); ?> • 
                                <?php echo date('F Y', mktime(0, 0, 0, $nom['month'], 1, $nom['year'])); ?>
                            </div>
                            <div class="nomination-reason"><?php echo nl2br(htmlspecialchars($nom['reason'])); ?></div>
                            <div class="nomination-actions">
                                <form method="POST" style="display: inline;">
                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="nomination_id" value="<?php echo $nom['id']; ?>">
                                    <input type="hidden" name="action" value="approved">
                                    <button type="submit" name="review_nomination" class="btn btn-sm btn-success">Approve</button>
                                </form>
                                <form method="POST" style="display: inline;">
                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="nomination_id" value="<?php echo $nom['id']; ?>">
                                    <input type="hidden" name="action" value="denied">
                                    <button type="submit" name="review_nomination" class="btn btn-sm btn-danger">Deny</button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="section">
                <h2>Recent Awards</h2>
                <?php if ($awards && $awards->num_rows > 0): ?>
                    <?php while ($award = $awards->fetch_assoc()): ?>
                        <div class="award-card">
                            <div class="award-header">
                                <div>
                                    <div class="award-recipient"><?php echo htmlspecialchars($award['username']); ?></div>
                                    <div class="award-type">
                                        <?php
                                        $types = ['motm' => '🌟 Member of the Month', 'excellence' => '⭐ Excellence Award', 'dedication' => '💪 Dedication Award', 'teamwork' => '🤝 Teamwork Award'];
                                        echo $award['award_type'] === 'custom' ? '✨ ' . htmlspecialchars($award['custom_award_name']) : ($types[$award['award_type']] ?? $award['award_type']);
                                        ?>
                                    </div>
                                    <div class="award-meta">
                                        <?php echo date('F Y', mktime(0, 0, 0, $award['month'], 1, $award['year'])); ?> • 
                                        <?php echo $award['dept_name'] ? htmlspecialchars($award['dept_name']) : 'Community-wide'; ?> •
                                        Awarded by <?php echo htmlspecialchars($award['awarded_by_name']); ?>
                                    </div>
                                </div>
                                <?php if ($can_manage): ?>
                                <form method="POST" onsubmit="return confirm('Delete this award?')">
                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="award_id" value="<?php echo $award['id']; ?>">
                                    <button type="submit" name="delete_award" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                                <?php else: ?>
                                <?php lockedButton('Delete', 'Manage permission required'); ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($award['description']): ?>
                                <div class="award-desc"><?php echo nl2br(htmlspecialchars($award['description'])); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">No awards yet</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleCustomName(value) {
            document.getElementById('custom_name_group').style.display = value === 'custom' ? 'block' : 'none';
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>
