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
define('DB_HOST', 'localhost');
define('DB_PORT', 3307);
define('DB_USER', 'root');
define('DB_PASS', 'AlexG103103!');
define('DB_NAME', 'gtav_rp_cms');

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