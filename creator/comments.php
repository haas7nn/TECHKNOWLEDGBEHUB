<?php
// comments on own tutorials

require_once '../includes/auth-check.php';
require_once '../classes/Tutorial.php';

$page_title = 'Comments';

// db connect
$db   = new Database();
$conn = $db->connect();
if (!$conn) {
    setFlashMessage('Database error. Please try again.', 'error');
    redirect('creator/dashboard.php');
}

// get own tutorial ids
$tutObj       = new Tutorial();
$my_tutorials = $tutObj->getByInstructor($current_user_id);
$tutorial_ids = array_column($my_tutorials, 'tutorial_id');

// default counts and pagination vars
$comments       = [];
$total_approved = 0;
$total_removed  = 0;
$status_filter  = clean($_GET['status'] ?? 'all');
$page           = max(1, (int)($_GET['page'] ?? 1));
$per_page       = 15;
$offset         = ($page - 1) * $per_page;
$total_comments = 0;
$total_pages    = 1;

// safe subquery for own tutorials
$subquery = "SELECT tutorial_id FROM dbProj_tutorials WHERE instructor_id = :uid";

if (!empty($tutorial_ids)) {
    // count approved and removed comments
    $badgeStmt = $conn->prepare(
        "SELECT status, COUNT(*) AS cnt
         FROM dbProj_comments
         WHERE tutorial_id IN ($subquery)
         GROUP BY status"
    );
    $badgeStmt->execute([':uid' => $current_user_id]);
    foreach ($badgeStmt->fetchAll() as $row) {
        if ($row['status'] === 'approved') $total_approved = (int)$row['cnt'];
        if ($row['status'] === 'removed')  $total_removed  = (int)$row['cnt'];
    }

    // build status filter condition
    $status_condition = '';
    $status_params    = [':uid' => $current_user_id];
    if ($status_filter === 'approved') {
        $status_condition = "AND c.status = 'approved'";
    } elseif ($status_filter === 'removed') {
        $status_condition = "AND c.status = 'removed'";
    }

    // total count for pagination
    $countStmt = $conn->prepare(
        "SELECT COUNT(*) FROM dbProj_comments c
         WHERE c.tutorial_id IN ($subquery) $status_condition"
    );
    $countStmt->execute($status_params);
    $total_comments = (int)$countStmt->fetchColumn();
    $total_pages    = max(1, (int)ceil($total_comments / $per_page));

    // fetch paginated comments with user and tutorial joined
    $listStmt = $conn->prepare(
        "SELECT c.comment_id, c.comment_text, c.status, c.created_at,
                u.full_name    AS commenter_name,
                t.title        AS tutorial_title,
                t.slug         AS tutorial_slug
         FROM dbProj_comments c
         JOIN dbProj_users     u ON c.user_id     = u.user_id
         JOIN dbProj_tutorials t ON c.tutorial_id = t.tutorial_id
         WHERE c.tutorial_id IN ($subquery) $status_condition
         ORDER BY c.created_at DESC
         LIMIT :lim OFFSET :off"
    );
    $listStmt->bindValue(':uid', $current_user_id, PDO::PARAM_INT);
    $listStmt->bindValue(':lim', $per_page,        PDO::PARAM_INT);
    $listStmt->bindValue(':off', $offset,          PDO::PARAM_INT);
    $listStmt->execute();
    $comments = $listStmt->fetchAll(PDO::FETCH_ASSOC);
}

