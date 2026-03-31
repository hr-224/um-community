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
requireAdmin();

$conn = getDBConnection();
$community = getCommunityName();
$site_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

// Available email templates
$templates = [
    'password_reset' => 'Password Reset',
    'app_received' => 'Application Received',
    'app_approved' => 'Application Approved',
    'app_denied' => 'Application Denied',
    'smtp_test' => 'SMTP Test'
];

$selected = $_GET['template'] ?? 'password_reset';
if (!isset($templates[$selected])) $selected = 'password_reset';

// Build preview content based on selected template
switch ($selected) {
    case 'password_reset':
        $subject = "Password Reset Request - $community";
        $body = "<p style=\"margin: 0 0 16px 0;\">Hello <strong>JohnDoe</strong>,</p>"
              . "<p style=\"margin: 0 0 16px 0;\">We received a request to reset your password for your " . htmlspecialchars($community) . " account.</p>"
              . "<p style=\"margin: 0 0 16px 0;\">Click the button below to set a new password. This link will expire in <strong>1 hour</strong>.</p>"
              . "<p style=\"margin: 20px 0 0 0; padding: 16px; background: var(--bg-card); border-radius: var(--radius-sm); border-left: 3px solid #f59e0b; font-size: 13px; color: #94a3b8;\">If you didn't request this reset, you can safely ignore this email. Your password will remain unchanged.</p>";
        $options = [
            'icon' => '🔐',
            'header_title' => 'Password Reset',
            'cta_text' => 'Reset My Password',
            'cta_url' => $site_url . '/auth/reset_password?token=example123',
            'footer_note' => 'This link expires in 1 hour. If the button doesn\'t work, copy and paste this URL into your browser: ' . $site_url . '/auth/reset_password?token=example123'
        ];
        break;
        
    case 'app_received':
        $subject = "Application Received - $community";
        $body = "<p style=\"margin: 0 0 16px 0;\">Dear <strong>Jane Smith</strong>,</p>"
              . "<p style=\"margin: 0 0 16px 0;\">Thank you for submitting your application for <strong>Los Santos Police Department</strong> (Patrol Officer).</p>"
              . "<table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" width=\"100%\" style=\"margin: 20px 0; background: var(--bg-primary); border-radius: var(--radius-md); border: 1px solid var(--bg-elevated);\">"
              . "<tr><td style=\"padding: 20px;\">"
              . "<table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" width=\"100%\">"
              . "<tr><td style=\"padding: 6px 0; color: #94a3b8; font-size: 13px;\">Department</td><td style=\"padding: 6px 0; color: #f1f5f9; font-size: 13px; text-align: right; font-weight: 600;\">Los Santos Police Department</td></tr>"
              . "<tr><td style=\"padding: 6px 0; color: #94a3b8; font-size: 13px;\">Position</td><td style=\"padding: 6px 0; color: #f1f5f9; font-size: 13px; text-align: right; font-weight: 600;\">Patrol Officer</td></tr>"
              . "<tr><td style=\"padding: 6px 0; color: #94a3b8; font-size: 13px;\">Status</td><td style=\"padding: 6px 0; font-size: 13px; text-align: right;\"><span style=\"display: inline-block; padding: 3px 12px; background: rgba(251,191,36,0.15); color: #fbbf24; border-radius: var(--radius-md); font-weight: 600; font-size: 12px;\">Under Review</span></td></tr>"
              . "</table>"
              . "</td></tr></table>"
              . "<p style=\"margin: 0 0 16px 0;\">Our team will review your application and you'll receive another email once a decision has been made.</p>"
              . "<p style=\"margin: 0; color: #94a3b8;\">If you have any questions, please don't hesitate to reach out.</p>";
        $options = [
            'icon' => '📋',
            'header_title' => 'Application Received',
            'cta_text' => 'Visit ' . $community,
            'cta_url' => $site_url,
            'footer_note' => 'You are receiving this email because you submitted an application at ' . htmlspecialchars($community) . '.'
        ];
        break;
        
    case 'app_approved':
        $subject = "Application Approved - $community";
        $body = "<p style=\"margin: 0 0 16px 0;\">Dear <strong>Jane Smith</strong>,</p>"
              . "<p style=\"margin: 0 0 20px 0;\">Congratulations! Your application has been <strong style=\"color: #22c55e;\">approved</strong>.</p>"
              . "<table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" width=\"100%\" style=\"margin: 20px 0; background: rgba(34,197,94,0.08); border-radius: var(--radius-md); border: 1px solid rgba(34,197,94,0.2);\">"
              . "<tr><td style=\"padding: 20px;\">"
              . "<table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" width=\"100%\">"
              . "<tr><td style=\"padding: 6px 0; color: #94a3b8; font-size: 13px;\">Department</td><td style=\"padding: 6px 0; color: #f1f5f9; font-size: 13px; text-align: right; font-weight: 600;\">Los Santos Police Department</td></tr>"
              . "<tr><td style=\"padding: 6px 0; color: #94a3b8; font-size: 13px;\">Position</td><td style=\"padding: 6px 0; color: #f1f5f9; font-size: 13px; text-align: right; font-weight: 600;\">Patrol Officer</td></tr>"
              . "<tr><td style=\"padding: 6px 0; color: #94a3b8; font-size: 13px;\">Status</td><td style=\"padding: 6px 0; font-size: 13px; text-align: right;\"><span style=\"display: inline-block; padding: 3px 12px; background: rgba(34,197,94,0.15); color: #22c55e; border-radius: var(--radius-md); font-weight: 600; font-size: 12px;\">✓ Approved</span></td></tr>"
              . "</table>"
              . "</td></tr></table>"
              . "<p style=\"margin: 0 0 16px 0;\">Welcome to the team! We're excited to have you on board.</p>"
              . "<p style=\"margin: 0 0 16px 0; padding: 14px 16px; background: var(--bg-primary); border-radius: var(--radius-sm); border-left: 3px solid #22c55e; font-size: 14px;\"><strong style=\"color: #f1f5f9;\">Note:</strong> <span style=\"color: #cbd5e1;\">Please report for orientation this Saturday at 6PM EST.</span></p>";
        $options = [
            'icon' => '🎉',
            'header_title' => 'Application Approved!',
            'cta_text' => 'Log In to Get Started',
            'cta_url' => $site_url . '/auth/login',
            'footer_note' => 'Welcome to ' . htmlspecialchars($community) . '! If you don\'t have login credentials yet, check for a separate account creation email.'
        ];
        break;
        
    case 'app_denied':
        $subject = "Application Update - $community";
        $body = "<p style=\"margin: 0 0 16px 0;\">Dear <strong>Jane Smith</strong>,</p>"
              . "<p style=\"margin: 0 0 20px 0;\">Thank you for your interest in <strong>Los Santos Police Department</strong> (Patrol Officer).</p>"
              . "<table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" width=\"100%\" style=\"margin: 20px 0; background: var(--bg-primary); border-radius: var(--radius-md); border: 1px solid var(--bg-elevated);\">"
              . "<tr><td style=\"padding: 20px;\">"
              . "<table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" width=\"100%\">"
              . "<tr><td style=\"padding: 6px 0; color: #94a3b8; font-size: 13px;\">Department</td><td style=\"padding: 6px 0; color: #f1f5f9; font-size: 13px; text-align: right; font-weight: 600;\">Los Santos Police Department</td></tr>"
              . "<tr><td style=\"padding: 6px 0; color: #94a3b8; font-size: 13px;\">Position</td><td style=\"padding: 6px 0; color: #f1f5f9; font-size: 13px; text-align: right; font-weight: 600;\">Patrol Officer</td></tr>"
              . "<tr><td style=\"padding: 6px 0; color: #94a3b8; font-size: 13px;\">Status</td><td style=\"padding: 6px 0; font-size: 13px; text-align: right;\"><span style=\"display: inline-block; padding: 3px 12px; background: rgba(239,68,68,0.15); color: var(--danger); border-radius: var(--radius-md); font-weight: 600; font-size: 12px;\">Not Approved</span></td></tr>"
              . "</table>"
              . "</td></tr></table>"
              . "<p style=\"margin: 0 0 16px 0;\">After careful review, we regret to inform you that your application has not been approved at this time.</p>"
              . "<p style=\"margin: 0 0 16px 0; padding: 14px 16px; background: var(--bg-primary); border-radius: var(--radius-sm); border-left: 3px solid #f59e0b; font-size: 14px;\"><strong style=\"color: #f1f5f9;\">Feedback:</strong> <span style=\"color: #cbd5e1;\">We appreciate your effort but are looking for candidates with more RP experience at this time.</span></p>"
              . "<p style=\"margin: 0; color: #94a3b8;\">You're welcome to apply again in the future. We appreciate your interest!</p>";
        $options = [
            'icon' => '📋',
            'header_title' => 'Application Update',
            'footer_note' => 'You are receiving this email because you submitted an application at ' . htmlspecialchars($community) . '.'
        ];
        break;
        
    case 'smtp_test':
        $subject = "SMTP Test - $community";
        $body = "<p style=\"margin: 0 0 16px 0;\">This is a test email from <strong>" . htmlspecialchars($community) . "</strong>.</p>"
              . "<p style=\"margin: 0 0 16px 0;\">If you're reading this, your SMTP settings are configured correctly and emails are being delivered successfully! ✅</p>"
              . "<table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" width=\"100%\" style=\"margin: 20px 0; background: rgba(34,197,94,0.08); border-radius: var(--radius-md); border: 1px solid rgba(34,197,94,0.2);\">"
              . "<tr><td style=\"padding: 16px 20px; text-align: center;\">"
              . "<p style=\"margin: 0; color: #22c55e; font-weight: 600; font-size: 14px;\">✓ SMTP Connection Successful</p>"
              . "<p style=\"margin: 6px 0 0 0; color: #94a3b8; font-size: 12px;\">Sent at " . date('Y-m-d H:i:s T') . "</p>"
              . "</td></tr></table>";
        $options = [
            'icon' => '✉️',
            'header_title' => 'SMTP Test Email',
            'footer_note' => 'This was a test email triggered from the admin panel.'
        ];
        break;
}

