<?php
// guard for viewer and student pages

require_once __DIR__ . '/../config/config.php';

// redirect to login if not logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    setFlashMessage('Please login to access this page', 'warning');
    redirect('auth/login.php');
}

// creators go to their own dashboard instead
if (!isViewer() && !isAdmin()) {
    if (isCreator()) {
        redirect('creator/dashboard.php');
    }
    // block everyone else
    setFlashMessage('Access denied.', 'error');
    redirect('auth/login.php');
}

// expose user vars to every viewer page
$current_user_id    = getCurrentUserId();
$current_user_name  = getCurrentUserName()  ?? 'Student';
$current_user_email = getCurrentUserEmail() ?? '';
$current_user_role  = getCurrentUserRole()  ?? 'viewer';
?>
