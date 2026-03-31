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
$attempt_id = intval($_GET['attempt_id'] ?? 0);

if (!$attempt_id) {
    header('Location: quizzes.php');
    exit;
}

// Get attempt with quiz info
$stmt = $conn->prepare("
    SELECT qa.*, q.title, q.pass_score, q.show_correct_answers
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    WHERE qa.id = ? AND qa.user_id = ?
");
$stmt->bind_param("ii", $attempt_id, $user_id);
$stmt->execute();
$attempt = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$attempt) {
    header('Location: quizzes.php');
    exit;
}

// Get questions with answers and user responses
$questions = [];
$result = $conn->query("SELECT * FROM quiz_questions WHERE quiz_id = {$attempt['quiz_id']} ORDER BY display_order");
while ($q = $result->fetch_assoc()) {
    // Get all answers
    $stmt = $conn->prepare("SELECT * FROM quiz_answers WHERE question_id = ? ORDER BY display_order");
    $stmt->bind_param("i", $q['id']);
    $stmt->execute();
    $q['answers'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Get user's answer for this question
    $stmt = $conn->prepare("SELECT * FROM quiz_attempt_answers WHERE attempt_id = ? AND question_id = ?");
    $stmt->bind_param("ii", $attempt_id, $q['id']);
    $stmt->execute();
    $q['user_answer'] = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $questions[] = $q;
}

$conn->close();
$theme = getThemeColors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results - <?php echo htmlspecialchars($attempt['title']); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
    <style>
        .result-header { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 32px; text-align: center; margin-bottom: 24px; }
        .result-status { font-size: 48px; margin-bottom: 16px; }
        .result-title { font-size: 24px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px; }
        .result-subtitle { font-size: 16px; color: var(--text-muted); }
        .result-score { font-size: 64px; font-weight: 800; margin: 24px 0; }
        .result-score.passed { color: #22c55e; }
        .result-score.failed { color: var(--danger); }
        .result-stats { display: flex; justify-content: center; gap: 32px; margin-top: 24px; flex-wrap: wrap; }
        .result-stat { text-align: center; }
        .result-stat-value { font-size: 24px; font-weight: 700; color: var(--accent); }
        .result-stat-label { font-size: 12px; color: var(--text-muted); text-transform: uppercase; }
        .question-review { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 24px; margin-bottom: 16px; }
        .question-review.correct { border-left: 4px solid #22c55e; }
        .question-review.incorrect { border-left: 4px solid var(--danger); }
        .question-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .question-number { font-size: 12px; font-weight: 600; }
        .question-number.correct { color: #22c55e; }
        .question-number.incorrect { color: var(--danger); }
        .question-text { font-size: 16px; font-weight: 500; color: var(--text-primary); margin-bottom: 16px; }
        .answer-review { padding: 10px 14px; border-radius: var(--radius-sm); margin-bottom: 8px; display: flex; align-items: center; gap: 10px; }
        .answer-review.correct-answer { background: rgba(34, 197, 94, 0.15); border: 1px solid rgba(34, 197, 94, 0.3); }
        .answer-review.wrong-selected { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); }
        .answer-review.neutral { background: var(--bg-primary); }
        .answer-icon { width: 20px; text-align: center; }
        .explanation { margin-top: 16px; padding: 12px; background: rgba(59, 130, 246, 0.1); border-radius: var(--radius-sm); font-size: 13px; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="result-header">
        <div class="result-status"><?php echo $attempt['passed'] ? '🎉' : '📚'; ?></div>
        <div class="result-title"><?php echo $attempt['passed'] ? 'Congratulations!' : 'Keep Studying!'; ?></div>
        <div class="result-subtitle"><?php echo htmlspecialchars($attempt['title']); ?></div>
        <div class="result-score <?php echo $attempt['passed'] ? 'passed' : 'failed'; ?>">
            <?php echo round($attempt['percentage']); ?>%
        </div>
        <div style="font-size: 14px; color: var(--text-muted);">
            <?php echo $attempt['score']; ?> / <?php echo $attempt['max_score']; ?> points
            <?php if ($attempt['passed']): ?>
                <span style="color: #22c55e; margin-left: 8px;">✓ Passed</span>
            <?php else: ?>
                <span style="color: var(--danger); margin-left: 8px;">✗ Need <?php echo $attempt['pass_score']; ?>% to pass</span>
            <?php endif; ?>
        </div>
        <div class="result-stats">
            <div class="result-stat">
                <div class="result-stat-value"><?php echo count(array_filter($questions, function($q) { return $q['user_answer'] && $q['user_answer']['is_correct']; })); ?></div>
                <div class="result-stat-label">Correct</div>
            </div>
            <div class="result-stat">
                <div class="result-stat-value"><?php echo count(array_filter($questions, function($q) { return $q['user_answer'] && !$q['user_answer']['is_correct']; })); ?></div>
                <div class="result-stat-label">Incorrect</div>
            </div>
            <div class="result-stat">
                <div class="result-stat-value"><?php echo round($attempt['time_spent_seconds'] / 60, 1); ?> min</div>
                <div class="result-stat-label">Time Taken</div>
            </div>
        </div>
    </div>
    
    <?php if ($attempt['show_correct_answers']): ?>
    <h3 style="margin-bottom: 16px;">Question Review</h3>
    
    <?php foreach ($questions as $i => $q): 
        $is_correct = $q['user_answer'] && $q['user_answer']['is_correct'];
        $user_selected = $q['user_answer'] ? json_decode($q['user_answer']['selected_answers'], true) : [];
    ?>
    <div class="question-review <?php echo $is_correct ? 'correct' : 'incorrect'; ?>">
        <div class="question-header">
            <div class="question-number <?php echo $is_correct ? 'correct' : 'incorrect'; ?>">
                <?php echo $is_correct ? '✓' : '✗'; ?> Question <?php echo $i + 1; ?>
            </div>
            <div style="font-size: 12px; color: var(--text-muted);">
                <?php echo $q['user_answer'] ? $q['user_answer']['points_earned'] : 0; ?> / <?php echo $q['points']; ?> pts
            </div>
        </div>
        <div class="question-text"><?php echo htmlspecialchars($q['question_text']); ?></div>
        
        <?php foreach ($q['answers'] as $a): 
            $was_selected = in_array((string)$a['id'], $user_selected);
            $is_correct_answer = $a['is_correct'];
            
            if ($is_correct_answer) {
                $class = 'correct-answer';
            } elseif ($was_selected && !$is_correct_answer) {
                $class = 'wrong-selected';
            } else {
                $class = 'neutral';
            }
        ?>
        <div class="answer-review <?php echo $class; ?>">
            <span class="answer-icon">
                <?php if ($is_correct_answer): ?>
                    ✓
                <?php elseif ($was_selected): ?>
                    ✗
                <?php endif; ?>
            </span>
            <span><?php echo htmlspecialchars($a['answer_text']); ?></span>
            <?php if ($was_selected): ?>
                <span style="font-size: 11px; color: var(--text-muted); margin-left: auto;">Your answer</span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        
        <?php if ($q['explanation']): ?>
        <div class="explanation">
            <strong>Explanation:</strong> <?php echo htmlspecialchars($q['explanation']); ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    
    <div style="text-align: center; margin-top: 24px;">
        <a href="quizzes.php" class="btn btn-primary">Back to Quizzes</a>
    </div>
</div>
</body>
</html>
