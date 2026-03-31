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
require_once 'config.php';
if (!defined('UM_FUNCTIONS_LOADED')) { require_once __DIR__ . '/includes/functions.php'; }
if (!defined('UM_EMAIL_LOADED')) { require_once __DIR__ . '/includes/email.php'; }

$conn = getDBConnection();
$message = '';
$error = '';
$success = false;

// Get logged in user data for prefilling
$prefill_name = '';
$prefill_email = '';
$prefill_discord = '';

if (isLoggedIn()) {
    $stmt = $conn->prepare("SELECT username, email, discord_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $logged_user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($logged_user) {
        $prefill_name = $logged_user['username'];
        $prefill_email = $logged_user['email'];
        $prefill_discord = $logged_user['discord_id'] ?? '';
    }
}

// Check if applications are enabled
if (getSetting('applications_enabled', '1') !== '1') {
    $error = 'Applications are currently closed.';
}

// Handle application submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['submit_application']) && !$error) {
    $template_id = intval($_POST['template_id']);
    $applicant_name = trim($_POST['applicant_name']);
    $applicant_email = trim($_POST['applicant_email']);
    $applicant_discord = trim($_POST['applicant_discord'] ?? '');
    
    // Get template questions
    $stmt = $conn->prepare("SELECT t.questions, t.title, d.name as dept_name FROM application_templates t JOIN departments d ON t.department_id = d.id WHERE t.id = ? AND t.is_active = TRUE");
    $stmt->bind_param("i", $template_id);
    $stmt->execute();
    $template = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($template) {
        $questions = json_decode($template['questions'], true);
        $answers = [];
        
        foreach ($questions as $index => $question) {
            $answers[$question] = $_POST['answer_' . $index] ?? '';
        }
        
        $answers_json = json_encode($answers);
        
        $stmt = $conn->prepare("INSERT INTO applications (template_id, applicant_name, applicant_email, applicant_discord, answers) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $template_id, $applicant_name, $applicant_email, $applicant_discord, $answers_json);
        $stmt->execute();
        $app_id = $stmt->insert_id;
        $stmt->close();
        
        // Build answers preview for Discord
        $answers_preview = '';
        $count = 0;
        foreach ($answers as $q => $a) {
            if ($count >= 3) {
                $answers_preview .= "\n... and more questions";
                break;
            }
            $answers_preview .= "Q: " . substr($q, 0, 50) . "\nA: " . substr($a, 0, 100) . "\n\n";
            $count++;
        }
        
        // Send Discord webhook with rich data
        sendDiscordWebhook('application_submitted', [
            'applicant_name' => $applicant_name,
            'email' => $applicant_email,
            'discord' => $applicant_discord,
            'department' => $template['dept_name'],
            'position' => $template['title'],
            'application_id' => $app_id,
            'answers_preview' => trim($answers_preview)
        ]);
        
        // Send confirmation email to applicant
        $community = getCommunityName();
        $site_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $email_subject = "Application Received - $community";
        $email_body = "<p style=\"margin: 0 0 16px 0;\">Dear <strong>" . htmlspecialchars($applicant_name) . "</strong>,</p>"
                    . "<p style=\"margin: 0 0 16px 0;\">Thank you for submitting your application for <strong>" . htmlspecialchars($template['dept_name']) . "</strong> (" . htmlspecialchars($template['title']) . ").</p>"
                    . "<table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" width=\"100%\" style=\"margin: 20px 0; background: var(--bg-primary); border-radius: var(--radius-md); border: 1px solid var(--bg-elevated);\">"
                    . "<tr><td style=\"padding: 20px;\">"
                    . "<table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" width=\"100%\">"
                    . "<tr><td style=\"padding: 6px 0; color: #94a3b8; font-size: 13px;\">Department</td><td style=\"padding: 6px 0; color: #f1f5f9; font-size: 13px; text-align: right; font-weight: 600;\">" . htmlspecialchars($template['dept_name']) . "</td></tr>"
                    . "<tr><td style=\"padding: 6px 0; color: #94a3b8; font-size: 13px;\">Position</td><td style=\"padding: 6px 0; color: #f1f5f9; font-size: 13px; text-align: right; font-weight: 600;\">" . htmlspecialchars($template['title']) . "</td></tr>"
                    . "<tr><td style=\"padding: 6px 0; color: #94a3b8; font-size: 13px;\">Status</td><td style=\"padding: 6px 0; font-size: 13px; text-align: right;\"><span style=\"display: inline-block; padding: 3px 12px; background: rgba(251,191,36,0.15); color: #fbbf24; border-radius: var(--radius-md); font-weight: 600; font-size: 12px;\">Under Review</span></td></tr>"
                    . "</table>"
                    . "</td></tr></table>"
                    . "<p style=\"margin: 0 0 16px 0;\">Our team will review your application and you'll receive another email once a decision has been made.</p>"
                    . "<p style=\"margin: 0; color: #94a3b8;\">If you have any questions, please don't hesitate to reach out.</p>";
        
        sendEmail($applicant_email, $email_subject, $email_body, [
            'icon' => '📋',
            'header_title' => 'Application Received',
            'cta_text' => 'Visit ' . $community,
            'cta_url' => $site_url,
            'footer_note' => 'You are receiving this email because you submitted an application at ' . htmlspecialchars($community) . '.'
        ]);
        
        $success = true;
        $message = 'Your application has been submitted successfully! A confirmation email has been sent to ' . $applicant_email . '. We will review it and get back to you.';
    } else {
        $error = 'Invalid application template.';
    }
}

