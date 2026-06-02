<?php
// tutorial view for guests and logged-in viewers
require_once '../config/config.php';
require_once '../classes/Tutorial.php';

// creators can open a tutorial in read-only preview
// they just dont get the rating comment or complete forms

// resolve visitor type and id
$is_logged_in       = isLoggedIn() && (isViewer() || isAdmin());
$current_user_id    = $is_logged_in ? getCurrentUserId()    : 0;
$current_user_name  = $is_logged_in ? getCurrentUserName()  : 'Guest';
$current_user_role  = $is_logged_in ? getCurrentUserRole()  : 'guest';

// slug from url
$slug = isset($_GET['slug']) ? clean($_GET['slug']) : '';

// no slug redirect to browse
if (empty($slug)) {
    redirect($is_logged_in ? 'viewer/browse-tutorials.php' : 'public/search.php');
}

// fetch tutorial by slug
$tutorialObj = new Tutorial();
$tutorial    = $tutorialObj->getBySlug($slug);

// 404 fallback redirect to browse
if (!$tutorial) {
    redirect($is_logged_in ? 'viewer/browse-tutorials.php' : 'public/search.php');
}

// logged-in uses logView for activity tracking
// guest gets direct view_count increment instead
if ($current_user_id > 0) {
    $tutorialObj->logView($tutorial['tutorial_id'], $current_user_id);
} else {
    $tmpDb = new Database();
    $tmpConn = $tmpDb->connect();
    if ($tmpConn) {
        $tmpConn->prepare(
            "UPDATE dbProj_tutorials SET view_count = view_count + 1 WHERE tutorial_id = :id"
        )->execute([':id' => $tutorial['tutorial_id']]);
    }
}

// main db connection
$database = new Database();
$conn     = $database->connect();

// redirect if db failed
if (!$conn) {
    redirect($is_logged_in ? 'viewer/browse-tutorials.php' : 'public/search.php');
}

// handle new comment submission
$comment_error = '';
if ($is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    if (!verifyCsrfFromPost()) {
        $comment_error = 'Invalid security token. Please try again.';
    } else {
        $comment_text = clean($_POST['comment']);
        if (empty($comment_text)) {
            $comment_error = 'Comment cannot be empty.';
        } else {
            // insert approved comment
            $stmt = $conn->prepare(
                "INSERT INTO dbProj_comments (tutorial_id, user_id, comment_text, status, created_at)
                 VALUES (:tid, :uid, :txt, 'approved', NOW())"
            );
            $stmt->bindParam(':tid', $tutorial['tutorial_id'], PDO::PARAM_INT);
            $stmt->bindParam(':uid', $current_user_id,         PDO::PARAM_INT);
            $stmt->bindParam(':txt', $comment_text,            PDO::PARAM_STR);
            if ($stmt->execute()) {
                setFlashMessage('Comment posted successfully!', 'success');
                redirect('viewer/tutorial-view.php?slug=' . urlencode($slug));
            } else {
                $comment_error = 'Failed to post comment. Please try again.';
            }
        }
    }
}

