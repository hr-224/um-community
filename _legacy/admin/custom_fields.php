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

// Create field
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['create_field'])) {
    $field_name = preg_replace('/[^a-z0-9_]/', '', strtolower(str_replace(' ', '_', trim($_POST['field_name']))));
    $field_label = trim($_POST['field_label']);
    $field_type = $_POST['field_type'];
    $applies_to = $_POST['applies_to'];
    $dept_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
    $is_required = isset($_POST['is_required']) ? 1 : 0;
    $options = !empty($_POST['field_options']) ? json_encode(array_filter(array_map('trim', explode("\n", $_POST['field_options'])))) : null;
    
    $stmt = $conn->prepare("INSERT INTO custom_fields (field_name, field_label, field_type, field_options, applies_to, department_id, is_required) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssii", $field_name, $field_label, $field_type, $options, $applies_to, $dept_id, $is_required);
    $stmt->execute();
    
    logAudit('create_custom_field', 'field', $stmt->insert_id, "Created custom field: $field_label");
    $message = 'Custom field created!';
    $stmt->close();
}

// Update field
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['update_field'])) {
    $field_id = intval($_POST['field_id']);
    $field_label = trim($_POST['field_label']);
    $is_required = isset($_POST['is_required']) ? 1 : 0;
    $options = !empty($_POST['field_options']) ? json_encode(array_filter(array_map('trim', explode("\n", $_POST['field_options'])))) : null;
    
    $stmt = $conn->prepare("UPDATE custom_fields SET field_label = ?, field_options = ?, is_required = ? WHERE id = ?");
    $stmt->bind_param("ssii", $field_label, $options, $is_required, $field_id);
    $stmt->execute();
    
    $message = 'Field updated!';
    $stmt->close();
}

// Toggle field
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['toggle_field'])) {
    $field_id = intval($_POST['field_id']);
    $stmt = $conn->prepare("UPDATE custom_fields SET is_active = NOT is_active WHERE id = ?");
    $stmt->bind_param("i", $field_id);
    $stmt->execute();
    $stmt->close();
    $message = 'Field status updated!';
}

// Delete field
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['delete_field'])) {
    $field_id = intval($_POST['field_id']);
    $stmt = $conn->prepare("DELETE FROM custom_field_values WHERE field_id = ?");
    $stmt->bind_param("i", $field_id);
    $stmt->execute();
    $stmt->close();
    $stmt = $conn->prepare("DELETE FROM custom_fields WHERE id = ?");
    $stmt->bind_param("i", $field_id);
    $stmt->execute();
    $stmt->close();
    logAudit('delete_custom_field', 'field', $field_id, 'Deleted custom field');
    $message = 'Field deleted!';
}

// Reorder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['reorder'])) {
    $orders = $_POST['orders'] ?? [];
    foreach ($orders as $id => $order) {
        $stmt_ord = $conn->prepare("UPDATE custom_fields SET display_order = ? WHERE id = ?");
            $o_val = intval($order); $id_val = intval($id);
            $stmt_ord->bind_param("ii", $o_val, $id_val);
            $stmt_ord->execute();
            $stmt_ord->close();
    }
    $message = 'Order updated!';
}

