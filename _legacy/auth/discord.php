<?php
/**
 * Discord OAuth Initiation
 * 
 * Redirects user to Discord for authorization
 * Usage: /auth/discord.php?action=login|register|link
 */

require_once '../config.php';
if (!defined('UM_FUNCTIONS_LOADED')) { require_once __DIR__ . '/../includes/functions.php'; }
require_once __DIR__ . '/../includes/discord.php';

$action = $_GET['action'] ?? 'login';

// Validate action
if (!in_array($action, ['login', 'register', 'link'])) {
    $action = 'login';
}

// For linking, user must be logged in
if ($action === 'link' && !isLoggedIn()) {
    $_SESSION['redirect_after_login'] = '/user/security';
    header('Location: /auth/login');
    exit;
}

// For linking, create a login token to persist session across OAuth redirect
if ($action === 'link' && isLoggedIn()) {
    // Create a login token so the session persists across the cross-site redirect
    createLoginToken(
        $_SESSION['user_id'],
        $_SESSION['username'],
        $_SESSION['is_admin'] ?? false
    );
}

// Check if Discord is configured
if (!isDiscordConfigured()) {
    toast('Discord login is not configured. Please contact the administrator.', 'error');
    header('Location: /auth/login');
    exit;
}

$settings = getDiscordSettings();

// Check if the requested action is allowed
if ($action === 'login' && !$settings['allow_login']) {
    toast('Discord login is currently disabled.', 'error');
    header('Location: /auth/login');
    exit;
}

if ($action === 'register' && !$settings['allow_registration']) {
    toast('Discord registration is currently disabled.', 'error');
    header('Location: /auth/register');
    exit;
}

// Store the return URL (validate it's a local path to prevent open redirect)
if (isset($_GET['return'])) {
    $returnPath = $_GET['return'];
    if (substr($returnPath, 0, 1) === '/' && substr($returnPath, 0, 2) !== '//') {
        $_SESSION['discord_return_url'] = $returnPath;
    } else {
        $_SESSION['discord_return_url'] = '/user/';
    }
} elseif ($action === 'link') {
    $_SESSION['discord_return_url'] = '/user/security';
} else {
    $_SESSION['discord_return_url'] = '/user/';
}

// Generate state token and redirect to Discord
$state = generateDiscordState();
$authUrl = getDiscordAuthUrl($state, $action);

// Ensure session is written before redirect
session_write_close();

header('Location: ' . $authUrl);
exit;
