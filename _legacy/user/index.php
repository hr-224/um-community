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
 * User Directory Index
 * Redirects to the main dashboard
 */
ob_start();

require_once '../config.php';
if (!defined('UM_FUNCTIONS_LOADED')) { require_once __DIR__ . '/../includes/functions.php'; }
if (!defined('UM_EMAIL_LOADED')) { require_once __DIR__ . '/../includes/email.php'; }

// Include discord.php for login token check
if (file_exists(__DIR__ . '/../includes/discord.php')) {
    require_once __DIR__ . '/../includes/discord.php';
}

// Check for Discord login token (bypasses session issues after cross-site redirect)
if (!isLoggedIn() && function_exists('checkLoginToken')) {
    checkLoginToken();
}

// If logged in, redirect to dashboard
if (isLoggedIn()) {
    ob_end_clean();
    header('Location: /index');
    exit();
}

// If not logged in, redirect to login
ob_end_clean();
header('Location: /auth/login');
exit();
