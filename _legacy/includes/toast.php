<?php
/**
 * ============================================================
 *  Ultimate Mods – FiveM Community Manager
 * ============================================================
 *  Toast Notification System
 *  
 *  A unified notification system for displaying alerts, messages,
 *  and notifications throughout the application.
 *  
 *  Usage:
 *  - PHP: toast('Your message', 'success');
 *  - PHP: toast('Error occurred', 'error', ['persistent' => true]);
 *  - JS:  Toast.success('Message saved!');
 *  - JS:  Toast.error('Something went wrong', { persistent: true });
 * ============================================================
 */

// Start session if not started (needed for toast queue)
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

/**
 * Queue a toast notification to be displayed on the next page render
 * 
 * @param string $message The message to display
 * @param string $type Type: 'success', 'error', 'warning', 'info'
 * @param array $options Optional settings:
 *   - persistent: bool - If true, toast won't auto-dismiss
 *   - duration: int - Auto-dismiss duration in ms (default: 4000)
 *   - action: array - ['text' => 'Button Text', 'url' => '/path']
 *   - title: string - Optional title above message
 */
function toast($message, $type = 'info', $options = []) {
    if (!isset($_SESSION['_toasts'])) {
        $_SESSION['_toasts'] = [];
    }
    
    $_SESSION['_toasts'][] = [
        'message' => $message,
        'type' => $type,
        'options' => $options,
        'time' => time()
    ];
}

/**
 * Shorthand functions for common toast types
 */
function toastSuccess($message, $options = []) {
    toast($message, 'success', $options);
}

function toastError($message, $options = []) {
    toast($message, 'error', $options);
}

function toastWarning($message, $options = []) {
    toast($message, 'warning', $options);
}

function toastInfo($message, $options = []) {
    toast($message, 'info', $options);
}

/**
 * Get and clear queued toasts
 */
function getQueuedToasts() {
    $toasts = $_SESSION['_toasts'] ?? [];
    $_SESSION['_toasts'] = [];
    return $toasts;
}

/**
 * Check if there are queued toasts
 */
function hasQueuedToasts() {
    return !empty($_SESSION['_toasts']);
}

/**
 * Output inline JavaScript to show a toast immediately
 * Use this when you need to show a toast on the same page load (not after redirect)
 * 
 * @param string $message The message
 * @param string $type Type: 'success', 'error', 'warning', 'info'
 * @param array $options Optional settings
 */
function showToast($message, $type = 'info', $options = []) {
    if (empty($message)) return;
    
    $jsOpts = [];
    if (!empty($options['persistent'])) $jsOpts[] = 'persistent: true';
    if (!empty($options['duration'])) $jsOpts[] = 'duration: ' . intval($options['duration']);
    if (!empty($options['title'])) $jsOpts[] = 'title: ' . json_encode($options['title']);
    if (!empty($options['action'])) $jsOpts[] = 'action: ' . json_encode($options['action']);
    $jsOptsStr = $jsOpts ? '{' . implode(', ', $jsOpts) . '}' : '{}';
    
    echo '<script>document.addEventListener("DOMContentLoaded", function() { Toast.' . $type . '(' . json_encode($message) . ', ' . $jsOptsStr . '); });</script>';
}

/**
 * Output inline toasts for $message and $error variables
 * This is a drop-in replacement for the old alert divs
 */
function showPageToasts() {
    global $message, $error, $success, $warning;
    
    if (!empty($message)) {
        showToast($message, 'success');
    }
    if (!empty($success)) {
        showToast($success, 'success');
    }
    if (!empty($error)) {
        showToast($error, 'error');
    }
    if (!empty($warning)) {
        showToast($warning, 'warning');
    }
}

/**
 * Output the toast container and any queued toasts
 * Call this at the end of the page, before </body>
 */
function renderToasts() {
    $queuedToasts = getQueuedToasts();
    ?>
<!-- Toast Notification System -->
<div id="toast-container" class="toast-container"></div>

<style>
/* Toast Container */
.toast-container {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 999999;
    display: flex;
    flex-direction: column-reverse;
    gap: 12px;
    max-width: 400px;
    pointer-events: none;
}

/* Individual Toast */
.toast {
    background: linear-gradient(135deg, rgba(25, 25, 35, 0.98), rgba(15, 15, 25, 0.98));
    
    border-radius: var(--radius-md);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), 0 0 0 1px var(--bg-elevated);
    padding: 0;
    overflow: hidden;
    pointer-events: auto;
    transform: translateX(120%);
    opacity: 0;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.3s ease;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    min-width: 300px;
    max-width: 400px;
}

