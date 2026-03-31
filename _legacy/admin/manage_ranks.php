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
if (!isAdmin() && !hasAnyPermission(['dept.view', 'dept.manage'])) {
    header('Location: /index?error=unauthorized');
    exit();
}

$conn = getDBConnection();
$message = '';
$is_admin = isAdmin();
$can_manage = $is_admin || hasPermission('dept.manage');

// Handle rank actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && $can_manage) {
    if (isset($_POST['add_rank'])) {
        $dept_id = intval($_POST['department_id']);
        $rank_name = trim($_POST['rank_name']);
        $rank_order = intval($_POST['rank_order']);
        
        $stmt = $conn->prepare("INSERT INTO ranks (department_id, rank_name, rank_order) VALUES (?, ?, ?)");
        $stmt->bind_param("isi", $dept_id, $rank_name, $rank_order);
        $stmt->execute();
        
        logAudit('create_rank', 'rank', $stmt->insert_id, "Created rank: $rank_name");
        $message = 'Rank created successfully!';
        $stmt->close();
        
    } elseif (isset($_POST['edit_rank'])) {
        $id = intval($_POST['rank_id']);
        $rank_name = trim($_POST['rank_name']);
        $rank_order = intval($_POST['rank_order']);
        
        $stmt = $conn->prepare("UPDATE ranks SET rank_name = ?, rank_order = ? WHERE id = ?");
        $stmt->bind_param("sii", $rank_name, $rank_order, $id);
        $stmt->execute();
        
        logAudit('update_rank', 'rank', $id, "Updated rank: $rank_name");
        $message = 'Rank updated successfully!';
        $stmt->close();
        
    } elseif (isset($_POST['delete_rank'])) {
        $id = intval($_POST['rank_id']);
        $stmt = $conn->prepare("DELETE FROM ranks WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        logAudit('delete_rank', 'rank', $id, 'Deleted rank');
        $message = 'Rank deleted successfully!';
        
    } elseif (isset($_POST['move_rank'])) {
        $id = intval($_POST['rank_id']);
        $direction = $_POST['direction'];
        $dept_id = intval($_POST['department_id']);
        
        // Get current rank
        $stmt = $conn->prepare("SELECT * FROM ranks WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $current = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($current) {
            $current_order = $current['rank_order'];
            
            if ($direction === 'up' && $current_order > 1) {
                // Swap with the rank above
                $swap_order = $current_order - 1;
                $stmt = $conn->prepare("UPDATE ranks SET rank_order = ? WHERE department_id = ? AND rank_order = ?");
                $stmt->bind_param("iii", $current_order, $dept_id, $swap_order);
                $stmt->execute();
                $stmt->close();
                $stmt = $conn->prepare("UPDATE ranks SET rank_order = ? WHERE id = ?");
                $stmt->bind_param("ii", $swap_order, $id);
                $stmt->execute();
                $stmt->close();
                $message = 'Rank moved up!';
            } elseif ($direction === 'down') {
                // Swap with the rank below
                $stmt = $conn->prepare("SELECT MAX(rank_order) as m FROM ranks WHERE department_id = ?");
                $stmt->bind_param("i", $dept_id);
                $stmt->execute();
                $max_row = $stmt->get_result()->fetch_assoc();
                $max = $max_row['m'] ?? 0;
                $stmt->close();
                if ($current_order < $max) {
                    $swap_order = $current_order + 1;
                    $stmt = $conn->prepare("UPDATE ranks SET rank_order = ? WHERE department_id = ? AND rank_order = ?");
                    $stmt->bind_param("iii", $current_order, $dept_id, $swap_order);
                    $stmt->execute();
                    $stmt->close();
                    $stmt = $conn->prepare("UPDATE ranks SET rank_order = ? WHERE id = ?");
                    $stmt->bind_param("ii", $swap_order, $id);
                    $stmt->execute();
                    $stmt->close();
                    $message = 'Rank moved down!';
                }
            }
        }
    }
}

