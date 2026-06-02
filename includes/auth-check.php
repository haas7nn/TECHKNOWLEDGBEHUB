<?php
// guard for creator and instructor pages

require_once __DIR__ . '/../config/config.php';

// redirect to login if not logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    setFlashMessage('Please login to access this page', 'warning');
    redirect('auth/login.php');
}

// viewers go to their own dashboard instead
if (!isCreator() && !isAdmin()) {
    if (isViewer()) {
        redirect('viewer/dashboard.php');
    }
    // block everyone else
    setFlashMessage('Access denied. This area is for instructors only.', 'error');
    redirect('auth/login.php');
}

// expose user vars to every creator page
$current_user_id    = getCurrentUserId();
$current_user_name  = getCurrentUserName()  ?? 'Instructor';
$current_user_email = getCurrentUserEmail() ?? '';
$current_user_role  = getCurrentUserRole()  ?? 'creator';
?>
