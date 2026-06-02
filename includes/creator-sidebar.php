<?php
// creator sidebar
// track current page for active link
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="creator-sidebar">
    <nav class="sidebar-nav">

        <!-- dashboard link -->
        <a href="<?= SITE_URL ?>/creator/dashboard.php" class="nav-item <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>

        <!-- tutorials list link -->
        <a href="<?= SITE_URL ?>/creator/my-tutorials.php" class="nav-item <?= $current_page === 'my-tutorials.php' ? 'active' : '' ?>">
            <i class="fas fa-book"></i>
            <span>My Tutorials</span>
        </a>

        <!-- create tutorial link -->
        <a href="<?= SITE_URL ?>/creator/create-tutorial.php" class="nav-item <?= $current_page === 'create-tutorial.php' ? 'active' : '' ?>">
            <i class="fas fa-plus-circle"></i>
            <span>Create Tutorial</span>
        </a>

        <div class="nav-divider"></div>

        <!-- analytics link -->
        <a href="<?= SITE_URL ?>/creator/analytics.php" class="nav-item <?= $current_page === 'analytics.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i>
            <span>Analytics</span>
        </a>

        <!-- comments link with approved count badge -->
        <a href="<?= SITE_URL ?>/creator/comments.php" class="nav-item <?= $current_page === 'comments.php' ? 'active' : '' ?>">
            <i class="fas fa-comments"></i>
            <span>Comments</span>
            <?php
            // use getCurrentUserId directly so badge never silently breaks
            $__sidebar_uid = (int) getCurrentUserId();
            if ($__sidebar_uid > 0) {
                try {
                    // fresh db connect
                    $__db   = new Database();
                    $__conn = $__db->connect();
                    if ($__conn) {
                        $__commentsStmt = $__conn->prepare("
                            SELECT COUNT(*) as cnt
                            FROM dbProj_comments
                            WHERE tutorial_id IN (
                                SELECT tutorial_id FROM dbProj_tutorials
                                WHERE instructor_id = :uid AND status = 'published'
                            ) AND status = 'approved'
                        ");
                        $__commentsStmt->execute([':uid' => $__sidebar_uid]);
                        $__cnt = (int)($__commentsStmt->fetchColumn() ?: 0);
                        // show badge only when count is positive
                        if ($__cnt > 0) {
                            echo '<span class="badge">' . $__cnt . '</span>';
                        }
                    }
                } catch (Exception $__e) { /* nav renders even on db error */ }
            }
            ?>
        </a>

        <!-- ratings link -->
        <a href="<?= SITE_URL ?>/creator/ratings.php" class="nav-item <?= $current_page === 'ratings.php' ? 'active' : '' ?>">
            <i class="fas fa-star"></i>
            <span>Ratings</span>
        </a>

        <div class="nav-divider"></div>

        <!-- profile link -->
        <a href="<?= SITE_URL ?>/creator/profile.php" class="nav-item <?= $current_page === 'profile.php' ? 'active' : '' ?>">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>

        <!-- settings link -->
        <a href="<?= SITE_URL ?>/creator/settings.php" class="nav-item <?= $current_page === 'settings.php' ? 'active' : '' ?>">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
        </a>

        <div class="nav-divider"></div>

        <!-- logout link -->
        <a href="<?= SITE_URL ?>/auth/logout.php" class="nav-item" style="color:#e53e3e;">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </nav>

    <!-- sidebar footer help box -->
    <div class="sidebar-footer">
        <div class="help-box">
            <i class="fas fa-question-circle"></i>
            <h4>Need Help?</h4>
            <p>View your tutorials or create a new one</p>
            <a href="<?= SITE_URL ?>/creator/create-tutorial.php" class="btn btn-small">Create Tutorial</a>
        </div>
    </div>
</aside>
