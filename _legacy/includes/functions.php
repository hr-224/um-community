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
 * UM Community Manager - Core Functions
 * 
 * Authentication, permissions, audit logging, notifications,
 * Discord webhooks, badge counts, CSRF protection, rate limiting,
 * pagination, CSV export, and utility functions.
 * 
 * This file is loaded automatically via config.php on new installs.
 * For upgraded installs, it can also be loaded directly by pages.
 */

// Prevent double-loading
if (defined('UM_FUNCTIONS_LOADED')) {
    return;
}
define('UM_FUNCTIONS_LOADED', true);

// Application Version
define('UM_VERSION', '1.4.0-beta');
define('UM_VERSION_DATE', '2026-02-23');

/**
 * Get application version
 */
function getAppVersion() {
    return UM_VERSION;
}

// =====================================================
// IP ADDRESS DETECTION (Cloudflare Compatible)
// =====================================================

/**
 * Get real client IP address
 * Supports Cloudflare, X-Forwarded-For, and standard REMOTE_ADDR
 */
function getClientIP() {
    // Cloudflare
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    
    // Standard proxy headers
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Can contain multiple IPs - first one is the client
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    
    // X-Real-IP (some proxies)
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        if (filter_var($_SERVER['HTTP_X_REAL_IP'], FILTER_VALIDATE_IP)) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }
    }
    
    // Fallback to REMOTE_ADDR
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

// =====================================================
// HTMX HELPER FUNCTIONS
// =====================================================

/**
 * Check if current request is an htmx AJAX request
 */
function isHtmxRequest() {
    return !empty($_SERVER['HTTP_HX_REQUEST']);
}

/**
 * Check if request wants a full page or just a fragment
 */
function isHtmxBoosted() {
    return !empty($_SERVER['HTTP_HX_BOOSTED']);
}

/**
 * Send htmx response headers
 * @param string $message Toast message to display
 * @param string $type Toast type (success, error, warning, info)
 * @param string $redirect Optional URL to redirect to
 * @param string $refresh Set to 'true' to refresh the page
 */
function htmxResponse($message = null, $type = 'success', $redirect = null, $refresh = false) {
    if ($message) {
        header('X-Toast-Message: ' . rawurlencode($message));
        header('X-Toast-Type: ' . $type);
    }
    if ($redirect) {
        header('X-Redirect: ' . $redirect);
    }
    if ($refresh) {
        header('HX-Refresh: true');
    }
}

/**
 * Send htmx trigger event
 * @param string $event Event name to trigger
 * @param array $detail Optional event detail data
 */
function htmxTrigger($event, $detail = null) {
    if ($detail) {
        header('HX-Trigger: ' . json_encode([$event => $detail]));
    } else {
        header('HX-Trigger: ' . $event);
    }
}

/**
 * Render only the content fragment for htmx requests
 * Call at the start of a page that supports partial updates
 * Returns true if this is an htmx request (page should only output fragment)
 */
function htmxFragmentMode() {
    return isHtmxRequest() && !isHtmxBoosted();
}

// =====================================================
// SETTINGS & THEME FUNCTIONS
// =====================================================

// Get a setting value from the database
function getSetting($key, $default = '') {
    static $settings = null;
    
    if ($settings === null) {
        $settings = [];
        try {
            $conn = getDBConnection();
            $result = @$conn->query("SELECT setting_key, setting_value FROM system_settings");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
            }
            $conn->close();
        } catch (Exception $e) {
            // Return default on error
        }
    }
    
    return $settings[$key] ?? $default;
}

// Get community name
function getCommunityName() {
    return getSetting('community_name', 'UM Community');
}

/**
 * Get the base URL of the site
 */
function getSiteUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    return $protocol . '://' . $host;
}

// For backwards compatibility
if (!defined('COMMUNITY_NAME')) {
    define('COMMUNITY_NAME', getCommunityName());
}

// Favicon path fallback
if (!defined('FAVICON_PATH')) {
    define('FAVICON_PATH', '/favicon.ico');
}

// Get theme colors (fixed design system colors)
function getThemeColors() {
    return [
        'primary' => '#5865F2',
        'secondary' => '#4752c4',
        'accent' => '#7c3aed'
    ];
}

// Validate password against policy
function validatePassword($password) {
    $errors = [];
    
    $min_length = intval(getSetting('password_min_length', '8'));
    $require_upper = getSetting('password_require_uppercase', '1') === '1';
    $require_lower = getSetting('password_require_lowercase', '1') === '1';
    $require_number = getSetting('password_require_number', '1') === '1';
    $require_special = getSetting('password_require_special', '0') === '1';
    
    if (strlen($password) < $min_length) {
        $errors[] = "Password must be at least $min_length characters long.";
    }
    if ($require_upper && !preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter.";
    }
    if ($require_lower && !preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter.";
    }
    if ($require_number && !preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number.";
    }
    if ($require_special && !preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Password must contain at least one special character.";
    }
    
    return $errors;
}

// =====================================================
// USER STATUS & AUTH FUNCTIONS
// =====================================================

// Get user status
function getUserStatus($user_id) {
    $conn = getDBConnection();
    
    $tableCheck = $conn->query("SHOW TABLES LIKE 'user_status'");
    if ($tableCheck->num_rows == 0) {
        $conn->close();
        return ['status' => 'offline', 'custom_status' => ''];
    }
    
    $stmt = $conn->prepare("SELECT status, custom_status, last_activity FROM user_status WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    if (!$result) {
        return ['status' => 'offline', 'custom_status' => ''];
    }
    
    return $result;
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['is_approved']) && $_SESSION['is_approved'];
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
}

// Check if user has a specific permission
function hasPermission($permission_key, $department_id = null) {
    if (!isLoggedIn()) return false;
    if (isAdmin()) return true;
    
    $conn = getDBConnection();
    $user_id = $_SESSION['user_id'];
    
    $tableCheck = $conn->query("SHOW TABLES LIKE 'permissions'");
    if ($tableCheck->num_rows == 0) {
        $conn->close();
        return false;
    }
    
    $sql = "SELECT COUNT(*) as cnt FROM user_roles ur
            JOIN role_permissions rp ON ur.role_id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            WHERE ur.user_id = ? AND p.permission_key = ?
            AND (ur.expires_at IS NULL OR ur.expires_at > NOW())";
    
    if ($department_id !== null) {
        $sql .= " AND (ur.department_id IS NULL OR ur.department_id = ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isi", $user_id, $permission_key, $department_id);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $user_id, $permission_key);
    }
    
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    return $result['cnt'] > 0;
}

// Check if user has any of the specified permissions
function hasAnyPermission($permissions, $department_id = null) {
    foreach ($permissions as $perm) {
        if (hasPermission($perm, $department_id)) return true;
    }
    return false;
}

// Check if user has all of the specified permissions
function hasAllPermissions($permissions, $department_id = null) {
    foreach ($permissions as $perm) {
        if (!hasPermission($perm, $department_id)) return false;
    }
    return true;
}

