<?php
/**
 * Creator Sidebar Navigation
 * side menu for creator pages
 * Hasan Fardan - 202301686
 */

// checking the page name to show which link is active
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="creator-sidebar">
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="my-tutorials.php" class="nav-item <?= $current_page === 'my-tutorials.php' ? 'active' : '' ?>">
            <i class="fas fa-book"></i>
            <span>My Tutorials</span>
        </a>
        
        <a href="create-tutorial.php" class="nav-item <?= $current_page === 'create-tutorial.php' ? 'active' : '' ?>">
            <i class="fas fa-plus-circle"></i>
            <span>Create Tutorial</span>
        </a>
        
        <div class="nav-divider"></div>
        
        <a href="analytics.php" class="nav-item <?= $current_page === 'analytics.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i>
            <span>Analytics</span>
        </a>
        
        <a href="comments.php" class="nav-item <?= $current_page === 'comments.php' ? 'active' : '' ?>">
            <i class="fas fa-comments"></i>
            <span>Comments</span>
            <span class="badge">5</span>
        </a>
        
        <a href="ratings.php" class="nav-item <?= $current_page === 'ratings.php' ? 'active' : '' ?>">
            <i class="fas fa-star"></i>
            <span>Ratings</span>
        </a>
        
        <div class="nav-divider"></div>
        
        <a href="profile.php" class="nav-item <?= $current_page === 'profile.php' ? 'active' : '' ?>">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
        
        <a href="settings.php" class="nav-item <?= $current_page === 'settings.php' ? 'active' : '' ?>">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <div class="help-box">
            <i class="fas fa-question-circle"></i>
            <h4>Need Help?</h4>
            <p>Check our documentation</p>
            <a href="#" class="btn btn-small">View Docs</a>
        </div>
    </div>
</aside>