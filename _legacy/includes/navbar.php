<?php
/**
 * ============================================================
 *  Ultimate Mods – FiveM Community Manager
 * ============================================================
 *  Modern Navbar Component - v2.0
 * ============================================================
 */

if (!isset($current_page)) $current_page = '';

// Get counts for badges
$nav_notif_count = isLoggedIn() ? getUnreadNotificationCount() : 0;
$nav_msg_count = isLoggedIn() ? getUnreadMessageCount() : 0;
$nav_ann_count = isLoggedIn() ? getUnreadAnnouncementCount() : 0;

// Get pending users count for admin badge
$nav_pending_users_count = 0;
if (isLoggedIn() && (isAdmin() || hasPermission('admin.users'))) {
    try {
        $conn = getDBConnection();
        $result = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE is_approved = FALSE");
        if ($result) {
            $row = $result->fetch_assoc();
            $nav_pending_users_count = intval($row['cnt'] ?? 0);
        }
        $conn->close();
    } catch (Exception $e) {
        $nav_pending_users_count = 0;
    }
}

// Get logo path and community name
$logo_path = getSetting('community_logo', '');
$has_logo = !empty($logo_path) && file_exists($_SERVER['DOCUMENT_ROOT'] . $logo_path);
$community_name = getCommunityName();

// Get user info
$user_avatar = '';
$username = '';
if (isLoggedIn()) {
    $username = $_SESSION['username'] ?? 'User';
    $user_avatar = getSetting('user_' . $_SESSION['user_id'] . '_profile_pic', '');
}
?>

<style>
/* =====================================================
   MODERN NAVBAR v2.0
   ===================================================== */
.navbar {
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border);
    height: 64px;
    position: sticky;
    top: 0;
    z-index: 1000;
    
}

.navbar-inner {
    max-width: 1600px;
    margin: 0 auto;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
}

/* Brand */
.navbar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    flex-shrink: 0;
}

.navbar-logo {
    width: 36px;
    height: 36px;
    border-radius: var(--radius-md);
    object-fit: contain;
}

.navbar-logo-dot {
    width: 10px;
    height: 10px;
    background: var(--accent);
    border-radius: 50%;
    box-shadow: 0 0 12px var(--accent);
}

.navbar-title {
    font-size: 17px;
    font-weight: 700;
    color: var(--text-primary);
    letter-spacing: -0.02em;
}

/* Nav Items */
.navbar-nav {
    display: flex;
    align-items: center;
    gap: 8px;
}

.nav-item {
    position: relative;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: var(--radius-sm);
    color: var(--text-secondary);
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.15s ease;
    white-space: nowrap;
}

.nav-link:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.nav-link.active {
    background: var(--bg-elevated);
    color: var(--text-primary);
}

.nav-link .icon {
    font-size: 16px;
    opacity: 0.9;
}

/* Badge */
.nav-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    font-size: 11px;
    font-weight: 700;
    color: var(--text-primary);
    background: var(--danger);
    border-radius: var(--radius-md);
    margin-left: 4px;
}

.nav-badge.warning {
    background: var(--warning);
    color: #000;
}

.nav-badge.accent {
    background: var(--accent);
}

/* Dropdown */
.nav-dropdown {
    position: relative;
}

.nav-dropdown-toggle {
    cursor: pointer;
}

.nav-dropdown-toggle::after {
    content: '';
    width: 0;
    height: 0;
    border-left: 4px solid transparent;
    border-right: 4px solid transparent;
    border-top: 5px solid currentColor;
    opacity: 0.5;
    margin-left: 6px;
    transition: transform 0.2s ease;
}

.nav-dropdown:hover .nav-dropdown-toggle::after,
.nav-dropdown.open .nav-dropdown-toggle::after {
    transform: rotate(180deg);
}

.nav-dropdown-menu {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    min-width: 220px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 8px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-8px);
    transition: all 0.2s ease;
    box-shadow: var(--shadow-lg);
    z-index: 1001;
}

.nav-dropdown:hover .nav-dropdown-menu {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.nav-dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    border-radius: var(--radius-md);
    color: var(--text-secondary);
    font-size: 14px;
    text-decoration: none;
    transition: all 0.15s ease;
}