// Get all permissions for a user
function getUserPermissions($user_id = null, $department_id = null) {
    if ($user_id === null) $user_id = $_SESSION['user_id'] ?? 0;
    
    $conn = getDBConnection();
    
    $tableCheck = $conn->query("SHOW TABLES LIKE 'permissions'");
    if ($tableCheck->num_rows == 0) {
        $conn->close();
        return [];
    }
    
    $sql = "SELECT DISTINCT p.permission_key FROM user_roles ur
            JOIN role_permissions rp ON ur.role_id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            WHERE ur.user_id = ?
            AND (ur.expires_at IS NULL OR ur.expires_at > NOW())";
    
    if ($department_id !== null) {
        $sql .= " AND (ur.department_id IS NULL OR ur.department_id = ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $department_id);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $permissions = [];
    while ($row = $result->fetch_assoc()) {
        $permissions[] = $row['permission_key'];
    }
    $stmt->close();
    $conn->close();
    
    return $permissions;
}

// Get user's roles
function getUserRoles($user_id = null, $department_id = null) {
    if ($user_id === null) $user_id = $_SESSION['user_id'] ?? 0;
    
    $conn = getDBConnection();
    
    $tableCheck = $conn->query("SHOW TABLES LIKE 'roles'");
    if ($tableCheck->num_rows == 0) {
        $conn->close();
        return [];
    }
    
    $sql = "SELECT r.*, ur.department_id as assigned_dept_id, d.name as dept_name
            FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            LEFT JOIN departments d ON ur.department_id = d.id
            WHERE ur.user_id = ?
            AND (ur.expires_at IS NULL OR ur.expires_at > NOW())";
    
    if ($department_id !== null) {
        $sql .= " AND (ur.department_id IS NULL OR ur.department_id = ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $department_id);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $roles = [];
    while ($row = $result->fetch_assoc()) {
        $roles[] = $row;
    }
    $stmt->close();
    $conn->close();
    
    return $roles;
}

// Require a specific permission
function requirePermission($permission_key, $department_id = null) {
    requireLogin();
    if (!hasPermission($permission_key, $department_id)) {
        header('Location: /index?error=unauthorized');
        exit();
    }
}

// Check if user can access applications
function canAccessApplications() {
    return isAdmin() || hasAnyPermission(['apps.view', 'apps.review', 'apps.templates.manage']);
}

// Check if user can manage a specific department
function canManageDepartment($department_id) {
    return isAdmin() || hasPermission('dept.manage', $department_id);
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /auth/login');
        exit();
    }
    // Force new accounts to complete setup before accessing anything else
    if (!empty($_SESSION['must_change_password'])) {
        $current_script = basename($_SERVER['SCRIPT_FILENAME'], '.php');
        if ($current_script !== 'setup_account' && $current_script !== 'logout') {
            header('Location: /auth/setup_account');
            exit();
        }
    }
    updateUserActivity();
    
    // Check license validity (silently for non-admins, redirect admin to fix)
    requireValidLicense();
}

// Redirect if not admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: /index');
        exit();
    }
}

// Check license validity and block access if invalid
function requireValidLicense() {
    // Skip license check on license settings page
    $current_script = basename($_SERVER['SCRIPT_FILENAME']);
    if ($current_script === 'license.php') {
        return true;
    }
    
    // Skip license check on login/logout pages
    $auth_pages = ['login.php', 'logout.php', 'forgot_password.php', 'reset_password.php', 'setup_account.php'];
    if (in_array($current_script, $auth_pages)) {
        return true;
    }
    
    // Skip in installer
    if (strpos($_SERVER['REQUEST_URI'], '/install') !== false) {
        return true;
    }
    
    try {
        // Include license functions if not already loaded
        if (!function_exists('checkLicense')) {
            $license_file = __DIR__ . '/license.php';
            if (!file_exists($license_file)) {
                return true; // License system not installed yet
            }
            require_once $license_file;
        }
        
        $conn = getDBConnection();
        
        // Check if license table exists
        $tableCheck = @$conn->query("SHOW TABLES LIKE 'license_info'");
        if (!$tableCheck || $tableCheck->num_rows == 0) {
            $conn->close();
            return true; // License table doesn't exist yet
        }
        
        $licenseCheck = checkLicense($conn, false);
        $conn->close();
        
        if (!$licenseCheck['valid']) {
            // Admin can access license settings page to fix the issue
            if (isAdmin()) {
                // Store the license error for display
                $_SESSION['license_error'] = $licenseCheck['error'];
                // Redirect admin to license settings
                header('Location: /admin/license');
                exit();
            } else {
                // Non-admin users see error page
                renderLicenseErrorPage($licenseCheck['error'], $licenseCheck['license']);
            }
        }
        
        // Store warning if present
        if (!empty($licenseCheck['warning'])) {
            $_SESSION['license_warning'] = $licenseCheck['warning'];
        }
        
        return true;
    } catch (Exception $e) {
        // If license check fails due to missing table or other error, allow access
        return true;
    }
}

// Log audit trail
function logAudit($action, $target_type = null, $target_id = null, $details = null) {
    $conn = getDBConnection();
    $user_id = $_SESSION['user_id'] ?? 0;
    
    $tableCheck = $conn->query("SHOW TABLES LIKE 'audit_log'");
    if ($tableCheck->num_rows == 0) {
        $conn->close();
        return;
    }
    
    $ip = getClientIP();
    $target_id = $target_id ?? 0;
    $target_type = $target_type ?? '';
    $details = $details ?? '';
    
    $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississ", $user_id, $action, $target_type, $target_id, $details, $ip);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

// Create notification
function createNotification($user_id, $title, $message, $type = 'info', $link = null) {
    $conn = getDBConnection();
    
    $tableCheck = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($tableCheck->num_rows == 0) {
        $conn->close();
        return false;
    }
    
    if ($link !== null) {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $title, $message, $type, $link);
    } else {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $title, $message, $type);
    }
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    
    return $result;
}

