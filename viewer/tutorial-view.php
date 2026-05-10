<?php
/**
 * Tutorial View Page
 * Hasan Fardan - 202301686
 */

require_once '../includes/viewer-auth-check.php';
require_once '../classes/Tutorial.php';

$page_title = 'View Tutorial';

// Bug 8 fix: this page is linked with ?slug=, read slug not id
$slug = isset($_GET['slug']) ? clean($_GET['slug']) : '';

if (empty($slug)) {
    setFlashMessage('Invalid tutorial', 'error');
    redirect('viewer/browse-tutorials.php');
}

$tutorialObj = new Tutorial();
$tutorial    = $tutorialObj->getBySlug($slug);

if (!$tutorial) {
    setFlashMessage('Tutorial not found', 'error');
    redirect('viewer/browse-tutorials.php');
}

// Bug 2 fix is in Tutorial.php logView() — no double count here
$tutorialObj->logView($tutorial['tutorial_id'], $current_user_id);

$database = new Database();
$conn     = $database->connect();

// Handle comment submission
$comment_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    // Bug 3 fix: CSRF is now verified (form now includes csrfField())
    if (!verifyCsrfFromPost()) {
        $comment_error = 'Invalid security token. Please try again.';
    } else {
        $comment_text = clean($_POST['comment']);
        if (empty($comment_text)) {
            $comment_error = 'Comment cannot be empty.';
        } else {
            $commentQuery = "INSERT INTO dbProj_comments (tutorial_id, user_id, comment_text, created_at)
                            VALUES (:tutorial_id, :user_id, :comment_text, NOW())";
            $commentStmt  = $conn->prepare($commentQuery);
            $commentStmt->bindParam(':tutorial_id',  $tutorial['tutorial_id'], PDO::PARAM_INT);
            $commentStmt->bindParam(':user_id',      $current_user_id,         PDO::PARAM_INT);
            $commentStmt->bindParam(':comment_text', $comment_text,            PDO::PARAM_STR);

            if ($commentStmt->execute()) {
                setFlashMessage('Comment posted successfully!', 'success');
                redirect('viewer/tutorial-view.php?slug=' . urlencode($slug));
            } else {
                $comment_error = 'Failed to post comment. Please try again.';
            }
        }
    }
}

// Handle rating submission
$rating_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rating'])) {
    if (!verifyCsrfFromPost()) {
        $rating_error = 'Invalid security token. Please try again.';
    } else {
        $rating_val = (int)$_POST['rating'];
        if ($rating_val < 1 || $rating_val > 5) {
            $rating_error = 'Invalid rating value.';
        } else {
            $ratingQuery = "INSERT INTO dbProj_ratings (tutorial_id, user_id, rating, rated_at)
                            VALUES (:tutorial_id, :user_id, :rating, NOW())
                            ON DUPLICATE KEY UPDATE rating = :rating2, rated_at = NOW()";
            $ratingStmt  = $conn->prepare($ratingQuery);
            $ratingStmt->bindParam(':tutorial_id', $tutorial['tutorial_id'], PDO::PARAM_INT);
            $ratingStmt->bindParam(':user_id',     $current_user_id,         PDO::PARAM_INT);
            $ratingStmt->bindValue(':rating',      $rating_val,              PDO::PARAM_INT);
            $ratingStmt->bindValue(':rating2',     $rating_val,              PDO::PARAM_INT);

            if ($ratingStmt->execute()) {
                setFlashMessage('Rating submitted! Thank you.', 'success');
                redirect('viewer/tutorial-view.php?slug=' . urlencode($slug));
            } else {
                $rating_error = 'Failed to submit rating. Please try again.';
            }
        }
    }
}

// Get comments
$commentsQuery = "SELECT c.*, u.full_name as user_name
                  FROM dbProj_comments c
                  JOIN dbProj_users u ON c.user_id = u.user_id
                  WHERE c.tutorial_id = :tutorial_id AND c.status = 'approved'
                  ORDER BY c.created_at DESC";