.nav-dropdown-item:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.nav-dropdown-item.active {
    background: var(--accent-muted);
    color: var(--accent);
}

.nav-dropdown-item .icon {
    font-size: 16px;
    width: 20px;
    text-align: center;
}

.nav-dropdown-divider {
    height: 1px;
    background: var(--border);
    margin: 8px 0;
}

.nav-dropdown-header {
    padding: 8px 14px 4px;
    font-size: 11px;
    font-weight: 700;
    color: var(--text-faint);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Mega Menu for Admin */
.nav-dropdown-mega {
    display: grid;
    grid-template-columns: repeat(6, 180px);
    gap: 8px;
    padding: 16px;
    min-width: auto;
    width: max-content;
    right: 0;
    left: auto;
}

.nav-mega-column {
    display: flex;
    flex-direction: column;
}

.nav-mega-column .nav-dropdown-header {
    padding: 4px 14px 8px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 4px;
}

.nav-mega-column .nav-dropdown-item {
    padding: 8px 14px;
    font-size: 13px;
}

.nav-mega-column .nav-dropdown-divider {
    margin: 4px 0;
}

/* Nested Submenu */
.nav-submenu {
    position: relative;
}

.nav-submenu-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 10px 14px;
    border-radius: var(--radius-md);
    color: var(--text-secondary);
    font-size: 14px;
    cursor: pointer;
    transition: all 0.15s ease;
}

.nav-submenu-toggle:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.nav-submenu-toggle .chevron {
    font-size: 12px;
    opacity: 0.5;
    transition: transform 0.2s ease;
}

.nav-submenu.open .nav-submenu-toggle .chevron {
    transform: rotate(90deg);
}

.nav-submenu-content {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
    padding-left: 12px;
}

.nav-submenu.open .nav-submenu-content {
    max-height: 500px;
}

/* Right Section */
.navbar-right {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-left: auto;
    flex-shrink: 0;
}

/* Notification Icon */
.nav-icon-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: var(--radius-md);
    background: transparent;
    border: none;
    color: var(--text-muted);
    font-size: 18px;
    cursor: pointer;
    position: relative;
    transition: all 0.15s ease;
}

.nav-icon-btn:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.nav-icon-btn .badge-dot {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 8px;
    height: 8px;
    background: var(--danger);
    border-radius: 50%;
    border: 2px solid var(--bg-secondary);
}

/* Theme toggle icons */
.theme-icon-dark  { display: inline; }
.theme-icon-light { display: none; }
[data-theme="light"] .theme-icon-dark  { display: none; }
[data-theme="light"] .theme-icon-light { display: inline; }

/* User Menu */
.navbar-user {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 12px 6px 6px;
    border-radius: var(--radius-md);
    background: var(--bg-elevated);
    cursor: pointer;
    transition: all 0.15s ease;
    margin-left: 8px;
}

.navbar-user:hover {
    background: var(--bg-hover);
}

.navbar-user-avatar {
    width: 32px;
    height: 32px;
    border-radius: var(--radius-md);
    object-fit: cover;
    background: var(--accent);
}

.navbar-user-avatar-placeholder {
    width: 32px;
    height: 32px;
    border-radius: var(--radius-md);
    background: var(--accent);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-primary);
    font-weight: 600;
    font-size: 14px;
}

.navbar-user-info {
    display: flex;
    flex-direction: column;
}

.navbar-username {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1.2;
}

.navbar-user-role {
    font-size: 11px;
    color: var(--text-muted);
}

.navbar-user-chevron {
    color: var(--text-muted);
    font-size: 10px;
    margin-left: 4px;
}

/* User Dropdown */
.user-dropdown {
    position: relative;
}

.user-dropdown-menu {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    min-width: 200px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 8px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-8px);
    transition: all 0.2s ease;
    box-shadow: var(--shadow-lg);
    z-index: 1001;
}

