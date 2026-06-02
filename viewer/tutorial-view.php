<?php
// tutorial view page that works for both guests and logged in viewers

// soft auth check guests can still view but creators get sent to their own area
require_once '../config/config.php';
require_once '../classes/Tutorial.php';

// redirect creators away from this page
if (isLoggedIn() && isCreator()) {
    redirect('creator/dashboard.php');
}

// check visitor type
$is_logged_in       = isLoggedIn() && (isViewer() || isAdmin());
$current_user_id    = $is_logged_in ? getCurrentUserId()    : 0;
$current_user_name  = $is_logged_in ? getCurrentUserName()  : 'Guest';
$current_user_role  = $is_logged_in ? getCurrentUserRole()  : 'guest';

// get slug from url
$slug = isset($_GET['slug']) ? clean($_GET['slug']) : '';

// if no slug was given redirect to the browse page
if (empty($slug)) {
    redirect($is_logged_in ? 'viewer/browse-tutorials.php' : 'public/search.php');
}

// find tutorial by slug
$tutorialObj = new Tutorial();
$tutorial    = $tutorialObj->getBySlug($slug);

// if the tutorial was not found send the user back to browse
if (!$tutorial) {
    redirect($is_logged_in ? 'viewer/browse-tutorials.php' : 'public/search.php');
}

// log view
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

// db connect
$database = new Database();
$conn     = $database->connect();

// if no db
if (!$conn) {
    redirect($is_logged_in ? 'viewer/browse-tutorials.php' : 'public/search.php');
}