// Get fields
$fields = $conn->query("
    SELECT f.*, d.name as dept_name, d.abbreviation
    FROM custom_fields f
    LEFT JOIN departments d ON f.department_id = d.id
    ORDER BY f.applies_to, f.department_id, f.display_order, f.field_label
");

$departments = $conn->query("SELECT id, name, abbreviation FROM departments ORDER BY name");

// Get field for editing
$edit_field = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM custom_fields WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_field = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custom Fields - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <?php include '../includes/styles.php'; ?>
    <style>
        .container { max-width: 1200px; }
        .message { background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2)); border: 1px solid rgba(16, 185, 129, 0.3); color: #4ade80; padding: 16px 24px; border-radius: var(--radius-md); margin-bottom: 24px; }
        .grid { display: grid; grid-template-columns: 380px 1fr; gap: 24px; }
        @media (max-width: 1024px) { .grid { grid-template-columns: 1fr; } }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .checkbox-label { display: flex; align-items: center; gap: 10px; cursor: pointer; }
        .checkbox-label input { width: auto; }
        .field-card { background: var(--bg-elevated); border: 1px solid var(--bg-elevated); border-radius: var(--radius-md); padding: 16px 20px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; }
        .field-card.inactive { opacity: 0.5; }
        .field-info { flex: 1; }
        .field-name { font-weight: 700; font-size: 15px; margin-bottom: 4px; }
        .field-meta { font-size: 12px; color: var(--text-muted); }
        .field-actions { display: flex; gap: 8px; }
        .badge-user { background: rgba(59, 130, 246, 0.2); color: #93c5fd; }
        .badge-roster { background: rgba(16, 185, 129, 0.2); color: #4ade80; }
        .badge-both { background: rgba(168, 85, 247, 0.2); color: #d8b4fe; }
        .badge-required { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .section-divider { font-size: 12px; color: var(--text-faint); text-transform: uppercase; letter-spacing: 1px; margin: 20px 0 12px; padding-bottom: 8px; border-bottom: 1px solid var(--bg-elevated); }
        .empty-state { text-align: center; padding: 40px; color: var(--text-muted); }
        #options_group { display: none; }
    </style>
</head>
<body>
    <?php $current_page = 'admin_fields'; include '../includes/navbar.php'; ?>

    <div class="container">
        <?php showPageToasts(); ?>

        <div class="grid">
            <div class="section">
                <h2><?php echo $edit_field ? 'Edit Field' : 'Create Custom Field'; ?></h2>
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <?php if ($edit_field): ?>
                        <input type="hidden" name="field_id" value="<?php echo $edit_field['id']; ?>">
                    <?php endif; ?>
                    
                    <?php if (!$edit_field): ?>
                    <div class="form-group">
                        <label>Field Name (internal)</label>
                        <input type="text" name="field_name" required placeholder="e.g., employee_id" pattern="[a-zA-Z0-9_\s]+">
                        <small>Only letters, numbers, underscores. Will be converted to lowercase.</small>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Display Label</label>
                        <input type="text" name="field_label" required value="<?php echo $edit_field ? htmlspecialchars($edit_field['field_label']) : ''; ?>" placeholder="e.g., Employee ID">
                    </div>
                    
                    <?php if (!$edit_field): ?>
                    <div class="form-group">
                        <label>Field Type</label>
                        <select name="field_type" onchange="toggleOptions(this.value)">
                            <option value="text">Text</option>
                            <option value="textarea">Text Area</option>
                            <option value="number">Number</option>
                            <option value="date">Date</option>
                            <option value="select">Dropdown</option>
                            <option value="checkbox">Checkbox</option>
                            <option value="url">URL</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Applies To</label>
                        <select name="applies_to">
                            <option value="both">Both User & Roster Profiles</option>
                            <option value="user">User Profiles Only</option>
                            <option value="roster">Roster Profiles Only</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Department (optional)</label>
                        <select name="department_id">
                            <option value="">All Departments</option>
                            <?php while ($dept = $departments->fetch_assoc()): ?>
                                <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <small>Leave empty to apply to all departments</small>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group" id="options_group" <?php echo ($edit_field && $edit_field['field_type'] === 'select') ? 'style="display:block"' : ''; ?>>
                        <label>Options (one per line)</label>
                        <textarea name="field_options" placeholder="Option 1&#10;Option 2&#10;Option 3"><?php 
                            if ($edit_field && $edit_field['field_options']) {
                                echo htmlspecialchars(implode("\n", json_decode($edit_field['field_options'], true)));
                            }
                        ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_required" <?php echo ($edit_field && $edit_field['is_required']) ? 'checked' : ''; ?>> Required field
                        </label>
                    </div>
                    
                    <?php if ($edit_field): ?>
                        <button type="submit" name="update_field" class="btn btn-primary" style="width: 100%;">Update Field</button>
                        <a href="custom_fields.php" class="btn" style="width: 100%; margin-top: 8px; display: block; text-align: center; background: var(--bg-elevated); text-decoration: none; color: white;">Cancel</a>
                    <?php else: ?>
                        <button type="submit" name="create_field" class="btn btn-primary" style="width: 100%;">Create Field</button>
                    <?php endif; ?>
                </form>
            </div>

            <div class="section">
                <h2>Custom Fields</h2>
                
                <?php 
                $current_section = '';
                if ($fields->num_rows > 0): 
                    while ($field = $fields->fetch_assoc()): 
                        $section = $field['applies_to'] . ($field['department_id'] ? '_' . $field['department_id'] : '_all');
                        if ($section !== $current_section):
                            $current_section = $section;
                            $section_label = ucfirst($field['applies_to']) . ' Fields';
                            if ($field['dept_name']) $section_label .= ' - ' . $field['abbreviation'];
                            elseif ($field['applies_to'] !== 'both') $section_label .= ' - All Depts';
                ?>
                    <div class="section-divider"><?php echo $section_label; ?></div>
                <?php endif; ?>
                        
                        <div class="field-card <?php echo !$field['is_active'] ? 'inactive' : ''; ?>">
                            <div class="field-info">
                                <div class="field-name">
                                    <?php echo htmlspecialchars($field['field_label']); ?>
                                    <?php if ($field['is_required']): ?><span class="badge badge-required">Required</span><?php endif; ?>
                                </div>
                                <div class="field-meta">
                                    <code><?php echo htmlspecialchars($field['field_name']); ?></code> • 
                                    <?php echo ucfirst($field['field_type']); ?>
                                    <?php if (!$field['is_active']): ?> • INACTIVE<?php endif; ?>
                                </div>
                            </div>
                            <div class="field-actions">
                                <a href="?edit=<?php echo $field['id']; ?>" class="btn btn-sm" style="background: var(--bg-elevated); text-decoration: none; color: white;">Edit</a>
                                <form method="POST" style="display: inline;">
                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="field_id" value="<?php echo $field['id']; ?>">
                                    <button type="submit" name="toggle_field" class="btn btn-sm" style="background: var(--bg-elevated);">
                                        <?php echo $field['is_active'] ? 'Disable' : 'Enable'; ?>
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this field and all its data?')">
                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="field_id" value="<?php echo $field['id']; ?>">
                                    <button type="submit" name="delete_field" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">No custom fields yet</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleOptions(type) {
            document.getElementById('options_group').style.display = type === 'select' ? 'block' : 'none';
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>
