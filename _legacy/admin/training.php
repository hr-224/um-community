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
require_once '../includes/permissions_ui.php';
requireLogin();

// Check permission
if (!isAdmin() && !hasAnyPermission(['training.manage', 'training.certify', 'training.programs'])) {
    header('Location: /index?error=unauthorized');
    exit();
}

$conn = getDBConnection();
$message = '';
$error = '';
$is_admin = isAdmin();
$can_certify = $is_admin || hasPermission('training.certify');
$can_manage_programs = $is_admin || hasPermission('training.programs');

// Handle certification type creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['create_cert_type']) && $can_manage_programs) {
    $name = trim($_POST['name']);
    $abbr = trim($_POST['abbreviation'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $dept_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : 0;
    $validity = !empty($_POST['validity_days']) ? intval($_POST['validity_days']) : 0;
    $icon = !empty($_POST['icon']) ? $_POST['icon'] : '📜';
    $color = !empty($_POST['color']) ? $_POST['color'] : '#3B82F6';
    
    // Use NULL for empty optional fields
    $dept_param = $dept_id > 0 ? $dept_id : null;
    $validity_param = $validity > 0 ? $validity : null;
    
    $stmt = $conn->prepare("INSERT INTO certification_types (name, abbreviation, description, department_id, validity_days, icon, color) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssiiss", $name, $abbr, $description, $dept_param, $validity_param, $icon, $color);
    
    if ($stmt->execute()) {
        $insert_id = $stmt->insert_id;
        $stmt->close();
        logAudit('create_cert_type', 'certification_type', $insert_id, "Created certification: $name");
        $message = 'Certification type created!';
    } else {
        $stmt->close();
        $error = 'Failed to create certification type.';
    }
}

// Handle issuing certification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['issue_cert']) && $can_certify) {
    $user_id = intval($_POST['user_id']);
    $cert_type_id = intval($_POST['certification_type_id']);
    $issued_date = $_POST['issued_date'] ?? date('Y-m-d');
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $notes = trim($_POST['notes'] ?? '');
    
    $stmt = $conn->prepare("INSERT INTO user_certifications (user_id, certification_type_id, status, issued_date, expiry_date, issued_by, notes) 
                           VALUES (?, ?, 'completed', ?, ?, ?, ?)");
    $issued_by = $_SESSION['user_id'];
    $stmt->bind_param("iissis", $user_id, $cert_type_id, $issued_date, $expiry_date, $issued_by, $notes);
    
    if ($stmt->execute()) {
        logAudit('issue_certification', 'user_certification', $stmt->insert_id, "Issued cert #$cert_type_id to user #$user_id");
        $message = 'Certification issued!';
        
        // Notify user
        $stmt_cn = $conn->prepare("SELECT name FROM certification_types WHERE id = ?");
        $stmt_cn->bind_param("i", $cert_type_id);
        $stmt_cn->execute();
        $cert_row = $stmt_cn->get_result()->fetch_assoc();
        $cert_name = $cert_row['name'] ?? 'Certification';
        $stmt_cn->close();
        createNotification($user_id, 'Certification Issued', "You have been certified for: $cert_name", 'success');
    }
    $stmt->close();
}

// Handle revoking certification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['revoke_cert']) && $can_certify) {
    $cert_id = intval($_POST['cert_id']);
    $reason = trim($_POST['revoke_reason']);
    
    $stmt = $conn->prepare("UPDATE user_certifications SET status = 'revoked', revoked_by = ?, revoked_reason = ? WHERE id = ?");
    $revoked_by = $_SESSION['user_id'];
    $stmt->bind_param("isi", $revoked_by, $reason, $cert_id);
    $stmt->execute();
    
    logAudit('revoke_certification', 'user_certification', $cert_id, "Revoked certification: $reason");
    $message = 'Certification revoked.';
    $stmt->close();
}

// Handle logging training session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['log_training'])) {
    $trainee_id = intval($_POST['trainee_id']);
    $cert_type_id = !empty($_POST['certification_type_id']) ? intval($_POST['certification_type_id']) : null;
    $session_date = $_POST['session_date'];
    $hours = floatval($_POST['hours']);
    $topic = trim($_POST['topic']);
    $notes = trim($_POST['notes'] ?? '');
    $rating = $_POST['performance_rating'] ?? null;
    
    $stmt = $conn->prepare("INSERT INTO training_records (trainee_id, trainer_id, certification_type_id, session_date, hours, topic, notes, performance_rating, status) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed')");
    $trainer_id = $_SESSION['user_id'];
    $stmt->bind_param("iiisdsss", $trainee_id, $trainer_id, $cert_type_id, $session_date, $hours, $topic, $notes, $rating);
    
    if ($stmt->execute()) {
        logAudit('log_training', 'training_record', $stmt->insert_id, "Logged training: $topic ($hours hrs)");
        $message = 'Training session logged!';
    }
    $stmt->close();
}

