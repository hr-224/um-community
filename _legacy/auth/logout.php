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

// Get theme colors before destroying session (config.php already called session_start)
$colors = getThemeColors();

// Remove active session from database
if (isset($_SESSION['session_token']) && isset($_SESSION['user_id'])) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("DELETE FROM active_sessions WHERE session_token = ? AND user_id = ?");
    if ($stmt) {
        $stmt->bind_param("si", $_SESSION['session_token'], $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
    }
    $conn->close();
}

// Clear Discord login token cookie
if (file_exists(__DIR__ . '/../includes/discord.php')) {
    require_once __DIR__ . '/../includes/discord.php';
    if (function_exists('clearLoginToken')) {
        clearLoginToken();
    }
}

// Clear all session data
$_SESSION = [];

// Delete session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

// Destroy the session
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>(function(){var m=document.cookie.match(/\bum_theme=([^;]+)/);document.documentElement.setAttribute('data-theme',m?m[1]:'dark');})();</script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --bg-base: #0a0a0a;
            --bg-card: #161616;
            --bg-elevated: #1c1c1c;
            --bg-input: #1a1a1a;
            --border: #2a2a2a;
            --accent: #5865F2;
            --accent-muted: rgba(88, 101, 242, 0.15);
            --text-primary: #f2f3f5;
            --text-secondary: #b5bac1;
            --text-muted: #80848e;
            --radius-md: 10px;
            --radius-lg: 14px;
        }
        [data-theme="light"] {
            --bg-base: #f0f2f5;
            --bg-card: #ffffff;
            --bg-elevated: #eaecf0;
            --border: #e0e2e8;
            --text-primary: #060607;
            --text-secondary: #313338;
            --text-muted: #4e5058;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        
        @keyframes progressBar {
            from { width: 0%; }
            to { width: 100%; }
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-base);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            -webkit-font-smoothing: antialiased;
            color: var(--text-primary);
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
        
        .logout-container {
            background: var(--bg-card);
            border: 1px solid var(--border);
            padding: 48px 40px;
            border-radius: var(--radius-lg);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 1;
            text-align: center;
            animation: fadeIn 0.5s ease;
        }
        
        .logout-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent), #7c3aed);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        
        .logout-icon {
            font-size: 64px;
            margin-bottom: 24px;
            animation: pulse 2s ease-in-out infinite;
        }
        
        h1 {
            color: var(--text-primary);
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 12px;
            letter-spacing: -0.02em;
        }
        
        .logout-message {
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 32px;
        }
        
        .spinner-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .spinner {
            width: 48px;
            height: 48px;
            border: 3px solid var(--border);
            border-top: 3px solid var(--accent);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        .progress-container {
            width: 100%;
            height: 4px;
            background: var(--bg-elevated);
            border-radius: var(--radius-md);
            overflow: hidden;
            margin-top: 24px;
        }
        
        .progress-bar {
            height: 100%;
            background: var(--accent);
            border-radius: var(--radius-md);
            animation: progressBar 3s ease-in-out forwards;
        }
        
        .status-text {
            color: var(--text-muted);
            font-size: 14px;
            margin-top: 20px;
            font-weight: 500;
            animation: slideUp 0.6s ease 0.6s both;
        }
        
        @media (max-width: 480px) {
            .logout-container {
                padding: 40px 30px;
            }
            h1 {
                font-size: 26px;
            }
            .logout-icon {
                font-size: 60px;
            }
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-icon">👋</div>
        <h1>Logging Out</h1>
        <p class="logout-message">Thanks for using <?php echo COMMUNITY_NAME; ?>. See you soon!</p>
        
        <div class="spinner-container">
            <div class="spinner"></div>
        </div>
        
        <div class="progress-container">
            <div class="progress-bar"></div>
        </div>
        
        <p class="status-text">Clearing session data...</p>
    </div>
    
    <script>
        const messages = [
            'Clearing session data...',
            'Securing your account...',
            'Logging you out...',
            'Almost done...'
        ];
        
        let messageIndex = 0;
        const statusText = document.querySelector('.status-text');
        
        const messageInterval = setInterval(() => {
            messageIndex++;
            if (messageIndex < messages.length) {
                statusText.style.opacity = '0';
                setTimeout(() => {
                    statusText.textContent = messages[messageIndex];
                    statusText.style.opacity = '1';
                }, 200);
            }
        }, 1000);
        
        statusText.style.transition = 'opacity 0.3s ease';
        
        setTimeout(() => {
            clearInterval(messageInterval);
            window.location.href = '/auth/login';
        }, 4000);
    </script>
</body>
</html>
