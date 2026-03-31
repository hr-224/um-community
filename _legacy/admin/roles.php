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
requireLogin();

// Check permission - only admins or users with admin.roles permission
if (!isAdmin() && !hasPermission('admin.roles')) {
    header('Location: /index?error=unauthorized');
    exit();
}

$conn = getDBConnection();
$message = '';
$error = '';
$is_admin = isAdmin();

// Handle role creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['create_role'])) {
    $role_name = trim($_POST['role_name']);
    $role_key = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $role_name));
    $description = trim($_POST['description']);
    $color = $_POST['color'] ?? '#6B7280';
    $dept_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
    $permissions = $_POST['permissions'] ?? [];
    
    $stmt = $conn->prepare("INSERT INTO roles (role_name, role_key, description, color, department_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $role_name, $role_key, $description, $color, $dept_id);
    
    if ($stmt->execute()) {
        $role_id = $stmt->insert_id;
        
        // Assign permissions
        foreach ($permissions as $perm_id) {
            $stmt_rp = $conn->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                $pid = intval($perm_id);
                $stmt_rp->bind_param("ii", $role_id, $pid);
                $stmt_rp->execute();
                $stmt_rp->close();
        }
        
        logAudit('create_role', 'role', $role_id, "Created role: $role_name");
        $message = 'Role created successfully!';
    } else {
        $error = 'Failed to create role.';
    }
    $stmt->close();
}

// Handle role update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['update_role'])) {
    $role_id = intval($_POST['role_id']);
    $role_name = trim($_POST['role_name']);
    $description = trim($_POST['description']);
    $color = $_POST['color'] ?? '#6B7280';
    $dept_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
    $permissions = $_POST['permissions'] ?? [];
    
    $stmt = $conn->prepare("UPDATE roles SET role_name = ?, description = ?, color = ?, department_id = ? WHERE id = ? AND is_system = FALSE");
    $stmt->bind_param("sssii", $role_name, $description, $color, $dept_id, $role_id);
    $stmt->execute();
    $stmt->close();
    
    // Update permissions
    $stmt_drp = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
    $stmt_drp->bind_param("i", $role_id);
    $stmt_drp->execute();
    $stmt_drp->close();
    foreach ($permissions as $perm_id) {
        $stmt_rp = $conn->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        $pid = intval($perm_id);
        $stmt_rp->bind_param("ii", $role_id, $pid);
        $stmt_rp->execute();
        $stmt_rp->close();
    }
    
    logAudit('update_role', 'role', $role_id, "Updated role: $role_name");
    $message = 'Role updated successfully!';
}

// Handle role deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['delete_role'])) {
    $role_id = intval($_POST['role_id']);
    
    // Check if system role
    $stmt_chk = $conn->prepare("SELECT is_system FROM roles WHERE id = ?");
    $stmt_chk->bind_param("i", $role_id);
    $stmt_chk->execute();
    $check = $stmt_chk->get_result()->fetch_assoc();
    $stmt_chk->close();
    if ($check && $check['is_system']) {
        $error = 'Cannot delete system roles.';
    } else {
        $stmt_dr = $conn->prepare("DELETE FROM roles WHERE id = ? AND is_system = FALSE");
        $stmt_dr->bind_param("i", $role_id);
        $stmt_dr->execute();
        $stmt_dr->close();
        logAudit('delete_role', 'role', $role_id, "Deleted role");
        $message = 'Role deleted successfully!';
    }
}

// Handle user role assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['assign_role'])) {
    $user_id = intval($_POST['user_id']);
    $role_id = intval($_POST['role_id']);
    $dept_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
    $expires = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    
    $stmt = $conn->prepare("INSERT INTO user_roles (user_id, role_id, department_id, assigned_by, expires_at) 
                           VALUES (?, ?, ?, ?, ?) 
                           ON DUPLICATE KEY UPDATE assigned_by = ?, assigned_at = NOW(), expires_at = ?");
    $admin_id = $_SESSION['user_id'];
    $stmt->bind_param("iiiisis", $user_id, $role_id, $dept_id, $admin_id, $expires, $admin_id, $expires);
    $stmt->execute();
    $stmt->close();
    
    logAudit('assign_role', 'user', $user_id, "Assigned role #$role_id");
    $message = 'Role assigned successfully!';
}

