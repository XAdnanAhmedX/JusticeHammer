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

if (!isLoggedIn()) {
    json_response(['ok' => false, 'error' => 'Authentication required'], 401);
}

if (!isAdmin()) {
    json_response(['ok' => false, 'error' => 'Forbidden: Only admins can assign cases'], 403);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    json_response(['ok' => false, 'error' => 'Invalid JSON input'], 400);
}

$caseId = isset($input['caseId']) ? (int)$input['caseId'] : 0;
$officialId = isset($input['officialId']) ? (int)$input['officialId'] : null;
$actorId = getCurrentUserId(); // Use session user as actor

if ($caseId <= 0) {
    json_response(['ok' => false, 'error' => 'Missing or invalid caseId'], 400);
}

try {
    $pdo = getDbConnection();
    
    $stmt = $pdo->prepare('SELECT id, district, status FROM cases WHERE id = :caseId');
    $stmt->execute(['caseId' => $caseId]);
    $case = $stmt->fetch();
    
    if (!$case) {
        json_response(['ok' => false, 'error' => 'Case not found'], 404);
    }
    
    if (!$officialId || $officialId <= 0) {
        $stmt = $pdo->prepare('
            SELECT id FROM users 
            WHERE role = "OFFICIAL" AND verified = 1 AND district = :district 
            ORDER BY id LIMIT 1
        ');
        $stmt->execute(['district' => $case['district']]);
        $official = $stmt->fetch();
        
        if ($official) {
            $officialId = $official['id'];
        } else {
            $stmt = $pdo->prepare('
                SELECT id FROM users 
                WHERE role = "OFFICIAL" AND verified = 1 
                ORDER BY id LIMIT 1
            ');
            $stmt->execute();
            $official = $stmt->fetch();
            
            if ($official) {
                $officialId = $official['id'];
            } else {
                json_response(['ok' => false, 'error' => 'No verified official available'], 400);
            }
        }
    }
    
    $stmt = $pdo->prepare('SELECT id, name, role, verified FROM users WHERE id = :officialId AND role = "OFFICIAL"');
    $stmt->execute(['officialId' => $officialId]);
    $official = $stmt->fetch();
    
    if (!$official) {
        json_response(['ok' => false, 'error' => 'Official not found'], 404);
    }
    
    if (!$official['verified']) {
        json_response(['ok' => false, 'error' => 'Official is not verified'], 400);
    }
    
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare('
        UPDATE cases 
        SET status = "ASSIGNED", assigned_to = :officialId 
        WHERE id = :caseId
    ');
    
    $stmt->execute([
        'officialId' => $officialId,
        'caseId' => $caseId
    ]);
    
    $meta = json_encode([
        'officialId' => $officialId,
        'officialName' => $official['name']
    ]);
    
    $stmt = $pdo->prepare('
        INSERT INTO timeline (case_id, actor_id, event, meta)
        VALUES (:case_id, :actor_id, :event, :meta)
    ');
    
    $stmt->execute([
        'case_id' => $caseId,
        'actor_id' => $actorId,
        'event' => 'Assigned to Official',
        'meta' => $meta
    ]);
    
    $pdo->commit();
    
    $stmt = $pdo->prepare('
        SELECT c.id, c.tracking_code, c.title, c.status, c.assigned_to, u.name AS assigned_to_name
        FROM cases c
        LEFT JOIN users u ON c.assigned_to = u.id
        WHERE c.id = :caseId
    ');
    $stmt->execute(['caseId' => $caseId]);
    $updatedCase = $stmt->fetch();
    
    json_response([
        'ok' => true,
        'case' => $updatedCase
    ], 200);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('Assign case error: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('Assign case error: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Unexpected error: ' . $e->getMessage()], 500);
}
