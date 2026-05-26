<?php
// my learning page that shows all tutorials this student has started or completed

require_once '../includes/viewer-auth-check.php';

$page_title  = 'My Learning';
// read which tab the user clicked or default to showing everything
$active_tab = $_GET['tab'] ?? 'all';
$allowed_tabs = ['all', 'in-progress', 'completed'];
if (!in_array($active_tab, $allowed_tabs)) { $active_tab = 'all'; }
$css_version = @filemtime(__DIR__ . '/../assets/css/viewer.css') ?: time();

// db connect
$database = new Database();
$conn     = $database->connect();

// if no db
if (!$conn) {
    setFlashMessage('Database connection error. Please try again.', 'error');
    redirect('viewer/dashboard.php');
}

// build the main query that returns one row per tutorial the user has any activity on
// the progress column uses recency to estimate how far through they are
$query = "
    SELECT
        t.tutorial_id,
        t.title,
        t.slug,
        t.thumbnail,
        t.difficulty,
        t.duration_minutes,
        t.view_count,
        c.category_name,
        u.full_name            AS instructor_name,
        COALESCE(AVG(r.rating), 0)         AS avg_rating,
        COUNT(DISTINCT r.rating_id)        AS rating_count,
        MAX(ua.activity_date)              AS last_activity_date,
        /* 1 if the user has ever marked this tutorial complete else 0 */
        MAX(IF(ua.activity_type = 'complete', 1, 0)) AS is_completed,
        /* progress is 100 if complete or based on how recently they viewed it */
        CASE
            WHEN MAX(IF(ua.activity_type = 'complete', 1, 0)) = 1 THEN 100
            WHEN MAX(ua.activity_date) >= DATE_SUB(NOW(), INTERVAL 7 DAY)  THEN 75
            WHEN MAX(ua.activity_date) >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 50
            ELSE 25
        END AS progress
    FROM dbProj_user_activity ua
    JOIN dbProj_tutorials  t  ON t.tutorial_id  = ua.tutorial_id
    JOIN dbProj_categories c  ON c.category_id  = t.category_id
    JOIN dbProj_users      u  ON u.user_id       = t.instructor_id
    LEFT JOIN dbProj_ratings r ON r.tutorial_id  = t.tutorial_id
    WHERE ua.user_id = :user_id
      AND ua.activity_type IN ('view', 'complete')
      AND t.status = 'published'
    GROUP BY
        t.tutorial_id, t.title, t.slug, t.thumbnail, t.difficulty,
        t.duration_minutes, t.view_count, c.category_name, u.full_name
";

// apply a having clause to filter by the active tab after the group by
if ($active_tab === 'completed') {
    $query .= " HAVING MAX(IF(ua.activity_type = 'complete', 1, 0)) = 1";
} elseif ($active_tab === 'in-progress') {
    $query .= " HAVING MAX(IF(ua.activity_type = 'complete', 1, 0)) = 0";
}

// sort by most recently touched so the newest activity appears at the top
$query .= " ORDER BY last_activity_date DESC";

