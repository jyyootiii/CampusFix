<?php
/**
 * CampusFix — Student Registration Processor
 * Saves new student account to SQLite with BCrypt hashed password.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect to dashboard — prevent creating new user while logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: student/dashboard.php');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name             = trim($_POST['name'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $student_id       = trim($_POST['student_id'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($name) || empty($email) || empty($student_id) || empty($password) || empty($confirm_password)) {
        header('Location: register.html?error=required');
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: register.html?error=invalid_email');
        exit;
    }

    if ($password !== $confirm_password) {
        header('Location: register.html?error=mismatch');
        exit;
    }

    if (strlen($password) < 8) {
        header('Location: register.html?error=short');
        exit;
    }

    $db = getDB();

    // Check for duplicate email or student ID
    $check = $db->prepare("SELECT id FROM users WHERE email = ? OR student_id = ? LIMIT 1");
    $check->execute([$email, $student_id]);

    if ($check->fetch()) {
        header('Location: register.html?error=duplicate');
        exit;
    }

    // Hash password and store record
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $now = date('Y-m-d H:i:s');
    
    $stmt = $db->prepare("
        INSERT INTO users (name, email, student_id, password, role, created_at)
        VALUES (?, ?, ?, ?, 'Student', ?)
    ");
    $stmt->execute([$name, $email, $student_id, $hashedPassword, $now]);

    header('Location: login.html?registered=1');
    exit;
}

// Direct GET request -> redirect to register.html
header('Location: register.html');
exit;
