<?php
/**
 * CampusFix — Logout Handler
 * Terminates user session and forwards to login.html with status.
 */

session_start();
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Clear static HTML navbar synchronization cookies
setcookie('cf_user_id', '', time() - 3600, '/');
setcookie('cf_user_name', '', time() - 3600, '/');
setcookie('cf_user_role', '', time() - 3600, '/');

header('Location: login.html?loggedout=1');
exit;