$departments = $conn->query("SELECT * FROM departments ORDER BY name");
$selected_dept = isset($_GET['dept']) ? intval($_GET['dept']) : 0;

if ($selected_dept > 0) {
    $stmt = $conn->prepare("SELECT * FROM ranks WHERE department_id = ? ORDER BY rank_order");
    $stmt->bind_param("i", $selected_dept);
    $stmt->execute();
    $ranks = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Ranks - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <?php include '../includes/styles.php'; ?>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .container {
            max-width: 1400px;
        }
        
        .message {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(5, 150, 105, 0.2) 100%);
            
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #d1fae5;
            padding: 18px 24px;
            border-radius: var(--radius-lg);
            margin-bottom: 24px;
            animation: slideIn 0.5s ease;
            box-shadow: 0 8px 32px rgba(16, 185, 129, 0.2);
        }
        
        .section {
            background: var(--bg-card);
            
            border: 1px solid var(--bg-elevated);
            padding: 36px;
            border-radius: var(--radius-lg);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            margin-bottom: 30px;
            animation: fadeIn 0.6s ease;
        }
        
        .section h2 {
            color: var(--text-primary);
            margin-bottom: 28px;
            padding-bottom: 16px;
            border-bottom: 2px solid rgba(88, 101, 242, 0.3);
            font-size: 24px;
            font-weight: 700;
        }
        
        .dept-selector {
            margin-bottom: 24px;
        }
        
        .dept-selector label {
            display: block;
            margin-bottom: 10px;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 14px;
        }
        
        .dept-selector select {
            padding: 14px 18px;
            border: 2px solid var(--bg-elevated);
            border-radius: var(--radius-md);
            font-size: 15px;
            min-width: 350px;
            background: var(--bg-card);
            color: var(--text-primary);
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .dept-selector select option {
            background: #1a1a2e;
            color: var(--text-primary);
            padding: 10px;
        }
        
        .dept-selector select:focus {
            outline: none;
            border-color: rgba(88, 101, 242, 0.5);
            background: var(--bg-elevated);
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn:hover { transform: translateY(-2px); }
        
        .btn-primary { 
            background: var(--accent);
            color: white;
            box-shadow: 0 4px 15px var(--shadow-color, rgba(88, 101, 242, 0.4));
        }
        
        .btn-edit { 
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 8px 16px;
            margin-right: 6px;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
        }
        
        .btn-delete { 
            background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
            color: white;
            padding: 8px 16px;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }
        
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
            margin-top: 20px;
        }
        
        th {
            background: var(--bg-elevated);
            padding: 16px;
            text-align: left;
            font-weight: 700;
            color: var(--text-primary);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        th:first-child { border-radius: var(--radius-md) 0 0 12px; }
        th:last-child { border-radius: 0 12px 12px 0; }
        
        td {
            padding: 16px;
            background: var(--bg-elevated);
            border-top: 1px solid var(--bg-card);
            border-bottom: 1px solid var(--bg-card);
        }
        
        td:first-child {
            border-left: 1px solid var(--bg-card);
            border-radius: var(--radius-md) 0 0 12px;
        }
        
        td:last-child {
            border-right: 1px solid var(--bg-card);
            border-radius: 0 12px 12px 0;
        }
        
        tr:hover td { background: var(--accent-muted); }
        
        .close {
            font-size: 32px;
            font-weight: bold;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.3s ease;
            line-height: 1;
        }
        
        .close:hover { 
            color: var(--text-primary);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        
        @media (max-width: 768px) {
            .dept-selector select { min-width: 100%; }
            .container { padding: 0 16px; }
            .section { padding: 24px; }
        }
    </style>
</head>
<body>
    <?php $current_page = 'admin_ranks'; include '../includes/navbar.php'; ?>

    <div class="container">
        <?php showPageToasts(); ?>

        <div class="section">
            <h2>Ranks by Department</h2>
            
            <div class="dept-selector">
                <label>Select Department:</label>
                <select onchange="window.location.href='manage_ranks?dept=' + this.value">
                    <option value="0">-- Select a Department --</option>
                    <?php
                    $departments->data_seek(0);
                    while ($dept = $departments->fetch_assoc()):
                    ?>
                        <option value="<?php echo $dept['id']; ?>" <?php echo $selected_dept == $dept['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <?php if ($selected_dept > 0): ?>
                <?php if ($can_manage): ?>
                <button class="btn btn-primary" onclick="openAddModal()">+ Add Rank</button>
                <?php else: ?>
                <?php lockedButton('+ Add Rank', 'Manage Departments permission required'); ?>
                <?php endif; ?>
                
                <?php if ($ranks->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Rank Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($rank = $ranks->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $rank['rank_order']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($rank['rank_name']); ?></strong></td>
                                    <td>
                                        <?php if ($can_manage): ?>
                                        <form method="POST" style="display: inline;">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="rank_id" value="<?php echo $rank['id']; ?>">
                                            <input type="hidden" name="department_id" value="<?php echo $selected_dept; ?>">
                                            <input type="hidden" name="direction" value="up">
                                            <button type="submit" name="move_rank" class="btn btn-sm" title="Move Up" style="padding: 4px 8px; background: var(--bg-elevated); border: 1px solid var(--border); color: var(--text-primary); border-radius: 6px; cursor: pointer;">▲</button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="rank_id" value="<?php echo $rank['id']; ?>">
                                            <input type="hidden" name="department_id" value="<?php echo $selected_dept; ?>">
                                            <input type="hidden" name="direction" value="down">
                                            <button type="submit" name="move_rank" class="btn btn-sm" title="Move Down" style="padding: 4px 8px; background: var(--bg-elevated); border: 1px solid var(--border); color: var(--text-primary); border-radius: 6px; cursor: pointer;">▼</button>
                                        </form>
                                        <button class="btn btn-edit" onclick='openEditModal(<?php echo json_encode($rank); ?>)'>Edit</button>
                                        <form method="POST" style="display: inline;">
                    <?php echo csrfField(); ?>
                                            <input type="hidden" name="rank_id" value="<?php echo $rank['id']; ?>">
                                            <button type="submit" name="delete_rank" class="btn btn-delete" onclick="return confirm('Delete this rank?')">Delete</button>
                                        </form>
                                        <?php else: ?>
                                        <?php lockedActions('Manage Departments permission required'); ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">No ranks for this department. Add one to get started!</div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Rank</h3>
                <span class="close" onclick="closeAddModal()">&times;</span>
            </div>
            <form method="POST">
                    <?php echo csrfField(); ?>
                <input type="hidden" name="department_id" value="<?php echo $selected_dept; ?>">
                <div class="form-group">
                    <label>Rank Name *</label>
                    <input type="text" name="rank_name" required>
                </div>
                <div class="form-group">
                    <label>Rank Order * (1 = highest)</label>
                    <input type="number" name="rank_order" min="1" required>
                </div>
                <button type="submit" name="add_rank" class="btn btn-primary" style="width: 100%;">Add Rank</button>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Rank</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST">
                    <?php echo csrfField(); ?>
                <input type="hidden" name="rank_id" id="edit_rank_id">
                <div class="form-group">
                    <label>Rank Name *</label>
                    <input type="text" name="rank_name" id="edit_rank_name" required>
                </div>
                <div class="form-group">
                    <label>Rank Order * (1 = highest)</label>
                    <input type="number" name="rank_order" id="edit_rank_order" min="1" required>
                </div>
                <button type="submit" name="edit_rank" class="btn btn-primary" style="width: 100%;">Update Rank</button>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            openModal('addModal');
        }
        function closeAddModal() {
            closeModal('addModal');
        }
        function openEditModal(rank) {
            document.getElementById('edit_rank_id').value = rank.id;
            document.getElementById('edit_rank_name').value = rank.rank_name;
            document.getElementById('edit_rank_order').value = rank.rank_order;
            openModal('editModal');
        }
        function closeEditModal() {
            closeModal('editModal');
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>