.toast.toast-visible {
    transform: translateX(0);
    opacity: 1;
}

.toast.toast-hiding {
    transform: translateX(120%);
    opacity: 0;
}

/* Toast Types - Left Border Accent */
.toast-success { border-left: 4px solid var(--success); }
.toast-error { border-left: 4px solid var(--danger); }
.toast-warning { border-left: 4px solid #f59e0b; }
.toast-info { border-left: 4px solid #3b82f6; }

/* Toast Header */
.toast-header {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 16px;
}

/* Toast Icon */
.toast-icon {
    width: 32px;
    height: 32px;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}

.toast-success .toast-icon { background: rgba(16, 185, 129, 0.2); }
.toast-error .toast-icon { background: rgba(239, 68, 68, 0.2); }
.toast-warning .toast-icon { background: rgba(245, 158, 11, 0.2); }
.toast-info .toast-icon { background: rgba(59, 130, 246, 0.2); }

/* Toast Content */
.toast-content {
    flex: 1;
    min-width: 0;
}

.toast-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 2px;
    line-height: 1.3;
}

.toast-message {
    font-size: 13px;
    color: var(--text-secondary);
    line-height: 1.4;
    word-wrap: break-word;
}

/* Toast Close Button */
.toast-close {
    background: none;
    border: none;
    color: var(--text-muted);
    font-size: 18px;
    cursor: pointer;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: all 0.2s;
    flex-shrink: 0;
    margin-left: 8px;
}

.toast-close:hover {
    background: var(--bg-elevated);
    color: var(--text-primary);
}

/* Toast Actions */
.toast-actions {
    display: flex;
    gap: 8px;
    padding: 0 16px 14px 60px;
}

.toast-btn {
    padding: 8px 14px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.toast-btn-primary {
    background: var(--accent);
    color: var(--text-primary);
}

.toast-btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(88, 101, 242, 0.4);
}

.toast-btn-secondary {
    background: var(--bg-elevated);
    color: var(--text-primary);
}

.toast-btn-secondary:hover {
    background: var(--bg-hover);
}

/* Progress Bar for Auto-dismiss */
.toast-progress {
    height: 3px;
    background: var(--bg-elevated);
    position: relative;
    overflow: hidden;
}

.toast-progress-bar {
    height: 100%;
    position: absolute;
    left: 0;
    top: 0;
    transition: width linear;
}

.toast-success .toast-progress-bar { background: var(--success); }
.toast-error .toast-progress-bar { background: var(--danger); }
.toast-warning .toast-progress-bar { background: #f59e0b; }
.toast-info .toast-progress-bar { background: #3b82f6; }

/* Responsive */
@media (max-width: 480px) {
    .toast-container {
        left: 10px;
        right: 10px;
        bottom: 10px;
        max-width: none;
    }
    
    .toast {
        min-width: 0;
        max-width: none;
    }
}
</style>

<script>
/**
 * Toast Notification System
 * 
 * Usage:
 *   Toast.success('Message saved!');
 *   Toast.error('Something went wrong');
 *   Toast.warning('Please check your input');
 *   Toast.info('Did you know?');
 *   
 *   // With options:
 *   Toast.success('File uploaded!', { duration: 5000 });
 *   Toast.error('Session expired', { 
 *       persistent: true,
 *       action: { text: 'Login', url: '/auth/login' }
 *   });
 */
const Toast = (function() {
    const ICONS = {
        success: '✓',
        error: '✕',
        warning: '⚠',
        info: 'ℹ'
    };
    
    const TITLES = {
        success: 'Success',
        error: 'Error',
        warning: 'Warning',
        info: 'Info'
    };
    
    const DEFAULT_DURATION = 4000;
    
    let container = null;
    let toastCount = 0;
    
    function init() {
        container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
    }
    
    function show(message, type = 'info', options = {}) {
        if (!container) init();
        
        const id = 'toast-' + (++toastCount);
        const duration = options.duration || DEFAULT_DURATION;
        const persistent = options.persistent || options.action;
        const title = options.title || TITLES[type] || 'Notice';
        
        // Create toast element
        const toast = document.createElement('div');
        toast.id = id;
        toast.className = `toast toast-${type}`;
        
        let actionsHtml = '';
        if (options.action) {
            const isExternal = options.action.url && options.action.url.startsWith('http');
            const targetAttr = isExternal ? ' target="_blank" rel="noopener noreferrer"' : '';
            actionsHtml = `
                <div class="toast-actions">
                    <a href="${escapeHtml(options.action.url || '#')}" class="toast-btn toast-btn-primary"${targetAttr}>
                        ${escapeHtml(options.action.text || 'View')}
                    </a>
                </div>
            `;
        }
        
        let progressHtml = '';
        if (!persistent) {
            progressHtml = `
                <div class="toast-progress">
                    <div class="toast-progress-bar" style="width: 100%;"></div>
                </div>
            `;
        }
        
        toast.innerHTML = `
            <div class="toast-header">
                <div class="toast-icon">${ICONS[type] || 'ℹ'}</div>
                <div class="toast-content">
                    <div class="toast-title">${escapeHtml(title)}</div>
                    <div class="toast-message">${escapeHtml(message)}</div>
                </div>
                <button class="toast-close" onclick="Toast.dismiss('${id}')" title="Dismiss">&times;</button>
            </div>
            ${actionsHtml}
            ${progressHtml}
        `;
        
        // Add to container
        container.appendChild(toast);
        
        // Trigger animation
        requestAnimationFrame(() => {
            toast.classList.add('toast-visible');
        });
        
        // Start progress bar animation and auto-dismiss
        if (!persistent) {
            const progressBar = toast.querySelector('.toast-progress-bar');
            if (progressBar) {
                progressBar.style.transition = `width ${duration}ms linear`;
                requestAnimationFrame(() => {
                    progressBar.style.width = '0%';
                });
            }
            
            setTimeout(() => dismiss(id), duration);
        }
        
        return id;
    }
    
    function dismiss(id) {
        const toast = document.getElementById(id);
        if (!toast) return;
        
        toast.classList.remove('toast-visible');
        toast.classList.add('toast-hiding');
        
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }
    
    function dismissAll() {
        if (!container) return;
        const toasts = container.querySelectorAll('.toast');
        toasts.forEach(toast => dismiss(toast.id));
    }
    
    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
    
    // Public API
    return {
        show: show,
        dismiss: dismiss,
        dismissAll: dismissAll,
        
        success: (msg, opts) => show(msg, 'success', opts),
        error: (msg, opts) => show(msg, 'error', opts),
        warning: (msg, opts) => show(msg, 'warning', opts),
        info: (msg, opts) => show(msg, 'info', opts),
        
        // License-specific toast
        license: function(errorType, message, options = {}) {
            const config = getLicenseToastConfig(errorType, message);
            return show(config.message, config.type, { ...config.options, ...options });
        }
    };
    
    function getLicenseToastConfig(errorType, message) {
        // Error type constants (must match PHP)
        const ERROR_API_UNREACHABLE = 1;
        const ERROR_INVALID_KEY = 2;
        const ERROR_EXPIRED = 3;
        const ERROR_CANCELED = 4;
        const ERROR_INACTIVE = 5;
        const ERROR_DOMAIN_MISMATCH = 6;
        const ERROR_NO_KEY = 7;
        
        let config = {
            type: 'error',
            message: message || 'License issue detected.',
            options: { persistent: true, title: 'License Issue' }
        };
        
        switch (errorType) {
            case ERROR_API_UNREACHABLE:
                config.type = 'warning';
                config.options.title = 'License Warning';
                config.options.action = { text: 'Check Status', url: '/admin/license' };
                break;
            case ERROR_INVALID_KEY:
            case ERROR_NO_KEY:
                config.options.action = { text: 'Enter License Key', url: '/admin/license' };
                break;
            case ERROR_EXPIRED:
                config.options.action = { text: 'Renew License', url: 'https://ultimate-mods.com/clients/purchases/' };
                break;
            case ERROR_CANCELED:
            case ERROR_INACTIVE:
                config.options.action = { text: 'Visit Ultimate Mods', url: 'https://ultimate-mods.com/clients/purchases/' };
                break;
            case ERROR_DOMAIN_MISMATCH:
                config.options.action = { text: 'Contact Support', url: 'https://ultimate-mods.com/contact/' };
                break;
        }
        
        return config;
    }
})();

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        Toast.show && null; // Just ensure Toast is ready
    });
}
</script>

