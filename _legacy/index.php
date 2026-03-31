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
// Check if system is installed
if (!file_exists('config.php') || (file_exists('install/index.php') && !defined('INSTALLED'))) {
    // Check if config.php exists and has INSTALLED constant
    if (file_exists('config.php')) {
        $config_content = file_get_contents('config.php');
        if (strpos($config_content, "define('INSTALLED', true)") === false) {
            header('Location: /install/');
            exit();
        }
    } else {
        header('Location: /install/');
        exit();
    }
}

require_once 'config.php';
if (!defined('UM_FUNCTIONS_LOADED')) { require_once __DIR__ . '/includes/functions.php'; }
if (!defined('UM_EMAIL_LOADED')) { require_once __DIR__ . '/includes/email.php'; }

// Include discord.php for login token check
if (file_exists(__DIR__ . '/includes/discord.php')) {
    require_once __DIR__ . '/includes/discord.php';
}

// Check for Discord login token (bypasses session issues after cross-site redirect)
if (!isLoggedIn() && function_exists('checkLoginToken')) {
    checkLoginToken();
}

requireLogin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get quick links from database
$ql_check = $conn->query("SHOW TABLES LIKE 'quick_links'");
$quick_links = [];
if ($ql_check && $ql_check->num_rows > 0) {
    $quick_links_result = $conn->query("SELECT * FROM quick_links WHERE is_active = TRUE ORDER BY sort_order ASC, id ASC");
    if ($quick_links_result && $quick_links_result->num_rows > 0) {
        while ($link = $quick_links_result->fetch_assoc()) {
            $quick_links[] = $link;
        }
    }
}

