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
requireLogin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle AJAX status save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_status'])) {
    header('Content-Type: application/json');
    
    // Verify CSRF token
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
    
    $status = $_POST['status'] ?? 'online';
    $custom_status = trim($_POST['custom_status'] ?? '');
    
    // Validate status value
    $valid_statuses = ['online', 'away', 'busy', 'offline'];
    if (!in_array($status, $valid_statuses)) {
        $status = 'online';
    }
    
    try {
        $stmt = $conn->prepare("INSERT INTO user_status (user_id, status, custom_status, last_activity, last_seen) 
                               VALUES (?, ?, ?, NOW(), NOW()) 
                               ON DUPLICATE KEY UPDATE status = ?, custom_status = ?, last_activity = NOW()");
        $stmt->bind_param("issss", $user_id, $status, $custom_status, $status, $custom_status);
        $result = $stmt->execute();
        $stmt->close();
        
        echo json_encode(['success' => $result, 'status' => $status, 'custom_status' => $custom_status]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    
    $conn->close();
    exit;
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['upload_profile_pic'])) {
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
        $file_type = $_FILES['profile_pic']['type'];
        $file_size = $_FILES['profile_pic']['size'];
        
        if (!in_array($file_type, $allowed_types)) {
            $error = 'Invalid file type. Please upload PNG, JPG, GIF, or WebP.';
        } elseif ($file_size > 2 * 1024 * 1024) {
            $error = 'File too large. Maximum size is 2MB.';
        } elseif (!validateUploadedImage($_FILES['profile_pic']['tmp_name'], $file_type)) {
            $error = 'Invalid image file. The file appears to be corrupted or not a real image.';
        } else {
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/profiles/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            
            // Delete old picture
            $old_pic = getSetting('user_' . $user_id . '_profile_pic', '');
            if ($old_pic && file_exists($_SERVER['DOCUMENT_ROOT'] . $old_pic)) {
                @unlink($_SERVER['DOCUMENT_ROOT'] . $old_pic);
            }
            
            $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
            $filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $filepath)) {
                $pic_path = '/uploads/profiles/' . $filename;
                $key = 'user_' . $user_id . '_profile_pic';
                $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->bind_param("sss", $key, $pic_path, $pic_path);
                $stmt->execute();
                $stmt->close();
                
                $message = 'Profile picture updated!';
            } else {
                $error = 'Failed to upload picture.';
            }
        }
    }
}

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get user status
$user_status = getUserStatus($user_id);

// Get profile picture
$profile_pic = getSetting('user_' . $user_id . '_profile_pic', '');
$has_profile_pic = !empty($profile_pic) && file_exists($_SERVER['DOCUMENT_ROOT'] . $profile_pic);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['update_profile'])) {
    $discord_id = trim($_POST['discord_id'] ?? '');
    $timezone = trim($_POST['timezone'] ?? 'UTC');
    
    // Validate timezone
    $valid_tz = @timezone_open($timezone);
    if (!$valid_tz) $timezone = 'UTC';
    
    // Try updating with timezone first, fall back to without if column doesn't exist
    $stmt = $conn->prepare("UPDATE users SET discord_id = ?, timezone = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("ssi", $discord_id, $timezone, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET discord_id = ? WHERE id = ?");
        $stmt->bind_param("si", $discord_id, $user_id);
    }
    
    if ($stmt->execute()) {
        logAudit('update_profile', 'user', $user_id, 'Updated profile information');
        $message = 'Profile updated successfully!';
        $user['discord_id'] = $discord_id;
        $user['timezone'] = $timezone;
    } else {
        $error = 'Failed to update profile.';
    }
    $stmt->close();
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (!password_verify($current_password, $user['password'])) {
        $error = 'Current password is incorrect.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } else {
        $password_errors = validatePassword($new_password);
        if (!empty($password_errors)) {
            $error = implode(' ', $password_errors);
        } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            logAudit('change_password', 'user', $user_id, 'Changed password');
            $message = 'Password changed successfully!';
            
            // Send notification email
            $stmt_email = $conn->prepare("SELECT email FROM users WHERE id = ?");
            $stmt_email->bind_param("i", $user_id);
            $stmt_email->execute();
            $email_row = $stmt_email->get_result()->fetch_assoc();
            $stmt_email->close();
            if ($email_row) {
                $notification_body = "Your password for " . getCommunityName() . " was changed on " . date('F j, Y \a\t g:i A') . ".\n\nIf you did not make this change, please contact an administrator immediately.";
                sendEmail($email_row['email'], 'Password Changed - ' . getCommunityName(), $notification_body, [
                    'preheader' => 'Your password has been changed.'
                ]);
            }
        } else {
            $error = 'Failed to change password.';
        }
        $stmt->close();
        } // Close password validation else
    }
}

