<?php
// browse and filter published tutorials

require_once '../includes/viewer-auth-check.php';
require_once '../classes/Tutorial.php';
require_once '../classes/Category.php';

$page_title  = 'Browse Tutorials';
$css_version = @filemtime(__DIR__ . '/../assets/css/viewer.css') ?: time();

// read filter params from url
$search        = isset($_GET['search'])     ? clean($_GET['search'])     : '';
$difficulty    = isset($_GET['difficulty']) ? clean($_GET['difficulty']) : '';
$sort          = isset($_GET['sort'])       ? clean($_GET['sort'])       : 'newest';
$page          = isset($_GET['page'])       ? max(1, (int)$_GET['page']) : 1;
$instructor_id = isset($_GET['instructor']) ? (int)$_GET['instructor']   : 0;
$date_from     = isset($_GET['date_from'])  ? clean($_GET['date_from'])  : '';
$date_to       = isset($_GET['date_to'])    ? clean($_GET['date_to'])    : '';

// 0 means all categories
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// validate date inputs
$date_filter_error = '';
if ($date_from !== '') {
    $p = DateTime::createFromFormat('Y-m-d', $date_from);
    if (!$p || $p->format('Y-m-d') !== $date_from) { $date_from = ''; $date_filter_error = 'Invalid "From" date — ignored.'; }
}
if ($date_to !== '') {
    $p = DateTime::createFromFormat('Y-m-d', $date_to);
    if (!$p || $p->format('Y-m-d') !== $date_to) { $date_to = ''; $date_filter_error = 'Invalid "To" date — ignored.'; }
}
if ($date_from !== '' && $date_to !== '' && $date_from > $date_to) {
    $date_to = ''; $date_filter_error = '"To" date must be on or after "From" date — ignored.';
}

// load categories for dropdown
$categoryObj = new Category();
$categories  = $categoryObj->getAll();

// active creators for instructor filter
$db   = new Database();
$conn = $db->connect();
$instructors = [];
if ($conn) {
    $iStmt = $conn->prepare(
        "SELECT user_id, full_name FROM dbProj_users
         WHERE role = 'creator' AND status = 'active' ORDER BY full_name ASC"
    );
    $iStmt->execute();
    $instructors = $iStmt->fetchAll(PDO::FETCH_ASSOC);
}

// build filter params for search
$filters = ['sort' => $sort];
if (!empty($search))        $filters['search']        = $search;
if ($category > 0)          $filters['category_id']   = $category;
if (!empty($difficulty))    $filters['difficulty']    = $difficulty;
if ($instructor_id > 0)     $filters['instructor_id'] = $instructor_id;
if (!empty($date_from))     $filters['date_from']     = $date_from;
if (!empty($date_to))       $filters['date_to']       = $date_to;

// run paginated search
$tutorialObj    = new Tutorial();
$result         = $tutorialObj->search($filters, $page, 12);

// unpack search results
$tutorials      = $result['tutorials']              ?? [];
$pagination     = $result['pagination']             ?? [];
$total_results  = $pagination['total_items']        ?? 0;

