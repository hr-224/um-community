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
 * UM Community Manager - Installation Wizard
 * 
 * This installer will guide you through setting up the system.
 * Delete the /install folder after installation is complete!
 */

session_start();

// Enable error reporting for debugging (will show in error_log)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Custom error handler to catch fatal errors
function installErrorHandler($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    error_log("Install Error [$errno]: $errstr in $errfile on line $errline");
    return true;
}
set_error_handler('installErrorHandler');

// Catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("Fatal Install Error: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
    }
});

// Check if already installed
if (file_exists('../config.php')) {
    $config_content = file_get_contents('../config.php');
    if (strpos($config_content, "'installed' => true") !== false || strpos($config_content, "define('INSTALLED', true)") !== false) {
        header('Location: ../auth/login');
        exit();
    }
}

// Initialize session variables
if (!isset($_SESSION['install_step'])) {
    $_SESSION['install_step'] = 1;
    $_SESSION['install_data'] = [];
}

$step = $_SESSION['install_step'];
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Step 1: System Requirements - just proceed
    if (isset($_POST['check_requirements'])) {
        $requirements_met = true;
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            $error = 'PHP 7.4 or higher is required.';
            $requirements_met = false;
        }
        
        // Check required extensions
        $required_extensions = ['mysqli', 'json', 'session', 'mbstring'];
        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                $error = "Required PHP extension '$ext' is not loaded.";
                $requirements_met = false;
                break;
            }
        }
        
        // Check if config.php is writable or can be created
        $config_path = dirname(__DIR__) . '/config.php';
        if (file_exists($config_path) && !is_writable($config_path)) {
            $error = 'config.php exists but is not writable. Please set proper permissions.';
            $requirements_met = false;
        } elseif (!file_exists($config_path) && !is_writable(dirname(__DIR__))) {
            $error = 'Cannot create config.php. Please make the root directory writable.';
            $requirements_met = false;
        }
        
        // Check uploads directory
        $uploads_path = dirname(__DIR__) . '/uploads';
        if (!is_dir($uploads_path)) {
            @mkdir($uploads_path, 0755, true);
            @mkdir($uploads_path . '/logos', 0755, true);
            @mkdir($uploads_path . '/departments', 0755, true);
        }
        
        if ($requirements_met) {
            $_SESSION['install_step'] = 2;
            header('Location: index.php');
            exit();
        }
    }
    
    // Step 2: Database Configuration
    if (isset($_POST['test_database'])) {
        $db_host = trim($_POST['db_host']);
        $db_port = intval($_POST['db_port']);
        $db_user = trim($_POST['db_user']);
        $db_pass = $_POST['db_pass'];
        $db_name = trim($_POST['db_name']);
        
        // Test connection
        $conn = @new mysqli($db_host, $db_user, $db_pass, '', $db_port);
        
        if ($conn->connect_error) {
            $error = 'Database connection failed: ' . $conn->connect_error;
        } else {
            // Try to create database if it doesn't exist
            $conn->query("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Try to select the database
            if (!$conn->select_db($db_name)) {
                $error = 'Could not select or create database: ' . $db_name;
            } else {
                // Save to session and proceed
                $_SESSION['install_data']['db'] = [
                    'host' => $db_host,
                    'port' => $db_port,
                    'user' => $db_user,
                    'pass' => $db_pass,
                    'name' => $db_name
                ];
                $_SESSION['install_step'] = 3;
                $conn->close();
                header('Location: index.php');
                exit();
            }
            $conn->close();
        }
    }
    
    // Step 3: Admin Account
    if (isset($_POST['create_admin'])) {
        $admin_username = trim($_POST['admin_username']);
        $admin_email = trim($_POST['admin_email']);
        $admin_password = $_POST['admin_password'];
        $admin_password_confirm = $_POST['admin_password_confirm'];
        
        if (strlen($admin_username) < 3) {
            $error = 'Username must be at least 3 characters.';
        } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($admin_password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($admin_password !== $admin_password_confirm) {
            $error = 'Passwords do not match.';
        } else {
            $_SESSION['install_data']['admin'] = [
                'username' => $admin_username,
                'email' => $admin_email,
                'password' => password_hash($admin_password, PASSWORD_DEFAULT)
            ];
            $_SESSION['install_step'] = 4;
            header('Location: index.php');
            exit();
        }
    }
    
    // Step 4: Community Settings
    if (isset($_POST['save_community'])) {
        $community_name = trim($_POST['community_name']);
        
        if (empty($community_name)) {
            $error = 'Community name is required.';
        } else {
            $_SESSION['install_data']['community'] = [
                'name' => $community_name
            ];
            
            // Handle logo upload
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'];
                $file_type = $_FILES['logo']['type'];
                $file_size = $_FILES['logo']['size'];
                
                if (in_array($file_type, $allowed_types) && $file_size <= 2 * 1024 * 1024) {
                    $logo_tmp = $_FILES['logo']['tmp_name'];
                    $logo_name = 'community_logo_' . time() . '.' . pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                    $logo_path = dirname(__DIR__) . '/uploads/logos/' . $logo_name;
                    
                    if (!is_dir(dirname($logo_path))) mkdir(dirname($logo_path), 0755, true);
                    if (move_uploaded_file($logo_tmp, $logo_path)) {
                        $_SESSION['install_data']['community']['logo'] = '/uploads/logos/' . $logo_name;
                    }
                }
            }
            
            $_SESSION['install_step'] = 5;
            header('Location: index.php');
            exit();
        }
    }
    
    // Step 5: Install
    if (isset($_POST['run_install'])) {
        $install_result = runInstallation();
        if ($install_result === true) {
            $_SESSION['install_step'] = 6;
            header('Location: index.php');
            exit();
        } else {
            $error = $install_result;
        }
    }
}

