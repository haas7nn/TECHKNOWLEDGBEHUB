<?php
// load categories for the category dropdown
// guard with isset($conn) so nav still renders without a db connection
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
$navCategories = [];
if (isset($conn)) {
    $catStmt = $conn->query("SELECT category_id, category_name FROM dbProj_categories ORDER BY category_name");
    $navCategories = $catStmt ? $catStmt->fetchAll() : [];
}
?>
<nav class="viewer-navbar">

    <!-- mobile hamburger toggle -->
    <button class="sidebar-toggle-btn" id="viewerSidebarToggle" aria-label="Toggle navigation" type="button">
        <i class="fas fa-bars"></i>
    </button>

    <!-- site logo -->
    <div class="navbar-brand">
        <a href="<?= SITE_URL ?>/viewer/dashboard.php">
            <i class="fas fa-graduation-cap"></i>
            <span><?= SITE_NAME ?></span>
        </a>
    </div>

    <!-- search bar -->
    <div class="navbar-search">
        <i class="fas fa-search"></i>
        <form action="<?= SITE_URL ?>/viewer/browse-tutorials.php" method="GET" role="search">
            <label for="navbar-search" class="sr-only">Search tutorials</label>
            <input type="text" id="navbar-search" name="search"
                   placeholder="Search tutorials..."
                   class="search-input"
                   aria-label="Search tutorials"
                   value="<?= isset($_GET['search']) ? e($_GET['search']) : '' ?>">
        </form>
    </div>

    <!-- main nav links -->
    <div class="navbar-menu">
        <a href="<?= SITE_URL ?>/viewer/browse-tutorials.php" class="nav-link">
            <i class="fas fa-th-large"></i><span>Browse</span>
        </a>
        <a href="<?= SITE_URL ?>/viewer/my-learning.php" class="nav-link">
            <i class="fas fa-book-open"></i><span>My Learning</span>
        </a>

        <!-- category dropdown -->
        <?php if (!empty($navCategories)): ?>
        <div class="nav-dropdown">
            <button class="nav-link dropdown-toggle"
                    onclick="toggleNavDropdown(event, 'categoryDropdownMenu')"
                    aria-expanded="false"
                    aria-haspopup="true"
                    type="button">
                <i class="fas fa-th-list" aria-hidden="true"></i>
                <span>Categories</span>
                <i class="fas fa-chevron-down" aria-hidden="true" style="font-size:10px;opacity:.6;"></i>
            </button>
            <ul class="dropdown-menu" id="categoryDropdownMenu">
                <?php foreach ($navCategories as $cat): ?>
                <li><a href="<?= SITE_URL ?>/viewer/browse-tutorials.php?category=<?= (int)$cat['category_id'] ?>" class="dropdown-item">
                    <i class="fas fa-tag" aria-hidden="true"></i> <?= e($cat['category_name']) ?>
                </a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- user dropdown -->
        <div class="nav-dropdown">
            <button class="nav-link dropdown-toggle"
                    onclick="toggleNavDropdown(event, 'viewerDropdownMenu')"
                    aria-expanded="false"
                    aria-haspopup="true"
                    type="button">
                <i class="fas fa-user-circle" aria-hidden="true"></i>
                <span><?= e($current_user_name ?? 'User') ?></span>
                <i class="fas fa-chevron-down" aria-hidden="true" style="font-size:10px;opacity:.6;"></i>
            </button>
            <!-- user dropdown items -->
            <div class="dropdown-menu" id="viewerDropdownMenu">
                <a href="<?= SITE_URL ?>/viewer/dashboard.php"   class="dropdown-item"><i class="fas fa-home"></i> Dashboard</a>
                <a href="<?= SITE_URL ?>/viewer/my-learning.php" class="dropdown-item"><i class="fas fa-graduation-cap"></i> My Learning</a>
                <div class="dropdown-divider"></div>
                <a href="<?= SITE_URL ?>/auth/logout.php" class="dropdown-item" style="color:var(--c-danger);"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
</nav>

<script>
// toggle dropdown open or closed one at a time
function toggleNavDropdown(e, menuId) {
    e.stopPropagation();
    var menu = document.getElementById(menuId);
    if (!menu) return;
    var isNowOpen = menu.classList.contains('is-open');
    document.querySelectorAll('.dropdown-menu.is-open').forEach(function(m){ m.classList.remove('is-open'); });
    // reopen if it was closed
    if (!isNowOpen) { menu.classList.add('is-open'); }
}
// close dropdowns on outside click
document.addEventListener('click', function () {
    document.querySelectorAll('.dropdown-menu.is-open').forEach(function(m){ m.classList.remove('is-open'); });
});

// toggle viewer sidebar on mobile
var _sidebarBtn = document.getElementById('viewerSidebarToggle');
if (_sidebarBtn) {
    _sidebarBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        var sb = document.querySelector('.viewer-sidebar');
        if (sb) sb.classList.toggle('open');
    });
}
// close sidebar on outside tap
document.addEventListener('click', function (e) {
    var sb = document.querySelector('.viewer-sidebar');
    if (sb && sb.classList.contains('open') && !sb.contains(e.target)) {
        sb.classList.remove('open');
    }
});
</script>