// Send Discord webhook
function sendDiscordWebhook($event_type, $data) {
    $conn = getDBConnection();
    
    // Get webhook settings
    $webhook_enabled = getSetting('discord_webhook_enabled', '0');
    if ($webhook_enabled !== '1') {
        return false;
    }
    
    // Determine which webhook URL to use based on event type
    $application_events = ['application', 'application_submitted', 'application_approved', 'application_denied', 'application_reviewed'];
    $is_application_event = in_array($event_type, $application_events);
    
    // Get appropriate webhook URL
    if ($is_application_event) {
        $webhook_url = getSetting('discord_webhook_applications_url', '');
        // Fall back to main webhook if applications webhook not set
        if (empty($webhook_url)) {
            $webhook_url = getSetting('discord_webhook_url', '');
        }
    } else {
        $webhook_url = getSetting('discord_webhook_url', '');
    }
    
    if (empty($webhook_url)) {
        return false;
    }
    
    $community_name = getSetting('community_name', 'Community');
    $site_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    
    // Color codes for different event types
    $colors = [
        'success' => 5763719,    // Green
        'error' => 15548997,     // Red
        'warning' => 16776960,   // Yellow
        'info' => 5814783,       // Blue
        'application' => 15105570, // Orange
        'approved' => 5763719,   // Green
        'denied' => 15548997,    // Red
        'admin' => 10181046,     // Purple
        'test' => 7506394        // Default blue-gray
    ];
    
    // Build rich embed based on event type
    $embed = [
        'timestamp' => date('c'),
        'footer' => [
            'text' => $community_name,
            'icon_url' => $site_url . getSetting('community_logo', '/favicon.ico')
        ]
    ];
    
    // Handle different event types with rich formatting
    switch ($event_type) {
        case 'application_submitted':
            $embed['title'] = '📋 New Application Received';
            $embed['color'] = $colors['application'];
            $embed['description'] = "A new application has been submitted and is awaiting review.";
            $embed['fields'] = [
                [
                    'name' => '👤 Applicant',
                    'value' => $data['applicant_name'] ?? 'Unknown',
                    'inline' => true
                ],
                [
                    'name' => '🏢 Department',
                    'value' => $data['department'] ?? 'Unknown',
                    'inline' => true
                ],
                [
                    'name' => '📝 Position',
                    'value' => $data['position'] ?? 'Unknown',
                    'inline' => true
                ]
            ];
            if (!empty($data['email'])) {
                $embed['fields'][] = [
                    'name' => '📧 Contact Email',
                    'value' => $data['email'],
                    'inline' => true
                ];
            }
            if (!empty($data['discord'])) {
                $embed['fields'][] = [
                    'name' => '💬 Discord',
                    'value' => $data['discord'],
                    'inline' => true
                ];
            }
            $embed['fields'][] = [
                'name' => '📅 Submitted',
                'value' => '<t:' . time() . ':R>',
                'inline' => true
            ];
            // Add preview of answers if available
            if (!empty($data['answers_preview'])) {
                $preview = substr($data['answers_preview'], 0, 500);
                if (strlen($data['answers_preview']) > 500) $preview .= '...';
                $embed['fields'][] = [
                    'name' => '📝 Application Preview',
                    'value' => "```\n" . $preview . "\n```",
                    'inline' => false
                ];
            }
            // Add action button (as a link in description)
            if (!empty($data['application_id'])) {
                $review_url = $site_url . '/admin/applications?view=' . $data['application_id'];
                $embed['description'] .= "\n\n**[🔍 Review Application](" . $review_url . ")**";
            }
            $embed['thumbnail'] = ['url' => 'https://cdn.discordapp.com/emojis/📋.png'];
            break;
            
        case 'application_approved':
            $embed['title'] = '✅ Application Approved';
            $embed['color'] = $colors['approved'];
            $embed['description'] = "An application has been approved!";
            $embed['fields'] = [
                [
                    'name' => '👤 Applicant',
                    'value' => $data['applicant_name'] ?? 'Unknown',
                    'inline' => true
                ],
                [
                    'name' => '🏢 Department',
                    'value' => $data['department'] ?? 'Unknown',
                    'inline' => true
                ],
                [
                    'name' => '👮 Reviewed By',
                    'value' => $data['reviewer'] ?? 'Unknown',
                    'inline' => true
                ]
            ];
            if (!empty($data['notes'])) {
                $embed['fields'][] = [
                    'name' => '📋 Notes',
                    'value' => $data['notes'],
                    'inline' => false
                ];
            }
            if (!empty($data['created_account'])) {
                $embed['fields'][] = [
                    'name' => '🎉 Account Created',
                    'value' => 'User account has been automatically created',
                    'inline' => false
                ];
            }
            break;
            
        case 'application_denied':
            $embed['title'] = '❌ Application Denied';
            $embed['color'] = $colors['denied'];
            $embed['description'] = "An application has been denied.";
            $embed['fields'] = [
                [
                    'name' => '👤 Applicant',
                    'value' => $data['applicant_name'] ?? 'Unknown',
                    'inline' => true
                ],
                [
                    'name' => '🏢 Department',
                    'value' => $data['department'] ?? 'Unknown',
                    'inline' => true
                ],
                [
                    'name' => '👮 Reviewed By',
                    'value' => $data['reviewer'] ?? 'Unknown',
                    'inline' => true
                ]
            ];
            if (!empty($data['reason'])) {
                $embed['fields'][] = [
                    'name' => '📋 Reason',
                    'value' => $data['reason'],
                    'inline' => false
                ];
            }
            break;
            
        case 'announcement':
            $embed['title'] = '📢 ' . ($data['title'] ?? 'New Announcement');
            $embed['color'] = $colors['info'];
            $embed['description'] = $data['message'] ?? $data['description'] ?? '';
            if (!empty($data['author'])) {
                $embed['author'] = [
                    'name' => 'Posted by ' . $data['author']
                ];
            }
            break;
            
        case 'user_registered':
            $embed['title'] = '👤 New User Registration';
            $embed['color'] = $colors['info'];
            $embed['fields'] = [
                [
                    'name' => 'Username',
                    'value' => $data['username'] ?? 'Unknown',
                    'inline' => true
                ],
                [
                    'name' => 'Email',
                    'value' => $data['email'] ?? 'Unknown',
                    'inline' => true
                ]
            ];
            break;
            
        case 'user_approved':
            $embed['title'] = '✅ User Approved';
            $embed['color'] = $colors['success'];
            $embed['fields'] = [
                [
                    'name' => 'Username',
                    'value' => $data['username'] ?? 'Unknown',
                    'inline' => true
                ],
                [
                    'name' => 'Approved By',
                    'value' => $data['approved_by'] ?? 'Admin',
                    'inline' => true
                ]
            ];
            break;
            
        case 'promotion':
            $embed['title'] = '🎉 Promotion';
            $embed['color'] = $colors['success'];
            $embed['description'] = ($data['username'] ?? 'A member') . ' has been promoted!';
            $embed['fields'] = [
                [
                    'name' => 'Previous Rank',
                    'value' => $data['old_rank'] ?? 'N/A',
                    'inline' => true
                ],
                [
                    'name' => 'New Rank',
                    'value' => $data['new_rank'] ?? 'N/A',
                    'inline' => true
                ],
                [
                    'name' => 'Department',
                    'value' => $data['department'] ?? 'N/A',
                    'inline' => true
                ]
            ];
            break;
            
        case 'test':
            $embed['title'] = '🧪 Webhook Test';
            $embed['color'] = $colors['test'];
            $embed['description'] = $data['message'] ?? 'This is a test notification to verify your Discord webhook is working correctly.';
            $embed['fields'] = [
                [
                    'name' => '✅ Status',
                    'value' => 'Connection successful!',
                    'inline' => true
                ],
                [
                    'name' => '⏰ Time',
                    'value' => '<t:' . time() . ':F>',
                    'inline' => true
                ]
            ];
            break;
            
        case 'application':
            // Generic application webhook (for test)
            if (!empty($data['test'])) {
                $embed['title'] = '🧪 Applications Webhook Test';
                $embed['color'] = $colors['test'];
                $embed['description'] = 'This is a test notification for the Applications webhook.';
                $embed['fields'] = [
                    [
                        'name' => '✅ Status',
                        'value' => 'Applications webhook working!',
                        'inline' => true
                    ],
                    [
                        'name' => '⏰ Time',
                        'value' => '<t:' . time() . ':F>',
                        'inline' => true
                    ]
                ];
            } else {
                $embed['title'] = $data['title'] ?? '📋 Application Update';
                $embed['color'] = $colors['application'];
                $embed['description'] = $data['description'] ?? $data['message'] ?? '';
            }
            break;
            
        default:
            // Generic event handling
            $embed['title'] = $data['title'] ?? ucfirst(str_replace('_', ' ', $event_type));
            $embed['description'] = $data['description'] ?? $data['message'] ?? '';
            $embed['color'] = $data['color'] ?? $colors[$data['type'] ?? 'info'] ?? $colors['info'];
            if (!empty($data['fields'])) {
                $embed['fields'] = $data['fields'];
            }
            break;
    }
    
    // Add author info if provided
    if (!empty($data['author_name'])) {
        $embed['author'] = [
            'name' => $data['author_name']
        ];
        if (!empty($data['author_icon'])) {
            $embed['author']['icon_url'] = $data['author_icon'];
        }
    }
    
    // Build payload
    $payload = json_encode([
        'embeds' => [$embed],
        'username' => $community_name
    ], JSON_UNESCAPED_SLASHES);
    
    // Send webhook
    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Log the webhook
    $tableCheck = $conn->query("SHOW TABLES LIKE 'discord_webhook_logs'");
    if ($tableCheck->num_rows > 0) {
        $success = ($http_code >= 200 && $http_code < 300) ? 1 : 0;
        $stmt = $conn->prepare("INSERT INTO discord_webhook_logs (event_type, payload, response_code, response_body, success) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssisi", $event_type, $payload, $http_code, $response, $success);
        $stmt->execute();
        $stmt->close();
    }
    
    $conn->close();
    return $http_code >= 200 && $http_code < 300;
}
// Get unread notification count
function getUnreadNotificationCount() {
    if (!isLoggedIn()) return 0;
    
    $conn = getDBConnection();
    
    $tableCheck = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($tableCheck->num_rows == 0) {
        $conn->close();
        return 0;
    }
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    return $result['count'] ?? 0;
}

// Get unread message count
function getUnreadMessageCount() {
    if (!isLoggedIn()) return 0;
    
    $conn = getDBConnection();
    
    $tableCheck = $conn->query("SHOW TABLES LIKE 'messages'");
    if ($tableCheck->num_rows == 0) {
        $conn->close();
        return 0;
    }
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM messages WHERE recipient_id = ? AND is_read = FALSE AND is_deleted_recipient = FALSE");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    return $result['count'] ?? 0;
}

