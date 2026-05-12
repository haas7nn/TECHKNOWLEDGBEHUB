<?php
// browse tutorials page — lets students filter and search all published tutorials
// Hasan Fardan - 202301686

require_once '../includes/viewer-auth-check.php';
require_once '../classes/Tutorial.php';
require_once '../classes/Category.php';

$page_title  = 'Browse Tutorials';
$css_version = @filemtime(__DIR__ . '/../assets/css/viewer.css') ?: time();

// grab the filters from the URL
$search     = isset($_GET['search'])   ? clean($_GET['search'])     : '';
$difficulty = isset($_GET['difficulty'])? clean($_GET['difficulty']) : '';
$sort       = isset($_GET['sort'])     ? clean($_GET['sort'])       : 'newest';
$page       = isset($_GET['page'])     ? max(1, (int)$_GET['page']) : 1;

// category needs to be an int — 0 means "all categories"
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// pull all categories for the filter dropdown
$categoryObj = new Category();
$categories  = $categoryObj->getAll();

// build the filters array — only pass category if they actually picked one
// bug fix: category 0 means all, so skip it
$filters = ['sort' => $sort];
if (!empty($search))     $filters['search']      = $search;
if ($category > 0)       $filters['category_id'] = $category;
if (!empty($difficulty)) $filters['difficulty']  = $difficulty;

// actually go get the tutorials from the database
$tutorialObj    = new Tutorial();
$result         = $tutorialObj->search($filters, $page, 12);

// pull out what we need — with safe fallbacks so nothing crashes
// bug fix: $total_results was missing before
$tutorials      = $result['tutorials']              ?? [];
$pagination     = $result['pagination']             ?? [];
$total_results  = $pagination['total_items']        ?? 0;

