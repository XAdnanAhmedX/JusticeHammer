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
    header('Location: dashboard.php');
    exit;
}

try {
    $pdo = getDbConnection();
    $userId = getCurrentUserId();
    $userRole = getCurrentUserRole();

    $stmt = $pdo->prepare('SELECT c.*,
                                  u1.name AS created_by_name,
                                  u2.name AS assigned_to_name,
                                  u3.name AS lawyer_name
                           FROM cases c 
                           LEFT JOIN users u1 ON c.created_by = u1.id 
                           LEFT JOIN users u2 ON c.assigned_to = u2.id
                           LEFT JOIN users u3 ON c.lawyer_id = u3.id
                           WHERE c.id = :caseId');
    $stmt->execute(['caseId' => $caseId]);
    $case = $stmt->fetch();

    if (!$case) {
        header('Location: dashboard.php');
        exit;
    }

    $hasAccess = false;
    $canAccept = false; // For lawyers: can they accept this case?

    if ($userRole === 'ADMIN') {
        $hasAccess = true;
    } elseif ($userRole === 'LITIGANT' && $case['created_by'] == $userId) {
        $hasAccess = true;
    } elseif ($userRole === 'LAWYER') {
        // Check if already assigned
        $stmt = $pdo->prepare('SELECT id FROM timeline WHERE case_id = :caseId AND event = "Lawyer Assigned" AND JSON_EXTRACT(meta, "$.lawyerId") = :userId');
        $stmt->execute(['caseId' => $caseId, 'userId' => $userId]);
        $hasAccess = $stmt->fetch() !== false;

        // If not assigned, check if lawyer can accept
        if (!$hasAccess) {
            // Check if lawyer is verified
            $stmt = $pdo->prepare('SELECT verified FROM users WHERE id = :userId AND role = "LAWYER"');
            $stmt->execute(['userId' => $userId]);
            $lawyer = $stmt->fetch();

            if ($lawyer && $lawyer['verified']) {
                // Get case creation timeline entry
                $stmt = $pdo->prepare('SELECT meta FROM timeline WHERE case_id = :caseId AND event = "Received" ORDER BY created_at ASC LIMIT 1');
                $stmt->execute(['caseId' => $caseId]);
                $receivedEvent = $stmt->fetch();

                if ($receivedEvent) {
                    $meta = json_decode($receivedEvent['meta'], true);
                    $openConsent = isset($meta['open_consent']) ? (int)$meta['open_consent'] : 0;
                    $preferredLawyerId = isset($meta['preferred_lawyer_id']) ? (int)$meta['preferred_lawyer_id'] : null;

                    if ($openConsent == 1 || ($preferredLawyerId && $preferredLawyerId == $userId)) {
                        $canAccept = true;
                        $hasAccess = true; // Allow access to view case details before accepting
                    }
                }
            }
        }
    } elseif ($userRole === 'OFFICIAL' && $case['assigned_to'] == $userId) {
        $hasAccess = true;
    }

    if (!$hasAccess) {
        header('Location: dashboard.php');
        exit;
    }

    $stmt = $pdo->prepare('SELECT t.*, u.name AS actor_name FROM timeline t LEFT JOIN users u ON t.actor_id = u.id WHERE t.case_id = :caseId ORDER BY t.created_at ASC');
    $stmt->execute(['caseId' => $caseId]);
    $timeline = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT * FROM evidence WHERE case_id = :caseId ORDER BY uploaded_at ASC');
    $stmt->execute(['caseId' => $caseId]);
    $evidence = $stmt->fetchAll();

    // Get lawyer ID for chat / UI
    $lawyerId = $case['lawyer_id'] ?? null;
    if (!$lawyerId) {
        // Check timeline for lawyer assignment
        $stmt = $pdo->prepare('
            SELECT JSON_EXTRACT(meta, "$.lawyerId") AS lawyer_id 
            FROM timeline 
            WHERE case_id = :caseId AND event = "Lawyer Assigned" 
            ORDER BY created_at DESC LIMIT 1
        ');
        $stmt->execute(['caseId' => $caseId]);
        $lawyerAssign = $stmt->fetch();
        if ($lawyerAssign && $lawyerAssign['lawyer_id']) {
            $lawyerId = (int)$lawyerAssign['lawyer_id'];
        }
    }

    // Check if litigant has rated
    $hasRated = false;
    if ($userRole === 'LITIGANT' && $lawyerId) {
        $stmt = $pdo->prepare('SELECT id FROM lawyer_ratings WHERE case_id = :caseId AND litigant_id = :litigantId');
        $stmt->execute(['caseId' => $caseId, 'litigantId' => $userId]);
        $hasRated = $stmt->fetch() !== false;
    }

    // Check if lawyer can mark as solved
    $canMarkSolved = false;
    if ($userRole === 'LAWYER' && $case['status'] !== 'SOLVED' && $case['status'] !== 'CLOSED') {
        if ($case['lawyer_id'] == $userId) {
            $canMarkSolved = true;
        } else {
            $stmt = $pdo->prepare('
                SELECT id FROM timeline 
                WHERE case_id = :caseId 
                AND event = "Lawyer Assigned" 
                AND JSON_EXTRACT(meta, "$.lawyerId") = :userId
            ');
            $stmt->execute(['caseId' => $caseId, 'userId' => $userId]);
            $canMarkSolved = $stmt->fetch() !== false;
        }
    }
} catch (PDOException $e) {
    error_log('Case details error: ' . $e->getMessage());
    header('Location: dashboard.php');
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
            color: #f9fafb;
        }

        /* Navbar */
        .navbar {
            background: rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(10px);
        }

        .navbar-brand,
        .navbar .nav-link {
            color: #ffffff !important;
            font-weight: 500;
        }

        .navbar .nav-link:hover {
            color: #93c5fd !important;
        }

        /* Cards */
        .details-card {
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 1rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.35);
        }

        .details-card h2,
        .details-card h4,
        .details-card h5 {
            color: #ffffff;
        }

        /* Text visibility fix */
        .text-muted {
            color: #d1d5db !important;
        }

        strong {
            color: #e5e7eb;
        }

        /* Tables */
        .table-dark {
            --bs-table-bg: transparent;
            --bs-table-striped-bg: rgba(255, 255, 255, 0.04);
            --bs-table-color: #f9fafb;
            border-color: rgba(255, 255, 255, 0.15);
        }

        .table-dark th {
            color: #ffffff;
            width: 30%;
        }

        .table-dark td {
            color: #e5e7eb;
        }

        /* Timeline blocks */
        .timeline>div {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        pre {
            color: #e5e7eb;
            background: rgba(0, 0, 0, 0.45) !important;
        }

        /* Evidence list */
        .list-group-item {
            background: rgba(255, 255, 255, 0.05) !important;
            border-color: rgba(255, 255, 255, 0.12);
            color: #f9fafb;
        }

        /* Buttons */
        .btn-secondary {
            background: #334155;
            border: none;
        }

        .btn-secondary:hover {
            background: #475569;
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
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">

        <div class="details-card">
            <h2><?php echo htmlspecialchars($case['title']); ?></h2>
            <p class="text-muted">
                Tracking Code: <strong><?php echo htmlspecialchars($case['tracking_code']); ?></strong>
            </p>
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
                    <th>Assigned To (Lawyer):</th>
                    <td><?php echo !empty($case['lawyer_name']) ? htmlspecialchars($case['lawyer_name']) : 'Not assigned'; ?></td>
                </tr>
                <tr>
                    <th>Assigned To (Official):</th>
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
            <?php foreach ($timeline as $event): ?>
                <div class="mb-3 p-3 rounded">
                    <strong><?php echo htmlspecialchars($event['event']); ?></strong>
                    <?php if ($event['actor_name']): ?>
                        <span class="text-muted">by <?php echo htmlspecialchars($event['actor_name']); ?></span>
                    <?php endif; ?>
                    <br>
                    <small class="text-muted"><?php echo date('Y-m-d H:i:s', strtotime($event['created_at'])); ?></small>

                    <?php if ($event['meta']): ?>
                        <?php $meta = json_decode($event['meta'], true); ?>
                        <?php if ($meta): ?>
                            <pre class="small mt-2"><?php echo htmlspecialchars(json_encode($meta, JSON_PRETTY_PRINT)); ?></pre>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($evidence)): ?>
            <div class="details-card">
                <h4>Evidence (<?php echo count($evidence); ?>)</h4>
                <ul class="list-group">
                    <?php foreach ($evidence as $ev): ?>
                        <li class="list-group-item">
                            <strong><?php echo htmlspecialchars($ev['filename']); ?></strong><br>
                            <small class="text-muted">SHA256: <?php echo htmlspecialchars($ev['sha256']); ?></small><br>
                            <small class="text-muted">Uploaded: <?php echo date('Y-m-d H:i:s', strtotime($ev['uploaded_at'])); ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($lawyerId && ($userRole === 'LITIGANT' || $userRole === 'LAWYER')): ?>
            <div class="details-card">
                <h4><i class="fas fa-comments"></i> Chat</h4>
                <div id="chatMessages" style="max-height: 400px; overflow-y: auto; margin-bottom: 1rem; padding: 1rem; background: rgba(0,0,0,0.3); border-radius: 10px;">
                    <!-- Messages will be loaded here -->
                </div>
                <form id="chatForm" enctype="multipart/form-data">
                    <input type="hidden" name="caseId" value="<?php echo $caseId; ?>">
                    <div class="input-group mb-2">
                        <input type="text" class="form-control" id="chatMessage" name="message" placeholder="Type your message..." style="background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.2);">
                        <input type="file" class="form-control" id="chatFile" name="file" accept=".pdf,.jpg,.jpeg,.png" style="background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.2); max-width: 200px;">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($canMarkSolved): ?>
            <div class="details-card">
                <button id="markSolvedBtn" class="btn btn-success" onclick="markCaseSolved(<?php echo $caseId; ?>)">
                    <i class="fas fa-check-circle"></i> Mark Case as Solved
                </button>
            </div>
        <?php endif; ?>

        <?php if ($userRole === 'LITIGANT' && $case['status'] === 'SOLVED' && $lawyerId && !$hasRated): ?>
            <div class="details-card">
                <h4><i class="fas fa-star"></i> Rate Your Lawyer</h4>
                <div class="mb-3">
                    <label class="form-label">Rating (1-5 stars)</label>
                    <select class="form-select" id="ratingSelect" style="background: rgba(255,255,255,0.1); color: #fff; max-width: 200px;">
                        <option value="">Select rating</option>
                        <option value="5">5 - Excellent</option>
                        <option value="4">4 - Very Good</option>
                        <option value="3">3 - Good</option>
                        <option value="2">2 - Fair</option>
                        <option value="1">1 - Poor</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Comment (Optional)</label>
                    <textarea class="form-control" id="ratingComment" rows="3" style="background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.2);"></textarea>
                </div>
                <button class="btn btn-primary" onclick="submitRating(<?php echo $caseId; ?>, <?php echo $lawyerId; ?>)">
                    <i class="fas fa-star"></i> Submit Rating
                </button>
            </div>
        <?php endif; ?>

        <div class="text-center mt-4">
            <?php if ($canAccept): ?>
                <button id="acceptCaseBtn" class="btn btn-success me-2" onclick="acceptCase(<?php echo $caseId; ?>)">
                    <i class="fas fa-check"></i> Accept Case
                </button>
            <?php endif; ?>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>

    </div>

    <script>
        let chatInterval = null;

        // Load chat messages
        function loadChatMessages() {
            fetch('../api/chat_get.php?caseId=<?php echo $caseId; ?>', {
                    credentials: 'same-origin'
                })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        const container = document.getElementById('chatMessages');
                        container.innerHTML = '';
                        data.messages.forEach(msg => {
                            const isSender = msg.sender_id == <?php echo $userId; ?>;
                            const div = document.createElement('div');
                            div.className = 'mb-2 p-2 rounded';
                            div.style.background = isSender ? 'rgba(59, 130, 246, 0.3)' : 'rgba(255, 255, 255, 0.1)';
                            div.style.textAlign = isSender ? 'right' : 'left';
                            const msgText = msg.message ? escapeHtml(msg.message) : '';
                            const fileLink = msg.file_name ? `<br><a href="../${escapeHtml(msg.file_path)}" target="_blank" class="text-light"><i class="fas fa-file"></i> ${escapeHtml(msg.file_name)}</a>` : '';
                            div.innerHTML = `
                    <strong>${escapeHtml(msg.sender_name)}</strong><br>
                    ${msgText}
                    ${fileLink}
                    <br><small class="text-muted">${new Date(msg.created_at).toLocaleString()}</small>
                `;
                            container.appendChild(div);
                        });
                        container.scrollTop = container.scrollHeight;
                    }
                });
        }

        // Send chat message
        document.getElementById('chatForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('../api/chat_send.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        document.getElementById('chatMessage').value = '';
                        document.getElementById('chatFile').value = '';
                        loadChatMessages();
                    } else {
                        alert('Error: ' + (data.error || 'Failed to send message'));
                    }
                });
        });

        // Mark case as solved
        function markCaseSolved(caseId) {
            if (!confirm('Are you sure you want to mark this case as solved?')) return;
            fetch('../api/case_solved.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        caseId: caseId
                    }),
                    credentials: 'same-origin'
                })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        alert('Case marked as solved!');
                        location.reload();
                    } else {
                        alert('Error: ' + (data.error || 'Failed to mark case as solved'));
                    }
                });
        }

        // Submit rating
        function submitRating(caseId, lawyerId) {
            const rating = document.getElementById('ratingSelect').value;
            const comment = document.getElementById('ratingComment').value;
            if (!rating) {
                alert('Please select a rating');
                return;
            }
            fetch('../api/rate_lawyer.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        caseId: caseId,
                        lawyerId: lawyerId,
                        rating: parseInt(rating),
                        comment: comment
                    }),
                    credentials: 'same-origin'
                })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        alert('Rating submitted successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + (data.error || 'Failed to submit rating'));
                    }
                });
        }

        function escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        // Load chat on page load and refresh every 5 seconds
        <?php if ($lawyerId && ($userRole === 'LITIGANT' || $userRole === 'LAWYER')): ?>
            loadChatMessages();
            chatInterval = setInterval(loadChatMessages, 5000);
        <?php endif; ?>

        function acceptCase(caseId) {
            if (!confirm('Are you sure you want to accept this case?')) {
                return;
            }

            const btn = document.getElementById('acceptCaseBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Accepting...';

            fetch('../api/lawyer_accept.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        caseId: caseId,
                        lawyerId: <?php echo $userId; ?>
                    }),
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.ok) {
                        alert('Case accepted successfully!');
                        window.location.reload();
                    } else {
                        alert('Error: ' + (data.error || 'Failed to accept case'));
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-check"></i> Accept Case';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check"></i> Accept Case';
                });
        }
    </script>
</body>

</html>