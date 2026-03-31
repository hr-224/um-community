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
$user_id = $_SESSION['user_id'];

// Get user's departments
$user_depts = [];
$stmt = $conn->prepare("SELECT department_id FROM roster WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $user_depts[] = intval($row['department_id']);
}
$stmt->close();

// Get available quizzes (active, and either no department or user's department)
$dept_filter = empty($user_depts) ? "q.department_id IS NULL" : "(q.department_id IS NULL OR q.department_id IN (" . implode(',', $user_depts) . "))";

$quizzes = [];
$quiz_result = @$conn->query("
    SELECT q.*, 
           d.name as department_name,
           ct.name as cert_name,
           (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as question_count,
           (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id AND user_id = " . intval($user_id) . ") as my_attempts,
           (SELECT MAX(percentage) FROM quiz_attempts WHERE quiz_id = q.id AND user_id = " . intval($user_id) . " AND completed_at IS NOT NULL) as best_score,
           (SELECT passed FROM quiz_attempts WHERE quiz_id = q.id AND user_id = " . intval($user_id) . " AND completed_at IS NOT NULL ORDER BY percentage DESC LIMIT 1) as has_passed
    FROM quizzes q
    LEFT JOIN departments d ON q.department_id = d.id
    LEFT JOIN certification_types ct ON q.certification_type_id = ct.id
    WHERE q.is_active = 1 AND $dept_filter
    ORDER BY q.title
");
if ($quiz_result) $quizzes = $quiz_result->fetch_all(MYSQLI_ASSOC);

// Get my quiz history
$my_history = [];
$history_result = @$conn->query("
    SELECT qa.*, q.title as quiz_title
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    WHERE qa.user_id = " . intval($user_id) . "
    ORDER BY qa.started_at DESC
    LIMIT 10
");
if ($history_result) $my_history = $history_result->fetch_all(MYSQLI_ASSOC);

$conn->close();
$theme = getThemeColors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Quizzes - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
    <style>
        .quiz-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
        .quiz-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px; transition: all 0.2s; }
        .quiz-card:hover { border-color: var(--accent); transform: translateY(-2px); }
        .quiz-title { font-size: 18px; font-weight: 600; color: var(--text-primary); margin-bottom: 8px; }
        .quiz-meta { font-size: 13px; color: var(--text-muted); margin-bottom: 16px; }
        .quiz-meta span { display: inline-block; margin-right: 16px; }
        .quiz-stats { display: flex; gap: 16px; margin-bottom: 16px; }
        .quiz-stat { flex: 1; text-align: center; padding: 12px; background: var(--bg-primary); border-radius: var(--radius-sm); }
        .quiz-stat-value { font-size: 18px; font-weight: 700; color: var(--accent); }
        .quiz-stat-label { font-size: 10px; color: var(--text-muted); text-transform: uppercase; }
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; }
        .badge-pass { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
        .badge-new { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
        .history-table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        .history-table th, .history-table td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border); }
        .history-table th { font-size: 11px; text-transform: uppercase; color: var(--text-muted); }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>📝 Training Quizzes</h1>
    </div>
    
    <h3 style="margin-bottom: 16px;">Available Quizzes</h3>
    
    <?php if (empty($quizzes)): ?>
        <div class="empty-state">
            <p>No quizzes available at this time.</p>
        </div>
    <?php else: ?>
        <div class="quiz-grid">
            <?php foreach ($quizzes as $quiz): ?>
            <?php
                $can_attempt = true;
                $attempt_msg = '';
                if ($quiz['max_attempts'] && $quiz['my_attempts'] >= $quiz['max_attempts']) {
                    $can_attempt = false;
                    $attempt_msg = 'Max attempts reached';
                }
            ?>
            <div class="quiz-card">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                    <div class="quiz-title"><?php echo htmlspecialchars($quiz['title']); ?></div>
                    <?php if ($quiz['has_passed']): ?>
                        <span class="badge badge-pass">✓ Passed</span>
                    <?php elseif ($quiz['my_attempts'] == 0): ?>
                        <span class="badge badge-new">New</span>
                    <?php endif; ?>
                </div>
                <div class="quiz-meta">
                    <?php if ($quiz['department_name']): ?>
                        <span>📁 <?php echo htmlspecialchars($quiz['department_name']); ?></span>
                    <?php endif; ?>
                    <?php if ($quiz['cert_name']): ?>
                        <span>📜 <?php echo htmlspecialchars($quiz['cert_name']); ?></span>
                    <?php endif; ?>
                </div>
                <div class="quiz-stats">
                    <div class="quiz-stat">
                        <div class="quiz-stat-value"><?php echo $quiz['question_count']; ?></div>
                        <div class="quiz-stat-label">Questions</div>
                    </div>
                    <div class="quiz-stat">
                        <div class="quiz-stat-value"><?php echo $quiz['pass_score']; ?>%</div>
                        <div class="quiz-stat-label">Pass Score</div>
                    </div>
                    <div class="quiz-stat">
                        <div class="quiz-stat-value"><?php echo $quiz['best_score'] !== null ? round($quiz['best_score']) . '%' : '-'; ?></div>
                        <div class="quiz-stat-label">Best Score</div>
                    </div>
                    <?php if ($quiz['time_limit_minutes']): ?>
                    <div class="quiz-stat">
                        <div class="quiz-stat-value"><?php echo $quiz['time_limit_minutes']; ?></div>
                        <div class="quiz-stat-label">Min Limit</div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if ($can_attempt): ?>
                    <a href="take_quiz.php?id=<?php echo $quiz['id']; ?>" class="btn btn-primary" style="width: 100%; text-align: center;">
                        <?php echo $quiz['my_attempts'] > 0 ? 'Retake Quiz' : 'Start Quiz'; ?>
                    </a>
                <?php else: ?>
                    <button class="btn" style="width: 100%;" disabled><?php echo $attempt_msg; ?></button>
                <?php endif; ?>
                <?php if ($quiz['max_attempts']): ?>
                    <div style="text-align: center; margin-top: 8px; font-size: 11px; color: var(--text-muted);">
                        <?php echo $quiz['my_attempts']; ?> / <?php echo $quiz['max_attempts']; ?> attempts used
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($my_history)): ?>
    <h3 style="margin-top: 40px; margin-bottom: 16px;">My Recent Attempts</h3>
    <div class="table-container">
        <table class="history-table">
            <thead>
                <tr>
                    <th>Quiz</th>
                    <th>Score</th>
                    <th>Result</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($my_history as $h): ?>
                <tr>
                    <td><?php echo htmlspecialchars($h['quiz_title']); ?></td>
                    <td><?php echo $h['score']; ?>/<?php echo $h['max_score']; ?> (<?php echo round($h['percentage']); ?>%)</td>
                    <td>
                        <?php if (!$h['completed_at']): ?>
                            <span class="badge" style="background: rgba(251, 191, 36, 0.2); color: #fbbf24;">In Progress</span>
                        <?php elseif ($h['passed']): ?>
                            <span class="badge badge-pass">Passed</span>
                        <?php else: ?>
                            <span class="badge" style="background: rgba(239, 68, 68, 0.2); color: var(--danger);">Failed</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('M j, Y g:i A', strtotime($h['started_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
