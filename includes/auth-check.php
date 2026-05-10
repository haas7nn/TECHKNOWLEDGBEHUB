<?php
/**
 * Authentication Check for Creator/Instructor Pages
 * Hasan Fardan - 202301686
 */

require_once __DIR__ . '/../config/config.php';

// not logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    setFlashMessage('Please login to access this page', 'warning');
    redirect('auth/login.php');
}

// only creators and admins can access creator pages
// viewers get sent to their own dashboard
if (!isCreator() && !isAdmin()) {
    if (isViewer()) {
        redirect('viewer/dashboard.php');
    }
    setFlashMessage('Access denied. This area is for instructors only.', 'error');
    redirect('auth/login.php');
}

// set current user variables
$current_user_id    = getCurrentUserId();
$current_user_name  = getCurrentUserName()  ?? 'Instructor';
$current_user_email = getCurrentUserEmail() ?? '';
$current_user_role  = getCurrentUserRole()  ?? 'creator';
?>
