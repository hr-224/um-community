<?php
/**
 * ============================================================
 *  Ultimate Mods – FiveM Community Manager
 * ============================================================
 *  Modern UI Styles - Discord/Stripe Inspired Design
 *  Version 2.0
 * ============================================================
 */

$colors = getThemeColors();

// Modern color overrides - flat, dark, bold
$modern = [
    'bg_base' => '#0a0a0a',
    'bg_primary' => '#0f0f0f', 
    'bg_secondary' => '#141414',
    'bg_card' => '#181818',
    'bg_elevated' => '#1e1e1e',
    'bg_hover' => '#252525',
    'bg_input' => '#1a1a1a',
    'border' => '#2a2a2a',
    'border_light' => '#333333',
    'accent' => $colors['primary'] ?? '#5865F2',
    'accent_hover' => '#4752c4',
    'accent_muted' => 'rgba(88, 101, 242, 0.15)',
    'success' => '#23a559',
    'success_muted' => 'rgba(35, 165, 89, 0.15)',
    'warning' => '#f0b232',
    'warning_muted' => 'rgba(240, 178, 50, 0.15)',
    'danger' => '#da373c',
    'danger_muted' => 'rgba(218, 55, 60, 0.15)',
    'info' => '#5865F2',
    'text_primary' => '#f2f3f5',
    'text_secondary' => '#b5bac1',
    'text_muted' => '#80848e',
    'text_faint' => '#4e5058',
];
?>
<script>(function(){var m=document.cookie.match(/\bum_theme=([^;]+)/);document.documentElement.setAttribute('data-theme',m?m[1]:'dark');})();</script>
<!-- Modern UI Styles v2.0 -->
<style>
/* =====================================================
   CSS RESET & VARIABLES
   ===================================================== */
*, *::before, *::after { 
    margin: 0; 
    padding: 0; 
    box-sizing: border-box; 
}

:root {
    /* Core Colors */
    --bg-base: <?php echo $modern['bg_base']; ?>;
    --bg-primary: <?php echo $modern['bg_primary']; ?>;
    --bg-secondary: <?php echo $modern['bg_secondary']; ?>;
    --bg-card: <?php echo $modern['bg_card']; ?>;
    --bg-elevated: <?php echo $modern['bg_elevated']; ?>;
    --bg-hover: <?php echo $modern['bg_hover']; ?>;
    --bg-input: <?php echo $modern['bg_input']; ?>;
    
    /* Borders */
    --border: <?php echo $modern['border']; ?>;
    --border-light: <?php echo $modern['border_light']; ?>;
    
    /* Accent - Discord Blurple */
    --accent: <?php echo $modern['accent']; ?>;
    --accent-hover: <?php echo $modern['accent_hover']; ?>;
    --accent-muted: <?php echo $modern['accent_muted']; ?>;
    --primary: var(--accent);
    --secondary: var(--accent-hover);
    
    /* Semantic Colors */
    --success: <?php echo $modern['success']; ?>;
    --success-muted: <?php echo $modern['success_muted']; ?>;
    --warning: <?php echo $modern['warning']; ?>;
    --warning-muted: <?php echo $modern['warning_muted']; ?>;
    --danger: <?php echo $modern['danger']; ?>;
    --danger-muted: <?php echo $modern['danger_muted']; ?>;
    --info: <?php echo $modern['info']; ?>;
    
    /* Typography */
    --text-primary: <?php echo $modern['text_primary']; ?>;
    --text-secondary: <?php echo $modern['text_secondary']; ?>;
    --text-muted: <?php echo $modern['text_muted']; ?>;
    --text-faint: <?php echo $modern['text_faint']; ?>;
    
    /* Shadows */
    --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.3);
    --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.4);
    --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.5);
    --shadow-xl: 0 16px 48px rgba(0, 0, 0, 0.6);
    --shadow-accent: 0 4px 14px rgba(88, 101, 242, 0.35);
    
    /* Spacing */
    --radius-sm: 4px;
    --radius-md: 8px;
    --radius-lg: 12px;
    --radius-xl: 16px;
    
    /* Transitions */
    --transition-fast: 0.1s ease;
    --transition-normal: 0.2s ease;
    --transition-slow: 0.3s ease;
    
    /* Legacy compatibility */
    --glass-bg: var(--bg-elevated);
    --glass-bg-hover: var(--bg-hover);
    --border-light: var(--border);
    --border-medium: var(--border);
}

/* =====================================================
   LIGHT MODE OVERRIDES
   ===================================================== */
