<?php
/**
 * Tutorial View Page
 * Hasan Fardan - 202301686
 */

require_once '../includes/viewer-auth-check.php';
require_once '../classes/Tutorial.php';

$slug = isset($_GET['slug']) ? clean($_GET['slug']) : '';

if (empty($slug)) {
    setFlashMessage('Invalid tutorial link.', 'error');
    redirect('viewer/browse-tutorials.php');
}

$tutorialObj = new Tutorial();
$tutorial    = $tutorialObj->getBySlug($slug);

if (!$tutorial) {
    setFlashMessage('Tutorial not found.', 'error');
    redirect('viewer/browse-tutorials.php');
}

// log the view (no double count — fixed in Tutorial.php)
$tutorialObj->logView($tutorial['tutorial_id'], $current_user_id);

$database = new Database();
$conn     = $database->connect();

// ---- handle comment POST ----
$comment_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    if (!verifyCsrfFromPost()) {
        $comment_error = 'Invalid security token. Please try again.';
    } else {
        $comment_text = clean($_POST['comment']);
        if (empty($comment_text)) {
            $comment_error = 'Comment cannot be empty.';
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO dbProj_comments (tutorial_id, user_id, comment_text, status, created_at)
                 VALUES (:tid, :uid, :txt, 'approved', NOW())"
            );
            $stmt->bindParam(':tid', $tutorial['tutorial_id'], PDO::PARAM_INT);
            $stmt->bindParam(':uid', $current_user_id,         PDO::PARAM_INT);
            $stmt->bindParam(':txt', $comment_text,            PDO::PARAM_STR);
            if ($stmt->execute()) {
                setFlashMessage('Comment posted!', 'success');
                redirect('viewer/tutorial-view.php?slug=' . urlencode($slug));
            } else {
                $comment_error = 'Failed to post comment. Please try again.';
            }
        }
    }
}

// ---- handle rating POST ----
$rating_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rating'])) {
    if (!verifyCsrfFromPost()) {
        $rating_error = 'Invalid security token. Please try again.';
    } else {
        $rating_val = (int)$_POST['rating'];
        if ($rating_val < 1 || $rating_val > 5) {
            $rating_error = 'Please select a rating between 1 and 5.';
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO dbProj_ratings (tutorial_id, user_id, rating, rated_at)
                 VALUES (:tid, :uid, :r, NOW())
                 ON DUPLICATE KEY UPDATE rating = :r2, rated_at = NOW()"
            );
            $stmt->bindParam(':tid', $tutorial['tutorial_id'], PDO::PARAM_INT);
            $stmt->bindParam(':uid', $current_user_id,         PDO::PARAM_INT);
            $stmt->bindValue(':r',   $rating_val,              PDO::PARAM_INT);
            $stmt->bindValue(':r2',  $rating_val,              PDO::PARAM_INT);
            if ($stmt->execute()) {
                setFlashMessage('Rating submitted! Thank you.', 'success');
                redirect('viewer/tutorial-view.php?slug=' . urlencode($slug));
            } else {
                $rating_error = 'Failed to submit rating. Please try again.';
            }
        }
    }
}

// ---- fetch comments ----
$commentsStmt = $conn->prepare(
    "SELECT c.*, u.full_name AS user_name
     FROM dbProj_comments c
     JOIN dbProj_users u ON c.user_id = u.user_id
     WHERE c.tutorial_id = :tid AND c.status = 'approved'
     ORDER BY c.created_at DESC"
);
$commentsStmt->bindParam(':tid', $tutorial['tutorial_id'], PDO::PARAM_INT);
$commentsStmt->execute();
$comments = $commentsStmt->fetchAll();

// ---- fetch user's existing rating ----
$rStmt = $conn->prepare(
    "SELECT rating FROM dbProj_ratings
     WHERE tutorial_id = :tid AND user_id = :uid LIMIT 1"
);
$rStmt->bindParam(':tid', $tutorial['tutorial_id'], PDO::PARAM_INT);
$rStmt->bindParam(':uid', $current_user_id,         PDO::PARAM_INT);
$rStmt->execute();
$rRow        = $rStmt->fetch();
$user_rating = $rRow ? (int)$rRow['rating'] : 0;

