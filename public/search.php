<?php
// public search open to guests and logged-in users

require_once '../config/config.php';
require_once '../classes/Tutorial.php';
require_once '../classes/Category.php';

// read filter params from url
$search_query  = isset($_GET['q'])          ? clean($_GET['q'])          : '';
$category_id   = isset($_GET['category'])   ? (int)$_GET['category']    : 0;
$difficulty    = isset($_GET['difficulty']) ? clean($_GET['difficulty']) : '';
$sort          = isset($_GET['sort'])       ? clean($_GET['sort'])       : 'newest';
$date_from     = isset($_GET['date_from'])  ? clean($_GET['date_from'])  : '';
$date_to       = isset($_GET['date_to'])    ? clean($_GET['date_to'])    : '';
$instructor_id = isset($_GET['instructor']) ? (int)$_GET['instructor']   : 0;
$page          = isset($_GET['page'])       ? max(1, (int)$_GET['page']) : 1;

// validate date inputs reject non-ymd values
$date_filter_error = '';
if ($date_from !== '') {
    $date_from_parsed = DateTime::createFromFormat('Y-m-d', $date_from);
    if (!$date_from_parsed || $date_from_parsed->format('Y-m-d') !== $date_from) {
        $date_from = '';
        $date_filter_error = 'Invalid "Published from" date — it has been ignored.';
    }
}
if ($date_to !== '') {
    $date_to_parsed = DateTime::createFromFormat('Y-m-d', $date_to);
    if (!$date_to_parsed || $date_to_parsed->format('Y-m-d') !== $date_to) {
        $date_to = '';
        $date_filter_error = 'Invalid "Date to" date — it has been ignored.';
    }
}
if ($date_from !== '' && $date_to !== '' && $date_from > $date_to) {
    $date_to = '';
    $date_filter_error = '"Date to" must be on or after "Published from" — it has been ignored.';
}

// page title based on active search
$page_title = $search_query ? 'Search Results' : 'Browse Tutorials';

// categories for filter dropdown
$categoryObj = new Category();
$categories  = $categoryObj->getAll();

// db connection
$db   = new Database();
$conn = $db->connect();
$instructors = [];
if ($conn) {
    // active creators for instructor dropdown
    $iStmt = $conn->prepare(
        "SELECT user_id, full_name FROM dbProj_users
         WHERE role = 'creator' AND status = 'active'
         ORDER BY full_name ASC"
    );
    $iStmt->execute();
    $instructors = $iStmt->fetchAll(PDO::FETCH_ASSOC);
}

// build filter params from provided values only
$tutorial = new Tutorial();
$filters  = ['sort' => $sort];
if (!empty($search_query)) $filters['search']       = $search_query;
if ($category_id > 0)      $filters['category_id']  = $category_id;
if (!empty($difficulty))   $filters['difficulty']   = $difficulty;
if ($instructor_id > 0)    $filters['instructor_id'] = $instructor_id;
if (!empty($date_from))    $filters['date_from']    = $date_from;
if (!empty($date_to))      $filters['date_to']      = $date_to;

// run search get tutorials and pagination
$search_results = $tutorial->search($filters, $page, 12);
$tutorials      = $search_results['tutorials'] ?? [];
$pagination     = $search_results['pagination'] ?? [
    'total_items'  => 0,
    'total_pages'  => 0,
    'current_page' => 1,
    'items_per_page' => 12,
];

// build pagination query string from active filters
$paginationParams = http_build_query(array_filter([
    'q'          => $search_query,
    'category'   => $category_id ?: '',
    'difficulty' => $difficulty,
    'sort'       => $sort,
    'instructor' => $instructor_id ?: '',
    'date_from'  => $date_from,
    'date_to'    => $date_to,
]));