[data-theme="light"] {
    --bg-base:      #f0f2f5;
    --bg-primary:   #ffffff;
    --bg-secondary: #f2f3f5;
    --bg-card:      #ffffff;
    --bg-elevated:  #eaecf0;
    --bg-hover:     #d4d7dc;
    --bg-input:     #fafafa;

    --border:       #e0e2e8;
    --border-light: #d4d7dc;

    --text-primary:   #060607;
    --text-secondary: #313338;
    --text-muted:     #4e5058;
    --text-faint:     #80848e;

    --shadow-sm:     0 1px 2px rgba(0,0,0,0.06);
    --shadow-md:     0 4px 12px rgba(0,0,0,0.08);
    --shadow-lg:     0 8px 24px rgba(0,0,0,0.12);
    --shadow-xl:     0 16px 48px rgba(0,0,0,0.16);
    --shadow-accent: 0 4px 14px rgba(88,101,242,0.25);
}

/* Theme transition — only fires during toggle, never on initial page load */
.theme-transitioning,
.theme-transitioning * {
    transition: background-color 0.25s ease, color 0.25s ease,
                border-color 0.25s ease, box-shadow 0.25s ease !important;
}

/* =====================================================
   BASE STYLES
   ===================================================== */
html {
    background: var(--bg-base);
    overflow-x: hidden;
    scroll-behavior: smooth;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    background: var(--bg-primary);
    min-height: 100vh;
    color: var(--text-primary);
    line-height: 1.5;
    font-size: 14px;
    font-weight: 400;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

/* Optional background image support */
<?php if (!empty($colors['bg_image'])): ?>
body::before {
    content: '';
    position: fixed;
    inset: 0;
    background-image: url('<?php echo htmlspecialchars($colors['bg_image']); ?>');
    background-size: cover;
    background-position: center;
    opacity: 0.15;
    z-index: -1;
}
<?php endif; ?>

/* =====================================================
   TYPOGRAPHY
   ===================================================== */
h1, h2, h3, h4, h5, h6 {
    font-weight: 700;
    line-height: 1.3;
    color: var(--text-primary);
    letter-spacing: -0.02em;
}

h1 { font-size: 28px; }
h2 { font-size: 20px; }
h3 { font-size: 16px; }
h4 { font-size: 14px; }

p { color: var(--text-secondary); }

a {
    color: var(--accent);
    text-decoration: none;
    transition: color var(--transition-fast);
}

a:hover {
    color: var(--accent-hover);
}

/* =====================================================
   SCROLLBAR
   ===================================================== */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: var(--bg-secondary);
}

::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--text-faint);
}

/* =====================================================
   NAVIGATION BAR
   ===================================================== */
.navbar {
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border);
    padding: 0 24px;
    height: 60px;
    display: flex;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 1000;
    
}

.navbar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 17px;
    font-weight: 700;
    color: var(--text-primary);
    text-decoration: none;
}

.navbar-brand img {
    height: 32px;
    width: auto;
    border-radius: var(--radius-sm);
}

.navbar-brand .brand-dot {
    width: 8px;
    height: 8px;
    background: var(--accent);
    border-radius: 50%;
    box-shadow: 0 0 8px var(--accent);
}

.navbar-menu {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-left: 40px;
}

.navbar-menu a, .nav-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border-radius: var(--radius-md);
    color: var(--text-muted);
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition: all var(--transition-fast);
}

.navbar-menu a:hover, .nav-item:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.navbar-menu a.active, .nav-item.active {
    background: var(--bg-elevated);
    color: var(--text-primary);
}

.navbar-right {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 12px;
}

.navbar-user {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 12px 6px 6px;
    border-radius: var(--radius-md);
    background: var(--bg-elevated);
    cursor: pointer;
    transition: background var(--transition-fast);
}

.navbar-user:hover {
    background: var(--bg-hover);
}

.navbar-avatar {
    width: 32px;
    height: 32px;
    border-radius: var(--radius-md);
    object-fit: cover;
}

.navbar-username {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
}

/* Mobile Nav Toggle */
.nav-toggle {
    display: none;
    background: none;
    border: none;
    color: var(--text-primary);
    font-size: 24px;
    cursor: pointer;
    padding: 8px;
}

/* =====================================================
   LAYOUT & CONTAINERS
   ===================================================== */
.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 32px 24px;
}

.container-narrow {
    max-width: 800px;
}

.container-wide {
    max-width: 1600px;
}

.page-header {
    margin-bottom: 32px;
}

.page-header h1 {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}

.page-header p {
    color: var(--text-muted);
    font-size: 14px;
}

.page-header .header-actions {
    display: flex;
    gap: 12px;
    margin-top: 16px;
}

/* Grid Layouts */
.grid {
    display: grid;
    gap: 20px;
}

