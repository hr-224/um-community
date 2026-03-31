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

// Get departments and training programs for dropdowns (with safe queries)
$departments = [];
$certifications = [];
$programs = [];

$dept_result = $conn->query("SELECT id, name FROM departments ORDER BY name");
if ($dept_result) $departments = $dept_result->fetch_all(MYSQLI_ASSOC);

// Check if certification_types table exists
$cert_result = @$conn->query("SELECT id, name FROM certification_types WHERE is_active = 1 ORDER BY name");
if ($cert_result) $certifications = $cert_result->fetch_all(MYSQLI_ASSOC);

// Check if training_programs table exists
$prog_result = @$conn->query("SELECT id, name FROM training_programs WHERE is_active = 1 ORDER BY name");
if ($prog_result) $programs = $prog_result->fetch_all(MYSQLI_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_quiz') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $department_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
        $certification_type_id = !empty($_POST['certification_type_id']) ? intval($_POST['certification_type_id']) : null;
        $training_program_id = !empty($_POST['training_program_id']) ? intval($_POST['training_program_id']) : null;
        $pass_score = intval($_POST['pass_score'] ?? 70);
        $time_limit = !empty($_POST['time_limit']) ? intval($_POST['time_limit']) : null;
        $max_attempts = !empty($_POST['max_attempts']) ? intval($_POST['max_attempts']) : null;
        $shuffle = isset($_POST['shuffle_questions']) ? 1 : 0;
        $show_answers = isset($_POST['show_correct_answers']) ? 1 : 0;
        
        if ($title) {
            // Build query with proper NULL handling
            $sql = "INSERT INTO quizzes (title, description, department_id, certification_type_id, training_program_id, pass_score, time_limit_minutes, max_attempts, shuffle_questions, show_correct_answers, created_by) VALUES (?, ?, ";
            $sql .= ($department_id === null ? "NULL" : "?") . ", ";
            $sql .= ($certification_type_id === null ? "NULL" : "?") . ", ";
            $sql .= ($training_program_id === null ? "NULL" : "?") . ", ";
            $sql .= "?, ";
            $sql .= ($time_limit === null ? "NULL" : "?") . ", ";
            $sql .= ($max_attempts === null ? "NULL" : "?") . ", ";
            $sql .= "?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            
            // Build params array dynamically
            $types = "ss";
            $params = [$title, $description];
            
            if ($department_id !== null) { $types .= "i"; $params[] = $department_id; }
            if ($certification_type_id !== null) { $types .= "i"; $params[] = $certification_type_id; }
            if ($training_program_id !== null) { $types .= "i"; $params[] = $training_program_id; }
            
            $types .= "i";
            $params[] = $pass_score;
            
            if ($time_limit !== null) { $types .= "i"; $params[] = $time_limit; }
            if ($max_attempts !== null) { $types .= "i"; $params[] = $max_attempts; }
            
            $types .= "iii";
            $params[] = $shuffle;
            $params[] = $show_answers;
            $params[] = $user_id;
            
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                $quiz_id = $stmt->insert_id;
                $message = "Quiz created! Now add questions.";
                header("Location: quiz_edit.php?id=$quiz_id&created=1");
                exit;
            } else {
                $error = "Failed to create quiz: " . $stmt->error;
            }
            $stmt->close();
        }
    } elseif ($action === 'toggle_active') {
        $quiz_id = intval($_POST['quiz_id'] ?? 0);
        $stmt = $conn->prepare("UPDATE quizzes SET is_active = NOT is_active WHERE id = ?");
        $stmt->bind_param("i", $quiz_id);
        $stmt->execute();
        $stmt->close();
        $message = "Quiz status updated!";
    } elseif ($action === 'delete_quiz') {
        $quiz_id = intval($_POST['quiz_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM quizzes WHERE id = ?");
        $stmt->bind_param("i", $quiz_id);
        if ($stmt->execute()) {
            $message = "Quiz deleted.";
        }
        $stmt->close();
    }
}

// Get all quizzes with stats
$quizzes = $conn->query("
    SELECT q.*, 
           d.name as department_name,
           ct.name as cert_name,
           tp.name as program_name,
           u.username as created_by_name,
           (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as question_count,
           (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id) as attempt_count,
           (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id AND passed = 1) as pass_count
    FROM quizzes q
    LEFT JOIN departments d ON q.department_id = d.id
    LEFT JOIN certification_types ct ON q.certification_type_id = ct.id
    LEFT JOIN training_programs tp ON q.training_program_id = tp.id
    LEFT JOIN users u ON q.created_by = u.id
    ORDER BY q.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

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
        .quiz-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; margin-top: 20px; }
        .quiz-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px; }
        .quiz-card.inactive { opacity: 0.6; }
        .quiz-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .quiz-title { font-size: 18px; font-weight: 600; color: var(--text-primary); }
        .quiz-badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; }
        .badge-active { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
        .badge-inactive { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
        .quiz-meta { font-size: 13px; color: var(--text-muted); margin-bottom: 12px; }
        .quiz-stats { display: flex; gap: 16px; margin: 16px 0; padding: 12px; background: var(--bg-primary); border-radius: var(--radius-sm); }
        .stat { text-align: center; flex: 1; }
        .stat-value { font-size: 20px; font-weight: 700; color: var(--accent); }
        .stat-label { font-size: 11px; color: var(--text-muted); text-transform: uppercase; }
        .quiz-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        @media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>📝 Training Quizzes</h1>
        <button class="btn btn-primary" onclick="document.getElementById('createModal').classList.add('active')">+ Create Quiz</button>
    </div>
    
    <?php if ($message) showToast($message, 'success'); ?>
    <?php if ($error) showToast($error, 'error'); ?>
    
    <?php if (empty($quizzes)): ?>
        <div class="empty-state">
            <p>No quizzes created yet. Create your first quiz to get started!</p>
        </div>
    <?php else: ?>
        <div class="quiz-grid">
            <?php foreach ($quizzes as $quiz): ?>
            <div class="quiz-card <?php echo $quiz['is_active'] ? '' : 'inactive'; ?>">
                <div class="quiz-header">
                    <div class="quiz-title"><?php echo htmlspecialchars($quiz['title']); ?></div>
                    <span class="quiz-badge <?php echo $quiz['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                        <?php echo $quiz['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </div>
                <div class="quiz-meta">
                    <?php if ($quiz['department_name']): ?>
                        <div>📁 <?php echo htmlspecialchars($quiz['department_name']); ?></div>
                    <?php endif; ?>
                    <?php if ($quiz['cert_name']): ?>
                        <div>📜 <?php echo htmlspecialchars($quiz['cert_name']); ?></div>
                    <?php endif; ?>
                    <?php if ($quiz['program_name']): ?>
                        <div>🎓 <?php echo htmlspecialchars($quiz['program_name']); ?></div>
                    <?php endif; ?>
                    <div>Pass Score: <?php echo $quiz['pass_score']; ?>%</div>
                    <?php if ($quiz['time_limit_minutes']): ?>
                        <div>Time Limit: <?php echo $quiz['time_limit_minutes']; ?> min</div>
                    <?php endif; ?>
                </div>
                <div class="quiz-stats">
                    <div class="stat">
                        <div class="stat-value"><?php echo $quiz['question_count']; ?></div>
                        <div class="stat-label">Questions</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value"><?php echo $quiz['attempt_count']; ?></div>
                        <div class="stat-label">Attempts</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value"><?php echo $quiz['attempt_count'] > 0 ? round(($quiz['pass_count'] / $quiz['attempt_count']) * 100) : 0; ?>%</div>
                        <div class="stat-label">Pass Rate</div>
                    </div>
                </div>
                <div class="quiz-actions">
                    <a href="quiz_edit.php?id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-primary">Edit Questions</a>
                    <a href="quiz_results.php?id=<?php echo $quiz['id']; ?>" class="btn btn-sm">View Results</a>
                    <form method="POST" style="display: inline;">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="toggle_active">
                        <input type="hidden" name="quiz_id" value="<?php echo $quiz['id']; ?>">
                        <button type="submit" class="btn btn-sm"><?php echo $quiz['is_active'] ? 'Deactivate' : 'Activate'; ?></button>
                    </form>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this quiz and all its questions?');">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="delete_quiz">
                        <input type="hidden" name="quiz_id" value="<?php echo $quiz['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Create Quiz Modal -->
<div class="modal" id="createModal">
    <div class="modal-content">
        <h3 style="margin-bottom: 20px;">Create New Quiz</h3>
        <form method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="create_quiz">
            
            <div class="form-group">
                <label>Quiz Title *</label>
                <input type="text" name="title" required placeholder="e.g., Patrol Procedures Quiz">
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3" placeholder="Brief description of the quiz..."></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Department (Optional)</label>
                    <select name="department_id">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Certification (Optional)</label>
                    <select name="certification_type_id">
                        <option value="">None</option>
                        <?php foreach ($certifications as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Pass Score (%)</label>
                    <input type="number" name="pass_score" value="70" min="1" max="100">
                </div>
                <div class="form-group">
                    <label>Time Limit (minutes)</label>
                    <input type="number" name="time_limit" placeholder="No limit">
                </div>
            </div>
            
            <div class="form-group">
                <label>Max Attempts</label>
                <input type="number" name="max_attempts" placeholder="Unlimited">
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="shuffle_questions"> Shuffle questions
                </label>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="show_correct_answers" checked> Show correct answers after completion
                </label>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn" onclick="document.getElementById('createModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Quiz</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('createModal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('active');
});
</script>
</body>
</html>
