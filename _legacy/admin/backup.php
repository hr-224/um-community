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

$message = '';
$error = '';
$backup_dir = __DIR__ . '/../backups/';

// Ensure backup directory exists
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Handle backup actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_backup') {
        $conn = getDBConnection();
        
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backup_dir . $filename;
        
        $tables = [];
        $result = $conn->query("SHOW TABLES");
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }
        
        $sql_dump = "-- Database Backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n-- Tables: " . count($tables) . "\n\n";
        $sql_dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        foreach ($tables as $table) {
            // Get create table statement
            $create = $conn->query("SHOW CREATE TABLE `$table`")->fetch_row();
            $sql_dump .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql_dump .= $create[1] . ";\n\n";
            
            // Get data
            $data = $conn->query("SELECT * FROM `$table`");
            if ($data->num_rows > 0) {
                $columns = [];
                $fields = $data->fetch_fields();
                foreach ($fields as $field) {
                    $columns[] = "`" . $field->name . "`";
                }
                
                while ($row = $data->fetch_row()) {
                    $values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . $conn->real_escape_string($value) . "'";
                        }
                    }
                    $sql_dump .= "INSERT INTO `$table` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
                }
                $sql_dump .= "\n";
            }
        }
        
        $sql_dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
        
        if (file_put_contents($filepath, $sql_dump)) {
            $message = "Backup created: $filename (" . round(strlen($sql_dump) / 1024, 1) . " KB)";
            logAudit('backup_create', 'system', 0, "Created database backup: $filename");
        } else {
            $error = 'Failed to create backup file.';
        }
        
        $conn->close();
    } elseif ($action === 'delete_backup') {
        $file = basename($_POST['file'] ?? '');
        if ($file && preg_match('/^backup_[\d_-]+\.sql$/', $file)) {
            $filepath = $backup_dir . $file;
            if (file_exists($filepath) && unlink($filepath)) {
                $message = 'Backup deleted.';
            }
        }
    } elseif ($action === 'download_backup') {
        $file = basename($_POST['file'] ?? '');
        if ($file && preg_match('/^backup_[\d_-]+\.sql$/', $file)) {
            $filepath = $backup_dir . $file;
            if (file_exists($filepath)) {
                header('Content-Type: application/sql');
                header('Content-Disposition: attachment; filename="' . $file . '"');
                header('Content-Length: ' . filesize($filepath));
                readfile($filepath);
                exit;
            }
        }
    }
}

// Get existing backups
$backups = [];
if (is_dir($backup_dir)) {
    $files = glob($backup_dir . 'backup_*.sql');
    foreach ($files as $file) {
        $backups[] = [
            'name' => basename($file),
            'size' => filesize($file),
            'date' => filemtime($file)
        ];
    }
    usort($backups, function($a, $b) { return $b['date'] - $a['date']; });
}

$theme = getThemeColors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup - <?php echo htmlspecialchars(getCommunityName()); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/../includes/styles.php'; ?>
    <style>
        .backup-info { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px; margin-bottom: 24px; }
        .backup-info p { color: var(--text-secondary); margin: 0 0 12px; }
        .backup-info ul { margin: 0; padding-left: 20px; color: var(--text-muted); }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>💾 Database Backup</h1>
        <form method="POST" style="margin:0;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
            <input type="hidden" name="action" value="create_backup">
            <button type="submit" class="btn btn-primary">🔄 Create Backup Now</button>
        </form>
    </div>
    
    <?php if ($message) showToast($message, 'success'); ?>
    <?php if ($error) showToast($error, 'error'); ?>
    
    <div class="backup-info">
        <p><strong>⚠️ Important Notes:</strong></p>
        <ul>
            <li>Backups are stored locally on the server</li>
            <li>Download backups to keep them safe offsite</li>
            <li>Backups include all database tables and data</li>
            <li>Recommended: Create a backup before major changes</li>
        </ul>
    </div>
    
    <h2 style="font-size:18px;color:#fff;margin-bottom:16px;">Available Backups</h2>
    
    <?php if (empty($backups)): ?>
        <div class="empty-state"><p>No backups yet. Create your first backup above.</p></div>
    <?php else: ?>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Filename</th>
                    <th>Size</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($backups as $backup): ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($backup['name']); ?></code></td>
                    <td><?php echo round($backup['size'] / 1024, 1); ?> KB</td>
                    <td><?php echo date('M j, Y g:i A', $backup['date']); ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                            <input type="hidden" name="action" value="download_backup">
                            <input type="hidden" name="file" value="<?php echo htmlspecialchars($backup['name']); ?>">
                            <button type="submit" class="btn btn-sm btn-secondary">Download</button>
                        </form>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this backup?');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                            <input type="hidden" name="action" value="delete_backup">
                            <input type="hidden" name="file" value="<?php echo htmlspecialchars($backup['name']); ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