.grid-2 { grid-template-columns: repeat(2, 1fr); }
.grid-3 { grid-template-columns: repeat(3, 1fr); }
.grid-4 { grid-template-columns: repeat(4, 1fr); }
.grid-auto { grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }

@media (max-width: 1024px) {
    .grid-4 { grid-template-columns: repeat(2, 1fr); }
    .grid-3 { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
    .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
}

/* =====================================================
   CARDS
   ===================================================== */
.card, .section {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    padding: 24px;
    box-shadow: var(--shadow-md);
}

.card-header, .section h2 {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border);
}

.section h2 {
    font-size: 16px;
    font-weight: 600;
    gap: 10px;
    border-bottom: none;
    padding-bottom: 0;
}

.section h2 .icon {
    font-size: 18px;
}

.card-title {
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.card-subtitle {
    font-size: 13px;
    color: var(--text-muted);
}

.card-body {
    color: var(--text-secondary);
}

.card-footer {
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 12px;
}

/* Card with accent top border */
.card-accent {
    position: relative;
    overflow: hidden;
}

.card-accent::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--accent);
}

.card-accent.success::before { background: var(--success); }
.card-accent.warning::before { background: var(--warning); }
.card-accent.danger::before { background: var(--danger); }

/* =====================================================
   STAT CARDS
   ===================================================== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    background: var(--bg-card);
    border-radius: var(--radius-lg);
    padding: 24px;
    box-shadow: var(--shadow-md);
    position: relative;
    overflow: hidden;
    transition: transform var(--transition-fast), box-shadow var(--transition-fast);
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--accent);
}

.stat-card.success::before { background: var(--success); }
.stat-card.warning::before { background: var(--warning); }
.stat-card.danger::before { background: var(--danger); }

.stat-value {
    font-size: 36px;
    font-weight: 700;
    color: var(--text-primary);
    letter-spacing: -0.02em;
    line-height: 1;
}

.stat-label {
    font-size: 13px;
    font-weight: 500;
    color: var(--text-muted);
    margin-top: 8px;
}

.stat-change {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    font-weight: 600;
    margin-top: 8px;
    padding: 4px 8px;
    border-radius: var(--radius-sm);
}

.stat-change.positive {
    color: var(--success);
    background: var(--success-muted);
}

.stat-change.negative {
    color: var(--danger);
    background: var(--danger-muted);
}

/* =====================================================
   BUTTONS
   ===================================================== */
.btn, button[type="submit"], input[type="submit"] {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 18px;
    font-size: 14px;
    font-weight: 600;
    font-family: inherit;
    border-radius: var(--radius-md);
    border: none;
    cursor: pointer;
    transition: all var(--transition-fast);
    text-decoration: none;
    white-space: nowrap;
}

.btn-primary, button[type="submit"], input[type="submit"] {
    background: var(--accent);
    color: var(--text-primary);
    box-shadow: var(--shadow-accent);
}

.btn-primary:hover, button[type="submit"]:hover, input[type="submit"]:hover {
    background: var(--accent-hover);
    transform: translateY(-1px);
}

.btn-secondary {
    background: var(--bg-elevated);
    color: var(--text-primary);
    border: 1px solid var(--border);
}

.btn-secondary:hover {
    background: var(--bg-hover);
    border-color: var(--border);
}

.btn-success {
    background: var(--success);
    color: var(--text-primary);
}

.btn-success:hover {
    filter: brightness(1.1);
}

.btn-warning {
    background: var(--warning);
    color: #000;
}

.btn-warning:hover {
    filter: brightness(1.1);
}

.btn-danger, .btn-delete {
    background: var(--danger);
    color: var(--text-primary);
}

.btn-danger:hover, .btn-delete:hover {
    filter: brightness(1.1);
}

.btn-ghost {
    background: transparent;
    color: var(--text-secondary);
}

.btn-ghost:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
}

.btn-lg {
    padding: 14px 28px;
    font-size: 16px;
}

.btn-icon {
    width: 36px;
    height: 36px;
    padding: 0;
}

.btn-icon.btn-sm {
    width: 28px;
    height: 28px;
}

.btn:disabled, button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none !important;
}

.btn-edit {
    background: var(--accent-muted);
    color: var(--accent);
}

.btn-edit:hover {
    background: var(--accent);
    color: var(--text-primary);
}

/* Button Groups */
.btn-group {
    display: flex;
    gap: 8px;
}

.btn-group .btn {
    flex: 1;
}

/* =====================================================
   FORM ELEMENTS
   ===================================================== */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 8px;
}

.form-group label.required::after {
    content: ' *';
    color: var(--danger);
}

input, select, textarea {
    width: 100%;
    padding: 12px 16px;
    font-size: 14px;
    font-family: inherit;
    color: var(--text-primary);
    background: var(--bg-input);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
}

