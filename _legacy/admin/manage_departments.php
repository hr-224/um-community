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
$error = '';
$is_admin = isAdmin();
$can_manage = $is_admin || hasPermission('dept.manage');

// Create uploads directory if needed
$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/departments/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Handle logo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['upload_logo']) && $can_manage) {
    $dept_id = intval($_POST['dept_id']);
    
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'];
        $file_type = $_FILES['logo']['type'];
        $file_size = $_FILES['logo']['size'];
        
        if (!in_array($file_type, $allowed_types)) {
            $error = 'Invalid file type. Please upload PNG, JPG, GIF, WebP, or SVG.';
        } elseif ($file_size > 2 * 1024 * 1024) {
            $error = 'File too large. Maximum size is 2MB.';
        } elseif (!validateUploadedImage($_FILES['logo']['tmp_name'], $file_type)) {
            $error = 'Invalid image file. The file appears to be corrupted or not a real image.';
        } else {
            // Get old logo to delete
            $stmt_old = $conn->prepare("SELECT logo_path FROM departments WHERE id = ?");
            $stmt_old->bind_param("i", $dept_id);
            $stmt_old->execute();
            $old = $stmt_old->get_result()->fetch_assoc();
            $stmt_old->close();
            if ($old && $old['logo_path'] && file_exists($_SERVER['DOCUMENT_ROOT'] . $old['logo_path'])) {
                @unlink($_SERVER['DOCUMENT_ROOT'] . $old['logo_path']);
            }
            
            $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $filename = 'dept_' . $dept_id . '_' . time() . '.' . $ext;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $filepath)) {
                // Sanitize SVG files
                if ($file_type === 'image/svg+xml') {
                    sanitizeSVG($filepath);
                }
                $logo_path = '/uploads/departments/' . $filename;
                $stmt = $conn->prepare("UPDATE departments SET logo_path = ? WHERE id = ?");
                $stmt->bind_param("si", $logo_path, $dept_id);
                $stmt->execute();
                $stmt->close();
                
                logAudit('upload_dept_logo', 'department', $dept_id, 'Uploaded department logo');
                $message = 'Logo uploaded successfully!';
            } else {
                $error = 'Failed to upload logo.';
            }
        }
    } else {
        $error = 'Please select a file to upload.';
    }
}

// Handle logo removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['remove_logo']) && $can_manage) {
    $dept_id = intval($_POST['dept_id']);
    
    $stmt_old = $conn->prepare("SELECT logo_path FROM departments WHERE id = ?");
    $stmt_old->bind_param("i", $dept_id);
    $stmt_old->execute();
    $old = $stmt_old->get_result()->fetch_assoc();
    $stmt_old->close();
    if ($old && $old['logo_path'] && file_exists($_SERVER['DOCUMENT_ROOT'] . $old['logo_path'])) {
        @unlink($_SERVER['DOCUMENT_ROOT'] . $old['logo_path']);
    }
    
    $stmt_rm = $conn->prepare("UPDATE departments SET logo_path = NULL WHERE id = ?");
    $stmt_rm->bind_param("i", $dept_id);
    $stmt_rm->execute();
    $stmt_rm->close();
    logAudit('remove_dept_logo', 'department', $dept_id, 'Removed department logo');
    $message = 'Logo removed!';
}

// Handle department actions (only if can manage)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && $can_manage) {
    if (isset($_POST['add_department'])) {
        $name = trim($_POST['name']);
        $abbr = trim($_POST['abbreviation']);
        $color = trim($_POST['color']);
        $icon = trim($_POST['icon']);
        $desc = trim($_POST['description']);
        
        $stmt = $conn->prepare("INSERT INTO departments (name, abbreviation, color, icon, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $abbr, $color, $icon, $desc);
        $stmt->execute();
        $new_id = $stmt->insert_id;
        $stmt->close();
        
        // Handle logo upload for new department
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $logo_type = $_FILES['logo']['type'];
            $allowed_types = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'];
            if (in_array($logo_type, $allowed_types) && $_FILES['logo']['size'] <= 2 * 1024 * 1024 && validateUploadedImage($_FILES['logo']['tmp_name'], $logo_type)) {
                $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                $filename = 'dept_' . $new_id . '_' . time() . '.' . $ext;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $filepath)) {
                    if ($logo_type === 'image/svg+xml') {
                        sanitizeSVG($filepath);
                    }
                    $logo_path = '/uploads/departments/' . $filename;
                    $stmt_logo = $conn->prepare("UPDATE departments SET logo_path = ? WHERE id = ?");
                    $stmt_logo->bind_param("si", $logo_path, $new_id);
                    $stmt_logo->execute();
                    $stmt_logo->close();
                }
            }
        }
        
        logAudit('create_department', 'department', $new_id, "Created department: $name");
        $message = 'Department created successfully!';
        
    } elseif (isset($_POST['edit_department'])) {
        $id = intval($_POST['dept_id']);
        $name = trim($_POST['name']);
        $abbr = trim($_POST['abbreviation']);
        $color = trim($_POST['color']);
        $icon = trim($_POST['icon']);
        $desc = trim($_POST['description']);
        
        $stmt = $conn->prepare("UPDATE departments SET name = ?, abbreviation = ?, color = ?, icon = ?, description = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $name, $abbr, $color, $icon, $desc, $id);
        $stmt->execute();
        $stmt->close();
        
        logAudit('update_department', 'department', $id, "Updated department: $name");
        $message = 'Department updated successfully!';
        
    } elseif (isset($_POST['delete_department'])) {
        $id = intval($_POST['dept_id']);
        
        // Delete logo file
        $stmt = $conn->prepare("SELECT logo_path FROM departments WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $old = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($old && $old['logo_path'] && file_exists($_SERVER['DOCUMENT_ROOT'] . $old['logo_path'])) {
            @unlink($_SERVER['DOCUMENT_ROOT'] . $old['logo_path']);
        }
        
        $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        logAudit('delete_department', 'department', $id, 'Deleted department');
        $message = 'Department deleted successfully!';
    }
}

