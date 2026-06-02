<?php
// track current page for active link
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- admin top bar -->
<nav class="admin-topbar">
    <!-- mobile hamburger toggle -->
    <button class="admin-sidebar-toggle" id="adminSidebarToggle" type="button" aria-label="Toggle sidebar"
            style="display:none;align-items:center;justify-content:center;width:38px;height:38px;background:rgba(255,255,255,.1);border:none;border-radius:6px;color:#fff;font-size:18px;cursor:pointer;flex-shrink:0;margin-right:8px;">
        <i class="fas fa-bars"></i>
    </button>
    <div class="brand"><i class="fas fa-graduation-cap"></i><span><?= SITE_NAME ?> Admin</span></div>
    <div class="topbar-right">
        <!-- logged in admin name -->
        <span><i class="fas fa-user-shield"></i> <?= e($current_user_name) ?></span>
        <a href="<?= SITE_URL ?>/public/search.php" class="topbar-link"><i class="fas fa-eye"></i> View Site</a>
        <a href="<?= SITE_URL ?>/auth/logout.php" class="topbar-link danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>

<script>
(function () {
    var btn = document.getElementById('adminSidebarToggle');
    if (!btn) return;
    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var sb = document.querySelector('.admin-sidebar');
        if (sb) sb.classList.toggle('open');
    });
    document.addEventListener('click', function (e) {
        var sb = document.querySelector('.admin-sidebar');
        if (sb && sb.classList.contains('open') && !sb.contains(e.target)) {
            sb.classList.remove('open');
        }
    });
}());
</script>

<!-- admin sidebar -->
<aside class="admin-sidebar">
    <div class="sidebar-section-title">Main</div>
    <a href="<?= SITE_URL ?>/admin/dashboard.php"  class="admin-nav-item <?= $current_page==='dashboard.php'    ?'active':'' ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a>

    <!-- content nav links -->
    <div class="sidebar-section-title">Content</div>
    <a href="<?= SITE_URL ?>/admin/tutorials.php"  class="admin-nav-item <?= $current_page==='tutorials.php'    ?'active':'' ?>"><i class="fas fa-book"></i> Tutorials</a>
    <a href="<?= SITE_URL ?>/admin/categories.php" class="admin-nav-item <?= $current_page==='categories.php' ? 'active' : '' ?>"><i class="fas fa-tags"></i> Categories</a>
    <a href="<?= SITE_URL ?>/admin/comments.php"   class="admin-nav-item <?= $current_page==='comments.php'     ?'active':'' ?>"><i class="fas fa-comments"></i> Comments</a>

    <!-- user nav links -->
    <div class="sidebar-section-title">Users</div>
    <a href="<?= SITE_URL ?>/admin/users.php"      class="admin-nav-item <?= $current_page==='users.php'        ?'active':'' ?>"><i class="fas fa-users"></i> Manage Users</a>

    <!-- reports nav links -->
    <div class="sidebar-section-title">Reports</div>
    <a href="<?= SITE_URL ?>/admin/report-popular.php"     class="admin-nav-item <?= $current_page==='report-popular.php'    ?'active':'' ?>"><i class="fas fa-fire"></i> Popular Tutorials</a>
    <a href="<?= SITE_URL ?>/admin/report-instructor.php"  class="admin-nav-item <?= $current_page==='report-instructor.php' ?'active':'' ?>"><i class="fas fa-chalkboard-teacher"></i> Instructor Report</a>
</aside>
