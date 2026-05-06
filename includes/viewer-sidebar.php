<?php
/**
 * Viewer Sidebar
 * side menu for student pages
 * Hasan Fardan - 202301686
 */

$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="viewer-sidebar">
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="browse-tutorials.php" class="nav-item <?= $current_page === 'browse-tutorials.php' ? 'active' : '' ?>">
            <i class="fas fa-search"></i>
            <span>Browse Tutorials</span>
        </a>
        
        <a href="my-learning.php" class="nav-item <?= $current_page === 'my-learning.php' ? 'active' : '' ?>">
            <i class="fas fa-graduation-cap"></i>
            <span>My Learning</span>
        </a>
        
        <div class="nav-divider"></div>
        
        <a href="my-progress.php" class="nav-item <?= $current_page === 'my-progress.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i>
            <span>My Progress</span>
        </a>
        
        <a href="my-ratings.php" class="nav-item <?= $current_page === 'my-ratings.php' ? 'active' : '' ?>">
            <i class="fas fa-star"></i>
            <span>My Ratings</span>
        </a>
        
        <a href="favorites.php" class="nav-item <?= $current_page === 'favorites.php' ? 'active' : '' ?>">
            <i class="fas fa-heart"></i>
            <span>Favorites</span>
        </a>
        
        <div class="nav-divider"></div>
        
        <a href="profile.php" class="nav-item <?= $current_page === 'profile.php' ? 'active' : '' ?>">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <div class="progress-widget">
            <h4><i class="fas fa-trophy"></i> Your Progress</h4>
            <div class="progress-circle">
                <span class="progress-value">0%</span>
            </div>
            <p>Keep learning to level up!</p>
        </div>
    </div>
</aside>