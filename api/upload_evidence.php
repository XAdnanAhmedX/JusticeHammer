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

$userId = getCurrentUserId();
$userRole = getCurrentUserRole();

// Validate required fields
$caseId = isset($_POST['caseId']) ? (int)$_POST['caseId'] : 0;
$onBehalfOf = isset($_POST['on_behalf_of']) ? (int)$_POST['on_behalf_of'] : null;

if ($caseId <= 0) {
    json_response(['ok' => false, 'error' => 'Missing or invalid caseId'], 400);
}

// Check file upload
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    json_response(['ok' => false, 'error' => 'File upload failed or no file provided'], 400);
}

$file = $_FILES['file'];

$maxSize = 10 * 1024 * 1024; 
if ($file['size'] > $maxSize) {
    json_response(['ok' => false, 'error' => 'File size exceeds 10MB limit'], 400);
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
if (!in_array($mimeType, $allowedMimes)) {
    json_response(['ok' => false, 'error' => 'Invalid file type. Only images and PDFs are allowed'], 400);
}

try {
    $pdo = getDbConnection();
    
    // Verify case exists and user has access
    $stmt = $pdo->prepare('SELECT id, created_by, assigned_to, status FROM cases WHERE id = :caseId');
    $stmt->execute(['caseId' => $caseId]);
    $case = $stmt->fetch();
    
    if (!$case) {
        json_response(['ok' => false, 'error' => 'Case not found'], 404);
    }
    

    $hasAccess = false;
    
    if ($userRole === 'ADMIN') {
        $hasAccess = true;
    } elseif ($case['created_by'] == $userId) {
        $hasAccess = true;
    } elseif ($userRole === 'OFFICIAL' && $case['assigned_to'] == $userId) {
        $hasAccess = true;
    } elseif ($userRole === 'LAWYER') {
        // Check if lawyer is assigned to this case
        $stmt = $pdo->prepare('SELECT id FROM timeline WHERE case_id = :caseId AND event = "Lawyer Assigned" AND JSON_EXTRACT(meta, "$.lawyerId") = :userId');
        $stmt->execute(['caseId' => $caseId, 'userId' => $userId]);
        $hasAccess = $stmt->fetch() !== false;
    }
    
    if (!$hasAccess) {
        json_response(['ok' => false, 'error' => 'Forbidden: You do not have access to this case'], 403);
    }
    
    // Get uploads directory from config
    $uploadsDir = getConfig('UPLOADS_DIR', 'uploads');
    $uploadsPath = __DIR__ . '/../' . $uploadsDir;
    
    // Ensure uploads directory exists
    if (!is_dir($uploadsPath)) {
        mkdir($uploadsPath, 0755, true);
    }
    
    $originalFilename = $file['name'];
    $randomFilename = random_filename($originalFilename);
    $storedPath = $uploadsPath . '/' . $randomFilename;
    
    if (!move_uploaded_file($file['tmp_name'], $storedPath)) {
        json_response(['ok' => false, 'error' => 'Failed to save uploaded file'], 500);
    }
    
    $sha256 = hash_file('sha256', $storedPath);
    
    $pdo->beginTransaction();
    
    // Insert evidence record
    $stmt = $pdo->prepare('
        INSERT INTO evidence (case_id, filename, stored_path, sha256, uploaded_by)
        VALUES (:case_id, :filename, :stored_path, :sha256, :uploaded_by)
    ');
    
    $relativePath = $uploadsDir . '/' . $randomFilename;
    
    $stmt->execute([
        'case_id' => $caseId,
        'filename' => $originalFilename,
        'stored_path' => $relativePath,
        'sha256' => $sha256,
        'uploaded_by' => $userId
    ]);
    
    $evidenceId = $pdo->lastInsertId();
    
    // Insert timeline entry
    if ($onBehalfOf) {
        $event = 'Evidence Uploaded (on_behalf)';
        $meta = json_encode([
            'evidenceId' => $evidenceId,
            'on_behalf_of' => $onBehalfOf
        ]);
    } else {
        $event = 'Evidence Uploaded';
        $meta = json_encode([
            'evidenceId' => $evidenceId
        ]);
    }
    
    $stmt = $pdo->prepare('
        INSERT INTO timeline (case_id, actor_id, event, meta)
        VALUES (:case_id, :actor_id, :event, :meta)
    ');
    
    $stmt->execute([
        'case_id' => $caseId,
        'actor_id' => $userId,
        'event' => $event,
        'meta' => $meta
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    json_response([
        'ok' => true,
        'evidenceId' => $evidenceId,
        'sha256' => $sha256,
        'filename' => $originalFilename
    ], 200);
    
} catch (PDOException $e) {
    // Rollback on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Clean up uploaded file if it exists
    if (isset($storedPath) && file_exists($storedPath)) {
        @unlink($storedPath);
    }
    
    error_log('Upload evidence error: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
    
} catch (Exception $e) {
    // Rollback on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Clean up uploaded file if it exists
    if (isset($storedPath) && file_exists($storedPath)) {
        @unlink($storedPath);
    }
    
    error_log('Upload evidence error: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Unexpected error: ' . $e->getMessage()], 500);
}