// handle rating submission
$rating_error = '';
if ($is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rating'])) {
    if (!verifyCsrfFromPost()) {
        $rating_error = 'Invalid security token. Please try again.';
    } else {
        $rating_val = (int)$_POST['rating'];
        // must be 1-5
        if ($rating_val < 1 || $rating_val > 5) {
            $rating_error = 'Please select a rating between 1 and 5.';
        } else {
            // upsert rating on duplicate key
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

// fetch approved comments newest first
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

// load user's existing rating if any
$user_rating = 0;
if ($is_logged_in && $current_user_id > 0) {
    $rStmt = $conn->prepare(
        "SELECT rating FROM dbProj_ratings
         WHERE tutorial_id = :tid AND user_id = :uid LIMIT 1"
    );
    $rStmt->bindParam(':tid', $tutorial['tutorial_id'], PDO::PARAM_INT);
    $rStmt->bindParam(':uid', $current_user_id,         PDO::PARAM_INT);
    $rStmt->execute();
    $rRow        = $rStmt->fetch();
    $user_rating = $rRow ? (int)$rRow['rating'] : 0;
}

// check if this viewer already completed the tutorial
$already_completed = false;
if (isLoggedIn() && hasRole('viewer') && $current_user_id > 0) {
    $cStmt = $conn->prepare(
        "SELECT 1 FROM dbProj_user_activity
         WHERE user_id = :uid AND tutorial_id = :tid AND activity_type = 'complete' LIMIT 1"
    );
    $cStmt->bindParam(':uid', $current_user_id,         PDO::PARAM_INT);
    $cStmt->bindParam(':tid', $tutorial['tutorial_id'], PDO::PARAM_INT);
    $cStmt->execute();
    $already_completed = (bool)$cStmt->fetchColumn();
}

// check if this viewer already favorited the tutorial
$already_favorited = false;
if ($is_logged_in && $current_user_id > 0) {
    $fStmt = $conn->prepare(
        "SELECT 1 FROM dbProj_user_activity
         WHERE user_id = :uid AND tutorial_id = :tid AND activity_type = 'favorite' LIMIT 1"
    );
    $fStmt->bindParam(':uid', $current_user_id,         PDO::PARAM_INT);
    $fStmt->bindParam(':tid', $tutorial['tutorial_id'], PDO::PARAM_INT);
    $fStmt->execute();
    $already_favorited = (bool)$fStmt->fetchColumn();
}

// prep page vars
$page_title  = $tutorial['title'];
$avg_rating  = number_format($tutorial['avg_rating'] ?? 0, 1);
$css_version = @filemtime(__DIR__ . '/../assets/css/viewer.css') ?: time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($tutorial['title']) ?> | <?= SITE_NAME ?></title>
    <!-- shared tokens must load before viewer.css -->
    <link rel="stylesheet" href="<?= asset('css/shared.css') ?>">
    <!-- cache-busted viewer styles -->
    <link rel="stylesheet" href="<?= asset('css/viewer.css') ?>?v=<?= $css_version ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- star widget uses vanilla JS -->
    <style>
        /* completion card */
        .complete-state { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; }
        .complete-state .ctext strong { font-size:15px; color:var(--c-text); display:block; }
        .complete-state .ctext p { font-size:13px; color:var(--c-text-3); margin-top:2px; }
        .complete-state .ctext p a { color:var(--c-primary); font-weight:600; }
        .complete-state .btn-success { flex-shrink:0; }
        .complete-state.done { justify-content:flex-start; }
        .complete-state.done .ctext strong { color:#065f46; }
        .complete-icon { width:46px; height:46px; border-radius:50%; background:var(--c-success-bg);
                         color:var(--c-success); display:flex; align-items:center; justify-content:center;
                         font-size:24px; flex-shrink:0; }
        /* favorite button heart state in the hero */
        #favBtn .fa-heart { transition:color .15s; }
        #favBtn.is-fav .fa-heart { color:#ff8fa3; }

        /* hero header: title left, actions top-right */
        .tview-hero-top { display:flex; align-items:flex-start; justify-content:space-between; gap:24px; margin-bottom:24px; }
        .tview-hero-head { flex:1; min-width:0; }
        .tview-hero-head h1 { margin-bottom:12px; }
        .tview-hero-head .tview-subtitle { margin-bottom:0; }
        .tview-actions { display:flex; gap:10px; flex-shrink:0; margin:0; }
        .tview-hero .tview-meta { margin-bottom:0; }
        @media (max-width: 768px) {
            .tview-hero-top { flex-direction:column; gap:18px; }
            .tview-actions { width:100%; }
            .tview-actions .btn-outline-white { flex:1; justify-content:center; }
        }
    </style>
</head>
<body>
    <?php if ($is_logged_in): ?>
        <?php include '../includes/viewer-nav.php'; ?>
    <?php else: ?>
        <!-- minimal nav for guests -->
        <nav class="viewer-navbar">
            <div class="navbar-brand">
                <a href="<?= SITE_URL ?>">
                    <i class="fas fa-graduation-cap" aria-hidden="true"></i>
                    <span><?= SITE_NAME ?></span>
                </a>
            </div>
            <div class="navbar-menu" style="margin-left:auto;display:flex;align-items:center;gap:6px;">
                <a href="<?= SITE_URL ?>/public/search.php" class="nav-link">
                    <i class="fas fa-search" aria-hidden="true"></i><span>Browse</span>
                </a>
                <a href="<?= SITE_URL ?>/auth/login.php" class="nav-link">
                    <i class="fas fa-sign-in-alt" aria-hidden="true"></i><span>Login</span>
                </a>
                <a href="<?= SITE_URL ?>/auth/register.php" class="nav-link"
                   style="background:var(--c-primary);color:#fff;border-radius:var(--r-pill);padding:7px 16px;">
                    <i class="fas fa-user-plus" aria-hidden="true"></i><span>Register</span>
                </a>
            </div>
        </nav>
    <?php endif; ?>

    <div class="tview-wrap">

        <!-- back link -->
        <div class="tview-back">
            <a href="<?= $is_logged_in ? SITE_URL . '/viewer/browse-tutorials.php' : SITE_URL . '/public/search.php' ?>"
               class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Browse
            </a>
        </div>

        <!-- tutorial hero -->
        <div class="tview-hero">
            <div class="tview-hero-top">
            <div class="tview-hero-head">
            <div class="tview-breadcrumb">
                <a href="<?= $is_logged_in ? SITE_URL . '/viewer/dashboard.php' : SITE_URL . '/public/search.php' ?>">Home</a>
                <i class="fas fa-chevron-right"></i>
                <a href="<?= $is_logged_in ? SITE_URL . '/viewer/browse-tutorials.php' : SITE_URL . '/public/search.php' ?>">Tutorials</a>
                <i class="fas fa-chevron-right"></i>
                <span><?= e($tutorial['category_name']) ?></span>
            </div>

            <h1><?= e($tutorial['title']) ?></h1>
            <p class="tview-subtitle"><?= e($tutorial['short_description']) ?></p>
            </div><!-- /tview-hero-head -->

            <div class="tview-actions">
                <?php if ($is_logged_in): ?>
                <button class="btn btn-outline-white <?= $already_favorited ? 'is-fav' : '' ?>"
                        id="favBtn"
                        data-tutorial="<?= (int)$tutorial['tutorial_id'] ?>"
                        onclick="toggleFavorite(this)">
                    <i class="<?= $already_favorited ? 'fas' : 'far' ?> fa-heart"></i>
                    <span id="favText"><?= $already_favorited ? 'Favorited' : 'Add to Favorites' ?></span>
                </button>
                <?php endif; ?>
                <button class="btn btn-outline-white" onclick="shareTutorial()">
                    <i class="fas fa-share-alt"></i> Share
                </button>
            </div>
            </div><!-- /tview-hero-top -->

            <!-- meta row -->
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
                <?php if (!empty($tutorial['duration_minutes'])): ?>
                <div class="tview-meta-item">
                    <i class="fas fa-clock"></i>
                    <div>
                        <small>Duration</small>
                        <strong><?= $tutorial['duration_minutes'] ?> min</strong>
                    </div>
                </div>
                <?php endif; ?>
                <div class="tview-meta-item">
                    <i class="fas fa-signal"></i>
                    <div>
                        <small>Level</small>
                        <strong><?= ucfirst($tutorial['difficulty']) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <?php displayFlashMessage(); ?>

        <!-- two column layout -->
        <div class="tview-body">

            <!-- main content column -->
            <div class="tview-main">

                <?php if (!empty($tutorial['video_url'])): ?>
                <!-- video player -->
                <div class="tview-card">
                    <h2 class="tview-section-title"><i class="fas fa-play-circle"></i> Video</h2>
                    <div class="tview-video-wrapper">
                        <iframe src="<?= e($tutorial['video_url']) ?>" frameborder="0"
                                allow="accelerometer; autoplay; encrypted-media; gyroscope"
                                allowfullscreen
                                sandbox="allow-scripts allow-same-origin allow-presentation"></iframe>
                    </div>
                </div>
                <?php endif; ?>

                <!-- sanitized tutorial body -->
                <div class="tview-card">
                    <h2 class="tview-section-title"><i class="fas fa-book-open"></i> Tutorial Content</h2>
                    <div class="tview-content-body">
                        <?php
                        // prefer HTMLPurifier when available for full XSS safety
                        if (class_exists('HTMLPurifier')) {
                            $purifier_config = HTMLPurifier_Config::createDefault();
                            $purifier_config->set('HTML.Allowed',
                                'p,br,strong,b,em,i,u,s,ul,ol,li,h2,h3,h4,blockquote,code,pre,a[href],img[src|alt|width|height],hr,span,div'
                            );
                            $purifier_config->set('URI.SafeIframeRegexp', null);
                            $purifier = new HTMLPurifier($purifier_config);
                            echo $purifier->purify($tutorial['content']);
                        } else {
                            // fallback strip_tags then strip event handlers and js uris
                            $allowed_tags = '<p><br><strong><b><em><i><u><s><ul><ol><li>'
                                          . '<h2><h3><h4><blockquote><code><pre><a><img><hr><span><div>';
                            $safe = strip_tags($tutorial['content'], $allowed_tags);
                            // strip inline event handlers
                            $safe = preg_replace('/\s*on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $safe);
                            // strip javascript uris from href and src
                            $safe = preg_replace('/(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i', '$1=$2#$2', $safe);
                            echo $safe;
                        }
                        ?>
                    </div>
                </div>

                <?php if (isLoggedIn() && hasRole('viewer')): ?>
                <div class="tview-card">
                    <div class="complete-state <?= $already_completed ? 'done' : '' ?>" id="completeState">
                        <?php if ($already_completed): ?>
                            <div class="complete-icon"><i class="fas fa-check-circle"></i></div>
                            <div class="ctext">
                                <strong>You've completed this tutorial</strong>
                                <p>Nice work &mdash; it's saved in <a href="<?= SITE_URL ?>/viewer/my-learning.php">My Learning</a>.</p>
                            </div>
                        <?php else: ?>
                            <div class="ctext">
                                <strong>Finished this tutorial?</strong>
                                <p>Mark it complete to track your progress in My Learning.</p>
                            </div>
                            <button id="markCompleteBtn" class="btn btn-success"
                                    data-tutorial="<?= (int)($tutorial['tutorial_id'] ?? 0) ?>">
                                <i class="fas fa-check-circle"></i> Mark as Complete
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- star rating form -->
                <div class="tview-card">
                    <h2 class="tview-section-title"><i class="fas fa-star"></i> Rate This Tutorial</h2>

                    <?php if ($is_logged_in): ?>
                        <!-- show existing or prompt to rate -->
                        <p class="tview-hint">
                            <?= $user_rating
                                ? 'You rated this ' . $user_rating . '/5. Update your rating below.'
                                : 'Help others by rating this tutorial.' ?>
                        </p>

                        <?php if ($rating_error): ?>
                            <div class="alert alert-error"><?= e($rating_error) ?></div>
                        <?php endif; ?>

                        <!-- star radio form -->
                        <form method="POST" action="" class="tview-rating-form">
                            <?php csrfField(); ?>
                            <div class="tview-stars" id="starRating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <input type="radio" name="rating" value="<?= $i ?>"
                                           id="star<?= $i ?>" <?= $user_rating === $i ? 'checked' : '' ?>>
                                    <label for="star<?= $i ?>" data-val="<?= $i ?>">
                                        <i class="fas fa-star"></i>
                                    </label>
                                <?php endfor; ?>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check"></i>
                                <?= $user_rating ? 'Update Rating' : 'Submit Rating' ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <!-- guest rate prompt -->
                        <p class="tview-hint">
                            <a href="<?= SITE_URL ?>/auth/login.php" style="color:var(--c-primary);font-weight:600;">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                            to rate this tutorial.
                        </p>
                    <?php endif; ?>
                </div>

                <!-- comments section -->
                <div class="tview-card">
                    <h2 class="tview-section-title">
                        <i class="fas fa-comments"></i> Comments
                        <span class="tview-count"><?= count($comments) ?></span>
                    </h2>

                    <?php if ($is_logged_in): ?>
                        <?php if ($comment_error): ?>
                            <div class="alert alert-error"><?= e($comment_error) ?></div>
                        <?php endif; ?>

                        <!-- comment form -->
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
                    <?php else: ?>
                        <!-- guest comment prompt -->
                        <p style="margin-bottom:16px;color:var(--c-text-3);">
                            <a href="<?= SITE_URL ?>/auth/login.php" style="color:var(--c-primary);font-weight:600;">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                            or
                            <a href="<?= SITE_URL ?>/auth/register.php" style="color:var(--c-primary);font-weight:600;">
                                Register
                            </a>
                            to leave a comment.
                        </p>
                    <?php endif; ?>

                    <?php if (empty($comments)): ?>
                        <!-- no comments yet -->
                        <div class="tview-empty-comments">
                            <i class="fas fa-comment-slash"></i>
                            <p>No comments yet. Be the first!</p>
                        </div>
                    <?php else: ?>
                        <!-- approved comments -->
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

            <!-- sidebar -->
            <aside class="tview-sidebar">

                <!-- about box -->
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
                        <?php if (!empty($tutorial['duration_minutes'])): ?>
                        <li><i class="fas fa-clock"></i>
                            <span><?= $tutorial['duration_minutes'] ?> minutes</span>
                        </li>
                        <?php endif; ?>
                        <li><i class="fas fa-eye"></i>
                            <span><?= number_format($tutorial['view_count']) ?> views</span>
                        </li>
                    </ul>
                </div>

                <?php if (!empty($tutorial['tags'])): ?>
                <!-- tag chips -->
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
                <!-- downloadable attachments -->
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

                <!-- user rating sidebar card -->
                <div class="tview-sidebar-card">
                    <h3><i class="fas fa-star"></i> Your Rating</h3>
                    <?php if ($user_rating): ?>
                        <div class="tview-your-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star" style="color:<?= $i <= $user_rating ? '#ffc107' : '#e2e8f0' ?>;font-size:22px;"></i>
                            <?php endfor; ?>
                            <p style="margin-top:8px;color:var(--c-text-3);font-size:13px;">You rated this <?= $user_rating ?>/5</p>
                        </div>
                    <?php else: ?>
                        <p style="color:var(--c-text-3);font-size:13px;">You haven't rated this tutorial yet. Scroll down to rate it!</p>
                    <?php endif; ?>
                </div>

            </aside>
        </div><!-- /tview-body -->
    </div><!-- /tview-wrap -->

    <script>
        // share via web share api or clipboard fallback
        function shareTutorial() {
            if (navigator.share) {
                navigator.share({ title: <?= json_encode($tutorial['title']) ?>, url: window.location.href });
            } else {
                navigator.clipboard.writeText(window.location.href).then(function() {
                    alert('Link copied to clipboard!');
                }).catch(function() {
                    // select text fallback
                    const dummy = document.createElement('input');
                    document.body.appendChild(dummy);
                    dummy.value = window.location.href;
                    dummy.select();
                    document.body.removeChild(dummy);
                    alert('Copy the URL from your address bar.');
                });
            }
        }

        // hover preview and click-to-lock star widget
        (function () {
            var container = document.getElementById('starRating');
            if (!container) return;

            var labels = Array.from(container.querySelectorAll('label'));
            var inputs = Array.from(container.querySelectorAll('input[type="radio"]'));

            function highlightUpTo(val) {
                labels.forEach(function (lbl) {
                    var star = lbl.querySelector('i');
                    if (star) star.style.color = parseInt(lbl.dataset.val, 10) <= val ? '#ffc107' : '#e2e8f0';
                });
            }

            // restore saved rating on load
            var checked = inputs.find(function (i) { return i.checked; });
            if (checked) highlightUpTo(parseInt(checked.value, 10));

            labels.forEach(function (lbl) {
                lbl.addEventListener('mouseover', function () {
                    highlightUpTo(parseInt(lbl.dataset.val, 10));
                });
                lbl.addEventListener('mouseout', function () {
                    var sel = inputs.find(function (i) { return i.checked; });
                    highlightUpTo(sel ? parseInt(sel.value, 10) : 0);
                });
                lbl.addEventListener('click', function () {
                    highlightUpTo(parseInt(lbl.dataset.val, 10));
                });
            });
        }());
    </script>
<script src="<?= asset('js/viewer.js') ?>"></script>
    <script>
        var CSRF = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;

        // ajax mark complete then swap the card to its completed state
        document.getElementById('markCompleteBtn')?.addEventListener('click', function() {
            var btn = this;
            btn.disabled = true;
            fetch('<?= SITE_URL ?>/api/mark-complete.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-Token': CSRF},
                body: JSON.stringify({tutorial_id: btn.dataset.tutorial})
            }).then(r => r.json()).then(function(data) {
                if (data.success) {
                    var state = document.getElementById('completeState');
                    state.classList.add('done');
                    state.innerHTML =
                        '<div class="complete-icon"><i class="fas fa-check-circle"></i></div>' +
                        '<div class="ctext"><strong>You\'ve completed this tutorial</strong>' +
                        '<p>Nice work &mdash; it\'s saved in <a href="<?= SITE_URL ?>/viewer/my-learning.php">My Learning</a>.</p></div>';
                } else {
                    btn.disabled = false;
                    alert(data.message || 'Could not mark complete. Please try again.');
                }
            }).catch(function() { btn.disabled = false; alert('Network error. Please try again.'); });
        });

        // ajax toggle favorite from inside the tutorial
        function toggleFavorite(btn) {
            btn.disabled = true;
            fetch('<?= SITE_URL ?>/api/toggle-favorite.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({tutorial_id: btn.dataset.tutorial, csrf_token: CSRF})
            }).then(r => r.json()).then(function(data) {
                if (data.success) {
                    var icon = btn.querySelector('.fa-heart');
                    var text = document.getElementById('favText');
                    if (data.favorited) {
                        btn.classList.add('is-fav');
                        if (icon) { icon.classList.remove('far'); icon.classList.add('fas'); }
                        if (text) text.textContent = 'Favorited';
                    } else {
                        btn.classList.remove('is-fav');
                        if (icon) { icon.classList.remove('fas'); icon.classList.add('far'); }
                        if (text) text.textContent = 'Add to Favorites';
                    }
                } else if (data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    alert(data.message || 'Could not update favorite.');
                }
                btn.disabled = false;
            }).catch(function() { btn.disabled = false; alert('Network error. Please try again.'); });
        }
    </script>
</body>
</html>
