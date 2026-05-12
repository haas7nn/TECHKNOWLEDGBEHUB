<?php
// my learning page — shows all the tutorials this student has started or completed
// Hasan Fardan - 202301686

require_once '../includes/viewer-auth-check.php';

$page_title  = 'My Learning';
$active_tab  = isset($_GET['tab']) ? clean($_GET['tab']) : 'all';
$css_version = @filemtime(__DIR__ . '/../assets/css/viewer.css') ?: time();

$database = new Database();
$conn     = $database->connect();

// Bug 4 fix: always check the connection worked before using it
if (!$conn) {
    setFlashMessage('Database connection error. Please try again.', 'error');
    redirect('viewer/dashboard.php');
}

// Bug 3 fix: added ANY_VALUE() around activity_type and activity_date so this
// works when MySQL is running in ONLY_FULL_GROUP_BY mode (which is the default
// in MySQL 5.7+ and 8.0). Without it the query would fail on strict servers.
$query = "SELECT t.tutorial_id, t.title, t.slug, t.thumbnail, t.difficulty,
                  t.duration_minutes, t.view_count,
                  c.category_name,
                  u.full_name AS instructor_name,
                  ANY_VALUE(ua.activity_type)  AS activity_type,
                  ANY_VALUE(ua.activity_date)  AS activity_date,
                  CASE
                    WHEN MAX(CASE WHEN ua.activity_type = 'complete' THEN 1 ELSE 0 END) = 1 THEN 100
                    WHEN MAX(ua.activity_date) >= DATE_SUB(NOW(), INTERVAL 7 DAY)  THEN 75
                    WHEN MAX(ua.activity_date) >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 50
                    ELSE 25
                  END AS progress,
                  COALESCE(AVG(r.rating), 0) AS avg_rating,
                  COUNT(DISTINCT r.rating_id) AS rating_count
          FROM dbProj_user_activity ua
          JOIN dbProj_tutorials t  ON ua.tutorial_id  = t.tutorial_id
          JOIN dbProj_categories c ON t.category_id   = c.category_id
          JOIN dbProj_users u       ON t.instructor_id = u.user_id
          LEFT JOIN dbProj_ratings r ON t.tutorial_id  = r.tutorial_id
          WHERE ua.user_id = :user_id";

// filter by tab if the student picked one
if ($active_tab === 'completed') {
    $query .= " AND ua.activity_type = 'complete'";
} elseif ($active_tab === 'in-progress') {
    $query .= " AND ua.activity_type = 'view'";
}

// group by tutorial so we get one row per tutorial, not one per activity
$query .= " GROUP BY t.tutorial_id, t.title, t.slug, t.thumbnail, t.difficulty,
                      t.duration_minutes, t.view_count, c.category_name, u.full_name
            ORDER BY ANY_VALUE(ua.activity_date) DESC";

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
    <link rel="stylesheet" href="<?= asset('css/viewer.css') ?>?v=<?= $css_version ?>">
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

            <!-- tab buttons to filter by status -->
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
                <!-- nothing found — point them to browse -->
                <div class="empty-state">
                    <i class="fas fa-book-reader"></i>
                    <h3>No tutorials here yet</h3>
                    <p>Start learning by browsing the tutorial library</p>
                    <a href="browse-tutorials.php" class="btn btn-primary">
                        <i class="fas fa-search"></i> Browse Tutorials
                    </a>
                </div>
            <?php else: ?>
                <div class="tutorials-grid">
                    <?php foreach ($my_tutorials as $tutorial): ?>
                    <div class="tutorial-card">

                        <!-- thumbnail or placeholder icon -->
                        <div class="card-thumbnail">
                            <?php if (!empty($tutorial['thumbnail'])): ?>
                                <img src="<?= SITE_URL ?>/uploads/<?= e($tutorial['thumbnail']) ?>"
                                     alt="<?= e($tutorial['title']) ?>"
                                     onerror="this.style.display='none'">
                            <?php else: ?>
                                <div style="height:180px;background:#e8ecf1;display:flex;align-items:center;justify-content:center;">
                                    <i class="fas fa-book" style="font-size:48px;color:#aaa;"></i>
                                </div>
                            <?php endif; ?>
                            <span class="difficulty-badge difficulty-<?= e($tutorial['difficulty']) ?>">
                                <?= ucfirst(e($tutorial['difficulty'])) ?>
                            </span>
                        </div>

                        <div class="card-body">
                            <span class="category-tag">
                                <i class="fas fa-folder"></i> <?= e($tutorial['category_name']) ?>
                            </span>
                            <h3><?= e($tutorial['title']) ?></h3>
                            <p class="instructor">
                                <i class="fas fa-user"></i> <?= e($tutorial['instructor_name']) ?>
                            </p>

                            <!-- progress bar — based on activity type and recency -->
                            <div class="progress-wrapper">
                                <div class="progress-bar">
                                    <div class="progress-fill"
                                         style="width:<?= (int)$tutorial['progress'] ?>%;"></div>
                                </div>
                                <span class="progress-text">
                                    <?= (int)$tutorial['progress'] ?>% complete
                                </span>
                            </div>

                            <div class="card-footer">
                                <!-- continue or review depending on progress -->
                                <a href="<?= SITE_URL ?>/viewer/tutorial-view.php?slug=<?= urlencode($tutorial['slug']) ?>"
                                   class="btn btn-primary btn-sm">
                                    <?= $tutorial['progress'] >= 100 ? 'Review' : 'Continue' ?>
                                    <i class="fas fa-arrow-right"></i>
                                </a>

                                <!-- remove from list — calls the API with AJAX -->
                                <button class="btn btn-outline btn-sm"
                                        onclick="removeTutorial(<?= (int)$tutorial['tutorial_id'] ?>, this)">
                                    <i class="fas fa-times"></i> Remove
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
    // sends an AJAX request to delete this tutorial from the student's activity log
    // then removes the card from the page without a full reload
    function removeTutorial(tutorialId, btn) {
        if (!confirm('Remove this tutorial from your learning list?')) return;

        // disable the button while the request is running
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        fetch('<?= SITE_URL ?>/api/remove-learning.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tutorial_id: tutorialId,
                csrf_token: '<?= generateCSRFToken() ?>'
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // remove the card from the page
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
</body>
</html>
