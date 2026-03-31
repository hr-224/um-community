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
requireLogin();

$conn = getDBConnection();

// Check if chain_of_command table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'chain_of_command'");
$entries = [];

if ($tableCheck && $tableCheck->num_rows > 0) {
    $result = $conn->query("
        SELECT coc.*, u.username, d.name as department_name, sup.username as reports_to_name
        FROM chain_of_command coc
        JOIN users u ON coc.user_id = u.id
        LEFT JOIN departments d ON coc.department_id = d.id
        LEFT JOIN users sup ON coc.reports_to = sup.id
        ORDER BY coc.department_id, coc.display_order ASC
    ");
    
    if ($result) {
        $entries = $result->fetch_all(MYSQLI_ASSOC);
    }
}

// Get all profile pics from system_settings in one query
$profilePics = [];
$picResult = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'user_%_profile_pic'");
if ($picResult) {
    while ($row = $picResult->fetch_assoc()) {
        // Extract user_id from key like 'user_123_profile_pic'
        if (preg_match('/user_(\d+)_profile_pic/', $row['setting_key'], $matches)) {
            $profilePics[$matches[1]] = $row['setting_value'];
        }
    }
}

// Add profile_pic to each entry
foreach ($entries as &$entry) {
    $entry['profile_pic'] = $profilePics[$entry['user_id']] ?? '';
}
unset($entry);

// Group by department
$departments = [];
foreach ($entries as $entry) {
    $dept_name = $entry['department_name'] ?: 'Leadership';
    if (!isset($departments[$dept_name])) {
        $departments[$dept_name] = [];
    }
    $departments[$dept_name][] = $entry;
}

$conn->close();

$theme = getThemeColors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chain of Command - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
    <style>
        .coc-section { margin-bottom: 40px; }
        .coc-section-title { font-size: 20px; font-weight: 600; color: var(--text-primary); margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid var(--border); }
        .coc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
        .coc-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px; display: flex; align-items: center; gap: 16px; transition: all 0.2s; }
        .coc-card:hover { border-color: var(--accent); transform: translateY(-2px); }
        .coc-avatar { width: 60px; height: 60px; border-radius: 50%; background: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 24px; color: var(--text-primary); flex-shrink: 0; overflow: hidden; }
        .coc-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .coc-info { flex: 1; min-width: 0; }
        .coc-position { font-size: 12px; color: var(--accent); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .coc-name { font-size: 16px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px; }
        .coc-reports-to { font-size: 11px; color: var(--text-muted); margin-top: 6px; }
        .empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
        .empty-state p { margin: 0; }
        @media (max-width: 768px) {
            .coc-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>🏛️ Chain of Command</h1>
    </div>
    
    <?php if (empty($departments)): ?>
        <div class="empty-state">
            <p>Chain of command has not been set up yet.</p>
        </div>
    <?php else: ?>
        <?php foreach ($departments as $dept_name => $members): ?>
        <div class="coc-section">
            <div class="coc-section-title"><?php echo htmlspecialchars($dept_name); ?></div>
            <div class="coc-grid">
                <?php foreach ($members as $member): ?>
                <div class="coc-card">
                    <div class="coc-avatar">
                        <?php if (!empty($member['profile_pic']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $member['profile_pic'])): ?>
                            <img src="<?php echo htmlspecialchars($member['profile_pic']); ?>" alt="<?php echo htmlspecialchars($member['username']); ?>">
                        <?php else: ?>
                            <?php echo strtoupper(substr($member['username'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="coc-info">
                        <div class="coc-position"><?php echo htmlspecialchars($member['position_title']); ?></div>
                        <div class="coc-name"><?php echo htmlspecialchars($member['username']); ?></div>
                        <?php if (!empty($member['reports_to_name'])): ?>
                            <div class="coc-reports-to">Reports to: <?php echo htmlspecialchars($member['reports_to_name']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
