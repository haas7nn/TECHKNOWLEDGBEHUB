<?php
// load config and helpers
require_once __DIR__ . '/../config/config.php';
// redirect to login if not logged in
if (!isLoggedIn()) {
    $uri = $_SERVER['REQUEST_URI'];
    // only save relative paths
    if (preg_match('#^/#', $uri) && !preg_match('#^//|https?://#i', $uri)) {
        $_SESSION['redirect_after_login'] = $uri;
    }
    setFlashMessage('Please login as administrator.', 'warning');
    redirect('auth/login.php');
}
// block non-admins
if (!isAdmin()) {
    setFlashMessage('Access denied. Admins only.', 'error');
    redirect('auth/login.php');
}
// expose user vars to every admin page
$current_user_id   = getCurrentUserId();
$current_user_name = getCurrentUserName() ?? 'Admin';
?>
