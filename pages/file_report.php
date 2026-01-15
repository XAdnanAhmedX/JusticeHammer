<?php
/**
 * File Report Page
 * Allows litigants to create new cases
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Load config constants
$config = require_once __DIR__ . '/../includes/config.php';
define('BASE_URL', $config['BASE_URL'] ?? 'http://127.0.0.1/JusticeHammerDBMS_corrected');

requireLogin();

// Only litigants can file reports
if (!isLitigant()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize_input($_POST['title'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $type = sanitize_input($_POST['type'] ?? '');
    $district = sanitize_input($_POST['district'] ?? '');
    $incidentDate = $_POST['incident_date'] ?? '';
    $contactPref = sanitize_input($_POST['contact_pref'] ?? 'EMAIL');
    $sensitive = isset($_POST['sensitive']) ? 1 : 0;
    $openConsent = isset($_POST['open_consent']) ? 1 : 0;
    $preferredLawyerId = !empty($_POST['preferred_lawyer_id']) ? (int)$_POST['preferred_lawyer_id'] : null;
    
    // Validation
    if (empty($title) || empty($type) || empty($district)) {
        $error = 'Title, type, and district are required';
    } elseif (!in_array($type, ['Crime', 'Gender-Based Violence', 'Land Dispute', 'Corruption', 'Other'])) {
        $error = 'Invalid case type';
    } elseif (!in_array($contactPref, ['EMAIL', 'PHONE', 'ANONYMOUS'])) {
        $error = 'Invalid contact preference';
    } else {
        // Make API call to create case
        $apiUrl = BASE_URL . '/api/create_case.php';
        $postData = http_build_query([
            'title' => $title,
            'description' => $description,
            'type' => $type,
            'district' => $district,
            'incident_date' => $incidentDate,
            'contact_pref' => $contactPref,
            'sensitive' => $sensitive,
            'open_consent' => $openConsent,
            'preferred_lawyer_id' => $preferredLawyerId
        ]);
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($httpCode === 200 && isset($result['ok']) && $result['ok']) {
            $success = 'Case filed successfully! Tracking Code: ' . htmlspecialchars($result['trackingCode']);
            // Clear form
            $_POST = [];
        } else {
            $error = $result['error'] ?? 'Failed to create case. Please try again.';
        }
    }
}

// Get list of verified lawyers for selection
$verifiedLawyers = [];
try {
    $pdo = getDbConnection();
    $stmt = $pdo->query('SELECT id, name, email, district FROM users WHERE role = "LAWYER" AND verified = 1 ORDER BY name');
    $verifiedLawyers = $stmt->fetchAll();
} catch (PDOException $e) {
    // Ignore error, just show empty list
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Report - Justice Hammer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            padding: 2rem 0;
            color: #fff;
        }
        .report-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 2rem;
            width: 100%;
            max-width: 700px;
            margin: 0 auto;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
        }
        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #0d6efd;
            color: #fff;
        }
        .btn-primary {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            border: none;
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
            <a class="navbar-brand" href="dashboard.php"><i class="fas fa-gavel"></i> Justice Hammer</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link active" href="file_report.php">File Report</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="report-card">
            <h2 class="mb-4"><i class="fas fa-file-alt"></i> File a New Report</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label for="title" class="form-label">Case Title *</label>
                    <input type="text" class="form-control" id="title" name="title" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                </div>
                
                <div class="mb-3">
                    <label for="type" class="form-label">Case Type *</label>
                    <select class="form-select" id="type" name="type" required>
                        <option value="">Select Type</option>
                        <option value="Crime" <?php echo (isset($_POST['type']) && $_POST['type'] === 'Crime') ? 'selected' : ''; ?>>Crime</option>
                        <option value="Gender-Based Violence" <?php echo (isset($_POST['type']) && $_POST['type'] === 'Gender-Based Violence') ? 'selected' : ''; ?>>Gender-Based Violence</option>
                        <option value="Land Dispute" <?php echo (isset($_POST['type']) && $_POST['type'] === 'Land Dispute') ? 'selected' : ''; ?>>Land Dispute</option>
                        <option value="Corruption" <?php echo (isset($_POST['type']) && $_POST['type'] === 'Corruption') ? 'selected' : ''; ?>>Corruption</option>
                        <option value="Other" <?php echo (isset($_POST['type']) && $_POST['type'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="district" class="form-label">District *</label>
                    <input type="text" class="form-control" id="district" name="district" required value="<?php echo htmlspecialchars($_POST['district'] ?? ''); ?>" placeholder="e.g., Dhaka, Chittagong">
                </div>
                
                <div class="mb-3">
                    <label for="incident_date" class="form-label">Incident Date</label>
                    <input type="date" class="form-control" id="incident_date" name="incident_date" value="<?php echo htmlspecialchars($_POST['incident_date'] ?? ''); ?>">
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="mb-3">
                    <label for="contact_pref" class="form-label">Contact Preference</label>
                    <select class="form-select" id="contact_pref" name="contact_pref">
                        <option value="EMAIL" <?php echo (!isset($_POST['contact_pref']) || $_POST['contact_pref'] === 'EMAIL') ? 'selected' : ''; ?>>Email</option>
                        <option value="PHONE" <?php echo (isset($_POST['contact_pref']) && $_POST['contact_pref'] === 'PHONE') ? 'selected' : ''; ?>>Phone</option>
                        <option value="ANONYMOUS" <?php echo (isset($_POST['contact_pref']) && $_POST['contact_pref'] === 'ANONYMOUS') ? 'selected' : ''; ?>>Anonymous</option>
                    </select>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="sensitive" name="sensitive" value="1" <?php echo (isset($_POST['sensitive']) && $_POST['sensitive']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="sensitive">Sensitive Case</label>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="open_consent" name="open_consent" value="1" <?php echo (!isset($_POST['open_consent']) || $_POST['open_consent']) ? 'checked' : ''; ?> onchange="toggleLawyerSelection()">
                    <label class="form-check-label" for="open_consent">Open Consent (Any verified lawyer can accept)</label>
                </div>
                
                <div class="mb-3" id="lawyerSelection" style="display: none;">
                    <label for="preferred_lawyer_id" class="form-label">Preferred Lawyer (Optional)</label>
                    <select class="form-select" id="preferred_lawyer_id" name="preferred_lawyer_id">
                        <option value="">Select a Lawyer</option>
                        <?php foreach ($verifiedLawyers as $lawyer): ?>
                            <option value="<?php echo $lawyer['id']; ?>"><?php echo htmlspecialchars($lawyer['name'] . ' (' . $lawyer['district'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary w-100">Submit Report</button>
            </form>
        </div>
    </div>
    
    <script>
        function toggleLawyerSelection() {
            const openConsent = document.getElementById('open_consent').checked;
            const lawyerSelection = document.getElementById('lawyerSelection');
            lawyerSelection.style.display = openConsent ? 'none' : 'block';
        }
        toggleLawyerSelection(); // Initialize
    </script>
</body>
</html>