// Handle user role removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['remove_role'])) {
    $user_id = intval($_POST['user_id']);
    $role_id = intval($_POST['role_id']);
    $dept_id = isset($_POST['department_id']) && $_POST['department_id'] !== '' ? intval($_POST['department_id']) : null;
    
    if ($dept_id !== null) {
        $stmt = $conn->prepare("DELETE FROM user_roles WHERE user_id = ? AND role_id = ? AND department_id = ?");
        $stmt->bind_param("iii", $user_id, $role_id, $dept_id);
    } else {
        $stmt = $conn->prepare("DELETE FROM user_roles WHERE user_id = ? AND role_id = ? AND department_id IS NULL");
        $stmt->bind_param("ii", $user_id, $role_id);
    }
    $stmt->execute();
    $stmt->close();
    
    logAudit('remove_role', 'user', $user_id, "Removed role #$role_id");
    $message = 'Role removed successfully!';
}

// Get all roles
$roles = $conn->query("SELECT r.*, d.name as dept_name, 
                       (SELECT COUNT(*) FROM user_roles WHERE role_id = r.id) as user_count
                       FROM roles r 
                       LEFT JOIN departments d ON r.department_id = d.id
                       ORDER BY r.is_system DESC, r.role_name");

// Get all permissions grouped by category
$permissions = $conn->query("SELECT * FROM permissions ORDER BY category, permission_name");
$perms_by_category = [];
while ($perm = $permissions->fetch_assoc()) {
    $perms_by_category[$perm['category']][] = $perm;
}

// Get departments
$departments = $conn->query("SELECT id, name, abbreviation FROM departments ORDER BY name");

// Get users for assignment
$users = $conn->query("SELECT id, username, email FROM users WHERE is_approved = TRUE ORDER BY username");

// Get current user role assignments
$user_roles = $conn->query("SELECT ur.*, u.username, r.role_name, r.color, d.name as dept_name
                            FROM user_roles ur
                            JOIN users u ON ur.user_id = u.id
                            JOIN roles r ON ur.role_id = r.id
                            LEFT JOIN departments d ON ur.department_id = d.id
                            ORDER BY u.username, r.role_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Roles & Permissions - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <?php include '../includes/styles.php'; ?>
    <style>
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        @media (max-width: 1200px) { .grid { grid-template-columns: 1fr; } }
        
        .role-card {
            background: var(--bg-elevated);
            border: 1px solid var(--bg-elevated);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 12px;
            transition: all 0.3s;
        }
        .role-card:hover { background: var(--bg-card); }
        .role-card.system { border-left: 3px solid var(--accent); }
        
        .role-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .role-name { font-weight: 700; font-size: 16px; display: flex; align-items: center; gap: 8px; }
        .role-badge { padding: 4px 10px; border-radius: var(--radius-lg); font-size: 11px; font-weight: 600; }
        .role-meta { font-size: 12px; color: var(--text-muted); margin-bottom: 12px; }
        .role-perms { display: flex; flex-wrap: wrap; gap: 6px; }
        .perm-tag { background: var(--bg-elevated); padding: 4px 8px; border-radius: 6px; font-size: 11px; }
        
        .perm-category { margin-bottom: 20px; }
        .perm-category h4 { text-transform: capitalize; margin-bottom: 10px; color: var(--accent); font-size: 14px; }
        .perm-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 8px; }
        .perm-item { display: flex; align-items: center; gap: 8px; font-size: 13px; }
        .perm-item input { width: 16px; height: 16px; }
        
        .user-role-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: var(--bg-elevated);
            border-radius: var(--radius-md);
            margin-bottom: 8px;
        }
        .user-role-info { display: flex; align-items: center; gap: 12px; }
        
        .btn { padding: 10px 20px; border: none; border-radius: var(--radius-md); cursor: pointer; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: var(--accent); color: white; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn-danger { background: linear-gradient(135deg, var(--danger), #dc2626); color: white; }
        
        
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 10px 14px; border: 2px solid var(--bg-elevated);
            border-radius: var(--radius-md); background: var(--bg-card); color: var(--text-primary); font-size: 14px;
        }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--accent); }
        
        .message { background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2)); border: 1px solid rgba(16, 185, 129, 0.3); color: #4ade80; padding: 16px; border-radius: var(--radius-md); margin-bottom: 20px; }
        .error { background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(220, 38, 38, 0.2)); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; padding: 16px; border-radius: var(--radius-md); margin-bottom: 20px; }
        
        .tabs { display: flex; gap: 8px; margin-bottom: 24px; }
        .tab { padding: 12px 24px; border-radius: var(--radius-md); background: var(--bg-card); color: var(--text-secondary); cursor: pointer; font-weight: 600; transition: all 0.3s; }
        .tab:hover { background: var(--bg-elevated); }
        .tab.active { background: var(--accent); color: white; }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>
    <?php $current_page = 'admin_roles'; include '../includes/navbar.php'; ?>
    
    <div class="container">
        <?php showPageToasts(); ?>
        
        <div class="tabs">
            <div class="tab active" onclick="showTab('roles')">🛡️ Roles</div>
            <div class="tab" onclick="showTab('assignments')">👥 User Assignments</div>
            <div class="tab" onclick="showTab('permissions')">🔑 Permissions</div>
        </div>
        
        <!-- Roles Tab -->
        <div class="tab-content active" id="tab-roles">
            <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h2>Manage Roles</h2>
                <button class="btn btn-primary" onclick="openCreateRoleModal()">+ Create Role</button>
            </div>
            
            <div class="grid">
                <?php 
                $roles->data_seek(0);
                while ($role = $roles->fetch_assoc()): 
                    // Get permissions for this role
                    $stmt_rp2 = $conn->prepare("SELECT p.permission_key, p.permission_name FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.id WHERE rp.role_id = ?");
                    $stmt_rp2->bind_param("i", $role['id']);
                    $stmt_rp2->execute();
                    $role_perms = $stmt_rp2->get_result();
                ?>
                <div class="role-card <?php echo $role['is_system'] ? 'system' : ''; ?>">
                    <div class="role-header">
                        <div class="role-name">
                            <span class="role-badge" style="background: <?php echo $role['color']; ?>20; color: <?php echo $role['color']; ?>;">
                                <?php echo htmlspecialchars($role['role_name']); ?>
                            </span>
                            <?php if ($role['is_system']): ?><span style="font-size: 11px; color: var(--text-muted);">(System)</span><?php endif; ?>
                        </div>
                        <?php if (!$role['is_system']): ?>
                        <div style="display: flex; gap: 8px;">
                            <button class="btn btn-sm" style="background: var(--bg-elevated);" onclick="editRole(<?php echo htmlspecialchars(json_encode($role)); ?>)">Edit</button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this role?')">
                    <?php echo csrfField(); ?>
                                <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
                                <button type="submit" name="delete_role" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="role-meta">
                        <?php echo htmlspecialchars($role['description'] ?? 'No description'); ?>
                        <?php if ($role['dept_name']): ?><br>Department: <?php echo htmlspecialchars($role['dept_name']); ?><?php endif; ?>
                        <br><?php echo $role['user_count']; ?> user(s) assigned
                    </div>
                    <div class="role-perms">
                        <?php while ($perm = $role_perms->fetch_assoc()): ?>
                        <span class="perm-tag"><?php echo htmlspecialchars($perm['permission_name']); ?></span>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        
        <!-- User Assignments Tab -->
        <div class="tab-content" id="tab-assignments">
            <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h2>User Role Assignments</h2>
                <button class="btn btn-primary" onclick="openAssignModal()">+ Assign Role</button>
            </div>
            
            <div class="section">
                <?php if ($user_roles->num_rows > 0): ?>
                    <?php while ($ur = $user_roles->fetch_assoc()): ?>
                    <div class="user-role-row">
                        <div class="user-role-info">
                            <strong><?php echo htmlspecialchars($ur['username']); ?></strong>
                            <span class="role-badge" style="background: <?php echo $ur['color']; ?>20; color: <?php echo $ur['color']; ?>;">
                                <?php echo htmlspecialchars($ur['role_name']); ?>
                            </span>
                            <?php if ($ur['dept_name']): ?>
                            <span style="font-size: 12px; color: var(--text-muted);">(<?php echo htmlspecialchars($ur['dept_name']); ?>)</span>
                            <?php endif; ?>
                            <?php if ($ur['expires_at']): ?>
                            <span style="font-size: 11px; color: #f0b232;">Expires: <?php echo date('M j, Y', strtotime($ur['expires_at'])); ?></span>
                            <?php endif; ?>
                        </div>
                        <form method="POST" onsubmit="return confirm('Remove this role from user?')">
                    <?php echo csrfField(); ?>
                            <input type="hidden" name="user_id" value="<?php echo $ur['user_id']; ?>">
                            <input type="hidden" name="role_id" value="<?php echo $ur['role_id']; ?>">
                            <input type="hidden" name="department_id" value="<?php echo $ur['department_id'] ?? ''; ?>">
                            <button type="submit" name="remove_role" class="btn btn-sm btn-danger">Remove</button>
                        </form>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-muted);">No role assignments yet</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Permissions Tab -->
        <div class="tab-content" id="tab-permissions">
            <h2 style="margin-bottom: 24px;">Available Permissions</h2>
            <div class="section">
                <?php foreach ($perms_by_category as $category => $perms): ?>
                <div class="perm-category">
                    <h4><?php echo htmlspecialchars($category); ?></h4>
                    <div class="perm-list">
                        <?php foreach ($perms as $perm): ?>
                        <div class="perm-item" title="<?php echo htmlspecialchars($perm['description']); ?>">
                            <span style="color: var(--accent);">🔑</span>
                            <span><?php echo htmlspecialchars($perm['permission_name']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Create/Edit Role Modal -->
    <div class="modal" id="roleModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="roleModalTitle">Create Role</h3>
                <button class="modal-close" onclick="closeRoleModal()">&times;</button>
            </div>
            <form method="POST" id="roleForm">
                    <?php echo csrfField(); ?>
                <input type="hidden" name="role_id" id="role_id">
                <div class="form-group">
                    <label>Role Name *</label>
                    <input type="text" name="role_name" id="role_name" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="role_description" rows="2"></textarea>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>Color</label>
                        <input type="color" name="color" id="role_color" value="#6B7280" style="height: 42px;">
                    </div>
                    <div class="form-group">
                        <label>Department (optional)</label>
                        <select name="department_id" id="role_department">
                            <option value="">All Departments</option>
                            <?php 
                            $departments->data_seek(0);
                            while ($dept = $departments->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Permissions</label>
                    <?php foreach ($perms_by_category as $category => $perms): ?>
                    <div style="margin-bottom: 12px;">
                        <strong style="text-transform: capitalize; font-size: 12px; color: var(--text-muted);"><?php echo $category; ?></strong>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-top: 6px;">
                            <?php foreach ($perms as $perm): ?>
                            <label class="perm-item" style="cursor: pointer;">
                                <input type="checkbox" name="permissions[]" value="<?php echo $perm['id']; ?>" class="perm-checkbox" data-id="<?php echo $perm['id']; ?>">
                                <?php echo htmlspecialchars($perm['permission_name']); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" name="create_role" id="roleSubmitBtn" class="btn btn-primary" style="width: 100%;">Create Role</button>
            </form>
        </div>
    </div>
    
    <!-- Assign Role Modal -->
    <div class="modal" id="assignModal">
        <div class="modal-content" style="max-width: 450px;">
            <div class="modal-header">
                <h3>Assign Role to User</h3>
                <button class="modal-close" onclick="closeAssignModal()">&times;</button>
            </div>
            <form method="POST">
                    <?php echo csrfField(); ?>
                <div class="form-group">
                    <label>User *</label>
                    <select name="user_id" required>
                        <option value="">Select User</option>
                        <?php 
                        $users->data_seek(0);
                        while ($user = $users->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['email']); ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Role *</label>
                    <select name="role_id" required>
                        <option value="">Select Role</option>
                        <?php 
                        $roles->data_seek(0);
                        while ($role = $roles->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Department Scope (optional)</label>
                    <select name="department_id">
                        <option value="">All Departments</option>
                        <?php 
                        $departments->data_seek(0);
                        while ($dept = $departments->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Expires (optional)</label>
                    <input type="date" name="expires_at">
                </div>
                <button type="submit" name="assign_role" class="btn btn-primary" style="width: 100%;">Assign Role</button>
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
        
        function openCreateRoleModal() {
            document.getElementById('roleModalTitle').textContent = 'Create Role';
            document.getElementById('roleForm').reset();
            document.getElementById('role_id').value = '';
            document.getElementById('roleSubmitBtn').name = 'create_role';
            document.getElementById('roleSubmitBtn').textContent = 'Create Role';
            document.querySelectorAll('.perm-checkbox').forEach(c => c.checked = false);
            document.getElementById('roleModal').classList.add('active');
        }
        
        function editRole(role) {
            document.getElementById('roleModalTitle').textContent = 'Edit Role';
            document.getElementById('role_id').value = role.id;
            document.getElementById('role_name').value = role.role_name;
            document.getElementById('role_description').value = role.description || '';
            document.getElementById('role_color').value = role.color || '#6B7280';
            document.getElementById('role_department').value = role.department_id || '';
            document.getElementById('roleSubmitBtn').name = 'update_role';
            document.getElementById('roleSubmitBtn').textContent = 'Update Role';
            
            // Load role permissions via AJAX would be better, but for now reset
            document.querySelectorAll('.perm-checkbox').forEach(c => c.checked = false);
            
            document.getElementById('roleModal').classList.add('active');
        }
        
        function closeRoleModal() {
            document.getElementById('roleModal').classList.remove('active');
        }
        
        function openAssignModal() {
            document.getElementById('assignModal').classList.add('active');
        }
        
        function closeAssignModal() {
            document.getElementById('assignModal').classList.remove('active');
        }
        
        document.getElementById('roleModal').addEventListener('click', function(e) {
            if (e.target === this) closeRoleModal();
        });
        
        document.getElementById('assignModal').addEventListener('click', function(e) {
            if (e.target === this) closeAssignModal();
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
