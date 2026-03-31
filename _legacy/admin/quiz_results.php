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
requireAdmin();

$conn = getDBConnection();
$quiz_id = intval($_GET['id'] ?? 0);

if (!$quiz_id) {
    header('Location: quizzes.php');
    exit;
}

// Get quiz info
$stmt = $conn->prepare("SELECT * FROM quizzes WHERE id = ?");
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quiz) {
    header('Location: quizzes.php');
    exit;
}

// Get all attempts with user info
$attempts = $conn->query("
    SELECT qa.*, u.username
    FROM quiz_attempts qa
    JOIN users u ON qa.user_id = u.id
    WHERE qa.quiz_id = $quiz_id
    ORDER BY qa.started_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$total_attempts = count($attempts);
$passed = 0;
$total_score = 0;
$total_time = 0;
$completed = 0;

foreach ($attempts as $a) {
    if ($a['passed']) $passed++;
    $total_score += $a['percentage'];
    if ($a['completed_at']) {
        $completed++;
        $total_time += $a['time_spent_seconds'];
    }
}

$avg_score = $total_attempts > 0 ? round($total_score / $total_attempts, 1) : 0;
$pass_rate = $total_attempts > 0 ? round(($passed / $total_attempts) * 100, 1) : 0;
$avg_time = $completed > 0 ? round($total_time / $completed / 60, 1) : 0;

$conn->close();
$theme = getThemeColors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results - <?php echo htmlspecialchars($quiz['title']); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
    <style>
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px; text-align: center; }
        .stat-value { font-size: 28px; font-weight: 700; color: var(--accent); }
        .stat-label { font-size: 12px; color: var(--text-muted); text-transform: uppercase; margin-top: 4px; }
        .results-table { width: 100%; border-collapse: collapse; }
        .results-table th, .results-table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid var(--border); }
        .results-table th { background: var(--bg-primary); font-weight: 600; font-size: 12px; text-transform: uppercase; color: var(--text-muted); }
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; }
        .badge-pass { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
        .badge-fail { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
        .badge-progress { background: rgba(251, 191, 36, 0.2); color: #fbbf24; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <div>
            <a href="quizzes.php" style="color: var(--text-muted); text-decoration: none; font-size: 14px;">← Back to Quizzes</a>
            <h1 style="margin-top: 8px;">📊 Results: <?php echo htmlspecialchars($quiz['title']); ?></h1>
        </div>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo $total_attempts; ?></div>
            <div class="stat-label">Total Attempts</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $pass_rate; ?>%</div>
            <div class="stat-label">Pass Rate</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $avg_score; ?>%</div>
            <div class="stat-label">Avg Score</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $avg_time; ?> min</div>
            <div class="stat-label">Avg Time</div>
        </div>
    </div>
    
    <?php if (empty($attempts)): ?>
        <div class="empty-state">
            <p>No one has attempted this quiz yet.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="results-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Score</th>
                        <th>Status</th>
                        <th>Time</th>
                        <th>Started</th>
                        <th>Completed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attempts as $a): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($a['username']); ?></td>
                        <td><?php echo $a['score']; ?>/<?php echo $a['max_score']; ?> (<?php echo round($a['percentage']); ?>%)</td>
                        <td>
                            <?php if (!$a['completed_at']): ?>
                                <span class="badge badge-progress">In Progress</span>
                            <?php elseif ($a['passed']): ?>
                                <span class="badge badge-pass">Passed</span>
                            <?php else: ?>
                                <span class="badge badge-fail">Failed</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $a['completed_at'] ? round($a['time_spent_seconds'] / 60, 1) . ' min' : '-'; ?></td>
                        <td><?php echo date('M j, Y g:i A', strtotime($a['started_at'])); ?></td>
                        <td><?php echo $a['completed_at'] ? date('M j, Y g:i A', strtotime($a['completed_at'])) : '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
