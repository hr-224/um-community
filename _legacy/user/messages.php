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
requireLogin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$message = '';

// Send message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['send_message'])) {
    $recipient_id = intval($_POST['recipient_id']);
    $subject = trim($_POST['subject']);
    $content = trim($_POST['content']);
    $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
    
    if ($recipient_id && $content) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, recipient_id, subject, content, parent_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iissi", $user_id, $recipient_id, $subject, $content, $parent_id);
        $stmt->execute();
        $message_id = $stmt->insert_id;
        $stmt->close();
        
        // Handle file attachment
        if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'text/plain', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            $file_type = $_FILES['attachment']['type'];
            $file_size = $_FILES['attachment']['size'];
            $original_name = $_FILES['attachment']['name'];
            
            if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
                $extension = pathinfo($original_name, PATHINFO_EXTENSION);
                $new_filename = uniqid('msg_') . '_' . time() . '.' . $extension;
                $upload_path = '../uploads/message_attachments/' . $new_filename;
                
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_path)) {
                    $file_path = '/uploads/message_attachments/' . $new_filename;
                    $stmt = $conn->prepare("INSERT INTO message_attachments (message_id, filename, original_filename, file_path, file_size, mime_type) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("isssis", $message_id, $new_filename, $original_name, $file_path, $file_size, $file_type);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
        
        $sender_name = $_SESSION['username'];
        createNotification($recipient_id, 'New Message', "You have a new message from $sender_name", 'info');
        
        $message = 'Message sent!';
    }
}

// Delete message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken() && isset($_POST['delete_message'])) {
    $msg_id = intval($_POST['message_id']);
    $stmt = $conn->prepare("UPDATE messages SET is_deleted_sender = TRUE WHERE id = ? AND sender_id = ?");
    $stmt->bind_param("ii", $msg_id, $user_id);
    $stmt->execute();
    $stmt->close();
    $stmt = $conn->prepare("UPDATE messages SET is_deleted_recipient = TRUE WHERE id = ? AND recipient_id = ?");
    $stmt->bind_param("ii", $msg_id, $user_id);
    $stmt->execute();
    $stmt->close();
    $message = 'Message deleted!';
}

$view = $_GET['view'] ?? 'inbox';
$selected_message = null;

