<?php
/**
 * CampusFix — Reusable Header Component
 * Provides clean college-styled navigation for student and admin pages.
 */

if (!function_exists('isLoggedIn')) {
    require_once __DIR__ . '/auth.php';
}

$base = getBasePath();
$user = currentUser();
$currentPage = basename($_SERVER['PHP_SELF']);
$flash = getFlash();

// Ensure cookies are synchronized for static HTML pages
if (isLoggedIn() && $user) {
    if (!isset($_COOKIE['cf_user_role']) || $_COOKIE['cf_user_role'] !== $user['role']) {
        setcookie('cf_user_id', (string)$user['id'], time() + 86400, '/');
        setcookie('cf_user_name', (string)$user['name'], time() + 86400, '/');
        setcookie('cf_user_role', (string)$user['role'], time() + 86400, '/');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — CampusFix' : 'CampusFix'; ?></title>
    <link rel="stylesheet" href="<?php echo $base; ?>assets/css/style.css">
</head>
<body>

    <!-- Header Banner -->
    <header class="site-header">
        <h1>CampusFix</h1>
        <p>Smart Campus Complaint &amp; Maintenance Management System</p>
    </header>

    <!-- Navigation Bar -->
    <nav class="site-nav">
        <div class="nav-wrap">
            <ul class="nav-links">
                <li><a href="<?php echo $base; ?>home.html">Home</a></li>
                
                <?php if (isLoggedIn() && isStudent()): ?>
                    <li><a href="<?php echo $base; ?>student/dashboard.php" class="<?php echo ($currentPage == 'dashboard.php') ? 'active' : ''; ?>">Dashboard</a></li>
                    <li><a href="<?php echo $base; ?>student/submit_complaint.php" class="<?php echo ($currentPage == 'submit_complaint.php') ? 'active' : ''; ?>">Submit Complaint</a></li>
                    <li><a href="<?php echo $base; ?>student/complaint_history.php" class="<?php echo ($currentPage == 'complaint_history.php') ? 'active' : ''; ?>">My Complaints</a></li>
                    <li><a href="<?php echo $base; ?>student/track_complaint.php" class="<?php echo ($currentPage == 'track_complaint.php') ? 'active' : ''; ?>">Track Complaint</a></li>
                <?php endif; ?>

                <?php if (isLoggedIn() && isAdmin()): ?>
                    <li><a href="<?php echo $base; ?>admin/dashboard.php" class="<?php echo ($currentPage == 'dashboard.php' && strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? 'active' : ''; ?>">Admin Dashboard</a></li>
                    <li><a href="<?php echo $base; ?>admin/manage_complaints.php" class="<?php echo ($currentPage == 'manage_complaints.php' || $currentPage == 'complaint_details.php') ? 'active' : ''; ?>">Manage Complaints</a></li>
                    <li><a href="<?php echo $base; ?>admin/reports.php" class="<?php echo ($currentPage == 'reports.php') ? 'active' : ''; ?>">Reports &amp; Analytics</a></li>
                    <li><a href="<?php echo $base; ?>view_data.php">Database Viewer</a></li>
                <?php endif; ?>

                <li><a href="<?php echo $base; ?>about.html">About Us</a></li>
                <li><a href="<?php echo $base; ?>knowledge.html">Knowledge Centre</a></li>
                <li><a href="<?php echo $base; ?>contact.html">Contact Us</a></li>
            </ul>

            <!-- Auth Status / Logout -->
            <div class="user-nav-status">
                <?php if (isLoggedIn()): ?>
                    <span>
                        Hello, <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                        <span class="role-tag <?php echo strtolower($user['role']); ?>"><?php echo htmlspecialchars($user['role']); ?></span>
                    </span>
                    <a href="<?php echo $base; ?>logout.php" class="btn btn-secondary btn-sm">Logout</a>
                <?php else: ?>
                    <a href="<?php echo $base; ?>login.html" class="btn btn-secondary btn-sm">Login</a>
                    <a href="<?php echo $base; ?>register.html" class="btn btn-primary btn-sm">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="container">
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo ($flash['type'] === 'error') ? 'error' : (($flash['type'] === 'info') ? 'info' : 'success'); ?>">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>
