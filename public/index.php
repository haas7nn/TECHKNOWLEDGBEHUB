<?php
// redirect shim — guests to search logged-in users to their dashboard
require_once '../config/config.php';

// guest redirect
if (!isLoggedIn()) {
    redirect('public/search.php');
}

// role-based redirect
$role = getCurrentUserRole();

// send to correct dashboard by role
if ($role === 'admin') {
    redirect('admin/dashboard.php');
} elseif ($role === 'creator') {
    redirect('creator/dashboard.php');
} else {
    redirect('viewer/dashboard.php');
}
?>
