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
requireLogin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Filters
$search = trim($_GET['search'] ?? '');
$dept_filter = intval($_GET['dept'] ?? 0);
$status_filter = $_GET['status'] ?? '';

// Get departments for filter dropdown
$departments = $conn->query("SELECT id, name, abbreviation, color FROM departments ORDER BY name");
$dept_list = [];
if ($departments) {
    while ($d = $departments->fetch_assoc()) $dept_list[] = $d;
}

// Build member query
$where = ["u.is_approved = TRUE"];
$params = [];
$types = '';

if ($search) {
    $where[] = "(u.username LIKE ? OR u.discord_id LIKE ? OR r.badge_number LIKE ? OR r.callsign LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= 'ssss';
}

if ($dept_filter > 0) {
    $where[] = "r.department_id = ?";
    $params[] = $dept_filter;
    $types .= 'i';
}

if ($status_filter && in_array($status_filter, ['active', 'loa', 'inactive'])) {
    $where[] = "r.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where_sql = implode(' AND ', $where);

$sql = "SELECT DISTINCT u.id, u.username, u.discord_id, u.created_at,
               us.status as online_status, us.custom_status,
               GROUP_CONCAT(DISTINCT CONCAT(d.abbreviation, ':', rk.rank_name, ':', r.status, ':', d.color) ORDER BY r.is_primary DESC SEPARATOR '||') as dept_info,
               (SELECT COUNT(*) FROM roster WHERE user_id = u.id) as dept_count
        FROM users u
        LEFT JOIN roster r ON r.user_id = u.id
        LEFT JOIN departments d ON r.department_id = d.id
        LEFT JOIN ranks rk ON r.rank_id = rk.id
        LEFT JOIN user_status us ON us.user_id = u.id
        WHERE $where_sql
        GROUP BY u.id
        ORDER BY u.username ASC
        LIMIT 200";

$stmt = $conn->prepare($sql);
if ($types && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$members = $stmt->get_result();

// Total count
$total_result = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE is_approved = TRUE");
$total_row = $total_result ? $total_result->fetch_assoc() : null;
$total_members = $total_row['cnt'] ?? 0;

$current_page = 'directory';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Directory - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <?php include '../includes/styles.php'; ?>
    <style>
        .container { max-width: 1100px; }
        
        .directory-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .filters {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .filters input[type="search"],
        .filters select {
            padding: 10px 16px;
            background: var(--bg-elevated);
            border: 1px solid var(--bg-card);
            border-radius: var(--radius-md);
            color: white;
            font-size: 14px;
        }
        
        .filters input[type="search"] { min-width: 250px; flex: 1; }
        .filters select { min-width: 160px; }
        
        .member-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 16px;
        }
        
        .member-card {
            background: var(--bg-card);
            border: 1px solid var(--bg-elevated);
            border-radius: var(--radius-lg);
            padding: 20px;
            transition: all 0.2s;
        }
        
        .member-card:hover {
            border-color: var(--border);
            background: var(--bg-elevated);
        }
        
        .member-top {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 14px;
        }
        
        .member-avatar {
            width: 48px;
            height: 48px;
            min-width: 48px;
            border-radius: 50%;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            overflow: hidden;
            position: relative;
        }
        
        .member-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .status-dot-online { color: #4ade80; }
        .status-dot-away { color: #f0b232; }
        .status-dot-busy { color: #f87171; }
        .status-dot-offline { color: #6b7280; }
        
        .member-name {
            font-weight: 700;
            font-size: 16px;
            margin-bottom: 2px;
        }
        
        .member-since {
            font-size: 12px;
            color: var(--text-muted);
        }
        
        .member-depts {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        
        .dept-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: var(--radius-sm);
            font-size: 11px;
            font-weight: 600;
        }
        
        .dept-rank {
            font-size: 10px;
            color: var(--text-primary);
            padding-left: 8px;
            margin-left: 6px;
            border-left: 1px solid var(--bg-elevated);
        }
        
        .status-tag {
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .member-discord {
            margin-top: 10px;
            font-size: 12px;
            color: var(--text-muted);
        }
        
        .result-count {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 16px;
        }
        
        .empty-state {
            text-align: center;
            padding: 80px;
            color: var(--text-muted);
        }
        
        .empty-state h3 { font-size: 24px; margin-bottom: 12px; }
        
        @media (max-width: 768px) {
            .member-grid { grid-template-columns: 1fr; }
            .filters input[type="search"] { min-width: 100%; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container">
        <div class="section" style="margin-bottom: 24px;">
            <h2>👥 Member Directory</h2>
            <p style="color: var(--text-muted); margin-top: 4px;"><?php echo $total_members; ?> approved members in the community.</p>
        </div>
        
        <form method="GET" class="filters">
            <input type="search" name="search" placeholder="Search by name, badge, callsign, or Discord..." value="<?php echo htmlspecialchars($search); ?>">
            <select name="dept" onchange="this.form.submit()">
                <option value="0">All Departments</option>
                <?php foreach ($dept_list as $d): ?>
                    <option value="<?php echo $d['id']; ?>" <?php echo $dept_filter == $d['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="loa" <?php echo $status_filter === 'loa' ? 'selected' : ''; ?>>On LOA</option>
                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
            <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">Search</button>
        </form>
        
        <?php if ($members->num_rows > 0): ?>
            <div class="result-count">Showing <?php echo $members->num_rows; ?> member<?php echo $members->num_rows !== 1 ? 's' : ''; ?></div>
            
            <div class="member-grid">
                <?php while ($member = $members->fetch_assoc()): ?>
                    <?php
                    $profile_pic = getSetting('user_' . $member['id'] . '_profile_pic', '');
                    $has_pic = !empty($profile_pic) && file_exists($_SERVER['DOCUMENT_ROOT'] . $profile_pic);
                    $online = $member['online_status'] ?? 'offline';
                    $status_dots = ['online' => '🟢', 'away' => '🟡', 'busy' => '🔴', 'offline' => '⚫'];
                    
                    // Parse department info
                    $dept_entries = [];
                    if ($member['dept_info']) {
                        foreach (explode('||', $member['dept_info']) as $info) {
                            $parts = explode(':', $info, 4);
                            if (count($parts) >= 4) {
                                $dept_entries[] = [
                                    'abbr' => $parts[0],
                                    'rank' => $parts[1],
                                    'status' => $parts[2],
                                    'color' => $parts[3]
                                ];
                            }
                        }
                    }
                    ?>
                    <div class="member-card">
                        <div class="member-top">
                            <div class="member-avatar">
                                <?php if ($has_pic): ?>
                                    <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="">
                                <?php else: ?>
                                    👤
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="member-name">
                                    <?php echo $status_dots[$online] ?? '⚫'; ?>
                                    <?php echo htmlspecialchars($member['username']); ?>
                                </div>
                                <div class="member-since">Member since <?php echo date('M Y', strtotime($member['created_at'])); ?></div>
                            </div>
                        </div>
                        
                        <?php if (!empty($dept_entries)): ?>
                            <div class="member-depts">
                                <?php foreach ($dept_entries as $de): ?>
                                    <span class="dept-tag" style="background: <?php echo htmlspecialchars($de['color']); ?>40; color: var(--text-primary); border: 1px solid <?php echo htmlspecialchars($de['color']); ?>;">
                                        <?php echo htmlspecialchars($de['abbr']); ?>
                                        <span class="dept-rank"><?php echo htmlspecialchars($de['rank']); ?></span>
                                        <?php if ($de['status'] === 'loa'): ?>
                                            <span class="status-tag" style="background: rgba(251,191,36,0.2); color: #f0b232;">LOA</span>
                                        <?php endif; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="font-size: 12px; color: var(--text-muted); font-style: italic;">No department assignments</div>
                        <?php endif; ?>
                        
                        <?php if ($member['discord_id']): ?>
                            <div class="member-discord">💬 <?php echo htmlspecialchars($member['discord_id']); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($member['custom_status']): ?>
                            <div style="font-size: 12px; color: var(--text-muted); margin-top: 6px; font-style: italic;">"<?php echo htmlspecialchars($member['custom_status']); ?>"</div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>No Members Found</h3>
                <p>No members match your search criteria. Try adjusting your filters.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php $conn->close(); ?>
