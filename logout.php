<?php
session_start();

// Clear all session data
$_SESSION = array();

// Destroy session
session_destroy();

// Redirect to student login page
header("Location: login.php");
exit();
?>