input:focus, select:focus, textarea:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-muted);
}

input::placeholder, textarea::placeholder {
    color: var(--text-faint);
}

select {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2380848e' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
    background-size: 12px;
    padding-right: 40px;
    cursor: pointer;
}

select option {
    background: var(--bg-elevated);
    color: var(--text-primary);
    padding: 12px;
}

textarea {
    min-height: 120px;
    resize: vertical;
    line-height: 1.6;
}

input[type="checkbox"], input[type="radio"] {
    width: 18px;
    height: 18px;
    min-width: 18px;
    padding: 0;
    cursor: pointer;
    accent-color: var(--accent);
    -webkit-appearance: auto;
    -moz-appearance: auto;
    appearance: auto;
}

input[type="date"], input[type="datetime-local"], input[type="time"] {
    color-scheme: dark;
}

input[type="color"] {
    width: 50px;
    height: 44px;
    padding: 4px;
    cursor: pointer;
}

input[type="file"] {
    padding: 10px;
    cursor: pointer;
}

input[type="file"]::file-selector-button {
    background: var(--accent);
    color: var(--text-primary);
    border: none;
    padding: 8px 16px;
    border-radius: var(--radius-sm);
    font-weight: 600;
    cursor: pointer;
    margin-right: 12px;
}

.form-hint {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 6px;
}

.form-error {
    font-size: 12px;
    color: var(--danger);
    margin-top: 6px;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

@media (max-width: 600px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}

/* Toggle Switch */
.toggle {
    position: relative;
    width: 44px;
    height: 24px;
}

.toggle input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    inset: 0;
    background: var(--bg-hover);
    border-radius: var(--radius-lg);
    transition: background var(--transition-fast);
}

.toggle-slider::before {
    position: absolute;
    content: '';
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background: var(--text-primary);
    border-radius: 50%;
    transition: transform var(--transition-fast);
}

.toggle input:checked + .toggle-slider {
    background: var(--accent);
}

.toggle input:checked + .toggle-slider::before {
    transform: translateX(20px);
}

/* =====================================================
   TABLES
   ===================================================== */
table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

thead {
    background: var(--bg-elevated);
}

th {
    text-align: left;
    padding: 14px 16px;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid var(--border);
}

td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    color: var(--text-secondary);
}

tr:last-child td {
    border-bottom: none;
}

tbody tr {
    transition: background var(--transition-fast);
}

tbody tr:hover {
    background: var(--bg-hover);
}

/* Table Actions */
td .btn-group, td .actions {
    display: flex;
    gap: 6px;
}

/* Responsive Table */
.table-responsive {
    overflow-x: auto;
    border-radius: var(--radius-lg);
    background: var(--bg-card);
}

.table-responsive table {
    min-width: 700px;
}

/* =====================================================
   BADGES & TAGS
   ===================================================== */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    font-size: 12px;
    font-weight: 600;
    border-radius: var(--radius-lg);
    background: var(--bg-elevated);
    color: var(--text-secondary);
}

.badge-primary {
    background: var(--accent-muted);
    color: var(--accent);
}

.badge-success {
    background: var(--success-muted);
    color: var(--success);
}

.badge-warning {
    background: var(--warning-muted);
    color: var(--warning);
}

.badge-danger {
    background: var(--danger-muted);
    color: var(--danger);
}

.badge-sm {
    padding: 2px 8px;
    font-size: 11px;
}

/* Status Indicator */
.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--text-muted);
}

.status-dot.online { background: var(--success); box-shadow: 0 0 8px var(--success); }
.status-dot.away { background: var(--warning); }
.status-dot.offline { background: var(--text-faint); }

/* =====================================================
   ALERTS & MESSAGES
   ===================================================== */
.alert, .message {
    padding: 16px 20px;
    border-radius: var(--radius-md);
    font-size: 14px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 16px;
}

.alert-icon {
    font-size: 18px;
    flex-shrink: 0;
}

.alert-success, .message.success {
    background: var(--success-muted);
    color: var(--success);
    border: 1px solid rgba(35, 165, 89, 0.3);
}

.alert-warning, .message.warning {
    background: var(--warning-muted);
    color: var(--warning);
    border: 1px solid rgba(240, 178, 50, 0.3);
}

.alert-danger, .alert-error, .message.error {
    background: var(--danger-muted);
    color: var(--danger);
    border: 1px solid rgba(218, 55, 60, 0.3);
}

.alert-info, .message.info {
    background: var(--accent-muted);
    color: var(--accent);
    border: 1px solid rgba(88, 101, 242, 0.3);
}

/* =====================================================
   MODALS
   ===================================================== */