// load the user's favorited tutorial IDs so heart icons show the right state on load
$user_favorites = [];
$db   = new Database();
$conn = $db->connect();
if ($conn) {
    $favStmt = $conn->prepare(
        "SELECT tutorial_id FROM dbProj_user_activity
         WHERE user_id = :uid AND activity_type = 'favorite'"
    );
    $favStmt->bindParam(':uid', $current_user_id, PDO::PARAM_INT);
    $favStmt->execute();
    foreach ($favStmt->fetchAll() as $row) {
        $user_favorites[] = $row['tutorial_id'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= asset('css/viewer.css') ?>?v=<?= $css_version ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/viewer-nav.php'; ?>

    <div class="viewer-container">
        <?php include '../includes/viewer-sidebar.php'; ?>

        <main class="viewer-main">

            <!-- page heading -->
            <div class="browse-header">
                <h1><i class="fas fa-th-large"></i> Browse Tutorials</h1>
                <p>Discover thousands of tutorials to master new skills</p>
            </div>

            <?php displayFlashMessage(); ?>

            <!-- filter bar -->
            <div class="browse-filters">
                <form method="GET" action="" class="filters-form">
                    <!-- text search box -->
                    <div class="search-box-large">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search"
                               placeholder="Search tutorials..."
                               value="<?= e($search) ?>">
                    </div>

                    <!-- category dropdown -->
                    <div class="filter-group">
                        <select name="category" class="filter-select">
                            <option value="0">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['category_id'] ?>"
                                        <?= $category === (int)$cat['category_id'] ? 'selected' : '' ?>>
                                    <?= e($cat['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- difficulty dropdown -->
                    <div class="filter-group">
                        <select name="difficulty" class="filter-select">
                            <option value="">All Levels</option>
                            <?php foreach (['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'] as $val => $label): ?>
                                <option value="<?= $val ?>" <?= $difficulty === $val ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- sort dropdown -->
                    <div class="filter-group">
                        <select name="sort" class="filter-select">
                            <option value="newest"   <?= $sort==='newest'   ?'selected':'' ?>>Newest First</option>
                            <option value="popular"  <?= $sort==='popular'  ?'selected':'' ?>>Most Popular</option>
                            <option value="rating"   <?= $sort==='rating'   ?'selected':'' ?>>Highest Rated</option>
                            <option value="title"    <?= $sort==='title'    ?'selected':'' ?>>Title A-Z</option>
                            <option value="relevant" <?= $sort==='relevant' ?'selected':'' ?>>Most Relevant</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                </form>
            </div>

            <!-- results info -->
            <div class="results-info">
                <?php if (!empty($search) || $category > 0 || !empty($difficulty)): ?>
                    <strong><?= number_format($total_results) ?></strong> tutorial<?= $total_results !== 1 ? 's' : '' ?> found
                    — <a href="browse-tutorials.php">Clear Filters</a>
                <?php else: ?>
                    Showing <strong><?= number_format($total_results) ?></strong> tutorial<?= $total_results !== 1 ? 's' : '' ?>
                <?php endif; ?>
            </div>

            <!-- tutorial cards grid -->
            <?php if (empty($tutorials)): ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h3>No tutorials found</h3>
                    <p>Try adjusting your filters or search terms</p>
                    <a href="browse-tutorials.php" class="btn btn-primary">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </div>
            <?php else: ?>
                <div class="tutorials-grid-large">
                    <?php foreach ($tutorials as $tutorial): ?>
                    <div class="tutorial-card-browse">

                        <!-- thumbnail with difficulty badge and favorite button -->
                        <div class="card-image">
                            <?php if (!empty($tutorial['thumbnail'])): ?>
                                <img src="<?= SITE_URL ?>/uploads/<?= e($tutorial['thumbnail']) ?>"
                                     alt="<?= e($tutorial['title']) ?>"
                                     onerror="this.style.display='none'">
                            <?php else: ?>
                                <div style="height:200px;background:#e8ecf1;display:flex;align-items:center;justify-content:center;">
                                    <i class="fas fa-book" style="font-size:48px;color:#aaa;"></i>
                                </div>
                            <?php endif; ?>

                            <span class="difficulty-badge difficulty-<?= e($tutorial['difficulty']) ?>">
                                <?= ucfirst($tutorial['difficulty']) ?>
                            </span>

                            <!-- heart button — filled if already in favorites -->
                            <?php $already_fav = in_array($tutorial['tutorial_id'], $user_favorites); ?>
                            <button class="favorite-btn"
                                    onclick="toggleFavorite(<?= $tutorial['tutorial_id'] ?>, this)"
                                    title="<?= $already_fav ? 'Remove from favorites' : 'Add to favorites' ?>">
                                <i class="<?= $already_fav ? 'fas' : 'far' ?> fa-heart"
                                   style="color:<?= $already_fav ? '#e53e3e' : 'inherit' ?>;"></i>
                            </button>
                        </div>

                        <!-- card text content -->
                        <div class="card-content">
                            <div class="card-tags">
                                <span class="tag">
                                    <i class="fas fa-folder"></i>
                                    <?= e($tutorial['category_name']) ?>
                                </span>
                            </div>

                            <h3><?= e($tutorial['title']) ?></h3>
                            <p class="description"><?= e($tutorial['short_description']) ?></p>

                            <!-- instructor row -->
                            <div class="instructor-info">
                                <?php if (!empty($tutorial['instructor_avatar'])): ?>
                                    <img src="<?= SITE_URL ?>/uploads/<?= e($tutorial['instructor_avatar']) ?>"
                                         alt="Instructor"
                                         onerror="this.style.display='none'">
                                <?php else: ?>
                                    <i class="fas fa-user-circle" style="font-size:26px;color:#cbd5e0;"></i>
                                <?php endif; ?>
                                <span><?= e($tutorial['instructor_name']) ?></span>
                            </div>

                            <!-- rating / views / duration -->
                            <div class="card-stats">
                                <span class="rating">
                                    <i class="fas fa-star" style="color:#ffc107;"></i>
                                    <?= number_format((float)($tutorial['avg_rating'] ?? 0), 1) ?>
                                    <small>(<?= (int)($tutorial['rating_count'] ?? 0) ?>)</small>
                                </span>
                                <span>
                                    <i class="fas fa-eye"></i>
                                    <?= number_format($tutorial['view_count']) ?>
                                </span>
                                <?php if (!empty($tutorial['duration_minutes'])): ?>
                                <span>
                                    <i class="fas fa-clock"></i>
                                    <?= $tutorial['duration_minutes'] ?> min
                                </span>
                                <?php endif; ?>
                            </div>

                            <!-- view tutorial button -->
                            <a href="<?= SITE_URL ?>/viewer/tutorial-view.php?slug=<?= urlencode($tutorial['slug']) ?>"
                               class="btn btn-primary btn-block">
                                <i class="fas fa-play-circle"></i> View Tutorial
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- pagination — only shows when there are multiple pages -->
                <?php if (!empty($pagination['total_pages']) && $pagination['total_pages'] > 1): ?>
                <?php
                // preserve all current filters in pagination links
                $base_url = '?' . http_build_query([
                    'search'     => $search,
                    'category'   => $category,
                    'difficulty' => $difficulty,
                    'sort'       => $sort,
                ]);
                ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?= $base_url ?>&page=<?= $page - 1 ?>" class="btn btn-outline">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php else: ?>
                        <button class="btn btn-outline" disabled>
                            <i class="fas fa-chevron-left"></i> Previous
                        </button>
                    <?php endif; ?>

                    <span class="page-info">Page <?= $page ?> of <?= $pagination['total_pages'] ?></span>

                    <?php if ($page < $pagination['total_pages']): ?>
                        <a href="<?= $base_url ?>&page=<?= $page + 1 ?>" class="btn btn-outline">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <button class="btn btn-outline" disabled>
                            Next <i class="fas fa-chevron-right"></i>
                        </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            <?php endif; ?>

        </main>
    </div>

    <script>
    // toggles the favorite state for a tutorial card
    // sends an AJAX request and updates the heart icon on success
    function toggleFavorite(tutorialId, btn) {
        fetch('<?= SITE_URL ?>/api/toggle-favorite.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tutorial_id: tutorialId,
                csrf_token: '<?= generateCSRFToken() ?>'
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const icon = btn.querySelector('i');
                if (data.favorited) {
                    icon.className = 'fas fa-heart';
                    icon.style.color = '#e53e3e';
                    btn.title = 'Remove from favorites';
                } else {
                    icon.className = 'far fa-heart';
                    icon.style.color = 'inherit';
                    btn.title = 'Add to favorites';
                }
            } else if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                alert(data.message || 'Could not update favorite.');
            }
        })
        .catch(() => alert('Network error. Please try again.'));
    }
    </script>
</body>
</html>