// Installation function
function runInstallation() {
    $db = $_SESSION['install_data']['db'];
    $admin = $_SESSION['install_data']['admin'];
    $community = $_SESSION['install_data']['community'];
    
    // Connect to database
    $conn = new mysqli($db['host'], $db['user'], $db['pass'], $db['name'], $db['port']);
    if ($conn->connect_error) {
        return 'Database connection failed: ' . $conn->connect_error;
    }
    
    // Set charset
    $conn->set_charset('utf8mb4');
    
    // Read schema file
    $schema_file = __DIR__ . '/schema.sql';
    if (!file_exists($schema_file)) {
        return 'Schema file not found. Please ensure install/schema.sql exists.';
    }
    
    $schema = file_get_contents($schema_file);
    
    // Remove comments and split into statements
    $schema = preg_replace('/--.*$/m', '', $schema); // Remove single-line comments
    $schema = preg_replace('/\/\*.*?\*\//s', '', $schema); // Remove multi-line comments
    
    // Split by semicolon (but not inside strings)
    $statements = array_filter(array_map('trim', preg_split('/;[\r\n]+/', $schema)));
    
    // Execute each statement
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement) || $statement === ';') continue;
        
        // Skip USE statements as we're already connected to the database
        if (stripos($statement, 'USE ') === 0) continue;
        
        // Add semicolon back if needed
        if (substr($statement, -1) !== ';') {
            $statement .= ';';
        }
        
        if (!$conn->query($statement)) {
            // Ignore "already exists" and "duplicate" errors
            if (strpos($conn->error, 'already exists') === false && 
                strpos($conn->error, 'Duplicate') === false &&
                strpos($conn->error, 'duplicate') === false) {
                // Log the error but continue for non-critical errors
                error_log("SQL Error: " . $conn->error . " in statement: " . substr($statement, 0, 100));
            }
        }
    }
    
    // Insert admin user
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, is_admin, is_approved, created_at) VALUES (?, ?, ?, 1, 1, NOW()) ON DUPLICATE KEY UPDATE password = VALUES(password), is_admin = 1, is_approved = 1");
    if (!$stmt) {
        return 'Failed to prepare admin insert: ' . $conn->error;
    }
    $stmt->bind_param("sss", $admin['username'], $admin['email'], $admin['password']);
    if (!$stmt->execute()) {
        return 'Failed to create admin account: ' . $stmt->error;
    }
    $stmt->close();
    
    // Update system settings
    $settings = [
        ['community_name', $community['name'], 'text']
    ];
    
    if (isset($community['logo'])) {
        $settings[] = ['community_logo', $community['logo'], 'text'];
    }
    
    foreach ($settings as $setting) {
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        if ($stmt) {
            $stmt->bind_param("ssss", $setting[0], $setting[1], $setting[2], $setting[1]);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    $conn->close();
    
    // Generate config.php
    $config_content = generateConfigFile($db);
    $config_path = dirname(__DIR__) . '/config.php';
    
    if (file_put_contents($config_path, $config_content) === false) {
        return 'Failed to write config.php. Please check file permissions.';
    }
    
    return true;
}

// Generate config.php content
function generateConfigFile($db) {
    $config = <<<'CONFIGSTART'
<?php
// Session security hardening
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}
session_start();

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Installation marker - DO NOT REMOVE
define('INSTALLED', true);

// Database configuration
define('DB_HOST', '%DB_HOST%');
define('DB_PORT', %DB_PORT%);
define('DB_USER', '%DB_USER%');
define('DB_PASS', '%DB_PASS%');
define('DB_NAME', '%DB_NAME%');

// Favicon path
define('FAVICON_PATH', '/favicon.ico');

// Create database connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

// Update user activity/status (must be in config as it runs before functions.php on some pages)
function updateUserActivity() {
    if (!isset($_SESSION['user_id'])) return;
    
    try {
        $conn = getDBConnection();
        $user_id = $_SESSION['user_id'];
        
        // Check if user_status table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'user_status'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            // Only update last_activity, preserve the user's chosen status
            $stmt = $conn->prepare("INSERT INTO user_status (user_id, last_activity, status) VALUES (?, NOW(), 'online') ON DUPLICATE KEY UPDATE last_activity = NOW()");
            if ($stmt) {
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->close();
            }
        }
        
        $conn->close();
    } catch (Exception $e) {
        // Never let activity tracking crash the page
    }
}

