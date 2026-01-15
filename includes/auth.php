<?php
/**
 * Authentication & Authorization Helpers
 * Session management and role-based access control
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Check if current user is admin
 * @return bool
 */
function isAdmin() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        return false;
    }
    return $_SESSION['role'] === 'ADMIN';
}

/**
 * Check if current user is official
 * @return bool
 */
function isOfficial() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        return false;
    }
    return $_SESSION['role'] === 'OFFICIAL';
}

/**
 * Check if current user is lawyer
 * @return bool
 */
function isLawyer() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        return false;
    }
    return $_SESSION['role'] === 'LAWYER';
}

/**
 * Check if current user is litigant
 * @return bool
 */
function isLitigant() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        return false;
    }
    return $_SESSION['role'] === 'LITIGANT';
}

/**
 * Require user to be verified
 * @param int $userId User ID to check
 * @throws Exception if user is not verified
 */
function requireVerified($userId) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT verified FROM users WHERE id = :userId');
        $stmt->execute(['userId' => $userId]);
        $user = $stmt->fetch();
        
        if (!$user || !$user['verified']) {
            http_response_code(403);
            json_response(['ok' => false, 'error' => 'Account not verified']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        json_response(['ok' => false, 'error' => 'Database error']);
    }
}

/**
 * Require login - redirect to login page if not logged in
 */
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        redirect_to('pages/login.php');
        exit;
    }
}

/**
 * Get current user ID from session
 * @return int|null
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role from session
 * @return string|null
 */
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Login user (set session variables)
 * @param int $userId User ID
 * @param string $email User email
 * @param string $role User role
 * @param string $name User name
 */
function loginUser($userId, $email, $role, $name) {
    $_SESSION['user_id'] = $userId;
    $_SESSION['email'] = $email;
    $_SESSION['role'] = $role;
    $_SESSION['name'] = $name;
}

/**
 * Logout user (destroy session)
 */
function logoutUser() {
    session_destroy();
    session_start();
}
