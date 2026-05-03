<?php
// auth check for viewer and student pages
// include this at the top of any page that only students or admins should see

require_once __DIR__ . '/../config/config.php';

// if they are not logged in save where they were going and send them to login
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    setFlashMessage('Please login to access this page', 'warning');
    redirect('auth/login.php');
}

// only viewers and admins are allowed here
// if they are a creator send them to the creator dashboard instead
if (!isViewer() && !isAdmin()) {
    if (isCreator()) {
        redirect('creator/dashboard.php');
    }
    // anyone else gets an access denied message and goes back to login
    setFlashMessage('Access denied.', 'error');
    redirect('auth/login.php');
}

// set up the current user variables so every viewer page can use them easily
$current_user_id    = getCurrentUserId();
$current_user_name  = getCurrentUserName()  ?? 'Student';
$current_user_email = getCurrentUserEmail() ?? '';
$current_user_role  = getCurrentUserRole()  ?? 'viewer';
?>