// Load core function libraries
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/email.php';
CONFIGSTART;

    // Replace placeholders
    $config = str_replace('%DB_HOST%', addslashes($db['host']), $config);
    $config = str_replace('%DB_PORT%', $db['port'], $config);
    $config = str_replace('%DB_USER%', addslashes($db['user']), $config);
    $config = str_replace('%DB_PASS%', addslashes($db['pass']), $config);
    $config = str_replace('%DB_NAME%', addslashes($db['name']), $config);
    
    return $config;
}

// Go back handler
if (isset($_GET['back']) && $step > 1) {
    $_SESSION['install_step'] = $step - 1;
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install - UM Community Manager</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --bg-base: #0a0a0a;
            --bg-primary: #0f0f0f;
            --bg-card: #161616;
            --bg-elevated: #1c1c1c;
            --bg-hover: #242424;
            --border: #2a2a2a;
            --accent: #5865F2;
            --accent-hover: #4752c4;
            --accent-muted: rgba(88, 101, 242, 0.15);
            --success: #23a559;
            --success-muted: rgba(35, 165, 89, 0.15);
            --warning: #f0b232;
            --danger: #da373c;
            --danger-muted: rgba(218, 55, 60, 0.15);
            --text-primary: #f2f3f5;
            --text-secondary: #b5bac1;
            --text-muted: #80848e;
            --text-faint: #4e5058;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.5);
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-base);
            min-height: 100vh;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            -webkit-font-smoothing: antialiased;
        }
        
        /* Background grid pattern */
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
        
        .installer {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 560px;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            position: relative;
        }
        
        .installer-header {
            background: var(--bg-elevated);
            padding: 32px;
            text-align: center;
            border-bottom: 1px solid var(--border);
            position: relative;
        }
        
        .installer-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--accent);
        }
        
        .installer-logo {
            width: 56px;
            height: 56px;
            background: var(--accent);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin: 0 auto 16px;
            box-shadow: 0 8px 24px rgba(88, 101, 242, 0.3);
        }
        
        .installer-header h1 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 6px;
            letter-spacing: -0.02em;
        }
        
        .installer-header p {
            color: var(--text-muted);
            font-size: 14px;
        }
        
        /* Steps Progress */
        .steps {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            padding: 20px;
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border);
        }
        
        .step-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .step-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--border);
            transition: all 0.3s ease;
        }
        
        .step-dot.active {
            background: var(--accent);
            box-shadow: 0 0 12px var(--accent);
            transform: scale(1.2);
        }
        
        .step-dot.completed {
            background: var(--success);
        }
        
        .step-line {
            width: 24px;
            height: 2px;
            background: var(--border);
        }
        
        .step-line.completed {
            background: var(--success);
        }
        
        .installer-content {
            padding: 32px;
        }
        
        .step-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }
        
        .step-desc {
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 28px;
            line-height: 1.5;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 13px;
            color: var(--text-secondary);
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s ease;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-muted);
        }
        
        .form-group input::placeholder {
            color: var(--text-faint);
        }
        
        .form-hint {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 6px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        /* Buttons */
        .btn {
            width: 100%;
            padding: 14px 24px;
            border: none;
            border-radius: var(--radius-md);
            font-size: 15px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--accent);
            color: var(--text-primary);
            box-shadow: 0 4px 14px rgba(88, 101, 242, 0.35);
        }
        
        .btn-primary:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(88, 101, 242, 0.4);
        }
        
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: var(--bg-elevated);
            color: var(--text-secondary);
            border: 1px solid var(--border);
            margin-top: 12px;
        }
        
        .btn-secondary:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }
        
        /* Alert Boxes */
        .error-box {
            background: var(--danger-muted);
            border: 1px solid rgba(218, 55, 60, 0.3);
            color: #f87171;
            padding: 16px 20px;
            border-radius: var(--radius-md);
            margin-bottom: 24px;
            font-size: 14px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .error-box::before {
            content: '⚠️';
            flex-shrink: 0;
        }
        
        .success-box {
            background: var(--success-muted);
            border: 1px solid rgba(35, 165, 89, 0.3);
            color: #4ade80;
            padding: 16px 20px;
            border-radius: var(--radius-md);
            margin-bottom: 24px;
            font-size: 14px;
        }
        
        /* Requirements */
        .requirement {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            background: var(--bg-elevated);
            border-radius: var(--radius-md);
            margin-bottom: 10px;
            border-left: 3px solid var(--border);
            transition: all 0.2s ease;
        }
        
        .requirement.pass { 
            border-left-color: var(--success); 
            background: var(--success-muted);
        }
        
        .requirement.fail { 
            border-left-color: var(--danger);
            background: var(--danger-muted);
        }
        
        .requirement-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            flex-shrink: 0;
        }
        
        .requirement.pass .requirement-icon { 
            background: var(--success); 
            color: var(--text-primary);
        }
        
        .requirement.fail .requirement-icon { 
            background: var(--danger); 
            color: var(--text-primary);
        }
        
        .requirement-text strong {
            display: block;
            font-size: 14px;
            margin-bottom: 2px;
        }
        
        .requirement-text span {
            font-size: 12px;
            color: var(--text-muted);
        }
        
        /* Color Picker */
        
        
        
        /* Logo Upload */
        .logo-upload {
            border: 2px dashed var(--border);
            border-radius: var(--radius-md);
            padding: 24px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background: var(--bg-primary);
        }
        
        .logo-upload:hover {
            border-color: var(--accent);
            background: var(--accent-muted);
        }
        
        .logo-upload input {
            display: none;
        }
        
        .logo-upload-icon {
            font-size: 40px;
            margin-bottom: 12px;
            opacity: 0.7;
        }
        
        .logo-upload-text {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }
        
        .logo-upload-hint {
            font-size: 12px;
            color: var(--text-muted);
        }
        
        /* Section Cards */
        .settings-section {
            background: var(--bg-elevated);
            border-radius: var(--radius-md);
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--border);
        }
        
        .settings-section-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .preview-box {
            padding: 20px;
            border-radius: var(--radius-md);
            text-align: center;
            margin-top: 12px;
        }
        
        .preview-box .preview-label {
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 4px;
        }
        
        .preview-box .preview-hint {
            font-size: 12px;
            opacity: 0.7;
        }
        
        /* Summary */
        .summary-card {
            background: var(--bg-elevated);
            border-radius: var(--radius-md);
            padding: 20px;
            margin-bottom: 24px;
            border: 1px solid var(--border);
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .summary-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        
        .summary-item:first-child {
            padding-top: 0;
        }
        
        .summary-label {
            color: var(--text-muted);
            font-size: 14px;
        }
        
        .summary-value {
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        
        /* Complete Screen */
        .complete-screen {
            text-align: center;
            padding: 20px 0;
        }
        
        .complete-icon {
            width: 80px;
            height: 80px;
            background: var(--success-muted);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 24px;
            border: 3px solid var(--success);
        }
        
        .security-notice {
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 20px;
            text-align: left;
            margin: 24px 0;
        }
        
        .security-notice strong {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            color: var(--warning);
        }
        
        .security-notice ol {
            margin-left: 20px;
            color: var(--text-secondary);
        }
        
        .security-notice li {
            margin-bottom: 8px;
            line-height: 1.5;
        }
        
        .security-notice code {
            background: var(--bg-primary);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 13px;
            color: var(--accent);
        }
        
        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .installer {
                margin: 0;
                border-radius: var(--radius-md);
            }
            
            .installer-content {
                padding: 24px;
            }
            
            .installer-header {
                padding: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="installer">
        <div class="installer-header">
            <div class="installer-logo">🚀</div>
            <h1>UM Community Manager</h1>
            <p>Installation Wizard • Step <?php echo $step; ?> of 6</p>
        </div>
        
        <div class="steps">
            <?php for ($i = 1; $i <= 6; $i++): ?>
                <?php if ($i > 1): ?>
                    <div class="step-line <?php echo $i <= $step ? 'completed' : ''; ?>"></div>
                <?php endif; ?>
                <div class="step-dot <?php echo $i < $step ? 'completed' : ($i == $step ? 'active' : ''); ?>"></div>
            <?php endfor; ?>
        </div>
        
        <div class="installer-content">
            <?php if ($error): ?>
            <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($step === 1): ?>
            <!-- Step 1: Requirements -->
            <h2 class="step-title">System Requirements</h2>
            <p class="step-desc">Let's verify your server meets all the requirements to run UM Community Manager.</p>
            
            <?php
            $php_ok = version_compare(PHP_VERSION, '7.4.0', '>=');
            $mysqli_ok = extension_loaded('mysqli');
            $json_ok = extension_loaded('json');
            $session_ok = extension_loaded('session');
            $mbstring_ok = extension_loaded('mbstring');
            $config_writable = is_writable(dirname(__DIR__)) || (file_exists(dirname(__DIR__) . '/config.php') && is_writable(dirname(__DIR__) . '/config.php'));
            ?>
            
            <div class="requirement <?php echo $php_ok ? 'pass' : 'fail'; ?>">
                <div class="requirement-icon"><?php echo $php_ok ? '✓' : '✕'; ?></div>
                <div class="requirement-text">
                    <strong>PHP Version</strong>
                    <span>Required: 7.4+ • Current: <?php echo PHP_VERSION; ?></span>
                </div>
            </div>
            
            <div class="requirement <?php echo $mysqli_ok ? 'pass' : 'fail'; ?>">
                <div class="requirement-icon"><?php echo $mysqli_ok ? '✓' : '✕'; ?></div>
                <div class="requirement-text">
                    <strong>MySQLi Extension</strong>
                    <span>Required for database connectivity</span>
                </div>
            </div>
            
            <div class="requirement <?php echo $json_ok ? 'pass' : 'fail'; ?>">
                <div class="requirement-icon"><?php echo $json_ok ? '✓' : '✕'; ?></div>
                <div class="requirement-text">
                    <strong>JSON Extension</strong>
                    <span>Required for data processing</span>
                </div>
            </div>
            
            <div class="requirement <?php echo $mbstring_ok ? 'pass' : 'fail'; ?>">
                <div class="requirement-icon"><?php echo $mbstring_ok ? '✓' : '✕'; ?></div>
                <div class="requirement-text">
                    <strong>MBString Extension</strong>
                    <span>Required for text processing</span>
                </div>
            </div>
            
            <div class="requirement <?php echo $config_writable ? 'pass' : 'fail'; ?>">
                <div class="requirement-icon"><?php echo $config_writable ? '✓' : '✕'; ?></div>
                <div class="requirement-text">
                    <strong>Config Writable</strong>
                    <span>config.php must be writable</span>
                </div>
            </div>
            
            <form method="POST" style="margin-top: 28px;">
                <button type="submit" name="check_requirements" class="btn btn-primary" <?php echo (!$php_ok || !$mysqli_ok || !$json_ok || !$config_writable) ? 'disabled' : ''; ?>>
                    Continue →
                </button>
            </form>
            
            <?php elseif ($step === 2): ?>
            <!-- Step 2: Database -->
            <h2 class="step-title">Database Configuration</h2>
            <p class="step-desc">Enter your MySQL database credentials. The database will be created automatically if it doesn't exist.</p>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Database Host</label>
                        <input type="text" name="db_host" value="<?php echo $_SESSION['install_data']['db']['host'] ?? 'localhost'; ?>" required placeholder="localhost">
                    </div>
                    <div class="form-group">
                        <label>Port</label>
                        <input type="number" name="db_port" value="<?php echo $_SESSION['install_data']['db']['port'] ?? '3306'; ?>" required placeholder="3306">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Database Username</label>
                    <input type="text" name="db_user" value="<?php echo $_SESSION['install_data']['db']['user'] ?? ''; ?>" required placeholder="Enter username">
                </div>
                
                <div class="form-group">
                    <label>Database Password</label>
                    <input type="password" name="db_pass" value="" placeholder="Enter password">
                </div>
                
                <div class="form-group">
                    <label>Database Name</label>
                    <input type="text" name="db_name" value="<?php echo $_SESSION['install_data']['db']['name'] ?? 'community_manager'; ?>" required placeholder="community_manager">
                    <div class="form-hint">Will be created if it doesn't exist</div>
                </div>
                
                <button type="submit" name="test_database" class="btn btn-primary">Test Connection & Continue →</button>
                <a href="?back=1" class="btn btn-secondary">← Back</a>
            </form>
            
            <?php elseif ($step === 3): ?>
            <!-- Step 3: Admin Account -->
            <h2 class="step-title">Admin Account</h2>
            <p class="step-desc">Create your administrator account. You'll use these credentials to log in and manage your community.</p>
            
            <form method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="admin_username" value="<?php echo $_SESSION['install_data']['admin']['username'] ?? ''; ?>" required minlength="3" placeholder="Choose a username">
                </div>
                
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="admin_email" value="<?php echo $_SESSION['install_data']['admin']['email'] ?? ''; ?>" required placeholder="admin@example.com">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="admin_password" required minlength="6" placeholder="Min 6 characters">
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="admin_password_confirm" required minlength="6" placeholder="Confirm password">
                    </div>
                </div>
                
                <button type="submit" name="create_admin" class="btn btn-primary">Continue →</button>
                <a href="?back=1" class="btn btn-secondary">← Back</a>
            </form>
            
            <?php elseif ($step === 4): ?>
            <!-- Step 4: Community Settings -->
            <h2 class="step-title">Community Settings</h2>
            <p class="step-desc">Set up your community's name and branding.</p>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Community Name</label>
                    <input type="text" name="community_name" value="<?php echo $_SESSION['install_data']['community']['name'] ?? ''; ?>" required placeholder="My Awesome Community">
                </div>
                
                <div class="form-group">
                    <label>Community Logo <span style="font-weight: 400; color: var(--text-muted); font-size: 11px;">(Optional)</span></label>
                    <label class="logo-upload">
                        <input type="file" name="logo" accept="image/*" onchange="updateLogoPreview(this)">
                        <div class="logo-upload-icon" id="logo-preview">
                            <?php if (!empty($_SESSION['install_data']['community']['logo'])): ?>
                                <img src="<?php echo htmlspecialchars($_SESSION['install_data']['community']['logo']); ?>" style="max-width: 80px; max-height: 60px; border-radius: var(--radius-sm);">
                            <?php else: ?>
                                🖼️
                            <?php endif; ?>
                        </div>
                        <div class="logo-upload-text">Click to upload logo</div>
                        <div class="logo-upload-hint">PNG, JPG, GIF, SVG • Max 2MB</div>
                    </label>
                </div>
                
                <button type="submit" name="save_community" class="btn btn-primary">Continue →</button>
                <a href="?back=1" class="btn btn-secondary">← Back</a>
            </form>
            
            <script>
            function updateLogoPreview(input) {
                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        document.getElementById('logo-preview').innerHTML = '<img src="' + e.target.result + '" style="max-width: 80px; max-height: 60px; border-radius: var(--radius-sm);">';
                    };
                    reader.readAsDataURL(input.files[0]);
                }
            }
            </script>
            
            <?php elseif ($step === 5): ?>
            <!-- Step 5: Confirm & Install -->
            <h2 class="step-title">Ready to Install</h2>
            <p class="step-desc">Review your settings before starting the installation.</p>
            
            <div class="summary-card">
                <div class="summary-item">
                    <span class="summary-label">Database</span>
                    <span class="summary-value"><?php echo htmlspecialchars($_SESSION['install_data']['db']['name']); ?>@<?php echo htmlspecialchars($_SESSION['install_data']['db']['host']); ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Admin Username</span>
                    <span class="summary-value"><?php echo htmlspecialchars($_SESSION['install_data']['admin']['username']); ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Admin Email</span>
                    <span class="summary-value"><?php echo htmlspecialchars($_SESSION['install_data']['admin']['email']); ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Community Name</span>
                    <span class="summary-value"><?php echo htmlspecialchars($_SESSION['install_data']['community']['name']); ?></span>
                </div>
            </div>
            
            <form method="POST">
                <button type="submit" name="run_install" class="btn btn-primary">🚀 Install Now</button>
                <a href="?back=1" class="btn btn-secondary">← Back</a>
            </form>
            
            <?php elseif ($step === 6): ?>
            <!-- Step 6: Complete -->
            <div class="complete-screen">
                <div class="complete-icon">🎉</div>
                <h2 class="step-title">Installation Complete!</h2>
                <p class="step-desc">Your community manager is ready to use.</p>
                
                <div class="security-notice">
                    <strong>⚠️ Important Security Steps</strong>
                    <ol>
                        <li>Delete the <code>/install</code> folder from your server</li>
                        <li>Set <code>config.php</code> to read-only (chmod 444)</li>
                        <li>Change the default admin password if needed</li>
                    </ol>
                </div>
                
                <a href="../auth/login" class="btn btn-primary">
                    Go to Login →
                </a>
            </div>
            <?php
            // Clear install session
            unset($_SESSION['install_step']);
            unset($_SESSION['install_data']);
            ?>
            
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
