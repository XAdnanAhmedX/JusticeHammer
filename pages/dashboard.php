<?php
/**
 * Dashboard Page
 * Role-based dashboard showing cases
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pdo = getDbConnection();
$userId = getCurrentUserId();
$userRole = getCurrentUserRole();
$cases = [];
$availableCases = []; // For lawyers: cases available for acceptance

try {
    if ($userRole === 'ADMIN') {
        // Redirect to admin dashboard
        header('Location: admin_dashboard.php');
        exit;
    } elseif ($userRole === 'LITIGANT') {
        $stmt = $pdo->prepare('SELECT c.*, u1.name AS created_by_name, u2.name AS assigned_to_name 
                              FROM cases c 
                              LEFT JOIN users u1 ON c.created_by = u1.id 
                              LEFT JOIN users u2 ON c.assigned_to = u2.id 
                              WHERE c.created_by = :userId 
                              ORDER BY c.created_at DESC');
        $stmt->execute(['userId' => $userId]);
        $cases = $stmt->fetchAll();
    } elseif ($userRole === 'LAWYER') {
        // Get assigned cases
        $stmt = $pdo->prepare('SELECT DISTINCT c.*, u1.name AS created_by_name, u2.name AS assigned_to_name 
                              FROM cases c 
                              LEFT JOIN users u1 ON c.created_by = u1.id 
                              LEFT JOIN users u2 ON c.assigned_to = u2.id 
                              INNER JOIN timeline t ON c.id = t.case_id 
                              WHERE t.event = "Lawyer Assigned" 
                              AND JSON_EXTRACT(t.meta, "$.lawyerId") = :userId 
                              ORDER BY c.created_at DESC');
        $stmt->execute(['userId' => $userId]);
        $cases = $stmt->fetchAll();
        
        // Get available cases (open consent or preferred)
        // First check if lawyer is verified
        $stmt = $pdo->prepare('SELECT verified FROM users WHERE id = :userId AND role = "LAWYER"');
        $stmt->execute(['userId' => $userId]);
        $lawyer = $stmt->fetch();
        
        if ($lawyer && $lawyer['verified']) {
            // Get all cases with "Received" event
            $stmt = $pdo->prepare('
                SELECT DISTINCT c.*, u1.name AS created_by_name, u2.name AS assigned_to_name, t.meta
                FROM cases c
                LEFT JOIN users u1 ON c.created_by = u1.id
                LEFT JOIN users u2 ON c.assigned_to = u2.id
                INNER JOIN timeline t ON c.id = t.case_id AND t.event = "Received"
                WHERE NOT EXISTS (
                    SELECT 1 FROM timeline t2 
                    WHERE t2.case_id = c.id 
                    AND t2.event = "Lawyer Assigned" 
                    AND JSON_EXTRACT(t2.meta, "$.lawyerId") = :userId
                )
                ORDER BY c.created_at DESC
            ');
            $stmt->execute(['userId' => $userId]);
            $allCases = $stmt->fetchAll();
            
            // Filter cases where lawyer can accept
            foreach ($allCases as $case) {
                $meta = json_decode($case['meta'], true);
                $openConsent = isset($meta['open_consent']) ? (int)$meta['open_consent'] : 0;
                $preferredLawyerId = isset($meta['preferred_lawyer_id']) ? (int)$meta['preferred_lawyer_id'] : null;
                
                if ($openConsent == 1 || ($preferredLawyerId && $preferredLawyerId == $userId)) {
                    $availableCases[] = $case;
                }
            }
        }
    } elseif ($userRole === 'OFFICIAL') {
        $stmt = $pdo->prepare('SELECT c.*, u1.name AS created_by_name, u2.name AS assigned_to_name 
                              FROM cases c 
                              LEFT JOIN users u1 ON c.created_by = u1.id 
                              LEFT JOIN users u2 ON c.assigned_to = u2.id 
                              WHERE c.assigned_to = :userId 
                              ORDER BY c.created_at DESC');
        $stmt->execute(['userId' => $userId]);
        $cases = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log('Dashboard error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Justice Hammer</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            padding: 2rem 0;
            color: #f9fafb;
        }

        /* Navbar */
        .navbar {
            background: rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(10px);
        }

        .navbar-brand,
        .navbar .nav-link,
        .navbar-text {
            color: #ffffff !important;
            font-weight: 500;
        }

        .navbar .nav-link:hover {
            color: #93c5fd !important;
        }

        /* Dashboard cards */
        .dashboard-card {
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 1rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.35);
        }

        .dashboard-card h2,
        .dashboard-card h3 {
            color: #ffffff;
        }

        /* Case cards */
        .case-card {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .case-card h5 {
            color: #ffffff;
        }

        /* Text visibility fix */
        .text-muted {
            color: #d1d5db !important;
        }

        strong {
            color: #e5e7eb;
        }

        /* Badges */
        .badge {
            padding: 0.45rem 0.7rem;
            font-weight: 500;
        }

        /* Buttons */
        .btn-outline-primary {
            color: #93c5fd;
            border-color: #3b82f6;
        }

        .btn-outline-primary:hover {
            background: #2563eb;
            color: #ffffff;
            border-color: #2563eb;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">
            <i class="fas fa-gavel"></i> Justice Hammer
        </a>
        <div class="navbar-nav ms-auto">
            <?php if (isLitigant()): ?>
                <a class="nav-link" href="file_report.php">File Report</a>
            <?php endif; ?>
            <span class="navbar-text me-3">
                Welcome, <?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?>
            </span>
            <a class="nav-link" href="logout.php">Logout</a>
        </div>
    </div>
</nav>

<div class="container">

    <div class="dashboard-card">
        <h2><i class="fas fa-tachometer-alt"></i> Dashboard</h2>
        <p class="text-muted">Role: <?php echo htmlspecialchars($userRole); ?></p>
    </div>

    <?php if ($userRole === 'LAWYER' && !empty($availableCases)): ?>
    <div class="dashboard-card">
        <h3>Available Cases (<?php echo count($availableCases); ?>)</h3>
        <p class="text-muted">Cases you can accept</p>
        <?php foreach ($availableCases as $case): ?>
            <div class="case-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5><?php echo htmlspecialchars($case['title']); ?></h5>
                        <p class="text-muted mb-2">
                            <strong>Tracking Code:</strong> <?php echo htmlspecialchars($case['tracking_code']); ?><br>
                            <strong>Type:</strong> <?php echo htmlspecialchars($case['type']); ?><br>
                            <strong>District:</strong> <?php echo htmlspecialchars($case['district']); ?><br>
                            <strong>Status:</strong>
                            <span class="badge bg-secondary"><?php echo htmlspecialchars($case['status']); ?></span><br>
                            <strong>Created:</strong> <?php echo date('Y-m-d H:i', strtotime($case['created_at'])); ?>
                        </p>
                    </div>
                    <div>
                        <a href="case_details.php?id=<?php echo $case['id']; ?>" class="btn btn-sm btn-outline-primary">
                            View Details
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="dashboard-card">
        <h3>My Cases (<?php echo count($cases); ?>)</h3>

        <?php if (empty($cases)): ?>
            <p class="text-muted">No cases found.</p>
            <?php if (isLitigant()): ?>
                <a href="file_report.php" class="btn btn-primary">File Your First Report</a>
            <?php endif; ?>
        <?php else: ?>
            <?php foreach ($cases as $case): ?>
                <div class="case-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5><?php echo htmlspecialchars($case['title']); ?></h5>
                            <p class="text-muted mb-2">
                                <strong>Tracking Code:</strong> <?php echo htmlspecialchars($case['tracking_code']); ?><br>
                                <strong>Type:</strong> <?php echo htmlspecialchars($case['type']); ?><br>
                                <strong>District:</strong> <?php echo htmlspecialchars($case['district']); ?><br>
                                <strong>Status:</strong>
                                <span class="badge bg-<?php 
                                    echo match($case['status']) {
                                        'RECEIVED' => 'secondary',
                                        'TRIAGED' => 'info',
                                        'ASSIGNED' => 'primary',
                                        'IN_PROGRESS' => 'warning',
                                        'CLOSED' => 'success',
                                        default => 'secondary'
                                    };
                                ?>">
                                    <?php echo htmlspecialchars($case['status']); ?>
                                </span><br>
                                <strong>Created:</strong> <?php echo date('Y-m-d H:i', strtotime($case['created_at'])); ?>
                            </p>
                        </div>
                        <a href="case_details.php?id=<?php echo $case['id']; ?>" class="btn btn-sm btn-outline-primary">
                            View Details
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
</div>

</body>
</html>