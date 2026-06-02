<?php
require_once '../config/config.php';

// past timestamp expires cookies immediately
$past = time() - 86400;

// clear legacy remember cookie
if (isset($_COOKIE['remember_user'])) {
    setcookie('remember_user', '', [
        'expires'  => $past,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}

// clear remember_token cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', [
        'expires'  => $past,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}

// expire session cookie using original PHP settings
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    // fall back to Strict if samesite not set
    $samesite = !empty($params['samesite']) ? $params['samesite'] : 'Strict';
    setcookie(session_name(), '', [
        'expires'  => $past,
        'path'     => $params['path']   ?: '/',
        'domain'   => $params['domain'] ?: '',
        'secure'   => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $samesite
    ]);
}

// clear then destroy session
$_SESSION = [];
session_destroy();

// new session needed to carry flash message to login
session_start();
$_SESSION['flash_message'] = 'You have been logged out successfully.';
$_SESSION['flash_type']    = 'success';

// redirect to login
header('Location: ' . SITE_URL . '/auth/login.php');
exit();
?>
