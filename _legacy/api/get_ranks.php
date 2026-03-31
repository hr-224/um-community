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

header('Content-Type: application/json');

$dept_id = isset($_GET['dept_id']) ? intval($_GET['dept_id']) : 0;

$conn = getDBConnection();
$stmt = $conn->prepare("SELECT id, rank_name FROM ranks WHERE department_id = ? ORDER BY rank_order ASC");
$stmt->bind_param("i", $dept_id);
$stmt->execute();
$result = $stmt->get_result();

$ranks = [];
while ($row = $result->fetch_assoc()) {
    $ranks[] = $row;
}

echo json_encode($ranks);

$stmt->close();
$conn->close();
?>