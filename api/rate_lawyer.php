<?php
/**
 * Rate Lawyer API Endpoint
 * POST /api/rate_lawyer.php
 * 
 * Litigant can rate a lawyer (1-5 stars)
 */

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

// Require authentication
if (!isLoggedIn() || !isLitigant()) {
    json_response(['ok' => false, 'error' => 'Forbidden: Litigant access required'], 403);
}

$userId = getCurrentUserId();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    json_response(['ok' => false, 'error' => 'Invalid JSON input'], 400);
}

$caseId = isset($input['caseId']) ? (int)$input['caseId'] : 0;
$lawyerId = isset($input['lawyerId']) ? (int)$input['lawyerId'] : 0;
$rating = isset($input['rating']) ? (int)$input['rating'] : 0;
$comment = isset($input['comment']) ? trim($input['comment']) : null;

if ($caseId <= 0 || $lawyerId <= 0) {
    json_response(['ok' => false, 'error' => 'Missing or invalid caseId or lawyerId'], 400);
}

if ($rating < 1 || $rating > 5) {
    json_response(['ok' => false, 'error' => 'Rating must be between 1 and 5'], 400);
}

try {
    $pdo = getDbConnection();
    
    // Verify case exists and belongs to litigant
    $stmt = $pdo->prepare('SELECT id, created_by, status FROM cases WHERE id = :caseId');
    $stmt->execute(['caseId' => $caseId]);
    $case = $stmt->fetch();
    
    if (!$case) {
        json_response(['ok' => false, 'error' => 'Case not found'], 404);
    }
    
    if ($case['created_by'] != $userId) {
        json_response(['ok' => false, 'error' => 'Forbidden: You do not own this case'], 403);
    }
    
    // Verify case is solved
    if ($case['status'] !== 'SOLVED') {
        json_response(['ok' => false, 'error' => 'Case must be solved before rating'], 400);
    }
    
    // Verify lawyer exists and is assigned to case
    $stmt = $pdo->prepare('SELECT id, name FROM users WHERE id = :lawyerId AND role = "LAWYER"');
    $stmt->execute(['lawyerId' => $lawyerId]);
    $lawyer = $stmt->fetch();
    
    if (!$lawyer) {
        json_response(['ok' => false, 'error' => 'Lawyer not found'], 404);
    }
    
    // Check if already rated
    $stmt = $pdo->prepare('SELECT id FROM lawyer_ratings WHERE case_id = :caseId AND litigant_id = :litigantId');
    $stmt->execute(['caseId' => $caseId, 'litigantId' => $userId]);
    if ($stmt->fetch()) {
        json_response(['ok' => false, 'error' => 'You have already rated this lawyer for this case'], 400);
    }
    
    // Insert rating
    $stmt = $pdo->prepare('
        INSERT INTO lawyer_ratings (case_id, lawyer_id, litigant_id, rating, comment)
        VALUES (:case_id, :lawyer_id, :litigant_id, :rating, :comment)
    ');
    
    $stmt->execute([
        'case_id' => $caseId,
        'lawyer_id' => $lawyerId,
        'litigant_id' => $userId,
        'rating' => $rating,
        'comment' => $comment
    ]);
    
    $ratingId = $pdo->lastInsertId();
    
    // Return success response
    json_response([
        'ok' => true,
        'ratingId' => $ratingId,
        'message' => 'Rating submitted successfully'
    ], 200);
    
} catch (PDOException $e) {
    error_log('Rate lawyer error: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
    
} catch (Exception $e) {
    error_log('Rate lawyer error: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Unexpected error: ' . $e->getMessage()], 500);
}
