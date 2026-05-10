<?php
/**
 * Viewer Top Navigation Bar
 * Hasan Fardan - 202301686
 */
?>
<nav class="viewer-navbar">
    <div class="navbar-brand">
        <a href="<?= SITE_URL ?>/viewer/dashboard.php">
            <i class="fas fa-graduation-cap"></i>
            <span><?= SITE_NAME ?></span>
        </a>
    </div>

    <div class="navbar-search">
        <form action="<?= SITE_URL ?>/viewer/browse-tutorials.php" method="GET">
            <i class="fas fa-search"></i>
            <input type="text" name="search" placeholder="Search tutorials..."
                   class="search-input" value="<?= isset($_GET['search']) ? e($_GET['search']) : '' ?>">
        </form>
    </div>

    <div class="navbar-menu">
        <a href="<?= SITE_URL ?>/viewer/browse-tutorials.php" class="nav-link">
            <i class="fas fa-th-large"></i>
            <span>Browse</span>
        </a>

        <a href="<?= SITE_URL ?>/viewer/my-learning.php" class="nav-link">
            <i class="fas fa-book-open"></i>
            <span>My Learning</span>
        </a>

        <div class="nav-dropdown" id="userDropdown">
            <button class="nav-link dropdown-toggle" onclick="toggleDropdown(event)">
                <i class="fas fa-user-circle"></i>
                <span><?= e($current_user_name) ?></span>
                <i class="fas fa-chevron-down" style="font-size:11px;"></i>
            </button>
            <div class="dropdown-menu" id="dropdownMenu" style="display:none;">
                <a href="<?= SITE_URL ?>/viewer/dashboard.php" class="dropdown-item">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="<?= SITE_URL ?>/viewer/my-learning.php" class="dropdown-item">
                    <i class="fas fa-graduation-cap"></i> My Learning
                </a>
                <div class="dropdown-divider"></div>
                <a href="<?= SITE_URL ?>/auth/logout.php" class="dropdown-item" style="color:#e53e3e;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<script>
function toggleDropdown(e) {
    e.stopPropagation();
    const menu = document.getElementById('dropdownMenu');
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}
// close dropdown when clicking anywhere else on the page
document.addEventListener('click', function() {
    const menu = document.getElementById('dropdownMenu');
    if (menu) menu.style.display = 'none';
});
</script>
