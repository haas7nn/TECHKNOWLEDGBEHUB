<?php
require_once '../config/config.php';

// use a past timestamp to expire cookies when we clear them
$past = time() - 86400;

// clear the legacy remember_user cookie if it exists
if (isset($_COOKIE['remember_user'])) {
    setcookie('remember_user', '', [
        'expires'  => $past,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}

// clear the current remember_token cookie set by loginphp
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', [
        'expires'  => $past,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}

// clear the session cookie using the same settings php originally used for it
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    // use the samesite from the actual session config or fall back to strict
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

// empty the session array then destroy the session completely
$_SESSION = [];
session_destroy();

// start a fresh session so we can carry the flash message across to login
session_start();
$_SESSION['flash_message'] = 'You have been logged out successfully.';
$_SESSION['flash_type']    = 'success';

// send them to the login page
header('Location: ' . SITE_URL . '/auth/login.php');
exit();
?>
