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
 * UM Community Manager - API v1
 * 
 * Authentication: X-API-Key and X-API-Secret headers
 * Rate limiting: Per-key configurable
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: X-API-Key, X-API-Secret, Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config.php';
if (!defined('UM_FUNCTIONS_LOADED')) { require_once __DIR__ . '/../../includes/functions.php'; }

// Helper functions
function apiResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode(['success' => $code < 400, 'data' => $data]);
    exit;
}

function apiError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// Get API key from headers
$api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';
$api_secret = $_SERVER['HTTP_X_API_SECRET'] ?? '';

if (!$api_key || !$api_secret) {
    apiError('Missing API key or secret', 401);
}

$conn = getDBConnection();

// Validate API key
$stmt = $conn->prepare("SELECT * FROM api_keys WHERE api_key = ? AND is_active = 1");
$stmt->bind_param("s", $api_key);
$stmt->execute();
$key_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$key_data) {
    apiError('Invalid API key', 401);
}

// Check expiration
if ($key_data['expires_at'] && strtotime($key_data['expires_at']) < time()) {
    apiError('API key expired', 401);
}

// Verify secret
if (!password_verify($api_secret, $key_data['secret_hash'])) {
    apiError('Invalid API secret', 401);
}

// Check rate limit (simple implementation)
$hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM api_request_log WHERE api_key_id = ? AND request_time > ?");
$stmt->bind_param("is", $key_data['id'], $hour_ago);
$stmt->execute();
$count_row = $stmt->get_result()->fetch_assoc();
$request_count = $count_row['cnt'] ?? 0;
$stmt->close();

if ($request_count >= $key_data['rate_limit']) {
    apiError('Rate limit exceeded', 429);
}