try {
    $stmt = $conn->prepare($query);
    $stmt->bindValue(':user_id', $current_user_id, PDO::PARAM_INT);
    $stmt->execute();
    $my_tutorials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('my-learning query failed: ' . $e->getMessage());
    $my_tutorials = [];
    setFlashMessage('Could not load your tutorials. Please try again.', 'error');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> | <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= asset('css/viewer.css') ?>?v=<?= $css_version ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <?php include '../includes/viewer-nav.php'; ?>

    <div class="viewer-container">
        <?php include '../includes/viewer-sidebar.php'; ?>

        <main class="viewer-main">

            <!-- page heading -->
            <div class="dashboard-header">
                <div>
                    <h1><i class="fas fa-graduation-cap"></i> My Learning</h1>
                    <p>Track your progress and continue where you left off</p>
                </div>
            </div>

            <?php displayFlashMessage(); ?>

            <!-- tab buttons to switch between all, in progress and completed tutorials -->
            <div class="my-learning-tabs">
                <a href="?tab=all"
                   class="tab-btn <?= $active_tab === 'all' ? 'active' : '' ?>">
                    <i class="fas fa-th"></i> All
                </a>
                <a href="?tab=in-progress"
                   class="tab-btn <?= $active_tab === 'in-progress' ? 'active' : '' ?>">
                    <i class="fas fa-tasks"></i> In Progress
                </a>
                <a href="?tab=completed"
                   class="tab-btn <?= $active_tab === 'completed' ? 'active' : '' ?>">
                    <i class="fas fa-check-circle"></i> Completed
                </a>
            </div>

            <?php if (empty($my_tutorials)): ?>
                <!-- empty state with a message that matches whichever tab is active -->
                <div class="empty-state">
                    <i class="fas fa-book-reader"></i>
                    <?php if ($active_tab === 'completed'): ?>
                        <h3>No completed tutorials yet</h3>
                        <p>Keep learning! Mark a tutorial complete when you finish.</p>
                    <?php elseif ($active_tab === 'in-progress'): ?>
                        <h3>Nothing in progress</h3>
                        <p>Start a tutorial to see it here.</p>
                    <?php else: ?>
                        <h3>No tutorials yet</h3>
                        <p>Start learning by browsing the tutorial library</p>
                    <?php endif; ?>
                    <a href="browse-tutorials.php" class="btn btn-primary">
                        <i class="fas fa-search"></i> Browse Tutorials
                    </a>
                </div>
            <?php else: ?>
                <!-- grid of tutorial cards for the tutorials this student has touched -->
                <div class="tutorials-grid">
                    <?php foreach ($my_tutorials as $tut): ?>
                    <div class="tutorial-card">

                        <!-- thumbnail with a difficulty badge overlaid -->
                        <div class="card-thumbnail">
                            <?php if (!empty($tut['thumbnail'])): ?>
                                <img src="<?= SITE_URL ?>/uploads/<?= e($tut['thumbnail']) ?>"
                                     alt="<?= e($tut['title']) ?>">
                            <?php else: ?>
                                <i class="fas fa-book thumb-icon"></i>
                            <?php endif; ?>
                            <span class="difficulty-badge difficulty-<?= e($tut['difficulty']) ?>">
                                <?= ucfirst(e($tut['difficulty'])) ?>
                            </span>
                        </div>

                        <div class="card-body">
                            <span class="category-tag">
                                <i class="fas fa-folder" aria-hidden="true"></i> <?= e($tut['category_name']) ?>
                            </span>
                            <h3><?= e($tut['title']) ?></h3>
                            <div class="instructor">
                                <i class="fas fa-user" aria-hidden="true"></i>
                                <span><?= e($tut['instructor_name']) ?></span>
                            </div>

                            <!-- progress bar that turns green when the tutorial is complete -->
                            <div class="progress-wrapper">
                                <div class="progress-bar">
                                    <div class="progress-fill"
                                         style="width:<?= (int)$tut['progress'] ?>%;
                                                background:<?= $tut['is_completed'] ? '#38a169' : 'var(--clr-primary)' ?>;">
                                    </div>
                                </div>
                                <span class="progress-text">
                                    <?php if ($tut['is_completed']): ?>
                                        <i class="fas fa-check-circle" style="color:#38a169;" aria-hidden="true"></i> Completed
                                    <?php else: ?>
                                        <?= (int)$tut['progress'] ?>% complete
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>

                        <!-- continue or review button and a remove link -->
                        <div class="card-footer">
                            <a href="<?= SITE_URL ?>/viewer/tutorial-view.php?slug=<?= urlencode($tut['slug']) ?>"
                               class="btn btn-primary btn-sm" style="flex:1;justify-content:center;">
                                <?= $tut['is_completed'] ? 'Review' : 'Continue' ?>
                                <i class="fas fa-arrow-right" aria-hidden="true"></i>
                            </a>
                            <button class="btn btn-outline btn-sm"
                                    onclick="removeTutorial(<?= (int)$tut['tutorial_id'] ?>, this)">
                                <i class="fas fa-times" aria-hidden="true"></i> Remove
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <script>
    // confirm then ajax
    function removeTutorial(tutorialId, btn) {
        if (!confirm('Remove this tutorial from your learning list?')) return;
        // disable the button and show a spinner while the request is in flight
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        fetch('<?= SITE_URL ?>/api/remove-learning.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tutorial_id: tutorialId,
                csrf_token: csrfToken
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // remove card
                btn.closest('.tutorial-card').remove();
            } else {
                alert(data.message || 'Failed to remove. Please try again.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-times"></i> Remove';
            }
        })
        .catch(() => {
            alert('Network error. Please try again.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-times"></i> Remove';
        });
    }
    </script>
<script src="<?= asset('js/viewer.js') ?>"></script>
</body>
</html>
