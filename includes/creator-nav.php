<?php
// top navigation bar for the creator panel
// uses the same css classes as the viewer nav
// track the current filename so the right nav link can be marked active

$_cp = basename($_SERVER['PHP_SELF']);
?>
<nav class="viewer-navbar">

    <!-- site logo linking back to the creator dashboard -->
    <div class="navbar-brand">
        <a href="<?= SITE_URL ?>/creator/dashboard.php">
            <i class="fas fa-graduation-cap"></i>
            <span><?= SITE_NAME ?></span>
        </a>
    </div>

    <!-- centre nav links with active state highlighting -->
    <div class="navbar-center">
        <a href="<?= SITE_URL ?>/creator/dashboard.php"
           class="nav-link<?= $_cp === 'dashboard.php'       ? ' active' : '' ?>">
            <i class="fas fa-home"></i><span>Dashboard</span>
        </a>
        <a href="<?= SITE_URL ?>/creator/my-tutorials.php"
           class="nav-link<?= $_cp === 'my-tutorials.php'    ? ' active' : '' ?>">
            <i class="fas fa-book"></i><span>My Tutorials</span>
        </a>
        <a href="<?= SITE_URL ?>/creator/create-tutorial.php"
           class="nav-link<?= $_cp === 'create-tutorial.php' ? ' active' : '' ?>">
            <i class="fas fa-plus-circle"></i><span>Create</span>
        </a>
        <a href="<?= SITE_URL ?>/creator/analytics.php"
           class="nav-link<?= $_cp === 'analytics.php'       ? ' active' : '' ?>">
            <i class="fas fa-chart-line"></i><span>Analytics</span>
        </a>
    </div>

    <!-- user account dropdown on the right side -->
    <div class="navbar-menu">
        <div class="nav-dropdown" id="creatorDropdownWrap">
            <button class="nav-link dropdown-toggle"
                    onclick="toggleNavDropdown(event, 'creatorDropdownMenu')"
                    aria-expanded="false"
                    aria-haspopup="true"
                    type="button">
                <i class="fas fa-user-circle" aria-hidden="true"></i>
                <span><?= e($current_user_name ?? 'Creator') ?></span>
                <i class="fas fa-chevron-down" aria-hidden="true" style="font-size:10px;opacity:.6;"></i>
            </button>
            <!-- dropdown menu items for profile settings viewing the site and logging out -->
            <div class="dropdown-menu" id="creatorDropdownMenu">
                <a href="<?= SITE_URL ?>/creator/profile.php"  class="dropdown-item"><i class="fas fa-user"></i> My Profile</a>
                <a href="<?= SITE_URL ?>/creator/settings.php" class="dropdown-item"><i class="fas fa-cog"></i> Settings</a>
                <a href="<?= SITE_URL ?>/public/search.php" class="dropdown-item" target="_blank"><i class="fas fa-eye"></i> View Site</a>
                <div class="dropdown-divider"></div>
                <a href="<?= SITE_URL ?>/auth/logout.php" class="dropdown-item" style="color:var(--c-danger);"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
</nav>

<script>
// toggle a dropdown menu open or closed without letting the click bubble to the document
function toggleNavDropdown(e, menuId) {
    e.stopPropagation();
    var menu = document.getElementById(menuId);
    if (!menu) return;
    menu.classList.toggle('is-open');
}
// close all open dropdowns when the user clicks anywhere else on the page
document.addEventListener('click', function () {
    document.querySelectorAll('.dropdown-menu.is-open').forEach(function(m){ m.classList.remove('is-open'); });
});
</script>