.user-dropdown:hover .user-dropdown-menu,
.user-dropdown.open .user-dropdown-menu {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

/* Mobile Toggle */
.navbar-toggle {
    display: none;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    width: 40px;
    height: 40px;
    background: none;
    border: none;
    cursor: pointer;
    padding: 8px;
    gap: 5px;
}

.navbar-toggle span {
    display: block;
    width: 20px;
    height: 2px;
    background: var(--text-primary);
    border-radius: 2px;
    transition: all 0.3s ease;
}

.navbar-toggle.active span:nth-child(1) {
    transform: rotate(45deg) translate(5px, 5px);
}

.navbar-toggle.active span:nth-child(2) {
    opacity: 0;
}

.navbar-toggle.active span:nth-child(3) {
    transform: rotate(-45deg) translate(5px, -5px);
}

/* Mobile Menu */
/* Large laptops and small desktops */
@media (max-width: 1400px) {
    .nav-dropdown-mega {
        grid-template-columns: repeat(3, 160px);
        right: 0;
        left: auto;
    }
    
    .navbar-title {
        max-width: 150px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
}

/* Standard laptops */
@media (max-width: 1280px) {
    .navbar-user-info {
        display: none;
    }
    
    .navbar-user {
        padding: 6px;
    }
    
    .navbar-user-chevron {
        display: none;
    }
    
    .nav-dropdown-mega {
        grid-template-columns: repeat(2, 160px);
    }
    
    .nav-link {
        padding: 8px 10px;
        font-size: 13px;
    }
    
    .nav-link .icon {
        display: none;
    }
    
    .navbar-right {
        gap: 4px;
    }
    
    .nav-icon-btn {
        width: 36px;
        height: 36px;
    }
}

/* Small laptops */
@media (max-width: 1100px) {
    .nav-link {
        padding: 6px 8px;
        font-size: 12px;
    }
    
    .navbar-title {
        display: none;
    }
    
    .nav-dropdown-mega {
        grid-template-columns: repeat(2, 140px);
        padding: 12px;
    }
    
    .nav-mega-title {
        font-size: 10px;
    }
    
    .nav-dropdown-item {
        font-size: 12px;
        padding: 6px 10px;
    }
}

/* Tablets and mobile */
@media (max-width: 1024px) {
    .navbar-toggle {
        display: flex;
    }
    
    .navbar-nav {
        position: fixed;
        top: 64px;
        left: 0;
        right: 0;
        bottom: 0;
        background: var(--bg-secondary);
        flex-direction: column;
        align-items: stretch;
        padding: 16px;
        gap: 4px;
        overflow-y: auto;
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        z-index: 999;
    }
    
    .navbar-nav.open {
        transform: translateX(0);
    }
    
    .nav-link {
        padding: 14px 16px;
        font-size: 15px;
    }
    
    .nav-link .icon {
        display: inline;
    }
    
    .nav-dropdown-menu {
        position: static;
        opacity: 1;
        visibility: visible;
        transform: none;
        box-shadow: none;
        background: var(--bg-hover);
        display: none;
        margin-top: 4px;
    }
    
    .nav-dropdown-mega {
        display: flex;
        flex-direction: column;
        grid-template-columns: none;
        width: 100%;
        padding: 8px;
    }
    
    .nav-mega-column {
        border-bottom: 1px solid var(--border);
        padding-bottom: 8px;
        margin-bottom: 8px;
    }
    
    .nav-mega-column:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }
    
    .nav-dropdown.open .nav-dropdown-menu {
        display: block;
    }
    
    .navbar-right {
        position: relative;
        gap: 4px;
    }
    
    .navbar-user-info {
        display: none;
    }
    
    .navbar-user {
        padding: 6px;
    }
    
    .navbar-user-chevron {
        display: none;
    }
    
    .user-dropdown-menu {
        right: 0;
        left: auto;
        max-width: calc(100vw - 24px);
    }
    
    .navbar-title {
        display: block;
        max-width: 120px;
    }
}

@media (max-width: 600px) {
    .navbar-inner {
        padding: 0 12px;
    }
    
    .navbar-title {
        font-size: 14px;
        max-width: 120px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .navbar-logo {
        width: 28px;
        height: 28px;
    }
    
    .nav-icon-btn {
        width: 36px;
        height: 36px;
        font-size: 16px;
    }
}
</style>

<nav class="navbar">
    <div class="navbar-inner">
        <!-- Brand -->
        <a href="/" class="navbar-brand">
            <?php if ($has_logo): ?>
                <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo" class="navbar-logo">
            <?php else: ?>
                <span class="navbar-logo-dot"></span>
            <?php endif; ?>
            <span class="navbar-title"><?php echo htmlspecialchars($community_name); ?></span>
        </a>
        
        <!-- Mobile Toggle -->
        <button class="navbar-toggle" id="navbarToggle" aria-label="Toggle menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
        
        <?php if (isLoggedIn()): ?>
        <!-- Main Navigation -->
        <div class="navbar-nav" id="navbarNav">
            <!-- Dashboard -->
            <div class="nav-item">
                <a href="/" class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                    <span class="icon">🏠</span> Dashboard
                </a>
            </div>
            
            <!-- Apply -->
            <div class="nav-item">
                <a href="/apply" class="nav-link <?php echo $current_page === 'apply' ? 'active' : ''; ?>">
                    <span class="icon">📋</span> Apply
                </a>
            </div>
            
            <!-- Communication -->
            <div class="nav-item nav-dropdown">
                <span class="nav-link nav-dropdown-toggle <?php echo in_array($current_page, ['announcements', 'messages', 'notifications']) ? 'active' : ''; ?>">
                    <span class="icon">💬</span> Communication
                    <?php if ($nav_msg_count + $nav_ann_count + $nav_notif_count > 0): ?>
                        <span class="nav-badge"><?php echo $nav_msg_count + $nav_ann_count + $nav_notif_count; ?></span>
                    <?php endif; ?>
                </span>
                <div class="nav-dropdown-menu">
                    <a href="/user/announcements" class="nav-dropdown-item <?php echo $current_page === 'announcements' ? 'active' : ''; ?>">
                        <span class="icon">📢</span> Announcements
                        <?php if ($nav_ann_count > 0): ?><span class="nav-badge"><?php echo $nav_ann_count; ?></span><?php endif; ?>
                    </a>
                    <a href="/user/messages" class="nav-dropdown-item <?php echo $current_page === 'messages' ? 'active' : ''; ?>">
                        <span class="icon">✉️</span> Messages
                        <?php if ($nav_msg_count > 0): ?><span class="nav-badge"><?php echo $nav_msg_count; ?></span><?php endif; ?>
                    </a>
                    <a href="/user/notifications" class="nav-dropdown-item <?php echo $current_page === 'notifications' ? 'active' : ''; ?>">
                        <span class="icon">🔔</span> Notifications
                        <?php if ($nav_notif_count > 0): ?><span class="nav-badge"><?php echo $nav_notif_count; ?></span><?php endif; ?>
                    </a>
                </div>
            </div>
            
            <!-- Department -->
            <div class="nav-item nav-dropdown">
                <span class="nav-link nav-dropdown-toggle <?php echo in_array($current_page, ['loa', 'documents', 'patrol_logs', 'user_sessions', 'user_sops', 'calendar', 'shifts', 'events', 'transfer_request']) ? 'active' : ''; ?>">
                    <span class="icon">👮</span> Department
                </span>
                <div class="nav-dropdown-menu">
                    <a href="/user/loa" class="nav-dropdown-item <?php echo $current_page === 'loa' ? 'active' : ''; ?>">
                        <span class="icon">🏖️</span> LOA Requests
                    </a>
                    <a href="/user/loa_calendar" class="nav-dropdown-item <?php echo $current_page === 'calendar' ? 'active' : ''; ?>">
                        <span class="icon">📅</span> LOA Calendar
                    </a>
                    <a href="/user/documents" class="nav-dropdown-item <?php echo $current_page === 'documents' ? 'active' : ''; ?>">
                        <span class="icon">📁</span> Documents
                    </a>
                    <a href="/user/sops" class="nav-dropdown-item <?php echo $current_page === 'user_sops' ? 'active' : ''; ?>">
                        <span class="icon">📜</span> SOPs
                    </a>
                    <div class="nav-dropdown-divider"></div>
                    <a href="/user/patrol_logs" class="nav-dropdown-item <?php echo $current_page === 'patrol_logs' ? 'active' : ''; ?>">
                        <span class="icon">🚔</span> Patrol Logs
                    </a>
                    <a href="/user/shifts" class="nav-dropdown-item <?php echo $current_page === 'shifts' ? 'active' : ''; ?>">
                        <span class="icon">📆</span> Shifts
                    </a>
                    <div class="nav-dropdown-divider"></div>
                    <a href="/user/events" class="nav-dropdown-item <?php echo $current_page === 'events' ? 'active' : ''; ?>">
                        <span class="icon">🎉</span> Events
                    </a>
                    <a href="/user/transfer_request" class="nav-dropdown-item <?php echo $current_page === 'transfer_request' ? 'active' : ''; ?>">
                        <span class="icon">🔄</span> Transfer Request
                    </a>
                </div>
            </div>
            
            <!-- Community -->
            <div class="nav-item nav-dropdown">
                <span class="nav-link nav-dropdown-toggle <?php echo in_array($current_page, ['directory', 'quizzes', 'chain_of_command']) ? 'active' : ''; ?>">
                    <span class="icon">🌐</span> Community
                </span>
                <div class="nav-dropdown-menu">
                    <a href="/user/directory" class="nav-dropdown-item <?php echo $current_page === 'directory' ? 'active' : ''; ?>">
                        <span class="icon">👥</span> Member Directory
                    </a>
                    <a href="/user/chain_of_command" class="nav-dropdown-item <?php echo $current_page === 'chain_of_command' ? 'active' : ''; ?>">
                        <span class="icon">🏛️</span> Chain of Command
                    </a>
                    <a href="/user/quizzes" class="nav-dropdown-item <?php echo $current_page === 'quizzes' ? 'active' : ''; ?>">
                        <span class="icon">📝</span> Quizzes
                    </a>
                </div>
            </div>
            
            <?php if (isAdmin() || hasPermission('admin.view')): ?>
            <!-- Admin -->
            <div class="nav-item nav-dropdown">
                <span class="nav-link nav-dropdown-toggle <?php echo strpos($current_page, 'admin') === 0 ? 'active' : ''; ?>">
                    <span class="icon">⚙️</span> Admin
                    <?php if ($nav_pending_users_count > 0): ?>
                        <span class="nav-badge warning"><?php echo $nav_pending_users_count; ?></span>
                    <?php endif; ?>
                </span>
                <div class="nav-dropdown-menu nav-dropdown-mega">
                    <div class="nav-mega-column">
                        <div class="nav-dropdown-header">Users & Apps</div>
                        <a href="/admin/" class="nav-dropdown-item <?php echo $current_page === 'admin_dashboard' ? 'active' : ''; ?>">
                            <span class="icon">👥</span> User Management
                            <?php if ($nav_pending_users_count > 0): ?><span class="nav-badge warning"><?php echo $nav_pending_users_count; ?></span><?php endif; ?>
                        </a>
                        <a href="/admin/applications" class="nav-dropdown-item <?php echo $current_page === 'admin_applications' ? 'active' : ''; ?>">
                            <span class="icon">📋</span> Applications
                        </a>
                        <a href="/admin/roles" class="nav-dropdown-item">
                            <span class="icon">🛡️</span> Roles & Permissions
                        </a>
                        <a href="/admin/activity" class="nav-dropdown-item">
                            <span class="icon">📊</span> Activity Monitor
                        </a>
                    </div>
                    
                    <div class="nav-mega-column">
                        <div class="nav-dropdown-header">Department</div>
                        <a href="/admin/manage_departments" class="nav-dropdown-item">
                            <span class="icon">🏢</span> Departments
                        </a>
                        <a href="/admin/manage_ranks" class="nav-dropdown-item">
                            <span class="icon">🎖️</span> Ranks
                        </a>
                        <a href="/admin/sops" class="nav-dropdown-item">
                            <span class="icon">📜</span> SOPs
                        </a>
                        <a href="/admin/training" class="nav-dropdown-item">
                            <span class="icon">🎓</span> Training
                        </a>
                        <a href="/admin/callsigns" class="nav-dropdown-item">
                            <span class="icon">📻</span> Callsigns
                        </a>
                        <a href="/admin/chain_of_command" class="nav-dropdown-item">
                            <span class="icon">🏛️</span> Chain of Command
                        </a>
                    </div>
                    
                    <div class="nav-mega-column">
                        <div class="nav-dropdown-header">Content</div>
                        <a href="/admin/announcements" class="nav-dropdown-item">
                            <span class="icon">📢</span> Announcements
                        </a>
                        <a href="/admin/documents" class="nav-dropdown-item">
                            <span class="icon">📁</span> Documents
                        </a>
                        <a href="/admin/quizzes" class="nav-dropdown-item">
                            <span class="icon">📝</span> Quizzes
                        </a>
                        <a href="/admin/events" class="nav-dropdown-item">
                            <span class="icon">🎉</span> Events
                        </a>
                        <a href="/admin/shifts" class="nav-dropdown-item">
                            <span class="icon">📆</span> Shifts
                        </a>
                    </div>
                    
                    <div class="nav-mega-column">
                        <div class="nav-dropdown-header">HR & Recognition</div>
                        <a href="/admin/promotions" class="nav-dropdown-item">
                            <span class="icon">⬆️</span> Promotions
                        </a>
                        <a href="/admin/transfers" class="nav-dropdown-item">
                            <span class="icon">🔄</span> Transfers
                        </a>
                        <a href="/admin/conduct" class="nav-dropdown-item">
                            <span class="icon">⚖️</span> Conduct Records
                        </a>
                        <a href="/admin/recognition" class="nav-dropdown-item">
                            <span class="icon">🏆</span> Recognition
                        </a>
                        <a href="/admin/badges" class="nav-dropdown-item">
                            <span class="icon">🎫</span> Badges
                        </a>
                        <a href="/admin/mentorships" class="nav-dropdown-item">
                            <span class="icon">🤝</span> Mentorships
                        </a>
                    </div>
                    
                    <div class="nav-mega-column">
                        <div class="nav-dropdown-header">System</div>
                        <a href="/admin/system_settings" class="nav-dropdown-item">
                            <span class="icon">⚙️</span> Settings
                        </a>
                        <a href="/admin/statistics" class="nav-dropdown-item">
                            <span class="icon">📊</span> Statistics
                        </a>
                        <a href="/admin/audit_log" class="nav-dropdown-item">
                            <span class="icon">📋</span> Audit Log
                        </a>
                        <a href="/admin/security_alerts" class="nav-dropdown-item">
                            <span class="icon">🔒</span> Security Alerts
                        </a>
                        <a href="/admin/sessions" class="nav-dropdown-item">
                            <span class="icon">💻</span> Active Sessions
                        </a>
                        <a href="/admin/maintenance" class="nav-dropdown-item">
                            <span class="icon">🔧</span> Maintenance
                        </a>
                        <a href="/admin/backup" class="nav-dropdown-item">
                            <span class="icon">💾</span> Backup
                        </a>
                    </div>
                    
                    <div class="nav-mega-column">
                        <div class="nav-dropdown-header">Integrations</div>
                        <a href="/admin/discord" class="nav-dropdown-item">
                            <span class="icon">🎮</span> Discord
                        </a>
                        <a href="/admin/smtp_settings" class="nav-dropdown-item">
                            <span class="icon">📧</span> Email (SMTP)
                        </a>
                        <a href="/admin/webhook_logs" class="nav-dropdown-item">
                            <span class="icon">🔗</span> Webhook Logs
                        </a>
                        <a href="/admin/api_keys" class="nav-dropdown-item">
                            <span class="icon">🔑</span> API Keys
                        </a>
                        <a href="/admin/api_docs" class="nav-dropdown-item">
                            <span class="icon">📖</span> API Docs
                        </a>
                        <div class="nav-dropdown-divider"></div>
                        <a href="/admin/custom_fields" class="nav-dropdown-item">
                            <span class="icon">📝</span> Custom Fields
                        </a>
                        <a href="/admin/password_policies" class="nav-dropdown-item">
                            <span class="icon">🔐</span> Password Policies
                        </a>
                        <a href="/admin/license" class="nav-dropdown-item">
                            <span class="icon">📄</span> License
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Right Section -->
        <div class="navbar-right">
            <!-- Theme Toggle -->
            <button class="nav-icon-btn" onclick="toggleTheme()" title="Toggle dark/light mode" style="font-size:16px; background:transparent; cursor:pointer;">
                <span class="theme-icon-dark">🌙</span>
                <span class="theme-icon-light">☀️</span>
            </button>
            <!-- Notifications -->
            <a href="/user/notifications" class="nav-icon-btn" title="Notifications">
                🔔
                <?php if ($nav_notif_count > 0): ?>
                    <span class="badge-dot"></span>
                <?php endif; ?>
            </a>
            
            <!-- User Menu -->
            <div class="user-dropdown">
                <div class="navbar-user">
                    <?php if (!empty($user_avatar) && file_exists($_SERVER['DOCUMENT_ROOT'] . $user_avatar)): ?>
                        <img src="<?php echo htmlspecialchars($user_avatar); ?>" alt="Avatar" class="navbar-user-avatar">
                    <?php else: ?>
                        <div class="navbar-user-avatar-placeholder">
                            <?php echo strtoupper(substr($username, 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <div class="navbar-user-info">
                        <span class="navbar-username"><?php echo htmlspecialchars($username); ?></span>
                        <span class="navbar-user-role"><?php echo isAdmin() ? 'Administrator' : 'Member'; ?></span>
                    </div>
                    <span class="navbar-user-chevron">▼</span>
                </div>
                <div class="user-dropdown-menu">
                    <div class="nav-dropdown-header">Account</div>
                    <a href="/user/profile" class="nav-dropdown-item">
                        <span class="icon">👤</span> My Profile
                    </a>
                    <a href="/user/security" class="nav-dropdown-item">
                        <span class="icon">🔒</span> Security Settings
                    </a>
                    <a href="/user/two_factor" class="nav-dropdown-item">
                        <span class="icon">🔐</span> Two-Factor Auth
                    </a>
                    <a href="/user/email_preferences" class="nav-dropdown-item">
                        <span class="icon">📧</span> Email Preferences
                    </a>
                    <a href="/user/login_history" class="nav-dropdown-item">
                        <span class="icon">📋</span> Login History
                    </a>
                    <div class="nav-dropdown-divider"></div>
                    <a href="/auth/logout" class="nav-dropdown-item" style="color: var(--danger);">
                        <span class="icon">🚪</span> Logout
                    </a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Not logged in -->
        <div class="navbar-right">
            <button class="nav-icon-btn" onclick="toggleTheme()" title="Toggle dark/light mode" style="font-size:16px; background:transparent; cursor:pointer;">
                <span class="theme-icon-dark">🌙</span>
                <span class="theme-icon-light">☀️</span>
            </button>
            <a href="/auth/login" class="btn btn-ghost">Login</a>
            <a href="/auth/register" class="btn btn-primary">Register</a>
        </div>
        <?php endif; ?>
    </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const navToggle = document.getElementById('navbarToggle');
    const navMenu = document.getElementById('navbarNav');
    
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', function() {
            this.classList.toggle('active');
            navMenu.classList.toggle('open');
        });
    }
    
    // Dropdown toggles for mobile
    document.querySelectorAll('.nav-dropdown-toggle').forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            if (window.innerWidth <= 1024) {
                e.preventDefault();
                const dropdown = this.closest('.nav-dropdown');
                dropdown.classList.toggle('open');
            }
        });
    });
    
    // Submenu toggles
    document.querySelectorAll('.nav-submenu-toggle').forEach(function(toggle) {
        toggle.addEventListener('click', function() {
            const submenu = this.closest('.nav-submenu');
            submenu.classList.toggle('open');
        });
    });
});
</script>
