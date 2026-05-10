<?php
/**
 * My Learning Page
 * students saved and enrolled tutorials
 * Hasan Fardan - 202301686
 */

// Make sure the user is logged in before showing their stuff
require_once '../includes/viewer-auth-check.php';

// Pull in the Tutorial class for database operations
require_once '../classes/Tutorial.php';

// Set the page title for the browser tab
$page_title = 'My Learning';

// === FIGURE OUT WHICH TAB IS ACTIVE ===
// Check the URL for a tab parameter (all, in-progress, completed, favorites)
// Default to 'all' if nothing is specified
$active_tab = isset($_GET['tab']) ? clean($_GET['tab']) : 'all';

// === CONNECT TO DATABASE ===
// Need a direct connection for our custom query below
$database = new Database();
$conn = $database->connect();

// === BUILD THE MAIN QUERY ===
// This pulls all tutorials the user has interacted with, plus ratings info
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

// === APPLY TAB FILTER ===
// If the user clicked a specific tab, filter the results
if ($active_tab === 'completed') {
    // Only show tutorials they fully finished
    $query .= " AND ua.activity_type = 'complete'";
} elseif ($active_tab === 'in-progress') {
    // Only show tutorials they started but haven't finished
    $query .= " AND ua.activity_type = 'view' AND ua.progress_percentage < 100";
}
// NOTE: 'all' and 'favorites' tabs don't need extra filtering yet
// (favorites logic will need to be added later)

// === FINISH AND RUN THE QUERY ===
// Group by tutorial so we don't get duplicates, order by most recent activity
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
    
    <!-- Our custom styles -->
    <link rel="stylesheet" href="<?= asset('css/viewer.css') ?>">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Top navigation bar -->
    <?php include '../includes/viewer-nav.php'; ?>
    
    <div class="viewer-container">
        <!-- Side menu -->
        <?php include '../includes/viewer-sidebar.php'; ?>
        
        <!-- Main content area -->
        <main class="viewer-main">
            <!-- Page header -->
            <div class="dashboard-header">
                <div>
                    <h1><i class="fas fa-graduation-cap"></i> My Learning</h1>
                    <p>Track your progress and continue where you left off</p>
                </div>
            </div>
            
            <!-- Show any flash messages (like "Tutorial added!" or errors) -->
            <?php displayFlashMessage(); ?>
            
            <!-- === TAB NAVIGATION === -->
            <!-- Four tabs: All, In Progress, Completed, Favorites -->
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
            
            <!-- === TUTORIALS LIST === -->
            <?php if (empty($my_tutorials)): ?>
                <!-- Nothing to show — friendly empty state -->
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
                <!-- Loop through and display each tutorial card -->
                <div class="learning-grid">
                    <?php foreach ($my_tutorials as $tutorial): ?>
                    <div class="learning-card">
                        <div class="card-image">
                            <!-- Show the tutorial thumbnail -->
                            <img src="<?= $tutorial['thumbnail'] ?>" alt="<?= e($tutorial['title']) ?>">
                        </div>
                        <div class="card-body">
                            <!-- Tutorial title -->
                            <h3><?= e($tutorial['title']) ?></h3>
                            <!-- Instructor name -->
                            <p class="instructor">
                                <i class="fas fa-user"></i>
                                <?= e($tutorial['instructor_name']) ?>
                            </p>
                            
                            <!-- Progress bar showing how far they've gotten -->
                            <div class="progress-section">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= $tutorial['progress'] ?>%"></div>
                                </div>
                                <span class="progress-text"><?= $tutorial['progress'] ?>% Complete</span>
                            </div>
                            
                            <!-- Action buttons: Continue/Start and Remove -->
                            <div class="card-actions">
                                <!-- Say "Continue" if they already started, "Start" if they haven't -->
                                <a href="tutorial-view.php?id=<?= $tutorial['tutorial_id'] ?>" class="btn btn-primary btn-block">
                                    <?= $tutorial['progress'] > 0 ? 'Continue' : 'Start' ?> Learning
                                </a>
                                <!-- Remove button (placeholder for now) -->
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
        // Remove a tutorial from the user's learning list
        // NOTE: This is just a placeholder , will connect to backend later
        function removeTutorial(id) {
            if (confirm('Remove this tutorial from your learning list?')) {
                alert('Remove feature will be implemented with Tutorial class');
            }
        }
    </script>
</body>
</html>