// Get active templates grouped by department
$templates = $conn->query("
    SELECT t.*, d.name as dept_name, d.abbreviation, d.icon, d.color, d.logo_path
    FROM application_templates t
    JOIN departments d ON t.department_id = d.id
    WHERE t.is_active = TRUE
    ORDER BY d.name, t.title
");

$selected_template = null;
if (isset($_GET['template'])) {
    $tid = intval($_GET['template']);
    $stmt = $conn->prepare("
        SELECT t.*, d.name as dept_name, d.abbreviation, d.icon, d.color, d.logo_path
        FROM application_templates t
        JOIN departments d ON t.department_id = d.id
        WHERE t.id = ? AND t.is_active = TRUE
    ");
    $stmt->bind_param("i", $tid);
    $stmt->execute();
    $selected_template = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$community_name = getCommunityName();

// Helper to convert hex to rgba
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply - <?php echo $community_name; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php $colors = getThemeColors(); ?>
    <script>(function(){var m=document.cookie.match(/\bum_theme=([^;]+)/);document.documentElement.setAttribute('data-theme',m?m[1]:'dark');})();</script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --bg-base: #0a0a0a;
            --bg-primary: #0f0f0f;
            --bg-card: #161616;
            --bg-elevated: #1c1c1c;
            --bg-input: #1a1a1a;
            --bg-hover: #242424;
            --border: #2a2a2a;
            --accent: <?php echo $colors['primary'] ?? '#5865F2'; ?>;
            --accent-hover: #4752c4;
            --accent-muted: rgba(88, 101, 242, 0.15);
            --success: #23a559;
            --success-muted: rgba(35, 165, 89, 0.15);
            --danger: #da373c;
            --danger-muted: rgba(218, 55, 60, 0.15);
            --text-primary: #f2f3f5;
            --text-secondary: #b5bac1;
            --text-muted: #80848e;
            --text-faint: #4e5058;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
        }
        [data-theme="light"] {
            --bg-base: #f0f2f5;
            --bg-primary: #ffffff;
            --bg-card: #ffffff;
            --bg-elevated: #eaecf0;
            --bg-input: #fafafa;
            --bg-hover: #d4d7dc;
            --border: #e0e2e8;
            --text-primary: #060607;
            --text-secondary: #313338;
            --text-muted: #4e5058;
            --text-faint: #80848e;
        }
        .theme-icon-dark { display: inline; }
        .theme-icon-light { display: none; }
        [data-theme="light"] .theme-icon-dark { display: none; }
        [data-theme="light"] .theme-icon-light { display: inline; }
        .theme-transitioning, .theme-transitioning * {
            transition: background-color 0.25s ease, color 0.25s ease, border-color 0.25s ease !important;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-base);
            min-height: 100vh;
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
        }
        
        /* Background grid */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: 
                linear-gradient(rgba(88, 101, 242, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(88, 101, 242, 0.03) 1px, transparent 1px);
            background-size: 60px 60px;
            pointer-events: none;
        }
        
        .container { max-width: 900px; margin: 40px auto; padding: 0 24px; }
        
        .message {
            padding: 16px 20px;
            border-radius: var(--radius-md);
            margin-bottom: 24px;
            font-weight: 500;
            font-size: 14px;
        }
        .message.success {
            background: var(--success-muted);
            border: 1px solid rgba(35, 165, 89, 0.3);
            border-left: 3px solid var(--success);
            color: #4ade80;
        }
        .message.error {
            background: var(--danger-muted);
            border: 1px solid rgba(218, 55, 60, 0.3);
            border-left: 3px solid var(--danger);
            color: #f87171;
        }
        
        .section {
            background: var(--bg-card);
            border: 1px solid var(--border);
            padding: 32px;
            border-radius: var(--radius-lg);
            margin-bottom: 24px;
            position: relative;
        }
        .section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--accent);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        .section h2 {
            margin-bottom: 12px;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .section p {
            color: var(--text-muted);
            margin-bottom: 24px;
        }
        
        .templates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }
        .template-card {
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 24px;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s ease;
            display: block;
        }
        .template-card:hover {
            transform: translateY(-4px);
            border-color: var(--accent);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        }
        .template-icon { font-size: 36px; margin-bottom: 12px; }
        .template-dept { font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
        .template-title { font-size: 16px; font-weight: 700; margin: 8px 0; }
        .template-desc { font-size: 14px; color: var(--text-secondary); }
        
        .form-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 24px;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--border);
        }
        .form-header-icon { font-size: 44px; }
        .form-header h2 { font-size: 22px; font-weight: 700; margin-bottom: 4px; }
        .form-header p { color: var(--text-muted); margin: 0; font-size: 14px; }
        
        .requirements {
            background: rgba(240, 178, 50, 0.1);
            border: 1px solid rgba(240, 178, 50, 0.2);
            padding: 16px 20px;
            border-radius: var(--radius-md);
            margin-bottom: 24px;
        }
        .requirements h4 { color: #f0b232; margin-bottom: 8px; font-size: 14px; }
        .requirements p { color: var(--text-secondary); font-size: 14px; margin: 0; white-space: pre-wrap; }
        
        .form-group { margin-bottom: 24px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 13px;
            color: var(--text-secondary);
        }
        .form-group label.required::after {
            content: ' *';
            color: var(--danger);
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            font-size: 14px;
            font-family: inherit;
            background: var(--bg-input);
            color: var(--text-primary);
            transition: all 0.2s ease;
        }
        .form-group input::placeholder, .form-group textarea::placeholder {
            color: var(--text-muted);
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-muted);
        }
        .form-group textarea { min-height: 120px; resize: vertical; }
        .form-group small {
            display: block;
            margin-top: 6px;
            color: var(--text-muted);
            font-size: 12px;
        }
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            font-family: inherit;
            transition: all 0.2s ease;
        }
        .btn-primary {
            background: var(--accent);
            color: white;
            box-shadow: 0 4px 14px rgba(88, 101, 242, 0.35);
        }
        .btn-primary:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(88, 101, 242, 0.4);
        }
        .btn-secondary {
            background: var(--bg-elevated);
            color: var(--text-secondary);
            border: 1px solid var(--border);
            text-decoration: none;
        }
        .btn-secondary:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }
        .empty-state {
            text-align: center;
            padding: 60px;
            color: var(--text-muted);
        }
        .success-box {
            text-align: center;
            padding: 60px 40px;
        }
        .success-box .icon { font-size: 64px; margin-bottom: 20px; }
        .success-box h2 { margin-bottom: 12px; color: var(--success); }
        .success-box p { color: var(--text-secondary); margin-bottom: 24px; }
        
        /* Public page navbar */
        .public-navbar {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            padding: 0 24px;
            height: 64px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .public-navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        .public-navbar-logo {
            width: 36px;
            height: 36px;
            border-radius: var(--radius-md);
            object-fit: contain;
        }
        .public-navbar-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
        }
        .public-navbar-links {
            display: flex;
            gap: 8px;
        }
        .public-navbar-links a {
            color: var(--text-secondary);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        .public-navbar-links a:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }
        @media (max-width: 768px) {
            .container { margin: 16px auto; padding: 0 12px; }
            .section { padding: 20px; border-radius: var(--radius-lg); }
            .section h2 { font-size: 20px; }
            .public-navbar { padding: 0 12px; }
            .public-navbar-links { gap: 4px; }
            .public-navbar-links a { padding: 6px 10px; font-size: 13px; }
        }
        @media (max-width: 480px) {
            .container { padding: 0 8px; }
            .section { padding: 16px; }
        }
    
    
    /* Custom Scrollbar */
    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }
    ::-webkit-scrollbar-track {
        background: var(--bg-card);
    }
    ::-webkit-scrollbar-thumb {
        background: var(--border);
        border-radius: 4px;
    }
    ::-webkit-scrollbar-thumb:hover {
        background: var(--bg-elevated);
    }
    </style>
