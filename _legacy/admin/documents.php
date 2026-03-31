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

// Ensure upload directory exists
$upload_dir = __DIR__ . '/../uploads/documents/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Get departments
$departments = $conn->query("SELECT id, name FROM departments ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'upload' && isset($_FILES['document'])) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? 'General');
        $department_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
        $is_public = isset($_POST['is_public']) ? 1 : 0;
        
        $file = $_FILES['document'];
        if ($file['error'] === UPLOAD_ERR_OK && $title) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'odt', 'ods', 'odp', 'png', 'jpg', 'jpeg', 'gif', 'zip', 'rar'];
            
            if (in_array($ext, $allowed) && $file['size'] <= 10 * 1024 * 1024) {
                $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $web_path = '/uploads/documents/' . $filename;
                    $stmt = $conn->prepare("INSERT INTO documents (title, description, file_path, file_name, file_type, file_size, category, department_id, is_public, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssssissii", $title, $description, $web_path, $file['name'], $file['type'], $file['size'], $category, $department_id, $is_public, $user_id);
                    if ($stmt->execute()) {
                        $message = 'Document uploaded successfully!';
                        logAudit('document_upload', 'document', $stmt->insert_id, "Uploaded: $title");
                    }
                    $stmt->close();
                } else {
                    $error = 'Failed to save file.';
                }
            } else {
                $error = 'Invalid file type or file too large (max 10MB).';
            }
        } else {
            $error = 'Please provide a title and file.';
        }
    } elseif ($action === 'delete') {
        $doc_id = intval($_POST['doc_id'] ?? 0);
        $stmt = $conn->prepare("SELECT file_path FROM documents WHERE id = ?");
        $stmt->bind_param("i", $doc_id);
        $stmt->execute();
        $doc = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($doc) {
            $filepath = __DIR__ . '/..' . $doc['file_path'];
            if (file_exists($filepath)) unlink($filepath);
            
            $stmt = $conn->prepare("DELETE FROM documents WHERE id = ?");
            $stmt->bind_param("i", $doc_id);
            $stmt->execute();
            $stmt->close();
            $message = 'Document deleted.';
            logAudit('document_delete', 'document', $doc_id, 'Deleted document');
        }
    }
}

// Get documents
$documents = $conn->query("
    SELECT d.*, u.username as uploaded_by_name, dept.name as department_name,
           (SELECT COUNT(*) FROM read_receipts WHERE content_type = 'document' AND content_id = d.id) as read_count
    FROM documents d
    LEFT JOIN users u ON d.uploaded_by = u.id
    LEFT JOIN departments dept ON d.department_id = dept.id
    ORDER BY d.created_at DESC
    LIMIT 100
")->fetch_all(MYSQLI_ASSOC);

$conn->close();

function formatBytes($bytes) {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

$theme = getThemeColors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Library - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>📁 Document Library</h1>
        <button class="btn btn-primary" onclick="document.getElementById('uploadModal').style.display='flex'">+ Upload Document</button>
    </div>
    
    <?php if ($message) showToast($message, 'success'); ?>
    <?php if ($error) showToast($error, 'error'); ?>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Size</th>
                    <th>Access</th>
                    <th>Read By</th>
                    <th>Uploaded</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documents as $doc): ?>
                <tr>
                    <td>
                        <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" rel="noopener noreferrer" style="color:var(--accent);">
                            <?php echo htmlspecialchars($doc['title']); ?>
                        </a>
                    </td>
                    <td><?php echo htmlspecialchars($doc['category']); ?></td>
                    <td><?php echo formatBytes($doc['file_size']); ?></td>
                    <td><?php echo $doc['is_public'] ? 'Public' : ($doc['department_name'] ?: 'Private'); ?></td>
                    <td><?php echo $doc['read_count']; ?></td>
                    <td><?php echo date('M j, Y', strtotime($doc['created_at'])); ?></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this document?');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="doc_id" value="<?php echo $doc['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Upload Modal -->
<div id="uploadModal" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Upload Document</h3>
            <button type="button" class="modal-close" onclick="closeUploadModal()">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
            <input type="hidden" name="action" value="upload">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label>File * (Max 10MB)</label>
                <input type="file" name="document" class="form-control" required>
            </div>
            <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category" class="form-control" value="General" placeholder="e.g., Policies, Training, Forms">
                </div>
                <div class="form-group">
                    <label>Department (optional)</label>
                    <select name="department_id" class="form-control">
                        <option value="">— All Departments —</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="is_public" checked> Public (visible to all members)</label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeUploadModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Upload</button>
            </div>
        </form>
    </div>
</div>

<script>
function closeUploadModal() {
    document.getElementById('uploadModal').style.display = 'none';
}
</script>

</body>
</html>
