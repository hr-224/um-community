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
 * UM Community Manager - Scheduled Tasks
 * 
 * Run this file via cron job:
 * 0 8 * * * php /path/to/scheduled_tasks.php daily
 * 0 8 * * 1 php /path/to/scheduled_tasks.php weekly
 * 0 8 1 * * php /path/to/scheduled_tasks.php monthly
 * 
 * Or via HTTP with token:
 * curl https://yoursite.com/cron/scheduled_tasks.php?task=daily&token=YOUR_CRON_TOKEN
 */

// Disable session and auth middleware for cron execution
define('CRON_CONTEXT', true);

$is_cli = php_sapi_name() === 'cli';

// For CLI execution, load config without session
if ($is_cli) {
    // Load config without session
    define('DB_HOST', '');  // Will be overwritten by config
    require_once __DIR__ . '/../config.php';
    if (!defined('UM_FUNCTIONS_LOADED')) { 
        require_once __DIR__ . '/../includes/functions.php'; 
    }
} else {
    // For web access, check authentication
    // First load config to get cron token
    require_once __DIR__ . '/../config.php';
    if (!defined('UM_FUNCTIONS_LOADED')) { 
        require_once __DIR__ . '/../includes/functions.php'; 
    }
    
    // Check for token-based authentication (for external cron services)
    $provided_token = $_GET['token'] ?? $_SERVER['HTTP_X_CRON_TOKEN'] ?? '';
    $valid_token = getSetting('cron_token', '');
    
    // Generate a token if one doesn't exist
    if (empty($valid_token)) {
        $valid_token = bin2hex(random_bytes(32));
        try {
            $conn = getDBConnection();
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type) VALUES ('cron_token', ?, 'text') ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->bind_param("ss", $valid_token, $valid_token);
            $stmt->execute();
            $stmt->close();
            $conn->close();
        } catch (Exception $e) {
            // Token will be regenerated next time
        }
    }
    
    // If token is provided and valid, allow access
    $token_auth = !empty($provided_token) && !empty($valid_token) && hash_equals($valid_token, $provided_token);
    
    if (!$token_auth) {
        // Fall back to admin authentication
        if (!isLoggedIn() || !isAdmin()) {
            // Return JSON error for API calls
            if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized', 'message' => 'Valid cron token or admin login required']);
                exit;
            }
            // Redirect to login for browser access
            header('Location: /auth/login');
            exit;
        }
    }
}

if (!defined('UM_EMAIL_LOADED')) { 
    require_once __DIR__ . '/../includes/email.php'; 
}

$task = $is_cli ? ($argv[1] ?? 'daily') : ($_GET['task'] ?? 'daily');
$results = [];
$start_time = microtime(true);

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    $error_msg = "Database connection failed: " . $e->getMessage();
    error_log("Cron Error: " . $error_msg);
    if ($is_cli) {
        echo "ERROR: " . $error_msg . "\n";
        exit(1);
    } else {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Database Error', 'message' => $error_msg]);
        exit;
    }
}

