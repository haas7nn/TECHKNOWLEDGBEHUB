<?php
/**
 * Authentication Check for Viewer/Student Pages
 * Hasan Fardan - 202301686
 */

require_once __DIR__ . '/../config/config.php';

// not logged in — save where they were trying to go, then send to login
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    setFlashMessage('Please login to access this page', 'warning');
    redirect('auth/login.php');
}

// only viewers (students) and admins can access viewer pages
// creators get sent to their own dashboard
if (!isViewer() && !isAdmin()) {
    if (isCreator()) {
        redirect('creator/dashboard.php');
    }
    setFlashMessage('Access denied.', 'error');
    redirect('auth/login.php');
}

// set current user variables for use in every viewer page
$current_user_id    = getCurrentUserId();
$current_user_name  = getCurrentUserName()  ?? 'Student';
$current_user_email = getCurrentUserEmail() ?? '';
$current_user_role  = getCurrentUserRole()  ?? 'viewer';
?>
