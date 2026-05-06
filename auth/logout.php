<?php
/**
 * logout logic
 * kills the session and sends them home
 * Hasan Fardan - 202301686
 */

session_start();

// wipe everything out of the session array
$_SESSION = array();

// kill the session completely
session_destroy();

// start it back up just to show the logout alert
session_start();
$_SESSION['flash_message'] = 'You have been logged out successfully.';
$_SESSION['flash_type'] = 'success';

// back to the home page we go
header("Location: ../public/index.php");
exit();
?>