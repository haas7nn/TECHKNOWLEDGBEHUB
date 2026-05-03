<?php
// auth check for creator and instructor pages
// include this at the top of any page that only creators or admins should see

require_once __DIR__ . '/../config/config.php';

// if they are not logged in save where they were going and send them to login
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    setFlashMessage('Please login to access this page', 'warning');
    redirect('auth/login.php');
}

// only creators and admins are allowed here
// if they are a viewer send them to the viewer dashboard instead
if (!isCreator() && !isAdmin()) {
    if (isViewer()) {
        redirect('viewer/dashboard.php');
    }
    // anyone else gets an access denied message and goes back to login
    setFlashMessage('Access denied. This area is for instructors only.', 'error');
    redirect('auth/login.php');
}

// set up the current user variables so every creator page can use them easily
$current_user_id    = getCurrentUserId();
$current_user_name  = getCurrentUserName()  ?? 'Instructor';
$current_user_email = getCurrentUserEmail() ?? '';
$current_user_role  = getCurrentUserRole()  ?? 'creator';
?>