<?php
    // Render any queued PHP toasts
    if (!empty($queuedToasts)): ?>
<script>
(function() {
    <?php foreach ($queuedToasts as $t): 
        $opts = $t['options'] ?? [];
        $jsOpts = [];
        if (!empty($opts['persistent'])) $jsOpts[] = 'persistent: true';
        if (!empty($opts['duration'])) $jsOpts[] = 'duration: ' . intval($opts['duration']);
        if (!empty($opts['title'])) $jsOpts[] = 'title: ' . json_encode($opts['title']);
        if (!empty($opts['action'])) $jsOpts[] = 'action: ' . json_encode($opts['action']);
        $jsOptsStr = $jsOpts ? '{' . implode(', ', $jsOpts) . '}' : '{}';
    ?>
    Toast.<?php echo $t['type']; ?>(<?php echo json_encode($t['message']); ?>, <?php echo $jsOptsStr; ?>);
    <?php endforeach; ?>
})();
</script>
<?php endif;
}

/**
 * Output license toast if there are issues (replaces the old bubble)
 * Call this function for admin pages
 */
function renderLicenseToast() {
    // Only show to logged in admins
    if (!function_exists('isLoggedIn') || !function_exists('isAdmin')) return;
    if (!isLoggedIn() || !isAdmin()) return;
    
    // Check if dismissed via cookie
    if (isset($_COOKIE['license_toast_dismissed']) && $_COOKIE['license_toast_dismissed'] === '1') {
        return;
    }
    
    // Check license status
    try {
        if (!function_exists('checkLicense')) return;
        
        $conn = getDBConnection();
        $licenseStatus = checkLicense($conn, false);
        $conn->close();
        
        // Only show if there's an issue
        if ($licenseStatus['valid'] && empty($licenseStatus['warning']) && !($licenseStatus['in_grace_period'] ?? false)) {
            return;
        }
        
        $errorType = $licenseStatus['error_type'] ?? 0;
        $message = $licenseStatus['error'] ?? $licenseStatus['warning'] ?? 'License issue detected.';
        $inGracePeriod = $licenseStatus['in_grace_period'] ?? false;
        
        if ($inGracePeriod && !empty($licenseStatus['grace_expires'])) {
            $message .= ' Grace period expires: ' . date('M j, Y', strtotime($licenseStatus['grace_expires']));
        }
        
        ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Toast.license(<?php echo intval($errorType); ?>, <?php echo json_encode($message); ?>);
});
</script>
<?php
    } catch (Exception $e) {
        // Silently fail
    }
}

