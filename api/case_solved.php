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
if (!isLoggedIn() || !isLawyer()) {
    json_response(['ok' => false, 'error' => 'Forbidden: Lawyer access required'], 403);
}

$userId = getCurrentUserId();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    json_response(['ok' => false, 'error' => 'Invalid JSON input'], 400);
}

$caseId = isset($input['caseId']) ? (int)$input['caseId'] : 0;

if ($caseId <= 0) {
    json_response(['ok' => false, 'error' => 'Missing or invalid caseId'], 400);
}

try {
    $pdo = getDbConnection();
    
    // Verify case exists and lawyer is assigned
    $stmt = $pdo->prepare('SELECT id, lawyer_id, status FROM cases WHERE id = :caseId');
    $stmt->execute(['caseId' => $caseId]);
    $case = $stmt->fetch();
    
    if (!$case) {
        json_response(['ok' => false, 'error' => 'Case not found'], 404);
    }
    
    // Verify lawyer is assigned
    $isAssigned = false;
    if ($case['lawyer_id'] == $userId) {
        $isAssigned = true;
    } else {
        $stmt = $pdo->prepare('
            SELECT id FROM timeline 
            WHERE case_id = :caseId 
            AND event = "Lawyer Assigned" 
            AND JSON_EXTRACT(meta, "$.lawyerId") = :userId
        ');
        $stmt->execute(['caseId' => $caseId, 'userId' => $userId]);
        $isAssigned = $stmt->fetch() !== false;
    }
    
    if (!$isAssigned) {
        json_response(['ok' => false, 'error' => 'You are not assigned to this case'], 403);
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Update case status
    $stmt = $pdo->prepare('UPDATE cases SET status = "SOLVED" WHERE id = :caseId');
    $stmt->execute(['caseId' => $caseId]);
    
    // Insert timeline entry
    $stmt = $pdo->prepare('
        INSERT INTO timeline (case_id, actor_id, event, meta)
        VALUES (:case_id, :actor_id, :event, :meta)
    ');
    
    $stmt->execute([
        'case_id' => $caseId,
        'actor_id' => $userId,
        'event' => 'Case Solved',
        'meta' => json_encode(['solved_by' => $userId])
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    json_response([
        'ok' => true,
        'message' => 'Case marked as solved',
        'caseId' => $caseId
    ], 200);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('Case solved error: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('Case solved error: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Unexpected error: ' . $e->getMessage()], 500);
}
