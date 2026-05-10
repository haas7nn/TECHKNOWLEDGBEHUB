<?php
/**
 * Student Dashboard - REAL DATA VERSION
 * Hasan Fardan - 202301686
 */

// Make sure the user is logged in before they can see their dashboard
require_once '../includes/viewer-auth-check.php';

// Pull in our User and Tutorial classes for database stuff
require_once '../classes/User.php';
require_once '../classes/Tutorial.php';

// Set the page title for the browser tab
$page_title = 'My Learning Dashboard';

// === GRAB THE STUDENT'S REAL STATS ===
// Create a User object and get their overall stats (completed tutorials)
$user = new User();
$user_stats = $user->getUserStats($current_user_id);

// === CONNECT TO THE DATABASE DIRECTLY FOR CUSTOM QUERIES ===
// Some stats need custom SQL, so let's get a direct connection
$database = new Database();
$conn = $database->connect();

// === COUNT HOW MANY TUTORIALS THE USER HAS STARTED ===
// Check the activity table for any enrollments or views by this user
$enrolledQuery = "SELECT COUNT(DISTINCT tutorial_id) as count 
                  FROM dbProj_user_activity 
                  WHERE user_id = :user_id";
$enrolledStmt = $conn->prepare($enrolledQuery);
$enrolledStmt->bindParam(':user_id', $current_user_id, PDO::PARAM_INT);
$enrolledStmt->execute();
$enrolled_count = $enrolledStmt->fetch()['count'];

// === HOW MANY HAVE THEY FINISHED? ===
// This comes from the User class stats we grabbed earlier
$completed_count = $user_stats['completed_tutorials'] ?? 0;

// === CALCULATE "IN PROGRESS" ===
// Simple math: started minus completed = still working on it
$in_progress_count = $enrolled_count - $completed_count;

// === CALCULATE TOTAL LEARNING TIME ===
// Sum up the duration of all tutorials they've marked as complete
$timeQuery = "SELECT SUM(t.duration_minutes) as total_time
              FROM dbProj_user_activity ua
              JOIN dbProj_tutorials t ON ua.tutorial_id = t.tutorial_id
              WHERE ua.user_id = :user_id AND ua.activity_type = 'complete'";
$timeStmt = $conn->prepare($timeQuery);
$timeStmt->bindParam(':user_id', $current_user_id, PDO::PARAM_INT);
$timeStmt->execute();
$total_learning_time = $timeStmt->fetch()['total_time'] ?? 0;

// === GET TUTORIALS TO "CONTINUE LEARNING" ===
// Fetch the last 3 tutorials this user interacted with, newest first
// Also figure out if they're done (100%) or halfway there (50%)
$continueQuery = "SELECT t.*, c.category_name, u.full_name as instructor_name,
                  ua.activity_type,
                  CASE 
                    WHEN ua.activity_type = 'complete' THEN 100
                    ELSE 50
                  END as progress
                  FROM dbProj_user_activity ua
                  JOIN dbProj_tutorials t ON ua.tutorial_id = t.tutorial_id
                  JOIN dbProj_categories c ON t.category_id = c.category_id
                  JOIN dbProj_users u ON t.instructor_id = u.user_id
                  WHERE ua.user_id = :user_id
                  ORDER BY ua.activity_date DESC
                  LIMIT 3";
$continueStmt = $conn->prepare($continueQuery);
$continueStmt->bindParam(':user_id', $current_user_id, PDO::PARAM_INT);
$continueStmt->execute();
$continue_learning = $continueStmt->fetchAll();

