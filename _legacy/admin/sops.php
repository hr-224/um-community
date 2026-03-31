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
requireAdmin();

$conn = getDBConnection();
$message = '';

// Create SOP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['create_sop'])) {
    $dept_id = intval($_POST['department_id']);
    $title = trim($_POST['title']);
    $content = $_POST['content'];
    $category = trim($_POST['category']);
    
    $stmt = $conn->prepare("INSERT INTO department_sops (department_id, title, content, category, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssi", $dept_id, $title, $content, $category, $_SESSION['user_id']);
    $stmt->execute();
    
    logAudit('create_sop', 'sop', $stmt->insert_id, "Created SOP: $title");
    $message = 'SOP created successfully!';
    $stmt->close();
}

// Update SOP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['update_sop'])) {
    $sop_id = intval($_POST['sop_id']);
    $title = trim($_POST['title']);
    $content = $_POST['content'];
    $category = trim($_POST['category']);
    $version = trim($_POST['version']);
    
    $stmt = $conn->prepare("UPDATE department_sops SET title = ?, content = ?, category = ?, version = ?, last_updated_by = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("ssssii", $title, $content, $category, $version, $_SESSION['user_id'], $sop_id);
    $stmt->execute();
    
    logAudit('update_sop', 'sop', $sop_id, "Updated SOP: $title");
    $message = 'SOP updated!';
    $stmt->close();
}

// Toggle SOP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['toggle_sop'])) {
    $sop_id = intval($_POST['sop_id']);
    $stmt = $conn->prepare("UPDATE department_sops SET is_active = NOT is_active WHERE id = ?");
    $stmt->bind_param("i", $sop_id);
    $stmt->execute();
    $stmt->close();
    $message = 'SOP status updated!';
}

// Delete SOP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['delete_sop'])) {
    $sop_id = intval($_POST['sop_id']);
    $stmt = $conn->prepare("DELETE FROM department_sops WHERE id = ?");
    $stmt->bind_param("i", $sop_id);
    $stmt->execute();
    $stmt->close();
    logAudit('delete_sop', 'sop', $sop_id, 'Deleted SOP');
    $message = 'SOP deleted!';
}

// Get filter
$dept_filter = $_GET['dept'] ?? '';

$where = "1=1";
if ($dept_filter) $where .= " AND s.department_id = " . intval($dept_filter);

