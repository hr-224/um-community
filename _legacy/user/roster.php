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

$dept_id = isset($_GET['dept']) ? intval($_GET['dept']) : 0;

$conn = getDBConnection();

$stmt = $conn->prepare("SELECT * FROM departments WHERE id = ?");
$stmt->bind_param("i", $dept_id);
$stmt->execute();
$dept = $stmt->get_result()->fetch_assoc();

if (!$dept) {
    header('Location: ../index');
    exit();
}

$search = $_GET['search'] ?? '';

$roster_sql = "
    SELECT r.*, u.username, u.discord_id, rk.rank_name, rk.rank_order
    FROM roster r
    JOIN users u ON r.user_id = u.id
    JOIN ranks rk ON r.rank_id = rk.id
    WHERE r.department_id = ?";

$params = [$dept_id];
$types = 'i';

if ($search) {
    $roster_sql .= " AND (u.username LIKE ? OR r.badge_number LIKE ? OR r.callsign LIKE ?)";
    $search_term = '%' . $search . '%';
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'sss';
}

$roster_sql .= " ORDER BY rk.rank_order ASC, u.username ASC";

// CSV Export (admin only)
if (isset($_GET['export']) && $_GET['export'] === 'csv' && isAdmin()) {
    $stmt = $conn->prepare($roster_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $export_result = $stmt->get_result();
    $rows = [];
    while ($r = $export_result->fetch_assoc()) {
        $rows[] = [$r['username'], $r['rank_name'], $r['badge_number'] ?? '', $r['callsign'] ?? '', $r['status'], $r['discord_id'] ?? '', $r['joined_date'] ?? ''];
    }
    $stmt->close();
    exportCSV($dept['abbreviation'] . '_roster_' . date('Y-m-d') . '.csv', ['Username', 'Rank', 'Badge #', 'Callsign', 'Status', 'Discord', 'Joined'], $rows);
}

$stmt = $conn->prepare($roster_sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$roster = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($dept['abbreviation']); ?> Roster</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <?php include '../includes/styles.php'; ?>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .container {
            max-width: 1400px;
        }
        
        .dept-header {
            background: var(--bg-card);
            border: 1px solid var(--border);
            padding: 40px;
            border-radius: var(--radius-lg);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            margin-bottom: 40px;
            display: flex;
            align-items: center;
            gap: 28px;
            animation: fadeIn 0.6s ease;
            position: relative;
        }
        
        .dept-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent), #7c3aed);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        
        .dept-icon {
            width: 80px;
            height: 80px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            background: var(--bg-card);
            filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.3));
            flex-shrink: 0;
        }
        
        .dept-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: var(--radius-lg);
        }
        
        .dept-header h2 {
            color: var(--text-primary);
            font-size: 36px;
            margin-bottom: 8px;
            font-weight: 800;
        }
        
        .dept-header p {
            color: var(--text-secondary);
            font-size: 16px;
            font-weight: 500;
        }
        
        .roster-table {
            background: var(--bg-card);
            
            border: 1px solid var(--bg-elevated);
            border-radius: var(--radius-lg);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: fadeIn 0.8s ease 0.2s both;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: var(--bg-elevated);
        }
        
        th {
            padding: 20px 18px;
            text-align: left;
            font-weight: 700;
            color: var(--text-primary);
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 1px;
        }
        
        td {
            padding: 20px 18px;
            border-bottom: 1px solid var(--bg-card);
            color: var(--text-primary);
        }
        
        tr:hover td {
            background: var(--accent-muted);
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: var(--radius-lg);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-active { 
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(5, 150, 105, 0.2) 100%);
            color: #4ade80;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .status-loa { 
            background: linear-gradient(135deg, rgba(251, 191, 36, 0.2) 0%, rgba(245, 158, 11, 0.2) 100%);
            color: #f0b232;
            border: 1px solid rgba(251, 191, 36, 0.3);
        }
        
        .status-inactive { 
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2) 0%, rgba(220, 38, 38, 0.2) 100%);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .empty-state {
            padding: 80px 20px;
            text-align: center;
            color: var(--text-muted);
        }
        
        .empty-state h3 {
            font-size: 24px;
            margin-bottom: 12px;
            color: var(--text-secondary);
        }
        
        @media (max-width: 768px) {
            .container { padding: 0 16px; }
            table { font-size: 13px; }
            th, td { padding: 14px 12px; }
            .dept-header {
                padding: 28px;
                flex-direction: column;
                text-align: center;
            }
            .dept-header h2 {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <?php $current_page = 'roster'; include '../includes/navbar.php'; ?>

    <div class="container">
        <?php $has_dept_logo = !empty($dept['logo_path']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $dept['logo_path']); ?>
        <div class="dept-header">
            <div class="dept-icon">
                <?php if ($has_dept_logo): ?>
                    <img src="<?php echo htmlspecialchars($dept['logo_path']); ?>" alt="<?php echo htmlspecialchars($dept['name']); ?>">
                <?php else: ?>
                    ?
                <?php endif; ?>
            </div>
            <div>
                <h2><?php echo htmlspecialchars($dept['name']); ?></h2>
                <p><?php echo htmlspecialchars($dept['abbreviation']); ?> Department Roster</p>
            </div>
        </div>

        <div class="search-bar" style="margin-bottom: 16px;">
            <form method="GET" style="display: flex; gap: 8px; flex: 1; flex-wrap: wrap; align-items: center;">
                <input type="hidden" name="dept" value="<?php echo $dept_id; ?>">
                <input type="search" name="search" placeholder="Search by name, badge, or callsign..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1; min-width: 200px;">
                <button type="submit" class="btn btn-primary btn-sm">Search</button>
                <?php if ($search): ?><a href="?dept=<?php echo $dept_id; ?>" class="btn btn-sm" style="background: var(--bg-elevated); text-decoration: none; color: var(--text-primary);">Clear</a><?php endif; ?>
                <?php if (isAdmin()): ?><a href="?dept=<?php echo $dept_id; ?>&search=<?php echo urlencode($search); ?>&export=csv" class="btn-export">📥 Export CSV</a><?php endif; ?>
            </form>
        </div>

        <div class="roster-table">
            <?php if ($roster->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Rank</th>
                            <th>Badge/ID</th>
                            <th>Callsign</th>
                            <th>Discord</th>
                            <th>Status</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($member = $roster->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($member['username']); ?></strong></td>
                                <td><?php echo htmlspecialchars($member['rank_name']); ?></td>
                                <td><?php echo htmlspecialchars($member['badge_number'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($member['callsign'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($member['discord_id'] ?? '-'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $member['status']; ?>">
                                        <?php echo strtoupper($member['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $member['joined_date'] ? date('M j, Y', strtotime($member['joined_date'])) : '-'; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <h3>No members found</h3>
                    <p>This department doesn't have any members yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>