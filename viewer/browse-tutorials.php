<?php
/**
 * Browse Tutorials Page
 * search and filter through all available tutorials
 * Hasan Fardan - 202301686
 */

// First, let's make sure the user is actually logged in and allowed to be here
require_once '../includes/viewer-auth-check.php';

// Pull in our Tutorial and Category classes so we can talk to the database
require_once '../classes/Tutorial.php';
require_once '../classes/Category.php';

// Set the page title — this shows up in the browser tab
$page_title = 'Browse Tutorials';

// === GRAB ALL THE FILTERS FROM THE URL ===
// Check if the user typed anything in the search box
$search = isset($_GET['search']) ? clean($_GET['search']) : '';

// See if they picked a specific category from the dropdown
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Check if they selected a difficulty level (beginner, intermediate, advanced)
$difficulty = isset($_GET['difficulty']) ? clean($_GET['difficulty']) : '';

// Figure out how they want to sort the results (default is newest first)
$sort = isset($_GET['sort']) ? clean($_GET['sort']) : 'newest';

// Get the current page number for pagination (default to page 1, never go below 1)
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// === LOAD THE CATEGORY DROPDOWN OPTIONS ===
// Create a Category object and fetch all categories so the user can filter by them
$categoryObj = new Category();
$categories = $categoryObj->getAll();

// === BUILD OUR FILTER ARRAY FOR THE DATABASE QUERY ===
// Start with an empty array and only add filters if the user actually set them
$filters = [];
if (!empty($search)) $filters['search'] = $search;           // they searched for something
if (!empty($category)) $filters['category_id'] = $category;  // they picked a category
if (!empty($difficulty)) $filters['difficulty'] = $difficulty; // they picked a difficulty
if (!empty($sort)) $filters['sort'] = $sort;                   // they chose a sort order

// === FETCH THE TUTORIALS ===
// If no filters are applied and we're sorting by newest, use the simple getPublished method
// Otherwise, use the search method to apply all the filters
$tutorial = new Tutorial();
$result = empty($filters) && $sort === 'newest' 
    ? $tutorial->getPublished($page, 12)   // simple fetch, 12 per page
    : $tutorial->search($filters, $page, 12);  // filtered search, 12 per page