$departments = $conn->query("SELECT * FROM departments ORDER BY name");

$current_page = 'admin_depts';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Departments - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <?php include '../includes/styles.php'; ?>
    <style>
        .grid {
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: 24px;
        }
        
        @media (max-width: 1024px) {
            .grid { grid-template-columns: 1fr; }
        }
        
        .dept-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.3s;
        }
        
        .dept-card:hover {
            border-color: rgba(88, 101, 242, 0.3);
            background: var(--bg-hover);
        }
        
        .dept-logo {
            width: 64px;
            height: 64px;
            border-radius: var(--radius-md);
            background: var(--bg-card);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            flex-shrink: 0;
            overflow: hidden;
        }
        
        .dept-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .dept-details {
            flex: 1;
            min-width: 0;
        }
        
        .dept-name {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .dept-meta {
            font-size: 13px;
            color: var(--text-muted);
        }
        
        .dept-actions {
            display: flex;
            gap: 8px;
        }
        
        .color-preview {
            display: inline-block;
            width: 16px;
            height: 16px;
            border-radius: 4px;
            vertical-align: middle;
            margin-right: 4px;
        }
        
        .logo-upload-area {
            border: 2px dashed var(--border);
            border-radius: var(--radius-md);
            padding: 20px;
            text-align: center;
            margin-top: 12px;
        }
        
        .logo-upload-area:hover {
            border-color: var(--accent);
        }
        
        .logo-preview-small {
            width: 60px;
            height: 60px;
            border-radius: var(--radius-sm);
            object-fit: contain;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container">
        <?php if ($message) showToast($message, 'success'); ?>
        
        <?php if ($error) showToast($error, 'error'); ?>

        <div class="grid">
            <div class="section <?php echo !$can_manage ? 'permission-locked' : ''; ?>">
                <h2>➕ Add Department</h2>
                <form method="POST" enctype="multipart/form-data">
                    <?php echo csrfField(); ?>
                    <div class="form-group">
                        <label class="required">Department Name</label>
                        <input type="text" name="name" required placeholder="e.g. Police Department">
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Abbreviation</label>
                        <input type="text" name="abbreviation" required placeholder="e.g. PD" maxlength="10">
                    </div>
                    
                    <div class="form-group">
                        <label>Color</label>
                        <div class="color-input-group" style="display: flex; gap: 12px;">
                            <input type="color" name="color" value="var(--accent)" style="width: 50px; height: 42px;">
                            <input type="text" value="var(--accent)" disabled style="flex: 1;">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Fallback Icon (Emoji)</label>
                        <input type="text" name="icon" placeholder="🚔" maxlength="10">
                        <small>Used if no logo is uploaded</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Department Logo</label>
                        <input type="file" name="logo" accept="image/*">
                        <small>PNG, JPG, GIF, WebP, or SVG. Max 2MB.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="3" placeholder="Brief description of this department"></textarea>
                    </div>
                    
                    <button type="submit" name="add_department" class="btn btn-primary btn-block">Create Department</button>
                </form>
                <?php if (!$can_manage): ?>
                <?php permissionLockOverlay('You need the "Manage Departments" permission to add departments.'); ?>
                <?php endif; ?>
            </div>

            <div class="section">
                <h2>🏢 Departments</h2>
                <?php if ($departments->num_rows > 0): ?>
                    <?php while ($dept = $departments->fetch_assoc()): ?>
                        <?php $has_logo = !empty($dept['logo_path']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $dept['logo_path']); ?>
                        <div class="dept-card">
                            <div class="dept-logo">
                                <?php if ($has_logo): ?>
                                    <img src="<?php echo htmlspecialchars($dept['logo_path']); ?>" alt="">
                                <?php else: ?>
                                    ?
                                <?php endif; ?>
                            </div>
                            <div class="dept-details">
                                <div class="dept-name">
                                    <span class="color-preview" style="background: <?php echo htmlspecialchars($dept['color'] ?: 'var(--accent)'); ?>"></span>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </div>
                                <div class="dept-meta">
                                    <?php echo htmlspecialchars($dept['abbreviation']); ?>
                                    <?php if ($dept['description']): ?> • <?php echo htmlspecialchars(substr($dept['description'], 0, 50)); ?><?php endif; ?>
                                </div>
                            </div>
                            <div class="dept-actions">
                                <?php if ($can_manage): ?>
                                <button class="btn btn-sm btn-secondary" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($dept)); ?>)">Edit</button>
                                <button class="btn btn-sm btn-primary" onclick="openLogoModal(<?php echo $dept['id']; ?>, '<?php echo $has_logo ? htmlspecialchars($dept['logo_path']) : ''; ?>')">Logo</button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this department? This cannot be undone.')">
                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="dept_id" value="<?php echo $dept['id']; ?>">
                                    <button type="submit" name="delete_department" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                                <?php else: ?>
                                <?php lockedButton('Edit', 'Manage Departments permission required'); ?>
                                <?php lockedButton('Logo', 'Manage Departments permission required'); ?>
                                <?php lockedButton('Delete', 'Manage Departments permission required'); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">No departments yet. Create one to get started!</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Department</h3>
                <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form method="POST">
                    <?php echo csrfField(); ?>
                <input type="hidden" name="dept_id" id="edit_dept_id">
                
                <div class="form-group">
                    <label class="required">Department Name</label>
                    <input type="text" name="name" id="edit_name" required>
                </div>
                
                <div class="form-group">
                    <label class="required">Abbreviation</label>
                    <input type="text" name="abbreviation" id="edit_abbr" required maxlength="10">
                </div>
                
                <div class="form-group">
                    <label>Color</label>
                    <input type="color" name="color" id="edit_color" style="width: 100%;">
                </div>
                
                <div class="form-group">
                    <label>Fallback Icon (Emoji)</label>
                    <input type="text" name="icon" id="edit_icon" maxlength="10">
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_desc" rows="3"></textarea>
                </div>
                
                <button type="submit" name="edit_department" class="btn btn-primary btn-block">Save Changes</button>
            </form>
        </div>
    </div>

    <!-- Logo Modal -->
    <div class="modal" id="logoModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Department Logo</h3>
                <button class="modal-close" onclick="closeModal('logoModal')">&times;</button>
            </div>
            
            <div id="currentLogoArea" class="logo-upload-area">
                <img id="currentLogoPreview" src="" alt="" class="logo-preview-small" style="display: none;">
                <p id="noLogoText">No logo uploaded</p>
            </div>
            
            <form method="POST" enctype="multipart/form-data" style="margin-top: 16px;">
                    <?php echo csrfField(); ?>
                <input type="hidden" name="dept_id" id="logo_dept_id">
                
                <div class="form-group">
                    <label>Upload New Logo</label>
                    <input type="file" name="logo" accept="image/*" required>
                    <small>PNG, JPG, GIF, WebP, or SVG. Max 2MB.</small>
                </div>
                
                <div style="display: flex; gap: 8px;">
                    <button type="submit" name="upload_logo" class="btn btn-primary" style="flex: 1;">Upload</button>
                    <button type="submit" name="remove_logo" class="btn btn-danger" id="removeLogoBtn" style="display: none;">Remove</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(dept) {
            document.getElementById('edit_dept_id').value = dept.id;
            document.getElementById('edit_name').value = dept.name;
            document.getElementById('edit_abbr').value = dept.abbreviation;
            document.getElementById('edit_color').value = dept.color || 'var(--accent)';
            document.getElementById('edit_icon').value = dept.icon || '';
            document.getElementById('edit_desc').value = dept.description || '';
            document.getElementById('editModal').classList.add('active');
        }
        
        function openLogoModal(deptId, logoPath) {
            document.getElementById('logo_dept_id').value = deptId;
            
            const preview = document.getElementById('currentLogoPreview');
            const noText = document.getElementById('noLogoText');
            const removeBtn = document.getElementById('removeLogoBtn');
            
            if (logoPath) {
                preview.src = logoPath;
                preview.style.display = 'block';
                noText.style.display = 'none';
                removeBtn.style.display = 'block';
            } else {
                preview.style.display = 'none';
                noText.style.display = 'block';
                removeBtn.style.display = 'none';
            }
            
            document.getElementById('logoModal').classList.add('active');
        }
        
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }
        
        // Close modal on outside click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) closeModal(this.id);
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
