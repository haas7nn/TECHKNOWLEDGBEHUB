<?php
// this file exists so old links to public/indexphp do not result in a 404 error
// it redirects guests to the search page and logged in users to their own dashboard
require_once '../config/config.php';

// guests go to search
if (!isLoggedIn()) {
    redirect('public/search.php');
}

// read the logged in user role to decide where to send them
$role = getCurrentUserRole();

// redirect to the correct dashboard based on the user role
if ($role === 'admin') {
    redirect('admin/dashboard.php');
} elseif ($role === 'creator') {
    redirect('creator/dashboard.php');
} else {
    redirect('viewer/dashboard.php');
}
?>