/**
 * Render real-time notification system using Server-Sent Events (SSE)
 * Notifications appear instantly when created - no polling delay
 */
function renderNotificationPolling() {
    if (!function_exists('isLoggedIn') || !isLoggedIn()) {
        return;
    }
    ?>
<script>
(function() {
    // Real-time notification system using Server-Sent Events
    let eventSource = null;
    let reconnectAttempts = 0;
    const MAX_RECONNECT_ATTEMPTS = 10;
    const RECONNECT_DELAY = 3000;
    let seenNotifications = new Set();
    
    // Load seen notifications from localStorage
    try {
        const stored = localStorage.getItem('um_seen_notifications');
        if (stored) {
            JSON.parse(stored).forEach(id => seenNotifications.add(id));
        }
    } catch (e) {}
    
    function saveSeenNotifications() {
        try {
            const arr = Array.from(seenNotifications).slice(-100);
            localStorage.setItem('um_seen_notifications', JSON.stringify(arr));
        } catch (e) {}
    }
    
    function updateBadge(id, count) {
        const badge = document.getElementById(id);
        if (badge) {
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = '';
            } else {
                badge.style.display = 'none';
            }
        }
        document.querySelectorAll('.' + id).forEach(el => {
            if (count > 0) {
                el.textContent = count;
                el.style.display = '';
            } else {
                el.style.display = 'none';
            }
        });
    }
    
    function updateCommunicationBadge() {
        // Update the combined communication badge
        const notifBadge = document.getElementById('badge-notifications');
        const msgBadge = document.getElementById('badge-messages');
        const annBadge = document.getElementById('badge-announcements');
        const totalBadge = document.getElementById('badge-comm-total');
        
        let total = 0;
        if (notifBadge) total += parseInt(notifBadge.textContent) || 0;
        if (msgBadge) total += parseInt(msgBadge.textContent) || 0;
        if (annBadge) total += parseInt(annBadge.textContent) || 0;
        
        if (totalBadge) {
            if (total > 0) {
                totalBadge.textContent = total;
                totalBadge.style.display = '';
            } else {
                totalBadge.style.display = 'none';
            }
        }
    }
    
    function showNotification(notif) {
        if (seenNotifications.has(notif.id)) return;
        
        seenNotifications.add(notif.id);
        saveSeenNotifications();
        
        // Play notification sound (optional)
        // playNotificationSound();
        
        // Show toast notification
        const toastOptions = {
            persistent: true,
            action: notif.link ? { text: 'View', url: notif.link } : null
        };
        
        if (notif.title) {
            toastOptions.title = notif.title;
        }
        
        const toastType = notif.type || 'info';
        if (Toast[toastType]) {
            Toast[toastType](notif.message, toastOptions);
        } else {
            Toast.info(notif.message, toastOptions);
        }
        
        // Mark as read via API
        fetch('/api/notifications.php?action=mark_read&id=' + notif.id, {
            method: 'POST',
            credentials: 'same-origin'
        }).catch(() => {});
    }
    
    function connectSSE() {
        // Check if SSE is supported
        if (typeof EventSource === 'undefined') {
            startPolling();
            return;
        }
        
        // Close existing connection
        if (eventSource) {
            eventSource.close();
        }
        
        eventSource = new EventSource('/api/notifications_stream.php');
        
        eventSource.addEventListener('connected', function(e) {
            reconnectAttempts = 0;
        });
        
        eventSource.addEventListener('update', function(e) {
            try {
                const data = JSON.parse(e.data);
                
                // Update badge counts
                if (typeof data.unread_count !== 'undefined') {
                    updateBadge('badge-notifications', data.unread_count);
                }
                if (typeof data.message_count !== 'undefined') {
                    updateBadge('badge-messages', data.message_count);
                }
                if (typeof data.pending_users_count !== 'undefined') {
                    updateBadge('pending-users-badge', data.pending_users_count);
                }
                
                updateCommunicationBadge();
                
                // Show new notifications as toasts
                if (data.notifications && data.notifications.length > 0) {
                    data.notifications.forEach(showNotification);
                }
                
                // Show new message alert
                if (data.new_messages && data.new_messages > 0) {
                    Toast.info('You have ' + data.new_messages + ' new message' + (data.new_messages > 1 ? 's' : ''), {
                        title: '💬 New Message',
                        action: { text: 'View', url: '/user/messages' }
                    });
                }
                
                // Show new pending user alert (admins)
                if (data.new_pending_users && data.new_pending_users > 0) {
                    Toast.warning(data.new_pending_users + ' new user' + (data.new_pending_users > 1 ? 's' : '') + ' awaiting approval', {
                        title: '👤 Pending Approval',
                        action: { text: 'Review', url: '/admin/' }
                    });
                }
                
            } catch (err) {
                // Silently handle parse errors
            }
        });
        
        eventSource.addEventListener('heartbeat', function(e) {
            // Connection is alive
        });
        
        eventSource.addEventListener('reconnect', function(e) {
            eventSource.close();
            setTimeout(connectSSE, 1000);
        });
        
        eventSource.addEventListener('error', function(e) {
            eventSource.close();
            
            if (reconnectAttempts < MAX_RECONNECT_ATTEMPTS) {
                reconnectAttempts++;
                setTimeout(connectSSE, RECONNECT_DELAY);
            } else {
                startPolling();
            }
        });
    }
    
    // Fallback polling (only used if SSE fails)
    function startPolling() {
        async function poll() {
            try {
                const response = await fetch('/api/notifications.php', {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                });
                
                if (!response.ok) return;
                
                const data = await response.json();
                if (!data.success) return;
                
                updateBadge('badge-notifications', data.unread_count);
                updateBadge('badge-messages', data.message_count);
                updateBadge('pending-users-badge', data.pending_users_count);
                updateCommunicationBadge();
                
                if (data.notifications) {
                    data.notifications.forEach(notif => {
                        if (!notif.is_read) showNotification(notif);
                    });
                }
            } catch (e) {
                // Silently handle polling errors
            }
        }
        
        poll();
        setInterval(poll, 10000); // Poll every 10 seconds as fallback
    }
    
    // Start real-time connection when page loads
    if (document.readyState === 'complete') {
        connectSSE();
    } else {
        window.addEventListener('load', connectSSE);
    }
    
    // Reconnect when tab becomes visible
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden && eventSource && eventSource.readyState === EventSource.CLOSED) {
            connectSSE();
        }
    });
    
    // Clean up on page unload
    window.addEventListener('beforeunload', function() {
        if (eventSource) {
            eventSource.close();
        }
    });
})();
</script>
<?php
}