$page_title  = $tutorial['title'];
$avg_rating  = number_format($tutorial['avg_rating'] ?? 0, 1);
$css_version = filemtime(__DIR__ . '/../assets/css/viewer.css');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($tutorial['title']) ?> - <?= SITE_NAME ?></title>
    <!-- cache-bust so updated CSS is always picked up -->
    <link rel="stylesheet" href="<?= asset('css/viewer.css') ?>?v=<?= $css_version ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/viewer-nav.php'; ?>

    <div class="tview-wrap">

        <!-- back button -->
        <div class="tview-back">
            <a href="<?= SITE_URL ?>/viewer/browse-tutorials.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Browse
            </a>
        </div>

        <!-- hero header -->
        <div class="tview-hero">
            <div class="tview-breadcrumb">
                <a href="<?= SITE_URL ?>/viewer/dashboard.php">Home</a>
                <i class="fas fa-chevron-right"></i>
                <a href="<?= SITE_URL ?>/viewer/browse-tutorials.php">Tutorials</a>
                <i class="fas fa-chevron-right"></i>
                <span><?= e($tutorial['category_name']) ?></span>
            </div>

            <h1><?= e($tutorial['title']) ?></h1>
            <p class="tview-subtitle"><?= e($tutorial['short_description']) ?></p>

            <div class="tview-meta">
                <div class="tview-meta-item">
                    <i class="fas fa-user"></i>
                    <div>
                        <small>Instructor</small>
                        <strong><?= e($tutorial['instructor_name']) ?></strong>
                    </div>
                </div>
                <div class="tview-meta-item">
                    <i class="fas fa-star"></i>
                    <div>
                        <small>Rating</small>
                        <strong><?= $avg_rating ?> / 5 (<?= $tutorial['rating_count'] ?> votes)</strong>
                    </div>
                </div>
                <div class="tview-meta-item">
                    <i class="fas fa-eye"></i>
                    <div>
                        <small>Views</small>
                        <strong><?= number_format($tutorial['view_count']) ?></strong>
                    </div>
                </div>
                <div class="tview-meta-item">
                    <i class="fas fa-clock"></i>
                    <div>
                        <small>Duration</small>
                        <strong><?= $tutorial['duration_minutes'] ?> min</strong>
                    </div>
                </div>
                <div class="tview-meta-item">
                    <i class="fas fa-signal"></i>
                    <div>
                        <small>Level</small>
                        <strong><?= ucfirst($tutorial['difficulty']) ?></strong>
                    </div>
                </div>
            </div>

            <div class="tview-actions">
                <button class="btn btn-outline-white" onclick="shareTutorial()">
                    <i class="fas fa-share-alt"></i> Share
                </button>
            </div>
        </div>

        <?php displayFlashMessage(); ?>

        <!-- two-column layout: main content + sidebar -->
        <div class="tview-body">

            <!-- LEFT: main content -->
            <div class="tview-main">

                <?php if (!empty($tutorial['video_url'])): ?>
                <div class="tview-card">
                    <h2 class="tview-section-title"><i class="fas fa-play-circle"></i> Video</h2>
                    <div class="tview-video-wrapper">
                        <iframe src="<?= e($tutorial['video_url']) ?>" frameborder="0"
                                allow="accelerometer; autoplay; encrypted-media; gyroscope"
                                allowfullscreen></iframe>
                    </div>
                </div>
                <?php endif; ?>

                <!-- tutorial body content -->
                <div class="tview-card">
                    <h2 class="tview-section-title"><i class="fas fa-book-open"></i> Tutorial Content</h2>
                    <div class="tview-content-body">
                        <?= $tutorial['content'] ?>
                    </div>
                </div>

                <!-- rating -->
                <div class="tview-card">
                    <h2 class="tview-section-title"><i class="fas fa-star"></i> Rate This Tutorial</h2>
                    <p class="tview-hint">
                        <?= $user_rating
                            ? 'You rated this ' . $user_rating . '/5 — you can update your rating below.'
                            : 'Help others by rating this tutorial.' ?>
                    </p>

                    <?php if ($rating_error): ?>
                        <div class="alert alert-error"><?= e($rating_error) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="" class="tview-rating-form">
                        <?php csrfField(); ?>
                        <div class="tview-stars">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" name="rating" value="<?= $i ?>"
                                       id="star<?= $i ?>" <?= $user_rating === $i ? 'checked' : '' ?>>
                                <label for="star<?= $i ?>"><i class="fas fa-star"></i></label>
                            <?php endfor; ?>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i>
                            <?= $user_rating ? 'Update Rating' : 'Submit Rating' ?>
                        </button>
                    </form>
                </div>

                <!-- comments -->
                <div class="tview-card">
                    <h2 class="tview-section-title">
                        <i class="fas fa-comments"></i> Comments
                        <span class="tview-count"><?= count($comments) ?></span>
                    </h2>

                    <?php if ($comment_error): ?>
                        <div class="alert alert-error"><?= e($comment_error) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="" class="tview-comment-form">
                        <?php csrfField(); ?>
                        <i class="fas fa-user-circle tview-avatar-icon"></i>
                        <div class="tview-comment-right">
                            <textarea name="comment" rows="3"
                                      placeholder="Share your thoughts about this tutorial..." required></textarea>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Post Comment
                            </button>
                        </div>
                    </form>

                    <?php if (empty($comments)): ?>
                        <div class="tview-empty-comments">
                            <i class="fas fa-comment-slash"></i>
                            <p>No comments yet — be the first!</p>
                        </div>
                    <?php else: ?>
                        <div class="tview-comments-list">
                            <?php foreach ($comments as $c): ?>
                            <div class="tview-comment">
                                <i class="fas fa-user-circle tview-comment-avatar"></i>
                                <div class="tview-comment-body">
                                    <div class="tview-comment-meta">
                                        <strong><?= e($c['user_name']) ?></strong>
                                        <span><?= timeAgo($c['created_at']) ?></span>
                                    </div>
                                    <p><?= e($c['comment_text']) ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div><!-- /tview-main -->

            <!-- RIGHT: sidebar -->
            <aside class="tview-sidebar">

                <div class="tview-sidebar-card">
                    <h3><i class="fas fa-info-circle"></i> About</h3>
                    <ul class="tview-info-list">
                        <li><i class="fas fa-calendar-alt"></i>
                            <span>Published <?= timeAgo($tutorial['created_at']) ?></span>
                        </li>
                        <li><i class="fas fa-folder"></i>
                            <span><?= e($tutorial['category_name']) ?></span>
                        </li>
                        <li><i class="fas fa-signal"></i>
                            <span><?= ucfirst($tutorial['difficulty']) ?></span>
                        </li>
                        <li><i class="fas fa-clock"></i>
                            <span><?= $tutorial['duration_minutes'] ?> minutes</span>
                        </li>
                        <li><i class="fas fa-eye"></i>
                            <span><?= number_format($tutorial['view_count']) ?> views</span>
                        </li>
                    </ul>
                </div>

                <?php if (!empty($tutorial['tags'])): ?>
                <div class="tview-sidebar-card">
                    <h3><i class="fas fa-tags"></i> Tags</h3>
                    <div class="tview-tag-list">
                        <?php foreach ($tutorial['tags'] as $tag): ?>
                            <span class="tview-tag"><?= e($tag['tag_name']) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($tutorial['media'])): ?>
                <div class="tview-sidebar-card">
                    <h3><i class="fas fa-download"></i> Downloads</h3>
                    <div class="tview-downloads">
                        <?php foreach ($tutorial['media'] as $m): ?>
                            <?php if ($m['media_type'] === 'document'): ?>
                            <a href="<?= SITE_URL ?>/uploads/<?= e($m['file_path']) ?>"
                               class="btn btn-outline btn-sm" download style="margin-bottom:8px;width:100%;">
                                <i class="fas fa-file-download"></i> <?= e($m['file_name']) ?>
                            </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="tview-sidebar-card">
                    <h3><i class="fas fa-star"></i> Your Rating</h3>
                    <?php if ($user_rating): ?>
                        <div class="tview-your-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star" style="color:<?= $i <= $user_rating ? '#ffc107' : '#e2e8f0' ?>;font-size:22px;"></i>
                            <?php endfor; ?>
                            <p style="margin-top:8px;color:#718096;font-size:13px;">You rated this <?= $user_rating ?>/5</p>
                        </div>
                    <?php else: ?>
                        <p style="color:#718096;font-size:13px;">You haven't rated this tutorial yet. Scroll down to rate it!</p>
                    <?php endif; ?>
                </div>

            </aside>
        </div><!-- /tview-body -->
    </div><!-- /tview-wrap -->

    <script>
        function shareTutorial() {
            if (navigator.share) {
                navigator.share({
                    title: '<?= addslashes(e($tutorial['title'])) ?>',
                    url: window.location.href
                });
            } else {
                const dummy = document.createElement('input');
                document.body.appendChild(dummy);
                dummy.value = window.location.href;
                dummy.select();
                document.execCommand('copy');
                document.body.removeChild(dummy);
                alert('Link copied to clipboard!');
            }
        }
    </script>
</body>
</html>