// Log request
$endpoint = $_GET['endpoint'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$ip = $_SERVER['REMOTE_ADDR'];
$stmt = $conn->prepare("INSERT INTO api_request_log (api_key_id, endpoint, method, ip_address, response_code) VALUES (?, ?, ?, ?, 200)");
$stmt->bind_param("isss", $key_data['id'], $endpoint, $method, $ip);
$stmt->execute();
$stmt->close();

// Update last used
$stmt = $conn->prepare("UPDATE api_keys SET last_used_at = NOW(), last_used_ip = ? WHERE id = ?");
$stmt->bind_param("si", $ip, $key_data['id']);
$stmt->execute();
$stmt->close();

// Parse permissions
$permissions = json_decode($key_data['permissions'], true) ?: [];

function hasPermission($perm) {
    global $permissions;
    return in_array($perm, $permissions);
}

// Route the request
$endpoint = trim($_GET['endpoint'] ?? '', '/');
$parts = explode('/', $endpoint);
$resource = $parts[0] ?? '';
$id = isset($parts[1]) ? intval($parts[1]) : null;

switch ($resource) {
    case 'users':
        if (!hasPermission('users.read') && !hasPermission('users.list')) {
            apiError('Permission denied', 403);
        }
        
        if ($id) {
            if (!hasPermission('users.read')) apiError('Permission denied', 403);
            $stmt = $conn->prepare("SELECT id, username, email, discord_id, is_approved, created_at FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$user) apiError('User not found', 404);
            apiResponse($user);
        } else {
            if (!hasPermission('users.list')) apiError('Permission denied', 403);
            $users = $conn->query("SELECT id, username, email, discord_id, is_approved, created_at FROM users WHERE is_approved = 1 ORDER BY username")->fetch_all(MYSQLI_ASSOC);
            apiResponse($users);
        }
        break;
        
    case 'departments':
        if (!hasPermission('departments.read')) apiError('Permission denied', 403);
        
        if ($id) {
            $stmt = $conn->prepare("SELECT * FROM departments WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $dept = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$dept) apiError('Department not found', 404);
            apiResponse($dept);
        } else {
            $depts = $conn->query("SELECT * FROM departments ORDER BY name")->fetch_all(MYSQLI_ASSOC);
            apiResponse($depts);
        }
        break;
        
    case 'roster':
        if (!hasPermission('roster.read')) apiError('Permission denied', 403);
        
        $dept_id = $_GET['department_id'] ?? null;
        $query = "
            SELECT r.*, u.username, u.discord_id, d.name as department_name, d.abbreviation, rk.rank_name
            FROM roster r
            JOIN users u ON r.user_id = u.id
            JOIN departments d ON r.department_id = d.id
            JOIN ranks rk ON r.rank_id = rk.id
        ";
        
        if ($dept_id) {
            $query .= " WHERE r.department_id = " . intval($dept_id);
        }
        $query .= " ORDER BY d.name, rk.rank_order";
        
        $roster = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
        apiResponse($roster);
        break;
        
    case 'announcements':
        if (!hasPermission('announcements.read')) apiError('Permission denied', 403);
        
        $limit = min(intval($_GET['limit'] ?? 10), 50);
        $stmt = $conn->prepare("
            SELECT a.*, u.username as author_name
            FROM announcements a
            JOIN users u ON a.author_id = u.id
            WHERE a.is_active = 1 AND (a.expires_at IS NULL OR a.expires_at > NOW())
            ORDER BY a.is_pinned DESC, a.created_at DESC
            LIMIT ?
        ");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $announcements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        apiResponse($announcements);
        break;
        
    case 'events':
        if (!hasPermission('events.read')) apiError('Permission denied', 403);
        
        $upcoming = isset($_GET['upcoming']);
        $query = "SELECT e.*, u.username as created_by_name FROM events e JOIN users u ON e.created_by = u.id";
        if ($upcoming) {
            $query .= " WHERE e.event_date >= CURDATE()";
        }
        $query .= " ORDER BY e.event_date " . ($upcoming ? "ASC" : "DESC") . " LIMIT 50";
        
        $events = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
        apiResponse($events);
        break;
        
    case 'certifications':
        if ($method === 'GET') {
            if (!hasPermission('certifications.read')) apiError('Permission denied', 403);
            
            $user_id = $_GET['user_id'] ?? null;
            if ($user_id) {
                $stmt = $conn->prepare("
                    SELECT uc.*, ct.name as cert_name, ct.abbreviation
                    FROM user_certifications uc
                    JOIN certification_types ct ON uc.certification_type_id = ct.id
                    WHERE uc.user_id = ?
                ");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $certs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
            } else {
                $certs = $conn->query("SELECT * FROM certification_types WHERE is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
            }
            apiResponse($certs);
        } elseif ($method === 'POST') {
            if (!hasPermission('certifications.write')) apiError('Permission denied', 403);
            
            $input = json_decode(file_get_contents('php://input'), true);
            $user_id = intval($input['user_id'] ?? 0);
            $cert_type_id = intval($input['certification_type_id'] ?? 0);
            $status = $input['status'] ?? 'completed';
            
            if (!$user_id || !$cert_type_id) {
                apiError('user_id and certification_type_id required');
            }
            
            $stmt = $conn->prepare("INSERT INTO user_certifications (user_id, certification_type_id, status, issued_date) VALUES (?, ?, ?, CURDATE()) ON DUPLICATE KEY UPDATE status = ?");
            $stmt->bind_param("iiss", $user_id, $cert_type_id, $status, $status);
            $stmt->execute();
            $stmt->close();
            
            apiResponse(['message' => 'Certification updated']);
        }
        break;
        
    case 'training':
        if ($method === 'GET') {
            if (!hasPermission('training.read')) apiError('Permission denied', 403);
            
            $user_id = $_GET['user_id'] ?? null;
            $query = "
                SELECT tr.*, 
                       trainee.username as trainee_name,
                       trainer.username as trainer_name,
                       tp.name as program_name
                FROM training_records tr
                JOIN users trainee ON tr.trainee_id = trainee.id
                JOIN users trainer ON tr.trainer_id = trainer.id
                LEFT JOIN training_programs tp ON tr.program_id = tp.id
            ";
            if ($user_id) {
                $query .= " WHERE tr.trainee_id = " . intval($user_id);
            }
            $query .= " ORDER BY tr.session_date DESC LIMIT 100";
            
            $records = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
            apiResponse($records);
        } elseif ($method === 'POST') {
            if (!hasPermission('training.write')) apiError('Permission denied', 403);
            
            $input = json_decode(file_get_contents('php://input'), true);
            $trainee_id = intval($input['trainee_id'] ?? 0);
            $trainer_id = intval($input['trainer_id'] ?? 0);
            $session_date = $input['session_date'] ?? date('Y-m-d');
            $hours = floatval($input['hours'] ?? 0);
            $topic = $input['topic'] ?? '';
            $notes = $input['notes'] ?? '';
            
            if (!$trainee_id || !$trainer_id || !$hours) {
                apiError('trainee_id, trainer_id, and hours required');
            }
            
            $stmt = $conn->prepare("INSERT INTO training_records (trainee_id, trainer_id, session_date, hours, topic, notes, status) VALUES (?, ?, ?, ?, ?, ?, 'completed')");
            $stmt->bind_param("iisdss", $trainee_id, $trainer_id, $session_date, $hours, $topic, $notes);
            $stmt->execute();
            $record_id = $stmt->insert_id;
            $stmt->close();
            
            apiResponse(['message' => 'Training record created', 'id' => $record_id]);
        }
        break;
        
    case 'status':
        // Health check - no permission needed
        apiResponse([
            'status' => 'ok',
            'version' => '1.0',
            'community' => getCommunityName()
        ]);
        break;
        
    default:
        apiError('Unknown endpoint', 404);
}

$conn->close();
