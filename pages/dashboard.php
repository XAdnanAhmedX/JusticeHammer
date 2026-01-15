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

try {
    if ($userRole === 'ADMIN') {
        // Admin sees all cases
        $stmt = $pdo->query('SELECT c.*, u1.name AS created_by_name, u2.name AS assigned_to_name 
                            FROM cases c 
                            LEFT JOIN users u1 ON c.created_by = u1.id 
                            LEFT JOIN users u2 ON c.assigned_to = u2.id 
                            ORDER BY c.created_at DESC 
                            LIMIT 50');
        $cases = $stmt->fetchAll();
    } elseif ($userRole === 'LITIGANT') {
        // Litigant sees their own cases
        $stmt = $pdo->prepare('SELECT c.*, u1.name AS created_by_name, u2.name AS assigned_to_name 
                              FROM cases c 
                              LEFT JOIN users u1 ON c.created_by = u1.id 
                              LEFT JOIN users u2 ON c.assigned_to = u2.id 
                              WHERE c.created_by = :userId 
                              ORDER BY c.created_at DESC');
        $stmt->execute(['userId' => $userId]);
        $cases = $stmt->fetchAll();
    } elseif ($userRole === 'LAWYER') {
        // Lawyer sees cases where they are assigned (check timeline)
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
    } elseif ($userRole === 'OFFICIAL') {
        // Official sees cases assigned to them
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
            color: #fff;
        }
        .dashboard-card {
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
        .case-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .badge {
            padding: 0.5rem 0.75rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><i class="fas fa-gavel"></i> Justice Hammer</a>
            <div class="navbar-nav ms-auto">
                <?php if (isLitigant()): ?>
                    <a class="nav-link" href="file_report.php">File Report</a>
                <?php endif; ?>
                <span class="navbar-text me-3">Welcome, <?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?></span>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="dashboard-card">
            <h2><i class="fas fa-tachometer-alt"></i> Dashboard</h2>
            <p class="text-muted">Role: <?php echo htmlspecialchars($userRole); ?></p>
        </div>
        
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
                                    ?>"><?php echo htmlspecialchars($case['status']); ?></span><br>
                                    <strong>Created:</strong> <?php echo date('Y-m-d H:i', strtotime($case['created_at'])); ?>
                                </p>
                            </div>
                            <a href="case_details.php?id=<?php echo $case['id']; ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