// fetch user favorite ids for heart state
$user_favorites = [];
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
    <title><?= $page_title ?> | <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= asset('css/viewer.css') ?>?v=<?= $css_version ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body data-csrf="<?= generateCSRFToken() ?>">
    <?php include '../includes/viewer-nav.php'; ?>

    <div class="viewer-container">
        <?php include '../includes/viewer-sidebar.php'; ?>

        <main class="viewer-main">

            <!-- page header -->
            <div class="browse-header">
                <h1><i class="fas fa-th-large"></i> Browse Tutorials</h1>
                <p>Discover thousands of tutorials to master new skills</p>
            </div>

            <?php displayFlashMessage(); ?>

            <!-- filter bar -->
            <div class="browse-filters">
                <form method="GET" action="" class="filters-form" id="browseFilterForm">
                    <div class="filter-row">
                        <!-- text search -->
                        <div class="search-box-large">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <input type="text" name="search"
                                   placeholder="Search tutorials..."
                                   value="<?= e($search) ?>">
                        </div>

                        <!-- category filter -->
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

                        <!-- instructor filter -->
                        <div class="filter-group">
                            <select name="instructor" class="filter-select">
                                <option value="0">All Instructors</option>
                                <?php foreach ($instructors as $ins): ?>
                                    <option value="<?= $ins['user_id'] ?>"
                                            <?= $instructor_id === (int)$ins['user_id'] ? 'selected' : '' ?>>
                                        <?= e($ins['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- difficulty filter -->
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

                        <!-- sort order -->
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
                            <i class="fas fa-filter" aria-hidden="true"></i> Apply Filters
                        </button>
                    </div>

                    <!-- date range filter -->
                    <div class="filter-row" style="margin-top:10px;align-items:center;flex-wrap:wrap;gap:10px;">
                        <label style="font-size:13px;font-weight:600;color:var(--c-text-3);">
                            <i class="fas fa-calendar-alt"></i> Published from:
                        </label>
                        <input type="date" name="date_from" class="filter-select" style="width:160px;"
                               value="<?= e($date_from) ?>">
                        <label style="font-size:13px;font-weight:600;color:var(--c-text-3);">to:</label>
                        <input type="date" name="date_to"   class="filter-select" style="width:160px;"
                               value="<?= e($date_to) ?>" id="browseDateTo">
                        <?php if ($date_from || $date_to || $search || $category || $difficulty || $instructor_id): ?>
                            <a href="browse-tutorials.php" class="btn btn-outline btn-sm">
                                <i class="fas fa-times"></i> Clear All
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($date_filter_error)): ?>
                            <span style="color:var(--c-danger);font-size:13px;"><?= e($date_filter_error) ?></span>
                        <?php endif; ?>
                        <span id="browse-date-error" style="color:var(--c-danger);font-size:13px;display:none;"></span>
                    </div>
                </form>
            </div>

            <!-- result count and clear filters link -->
            <div class="results-info">
                <?php if (!empty($search) || $category > 0 || !empty($difficulty)): ?>
                    <strong><?= number_format($total_results) ?></strong> tutorial<?= $total_results !== 1 ? 's' : '' ?> found
                    &nbsp;<a href="browse-tutorials.php">Clear Filters</a>
                <?php else: ?>
                    Showing <strong><?= number_format($total_results) ?></strong> tutorial<?= $total_results !== 1 ? 's' : '' ?>
                <?php endif; ?>
            </div>

            <!-- tutorials grid or empty state -->
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
                    <div class="tutorial-card">

                        <!-- thumbnail with badge and favorite -->
                        <div class="card-thumbnail">
                            <?php if (!empty($tutorial['thumbnail'])): ?>
                                <img src="<?= SITE_URL ?>/uploads/<?= e($tutorial['thumbnail']) ?>"
                                     alt="<?= e($tutorial['title']) ?>"
                                     onerror="this.onerror=null;this.src='<?= SITE_URL ?>/uploads/placeholder.svg'">
                            <?php else: ?>
                                <i class="fas fa-book thumb-icon"></i>
                            <?php endif; ?>

                            <span class="difficulty-badge difficulty-<?= e($tutorial['difficulty']) ?>">
                                <?= ucfirst($tutorial['difficulty']) ?>
                            </span>

                            <!-- heart filled when already favorited -->
                            <?php $already_fav = in_array($tutorial['tutorial_id'], $user_favorites); ?>
                            <button class="favorite-btn <?= $already_fav ? 'active' : '' ?>"
                                    onclick="toggleFavorite(<?= $tutorial['tutorial_id'] ?>, this)"
                                    title="<?= $already_fav ? 'Remove from favorites' : 'Add to favorites' ?>">
                                <i class="<?= $already_fav ? 'fas' : 'far' ?> fa-heart"
                                   style="color:<?= $already_fav ? '#ef4444' : 'inherit' ?>;"></i>
                            </button>
                        </div>

                        <!-- card body -->
                        <div class="card-body">
                            <div class="category-tag">
                                <i class="fas fa-folder" aria-hidden="true"></i>
                                <?= e($tutorial['category_name']) ?>
                            </div>

                            <h3>
                                <?= e($tutorial['title']) ?>
                                <?php if (!empty($tutorial['has_media']) || !empty($tutorial['video_url'])): ?>
                                    <span class="media-badge" title="Includes media"><i class="fas fa-paperclip"></i></span>
                                <?php endif; ?>
                            </h3>
                            <p class="description"><?= e($tutorial['short_description']) ?></p>

                            <!-- instructor with avatar -->
                            <div class="instructor">
                                <?php if (!empty($tutorial['instructor_avatar'])): ?>
                                    <img src="<?= SITE_URL ?>/uploads/<?= e($tutorial['instructor_avatar']) ?>"
                                         alt="<?= e($tutorial['instructor_name']) ?>"
                                         style="width:22px;height:22px;border-radius:50%;object-fit:cover;"
                                         onerror="this.onerror=null;this.style.display='none'">
                                <?php else: ?>
                                    <i class="fas fa-user-circle" style="font-size:20px;color:#cbd5e0;"></i>
                                <?php endif; ?>
                                <span><?= e($tutorial['instructor_name']) ?></span>
                            </div>

                            <!-- rating views duration -->
                            <div class="card-meta">
                                <span>
                                    <i class="fas fa-star" style="color:#d69e2e;"></i>
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
                        </div>

                        <!-- card footer -->
                        <div class="card-footer">
                            <a href="<?= SITE_URL ?>/viewer/tutorial-view.php?slug=<?= urlencode($tutorial['slug']) ?>"
                               class="btn btn-primary" style="flex:1;justify-content:center;">
                                <i class="fas fa-play-circle" aria-hidden="true"></i> View Tutorial
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- pagination -->
                <?php if (!empty($pagination['total_pages']) && $pagination['total_pages'] > 1): ?>
                <?php
                // build pagination base url with active filters
                $base_url = '?' . http_build_query(array_filter([
                    'search'     => $search,
                    'category'   => $category ?: '',
                    'difficulty' => $difficulty,
                    'sort'       => $sort,
                ]));
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
    // ajax toggle favorite
    function toggleFavorite(tutorialId, btn) {
        fetch('<?= SITE_URL ?>/api/toggle-favorite.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tutorial_id: tutorialId,
                csrf_token: document.body.dataset.csrf
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
                // guest redirect to login
                window.location.href = data.redirect;
            } else {
                alert(data.message || 'Could not update favorite.');
            }
        })
        .catch(() => alert('Network error. Please try again.'));
    }
    </script>
<script src="<?= asset('js/viewer.js') ?>"></script>
<script>
(function () {
    var form  = document.getElementById('browseFilterForm');
    var dfrom = form ? form.querySelector('[name="date_from"]') : null;
    var dto   = document.getElementById('browseDateTo');
    var errEl = document.getElementById('browse-date-error');

    function validateDates() {
        if (!dfrom || !dto || !dfrom.value || !dto.value) {
            if (errEl) errEl.style.display = 'none';
            if (dto) dto.style.borderColor = '';
            return true;
        }
        if (dto.value < dfrom.value) {
            if (errEl) { errEl.textContent = '"To" date must be on or after "From" date'; errEl.style.display = 'inline'; }
            dto.style.borderColor = 'var(--c-danger)';
            return false;
        }
        if (errEl) errEl.style.display = 'none';
        if (dto) dto.style.borderColor = '';
        return true;
    }

    if (dfrom) dfrom.addEventListener('change', validateDates);
    if (dto)   dto.addEventListener('change', validateDates);
    if (form)  form.addEventListener('submit', function(e) { if (!validateDates()) e.preventDefault(); });
}());
</script>
</body>
</html>
