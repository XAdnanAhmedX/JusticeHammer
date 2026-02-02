<?php


require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isLoggedIn()) {
    json_response(['ok' => false, 'error' => 'Authentication required'], 401);
}

$userId = getCurrentUserId();
$userRole = getCurrentUserRole();
$caseId = isset($_GET['caseId']) ? (int)$_GET['caseId'] : 0;

if ($caseId <= 0) {
    json_response(['ok' => false, 'error' => 'Missing or invalid caseId'], 400);
}

try {
    $pdo = getDbConnection();
    
    $stmt = $pdo->prepare('SELECT id, created_by, lawyer_id FROM cases WHERE id = :caseId');
    $stmt->execute(['caseId' => $caseId]);
    $case = $stmt->fetch();
    
    if (!$case) {
        json_response(['ok' => false, 'error' => 'Case not found'], 404);
    }
    
    $hasAccess = false;
    if ($userRole === 'ADMIN') {
        $hasAccess = true;
    } elseif ($userRole === 'LITIGANT' && $case['created_by'] == $userId) {
        $hasAccess = true;
    } elseif ($userRole === 'LAWYER') {
        if ($case['lawyer_id'] == $userId) {
            $hasAccess = true;
        } else {
            $stmt = $pdo->prepare('
                SELECT id FROM timeline 
                WHERE case_id = :caseId 
                AND event = "Lawyer Assigned" 
                AND JSON_EXTRACT(meta, "$.lawyerId") = :userId
            ');
            $stmt->execute(['caseId' => $caseId, 'userId' => $userId]);
            $hasAccess = $stmt->fetch() !== false;
        }
    }
    
    if (!$hasAccess) {
        json_response(['ok' => false, 'error' => 'Forbidden'], 403);
    }
    
    // Get chat messages
    $stmt = $pdo->prepare('
        SELECT cm.*, u1.name AS sender_name, u2.name AS receiver_name
        FROM chat_messages cm
        LEFT JOIN users u1 ON cm.sender_id = u1.id
        LEFT JOIN users u2 ON cm.receiver_id = u2.id
        WHERE cm.case_id = :caseId
        ORDER BY cm.created_at ASC
    ');
    $stmt->execute(['caseId' => $caseId]);
    $messages = $stmt->fetchAll();
    
    // Return messages
    json_response([
        'ok' => true,
        'messages' => $messages
    ], 200);
    
} catch (PDOException $e) {
    error_log('Chat get error: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
    
} catch (Exception $e) {
    error_log('Chat get error: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Unexpected error: ' . $e->getMessage()], 500);
}
