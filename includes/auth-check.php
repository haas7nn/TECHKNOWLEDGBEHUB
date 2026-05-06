<?php
/**
 * Authentication Check for Creator Pages
 * put this at the top of all creator files to keep people out
 * Hasan Fardan - 202301686
 */

require_once __DIR__ . '/../config/config.php';

// making sure they are actually logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    setFlashMessage('Please login to access this page', 'warning');
    redirect('auth/login.php');
}

// only letting creators or admins through
if (!isCreator() && !isAdmin()) {
    setFlashMessage('Access denied. This page is for instructors only.', 'error');
    redirect('public/index.php');
}

// if they made it here they are good to go
$current_user_id = getCurrentUserId();
$current_user_name = getCurrentUserName();
$current_user_email = getCurrentUserEmail();
$current_user_role = getCurrentUserRole();

// making sure these variables exist so nothing breaks
if (!isset($current_user_id)) {
    $current_user_id = 0;
}
if (!isset($current_user_name)) {
    $current_user_name = 'User';
}
if (!isset($current_user_email)) {
    $current_user_email = '';
}
if (!isset($current_user_role)) {
    $current_user_role = 'viewer';
}
?>