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

// Only lawyers can accept cases
if (!isLawyer()) {
    json_response(['ok' => false, 'error' => 'Forbidden: Only lawyers can accept cases'], 403);
}

$userId = getCurrentUserId();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    json_response(['ok' => false, 'error' => 'Invalid JSON input'], 400);
}

$caseId   = isset($input['caseId']) ? (int)$input['caseId'] : 0;
$lawyerId = $userId; // lawyer can only accept for self

if ($caseId <= 0) {
    json_response(['ok' => false, 'error' => 'Missing or invalid caseId'], 400);
}

try {
    $pdo = getDbConnection();

    //  Verify lawyer
    $stmt = $pdo->prepare('
        SELECT id, name, verified 
        FROM users 
        WHERE id = :lawyerId AND role = "LAWYER"
    ');
    $stmt->execute(['lawyerId' => $lawyerId]);
    $lawyer = $stmt->fetch();

    if (!$lawyer) {
        json_response(['ok' => false, 'error' => 'Lawyer not found'], 404);
    }
    if (!$lawyer['verified']) {
        json_response(['ok' => false, 'error' => 'Account not verified'], 403);
    }

    //  Verify case
    $stmt = $pdo->prepare('
        SELECT id, lawyer_id 
        FROM cases 
        WHERE id = :caseId
    ');
    $stmt->execute(['caseId' => $caseId]);
    $case = $stmt->fetch();

    if (!$case) {
        json_response(['ok' => false, 'error' => 'Case not found'], 404);
    }

    //  Prevent double assignment
    if (!empty($case['lawyer_id'])) {
        json_response(['ok' => false, 'error' => 'Case already assigned to a lawyer'], 400);
    }

    //  Check consent from timeline
    $stmt = $pdo->prepare('
        SELECT meta FROM timeline 
        WHERE case_id = :caseId 
        AND event = "Received"
        ORDER BY created_at ASC
        LIMIT 1
    ');
    $stmt->execute(['caseId' => $caseId]);
    $receivedEvent = $stmt->fetch();

    if (!$receivedEvent) {
        json_response(['ok' => false, 'error' => 'Case creation record not found'], 404);
    }

    $meta = json_decode($receivedEvent['meta'], true);
    $openConsent = (int)($meta['open_consent'] ?? 0);
    $preferredLawyerId = (int)($meta['preferred_lawyer_id'] ?? 0);

    if (
        $openConsent !== 1 &&
        $preferredLawyerId !== $lawyerId
    ) {
        json_response(['ok' => false, 'error' => 'You do not have permission to accept this case'], 403);
    }



    $pdo->beginTransaction();

    //  UPDATE cases table 
    $stmt = $pdo->prepare('
        UPDATE cases
        SET lawyer_id = :lawyerId
        WHERE id = :caseId
    ');
    $stmt->execute([
        'lawyerId' => $lawyerId,
        'caseId'   => $caseId
    ]);

    //  INSERT timeline entry
    $metaJson = json_encode([
        'lawyerId'   => $lawyerId,
        'lawyerName' => $lawyer['name']
    ]);

    $stmt = $pdo->prepare('
        INSERT INTO timeline (case_id, actor_id, event, meta)
        VALUES (:case_id, :actor_id, :event, :meta)
    ');
    $stmt->execute([
        'case_id'  => $caseId,
        'actor_id' => $lawyerId,
        'event'    => 'Lawyer Assigned',
        'meta'     => $metaJson
    ]);

    $pdo->commit();



    json_response([
        'ok'       => true,
        'message'  => 'Case accepted successfully',
        'caseId'   => $caseId,
        'lawyerId' => $lawyerId
    ], 200);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Lawyer accept error: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Database error'], 500);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Lawyer accept error: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Unexpected error'], 500);
}
