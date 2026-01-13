<?php
/**
 * Case Details Page
 * Shows detailed information about a specific case
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$caseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($caseId <= 0) {
    header('Location: /pages/dashboard.php');
    exit;
}

try {
    $pdo = getDbConnection();
    $userId = getCurrentUserId();
    $userRole = getCurrentUserRole();
    
    // Get case with visibility check
    $stmt = $pdo->prepare('SELECT c.*, u1.name AS created_by_name, u2.name AS assigned_to_name 
                           FROM cases c 
                           LEFT JOIN users u1 ON c.created_by = u1.id 
                           LEFT JOIN users u2 ON c.assigned_to = u2.id 
                           WHERE c.id = :caseId');
    $stmt->execute(['caseId' => $caseId]);
    $case = $stmt->fetch();
    
    if (!$case) {
        header('Location: /pages/dashboard.php');
        exit;
    }
    
    // Visibility check (simplified - should match dashboard logic)
    $hasAccess = false;
    if ($userRole === 'ADMIN') {
        $hasAccess = true;
    } elseif ($userRole === 'LITIGANT' && $case['created_by'] == $userId) {
        $hasAccess = true;
    } elseif ($userRole === 'LAWYER') {
        // Check if lawyer is assigned
        $stmt = $pdo->prepare('SELECT id FROM timeline WHERE case_id = :caseId AND event = "Lawyer Assigned" AND JSON_EXTRACT(meta, "$.lawyerId") = :userId');
        $stmt->execute(['caseId' => $caseId, 'userId' => $userId]);
        $hasAccess = $stmt->fetch() !== false;
    } elseif ($userRole === 'OFFICIAL' && $case['assigned_to'] == $userId) {
        $hasAccess = true;
    }
    
    if (!$hasAccess) {
        header('Location: /pages/dashboard.php');
        exit;
    }
    
    // Get timeline events
    $stmt = $pdo->prepare('SELECT t.*, u.name AS actor_name FROM timeline t LEFT JOIN users u ON t.actor_id = u.id WHERE t.case_id = :caseId ORDER BY t.created_at ASC');
    $stmt->execute(['caseId' => $caseId]);
    $timeline = $stmt->fetchAll();
    
    // Get evidence
    $stmt = $pdo->prepare('SELECT * FROM evidence WHERE case_id = :caseId ORDER BY uploaded_at ASC');
    $stmt->execute(['caseId' => $caseId]);
    $evidence = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('Case details error: ' . $e->getMessage());
    header('Location: /pages/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Case Details - Justice Hammer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            padding: 2rem 0;
            color: #fff;
        }
        .details-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 1rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        .navbar {
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="/pages/dashboard.php"><i class="fas fa-gavel"></i> Justice Hammer</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="/pages/dashboard.php">Dashboard</a>
                <a class="nav-link" href="/pages/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="details-card">
            <h2><?php echo htmlspecialchars($case['title']); ?></h2>
            <p class="text-muted">Tracking Code: <strong><?php echo htmlspecialchars($case['tracking_code']); ?></strong></p>
        </div>
        
        <div class="details-card">
            <h4>Case Information</h4>
            <table class="table table-dark">
                <tr>
                    <th>Type:</th>
                    <td><?php echo htmlspecialchars($case['type']); ?></td>
                </tr>
                <tr>
                    <th>District:</th>
                    <td><?php echo htmlspecialchars($case['district']); ?></td>
                </tr>
                <tr>
                    <th>Status:</th>
                    <td><span class="badge bg-primary"><?php echo htmlspecialchars($case['status']); ?></span></td>
                </tr>
                <tr>
                    <th>Incident Date:</th>
                    <td><?php echo $case['incident_date'] ? htmlspecialchars($case['incident_date']) : 'N/A'; ?></td>
                </tr>
                <tr>
                    <th>Created By:</th>
                    <td><?php echo htmlspecialchars($case['created_by_name']); ?></td>
                </tr>
                <tr>
                    <th>Assigned To:</th>
                    <td><?php echo $case['assigned_to_name'] ? htmlspecialchars($case['assigned_to_name']) : 'Not assigned'; ?></td>
                </tr>
                <tr>
                    <th>Created At:</th>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($case['created_at'])); ?></td>
                </tr>
            </table>
            
            <?php if ($case['description']): ?>
                <h5>Description:</h5>
                <p><?php echo nl2br(htmlspecialchars($case['description'])); ?></p>
            <?php endif; ?>
        </div>
        
        <div class="details-card">
            <h4>Timeline</h4>
            <div class="timeline">
                <?php foreach ($timeline as $event): ?>
                    <div class="mb-3 p-3" style="background: rgba(255,255,255,0.03); border-radius: 5px;">
                        <strong><?php echo htmlspecialchars($event['event']); ?></strong>
                        <?php if ($event['actor_name']): ?>
                            <span class="text-muted">by <?php echo htmlspecialchars($event['actor_name']); ?></span>
                        <?php endif; ?>
                        <br>
                        <small class="text-muted"><?php echo date('Y-m-d H:i:s', strtotime($event['created_at'])); ?></small>
                        <?php if ($event['meta']): ?>
                            <?php $meta = json_decode($event['meta'], true); ?>
                            <?php if ($meta): ?>
                                <pre class="text-muted small mt-2" style="background: rgba(0,0,0,0.3); padding: 0.5rem; border-radius: 3px;"><?php echo htmlspecialchars(json_encode($meta, JSON_PRETTY_PRINT)); ?></pre>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <?php if (!empty($evidence)): ?>
        <div class="details-card">
            <h4>Evidence (<?php echo count($evidence); ?>)</h4>
            <ul class="list-group list-group-dark">
                <?php foreach ($evidence as $ev): ?>
                    <li class="list-group-item bg-dark text-light">
                        <strong><?php echo htmlspecialchars($ev['filename']); ?></strong><br>
                        <small class="text-muted">SHA256: <?php echo htmlspecialchars($ev['sha256']); ?></small><br>
                        <small class="text-muted">Uploaded: <?php echo date('Y-m-d H:i:s', strtotime($ev['uploaded_at'])); ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <div class="text-center mt-4">
            <a href="/pages/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