// total for all tab badge
$total_all = $total_approved + $total_removed;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> | <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= asset('css/creator.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .filter-tabs  { display:flex; gap:8px; margin-bottom:24px; flex-wrap:wrap; }
        .filter-tab   { padding:8px 18px; border-radius:20px; font-size:13px; font-weight:600;
                        text-decoration:none; background:#f0f2f5; color:#555; transition:all .2s; }
        .filter-tab:hover, .filter-tab.active { background:#667eea; color:#fff; }
        .badge-count  { display:inline-block; background:#e74c3c; color:#fff; border-radius:10px;
                        font-size:11px; padding:1px 7px; margin-left:5px; }
        .comment-card { background:#fff; border-radius:12px; padding:18px 22px; margin-bottom:14px;
                        box-shadow:0 2px 8px rgba(0,0,0,.06); border-left:4px solid #ddd; }
        .comment-card.approved { border-left-color:#27ae60; }
        .comment-card.removed  { border-left-color:#e74c3c; }
        .comment-meta { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:10px; }
        .comment-author   { font-weight:600; color:#2c3e50; font-size:14px; }
        .comment-tutorial { font-size:13px; color:#667eea; text-decoration:none; }
        .comment-tutorial:hover { text-decoration:underline; }
        .comment-date { font-size:12px; color:#7f8c8d; margin-left:auto; }
        .comment-text { font-size:14px; color:#34495e; line-height:1.6; margin:0; }
        .status-badge { padding:2px 10px; border-radius:12px; font-size:12px; font-weight:600; }
        .status-badge.approved { background:#e8f8f0; color:#27ae60; }
        .status-badge.removed  { background:#fdecea; color:#e74c3c; }
        .empty-state { text-align:center; padding:60px 20px; color:#7f8c8d;
                       background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,.07); }
        .empty-state i { font-size:44px; color:#ddd; margin-bottom:14px; display:block; }
    </style>
</head>
<body>
<?php include '../includes/creator-nav.php'; ?>

<div class="dashboard-container">
    <?php include '../includes/creator-sidebar.php'; ?>

    <main class="dashboard-main">

        <!-- page heading -->
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-comments" style="color:#667eea;"></i> Comments</h1>
                <p>Comments left on your tutorials</p>
            </div>
        </div>

        <?php displayFlashMessage(); ?>

        <!-- filter tabs -->
        <div class="filter-tabs">
            <a href="?status=all"      class="filter-tab <?= $status_filter==='all'      ?'active':'' ?>">
                All <?php if ($total_all): ?><span class="badge-count"><?= $total_all ?></span><?php endif; ?>
            </a>
            <a href="?status=approved" class="filter-tab <?= $status_filter==='approved' ?'active':'' ?>">
                Approved <?php if ($total_approved): ?><span class="badge-count" style="background:#27ae60;"><?= $total_approved ?></span><?php endif; ?>
            </a>
            <a href="?status=removed"  class="filter-tab <?= $status_filter==='removed'  ?'active':'' ?>">
                Removed <?php if ($total_removed): ?><span class="badge-count"><?= $total_removed ?></span><?php endif; ?>
            </a>
        </div>

        <?php if (empty($tutorial_ids)): ?>
            <!-- no tutorials yet -->
            <div class="empty-state">
                <i class="fas fa-book-open"></i>
                <h3>No Tutorials Yet</h3>
                <p>Create your first tutorial to start receiving comments.</p>
                <a href="create-tutorial.php" class="btn btn-primary" style="margin-top:16px; display:inline-block;">Create Tutorial</a>
            </div>
        <?php elseif (empty($comments)): ?>
            <!-- no comments for this filter -->
            <div class="empty-state">
                <i class="fas fa-comment-slash"></i>
                <h3>No Comments Found</h3>
                <p>No comments match the selected filter.</p>
            </div>
        <?php else: ?>
            <!-- count and page info -->
            <p style="font-size:13px; color:#7f8c8d; margin-bottom:16px;">
                <?= $total_comments ?> comment(s)
                <?php if ($total_pages > 1): ?> &mdash; page <?= $page ?> of <?= $total_pages ?><?php endif; ?>
            </p>
            <!-- comment cards -->
            <?php foreach ($comments as $c): ?>
            <div class="comment-card <?= e($c['status']) ?>">
                <div class="comment-meta">
                    <span class="comment-author"><i class="fas fa-user-circle" style="color:#bbb; margin-right:4px;"></i><?= e($c['commenter_name']) ?></span>
                    <span class="status-badge <?= e($c['status']) ?>"><?= ucfirst(e($c['status'])) ?></span>
                    <a href="<?= SITE_URL ?>/viewer/tutorial-view.php?slug=<?= urlencode($c['tutorial_slug']) ?>"
                       class="comment-tutorial" target="_blank">
                        <i class="fas fa-book"></i> <?= e(truncate($c['tutorial_title'], 45)) ?>
                    </a>
                    <span class="comment-date"><?= timeAgo($c['created_at']) ?></span>
                </div>
                <p class="comment-text"><?= e($c['comment_text']) ?></p>
            </div>
            <?php endforeach; ?>

            <!-- pagination nav -->
            <?php if ($total_pages > 1): ?>
            <?php $base = '?status=' . urlencode($status_filter); ?>
            <div style="display:flex;gap:10px;align-items:center;margin-top:20px;">
                <?php if ($page > 1): ?>
                    <a href="<?= $base ?>&page=<?= $page - 1 ?>" class="btn btn-outline btn-sm">
                        <i class="fas fa-chevron-left"></i> Prev
                    </a>
                <?php endif; ?>
                <span style="font-size:13px;color:#7f8c8d;">Page <?= $page ?> of <?= $total_pages ?></span>
                <?php if ($page < $total_pages): ?>
                    <a href="<?= $base ?>&page=<?= $page + 1 ?>" class="btn btn-outline btn-sm">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>
<script src="<?= asset('js/creator.js') ?>"></script>
</body>
</html>