// Get all departments with member counts
$departments = $conn->query("
    SELECT d.*, 
           (SELECT COUNT(*) FROM roster WHERE department_id = d.id) as member_count,
           (SELECT COUNT(*) FROM roster WHERE department_id = d.id AND status = 'active') as active_count
    FROM departments d 
    ORDER BY d.name
");

// Dashboard widgets data
$is_admin_user = isAdmin();

// Community stats (consolidated into single query)
$stats_result = $conn->query("SELECT 
    (SELECT COUNT(*) FROM users WHERE is_approved = TRUE) as total_members,
    (SELECT COUNT(*) FROM roster WHERE status = 'active') as active_roster,
    (SELECT COUNT(*) FROM roster WHERE status = 'loa') as loa_count,
    (SELECT COUNT(*) FROM departments) as dept_count
");
$stats = $stats_result ? $stats_result->fetch_assoc() : [];
$total_members = $stats['total_members'] ?? 0;
$active_roster = $stats['active_roster'] ?? 0;
$loa_count = $stats['loa_count'] ?? 0;
$dept_count = $stats['dept_count'] ?? 0;

// Pending items (for admins/managers) - consolidated into single query
$pending_items = [];
if ($is_admin_user || hasAnyPermission(['admin.users', 'apps.review', 'activity.manage', 'roster.promote'])) {
    $pending_result = $conn->query("SELECT 
        (SELECT COUNT(*) FROM users WHERE is_approved = FALSE) as pending_approvals,
        (SELECT COUNT(*) FROM applications WHERE status = 'pending') as pending_apps,
        (SELECT COUNT(*) FROM activity_logs WHERE status = 'pending') as pending_activity,
        (SELECT COUNT(*) FROM promotion_requests WHERE status = 'pending') as pending_promos,
        (SELECT COUNT(*) FROM loa_requests WHERE status = 'pending') as pending_loas
    ");
    $pending = $pending_result ? $pending_result->fetch_assoc() : [];
    
    if (($is_admin_user || hasPermission('admin.users')) && ($pending['pending_approvals'] ?? 0) > 0)
        $pending_items[] = ['icon' => '👤', 'text' => $pending['pending_approvals'] . " pending user approval(s)", 'url' => '/admin/index'];
    if (($is_admin_user || hasPermission('apps.review')) && ($pending['pending_apps'] ?? 0) > 0)
        $pending_items[] = ['icon' => '📋', 'text' => $pending['pending_apps'] . " pending application(s)", 'url' => '/admin/applications'];
    if (($is_admin_user || hasPermission('activity.manage')) && ($pending['pending_activity'] ?? 0) > 0)
        $pending_items[] = ['icon' => '📊', 'text' => $pending['pending_activity'] . " unverified activity log(s)", 'url' => '/admin/activity'];
    if (($is_admin_user || hasPermission('roster.promote')) && ($pending['pending_promos'] ?? 0) > 0)
        $pending_items[] = ['icon' => '⬆️', 'text' => $pending['pending_promos'] . " pending promotion(s)", 'url' => '/admin/promotions'];
    if (($pending['pending_loas'] ?? 0) > 0)
        $pending_items[] = ['icon' => '📅', 'text' => $pending['pending_loas'] . " pending LOA request(s)", 'url' => '/user/loa'];
}

// Expiring certifications (user's own)
$expiring_certs = [];
$cert_check = $conn->query("SHOW TABLES LIKE 'user_certifications'");
if ($cert_check && $cert_check->num_rows > 0) {
    $stmt_cert = $conn->prepare("SELECT uc.*, ct.name as cert_name FROM user_certifications uc 
        JOIN certification_types ct ON uc.certification_type_id = ct.id 
        WHERE uc.user_id = ? AND uc.status = 'completed' AND uc.expiry_date IS NOT NULL 
        AND uc.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY uc.expiry_date ASC LIMIT 5");
    $stmt_cert->bind_param("i", $user_id);
    $stmt_cert->execute();
    $cert_result = $stmt_cert->get_result();
    if ($cert_result) {
        while ($cert = $cert_result->fetch_assoc()) {
            $expiring_certs[] = $cert;
        }
    }
}

// Recent activity feed (last 5 community-wide events)
$recent_feed = $conn->query("SELECT al.action, al.details, al.created_at, u.username 
    FROM audit_log al LEFT JOIN users u ON al.user_id = u.id 
    WHERE al.action IN ('user_login', 'create_announcement', 'application_submitted', 'promotion', 'create_rank', 'user_approved')
    ORDER BY al.created_at DESC LIMIT 5");

// === PERSONAL DASHBOARD DATA ===

// My upcoming LOA
$my_loa = [];
$stmt_loa = $conn->prepare("SELECT * FROM loa_requests WHERE user_id = ? AND status = 'approved' AND end_date >= CURDATE() ORDER BY start_date ASC LIMIT 3");
if ($stmt_loa) {
    $stmt_loa->bind_param("i", $user_id);
    $stmt_loa->execute();
    $loa_result = $stmt_loa->get_result();
    while ($row = $loa_result->fetch_assoc()) $my_loa[] = $row;
    $stmt_loa->close();
}

// My unacknowledged SOPs
$unacked_sops = 0;
$my_dept_ids = [];
$stmt_mydepts = $conn->prepare("SELECT department_id FROM roster WHERE user_id = ?");
if ($stmt_mydepts) {
    $stmt_mydepts->bind_param("i", $user_id);
    $stmt_mydepts->execute();
    $mydept_result = $stmt_mydepts->get_result();
    while ($row = $mydept_result->fetch_assoc()) $my_dept_ids[] = $row['department_id'];
    $stmt_mydepts->close();
}
if (!empty($my_dept_ids)) {
    $dept_placeholders = implode(',', array_fill(0, count($my_dept_ids), '?'));
    $sop_sql = "SELECT COUNT(*) as cnt FROM department_sops s 
        WHERE s.department_id IN ($dept_placeholders) AND s.is_active = TRUE 
        AND s.id NOT IN (SELECT sop_id FROM sop_acknowledgments WHERE user_id = ?)";
    $stmt_sop = $conn->prepare($sop_sql);
    $types = str_repeat('i', count($my_dept_ids)) . 'i';
    $params = array_merge($my_dept_ids, [$user_id]);
    $stmt_sop->bind_param($types, ...$params);
    $stmt_sop->execute();
    $sop_result = $stmt_sop->get_result()->fetch_assoc();
    $unacked_sops = $sop_result['cnt'] ?? 0;
    $stmt_sop->close();
}

// My active certifications count
$my_cert_count = 0;
$stmt_certcnt = $conn->prepare("SELECT COUNT(*) as cnt FROM user_certifications WHERE user_id = ? AND status = 'completed'");
$stmt_certcnt->bind_param("i", $user_id);
$stmt_certcnt->execute();
$cert_cnt_result = $stmt_certcnt->get_result();
if ($cert_cnt_result) {
    $my_cert_count = $cert_cnt_result->fetch_assoc()['cnt'] ?? 0;
}
$stmt_certcnt->close();

// My recent activity hours (last 30 days)
$my_activity_hours = 0;
$stmt_acthrs = $conn->prepare("SELECT COALESCE(SUM(duration_minutes), 0) as total FROM activity_logs WHERE user_id = ? AND status = 'verified' AND activity_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$stmt_acthrs->bind_param("i", $user_id);
$stmt_acthrs->execute();
$act_result = $stmt_acthrs->get_result();
if ($act_result) {
    $my_activity_hours = round(($act_result->fetch_assoc()['total'] ?? 0) / 60, 1);
}
$stmt_acthrs->close();

$current_page = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <?php include 'includes/styles.php'; ?>
    <style>
        /* Welcome Section */
        .welcome {
            background: var(--bg-card);
            border: 1px solid var(--border);
            padding: 32px;
            border-radius: var(--radius-lg);
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
        }
        
        .welcome::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--accent);
        }
        
        .welcome h2 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }
        
        .welcome p {
            color: var(--text-muted);
            font-size: 15px;
            margin-bottom: 24px;
        }
        
        .quick-links {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .quick-links a {
            padding: 10px 18px;
            background: var(--accent);
            color: white;
            text-decoration: none;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(88, 101, 242, 0.35);
        }
        
        .quick-links a:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        
        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 600px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
        
        /* Departments Grid */
        .departments-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }
        
        @media (max-width: 1200px) {
            .departments-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .departments-grid { grid-template-columns: 1fr; }
        }
        
        .dept-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            padding: 24px;
            border-radius: var(--radius-lg);
            transition: all 0.2s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            min-height: 160px;
        }
        
        .dept-card:hover {
            transform: translateY(-4px);
            border-color: var(--accent);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        }
        
        .dept-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: auto;
        }
        
        .dept-icon {
            width: 52px;
            height: 52px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            background: var(--bg-elevated);
            flex-shrink: 0;
        }
        
        .dept-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: var(--radius-md);
        }
        
        .dept-info {
            flex: 1;
            min-width: 0;
        }
        
        .dept-info h3 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .dept-abbr {
            color: var(--text-muted);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .dept-stats {
            display: flex;
            gap: 24px;
            padding-top: 16px;
            margin-top: 16px;
            border-top: 1px solid var(--border);
            justify-content: center;
        }
        
        .stat {
            text-align: center;
        }
        
        .stat-value {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-primary);
            display: block;
            letter-spacing: -0.02em;
        }
        
        .stat-label {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 2px;
            font-weight: 600;
        }
        
        /* Section Headers */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .section-header h2 {
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-header a {
            font-size: 13px;
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
        }
        
        .section-header a:hover {
            color: var(--accent-hover);
        }
        
        /* Activity List */
        .activity-list {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            transition: background 0.15s ease;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-item:hover {
            background: var(--bg-hover);
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            background: var(--bg-elevated);
            flex-shrink: 0;
        }
        
        .activity-content {
            flex: 1;
            min-width: 0;
        }
        
        .activity-text {
            font-size: 14px;
            color: var(--text-primary);
        }
        
        .activity-text strong {
            font-weight: 600;
        }
        
        .activity-time {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }
        
        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 28px;
        }
        
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Personal Stats */
        .personal-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        
        @media (max-width: 768px) {
            .personal-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        .personal-stat {
            background: var(--bg-elevated);
            border-radius: var(--radius-md);
            padding: 16px;
            text-align: center;
        }
        
        .personal-stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .personal-stat-label {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
        }
        
        /* Mobile adjustments */
        @media (max-width: 768px) {
            .welcome {
                padding: 24px;
            }
            .welcome h2 {
                font-size: 20px;
            }
            .welcome p {
                font-size: 14px;
                margin-bottom: 16px;
            }
            .quick-links {
                gap: 8px;
            }
            .quick-links a {
                padding: 10px 14px;
                font-size: 13px;
            }
            .dept-card {
                padding: 20px;
                min-height: 140px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        <div class="welcome">
            <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
            <p>Select a department below to view rosters and member information.</p>
            <div class="quick-links">
                <?php if (!empty($quick_links)): ?>
                    <?php foreach ($quick_links as $link): ?>
                        <a href="<?php echo htmlspecialchars($link['url']); ?>" title="<?php echo htmlspecialchars($link['title']); ?>"><?php echo htmlspecialchars($link['icon'] . ' ' . $link['title']); ?></a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <a href="/user/loa" title="Submit a Leave of Absence">📅 Request LOA</a>
                    <a href="/user/loa_calendar" title="View LOA Calendar">📆 LOA Calendar</a>
                    <a href="/user/messages" title="View your messages">✉️ Messages</a>
                    <a href="/user/announcements" title="View announcements">📢 Announcements</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Dashboard Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_members; ?></div>
                <div class="stat-label">Total Members</div>
            </div>
            <div class="stat-card success">
                <div class="stat-value"><?php echo $active_roster; ?></div>
                <div class="stat-label">Active Roster</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-value"><?php echo $loa_count; ?></div>
                <div class="stat-label">On LOA</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $dept_count; ?></div>
                <div class="stat-label">Departments</div>
            </div>
        </div>

        <!-- Personal Stats -->
        <div class="card" style="margin-bottom: 24px;">
            <h2 style="font-size: 16px; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <span>👤</span> My Summary
            </h2>
            <div class="personal-stats">
                <div class="personal-stat">
                    <div class="personal-stat-value" style="color: var(--accent);"><?php echo $my_cert_count; ?></div>
                    <div class="personal-stat-label">Certifications</div>
                </div>
                <div class="personal-stat">
                    <div class="personal-stat-value" style="color: var(--success);"><?php echo $my_activity_hours; ?>h</div>
                    <div class="personal-stat-label">Activity (30d)</div>
                </div>
                <div class="personal-stat">
                    <div class="personal-stat-value" style="color: <?php echo count($my_loa) > 0 ? 'var(--warning)' : 'var(--text-faint)'; ?>;"><?php echo count($my_loa); ?></div>
                    <div class="personal-stat-label">Upcoming LOA</div>
                </div>
                <a href="/user/sops" class="personal-stat" style="text-decoration: none;">
                    <div class="personal-stat-value" style="color: <?php echo $unacked_sops > 0 ? 'var(--danger)' : 'var(--text-faint)'; ?>;"><?php echo $unacked_sops; ?></div>
                    <div class="personal-stat-label">Pending SOPs</div>
                </a>
            </div>
        </div>

        <!-- Widgets Row -->
        <?php if (!empty($pending_items) || !empty($expiring_certs)): ?>
        <div class="dashboard-grid" style="margin-bottom: 24px; margin-top: 0;">
            <?php if (!empty($pending_items)): ?>
            <div class="card">
                <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                    <span>⚡</span> Needs Attention
                </h3>
                <?php foreach ($pending_items as $item): ?>
                <a href="<?php echo $item['url']; ?>" class="list-item" style="text-decoration: none; border-radius: var(--radius-md); background: var(--warning-muted); border: 1px solid rgba(240,178,50,0.2); margin-bottom: 8px;">
                    <span style="font-size: 18px;"><?php echo $item['icon']; ?></span>
                    <span style="flex: 1; color: var(--warning);"><?php echo $item['text']; ?></span>
                    <span style="color: var(--text-faint);">→</span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($expiring_certs)): ?>
            <div class="card">
                <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                    <span>📜</span> Certifications Expiring
                </h3>
                <?php foreach ($expiring_certs as $cert): ?>
                <div class="list-item" style="border-radius: var(--radius-md); background: var(--danger-muted); border: 1px solid rgba(218,55,60,0.2); margin-bottom: 8px;">
                    <span style="color: var(--danger);"><?php echo htmlspecialchars($cert['cert_name']); ?></span>
                    <span style="color: var(--text-muted); font-size: 12px; margin-left: auto;">Expires <?php echo date('M j', strtotime($cert['expiry_date'])); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if (empty($expiring_certs) && !empty($pending_items)): ?>
            <!-- Recent Activity for second column -->
            <div class="card">
                <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                    <span>📰</span> Recent Activity
                </h3>
                <?php if ($recent_feed && $recent_feed->num_rows > 0): ?>
                    <?php while ($feed = $recent_feed->fetch_assoc()): ?>
                    <div class="list-item" style="padding: 10px 0; border-bottom: 1px solid var(--border);">
                        <span style="color: var(--text-secondary); font-size: 13px;"><?php echo htmlspecialchars($feed['username'] ?? 'System'); ?> — <?php echo htmlspecialchars(str_replace('_', ' ', $feed['action'])); ?></span>
                        <span style="color: var(--text-muted); font-size: 11px; margin-left: auto;"><?php echo date('M j, g:ia', strtotime($feed['created_at'])); ?></span>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="color: var(--text-muted); font-size: 13px;">No recent activity</div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="departments-grid">
            <?php if ($departments): while ($dept = $departments->fetch_assoc()): ?>
                <?php 
                $has_dept_logo = !empty($dept['logo_path']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $dept['logo_path']);
                ?>
                <a href="user/roster?dept=<?php echo $dept['id']; ?>" class="dept-card">
                    <div class="dept-header">
                        <div class="dept-icon">
                            <?php if ($has_dept_logo): ?>
                                <img src="<?php echo htmlspecialchars($dept['logo_path']); ?>" alt="<?php echo htmlspecialchars($dept['name']); ?>">
                            <?php else: ?>
                                ?
                            <?php endif; ?>
                        </div>
                        <div class="dept-info">
                            <h3><?php echo htmlspecialchars($dept['name']); ?></h3>
                            <div class="dept-abbr"><?php echo htmlspecialchars($dept['abbreviation']); ?></div>
                        </div>
                    </div>
                    <div class="dept-stats">
                        <div class="stat">
                            <div class="stat-value"><?php echo $dept['member_count']; ?></div>
                            <div class="stat-label">Total</div>
                        </div>
                        <div class="stat">
                            <div class="stat-value"><?php echo $dept['active_count']; ?></div>
                            <div class="stat-label">Active</div>
                        </div>
                    </div>
                </a>
            <?php endwhile; endif; ?>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
