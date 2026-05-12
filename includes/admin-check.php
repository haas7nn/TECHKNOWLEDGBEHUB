<?php
require_once __DIR__ . '/../config/config.php';
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    setFlashMessage('Please login as administrator.', 'warning');
    redirect('auth/login.php');
}
if (!isAdmin()) {
    setFlashMessage('Access denied. Admins only.', 'error');
    redirect('auth/login.php');
}
$current_user_id   = getCurrentUserId();
$current_user_name = getCurrentUserName() ?? 'Admin';
?>
