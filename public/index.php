<?php
/**
 * Public index — redirects logged-in users to their dashboard,
 * guests to the login page. Exists so old redirect('public/index.php')
 * calls don't 404.
 */
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$role = getCurrentUserRole();

if ($role === 'admin') {
    redirect('admin/dashboard.php');
} elseif ($role === 'creator') {
    redirect('creator/dashboard.php');
} else {
    redirect('viewer/dashboard.php');
}
?>
