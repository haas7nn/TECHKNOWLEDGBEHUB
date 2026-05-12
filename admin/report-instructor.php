<?php
/**
 * Admin Report: Content Created by a Specific Instructor
 * Spec requirement: "Content created by a specific user"
 * Uses stored procedure GetInstructorReport
 */
require_once '../includes/admin-check.php';

$db   = new Database();
$conn = $db->connect();
if (!$conn) { setFlashMessage("Database error. Please try again.", "error"); redirect("admin/dashboard.php"); }

$instructor_id = (int)($_GET['instructor_id'] ?? 0);

// get all creators for the dropdown
$instructors = $conn->query(
    "SELECT user_id, full_name, email FROM dbProj_users
     WHERE role IN ('creator','admin') ORDER BY full_name"
)->fetchAll();

$report = [];
$instructor_info = null;

if ($instructor_id) {
    $instructor_info = $conn->prepare("SELECT * FROM dbProj_users WHERE user_id=:id");
    $instructor_info->bindParam(':id', $instructor_id, PDO::PARAM_INT);
    $instructor_info->execute();
    $instructor_info = $instructor_info->fetch();

    // use stored procedure
    try {
        $stmt = $conn->prepare("CALL GetInstructorReport(:instructor_id)");
        $stmt->bindParam(':instructor_id', $instructor_id, PDO::PARAM_INT);
        $stmt->execute();
        $report = $stmt->fetchAll();
    } catch (PDOException $e) {
        // fallback query
        $stmt = $conn->prepare(
            "SELECT t.tutorial_id, t.title, t.slug, t.status, t.view_count,
                    t.created_at, t.published_at, c.category_name,
                    COALESCE(AVG(r.rating),0) as avg_rating,
                    COUNT(DISTINCT r.rating_id) as rating_count,
                    COUNT(DISTINCT cm.comment_id) as comment_count
             FROM dbProj_tutorials t
             JOIN dbProj_categories c ON t.category_id = c.category_id
             LEFT JOIN dbProj_ratings r ON t.tutorial_id = r.tutorial_id
             LEFT JOIN dbProj_comments cm ON t.tutorial_id = cm.tutorial_id AND cm.status='approved'
             WHERE t.instructor_id = :instructor_id
             GROUP BY t.tutorial_id
             ORDER BY t.created_at DESC"
        );
        $stmt->bindParam(':instructor_id', $instructor_id, PDO::PARAM_INT);
        $stmt->execute();
        $report = $stmt->fetchAll();
    }
}

// summary stats
$total_views   = array_sum(array_column($report, 'view_count'));
$avg_rating_all = count($report) ? array_sum(array_column($report, 'avg_rating')) / count($report) : 0;
$published_count = count(array_filter($report, fn($r) => $r['status'] === 'published'));
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Instructor Report - <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head><body>
<?php include '../includes/admin-nav.php'; ?>
<div class="admin-wrap"><main class="admin-main">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-chalkboard-teacher"></i> Instructor Performance Report</h1>
            <p>View all content created by a specific instructor</p>
        </div>
    </div>
    <?php displayFlashMessage(); ?>

    <div class="admin-card" style="margin-bottom:24px;">
        <div class="admin-card-header"><h2><i class="fas fa-filter"></i> Select Instructor</h2></div>
        <div class="admin-card-body">
            <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
                <div style="flex:1;min-width:250px;">
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px;">Instructor</label>
                    <select name="instructor_id" class="form-control">
                        <option value="">-- Select an Instructor --</option>
                        <?php foreach ($instructors as $ins): ?>
                            <option value="<?= $ins['user_id'] ?>"
                                    <?= $instructor_id===$ins['user_id']?'selected':'' ?>>
                                <?= e($ins['full_name']) ?> (<?= e($ins['email']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-chart-bar"></i> Generate Report
                </button>
            </form>
        </div>
    </div>

    <?php if ($instructor_id && $instructor_info): ?>

    <!-- instructor summary cards -->
    <div class="stats-grid" style="margin-bottom:24px;">
        <div class="stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#667eea,#764ba2)"><i class="fas fa-book"></i></div>
            <div class="stat-details"><h3><?= count($report) ?></h3><p>Total Tutorials</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#38a169,#2f855a)"><i class="fas fa-check-circle"></i></div>
            <div class="stat-details"><h3><?= $published_count ?></h3><p>Published</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#4facfe,#00f2fe)"><i class="fas fa-eye"></i></div>
            <div class="stat-details"><h3><?= number_format($total_views) ?></h3><p>Total Views</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#fa709a,#fee140)"><i class="fas fa-star"></i></div>
            <div class="stat-details"><h3><?= number_format($avg_rating_all, 1) ?></h3><p>Avg Rating</p></div>
        </div>
    </div>

    <?php if (empty($report)): ?>
        <div class="empty-state">
            <i class="fas fa-book-open"></i>
            <h3><?= e($instructor_info['full_name']) ?> has no tutorials yet</h3>
        </div>
    <?php else: ?>
    <div class="admin-card">
        <div class="admin-card-header">
            <h2><i class="fas fa-user"></i> <?= e($instructor_info['full_name']) ?>'s Tutorials</h2>
            <span class="badge badge-info"><?= e($instructor_info['email']) ?></span>
        </div>
        <div class="admin-card-body" style="padding:0;">
        <table class="admin-table">
            <thead><tr><th>Title</th><th>Category</th><th>Status</th><th>Views</th><th>Rating</th><th>Comments</th><th>Published</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($report as $t): ?>
            <tr>
                <td><strong><?= e(truncate($t['title'], 45)) ?></strong></td>
                <td><span class="badge badge-info"><?= e($t['category_name']) ?></span></td>
                <td>
                    <span class="badge badge-<?= $t['status']==='published'?'success':($t['status']==='draft'?'warning':'gray') ?>">
                        <?= ucfirst($t['status']) ?>
                    </span>
                </td>
                <td><?= number_format($t['view_count']) ?></td>
                <td>
                    <i class="fas fa-star" style="color:#ffc107;"></i>
                    <?= number_format($t['avg_rating'], 1) ?>
                    <small style="color:#a0aec0;">(<?= $t['rating_count'] ?>)</small>
                </td>
                <td><?= $t['comment_count'] ?></td>
                <td style="font-size:12px;color:#718096;">
                    <?= !empty($t['published_at']) ? formatDate($t['published_at']) : '—' ?>
                </td>
                <td>
                    <a href="<?= SITE_URL ?>/viewer/tutorial-view.php?slug=<?= urlencode($t['slug']) ?>"
                       class="btn-icon" target="_blank" title="View">
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>
    <?php elseif ($instructor_id && !$instructor_info): ?>
        <div class="empty-state"><i class="fas fa-user-slash"></i><h3>Instructor not found</h3></div>
    <?php endif; ?>
</main></div>
</body></html>
