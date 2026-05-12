<?php
require_once '../config/config.php';

// Bug 34 fix: use consistent past timestamp for all cookie clearing
$past = time() - 86400;

// clear remember-me cookie
if (isset($_COOKIE['remember_user'])) {
    setcookie('remember_user', '', [
        'expires'  => $past,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}

// clear session cookie using the same params PHP configured it with
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    // Bug 26 fix: use samesite from actual session config, fall back to Strict
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

// wipe session data and destroy
$_SESSION = [];
session_destroy();

// Bug 21 fix: start a fresh session AFTER destroy to carry the flash message
session_start();
$_SESSION['flash_message'] = 'You have been logged out successfully.';
$_SESSION['flash_type']    = 'success';

header('Location: ' . SITE_URL . '/auth/login.php');
exit();
?>