.modal {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity var(--transition-normal), visibility var(--transition-normal);
    padding: 24px;
}

.modal.active, .modal.show, .modal[style*="block"], .modal[style*="flex"] {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
}

.modal-content {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-xl);
    width: 100%;
    max-width: 500px;
    max-height: 90vh;
    overflow: hidden;
    transform: translateY(20px);
    transition: transform var(--transition-normal);
}

.modal.active .modal-content, .modal.show .modal-content, .modal[style*="block"] .modal-content, .modal[style*="flex"] .modal-content {
    transform: translateY(0);
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    background: var(--bg-elevated);
}

.modal-header h3 {
    font-size: 18px;
    font-weight: 700;
    margin: 0;
    color: var(--text-primary);
}

/* Support both .modal-close and .close */
.modal-close, .modal .close {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-hover);
    border: none;
    border-radius: var(--radius-md);
    color: var(--text-muted);
    font-size: 20px;
    cursor: pointer;
    transition: all var(--transition-fast);
    flex-shrink: 0;
}

.modal-close:hover, .modal .close:hover {
    background: var(--danger-muted);
    color: var(--danger);
}

.modal-body {
    padding: 24px;
    overflow-y: auto;
    max-height: calc(90vh - 140px);
}

/* Direct form children in modal-content get padding */
.modal-content > form,
.modal-content > .modal-header + form {
    padding: 24px;
    overflow-y: auto;
    max-height: calc(90vh - 80px);
}

/* When modal has header, adjust form max-height */
.modal-content:has(.modal-header) > form {
    max-height: calc(90vh - 160px);
}

.modal-content form .form-group {
    margin-bottom: 20px;
}

.modal-content form .form-group:last-of-type {
    margin-bottom: 24px;
}

.modal-content form label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    font-size: 14px;
    color: var(--text-primary);
}

.modal-content form input[type="text"],
.modal-content form input[type="email"],
.modal-content form input[type="password"],
.modal-content form input[type="number"],
.modal-content form input[type="date"],
.modal-content form input[type="datetime-local"],
.modal-content form input[type="color"],
.modal-content form select,
.modal-content form textarea {
    width: 100%;
    padding: 12px 16px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    color: var(--text-primary);
    font-size: 14px;
    transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
    box-sizing: border-box;
}

.modal-content form input[type="color"] {
    padding: 8px;
    height: 48px;
    cursor: pointer;
}

.modal-content form input:focus,
.modal-content form select:focus,
.modal-content form textarea:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-muted);
}

.modal-content form .btn {
    margin-top: 8px;
}

.modal-footer {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    padding: 16px 24px;
    border-top: 1px solid var(--border);
    background: var(--bg-elevated);
}

/* Modal footer inside form - cancel form padding on footer */
.modal-content > form .modal-footer {
    margin: 0 -24px -24px -24px;
    padding: 16px 24px;
}

.modal-lg .modal-content {
    max-width: 700px;
}

.modal-xl .modal-content {
    max-width: 900px;
}

/* =====================================================
   DROPDOWNS
   ===================================================== */
.dropdown {
    position: relative;
    display: inline-block;
}

.dropdown-menu {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);
    min-width: 200px;
    padding: 8px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all var(--transition-fast);
    z-index: 1500;
}

.dropdown.active .dropdown-menu, .dropdown-menu.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    border-radius: var(--radius-md);
    color: var(--text-secondary);
    font-size: 14px;
    cursor: pointer;
    transition: all var(--transition-fast);
}

.dropdown-item:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.dropdown-item.danger {
    color: var(--danger);
}

.dropdown-item.danger:hover {
    background: var(--danger-muted);
}

.dropdown-divider {
    height: 1px;
    background: var(--border);
    margin: 8px 0;
}

/* =====================================================
   TABS
   ===================================================== */
.tabs {
    display: flex;
    gap: 4px;
    padding: 4px;
    background: var(--bg-secondary);
    border-radius: var(--radius-lg);
    margin-bottom: 24px;
}

.tab {
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 500;
    color: var(--text-muted);
    background: transparent;
    border: none;
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all var(--transition-fast);
}

.tab:hover {
    color: var(--text-secondary);
}

.tab.active {
    background: var(--bg-card);
    color: var(--text-primary);
    box-shadow: var(--shadow-sm);
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

/* =====================================================
   PAGINATION
   ===================================================== */
.pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    margin-top: 24px;
}

.page-btn {
    min-width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-elevated);
    border: none;
    border-radius: var(--radius-md);
    color: var(--text-secondary);
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all var(--transition-fast);
    text-decoration: none;
}

.page-btn:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.page-btn.active {
    background: var(--accent);
    color: var(--text-primary);
}

