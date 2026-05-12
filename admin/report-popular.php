<?php
/**
 * Admin Report: Most Popular Tutorials Within a Date Range
 * Spec requirement: "Most popular content within a date range"
 * Uses stored procedure GetPopularTutorials
 */
require_once '../includes/admin-check.php';

$db   = new Database();
$conn = $db->connect();
if (!$conn) { setFlashMessage("Database error. Please try again.", "error"); redirect("admin/dashboard.php"); }

$date_from = clean($_GET['date_from'] ?? date('Y-m-01'));
$date_to   = clean($_GET['date_to']   ?? date('Y-m-d'));
$limit     = max(1, min(50, (int)($_GET['limit'] ?? 10)));

// use stored procedure (spec requirement: at least one stored procedure)
try {
    $stmt = $conn->prepare("CALL GetPopularTutorials(:date_from, :date_to, :lim)");
    $stmt->bindParam(':date_from', $date_from, PDO::PARAM_STR);
    $stmt->bindParam(':date_to',   $date_to,   PDO::PARAM_STR);
    $stmt->bindParam(':lim',       $limit,     PDO::PARAM_INT);
    $stmt->execute();
    $tutorials = $stmt->fetchAll();
} catch (PDOException $e) {
    // fallback if stored procedure not available
    $stmt = $conn->prepare(
        "SELECT t.tutorial_id, t.title, t.slug, t.view_count,
                u.full_name as instructor_name, c.category_name,
                COALESCE(AVG(r.rating),0) as avg_rating,
                COUNT(DISTINCT r.rating_id) as rating_count,
                COUNT(DISTINCT cm.comment_id) as comment_count
         FROM dbProj_tutorials t
         JOIN dbProj_users u ON t.instructor_id = u.user_id
         JOIN dbProj_categories c ON t.category_id = c.category_id
         LEFT JOIN dbProj_ratings r ON t.tutorial_id = r.tutorial_id
         LEFT JOIN dbProj_comments cm ON t.tutorial_id = cm.tutorial_id AND cm.status='approved'
         WHERE t.status='published'
           AND t.published_at IS NOT NULL
           AND DATE(t.published_at) BETWEEN :date_from AND :date_to
         GROUP BY t.tutorial_id
         ORDER BY t.view_count DESC
         LIMIT :lim"
    );
    $stmt->bindParam(':date_from', $date_from, PDO::PARAM_STR);
    $stmt->bindParam(':date_to',   $date_to,   PDO::PARAM_STR);
    $stmt->bindValue(':lim',       $limit,     PDO::PARAM_INT);
    $stmt->execute();
    $tutorials = $stmt->fetchAll();
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Popular Tutorials Report - <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head><body>
<?php include '../includes/admin-nav.php'; ?>
<div class="admin-wrap"><main class="admin-main">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-fire"></i> Popular Tutorials Report</h1>
            <p>Most viewed tutorials within a date range</p>
        </div>
    </div>
    <?php displayFlashMessage(); ?>

    <div class="admin-card" style="margin-bottom:24px;">
        <div class="admin-card-header"><h2><i class="fas fa-filter"></i> Filter Report</h2></div>
        <div class="admin-card-body">
            <form method="GET" class="report-filter">
                <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end;">
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px;">From Date</label>
                        <input type="date" name="date_from" value="<?= e($date_from) ?>"
                               class="form-control" style="width:180px;">
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px;">To Date</label>
                        <input type="date" name="date_to" value="<?= e($date_to) ?>"
                               class="form-control" style="width:180px;">
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;margin-bottom:5px;">Top N Results</label>
                        <select name="limit" class="form-control" style="width:120px;">
                            <?php foreach ([5,10,20,50] as $n): ?>
                                <option value="<?= $n ?>" <?= $limit===$n?'selected':'' ?>>Top <?= $n ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-bottom:1px;">
                        <i class="fas fa-chart-bar"></i> Generate Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($tutorials)): ?>
        <div class="empty-state">
            <i class="fas fa-chart-bar"></i>
            <h3>No data found for this period</h3>
            <p>Try adjusting the date range</p>
        </div>
    <?php else: ?>
    <div class="admin-card">
        <div class="admin-card-header">
            <h2><i class="fas fa-trophy"></i> Results: <?= date('d M Y', strtotime($date_from)) ?> — <?= date('d M Y', strtotime($date_to)) ?></h2>
            <span class="badge badge-info"><?= count($tutorials) ?> tutorials</span>
        </div>
        <div class="admin-card-body" style="padding:0;">
        <table class="admin-table">
            <thead><tr><th>#</th><th>Tutorial</th><th>Instructor</th><th>Category</th><th>Views</th><th>Rating</th><th>Comments</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($tutorials as $i => $t): ?>
            <tr>
                <td>
                    <span class="rank <?= $i===0?'rank-1':($i===1?'rank-2':($i===2?'rank-3':'rank-n')) ?>">
                        <?= $i + 1 ?>
                    </span>
                </td>
                <td><strong><?= e(truncate($t['title'], 50)) ?></strong></td>
                <td><?= e($t['instructor_name']) ?></td>
                <td><span class="badge badge-info"><?= e($t['category_name']) ?></span></td>
                <td>
                    <strong><?= number_format($t['view_count']) ?></strong>
                    <div style="background:#e8ecf1;border-radius:4px;height:6px;margin-top:4px;min-width:80px;">
                        <div style="background:#667eea;height:100%;border-radius:4px;width:<?= $tutorials[0]['view_count'] > 0 ? round($t['view_count']/$tutorials[0]['view_count']*100) : 0 ?>%;"></div>
                    </div>
                </td>
                <td>
                    <i class="fas fa-star" style="color:#ffc107;"></i>
                    <?= number_format($t['avg_rating'], 1) ?>
                    <small style="color:#a0aec0;">(<?= $t['rating_count'] ?>)</small>
                </td>
                <td><?= $t['comment_count'] ?></td>
                <td>
                    <a href="<?= SITE_URL ?>/viewer/tutorial-view.php?slug=<?= urlencode($t['slug']) ?>"
                       class="btn-icon" target="_blank" title="View Tutorial">
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
</main></div>
</body></html>
