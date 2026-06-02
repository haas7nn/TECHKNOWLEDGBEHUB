<?php
// student dashboard page that shows real learning stats and recommended tutorials

require_once '../includes/viewer-auth-check.php';
require_once '../classes/User.php';
require_once '../classes/Tutorial.php';

$page_title = 'My Learning Dashboard';

// load the user object and fetch their overall stats
$user = new User();
$user_stats = $user->getUserStats($current_user_id);

// db connect
$database = new Database();
$conn = $database->connect();

// make sure the database actually connected before we try to use it
if (!$conn) {
    setFlashMessage('Could not connect to the database. Please try again later.', 'error');
    // show a blank dashboard with the error flash above
}

// set default values for everything in case the database is down
$enrolled_count      = 0;
$completed_count     = 0;
$in_progress_count   = 0;
$total_learning_time = 0;
$continue_learning   = [];
$recommended_tutorials = [];

if ($conn) {
    // count how many unique tutorials this student has interacted with
    // only count actual views and completions not favourites (f28)
    $enrolledStmt = $conn->prepare(
        "SELECT COUNT(DISTINCT tutorial_id) as count
         FROM dbProj_user_activity
         WHERE user_id = :user_id
           AND activity_type IN ('view', 'complete')"
    );
    $enrolledStmt->bindParam(':user_id', $current_user_id, PDO::PARAM_INT);
    $enrolledStmt->execute();
    $row = $enrolledStmt->fetch();
    $enrolled_count = (int)($row ? ($row['count'] ?? 0) : 0);

    // grab the completed count from user stats and work out how many are still in progress
    // clamp to 0 to prevent a negative value if completed_count exceeds enrolled_count
    $completed_count   = $user_stats['completed_tutorials'] ?? 0;
    $in_progress_count = max(0, $enrolled_count - $completed_count);

    // add up the total time spent on tutorials the user has completed
    $timeStmt = $conn->prepare(
        "SELECT SUM(t.duration_minutes) as total_time
         FROM dbProj_user_activity ua
         JOIN dbProj_tutorials t ON ua.tutorial_id = t.tutorial_id
         WHERE ua.user_id = :user_id AND ua.activity_type = 'complete'"
    );
    $timeStmt->bindParam(':user_id', $current_user_id, PDO::PARAM_INT);
    $timeStmt->execute();
    $row = $timeStmt->fetch();
    $total_learning_time = (float)($row ? ($row['total_time'] ?? 0) : 0);

    // get the last 3 tutorials this student touched so they can continue where they left off
    // progress is 100 if complete 75 if viewed in the last 7 days 50 if last 30 days else 25
    $continueStmt = $conn->prepare(
        "SELECT t.*, c.category_name, u.full_name as instructor_name,
                ua.activity_type,
                ua.activity_date,
                CASE
                  WHEN ua.activity_type = 'complete' THEN 100
                  WHEN ua.activity_type = 'view' AND ua.activity_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)  THEN 75
                  WHEN ua.activity_type = 'view' AND ua.activity_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 50
                  ELSE 25
                END as progress
         FROM dbProj_user_activity ua
         JOIN dbProj_tutorials t ON ua.tutorial_id = t.tutorial_id
         JOIN dbProj_categories c ON t.category_id = c.category_id
         JOIN dbProj_users u ON t.instructor_id = u.user_id
         WHERE ua.user_id = :user_id
           AND t.status = 'published'
         ORDER BY ua.activity_date DESC
         LIMIT 3"
    );
    $continueStmt->bindParam(':user_id', $current_user_id, PDO::PARAM_INT);
    $continueStmt->execute();
    $continue_learning = $continueStmt->fetchAll();

    // fetch a set of published tutorials to show in the recommendations section
    $tutorial              = new Tutorial();
    $recommended_result    = $tutorial->getPublished(1, 6);
    $recommended_tutorials = $recommended_result['tutorials'] ?? [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> | <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= asset('css/viewer.css') ?>?v=<?= @filemtime(__DIR__ . '/../assets/css/viewer.css') ?: time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/viewer-nav.php'; ?>

    <div class="viewer-container">
        <?php include '../includes/viewer-sidebar.php'; ?>

        <main class="viewer-main">

            <!-- welcome heading and browse button -->
            <div class="dashboard-header">
                <div>
                    <h1><i class="fas fa-graduation-cap"></i> Welcome back, <?= e($current_user_name) ?>!</h1>
                    <p>Continue your learning journey where you left off</p>
                </div>
                <a href="browse-tutorials.php" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    Browse Tutorials
                </a>
            </div>

            <?php displayFlashMessage(); ?>

            <!-- four stat cards showing enrolled, in progress, completed and total learning time -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon si-purple">
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
                    <div class="stat-icon si-pink">
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
                    <div class="stat-icon si-blue">
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
                    <div class="stat-icon si-gold">
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

            <!-- show the last three tutorials the student was working on -->
            <?php if (!empty($continue_learning)): ?>
            <section class="continue-section">
                <h2><i class="fas fa-play-circle"></i> Continue Learning</h2>
                <div class="continue-grid">
                    <?php foreach ($continue_learning as $tut): ?>
                    <div class="continue-card">
                        <div class="tutorial-thumbnail">
                            <?php if (!empty($tut['thumbnail'])): ?>
                                <img src="<?= SITE_URL ?>/uploads/<?= e($tut['thumbnail']) ?>" alt="<?= e($tut['title']) ?>" onerror="this.onerror=null;this.src='<?= SITE_URL ?>/uploads/placeholder.svg'">
                            <?php else: ?>
                                <!-- placeholder icon when the tutorial has no thumbnail -->
                                <div class="thumb-placeholder"><i class="fas fa-book"></i></div>
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
                            <!-- progress bar showing how far through the tutorial the student is -->
                            <div class="progress-wrapper">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= $tut['progress'] ?>%"></div>
                                </div>
                                <span class="progress-text"><?= $tut['progress'] ?>% complete</span>
                            </div>
                            <a href="tutorial-view.php?slug=<?= urlencode($tut['slug']) ?>" class="btn btn-primary btn-sm">
                                Continue <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- section showing tutorials the platform recommends for this student -->
            <section class="recommended-section">
                <div class="section-header">
                    <h2><i class="fas fa-lightbulb"></i> Recommended for You</h2>
                    <a href="browse-tutorials.php" class="btn btn-outline">See All</a>
                </div>

                <?php if (empty($recommended_tutorials)): ?>
                <!-- empty state shown when there are no tutorials to recommend yet -->
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
                <!-- grid of recommended tutorial cards -->
                <div class="tutorials-grid">
                    <?php foreach ($recommended_tutorials as $tut): ?>
                    <div class="tutorial-card">
                        <div class="card-thumbnail">
                            <?php if (!empty($tut['thumbnail'])): ?>
                                <img src="<?= SITE_URL ?>/uploads/<?= e($tut['thumbnail']) ?>" alt="<?= e($tut['title']) ?>" onerror="this.onerror=null;this.src='<?= SITE_URL ?>/uploads/placeholder.svg'">
                            <?php else: ?>
                                <i class="fas fa-book thumb-icon"></i>
                            <?php endif; ?>
                            <span class="difficulty-badge difficulty-<?= $tut['difficulty'] ?>">
                                <?= ucfirst($tut['difficulty']) ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <span class="category-tag">
                                <i class="fas fa-folder" aria-hidden="true"></i>
                                <?= e($tut['category_name']) ?>
                            </span>
                            <h3><?= e($tut['title']) ?></h3>
                            <p><?= e(truncate($tut['short_description'], 100)) ?></p>

                            <!-- instructor name and duration -->
                            <div class="card-meta">
                                <span><i class="fas fa-user" aria-hidden="true"></i> <?= e($tut['instructor_name']) ?></span>
                                <?php if ($tut['duration_minutes']): ?>
                                <span><i class="fas fa-clock" aria-hidden="true"></i> <?= $tut['duration_minutes'] ?> min</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- card footer with rating and start learning button -->
                        <div class="card-footer">
                            <div class="rating">
                                <i class="fas fa-star" aria-hidden="true"></i>
                                <span><?= number_format($tut['avg_rating'] ?? 0, 1) ?></span>
                                <small>(<?= $tut['rating_count'] ?>)</small>
                            </div>
                            <a href="tutorial-view.php?slug=<?= urlencode($tut['slug']) ?>"
                               class="btn btn-sm btn-primary">
                                Start Learning
                            </a>
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