// hide hero when filters active
$hasActiveFilters = $search_query || $category_id || $difficulty || $instructor_id || $date_from || $date_to;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> | <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= asset('css/shared.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/search.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- role-aware top nav -->
    <div class="site-topbar">
        <div class="navbar-brand">
            <a href="<?= SITE_URL ?>">
                <i class="fas fa-graduation-cap" aria-hidden="true"></i>
                <span><?= SITE_NAME ?></span>
            </a>
        </div>
        <div class="nav-links">
            <?php if (isLoggedIn()):
                // role-based nav links
                $__role = getCurrentUserRole();
                if ($__role === 'admin'): ?>
                    <a href="<?= SITE_URL ?>/admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Admin Panel</a>
                <?php elseif ($__role === 'creator'): ?>
                    <a href="<?= SITE_URL ?>/creator/dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="<?= SITE_URL ?>/creator/create-tutorial.php"><i class="fas fa-plus"></i> Create</a>
                <?php else: ?>
                    <a href="<?= SITE_URL ?>/viewer/dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="<?= SITE_URL ?>/viewer/browse-tutorials.php"><i class="fas fa-th-large"></i> Browse</a>
                    <a href="<?= SITE_URL ?>/viewer/my-learning.php"><i class="fas fa-book-open"></i> My Learning</a>
                <?php endif; ?>
                <a href="<?= SITE_URL ?>/auth/logout.php" class="nav-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            <?php else: ?>
                <!-- guest login and register -->
                <a href="<?= SITE_URL ?>/auth/login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
                <a href="<?= SITE_URL ?>/auth/register.php" class="nav-btn"><i class="fas fa-user-plus"></i> Register</a>
            <?php endif; ?>

            <!-- category dropdown -->
            <?php if (!empty($categories)): ?>
            <div class="nav-dropdown-wrapper" style="position:relative;display:inline-block;">
                <button class="nav-cat-toggle"
                        onclick="toggleSearchCatMenu(event)"
                        aria-expanded="false"
                        aria-haspopup="true"
                        type="button"
                        style="background:none;border:1px solid currentColor;border-radius:4px;padding:6px 10px;cursor:pointer;color:inherit;font-size:inherit;">
                    <i class="fas fa-th-list" aria-hidden="true"></i> Categories
                    <i class="fas fa-chevron-down" aria-hidden="true" style="font-size:10px;opacity:.6;margin-left:4px;"></i>
                </button>
                <ul id="searchCatDropdown"
                    style="display:none;position:absolute;right:0;top:110%;min-width:200px;background:#fff;border:1px solid #ddd;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,.12);list-style:none;margin:0;padding:6px 0;z-index:9999;">
                    <?php foreach ($categories as $cat): ?>
                    <li>
                        <a href="<?= SITE_URL ?>/public/search.php?category=<?= (int)$cat['category_id'] ?>"
                           style="display:block;padding:8px 16px;color:#333;text-decoration:none;white-space:nowrap;"
                           onmouseover="this.style.background='#f5f5f5'" onmouseout="this.style.background=''">
                            <i class="fas fa-tag" aria-hidden="true"></i> <?= e($cat['category_name']) ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function toggleSearchCatMenu(e) {
        e.stopPropagation();
        var menu = document.getElementById('searchCatDropdown');
        if (!menu) return;
        var nowVisible = menu.style.display !== 'none';
        menu.style.display = nowVisible ? 'none' : 'block';
        e.currentTarget.setAttribute('aria-expanded', String(!nowVisible));
    }
    document.addEventListener('click', function() {
        var menu = document.getElementById('searchCatDropdown');
        if (menu) menu.style.display = 'none';
    });
    </script>

    <?php if (!$hasActiveFilters && $page === 1): ?>
    <!-- hero shown when no filters and page 1 -->
    <div class="site-hero">
        <h1><i class="fas fa-graduation-cap"></i> TechKnowledge Hub</h1>
        <p>Discover expert tutorials on Web Development, Databases, Programming &amp; more</p>
        <form class="hero-search" method="GET" action="">
            <input type="text" name="q" placeholder="What do you want to learn today?">
            <button type="submit"><i class="fas fa-search"></i> Search</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="search-page">
        <div class="container">

            <!-- dynamic heading -->
            <div class="search-header">
                <h1>
                    <?php if ($search_query): ?>
                        Search results for "<?= e($search_query) ?>"
                    <?php elseif ($hasActiveFilters): ?>
                        Filtered Tutorials
                    <?php else: ?>
                        All Tutorials, Newest First
                    <?php endif; ?>
                </h1>
                <p><?= $pagination['total_items'] ?> tutorial<?= $pagination['total_items'] !== 1 ? 's' : '' ?> found</p>
            </div>

            <!-- filter form -->
            <div class="search-filters">
                <form method="GET" action="" class="filter-form" id="searchFilterForm">

                    <!-- text search -->
                    <div class="filter-row">
                        <div class="search-input-wrapper">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <label for="search-input" class="sr-only">Search tutorials</label>
                            <input
                                type="text"
                                id="search-input"
                                name="q"
                                placeholder="Search by title or keyword..."
                                value="<?= e($search_query) ?>"
                                class="search-input"
                            >
                        </div>
                        <button type="submit" class="btn btn-primary" id="searchSubmitBtn">
                            <i class="fas fa-search" aria-hidden="true"></i> Search
                        </button>
                    </div>

                    <!-- filter dropdowns row -->
                    <div class="filter-row filter-row-selects">
                        <div class="filter-group">
                            <label for="filter-category" class="filter-label">Category</label>
                            <select id="filter-category" name="category" class="filter-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>" <?= $category_id == $cat['category_id'] ? 'selected' : '' ?>>
                                        <?= e($cat['category_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="filter-instructor" class="filter-label">Instructor</label>
                            <select id="filter-instructor" name="instructor" class="filter-select">
                                <option value="">All Instructors</option>
                                <?php foreach ($instructors as $ins): ?>
                                    <option value="<?= $ins['user_id'] ?>" <?= $instructor_id == $ins['user_id'] ? 'selected' : '' ?>>
                                        <?= e($ins['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="filter-difficulty" class="filter-label">Level</label>
                            <select id="filter-difficulty" name="difficulty" class="filter-select">
                                <option value="">All Levels</option>
                                <option value="beginner"     <?= $difficulty === 'beginner'     ? 'selected' : '' ?>>Beginner</option>
                                <option value="intermediate" <?= $difficulty === 'intermediate' ? 'selected' : '' ?>>Intermediate</option>
                                <option value="advanced"     <?= $difficulty === 'advanced'     ? 'selected' : '' ?>>Advanced</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="filter-sort" class="filter-label">Sort by</label>
                            <select id="filter-sort" name="sort" class="filter-select">
                                <option value="newest"   <?= $sort === 'newest'   ? 'selected' : '' ?>>Newest First</option>
                                <option value="popular"  <?= $sort === 'popular'  ? 'selected' : '' ?>>Most Popular</option>
                                <option value="rating"   <?= $sort === 'rating'   ? 'selected' : '' ?>>Highest Rated</option>
                                <option value="relevant" <?= $sort === 'relevant' ? 'selected' : '' ?>>Most Relevant</option>
                            </select>
                        </div>
                    </div>

                    <!-- date range filter -->
                    <div class="filter-row filter-row-dates">
                        <label for="date_from" class="date-label">
                            <i class="fas fa-calendar-alt" aria-hidden="true"></i> Published from:
                        </label>
                        <input type="date" id="date_from" name="date_from" class="filter-date" value="<?= e($date_from) ?>">
                        <label for="date_to" class="date-label">to:</label>
                        <input type="date" id="date_to"   name="date_to"   class="filter-date" value="<?= e($date_to) ?>">
                        <button type="submit" class="btn btn-outline btn-sm">
                            <i class="fas fa-filter" aria-hidden="true"></i> Apply Dates
                        </button>
                        <!-- clear all when filters active -->
                        <?php if ($hasActiveFilters): ?>
                            <a href="search.php" class="btn btn-outline btn-sm">
                                <i class="fas fa-times" aria-hidden="true"></i> Clear All
                            </a>
                        <?php endif; ?>
                        <!-- server-side date range error -->
                        <?php if (!empty($date_filter_error)): ?>
                            <span class="date-range-error" role="alert"><?= e($date_filter_error) ?></span>
                        <?php endif; ?>
                        <span id="date-range-error" class="date-range-error" role="alert"></span>
                    </div>

                </form>
            </div>

            <!-- results -->
            <?php if (empty($tutorials)): ?>
                <!-- empty results state -->
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h2>No tutorials found</h2>
                    <p>Try adjusting your search or filters</p>
                    <a href="search.php" class="btn btn-primary">Clear Filters</a>
                </div>
            <?php else: ?>
                <!-- tutorials grid -->
                <div class="tutorials-grid">
                    <?php foreach ($tutorials as $tut): ?>
                        <!-- tutorial card -->
                        <div class="tutorial-card">
                            <div class="card-image">
                                <?php if (!empty($tut['thumbnail'])): ?>
                                    <img src="<?= SITE_URL ?>/uploads/<?= e($tut['thumbnail']) ?>" alt="<?= e($tut['title']) ?>" onerror="this.onerror=null;this.src='<?= SITE_URL ?>/uploads/placeholder.svg'">
                                <?php else: ?>
                                    <!-- no thumbnail fallback -->
                                    <div class="card-image-placeholder">
                                        <i class="fas fa-book" aria-hidden="true"></i>
                                    </div>
                                <?php endif; ?>
                                <span class="difficulty-badge difficulty-<?= $tut['difficulty'] ?>">
                                    <?= ucfirst($tut['difficulty']) ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <span class="category-tag"><?= e($tut['category_name']) ?></span>
                                <h3>
                                    <?= e($tut['title']) ?>
                                    <?php if (!empty($tut['has_media']) || !empty($tut['video_url'])): ?>
                                        <span class="media-indicator" title="Has media attachments"><i class="fas fa-paperclip"></i></span>
                                    <?php endif; ?>
                                </h3>
                                <p><?= e(truncate($tut['short_description'], 120)) ?></p>

                                <!-- meta row -->
                                <div class="card-meta">
                                    <span><i class="fas fa-user"></i> <?= e($tut['instructor_name']) ?></span>
                                    <span><i class="fas fa-star"></i> <?= number_format((float)($tut['avg_rating'] ?? 0), 1) ?></span>
                                    <span><i class="fas fa-eye"></i> <?= number_format($tut['view_count']) ?></span>
                                </div>

                                <a href="<?= SITE_URL ?>/viewer/tutorial-view.php?slug=<?= urlencode($tut['slug']) ?>" class="btn btn-block">
                                    View More &rarr;
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- pagination -->
                <?php if ($pagination['total_pages'] > 1):
                    $total = $pagination['total_pages'];
                    $start = max(1, $page - 2);
                    $end   = min($total, $page + 2);
                ?>
                    <nav class="pagination" aria-label="Tutorial pages">
                        <!-- prev page -->
                        <?php if ($page > 1): ?>
                            <a href="?<?= $paginationParams ?>&page=<?= $page - 1 ?>"
                               class="pagination-btn" aria-label="Previous page">
                                <i class="fas fa-chevron-left" aria-hidden="true"></i>
                            </a>
                        <?php endif; ?>

                        <!-- first page with ellipsis -->
                        <?php if ($start > 1): ?>
                            <a href="?<?= $paginationParams ?>&page=1" class="pagination-btn">1</a>
                            <?php if ($start > 2): ?>
                                <span class="pagination-ellipsis">&hellip;</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <!-- numbered page links -->
                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <a href="?<?= $paginationParams ?>&page=<?= $i ?>"
                               class="pagination-btn<?= $i === $page ? ' active' : '' ?>"
                               <?= $i === $page ? 'aria-current="page"' : '' ?>>
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <!-- last page with ellipsis -->
                        <?php if ($end < $total): ?>
                            <?php if ($end < $total - 1): ?>
                                <span class="pagination-ellipsis">&hellip;</span>
                            <?php endif; ?>
                            <a href="?<?= $paginationParams ?>&page=<?= $total ?>"
                               class="pagination-btn"><?= $total ?></a>
                        <?php endif; ?>

                        <!-- next page -->
                        <?php if ($page < $total): ?>
                            <a href="?<?= $paginationParams ?>&page=<?= $page + 1 ?>"
                               class="pagination-btn" aria-label="Next page">
                                <i class="fas fa-chevron-right" aria-hidden="true"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

<script>
(function () {
    // grab form and date fields for validation
    var form   = document.getElementById('searchFilterForm');
    var dfrom  = document.getElementById('date_from');
    var dto    = document.getElementById('date_to');
    var errEl  = document.getElementById('date-range-error');
    var subBtn = document.getElementById('searchSubmitBtn');

    function validateDates() {
        // skip if either field empty
        if (!dfrom || !dto || !dfrom.value || !dto.value) {
            if (errEl) errEl.style.display = 'none';
            if (dto) dto.style.borderColor = '';
            return true;
        }
        if (dto.value < dfrom.value) {
            // highlight invalid to-date
            if (errEl) {
                errEl.textContent = '"Date to" must be on or after "Published from"';
                errEl.style.display = 'block';
            }
            dto.style.borderColor = 'var(--c-danger)';
            return false;
        }
        // clear previous error
        if (errEl) errEl.style.display = 'none';
        if (dto) dto.style.borderColor = '';
        return true;
    }

    // revalidate on field change
    if (dfrom) dfrom.addEventListener('change', validateDates);
    if (dto)   dto.addEventListener('change', validateDates);

    if (form) {
        form.addEventListener('submit', function (e) {
            if (!validateDates()) { e.preventDefault(); return; }
            // spinner while searching
            if (subBtn) {
                subBtn.disabled = true;
                subBtn.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Searching…';
            }
        });
    }
}());
</script>
</body>
</html>