// Generate the preview HTML
$preview_html = buildEmailHTML($subject, $body, $options);

// If requesting raw preview (iframe src)
if (isset($_GET['raw'])) {
    header('Content-Type: text/html; charset=UTF-8');
    echo $preview_html;
    exit();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Preview - <?php echo htmlspecialchars($community); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php outputThemeCSS(); ?>
    <?php include '../includes/styles.php'; ?>
    <style>
        .preview-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .preview-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
        }
        .preview-header h1 {
            margin: 0;
            font-size: 24px;
        }
        .template-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .template-tab {
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            text-decoration: none;
            color: var(--text-secondary);
            background: var(--bg-elevated);
            border: 1px solid var(--bg-elevated);
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .template-tab:hover {
            background: var(--bg-elevated);
            color: var(--text-primary);
        }
        .template-tab.active {
            background: var(--accent);
            color: var(--text-primary);
            border-color: var(--accent);
        }
        .email-meta {
            background: var(--bg-primary);
            border: 1px solid var(--bg-elevated);
            border-radius: var(--radius-md);
            padding: 16px 20px;
            margin-bottom: 20px;
        }
        .email-meta-row {
            display: flex;
            gap: 12px;
            padding: 6px 0;
            font-size: 14px;
        }
        .email-meta-label {
            color: #94a3b8;
            min-width: 70px;
            font-weight: 500;
        }
        .email-meta-value {
            color: #e2e8f0;
        }
        .preview-frame-wrapper {
            background: #fff;
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,0.3);
        }
        .preview-frame-toolbar {
            background: #f1f5f9;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid #e2e8f0;
        }
        .toolbar-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        .toolbar-dot.red { background: var(--danger); }
        .toolbar-dot.yellow { background: #fbbf24; }
        .toolbar-dot.green { background: #22c55e; }
        .toolbar-label {
            margin-left: 8px;
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
        }
        .preview-iframe {
            width: 100%;
            min-height: 700px;
            border: none;
            display: block;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 16px;
        }
        .back-link:hover { color: var(--text-primary); }
        @media (max-width: 768px) {
            .preview-header { flex-direction: column; align-items: flex-start; }
            .preview-iframe { min-height: 500px; }
        }
    </style>
</head>
<body>
    <?php $current_page = 'admin_smtp'; include '../includes/navbar.php'; ?>
    <div class="preview-container">
        <a href="/admin/smtp_settings" class="back-link">← Back to SMTP Settings</a>
        
        <div class="preview-header">
            <h1>📧 Email Template Preview</h1>
        </div>
        
        <div class="template-tabs">
            <?php foreach ($templates as $key => $label): ?>
                <a href="?template=<?php echo $key; ?>" class="template-tab <?php echo $selected === $key ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($label); ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <div style="margin-top: 20px;">
            <div class="email-meta">
                <div class="email-meta-row">
                    <span class="email-meta-label">Subject:</span>
                    <span class="email-meta-value"><?php echo htmlspecialchars($subject); ?></span>
                </div>
                <div class="email-meta-row">
                    <span class="email-meta-label">To:</span>
                    <span class="email-meta-value">recipient@example.com</span>
                </div>
                <div class="email-meta-row">
                    <span class="email-meta-label">From:</span>
                    <span class="email-meta-value"><?php echo htmlspecialchars($community); ?> &lt;noreply@example.com&gt;</span>
                </div>
            </div>
            
            <div class="preview-frame-wrapper">
                <div class="preview-frame-toolbar">
                    <span class="toolbar-dot red"></span>
                    <span class="toolbar-dot yellow"></span>
                    <span class="toolbar-dot green"></span>
                    <span class="toolbar-label">Email Preview — <?php echo htmlspecialchars($templates[$selected]); ?></span>
                </div>
                <iframe class="preview-iframe" src="?template=<?php echo $selected; ?>&raw=1"></iframe>
            </div>
        </div>
    </div>
</body>
</html>
