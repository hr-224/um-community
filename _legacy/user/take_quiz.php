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
$quiz_id = intval($_GET['id'] ?? 0);
$message = '';
$error = '';

if (!$quiz_id) {
    header('Location: quizzes.php');
    exit;
}

// Get quiz info
$stmt = $conn->prepare("SELECT * FROM quizzes WHERE id = ? AND is_active = 1");
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quiz) {
    header('Location: quizzes.php');
    exit;
}

// Check max attempts
if ($quiz['max_attempts']) {
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM quiz_attempts WHERE quiz_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $quiz_id, $user_id);
    $stmt->execute();
    $count_row = $stmt->get_result()->fetch_assoc();
    $count = $count_row['cnt'] ?? 0;
    $stmt->close();
    
    if ($count >= $quiz['max_attempts']) {
        $_SESSION['error'] = "You've reached the maximum attempts for this quiz.";
        header('Location: quizzes.php');
        exit;
    }
}

// Check for existing incomplete attempt
$stmt = $conn->prepare("SELECT * FROM quiz_attempts WHERE quiz_id = ? AND user_id = ? AND completed_at IS NULL ORDER BY started_at DESC LIMIT 1");
$stmt->bind_param("ii", $quiz_id, $user_id);
$stmt->execute();
$attempt = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Submit quiz
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['submit_quiz'])) {
    $attempt_id = intval($_POST['attempt_id'] ?? 0);
    
    if ($attempt_id) {
        $answers = $_POST['answers'] ?? [];
        $total_score = 0;
        $max_score = 0;
        
        // Get questions
        $questions = $conn->query("SELECT * FROM quiz_questions WHERE quiz_id = $quiz_id")->fetch_all(MYSQLI_ASSOC);
        
        foreach ($questions as $q) {
            $max_score += $q['points'];
            $user_answers = isset($answers[$q['id']]) ? (array)$answers[$q['id']] : [];
            
            // Get correct answers
            $correct_answers = [];
            $stmt = $conn->prepare("SELECT id FROM quiz_answers WHERE question_id = ? AND is_correct = 1");
            $stmt->bind_param("i", $q['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $correct_answers[] = (string)$row['id'];
            }
            $stmt->close();
            
            // Check if correct
            sort($user_answers);
            sort($correct_answers);
            $is_correct = ($user_answers === $correct_answers);
            $points = $is_correct ? $q['points'] : 0;
            $total_score += $points;
            
            // Save answer
            $user_answers_json = json_encode($user_answers);
            $stmt = $conn->prepare("INSERT INTO quiz_attempt_answers (attempt_id, question_id, selected_answers, is_correct, points_earned) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisii", $attempt_id, $q['id'], $user_answers_json, $is_correct, $points);
            $stmt->execute();
            $stmt->close();
        }
        
        // Calculate percentage and pass/fail
        $percentage = $max_score > 0 ? ($total_score / $max_score) * 100 : 0;
        $passed = $percentage >= $quiz['pass_score'] ? 1 : 0;
        
        // Calculate time spent
        $stmt = $conn->prepare("SELECT started_at FROM quiz_attempts WHERE id = ?");
        $stmt->bind_param("i", $attempt_id);
        $stmt->execute();
        $started_row = $stmt->get_result()->fetch_assoc();
        $started = $started_row['started_at'] ?? date('Y-m-d H:i:s');
        $stmt->close();
        $time_spent = time() - strtotime($started);
        
        // Update attempt
        $stmt = $conn->prepare("UPDATE quiz_attempts SET score = ?, max_score = ?, percentage = ?, passed = ?, completed_at = NOW(), time_spent_seconds = ? WHERE id = ?");
        $stmt->bind_param("iidiii", $total_score, $max_score, $percentage, $passed, $time_spent, $attempt_id);
        $stmt->execute();
        $stmt->close();
        
        // Redirect to results
        header("Location: quiz_result.php?attempt_id=$attempt_id");
        exit;
    }
}

// Create new attempt if needed
if (!$attempt) {
    $stmt = $conn->prepare("INSERT INTO quiz_attempts (quiz_id, user_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $quiz_id, $user_id);
    $stmt->execute();
    $attempt_id = $stmt->insert_id;
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT * FROM quiz_attempts WHERE id = ?");
    $stmt->bind_param("i", $attempt_id);
    $stmt->execute();
    $attempt = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Get questions
$questions = $conn->query("SELECT * FROM quiz_questions WHERE quiz_id = $quiz_id ORDER BY " . ($quiz['shuffle_questions'] ? "RAND()" : "display_order"))->fetch_all(MYSQLI_ASSOC);

// Get answers for each question
foreach ($questions as &$q) {
    $stmt = $conn->prepare("SELECT * FROM quiz_answers WHERE question_id = ? ORDER BY " . ($quiz['shuffle_questions'] ? "RAND()" : "display_order"));
    $stmt->bind_param("i", $q['id']);
    $stmt->execute();
    $q['answers'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
unset($q);

// Calculate time remaining if time limit
$time_remaining = null;
if ($quiz['time_limit_minutes']) {
    $started = strtotime($attempt['started_at']);
    $elapsed = time() - $started;
    $time_remaining = ($quiz['time_limit_minutes'] * 60) - $elapsed;
    if ($time_remaining <= 0) {
        // Auto-submit with current answers
        header("Location: take_quiz.php?id=$quiz_id&timeout=1");
        exit;
    }
}

$conn->close();
$theme = getThemeColors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($quiz['title']); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
    <style>
        .quiz-header { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
        .quiz-title { font-size: 20px; font-weight: 600; color: var(--text-primary); }
        .quiz-info { font-size: 13px; color: var(--text-muted); }
        .timer { background: var(--accent); color: var(--text-primary); padding: 10px 20px; border-radius: var(--radius-sm); font-weight: 700; font-size: 18px; }
        .timer.warning { background: var(--danger); animation: pulse 1s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
        .question-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 24px; margin-bottom: 20px; }
        .question-number { font-size: 12px; color: var(--accent); font-weight: 600; margin-bottom: 8px; }
        .question-text { font-size: 16px; font-weight: 500; color: var(--text-primary); margin-bottom: 20px; line-height: 1.5; }
        .answer-option { display: flex; align-items: center; gap: 12px; padding: 14px 16px; background: var(--bg-primary); border: 1px solid var(--border); border-radius: var(--radius-sm); margin-bottom: 10px; cursor: pointer; transition: all 0.2s; }
        .answer-option:hover { background: var(--bg-elevated); border-color: var(--accent); }
        .answer-option.selected { background: rgba(59, 130, 246, 0.15); border-color: var(--accent); }
        .answer-option input { display: none; }
        .answer-check { width: 22px; height: 22px; border: 2px solid var(--border); border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .answer-option.selected .answer-check { background: var(--accent); border-color: var(--accent); }
        .answer-option.selected .answer-check::after { content: '✓'; color: var(--text-primary); font-size: 14px; }
        .answer-text { flex: 1; }
        .submit-section { text-align: center; padding: 20px; }
        .progress-bar { height: 4px; background: var(--bg-elevated); border-radius: 2px; margin-bottom: 24px; }
        .progress-fill { height: 100%; background: var(--accent); border-radius: 2px; transition: width 0.3s; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="quiz-header">
        <div>
            <div class="quiz-title"><?php echo htmlspecialchars($quiz['title']); ?></div>
            <div class="quiz-info"><?php echo count($questions); ?> questions • Pass score: <?php echo $quiz['pass_score']; ?>%</div>
        </div>
        <?php if ($time_remaining !== null): ?>
        <div class="timer" id="timer" data-remaining="<?php echo $time_remaining; ?>">
            <?php echo floor($time_remaining / 60); ?>:<?php echo str_pad($time_remaining % 60, 2, '0', STR_PAD_LEFT); ?>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="progress-bar">
        <div class="progress-fill" id="progressFill" style="width: 0%;"></div>
    </div>
    
    <form method="POST" id="quizForm">
        <?php echo csrfField(); ?>
        <input type="hidden" name="attempt_id" value="<?php echo $attempt['id']; ?>">
        <input type="hidden" name="submit_quiz" value="1">
        
        <?php foreach ($questions as $i => $q): ?>
        <div class="question-card" data-question="<?php echo $i; ?>">
            <div class="question-number">Question <?php echo $i + 1; ?> of <?php echo count($questions); ?> • <?php echo $q['points']; ?> point<?php echo $q['points'] > 1 ? 's' : ''; ?></div>
            <div class="question-text"><?php echo htmlspecialchars($q['question_text']); ?></div>
            
            <div class="answers">
                <?php foreach ($q['answers'] as $a): ?>
                <?php 
                    $input_type = $q['question_type'] === 'multi_select' ? 'checkbox' : 'radio';
                    $input_name = $q['question_type'] === 'multi_select' ? "answers[{$q['id']}][]" : "answers[{$q['id']}]";
                ?>
                <label class="answer-option" onclick="selectAnswer(this, '<?php echo $input_type; ?>')">
                    <input type="<?php echo $input_type; ?>" name="<?php echo $input_name; ?>" value="<?php echo $a['id']; ?>">
                    <span class="answer-check"></span>
                    <span class="answer-text"><?php echo htmlspecialchars($a['answer_text']); ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div class="submit-section">
            <button type="submit" class="btn btn-primary btn-lg" onclick="return confirm('Are you sure you want to submit your quiz?');">Submit Quiz</button>
        </div>
    </form>
</div>

<script>
function selectAnswer(label, type) {
    var input = label.querySelector('input');
    
    if (type === 'radio') {
        // Deselect siblings
        var siblings = label.parentElement.querySelectorAll('.answer-option');
        siblings.forEach(function(s) { s.classList.remove('selected'); });
    }
    
    if (type === 'checkbox') {
        label.classList.toggle('selected');
        input.checked = label.classList.contains('selected');
    } else {
        label.classList.add('selected');
        input.checked = true;
    }
    
    updateProgress();
}

function updateProgress() {
    var questions = document.querySelectorAll('.question-card');
    var answered = 0;
    questions.forEach(function(q) {
        if (q.querySelector('input:checked')) answered++;
    });
    var percent = (answered / questions.length) * 100;
    document.getElementById('progressFill').style.width = percent + '%';
}

<?php if ($time_remaining !== null): ?>
// Timer
var remaining = <?php echo $time_remaining; ?>;
var timerEl = document.getElementById('timer');

setInterval(function() {
    remaining--;
    if (remaining <= 0) {
        document.getElementById('quizForm').submit();
        return;
    }
    if (remaining <= 60) {
        timerEl.classList.add('warning');
    }
    var min = Math.floor(remaining / 60);
    var sec = remaining % 60;
    timerEl.textContent = min + ':' + (sec < 10 ? '0' : '') + sec;
}, 1000);
<?php endif; ?>
</script>
</body>
</html>