$sops = $conn->query("
    SELECT s.*, d.name as dept_name, d.abbreviation, u.username as author_name,
           (SELECT COUNT(*) FROM sop_acknowledgments WHERE sop_id = s.id) as ack_count,
           (SELECT COUNT(*) FROM roster WHERE department_id = s.department_id AND status = 'active') as dept_roster_count
    FROM department_sops s
    JOIN departments d ON s.department_id = d.id
    JOIN users u ON s.created_by = u.id
    WHERE $where
    ORDER BY d.name, s.category, s.title
");

// If viewing compliance for a specific SOP
$compliance_sop = null;
$unacknowledged = null;
if (isset($_GET['compliance'])) {
    $comp_id = intval($_GET['compliance']);
    $stmt = $conn->prepare("SELECT s.*, d.name as dept_name, d.abbreviation FROM department_sops s JOIN departments d ON s.department_id = d.id WHERE s.id = ?");
    $stmt->bind_param("i", $comp_id);
    $stmt->execute();
    $compliance_sop = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($compliance_sop) {
        $unacknowledged = $conn->query("SELECT u.username, r.badge_number, rk.rank_name
            FROM roster r
            JOIN users u ON r.user_id = u.id
            JOIN ranks rk ON r.rank_id = rk.id
            WHERE r.department_id = {$compliance_sop['department_id']} AND r.status = 'active'
            AND r.user_id NOT IN (SELECT sa.user_id FROM sop_acknowledgments sa WHERE sa.sop_id = $comp_id)
            ORDER BY rk.rank_order, u.username");
    }
}

$departments = $conn->query("SELECT id, name, abbreviation FROM departments ORDER BY name");

// Get SOP for editing
$edit_sop = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM department_sops WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_sop = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department SOPs - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <?php include '../includes/styles.php'; ?>
    <style>
        .container { max-width: 1400px; }
        .message { background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2)); border: 1px solid rgba(16, 185, 129, 0.3); color: #4ade80; padding: 16px 24px; border-radius: var(--radius-md); margin-bottom: 24px; }
        .grid { display: grid; grid-template-columns: 400px 1fr; gap: 24px; }
        @media (max-width: 1024px) { .grid { grid-template-columns: 1fr; } }
        .form-group textarea { min-height: 300px; resize: vertical; font-family: monospace; }
        .filters { display: flex; gap: 12px; margin-bottom: 20px; }
        .filters select { padding: 10px 16px; border: 1px solid var(--bg-elevated); border-radius: var(--radius-sm); background: var(--bg-card); color: var(--text-primary); }
        .sop-card { background: var(--bg-elevated); border: 1px solid var(--bg-elevated); border-radius: var(--radius-md); padding: 20px; margin-bottom: 12px; }
        .sop-card.inactive { opacity: 0.5; }
        .sop-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
        .sop-title { font-weight: 700; font-size: 16px; }
        .sop-meta { font-size: 12px; color: var(--text-muted); margin-bottom: 8px; }
        .sop-actions { display: flex; gap: 8px; margin-top: 12px; }
        .empty-state { text-align: center; padding: 60px; color: var(--text-muted); }
    </style>
</head>
<body>
    <?php $current_page = 'admin_sops'; include '../includes/navbar.php'; ?>

    <div class="container">
        <?php showPageToasts(); ?>

        <div class="grid">
            <div class="section">
                <h2><?php echo $edit_sop ? 'Edit SOP' : 'Create New SOP'; ?></h2>
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <?php if ($edit_sop): ?>
                        <input type="hidden" name="sop_id" value="<?php echo $edit_sop['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Department *</label>
                        <select name="department_id" required <?php echo $edit_sop ? 'disabled' : ''; ?>>
                            <option value="">Select Department</option>
                            <?php $departments->data_seek(0); while ($dept = $departments->fetch_assoc()): ?>
                                <option value="<?php echo $dept['id']; ?>" <?php echo ($edit_sop && $edit_sop['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Title *</label>
                        <input type="text" name="title" required value="<?php echo $edit_sop ? htmlspecialchars($edit_sop['title']) : ''; ?>" placeholder="SOP Title">
                    </div>
                    
                    <div class="form-group">
                        <label>Category</label>
                        <input type="text" name="category" value="<?php echo $edit_sop ? htmlspecialchars($edit_sop['category']) : ''; ?>" placeholder="e.g., General, Procedures, Policies">
                    </div>
                    
                    <?php if ($edit_sop): ?>
                    <div class="form-group">
                        <label>Version</label>
                        <input type="text" name="version" value="<?php echo htmlspecialchars($edit_sop['version']); ?>">
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Content * (Markdown supported)</label>
                        <textarea name="content" required placeholder="# Section Title&#10;&#10;Content here..."><?php echo $edit_sop ? htmlspecialchars($edit_sop['content']) : ''; ?></textarea>
                    </div>
                    
                    <?php if ($edit_sop): ?>
                        <button type="submit" name="update_sop" class="btn btn-primary" style="width: 100%;">Update SOP</button>
                        <a href="sops.php" class="btn" style="width: 100%; margin-top: 8px; display: block; text-align: center; background: var(--bg-elevated); text-decoration: none; color: white;">Cancel</a>
                    <?php else: ?>
                        <button type="submit" name="create_sop" class="btn btn-primary" style="width: 100%;">Create SOP</button>
                    <?php endif; ?>
                </form>
            </div>

            <div class="section">
                <h2>All SOPs</h2>
                
                <form method="GET" class="filters">
                    <select name="dept" onchange="this.form.submit()">
                        <option value="">All Departments</option>
                        <?php $departments->data_seek(0); while ($dept = $departments->fetch_assoc()): ?>
                            <option value="<?php echo $dept['id']; ?>" <?php echo $dept_filter == $dept['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['abbreviation']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </form>

                <?php if ($sops->num_rows > 0): ?>
                    <?php while ($sop = $sops->fetch_assoc()): ?>
                        <div class="sop-card <?php echo !$sop['is_active'] ? 'inactive' : ''; ?>">
                            <div class="sop-header">
                                <div class="sop-title"><?php echo htmlspecialchars($sop['title']); ?></div>
                                <span class="badge"><?php echo htmlspecialchars($sop['abbreviation']); ?></span>
                            </div>
                            <div class="sop-meta">
                                <?php if ($sop['category']): ?>Category: <?php echo htmlspecialchars($sop['category']); ?> • <?php endif; ?>
                                Version <?php echo htmlspecialchars($sop['version']); ?> • 
                                By <?php echo htmlspecialchars($sop['author_name']); ?> •
                                <?php 
                                $compliance_pct = $sop['dept_roster_count'] > 0 ? round(($sop['ack_count'] / $sop['dept_roster_count']) * 100) : 0;
                                ?>
                                <span style="color: <?php echo $compliance_pct >= 100 ? '#4ade80' : ($compliance_pct >= 50 ? '#f0b232' : '#f87171'); ?>;">
                                    <?php echo $sop['ack_count']; ?>/<?php echo $sop['dept_roster_count']; ?> acknowledged (<?php echo $compliance_pct; ?>%)
                                </span>
                                <?php if (!$sop['is_active']): ?> • <strong>INACTIVE</strong><?php endif; ?>
                            </div>
                            <div class="sop-actions">
                                <a href="?compliance=<?php echo $sop['id']; ?>&dept=<?php echo urlencode($dept_filter); ?>" class="btn btn-sm" style="background: rgba(102,126,234,0.2); text-decoration: none; color: #93c5fd;">👁 Compliance</a>
                                <a href="?edit=<?php echo $sop['id']; ?>" class="btn btn-sm" style="background: var(--bg-elevated); text-decoration: none; color: white;">Edit</a>
                                <form method="POST" style="display: inline;">
                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="sop_id" value="<?php echo $sop['id']; ?>">
                                    <button type="submit" name="toggle_sop" class="btn btn-sm" style="background: var(--bg-elevated);">
                                        <?php echo $sop['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this SOP?')">
                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="sop_id" value="<?php echo $sop['id']; ?>">
                                    <button type="submit" name="delete_sop" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">No SOPs found</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($compliance_sop && $unacknowledged): ?>
    <div class="modal active" onclick="if(event.target===this) window.location='?dept=<?php echo urlencode($dept_filter); ?>'">
        <div class="modal-content" style="max-width: 550px;">
            <div class="modal-header">
                <h3>SOP Compliance: <?php echo htmlspecialchars($compliance_sop['title']); ?></h3>
                <a href="?dept=<?php echo urlencode($dept_filter); ?>" class="modal-close">&times;</a>
            </div>
            <p style="color: var(--text-muted); margin-bottom: 16px; font-size: 14px;">
                <?php echo htmlspecialchars($compliance_sop['dept_name']); ?> — Members who have <strong style="color: #f87171;">NOT</strong> acknowledged this SOP:
            </p>
            <?php if ($unacknowledged->num_rows > 0): ?>
                <table style="width: 100%; font-size: 14px;">
                    <thead><tr><th>Member</th><th>Rank</th><th>Badge #</th></tr></thead>
                    <tbody>
                    <?php while ($u = $unacknowledged->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($u['username']); ?></td>
                            <td><?php echo htmlspecialchars($u['rank_name']); ?></td>
                            <td><?php echo htmlspecialchars($u['badge_number'] ?? '—'); ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 24px; color: #4ade80;">✅ All active members have acknowledged this SOP!</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>
<?php $conn->close(); ?>
