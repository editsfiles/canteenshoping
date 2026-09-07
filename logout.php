<?php
session_start();

// Clear all session data
$_SESSION = array();

// Clear permanent remember-me cookie
if (isset($_COOKIE['canteen_student_auth'])) {
    setcookie('canteen_student_auth', '', time() - 3600, '/');
}

// Destroy session
session_destroy();

// Redirect to student login page
header("Location: login.php");
exit();
?>