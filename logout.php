<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// 1. Clear session variables from server memory
$_SESSION = array();
session_unset();
session_destroy();

// 2. FORCE BROWSER TO EXPIRE THE TRACKING COOKIE (Fixes the login lock)
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// 3. Clear browser cache headers for this redirect
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// 4. Redirect to login
header("Location: login.html");
exit;
?>
