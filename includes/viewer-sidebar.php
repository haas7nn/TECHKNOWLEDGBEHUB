<?php
/**
 * Viewer Sidebar , only links to pages that actually exist
 * Hasan Fardan - 202301686
 */
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="viewer-sidebar">
    <nav class="sidebar-nav">
        <a href="<?= SITE_URL ?>/viewer/dashboard.php"
           class="nav-item <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>

        <a href="<?= SITE_URL ?>/viewer/browse-tutorials.php"
           class="nav-item <?= $current_page === 'browse-tutorials.php' ? 'active' : '' ?>">
            <i class="fas fa-th-large"></i>
            <span>Browse Tutorials</span>
        </a>

        <a href="<?= SITE_URL ?>/viewer/my-learning.php"
           class="nav-item <?= $current_page === 'my-learning.php' ? 'active' : '' ?>">
            <i class="fas fa-graduation-cap"></i>
            <span>My Learning</span>
        </a>

        <div class="nav-divider"></div>

        <a href="<?= SITE_URL ?>/public/search.php"
           class="nav-item <?= $current_page === 'search.php' ? 'active' : '' ?>">
            <i class="fas fa-search"></i>
            <span>Search</span>
        </a>

        <div class="nav-divider"></div>

        <a href="<?= SITE_URL ?>/auth/logout.php" class="nav-item" style="color:#e53e3e;">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="progress-widget">
            <h4><i class="fas fa-trophy"></i> Keep Learning!</h4>
            <p>Browse tutorials to start tracking your progress.</p>
        </div>
    </div>
</aside>
