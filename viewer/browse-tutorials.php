<?php
/**
 * Browse Tutorials Page
 * search and filter through all available tutorials
 * Hasan Fardan - 202301686
 */

require_once '../includes/viewer-auth-check.php';
require_once '../classes/Tutorial.php';
require_once '../classes/Category.php';

$page_title = 'Browse Tutorials';

// Get filters
$search = isset($_GET['search']) ? clean($_GET['search']) : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$difficulty = isset($_GET['difficulty']) ? clean($_GET['difficulty']) : '';
$sort = isset($_GET['sort']) ? clean($_GET['sort']) : 'newest';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Get categories for filter
$categoryObj = new Category();
$categories = $categoryObj->getAll();

// Build filters array
$filters = [];
if (!empty($search)) $filters['search'] = $search;
if (!empty($category)) $filters['category_id'] = $category;
if (!empty($difficulty)) $filters['difficulty'] = $difficulty;
if (!empty($sort)) $filters['sort'] = $sort;

// Get tutorials from database
$tutorial = new Tutorial();
$result = empty($filters) && $sort === 'newest' 
    ? $tutorial->getPublished($page, 12) 
    : $tutorial->search($filters, $page, 12);

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
    <link rel="stylesheet" href="<?= asset('css/viewer.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/viewer-nav.php'; ?>
    
    <div class="viewer-container">
        <?php include '../includes/viewer-sidebar.php'; ?>
        
        <main class="viewer-main">
            <div class="browse-header">
                <div>
                    <h1><i class="fas fa-th"></i> Browse Tutorials</h1>
                    <p>Discover thousands of tutorials to master new skills</p>
                </div>
            </div>
            
            <!-- search and filters bar -->
            <div class="browse-filters">
                <form method="GET" action="" class="filters-form">
                    <div class="search-box-large">
                        <i class="fas fa-search"></i>
                        <input 
                            type="text" 
                            name="search" 
                            placeholder="Search for tutorials..." 
                            value="<?= e($search) ?>"
                        >
                    </div>
                    
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
                    
                    <div class="filter-group">
                        <select name="difficulty" class="filter-select">
                            <option value="">All Levels</option>
                            <option value="beginner" <?= $difficulty === 'beginner' ? 'selected' : '' ?>>Beginner</option>
                            <option value="intermediate" <?= $difficulty === 'intermediate' ? 'selected' : '' ?>>Intermediate</option>
                            <option value="advanced" <?= $difficulty === 'advanced' ? 'selected' : '' ?>>Advanced</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select name="sort" class="filter-select">
                            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                            <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most Popular</option>
                            <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Highest Rated</option>
                            <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>Title A-Z</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i>
                        Apply Filters
                    </button>
                </form>
            </div>
            
            <!-- results info -->
            <div class="results-info">
                <p>
                    <?php if ($total_results > 0): ?>
                        Showing <strong><?= $total_results ?></strong> tutorial<?= $total_results !== 1 ? 's' : '' ?>
                    <?php else: ?>
                        No tutorials found
                    <?php endif; ?>
                </p>
            </div>
            
            <!-- tutorials grid -->
            <?php if (empty($tutorials)): ?>
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
                <div class="tutorials-grid-large">
                    <?php foreach ($tutorials as $tutorial): ?>
                    <div class="tutorial-card-browse">
                        <div class="card-image">
                            <img src="<?= $tutorial['thumbnail'] ?>" alt="<?= e($tutorial['title']) ?>">
                            <span class="difficulty-badge difficulty-<?= $tutorial['difficulty'] ?>">
                                <?= ucfirst($tutorial['difficulty']) ?>
                            </span>
                            <button class="favorite-btn" onclick="toggleFavorite(<?= $tutorial['tutorial_id'] ?>)">
                                <i class="far fa-heart"></i>
                            </button>
                        </div>
                        
                        <div class="card-content">
                            <div class="card-tags">
                                <span class="tag">
                                    <i class="fas fa-folder"></i>
                                    <?= e($tutorial['category_name']) ?>
                                </span>
                            </div>
                            
                            <h3><?= e($tutorial['title']) ?></h3>
                            <p class="description"><?= truncate($tutorial['short_description'], 120) ?></p>
                            
                            <div class="instructor-info">
                                <img src="<?= $tutorial['instructor_avatar'] ?? asset('images/default-avatar.png') ?>" alt="Instructor">
                                <span><?= e($tutorial['instructor_name']) ?></span>
                            </div>
                            
                            <div class="card-stats">
                                <span class="rating">
                                    <i class="fas fa-star"></i>
                                    <?= number_format($tutorial['avg_rating'], 1) ?>
                                    <small>(<?= $tutorial['rating_count'] ?>)</small>
                                </span>
                                <span><i class="fas fa-eye"></i> <?= number_format($tutorial['view_count']) ?></span>
                                <span><i class="fas fa-clock"></i> <?= $tutorial['duration_minutes'] ?> min</span>
                            </div>
                            
                            <a href="tutorial-view.php?slug=<?= urlencode($tutorial['slug']) ?>" class="btn btn-block btn-primary">
                                View Tutorial <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                <div class="pagination">
                    <?php
                    $base_url = '?search=' . urlencode($search)
                        . '&category=' . $category
                        . '&difficulty=' . urlencode($difficulty)
                        . '&sort=' . urlencode($sort);
                    ?>
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
        function toggleFavorite(tutorialId) {
            // will implement with ajax later
            alert('Favorite feature will be implemented with Tutorial class');
        }
    </script>
</body>
</html>