// Get user's roster entries
$stmt_roster = $conn->prepare("
    SELECT r.*, d.name as dept_name, d.abbreviation, rk.rank_name
    FROM roster r
    JOIN departments d ON r.department_id = d.id
    JOIN ranks rk ON r.rank_id = rk.id
    WHERE r.user_id = ?
    ORDER BY r.is_primary DESC, d.name
");
$stmt_roster->bind_param("i", $user_id);
$stmt_roster->execute();
$roster_entries = $stmt_roster->get_result();

// Get user's roles
$user_roles = [];
$stmt_roles = $conn->prepare("
    SELECT r.role_name, r.role_key, r.color, r.description
    FROM user_roles ur
    JOIN roles r ON ur.role_id = r.id
    WHERE ur.user_id = ?
    ORDER BY r.role_name
");
$stmt_roles->bind_param("i", $user_id);
$stmt_roles->execute();
$roles_result = $stmt_roles->get_result();
if ($roles_result && $roles_result->num_rows > 0) {
    while ($role = $roles_result->fetch_assoc()) {
        $user_roles[] = $role;
    }
}

// Get user's earned badges/medals
$user_badges = [];
$stmt_badges = $conn->prepare("
    SELECT b.name, b.icon, b.color, b.rarity, b.description, ub.awarded_at, ub.reason
    FROM user_badges ub
    JOIN badges b ON ub.badge_id = b.id
    WHERE ub.user_id = ?
    ORDER BY ub.awarded_at DESC
");
if ($stmt_badges) {
    $stmt_badges->bind_param("i", $user_id);
    $stmt_badges->execute();
    $badges_result = $stmt_badges->get_result();
    if ($badges_result && $badges_result->num_rows > 0) {
        while ($badge = $badges_result->fetch_assoc()) {
            $user_badges[] = $badge;
        }
    }
    $stmt_badges->close();
}

// Get user's awards
$stmt_awards = $conn->prepare("
    SELECT a.*, d.abbreviation as dept_abbr
    FROM recognition_awards a
    LEFT JOIN departments d ON a.department_id = d.id
    WHERE a.user_id = ? AND a.is_active = TRUE
    ORDER BY a.created_at DESC
    LIMIT 5
");
$stmt_awards->bind_param("i", $user_id);
$stmt_awards->execute();
$awards = $stmt_awards->get_result();

// Get user's certifications
$my_certs = [];
$stmt_cert = $conn->prepare("
    SELECT uc.*, ct.name as cert_name, ct.abbreviation as cert_abbr, ct.icon, ct.color,
           iu.username as issued_by_name
    FROM user_certifications uc
    JOIN certification_types ct ON uc.certification_type_id = ct.id
    LEFT JOIN users iu ON uc.issued_by = iu.id
    WHERE uc.user_id = ?
    ORDER BY uc.status = 'completed' DESC, uc.issued_date DESC
");
$stmt_cert->bind_param("i", $user_id);
$stmt_cert->execute();
$cert_result = $stmt_cert->get_result();
if ($cert_result) {
    while ($row = $cert_result->fetch_assoc()) $my_certs[] = $row;
}

// Get user's training records
$my_training = [];
$stmt_train = $conn->prepare("
    SELECT tr.*, tp.name as program_name, u.username as trainer_name,
           ct.name as cert_name
    FROM training_records tr
    LEFT JOIN training_programs tp ON tr.program_id = tp.id
    JOIN users u ON tr.trainer_id = u.id
    LEFT JOIN certification_types ct ON tr.certification_type_id = ct.id
    WHERE tr.trainee_id = ?
    ORDER BY tr.session_date DESC
    LIMIT 20
");
$stmt_train->bind_param("i", $user_id);
$stmt_train->execute();
$train_result = $stmt_train->get_result();
if ($train_result) {
    while ($row = $train_result->fetch_assoc()) $my_training[] = $row;
}

// Get user's conduct records
$my_conduct = [];
$stmt_conduct = $conn->prepare("
    SELECT cr.*, d.name as dept_name, iu.username as issued_by_name
    FROM conduct_records cr
    LEFT JOIN departments d ON cr.department_id = d.id
    JOIN users iu ON cr.issued_by = iu.id
    WHERE cr.user_id = ? AND cr.is_active = TRUE
    ORDER BY cr.created_at DESC
    LIMIT 20
");
$stmt_conduct->bind_param("i", $user_id);
$stmt_conduct->execute();
$conduct_result = $stmt_conduct->get_result();
if ($conduct_result) {
    while ($row = $conduct_result->fetch_assoc()) $my_conduct[] = $row;
}

// Get user's activity logs
$my_activity = [];
$stmt_activity = $conn->prepare("
    SELECT al.*, d.name as dept_name, d.abbreviation as dept_abbr,
           at.name as type_name, at.icon as type_icon
    FROM activity_logs al
    JOIN departments d ON al.department_id = d.id
    LEFT JOIN activity_types at ON al.activity_type_id = at.id
    WHERE al.user_id = ?
    ORDER BY al.activity_date DESC
    LIMIT 25
");
$stmt_activity->bind_param("i", $user_id);
$stmt_activity->execute();
$activity_result = $stmt_activity->get_result();
if ($activity_result) {
    while ($row = $activity_result->fetch_assoc()) $my_activity[] = $row;
}

// Get user's promotion/rank history
$my_promotions = [];
$stmt_promo = $conn->prepare("
    SELECT ph.*, d.name as dept_name, d.abbreviation as dept_abbr,
           fr.rank_name as from_rank_name, tr.rank_name as to_rank_name,
           pu.username as processed_by_name
    FROM promotion_history ph
    JOIN departments d ON ph.department_id = d.id
    LEFT JOIN ranks fr ON ph.from_rank_id = fr.id
    JOIN ranks tr ON ph.to_rank_id = tr.id
    LEFT JOIN users pu ON ph.processed_by = pu.id
    WHERE ph.user_id = ?
    ORDER BY ph.effective_date DESC, ph.created_at DESC
    LIMIT 20
");
$stmt_promo->bind_param("i", $user_id);
$stmt_promo->execute();
$promo_result = $stmt_promo->get_result();
if ($promo_result) {
    while ($row = $promo_result->fetch_assoc()) $my_promotions[] = $row;
}

// Get user's timezone
$user_timezone = $user['timezone'] ?? 'UTC';

$current_page = 'profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <?php include '../includes/styles.php'; ?>
    <style>
        .profile-header {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 32px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 24px;
            position: relative;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent), #7c3aed);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
            flex-shrink: 0;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-avatar-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--bg-elevated);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
            color: #333;
            font-size: 11px;
            font-weight: 600;
            text-align: center;
            padding: 8px;
        }
        
        .profile-avatar:hover .profile-avatar-overlay {
            opacity: 1;
        }
        
        .profile-info h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }
        
        .profile-info p {
            color: var(--text-muted);
            font-size: 14px;
        }
        
        .profile-badges {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            flex-wrap: wrap;
        }
        
        .badge-admin {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: var(--radius-lg);
            font-size: 12px;
            font-weight: 700;
        }
        
        .badge-role {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: var(--radius-lg);
            font-size: 12px;
            font-weight: 600;
            cursor: help;
        }
        
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: var(--radius-lg);
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-indicator.online { background: rgba(16, 185, 129, 0.2); color: #4ade80; }
        .status-indicator.away { background: rgba(251, 191, 36, 0.2); color: #f0b232; }
        .status-indicator.busy { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .status-indicator.offline { background: rgba(156, 163, 175, 0.2); color: #9ca3af; }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        
        .status-indicator.online .status-dot { background: var(--success); }
        .status-indicator.away .status-dot { background: #f59e0b; }
        .status-indicator.busy .status-dot { background: var(--danger); }
        .status-indicator.offline .status-dot { background: #6b7280; }
        
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        
        @media (max-width: 900px) {
            .profile-grid { grid-template-columns: 1fr; }
            .profile-header { flex-direction: column; text-align: center; }
            .profile-badges { justify-content: center; }
        }
        
        @media (max-width: 768px) {
            .container { padding: 20px 16px; }
            .profile-card { padding: 20px; }
            .profile-header { gap: 16px; }
            .profile-avatar { width: 80px; height: 80px; font-size: 32px; }
            .profile-name { font-size: 22px; }
            .tabs { gap: 4px; flex-wrap: nowrap; }
            .tab { padding: 6px 12px; font-size: 12px; white-space: nowrap; }
            .info-grid { grid-template-columns: 1fr !important; gap: 12px; }
            .stat-grid { grid-template-columns: 1fr 1fr !important; gap: 12px; }
            .stat-card { padding: 16px; }
            .stat-value { font-size: 24px; }
            .detail-grid { grid-template-columns: 1fr !important; }
            .roster-item { flex-wrap: wrap; gap: 8px; }
            .training-item, .conduct-item, .activity-item { padding: 12px; }
            table { font-size: 13px; }
            th, td { padding: 10px 8px; }
        }
        
        @media (max-width: 480px) {
            .profile-avatar { width: 64px; height: 64px; font-size: 28px; }
            .profile-name { font-size: 20px; }
            .tabs { margin-bottom: 16px; }
            .tab { padding: 6px 10px; font-size: 11px; }
            .profile-badges { flex-wrap: wrap; justify-content: center; }
            .status-indicator { font-size: 11px; padding: 4px 10px; }
        }
        
        .roster-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: var(--bg-card);
            border-radius: var(--radius-md);
            margin-bottom: 8px;
        }
        
        .roster-item.primary {
            border-left: 3px solid var(--accent);
        }
        
        .award-item {
            padding: 12px;
            background: linear-gradient(135deg, rgba(251, 191, 36, 0.1), rgba(245, 158, 11, 0.05));
            border: 1px solid rgba(251, 191, 36, 0.2);
            border-radius: var(--radius-md);
            margin-bottom: 8px;
        }
        
        .tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 20px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }
        .tabs::-webkit-scrollbar { display: none; }
        
        .tab {
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            color: var(--text-muted);
            transition: all 0.2s;
            border: none;
            background: none;
            white-space: nowrap;
        }
        
        .tab:hover { color: var(--text-primary); background: var(--bg-elevated); }
        .tab.active { background: var(--bg-hover); color: var(--text-primary); }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .status-saved {
            display: inline-block;
            margin-left: 8px;
            color: #4ade80;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .status-saved.show { opacity: 1; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container">
        <?php if ($message) showToast($message, 'success'); ?>
        
        <?php if ($error) showToast($error, 'error'); ?>
        
        <div class="profile-header">
            <form method="POST" enctype="multipart/form-data" id="avatarForm">
                    <?php echo csrfField(); ?>
                <input type="file" name="profile_pic" id="profile_pic_input" accept="image/*" style="display: none;">
                <input type="hidden" name="upload_profile_pic" value="1">
                <div class="profile-avatar" onclick="document.getElementById('profile_pic_input').click()">
                    <?php if ($has_profile_pic): ?>
                        <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="" id="avatarPreview">
                    <?php else: ?>
                        <span id="avatarPlaceholder">👤</span>
                        <img src="" alt="" id="avatarPreview" style="display: none;">
                    <?php endif; ?>
                    <div class="profile-avatar-overlay">Upload profile image</div>
                </div>
            </form>
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($user['username']); ?></h1>
                <p><?php echo htmlspecialchars($user['email']); ?></p>
                <p>Member since <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                <div class="profile-badges">
                    <span class="status-indicator <?php echo $user_status['status']; ?>" id="statusBadge">
                        <span class="status-dot"></span>
                        <span id="statusText"><?php echo ucfirst($user_status['status']); ?></span>
                        <?php if ($user_status['custom_status']): ?>
                            <span id="customStatusText">- <?php echo htmlspecialchars($user_status['custom_status']); ?></span>
                        <?php else: ?>
                            <span id="customStatusText"></span>
                        <?php endif; ?>
                    </span>
                    <?php if ($user['is_admin']): ?>
                        <span class="badge badge-admin" style="background: linear-gradient(135deg, var(--danger), #dc2626); color: white;">👑 Admin</span>
                    <?php endif; ?>
                    <?php foreach ($user_roles as $role): ?>
                        <span class="badge badge-role" style="background: <?php echo htmlspecialchars($role['color']); ?>20; border: 1px solid <?php echo htmlspecialchars($role['color']); ?>50; color: <?php echo htmlspecialchars($role['color']); ?>;" title="<?php echo htmlspecialchars($role['description'] ?? ''); ?>">
                            <?php echo htmlspecialchars($role['role_name']); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                <?php if (!empty($user_badges)): ?>
                <div class="profile-badges" style="margin-top: 8px;">
                    <?php foreach ($user_badges as $badge): 
                        $rarity_colors = ['common' => '#9ca3af', 'uncommon' => '#22c55e', 'rare' => '#3b82f6', 'epic' => '#a855f7', 'legendary' => '#f59e0b'];
                        $badge_color = $rarity_colors[$badge['rarity']] ?? '#9ca3af';
                    ?>
                        <span class="badge badge-medal" style="background: <?php echo $badge_color; ?>20; border: 1px solid <?php echo $badge_color; ?>50; color: <?php echo $badge_color; ?>;" title="<?php echo htmlspecialchars($badge['description'] ?? ''); ?> - Earned <?php echo date('M j, Y', strtotime($badge['awarded_at'])); ?>">
                            <?php echo htmlspecialchars($badge['icon']); ?> <?php echo htmlspecialchars($badge['name']); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="profile-grid">
            <div>
                <div class="section">
                    <div class="tabs">
                        <button class="tab active" onclick="showTab('profile', this)">Profile</button>
                        <button class="tab" onclick="showTab('security', this)">Security</button>
                        <button class="tab" onclick="showTab('training', this)">Training</button>
                        <button class="tab" onclick="showTab('conduct', this)">Conduct</button>
                        <button class="tab" onclick="showTab('activity', this)">Activity</button>
                        <button class="tab" onclick="showTab('history', this)">Rank History</button>
                        <button class="tab" onclick="showTab('status', this)">Status</button>
                    </div>
                    
                    <div id="tab-profile" class="tab-content active">
                        <form method="POST">
                    <?php echo csrfField(); ?>
                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                <small>Username cannot be changed</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                <small>Contact an admin to change your email</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Discord ID</label>
                                <input type="text" name="discord_id" value="<?php echo htmlspecialchars($user['discord_id'] ?? ''); ?>" placeholder="username#0000 or username">
                            </div>
                            
                            <div class="form-group">
                                <label>Timezone</label>
                                <select name="timezone">
                                    <?php
                                    $timezones = [
                                        'UTC' => 'UTC',
                                        'America/New_York' => 'Eastern Time (US)',
                                        'America/Chicago' => 'Central Time (US)',
                                        'America/Denver' => 'Mountain Time (US)',
                                        'America/Los_Angeles' => 'Pacific Time (US)',
                                        'America/Anchorage' => 'Alaska Time',
                                        'Pacific/Honolulu' => 'Hawaii Time',
                                        'America/Toronto' => 'Eastern Time (Canada)',
                                        'America/Vancouver' => 'Pacific Time (Canada)',
                                        'Europe/London' => 'London (GMT/BST)',
                                        'Europe/Paris' => 'Central European',
                                        'Europe/Berlin' => 'Berlin',
                                        'Europe/Moscow' => 'Moscow',
                                        'Asia/Tokyo' => 'Tokyo',
                                        'Asia/Shanghai' => 'China Standard',
                                        'Asia/Kolkata' => 'India Standard',
                                        'Asia/Dubai' => 'Gulf Standard',
                                        'Australia/Sydney' => 'Sydney',
                                        'Australia/Perth' => 'Perth',
                                        'Pacific/Auckland' => 'New Zealand',
                                    ];
                                    $current_tz = $user['timezone'] ?? 'UTC';
                                    foreach ($timezones as $tz_val => $tz_label):
                                    ?>
                                        <option value="<?php echo $tz_val; ?>" <?php echo $current_tz === $tz_val ? 'selected' : ''; ?>><?php echo $tz_label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small>Used for displaying dates and times throughout the system</small>
                            </div>
                            
                            <button type="submit" name="update_profile" class="btn btn-primary btn-block">Save Changes</button>
                        </form>
                    </div>
                    
                    <div id="tab-security" class="tab-content">
                        <form method="POST">
                    <?php echo csrfField(); ?>
                            <div class="form-group">
                                <label class="required">Current Password</label>
                                <input type="password" name="current_password" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="required">New Password</label>
                                <input type="password" name="new_password" required minlength="8">
                                <small>Minimum 8 characters</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="required">Confirm New Password</label>
                                <input type="password" name="confirm_password" required>
                            </div>
                            
                            <button type="submit" name="change_password" class="btn btn-primary btn-block">Change Password</button>
                        </form>
                    </div>
                    
                    <div id="tab-status" class="tab-content">
                        <div class="form-group">
                            <label>Status <span class="status-saved" id="statusSaved">✓ Saved</span></label>
                            <select id="statusSelect" onchange="saveStatus()">
                                <option value="online" <?php echo $user_status['status'] === 'online' ? 'selected' : ''; ?>>🟢 Online</option>
                                <option value="away" <?php echo $user_status['status'] === 'away' ? 'selected' : ''; ?>>🟡 Away</option>
                                <option value="busy" <?php echo $user_status['status'] === 'busy' ? 'selected' : ''; ?>>🔴 Do Not Disturb</option>
                                <option value="offline" <?php echo $user_status['status'] === 'offline' ? 'selected' : ''; ?>>⚫ Appear Offline</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Custom Status</label>
                            <input type="text" id="customStatusInput" value="<?php echo htmlspecialchars($user_status['custom_status'] ?? ''); ?>" placeholder="What are you up to?" onblur="saveStatus()">
                        </div>
                    </div>
                    
                    <!-- Training & Certifications Tab -->
                    <div id="tab-training" class="tab-content">
                        <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 16px;">📜 My Certifications</h3>
                        <?php if (!empty($my_certs)): ?>
                            <?php foreach ($my_certs as $cert): ?>
                                <?php
                                $status_colors = ['completed' => '#4ade80', 'pending' => '#f0b232', 'in_progress' => '#93c5fd', 'expired' => '#f87171', 'revoked' => '#f87171'];
                                $status_color = $status_colors[$cert['status']] ?? '#9ca3af';
                                $is_expiring = $cert['status'] === 'completed' && $cert['expiry_date'] && strtotime($cert['expiry_date']) < strtotime('+30 days');
                                ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 16px; background: var(--bg-elevated); border: 1px solid var(--bg-elevated); border-radius: var(--radius-md); margin-bottom: 8px; <?php echo $is_expiring ? 'border-color: rgba(251,191,36,0.3);' : ''; ?>">
                                    <div>
                                        <div style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($cert['icon'] ?? '📜'); ?> <?php echo htmlspecialchars($cert['cert_name']); ?>
                                            <?php if ($cert['cert_abbr']): ?><span style="color: var(--text-muted); font-size: 12px;">(<?php echo htmlspecialchars($cert['cert_abbr']); ?>)</span><?php endif; ?>
                                        </div>
                                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                                            <?php if ($cert['issued_date']): ?>Issued: <?php echo date('M j, Y', strtotime($cert['issued_date'])); ?><?php endif; ?>
                                            <?php if ($cert['expiry_date']): ?> • Expires: <?php echo date('M j, Y', strtotime($cert['expiry_date'])); ?><?php endif; ?>
                                            <?php if ($cert['issued_by_name']): ?> • By: <?php echo htmlspecialchars($cert['issued_by_name']); ?><?php endif; ?>
                                        </div>
                                    </div>
                                    <span style="padding: 4px 12px; border-radius: var(--radius-lg); font-size: 11px; font-weight: 700; text-transform: uppercase; background: <?php echo $status_color; ?>20; color: <?php echo $status_color; ?>;"><?php echo ucfirst($cert['status']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: var(--text-muted); text-align: center; padding: 24px;">No certifications yet.</p>
                        <?php endif; ?>
                        
                        <?php if (!empty($my_training)): ?>
                            <h3 style="font-size: 16px; font-weight: 700; margin: 24px 0 16px;">📚 Training Sessions</h3>
                            <?php foreach ($my_training as $session): ?>
                                <?php $perf_colors = ['excellent' => '#4ade80', 'good' => '#93c5fd', 'satisfactory' => '#f0b232', 'needs_improvement' => '#fdba74', 'unsatisfactory' => '#f87171']; ?>
                                <div style="padding: 14px 16px; background: var(--bg-elevated); border: 1px solid var(--bg-elevated); border-radius: var(--radius-md); margin-bottom: 8px;">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                        <div>
                                            <div style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($session['topic'] ?? ($session['program_name'] ?? 'Training Session')); ?></div>
                                            <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                                                <?php echo date('M j, Y', strtotime($session['session_date'])); ?> • <?php echo $session['hours']; ?>h • Trainer: <?php echo htmlspecialchars($session['trainer_name']); ?>
                                            </div>
                                        </div>
                                        <?php if ($session['performance_rating']): ?>
                                            <span style="padding: 4px 12px; border-radius: var(--radius-lg); font-size: 11px; font-weight: 700; background: <?php echo ($perf_colors[$session['performance_rating']] ?? '#9ca3af'); ?>20; color: <?php echo $perf_colors[$session['performance_rating']] ?? '#9ca3af'; ?>;"><?php echo ucfirst(str_replace('_', ' ', $session['performance_rating'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Conduct Tab -->
                    <div id="tab-conduct" class="tab-content">
                        <?php if (!empty($my_conduct)): ?>
                            <?php foreach ($my_conduct as $record): ?>
                                <?php
                                $type_config = [
                                    'commendation' => ['icon' => '⭐', 'color' => '#4ade80', 'bg' => 'rgba(110,231,183,0.08)', 'border' => 'rgba(110,231,183,0.2)'],
                                    'warning' => ['icon' => '⚠️', 'color' => '#f0b232', 'bg' => 'rgba(251,191,36,0.08)', 'border' => 'rgba(251,191,36,0.2)'],
                                    'disciplinary' => ['icon' => '🔴', 'color' => '#f87171', 'bg' => 'rgba(239,68,68,0.08)', 'border' => 'rgba(239,68,68,0.2)'],
                                    'note' => ['icon' => '📝', 'color' => '#93c5fd', 'bg' => 'rgba(59,130,246,0.08)', 'border' => 'rgba(59,130,246,0.2)'],
                                ];
                                $tc = $type_config[$record['type']] ?? $type_config['note'];
                                ?>
                                <div style="padding: 16px; background: <?php echo $tc['bg']; ?>; border: 1px solid <?php echo $tc['border']; ?>; border-radius: var(--radius-md); margin-bottom: 10px;">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                        <div style="font-weight: 700; font-size: 15px;"><?php echo $tc['icon']; ?> <?php echo htmlspecialchars($record['title']); ?></div>
                                        <span style="padding: 4px 12px; border-radius: var(--radius-lg); font-size: 11px; font-weight: 700; text-transform: uppercase; background: <?php echo $tc['color']; ?>20; color: <?php echo $tc['color']; ?>;"><?php echo ucfirst($record['type']); ?></span>
                                    </div>
                                    <div style="font-size: 13px; color: var(--text-secondary); line-height: 1.6; margin-bottom: 8px;"><?php echo nl2br(htmlspecialchars($record['description'])); ?></div>
                                    <div style="font-size: 12px; color: var(--text-muted);">
                                        <?php echo date('M j, Y', strtotime($record['created_at'])); ?>
                                        • By: <?php echo htmlspecialchars($record['issued_by_name']); ?>
                                        <?php if ($record['dept_name']): ?> • <?php echo htmlspecialchars($record['dept_name']); ?><?php endif; ?>
                                        <?php if ($record['severity'] && $record['type'] !== 'commendation'): ?> • Severity: <?php echo ucfirst($record['severity']); ?><?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                                <div style="font-size: 36px; margin-bottom: 12px;">✨</div>
                                <p>No conduct records. Keep up the great work!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Activity Tab -->
                    <div id="tab-activity" class="tab-content">
                        <?php if (!empty($my_activity)): ?>
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                                    <thead>
                                        <tr style="border-bottom: 1px solid var(--bg-elevated);">
                                            <th style="text-align: left; padding: 10px 12px; color: var(--text-muted); font-size: 11px; text-transform: uppercase;">Date</th>
                                            <th style="text-align: left; padding: 10px 12px; color: var(--text-muted); font-size: 11px; text-transform: uppercase;">Department</th>
                                            <th style="text-align: left; padding: 10px 12px; color: var(--text-muted); font-size: 11px; text-transform: uppercase;">Type</th>
                                            <th style="text-align: left; padding: 10px 12px; color: var(--text-muted); font-size: 11px; text-transform: uppercase;">Duration</th>
                                            <th style="text-align: left; padding: 10px 12px; color: var(--text-muted); font-size: 11px; text-transform: uppercase;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($my_activity as $act): ?>
                                            <?php $act_status_colors = ['verified' => '#4ade80', 'pending' => '#f0b232', 'rejected' => '#f87171']; ?>
                                            <tr style="border-bottom: 1px solid var(--bg-card);">
                                                <td style="padding: 10px 12px;"><?php echo date('M j, Y', strtotime($act['activity_date'])); ?></td>
                                                <td style="padding: 10px 12px;"><?php echo htmlspecialchars($act['dept_abbr']); ?></td>
                                                <td style="padding: 10px 12px;"><?php echo htmlspecialchars($act['type_icon'] ?? '📋'); ?> <?php echo htmlspecialchars($act['type_name'] ?? ($act['description'] ?? '—')); ?></td>
                                                <td style="padding: 10px 12px;"><?php echo $act['duration_minutes']; ?> min</td>
                                                <td style="padding: 10px 12px;"><span style="padding: 3px 10px; border-radius: var(--radius-lg); font-size: 11px; font-weight: 600; background: <?php echo ($act_status_colors[$act['status']] ?? '#9ca3af'); ?>20; color: <?php echo $act_status_colors[$act['status']] ?? '#9ca3af'; ?>;"><?php echo ucfirst($act['status']); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                                <div style="font-size: 36px; margin-bottom: 12px;">📋</div>
                                <p>No activity logs recorded yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Rank History Tab -->
                    <div id="tab-history" class="tab-content">
                        <?php if (!empty($my_promotions)): ?>
                            <div style="position: relative; padding-left: 24px;">
                                <?php foreach ($my_promotions as $i => $promo): ?>
                                    <?php
                                    $change_config = [
                                        'promotion' => ['icon' => '⬆️', 'color' => '#4ade80'],
                                        'demotion' => ['icon' => '⬇️', 'color' => '#f87171'],
                                        'lateral' => ['icon' => '↔️', 'color' => '#93c5fd'],
                                        'initial' => ['icon' => '🎯', 'color' => '#c4b5fd'],
                                    ];
                                    $cc = $change_config[$promo['change_type']] ?? $change_config['promotion'];
                                    ?>
                                    <div style="position: relative; padding-bottom: 20px; <?php echo $i < count($my_promotions) - 1 ? 'border-left: 2px solid var(--bg-elevated); margin-left: -13px; padding-left: 24px;' : 'margin-left: -13px; padding-left: 24px;'; ?>">
                                        <div style="position: absolute; left: -20px; top: 2px; width: 16px; height: 16px; border-radius: 50%; background: <?php echo $cc['color']; ?>; display: flex; align-items: center; justify-content: center; font-size: 9px;"><?php echo $cc['icon']; ?></div>
                                        <div style="background: var(--bg-elevated); border: 1px solid var(--bg-elevated); border-radius: var(--radius-md); padding: 14px 16px;">
                                            <div style="font-weight: 700; font-size: 14px; margin-bottom: 4px;">
                                                <?php if ($promo['from_rank_name']): ?>
                                                    <?php echo htmlspecialchars($promo['from_rank_name']); ?> → <?php echo htmlspecialchars($promo['to_rank_name']); ?>
                                                <?php else: ?>
                                                    Assigned: <?php echo htmlspecialchars($promo['to_rank_name']); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div style="font-size: 12px; color: var(--text-muted);">
                                                <?php echo date('M j, Y', strtotime($promo['effective_date'])); ?>
                                                • <?php echo htmlspecialchars($promo['dept_name']); ?>
                                                <?php if ($promo['processed_by_name']): ?> • By: <?php echo htmlspecialchars($promo['processed_by_name']); ?><?php endif; ?>
                                            </div>
                                            <?php if ($promo['reason']): ?>
                                                <div style="font-size: 13px; color: var(--text-secondary); margin-top: 6px; font-style: italic;">"<?php echo htmlspecialchars($promo['reason']); ?>"</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                                <div style="font-size: 36px; margin-bottom: 12px;">📊</div>
                                <p>No rank history recorded yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div>
                <div class="section">
                    <h2>🏢 My Departments</h2>
                    <?php if ($roster_entries->num_rows > 0): ?>
                        <?php while ($entry = $roster_entries->fetch_assoc()): ?>
                            <div class="roster-item <?php echo $entry['is_primary'] ? 'primary' : ''; ?>">
                                <div>
                                    <div style="font-weight: 700; font-size: 14px;"><?php echo htmlspecialchars($entry['dept_name']); ?></div>
                                    <div style="font-size: 13px; color: var(--text-muted);">
                                        <?php echo htmlspecialchars($entry['rank_name']); ?>
                                        <?php if ($entry['badge_number']): ?> • Badge: <?php echo htmlspecialchars($entry['badge_number']); ?><?php endif; ?>
                                        <?php if ($entry['is_primary']): ?><span class="badge badge-primary" style="margin-left: 8px;">Primary</span><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-muted">Not assigned to any departments</p>
                    <?php endif; ?>
                </div>
                
                <?php if ($awards->num_rows > 0): ?>
                <div class="section">
                    <h2>🏆 Awards</h2>
                    <?php while ($award = $awards->fetch_assoc()): ?>
                        <div class="award-item">
                            <strong>
                                <?php
                                $types = ['motm' => '🌟 Member of the Month', 'excellence' => '⭐ Excellence', 'dedication' => '💪 Dedication', 'teamwork' => '🤝 Teamwork'];
                                echo $award['award_type'] === 'custom' ? '✨ ' . htmlspecialchars($award['custom_award_name']) : ($types[$award['award_type']] ?? $award['award_type']);
                                ?>
                            </strong>
                            <div class="text-muted" style="font-size: 12px; margin-top: 4px;">
                                <?php echo date('F Y', mktime(0, 0, 0, $award['month'], 1, $award['year'])); ?>
                                <?php if ($award['dept_abbr']): ?> • <?php echo htmlspecialchars($award['dept_abbr']); ?><?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName, btn) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            btn.classList.add('active');
            document.getElementById('tab-' + tabName).classList.add('active');
        }
        
        function saveStatus() {
            const status = document.getElementById('statusSelect').value;
            const customStatus = document.getElementById('customStatusInput').value;
            const csrfToken = '<?php echo htmlspecialchars(generateCSRFToken()); ?>';
            
            fetch('/user/profile', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ajax_status=1&csrf_token=' + encodeURIComponent(csrfToken) + '&status=' + encodeURIComponent(status) + '&custom_status=' + encodeURIComponent(customStatus)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Update the badge
                    const badge = document.getElementById('statusBadge');
                    badge.className = 'status-indicator ' + status;
                    document.getElementById('statusText').textContent = status.charAt(0).toUpperCase() + status.slice(1);
                    document.getElementById('customStatusText').textContent = customStatus ? '- ' + customStatus : '';
                    
                    // Show saved indicator
                    const saved = document.getElementById('statusSaved');
                    saved.classList.add('show');
                    setTimeout(() => saved.classList.remove('show'), 2000);
                } else {
                    console.error('Status save failed:', data.error || 'Unknown error');
                    alert('Failed to save status. Please try again.');
                }
            })
            .catch(err => {
                console.error('Status save error:', err);
                alert('Failed to save status. Please try again.');
            });
        }
        
        // Profile picture upload with instant preview
        document.getElementById('profile_pic_input').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            // Validate file type
            const validTypes = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
            if (!validTypes.includes(file.type)) {
                alert('Please select a valid image file (PNG, JPG, GIF, or WebP)');
                return;
            }
            
            // Validate file size (2MB)
            if (file.size > 2 * 1024 * 1024) {
                alert('Image is too large. Maximum size is 2MB.');
                return;
            }
            
            // Show instant preview
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('avatarPreview');
                const placeholder = document.getElementById('avatarPlaceholder');
                
                preview.src = e.target.result;
                preview.style.display = '';
                if (placeholder) placeholder.style.display = 'none';
            };
            reader.readAsDataURL(file);
            
            // Upload the file
            const formData = new FormData(document.getElementById('avatarForm'));
            fetch('profile', {
                method: 'POST',
                body: formData
            })
            .then(r => r.text())
            .then(() => {
                // Optionally reload to update navbar avatar too
                // For now, just keep the preview
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