.page-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.page-info {
    color: var(--text-muted);
    font-size: 13px;
    margin: 0 16px;
}

/* =====================================================
   AVATARS
   ===================================================== */
.avatar {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-md);
    object-fit: cover;
    background: var(--bg-elevated);
}

.avatar-sm { width: 32px; height: 32px; }
.avatar-lg { width: 56px; height: 56px; }
.avatar-xl { width: 80px; height: 80px; }

.avatar-circle {
    border-radius: 50%;
}

.avatar-stack {
    display: flex;
}

.avatar-stack .avatar {
    margin-left: -12px;
    border: 2px solid var(--bg-card);
}

.avatar-stack .avatar:first-child {
    margin-left: 0;
}

/* Avatar Placeholder */
.avatar-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--accent);
    color: var(--text-primary);
    font-weight: 600;
    font-size: 16px;
}

.avatar-placeholder.avatar-sm { font-size: 13px; }
.avatar-placeholder.avatar-lg { font-size: 20px; }

/* =====================================================
   EMPTY STATES
   ===================================================== */
.empty-state {
    text-align: center;
    padding: 60px 24px;
    color: var(--text-muted);
}

.empty-state-icon {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.5;
}

.empty-state h3 {
    font-size: 18px;
    color: var(--text-secondary);
    margin-bottom: 8px;
}

.empty-state p {
    font-size: 14px;
    max-width: 400px;
    margin: 0 auto 24px;
}

/* =====================================================
   LOADING STATES
   ===================================================== */
.loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px;
}

.spinner {
    width: 32px;
    height: 32px;
    border: 3px solid var(--border);
    border-top-color: var(--accent);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.skeleton {
    background: linear-gradient(90deg, var(--bg-elevated) 25%, var(--bg-hover) 50%, var(--bg-elevated) 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
    border-radius: var(--radius-md);
}

@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* =====================================================
   NOTIFICATIONS & TOASTS
   ===================================================== */
.toast-container {
    position: fixed;
    top: 24px;
    right: 24px;
    z-index: 3000;
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-width: 400px;
}

.toast {
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 16px 20px;
    box-shadow: var(--shadow-lg);
    display: flex;
    align-items: flex-start;
    gap: 12px;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.toast-icon {
    font-size: 20px;
    flex-shrink: 0;
}

.toast-content {
    flex: 1;
}

.toast-title {
    font-weight: 600;
    margin-bottom: 4px;
}

.toast-message {
    font-size: 14px;
    color: var(--text-secondary);
}

.toast-close {
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    padding: 4px;
    font-size: 18px;
}

.toast.success { border-left: 3px solid var(--success); }
.toast.success .toast-icon { color: var(--success); }

.toast.error { border-left: 3px solid var(--danger); }
.toast.error .toast-icon { color: var(--danger); }

.toast.warning { border-left: 3px solid var(--warning); }
.toast.warning .toast-icon { color: var(--warning); }

.toast.info { border-left: 3px solid var(--accent); }
.toast.info .toast-icon { color: var(--accent); }

/* =====================================================
   SIDEBAR LAYOUT
   ===================================================== */
.layout-sidebar {
    display: grid;
    grid-template-columns: 260px 1fr;
    min-height: 100vh;
}

.sidebar {
    background: var(--bg-secondary);
    border-right: 1px solid var(--border);
    padding: 24px 16px;
    position: sticky;
    top: 0;
    height: 100vh;
    overflow-y: auto;
}

.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 12px 24px;
    margin-bottom: 16px;
    border-bottom: 1px solid var(--border);
}

.sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.sidebar-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-radius: var(--radius-md);
    color: var(--text-muted);
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition: all var(--transition-fast);
}

.sidebar-link:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.sidebar-link.active {
    background: var(--accent-muted);
    color: var(--accent);
}

.sidebar-link .icon {
    font-size: 18px;
    width: 24px;
    text-align: center;
}

.sidebar-section {
    margin-top: 24px;
    padding-top: 16px;
    border-top: 1px solid var(--border);
}

.sidebar-section-title {
    font-size: 11px;
    font-weight: 700;
    color: var(--text-faint);
    text-transform: uppercase;
    letter-spacing: 0.1em;
    padding: 0 14px;
    margin-bottom: 12px;
}

/* =====================================================
   SEARCH
   ===================================================== */
.search-bar {
    position: relative;
    display: flex;
    align-items: center;
}

.search-bar input {
    padding-left: 44px;
    background: var(--bg-input);
}

.search-bar .search-icon {
    position: absolute;
    left: 14px;
    color: var(--text-muted);
    pointer-events: none;
}

.search-bar .search-clear {
    position: absolute;
    right: 12px;
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    padding: 4px;
}

/* =====================================================
   FILTERS
   ===================================================== */
.filters {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 24px;
    align-items: flex-end;
}

.filters .form-group {
    margin-bottom: 0;
    min-width: 150px;
}

.filters select, .filters input {
    height: 42px;
}

.filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    font-size: 13px;
    color: var(--text-secondary);
}

