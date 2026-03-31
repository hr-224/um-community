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

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Get user's current departments
$stmt = $conn->prepare("SELECT d.id, d.name FROM departments d JOIN roster r ON d.id = r.department_id WHERE r.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_depts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get all departments
$all_depts = $conn->query("SELECT id, name FROM departments ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken()) {
    $from_dept = intval($_POST['from_department'] ?? 0);
    $to_dept = intval($_POST['to_department'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    
    if (!$from_dept || !$to_dept) {
        $error = 'Please select both departments.';
    } elseif ($from_dept === $to_dept) {
        $error = 'Cannot transfer to the same department.';
    } elseif (empty($reason)) {
        $error = 'Please provide a reason for the transfer request.';
    } else {
        // Check for pending request
        $stmt = $conn->prepare("SELECT id FROM transfer_requests WHERE user_id = ? AND status = 'pending'");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = 'You already have a pending transfer request.';
        } else {
            $stmt = $conn->prepare("INSERT INTO transfer_requests (user_id, from_department_id, to_department_id, reason) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiis", $user_id, $from_dept, $to_dept, $reason);
            if ($stmt->execute()) {
                $message = 'Transfer request submitted successfully!';
                logAudit('transfer_request', 'transfer_request', $stmt->insert_id, 'Requested department transfer');
            } else {
                $error = 'Failed to submit request.';
            }
        }
        $stmt->close();
    }
}

// Get user's transfer requests
$stmt = $conn->prepare("
    SELECT tr.*, 
           fd.name as from_dept_name, td.name as to_dept_name,
           u.username as reviewed_by_name
    FROM transfer_requests tr
    LEFT JOIN departments fd ON tr.from_department_id = fd.id
    LEFT JOIN departments td ON tr.to_department_id = td.id
    LEFT JOIN users u ON tr.reviewed_by = u.id
    WHERE tr.user_id = ?
    ORDER BY tr.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

$theme = getThemeColors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Request - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
    <style>
        .transfer-form { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 24px; margin-bottom: 30px; }
        .form-row { display: grid; grid-template-columns: 1fr 50px 1fr; gap: 16px; align-items: end; margin-bottom: 20px; }
        .arrow-icon { text-align: center; font-size: 24px; color: var(--text-muted); padding-bottom: 10px; }
        .requests-list { display: flex; flex-direction: column; gap: 16px; }
        .request-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px; }
        .request-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .request-depts { display: flex; align-items: center; gap: 12px; }
        .dept-badge { padding: 6px 12px; background: var(--bg-elevated); border-radius: var(--radius-lg); font-size: 13px; }
        .status-badge { padding: 4px 12px; border-radius: var(--radius-lg); font-size: 12px; font-weight: 600; }
        .status-pending { background: rgba(245,158,11,0.2); color: #f59e0b; }
        .status-approved { background: rgba(16,185,129,0.2); color: var(--success); }
        .status-denied { background: rgba(239,68,68,0.2); color: var(--danger); }
        .request-reason { color: var(--text-secondary); font-size: 14px; margin-bottom: 12px; }
        .request-meta { font-size: 12px; color: var(--text-muted); }
        .review-notes { margin-top: 12px; padding: 12px; background: var(--bg-primary); border-radius: var(--radius-sm); font-size: 13px; }
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .arrow-icon { transform: rotate(90deg); padding: 0; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>🔄 Transfer Request</h1>
    </div>
    
    <?php if ($message) showToast($message, 'success'); ?>
    <?php if ($error) showToast($error, 'error'); ?>
    
    <?php if (!empty($current_depts)): ?>
    <form method="POST" class="transfer-form">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
        
        <h3 style="margin-top: 0; margin-bottom: 20px; color: var(--text-primary);">Request a Department Transfer</h3>
        
        <div class="form-row">
            <div class="form-group">
                <label>From Department</label>
                <select name="from_department" class="form-control" required>
                    <option value="">Select current department...</option>
                    <?php foreach ($current_depts as $dept): ?>
                        <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="arrow-icon">→</div>
            <div class="form-group">
                <label>To Department</label>
                <select name="to_department" class="form-control" required>
                    <option value="">Select new department...</option>
                    <?php foreach ($all_depts as $dept): ?>
                        <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label>Reason for Transfer</label>
            <textarea name="reason" class="form-control" rows="4" placeholder="Please explain why you are requesting this transfer..." required></textarea>
        </div>
        
        <button type="submit" class="btn btn-primary">Submit Request</button>
    </form>
    <?php else: ?>
    <div class="alert alert-info">You must be a member of at least one department to request a transfer.</div>
    <?php endif; ?>
    
    <h2 style="margin-bottom: 20px; font-size: 18px; color: var(--text-primary);">My Transfer Requests</h2>
    
    <?php if (empty($requests)): ?>
        <div class="empty-state"><p>No transfer requests submitted.</p></div>
    <?php else: ?>
    <div class="requests-list">
        <?php foreach ($requests as $req): ?>
        <div class="request-card">
            <div class="request-header">
                <div class="request-depts">
                    <span class="dept-badge"><?php echo htmlspecialchars($req['from_dept_name']); ?></span>
                    <span style="color: var(--text-muted);">→</span>
                    <span class="dept-badge"><?php echo htmlspecialchars($req['to_dept_name']); ?></span>
                </div>
                <span class="status-badge status-<?php echo $req['status']; ?>">
                    <?php echo ucfirst($req['status']); ?>
                </span>
            </div>
            <div class="request-reason"><?php echo htmlspecialchars($req['reason']); ?></div>
            <div class="request-meta">
                Submitted: <?php echo date('M j, Y g:i A', strtotime($req['created_at'])); ?>
                <?php if ($req['reviewed_by_name']): ?>
                    • Reviewed by: <?php echo htmlspecialchars($req['reviewed_by_name']); ?> on <?php echo date('M j, Y', strtotime($req['reviewed_at'])); ?>
                <?php endif; ?>
            </div>
            <?php if ($req['review_notes']): ?>
            <div class="review-notes">
                <strong>Review Notes:</strong> <?php echo htmlspecialchars($req['review_notes']); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