$commentsStmt  = $conn->prepare($commentsQuery);
$commentsStmt->bindParam(':tutorial_id', $tutorial['tutorial_id'], PDO::PARAM_INT);
$commentsStmt->execute();
$comments = $commentsStmt->fetchAll();

// Get user's existing rating if any
$userRatingQuery = "SELECT rating FROM dbProj_ratings
                    WHERE tutorial_id = :tutorial_id AND user_id = :user_id LIMIT 1";
$userRatingStmt  = $conn->prepare($userRatingQuery);
$userRatingStmt->bindParam(':tutorial_id', $tutorial['tutorial_id'], PDO::PARAM_INT);
$userRatingStmt->bindParam(':user_id',     $current_user_id,         PDO::PARAM_INT);
$userRatingStmt->execute();
$user_rating_row = $userRatingStmt->fetch();
$user_rating     = $user_rating_row ? (int)$user_rating_row['rating'] : 0;

// Bug 4+5 fix: use correct variable name and remove undefined is_favorited
$tutorial_id = $tutorial['tutorial_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($tutorial['title']) ?> - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= asset('css/viewer.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/viewer-nav.php'; ?>

    <div class="tutorial-view-container">
        <div class="back-navigation">
            <a href="browse-tutorials.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i>
                Back to Browse
            </a>
        </div>

        <div class="tutorial-header">
            <div class="tutorial-header-content">
                <div class="breadcrumb">
                    <a href="../viewer/dashboard.php">Home</a>
                    <i class="fas fa-chevron-right"></i>
                    <a href="browse-tutorials.php">Tutorials</a>
                    <i class="fas fa-chevron-right"></i>
                    <span><?= e($tutorial['category_name']) ?></span>
                </div>

                <h1><?= e($tutorial['title']) ?></h1>
                <p class="subtitle"><?= e($tutorial['short_description']) ?></p>

                <div class="tutorial-meta">
                    <div class="meta-item">
                        <i class="fas fa-user"></i>
                        <div>
                            <small>Instructor</small>
                            <strong><?= e($tutorial['instructor_name']) ?></strong>
                        </div>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-star"></i>
                        <div>
                            <small>Rating</small>
                            <strong><?= number_format($tutorial['avg_rating'], 1) ?> (<?= $tutorial['rating_count'] ?>)</strong>
                        </div>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-eye"></i>
                        <div>
                            <small>Views</small>
                            <strong><?= number_format($tutorial['view_count']) ?></strong>
                        </div>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-clock"></i>
                        <div>
                            <small>Duration</small>
                            <strong><?= $tutorial['duration_minutes'] ?> min</strong>
                        </div>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-signal"></i>
                        <div>
                            <small>Level</small>
                            <strong><?= ucfirst($tutorial['difficulty']) ?></strong>
                        </div>
                    </div>
                </div>

                <div class="tutorial-actions">
                    <!-- Bug 4 fix: use $tutorial_id (defined above) not bare $tutorial_id which was undefined -->
                    <button class="btn btn-outline" onclick="shareTutorial()">
                        <i class="fas fa-share-alt"></i>
                        Share
                    </button>
                </div>
            </div>
        </div>

        <?php displayFlashMessage(); ?>

        <div class="tutorial-content-wrapper">
            <div class="tutorial-main-content">

                <?php if (!empty($tutorial['video_url'])): ?>
                <section class="video-section">
                    <div class="video-wrapper">
                        <iframe src="<?= e($tutorial['video_url']) ?>" frameborder="0" allowfullscreen></iframe>
                    </div>
                </section>
                <?php endif; ?>

                <section class="content-section">
                    <h2><i class="fas fa-book-open"></i> Tutorial Content</h2>
                    <div class="tutorial-body">
                        <?= $tutorial['content'] ?>
                    </div>
                </section>

                <!-- Rating Section -->
                <section class="rating-section">
                    <h2><i class="fas fa-star"></i> Rate This Tutorial</h2>
                    <p>Help others by sharing your experience <?= $user_rating ? '(Your current rating: ' . $user_rating . '/5)' : '' ?></p>

                    <?php if (!empty($rating_error)): ?>
                        <div class="alert alert-error"><?= e($rating_error) ?></div>
                    <?php endif; ?>

                    <!-- Bug 3 fix: csrfField() added to rating form -->
                    <form method="POST" action="" class="rating-form">
                        <?php csrfField(); ?>
                        <div class="star-rating-input">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" name="rating" value="<?= $i ?>" id="star<?= $i ?>"
                                       <?= $user_rating === $i ? 'checked' : '' ?>>
                                <label for="star<?= $i ?>"><i class="fas fa-star"></i></label>
                            <?php endfor; ?>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <?= $user_rating ? 'Update Rating' : 'Submit Rating' ?>
                        </button>
                    </form>
                </section>

                <!-- Comments Section -->
                <section class="comments-section">
                    <h2><i class="fas fa-comments"></i> Comments (<?= count($comments) ?>)</h2>

                    <?php if (!empty($comment_error)): ?>
                        <div class="alert alert-error"><?= e($comment_error) ?></div>
                    <?php endif; ?>

                    <!-- Bug 3 fix: csrfField() added to comment form -->
                    <form method="POST" action="" class="comment-form">
                        <?php csrfField(); ?>
                        <div class="user-avatar">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="comment-input-wrapper">
                            <textarea name="comment" placeholder="Share your thoughts..." rows="3" required></textarea>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i>
                                Post Comment
                            </button>
                        </div>
                    </form>

                    <?php if (empty($comments)): ?>
                        <div class="empty-comments">
                            <i class="fas fa-comment-slash"></i>
                            <p>No comments yet. Be the first to share your thoughts!</p>
                        </div>
                    <?php else: ?>
                        <div class="comments-list">
                            <?php foreach ($comments as $comment): ?>
                                <div class="comment-item">
                                    <div class="comment-avatar">
                                        <i class="fas fa-user-circle"></i>
                                    </div>
                                    <div class="comment-content">
                                        <div class="comment-header">
                                            <strong><?= e($comment['user_name']) ?></strong>
                                            <span class="comment-date"><?= timeAgo($comment['created_at']) ?></span>
                                        </div>
                                        <p><?= e($comment['comment_text']) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <aside class="tutorial-sidebar">
                <div class="sidebar-card">
                    <h3><i class="fas fa-info-circle"></i> About This Tutorial</h3>
                    <ul class="info-list">
                        <li><i class="fas fa-calendar"></i><span>Published <?= timeAgo($tutorial['created_at']) ?></span></li>
                        <li><i class="fas fa-folder"></i><span>Category: <?= e($tutorial['category_name']) ?></span></li>
                        <li><i class="fas fa-language"></i><span>Language: English</span></li>
                    </ul>
                </div>

                <?php if (!empty($tutorial['tags'])): ?>
                <div class="sidebar-card">
                    <h3><i class="fas fa-tags"></i> Tags</h3>
                    <div class="tag-list">
                        <?php foreach ($tutorial['tags'] as $tag): ?>
                            <span class="tag-badge"><?= e($tag['tag_name']) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($tutorial['media'])): ?>
                <div class="sidebar-card">
                    <h3><i class="fas fa-paperclip"></i> Downloads</h3>
                    <?php foreach ($tutorial['media'] as $media): ?>
                        <?php if ($media['media_type'] === 'document'): ?>
                            <a href="<?= SITE_URL ?>/uploads/<?= e($media['file_path']) ?>"
                               class="btn btn-outline btn-sm" download>
                                <i class="fas fa-download"></i> <?= e($media['file_name']) ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>

    <script>
        function shareTutorial() {
            if (navigator.share) {
                navigator.share({
                    title: '<?= e($tutorial['title']) ?>',
                    url: window.location.href
                });
            } else {
                prompt('Copy this link:', window.location.href);
            }
        }
    </script>
</body>
</html>
