<?php
// my tutorials page showing all tutorials this instructor has created

require_once '../includes/auth-check.php';
require_once '../classes/Tutorial.php';

$page_title = 'My Tutorials';

// read the status filter and sort order from the url
$status_filter = isset($_GET['status']) ? clean($_GET['status']) : '';
$sort = isset($_GET['sort']) ? clean($_GET['sort']) : 'newest';

// fetch all tutorials belonging to this instructor filtered by status if one was selected
$tutorial = new Tutorial();
$my_tutorials = $tutorial->getByInstructor($current_user_id, $status_filter);

// sort the tutorials in memory based on the chosen sort option
usort($my_tutorials, function($a, $b) use ($sort) {
    switch($sort) {
        case 'oldest':
            return strtotime($a['created_at']) - strtotime($b['created_at']);
        case 'most_viewed':
            return $b['view_count'] - $a['view_count'];
        case 'highest_rated':
            return ($b['avg_rating'] ?? 0) - ($a['avg_rating'] ?? 0);
        case 'newest':
        default:
            return strtotime($b['created_at']) - strtotime($a['created_at']);
    }
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> | <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= asset('css/creator.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/creator-nav.php'; ?>

    <div class="dashboard-container">
        <?php include '../includes/creator-sidebar.php'; ?>

        <main class="dashboard-main">

            <!-- page heading showing the total tutorial count and a create button -->
            <div class="dashboard-header">
                <div>
                    <h1><i class="fas fa-book"></i> My Tutorials</h1>
                    <p>Manage all your tutorials in one place (<?= count($my_tutorials) ?> total)</p>
                </div>
                <a href="create-tutorial.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Create New Tutorial
                </a>
            </div>

            <?php displayFlashMessage(); ?>

            <!-- filter bar for narrowing by status and sorting the results -->
            <div class="filters-bar">
                <div class="filter-group">
                    <label>Status:</label>
                    <select class="filter-select" onchange="filterByStatus(this.value)">
                        <option value="">All Status</option>
                        <option value="published" <?= $status_filter === 'published' ? 'selected' : '' ?>>Published</option>
                        <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="archived" <?= $status_filter === 'archived' ? 'selected' : '' ?>>Archived</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Sort by:</label>
                    <select class="filter-select" onchange="sortTutorials(this.value)">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                        <option value="most_viewed" <?= $sort === 'most_viewed' ? 'selected' : '' ?>>Most Viewed</option>
                        <option value="highest_rated" <?= $sort === 'highest_rated' ? 'selected' : '' ?>>Highest Rated</option>
                    </select>
                </div>
            </div>

            <!-- grid of tutorial cards or an empty state prompt if nothing has been created yet -->
            <?php if (empty($my_tutorials)): ?>
                <div class="empty-state-large">
                    <i class="fas fa-book-open"></i>
                    <h2>No tutorials yet</h2>
                    <p>Start sharing your knowledge by creating your first tutorial!</p>
                    <a href="create-tutorial.php" class="btn btn-primary btn-large">
                        <i class="fas fa-plus-circle"></i>
                        Create Your First Tutorial
                    </a>
                </div>
            <?php else: ?>
                <div class="tutorials-grid">
                    <?php foreach ($my_tutorials as $tut): ?>
                        <div class="tutorial-card-large">

                            <!-- thumbnail with a status badge overlaid -->
                            <div class="tutorial-thumbnail">
                                <?php if (!empty($tut['thumbnail'])): ?>
                                    <img src="<?= SITE_URL ?>/uploads/<?= e($tut['thumbnail']) ?>" alt="<?= e($tut['title']) ?>">
                                <?php else: ?>
                                    <div class="thumb-placeholder tall"><i class="fas fa-book"></i></div>
                                <?php endif; ?>
                                <span class="tutorial-status status-<?= $tut['status'] ?>">
                                    <?= ucfirst($tut['status']) ?>
                                </span>
                            </div>

                            <!-- card body with category, title, description and stats -->
                            <div class="tutorial-card-body">
                                <span class="category-tag">
                                    <i class="fas fa-folder"></i>
                                    <?= e($tut['category_name']) ?>
                                </span>
                                <h3><?= e($tut['title']) ?></h3>
                                <p><?= e(truncate($tut['short_description'], 120)) ?></p>

                                <!-- views, rating and comment count row -->
                                <div class="tutorial-meta">
                                    <span>
                                        <i class="fas fa-eye"></i>
                                        <?= number_format($tut['view_count']) ?> views
                                    </span>
                                    <span>
                                        <i class="fas fa-star star-gold"></i>
                                        <?= number_format($tut['avg_rating'] ?? 0, 1) ?>
                                        (<?= $tut['rating_count'] ?>)
                                    </span>
                                    <span>
                                        <i class="fas fa-comments"></i>
                                        <?= $tut['comment_count'] ?>
                                    </span>
                                </div>

                                <!-- footer with creation date and edit, view and delete action buttons -->
                                <div class="tutorial-footer">
                                    <small class="text-muted">
                                        Created <?= timeAgo($tut['created_at']) ?>
                                    </small>
                                    <div class="card-actions">
                                        <a href="edit-tutorial.php?id=<?= $tut['tutorial_id'] ?>" class="btn btn-icon" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="<?= SITE_URL ?>/viewer/tutorial-view.php?slug=<?= urlencode($tut['slug']) ?>" class="btn btn-icon" title="View" target="_blank">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                        <button class="btn btn-icon btn-danger" title="Delete" onclick="deleteTutorial(<?= (int)$tut['tutorial_id'] ?>, <?= json_encode($tut['title']) ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // filter reload
        function filterByStatus(status) {
            const currentUrl = new URL(window.location.href);
            if (status) {
                currentUrl.searchParams.set('status', status);
            } else {
                currentUrl.searchParams.delete('status');
            }
            window.location.href = currentUrl.toString();
        }

        // sort reload
        function sortTutorials(sort) {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('sort', sort);
            window.location.href = currentUrl.toString();
        }

        // confirm delete
        function deleteTutorial(id, title) {
            if (!confirm('Archive "' + title + '"?\nIt will be removed from public view.')) return;
            // post with csrf
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?= SITE_URL ?>/creator/delete-tutorial.php';
            form.innerHTML =
                '<input type="hidden" name="tutorial_id" value="' + id + '">' +
                '<input type="hidden" name="csrf_token" value="' + <?= json_encode(generateCSRFToken()) ?> + '">';
            document.body.appendChild(form);
            form.submit();
        }
    </script>
<script src="<?= asset('js/creator.js') ?>"></script>
</body>
</html>
