<?php
/**
 * Registration Page
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Load config constants
$config = require_once __DIR__ . '/../includes/config.php';
define('UPLOADS_DIR', $config['UPLOADS_DIR'] ?? 'uploads');
define('MAX_FILE_SIZE', $config['MAX_FILE_SIZE'] ?? 10485760);
define('BASE_URL', $config['BASE_URL'] ?? 'http://127.0.0.1/JusticeHammerDBMS_corrected');

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize_input($_POST['name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = sanitize_input($_POST['role'] ?? '');
    $district = sanitize_input($_POST['district'] ?? '');
    
    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($role)) {
        $error = 'All required fields must be filled';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } elseif (!in_array($role, ['LITIGANT', 'LAWYER', 'OFFICIAL'])) {
        $error = 'Invalid role selected';
    } else {
        try {
            $pdo = getDbConnection();
            
            // Check if email already exists
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                $error = 'Email already registered';
            } else {
                // Handle file upload if provided (for LAWYER/OFFICIAL)
                $verificationFile = null;
                $verificationFilename = null;
                
                if (($role === 'LAWYER' || $role === 'OFFICIAL') && isset($_FILES['verification_file']) && $_FILES['verification_file']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['verification_file'];
                    $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
                    
                    if (in_array($file['type'], $allowedTypes) && $file['size'] <= MAX_FILE_SIZE) {
                        $randomName = random_filename($file['name']);
                        $uploadPath = __DIR__ . '/../' . UPLOADS_DIR . '/' . $randomName;
                        
                        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                            $verificationFile = '/' . UPLOADS_DIR . '/' . $randomName;
                            $verificationFilename = $file['name'];
                        }
                    }
                }
                
                // Insert user
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $districtValue = !empty($district) ? $district : null;
                
                $stmt = $pdo->prepare('INSERT INTO users (email, name, role, district, password_hash, verified) VALUES (:email, :name, :role, :district, :password_hash, 0)');
                $stmt->execute([
                    'email' => $email,
                    'name' => $name,
                    'role' => $role,
                    'district' => $districtValue,
                    'password_hash' => $passwordHash
                ]);
                
                $userId = $pdo->lastInsertId();
                
                // Insert verification request timeline if file uploaded
                if ($verificationFile) {
                    $stmt = $pdo->prepare('INSERT INTO timeline (case_id, actor_id, event, meta) VALUES (NULL, :actor_id, :event, :meta)');
                    $stmt->execute([
                        'actor_id' => $userId,
                        'event' => 'Verification Request',
                        'meta' => json_encode([
                            'file' => $verificationFile,
                            'filename' => $verificationFilename
                        ])
                    ]);
                }
                
                $success = 'Registration successful! You can now login.';
                // Optionally auto-login
                // loginUser($userId, $email, $role, $name);
                // header('Location: dashboard.php');
                // exit;
            }
        } catch (PDOException $e) {
            $error = 'Registration failed. Please try again.';
            error_log('Registration error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Justice Hammer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            padding: 2rem 0;
            color: #fff;
        }
        .register-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 2rem;
            width: 100%;
            max-width: 500px;
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
    </style>
</head>
<body>
    <div class="register-card">
        <h2 class="text-center mb-4"><i class="fas fa-gavel"></i> Justice Hammer</h2>
        <h4 class="text-center mb-4">Register</h4>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="name" class="form-label">Full Name *</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email *</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="role" class="form-label">Role *</label>
                <select class="form-select" id="role" name="role" required>
                    <option value="">Select Role</option>
                    <option value="LITIGANT">Litigant</option>
                    <option value="LAWYER">Lawyer</option>
                    <option value="OFFICIAL">Official</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="district" class="form-label">District</label>
                <input type="text" class="form-control" id="district" name="district" placeholder="e.g., Dhaka, Chittagong">
            </div>
            <div class="mb-3" id="verificationFileGroup" style="display: none;">
                <label for="verification_file" class="form-label">Verification Document (Optional)</label>
                <input type="file" class="form-control" id="verification_file" name="verification_file" accept=".pdf,.jpg,.jpeg,.png">
                <small class="text-muted">Upload verification document (PDF, JPG, PNG - Max 10MB)</small>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password *</label>
                <input type="password" class="form-control" id="password" name="password" required minlength="8">
                <small class="text-muted">Minimum 8 characters</small>
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm Password *</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 mb-3">Register</button>
        </form>
        
        <div class="text-center">
            <a href="login.php" class="text-light">Already have an account? Login</a>
        </div>
    </div>
    
    <script>
        document.getElementById('role').addEventListener('change', function() {
            const verificationGroup = document.getElementById('verificationFileGroup');
            if (this.value === 'LAWYER' || this.value === 'OFFICIAL') {
                verificationGroup.style.display = 'block';
            } else {
                verificationGroup.style.display = 'none';
            }
        });
    </script>
</body>
</html>
