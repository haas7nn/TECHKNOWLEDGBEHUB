<?php
/**
 * Student Dashboard - REAL DATA VERSION
 * Hasan Fardan - 202301686
 */

require_once '../includes/viewer-auth-check.php';
require_once '../classes/User.php';
require_once '../classes/Tutorial.php';

$page_title = 'My Learning Dashboard';

// Get real student stats
$user = new User();
$user_stats = $user->getUserStats($current_user_id);

// Get user's activity
$database = new Database();
$conn = $database->connect();

// Count enrolled/viewed tutorials
$enrolledQuery = "SELECT COUNT(DISTINCT tutorial_id) as count 
                  FROM techknow_user_activity 
                  WHERE user_id = :user_id";
$enrolledStmt = $conn->prepare($enrolledQuery);
$enrolledStmt->bindParam(':user_id', $current_user_id, PDO::PARAM_INT);
$enrolledStmt->execute();
$enrolled_count = $enrolledStmt->fetch()['count'];

// Count completed tutorials
$completed_count = $user_stats['completed_tutorials'] ?? 0;

// Count in progress
$in_progress_count = $enrolled_count - $completed_count;

// Calculate total learning time (estimated from completed tutorials)
$timeQuery = "SELECT SUM(t.duration_minutes) as total_time
              FROM techknow_user_activity ua
              JOIN techknow_tutorials t ON ua.tutorial_id = t.tutorial_id
              WHERE ua.user_id = :user_id AND ua.activity_type = 'complete'";
$timeStmt = $conn->prepare($timeQuery);
$timeStmt->bindParam(':user_id', $current_user_id, PDO::PARAM_INT);
$timeStmt->execute();
$total_learning_time = $timeStmt->fetch()['total_time'] ?? 0;

// Get recent tutorials (continue learning)
$continueQuery = "SELECT t.*, c.category_name, u.full_name as instructor_name,
                  ua.activity_type,
                  CASE 
                    WHEN ua.activity_type = 'complete' THEN 100
                    ELSE 50
                  END as progress
                  FROM techknow_user_activity ua
                  JOIN techknow_tutorials t ON ua.tutorial_id = t.tutorial_id
                  JOIN techknow_categories c ON t.category_id = c.category_id
                  JOIN techknow_users u ON t.instructor_id = u.user_id
                  WHERE ua.user_id = :user_id
                  ORDER BY ua.activity_date DESC
                  LIMIT 3";
$continueStmt = $conn->prepare($continueQuery);
$continueStmt->bindParam(':user_id', $current_user_id, PDO::PARAM_INT);
$continueStmt->execute();
$continue_learning = $continueStmt->fetchAll();

// Get recommended tutorials (most popular)
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
                    <h1>Welcome back, <?= e($current_user_name) ?>! 📚</h1>
                    <p>Continue your learning journey where you left off</p>
                </div>
                <a href="browse-tutorials.php" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    Browse Tutorials
                </a>
            </div>
            
            <?php displayFlashMessage(); ?>
            
            <!-- Real Statistics Cards -->
            <div class="stats-grid">
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
            
            <!-- Continue Learning Section -->
            <?php if (!empty($continue_learning)): ?>
            <section class="continue-section">
                <h2><i class="fas fa-play-circle"></i> Continue Learning</h2>
                <div class="continue-grid">
                    <?php foreach ($continue_learning as $tut): ?>
                    <div class="continue-card">
                        <div class="tutorial-thumbnail">
                            <?php if (!empty($tut['thumbnail'])): ?>
                                <img src="<?= SITE_URL ?>/uploads/<?= e($tut['thumbnail']) ?>" alt="<?= e($tut['title']) ?>">
                            <?php else: ?>
                                <div style="width:100%;height:150px;background:#e0e0e0;display:flex;align-items:center;justify-content:center;">
                                    <i class="fas fa-book" style="font-size:48px;color:#999;"></i>
                                </div>
                            <?php endif; ?>
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
                            <div class="progress-wrapper">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= $tut['progress'] ?>%"></div>
                                </div>
                                <span class="progress-text"><?= $tut['progress'] ?>% complete</span>
                            </div>
                            <a href="../public/search.php?q=<?= urlencode($tut['title']) ?>" class="btn btn-primary btn-small">
                                Continue <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
            
            <!-- Recommended Tutorials (continues...) -->
            <section class="recommended-section">
                <div class="section-header">
                    <h2><i class="fas fa-lightbulb"></i> Recommended for You</h2>
                    <a href="browse-tutorials.php" class="btn btn-outline">See All</a>
                </div>
                
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
                <div class="tutorials-grid">
                    <?php foreach ($recommended_tutorials as $tut): ?>
                    <div class="tutorial-card">
                        <div class="card-thumbnail">
                            <?php if (!empty($tut['thumbnail'])): ?>
                                <img src="<?= SITE_URL ?>/uploads/<?= e($tut['thumbnail']) ?>" alt="<?= e($tut['title']) ?>">
                            <?php else: ?>
                                <div style="width:100%;height:180px;background:#e0e0e0;display:flex;align-items:center;justify-content:center;">
                                    <i class="fas fa-book" style="font-size:48px;color:#999;"></i>
                                </div>
                            <?php endif; ?>
                            <span class="difficulty-badge difficulty-<?= $tut['difficulty'] ?>">
                                <?= ucfirst($tut['difficulty']) ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <span class="category-tag">
                                <i class="fas fa-folder"></i>
                                <?= e($tut['category_name']) ?>
                            </span>
                            <h3><?= e($tut['title']) ?></h3>
                            <p><?= truncate($tut['short_description'], 100) ?></p>
                            
                            <div class="card-meta">
                                <span><i class="fas fa-user"></i> <?= e($tut['instructor_name']) ?></span>
                                <span><i class="fas fa-clock"></i> <?= $tut['duration_minutes'] ?> min</span>
                            </div>
                            
                            <div class="card-footer">
                                <div class="rating">
                                    <i class="fas fa-star" style="color: #ffc107;"></i>
                                    <span><?= number_format($tut['avg_rating'] ?? 0, 1) ?></span>
                                    <small>(<?= $tut['rating_count'] ?>)</small>
                                </div>
                                <a href="../public/search.php?q=<?= urlencode($tut['title']) ?>" class="btn btn-sm btn-primary">
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
    
    <script src="<?= asset('js/viewer.js') ?>"></script>
</body>
</html>