// Get certification types
$cert_types = $conn->query("SELECT ct.*, d.name as dept_name,
                            (SELECT COUNT(*) FROM user_certifications WHERE certification_type_id = ct.id AND status = 'completed') as active_count
                            FROM certification_types ct
                            LEFT JOIN departments d ON ct.department_id = d.id
                            WHERE ct.is_active = TRUE
                            ORDER BY d.name, ct.name");

// Get recent certifications
$recent_certs = $conn->query("SELECT uc.*, u.username, ct.name as cert_name, ct.icon, ct.color, ib.username as issued_by_name
                              FROM user_certifications uc
                              JOIN users u ON uc.user_id = u.id
                              JOIN certification_types ct ON uc.certification_type_id = ct.id
                              LEFT JOIN users ib ON uc.issued_by = ib.id
                              ORDER BY uc.created_at DESC
                              LIMIT 20");

// Get recent training records
$recent_training = $conn->query("SELECT tr.*, 
                                  trainee.username as trainee_name,
                                  trainer.username as trainer_name,
                                  ct.name as cert_name
                                  FROM training_records tr
                                  JOIN users trainee ON tr.trainee_id = trainee.id
                                  JOIN users trainer ON tr.trainer_id = trainer.id
                                  LEFT JOIN certification_types ct ON tr.certification_type_id = ct.id
                                  ORDER BY tr.session_date DESC, tr.created_at DESC
                                  LIMIT 20");

// Get users and departments for forms
$users = $conn->query("SELECT u.id, u.username FROM users u WHERE u.is_approved = TRUE ORDER BY u.username");
$departments = $conn->query("SELECT id, name FROM departments ORDER BY name");

// Training stats - use safe queries to prevent 500 errors
$stats = [
    'total_certs' => safeQueryCount($conn, "SELECT COUNT(*) as cnt FROM user_certifications WHERE status = 'completed'"),
    'this_month' => safeQueryCount($conn, "SELECT COUNT(*) as cnt FROM training_records WHERE MONTH(session_date) = MONTH(CURDATE()) AND YEAR(session_date) = YEAR(CURDATE())"),
    'total_hours' => safeQueryValue($conn, "SELECT COALESCE(SUM(hours), 0) as total FROM training_records WHERE status = 'completed'", 'total', 0),
    'expiring_soon' => safeQueryCount($conn, "SELECT COUNT(*) as cnt FROM user_certifications WHERE status = 'completed' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training & Certifications - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <?php include '../includes/styles.php'; ?>
    <style>
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        @media (max-width: 768px) { .stats-row { grid-template-columns: repeat(2, 1fr); } }
        .stat-card { background: var(--bg-card); border-radius: var(--radius-lg); padding: 20px; text-align: center; }
        .stat-value { font-size: 32px; font-weight: 800; color: var(--text-primary); }
        .stat-label { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
        
        .cert-type-card {
            background: var(--bg-elevated);
            border: 1px solid var(--bg-elevated);
            border-radius: var(--radius-md);
            padding: 16px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .cert-icon { font-size: 28px; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; border-radius: var(--radius-md); }
        .cert-info { flex: 1; }
        .cert-name { font-weight: 700; font-size: 14px; }
        .cert-meta { font-size: 11px; color: var(--text-muted); }
        
        .record-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 16px;
            background: var(--bg-elevated);
            border-radius: var(--radius-md);
            margin-bottom: 8px;
        }
        .record-info { display: flex; align-items: center; gap: 12px; }
        
        .badge { padding: 4px 10px; border-radius: var(--radius-lg); font-size: 11px; font-weight: 600; }
        .badge-completed { background: rgba(16, 185, 129, 0.2); color: #4ade80; }
        .badge-pending { background: rgba(251, 191, 36, 0.2); color: #f0b232; }
        .badge-expired { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .badge-revoked { background: rgba(107, 114, 128, 0.2); color: #9ca3af; }
        
        .btn { padding: 10px 20px; border: none; border-radius: var(--radius-md); cursor: pointer; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: var(--accent); color: white; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn-danger { background: linear-gradient(135deg, var(--danger), #dc2626); color: white; }
        
        .tabs { display: flex; gap: 8px; margin-bottom: 24px; }
        .tab { padding: 12px 24px; border-radius: var(--radius-md); background: var(--bg-card); color: var(--text-secondary); cursor: pointer; font-weight: 600; transition: all 0.3s; }
        .tab:hover { background: var(--bg-elevated); }
        .tab.active { background: var(--accent); color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 10px 14px; border: 2px solid var(--bg-elevated);
            border-radius: var(--radius-md); background: var(--bg-card); color: var(--text-primary);
        }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--accent); }
        
        .message { background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2)); border: 1px solid rgba(16, 185, 129, 0.3); color: #4ade80; padding: 16px; border-radius: var(--radius-md); margin-bottom: 20px; }
        .error { background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(220, 38, 38, 0.2)); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; padding: 16px; border-radius: var(--radius-md); margin-bottom: 20px; }
        
        .rating-excellent { color: var(--success); }
        .rating-good { color: #3b82f6; }
        .rating-satisfactory { color: #f59e0b; }
        .rating-needs_improvement { color: #f97316; }
        .rating-unsatisfactory { color: var(--danger); }
    </style>
</head>
<body>
    <?php $current_page = 'admin_training'; include '../includes/navbar.php'; ?>
    
    <div class="container">
        <?php showPageToasts(); ?>
        
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_certs']; ?></div>
                <div class="stat-label">Active Certifications</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['this_month']; ?></div>
                <div class="stat-label">Training Sessions (Month)</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_hours'], 1); ?></div>
                <div class="stat-label">Total Training Hours</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="<?php echo $stats['expiring_soon'] > 0 ? 'color: #f0b232; -webkit-text-fill-color: #f0b232;' : ''; ?>"><?php echo $stats['expiring_soon']; ?></div>
                <div class="stat-label">Expiring in 30 Days</div>
            </div>
        </div>
        
        <div class="tabs">
            <div class="tab active" onclick="showTab('certifications')">📜 Certifications</div>
            <div class="tab" onclick="showTab('training')">📚 Training Records</div>
            <div class="tab" onclick="showTab('types')">⚙️ Cert Types <?php if (!$can_manage_programs): ?><span style="opacity: 0.5;">🔒</span><?php endif; ?></div>
        </div>
        
        <!-- Certifications Tab -->
        <div class="tab-content active" id="tab-certifications">
            <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h2>Member Certifications</h2>
                <?php if ($can_certify): ?>
                <button class="btn btn-primary" onclick="openIssueCertModal()">+ Issue Certification</button>
                <?php else: ?>
                <?php lockedButton('+ Issue Certification', 'Certify Members permission required'); ?>
                <?php endif; ?>
            </div>
            
            <div class="section">
                <?php if ($recent_certs && $recent_certs->num_rows > 0): ?>
                    <?php while ($cert = $recent_certs->fetch_assoc()): ?>
                    <div class="record-row">
                        <div class="record-info">
                            <span style="font-size: 24px;"><?php echo $cert['icon']; ?></span>
                            <div>
                                <strong><?php echo htmlspecialchars($cert['username']); ?></strong> - <?php echo htmlspecialchars($cert['cert_name']); ?>
                                <div style="font-size: 11px; color: var(--text-muted);">
                                    Issued: <?php echo date('M j, Y', strtotime($cert['issued_date'])); ?>
                                    <?php if ($cert['expiry_date']): ?> • Expires: <?php echo date('M j, Y', strtotime($cert['expiry_date'])); ?><?php endif; ?>
                                    <?php if ($cert['issued_by_name']): ?> • By: <?php echo htmlspecialchars($cert['issued_by_name']); ?><?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span class="badge badge-<?php echo $cert['status']; ?>"><?php echo strtoupper($cert['status']); ?></span>
                            <?php if ($cert['status'] === 'completed'): ?>
                                <?php if ($can_certify): ?>
                                <button class="btn btn-sm btn-danger" onclick="openRevokeModal(<?php echo $cert['id']; ?>, '<?php echo htmlspecialchars(addslashes($cert['username'])); ?>', '<?php echo htmlspecialchars(addslashes($cert['cert_name'])); ?>')">Revoke</button>
                                <?php else: ?>
                                <?php lockedButton('Revoke', 'Certify permission required'); ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-muted);">No certifications issued yet</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Training Records Tab -->
        <div class="tab-content" id="tab-training">
            <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h2>Training Records</h2>
                <button class="btn btn-primary" onclick="openTrainingModal()">+ Log Training</button>
            </div>
            
            <div class="section">
                <?php if ($recent_training && $recent_training->num_rows > 0): ?>
                    <?php while ($tr = $recent_training->fetch_assoc()): ?>
                    <div class="record-row">
                        <div class="record-info">
                            <div>
                                <strong><?php echo htmlspecialchars($tr['trainee_name']); ?></strong>
                                <?php if ($tr['cert_name']): ?><span style="color: var(--text-muted);"> - <?php echo htmlspecialchars($tr['cert_name']); ?></span><?php endif; ?>
                                <div style="font-size: 11px; color: var(--text-muted);">
                                    <?php echo date('M j, Y', strtotime($tr['session_date'])); ?> • <?php echo $tr['hours']; ?> hrs • Trainer: <?php echo htmlspecialchars($tr['trainer_name']); ?>
                                    <?php if ($tr['topic']): ?><br>Topic: <?php echo htmlspecialchars($tr['topic']); ?><?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php if ($tr['performance_rating']): ?>
                        <span class="rating-<?php echo $tr['performance_rating']; ?>" style="font-weight: 600; text-transform: capitalize;">
                            <?php echo str_replace('_', ' ', $tr['performance_rating']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-muted);">No training records yet</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Certification Types Tab -->
        <div class="tab-content" id="tab-types">
            <?php if ($can_manage_programs): ?>
            <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h2>Certification Types</h2>
                <button class="btn btn-primary" onclick="openCertTypeModal()">+ Add Cert Type</button>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 12px;">
                <?php if ($cert_types): $cert_types->data_seek(0); while ($ct = $cert_types->fetch_assoc()): ?>
                <div class="cert-type-card">
                    <div class="cert-icon" style="background: <?php echo $ct['color']; ?>20;"><?php echo $ct['icon']; ?></div>
                    <div class="cert-info">
                        <div class="cert-name"><?php echo htmlspecialchars($ct['name']); ?> <?php if ($ct['abbreviation']): ?>(<?php echo htmlspecialchars($ct['abbreviation']); ?>)<?php endif; ?></div>
                        <div class="cert-meta">
                            <?php echo $ct['dept_name'] ?? 'All Departments'; ?> • <?php echo $ct['active_count']; ?> active
                            <?php if ($ct['validity_days']): ?> • Valid <?php echo $ct['validity_days']; ?> days<?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; endif; ?>
            </div>
            <?php else: ?>
            <div class="section permission-locked" style="min-height: 200px;">
                <h2>Certification Types</h2>
                <p style="color: var(--text-muted);">Manage certification types that can be issued to members.</p>
                <?php permissionLockOverlay('You need the "Manage Training Programs" permission to manage certification types.'); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Issue Certification Modal -->
    <div class="modal" id="issueCertModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Issue Certification</h3>
                <button class="modal-close" onclick="closeModal('issueCertModal')">&times;</button>
            </div>
            <form method="POST">
                    <?php echo csrfField(); ?>
                <div class="form-group">
                    <label>Member *</label>
                    <select name="user_id" required>
                        <option value="">Select Member</option>
                        <?php if ($users): $users->data_seek(0); while ($u = $users->fetch_assoc()): ?>
                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Certification Type *</label>
                    <select name="certification_type_id" required>
                        <option value="">Select Certification</option>
                        <?php if ($cert_types): $cert_types->data_seek(0); while ($ct = $cert_types->fetch_assoc()): ?>
                        <option value="<?php echo $ct['id']; ?>"><?php echo htmlspecialchars($ct['name']); ?></option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label>Issue Date</label>
                        <input type="date" name="issued_date" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Expiry Date (optional)</label>
                        <input type="date" name="expiry_date">
                    </div>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="2"></textarea>
                </div>
                <button type="submit" name="issue_cert" class="btn btn-primary" style="width: 100%;">Issue Certification</button>
            </form>
        </div>
    </div>
    
    <!-- Log Training Modal -->
    <div class="modal" id="trainingModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Log Training Session</h3>
                <button class="modal-close" onclick="closeModal('trainingModal')">&times;</button>
            </div>
            <form method="POST">
                    <?php echo csrfField(); ?>
                <div class="form-group">
                    <label>Trainee *</label>
                    <select name="trainee_id" required>
                        <option value="">Select Trainee</option>
                        <?php if ($users): $users->data_seek(0); while ($u = $users->fetch_assoc()): ?>
                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Related Certification (optional)</label>
                    <select name="certification_type_id">
                        <option value="">None</option>
                        <?php if ($cert_types): $cert_types->data_seek(0); while ($ct = $cert_types->fetch_assoc()): ?>
                        <option value="<?php echo $ct['id']; ?>"><?php echo htmlspecialchars($ct['name']); ?></option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label>Date *</label>
                        <input type="date" name="session_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Hours *</label>
                        <input type="number" name="hours" step="0.25" min="0.25" value="1" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Topic/Subject *</label>
                    <input type="text" name="topic" required>
                </div>
                <div class="form-group">
                    <label>Performance Rating</label>
                    <select name="performance_rating">
                        <option value="">Not Rated</option>
                        <option value="excellent">Excellent</option>
                        <option value="good">Good</option>
                        <option value="satisfactory">Satisfactory</option>
                        <option value="needs_improvement">Needs Improvement</option>
                        <option value="unsatisfactory">Unsatisfactory</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="2"></textarea>
                </div>
                <button type="submit" name="log_training" class="btn btn-primary" style="width: 100%;">Log Training</button>
            </form>
        </div>
    </div>
    
    <!-- Create Cert Type Modal -->
    <div class="modal" id="certTypeModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Certification Type</h3>
                <button class="modal-close" onclick="closeModal('certTypeModal')">&times;</button>
            </div>
            <form method="POST">
                    <?php echo csrfField(); ?>
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" required placeholder="e.g., Field Training Officer">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label>Abbreviation</label>
                        <input type="text" name="abbreviation" placeholder="e.g., FTO">
                    </div>
                    <div class="form-group">
                        <label>Validity (days)</label>
                        <input type="number" name="validity_days" placeholder="Leave blank for permanent">
                    </div>
                </div>
                <div class="form-group">
                    <label>Department (optional)</label>
                    <select name="department_id">
                        <option value="">All Departments</option>
                        <?php if ($departments): $departments->data_seek(0); while ($d = $departments->fetch_assoc()): ?>
                        <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label>Icon</label>
                        <input type="text" name="icon" value="📜" maxlength="5">
                    </div>
                    <div class="form-group">
                        <label>Color</label>
                        <input type="color" name="color" value="#3B82F6" style="height: 42px;">
                    </div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="2"></textarea>
                </div>
                <button type="submit" name="create_cert_type" class="btn btn-primary" style="width: 100%;">Create Certification Type</button>
            </form>
        </div>
    </div>
    
    <!-- Revoke Modal -->
    <div class="modal" id="revokeModal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3>Revoke Certification</h3>
                <button class="modal-close" onclick="closeModal('revokeModal')">&times;</button>
            </div>
            <form method="POST">
                    <?php echo csrfField(); ?>
                <input type="hidden" name="cert_id" id="revoke_cert_id">
                <p style="margin-bottom: 16px;">Revoke <strong id="revoke_cert_name"></strong> from <strong id="revoke_user_name"></strong>?</p>
                <div class="form-group">
                    <label>Reason for Revocation *</label>
                    <textarea name="revoke_reason" required rows="3"></textarea>
                </div>
                <button type="submit" name="revoke_cert" class="btn btn-danger" style="width: 100%;">Revoke Certification</button>
            </form>
        </div>
    </div>
    
    <script>
        function showTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            event.target.classList.add('active');
            document.getElementById('tab-' + tab).classList.add('active');
        }
        
        function openIssueCertModal() { document.getElementById('issueCertModal').classList.add('active'); }
        function openTrainingModal() { document.getElementById('trainingModal').classList.add('active'); }
        function openCertTypeModal() { document.getElementById('certTypeModal').classList.add('active'); }
        
        function openRevokeModal(certId, userName, certName) {
            document.getElementById('revoke_cert_id').value = certId;
            document.getElementById('revoke_user_name').textContent = userName;
            document.getElementById('revoke_cert_name').textContent = certName;
            document.getElementById('revokeModal').classList.add('active');
        }
        
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }
        
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) this.classList.remove('active');
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
