<?php
/**
 * Creator sidebar navigation component.
 *
 * Dependency: config.php must be included before this file so that
 * getCurrentUserId() and the SITE_URL constant are available.
 * The including page is also expected to have included auth-check.php
 * (which calls getCurrentUserId() itself), but this file derives the
 * current user directly from getCurrentUserId() so the badge count
 * is never silently skipped due to a missing $current_user_id variable.
 */

// creator sidebar
// get current page
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="creator-sidebar">
    <nav class="sidebar-nav">

        <!-- link to the creator dashboard marked active when on that page -->
        <a href="<?= SITE_URL ?>/creator/dashboard.php" class="nav-item <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>

        <!-- link to the list of all this creator's tutorials -->
        <a href="<?= SITE_URL ?>/creator/my-tutorials.php" class="nav-item <?= $current_page === 'my-tutorials.php' ? 'active' : '' ?>">
            <i class="fas fa-book"></i>
            <span>My Tutorials</span>
        </a>

        <!-- link to the create tutorial form -->
        <a href="<?= SITE_URL ?>/creator/create-tutorial.php" class="nav-item <?= $current_page === 'create-tutorial.php' ? 'active' : '' ?>">
            <i class="fas fa-plus-circle"></i>
            <span>Create Tutorial</span>
        </a>

        <div class="nav-divider"></div>

        <!-- link to the analytics page -->
        <a href="<?= SITE_URL ?>/creator/analytics.php" class="nav-item <?= $current_page === 'analytics.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i>
            <span>Analytics</span>
        </a>

        <!-- comments link with a badge showing how many approved comments exist -->
        <a href="<?= SITE_URL ?>/creator/comments.php" class="nav-item <?= $current_page === 'comments.php' ? 'active' : '' ?>">
            <i class="fas fa-comments"></i>
            <span>Comments</span>
            <?php
            // resolve the current user via getcurrentuserid() (always available from
            // configphp) rather than relying on $current_user_id being set by the
            // including page  a return value of 0 / null means no one is logged in
            $__sidebar_uid = (int) getCurrentUserId();
            if ($__sidebar_uid > 0) {
                try {
                    // fresh db connect
                    $__db   = new Database();
                    $__conn = $__db->connect();
                    if ($__conn) {
                        // use a subquery so no raw string interpolation is needed
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
                        // only show the badge when there is at least one comment
                        if ($__cnt > 0) {
                            echo '<span class="badge">' . $__cnt . '</span>';
                        }
                    }
                } catch (Exception $__e) { /* ignore errors silently so the nav still renders */ }
            }
            ?>
        </a>

        <!-- link to the ratings page -->
        <a href="<?= SITE_URL ?>/creator/ratings.php" class="nav-item <?= $current_page === 'ratings.php' ? 'active' : '' ?>">
            <i class="fas fa-star"></i>
            <span>Ratings</span>
        </a>

        <div class="nav-divider"></div>

        <!-- link to the creator profile page -->
        <a href="<?= SITE_URL ?>/creator/profile.php" class="nav-item <?= $current_page === 'profile.php' ? 'active' : '' ?>">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>

        <!-- link to the creator settings page -->
        <a href="<?= SITE_URL ?>/creator/settings.php" class="nav-item <?= $current_page === 'settings.php' ? 'active' : '' ?>">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
        </a>

        <div class="nav-divider"></div>

        <!-- logout link styled in red to stand out -->
        <a href="<?= SITE_URL ?>/auth/logout.php" class="nav-item" style="color:#e53e3e;">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </nav>

    <!-- footer help box with a quick create tutorial shortcut -->
    <div class="sidebar-footer">
        <div class="help-box">
            <i class="fas fa-question-circle"></i>
            <h4>Need Help?</h4>
            <p>View your tutorials or create a new one</p>
            <a href="<?= SITE_URL ?>/creator/create-tutorial.php" class="btn btn-small">Create Tutorial</a>
        </div>
    </div>
</aside>
