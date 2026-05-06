<?php
/**
 * My Tutorials Page
 * list of all tutorials created by the instructor
 * Hasan Fardan - 202301686
 */

require_once '../includes/auth-check.php';

$page_title = 'My Tutorials';

// mock data 
$tutorials = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= asset('css/creator.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/creator-nav.php'; ?>
    
    <div class="dashboard-container">
        <?php include '../includes/creator-sidebar.php'; ?>
        
        <main class="dashboard-main">
            <!-- header -->
            <div class="dashboard-header">
                <div>
                    <h1><i class="fas fa-book"></i> My Tutorials</h1>
                    <p>Manage all your tutorials in one place</p>
                </div>
                <a href="create-tutorial.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Create New Tutorial
                </a>
            </div>
            
            <!-- flash messages -->
            <?php displayFlashMessage(); ?>
            
            <!-- filters -->
            <div class="filters-bar">
                <div class="filter-group">
                    <label>Status:</label>
                    <select class="filter-select">
                        <option value="">All Status</option>
                        <option value="published">Published</option>
                        <option value="draft">Draft</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Sort by:</label>
                    <select class="filter-select">
                        <option value="newest">Newest First</option>
                        <option value="oldest">Oldest First</option>
                        <option value="most_viewed">Most Viewed</option>
                        <option value="highest_rated">Highest Rated</option>
                    </select>
                </div>
                
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search your tutorials...">
                </div>
            </div>
            
            <!-- tutorials list -->
            <?php if (empty($tutorials)): ?>
                <div class="empty-state-large">
                    <i class="fas fa-book-open"></i>
                    <h2>No tutorials yet</h2>
                    <p>Start sharing your knowledge by creating your first tutorial  !</p>
                    <a href="create-tutorial.php" class="btn btn-primary btn-large">
                        <i class="fas fa-plus-circle"></i>
                        Create Your First Tutorial
                    </a>
                </div>
            <?php else: ?>
                <div class="tutorials-grid">
                    <?php foreach ($tutorials as $tutorial): ?>
                        <div class="tutorial-card-large">
                            <div class="tutorial-thumbnail">
                                <img src="<?= $tutorial['thumbnail'] ?>" alt="<?= e($tutorial['title']) ?>">
                                <span class="tutorial-status status-<?= $tutorial['status'] ?>">
                                    <?= ucfirst($tutorial['status']) ?>
                                </span>
                            </div>
                            
                            <div class="tutorial-card-body">
                                <h3><?= e($tutorial['title']) ?></h3>
                                <p><?= truncate($tutorial['short_description'], 120) ?></p>
                                
                                <div class="tutorial-meta">
                                    <span><i class="fas fa-eye"></i> <?= number_format($tutorial['view_count']) ?> views</span>
                                    <span><i class="fas fa-star"></i> <?= number_format($tutorial['avg_rating'], 1) ?></span>
                                    <span><i class="fas fa-comments"></i> <?= $tutorial['comment_count'] ?></span>
                                </div>
                                
                                <div class="tutorial-footer">
                                    <small class="text-muted">
                                        Created <?= timeAgo($tutorial['created_at']) ?>
                                    </small>
                                    <div class="card-actions">
                                        <a href="edit-tutorial.php?id=<?= $tutorial['tutorial_id'] ?>" class="btn btn-icon" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="../public/tutorial-detail.php?slug=<?= $tutorial['slug'] ?>" class="btn btn-icon" title="View" target="_blank">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                        <button class="btn btn-icon btn-danger" title="Delete" onclick="deleteTutorial(<?= $tutorial['tutorial_id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        function deleteTutorial(id) {
            if (confirm('Are you sure you want to delete this tutorial? This action cannot be undone')) {
                // will be added 
                alert('Delete functionality will be implemented with Tutorial class');
            }
        }
    </script>
</body>
</html>