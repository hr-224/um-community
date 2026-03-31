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

$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$first_day = mktime(0, 0, 0, $month, 1, $year);
$days_in_month = date('t', $first_day);
$start_day = date('w', $first_day);
$month_name = date('F', $first_day);

// Get approved LOAs for this month
$start_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
$end_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-$days_in_month";

$loas = $conn->query("
    SELECT l.*, u.username
    FROM loa_requests l
    JOIN users u ON l.user_id = u.id
    WHERE l.status = 'approved'
    AND ((l.start_date BETWEEN '$start_date' AND '$end_date')
         OR (l.end_date BETWEEN '$start_date' AND '$end_date')
         OR (l.start_date <= '$start_date' AND l.end_date >= '$end_date'))
    ORDER BY l.start_date
");

// Build LOA data by day
$loa_by_day = [];
while ($loa = $loas->fetch_assoc()) {
    $s = max(strtotime($loa['start_date']), strtotime($start_date));
    $e = min(strtotime($loa['end_date']), strtotime($end_date));
    
    for ($d = $s; $d <= $e; $d += 86400) {
        $day = date('j', $d);
        if (!isset($loa_by_day[$day])) $loa_by_day[$day] = [];
        $loa_by_day[$day][] = $loa;
    }
}

$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) { $prev_month = 12; $prev_year--; }

$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) { $next_month = 1; $next_year++; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LOA Calendar - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <?php include '../includes/styles.php'; ?>
    <style>
        .container { max-width: 1200px; }
        
        .calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .calendar-title { font-size: 28px; font-weight: 700; }
        .calendar-nav { display: flex; gap: 8px; }
        .calendar-nav a { padding: 10px 20px; border-radius: var(--radius-md); background: var(--bg-card); border: 1px solid var(--bg-elevated); color: var(--text-primary); text-decoration: none; font-weight: 500; transition: all 0.3s; }
        .calendar-nav a:hover { background: var(--accent-muted); }
        
        .calendar { background: var(--bg-card); border: 1px solid var(--bg-elevated); border-radius: var(--radius-lg); overflow: hidden; }
        
        .calendar-weekdays { display: grid; grid-template-columns: repeat(7, 1fr); background: var(--bg-elevated); }
        .calendar-weekday { padding: 16px; text-align: center; font-weight: 700; font-size: 13px; text-transform: uppercase; color: var(--text-secondary); }
        
        .calendar-days { display: grid; grid-template-columns: repeat(7, 1fr); }
        .calendar-day { min-height: 100px; padding: 8px; border: 1px solid var(--bg-card); position: relative; }
        .calendar-day.empty { background: rgba(0, 0, 0, 0.1); }
        .calendar-day.today { background: var(--accent-muted); }
        .calendar-day:hover { background: var(--bg-elevated); }
        
        .day-number { font-size: 14px; font-weight: 600; margin-bottom: 6px; color: var(--text-secondary); }
        .calendar-day.today .day-number { color: var(--accent); font-weight: 800; }
        
        .loa-item { background: linear-gradient(135deg, rgba(251, 191, 36, 0.2), rgba(245, 158, 11, 0.1)); border-left: 3px solid #f59e0b; border-radius: 4px; padding: 4px 6px; margin-bottom: 4px; font-size: 11px; color: #f0b232; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .loa-item:hover { background: rgba(251, 191, 36, 0.3); }
        
        .more-loas { font-size: 10px; color: var(--text-muted); text-align: center; margin-top: 4px; }
        
        .legend { display: flex; gap: 24px; margin-top: 24px; padding: 20px; background: var(--bg-elevated); border-radius: var(--radius-md); }
        .legend-item { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-secondary); }
        .legend-color { width: 16px; height: 16px; border-radius: 4px; }
        .legend-color.loa { background: linear-gradient(135deg, rgba(251, 191, 36, 0.4), rgba(245, 158, 11, 0.2)); border-left: 3px solid #f59e0b; }
        .legend-color.today { background: var(--accent-muted); }
        
        @media (max-width: 768px) {
            .calendar-header { flex-direction: column; gap: 12px; align-items: flex-start; }
            .calendar-title { font-size: 22px; }
            .calendar-nav { width: 100%; }
            .calendar-nav a { padding: 8px 14px; font-size: 13px; flex: 1; text-align: center; }
            .calendar-day { min-height: 60px; padding: 4px; }
            .calendar-weekday { padding: 10px 4px; font-size: 10px; }
            .loa-item { font-size: 9px; padding: 2px 4px; }
            .day-number { font-size: 12px; }
        }
        @media (max-width: 480px) {
            .calendar-day { min-height: 44px; padding: 2px; }
            .calendar-weekday { padding: 8px 2px; font-size: 9px; }
            .day-number { font-size: 10px; }
            .loa-item { font-size: 8px; padding: 1px 2px; }
        }
    </style>
</head>
<body>
    <?php $current_page = 'loa_calendar'; include '../includes/navbar.php'; ?>

    <div class="container">
        <div class="calendar-header">
            <div class="calendar-title"><?php echo $month_name . ' ' . $year; ?></div>
            <div class="calendar-nav">
                <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>">← Previous</a>
                <a href="?month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?>">Today</a>
                <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>">Next →</a>
            </div>
        </div>

        <div class="calendar">
            <div class="calendar-weekdays">
                <div class="calendar-weekday">Sun</div>
                <div class="calendar-weekday">Mon</div>
                <div class="calendar-weekday">Tue</div>
                <div class="calendar-weekday">Wed</div>
                <div class="calendar-weekday">Thu</div>
                <div class="calendar-weekday">Fri</div>
                <div class="calendar-weekday">Sat</div>
            </div>
            <div class="calendar-days">
                <?php
                // Empty cells before first day
                for ($i = 0; $i < $start_day; $i++) {
                    echo '<div class="calendar-day empty"></div>';
                }
                
                // Days of month
                $today = date('Y-m-d');
                for ($day = 1; $day <= $days_in_month; $day++) {
                    $current_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($day, 2, '0', STR_PAD_LEFT);
                    $is_today = $current_date === $today;
                    $day_loas = $loa_by_day[$day] ?? [];
                    ?>
                    <div class="calendar-day <?php echo $is_today ? 'today' : ''; ?>">
                        <div class="day-number"><?php echo $day; ?></div>
                        <?php
                        $shown = 0;
                        foreach ($day_loas as $loa) {
                            if ($shown < 3) {
                                echo '<div class="loa-item" title="' . htmlspecialchars($loa['username']) . ': ' . htmlspecialchars($loa['reason']) . '">';
                                echo htmlspecialchars($loa['username']);
                                echo '</div>';
                                $shown++;
                            }
                        }
                        if (count($day_loas) > 3) {
                            echo '<div class="more-loas">+' . (count($day_loas) - 3) . ' more</div>';
                        }
                        ?>
                    </div>
                    <?php
                }
                
                // Empty cells after last day
                $total_cells = $start_day + $days_in_month;
                $remaining = 7 - ($total_cells % 7);
                if ($remaining < 7) {
                    for ($i = 0; $i < $remaining; $i++) {
                        echo '<div class="calendar-day empty"></div>';
                    }
                }
                ?>
            </div>
        </div>

        <div class="legend">
            <div class="legend-item">
                <div class="legend-color loa"></div>
                Member on LOA
            </div>
            <div class="legend-item">
                <div class="legend-color today"></div>
                Today
            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