.filter-chip.active {
    background: var(--accent-muted);
    border-color: var(--accent);
    color: var(--accent);
}

.filter-chip-remove {
    background: none;
    border: none;
    color: inherit;
    cursor: pointer;
    padding: 0;
    font-size: 16px;
    line-height: 1;
}

/* =====================================================
   PROGRESS BARS
   ===================================================== */
.progress {
    height: 8px;
    background: var(--bg-hover);
    border-radius: var(--radius-sm);
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    background: var(--accent);
    border-radius: var(--radius-sm);
    transition: width var(--transition-slow);
}

.progress-bar.success { background: var(--success); }
.progress-bar.warning { background: var(--warning); }
.progress-bar.danger { background: var(--danger); }

/* =====================================================
   LISTS
   ===================================================== */
.list {
    display: flex;
    flex-direction: column;
}

.list-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    transition: background var(--transition-fast);
}

.list-item:last-child {
    border-bottom: none;
}

.list-item:hover {
    background: var(--bg-hover);
}

.list-item-content {
    flex: 1;
    min-width: 0;
}

.list-item-title {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 2px;
}

.list-item-subtitle {
    font-size: 13px;
    color: var(--text-muted);
}

.list-item-action {
    flex-shrink: 0;
}

/* =====================================================
   TOOLTIPS
   ===================================================== */
[data-tooltip] {
    position: relative;
}

[data-tooltip]::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    padding: 6px 12px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    font-size: 12px;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transition: all var(--transition-fast);
    z-index: 1000;
    margin-bottom: 8px;
    box-shadow: var(--shadow-md);
}

[data-tooltip]:hover::after {
    opacity: 1;
    visibility: visible;
}

/* =====================================================
   RESPONSIVE
   ===================================================== */
@media (max-width: 1024px) {
    .layout-sidebar {
        grid-template-columns: 1fr;
    }
    
    .sidebar {
        display: none;
    }
    
    .nav-toggle {
        display: block;
    }
}

@media (max-width: 768px) {
    .container {
        padding: 20px 16px;
    }
    
    .navbar {
        padding: 0 16px;
    }
    
    .navbar-menu {
        display: none;
    }
    
    .page-header h1 {
        font-size: 22px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .stat-value {
        font-size: 28px;
    }
    
    .modal-content {
        margin: 16px;
        max-height: calc(100vh - 32px);
    }
    
    .modal-body {
        padding: 20px;
    }
    
    .toast-container {
        left: 16px;
        right: 16px;
        max-width: none;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .filters {
        flex-direction: column;
    }
    
    .filters .form-group {
        width: 100%;
    }
    
    .btn-group {
        flex-direction: column;
    }
    
    table {
        font-size: 13px;
    }
    
    th, td {
        padding: 10px 12px;
    }
}

/* =====================================================
   UTILITY CLASSES
   ===================================================== */
.text-center { text-align: center; }
.text-right { text-align: right; }
.text-left { text-align: left; }

.text-primary { color: var(--text-primary); }
.text-secondary { color: var(--text-secondary); }
.text-muted { color: var(--text-muted); }
.text-accent { color: var(--accent); }
.text-success { color: var(--success); }
.text-warning { color: var(--warning); }
.text-danger { color: var(--danger); }

.bg-elevated { background: var(--bg-elevated); }
.bg-card { background: var(--bg-card); }

.rounded { border-radius: var(--radius-md); }
.rounded-lg { border-radius: var(--radius-lg); }
.rounded-full { border-radius: 9999px; }

.shadow { box-shadow: var(--shadow-md); }
.shadow-lg { box-shadow: var(--shadow-lg); }

.mt-0 { margin-top: 0; }
.mt-1 { margin-top: 8px; }
.mt-2 { margin-top: 16px; }
.mt-3 { margin-top: 24px; }
.mt-4 { margin-top: 32px; }

.mb-0 { margin-bottom: 0; }
.mb-1 { margin-bottom: 8px; }
.mb-2 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 24px; }
.mb-4 { margin-bottom: 32px; }

.p-0 { padding: 0; }
.p-1 { padding: 8px; }
.p-2 { padding: 16px; }
.p-3 { padding: 24px; }
.p-4 { padding: 32px; }

.flex { display: flex; }
.flex-center { display: flex; align-items: center; justify-content: center; }
.flex-between { display: flex; align-items: center; justify-content: space-between; }
.flex-col { display: flex; flex-direction: column; }
.gap-1 { gap: 8px; }
.gap-2 { gap: 16px; }
.gap-3 { gap: 24px; }

.hidden { display: none; }
.visible { visibility: visible; }
.invisible { visibility: hidden; }

.truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    border: 0;
}

