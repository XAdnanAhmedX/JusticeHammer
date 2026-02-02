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

// Get input data
$caseId = isset($_POST['caseId']) ? (int)$_POST['caseId'] : 0;
$message = trim($_POST['message'] ?? '');

if ($caseId <= 0) {
    json_response(['ok' => false, 'error' => 'Missing or invalid caseId'], 400);
}

if (empty($message) && !isset($_FILES['file'])) {
    json_response(['ok' => false, 'error' => 'Message or file is required'], 400);
}

try {
    $pdo = getDbConnection();

    // Verify case exists and user has access
    $stmt = $pdo->prepare('SELECT id, created_by, lawyer_id FROM cases WHERE id = :caseId');
    $stmt->execute(['caseId' => $caseId]);
    $case = $stmt->fetch();

    if (!$case) {
        json_response(['ok' => false, 'error' => 'Case not found'], 404);
    }

    // Determine receiver based on role
    $receiverId = null;
    if ($userRole === 'LITIGANT') {
        // Litigant sends to lawyer
        if (!$case['lawyer_id']) {
            json_response(['ok' => false, 'error' => 'No lawyer assigned to this case'], 400);
        }
        $receiverId = $case['lawyer_id'];

        // Verify litigant owns the case
        if ($case['created_by'] != $userId) {
            json_response(['ok' => false, 'error' => 'Forbidden'], 403);
        }
    } elseif ($userRole === 'LAWYER') {
        // Lawyer sends to litigant
        $receiverId = $case['created_by'];

        // Verify lawyer is assigned
        if ($case['lawyer_id'] != $userId) {
            // Check timeline for assignment
            $stmt = $pdo->prepare('
                SELECT id FROM timeline 
                WHERE case_id = :caseId 
                AND event = "Lawyer Assigned" 
                AND JSON_EXTRACT(meta, "$.lawyerId") = :userId
            ');
            $stmt->execute(['caseId' => $caseId, 'userId' => $userId]);
            if (!$stmt->fetch()) {
                json_response(['ok' => false, 'error' => 'Forbidden'], 403);
            }
        }
    } else {
        json_response(['ok' => false, 'error' => 'Only litigants and lawyers can send messages'], 403);
    }

    // Handle file upload if present
    $filePath = null;
    $fileName = null;

    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file'];
        $maxSize = 10 * 1024 * 1024; // 10MB

        if ($file['size'] > $maxSize) {
            json_response(['ok' => false, 'error' => 'File size exceeds 10MB limit'], 400);
        }

        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            json_response(['ok' => false, 'error' => 'Invalid file type'], 400);
        }

                // Absolute filesystem path to /uploads
                $uploadsPath = realpath(__DIR__ . '/../uploads');

                if ($uploadsPath === false) {
                    json_response(['ok' => false, 'error' => 'Uploads directory not found'], 500);
                }
        
                if (!is_dir($uploadsPath) || !is_writable($uploadsPath)) {
                    json_response(['ok' => false, 'error' => 'Uploads directory is not writable'], 500);
                }
        
                $uploadsPath .= DIRECTORY_SEPARATOR;
        
                $randomFilename = random_filename($file['name']);
                $storedPath = $uploadsPath . $randomFilename;
        

        if (move_uploaded_file($file['tmp_name'], $storedPath)) {
            $filePath = 'uploads/' . $randomFilename;
            $fileName = $file['name'];
        } else {
            error_log('Chat file upload failed. storedPath=' . $storedPath);
            json_response(['ok' => false, 'error' => 'Failed to save uploaded file'], 500);
        }
    }

    // Insert chat message
    $stmt = $pdo->prepare('
        INSERT INTO chat_messages (case_id, sender_id, receiver_id, message, file_path, file_name)
        VALUES (:case_id, :sender_id, :receiver_id, :message, :file_path, :file_name)
    ');

    $stmt->execute([
        'case_id' => $caseId,
        'sender_id' => $userId,
        'receiver_id' => $receiverId,
        'message' => $message ?: ($fileName ? 'File: ' . $fileName : ''),
        'file_path' => $filePath,
        'file_name' => $fileName
    ]);

    $messageId = $pdo->lastInsertId();

    // Return success response
    json_response([
        'ok' => true,
        'messageId' => $messageId,
        'message' => 'Message sent successfully'
    ], 200);
} catch (PDOException $e) {
    error_log('Chat send error: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
} catch (Exception $e) {
    error_log('Chat send error: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Unexpected error: ' . $e->getMessage()], 500);
}