// =========================================
// CERTIFICATION EXPIRY ALERTS (Daily)
// =========================================
if ($task === 'daily' || $task === 'cert_alerts') {
    $results[] = "=== Certification Expiry Alerts ===";
    $alerts_sent = 0;
    
    $stmt = $conn->prepare("
        SELECT uc.*, ct.name as cert_name, u.username, u.email, u.id as user_id
        FROM user_certifications uc
        JOIN certification_types ct ON uc.certification_type_id = ct.id
        JOIN users u ON uc.user_id = u.id
        WHERE uc.expiry_date IS NOT NULL
        AND uc.status = 'completed'
        AND uc.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $expiring = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    foreach ($expiring as $cert) {
        // Check user preferences
        $stmt = $conn->prepare("SELECT certification_expiry_alerts FROM user_email_preferences WHERE user_id = ?");
        $stmt->bind_param("i", $cert['user_id']);
        $stmt->execute();
        $pref = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($pref && !$pref['certification_expiry_alerts']) {
            continue;
        }
        
        $days_left = floor((strtotime($cert['expiry_date']) - time()) / 86400);
        
        if (!in_array($days_left, [30, 14, 7, 3, 1])) {
            continue;
        }
        
        $subject = "Certification Expiring: " . $cert['cert_name'];
        $message = "Hi {$cert['username']},\n\n";
        $message .= "Your certification '{$cert['cert_name']}' is expiring in {$days_left} day" . ($days_left > 1 ? 's' : '') . ".\n\n";
        $message .= "Expiry Date: " . date('F j, Y', strtotime($cert['expiry_date'])) . "\n\n";
        $message .= "Please arrange for recertification if needed.\n\n";
        $message .= "- " . getCommunityName();
        
        if (sendEmail($cert['email'], $subject, $message)) {
            $alerts_sent++;
            createNotification($cert['user_id'], 'Certification Expiring', "Your {$cert['cert_name']} certification expires in {$days_left} days", 'warning');
        }
    }
    
    $results[] = "Sent $alerts_sent certification expiry alerts";
}

// =========================================
// WEEKLY ACTIVITY REPORTS
// =========================================
if ($task === 'weekly') {
    $results[] = "=== Weekly Activity Reports ===";
    $reports_sent = 0;
    
    try {
        // Check if required tables exist
        $tables_exist = $conn->query("SHOW TABLES LIKE 'user_email_preferences'");
        if ($tables_exist->num_rows === 0) {
            $results[] = "Skipped: user_email_preferences table not found";
        } else {
            // Check which optional tables exist
            $has_training = $conn->query("SHOW TABLES LIKE 'training_records'")->num_rows > 0;
            $has_patrol = $conn->query("SHOW TABLES LIKE 'patrol_logs'")->num_rows > 0;
            $has_sessions = $conn->query("SHOW TABLES LIKE 'sessions'")->num_rows > 0;
            
            $users = $conn->query("
                SELECT u.id, u.username, u.email
                FROM users u
                LEFT JOIN user_email_preferences uep ON u.id = uep.user_id
                WHERE (uep.weekly_activity_report = 1 OR uep.user_id IS NULL) AND u.is_approved = 1
            ");
            
            if ($users) {
                $users = $users->fetch_all(MYSQLI_ASSOC);
                
                foreach ($users as $user) {
                    $week_start = date('Y-m-d', strtotime('-7 days'));
                    $stats = [
                        'training_hours' => 0,
                        'patrol_minutes' => 0,
                        'sessions' => 0
                    ];
                    
                    // Training hours
                    if ($has_training) {
                        $stmt = $conn->prepare("SELECT COALESCE(SUM(hours), 0) as total FROM training_records WHERE trainee_id = ? AND session_date >= ?");
                        if ($stmt) {
                            $stmt->bind_param("is", $user['id'], $week_start);
                            $stmt->execute();
                            $result_row = $stmt->get_result()->fetch_assoc();
                            $stats['training_hours'] = $result_row['total'] ?? 0;
                            $stmt->close();
                        }
                    }
                    
                    // Patrol time
                    if ($has_patrol) {
                        $stmt = $conn->prepare("SELECT COALESCE(SUM(duration_minutes), 0) as total FROM patrol_logs WHERE user_id = ? AND created_at >= ?");
                        if ($stmt) {
                            $stmt->bind_param("is", $user['id'], $week_start);
                            $stmt->execute();
                            $result_row = $stmt->get_result()->fetch_assoc();
                            $stats['patrol_minutes'] = $result_row['total'] ?? 0;
                            $stmt->close();
                        }
                    }
                    
                    // Sessions
                    if ($has_sessions) {
                        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM sessions WHERE user_id = ? AND clock_in >= ?");
                        if ($stmt) {
                            $stmt->bind_param("is", $user['id'], $week_start);
                            $stmt->execute();
                            $result_row = $stmt->get_result()->fetch_assoc();
                            $stats['sessions'] = $result_row['cnt'] ?? 0;
                            $stmt->close();
                        }
                    }
                    
                    $subject = "Your Weekly Activity Report - " . getCommunityName();
                    $message = "Hi {$user['username']},\n\n";
                    $message .= "Here's your activity summary for the past week:\n\n";
                    $message .= "Training Hours: " . round($stats['training_hours'], 1) . "\n";
                    $message .= "Patrol Time: " . round($stats['patrol_minutes'] / 60, 1) . " hours\n";
                    $message .= "Sessions: " . $stats['sessions'] . "\n\n";
                    $message .= "Keep up the great work!\n\n";
                    $message .= "- " . getCommunityName() . "\n\n";
                    $message .= "To unsubscribe, visit Email Preferences in your profile settings.";
                    
                    if (function_exists('sendEmail') && sendEmail($user['email'], $subject, $message)) {
                        $reports_sent++;
                    }
                }
            }
            
            $results[] = "Sent $reports_sent weekly reports";
        }
    } catch (Exception $e) {
        $results[] = "Error: " . $e->getMessage();
    }
}

// =========================================
// MONTHLY ACTIVITY REPORTS
// =========================================
if ($task === 'monthly') {
    $results[] = "=== Monthly Activity Reports ===";
    $reports_sent = 0;
    
    try {
        // Check if required tables exist
        $tables_exist = $conn->query("SHOW TABLES LIKE 'user_email_preferences'");
        if ($tables_exist->num_rows === 0) {
            $results[] = "Skipped: user_email_preferences table not found";
        } else {
            // Check which optional tables exist
            $has_training = $conn->query("SHOW TABLES LIKE 'training_records'")->num_rows > 0;
            $has_patrol = $conn->query("SHOW TABLES LIKE 'patrol_logs'")->num_rows > 0;
            $has_sessions = $conn->query("SHOW TABLES LIKE 'sessions'")->num_rows > 0;
            $has_certs = $conn->query("SHOW TABLES LIKE 'user_certifications'")->num_rows > 0;
            
            $users = $conn->query("
                SELECT u.id, u.username, u.email
                FROM users u
                LEFT JOIN user_email_preferences uep ON u.id = uep.user_id
                WHERE (uep.monthly_activity_report = 1 OR uep.user_id IS NULL) AND u.is_approved = 1
            ");
            
            if ($users) {
                $users = $users->fetch_all(MYSQLI_ASSOC);
                
                foreach ($users as $user) {
                    $month_start = date('Y-m-01', strtotime('-1 month'));
                    $month_end = date('Y-m-t', strtotime('-1 month'));
                    $month_name = date('F Y', strtotime('-1 month'));
                    
                    $stats = [
                        'training_hours' => 0,
                        'patrol_minutes' => 0,
                        'sessions' => 0,
                        'session_minutes' => 0,
                        'new_certs' => 0
                    ];
                    
                    if ($has_training) {
                        $stmt = $conn->prepare("SELECT COALESCE(SUM(hours), 0) as total FROM training_records WHERE trainee_id = ? AND session_date BETWEEN ? AND ?");
                        if ($stmt) {
                            $stmt->bind_param("iss", $user['id'], $month_start, $month_end);
                            $stmt->execute();
                            $result_row = $stmt->get_result()->fetch_assoc();
                            $stats['training_hours'] = $result_row['total'] ?? 0;
                            $stmt->close();
                        }
                    }
                    
                    if ($has_patrol) {
                        $stmt = $conn->prepare("SELECT COALESCE(SUM(duration_minutes), 0) as total FROM patrol_logs WHERE user_id = ? AND created_at BETWEEN ? AND ?");
                        if ($stmt) {
                            $stmt->bind_param("iss", $user['id'], $month_start, $month_end);
                            $stmt->execute();
                            $result_row = $stmt->get_result()->fetch_assoc();
                            $stats['patrol_minutes'] = $result_row['total'] ?? 0;
                            $stmt->close();
                        }
                    }
                    
                    if ($has_sessions) {
                        $stmt = $conn->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(TIMESTAMPDIFF(MINUTE, clock_in, COALESCE(clock_out, NOW()))), 0) as minutes FROM sessions WHERE user_id = ? AND clock_in BETWEEN ? AND ?");
                        if ($stmt) {
                            $stmt->bind_param("iss", $user['id'], $month_start, $month_end);
                            $stmt->execute();
                            $session_stats = $stmt->get_result()->fetch_assoc();
                            $stats['sessions'] = $session_stats['cnt'] ?? 0;
                            $stats['session_minutes'] = $session_stats['minutes'] ?? 0;
                            $stmt->close();
                        }
                    }
                    
                    if ($has_certs) {
                        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM user_certifications WHERE user_id = ? AND issued_date BETWEEN ? AND ?");
                        if ($stmt) {
                            $stmt->bind_param("iss", $user['id'], $month_start, $month_end);
                            $stmt->execute();
                            $result_row = $stmt->get_result()->fetch_assoc();
                            $stats['new_certs'] = $result_row['cnt'] ?? 0;
                            $stmt->close();
                        }
                    }
                    
                    $subject = "Your Monthly Report for $month_name - " . getCommunityName();
                    $message = "Hi {$user['username']},\n\n";
                    $message .= "Here's your activity summary for $month_name:\n\n";
                    $message .= "TRAINING\n";
                    $message .= "  Hours: " . round($stats['training_hours'], 1) . "\n\n";
                    $message .= "ACTIVITY\n";
                    $message .= "  Patrol Time: " . round($stats['patrol_minutes'] / 60, 1) . " hours\n";
                    $message .= "  Sessions: " . $stats['sessions'] . "\n";
                    $message .= "  Total Time: " . round($stats['session_minutes'] / 60, 1) . " hours\n\n";
                    $message .= "CERTIFICATIONS\n";
                    $message .= "  New Certifications: " . $stats['new_certs'] . "\n\n";
                    $message .= "Thank you for your dedication!\n\n";
                    $message .= "- " . getCommunityName() . "\n\n";
                    $message .= "To unsubscribe, visit Email Preferences.";
                    
                    if (function_exists('sendEmail') && sendEmail($user['email'], $subject, $message)) {
                        $reports_sent++;
                    }
                }
            }
            
            $results[] = "Sent $reports_sent monthly reports";
        }
    } catch (Exception $e) {
        $results[] = "Error: " . $e->getMessage();
    }
}

$conn->close();
$execution_time = round((microtime(true) - $start_time) * 1000, 2);

// Output results
if ($is_cli) {
    foreach ($results as $r) {
        echo $r . "\n";
    }
    echo "\nExecution time: {$execution_time}ms\n";
} elseif (isset($_GET['format']) && $_GET['format'] === 'json') {
    // JSON output for API calls
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'task' => $task,
        'results' => $results,
        'execution_time_ms' => $execution_time
    ]);
} elseif (!empty($provided_token) && $token_auth) {
    // External cron service with token - return minimal output
    // This prevents "output too large" errors from cron services
    header('Content-Type: text/plain');
    $result_count = count($results);
    echo "OK - Task: $task - Results: $result_count - Time: {$execution_time}ms";
} else {
    $theme = getThemeColors();
    $cron_token = getSetting('cron_token', '');
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scheduled Tasks - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>⏰ Scheduled Tasks</h1>
    </div>
    
    <?php showPageToasts(); ?>
    
    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 24px; margin-bottom: 24px;">
        <h3>Task Result: <?php echo htmlspecialchars($task); ?></h3>
        <div style="margin-top: 16px; font-family: monospace; background: rgba(0,0,0,0.2); padding: 16px; border-radius: var(--radius-sm);">
            <?php foreach ($results as $r): ?>
                <div style="padding: 4px 0;"><?php echo htmlspecialchars($r); ?></div>
            <?php endforeach; ?>
            <div style="padding: 4px 0; color: var(--text-muted); margin-top: 8px;">
                Execution time: <?php echo $execution_time; ?>ms
            </div>
        </div>
    </div>
    
    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 24px; margin-bottom: 24px;">
        <h3>Run Tasks</h3>
        <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 16px;">
            <a href="?task=daily" class="btn <?php echo $task === 'daily' ? 'btn-primary' : ''; ?>">📅 Daily (Cert Alerts)</a>
            <a href="?task=weekly" class="btn <?php echo $task === 'weekly' ? 'btn-primary' : ''; ?>">📊 Weekly Reports</a>
            <a href="?task=monthly" class="btn <?php echo $task === 'monthly' ? 'btn-primary' : ''; ?>">📈 Monthly Reports</a>
        </div>
    </div>
    
    <div style="background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 24px;">
        <h3>🔧 External Cron Setup</h3>
        <p style="color: var(--text-secondary); margin: 12px 0;">Use these URLs with external cron services like cron-job.org:</p>
        
        <h4 style="margin-top: 20px;">Your Cron Token</h4>
        <div style="display: flex; align-items: center; gap: 12px; margin: 8px 0;">
            <code style="flex: 1; font-family: monospace; background: rgba(0,0,0,0.3); padding: 12px 16px; border-radius: var(--radius-sm); font-size: 13px; word-break: break-all;">
                <?php echo htmlspecialchars($cron_token); ?>
            </code>
            <button onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($cron_token); ?>'); Toast.success('Token copied!');" class="btn btn-sm">📋 Copy</button>
        </div>
        
        <h4 style="margin-top: 20px;">HTTP Endpoints</h4>
        <div style="font-family: monospace; background: rgba(0,0,0,0.2); padding: 16px; border-radius: var(--radius-sm); margin-top: 8px; font-size: 12px; line-height: 1.8;">
            <div style="margin-bottom: 12px;">
                <strong style="color: var(--accent);">Daily (Certification Alerts):</strong><br>
                <?php echo htmlspecialchars($base_url); ?>/cron/scheduled_tasks.php?task=daily&token=<?php echo htmlspecialchars($cron_token); ?>
            </div>
            <div style="margin-bottom: 12px;">
                <strong style="color: var(--accent);">Weekly Reports:</strong><br>
                <?php echo htmlspecialchars($base_url); ?>/cron/scheduled_tasks.php?task=weekly&token=<?php echo htmlspecialchars($cron_token); ?>
            </div>
            <div>
                <strong style="color: var(--accent);">Monthly Reports:</strong><br>
                <?php echo htmlspecialchars($base_url); ?>/cron/scheduled_tasks.php?task=monthly&token=<?php echo htmlspecialchars($cron_token); ?>
            </div>
        </div>
        
        <h4 style="margin-top: 24px;">CLI Setup (Server Crontab)</h4>
        <div style="font-family: monospace; background: rgba(0,0,0,0.2); padding: 16px; border-radius: var(--radius-sm); margin-top: 8px; font-size: 12px; line-height: 2;">
            # Daily at 8am (cert alerts)<br>
            0 8 * * * php <?php echo __FILE__; ?> daily<br><br>
            # Weekly on Monday at 8am<br>
            0 8 * * 1 php <?php echo __FILE__; ?> weekly<br><br>
            # Monthly on 1st at 8am<br>
            0 8 1 * * php <?php echo __FILE__; ?> monthly
        </div>
        
        <div style="margin-top: 20px; padding: 12px 16px; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: var(--radius-sm);">
            <p style="font-size: 13px; color: var(--text-secondary); margin: 0;">
                💡 <strong>Tip:</strong> Add <code>&format=json</code> to get JSON output for monitoring.
            </p>
        </div>
    </div>
</div>
</body>
</html>
<?php } ?>
