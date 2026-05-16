<?php
// admin only
require_once '../includes/admin-check.php';

// db connect
$db   = new Database();
$conn = $db->connect();
// stop if db down
if (!$conn) { setFlashMessage("Database error. Please try again.", "error"); redirect("auth/login.php"); }

// handle admin action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfFromPost()) {
    // get action and tutorial
    $action      = clean($_POST['action']      ?? '');
    $tutorial_id = (int)($_POST['tutorial_id'] ?? 0);

    if ($tutorial_id) {
        if ($action === 'archive') {
            // archive the tutorial so it disappears from public view but is not permanently gone
            $conn->prepare("UPDATE dbProj_tutorials SET status='archived' WHERE tutorial_id=:id")
                 ->execute([':id' => $tutorial_id]);
            setFlashMessage('Tutorial has been archived and removed from public view.', 'success');
        } elseif ($action === 'publish') {
            // make the tutorial visible to everyone and record when it was published
            $conn->prepare("UPDATE dbProj_tutorials SET status='published', published_at=NOW() WHERE tutorial_id=:id")
                 ->execute([':id' => $tutorial_id]);
            setFlashMessage('Tutorial published successfully.', 'success');
        } elseif ($action === 'draft') {
            // move the tutorial back to draft so it is hidden from the public
            $conn->prepare("UPDATE dbProj_tutorials SET status='draft' WHERE tutorial_id=:id")
                 ->execute([':id' => $tutorial_id]);
            setFlashMessage('Tutorial moved to draft.', 'success');
        }
    }
    // redirect back
    redirect('admin/tutorials.php');
}

// get filters
$search     = clean($_GET['search']     ?? '');
$status_f   = clean($_GET['status']     ?? '');
$category_f = (int)($_GET['category']   ?? 0);
$page       = max(1, (int)($_GET['page'] ?? 1));
$per_page   = 15;
$offset     = ($page - 1) * $per_page;

// build filters
$where  = ['1=1'];
$params = [];
// add a title or instructor name search if the admin typed something
if ($search)     { $where[] = "(t.title LIKE :s1 OR u.full_name LIKE :s2)"; $params[':s1']="%$search%"; $params[':s2']="%$search%"; }
// filter by status if the admin picked one
if ($status_f)   { $where[] = "t.status=:status";      $params[':status']  = $status_f; }
// filter by category if the admin picked one
if ($category_f) { $where[] = "t.category_id=:cat_id"; $params[':cat_id']  = $category_f; }

// combine conditions
$where_sql = implode(' AND ', $where);

// count for pagination
$count_sql = "SELECT COUNT(*) FROM dbProj_tutorials t
              JOIN dbProj_users u ON t.instructor_id = u.user_id
              WHERE $where_sql";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($params);
$total      = $count_stmt->fetchColumn();
$total_pages = max(1, ceil($total / $per_page));

// main query
$sql = "SELECT t.*, u.full_name as instructor_name, c.category_name,
        COALESCE(AVG(r.rating),0) as avg_rating,
        COUNT(DISTINCT cm.comment_id) as comment_count
        FROM dbProj_tutorials t
        JOIN dbProj_users u ON t.instructor_id = u.user_id
        JOIN dbProj_categories c ON t.category_id = c.category_id
        LEFT JOIN dbProj_ratings r ON t.tutorial_id = r.tutorial_id
        LEFT JOIN dbProj_comments cm ON t.tutorial_id = cm.tutorial_id AND cm.status='approved'
        WHERE $where_sql
        GROUP BY t.tutorial_id
        ORDER BY t.created_at DESC
        LIMIT :lim OFFSET :off";
$stmt = $conn->prepare($sql);
// bind the filter params then bind pagination separately as integers
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':lim', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset,   PDO::PARAM_INT);
$stmt->execute();
$tutorials = $stmt->fetchAll();