// Get unread announcement count
function getUnreadAnnouncementCount() {
    if (!isLoggedIn()) return 0;
    
    $conn = getDBConnection();
    
    $tableCheck = $conn->query("SHOW TABLES LIKE 'announcements'");
    if ($tableCheck->num_rows == 0) {
        $conn->close();
        return 0;
    }
    
    // Get user's departments
    $user_depts = [];
    $dept_result = $conn->query("SELECT department_id FROM roster WHERE user_id = " . intval($_SESSION['user_id']));
    while ($row = $dept_result->fetch_assoc()) {
        $user_depts[] = $row['department_id'];
    }
    
    $sql = "SELECT COUNT(*) as count FROM announcements a
            WHERE a.is_active = TRUE 
            AND (a.starts_at IS NULL OR a.starts_at <= NOW())
            AND (a.expires_at IS NULL OR a.expires_at > NOW())
            AND a.id NOT IN (SELECT announcement_id FROM announcement_reads WHERE user_id = ?)
            AND (a.target_type = 'all'";
    
    if (!empty($user_depts)) {
        $sql .= " OR (a.target_type = 'department' AND a.target_department_id IN (" . implode(',', $user_depts) . "))";
    }
    
    if (isAdmin()) {
        $sql .= " OR a.target_type = 'admins'";
    }
    
    $sql .= ")";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    return $result['count'] ?? 0;
}

// Output theme CSS variables
function outputThemeCSS() {
    $colors = getThemeColors();
    echo "<style>:root {";
    echo "--primary: {$colors['primary']};";
    echo "--secondary: {$colors['secondary']};";
    echo "--accent: {$colors['accent']};";
    
    // Calculate accent-muted for consistency
    list($r, $g, $b) = sscanf($colors['primary'], "#%02x%02x%02x");
    if ($r !== null && $g !== null && $b !== null) {
        echo "--accent-muted: rgba($r, $g, $b, 0.15);";
        echo "--shadow-color: rgba($r, $g, $b, 0.3);";
    }
    
    echo "}</style>";
}

// Check and process auto LOA returns
function checkAutoLOAReturn() {
    $auto_return = getSetting('auto_loa_return', '1');
    if ($auto_return !== '1') return;
    
    $conn = getDBConnection();
    
    $tableCheck = $conn->query("SHOW TABLES LIKE 'loa_requests'");
    if ($tableCheck->num_rows == 0) {
        $conn->close();
        return;
    }
    
    // Update roster status back to active for expired LOAs
    $conn->query("UPDATE roster r
                  INNER JOIN loa_requests l ON r.user_id = l.user_id
                  SET r.status = 'active'
                  WHERE l.status = 'approved' 
                  AND l.end_date < CURDATE() 
                  AND r.status = 'loa'
                  AND l.auto_returned = FALSE");
    
    // Mark LOAs as auto-returned
    $conn->query("UPDATE loa_requests 
                  SET auto_returned = TRUE, return_date = CURDATE()
                  WHERE status = 'approved' 
                  AND end_date < CURDATE() 
                  AND auto_returned = FALSE");
    
    $conn->close();
}

// Run auto LOA check occasionally
if (rand(1, 100) <= 5) {
    checkAutoLOAReturn();
}

// =====================================================
// SECURITY: CSRF Protection
// =====================================================
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRFToken()) . '">';
}

function verifyCSRFToken() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return true;
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('<h1>403 Forbidden</h1><p>Invalid security token. Please go back, refresh the page, and try again.</p>');
    }
    return true;
}

// =====================================================
// SECURITY: Brute Force / Rate Limiting
// =====================================================
function checkLoginRateLimit($username) {
    $conn = getDBConnection();
    
    // Create table if not exists
    $conn->query("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_username (username),
        INDEX idx_ip (ip_address),
        INDEX idx_attempted (attempted_at)
    )");
    
    // Clean old attempts (older than 30 minutes)
    $conn->query("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
    
    $ip = getClientIP();
    $window = 15; // minutes
    
    // Check per-username (max 5 attempts in window)
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM login_attempts WHERE username = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    $stmt->bind_param("si", $username, $window);
    $stmt->execute();
    $user_row = $stmt->get_result()->fetch_assoc();
    $user_attempts = $user_row['cnt'] ?? 0;
    $stmt->close();
    
    // Check per-IP (max 15 attempts in window)
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM login_attempts WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    $stmt->bind_param("si", $ip, $window);
    $stmt->execute();
    $ip_row = $stmt->get_result()->fetch_assoc();
    $ip_attempts = $ip_row['cnt'] ?? 0;
    $stmt->close();
    
    $conn->close();
    
    if ($user_attempts >= 5) {
        return 'Too many login attempts for this account. Please wait 15 minutes.';
    }
    if ($ip_attempts >= 15) {
        return 'Too many login attempts from this IP. Please wait 15 minutes.';
    }
    return false;
}

function recordFailedLogin($username) {
    $conn = getDBConnection();
    $ip = getClientIP();
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Record to login_attempts for rate limiting
    $stmt = $conn->prepare("INSERT INTO login_attempts (username, ip_address) VALUES (?, ?)");
    $stmt->bind_param("ss", $username, $ip);
    $stmt->execute();
    $stmt->close();
    
    // Also record to login_history if user exists
    $userStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $userStmt->bind_param("s", $username);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    if ($userResult->num_rows > 0) {
        $user = $userResult->fetch_assoc();
        $histStmt = $conn->prepare("INSERT INTO login_history (user_id, ip_address, user_agent, success, failure_reason) VALUES (?, ?, ?, 0, 'Invalid password')");
        if ($histStmt) {
            $histStmt->bind_param("iss", $user['id'], $ip, $ua);
            $histStmt->execute();
            $histStmt->close();
        }
    }
    $userStmt->close();
    
    $conn->close();
}

function clearLoginAttempts($username) {
    $conn = getDBConnection();
    $ip = getClientIP();
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE username = ? OR ip_address = ?");
    $stmt->bind_param("ss", $username, $ip);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

// =====================================================
// UTILITY: Pagination Helper
// =====================================================
function getPagination($total, $per_page, $current_page, $base_url) {
    $total_pages = max(1, ceil($total / $per_page));
    $current_page = max(1, min($current_page, $total_pages));
    $offset = ($current_page - 1) * $per_page;
    
    return [
        'total' => $total,
        'per_page' => $per_page,
        'current_page' => $current_page,
        'total_pages' => $total_pages,
        'offset' => $offset,
        'base_url' => $base_url
    ];
}

function renderPagination($p) {
    if ($p['total_pages'] <= 1) return '';
    
    $html = '<div class="pagination">';
    
    // Previous
    if ($p['current_page'] > 1) {
        $html .= '<a href="' . $p['base_url'] . '&page=' . ($p['current_page'] - 1) . '" class="page-btn">← Prev</a>';
    } else {
        $html .= '<span class="page-btn disabled">← Prev</span>';
    }
    
    // Page numbers
    $start = max(1, $p['current_page'] - 2);
    $end = min($p['total_pages'], $p['current_page'] + 2);
    
    if ($start > 1) {
        $html .= '<a href="' . $p['base_url'] . '&page=1" class="page-btn">1</a>';
        if ($start > 2) $html .= '<span class="page-btn disabled">…</span>';
    }
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $p['current_page']) {
            $html .= '<span class="page-btn active">' . $i . '</span>';
        } else {
            $html .= '<a href="' . $p['base_url'] . '&page=' . $i . '" class="page-btn">' . $i . '</a>';
        }
    }
    
    if ($end < $p['total_pages']) {
        if ($end < $p['total_pages'] - 1) $html .= '<span class="page-btn disabled">…</span>';
        $html .= '<a href="' . $p['base_url'] . '&page=' . $p['total_pages'] . '" class="page-btn">' . $p['total_pages'] . '</a>';
    }
    
    // Next
    if ($p['current_page'] < $p['total_pages']) {
        $html .= '<a href="' . $p['base_url'] . '&page=' . ($p['current_page'] + 1) . '" class="page-btn">Next →</a>';
    } else {
        $html .= '<span class="page-btn disabled">Next →</span>';
    }
    
    $html .= '<span class="page-info">Page ' . $p['current_page'] . ' of ' . $p['total_pages'] . ' (' . $p['total'] . ' total)</span>';
    $html .= '</div>';
    
    return $html;
}

