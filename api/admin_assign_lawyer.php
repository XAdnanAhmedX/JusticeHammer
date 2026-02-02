<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Require POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require authentication and admin role
if (!isLoggedIn() || !isAdmin()) {
    json_response(['ok' => false, 'error' => 'Forbidden: Admin access required'], 403);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    json_response(['ok' => false, 'error' => 'Invalid JSON input'], 400);
}

$caseId = isset($input['caseId']) ? (int)$input['caseId'] : 0;
$lawyerId = isset($input['lawyerId']) ? (int)$input['lawyerId'] : 0;

if ($caseId <= 0 || $lawyerId <= 0) {
    json_response(['ok' => false, 'error' => 'Missing or invalid caseId or lawyerId'], 400);
}

try {
    $pdo = getDbConnection();
    $adminId = getCurrentUserId();
    
    // Verify case exists
    $stmt = $pdo->prepare('SELECT id, title, status FROM cases WHERE id = :caseId');
    $stmt->execute(['caseId' => $caseId]);
    $case = $stmt->fetch();
    
    if (!$case) {
        json_response(['ok' => false, 'error' => 'Case not found'], 404);
    }
    
    // Verify lawyer exists and is verified
    $stmt = $pdo->prepare('SELECT id, name, verified FROM users WHERE id = :lawyerId AND role = "LAWYER"');
    $stmt->execute(['lawyerId' => $lawyerId]);
    $lawyer = $stmt->fetch();
    
    if (!$lawyer) {
        json_response(['ok' => false, 'error' => 'Lawyer not found'], 404);
    }
    
    if (!$lawyer['verified']) {
        json_response(['ok' => false, 'error' => 'Lawyer is not verified'], 400);
    }
    
    // Check if lawyer is already assigned
    $stmt = $pdo->prepare('
        SELECT id FROM timeline 
        WHERE case_id = :caseId 
        AND event = "Lawyer Assigned" 
        AND JSON_EXTRACT(meta, "$.lawyerId") = :lawyerId
    ');
    $stmt->execute(['caseId' => $caseId, 'lawyerId' => $lawyerId]);
    if ($stmt->fetch()) {
        json_response(['ok' => false, 'error' => 'Lawyer is already assigned to this case'], 400);
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Update case lawyer_id
    $stmt = $pdo->prepare('UPDATE cases SET lawyer_id = :lawyerId WHERE id = :caseId');
    $stmt->execute([
        'lawyerId' => $lawyerId,
        'caseId' => $caseId
    ]);
    
    // Insert timeline entry
    $metaJson = json_encode([
        'lawyerId' => $lawyerId,
        'lawyerName' => $lawyer['name'],
        'assignedBy' => 'admin',
        'adminId' => $adminId
    ]);
    
    $stmt = $pdo->prepare('
        INSERT INTO timeline (case_id, actor_id, event, meta)
        VALUES (:case_id, :actor_id, :event, :meta)
    ');
    
    $stmt->execute([
        'case_id' => $caseId,
        'actor_id' => $adminId,
        'event' => 'Lawyer Assigned',
        'meta' => $metaJson
    ]);
    
    // Insert admin action record
    $stmt = $pdo->prepare('
        INSERT INTO admin_actions (admin_id, target_user_id, action_type, details)
        VALUES (:admin_id, :target_user_id, :action_type, :details)
    ');
    
    $stmt->execute([
        'admin_id' => $adminId,
        'target_user_id' => $lawyerId,
        'action_type' => 'ASSIGN_CASE',
        'details' => json_encode([
            'case_id' => $caseId,
            'case_title' => $case['title'],
            'lawyer_name' => $lawyer['name']
        ])
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    json_response([
        'ok' => true,
        'message' => 'Lawyer assigned successfully',
        'caseId' => $caseId,
        'lawyerId' => $lawyerId
    ], 200);
    
} catch (PDOException $e) {
    // Rollback on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('Admin assign lawyer error: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
    
} catch (Exception $e) {
    // Rollback on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('Admin assign lawyer error: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Unexpected error: ' . $e->getMessage()], 500);
}