</head>
<body>
    <?php if (isLoggedIn()): ?>
        <?php include 'includes/navbar.php'; ?>
    <?php else: ?>
    <nav class="public-navbar">
        <a href="/" class="public-navbar-brand">
            <?php 
            $logo_path = getSetting('community_logo', '');
            $has_logo = !empty($logo_path) && file_exists($_SERVER['DOCUMENT_ROOT'] . $logo_path);
            if ($has_logo): ?>
                <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo" class="public-navbar-logo">
            <?php endif; ?>
            <span class="public-navbar-title"><?php echo htmlspecialchars($community_name); ?></span>
        </a>
        <div class="public-navbar-links">
            <button onclick="toggleTheme()" title="Toggle dark/light mode" style="background:var(--bg-elevated);border:1px solid var(--border);border-radius:var(--radius-sm);width:36px;height:36px;cursor:pointer;font-size:15px;display:flex;align-items:center;justify-content:center;color:var(--text-muted);">
                <span class="theme-icon-dark">🌙</span>
                <span class="theme-icon-light">☀️</span>
            </button>
            <a href="/auth/login">🔑 Member Login</a>
            <a href="/auth/register">📝 Register</a>
        </div>
    </nav>
    <?php endif; ?>

    <div class="container">
        <?php 
        $applications_closed = getSetting('applications_enabled', '1') !== '1';
        ?>
        
        <?php if ($error && !$success && !$applications_closed): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($applications_closed): ?>
            <div class="section" style="text-align: center; padding: 60px 40px;">
                <div style="font-size: 64px; margin-bottom: 24px;">🔒</div>
                <h2 style="margin-bottom: 16px; border: none; padding: 0;">Applications Currently Closed</h2>
                <p style="color: var(--text-secondary); font-size: 16px; max-width: 500px; margin: 0 auto;">
                    We are not accepting applications at this time. Please check back later or contact an administrator for more information.
                </p>
            </div>
        <?php elseif ($success): ?>
            <div class="section">
                <div class="success-box">
                    <div class="icon">✅</div>
                    <h2>Application Submitted!</h2>
                    <p><?php echo htmlspecialchars($message); ?></p>
                    <a href="/apply" class="btn btn-primary">Submit Another Application</a>
                </div>
            </div>
        <?php elseif ($selected_template): ?>
            <div class="section">
                <div class="form-header">
                    <div class="form-header-icon">
                        <?php if (!empty($selected_template['logo_path']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $selected_template['logo_path'])): ?>
                            <img src="<?php echo htmlspecialchars($selected_template['logo_path']); ?>" alt="" style="width: 56px; height: 56px; object-fit: contain; border-radius: var(--radius-sm);">
                        <?php else: ?>
                            <?php echo $selected_template['icon'] ?? '📋'; ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h2><?php echo htmlspecialchars($selected_template['title']); ?></h2>
                        <p><?php echo htmlspecialchars($selected_template['dept_name']); ?></p>
                    </div>
                </div>

                <?php if ($selected_template['description']): ?>
                    <p style="margin-bottom: 24px; color: var(--text-secondary);">
                        <?php echo nl2br(htmlspecialchars($selected_template['description'])); ?>
                    </p>
                <?php endif; ?>

                <?php if ($selected_template['requirements']): ?>
                    <div class="requirements">
                        <h4>⚠️ Requirements</h4>
                        <p><?php echo nl2br(htmlspecialchars($selected_template['requirements'])); ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="template_id" value="<?php echo $selected_template['id']; ?>">
                    
                    <div class="form-group">
                        <label class="required">Your Name</label>
                        <input type="text" name="applicant_name" required placeholder="Enter your full name" value="<?php echo htmlspecialchars($prefill_name); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Email Address</label>
                        <input type="email" name="applicant_email" required placeholder="your@email.com" value="<?php echo htmlspecialchars($prefill_email); ?>">
                        <small>We'll use this to contact you about your application</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Discord Username</label>
                        <input type="text" name="applicant_discord" placeholder="username#0000 or username" value="<?php echo htmlspecialchars($prefill_discord); ?>">
                    </div>

                    <?php 
                    $questions = json_decode($selected_template['questions'], true);
                    foreach ($questions as $index => $question): 
                    ?>
                        <div class="form-group">
                            <label class="required"><?php echo htmlspecialchars($question); ?></label>
                            <textarea name="answer_<?php echo $index; ?>" required placeholder="Your answer..."></textarea>
                        </div>
                    <?php endforeach; ?>

                    <div style="display: flex; gap: 12px; margin-top: 32px;">
                        <a href="/apply" class="btn btn-secondary" style="text-decoration: none;">← Back</a>
                        <button type="submit" name="submit_application" class="btn btn-primary" style="flex: 1;">Submit Application</button>
                    </div>
                </form>
            </div>
        <?php elseif (!$applications_closed): ?>
            <div class="section">
                <h2>Join Our Community</h2>
                <p>Select a department below to begin your application.</p>

                <?php if ($templates->num_rows > 0): ?>
                    <div class="templates-grid">
                        <?php while ($template = $templates->fetch_assoc()): ?>
                            <a href="?template=<?php echo $template['id']; ?>" class="template-card">
                                <div class="template-icon">
                                    <?php if (!empty($template['logo_path']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $template['logo_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($template['logo_path']); ?>" alt="" style="width: 48px; height: 48px; object-fit: contain; border-radius: var(--radius-sm);">
                                    <?php else: ?>
                                        <?php echo $template['icon'] ?? '📋'; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="template-dept"><?php echo htmlspecialchars($template['abbreviation']); ?></div>
                                <div class="template-title"><?php echo htmlspecialchars($template['title']); ?></div>
                                <?php if ($template['description']): ?>
                                    <div class="template-desc"><?php echo htmlspecialchars(substr($template['description'], 0, 100)); ?><?php if (strlen($template['description']) > 100): ?>...<?php endif; ?></div>
                                <?php endif; ?>
                            </a>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No open positions at this time. Check back later!</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <script>
    function toggleTheme() {
        var root = document.documentElement;
        var newTheme = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
        root.classList.add('theme-transitioning');
        root.setAttribute('data-theme', newTheme);
        document.cookie = 'um_theme=' + newTheme + '; path=/; max-age=31536000; SameSite=Lax';
        setTimeout(function() { root.classList.remove('theme-transitioning'); }, 300);
    }
    </script>
</body>
</html>
<?php $conn->close(); ?>