if (isset($_GET['msg'])) {
    $msg_id = intval($_GET['msg']);
    $stmt = $conn->prepare("SELECT m.*, s.username as sender_name, r.username as recipient_name FROM messages m JOIN users s ON m.sender_id = s.id JOIN users r ON m.recipient_id = r.id WHERE m.id = ? AND (m.sender_id = ? OR m.recipient_id = ?)");
    $stmt->bind_param("iii", $msg_id, $user_id, $user_id);
    $stmt->execute();
    $selected_message = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Get attachments
    $attachments = [];
    if ($selected_message) {
        $stmt = $conn->prepare("SELECT * FROM message_attachments WHERE message_id = ?");
        $stmt->bind_param("i", $msg_id);
        $stmt->execute();
        $attachments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    
    if ($selected_message && $selected_message['recipient_id'] == $user_id && !$selected_message['is_read']) {
        $stmt = $conn->prepare("UPDATE messages SET is_read = TRUE, read_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $msg_id);
        $stmt->execute();
        $stmt->close();
    }
}

if ($view === 'inbox') {
    $stmt = $conn->prepare("SELECT m.*, u.username as sender_name FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.recipient_id = ? AND m.is_deleted_recipient = FALSE ORDER BY m.created_at DESC LIMIT 50");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $messages_result = $stmt->get_result();
} else {
    $stmt = $conn->prepare("SELECT m.*, u.username as recipient_name FROM messages m JOIN users u ON m.recipient_id = u.id WHERE m.sender_id = ? AND m.is_deleted_sender = FALSE ORDER BY m.created_at DESC LIMIT 50");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $messages_result = $stmt->get_result();
}

$stmt = $conn->prepare("SELECT id, username FROM users WHERE is_approved = TRUE AND id != ? ORDER BY username");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$users = $stmt->get_result();
$stmt_uc = $conn->prepare("SELECT COUNT(*) as count FROM messages WHERE recipient_id = ? AND is_read = FALSE AND is_deleted_recipient = FALSE");
$stmt_uc->bind_param("i", $user_id);
$stmt_uc->execute();
$unread_row = $stmt_uc->get_result()->fetch_assoc();
$unread_count = $unread_row['count'] ?? 0;
$stmt_uc->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - <?php echo COMMUNITY_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/x-icon" href="<?php echo FAVICON_PATH; ?>">
    <?php include '../includes/styles.php'; ?>
    <style>
        .container { max-width: 1200px; }
        .alert { background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2)); border: 1px solid rgba(16, 185, 129, 0.3); color: #4ade80; padding: 16px 24px; border-radius: var(--radius-md); margin-bottom: 24px; }
        .mail-layout { display: grid; grid-template-columns: 220px 1fr; gap: 24px; }
        @media (max-width: 900px) { .mail-layout { grid-template-columns: 1fr; } }
        .sidebar { background: var(--bg-card); border: 1px solid var(--bg-elevated); border-radius: var(--radius-lg); padding: 20px; height: fit-content; }
        .compose-btn { width: 100%; padding: 14px; border: none; border-radius: var(--radius-md); background: var(--accent); color: white; font-weight: 700; cursor: pointer; margin-bottom: 20px; }
        .sidebar-item { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-radius: var(--radius-md); color: var(--text-secondary); text-decoration: none; margin-bottom: 8px; }
        .sidebar-item:hover { background: var(--bg-elevated); }
        .sidebar-item.active { background: var(--accent-muted); color: var(--text-primary); }
        .main-content { background: var(--bg-card); border: 1px solid var(--bg-elevated); border-radius: var(--radius-lg); overflow: hidden; }
        .content-header { padding: 20px 24px; border-bottom: 1px solid var(--bg-elevated); }
        .content-header h2 { font-size: 18px; }
        .message-list { max-height: 600px; overflow-y: auto; }
        .message-item { display: block; padding: 16px 24px; border-bottom: 1px solid var(--bg-card); text-decoration: none; color: inherit; transition: all 0.2s; }
        .message-item:hover { background: var(--bg-card); }
        .message-item.unread { background: var(--accent-muted); border-left: 3px solid var(--accent); }
        .message-item .from { font-weight: 700; font-size: 14px; margin-bottom: 4px; }
        .message-item .subject { font-size: 14px; color: var(--text-primary); margin-bottom: 4px; }
        .message-item .preview { font-size: 13px; color: var(--text-muted); }
        .message-item .time { font-size: 12px; color: var(--text-faint); float: right; }
        .message-view { padding: 24px; }
        .message-view-header { margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--bg-elevated); }
        .message-view-header h3 { font-size: 20px; margin-bottom: 8px; }
        .message-view-meta { font-size: 13px; color: var(--text-muted); }
        .message-view-body { line-height: 1.7; color: var(--text-primary); white-space: pre-wrap; margin-bottom: 24px; }
        .reply-form { background: var(--bg-elevated); padding: 20px; border-radius: var(--radius-md); }
        .reply-form textarea { width: 100%; padding: 12px; border: 1px solid var(--bg-elevated); border-radius: var(--radius-sm); background: var(--bg-card); color: var(--text-primary); min-height: 100px; resize: vertical; margin-bottom: 12px; }
        .btn { padding: 10px 20px; border: none; border-radius: var(--radius-sm); cursor: pointer; font-weight: 600; }
        .btn-primary { background: var(--accent); color: white; }
        .btn-sm { padding: 8px 16px; font-size: 13px; }
        .empty-state { text-align: center; padding: 60px; color: var(--text-muted); }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid var(--bg-elevated); border-radius: var(--radius-sm); background: rgba(15, 12, 41, 0.8); color: var(--text-primary); font-size: 14px; }
        .form-group select { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23ffffff' d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 40px; }
        .form-group select option { background: #1a1a2e; color: var(--text-primary); }
        .form-group textarea { min-height: 150px; resize: vertical; }
    </style>
</head>
<body>
    <?php $current_page = 'messages'; include '../includes/navbar.php'; ?>

    <div class="container">
        <?php if ($message) showToast($message, 'info'); ?>

        <div class="mail-layout">
            <div class="sidebar">
                <button class="compose-btn" onclick="openCompose()">✏️ Compose</button>
                <a href="?view=inbox" class="sidebar-item <?php echo $view === 'inbox' ? 'active' : ''; ?>">
                    📥 Inbox
                    <?php if ($unread_count > 0): ?><span class="badge"><?php echo $unread_count; ?></span><?php endif; ?>
                </a>
                <a href="?view=sent" class="sidebar-item <?php echo $view === 'sent' ? 'active' : ''; ?>">
                    📤 Sent
                </a>
            </div>

            <div class="main-content">
                <?php if ($selected_message): ?>
                    <div class="content-header">
                        <a href="?view=<?php echo $view; ?>" style="color: var(--text-secondary); text-decoration: none;">← Back to <?php echo ucfirst($view); ?></a>
                    </div>
                    <div class="message-view">
                        <div class="message-view-header">
                            <h3><?php echo htmlspecialchars($selected_message['subject'] ?: '(No Subject)'); ?></h3>
                            <div class="message-view-meta">
                                <strong>From:</strong> <?php echo htmlspecialchars($selected_message['sender_name']); ?> &nbsp;|&nbsp;
                                <strong>To:</strong> <?php echo htmlspecialchars($selected_message['recipient_name']); ?> &nbsp;|&nbsp;
                                <?php echo date('M j, Y g:i A', strtotime($selected_message['created_at'])); ?>
                            </div>
                        </div>
                        <div class="message-view-body"><?php echo htmlspecialchars($selected_message['content']); ?></div>
                        
                        <?php if (!empty($attachments)): ?>
                        <div class="message-attachments" style="margin-bottom: 24px; padding: 16px; background: var(--bg-primary); border-radius: var(--radius-sm);">
                            <div style="font-weight: 600; margin-bottom: 12px; font-size: 13px; color: var(--text-secondary);">📎 Attachments</div>
                            <?php foreach ($attachments as $att): ?>
                            <a href="<?php echo htmlspecialchars($att['file_path']); ?>" download="<?php echo htmlspecialchars($att['original_filename']); ?>" 
                               style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; background: var(--bg-elevated); border-radius: var(--radius-sm); text-decoration: none; color: var(--text-primary); margin-right: 8px; margin-bottom: 8px;">
                                <?php 
                                $icon = '📄';
                                if (strpos($att['mime_type'], 'image') !== false) $icon = '🖼️';
                                elseif (strpos($att['mime_type'], 'pdf') !== false) $icon = '📕';
                                elseif (strpos($att['mime_type'], 'word') !== false) $icon = '📘';
                                echo $icon;
                                ?>
                                <span><?php echo htmlspecialchars($att['original_filename']); ?></span>
                                <span style="font-size: 11px; color: var(--text-muted);">(<?php echo round($att['file_size'] / 1024); ?> KB)</span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($selected_message['sender_id'] != $user_id): ?>
                            <div class="reply-form">
                                <form method="POST">
                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="recipient_id" value="<?php echo $selected_message['sender_id']; ?>">
                                    <input type="hidden" name="subject" value="Re: <?php echo htmlspecialchars($selected_message['subject']); ?>">
                                    <input type="hidden" name="parent_id" value="<?php echo $selected_message['id']; ?>">
                                    <textarea name="content" placeholder="Write your reply..." required></textarea>
                                    <button type="submit" name="send_message" class="btn btn-primary">Send Reply</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="content-header">
                        <h2><?php echo $view === 'inbox' ? '📥 Inbox' : '📤 Sent Messages'; ?></h2>
                    </div>
                    <div class="message-list">
                        <?php if ($messages_result->num_rows > 0): ?>
                            <?php while ($msg = $messages_result->fetch_assoc()): ?>
                                <a href="?view=<?php echo $view; ?>&msg=<?php echo $msg['id']; ?>" class="message-item <?php echo ($view === 'inbox' && !$msg['is_read']) ? 'unread' : ''; ?>">
                                    <span class="time"><?php echo date('M j', strtotime($msg['created_at'])); ?></span>
                                    <div class="from"><?php echo htmlspecialchars($view === 'inbox' ? $msg['sender_name'] : $msg['recipient_name']); ?></div>
                                    <div class="subject"><?php echo htmlspecialchars($msg['subject'] ?: '(No Subject)'); ?></div>
                                    <div class="preview"><?php echo htmlspecialchars(substr($msg['content'], 0, 80)); ?>...</div>
                                </a>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">No messages</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal" id="composeModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>New Message</h3>
                <button class="modal-close" onclick="closeCompose()">&times;</button>
            </div>
            <form method="POST" id="composeForm" enctype="multipart/form-data">
                    <?php echo csrfField(); ?>
                <div class="form-group">
                    <label>To</label>
                    <div class="user-search-container">
                        <input type="text" id="recipientSearch" class="form-control" placeholder="Search for a user..." autocomplete="off">
                        <input type="hidden" name="recipient_id" id="recipientId" required>
                        <div id="searchResults" class="search-results"></div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" placeholder="Message subject">
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="content" required placeholder="Type your message..."></textarea>
                </div>
                <div class="form-group">
                    <label>Attachment (optional)</label>
                    <input type="file" name="attachment" style="padding: 10px; background: var(--bg-primary); border-radius: var(--radius-sm); border: 1px solid var(--bg-elevated);">
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Max 5MB. Allowed: PDF, Images, Word, Text</div>
                </div>
                <button type="submit" name="send_message" class="btn btn-primary" style="width: 100%;">Send Message</button>
            </form>
        </div>
    </div>

    <style>
        .user-search-container { position: relative; }
        .search-results { 
            position: absolute; 
            top: 100%; 
            left: 0; 
            right: 0; 
            background: #1a1a2e; 
            border: 1px solid var(--bg-elevated); 
            border-radius: var(--radius-sm); 
            max-height: 200px; 
            overflow-y: auto; 
            z-index: 100; 
            display: none;
            margin-top: 4px;
        }
        .search-results.show { display: block; }
        .search-result-item { 
            padding: 10px 12px; 
            cursor: pointer; 
            border-bottom: 1px solid var(--bg-primary);
            transition: background 0.2s;
        }
        .search-result-item:last-child { border-bottom: none; }
        .search-result-item:hover { background: var(--bg-elevated); }
        .search-result-item.selected { background: var(--accent); }
        .selected-user { 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            background: var(--accent); 
            padding: 6px 12px; 
            border-radius: var(--radius-lg); 
            font-size: 13px;
            margin-top: 8px;
        }
        .selected-user .remove { 
            cursor: pointer; 
            opacity: 0.7; 
            font-size: 16px;
        }
        .selected-user .remove:hover { opacity: 1; }
        .search-results::-webkit-scrollbar { width: 6px; }
        .search-results::-webkit-scrollbar-track { background: var(--bg-primary); }
        .search-results::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
    </style>

    <script>
        // User data for search
        var allUsers = [
            <?php 
            $users->data_seek(0); // Reset pointer
            $first = true;
            while ($user = $users->fetch_assoc()): 
                if (!$first) echo ',';
                $first = false;
            ?>
            { id: <?php echo $user['id']; ?>, name: <?php echo json_encode($user['username']); ?> }
            <?php endwhile; ?>
        ];
        
        var searchInput = document.getElementById('recipientSearch');
        var searchResults = document.getElementById('searchResults');
        var recipientId = document.getElementById('recipientId');
        var selectedUser = null;
        
        searchInput.addEventListener('input', function() {
            var query = this.value.toLowerCase().trim();
            
            if (query.length < 1) {
                searchResults.classList.remove('show');
                return;
            }
            
            var matches = allUsers.filter(function(user) {
                return user.name.toLowerCase().includes(query);
            }).slice(0, 10); // Limit to 10 results
            
            if (matches.length === 0) {
                searchResults.innerHTML = '<div class="search-result-item" style="color: var(--text-muted);">No users found</div>';
            } else {
                searchResults.innerHTML = matches.map(function(user) {
                    return '<div class="search-result-item" data-id="' + user.id + '" data-name="' + user.name.replace(/"/g, '&quot;') + '">' + 
                           user.name.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</div>';
                }).join('');
            }
            
            searchResults.classList.add('show');
        });
        
        searchResults.addEventListener('click', function(e) {
            var item = e.target.closest('.search-result-item');
            if (item && item.dataset.id) {
                selectUser(item.dataset.id, item.dataset.name);
            }
        });
        
        function selectUser(id, name) {
            selectedUser = { id: id, name: name };
            recipientId.value = id;
            searchInput.value = name;
            searchInput.style.display = 'none';
            
            // Show selected user tag
            var tag = document.createElement('div');
            tag.className = 'selected-user';
            tag.id = 'selectedUserTag';
            tag.innerHTML = '<span>' + name.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</span><span class="remove" onclick="clearSelection()">&times;</span>';
            searchInput.parentNode.insertBefore(tag, searchInput);
            
            searchResults.classList.remove('show');
        }
        
        function clearSelection() {
            selectedUser = null;
            recipientId.value = '';
            searchInput.value = '';
            searchInput.style.display = 'block';
            var tag = document.getElementById('selectedUserTag');
            if (tag) tag.remove();
            searchInput.focus();
        }
        
        // Hide results when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.classList.remove('show');
            }
        });
        
        // Form validation
        document.getElementById('composeForm').addEventListener('submit', function(e) {
            if (!recipientId.value) {
                e.preventDefault();
                searchInput.style.display = 'block';
                searchInput.focus();
                alert('Please select a recipient');
            }
        });
        
        function openCompose() { 
            document.getElementById('composeModal').classList.add('active'); 
            clearSelection();
        }
        function closeCompose() { document.getElementById('composeModal').classList.remove('active'); }
        document.getElementById('composeModal').addEventListener('click', function(e) { if (e.target === this) closeCompose(); });
    </script>
</body>
</html>
<?php $conn->close(); ?>
