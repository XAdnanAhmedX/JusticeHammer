<?php
/**
 * Create Case API Endpoint
 * POST /api/create_case.php
 * 
 * Creates a new case with transaction safety
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
if (!isLoggedIn()) {
    json_response(['ok' => false, 'error' => 'Authentication required'], 401);
}

// Only litigants can create cases (or admin for testing)
$userId = getCurrentUserId();
if (!isLitigant() && !isAdmin()) {
    json_response(['ok' => false, 'error' => 'Only litigants can create cases'], 403);
}

// Get input data
$title = sanitize_input($_POST['title'] ?? '');
$description = sanitize_input($_POST['description'] ?? '');
$type = sanitize_input($_POST['type'] ?? '');
$district = sanitize_input($_POST['district'] ?? '');
$incidentDate = !empty($_POST['incident_date']) ? sanitize_input($_POST['incident_date']) : null;
$contactPref = sanitize_input($_POST['contact_pref'] ?? 'EMAIL');
$sensitive = isset($_POST['sensitive']) ? (int)$_POST['sensitive'] : 0;
$openConsent = isset($_POST['open_consent']) ? (int)$_POST['open_consent'] : 1;
$preferredLawyerId = !empty($_POST['preferred_lawyer_id']) ? (int)$_POST['preferred_lawyer_id'] : null;

// Validation
if (empty($title)) {
    json_response(['ok' => false, 'error' => 'Missing field: title'], 400);
}

if (empty($type)) {
    json_response(['ok' => false, 'error' => 'Missing field: type'], 400);
}

if (empty($district)) {
    json_response(['ok' => false, 'error' => 'Missing field: district'], 400);
}

if (!in_array($type, ['Crime', 'Gender-Based Violence', 'Land Dispute', 'Corruption', 'Other'])) {
    json_response(['ok' => false, 'error' => 'Invalid case type'], 400);
}

if (!in_array($contactPref, ['EMAIL', 'PHONE', 'ANONYMOUS'])) {
    json_response(['ok' => false, 'error' => 'Invalid contact preference'], 400);
}

// Validate incident date if provided
if ($incidentDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $incidentDate)) {
    json_response(['ok' => false, 'error' => 'Invalid incident date format (expected YYYY-MM-DD)'], 400);
}

try {
    $pdo = getDbConnection();
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Generate tracking code with retry logic (up to 5 attempts)
    $trackingCode = null;
    $maxAttempts = 5;
    $attempt = 0;
    
    while ($attempt < $maxAttempts) {
        $trackingCode = generate_tracking_code(8);
        
        // Check if tracking code already exists
        $stmt = $pdo->prepare('SELECT id FROM cases WHERE tracking_code = :code');
        $stmt->execute(['code' => $trackingCode]);
        
        if (!$stmt->fetch()) {
            // Tracking code is unique, break out of loop
            break;
        }
        
        $attempt++;
        
        if ($attempt >= $maxAttempts) {
            $pdo->rollBack();
            json_response(['ok' => false, 'error' => 'Failed to generate unique tracking code after ' . $maxAttempts . ' attempts'], 500);
        }
    }
    
    // Insert case
    $stmt = $pdo->prepare('
        INSERT INTO cases (tracking_code, title, description, type, district, incident_date, status, created_by) 
        VALUES (:tracking_code, :title, :description, :type, :district, :incident_date, :status, :created_by)
    ');
    
    $stmt->execute([
        'tracking_code' => $trackingCode,
        'title' => $title,
        'description' => $description ?: null,
        'type' => $type,
        'district' => $district,
        'incident_date' => $incidentDate,
        'status' => 'RECEIVED',
        'created_by' => $userId
    ]);
    
    $caseId = $pdo->lastInsertId();
    
    // Insert timeline entry
    $meta = [
        'contact_pref' => $contactPref,
        'sensitive' => $sensitive,
        'open_consent' => $openConsent
    ];
    
    if ($preferredLawyerId && !$openConsent) {
        $meta['preferred_lawyer_id'] = $preferredLawyerId;
    }
    
    $stmt = $pdo->prepare('
        INSERT INTO timeline (case_id, actor_id, event, meta) 
        VALUES (:case_id, :actor_id, :event, :meta)
    ');
    
    $stmt->execute([
        'case_id' => $caseId,
        'actor_id' => $userId,
        'event' => 'Received',
        'meta' => json_encode($meta)
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    json_response([
        'ok' => true,
        'caseId' => $caseId,
        'trackingCode' => $trackingCode
    ], 200);
    
} catch (PDOException $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('Create case error: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
    
} catch (Exception $e) {
    // Rollback on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('Create case error: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Unexpected error: ' . $e->getMessage()], 500);
}
