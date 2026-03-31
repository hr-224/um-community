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
/**
 * UM Community Manager - Email System
 * 
 * Professional HTML email templates with community branding,
 * SMTP email delivery, and template builder.
 * 
 * This file is loaded automatically via config.php on new installs.
 * For upgraded installs, it can also be loaded directly by pages.
 */

// Prevent double-loading
if (defined('UM_EMAIL_LOADED')) {
    return;
}
define('UM_EMAIL_LOADED', true);

// Send email using SMTP
function sendEmail($to, $subject, $body, $options = []) {
    $conn = getDBConnection();
    
    // Check if email notifications are enabled
    $email_enabled = getSetting('email_notifications_enabled', '1');
    if ($email_enabled !== '1') {
        $conn->close();
        return false;
    }
    
    // Get SMTP settings
    $tableCheck = $conn->query("SHOW TABLES LIKE 'smtp_settings'");
    if ($tableCheck->num_rows == 0) {
        $conn->close();
        return false;
    }
    
    $smtp = $conn->query("SELECT * FROM smtp_settings WHERE is_active = TRUE LIMIT 1")->fetch_assoc();
    $conn->close();
    
    if (!$smtp) {
        return false;
    }
    
    // Wrap body in branded HTML template unless already wrapped or raw flag set
    if (empty($options['raw']) && strpos($body, '<!DOCTYPE') === false) {
        $body = buildEmailHTML($subject, $body, $options);
    }
    
    // Use PHP's mail() as fallback or implement SMTP
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . $smtp['smtp_from_name'] . ' <' . $smtp['smtp_from_email'] . '>',
        'Reply-To: ' . $smtp['smtp_from_email'],
        'X-Mailer: PHP/' . phpversion()
    ];
    
    // Try to use SMTP if fsockopen is available
    if (function_exists('fsockopen') && !empty($smtp['smtp_host'])) {
        return sendSMTPEmail($smtp, $to, $subject, $body);
    }
    
    // Fallback to mail()
    return @mail($to, $subject, $body, implode("\r\n", $headers));
}

