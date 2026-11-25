<?php
/**
 * CampusFix — Login Processor
 * Handles authentication for Students and Administrators using SQLite PDO.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect directly to dashboard
if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: student/dashboard.php');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        header('Location: login.html?error=required');
        exit;
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT id, name, email, student_id, password, role FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Successful login
        session_regenerate_id(true);

        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['student_id'] = $user['student_id'];
        $_SESSION['user_role']  = $user['role'];

        // Synchronize browser cookies for static HTML page navbars
        setcookie('cf_user_id', (string)$user['id'], time() + 86400, '/');
        setcookie('cf_user_name', (string)$user['name'], time() + 86400, '/');
        setcookie('cf_user_role', (string)$user['role'], time() + 86400, '/');

        if ($user['role'] === 'Admin') {
            header('Location: admin/dashboard.php');
        } else {
            header('Location: student/dashboard.php');
        }
        exit;
    } else {
        // Invalid credentials
        header('Location: login.html?error=invalid');
        exit;
    }
}

// Direct GET request without POST -> redirect to login.html
header('Location: login.html');
exit;
