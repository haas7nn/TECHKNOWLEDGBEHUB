<?php $current_page = basename($_SERVER['PHP_SELF']); ?>
<nav class="admin-topbar">
    <div class="brand"><i class="fas fa-graduation-cap"></i><span><?= SITE_NAME ?> Admin</span></div>
    <div class="topbar-right">
        <span><i class="fas fa-user-shield"></i> <?= e($current_user_name) ?></span>
        <a href="<?= SITE_URL ?>/viewer/dashboard.php" class="topbar-link"><i class="fas fa-eye"></i> View Site</a>
        <a href="<?= SITE_URL ?>/auth/logout.php" class="topbar-link danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>

<aside class="admin-sidebar">
    <div class="sidebar-section-title">Main</div>
    <a href="<?= SITE_URL ?>/admin/dashboard.php"  class="admin-nav-item <?= $current_page==='dashboard.php'    ?'active':'' ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a>

    <div class="sidebar-section-title">Content</div>
    <a href="<?= SITE_URL ?>/admin/tutorials.php"  class="admin-nav-item <?= $current_page==='tutorials.php'    ?'active':'' ?>"><i class="fas fa-book"></i> Tutorials</a>
    <a href="<?= SITE_URL ?>/admin/comments.php"   class="admin-nav-item <?= $current_page==='comments.php'     ?'active':'' ?>"><i class="fas fa-comments"></i> Comments</a>

    <div class="sidebar-section-title">Users</div>
    <a href="<?= SITE_URL ?>/admin/users.php"      class="admin-nav-item <?= $current_page==='users.php'        ?'active':'' ?>"><i class="fas fa-users"></i> Manage Users</a>

    <div class="sidebar-section-title">Reports</div>
    <a href="<?= SITE_URL ?>/admin/report-popular.php"     class="admin-nav-item <?= $current_page==='report-popular.php'    ?'active':'' ?>"><i class="fas fa-fire"></i> Popular Tutorials</a>
    <a href="<?= SITE_URL ?>/admin/report-instructor.php"  class="admin-nav-item <?= $current_page==='report-instructor.php' ?'active':'' ?>"><i class="fas fa-chalkboard-teacher"></i> Instructor Report</a>
</aside>
