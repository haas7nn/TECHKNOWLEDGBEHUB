<?php
/**
 * Student Dashboard
 * main page for students to see their learning progress
 * Hasan Fardan - 202301686
 */

require_once '../includes/viewer-auth-check.php';  // 
require_once '../classes/User.php';

$page_title = 'My Learning Dashboard';

// getting student stats
$user = new User();
$user_stats = $user->getUserStats($current_user_id);  // 


// mock data until tutorial class is ready
$enrolled_count = 0;
$completed_count = $user_stats['completed_tutorials'] ?? 0;
$in_progress_count = 0;
$total_learning_time = 0;

// recent activity
$recent_tutorials = [];
$recommended_tutorials = [];
$continue_learning = [];
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
            <!-- welcome header -->
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
            
            <!-- learning stats cards -->
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
            
            <!-- continue learning section -->
            <?php if (!empty($continue_learning)): ?>
            <section class="continue-section">
                <h2><i class="fas fa-play-circle"></i> Continue Learning</h2>
                <div class="continue-grid">
                    <?php foreach ($continue_learning as $tutorial): ?>
                    <div class="continue-card">
                        <div class="tutorial-thumbnail">
                            <img src="<?= $tutorial['thumbnail'] ?>" alt="<?= e($tutorial['title']) ?>">
                            <div class="play-overlay">
                                <i class="fas fa-play-circle"></i>
                            </div>
                        </div>
                        <div class="tutorial-info">
                            <h3><?= e($tutorial['title']) ?></h3>
                            <p class="instructor">
                                <i class="fas fa-user"></i> 
                                <?= e($tutorial['instructor_name']) ?>
                            </p>
                            <div class="progress-wrapper">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= $tutorial['progress'] ?>%"></div>
                                </div>
                                <span class="progress-text"><?= $tutorial['progress'] ?>% complete</span>
                            </div>
                            <a href="tutorial-view.php?id=<?= $tutorial['tutorial_id'] ?>" class="btn btn-primary btn-small">
                                Continue <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
            
            <!-- recommended tutorials -->
            <section class="recommended-section">
                <div class="section-header">
                    <h2><i class="fas fa-lightbulb"></i> Recommended for You</h2>
                    <a href="browse-tutorials.php" class="btn btn-outline">See All</a>
                </div>
                
                <?php if (empty($recommended_tutorials)): ?>
                <div class="empty-state">
                    <i class="fas fa-graduation-cap"></i>
                    <h3>Start Your Learning Journey!</h3>
                    <p>Explore our vast collection of tutorials to get personalized recommendations</p>
                    <a href="browse-tutorials.php" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        Browse Tutorials
                    </a>
                </div>
                <?php else: ?>
                <div class="tutorials-grid">
                    <?php foreach ($recommended_tutorials as $tutorial): ?>
                    <div class="tutorial-card">
                        <div class="card-thumbnail">
                            <img src="<?= $tutorial['thumbnail'] ?>" alt="<?= e($tutorial['title']) ?>">
                            <span class="difficulty-badge difficulty-<?= $tutorial['difficulty'] ?>">
                                <?= ucfirst($tutorial['difficulty']) ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <span class="category-tag">
                                <i class="fas fa-folder"></i>
                                <?= e($tutorial['category_name']) ?>
                            </span>
                            <h3><?= e($tutorial['title']) ?></h3>
                            <p><?= truncate($tutorial['short_description'], 100) ?></p>
                            
                            <div class="card-meta">
                                <span><i class="fas fa-user"></i> <?= e($tutorial['instructor_name']) ?></span>
                                <span><i class="fas fa-clock"></i> <?= $tutorial['duration_minutes'] ?> min</span>
                            </div>
                            
                            <div class="card-footer">
                                <div class="rating">
                                    <i class="fas fa-star"></i>
                                    <span><?= number_format($tutorial['avg_rating'], 1) ?></span>
                                    <small>(<?= $tutorial['rating_count'] ?>)</small>
                                </div>
                                <a href="tutorial-view.php?id=<?= $tutorial['tutorial_id'] ?>" class="btn btn-sm btn-primary">
                                    Start Learning
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>
            
            <!-- learning activity -->
            <section class="activity-section">
                <h2><i class="fas fa-history"></i> Recent Activity</h2>
                <div class="activity-timeline">
                    <div class="empty-timeline">
                        <i class="fas fa-clipboard-list"></i>
                        <p>Your learning activity will appear here</p>
                    </div>
                </div>
            </section>
            
        </main>
    </div>
    
    <script src="<?= asset('js/viewer.js') ?>"></script>
</body>
</html>