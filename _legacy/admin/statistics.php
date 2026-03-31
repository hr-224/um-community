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
requireAdmin();

$conn = getDBConnection();

// Get department filter
$dept_filter = $_GET['dept'] ?? '';

// Get all departments with stats
$dept_stats = $conn->query("
    SELECT d.*,
           (SELECT COUNT(*) FROM roster WHERE department_id = d.id) as total_members,
           (SELECT COUNT(*) FROM roster WHERE department_id = d.id AND status = 'active') as active_members,
           (SELECT COUNT(*) FROM roster WHERE department_id = d.id AND status = 'loa') as on_loa,
           (SELECT COUNT(*) FROM roster WHERE department_id = d.id AND status = 'inactive') as inactive_members,
           (SELECT COUNT(*) FROM roster WHERE department_id = d.id AND joined_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as new_joins_30d
    FROM departments d
    ORDER BY d.name
");

// Get detailed stats for selected department
$detail_stats = null;
$roster_breakdown = null;
$rank_distribution = null;

if ($dept_filter) {
    $dept_id = intval($dept_filter);
    
    // Get roster breakdown by rank
    $rank_distribution = $conn->query("
        SELECT r.rank_name, COUNT(ro.id) as count
        FROM ranks r
        LEFT JOIN roster ro ON r.id = ro.rank_id AND ro.department_id = $dept_id
        WHERE r.department_id = $dept_id
        GROUP BY r.id
        ORDER BY r.rank_order
    ");
    
    // Get recent activity
    $recent_activity = $conn->query("
        SELECT a.*, u.username
        FROM audit_log a
        JOIN users u ON a.user_id = u.id
        WHERE a.details LIKE '%department%' OR a.target_type = 'roster'
        ORDER BY a.created_at DESC
        LIMIT 20
    ");
}

// Community-wide stats
$community_stats = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE is_approved = TRUE) as total_users,
        (SELECT COUNT(*) FROM roster) as total_roster_entries,
        (SELECT COUNT(*) FROM loa_requests WHERE status = 'approved' AND end_date >= CURDATE()) as current_loas,
        (SELECT COUNT(*) FROM loa_requests WHERE status = 'pending') as pending_loas,
        (SELECT COUNT(*) FROM applications WHERE status = 'pending') as pending_applications
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Statistics - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <?php include '../includes/styles.php'; ?>
    <style>
        .container { max-width: 1400px; }
        
        .community-stats {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 32px;
        }
        @media (max-width: 1024px) { .community-stats { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 600px) { .community-stats { grid-template-columns: repeat(2, 1fr); } }
        
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--bg-elevated);
            padding: 24px;
            border-radius: var(--radius-lg);
            text-align: center;
        }
        .stat-value { font-size: 36px; font-weight: 800; color: var(--text-primary); }
        .stat-label { font-size: 13px; color: var(--text-muted); margin-top: 4px; }
        
        .section {
            background: var(--bg-card);
            border: 1px solid var(--bg-elevated);
            padding: 32px;
            border-radius: var(--radius-lg);
            margin-bottom: 24px;
        }
        .section h2 { margin-bottom: 24px; padding-bottom: 12px; border-bottom: 2px solid rgba(88, 101, 242, 0.3); font-size: 20px; }
        
        .dept-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
        }
        
        .dept-card {
            background: var(--bg-elevated);
            border: 1px solid var(--bg-elevated);
            border-radius: var(--radius-lg);
            padding: 24px;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .dept-card:hover { background: var(--accent-muted); border-color: var(--accent); transform: translateY(-2px); }
        .dept-card.active { border-color: var(--accent); background: var(--accent-muted); }
        
        .dept-header { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
        .dept-icon { font-size: 36px; }
        .dept-name { font-weight: 700; font-size: 16px; }
        .dept-abbr { font-size: 12px; color: var(--text-muted); }
        
        .dept-stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
        .dept-stat { text-align: center; padding: 12px 8px; background: var(--bg-elevated); border-radius: var(--radius-sm); }
        .dept-stat-value { font-size: 20px; font-weight: 700; }
        .dept-stat-value.active { color: #4ade80; }
        .dept-stat-value.loa { color: #f0b232; }
        .dept-stat-value.inactive { color: #f87171; }
        .dept-stat-value.new { color: #93c5fd; }
        .dept-stat-label { font-size: 10px; color: var(--text-muted); text-transform: uppercase; margin-top: 4px; }
        
        .rank-bars { margin-top: 16px; }
        .rank-bar { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
        .rank-name { width: 120px; font-size: 13px; }
        .rank-bar-bg { flex: 1; height: 24px; background: var(--bg-card); border-radius: 4px; overflow: hidden; }
        .rank-bar-fill { height: 100%; background: var(--accent); border-radius: 4px; display: flex; align-items: center; justify-content: flex-end; padding-right: 8px; font-size: 12px; font-weight: 600; min-width: 30px; }
        
        .empty-state { text-align: center; padding: 40px; color: var(--text-muted); }
    </style>
</head>
<body>
    <?php $current_page = 'admin_stats'; include '../includes/navbar.php'; ?>

    <div class="container">
        <div class="community-stats">
            <div class="stat-card">
                <div class="stat-value"><?php echo $community_stats['total_users']; ?></div>
                <div class="stat-label">Total Members</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $community_stats['total_roster_entries']; ?></div>
                <div class="stat-label">Roster Entries</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $community_stats['current_loas']; ?></div>
                <div class="stat-label">Current LOAs</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $community_stats['pending_loas']; ?></div>
                <div class="stat-label">Pending LOAs</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $community_stats['pending_applications']; ?></div>
                <div class="stat-label">Pending Apps</div>
            </div>
        </div>

        <div class="section">
            <h2>Department Overview</h2>
            <div class="dept-grid">
                <?php while ($dept = $dept_stats->fetch_assoc()): ?>
                    <a href="?dept=<?php echo $dept['id']; ?>" class="dept-card <?php echo $dept_filter == $dept['id'] ? 'active' : ''; ?>">
                        <div class="dept-header">
                            <div class="dept-icon"><?php echo $dept['icon']; ?></div>
                            <div>
                                <div class="dept-name"><?php echo htmlspecialchars($dept['name']); ?></div>
                                <div class="dept-abbr"><?php echo htmlspecialchars($dept['abbreviation']); ?></div>
                            </div>
                        </div>
                        <div class="dept-stats-row">
                            <div class="dept-stat">
                                <div class="dept-stat-value active"><?php echo $dept['active_members']; ?></div>
                                <div class="dept-stat-label">Active</div>
                            </div>
                            <div class="dept-stat">
                                <div class="dept-stat-value loa"><?php echo $dept['on_loa']; ?></div>
                                <div class="dept-stat-label">LOA</div>
                            </div>
                            <div class="dept-stat">
                                <div class="dept-stat-value inactive"><?php echo $dept['inactive_members']; ?></div>
                                <div class="dept-stat-label">Inactive</div>
                            </div>
                            <div class="dept-stat">
                                <div class="dept-stat-value new"><?php echo $dept['new_joins_30d']; ?></div>
                                <div class="dept-stat-label">New (30d)</div>
                            </div>
                        </div>
                    </a>
                <?php endwhile; ?>
            </div>
        </div>

        <?php if ($dept_filter && $rank_distribution): ?>
        <div class="section">
            <h2>Rank Distribution</h2>
            <?php 
            $max_count = 1;
            $ranks = [];
            while ($rank = $rank_distribution->fetch_assoc()) {
                $ranks[] = $rank;
                if ($rank['count'] > $max_count) $max_count = $rank['count'];
            }
            ?>
            <div class="rank-bars">
                <?php foreach ($ranks as $rank): ?>
                    <div class="rank-bar">
                        <div class="rank-name"><?php echo htmlspecialchars($rank['rank_name']); ?></div>
                        <div class="rank-bar-bg">
                            <div class="rank-bar-fill" style="width: <?php echo ($rank['count'] / $max_count) * 100; ?>%">
                                <?php echo $rank['count']; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php $conn->close(); ?>
