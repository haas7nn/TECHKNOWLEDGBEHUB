<?php
// root landing page
// logged in users go straight to their dashboard
// guests see the landing page with login and browse options

require_once 'config/config.php';

// redirect by role
if (isLoggedIn()) {
    $role = getCurrentUserRole();
    if ($role === 'admin')        redirect('admin/dashboard.php');
    elseif ($role === 'creator')  redirect('creator/dashboard.php');
    else                          redirect('viewer/dashboard.php');
}

// get stats and featured
$db   = new Database();
$conn = $db->connect();

$total_tutorials  = 0;
$total_categories = 0;
$total_students   = 0;
$featured         = [];
$latest           = [];
$categories       = [];

if ($conn) {
    // published count
    $total_tutorials  = $conn->query("SELECT COUNT(*) FROM dbProj_tutorials WHERE status='published'")->fetchColumn();
    // category count
    $total_categories = $conn->query("SELECT COUNT(*) FROM dbProj_categories")->fetchColumn();
    // viewer count
    $total_students   = $conn->query("SELECT COUNT(*) FROM dbProj_users WHERE role='viewer' AND status='active'")->fetchColumn();

    // get top 3 tutorials
    $stmt = $conn->prepare(
        "SELECT t.tutorial_id, t.title, t.slug, t.thumbnail, t.short_description,
                t.difficulty, t.view_count, t.duration_minutes,
                u.full_name AS instructor_name,
                c.category_name,
                COALESCE(AVG(r.rating), 0) AS avg_rating
         FROM dbProj_tutorials t
         JOIN dbProj_users u      ON u.user_id     = t.instructor_id
         JOIN dbProj_categories c ON c.category_id = t.category_id
         LEFT JOIN dbProj_ratings r ON r.tutorial_id = t.tutorial_id
         WHERE t.status = 'published'
         GROUP BY t.tutorial_id
         ORDER BY t.view_count DESC
         LIMIT 3"
    );
    $stmt->execute();
    $featured = $stmt->fetchAll();

    // get latest 6 published tutorials (reverse chronological — satisfies req 1.2)
    $latestStmt = $conn->prepare(
        "SELECT t.tutorial_id, t.title, t.slug, t.thumbnail, t.short_description,
                t.difficulty, t.view_count, t.duration_minutes, t.published_at,
                u.full_name AS instructor_name,
                c.category_name,
                COALESCE(AVG(r.rating), 0) AS avg_rating
         FROM dbProj_tutorials t
         JOIN dbProj_users u      ON u.user_id     = t.instructor_id
         JOIN dbProj_categories c ON c.category_id = t.category_id
         LEFT JOIN dbProj_ratings r ON r.tutorial_id = t.tutorial_id
         WHERE t.status = 'published'
         GROUP BY t.tutorial_id
         ORDER BY t.published_at DESC
         LIMIT 6"
    );
    $latestStmt->execute();
    $latest = $latestStmt->fetchAll();

    // get all categories
    $categories = $conn->query(
        "SELECT category_id, category_name, icon FROM dbProj_categories ORDER BY category_name"
    )->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> | Learn Without Limits</title>
    <link rel="stylesheet" href="<?= asset('css/search.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    /* landing page extras not covered by search.css */

    /* stats strip */
    .stats-strip {
        display: flex;
        justify-content: center;
        gap: 0;
        background: var(--c-surface);
        border-bottom: 1px solid var(--c-border);
        flex-wrap: wrap;
    }
    .stat-item {
        flex: 1;
        min-width: 140px;
        max-width: 220px;
        text-align: center;
        padding: 28px 20px;
        border-right: 1px solid var(--c-border);
    }
    .stat-item:last-child { border-right: none; }
    .stat-item .stat-num {
        font-size: 32px;
        font-weight: 800;
        color: var(--c-primary);
        line-height: 1;
        display: block;
        margin-bottom: 6px;
    }
    .stat-item .stat-label {
        font-size: 13px;
        color: var(--c-text-3);
        font-weight: 500;
    }
    .stat-item i {
        font-size: 22px;
        color: var(--c-primary);
        margin-bottom: 10px;
        display: block;
        opacity: .75;
    }

    /* category pills */
    .categories-strip {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 10px;
        padding: 36px 24px;
        background: var(--c-page);
    }
    .cat-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: var(--c-surface);
        border: 1.5px solid var(--c-border);
        border-radius: var(--r-pill);
        font-size: 14px;
        font-weight: 500;
        color: var(--c-text-2);
        text-decoration: none;
        transition: background var(--t-fast), border-color var(--t-fast), color var(--t-fast);
    }
    .cat-pill:hover {
        background: var(--c-primary-bg);
        border-color: var(--c-primary);
        color: var(--c-primary);
    }
    .cat-pill i { color: var(--c-primary); font-size: 15px; }

    /* featured tutorials section */
    .featured-section {
        padding: 52px 24px 60px;
        background: var(--c-page);
    }
    .featured-section h2 {
        text-align: center;
        font-size: 26px;
        font-weight: 800;
        color: var(--c-text);
        margin-bottom: 8px;
    }
    .featured-section .sub {
        text-align: center;
        color: var(--c-text-3);
        font-size: 15px;
        margin-bottom: 36px;
    }
    .featured-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 24px;
        max-width: 1100px;
        margin: 0 auto 36px;
    }

    /* CTA strip at the bottom */
    .cta-strip {
        background: var(--c-gradient);
        text-align: center;
        padding: 60px 24px;
        color: #fff;
    }
    .cta-strip h2 { font-size: 28px; font-weight: 800; margin-bottom: 10px; }
    .cta-strip p  { font-size: 16px; opacity: .85; margin-bottom: 28px; }
    .cta-strip .btn-white {
        background: #ffffff;
        color: var(--c-primary);
        font-weight: 700;
        padding: 13px 28px;
        border-radius: var(--r-pill);
        text-decoration: none;
        font-size: 15px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: opacity var(--t-fast);
    }
    .cta-strip .btn-white:hover { opacity: .90; }
    .cta-strip .btn-ghost {
        background: transparent;
        color: #fff;
        border: 2px solid rgba(255,255,255,.55);
        font-weight: 600;
        padding: 13px 28px;
        border-radius: var(--r-pill);
        text-decoration: none;
        font-size: 15px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: border-color var(--t-fast), background var(--t-fast);
        margin-left: 12px;
    }
    .cta-strip .btn-ghost:hover {
        border-color: rgba(255,255,255,.85);
        background: rgba(255,255,255,.08);
    }

    /* hero tweaks: bigger text, 3 CTA buttons */
    .hero-actions {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 32px;
    }
    .btn-hero-primary {
        padding: 14px 28px;
        background: #ffffff;
        color: var(--c-primary);
        border-radius: var(--r-pill);
        font-size: 15px;
        font-weight: 700;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: opacity var(--t-fast), box-shadow var(--t-fast);
    }
    .btn-hero-primary:hover { opacity: .90; box-shadow: 0 4px 16px rgba(0,0,0,.15); }

    .btn-hero-secondary {
        padding: 13px 24px;
        background: rgba(255,255,255,.15);
        color: #fff;
        border: 2px solid rgba(255,255,255,.50);
        border-radius: var(--r-pill);
        font-size: 15px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: background var(--t-fast), border-color var(--t-fast);
    }
    .btn-hero-secondary:hover {
        background: rgba(255,255,255,.25);
        border-color: rgba(255,255,255,.80);
    }

    .btn-hero-ghost {
        padding: 13px 22px;
        background: transparent;
        color: rgba(255,255,255,.80);
        border: none;
        border-radius: var(--r-pill);
        font-size: 14px;
        font-weight: 500;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        transition: color var(--t-fast);
        text-decoration: underline;
        text-underline-offset: 3px;
        text-decoration-color: rgba(255,255,255,.35);
    }
    .btn-hero-ghost:hover { color: #fff; text-decoration-color: rgba(255,255,255,.75); }

    /* featured card style reused from search.css */
    .feat-card {
        background: var(--c-surface);
        border-radius: var(--r-lg);
        border: 1px solid var(--c-border);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        display: flex;
        flex-direction: column;
        transition: transform var(--t-base), box-shadow var(--t-base);
        text-decoration: none;
        color: inherit;
    }
    .feat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-md);
        border-color: rgba(102,126,234,.20);
    }
    .feat-card .feat-img {
        height: 180px;
        overflow: hidden;
        background: var(--c-border-2);
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }
    .feat-card .feat-img img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .feat-card .feat-img .no-img {
        font-size: 48px;
        color: var(--c-text-4);
    }
    .feat-card .feat-body {
        padding: 18px;
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .feat-card .feat-cat {
        font-size: 11px;
        font-weight: 700;
        color: var(--c-primary);
        text-transform: uppercase;
        letter-spacing: .05em;
    }
    .feat-card .feat-title {
        font-size: 15px;
        font-weight: 700;
        color: var(--c-text);
        line-height: 1.4;
    }
    .feat-card .feat-meta {
        display: flex;
        gap: 14px;
        font-size: 12px;
        color: var(--c-text-4);
        margin-top: auto;
        padding-top: 10px;
        border-top: 1px solid var(--c-border-2);
        flex-wrap: wrap;
    }
    .feat-card .feat-meta span {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .feat-card .feat-meta .fa-star { color: var(--c-warning); }

    .view-all-wrap {
        text-align: center;
        margin-top: 8px;
    }
    .btn-view-all {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 28px;
        background: var(--c-primary-bg);
        color: var(--c-primary);
        border-radius: var(--r-pill);
        font-size: 14px;
        font-weight: 700;
        text-decoration: none;
        transition: background var(--t-fast), box-shadow var(--t-fast);
    }
    .btn-view-all:hover {
        background: var(--c-primary);
        color: #fff;
        box-shadow: 0 4px 12px rgba(102,126,234,.30);
    }

    @media (max-width: 768px) {
        .stat-item  { min-width: 50%; border-right: none; border-bottom: 1px solid var(--c-border); }
        .hero-actions { flex-direction: column; align-items: center; }
        .featured-grid { grid-template-columns: 1fr; }
        .cta-strip .btn-ghost { margin-left: 0; margin-top: 10px; }
    }
    </style>
</head>
<body>

<!-- public navigation bar -->
<div class="site-topbar">
    <div class="navbar-brand">
        <a href="<?= SITE_URL ?>">
            <i class="fas fa-graduation-cap" aria-hidden="true"></i>
            <span><?= SITE_NAME ?></span>
        </a>
    </div>
    <div class="nav-links">
        <a href="<?= SITE_URL ?>/public/search.php">
            <i class="fas fa-search" aria-hidden="true"></i> Browse
        </a>
        <a href="<?= SITE_URL ?>/auth/login.php">
            <i class="fas fa-sign-in-alt" aria-hidden="true"></i> Login
        </a>
        <a href="<?= SITE_URL ?>/auth/register.php" class="nav-btn">
            <i class="fas fa-user-plus" aria-hidden="true"></i> Register
        </a>
    </div>
</div>

<!-- hero section -->
<div class="site-hero" style="padding: 80px 24px 70px;">
    <h1 style="font-size: 46px; font-weight: 800; margin-bottom: 16px; letter-spacing: -.03em;">
        Learn. Build. Grow.
    </h1>
    <p style="font-size: 18px; opacity: .85; max-width: 540px; margin: 0 auto 8px;">
        Expert tutorials on Web Development, Databases, Programming and more.
        Completely free to explore.
    </p>

    <div class="hero-actions">
        <!-- primary: get started -->
        <a href="<?= SITE_URL ?>/auth/register.php" class="btn-hero-primary">
            <i class="fas fa-rocket" aria-hidden="true"></i> Get Started Free
        </a>
        <!-- secondary: login -->
        <a href="<?= SITE_URL ?>/auth/login.php" class="btn-hero-secondary">
            <i class="fas fa-sign-in-alt" aria-hidden="true"></i> Login
        </a>
        <!-- ghost: browse without signing in -->
        <a href="<?= SITE_URL ?>/public/search.php" class="btn-hero-ghost">
            Browse without signing in <i class="fas fa-arrow-right" aria-hidden="true"></i>
        </a>
    </div>
</div>

<!-- stats strip -->
<div class="stats-strip">
    <div class="stat-item">
        <i class="fas fa-book-open" aria-hidden="true"></i>
        <span class="stat-num"><?= number_format($total_tutorials) ?>+</span>
        <span class="stat-label">Tutorials</span>
    </div>
    <div class="stat-item">
        <i class="fas fa-layer-group" aria-hidden="true"></i>
        <span class="stat-num"><?= number_format($total_categories) ?></span>
        <span class="stat-label">Categories</span>
    </div>
    <div class="stat-item">
        <i class="fas fa-users" aria-hidden="true"></i>
        <span class="stat-num"><?= number_format($total_students) ?>+</span>
        <span class="stat-label">Students</span>
    </div>
    <div class="stat-item">
        <i class="fas fa-certificate" aria-hidden="true"></i>
        <span class="stat-num">100%</span>
        <span class="stat-label">Free Access</span>
    </div>
</div>

<!-- category pills -->
<?php if (!empty($categories)): ?>
<div class="categories-strip">
    <?php foreach ($categories as $cat): ?>
    <a href="<?= SITE_URL ?>/public/search.php?category=<?= $cat['category_id'] ?>" class="cat-pill">
        <i class="fas <?= e($cat['icon'] ?? 'fa-folder') ?>" aria-hidden="true"></i>
        <?= e($cat['category_name']) ?>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- latest tutorials (reverse chronological — newest first, per req 1.2) -->
<?php if (!empty($latest)): ?>
<div class="featured-section" style="background:var(--c-surface);border-bottom:1px solid var(--c-border);">
    <h2>Latest Tutorials</h2>
    <p class="sub">Newest content, added most recently first</p>

    <div class="featured-grid">
        <?php foreach ($latest as $tut): ?>
        <a href="<?= SITE_URL ?>/viewer/tutorial-view.php?slug=<?= urlencode($tut['slug']) ?>"
           class="feat-card">
            <div class="feat-img">
                <?php if (!empty($tut['thumbnail'])): ?>
                    <img src="<?= SITE_URL ?>/uploads/<?= e($tut['thumbnail']) ?>"
                         alt="<?= e($tut['title']) ?>">
                <?php else: ?>
                    <i class="fas fa-book no-img" aria-hidden="true"></i>
                <?php endif; ?>
                <span class="difficulty-badge difficulty-<?= e($tut['difficulty']) ?>"
                      style="position:absolute;top:10px;right:10px;">
                    <?= ucfirst($tut['difficulty']) ?>
                </span>
            </div>
            <div class="feat-body">
                <div class="feat-cat">
                    <i class="fas fa-folder" aria-hidden="true"></i> <?= e($tut['category_name']) ?>
                </div>
                <div class="feat-title"><?= e($tut['title']) ?></div>
                <p style="font-size:13px;color:var(--c-text-3);line-height:1.5;flex:1;">
                    <?= e(truncate($tut['short_description'], 100)) ?>
                </p>
                <div class="feat-meta">
                    <span><i class="fas fa-user" aria-hidden="true"></i> <?= e($tut['instructor_name']) ?></span>
                    <span><i class="fas fa-calendar-alt" aria-hidden="true"></i>
                        <?= date('M j, Y', strtotime($tut['published_at'])) ?>
                    </span>
                    <span><i class="fas fa-star" aria-hidden="true"></i>
                        <?= number_format((float)$tut['avg_rating'], 1) ?>
                    </span>
                </div>
                <div style="margin-top:14px;">
                    <span style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:700;color:var(--c-primary);">
                        View More <i class="fas fa-arrow-right" aria-hidden="true"></i>
                    </span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="view-all-wrap">
        <a href="<?= SITE_URL ?>/public/search.php?sort=newest" class="btn-view-all">
            View All (Newest First) <i class="fas fa-arrow-right" aria-hidden="true"></i>
        </a>
    </div>
</div>
<?php endif; ?>

<!-- featured tutorials -->
<?php if (!empty($featured)): ?>
<div class="featured-section">
    <h2>Popular Tutorials</h2>
    <p class="sub">Handpicked by our community, no account needed to watch</p>

    <div class="featured-grid">
        <?php foreach ($featured as $tut): ?>
        <a href="<?= SITE_URL ?>/viewer/tutorial-view.php?slug=<?= urlencode($tut['slug']) ?>"
           class="feat-card">
            <div class="feat-img">
                <?php if (!empty($tut['thumbnail'])): ?>
                    <img src="<?= SITE_URL ?>/uploads/<?= e($tut['thumbnail']) ?>"
                         alt="<?= e($tut['title']) ?>">
                <?php else: ?>
                    <i class="fas fa-book no-img" aria-hidden="true"></i>
                <?php endif; ?>
                <span class="difficulty-badge difficulty-<?= e($tut['difficulty']) ?>"
                      style="position:absolute;top:10px;right:10px;">
                    <?= ucfirst($tut['difficulty']) ?>
                </span>
            </div>
            <div class="feat-body">
                <div class="feat-cat">
                    <i class="fas fa-folder" aria-hidden="true"></i> <?= e($tut['category_name']) ?>
                </div>
                <div class="feat-title"><?= e($tut['title']) ?></div>
                <div class="feat-meta">
                    <span><i class="fas fa-user" aria-hidden="true"></i> <?= e($tut['instructor_name']) ?></span>
                    <span><i class="fas fa-star" aria-hidden="true"></i> <?= number_format((float)$tut['avg_rating'], 1) ?></span>
                    <span><i class="fas fa-eye" aria-hidden="true"></i> <?= number_format($tut['view_count']) ?></span>
                    <?php if ($tut['duration_minutes']): ?>
                    <span><i class="fas fa-clock" aria-hidden="true"></i> <?= $tut['duration_minutes'] ?> min</span>
                    <?php endif; ?>
                </div>
                <div style="margin-top:14px;">
                    <span style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:700;color:var(--c-primary);">
                        View More <i class="fas fa-arrow-right" aria-hidden="true"></i>
                    </span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="view-all-wrap">
        <a href="<?= SITE_URL ?>/public/search.php" class="btn-view-all">
            View All Tutorials <i class="fas fa-arrow-right" aria-hidden="true"></i>
        </a>
    </div>
</div>
<?php endif; ?>

<!-- bottom CTA -->
<div class="cta-strip">
    <h2>Ready to start learning?</h2>
    <p>Create a free account to track your progress, rate tutorials and join the community</p>
    <a href="<?= SITE_URL ?>/auth/register.php" class="btn-white">
        <i class="fas fa-user-plus" aria-hidden="true"></i> Create Free Account
    </a>
    <a href="<?= SITE_URL ?>/public/search.php" class="btn-ghost">
        <i class="fas fa-search" aria-hidden="true"></i> Just Browse
    </a>
</div>

</body>
</html>