// Build professional branded HTML email
function buildEmailHTML($subject, $body_content, $options = []) {
    $community = getCommunityName();
    $colors = getThemeColors();
    $primary = $colors['primary'];
    $secondary = $colors['secondary'];
    $bg_dark = '#0a0a0a';
    $bg_card = '#161616';
    
    // Logo
    $logo_path = getSetting('community_logo', '');
    $site_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $logo_url = !empty($logo_path) ? $site_url . $logo_path : '';
    
    // Optional header accent icon/emoji
    $header_icon = $options['icon'] ?? '';
    $header_title = $options['header_title'] ?? $subject;
    
    // Optional CTA button
    $cta_text = $options['cta_text'] ?? '';
    $cta_url = $options['cta_url'] ?? '';
    
    // Optional footer note
    $footer_note = $options['footer_note'] ?? '';
    
    // Convert plain text line breaks to HTML
    if (strpos($body_content, '<') === false) {
        $body_content = nl2br(htmlspecialchars($body_content));
    }
    
    // Build logo HTML
    $logo_html = '';
    if (!empty($logo_url)) {
        $logo_html = '<img src="' . htmlspecialchars($logo_url) . '" alt="' . htmlspecialchars($community) . '" style="max-height: 60px; max-width: 200px; margin-bottom: 8px; display: block; margin-left: auto; margin-right: auto;">';
    }
    
    // Build CTA button
    $cta_html = '';
    if (!empty($cta_text) && !empty($cta_url)) {
        $cta_html = '
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 28px auto;">
            <tr>
                <td style="border-radius: var(--radius-sm); background: linear-gradient(135deg, ' . $primary . ', ' . $secondary . ');">
                    <a href="' . htmlspecialchars($cta_url) . '" target="_blank" style="display: inline-block; padding: 14px 36px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; font-size: 16px; font-weight: 600; color: var(--text-primary); text-decoration: none; border-radius: var(--radius-sm); letter-spacing: 0.3px;">' . htmlspecialchars($cta_text) . '</a>
                </td>
            </tr>
        </table>';
    }
    
    // Build footer note
    $footer_note_html = '';
    if (!empty($footer_note)) {
        $footer_note_html = '<p style="margin: 16px 0 0 0; padding-top: 16px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #94a3b8; line-height: 1.5;">' . $footer_note . '</p>';
    }
    
    // Build header icon
    $icon_html = '';
    if (!empty($header_icon)) {
        $icon_html = '<div style="font-size: 40px; margin-bottom: 8px; text-align: center;">' . $header_icon . '</div>';
    }
    
    $year = date('Y');
    
    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>' . htmlspecialchars($subject) . '</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
</head>
<body style="margin: 0; padding: 0; background-color: #0f172a; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">
    
    <!-- Preheader text (hidden) -->
    <div style="display: none; max-height: 0; overflow: hidden; font-size: 1px; line-height: 1px; color: #0f172a;">
        ' . htmlspecialchars(strip_tags(substr($body_content, 0, 120))) . '
    </div>
    
    <!-- Outer wrapper -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #0f172a;">
        <tr>
            <td align="center" style="padding: 32px 16px;">
                
                <!-- Main container -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="max-width: 600px; width: 100%;">
                    
                    <!-- Logo & Community Header -->
                    <tr>
                        <td align="center" style="padding: 0 0 24px 0;">
                            ' . $logo_html . '
                            <h2 style="margin: 0; font-size: 18px; font-weight: 700; color: var(--text-primary); letter-spacing: 1.5px; text-transform: uppercase; opacity: 0.9;">' . htmlspecialchars($community) . '</h2>
                        </td>
                    </tr>
                    
                    <!-- Content card -->
                    <tr>
                        <td>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #1e293b; border-radius: var(--radius-lg); border: 1px solid var(--bg-elevated); overflow: hidden;">
                                
                                <!-- Gradient accent bar -->
                                <tr>
                                    <td style="height: 4px; background: linear-gradient(90deg, ' . $primary . ', ' . $secondary . ', ' . ($colors['accent'] ?? $primary) . ');"></td>
                                </tr>
                                
                                <!-- Card header -->
                                <tr>
                                    <td style="padding: 32px 36px 0 36px; text-align: center;">
                                        ' . $icon_html . '
                                        <h1 style="margin: 0 0 4px 0; font-size: 22px; font-weight: 700; color: #f1f5f9; line-height: 1.3;">' . htmlspecialchars($header_title) . '</h1>
                                        <div style="width: 40px; height: 3px; background: linear-gradient(90deg, ' . $primary . ', ' . $secondary . '); margin: 12px auto 0 auto; border-radius: 2px;"></div>
                                    </td>
                                </tr>
                                
                                <!-- Card body -->
                                <tr>
                                    <td style="padding: 24px 36px 32px 36px;">
                                        <div style="font-size: 15px; line-height: 1.7; color: #cbd5e1;">
                                            ' . $body_content . '
                                        </div>
                                        ' . $cta_html . '
                                    </td>
                                </tr>
                                
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 24px 36px 0 36px; text-align: center;">
                            <p style="margin: 0; font-size: 13px; color: #64748b; line-height: 1.6;">
                                &copy; ' . $year . ' ' . htmlspecialchars($community) . '. All rights reserved.
                            </p>
                            ' . $footer_note_html . '
                            <p style="margin: 8px 0 0 0; font-size: 11px; color: #475569;">
                                <a href="' . $site_url . '" style="color: ' . $primary . '; text-decoration: none;">' . htmlspecialchars($site_url) . '</a>
                            </p>
                        </td>
                    </tr>
                    
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
}

// SMTP email sender
function sendSMTPEmail($smtp, $to, $subject, $body) {
    $port = $smtp['smtp_port'] ?? 587;
    $host = $smtp['smtp_host'];
    
    if ($smtp['smtp_encryption'] === 'ssl') {
        $host = 'ssl://' . $host;
    }
    
    $socket = @fsockopen($host, $port, $errno, $errstr, 30);
    if (!$socket) {
        return false;
    }
    
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '220') {
        fclose($socket);
        return false;
    }
    
    // EHLO
    fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
    $response = '';
    while ($line = fgets($socket, 515)) {
        $response .= $line;
        if (substr($line, 3, 1) == ' ') break;
    }
    
    // STARTTLS for TLS
    if ($smtp['smtp_encryption'] === 'tls') {
        fputs($socket, "STARTTLS\r\n");
        fgets($socket, 515);
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        
        fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }
    }
    
    // AUTH LOGIN
    fputs($socket, "AUTH LOGIN\r\n");
    fgets($socket, 515);
    fputs($socket, base64_encode($smtp['smtp_username']) . "\r\n");
    fgets($socket, 515);
    fputs($socket, base64_encode($smtp['smtp_password']) . "\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '235') {
        fclose($socket);
        return false;
    }
    
    // MAIL FROM
    fputs($socket, "MAIL FROM:<" . $smtp['smtp_from_email'] . ">\r\n");
    fgets($socket, 515);
    
    // RCPT TO
    fputs($socket, "RCPT TO:<" . $to . ">\r\n");
    fgets($socket, 515);
    
    // DATA
    fputs($socket, "DATA\r\n");
    fgets($socket, 515);
    
    // Email content
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . $smtp['smtp_from_name'] . " <" . $smtp['smtp_from_email'] . ">\r\n";
    $headers .= "To: " . $to . "\r\n";
    $headers .= "Subject: " . $subject . "\r\n";
    
    fputs($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
    fgets($socket, 515);
    
    // QUIT
    fputs($socket, "QUIT\r\n");
    fclose($socket);
    
    return true;
}