// =====================================================
// UTILITY: CSV Export Helper
// =====================================================
function exportCSV($filename, $headers, $rows) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    $output = fopen('php://output', 'w');
    
    // BOM for Excel UTF-8 support
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, $headers);
    
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}

// Sanitize SVG files by stripping dangerous elements and attributes
function sanitizeSVG($filepath) {
    $content = file_get_contents($filepath);
    if ($content === false) return false;
    
    // Remove script tags and their contents
    $content = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $content);
    $content = preg_replace('/<script\b[^>]*\/>/is', '', $content);
    
    // Remove all on* event handler attributes (onclick, onload, onerror, onmouseover, etc.)
    $content = preg_replace('/\bon\w+\s*=\s*["\'][^"\']*["\']/i', '', $content);
    $content = preg_replace('/\bon\w+\s*=\s*[^\s>]*/i', '', $content);
    
    // Remove javascript: and data: URIs in href/xlink:href/src attributes
    $content = preg_replace('/\b(href|xlink:href|src)\s*=\s*["\']?\s*javascript\s*:/i', '$1="', $content);
    $content = preg_replace('/\b(href|xlink:href|src)\s*=\s*["\']?\s*data\s*:\s*text\/html/i', '$1="', $content);
    
    // Remove foreignObject (can embed HTML)
    $content = preg_replace('/<foreignObject\b[^>]*>.*?<\/foreignObject>/is', '', $content);
    $content = preg_replace('/<foreignObject\b[^>]*\/>/is', '', $content);
    
    // Remove use tags pointing to external resources
    $content = preg_replace('/<use\b[^>]*xlink:href\s*=\s*["\'](?!#)[^"\']*["\'][^>]*\/>/is', '', $content);
    
    // Remove set and animate tags that could trigger behavior
    $content = preg_replace('/<set\b[^>]*attributeName\s*=\s*["\']on\w+["\']/is', '<set ', $content);
    
    return file_put_contents($filepath, $content) !== false;
}

// Validate uploaded image file (server-side, beyond MIME check)
function validateUploadedImage($tmp_path, $mime_type) {
    // For SVGs, check it's actually XML/SVG content
    if ($mime_type === 'image/svg+xml') {
        $content = file_get_contents($tmp_path);
        // Must contain <svg tag
        if (stripos($content, '<svg') === false) {
            return false;
        }
        return true;
    }
    
    // For raster images, use getimagesize to verify
    $info = @getimagesize($tmp_path);
    if ($info === false) {
        return false;
    }
    
    // Verify the detected type matches claimed type
    $type_map = [
        'image/png' => IMAGETYPE_PNG,
        'image/jpeg' => IMAGETYPE_JPEG,
        'image/gif' => IMAGETYPE_GIF,
        'image/webp' => IMAGETYPE_WEBP,
    ];
    
    if (isset($type_map[$mime_type]) && $info[2] !== $type_map[$mime_type]) {
        return false;
    }
    
    return true;
}

// Rate limit generic actions (returns true if allowed, false if rate limited)
function checkRateLimit($action, $identifier, $max_attempts = 5, $window_minutes = 15) {
    $conn = getDBConnection();
    
    // Check if login_attempts table exists, create if not
    $tableCheck = $conn->query("SHOW TABLES LIKE 'login_attempts'");
    if ($tableCheck->num_rows == 0) {
        $conn->query("CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_la_username (username),
            INDEX idx_la_ip (ip_address),
            INDEX idx_la_attempted (attempted_at)
        )");
    }
    
    // Reuse login_attempts table with action prefix
    $key = $action . ':' . $identifier;
    $window = date('Y-m-d H:i:s', strtotime("-{$window_minutes} minutes"));
    
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM login_attempts WHERE username = ? AND attempted_at > ?");
    if (!$stmt) {
        $conn->close();
        return true; // Allow action if query fails
    }
    $stmt->bind_param("ss", $key, $window);
    $stmt->execute();
    $count_row = $stmt->get_result()->fetch_assoc();
    $count = $count_row['cnt'] ?? 0;
    $stmt->close();
    $conn->close();
    
    return $count < $max_attempts;
}

// Record a rate-limited action
function recordRateLimitAction($action, $identifier) {
    $conn = getDBConnection();
    
    // Check if login_attempts table exists, create if not
    $tableCheck = $conn->query("SHOW TABLES LIKE 'login_attempts'");
    if ($tableCheck->num_rows == 0) {
        $conn->query("CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_la_username (username),
            INDEX idx_la_ip (ip_address),
            INDEX idx_la_attempted (attempted_at)
        )");
    }
    
    $key = $action . ':' . $identifier;
    $ip = getClientIP();
    
    $stmt = $conn->prepare("INSERT INTO login_attempts (username, ip_address, attempted_at) VALUES (?, ?, NOW())");
    if ($stmt) {
        $stmt->bind_param("ss", $key, $ip);
        $stmt->execute();
        $stmt->close();
    }
    $conn->close();
}

// Format a date/time in the current user's timezone
function formatUserDate($datetime, $format = 'M j, Y g:i A') {
    if (!$datetime) return '—';
    $tz = 'UTC';
    if (isset($_SESSION['user_id'])) {
        static $cached_tz = null;
        if ($cached_tz === null) {
            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT timezone FROM users WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $_SESSION['user_id']);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $cached_tz = ($row && !empty($row['timezone'])) ? $row['timezone'] : 'UTC';
            } else {
                $cached_tz = 'UTC';
            }
        }
        $tz = $cached_tz;
    }
    try {
        $dt = new DateTime($datetime, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone($tz));
        return $dt->format($format);
    } catch (Exception $e) {
        return date($format, strtotime($datetime));
    }
}

// Record login attempt in history
function recordLoginHistory($user_id, $success = true, $failure_reason = null) {
    try {
        $conn = getDBConnection();
        
        // Check if table exists
        $result = $conn->query("SHOW TABLES LIKE 'login_history'");
        if ($result->num_rows === 0) {
            $conn->query("CREATE TABLE IF NOT EXISTS login_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                ip_address VARCHAR(45),
                user_agent VARCHAR(500),
                location VARCHAR(255),
                success BOOLEAN DEFAULT TRUE,
                failure_reason VARCHAR(255),
                login_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_login_user (user_id),
                INDEX idx_login_date (login_at)
            )");
        }
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        $success_int = $success ? 1 : 0;
        
        $stmt = $conn->prepare("INSERT INTO login_history (user_id, ip_address, user_agent, success, failure_reason) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("issis", $user_id, $ip, $ua, $success_int, $failure_reason);
            $stmt->execute();
            $stmt->close();
        }
        $conn->close();
    } catch (Exception $e) {
        // Silently fail - don't break login
    }
}

