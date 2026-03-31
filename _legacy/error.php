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
 * Custom Error Page
 * Displays themed error messages for 404, 403, 500, etc.
 */

// Try to load config if available
$config_exists = file_exists('config.php');
if ($config_exists) {
    $config_content = file_get_contents('config.php');
    if (strpos($config_content, "define('INSTALLED', true)") !== false) {
        require_once 'config.php';
if (!defined('UM_FUNCTIONS_LOADED')) { require_once __DIR__ . '/includes/functions.php'; }
if (!defined('UM_EMAIL_LOADED')) { require_once __DIR__ . '/includes/email.php'; }
    } else {
        $config_exists = false;
    }
}

$error_code = isset($_GET['code']) ? intval($_GET['code']) : 404;

$error_messages = [
    400 => ['title' => 'Bad Request', 'message' => 'The server could not understand your request.', 'icon' => '❌'],
    401 => ['title' => 'Unauthorized', 'message' => 'You need to log in to access this page.', 'icon' => '🔐'],
    403 => ['title' => 'Access Denied', 'message' => 'You do not have permission to access this resource.', 'icon' => '🚫'],
    404 => ['title' => 'Page Not Found', 'message' => 'The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.', 'icon' => '🔍'],
    500 => ['title' => 'Server Error', 'message' => 'Something went wrong on our end. Please try again later.', 'icon' => '⚠️'],
    503 => ['title' => 'Service Unavailable', 'message' => 'The service is temporarily unavailable. Please try again later.', 'icon' => '🔧']
];

$error = $error_messages[$error_code] ?? $error_messages[404];
$community_name = $config_exists ? getCommunityName() : 'UM Community Manager';

// Get theme colors if available
if ($config_exists && function_exists('getThemeColors')) {
    $colors = getThemeColors();
} else {
    $colors = [
        'primary' => 'var(--accent)',
        'secondary' => 'var(--accent-hover)',
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $error_code; ?> - <?php echo $error['title']; ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --bg-base: #0a0a0a;
            --bg-card: #161616;
            --border: #2a2a2a;
            --accent: #5865F2;
            --accent-hover: #4752c4;
            --text-primary: #f2f3f5;
            --text-secondary: #b5bac1;
            --text-muted: #80848e;
            --radius-md: 10px;
            --radius-lg: 14px;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-base);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
        }
        
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
        
        .error-container {
            text-align: center;
            padding: 40px;
            max-width: 500px;
        }
        
        .error-icon {
            font-size: 72px;
            margin-bottom: 24px;
        }
        
        .error-code {
            font-size: 100px;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1;
            margin-bottom: 16px;
            letter-spacing: -0.04em;
        }
        
        .error-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 12px;
        }
        
        .error-message {
            font-size: 15px;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 32px;
        }
        
        .error-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 28px;
            border-radius: var(--radius-md);
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, <?php echo $colors['primary']; ?>, <?php echo $colors['secondary']; ?>);
            color: white;
            box-shadow: 0 4px 15px rgba(88, 101, 242, 0.4);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(88, 101, 242, 0.5);
        }
        
        .btn-secondary {
            background: var(--bg-elevated);
            color: white;
            border: 1px solid var(--border);
        }
        
        .btn-secondary:hover {
            background: var(--bg-hover);
        }
        
        .community-name {
            margin-top: 48px;
            font-size: 14px;
            color: var(--text-faint);
        }
        @media (max-width: 480px) {
            .error-container { padding: 24px; margin: 16px; }
            .error-code { font-size: 64px; }
            .error-title { font-size: 20px; }
        }
    
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon"><?php echo $error['icon']; ?></div>
        <div class="error-code"><?php echo $error_code; ?></div>
        <h1 class="error-title"><?php echo $error['title']; ?></h1>
        <p class="error-message"><?php echo $error['message']; ?></p>
        
        <div class="error-actions">
            <a href="/" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                Go Home
            </a>
            <a href="javascript:history.back()" class="btn btn-secondary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                Go Back
            </a>
        </div>
        
        <p class="community-name"><?php echo htmlspecialchars($community_name); ?></p>
    </div>
</body>
</html>