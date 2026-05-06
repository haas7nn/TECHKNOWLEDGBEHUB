<?php
/**
 * Viewer Navigation Bar
 * top nav for student pages
 * Hasan Fardan - 202301686
 */
?>
<nav class="viewer-navbar">
    <div class="navbar-brand">
        <a href="../public/index.php">
            <i class="fas fa-graduation-cap"></i>
            <span><?= SITE_NAME ?></span>
        </a>
    </div>
    
    <div class="navbar-search">
        <form action="browse-tutorials.php" method="GET">
            <i class="fas fa-search"></i>
            <input 
                type="text" 
                name="search" 
                placeholder="Search tutorials..." 
                class="search-input"
            >
        </form>
    </div>
    
    <div class="navbar-menu">
        <a href="browse-tutorials.php" class="nav-link">
            <i class="fas fa-th"></i>
            <span>Browse</span>
        </a>
        
        <a href="my-learning.php" class="nav-link">
            <i class="fas fa-graduation-cap"></i>
            <span>My Learning</span>
        </a>
        
        <div class="nav-dropdown">
            <button class="nav-link dropdown-toggle">
                <i class="fas fa-user-circle"></i>
                <span><?= e($current_user_name) ?></span>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="dropdown-menu">
                <a href="dashboard.php" class="dropdown-item">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
                <a href="profile.php" class="dropdown-item">
                    <i class="fas fa-user"></i>
                    My Profile
                </a>
                <a href="my-progress.php" class="dropdown-item">
                    <i class="fas fa-chart-line"></i>
                    My Progress
                </a>
                <a href="my-ratings.php" class="dropdown-item">
                    <i class="fas fa-star"></i>
                    My Ratings
                </a>
                <div class="dropdown-divider"></div>
                <a href="../auth/logout.php" class="dropdown-item">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </div>
</nav>