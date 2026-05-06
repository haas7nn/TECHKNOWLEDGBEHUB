<?php
/**
 * Tutorial View Page
 * detailed view of a single tutorial with content
 * Hasan Fardan - 202301686
 */

require_once '../includes/viewer-auth-check.php';
require_once '../classes/Tutorial.php';

$page_title = 'View Tutorial';

// Get slug from URL
$slug = isset($_GET['slug']) ? clean($_GET['slug']) : '';

if (empty($slug)) {
    setFlashMessage('Invalid tutorial', 'error');
    redirect('viewer/browse-tutorials.php');
}

// Get tutorial from database
$tutorialObj = new Tutorial();
$tutorial = $tutorialObj->getBySlug($slug);

if (!$tutorial) {
    setFlashMessage('Tutorial not found', 'error');
    redirect('viewer/browse-tutorials.php');
}

// Log view
$tutorialObj->logView($tutorial['tutorial_id'], $current_user_id);

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    if (!verifyCsrfFromPost()) {
        $error = 'Invalid security token';
    } else {
        $comment_text = clean($_POST['comment']);
        // Save comment to database
        $database = new Database();
        $conn = $database->connect();
        
        $commentQuery = "INSERT INTO techknow_comments (tutorial_id, user_id, comment_text, created_at) 
                        VALUES (:tutorial_id, :user_id, :comment_text, NOW())";
        $commentStmt = $conn->prepare($commentQuery);
        $commentStmt->bindParam(':tutorial_id', $tutorial['tutorial_id'], PDO::PARAM_INT);
        $commentStmt->bindParam(':user_id', $current_user_id, PDO::PARAM_INT);
        $commentStmt->bindParam(':comment_text', $comment_text, PDO::PARAM_STR);
        
        if ($commentStmt->execute()) {
            setFlashMessage('Comment posted successfully!', 'success');
            redirect('viewer/tutorial-view.php?slug=' . $slug);
        }
    }
}

// Get comments
$database = new Database();
$conn = $database->connect();

$commentsQuery = "SELECT c.*, u.full_name as user_name 
                  FROM techknow_comments c 
                  JOIN techknow_users u ON c.user_id = u.user_id 
                  WHERE c.tutorial_id = :tutorial_id 
                  ORDER BY c.created_at DESC";
