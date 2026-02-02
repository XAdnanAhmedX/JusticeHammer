<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';


function isAdmin() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        return false;
    }
    return $_SESSION['role'] === 'ADMIN';
}


function isOfficial() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        return false;
    }
    return $_SESSION['role'] === 'OFFICIAL';
}


function isLawyer() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        return false;
    }
    return $_SESSION['role'] === 'LAWYER';
}


function isLitigant() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        return false;
    }
    return $_SESSION['role'] === 'LITIGANT';
}


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


function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        redirect_to('pages/login.php');
        exit;
    }
}


 // Get current user ID from session

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

 //Get current user role from session

function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

//Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

//set session variables
function loginUser($userId, $email, $role, $name) {
    $_SESSION['user_id'] = $userId;
    $_SESSION['email'] = $email;
    $_SESSION['role'] = $role;
    $_SESSION['name'] = $name;
}


 // Logout user 

function logoutUser() {
    session_destroy();
    session_start();
}
