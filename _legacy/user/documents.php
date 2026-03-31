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
requireLogin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Mark as read if viewing/downloading
if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    $doc_id = intval($_GET['read']);
    $stmt = $conn->prepare("INSERT IGNORE INTO read_receipts (content_type, content_id, user_id) VALUES ('document', ?, ?)");
    $stmt->bind_param("ii", $doc_id, $user_id);
    $stmt->execute();
    $stmt->close();
    
    // If download requested, serve the file
    if (isset($_GET['download'])) {
        $stmt = $conn->prepare("SELECT file_path, file_name, file_type FROM documents WHERE id = ?");
        $stmt->bind_param("i", $doc_id);
        $stmt->execute();
        $doc = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($doc && file_exists(__DIR__ . '/..' . $doc['file_path'])) {
            $conn->close();
            header('Content-Type: ' . ($doc['file_type'] ?: 'application/octet-stream'));
            header('Content-Disposition: attachment; filename="' . basename($doc['file_name']) . '"');
            header('Content-Length: ' . filesize(__DIR__ . '/..' . $doc['file_path']));
            readfile(__DIR__ . '/..' . $doc['file_path']);
            exit;
        }
    }
}

// Get user's departments
$stmt = $conn->prepare("SELECT department_id FROM roster WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_depts = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'department_id');
$stmt->close();

// Get categories
$categories = $conn->query("SELECT DISTINCT category FROM documents ORDER BY category")->fetch_all(MYSQLI_ASSOC);
$current_category = $_GET['category'] ?? '';

// Get documents user can access
$sql = "SELECT d.*, u.username as uploaded_by_name, dept.name as department_name,
        (SELECT COUNT(*) FROM read_receipts WHERE content_type = 'document' AND content_id = d.id AND user_id = ?) as is_read
        FROM documents d
        LEFT JOIN users u ON d.uploaded_by = u.id
        LEFT JOIN departments dept ON d.department_id = dept.id
        WHERE d.is_public = 1";

if (!empty($user_depts)) {
    $placeholders = implode(',', array_fill(0, count($user_depts), '?'));
    $sql .= " OR d.department_id IN ($placeholders)";
}

if ($current_category) {
    $sql .= " AND d.category = ?";
}

$sql .= " ORDER BY d.created_at DESC";

$stmt = $conn->prepare($sql);

// Bind parameters
$types = "i";
$params = [$user_id];
foreach ($user_depts as $dept_id) {
    $types .= "i";
    $params[] = $dept_id;
}
if ($current_category) {
    $types .= "s";
    $params[] = $current_category;
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

// File type icons
function getFileIcon($type) {
    $type = strtolower($type);
    if (strpos($type, 'pdf') !== false) return '📕';
    if (strpos($type, 'word') !== false || strpos($type, 'doc') !== false) return '📘';
    if (strpos($type, 'excel') !== false || strpos($type, 'sheet') !== false) return '📗';
    if (strpos($type, 'image') !== false) return '🖼️';
    if (strpos($type, 'video') !== false) return '🎬';
    if (strpos($type, 'audio') !== false) return '🎵';
    if (strpos($type, 'zip') !== false || strpos($type, 'rar') !== false) return '📦';
    return '📄';
}

function formatFileSize($bytes) {
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
    <title>Documents - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
    <style>
        .doc-filters { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 24px; }
        .filter-btn { padding: 8px 16px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-secondary); border-radius: var(--radius-lg); text-decoration: none; font-size: 13px; transition: all 0.2s; }
        .filter-btn:hover, .filter-btn.active { background: var(--accent); color: var(--text-primary); border-color: transparent; }
        .doc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
        .doc-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px; display: flex; gap: 16px; transition: all 0.2s; }
        .doc-card:hover { border-color: var(--accent); transform: translateY(-2px); }
        .doc-card.unread { border-left: 3px solid var(--accent); }
        .doc-icon { font-size: 32px; flex-shrink: 0; }
        .doc-info { flex: 1; min-width: 0; }
        .doc-title { font-weight: 600; color: var(--text-primary); margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .doc-meta { font-size: 12px; color: var(--text-muted); margin-bottom: 8px; }
        .doc-category { display: inline-block; padding: 2px 8px; background: var(--bg-elevated); border-radius: var(--radius-md); font-size: 11px; margin-right: 8px; }
        .doc-description { font-size: 13px; color: var(--text-secondary); margin-bottom: 12px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .doc-actions { display: flex; gap: 8px; }
        .doc-btn { padding: 6px 12px; border-radius: 6px; font-size: 12px; text-decoration: none; transition: all 0.2s; }
        .doc-btn-download { background: var(--accent); color: var(--text-primary); }
        .doc-btn-view { background: var(--bg-elevated); color: var(--text-secondary); }
        @media (max-width: 768px) {
            .doc-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>📁 Document Library</h1>
    </div>
    
    <div class="doc-filters">
        <a href="documents" class="filter-btn <?php echo !$current_category ? 'active' : ''; ?>">All</a>
        <?php foreach ($categories as $cat): ?>
            <a href="?category=<?php echo urlencode($cat['category']); ?>" class="filter-btn <?php echo $current_category === $cat['category'] ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($cat['category']); ?>
            </a>
        <?php endforeach; ?>
    </div>
    
    <?php if (empty($documents)): ?>
        <div class="empty-state">
            <p>No documents available.</p>
        </div>
    <?php else: ?>
    <div class="doc-grid">
        <?php foreach ($documents as $doc): ?>
        <div class="doc-card <?php echo !$doc['is_read'] ? 'unread' : ''; ?>">
            <div class="doc-icon"><?php echo getFileIcon($doc['file_type']); ?></div>
            <div class="doc-info">
                <div class="doc-title"><?php echo htmlspecialchars($doc['title']); ?></div>
                <div class="doc-meta">
                    <span class="doc-category"><?php echo htmlspecialchars($doc['category']); ?></span>
                    <?php echo formatFileSize($doc['file_size']); ?> • <?php echo date('M j, Y', strtotime($doc['created_at'])); ?>
                </div>
                <?php if ($doc['description']): ?>
                    <div class="doc-description"><?php echo htmlspecialchars($doc['description']); ?></div>
                <?php endif; ?>
                <div class="doc-actions">
                    <a href="?read=<?php echo $doc['id']; ?>&download=1" class="doc-btn doc-btn-download" download>Download</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