$commentsStmt = $conn->prepare($commentsQuery);
$commentsStmt->bindParam(':tutorial_id', $tutorial['tutorial_id'], PDO::PARAM_INT);
$commentsStmt->execute();
$comments = $commentsStmt->fetchAll();
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
        <!-- back button -->
        <div class="back-navigation">
            <a href="browse-tutorials.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i>
                Back to Browse
            </a>
        </div>
        
        <!-- tutorial header -->
        <div class="tutorial-header">
            <div class="tutorial-header-content">
                <div class="breadcrumb">
                    <a href="../public/index.php">Home</a>
                    <i class="fas fa-chevron-right"></i>
                    <a href="browse-tutorials.php">Tutorials</a>
                    <i class="fas fa-chevron-right"></i>
                    <span><?= e($tutorial['category_name']) ?></span>
                </div>
                
                <h1><?= e($tutorial['title']) ?></h1>
                <p class="subtitle"><?= e($tutorial['short_description']) ?></p>
                
                <div class="tutorial-meta">
                    <div class="meta-item">
                        <img src="<?= $tutorial['instructor_avatar'] ?>" alt="Instructor" class="instructor-avatar">
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
                    <button class="btn btn-primary btn-large" onclick="markAsComplete()">
                        <i class="fas fa-check-circle"></i>
                        Mark as Complete
                    </button>
                    
                    <button class="btn btn-outline" onclick="toggleFavorite(<?= $tutorial_id ?>)">
                        <i class="<?= $tutorial['is_favorited'] ? 'fas' : 'far' ?> fa-heart"></i>
                        <?= $tutorial['is_favorited'] ? 'Saved' : 'Save' ?>
                    </button>
                    
                    <button class="btn btn-outline" onclick="shareTutorial()">
                        <i class="fas fa-share-alt"></i>
                        Share
                    </button>
                </div>
            </div>
        </div>
        
        <?php displayFlashMessage(); ?>
        
        <!-- main content area -->
        <div class="tutorial-content-wrapper">
            <div class="tutorial-main-content">
                <!-- video section if available -->
                <?php if (!empty($tutorial['video_url'])): ?>
                <section class="video-section">
                    <div class="video-wrapper">
                        <iframe 
                            src="<?= e($tutorial['video_url']) ?>" 
                            frameborder="0" 
                            allowfullscreen
                        ></iframe>
                    </div>
                </section>
                <?php endif; ?>
                
                <!-- tutorial content -->
                <section class="content-section">
                    <h2><i class="fas fa-book-open"></i> Tutorial Content</h2>
                    <div class="tutorial-body">
                        <?= $tutorial['content'] ?>
                    </div>
                </section>
                
                <!-- rating section -->
                <section class="rating-section">
                    <h2><i class="fas fa-star"></i> Rate This Tutorial</h2>
                    <p>Help others by sharing your experience</p>
                    
                    <form method="POST" action="" class="rating-form">
                        <div class="star-rating-input">
                            <input type="radio" name="rating" value="5" id="star5">
                            <label for="star5"><i class="fas fa-star"></i></label>
                            
                            <input type="radio" name="rating" value="4" id="star4">
                            <label for="star4"><i class="fas fa-star"></i></label>
                            
                            <input type="radio" name="rating" value="3" id="star3">
                            <label for="star3"><i class="fas fa-star"></i></label>
                            
                            <input type="radio" name="rating" value="2" id="star2">
                            <label for="star2"><i class="fas fa-star"></i></label>
                            
                            <input type="radio" name="rating" value="1" id="star1">
                            <label for="star1"><i class="fas fa-star"></i></label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            Submit Rating
                        </button>
                    </form>
                </section>
                
                <!-- comments section -->
                <section class="comments-section">
                    <h2><i class="fas fa-comments"></i> Comments (<?= count($comments) ?>)</h2>
                    
                    <!-- add comment form -->
                    <form method="POST" action="" class="comment-form">
                        <div class="user-avatar">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="comment-input-wrapper">
                            <textarea 
                                name="comment" 
                                placeholder="Share your thoughts..." 
                                rows="3"
                                required
                            ></textarea>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i>
                                Post Comment
                            </button>
                        </div>
                    </form>
                    
                    <!-- comments list -->
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
            
            <!-- sidebar with related info -->
            <aside class="tutorial-sidebar">
                <div class="sidebar-card">
                    <h3><i class="fas fa-info-circle"></i> About This Tutorial</h3>
                    <ul class="info-list">
                        <li>
                            <i class="fas fa-calendar"></i>
                            <span>Published <?= timeAgo($tutorial['created_at']) ?></span>
                        </li>
                        <li>
                            <i class="fas fa-folder"></i>
                            <span>Category: <?= e($tutorial['category_name']) ?></span>
                        </li>
                        <li>
                            <i class="fas fa-language"></i>
                            <span>Language: English</span>
                        </li>
                    </ul>
                </div>
                
                <div class="sidebar-card">
                    <h3><i class="fas fa-lightbulb"></i> Related Tutorials</h3>
                    <div class="related-tutorials">
                        <p class="text-muted">Related tutorials will appear here</p>
                    </div>
                </div>
            </aside>
        </div>
    </div>
    
    <script>
        function markAsComplete() {
            if (confirm('Mark this tutorial as complete?')) {
                alert('Complete feature will be implemented with Tutorial class');
            }
        }
        
        function toggleFavorite(id) {
            alert('Favorite feature will be implemented with Tutorial class');
        }
        
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