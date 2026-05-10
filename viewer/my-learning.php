<?php
/**
 * My Learning Page
 * students saved and enrolled tutorials
 * Hasan Fardan - 202301686
 */

require_once '../includes/viewer-auth-check.php';
require_once '../classes/Tutorial.php';

$page_title = 'My Learning';

$active_tab = isset($_GET['tab']) ? clean($_GET['tab']) : 'all';

// Get real enrollment data from database
$database = new Database();
$conn = $database->connect();

$query = "SELECT DISTINCT t.*, c.category_name, u.full_name as instructor_name,
          ua.activity_type,
          COALESCE(ua.progress_percentage, 0) as progress,
          COALESCE(AVG(r.rating), 0) as avg_rating,
          COUNT(DISTINCT r2.rating_id) as rating_count
          FROM dbProj_user_activity ua
          JOIN dbProj_tutorials t ON ua.tutorial_id = t.tutorial_id
          JOIN dbProj_categories c ON t.category_id = c.category_id
          JOIN dbProj_users u ON t.instructor_id = u.user_id
          LEFT JOIN dbProj_ratings r ON t.tutorial_id = r.tutorial_id
          LEFT JOIN dbProj_ratings r2 ON t.tutorial_id = r2.tutorial_id
          WHERE ua.user_id = :user_id";

// Apply tab filter
if ($active_tab === 'completed') {
    $query .= " AND ua.activity_type = 'complete'";
} elseif ($active_tab === 'in-progress') {
    $query .= " AND ua.activity_type = 'view' AND ua.progress_percentage < 100";
}

$query .= " GROUP BY t.tutorial_id ORDER BY ua.activity_date DESC";

$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $current_user_id, PDO::PARAM_INT);
$stmt->execute();
$my_tutorials = $stmt->fetchAll();
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
            <div class="dashboard-header">
                <div>
                    <h1><i class="fas fa-graduation-cap"></i> My Learning</h1>
                    <p>Track your progress and continue where you left off</p>
                </div>
            </div>
            
            <?php displayFlashMessage(); ?>
            
            <!-- tabs navigation -->
            <div class="tabs-navigation">
                <a href="?tab=all" class="tab <?= $active_tab === 'all' ? 'active' : '' ?>">
                    <i class="fas fa-th"></i>
                    All Tutorials
                </a>
                <a href="?tab=in-progress" class="tab <?= $active_tab === 'in-progress' ? 'active' : '' ?>">
                    <i class="fas fa-tasks"></i>
                    In Progress
                </a>
                <a href="?tab=completed" class="tab <?= $active_tab === 'completed' ? 'active' : '' ?>">
                    <i class="fas fa-check-circle"></i>
                    Completed
                </a>
                <a href="?tab=favorites" class="tab <?= $active_tab === 'favorites' ? 'active' : '' ?>">
                    <i class="fas fa-heart"></i>
                    Favorites
                </a>
            </div>
            
            <!-- tutorials list -->
            <?php if (empty($my_tutorials)): ?>
                <div class="empty-state-large">
                    <i class="fas fa-book-reader"></i>
                    <h2>No tutorials found</h2>
                    <p>Start learning by exploring our tutorial library</p>
                    <a href="browse-tutorials.php" class="btn btn-primary btn-large">
                        <i class="fas fa-search"></i>
                        Browse Tutorials
                    </a>
                </div>
            <?php else: ?>
                <div class="learning-grid">
                    <?php foreach ($my_tutorials as $tutorial): ?>
                    <div class="learning-card">
                        <div class="card-image">
                            <img src="<?= $tutorial['thumbnail'] ?>" alt="<?= e($tutorial['title']) ?>">
                        </div>
                        <div class="card-body">
                            <h3><?= e($tutorial['title']) ?></h3>
                            <p class="instructor">
                                <i class="fas fa-user"></i>
                                <?= e($tutorial['instructor_name']) ?>
                            </p>
                            
                            <div class="progress-section">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= $tutorial['progress'] ?>%"></div>
                                </div>
                                <span class="progress-text"><?= $tutorial['progress'] ?>% Complete</span>
                            </div>
                            
                            <div class="card-actions">
                                <a href="tutorial-view.php?id=<?= $tutorial['tutorial_id'] ?>" class="btn btn-primary btn-block">
                                    <?= $tutorial['progress'] > 0 ? 'Continue' : 'Start' ?> Learning
                                </a>
                                <button class="btn btn-outline btn-block" onclick="removeTutorial(<?= $tutorial['tutorial_id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                    Remove
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        function removeTutorial(id) {
            if (confirm('Remove this tutorial from your learning list?')) {
                alert('Remove feature will be implemented with Tutorial class');
            }
        }
    </script>
</body>
</html>