// handle comment post
$comment_error = '';
if ($is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    if (!verifyCsrfFromPost()) {
        $comment_error = 'Invalid security token. Please try again.';
    } else {
        $comment_text = clean($_POST['comment']);
        if (empty($comment_text)) {
            $comment_error = 'Comment cannot be empty.';
        } else {
            // insert comment
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

// handle rating post
$rating_error = '';
if ($is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rating'])) {
    if (!verifyCsrfFromPost()) {
        $rating_error = 'Invalid security token. Please try again.';
    } else {
        $rating_val = (int)$_POST['rating'];
        // rating must be between 1 and 5
        if ($rating_val < 1 || $rating_val > 5) {
            $rating_error = 'Please select a rating between 1 and 5.';
        } else {
            // upsert rating
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

// get approved comments
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

// check existing rating
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

// prep variables
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
    <!-- shared design tokens must load first so var(--c-*) resolves in viewer.css and inline styles -->
    <link rel="stylesheet" href="<?= asset('css/shared.css') ?>">
    <!-- cache bust the CSS so updated styles always load -->
    <link rel="stylesheet" href="<?= asset('css/viewer.css') ?>?v=<?= $css_version ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
</head>
<body>
    <?php if ($is_logged_in): ?>
        <?php include '../includes/viewer-nav.php'; ?>
    <?php else: ?>
        <!-- minimal public navigation bar shown to guests who are not logged in -->
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

        <!-- back to browse button -->
        <div class="tview-back">
            <a href="<?= $is_logged_in ? SITE_URL . '/viewer/browse-tutorials.php' : SITE_URL . '/public/search.php' ?>"
               class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Browse
            </a>
        </div>

        <!-- hero section with the tutorial title, meta info and share button -->
        <div class="tview-hero">
            <div class="tview-breadcrumb">
                <a href="<?= $is_logged_in ? SITE_URL . '/viewer/dashboard.php' : SITE_URL . '/public/search.php' ?>">Home</a>
                <i class="fas fa-chevron-right"></i>
                <a href="<?= $is_logged_in ? SITE_URL . '/viewer/browse-tutorials.php' : SITE_URL . '/public/search.php' ?>">Tutorials</a>
                <i class="fas fa-chevron-right"></i>
                <span><?= e($tutorial['category_name']) ?></span>
            </div>

            <h1><?= e($tutorial['title']) ?></h1>
            <p class="tview-subtitle"><?= e($tutorial['short_description']) ?></p>

            <!-- meta row showing instructor, rating, views, duration and difficulty -->
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

            <div class="tview-actions">
                <button class="btn btn-outline-white" onclick="shareTutorial()">
                    <i class="fas fa-share-alt"></i> Share
                </button>
            </div>
        </div>

        <?php displayFlashMessage(); ?>

        <!-- two column layout with main content on the left and sidebar on the right -->
        <div class="tview-body">

            <!-- left column with video tutorial body rating form and comments -->
            <div class="tview-main">

                <?php if (!empty($tutorial['video_url'])): ?>
                <!-- embedded video player if a video URL was provided -->
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

                <!-- the main tutorial body content with javascript URIs stripped for safety -->
                <div class="tview-card">
                    <h2 class="tview-section-title"><i class="fas fa-book-open"></i> Tutorial Content</h2>
                    <div class="tview-content-body">
                        <?php
                        // todo replace with htmlpurifier in production for full xss safety
                        // the preg_replace approach is fragile and can be bypassed strip_tags
                        // with a strict allowlist is safer as a minimum measure
                        if (class_exists('HTMLPurifier')) {
                            // use htmlpurifier when available — the gold standard for html sanitisation
                            $purifier_config = HTMLPurifier_Config::createDefault();
                            $purifier_config->set('HTML.Allowed',
                                'p,br,strong,b,em,i,u,s,ul,ol,li,h2,h3,h4,blockquote,code,pre,a[href],img[src|alt|width|height],hr,span,div'
                            );
                            $purifier_config->set('URI.SafeIframeRegexp', null);
                            $purifier = new HTMLPurifier($purifier_config);
                            echo $purifier->purify($tutorial['content']);
                        } else {
                            // fallback strip_tags with a strict allowlist then remove any
                            // remaining inline event handlers and javascript uris via regex
                            $allowed_tags = '<p><br><strong><b><em><i><u><s><ul><ol><li>'
                                          . '<h2><h3><h4><blockquote><code><pre><a><img><hr><span><div>';
                            $safe = strip_tags($tutorial['content'], $allowed_tags);
                            // remove inline event handlers (onclick= onmouseover= etc)
                            $safe = preg_replace('/\s*on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $safe);
                            // remove javascript uris from href and src attributes
                            $safe = preg_replace('/(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i', '$1=$2#$2', $safe);
                            echo $safe;
                        }
                        ?>
                    </div>
                </div>

                <?php if (isLoggedIn() && hasRole('viewer')): ?>
                <div class="mark-complete-section" style="margin-top: 20px;">
                    <button id="markCompleteBtn" class="btn btn-success"
                        data-tutorial="<?= (int)($tutorial['tutorial_id'] ?? 0) ?>">
                        <i class="fas fa-check-circle"></i> Mark as Complete
                    </button>
                    <span id="completeMsg" style="display:none;color:green;margin-left:10px;">Marked as complete!</span>
                </div>
                <?php endif; ?>

                <!-- star rating section where users can leave or update their rating -->
                <div class="tview-card">
                    <h2 class="tview-section-title"><i class="fas fa-star"></i> Rate This Tutorial</h2>

                    <?php if ($is_logged_in): ?>
                        <!-- tell the user whether they have already rated this or not -->
                        <p class="tview-hint">
                            <?= $user_rating
                                ? 'You rated this ' . $user_rating . '/5. Update your rating below.'
                                : 'Help others by rating this tutorial.' ?>
                        </p>

                        <?php if ($rating_error): ?>
                            <div class="alert alert-error"><?= e($rating_error) ?></div>
                        <?php endif; ?>

                        <!-- rating form with five star radio buttons -->
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
                        <!-- prompt guest users to log in before they can rate -->
                        <p class="tview-hint">
                            <a href="<?= SITE_URL ?>/auth/login.php" style="color:var(--c-primary);font-weight:600;">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                            to rate this tutorial.
                        </p>
                    <?php endif; ?>
                </div>

                <!-- comments section showing existing comments and a form to add a new one -->
                <div class="tview-card">
                    <h2 class="tview-section-title">
                        <i class="fas fa-comments"></i> Comments
                        <span class="tview-count"><?= count($comments) ?></span>
                    </h2>

                    <?php if ($is_logged_in): ?>
                        <?php if ($comment_error): ?>
                            <div class="alert alert-error"><?= e($comment_error) ?></div>
                        <?php endif; ?>

                        <!-- comment submission form for logged in users -->
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
                        <!-- prompt guests to log in or register before they can comment -->
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
                        <!-- empty state when nobody has commented yet -->
                        <div class="tview-empty-comments">
                            <i class="fas fa-comment-slash"></i>
                            <p>No comments yet. Be the first!</p>
                        </div>
                    <?php else: ?>
                        <!-- list of approved comments newest first -->
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

            <!-- right sidebar with about info, tags, downloads and the user's rating -->
            <aside class="tview-sidebar">

                <!-- about box showing published date, category, level, duration and views -->
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
                <!-- tag chips for this tutorial -->
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
                <!-- downloadable files attached to this tutorial -->
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

                <!-- show the logged in user's current rating for this tutorial -->
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
        // share button
        function shareTutorial() {
            if (navigator.share) {
                // json_encode handles all js string escaping safely
                navigator.share({ title: <?= json_encode($tutorial['title']) ?>, url: window.location.href });
            } else {
                navigator.clipboard.writeText(window.location.href).then(function() {
                    // show success message
                    alert('Link copied to clipboard!');
                }).catch(function() {
                    // fallback select text from input
                    const dummy = document.createElement('input');
                    document.body.appendChild(dummy);
                    dummy.value = window.location.href;
                    dummy.select();
                    document.body.removeChild(dummy);
                    alert('Copy the URL from your address bar.');
                });
            }
        }

        // jquerydriven star rating — hover previews and clicktolock highlighting
        $(function() {
            var $container = $('#starRating');
            if (!$container.length) return;

            // paint stars gold up to val grey beyond it
            function highlightUpTo(val) {
                $container.find('label').each(function() {
                    $(this).find('i').css('color', parseInt($(this).data('val')) <= val ? '#ffc107' : '#e2e8f0');
                });
            }

            // restore any previously selected rating when the page loads
            var $checked = $container.find('input:checked');
            if ($checked.length) highlightUpTo(parseInt($checked.val()));

            $container.find('label')
                .on('mouseover', function() {
                    highlightUpTo(parseInt($(this).data('val')));
                })
                .on('mouseout', function() {
                    var $sel = $container.find('input:checked');
                    highlightUpTo($sel.length ? parseInt($sel.val()) : 0);
                })
                .on('click', function() {
                    highlightUpTo(parseInt($(this).data('val')));
                });
        });
    </script>
<script src="<?= asset('js/viewer.js') ?>"></script>
    <script>
        // mark complete ajax
        document.getElementById('markCompleteBtn')?.addEventListener('click', function() {
            var tutId = this.dataset.tutorial;
            fetch('<?= SITE_URL ?>/api/mark-complete.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-Token': <?= json_encode($_SESSION['csrf_token'] ?? '') ?>},
                body: JSON.stringify({tutorial_id: tutId})
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    document.getElementById('completeMsg').style.display = 'inline';
                    this.disabled = true;
                    this.innerHTML = '<i class="fas fa-check-circle"></i> Completed';
                }
            });
        });
    </script>
</body>
</html>