// Pull out the results so we can use them in the HTML below
$tutorials = $result['tutorials'];
$pagination = $result['pagination'];
$total_results = $pagination['total_items'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - <?= SITE_NAME ?></title>
    
    <!-- Our custom viewer styles -->
    <link rel="stylesheet" href="<?= asset('css/viewer.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/viewer.css') ?>">
    
    <!-- Font Awesome for all those nice icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Top navigation bar -->
    <?php include '../includes/viewer-nav.php'; ?>
    
    <div class="viewer-container">
        <!-- Side menu with links -->
        <?php include '../includes/viewer-sidebar.php'; ?>
        
        <!-- Main content area -->
        <main class="viewer-main">
            <!-- Page header with title and description -->
            <div class="browse-header">
                <div>
                    <h1><i class="fas fa-th"></i> Browse Tutorials</h1>
                    <p>Discover thousands of tutorials to master new skills</p>
                </div>
            </div>
            
            <!-- === SEARCH AND FILTERS BAR === -->
            <div class="browse-filters">
                <form method="GET" action="" class="filters-form">
                    <!-- Big search input -->
                    <div class="search-box-large">
                        <i class="fas fa-search"></i>
                        <input 
                            type="text" 
                            name="search" 
                            placeholder="Search for tutorials..." 
                            value="<?= e($search) ?>"
                        >
                    </div>
                    
                    <!-- Category dropdown -->
                    <div class="filter-group">
                        <select name="category" class="filter-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['category_id'] ?>" <?= $category == $cat['category_id'] ? 'selected' : '' ?>>
                                    <?= e($cat['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Difficulty level dropdown -->
                    <div class="filter-group">
                        <select name="difficulty" class="filter-select">
                            <option value="">All Levels</option>
                            <option value="beginner" <?= $difficulty === 'beginner' ? 'selected' : '' ?>>Beginner</option>
                            <option value="intermediate" <?= $difficulty === 'intermediate' ? 'selected' : '' ?>>Intermediate</option>
                            <option value="advanced" <?= $difficulty === 'advanced' ? 'selected' : '' ?>>Advanced</option>
                        </select>
                    </div>
                    
                    <!-- Sort order dropdown -->
                    <div class="filter-group">
                        <select name="sort" class="filter-select">
                            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                            <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most Popular</option>
                            <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Highest Rated</option>
                            <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>Title A-Z</option>
                        </select>
                    </div>
                    
                    <!-- Apply button -->
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i>
                        Apply Filters
                    </button>
                </form>
            </div>
            
            <!-- === SHOW HOW MANY RESULTS WE FOUND === -->
            <div class="results-info">
                <p>
                    <?php if ($total_results > 0): ?>
                        Showing <strong><?= $total_results ?></strong> tutorial<?= $total_results !== 1 ? 's' : '' ?>
                    <?php else: ?>
                        No tutorials found
                    <?php endif; ?>
                </p>
            </div>
            
            <!-- === TUTORIALS GRID === -->
            <?php if (empty($tutorials)): ?>
                <!-- Nothing found — show a friendly empty state -->
                <div class="empty-state-large">
                    <i class="fas fa-search"></i>
                    <h2>No tutorials found</h2>
                    <p>Try adjusting your filters or search terms</p>
                    <a href="browse-tutorials.php" class="btn btn-primary">
                        <i class="fas fa-redo"></i>
                        Clear Filters
                    </a>
                </div>
            <?php else: ?>
                <!-- We got results! Loop through and display each tutorial card -->
                <div class="tutorials-grid-large">
                    <?php foreach ($tutorials as $tutorial): ?>
                    <div class="tutorial-card-browse">
                        <div class="card-image">
                            <!-- Show the thumbnail if we have one, otherwise show a placeholder book icon -->
                            <?php if (!empty($tutorial['thumbnail'])): ?>
                                <img src="<?= SITE_URL ?>/uploads/<?= e($tutorial['thumbnail']) ?>" alt="<?= e($tutorial['title']) ?>">
                            <?php else: ?>
                                <div style="width:100%;height:200px;background:#e8ecf1;display:flex;align-items:center;justify-content:center;">
                                    <i class="fas fa-book" style="font-size:48px;color:#aaa;"></i>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Difficulty badge (Beginner / Intermediate / Advanced) -->
                            <span class="difficulty-badge difficulty-<?= $tutorial['difficulty'] ?>">
                                <?= ucfirst($tutorial['difficulty']) ?>
                            </span>
                            
                            <!-- Heart button to favorite this tutorial -->
                            <button class="favorite-btn" onclick="toggleFavorite(<?= $tutorial['tutorial_id'] ?>)">
                                <i class="far fa-heart"></i>
                            </button>
                        </div>
                        
                        <div class="card-content">
                            <!-- Category tag -->
                            <div class="card-tags">
                                <span class="tag">
                                    <i class="fas fa-folder"></i>
                                    <?= e($tutorial['category_name']) ?>
                                </span>
                            </div>
                            
                            <!-- Tutorial title and short description -->
                            <h3><?= e($tutorial['title']) ?></h3>
                            <p class="description"><?= truncate($tutorial['short_description'], 120) ?></p>
                            
                            <!-- Instructor info: avatar + name -->
                            <div class="instructor-info">
                                <?php if (!empty($tutorial['instructor_avatar'])): ?>
                                    <img src="<?= SITE_URL ?>/uploads/<?= e($tutorial['instructor_avatar']) ?>" alt="Instructor"
                                         onerror="this.style.display='none'">
                                <?php else: ?>
                                    <i class="fas fa-user-circle" style="font-size:26px;color:#cbd5e0;"></i>
                                <?php endif; ?>
                                <span><?= e($tutorial['instructor_name']) ?></span>
                            </div>
                            
                            <!-- Stats row: rating, views, duration -->
                            <div class="card-stats">
                                <span class="rating">
                                    <i class="fas fa-star"></i>
                                    <?= number_format($tutorial['avg_rating'], 1) ?>
                                    <small>(<?= $tutorial['rating_count'] ?>)</small>
                                </span>
                                <span><i class="fas fa-eye"></i> <?= number_format($tutorial['view_count']) ?></span>
                                <span><i class="fas fa-clock"></i> <?= $tutorial['duration_minutes'] ?> min</span>
                            </div>
                            
                            <!-- Big "View Tutorial" button -->
                            <a href="tutorial-view.php?slug=<?= urlencode($tutorial['slug']) ?>" class="btn btn-block btn-primary">
                                View Tutorial <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- === PAGINATION === -->
                <!-- Only show pagination if there's more than one page -->
                <?php if ($pagination['total_pages'] > 1): ?>
                <div class="pagination">
                    <?php
                    // Build the base URL with all current filters so pagination keeps them
                    $base_url = '?search=' . urlencode($search)
                        . '&category=' . $category
                        . '&difficulty=' . urlencode($difficulty)
                        . '&sort=' . urlencode($sort);
                    ?>
                    
                    <!-- Previous page button (disabled if we're on page 1) -->
                    <?php if ($page > 1): ?>
                        <a href="<?= $base_url ?>&page=<?= $page - 1 ?>" class="btn btn-outline">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php else: ?>
                        <button class="btn btn-outline" disabled>
                            <i class="fas fa-chevron-left"></i> Previous
                        </button>
                    <?php endif; ?>

                    <!-- Page counter -->
                    <span class="page-info">Page <?= $page ?> of <?= $pagination['total_pages'] ?></span>

                    <!-- Next page button (disabled if we're on the last page) -->
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
        // Toggle favorite status for a tutorial
        // NOTE: This is just a placeholder for now — will hook it up to AJAX later
        function toggleFavorite(tutorialId) {
            alert('Favorite feature will be implemented with Tutorial class');
        }
    </script>
</body>
</html>