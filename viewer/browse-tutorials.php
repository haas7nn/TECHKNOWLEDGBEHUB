<?php
/**
 * Browse Tutorials Page
 * search and filter through all available tutorials
 * Hasan Fardan - 202301686
 */

require_once '../includes/viewer-auth-check.php';

$page_title = 'Browse Tutorials';

// getting filters from url
$search = isset($_GET['search']) ? clean($_GET['search']) : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$difficulty = isset($_GET['difficulty']) ? clean($_GET['difficulty']) : '';
$sort = isset($_GET['sort']) ? clean($_GET['sort']) : 'newest';

// getting categories for filter
$database = new Database();
$conn = $database->connect();

$categoriesQuery = "SELECT category_id, category_name FROM techknow_categories ORDER BY category_name";
$categoriesStmt = $conn->query($categoriesQuery);
$categories = $categoriesStmt->fetchAll();

// mock tutorials data
$tutorials = [];
$total_results = 0;
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
                            
                            <a href="tutorial-view.php?id=<?= $tutorial['tutorial_id'] ?>" class="btn btn-block btn-primary">
                                View Tutorial <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- pagination -->
                <div class="pagination">
                    <button class="btn btn-outline" disabled>
                        <i class="fas fa-chevron-left"></i>
                        Previous
                    </button>
                    <span class="page-info">Page 1 of 1</span>
                    <button class="btn btn-outline" disabled>
                        Next
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
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