/* =====================================================
   HTMX INTEGRATION
   ===================================================== */
.htmx-request {
    opacity: 0.7;
    pointer-events: none;
}

.htmx-request .spinner {
    display: block;
}

.htmx-swapping {
    opacity: 0;
    transition: opacity 0.15s ease-out;
}

.htmx-settling {
    opacity: 1;
    transition: opacity 0.15s ease-in;
}

/* =====================================================
   VERSION FOOTER
   ===================================================== */
.app-version-footer {
    position: fixed;
    bottom: 12px;
    right: 16px;
    font-size: 11px;
    font-weight: 500;
    color: var(--text-faint);
    background: var(--bg-secondary);
    padding: 4px 10px;
    border-radius: var(--radius-sm);
    z-index: 100;
}

/* =====================================================
   PRINT STYLES
   ===================================================== */
@media print {
    body {
        background: white;
        color: black;
    }
    
    .navbar, .sidebar, .toast-container, .app-version-footer {
        display: none;
    }
    
    .card, .section {
        box-shadow: none;
        border: 1px solid #ddd;
    }
}
</style>

<!-- htmx Library -->
<script src="/assets/js/htmx.min.js"></script>

<!-- Global Scripts -->
<script>
// Configure htmx
document.addEventListener('DOMContentLoaded', function() {
    // htmx configuration
    if (typeof htmx !== 'undefined') {
        htmx.config.defaultSwapStyle = 'outerHTML';
        htmx.config.historyCacheSize = 0;
        
        // Handle toast messages from htmx responses
        document.body.addEventListener('htmx:afterRequest', function(evt) {
            const xhr = evt.detail.xhr;
            if (xhr) {
                const toastMessage = xhr.getResponseHeader('X-Toast-Message');
                const toastType = xhr.getResponseHeader('X-Toast-Type') || 'info';
                if (toastMessage && typeof Toast !== 'undefined') {
                    Toast[toastType](decodeURIComponent(toastMessage));
                }
                
                const redirect = xhr.getResponseHeader('X-Redirect');
                if (redirect) {
                    window.location.href = redirect;
                }
            }
        });
    }
    
    // Close modals with Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.active, .modal.show, .modal[style*="block"], .modal[style*="flex"]').forEach(function(modal) {
                modal.style.display = '';
                modal.classList.remove('active', 'show');
            });
        }
    });

    // Close modals when clicking backdrop
    document.querySelectorAll('.modal').forEach(function(modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.style.display = '';
                modal.classList.remove('active', 'show');
            }
        });
    });
    
    // Dropdown toggle
    document.querySelectorAll('.dropdown-toggle').forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = this.closest('.dropdown');
            dropdown.classList.toggle('active');
        });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function() {
        document.querySelectorAll('.dropdown.active').forEach(function(dropdown) {
            dropdown.classList.remove('active');
        });
    });
    
    // Form loading states
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function() {
            const btn = form.querySelector('button[type="submit"], input[type="submit"]');
            if (btn && !btn.disabled) {
                btn.disabled = true;
                const originalText = btn.innerHTML;
                btn.dataset.originalText = originalText;
                btn.innerHTML = '<span class="spinner" style="width:16px;height:16px;border-width:2px;"></span> Loading...';
            }
        });
    });
});

// Modal helpers
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('active');
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.style.display = '';
        modal.classList.remove('active', 'show');
    }
}

function toggleTheme() {
    var root = document.documentElement;
    var newTheme = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
    root.classList.add('theme-transitioning');
    root.setAttribute('data-theme', newTheme);
    document.cookie = 'um_theme=' + newTheme + '; path=/; max-age=31536000; SameSite=Lax';
    setTimeout(function() { root.classList.remove('theme-transitioning'); }, 300);
}
</script>

<?php
// Version footer
if (defined('UM_VERSION')) {
    echo '<div class="app-version-footer">v' . UM_VERSION . '</div>';
}
?>

<?php
// Include toast system
if (!defined('TOAST_LOADED')) {
    require_once __DIR__ . '/toast.php';
    define('TOAST_LOADED', true);
}
renderToasts();
if (function_exists('renderLicenseToast')) {
    renderLicenseToast();
}
if (function_exists('renderNotificationPolling')) {
    renderNotificationPolling();
}
?>