/**
 * Send welcome email to new user (auto-approved)
 */
function sendWelcomeEmail($email, $username) {
    $community = getCommunityName();
    $site_url = getSiteUrl();
    $login_url = $site_url . '/auth/login';
    
    $subject = "Welcome to {$community}!";
    
    $body = "
        <h2 style='color: var(--success); margin-bottom: 20px;'>🎉 Welcome to {$community}!</h2>
        
        <p>Hi <strong>{$username}</strong>,</p>
        
        <p>Your account has been created successfully and you're ready to get started!</p>
        
        <div style='background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: var(--radius-sm); padding: 20px; margin: 20px 0;'>
            <p style='margin: 0 0 10px 0;'><strong>Your Account Details:</strong></p>
            <p style='margin: 0;'>Username: <strong>{$username}</strong></p>
            <p style='margin: 5px 0 0 0;'>Email: <strong>{$email}</strong></p>
        </div>
        
        <p>You can now log in and explore all the features available to you.</p>
        
        <p style='margin: 25px 0;'>
            <a href='{$login_url}' style='display: inline-block; background: linear-gradient(135deg, var(--success), #059669); color: white; padding: 12px 30px; text-decoration: none; border-radius: var(--radius-sm); font-weight: 600;'>Log In Now</a>
        </p>
        
        <p>If you have any questions, feel free to reach out to our team.</p>
        
        <p>Welcome aboard!<br>The {$community} Team</p>
    ";
    
    return sendEmail($email, $subject, $body, ['preheader' => "Your account is ready!"]);
}

/**
 * Send pending approval email to new user
 */
function sendPendingApprovalEmail($email, $username) {
    $community = getCommunityName();
    $site_url = getSiteUrl();
    
    $subject = "Registration Received - {$community}";
    
    $body = "
        <h2 style='color: #f59e0b; margin-bottom: 20px;'>⏳ Registration Received</h2>
        
        <p>Hi <strong>{$username}</strong>,</p>
        
        <p>Thank you for registering at <strong>{$community}</strong>!</p>
        
        <div style='background: #fffbeb; border: 1px solid #fde68a; border-radius: var(--radius-sm); padding: 20px; margin: 20px 0;'>
            <p style='margin: 0 0 10px 0;'><strong>📋 What happens next?</strong></p>
            <p style='margin: 0;'>Your account is currently pending approval by our administrators. You will receive another email once your account has been reviewed.</p>
        </div>
        
        <p><strong>Your Account Details:</strong></p>
        <ul style='margin: 10px 0;'>
            <li>Username: <strong>{$username}</strong></li>
            <li>Email: <strong>{$email}</strong></li>
            <li>Status: <span style='color: #f59e0b; font-weight: 600;'>Pending Approval</span></li>
        </ul>
        
        <p>This process usually takes 24-48 hours. Thank you for your patience!</p>
        
        <p>Best regards,<br>The {$community} Team</p>
    ";
    
    return sendEmail($email, $subject, $body, ['preheader' => "Your registration is being reviewed"]);
}

/**
 * Send account approved email
 */
function sendAccountApprovedEmail($email, $username) {
    $community = getCommunityName();
    $site_url = getSiteUrl();
    $login_url = $site_url . '/auth/login';
    
    $subject = "Account Approved - {$community}";
    
    $body = "
        <h2 style='color: var(--success); margin-bottom: 20px;'>✅ Your Account Has Been Approved!</h2>
        
        <p>Hi <strong>{$username}</strong>,</p>
        
        <p>Great news! Your account at <strong>{$community}</strong> has been approved by our administrators.</p>
        
        <div style='background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: var(--radius-sm); padding: 20px; margin: 20px 0;'>
            <p style='margin: 0;'>🎉 You now have full access to all features!</p>
        </div>
        
        <p style='margin: 25px 0;'>
            <a href='{$login_url}' style='display: inline-block; background: linear-gradient(135deg, var(--success), #059669); color: white; padding: 12px 30px; text-decoration: none; border-radius: var(--radius-sm); font-weight: 600;'>Log In Now</a>
        </p>
        
        <p>We're excited to have you as part of our community!</p>
        
        <p>Welcome aboard!<br>The {$community} Team</p>
    ";
    
    return sendEmail($email, $subject, $body, ['preheader' => "You can now log in!"]);
}
