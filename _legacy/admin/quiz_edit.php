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
if (!defined('TOAST_LOADED')) { require_once __DIR__ . '/../includes/toast.php'; define('TOAST_LOADED', true); }
requireLogin();
requireAdmin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_question') {
        $question_text = trim($_POST['question_text'] ?? '');
        $question_type = $_POST['question_type'] ?? 'multiple_choice';
        $points = intval($_POST['points'] ?? 1);
        $explanation = trim($_POST['explanation'] ?? '');
        
        if ($question_text) {
            // Get next display order
            $result = $conn->query("SELECT MAX(display_order) as max_order FROM quiz_questions WHERE quiz_id = $quiz_id");
            $max = $result->fetch_assoc();
            $display_order = ($max['max_order'] ?? 0) + 1;
            
            $stmt = $conn->prepare("INSERT INTO quiz_questions (quiz_id, question_text, question_type, points, explanation, display_order) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issisi", $quiz_id, $question_text, $question_type, $points, $explanation, $display_order);
            $stmt->execute();
            $question_id = $stmt->insert_id;
            $stmt->close();
            
            // Add answers
            $answers = $_POST['answers'] ?? [];
            $correct = $_POST['correct'] ?? [];
            
            foreach ($answers as $i => $answer_text) {
                $answer_text = trim($answer_text);
                if ($answer_text) {
                    $is_correct = in_array($i, $correct) ? 1 : 0;
                    $stmt = $conn->prepare("INSERT INTO quiz_answers (question_id, answer_text, is_correct, display_order) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("isii", $question_id, $answer_text, $is_correct, $i);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            
            $message = "Question added!";
        }
    } elseif ($action === 'update_question') {
        $question_id = intval($_POST['question_id'] ?? 0);
        $question_text = trim($_POST['question_text'] ?? '');
        $points = intval($_POST['points'] ?? 1);
        $explanation = trim($_POST['explanation'] ?? '');
        
        $stmt = $conn->prepare("UPDATE quiz_questions SET question_text = ?, points = ?, explanation = ? WHERE id = ? AND quiz_id = ?");
        $stmt->bind_param("sisii", $question_text, $points, $explanation, $question_id, $quiz_id);
        $stmt->execute();
        $stmt->close();
        
        // Delete old answers
        $stmt = $conn->prepare("DELETE FROM quiz_answers WHERE question_id = ?");
        $stmt->bind_param("i", $question_id);
        $stmt->execute();
        $stmt->close();
        
        // Add new answers
        $answers = $_POST['answers'] ?? [];
        $correct = $_POST['correct'] ?? [];
        
        foreach ($answers as $i => $answer_text) {
            $answer_text = trim($answer_text);
            if ($answer_text) {
                $is_correct = in_array($i, $correct) ? 1 : 0;
                $stmt = $conn->prepare("INSERT INTO quiz_answers (question_id, answer_text, is_correct, display_order) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isii", $question_id, $answer_text, $is_correct, $i);
                $stmt->execute();
                $stmt->close();
            }
        }
        
        $message = "Question updated!";
    } elseif ($action === 'delete_question') {
        $question_id = intval($_POST['question_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM quiz_questions WHERE id = ? AND quiz_id = ?");
        $stmt->bind_param("ii", $question_id, $quiz_id);
        $stmt->execute();
        $stmt->close();
        $message = "Question deleted.";
    } elseif ($action === 'update_quiz') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $pass_score = intval($_POST['pass_score'] ?? 70);
        $time_limit = !empty($_POST['time_limit']) ? intval($_POST['time_limit']) : null;
        $max_attempts = !empty($_POST['max_attempts']) ? intval($_POST['max_attempts']) : null;
        $shuffle = isset($_POST['shuffle_questions']) ? 1 : 0;
        $show_answers = isset($_POST['show_correct_answers']) ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE quizzes SET title = ?, description = ?, pass_score = ?, time_limit_minutes = ?, max_attempts = ?, shuffle_questions = ?, show_correct_answers = ? WHERE id = ?");
        $stmt->bind_param("ssiiiiii", $title, $description, $pass_score, $time_limit, $max_attempts, $shuffle, $show_answers, $quiz_id);
        $stmt->execute();
        $stmt->close();
        
        // Refresh quiz data
        $stmt = $conn->prepare("SELECT * FROM quizzes WHERE id = ?");
        $stmt->bind_param("i", $quiz_id);
        $stmt->execute();
        $quiz = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $message = "Quiz settings updated!";
    }
}

// Get questions with answers
$questions = [];
$result = $conn->query("SELECT * FROM quiz_questions WHERE quiz_id = $quiz_id ORDER BY display_order");
while ($q = $result->fetch_assoc()) {
    $q['answers'] = [];
    $stmt = $conn->prepare("SELECT * FROM quiz_answers WHERE question_id = ? ORDER BY display_order");
    $stmt->bind_param("i", $q['id']);
    $stmt->execute();
    $q['answers'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
    <title>Edit Quiz - <?php echo htmlspecialchars($quiz['title']); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
    <style>
        .quiz-settings { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px; margin-bottom: 24px; }
        .question-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px; margin-bottom: 16px; }
        .question-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .question-number { font-size: 12px; color: var(--accent); font-weight: 600; }
        .question-text { font-size: 16px; font-weight: 500; color: var(--text-primary); margin-bottom: 16px; }
        .answer-list { list-style: none; padding: 0; margin: 0; }
        .answer-item { padding: 10px 16px; margin: 6px 0; border-radius: var(--radius-sm); background: var(--bg-primary); display: flex; align-items: center; gap: 10px; }
        .answer-item.correct { background: rgba(34, 197, 94, 0.15); border: 1px solid rgba(34, 197, 94, 0.3); }
        .answer-item.incorrect { background: var(--bg-primary); }
        .answer-indicator { width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; }
        .answer-indicator.correct { background: #22c55e; color: var(--text-primary); }
        .answer-indicator.incorrect { background: var(--bg-elevated); color: var(--text-muted); }
        .add-question-form { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 24px; }
        .answers-container { margin-top: 16px; }
        .answer-input-row { display: flex; gap: 12px; align-items: center; margin-bottom: 10px; }
        .answer-input-row input[type="text"] { flex: 1; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .question-type-info { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
        @media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <div>
            <a href="quizzes.php" style="color: var(--text-muted); text-decoration: none; font-size: 14px;">← Back to Quizzes</a>
            <h1 style="margin-top: 8px;">📝 <?php echo htmlspecialchars($quiz['title']); ?></h1>
        </div>
    </div>
    
    <?php if ($message) showToast($message, 'success'); ?>
    <?php if ($error) showToast($error, 'error'); ?>
    
    <!-- Quiz Settings -->
    <div class="quiz-settings">
        <h3 style="margin-bottom: 16px;">Quiz Settings</h3>
        <form method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="update_quiz">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Quiz Title</label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($quiz['title']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Pass Score (%)</label>
                    <input type="number" name="pass_score" value="<?php echo $quiz['pass_score']; ?>" min="1" max="100">
                </div>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="2"><?php echo htmlspecialchars($quiz['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Time Limit (minutes)</label>
                    <input type="number" name="time_limit" value="<?php echo $quiz['time_limit_minutes']; ?>" placeholder="No limit">
                </div>
                <div class="form-group">
                    <label>Max Attempts</label>
                    <input type="number" name="max_attempts" value="<?php echo $quiz['max_attempts']; ?>" placeholder="Unlimited">
                </div>
            </div>
            
            <div class="form-row">
                <label style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="shuffle_questions" <?php echo $quiz['shuffle_questions'] ? 'checked' : ''; ?>> Shuffle questions
                </label>
                <label style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="show_correct_answers" <?php echo $quiz['show_correct_answers'] ? 'checked' : ''; ?>> Show correct answers
                </label>
            </div>
            
            <button type="submit" class="btn btn-primary" style="margin-top: 16px;">Save Settings</button>
        </form>
    </div>
    
    <!-- Questions -->
    <h3 style="margin-bottom: 16px;">Questions (<?php echo count($questions); ?>)</h3>
    
    <?php foreach ($questions as $i => $q): ?>
    <div class="question-card">
        <div class="question-header">
            <div>
                <div class="question-number">Question <?php echo $i + 1; ?> • <?php echo ucfirst(str_replace('_', ' ', $q['question_type'])); ?> • <?php echo $q['points']; ?> pts</div>
                <div class="question-text"><?php echo htmlspecialchars($q['question_text']); ?></div>
            </div>
            <div style="display: flex; gap: 8px;">
                <button class="btn btn-sm" onclick="editQuestion(<?php echo htmlspecialchars(json_encode($q)); ?>)">Edit</button>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this question?');">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="delete_question">
                    <input type="hidden" name="question_id" value="<?php echo $q['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                </form>
            </div>
        </div>
        <ul class="answer-list">
            <?php foreach ($q['answers'] as $a): ?>
            <li class="answer-item <?php echo $a['is_correct'] ? 'correct' : 'incorrect'; ?>">
                <span class="answer-indicator <?php echo $a['is_correct'] ? 'correct' : 'incorrect'; ?>">
                    <?php echo $a['is_correct'] ? '✓' : ''; ?>
                </span>
                <?php echo htmlspecialchars($a['answer_text']); ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($q['explanation']): ?>
        <div style="margin-top: 12px; padding: 12px; background: rgba(59, 130, 246, 0.1); border-radius: var(--radius-sm); font-size: 13px; color: var(--text-secondary);">
            <strong>Explanation:</strong> <?php echo htmlspecialchars($q['explanation']); ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    
    <!-- Add Question Form -->
    <div class="add-question-form" id="questionForm">
        <h3 id="formTitle">Add New Question</h3>
        <form method="POST" id="questionFormElement">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" id="formAction" value="add_question">
            <input type="hidden" name="question_id" id="editQuestionId" value="">
            
            <div class="form-group">
                <label>Question Text *</label>
                <textarea name="question_text" id="questionText" rows="2" required placeholder="Enter your question..."></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Question Type</label>
                    <select name="question_type" id="questionType" onchange="updateAnswerUI()">
                        <option value="multiple_choice">Multiple Choice (one correct)</option>
                        <option value="multi_select">Multiple Select (multiple correct)</option>
                        <option value="true_false">True / False</option>
                    </select>
                    <div class="question-type-info" id="typeInfo">Select one correct answer</div>
                </div>
                <div class="form-group">
                    <label>Points</label>
                    <input type="number" name="points" id="questionPoints" value="1" min="1">
                </div>
            </div>
            
            <div class="answers-container" id="answersContainer">
                <label>Answers (check correct ones)</label>
                <div id="answerInputs">
                    <div class="answer-input-row">
                        <input type="checkbox" name="correct[]" value="0">
                        <input type="text" name="answers[]" placeholder="Answer option 1" required>
                    </div>
                    <div class="answer-input-row">
                        <input type="checkbox" name="correct[]" value="1">
                        <input type="text" name="answers[]" placeholder="Answer option 2" required>
                    </div>
                    <div class="answer-input-row">
                        <input type="checkbox" name="correct[]" value="2">
                        <input type="text" name="answers[]" placeholder="Answer option 3">
                    </div>
                    <div class="answer-input-row">
                        <input type="checkbox" name="correct[]" value="3">
                        <input type="text" name="answers[]" placeholder="Answer option 4">
                    </div>
                </div>
                <button type="button" class="btn btn-sm" onclick="addAnswerRow()" id="addAnswerBtn" style="margin-top: 8px;">+ Add Answer</button>
            </div>
            
            <div class="form-group" style="margin-top: 16px;">
                <label>Explanation (shown after answering)</label>
                <textarea name="explanation" id="questionExplanation" rows="2" placeholder="Explain the correct answer..."></textarea>
            </div>
            
            <div style="display: flex; gap: 12px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary" id="submitBtn">Add Question</button>
                <button type="button" class="btn" onclick="resetForm()" id="cancelBtn" style="display: none;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
var answerCount = 4;

function updateAnswerUI() {
    var type = document.getElementById('questionType').value;
    var info = document.getElementById('typeInfo');
    var inputs = document.getElementById('answerInputs');
    var addBtn = document.getElementById('addAnswerBtn');
    
    if (type === 'true_false') {
        info.textContent = 'Select which is correct: True or False';
        inputs.innerHTML = `
            <div class="answer-input-row">
                <input type="radio" name="correct[]" value="0">
                <input type="text" name="answers[]" value="True" readonly style="background: var(--bg-primary);">
            </div>
            <div class="answer-input-row">
                <input type="radio" name="correct[]" value="1">
                <input type="text" name="answers[]" value="False" readonly style="background: var(--bg-primary);">
            </div>
        `;
        addBtn.style.display = 'none';
    } else if (type === 'multi_select') {
        info.textContent = 'Check all correct answers';
        addBtn.style.display = 'inline-block';
        // Convert radios to checkboxes
        var radios = inputs.querySelectorAll('input[type="radio"]');
        radios.forEach(function(r) {
            r.type = 'checkbox';
        });
    } else {
        info.textContent = 'Select one correct answer';
        addBtn.style.display = 'inline-block';
        // Convert checkboxes to radios for single select behavior
    }
}

function addAnswerRow() {
    var container = document.getElementById('answerInputs');
    var type = document.getElementById('questionType').value;
    var inputType = type === 'multiple_choice' ? 'checkbox' : 'checkbox';
    var row = document.createElement('div');
    row.className = 'answer-input-row';
    row.innerHTML = `
        <input type="${inputType}" name="correct[]" value="${answerCount}">
        <input type="text" name="answers[]" placeholder="Answer option ${answerCount + 1}">
        <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()">×</button>
    `;
    container.appendChild(row);
    answerCount++;
}

function editQuestion(q) {
    document.getElementById('formTitle').textContent = 'Edit Question';
    document.getElementById('formAction').value = 'update_question';
    document.getElementById('editQuestionId').value = q.id;
    document.getElementById('questionText').value = q.question_text;
    document.getElementById('questionType').value = q.question_type;
    document.getElementById('questionPoints').value = q.points;
    document.getElementById('questionExplanation').value = q.explanation || '';
    document.getElementById('submitBtn').textContent = 'Update Question';
    document.getElementById('cancelBtn').style.display = 'inline-block';
    
    // Rebuild answer inputs
    var container = document.getElementById('answerInputs');
    container.innerHTML = '';
    answerCount = 0;
    
    q.answers.forEach(function(a, i) {
        var inputType = q.question_type === 'true_false' ? 'radio' : 'checkbox';
        var row = document.createElement('div');
        row.className = 'answer-input-row';
        row.innerHTML = `
            <input type="${inputType}" name="correct[]" value="${i}" ${a.is_correct ? 'checked' : ''}>
            <input type="text" name="answers[]" value="${a.answer_text.replace(/"/g, '&quot;')}" ${q.question_type === 'true_false' ? 'readonly style="background: var(--bg-primary);"' : ''}>
        `;
        container.appendChild(row);
        answerCount++;
    });
    
    updateAnswerUI();
    document.getElementById('questionForm').scrollIntoView({ behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('formTitle').textContent = 'Add New Question';
    document.getElementById('formAction').value = 'add_question';
    document.getElementById('editQuestionId').value = '';
    document.getElementById('questionFormElement').reset();
    document.getElementById('submitBtn').textContent = 'Add Question';
    document.getElementById('cancelBtn').style.display = 'none';
    
    document.getElementById('answerInputs').innerHTML = `
        <div class="answer-input-row">
            <input type="checkbox" name="correct[]" value="0">
            <input type="text" name="answers[]" placeholder="Answer option 1" required>
        </div>
        <div class="answer-input-row">
            <input type="checkbox" name="correct[]" value="1">
            <input type="text" name="answers[]" placeholder="Answer option 2" required>
        </div>
        <div class="answer-input-row">
            <input type="checkbox" name="correct[]" value="2">
            <input type="text" name="answers[]" placeholder="Answer option 3">
        </div>
        <div class="answer-input-row">
            <input type="checkbox" name="correct[]" value="3">
            <input type="text" name="answers[]" placeholder="Answer option 4">
        </div>
    `;
    answerCount = 4;
    updateAnswerUI();
}
</script>
</body>
</html>
