<?php
// saved favorite tutorials
require_once '../includes/viewer-auth-check.php';

$page_title  = 'My Favorites';
$css_version = @filemtime(__DIR__ . '/../assets/css/viewer.css') ?: time();

// db connect
$database = new Database();
$conn     = $database->connect();

// redirect if db failed
if (!$conn) {
    setFlashMessage('Database connection error. Please try again.', 'error');
    redirect('viewer/dashboard.php');
}

// fetch favorited published tutorials newest first
$favorites = [];
try {
    $stmt = $conn->prepare("
        SELECT t.tutorial_id, t.title, t.slug, t.thumbnail, t.difficulty,
               t.duration_minutes, t.view_count, t.short_description, t.video_url,
               c.category_name,
               u.full_name AS instructor_name,
               COALESCE(AVG(r.rating), 0)  AS avg_rating,
               COUNT(DISTINCT r.rating_id) AS rating_count,
               fav.activity_date           AS favorited_at
        FROM dbProj_user_activity fav
        JOIN dbProj_tutorials  t ON t.tutorial_id = fav.tutorial_id
        JOIN dbProj_categories c ON c.category_id = t.category_id
        JOIN dbProj_users      u ON u.user_id     = t.instructor_id
        LEFT JOIN dbProj_ratings r ON r.tutorial_id = t.tutorial_id
        WHERE fav.user_id = :uid
          AND fav.activity_type = 'favorite'
          AND t.status = 'published'
        GROUP BY t.tutorial_id, t.title, t.slug, t.thumbnail, t.difficulty,
                 t.duration_minutes, t.view_count, t.short_description, t.video_url,
                 c.category_name, u.full_name, fav.activity_date
        ORDER BY fav.activity_date DESC
    ");
    $stmt->bindValue(':uid', $current_user_id, PDO::PARAM_INT);
    $stmt->execute();
    $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('favorites query failed: ' . $e->getMessage());
    setFlashMessage('Could not load favorites. Please try again.', 'error');
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

            <!-- page header -->
            <div class="dashboard-header">
                <div>
                    <h1><i class="fas fa-heart" style="color:#ef4444;"></i> My Favorites</h1>
                    <p>Tutorials you saved to revisit later (<?= count($favorites) ?>)</p>
                </div>
            </div>

            <?php displayFlashMessage(); ?>

            <?php if (empty($favorites)): ?>
                <!-- empty state -->
                <div class="empty-state">
                    <i class="fas fa-heart"></i>
                    <h3>No favorites yet</h3>
                    <p>Tap the heart on any tutorial to save it here for quick access.</p>
                    <a href="browse-tutorials.php" class="btn btn-primary">
                        <i class="fas fa-search"></i> Browse Tutorials
                    </a>
                </div>
            <?php else: ?>
                <!-- favorite tutorial cards -->
                <div class="tutorials-grid-large">
                    <?php foreach ($favorites as $tut): ?>
                    <div class="tutorial-card" id="fav-card-<?= (int)$tut['tutorial_id'] ?>">

                        <!-- thumbnail with badge and remove-favorite heart -->
                        <div class="card-thumbnail">
                            <?php if (!empty($tut['thumbnail'])): ?>
                                <img src="<?= SITE_URL ?>/uploads/<?= e($tut['thumbnail']) ?>"
                                     alt="<?= e($tut['title']) ?>"
                                     onerror="this.onerror=null;this.src='<?= SITE_URL ?>/uploads/placeholder.svg'">
                            <?php else: ?>
                                <i class="fas fa-book thumb-icon"></i>
                            <?php endif; ?>

                            <span class="difficulty-badge difficulty-<?= e($tut['difficulty']) ?>">
                                <?= ucfirst(e($tut['difficulty'])) ?>
                            </span>

                            <!-- filled heart removes from favorites -->
                            <button class="favorite-btn active"
                                    onclick="removeFavorite(<?= (int)$tut['tutorial_id'] ?>, this)"
                                    title="Remove from favorites">
                                <i class="fas fa-heart" style="color:#ef4444;"></i>
                            </button>
                        </div>

                        <!-- card body -->
                        <div class="card-body">
                            <div class="category-tag">
                                <i class="fas fa-folder" aria-hidden="true"></i>
                                <?= e($tut['category_name']) ?>
                            </div>
                            <h3>
                                <?= e($tut['title']) ?>
                                <?php if (!empty($tut['video_url'])): ?>
                                    <span class="media-badge" title="Includes media"><i class="fas fa-paperclip"></i></span>
                                <?php endif; ?>
                            </h3>
                            <p class="description"><?= e($tut['short_description']) ?></p>

                            <div class="instructor">
                                <i class="fas fa-user-circle" style="font-size:20px;color:#cbd5e0;"></i>
                                <span><?= e($tut['instructor_name']) ?></span>
                            </div>

                            <div class="card-meta">
                                <span>
                                    <i class="fas fa-star" style="color:#d69e2e;"></i>
                                    <?= number_format((float)($tut['avg_rating'] ?? 0), 1) ?>
                                    <small>(<?= (int)($tut['rating_count'] ?? 0) ?>)</small>
                                </span>
                                <span>
                                    <i class="fas fa-eye"></i>
                                    <?= number_format($tut['view_count']) ?>
                                </span>
                                <?php if (!empty($tut['duration_minutes'])): ?>
                                <span>
                                    <i class="fas fa-clock"></i>
                                    <?= $tut['duration_minutes'] ?> min
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- card footer -->
                        <div class="card-footer">
                            <a href="<?= SITE_URL ?>/viewer/tutorial-view.php?slug=<?= urlencode($tut['slug']) ?>"
                               class="btn btn-primary" style="flex:1;justify-content:center;">
                                <i class="fas fa-play-circle" aria-hidden="true"></i> View Tutorial
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <script>
    // remove a favorite then drop its card
    function removeFavorite(tutorialId, btn) {
        if (!confirm('Remove this tutorial from your favorites?')) return;
        btn.disabled = true;

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        fetch('<?= SITE_URL ?>/api/toggle-favorite.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tutorial_id: tutorialId, csrf_token: csrfToken })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.favorited === false) {
                const card = document.getElementById('fav-card-' + tutorialId);
                if (card) card.remove();
                // show empty state if nothing left
                if (!document.querySelector('.tutorials-grid-large .tutorial-card')) {
                    window.location.reload();
                }
            } else {
                alert(data.message || 'Could not remove favorite. Please try again.');
                btn.disabled = false;
            }
        })
        .catch(() => {
            alert('Network error. Please try again.');
            btn.disabled = false;
        });
    }
    </script>
<script src="<?= asset('js/viewer.js') ?>"></script>
</body>
</html>
