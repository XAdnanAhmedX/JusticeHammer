<?php


require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require authentication
if (!isLoggedIn() || !isAdmin()) {
    json_response(['ok' => false, 'error' => 'Forbidden: Admin access required'], 403);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    json_response(['ok' => false, 'error' => 'Invalid JSON input'], 400);
}

$userId = isset($input['userId']) ? (int)$input['userId'] : 0;
$verified = isset($input['verified']) ? (int)$input['verified'] : 0;
$action = isset($input['action']) ? $input['action'] : ($verified ? 'approve' : 'deny'); // approve or deny

if ($userId <= 0) {
    json_response(['ok' => false, 'error' => 'Missing or invalid userId'], 400);
}

try {
    $pdo = getDbConnection();
    $adminId = getCurrentUserId();
    
    // Verify user exists and is a lawyer
    $stmt = $pdo->prepare('SELECT id, name, email, role, verified FROM users WHERE id = :userId');
    $stmt->execute(['userId' => $userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        json_response(['ok' => false, 'error' => 'User not found'], 404);
    }
    
    if ($user['role'] !== 'LAWYER') {
        json_response(['ok' => false, 'error' => 'User is not a lawyer'], 400);
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Update user verification status
    $stmt = $pdo->prepare('UPDATE users SET verified = :verified WHERE id = :userId');
    $stmt->execute([
        'verified' => $verified,
        'userId' => $userId
    ]);
    
    // Insert timeline entry
    $stmt = $pdo->prepare('
        INSERT INTO timeline (case_id, actor_id, event, meta)
        VALUES (NULL, :actor_id, :event, :meta)
    ');
    
    $stmt->execute([
        'actor_id' => $adminId,
        'event' => 'Admin Verified User',
        'meta' => json_encode([
            'userId' => $userId,
            'verified' => $verified,
            'action' => $action
        ])
    ]);
    
    // Insert admin action record
    $stmt = $pdo->prepare('
        INSERT INTO admin_actions (admin_id, target_user_id, action_type, details)
        VALUES (:admin_id, :target_user_id, :action_type, :details)
    ');
    
    $actionType = $verified ? 'VERIFY_LAWYER' : 'DENY_LAWYER';
    $stmt->execute([
        'admin_id' => $adminId,
        'target_user_id' => $userId,
        'action_type' => $actionType,
        'details' => json_encode([
            'user_name' => $user['name'],
            'user_email' => $user['email']
        ])
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    $stmt = $pdo->prepare('SELECT id, name, email, role, verified, verification_file_path FROM users WHERE id = :userId');
    $stmt->execute(['userId' => $userId]);
    $updatedUser = $stmt->fetch();
    
    json_response([
        'ok' => true,
        'user' => $updatedUser,
        'message' => $verified ? 'Lawyer verified successfully' : 'Lawyer verification denied'
    ], 200);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('Admin verify error: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('Admin verify error: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Unexpected error: ' . $e->getMessage()], 500);
}
