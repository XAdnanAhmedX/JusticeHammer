<?php

/**
 * Admin Dashboard Page
 * Shows all admin features: cases, verification requests, lawyer list
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

if (!isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$pdo = getDbConnection();
$activeTab = $_GET['tab'] ?? 'cases';

// Get all cases
$allCases = [];
$stmt = $pdo->query('SELECT c.*, u1.name AS created_by_name, u2.name AS assigned_to_name, u3.name AS lawyer_name, u3.id AS lawyer_id
                     FROM cases c 
                     LEFT JOIN users u1 ON c.created_by = u1.id 
                     LEFT JOIN users u2 ON c.assigned_to = u2.id
                     LEFT JOIN users u3 ON c.lawyer_id = u3.id
                     ORDER BY c.created_at DESC');
$allCases = $stmt->fetchAll();

// Get pending verification requests
$pendingVerifications = [];
$stmt = $pdo->query('SELECT u.*, t.meta, t.created_at AS request_date
                     FROM users u
                     LEFT JOIN timeline t ON u.id = t.actor_id AND t.event = "Verification Request"
                     WHERE u.role = "LAWYER" AND u.verified = 0
                     ORDER BY u.created_at ASC');
$pendingVerifications = $stmt->fetchAll();

// Get all lawyers with average ratings
$lawyers = [];
$stmt = $pdo->query('
    SELECT u.*, 
           COALESCE(AVG(lr.rating), 0) AS avg_rating,
           COUNT(lr.id) AS total_ratings
    FROM users u
    LEFT JOIN lawyer_ratings lr ON u.id = lr.lawyer_id
    WHERE u.role = "LAWYER"
    GROUP BY u.id
    ORDER BY avg_rating DESC, total_ratings DESC, u.name ASC
');
$lawyers = $stmt->fetchAll();

// Get verified lawyers for assignment dropdown
$verifiedLawyers = [];
$stmt = $pdo->query('SELECT id, name, district FROM users WHERE role = "LAWYER" AND verified = 1 ORDER BY name');
$verifiedLawyers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Justice Hammer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            padding: 2rem 0;
            color: #f9fafb;
        }

        .navbar {
            background: rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(10px);
        }

        .navbar-brand,
        .navbar .nav-link {
            color: #ffffff !important;
        }

        .dashboard-card {
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 1rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.35);
        }

        .nav-tabs {
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .nav-tabs .nav-link {
            color: #d1d5db;
            border: none;
            border-bottom: 2px solid transparent;
        }

        .nav-tabs .nav-link.active {
            color: #ffffff;
            background: transparent;
            border-bottom-color: #3b82f6;
        }

        .case-card,
        .lawyer-card,
        .verification-card {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .text-muted {
            color: #d1d5db !important;
        }

        .badge {
            padding: 0.45rem 0.7rem;
        }

        .stars {
            color: #fbbf24;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><i class="fas fa-gavel"></i> Justice Hammer</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link active" href="admin_dashboard.php">Admin Panel</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="dashboard-card">
            <h2><i class="fas fa-user-shield"></i> Admin Dashboard</h2>

            <ul class="nav nav-tabs mb-4">
                <li class="nav-item">
                    <a class="nav-link <?php echo $activeTab === 'cases' ? 'active' : ''; ?>" href="?tab=cases">All Cases</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activeTab === 'verifications' ? 'active' : ''; ?>" href="?tab=verifications">
                        Verification Requests <span class="badge bg-danger"><?php echo count($pendingVerifications); ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activeTab === 'lawyers' ? 'active' : ''; ?>" href="?tab=lawyers">Lawyers</a>
                </li>
            </ul>

            <?php if ($activeTab === 'cases'): ?>
                <h3>All Cases (<?php echo count($allCases); ?>)</h3>
                <?php foreach ($allCases as $case): ?>
                    <div class="case-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h5><?php echo htmlspecialchars($case['title']); ?></h5>
                                <p class="text-muted mb-2">
                                    <strong>Tracking:</strong> <?php echo htmlspecialchars($case['tracking_code']); ?><br>
                                    <strong>Type:</strong> <?php echo htmlspecialchars($case['type']); ?><br>
                                    <strong>District:</strong> <?php echo htmlspecialchars($case['district']); ?><br>
                                    <strong>Status:</strong>
                                    <span class="badge bg-<?php
                                                            echo match ($case['status']) {
                                                                'RECEIVED' => 'secondary',
                                                                'TRIAGED' => 'info',
                                                                'ASSIGNED' => 'primary',
                                                                'IN_PROGRESS' => 'warning',
                                                                'CLOSED' => 'success',
                                                                'SOLVED' => 'success',
                                                                default => 'secondary'
                                                            };
                                                            ?>"><?php echo htmlspecialchars($case['status']); ?></span><br>
                                    <strong>Created by:</strong> <?php echo htmlspecialchars($case['created_by_name']); ?><br>
                                    <?php if ($case['lawyer_name']): ?>
                                        <strong>Lawyer:</strong> <?php echo htmlspecialchars($case['lawyer_name']); ?><br>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div>
                                <?php if (!$case['lawyer_id']): ?>
                                    <button class="btn btn-sm btn-primary" onclick="showAssignModal(<?php echo $case['id']; ?>, '<?php echo htmlspecialchars($case['title'], ENT_QUOTES); ?>')">
                                        Assign Lawyer
                                    </button>
                                <?php endif; ?>
                                <a href="case_details.php?id=<?php echo $case['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

            <?php elseif ($activeTab === 'verifications'): ?>
                <h3>Pending Verification Requests (<?php echo count($pendingVerifications); ?>)</h3>
                <?php if (empty($pendingVerifications)): ?>
                    <p class="text-muted">No pending verification requests.</p>
                <?php else: ?>
                    <?php foreach ($pendingVerifications as $lawyer): ?>
                        <div class="verification-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h5><?php echo htmlspecialchars($lawyer['name']); ?></h5>
                                    <p class="text-muted mb-2">
                                        <strong>Email:</strong> <?php echo htmlspecialchars($lawyer['email']); ?><br>
                                        <strong>District:</strong> <?php echo htmlspecialchars($lawyer['district'] ?? 'N/A'); ?><br>
                                        <strong>Registered:</strong> <?php echo date('Y-m-d H:i', strtotime($lawyer['created_at'])); ?><br>
                                        <?php if ($lawyer['verification_file_path']): ?>
                                            <strong>Document:</strong>
                                            <a href="../<?php echo htmlspecialchars($lawyer['verification_file_path']); ?>" target="_blank" class="text-light">
                                                <i class="fas fa-file"></i> View Document
                                            </a>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div>
                                    <button class="btn btn-sm btn-success" onclick="verifyLawyer(<?php echo $lawyer['id']; ?>, 1)">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="verifyLawyer(<?php echo $lawyer['id']; ?>, 0)">
                                        <i class="fas fa-times"></i> Deny
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            <?php elseif ($activeTab === 'lawyers'): ?>
                <h3>All Lawyers (<?php echo count($lawyers); ?>)</h3>
                <?php foreach ($lawyers as $lawyer): ?>
                    <div class="lawyer-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h5><?php echo htmlspecialchars($lawyer['name']); ?></h5>
                                <p class="text-muted mb-2">
                                    <strong>Email:</strong> <?php echo htmlspecialchars($lawyer['email']); ?><br>
                                    <strong>District:</strong> <?php echo htmlspecialchars($lawyer['district'] ?? 'N/A'); ?><br>
                                    <strong>Status:</strong>
                                    <span class="badge bg-<?php echo $lawyer['verified'] ? 'success' : 'warning'; ?>">
                                        <?php echo $lawyer['verified'] ? 'Verified' : 'Pending'; ?>
                                    </span><br>
                                    <strong>Average Rating:</strong>
                                    <?php if ($lawyer['total_ratings'] > 0): ?>
                                        <span class="stars">
                                            <?php
                                            $avgRating = round($lawyer['avg_rating'], 1);
                                            for ($i = 1; $i <= 5; $i++) {
                                                echo $i <= $avgRating ? '★' : '☆';
                                            }
                                            ?>
                                        </span>
                                        <?php echo $avgRating; ?>/5.0 (<?php echo $lawyer['total_ratings']; ?> ratings)
                                    <?php else: ?>
                                        <span class="text-muted">No ratings yet</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Assign Lawyer Modal -->
    <div class="modal fade" id="assignModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="background: #1a1a2e; color: #fff; border: 1px solid rgba(255,255,255,0.2);">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Lawyer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="caseTitle"></p>
                    <select class="form-select" id="lawyerSelect" style="background: rgba(255,255,255,0.1); color: #fff;">
                        <option value="">Select a Lawyer</option>
                        <?php foreach ($verifiedLawyers as $lawyer): ?>
                            <option value="<?php echo $lawyer['id']; ?>"><?php echo htmlspecialchars($lawyer['name'] . ' (' . $lawyer['district'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="assignLawyer()">Assign</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentCaseId = null;

        function showAssignModal(caseId, caseTitle) {
            currentCaseId = caseId;
            document.getElementById('caseTitle').textContent = 'Case: ' + caseTitle;
            document.getElementById('lawyerSelect').value = '';
            new bootstrap.Modal(document.getElementById('assignModal')).show();
        }

        function assignLawyer() {
            const lawyerId = document.getElementById('lawyerSelect').value;
            if (!lawyerId) {
                alert('Please select a lawyer');
                return;
            }

            fetch('../api/admin_assign_lawyer.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        caseId: currentCaseId,
                        lawyerId: parseInt(lawyerId)
                    }),
                    credentials: 'same-origin'
                })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        alert('Lawyer assigned successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + (data.error || 'Failed to assign lawyer'));
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('An error occurred');
                });
        }

        function verifyLawyer(userId, verified) {
            const action = verified ? 'approve' : 'deny';
            if (!confirm(`Are you sure you want to ${action} this lawyer?`)) {
                return;
            }

            fetch('../api/admin_verify.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        userId: userId,
                        verified: verified,
                        action: action
                    }),
                    credentials: 'same-origin'
                })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        alert(data.message || 'Action completed successfully');
                        location.reload();
                    } else {
                        alert('Error: ' + (data.error || 'Failed to process request'));
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('An error occurred');
                });
        }
    </script>
</body>

</html>