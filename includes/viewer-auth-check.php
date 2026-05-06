<?php
/**
 * Authentication Check for Viewer Pages
 * keeps non-students out of student pages
 * Hasan Fardan - 202301686
 */

require_once __DIR__ . '/../config/config.php';

// check if logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    setFlashMessage('Please login to access this page', 'warning');
    redirect('auth/login.php');
}

// viewers can access but also let admins in for testing
if (!isViewer() && !isAdmin()) {
    setFlashMessage('Access denied. This page is for students only.', 'error');
    redirect('public/index.php');
}

// current user data
$current_user_id = getCurrentUserId();
$current_user_name = getCurrentUserName();
$current_user_email = getCurrentUserEmail();
$current_user_role = getCurrentUserRole();

// defaults
if (!isset($current_user_id)) {
    $current_user_id = 0;
}
if (!isset($current_user_name)) {
    $current_user_name = 'Student';
}
if (!isset($current_user_email)) {
    $current_user_email = '';
}
if (!isset($current_user_role)) {
    $current_user_role = 'viewer';
}
?>