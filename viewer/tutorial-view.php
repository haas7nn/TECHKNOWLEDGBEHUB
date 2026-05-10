<?php
/**
 * Tutorial View Page
 * Hasan Fardan - 202301686
 */

// Make sure the user is logged in before they can view tutorials
require_once '../includes/viewer-auth-check.php';

// Pull in the Tutorial class for database operations
require_once '../classes/Tutorial.php';

// === GET THE TUTORIAL SLUG FROM THE URL ===
// This is the friendly URL identifier (like "learn-php-basics")
$slug = isset($_GET['slug']) ? clean($_GET['slug']) : '';

// If no slug was provided, send them back to browse with an error
if (empty($slug)) {
    setFlashMessage('Invalid tutorial link.', 'error');
    redirect('viewer/browse-tutorials.php');
}

// === FETCH THE TUTORIAL FROM DATABASE ===
$tutorialObj = new Tutorial();
$tutorial    = $tutorialObj->getBySlug($slug);

// If the slug doesn't match anything, show an error and redirect
if (!$tutorial) {
    setFlashMessage('Tutorial not found.', 'error');
    redirect('viewer/browse-tutorials.php');
}

// === LOG THIS VIEW ===
// Track that this user viewed this tutorial (prevents double-counting in Tutorial.php)
$tutorialObj->logView($tutorial['tutorial_id'], $current_user_id);

// === CONNECT TO DATABASE FOR DIRECT QUERIES ===
// We need raw SQL for comments, ratings, etc.
$database = new Database();
$conn     = $database->connect();

// ============================================
// ---- HANDLE COMMENT POST ----
// ============================================
$comment_error = '';

// Check if the user just submitted a comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    
    // First, verify the CSRF token so we know this is a legit request
    if (!verifyCsrfFromPost()) {
        $comment_error = 'Invalid security token. Please try again.';
    } else {
        // Clean up the comment text to prevent nasty stuff
        $comment_text = clean($_POST['comment']);
        
        // Don't let them post empty comments
        if (empty($comment_text)) {
            $comment_error = 'Comment cannot be empty.';
        } else {
            // Insert the comment into the database (auto-approved for now)
            $stmt = $conn->prepare(
                "INSERT INTO dbProj_comments (tutorial_id, user_id, comment_text, status, created_at)
                 VALUES (:tid, :uid, :txt, 'approved', NOW())"
            );
            $stmt->bindParam(':tid', $tutorial['tutorial_id'], PDO::PARAM_INT);
            $stmt->bindParam(':uid', $current_user_id,         PDO::PARAM_INT);
            $stmt->bindParam(':txt', $comment_text,            PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                // Success! Show a message and refresh the page so they see their comment
                setFlashMessage('Comment posted!', 'success');
                redirect('viewer/tutorial-view.php?slug=' . urlencode($slug));
            } else {
                $comment_error = 'Failed to post comment. Please try again.';
            }
        }
    }
}

// ============================================
// ---- HANDLE RATING POST ----
// ============================================
$rating_error = '';

