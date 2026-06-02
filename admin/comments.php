<?php
// admin only
require_once '../includes/admin-check.php';

// db connect
$db   = new Database();
$conn = $db->connect();
// stop if db down
if (!$conn) { setFlashMessage("Database error. Please try again.", "error"); redirect("auth/login.php"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfFromPost()) {
    $action     = clean($_POST['action']     ?? '');
    $comment_id = (int)($_POST['comment_id'] ?? 0);

    if ($comment_id) {
        if ($action === 'approve') {
            $conn->prepare("UPDATE dbProj_comments SET status='approved' WHERE comment_id=:id")
                 ->execute([':id' => $comment_id]);
            setFlashMessage('Comment approved and is now visible.', 'success');
        } elseif ($action === 'remove') {
            // hide but keep in db
            $conn->prepare("UPDATE dbProj_comments SET status='removed' WHERE comment_id=:id")
                 ->execute([':id' => $comment_id]);
            setFlashMessage('Comment removed and hidden from users.', 'success');
        } elseif ($action === 'delete') {
            // permanent delete
            $conn->prepare("DELETE FROM dbProj_comments WHERE comment_id=:id")
                 ->execute([':id' => $comment_id]);
            setFlashMessage('Comment permanently deleted.', 'success');
        }
    }
    // preserve tab after redirect
    $safe_status = isset($_GET['status']) ? urlencode(clean($_GET['status'])) : '';
    redirect('admin/comments.php' . ($safe_status ? '?status=' . $safe_status : ''));
}

$status_f = clean($_GET['status'] ?? 'all');
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;

$where  = ['1=1'];
$params = [];
if ($status_f && $status_f !== 'all') {
    // filter by selected tab
    $where[] = "c.status=:status";
    $params[':status'] = $status_f;
}

// total rows for pagination
$where_sql   = implode(' AND ', $where);
$total       = $conn->prepare("SELECT COUNT(*) FROM dbProj_comments c WHERE $where_sql");
$total->execute($params);
$total       = $total->fetchColumn();
$total_pages = max(1, ceil($total / $per_page));

// fetch comments with user and tutorial info
$sql = "SELECT c.*, u.full_name as user_name, u.email as user_email,
        t.title as tutorial_title, t.slug as tutorial_slug
        FROM dbProj_comments c
        JOIN dbProj_users u ON c.user_id = u.user_id
        JOIN dbProj_tutorials t ON c.tutorial_id = t.tutorial_id
        WHERE $where_sql
        ORDER BY c.created_at DESC
        LIMIT :lim OFFSET :off";

$stmt = $conn->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
// bind pagination as integers to avoid quoting
$stmt->bindValue(':lim', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset,   PDO::PARAM_INT);
$stmt->execute();
$comments = $stmt->fetchAll();

// counts for tab badges
$tab_counts = [];
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM dbProj_comments WHERE status = :s");
foreach (['approved', 'removed'] as $s) {
    $count_stmt->execute([':s' => $s]);
    $tab_counts[$s] = $count_stmt->fetchColumn();
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Comment Moderation | <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head><body>
<?php include '../includes/admin-nav.php'; ?>
<div class="admin-wrap"><main class="admin-main">

    <div class="page-header">
        <div><h1><i class="fas fa-comments"></i> Comment Moderation</h1>
        <p>Review, approve, or remove user comments</p></div>
    </div>
    <?php displayFlashMessage(); ?>

    <!-- filter tabs by status -->
    <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
        <?php foreach (['all'=>'gray','approved'=>'success','removed'=>'danger'] as $s => $badge): ?>
        <a href="?status=<?= $s ?>"
           class="btn btn-sm <?= $status_f===$s?'btn-primary':'btn-outline' ?>">
            <?= ucfirst($s) ?>
            <?php if (isset($tab_counts[$s])): ?>
                <span style="background:rgba(255,255,255,.25);padding:1px 7px;border-radius:10px;font-size:11px;">
                    <?= $tab_counts[$s] ?>
                </span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($comments)): ?>
        <div class="empty-state">
            <i class="fas fa-comment-slash"></i>
            <h3>No <?= $status_f ?> comments</h3>
            <p>Nothing to moderate right now.</p>
        </div>
    <?php else: ?>
    <div class="admin-card">
        <div class="admin-card-body" style="padding:0;">
        <table class="admin-table">
            <thead><tr><th>User</th><th>Comment</th><th>Tutorial</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($comments as $c): ?>
            <tr>
                <td>
                    <strong><?= e($c['user_name']) ?></strong><br>
                    <small style="color:#a0aec0;"><?= e($c['user_email']) ?></small>
                </td>
                <td style="max-width:280px;">
                    <p style="font-size:13px;color:#4a5568;line-height:1.5;">
                        <?= e(truncate($c['comment_text'], 120)) ?>
                    </p>
                </td>
                <td>
                    <a href="<?= SITE_URL ?>/viewer/tutorial-view.php?slug=<?= urlencode($c['tutorial_slug']) ?>"
                       style="color:#667eea;font-size:13px;" target="_blank">
                        <?= e(truncate($c['tutorial_title'], 35)) ?>
                        <i class="fas fa-external-link-alt" style="font-size:10px;"></i>
                    </a>
                </td>
                <td>
                    <!-- badge colour by status -->
                    <span class="badge badge-<?= $c['status']==='approved'?'success':'danger' ?>">
                        <?= ucfirst($c['status']) ?>
                    </span>
                </td>
                <td style="font-size:12px;color:#718096;"><?= timeAgo($c['created_at']) ?></td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <!-- approve only if not already approved -->
                        <?php if ($c['status'] !== 'approved'): ?>
                        <form method="POST" style="display:inline;">
                            <?php csrfField(); ?>
                            <input type="hidden" name="comment_id" value="<?= $c['comment_id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <button class="btn-icon success" title="Approve">
                                <i class="fas fa-check"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                        <!-- remove only if not already removed -->
                        <?php if ($c['status'] !== 'removed'): ?>
                        <form method="POST" style="display:inline;">
                            <?php csrfField(); ?>
                            <input type="hidden" name="comment_id" value="<?= $c['comment_id'] ?>">
                            <input type="hidden" name="action" value="remove">
                            <button class="btn-icon danger" title="Remove (hide from users)">
                                <i class="fas fa-eye-slash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                        <!-- always available permanent delete -->
                        <form method="POST" style="display:inline;"
                              onsubmit="return confirm('Permanently delete this comment?')">
                            <?php csrfField(); ?>
                            <input type="hidden" name="comment_id" value="<?= $c['comment_id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button class="btn-icon danger" title="Delete permanently">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?status=<?= $status_f ?>&page=<?= $page-1 ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-chevron-left"></i> Prev
            </a>
        <?php endif; ?>
        <span class="page-info">Page <?= $page ?> of <?= $total_pages ?></span>
        <?php if ($page < $total_pages): ?>
            <a href="?status=<?= $status_f ?>&page=<?= $page+1 ?>" class="btn btn-outline btn-sm">
                Next <i class="fas fa-chevron-right"></i>
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</main></div>
</body></html>