// Log webhook call results
function logWebhook($type, $url, $payload, $response_code, $response_body, $success, $error = null) {
    try {
        $conn = getDBConnection();
        
        // Check if table exists
        $result = $conn->query("SHOW TABLES LIKE 'webhook_logs'");
        if ($result->num_rows === 0) {
            $conn->query("CREATE TABLE IF NOT EXISTS webhook_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                webhook_type VARCHAR(50) NOT NULL,
                target_url VARCHAR(500),
                payload TEXT,
                response_code INT,
                response_body TEXT,
                success BOOLEAN DEFAULT FALSE,
                error_message VARCHAR(500),
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_webhook_type (webhook_type),
                INDEX idx_webhook_date (sent_at)
            )");
        }
        
        $success_int = $success ? 1 : 0;
        $payload_str = is_array($payload) ? json_encode($payload) : $payload;
        $response_str = is_string($response_body) ? substr($response_body, 0, 5000) : '';
        
        $stmt = $conn->prepare("INSERT INTO webhook_logs (webhook_type, target_url, payload, response_code, response_body, success, error_message) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sssisis", $type, $url, $payload_str, $response_code, $response_str, $success_int, $error);
            $stmt->execute();
            $stmt->close();
        }
        $conn->close();
    } catch (Exception $e) {
        // Silently fail
    }
}

// Check if maintenance mode is enabled and block non-admins
function checkMaintenanceMode() {
    // Skip in cron context
    if (defined('CRON_CONTEXT') && CRON_CONTEXT === true) {
        return;
    }
    
    // Get current request path
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($requestUri, PHP_URL_PATH);
    $current_page = basename($_SERVER['PHP_SELF'] ?? '');
    
    // Always allow these paths
    $allowedPaths = [
        '/auth/',
        '/maintenance',
        '/license-error',
        '/install/',
        '/cron/',
        '/api/'
    ];
    
    $allowedPages = ['login.php', 'logout.php', 'forgot_password.php', 'reset_password.php', 'maintenance.php', 'license-error.php'];
    
    // Check if current path is allowed
    foreach ($allowedPaths as $allowed) {
        if (strpos($path, $allowed) === 0) {
            return;
        }
    }
    if (in_array($current_page, $allowedPages)) {
        return;
    }
    
    try {
        $conn = getDBConnection();
        
        // Check maintenance mode - try both tables (settings and system_settings)
        $maintenance_mode = false;
        $result = @$conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
        if (!$result) {
            $result = @$conn->query("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode'");
        }
        $row = $result ? $result->fetch_assoc() : null;
        $maintenance_mode = ($row && $row['setting_value'] === '1');
        
        if ($maintenance_mode) {
            // Check if user is admin
            $is_admin = false;
            if (isset($_SESSION['user_id'])) {
                $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $_SESSION['user_id']);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    $is_admin = ($user && $user['is_admin']);
                }
            }
            
            $conn->close();
            
            if (!$is_admin) {
                // Redirect to maintenance page
                header('Location: /maintenance');
                exit;
            }
        } else {
            $conn->close();
        }
    } catch (Exception $e) {
        // If check fails, allow access
    }
}

/**
 * Enforce license check on every request
 * Blocks access if license is invalid (except for allowed paths)
 */
function enforceLicenseCheck() {
    // Skip in cron context
    if (defined('CRON_CONTEXT') && CRON_CONTEXT === true) {
        return;
    }
    
    // Get current request path
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($requestUri, PHP_URL_PATH);
    $current_page = basename($_SERVER['PHP_SELF'] ?? '');
    
    // Always allow these paths without license check
    $allowedPaths = [
        '/auth/',
        '/admin/license',
        '/license-error',
        '/maintenance',
        '/install/',
        '/cron/',
        '/api/'
    ];
    
    $allowedPages = ['login.php', 'logout.php', 'forgot_password.php', 'reset_password.php', 
                     'maintenance.php', 'license-error.php', 'license.php'];
    
    // Check if current path is allowed
    foreach ($allowedPaths as $allowed) {
        if (strpos($path, $allowed) === 0) {
            return;
        }
    }
    if (in_array($current_page, $allowedPages)) {
        return;
    }
    
    // Only check if license.php is available
    if (!defined('UM_LICENSE_LOADED')) {
        $licensePath = __DIR__ . '/license.php';
        if (file_exists($licensePath)) {
            require_once $licensePath;
        } else {
            return; // Can't check without license file
        }
    }
    
    try {
        $conn = getDBConnection();
        
        // Check license status
        if (function_exists('shouldBlockForLicense')) {
            $licenseCheck = shouldBlockForLicense($conn);
            
            if ($licenseCheck['blocked']) {
                // Store error for display
                if (session_status() === PHP_SESSION_ACTIVE) {
                    $_SESSION['license_error'] = $licenseCheck['reason'];
                }
                
                // Check if user is admin
                $is_admin = false;
                if (isset($_SESSION['user_id'])) {
                    $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $_SESSION['user_id']);
                        $stmt->execute();
                        $user = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        $is_admin = ($user && $user['is_admin']);
                    }
                }
                
                $conn->close();
                
                if ($is_admin) {
                    // Redirect admin to license page
                    header('Location: /admin/license');
                } else {
                    // Redirect others to error page
                    header('Location: /license-error');
                }
                exit;
            }
        }
        
        $conn->close();
    } catch (Exception $e) {
        // If check fails, allow access (fail open for usability)
        error_log("License check error: " . $e->getMessage());
    }
}

// Get user's badges for display
function getUserBadges($user_id, $limit = 5) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("
            SELECT b.icon, b.name, b.color, b.rarity 
            FROM user_badges ub 
            JOIN badges b ON ub.badge_id = b.id 
            WHERE ub.user_id = ? 
            ORDER BY ub.awarded_at DESC 
            LIMIT ?
        ");
        if ($stmt) {
            $stmt->bind_param("ii", $user_id, $limit);
            $stmt->execute();
            $badges = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            $conn->close();
            return $badges;
        }
        $conn->close();
    } catch (Exception $e) {
        // Return empty array on failure
    }
    return [];
}

// Get user's callsign
function getUserCallsign($user_id) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT callsign FROM callsigns WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $conn->close();
            return $row ? $row['callsign'] : null;
        }
        $conn->close();
    } catch (Exception $e) {
        // Return null on failure
    }
    return null;
}

// =====================================================
// LOGIN ANOMALY DETECTION
// =====================================================

/**
 * Generate a unique device fingerprint hash
 */
function generateDeviceHash($user_agent, $ip = null) {
    // Create a hash based on user agent characteristics
    // We don't include IP in the hash as IPs change frequently
    $browser_info = [
        'ua' => $user_agent,
        // Extract key browser identifiers
        'browser' => preg_match('/(Chrome|Firefox|Safari|Edge|MSIE|Opera)/i', $user_agent, $m) ? $m[1] : 'Unknown',
        'os' => preg_match('/(Windows|Mac|Linux|Android|iOS|iPhone|iPad)/i', $user_agent, $m) ? $m[1] : 'Unknown',
    ];
    return hash('sha256', json_encode($browser_info));
}

/**
 * Parse user agent into friendly device name
 */
function parseDeviceName($user_agent) {
    $browser = 'Unknown Browser';
    $os = 'Unknown OS';
    
    // Detect browser
    if (preg_match('/Firefox\/[\d.]+/i', $user_agent)) $browser = 'Firefox';
    elseif (preg_match('/Edg\/[\d.]+/i', $user_agent)) $browser = 'Edge';
    elseif (preg_match('/Chrome\/[\d.]+/i', $user_agent)) $browser = 'Chrome';
    elseif (preg_match('/Safari\/[\d.]+/i', $user_agent) && !preg_match('/Chrome/i', $user_agent)) $browser = 'Safari';
    elseif (preg_match('/MSIE|Trident/i', $user_agent)) $browser = 'Internet Explorer';
    elseif (preg_match('/Opera|OPR/i', $user_agent)) $browser = 'Opera';
    
    // Detect OS
    if (preg_match('/Windows NT 10/i', $user_agent)) $os = 'Windows 10/11';
    elseif (preg_match('/Windows/i', $user_agent)) $os = 'Windows';
    elseif (preg_match('/Macintosh|Mac OS/i', $user_agent)) $os = 'macOS';
    elseif (preg_match('/Linux/i', $user_agent) && !preg_match('/Android/i', $user_agent)) $os = 'Linux';
    elseif (preg_match('/Android/i', $user_agent)) $os = 'Android';
    elseif (preg_match('/iPhone/i', $user_agent)) $os = 'iPhone';
    elseif (preg_match('/iPad/i', $user_agent)) $os = 'iPad';
    elseif (preg_match('/iOS/i', $user_agent)) $os = 'iOS';
    
    return "$browser on $os";
}