// load all categories for the filter dropdown
$categories = $conn->query("SELECT * FROM dbProj_categories ORDER BY category_name")->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manage Tutorials | <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head><body>
<?php include '../includes/admin-nav.php'; ?>
<div class="admin-wrap"><main class="admin-main">

    <!-- page header with total count -->
    <div class="page-header">
        <div><h1><i class="fas fa-book"></i> Manage Tutorials</h1><p><?= $total ?> tutorials total</p></div>
    </div>
    <?php displayFlashMessage(); ?>

    <!-- filter bar for searching by title instructor status and category -->
    <form method="GET" class="filter-bar">
        <input type="text" name="search" placeholder="Search title or instructor..." value="<?= e($search) ?>">
        <!-- status dropdown -->
        <select name="status">
            <option value="">All Statuses</option>
            <?php foreach (['published','draft','archived'] as $s): ?>
            <option value="<?= $s ?>" <?= $status_f===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
        <!-- category dropdown -->
        <select name="category">
            <option value="0">All Categories</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['category_id'] ?>" <?= $category_f === (int)$cat['category_id'] ? 'selected' : '' ?>><?= e($cat['category_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
        <a href="tutorials.php" class="btn btn-outline btn-sm">Clear</a>
    </form>

    <!-- tutorials table -->
    <div class="admin-card">
        <div class="admin-card-body" style="padding:0;">
        <?php if (empty($tutorials)): ?>
            <!-- friendly message when no tutorials match the filters -->
            <div class="empty-state"><i class="fas fa-book-open"></i><h3>No tutorials found</h3></div>
        <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>Title</th><th>Instructor</th><th>Category</th><th>Status</th><th>Rating</th><th>Views</th><th>Comments</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($tutorials as $t): ?>
            <!-- one row per tutorial -->
            <tr>
                <td>
                    <strong><?= e(truncate($t['title'], 45)) ?></strong><br>
                    <small style="color:#a0aec0;"><?= formatDate($t['created_at']) ?></small>
                </td>
                <td><?= e($t['instructor_name']) ?></td>
                <td><span class="badge badge-info"><?= e($t['category_name']) ?></span></td>
                <td>
                    <!-- badge colour depends on whether the tutorial is published draft or archived -->
                    <span class="badge badge-<?= $t['status']==='published'?'success':($t['status']==='draft'?'warning':'gray') ?>">
                        <?= ucfirst($t['status']) ?>
                    </span>
                </td>
                <td>
                    <i class="fas fa-star" style="color:#ffc107;"></i>
                    <?= number_format($t['avg_rating'], 1) ?>
                </td>
                <td><?= number_format($t['view_count']) ?></td>
                <td><?= $t['comment_count'] ?></td>
                <td>
                    <!-- action buttons for viewing publishing and archiving -->
                    <div style="display:flex;gap:6px;">
                        <!-- link to view the tutorial on the public site -->
                        <a href="<?= SITE_URL ?>/viewer/tutorial-view.php?slug=<?= urlencode($t['slug']) ?>"
                           class="btn-icon" title="View" target="_blank">
                            <i class="fas fa-eye"></i>
                        </a>
                        <!-- publish button only shown when the tutorial is not already published -->
                        <?php if ($t['status'] !== 'published'): ?>
                        <form method="POST" style="display:inline;">
                            <?php csrfField(); ?>
                            <input type="hidden" name="tutorial_id" value="<?= $t['tutorial_id'] ?>">
                            <input type="hidden" name="action" value="publish">
                            <button class="btn-icon success" title="Publish"><i class="fas fa-check"></i></button>
                        </form>
                        <?php endif; ?>
                        <!-- archive button only shown when the tutorial is not already archived -->
                        <?php if ($t['status'] !== 'archived'): ?>
                        <form method="POST" style="display:inline;"
                              onsubmit="return confirm('Archive this tutorial? It will be removed from public view.')">
                            <?php csrfField(); ?>
                            <input type="hidden" name="tutorial_id" value="<?= $t['tutorial_id'] ?>">
                            <input type="hidden" name="action" value="archive">
                            <button class="btn-icon danger" title="Archive"><i class="fas fa-archive"></i></button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        </div>
    </div>

    <!-- pagination controls shown only when there is more than one page -->
    <?php if ($total_pages > 1): ?>
    <?php $base = http_build_query(['search'=>$search,'status'=>$status_f,'category'=>$category_f]); ?>
    <div class="pagination">
        <!-- previous page link -->
        <?php if ($page > 1): ?>
            <a href="?<?= $base ?>&page=<?= $page-1 ?>" class="btn btn-outline btn-sm"><i class="fas fa-chevron-left"></i> Prev</a>
        <?php endif; ?>
        <span class="page-info">Page <?= $page ?> of <?= $total_pages ?></span>
        <!-- next page link -->
        <?php if ($page < $total_pages): ?>
            <a href="?<?= $base ?>&page=<?= $page+1 ?>" class="btn btn-outline btn-sm">Next <i class="fas fa-chevron-right"></i></a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</main></div>
</body></html>
