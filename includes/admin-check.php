<?php
// load the app config and all helper functions
require_once __DIR__ . '/../config/config.php';
// if the user is not logged in save where they were trying to go then redirect to login
if (!isLoggedIn()) {
    $uri = $_SERVER['REQUEST_URI'];
    // only store relative paths never external urls
    if (preg_match('#^/#', $uri) && !preg_match('#^//|https?://#i', $uri)) {
        $_SESSION['redirect_after_login'] = $uri;
    }
    setFlashMessage('Please login as administrator.', 'warning');
    redirect('auth/login.php');
}
// if the user is logged in but is not an admin block them from accessing this page
if (!isAdmin()) {
    setFlashMessage('Access denied. Admins only.', 'error');
    redirect('auth/login.php');
}
// store the current admin user details so every admin page can use them
$current_user_id   = getCurrentUserId();
$current_user_name = getCurrentUserName() ?? 'Admin';
?>
