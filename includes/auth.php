<?php
/**
 * CampusFix - Authentication & Session Helper Functions
 * 
 * Provides simple, secure helpers for user session management,
 * role authorization, flash alerts, and CSRF protection.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if a user is currently logged in.
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Retrieves the current logged-in user array from session.
 */
function currentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id'         => $_SESSION['user_id'] ?? null,
        'name'       => $_SESSION['user_name'] ?? 'User',
        'email'      => $_SESSION['user_email'] ?? '',
        'student_id' => $_SESSION['student_id'] ?? '',
        'role'       => $_SESSION['user_role'] ?? 'Student'
    ];
}

/**
 * Returns true if the logged-in user is an Administrator.
 */
function isAdmin() {
    return isLoggedIn() && (($_SESSION['user_role'] ?? '') === 'Admin');
}

/**
 * Returns true if the logged-in user is a Student.
 */
function isStudent() {
    return isLoggedIn() && (($_SESSION['user_role'] ?? '') === 'Student');
}

/**
 * Restricts page access to logged-in users only.
 * Redirects guests to login.php.
 */
function requireLogin() {
    if (!isLoggedIn()) {
        // Store intended destination if helpful
        setFlash('error', 'Please log in to access this page.');
        header('Location: ' . getBasePath() . 'login.php');
        exit;
    }
}

/**
 * Restricts page access to users with a specific role ('Student' or 'Admin').
 */
function requireRole($requiredRole) {
    requireLogin();
    if (($_SESSION['user_role'] ?? '') !== $requiredRole) {
        setFlash('error', 'Access denied. You do not have permission to view that page.');
        if (isAdmin()) {
            header('Location: ' . getBasePath() . 'admin/dashboard.php');
        } else {
            header('Location: ' . getBasePath() . 'student/dashboard.php');
        }
        exit;
    }
}

/**
 * Computes base relative path for links depending on subfolder depth.
 */
function getBasePath() {
    // If called from /student/ or /admin/ or /pages/, return '../', otherwise './'
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($script, '/student/') !== false || strpos($script, '/admin/') !== false || strpos($script, '/pages/') !== false) {
        return '../';
    }
    return './';
}

/**
 * Sets a flash message to be displayed on the next page view.
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type'    => $type, // 'success', 'error', 'info', 'warning'
        'message' => $message
    ];
}

/**
 * Gets and clears the current flash message.
 */
function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Generates or retrieves the active CSRF token.
 */
function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates the CSRF token sent with a POST request.
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