// Check if the user just submitted a rating
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rating'])) {
    
    // Again, verify CSRF first
    if (!verifyCsrfFromPost()) {
        $rating_error = 'Invalid security token. Please try again.';
    } else {
        $rating_val = (int)$_POST['rating'];
        
        // Ratings must be between 1 and 5 stars
        if ($rating_val < 1 || $rating_val > 5) {
            $rating_error = 'Please select a rating between 1 and 5.';
        } else {
            // Insert or update their rating (ON DUPLICATE KEY lets them change their mind)
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

// ============================================
// ---- FETCH EXISTING COMMENTS ----
// ============================================
// Get all approved comments for this tutorial, newest first, with user names
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

// ============================================
// ---- CHECK IF USER ALREADY RATED THIS ----
// ============================================
// See if this user has already rated this tutorial so we can show/update it
$rStmt = $conn->prepare(
    "SELECT rating FROM dbProj_ratings
     WHERE tutorial_id = :tid AND user_id = :uid LIMIT 1"
);
$rStmt->bindParam(':tid', $tutorial['tutorial_id'], PDO::PARAM_INT);
$rStmt->bindParam(':uid', $current_user_id,         PDO::PARAM_INT);
$rStmt->execute();
$rRow        = $rStmt->fetch();
$user_rating = $rRow ? (int)$rRow['rating'] : 0;  // 0 means they haven't rated yet

// === SET UP PAGE VARIABLES ===
$page_title  = $tutorial['title'];
$avg_rating  = number_format($tutorial['avg_rating'] ?? 0, 1);
// Cache-bust the CSS so users always get the latest version
$css_version = filemtime(__DIR__ . '/../assets/css/viewer.css');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($tutorial['title']) ?> - <?= SITE_NAME ?></title>
    
    <!-- Cache-bust so updated CSS is always picked up -->
    <link rel="stylesheet" href="<?= asset('css/viewer.css') ?>?v=<?= $css_version ?>">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Top navigation bar -->
    <?php include '../includes/viewer-nav.php'; ?>

    <div class="tview-wrap">

        <!-- Back button to return to the browse page -->
        <div class="tview-back">
            <a href="<?= SITE_URL ?>/viewer/browse-tutorials.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Browse
            </a>
        </div>

        <!-- Hero header section with tutorial info -->
        <div class="tview-hero">
            <!-- Breadcrumb: Home > Tutorials > Category -->
            <div class="tview-breadcrumb">
                <a href="<?= SITE_URL ?>/viewer/dashboard.php">Home</a>
                <i class="fas fa-chevron-right"></i>
                <a href="<?= SITE_URL ?>/viewer/browse-tutorials.php">Tutorials</a>
                <i class="fas fa-chevron-right"></i>
                <span><?= e($tutorial['category_name']) ?></span>
            </div>

            <!-- Main title and description -->
            <h1><?= e($tutorial['title']) ?></h1>
            <p class="tview-subtitle"><?= e($tutorial['short_description']) ?></p>

            <!-- Meta info row: instructor, rating, views, duration, difficulty -->
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

            <!-- Share button -->
            <div class="tview-actions">
                <button class="btn btn-outline-white" onclick="shareTutorial()">
                    <i class="fas fa-share-alt"></i> Share
                </button>
            </div>
        </div>

        <!-- Show any flash messages (success/error from form submissions) -->
        <?php displayFlashMessage(); ?>

        <!-- Two-column layout: main content on left, sidebar on right -->
        <div class="tview-body">

            <!-- LEFT COLUMN: Main content -->
            <div class="tview-main">

                <!-- Video section (only if there's a video URL) -->
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

                <!-- Tutorial body content (the actual lesson) -->
                <div class="tview-card">
                    <h2 class="tview-section-title"><i class="fas fa-book-open"></i> Tutorial Content</h2>
                    <div class="tview-content-body">
                        <?= $tutorial['content'] ?>
                    </div>
                </div>

                <!-- Rating section -->
                <div class="tview-card">
                    <h2 class="tview-section-title"><i class="fas fa-star"></i> Rate This Tutorial</h2>
                    
                    <!-- Tell them if they already rated it -->
                    <p class="tview-hint">
                        <?= $user_rating
                            ? 'You rated this ' . $user_rating . '/5 — you can update your rating below.'
                            : 'Help others by rating this tutorial.' ?>
                    </p>

                    <!-- Show any rating error messages -->
                    <?php if ($rating_error): ?>
                        <div class="alert alert-error"><?= e($rating_error) ?></div>
                    <?php endif; ?>

                    <!-- Star rating form -->
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

                <!-- Comments section -->
                <div class="tview-card">
                    <h2 class="tview-section-title">
                        <i class="fas fa-comments"></i> Comments
                        <span class="tview-count"><?= count($comments) ?></span>
                    </h2>

                    <!-- Show any comment error messages -->
                    <?php if ($comment_error): ?>
                        <div class="alert alert-error"><?= e($comment_error) ?></div>
                    <?php endif; ?>

                    <!-- Comment input form -->
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

                    <!-- Display existing comments or empty state -->
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

            <!-- RIGHT COLUMN: Sidebar -->
            <aside class="tview-sidebar">

                <!-- About info card -->
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

                <!-- Tags (only if there are any) -->
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

                <!-- Downloadable files (only documents) -->
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

                <!-- User's current rating display -->
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
        // Share the tutorial using native share API, or fallback to copying the link
        function shareTutorial() {
            if (navigator.share) {
                // Mobile browsers: use native share sheet
                navigator.share({
                    title: '<?= addslashes(e($tutorial['title'])) ?>',
                    url: window.location.href
                });
            } else {
                // Desktop fallback: copy URL to clipboard
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