// === GET RECOMMENDED TUTORIALS ===
// For now, just grab the 6 most popular published tutorials
$tutorial = new Tutorial();
$recommended_result = $tutorial->getPublished(1, 6);
$recommended_tutorials = $recommended_result['tutorials'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - <?= SITE_NAME ?></title>
    
    <!-- Our custom viewer styles -->
    <link rel="stylesheet" href="<?= asset('css/viewer.css') ?>?v=<?= filemtime(__DIR__ . '/../assets/css/viewer.css') ?>">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Top navigation bar -->
    <?php include '../includes/viewer-nav.php'; ?>
    
    <div class="viewer-container">
        <!-- Side menu -->
        <?php include '../includes/viewer-sidebar.php'; ?>
        
        <!-- Main dashboard content -->
        <main class="viewer-main">
            <!-- Welcome header with user's name -->
            <div class="dashboard-header">
                <div>
                    <h1>Welcome back, <?= e($current_user_name) ?>! 📚</h1>
                    <p>Continue your learning journey where you left off</p>
                </div>
                <!-- Quick link to browse more tutorials -->
                <a href="browse-tutorials.php" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    Browse Tutorials
                </a>
            </div>
            
            <!-- Show any success/error messages (like "Tutorial completed!") -->
            <?php displayFlashMessage(); ?>
            
            <!-- === STATS CARDS ROW === -->
            <!-- Four cards showing: enrolled, in progress, completed, total time -->
            <div class="stats-grid">
                <!-- Card 1: How many tutorials they're enrolled in -->
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?= $enrolled_count ?></h3>
                        <p>Enrolled Tutorials</p>
                        <span class="stat-change">
                            <i class="fas fa-graduation-cap"></i> Learning
                        </span>
                    </div>
                </div>
                
                <!-- Card 2: Still working on these -->
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?= $in_progress_count ?></h3>
                        <p>In Progress</p>
                        <span class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> Keep going!
                        </span>
                    </div>
                </div>
                
                <!-- Card 3: Finished these ones -->
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?= $completed_count ?></h3>
                        <p>Completed</p>
                        <span class="stat-change positive">
                            <i class="fas fa-trophy"></i> Great job!
                        </span>
                    </div>
                </div>
                
                <!-- Card 4: Total hours spent learning -->
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?= number_format($total_learning_time / 60, 1) ?>h</h3>
                        <p>Learning Time</p>
                        <span class="stat-change">
                            <i class="fas fa-fire"></i> Amazing!
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- === CONTINUE LEARNING SECTION === -->
            <!-- Only show this if the user actually has some activity history -->
            <?php if (!empty($continue_learning)): ?>
            <section class="continue-section">
                <h2><i class="fas fa-play-circle"></i> Continue Learning</h2>
                <div class="continue-grid">
                    <!-- Loop through their last 3 active tutorials -->
                    <?php foreach ($continue_learning as $tut): ?>
                    <div class="continue-card">
                        <div class="tutorial-thumbnail">
                            <!-- Show thumbnail or a placeholder book icon -->
                            <?php if (!empty($tut['thumbnail'])): ?>
                                <img src="<?= SITE_URL ?>/uploads/<?= e($tut['thumbnail']) ?>" alt="<?= e($tut['title']) ?>">
                            <?php else: ?>
                                <div style="width:100%;height:150px;background:#e0e0e0;display:flex;align-items:center;justify-content:center;">
                                    <i class="fas fa-book" style="font-size:48px;color:#999;"></i>
                                </div>
                            <?php endif; ?>
                            <!-- Play button overlay on the thumbnail -->
                            <div class="play-overlay">
                                <i class="fas fa-play-circle"></i>
                            </div>
                        </div>
                        <div class="tutorial-info">
                            <h3><?= e($tut['title']) ?></h3>
                            <p class="instructor">
                                <i class="fas fa-user"></i> 
                                <?= e($tut['instructor_name']) ?>
                            </p>
                            <!-- Progress bar: 100% if complete, 50% if just enrolled -->
                            <div class="progress-wrapper">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= $tut['progress'] ?>%"></div>
                                </div>
                                <span class="progress-text"><?= $tut['progress'] ?>% complete</span>
                            </div>
                            <!-- Button to jump back into the tutorial -->
                            <a href="tutorial-view.php?slug=<?= urlencode($tut['slug']) ?>" class="btn btn-primary btn-small">
                                Continue <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
            
            <!-- === RECOMMENDED TUTORIALS SECTION === -->
            <section class="recommended-section">
                <div class="section-header">
                    <h2><i class="fas fa-lightbulb"></i> Recommended for You</h2>
                    <a href="browse-tutorials.php" class="btn btn-outline">See All</a>
                </div>
                
                <!-- If there are no published tutorials yet, show a friendly empty state -->
                <?php if (empty($recommended_tutorials)): ?>
                <div class="empty-state">
                    <i class="fas fa-graduation-cap"></i>
                    <h3>Start Your Learning Journey!</h3>
                    <p>Explore our vast collection of tutorials</p>
                    <a href="browse-tutorials.php" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        Browse Tutorials
                    </a>
                </div>
                <?php else: ?>
                <!-- Show the recommended tutorial cards in a grid -->
                <div class="tutorials-grid">
                    <?php foreach ($recommended_tutorials as $tut): ?>
                    <div class="tutorial-card">
                        <div class="card-thumbnail">
                            <!-- Thumbnail image or fallback placeholder -->
                            <?php if (!empty($tut['thumbnail'])): ?>
                                <img src="<?= SITE_URL ?>/uploads/<?= e($tut['thumbnail']) ?>" alt="<?= e($tut['title']) ?>">
                            <?php else: ?>
                                <div style="width:100%;height:180px;background:#e0e0e0;display:flex;align-items:center;justify-content:center;">
                                    <i class="fas fa-book" style="font-size:48px;color:#999;"></i>
                                </div>
                            <?php endif; ?>
                            <!-- Difficulty badge (Beginner/Intermediate/Advanced) -->
                            <span class="difficulty-badge difficulty-<?= $tut['difficulty'] ?>">
                                <?= ucfirst($tut['difficulty']) ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <!-- Category tag -->
                            <span class="category-tag">
                                <i class="fas fa-folder"></i>
                                <?= e($tut['category_name']) ?>
                            </span>
                            <!-- Title and short description -->
                            <h3><?= e($tut['title']) ?></h3>
                            <p><?= truncate($tut['short_description'], 100) ?></p>
                            
                            <!-- Instructor name and duration -->
                            <div class="card-meta">
                                <span><i class="fas fa-user"></i> <?= e($tut['instructor_name']) ?></span>
                                <span><i class="fas fa-clock"></i> <?= $tut['duration_minutes'] ?> min</span>
                            </div>
                            
                            <!-- Rating and "Start Learning" button -->
                            <div class="card-footer">
                                <div class="rating">
                                    <i class="fas fa-star" style="color: #ffc107;"></i>
                                    <span><?= number_format($tut['avg_rating'] ?? 0, 1) ?></span>
                                    <small>(<?= $tut['rating_count'] ?>)</small>
                                </div>
                                <a href="tutorial-view.php?slug=<?= urlencode($tut['slug']) ?>" class="btn btn-sm btn-primary">
                                    Start Learning
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
    
    <!-- Load our viewer JavaScript -->
    <script src="<?= asset('js/viewer.js') ?>"></script>
</body>
</html>