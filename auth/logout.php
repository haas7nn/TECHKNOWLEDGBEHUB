<?php
/**
 * Logout - kills the session and sends user to login page
 * Hasan Fardan - 202301686
 */

require_once '../config/config.php';

// clear the remember me cookie if it exists
if (isset($_COOKIE['remember_user'])) {
    setcookie('remember_user', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}

// wipe the session data
$_SESSION = [];

// destroy the session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

// start a fresh session just to carry the flash message
session_start();
$_SESSION['flash_message'] = 'You have been logged out successfully.';
$_SESSION['flash_type']    = 'success';

// redirect to login, not index (index doesn't exist)
header('Location: ' . SITE_URL . '/auth/login.php');
exit();
?>
