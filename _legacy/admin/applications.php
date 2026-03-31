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
require_once '../includes/permissions_ui.php';
requireLogin();

// Check permission - admin or has application permissions
if (!isAdmin() && !hasAnyPermission(['apps.view', 'apps.review', 'apps.templates.manage'])) {
    header('Location: /index?error=unauthorized');
    exit();
}

$conn = getDBConnection();
$message = '';
$is_admin = isAdmin();
$can_review = $is_admin || hasPermission('apps.review');
$can_manage_templates = $is_admin || hasPermission('apps.templates.manage');

// Handle template creation (admin or templates.manage permission only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['create_template']) && $can_manage_templates) {
    $dept_id = intval($_POST['department_id']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $requirements = trim($_POST['requirements']);
    $questions = json_encode(array_filter($_POST['questions'] ?? [], function($q) { return !empty(trim($q)); }));
    
    $stmt = $conn->prepare("INSERT INTO application_templates (department_id, title, description, questions, requirements) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $dept_id, $title, $description, $questions, $requirements);
    $stmt->execute();
    
    logAudit('create_app_template', 'template', $stmt->insert_id, "Created application template: $title");
    $message = 'Application template created!';
    $stmt->close();
}

// Handle template update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['update_template']) && $can_manage_templates) {
    $id = intval($_POST['template_id']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $requirements = trim($_POST['requirements']);
    $questions = json_encode(array_filter($_POST['questions'] ?? [], function($q) { return !empty(trim($q)); }));
    
    $stmt = $conn->prepare("UPDATE application_templates SET title = ?, description = ?, questions = ?, requirements = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $title, $description, $questions, $requirements, $id);
    $stmt->execute();
    
    logAudit('update_app_template', 'template', $id, "Updated application template: $title");
    $message = 'Application template updated!';
    $stmt->close();
}

// Handle template toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['toggle_template']) && $can_manage_templates) {
    $id = intval($_POST['template_id']);
    $stmt = $conn->prepare("UPDATE application_templates SET is_active = NOT is_active WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    $message = 'Template status updated!';
}

// Handle template deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['delete_template']) && $can_manage_templates) {
    $id = intval($_POST['template_id']);
    // Check if there are any applications using this template
    $stmt_chk = $conn->prepare("SELECT COUNT(*) as cnt FROM applications WHERE template_id = ?");
        $stmt_chk->bind_param("i", $id);
        $stmt_chk->execute();
        $check = $stmt_chk->get_result();
    $row = $check->fetch_assoc();
    if ($row['cnt'] > 0) {
        $message = 'Cannot delete template: ' . $row['cnt'] . ' application(s) are linked to it. Deactivate it instead.';
    } else {
        $stmt_del = $conn->prepare("DELETE FROM application_templates WHERE id = ?");
            $stmt_del->bind_param("i", $id);
            $stmt_del->execute();
            $stmt_del->close();
        logAudit('delete_app_template', 'template', $id, "Deleted application template");
        $message = 'Application template deleted!';
    }
}

// Handle application review
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['review_application']) && $can_review) {
    $app_id = intval($_POST['application_id']);
    $status = $_POST['status'];
    $notes = trim($_POST['reviewer_notes'] ?? '');
    
    // Get applicant info for notifications
    $stmt_app = $conn->prepare("SELECT a.*, t.title as template_title, t.department_id, d.name as dept_name 
                              FROM applications a 
                              JOIN application_templates t ON a.template_id = t.id
                              JOIN departments d ON t.department_id = d.id
                              WHERE a.id = ?");
    $stmt_app->bind_param("i", $app_id);
    $stmt_app->execute();
    $app_info = $stmt_app->get_result()->fetch_assoc();
    $stmt_app->close();
    
    $stmt = $conn->prepare("UPDATE applications SET status = ?, reviewer_id = ?, reviewer_notes = ?, reviewed_at = NOW() WHERE id = ?");
    $stmt->bind_param("sisi", $status, $_SESSION['user_id'], $notes, $app_id);
    $stmt->execute();
    $stmt->close();
    
    $created_new_account = false;
    $temp_username = '';
    $temp_password = '';
    $new_user_id = null;
    
    // On ANY approval, always ensure an account exists for this applicant
    if ($status === 'approved') {
        // Check if user account already exists with this email
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt_check->bind_param("s", $app_info['applicant_email']);
        $stmt_check->execute();
        $existing_user = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();
        
        if ($existing_user) {
            $new_user_id = $existing_user['id'];
        } else {
            // Create a new user account with temporary credentials
            $temp_password = bin2hex(random_bytes(8));
            $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
            $temp_username = preg_replace('/[^a-zA-Z0-9_]/', '', $app_info['applicant_name']);
            if (empty($temp_username)) $temp_username = 'user';
            
            // Make sure username is unique
            $base_username = $temp_username;
            $counter = 1;
            $max_attempts = 1000; // Safety limit
            $stmt_uname = $conn->prepare("SELECT id FROM users WHERE username = ?");
            while ($counter <= $max_attempts) {
                $stmt_uname->bind_param("s", $temp_username);
                $stmt_uname->execute();
                if ($stmt_uname->get_result()->num_rows === 0) break;
                $temp_username = $base_username . $counter;
                $counter++;
            }
            $stmt_uname->close();
            
            $discord_id = $app_info['applicant_discord'] ?? '';
            $is_approved_val = 1;
            $must_change_val = 1;
            $stmt_create = $conn->prepare("INSERT INTO users (username, email, password, discord_id, is_admin, is_approved, must_change_password) VALUES (?, ?, ?, ?, 0, ?, ?)");
            $stmt_create->bind_param("ssssii", $temp_username, $app_info['applicant_email'], $hashed_password, $discord_id, $is_approved_val, $must_change_val);
            $stmt_create->execute();
            $new_user_id = $stmt_create->insert_id;
            $stmt_create->close();
            
            $created_new_account = true;
            logAudit('create_account_from_app', 'user', $new_user_id, "Auto-created account for approved applicant: " . $app_info['applicant_name']);
        }
        
        // If "Approve & Add to Roster" was chosen, also create roster entry
        if (isset($_POST['create_roster']) && $_POST['create_roster'] === '1' && $new_user_id) {
            $rank_id = intval($_POST['onboard_rank_id']);
            $badge_number = trim($_POST['onboard_badge'] ?? '');
            $callsign = trim($_POST['onboard_callsign'] ?? '');
            $join_date = $_POST['onboard_join_date'] ?? date('Y-m-d');
            $dept_id = intval($_POST['onboard_dept_id']);
            
            $is_primary = 1;
            $stmt_roster = $conn->prepare("INSERT INTO roster (user_id, department_id, rank_id, badge_number, callsign, joined_date, is_primary) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_roster->bind_param("iiisssi", $new_user_id, $dept_id, $rank_id, $badge_number, $callsign, $join_date, $is_primary);
            $stmt_roster->execute();
            $stmt_roster->close();
            
            logAudit('onboard_member', 'user', $new_user_id, "Onboarded from application #$app_id to department $dept_id");
        }
    }
    
    // Send Discord webhook for application decision
    sendDiscordWebhook('application_' . $status, [
        'applicant_name' => $app_info['applicant_name'],
        'department' => $app_info['dept_name'],
        'position' => $app_info['template_title'],
        'reviewer' => $_SESSION['username'],
        'notes' => $status === 'approved' ? ($notes ?? '') : '',
        'reason' => $status === 'denied' ? ($notes ?? '') : '',
        'created_account' => $created_new_account
    ]);
    
    // Send email notifications to applicant
    if (!empty($app_info['applicant_email'])) {
        $community = getCommunityName();
        $site_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $email_subject = "Application " . ucfirst($status) . " - " . $community;
        
        if ($status === 'approved') {
            $email_body = "<p style=\"margin: 0 0 16px 0;\">Dear <strong>" . htmlspecialchars($app_info['applicant_name']) . "</strong>,</p>"
                        . "<p style=\"margin: 0 0 20px 0;\">Congratulations! Your application has been <strong style=\"color: #22c55e;\">approved</strong>.</p>"
                        . "<table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" width=\"100%\" style=\"margin: 20px 0; background: rgba(34,197,94,0.08); border-radius: var(--radius-md); border: 1px solid rgba(34,197,94,0.2);\">"
                        . "<tr><td style=\"padding: 20px;\">"
                        . "<table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" width=\"100%\">"
                        . "<tr><td style=\"padding: 6px 0; color: #94a3b8; font-size: 13px;\">Department</td><td style=\"padding: 6px 0; color: #f1f5f9; font-size: 13px; text-align: right; font-weight: 600;\">" . htmlspecialchars($app_info['dept_name']) . "</td></tr>"
                        . "<tr><td style=\"padding: 6px 0; color: #94a3b8; font-size: 13px;\">Position</td><td style=\"padding: 6px 0; color: #f1f5f9; font-size: 13px; text-align: right; font-weight: 600;\">" . htmlspecialchars($app_info['template_title']) . "</td></tr>"
                        . "<tr><td style=\"padding: 6px 0; color: #94a3b8; font-size: 13px;\">Status</td><td style=\"padding: 6px 0; font-size: 13px; text-align: right;\"><span style=\"display: inline-block; padding: 3px 12px; background: rgba(34,197,94,0.15); color: #22c55e; border-radius: var(--radius-md); font-weight: 600; font-size: 12px;\">✓ Approved</span></td></tr>"
                        . "</table>"
                        . "</td></tr></table>"
                        . "<p style=\"margin: 0 0 16px 0;\">Welcome to the team! We're excited to have you on board.</p>";
            if (!empty($notes)) {
                $email_body .= "<p style=\"margin: 0 0 16px 0; padding: 14px 16px; background: var(--bg-primary); border-radius: var(--radius-sm); border-left: 3px solid #22c55e; font-size: 14px;\"><strong style=\"color: #f1f5f9;\">Note:</strong> <span style=\"color: #cbd5e1;\">" . htmlspecialchars($notes) . "</span></p>";
            }
            
            $approval_footer = $created_new_account 
                ? 'Welcome to ' . htmlspecialchars($community) . '! Your account credentials have been sent in a separate email.'
                : 'Welcome to ' . htmlspecialchars($community) . '! You can log in with your existing account.';
            
            sendEmail($app_info['applicant_email'], $email_subject, $email_body, [
                'icon' => '🎉',
                'header_title' => 'Application Approved!',
                'cta_text' => 'Log In to Get Started',
                'cta_url' => $site_url . '/auth/login',
                'footer_note' => $approval_footer
            ]);
            
            // Send separate credentials email for newly created accounts
            if ($created_new_account) {
                $creds_subject = "Your Account Credentials - " . $community;
                $creds_body = "<p style=\"margin: 0 0 16px 0;\">Hi <strong>" . htmlspecialchars($app_info['applicant_name']) . "</strong>,</p>"
                    . "<p style=\"margin: 0 0 20px 0;\">An account has been created for you. Below are your temporary login credentials.</p>"
                    . "<table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" width=\"100%\" style=\"margin: 20px 0; background: rgba(59,130,246,0.08); border-radius: var(--radius-md); border: 1px solid rgba(59,130,246,0.2);\">"
                    . "<tr><td style=\"padding: 20px;\">"
                    . "<table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" width=\"100%\">"
                    . "<tr><td style=\"padding: 8px 0; color: #94a3b8; font-size: 13px;\">Username</td><td style=\"padding: 8px 0; color: #f1f5f9; font-size: 15px; text-align: right; font-weight: 700; font-family: monospace; letter-spacing: 0.5px;\">" . htmlspecialchars($temp_username) . "</td></tr>"
                    . "<tr><td style=\"padding: 8px 0; color: #94a3b8; font-size: 13px;\">Temporary Password</td><td style=\"padding: 8px 0; color: #f1f5f9; font-size: 15px; text-align: right; font-weight: 700; font-family: monospace; letter-spacing: 0.5px;\">" . htmlspecialchars($temp_password) . "</td></tr>"
                    . "</table>"
                    . "</td></tr></table>"
                    . "<p style=\"margin: 0 0 16px 0; padding: 14px 16px; background: rgba(251,191,36,0.08); border-radius: var(--radius-sm); border-left: 3px solid #f59e0b; font-size: 14px;\"><strong style=\"color: #f0b232;\">⚠ Important:</strong> <span style=\"color: #cbd5e1;\">You will be required to choose a new username and password when you first log in. These temporary credentials will no longer work after that.</span></p>";
                
                sendEmail($app_info['applicant_email'], $creds_subject, $creds_body, [
                    'icon' => '🔑',
                    'header_title' => 'Your Account Credentials',
                    'cta_text' => 'Log In & Set Up Your Account',
                    'cta_url' => $site_url . '/auth/login',
                    'preheader' => 'Your temporary login credentials for ' . $community,
                    'footer_note' => 'This email contains sensitive information. If you did not apply at ' . htmlspecialchars($community) . ', please disregard this message.'
                ]);
            }
        } else {
            $email_body = "<p style=\"margin: 0 0 16px 0;\">Dear <strong>" . htmlspecialchars($app_info['applicant_name']) . "</strong>,</p>"
                        . "<p style=\"margin: 0 0 20px 0;\">Thank you for your interest in <strong>" . htmlspecialchars($app_info['dept_name']) . "</strong> (" . htmlspecialchars($app_info['template_title']) . ").</p>"
                        . "<table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" width=\"100%\" style=\"margin: 20px 0; background: var(--bg-primary); border-radius: var(--radius-md); border: 1px solid var(--bg-elevated);\">"
                        . "<tr><td style=\"padding: 20px;\">"
                        . "<table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" width=\"100%\">"
                        . "<tr><td style=\"padding: 6px 0; color: #94a3b8; font-size: 13px;\">Department</td><td style=\"padding: 6px 0; color: #f1f5f9; font-size: 13px; text-align: right; font-weight: 600;\">" . htmlspecialchars($app_info['dept_name']) . "</td></tr>"
                        . "<tr><td style=\"padding: 6px 0; color: #94a3b8; font-size: 13px;\">Position</td><td style=\"padding: 6px 0; color: #f1f5f9; font-size: 13px; text-align: right; font-weight: 600;\">" . htmlspecialchars($app_info['template_title']) . "</td></tr>"
                        . "<tr><td style=\"padding: 6px 0; color: #94a3b8; font-size: 13px;\">Status</td><td style=\"padding: 6px 0; font-size: 13px; text-align: right;\"><span style=\"display: inline-block; padding: 3px 12px; background: rgba(239,68,68,0.15); color: var(--danger); border-radius: var(--radius-md); font-weight: 600; font-size: 12px;\">Not Approved</span></td></tr>"
                        . "</table>"
                        . "</td></tr></table>"
                        . "<p style=\"margin: 0 0 16px 0;\">After careful review, we regret to inform you that your application has not been approved at this time.</p>";
            if (!empty($notes)) {
                $email_body .= "<p style=\"margin: 0 0 16px 0; padding: 14px 16px; background: var(--bg-primary); border-radius: var(--radius-sm); border-left: 3px solid #f59e0b; font-size: 14px;\"><strong style=\"color: #f1f5f9;\">Feedback:</strong> <span style=\"color: #cbd5e1;\">" . htmlspecialchars($notes) . "</span></p>";
            }
            $email_body .= "<p style=\"margin: 0; color: #94a3b8;\">You're welcome to apply again in the future. We appreciate your interest!</p>";
            
            sendEmail($app_info['applicant_email'], $email_subject, $email_body, [
                'icon' => '📋',
                'header_title' => 'Application Update',
                'footer_note' => 'You are receiving this email because you submitted an application at ' . htmlspecialchars($community) . '.'
            ]);
        }
    }
    
    logAudit('review_application', 'application', $app_id, "Reviewed application: $status");
    $message = 'Application ' . $status . '!';
}

// Handle application deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['delete_application']) && $can_review) {
    $id = intval($_POST['application_id']);
    $stmt = $conn->prepare("DELETE FROM applications WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $message = 'Application deleted!';
}

// Get templates
$templates = $conn->query("
    SELECT t.*, d.name as dept_name, d.abbreviation,
           (SELECT COUNT(*) FROM applications WHERE template_id = t.id AND status = 'pending') as pending_count
    FROM application_templates t
    JOIN departments d ON t.department_id = d.id
    ORDER BY d.name, t.title
");

// Get applications
$status_filter = $_GET['status'] ?? 'pending';
$valid_statuses = ['pending', 'under_review', 'approved', 'denied', 'archived'];
if (!in_array($status_filter, $valid_statuses)) $status_filter = 'pending';
$stmt_apps = $conn->prepare("
    SELECT a.*, t.title as template_title, d.name as dept_name, d.abbreviation,
           r.username as reviewer_name
    FROM applications a
    JOIN application_templates t ON a.template_id = t.id
    JOIN departments d ON t.department_id = d.id
    LEFT JOIN users r ON a.reviewer_id = r.id
    WHERE a.status = ?
    ORDER BY a.created_at DESC
    LIMIT 50
");
$stmt_apps->bind_param("s", $status_filter);
$stmt_apps->execute();
$applications = $stmt_apps->get_result();
$stmt_apps->close();

// Get departments
$departments = $conn->query("SELECT id, name, abbreviation FROM departments ORDER BY name");

// Get all ranks grouped by department for onboarding modal
$all_ranks = $conn->query("SELECT r.id, r.rank_name as name, r.department_id, d.name as dept_name FROM ranks r JOIN departments d ON r.department_id = d.id ORDER BY d.name, r.rank_order");
$ranks_by_dept = [];
if ($all_ranks) {
    while ($rank = $all_ranks->fetch_assoc()) {
        $ranks_by_dept[$rank['department_id']][] = $rank;
    }
}

// Stats
$stats = $conn->query("
    SELECT 
        SUM(status = 'pending') as pending,
        SUM(status = 'under_review') as under_review,
        SUM(status = 'approved') as approved,
        SUM(status = 'denied') as denied
    FROM applications
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <?php include '../includes/styles.php'; ?>
    <style>
        .container { max-width: 1400px; }
        .message {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2));
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #4ade80;
            padding: 16px 24px;
            border-radius: var(--radius-md);
            margin-bottom: 24px;
        }
        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .tab {
            padding: 12px 24px;
            border-radius: var(--radius-md);
            background: var(--bg-card);
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s, color 0.3s;
        }
        .tab:hover { background: var(--bg-elevated); color: var(--text-primary); }
        .tab.active {
            background: var(--accent);
            color: white;
        }
        .tab .count {
            background: var(--border);
            padding: 2px 8px;
            border-radius: var(--radius-md);
            font-size: 12px;
            margin-left: 6px;
        }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        @media (max-width: 1024px) { .grid { grid-template-columns: 1fr; } }
        .section {
            background: var(--bg-card);
            
            border: 1px solid var(--bg-elevated);
            padding: 32px;
            border-radius: var(--radius-lg);
        }
        .section h2 {
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--accent-muted);
            font-size: 20px;
        }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--bg-elevated);
            border-radius: var(--radius-md);
            font-size: 14px;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--accent);
        }
        .form-group textarea { min-height: 80px; resize: vertical; }
        .questions-list { margin-top: 12px; }
        .question-input {
            display: flex;
            gap: 8px;
            margin-bottom: 8px;
        }
        .question-input input { flex: 1; }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .btn-primary { background: var(--accent); color: white; }
        .btn-primary:hover { transform: translateY(-2px); }
        .btn-success { background: linear-gradient(135deg, var(--success), #059669); color: white; }
        .btn-danger { background: linear-gradient(135deg, var(--danger), #dc2626); color: white; }
        .btn-sm { padding: 8px 16px; font-size: 12px; }
        .template-card, .application-card {
            background: var(--bg-elevated);
            border: 1px solid var(--bg-elevated);
            border-radius: var(--radius-md);
            padding: 20px;
            margin-bottom: 12px;
        }
        .template-card.inactive { opacity: 0.5; }
        .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
        .card-title { font-weight: 700; font-size: 16px; }
        .card-meta { font-size: 12px; color: var(--text-muted); margin-bottom: 8px; }
        .card-actions { display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap; }
        .badge {
            padding: 4px 10px;
            border-radius: var(--radius-lg);
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-pending { background: rgba(251, 191, 36, 0.2); color: #f0b232; }
        .badge-under_review { background: rgba(59, 130, 246, 0.2); color: #93c5fd; }
        .badge-approved { background: rgba(16, 185, 129, 0.2); color: #4ade80; }
        .badge-denied { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .empty-state { text-align: center; padding: 60px; color: var(--text-muted); }
        .answer-item { background: var(--bg-card); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 12px; }
        .answer-item strong { display: block; margin-bottom: 4px; color: var(--text-secondary); font-size: 13px; }
        .page-actions { margin-bottom: 24px; }
        @media (max-width: 768px) {
            .card-actions { flex-direction: column; }
            .card-actions .btn { width: 100%; }
        }
    </style>
</head>
<body>
    <?php $current_page = 'admin_apps'; include '../includes/navbar.php'; ?>

    <div class="container">
        <div class="page-actions">
            <a href="/apply" target="_blank" rel="noopener noreferrer" class="btn btn-primary">📋 View Public Form</a>
        </div>
        
        <?php showPageToasts(); ?>

        <div class="tabs">
            <a href="?status=pending" class="tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                Pending <span class="count"><?php echo $stats['pending'] ?? 0; ?></span>
            </a>
            <a href="?status=under_review" class="tab <?php echo $status_filter === 'under_review' ? 'active' : ''; ?>">
                Under Review <span class="count"><?php echo $stats['under_review'] ?? 0; ?></span>
            </a>
            <a href="?status=approved" class="tab <?php echo $status_filter === 'approved' ? 'active' : ''; ?>">
                Approved <span class="count"><?php echo $stats['approved'] ?? 0; ?></span>
            </a>
            <a href="?status=denied" class="tab <?php echo $status_filter === 'denied' ? 'active' : ''; ?>">
                Denied <span class="count"><?php echo $stats['denied'] ?? 0; ?></span>
            </a>
        </div>

        <div class="grid">
            <div class="section <?php echo !$can_manage_templates ? 'permission-locked' : ''; ?>">
                <h2>Application Templates</h2>
                
                <form method="POST" style="margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--bg-elevated);">
                    <?php echo csrfField(); ?>
                    <div class="form-group">
                        <label>Department *</label>
                        <select name="department_id" required>
                            <option value="">Select Department</option>
                            <?php while ($dept = $departments->fetch_assoc()): ?>
                                <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Template Title *</label>
                        <input type="text" name="title" required placeholder="e.g., Police Officer Application">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" placeholder="Brief description of the position..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Requirements</label>
                        <textarea name="requirements" placeholder="List any requirements..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Application Questions</label>
                        <div class="questions-list" id="questions-list">
                            <div class="question-input">
                                <input type="text" name="questions[]" placeholder="Question 1">
                            </div>
                            <div class="question-input">
                                <input type="text" name="questions[]" placeholder="Question 2">
                            </div>
                            <div class="question-input">
                                <input type="text" name="questions[]" placeholder="Question 3">
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm" style="background: var(--bg-elevated); margin-top: 8px;" onclick="addQuestion()">+ Add Question</button>
                    </div>
                    <button type="submit" name="create_template" class="btn btn-primary" style="width: 100%;">Create Template</button>
                </form>
                <?php if (!$can_manage_templates): ?>
                <?php permissionLockOverlay('You need the "Manage Templates" permission to create and edit templates.'); ?>
                <?php endif; ?>

                <?php if ($templates->num_rows > 0): ?>
                    <?php while ($template = $templates->fetch_assoc()): ?>
                        <div class="template-card <?php echo !$template['is_active'] ? 'inactive' : ''; ?>">
                            <div class="card-header">
                                <div class="card-title"><?php echo htmlspecialchars($template['title']); ?></div>
                                <?php if ($template['pending_count'] > 0): ?>
                                    <span class="badge badge-pending"><?php echo $template['pending_count']; ?> pending</span>
                                <?php endif; ?>
                            </div>
                            <div class="card-meta">
                                <?php echo htmlspecialchars($template['dept_name']); ?> •
                                <?php echo count(json_decode($template['questions'], true)); ?> questions
                                <?php if (!$template['is_active']): ?> • INACTIVE<?php endif; ?>
                            </div>
                            <div class="card-actions">
                                <?php if ($can_manage_templates): ?>
                                <button type="button" class="btn btn-sm" style="background: var(--accent-muted);" onclick="editTemplate(<?php echo htmlspecialchars(json_encode([
                                    'id' => $template['id'],
                                    'title' => $template['title'],
                                    'description' => $template['description'],
                                    'requirements' => $template['requirements'],
                                    'questions' => json_decode($template['questions'], true)
                                ])); ?>)">
                                    ✏️ Edit
                                </button>
                                <form method="POST" style="display: inline;">
                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                                    <button type="submit" name="toggle_template" class="btn btn-sm" style="background: var(--bg-elevated);">
                                        <?php echo $template['is_active'] ? '⏸️ Deactivate' : '▶️ Activate'; ?>
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this template? This cannot be undone.');">
                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                                    <button type="submit" name="delete_template" class="btn btn-sm btn-danger">
                                        🗑️ Delete
                                    </button>
                                </form>
                                <?php else: ?>
                                <?php lockedButton('Edit', 'Manage Templates permission required'); ?>
                                <?php lockedButton('Toggle', 'Manage Templates permission required'); ?>
                                <?php lockedButton('Delete', 'Manage Templates permission required'); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">No templates yet</div>
                <?php endif; ?>
            </div>

            <div class="section">
                <h2><?php echo ucfirst(str_replace('_', ' ', $status_filter)); ?> Applications</h2>
                
                <?php if ($applications->num_rows > 0): ?>
                    <?php while ($app = $applications->fetch_assoc()): ?>
                        <div class="application-card">
                            <div class="card-header">
                                <div class="card-title"><?php echo htmlspecialchars($app['applicant_name']); ?></div>
                                <span class="badge badge-<?php echo $app['status']; ?>"><?php echo strtoupper(str_replace('_', ' ', $app['status'])); ?></span>
                            </div>
                            <div class="card-meta">
                                <?php echo htmlspecialchars($app['dept_name']); ?> - <?php echo htmlspecialchars($app['template_title']); ?><br>
                                📧 <?php echo htmlspecialchars($app['applicant_email']); ?>
                                <?php if ($app['applicant_discord']): ?> • 💬 <?php echo htmlspecialchars($app['applicant_discord']); ?><?php endif; ?><br>
                                Submitted: <?php echo date('M j, Y g:i A', strtotime($app['created_at'])); ?>
                                <?php if ($app['reviewer_name']): ?><br>Reviewed by: <?php echo htmlspecialchars($app['reviewer_name']); ?><?php endif; ?>
                            </div>
                            <div class="card-actions">
                                <button type="button" class="btn btn-sm btn-primary" onclick="viewApplication(<?php echo $app['id']; ?>, '<?php echo htmlspecialchars(addslashes($app['applicant_name'])); ?>', '<?php echo htmlspecialchars(addslashes($app['answers'])); ?>')">View Answers</button>
                                <?php if ($app['status'] === 'pending' || $app['status'] === 'under_review'): ?>
                                    <?php if ($can_review): ?>
                                        <?php 
                                        // Get department_id for this application
                                        $stmt_dept = $conn->prepare("SELECT t.department_id FROM application_templates t WHERE t.id = ?");
                                        $stmt_dept->bind_param("i", $app['template_id']);
                                        $stmt_dept->execute();
                                        $dept_row = $stmt_dept->get_result()->fetch_assoc();
                                        $app_dept_id = $dept_row['department_id'] ?? 0;
                                        $stmt_dept->close();
                                        ?>
                                        <button type="button" class="btn btn-sm btn-success" onclick="openApproveModal(<?php echo $app['id']; ?>, '<?php echo htmlspecialchars(addslashes($app['applicant_name'])); ?>', '<?php echo htmlspecialchars(addslashes($app['applicant_email'])); ?>', '<?php echo htmlspecialchars(addslashes($app['applicant_discord'] ?? '')); ?>', <?php echo $app_dept_id; ?>, '<?php echo htmlspecialchars(addslashes($app['dept_name'])); ?>')">Approve</button>
                                        <form method="POST" style="display: inline;">
                    <?php echo csrfField(); ?>
                                            <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                            <input type="hidden" name="status" value="denied">
                                            <button type="submit" name="review_application" class="btn btn-sm btn-danger">Deny</button>
                                        </form>
                                    <?php else: ?>
                                        <?php lockedButton('Approve', 'Review Applications permission required'); ?>
                                        <?php lockedButton('Deny', 'Review Applications permission required'); ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($can_review): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this application?')">
                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                    <button type="submit" name="delete_application" class="btn btn-sm" style="background: var(--bg-elevated);">Delete</button>
                                </form>
                                <?php else: ?>
                                <?php lockedButton('Delete', 'Review Applications permission required'); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">No <?php echo str_replace('_', ' ', $status_filter); ?> applications</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal" id="viewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title">Application</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div id="modal-answers"></div>
        </div>
    </div>

    <!-- Edit Template Modal -->
    <div class="modal" id="editTemplateModal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3>Edit Template</h3>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" id="editTemplateForm">
                    <?php echo csrfField(); ?>
                <input type="hidden" name="template_id" id="edit_template_id">
                <div class="form-group">
                    <label>Template Title *</label>
                    <input type="text" name="title" id="edit_title" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description"></textarea>
                </div>
                <div class="form-group">
                    <label>Requirements</label>
                    <textarea name="requirements" id="edit_requirements"></textarea>
                </div>
                <div class="form-group">
                    <label>Application Questions</label>
                    <div id="edit-questions-list"></div>
                    <button type="button" class="btn btn-sm" style="background: var(--bg-elevated); margin-top: 8px;" onclick="addEditQuestion()">+ Add Question</button>
                </div>
                <div style="display: flex; gap: 12px; margin-top: 20px;">
                    <button type="button" class="btn" style="background: var(--bg-elevated); flex: 1;" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" name="update_template" class="btn btn-primary" style="flex: 1;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function addQuestion() {
            const list = document.getElementById('questions-list');
            const count = list.children.length + 1;
            const div = document.createElement('div');
            div.className = 'question-input';
            div.innerHTML = '<input type="text" name="questions[]" placeholder="Question ' + count + '">';
            list.appendChild(div);
        }
        
        function addEditQuestion() {
            const list = document.getElementById('edit-questions-list');
            const count = list.children.length + 1;
            const div = document.createElement('div');
            div.className = 'question-input';
            div.innerHTML = '<input type="text" name="questions[]" placeholder="Question ' + count + '"><button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()" style="padding: 8px 12px;">×</button>';
            list.appendChild(div);
        }
        
        function editTemplate(data) {
            document.getElementById('edit_template_id').value = data.id;
            document.getElementById('edit_title').value = data.title || '';
            document.getElementById('edit_description').value = data.description || '';
            document.getElementById('edit_requirements').value = data.requirements || '';
            
            // Populate questions
            const list = document.getElementById('edit-questions-list');
            list.innerHTML = '';
            if (data.questions && data.questions.length > 0) {
                data.questions.forEach((q, i) => {
                    const div = document.createElement('div');
                    div.className = 'question-input';
                    div.innerHTML = '<input type="text" name="questions[]" value="' + q.replace(/"/g, '&quot;') + '" placeholder="Question ' + (i + 1) + '"><button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()" style="padding: 8px 12px;">×</button>';
                    list.appendChild(div);
                });
            }
            
            document.getElementById('editTemplateModal').classList.add('active');
        }
        
        function closeEditModal() {
            document.getElementById('editTemplateModal').classList.remove('active');
        }

        function viewApplication(id, name, answersJson) {
            document.getElementById('modal-title').textContent = name + "'s Application";
            const answers = JSON.parse(answersJson);
            let html = '';
            for (const [question, answer] of Object.entries(answers)) {
                html += '<div class="answer-item"><strong>' + question + '</strong>' + answer + '</div>';
            }
            document.getElementById('modal-answers').innerHTML = html;
            document.getElementById('viewModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('viewModal').classList.remove('active');
        }

        document.getElementById('viewModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
        
        document.getElementById('editTemplateModal').addEventListener('click', function(e) {
            if (e.target === this) closeEditModal();
        });
        
        // Onboarding/Approve modal
        const ranksByDept = <?php echo json_encode($ranks_by_dept); ?>;
        
        function openApproveModal(appId, name, email, discord, deptId, deptName) {
            document.getElementById('approve_app_id').value = appId;
            document.getElementById('approve_dept_id').value = deptId;
            document.getElementById('approve_applicant_name').textContent = name;
            document.getElementById('approve_applicant_email').textContent = email;
            document.getElementById('approve_applicant_discord').textContent = discord || 'Not provided';
            document.getElementById('approve_dept_name').textContent = deptName;
            document.getElementById('onboard_join_date').value = new Date().toISOString().split('T')[0];
            
            // Populate ranks dropdown
            const rankSelect = document.getElementById('onboard_rank_id');
            rankSelect.innerHTML = '<option value="">Select Rank</option>';
            if (ranksByDept[deptId]) {
                ranksByDept[deptId].forEach(rank => {
                    const opt = document.createElement('option');
                    opt.value = rank.id;
                    opt.textContent = rank.name;
                    rankSelect.appendChild(opt);
                });
            }
            
            document.getElementById('approveModal').classList.add('active');
        }
        
        function closeApproveModal() {
            document.getElementById('approveModal').classList.remove('active');
        }
        
        function submitApproval(addToRoster) {
            document.getElementById('create_roster').value = addToRoster ? '1' : '0';
            
            if (addToRoster) {
                const rankId = document.getElementById('onboard_rank_id').value;
                if (!rankId) {
                    alert('Please select a rank for the new member.');
                    return;
                }
            }
            
            document.getElementById('approveForm').submit();
        }
        
        document.getElementById('approveModal').addEventListener('click', function(e) {
            if (e.target === this) closeApproveModal();
        });
    </script>
    
    <!-- Approve/Onboarding Modal -->
    <div class="modal" id="approveModal">
        <div class="modal-content" style="max-width: 550px;">
            <div class="modal-header">
                <h3>✅ Approve Application</h3>
                <button class="modal-close" onclick="closeApproveModal()">&times;</button>
            </div>
            
            <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: var(--radius-md); padding: 16px; margin-bottom: 20px;">
                <div style="font-weight: 700; font-size: 18px; margin-bottom: 8px;" id="approve_applicant_name"></div>
                <div style="font-size: 13px; color: var(--text-secondary);">
                    📧 <span id="approve_applicant_email"></span><br>
                    💬 <span id="approve_applicant_discord"></span><br>
                    🏢 <span id="approve_dept_name"></span>
                </div>
            </div>
            
            <form method="POST" id="approveForm">
                    <?php echo csrfField(); ?>
                <input type="hidden" name="review_application" value="1">
                <input type="hidden" name="application_id" id="approve_app_id">
                <input type="hidden" name="status" value="approved">
                <input type="hidden" name="create_roster" id="create_roster" value="0">
                <input type="hidden" name="onboard_dept_id" id="approve_dept_id">
                
                <div style="background: var(--bg-elevated); border-radius: var(--radius-md); padding: 20px; margin-bottom: 20px;">
                    <h4 style="margin-bottom: 16px; font-size: 14px; text-transform: uppercase; color: var(--text-muted);">📋 Add to Roster (Optional)</h4>
                    
                    <div class="form-group">
                        <label>Rank</label>
                        <select name="onboard_rank_id" id="onboard_rank_id">
                            <option value="">Select Rank</option>
                        </select>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Badge Number</label>
                            <input type="text" name="onboard_badge" id="onboard_badge" placeholder="Optional">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Callsign</label>
                            <input type="text" name="onboard_callsign" id="onboard_callsign" placeholder="Optional">
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 12px; margin-bottom: 0;">
                        <label>Join Date</label>
                        <input type="date" name="onboard_join_date" id="onboard_join_date">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Reviewer Notes (sent to applicant)</label>
                    <textarea name="reviewer_notes" placeholder="Optional message to include in approval email..."></textarea>
                </div>
                
                <div style="display: flex; gap: 12px;">
                    <button type="button" class="btn" style="background: var(--bg-elevated); flex: 1;" onclick="closeApproveModal()">Cancel</button>
                    <button type="button" class="btn btn-primary" style="flex: 1;" onclick="submitApproval(false)">Approve Only</button>
                    <button type="button" class="btn btn-success" style="flex: 1.5;" onclick="submitApproval(true)">Approve & Add to Roster</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
