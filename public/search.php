<?php
/**
 * Search Results Page
 * Full-text search with filters
 * Role 3 Deliverable
 */

require_once '../config/config.php';
require_once '../classes/Tutorial.php';
require_once '../classes/Category.php';

$page_title = 'Search Results';

// Get search parameters
$search_query = isset($_GET['q']) ? clean($_GET['q']) : '';
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$difficulty = isset($_GET['difficulty']) ? clean($_GET['difficulty']) : '';
$sort = isset($_GET['sort']) ? clean($_GET['sort']) : 'relevant';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Get categories for filter
$categoryObj = new Category();
$categories = $categoryObj->getAll();

// Perform search
$tutorial = new Tutorial();
$filters = [
    'search' => $search_query,
    'category_id' => $category_id,
    'difficulty' => $difficulty,
    'sort' => $sort
];

$search_results = $tutorial->search($filters, $page, 12);
$tutorials = $search_results['tutorials'];
$pagination = $search_results['pagination'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= asset('css/search.css') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="search-page">
        <div class="container">
            <div class="search-header">
                <h1>
                    <?php if ($search_query): ?>
                        Search results for "<?= e($search_query) ?>"
                    <?php else: ?>
                        Browse All Tutorials
                    <?php endif; ?>
                </h1>
                <p><?= $pagination['total_items'] ?> tutorials found</p>
            </div>
            
            <!-- Search & Filter Bar -->
            <div class="search-filters">
                <form method="GET" action="" class="filter-form">
                    <div class="search-input-wrapper">
                        <i class="fas fa-search"></i>
                        <input 
                            type="text" 
                            name="q" 
                            placeholder="Search tutorials..." 
                            value="<?= e($search_query) ?>"
                            class="search-input"
                        >
                    </div>
                    
                    <select name="category" class="filter-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>" <?= $category_id == $cat['category_id'] ? 'selected' : '' ?>>
                                <?= e($cat['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="difficulty" class="filter-select">
                        <option value="">All Levels</option>
                        <option value="beginner" <?= $difficulty === 'beginner' ? 'selected' : '' ?>>Beginner</option>
                        <option value="intermediate" <?= $difficulty === 'intermediate' ? 'selected' : '' ?>>Intermediate</option>
                        <option value="advanced" <?= $difficulty === 'advanced' ? 'selected' : '' ?>>Advanced</option>
                    </select>
                    
                    <select name="sort" class="filter-select">
                        <option value="relevant" <?= $sort === 'relevant' ? 'selected' : '' ?>>Most Relevant</option>
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most Popular</option>
                        <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Highest Rated</option>
                    </select>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                </form>
            </div>
            
            <!-- Results Grid -->
            <?php if (empty($tutorials)): ?>
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h2>No tutorials found</h2>
                    <p>Try adjusting your search or filters</p>
                    <a href="search.php" class="btn btn-primary">Clear Filters</a>
                </div>
            <?php else: ?>
                <div class="tutorials-grid">
                    <?php foreach ($tutorials as $tut): ?>
                        <div class="tutorial-card">
                            <div class="card-image">
                                <img src="<?= $tut['thumbnail'] ?>" alt="<?= e($tut['title']) ?>">
                                <span class="difficulty-badge difficulty-<?= $tut['difficulty'] ?>">
                                    <?= ucfirst($tut['difficulty']) ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <span class="category-tag"><?= e($tut['category_name']) ?></span>
                                <h3><?= e($tut['title']) ?></h3>
                                <p><?= truncate($tut['short_description'], 120) ?></p>
                                
                                <div class="card-meta">
                                    <span><i class="fas fa-user"></i> <?= e($tut['instructor_name']) ?></span>
                                    <span><i class="fas fa-star"></i> <?= number_format($tut['avg_rating'], 1) ?></span>
                                </div>
                                
                                <a href="tutorial.php?slug=<?= $tut['slug'] ?>" class="btn btn-block">
                                    View Tutorial
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?q=<?= urlencode($search_query) ?>&category=<?= $category_id ?>&difficulty=<?= $difficulty ?>&sort=<?= $sort ?>&page=<?= $page - 1 ?>" class="btn btn-outline">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <span>Page <?= $page ?> of <?= $pagination['total_pages'] ?></span>
                        
                        <?php if ($page < $pagination['total_pages']): ?>
                            <a href="?q=<?= urlencode($search_query) ?>&category=<?= $category_id ?>&difficulty=<?= $difficulty ?>&sort=<?= $sort ?>&page=<?= $page + 1 ?>" class="btn btn-outline">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>