/**
 * Get approximate location from IP using free API
 */
function getLocationFromIP($ip) {
    // Skip for local IPs
    if (in_array($ip, ['127.0.0.1', '::1', 'localhost']) || 
        preg_match('/^(10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.)/', $ip)) {
        return 'Local Network';
    }
    
    try {
        // Use ip-api.com (free, no API key required, 45 requests/minute)
        $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,city,regionName,country", false, 
            stream_context_create(['http' => ['timeout' => 3]]));
        
        if ($response) {
            $data = json_decode($response, true);
            if ($data && $data['status'] === 'success') {
                $parts = array_filter([$data['city'], $data['regionName'], $data['country']]);
                return implode(', ', $parts) ?: 'Unknown';
            }
        }
    } catch (Exception $e) {
        // Silently fail
    }
    
    return 'Unknown';
}

/**
 * Check for login anomalies and return array of detected issues
 */
function detectLoginAnomalies($user_id, $ip, $user_agent) {
    $anomalies = [];
    $conn = getDBConnection();
    
    // Check if tables exist
    $tableCheck = $conn->query("SHOW TABLES LIKE 'trusted_devices'");
    if ($tableCheck->num_rows == 0) {
        $conn->close();
        return $anomalies;
    }
    
    $device_hash = generateDeviceHash($user_agent);
    $device_name = parseDeviceName($user_agent);
    $location = getLocationFromIP($ip);
    
    // 1. Check for new device
    $stmt = $conn->prepare("SELECT * FROM trusted_devices WHERE user_id = ? AND device_hash = ?");
    $stmt->bind_param("is", $user_id, $device_hash);
    $stmt->execute();
    $existing_device = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$existing_device) {
        $anomalies[] = [
            'type' => 'new_device',
            'severity' => 'medium',
            'message' => "New device detected: $device_name",
            'details' => json_encode(['device' => $device_name, 'ip' => $ip, 'location' => $location])
        ];
        
        // Add to trusted devices (but not marked as trusted yet)
        // Use ON DUPLICATE KEY UPDATE to handle race conditions
        $stmt = $conn->prepare("INSERT INTO trusted_devices (user_id, device_hash, device_name, ip_address, last_ip, location) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE last_seen = NOW(), last_ip = VALUES(last_ip)");
        $stmt->bind_param("isssss", $user_id, $device_hash, $device_name, $ip, $ip, $location);
        $stmt->execute();
        $stmt->close();
    } else {
        // Update last seen
        $stmt = $conn->prepare("UPDATE trusted_devices SET last_seen = NOW(), last_ip = ? WHERE id = ?");
        $stmt->bind_param("si", $ip, $existing_device['id']);
        $stmt->execute();
        $stmt->close();
        
        // 2. Check for new IP on known device
        if ($existing_device['last_ip'] !== $ip && $existing_device['ip_address'] !== $ip) {
            $anomalies[] = [
                'type' => 'new_ip',
                'severity' => 'low',
                'message' => "Login from new IP address: $ip",
                'details' => json_encode(['new_ip' => $ip, 'previous_ip' => $existing_device['last_ip'], 'device' => $device_name])
            ];
        }
        
        // 3. Check for new location
        if ($existing_device['location'] && $location !== 'Unknown' && $location !== 'Local Network' && 
            $existing_device['location'] !== $location && $existing_device['location'] !== 'Unknown') {
            $anomalies[] = [
                'type' => 'new_location',
                'severity' => 'medium',
                'message' => "Login from new location: $location",
                'details' => json_encode(['new_location' => $location, 'previous_location' => $existing_device['location']])
            ];
            
            // Update location
            $stmt = $conn->prepare("UPDATE trusted_devices SET location = ? WHERE id = ?");
            $stmt->bind_param("si", $location, $existing_device['id']);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    // 4. Check for recent failed login attempts
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM login_history WHERE user_id = ? AND success = 0 AND login_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $failed = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($failed && $failed['cnt'] >= 3) {
        $anomalies[] = [
            'type' => 'failed_attempts',
            'severity' => $failed['cnt'] >= 5 ? 'high' : 'medium',
            'message' => "Successful login after {$failed['cnt']} failed attempts in the last hour",
            'details' => json_encode(['failed_count' => $failed['cnt'], 'ip' => $ip])
        ];
    }
    
    // 5. Check for impossible travel (login from far location within short time)
    $stmt = $conn->prepare("
        SELECT location, login_at, ip_address 
        FROM login_history 
        WHERE user_id = ? AND success = 1 AND location IS NOT NULL AND location != '' AND location != 'Unknown' AND location != 'Local Network'
        ORDER BY login_at DESC 
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $last_login = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($last_login && $location !== 'Unknown' && $location !== 'Local Network' && $last_login['location'] !== $location) {
        $time_diff = time() - strtotime($last_login['login_at']);
        // If different country/region in less than 2 hours, flag as suspicious
        if ($time_diff < 7200) {
            // Extract countries for comparison
            $last_country = preg_match('/([^,]+)$/', trim($last_login['location']), $m) ? trim($m[1]) : '';
            $curr_country = preg_match('/([^,]+)$/', trim($location), $m) ? trim($m[1]) : '';
            
            if ($last_country && $curr_country && $last_country !== $curr_country) {
                $anomalies[] = [
                    'type' => 'impossible_travel',
                    'severity' => 'critical',
                    'message' => "Impossible travel detected: {$last_login['location']} → $location in " . round($time_diff/60) . " minutes",
                    'details' => json_encode([
                        'from_location' => $last_login['location'],
                        'to_location' => $location,
                        'time_diff_minutes' => round($time_diff/60),
                        'from_ip' => $last_login['ip_address'],
                        'to_ip' => $ip
                    ])
                ];
            }
        }
    }
    
    $conn->close();
    return $anomalies;
}

/**
 * Log security alerts and send email notifications
 */
function processLoginAnomalies($user_id, $anomalies, $ip, $user_agent) {
    if (empty($anomalies)) return;
    
    $conn = getDBConnection();
    
    // Check if security_alerts table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'security_alerts'");
    if ($tableCheck->num_rows == 0) {
        $conn->close();
        return;
    }
    
    // Get user info and security preferences
    $stmt = $conn->prepare("SELECT u.username, u.email, s.* FROM users u 
                            LEFT JOIN user_security_settings s ON u.id = s.user_id 
                            WHERE u.id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        $conn->close();
        return;
    }
    
    // Default settings if not configured
    $email_new_device = $user['email_on_new_device'] ?? true;
    $email_new_ip = $user['email_on_new_ip'] ?? true;
    $email_new_location = $user['email_on_new_location'] ?? true;
    $email_failed_attempts = $user['email_on_failed_attempts'] ?? true;
    
    $alerts_to_email = [];
    $location = getLocationFromIP($ip);
    
    foreach ($anomalies as $anomaly) {
        // Log the alert
        $stmt = $conn->prepare("INSERT INTO security_alerts (user_id, alert_type, severity, ip_address, user_agent, location, details) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $user_id, $anomaly['type'], $anomaly['severity'], $ip, $user_agent, $location, $anomaly['details']);
        $stmt->execute();
        $stmt->close();
        
        // Check if we should email for this type
        $should_email = false;
        switch ($anomaly['type']) {
            case 'new_device': $should_email = $email_new_device; break;
            case 'new_ip': $should_email = $email_new_ip; break;
            case 'new_location': $should_email = $email_new_location; break;
            case 'failed_attempts': $should_email = $email_failed_attempts; break;
            case 'impossible_travel': $should_email = true; break; // Always email for critical
        }
        
        if ($should_email) {
            $alerts_to_email[] = $anomaly;
        }
    }
    
    $conn->close();
    
    // Send email notification if there are alerts to send
    if (!empty($alerts_to_email) && !empty($user['email'])) {
        sendSecurityAlertEmail($user, $alerts_to_email, $ip, $location);
    }
}

/**
 * Send security alert email
 */
function sendSecurityAlertEmail($user, $alerts, $ip, $location) {
    $community = getCommunityName();
    $device_name = parseDeviceName($_SERVER['HTTP_USER_AGENT'] ?? '');
    $time = date('F j, Y \a\t g:i A T');
    
    // Determine severity
    $max_severity = 'low';
    foreach ($alerts as $alert) {
        if ($alert['severity'] === 'critical') { $max_severity = 'critical'; break; }
        if ($alert['severity'] === 'high' && $max_severity !== 'critical') $max_severity = 'high';
        if ($alert['severity'] === 'medium' && !in_array($max_severity, ['high', 'critical'])) $max_severity = 'medium';
    }
    
    $severity_colors = [
        'low' => '#3b82f6',
        'medium' => '#f59e0b',
        'high' => 'var(--danger)',
        'critical' => '#dc2626'
    ];
    $severity_color = $severity_colors[$max_severity] ?? '#3b82f6';
    
    // Build alert list
    $alert_items = '';
    $type_icons = [
        'new_device' => '💻',
        'new_ip' => '🌐',
        'new_location' => '📍',
        'failed_attempts' => '🔒',
        'impossible_travel' => '✈️'
    ];
    foreach ($alerts as $alert) {
        $icon = $type_icons[$alert['type']] ?? '⚠️';
        $alert_items .= "<li style=\"margin-bottom: 8px;\">$icon {$alert['message']}</li>";
    }
    
    $subject_map = [
        'critical' => "🚨 CRITICAL Security Alert - $community",
        'high' => "⚠️ Security Alert - $community"
    ];
    $subject = $subject_map[$max_severity] ?? "🔔 Login Notification - $community";
    
    $body = "
        <p style=\"margin: 0 0 16px 0;\">Hi <strong>" . htmlspecialchars($user['username']) . "</strong>,</p>
        <p style=\"margin: 0 0 16px 0;\">We detected the following activity on your account:</p>
        
        <div style=\"background: {$severity_color}15; border-left: 4px solid {$severity_color}; padding: 16px; margin: 20px 0; border-radius: 4px;\">
            <ul style=\"margin: 0; padding-left: 20px;\">
                $alert_items
            </ul>
        </div>
        
        <p style=\"margin: 16px 0; color: #64748b;\"><strong>Details:</strong></p>
        <table style=\"width: 100%; border-collapse: collapse; margin-bottom: 20px;\">
            <tr><td style=\"padding: 8px 0; color: #64748b;\">Time:</td><td style=\"padding: 8px 0;\">$time</td></tr>
            <tr><td style=\"padding: 8px 0; color: #64748b;\">Device:</td><td style=\"padding: 8px 0;\">$device_name</td></tr>
            <tr><td style=\"padding: 8px 0; color: #64748b;\">IP Address:</td><td style=\"padding: 8px 0;\">$ip</td></tr>
            <tr><td style=\"padding: 8px 0; color: #64748b;\">Location:</td><td style=\"padding: 8px 0;\">$location</td></tr>
        </table>
        
        <p style=\"margin: 16px 0;\"><strong>If this was you:</strong> You can safely ignore this email.</p>
        <p style=\"margin: 16px 0; color: var(--danger);\"><strong>If this wasn't you:</strong> Someone may have access to your account. Please change your password immediately and enable two-factor authentication.</p>
    ";
    
    // Send the email
    if (function_exists('sendEmail')) {
        sendEmail($user['email'], $subject, $body, [
            'icon' => $max_severity === 'critical' ? '🚨' : '🔔',
            'header_title' => $max_severity === 'critical' ? 'Security Alert!' : 'Login Notification',
            'preheader' => "New login activity detected on your $community account",
            'cta_text' => 'Review Security Settings',
            'cta_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/user/security'
        ]);
    }
}

/**
 * Get user's security settings
 */
function getUserSecuritySettings($user_id) {
    $defaults = [
        'email_on_new_device' => true,
        'email_on_new_ip' => true,
        'email_on_new_location' => true,
        'email_on_failed_attempts' => true,
        'require_2fa_new_device' => false
    ];
    
    try {
        $conn = getDBConnection();
        $tableCheck = $conn->query("SHOW TABLES LIKE 'user_security_settings'");
        if ($tableCheck->num_rows == 0) {
            $conn->close();
            return $defaults;
        }
        
        $stmt = $conn->prepare("SELECT * FROM user_security_settings WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $settings = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();
        
        return $settings ?: $defaults;
    } catch (Exception $e) {
        return $defaults;
    }
}

/**
 * Update user's security settings
 */
function updateUserSecuritySettings($user_id, $settings) {
    try {
        $conn = getDBConnection();
        
        // Ensure table exists
        $conn->query("CREATE TABLE IF NOT EXISTS user_security_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            email_on_new_device BOOLEAN DEFAULT TRUE,
            email_on_new_ip BOOLEAN DEFAULT TRUE,
            email_on_new_location BOOLEAN DEFAULT TRUE,
            email_on_failed_attempts BOOLEAN DEFAULT TRUE,
            require_2fa_new_device BOOLEAN DEFAULT FALSE,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        
        $stmt = $conn->prepare("INSERT INTO user_security_settings 
            (user_id, email_on_new_device, email_on_new_ip, email_on_new_location, email_on_failed_attempts, require_2fa_new_device) 
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            email_on_new_device = VALUES(email_on_new_device),
            email_on_new_ip = VALUES(email_on_new_ip),
            email_on_new_location = VALUES(email_on_new_location),
            email_on_failed_attempts = VALUES(email_on_failed_attempts),
            require_2fa_new_device = VALUES(require_2fa_new_device)");
        
        $email_device = $settings['email_on_new_device'] ? 1 : 0;
        $email_ip = $settings['email_on_new_ip'] ? 1 : 0;
        $email_location = $settings['email_on_new_location'] ? 1 : 0;
        $email_failed = $settings['email_on_failed_attempts'] ? 1 : 0;
        $require_2fa = $settings['require_2fa_new_device'] ? 1 : 0;
        
        $stmt->bind_param("iiiiii", $user_id, $email_device, $email_ip, $email_location, $email_failed, $require_2fa);
        $result = $stmt->execute();
        $stmt->close();
        $conn->close();
        
        return $result;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Safely execute a COUNT/SUM query and return the value
 * Returns 0 if query fails or table doesn't exist
 */
function safeQueryCount($conn, $sql, $column = 'cnt') {
    try {
        $result = @$conn->query($sql);
        if ($result && $row = $result->fetch_assoc()) {
            return intval($row[$column] ?? 0);
        }
        return 0;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Safely execute a query and return a single value
 * Returns default if query fails
 */
function safeQueryValue($conn, $sql, $column, $default = null) {
    try {
        $result = @$conn->query($sql);
        if ($result && $row = $result->fetch_assoc()) {
            return $row[$column] ?? $default;
        }
        return $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Check if a table exists in the database
 */
function tableExists($conn, $tableName) {
    $tableName = $conn->real_escape_string($tableName);
    $result = @$conn->query("SHOW TABLES LIKE '$tableName'");
    return $result && $result->num_rows > 0;
}

/**
 * Safely get result from prepared statement
 * Returns null if no results
 */
function safeGetRow($stmt) {
    if (!$stmt) return null;
    $result = $stmt->get_result();
    if (!$result) return null;
    return $result->fetch_assoc();
}

// Auto-run maintenance mode check
checkMaintenanceMode();

// Auto-run license enforcement check
enforceLicenseCheck();

