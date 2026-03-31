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
/**
 * Permission Lock UI Helper Functions
 * 
 * These functions create visual lock overlays for UI elements
 * that the user doesn't have permission to access.
 * 
 * The CSS is in includes/styles.php
 * 
 * Usage Examples:
 * 
 * 1. Lock an entire section/form:
 *    <div class="section <?php echo !$can_manage ? 'permission-locked' : ''; ?>">
 *        <h2>Add Record</h2>
 *        <form>...</form>
 *        <?php if (!$can_manage) permissionLockOverlay('You need manage permission'); ?>
 *    </div>
 * 
 * 2. Lock a button inline:
 *    <?php if ($can_manage): ?>
 *        <button class="btn btn-danger">Delete</button>
 *    <?php else: ?>
 *        <?php lockedButton('Delete'); ?>
 *    <?php endif; ?>
 * 
 * 3. Lock table actions:
 *    <?php if ($can_manage): ?>
 *        <button>Edit</button>
 *    <?php else: ?>
 *        <?php lockedActions(); ?>
 *    <?php endif; ?>
 */

/**
 * Output a permission lock overlay (place inside a .permission-locked container)
 * 
 * @param string $message The message explaining why access is restricted
 * @param string $title Optional title (default: "Permission Required")
 */
function permissionLockOverlay($message = 'You do not have permission to access this feature.', $title = 'Permission Required') {
    ?>
    <div class="permission-lock-overlay">
        <div class="lock-icon">🔒</div>
        <div class="lock-title"><?php echo htmlspecialchars($title); ?></div>
        <div class="lock-message"><?php echo htmlspecialchars($message); ?></div>
        <div class="lock-badge">Access Restricted</div>
    </div>
    <?php
}

/**
 * Create an inline locked button placeholder
 * 
 * @param string $text The button text
 * @param string $tooltip Optional tooltip on hover
 */
function lockedButton($text, $tooltip = 'Permission required') {
    ?>
    <span class="btn-locked" title="<?php echo htmlspecialchars($tooltip); ?>">
        <span class="lock-icon">🔒</span>
        <?php echo htmlspecialchars($text); ?>
    </span>
    <?php
}

/**
 * Create a locked actions placeholder for tables
 * 
 * @param string $tooltip Optional tooltip
 */
function lockedActions($tooltip = 'You do not have permission to perform actions') {
    ?>
    <span class="actions-locked" title="<?php echo htmlspecialchars($tooltip); ?>">
        🔒 No Access
    </span>
    <?php
}

/**
 * Helper to get the CSS class for a locked container
 * 
 * @param bool $isLocked Whether the container should be locked
 * @return string CSS class string
 */
function lockClass($isLocked) {
    return $isLocked ? 'permission-locked' : '';
}
