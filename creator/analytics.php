<?php
// creator analytics aggregation

require_once '../includes/auth-check.php';
require_once '../classes/Tutorial.php';

$page_title = 'Analytics';

// db connect
$db   = new Database();
$conn = $db->connect();

// default stat values
$my_tutorials  = [];
$total_views   = 0;
$total_ratings = 0;
$avg_rating    = 0;
$total_comments = 0;
$monthly_views  = [];
$top_tutorials  = [];

if ($conn) {
    // fetch own tutorials
    $tutObj = new Tutorial();
    $my_tutorials = $tutObj->getByInstructor($current_user_id);

    // sum total views
    foreach ($my_tutorials as $t) {
        $total_views += (int)($t['view_count'] ?? 0);
    }

    // avg rating via safe subquery
    $rStmt = $conn->prepare(
        "SELECT COUNT(*) AS cnt, COALESCE(AVG(rating),0) AS avg
         FROM dbProj_ratings
         WHERE tutorial_id IN (
             SELECT tutorial_id FROM dbProj_tutorials
             WHERE instructor_id = :uid AND status = 'published'
         )"
    );
    $rStmt->execute([':uid' => $current_user_id]);
    $rRow = $rStmt->fetch(PDO::FETCH_ASSOC);
    $total_ratings = (int)($rRow['cnt'] ?? 0);
    $avg_rating    = $total_ratings > 0 ? round((float)$rRow['avg'], 1) : 0;

    // count approved comments
    $cStmt = $conn->prepare(
        "SELECT COUNT(*) FROM dbProj_comments
         WHERE tutorial_id IN (
             SELECT tutorial_id FROM dbProj_tutorials
             WHERE instructor_id = :uid AND status = 'published'
         ) AND status = 'approved'"
    );
    $cStmt->execute([':uid' => $current_user_id]);
    $total_comments = (int)($cStmt->fetchColumn() ?: 0);

    // monthly views last six months
    $mStmt = $conn->prepare(
        "SELECT DATE_FORMAT(activity_date,'%Y-%m') AS month, COUNT(*) AS views
         FROM dbProj_user_activity
         WHERE tutorial_id IN (
             SELECT tutorial_id FROM dbProj_tutorials
             WHERE instructor_id = :uid AND status = 'published'
         )
           AND activity_type = 'view'
           AND activity_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
         GROUP BY month
         ORDER BY month ASC"
    );
    $mStmt->execute([':uid' => $current_user_id]);
    $monthly_views = $mStmt->fetchAll(PDO::FETCH_ASSOC);

    // sort copy for top five without changing original
    $sorted = $my_tutorials;
    usort($sorted, fn($a,$b) => (int)$b['view_count'] - (int)$a['view_count']);
    $top_tutorials = array_slice($sorted, 0, 5);
}

// count published vs draft
$published_count = count(array_filter($my_tutorials, fn($t) => $t['status'] === 'published'));
$draft_count     = count(array_filter($my_tutorials, fn($t) => $t['status'] === 'draft'));
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
        .analytics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap: 20px; margin-bottom: 28px; }
        /* stat colours live in creator.css */
        .stat-info h3 { font-size:24px; font-weight:700; margin:0 0 2px; color:#2c3e50; }
        .stat-info p  { font-size:12px; color:#7f8c8d; margin:0; }
        .two-col { display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:24px; }
        @media(max-width:750px){ .two-col{ grid-template-columns:1fr; } }
        .section-card { background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,.07); overflow:hidden; margin-bottom:24px; }
        .section-header { padding:16px 24px; border-bottom:1px solid #f0f0f0; display:flex; align-items:center; justify-content:space-between; }
        .section-header h2 { font-size:15px; font-weight:600; color:#2c3e50; margin:0; }
        .section-body { padding:22px 24px; }
        .tut-row { display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid #f5f5f5; }
        .tut-row:last-child { border-bottom:none; }
        .tut-rank { width:26px; height:26px; border-radius:50%; background:#667eea; color:#fff; font-size:11px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .tut-rank.r1 { background:#f1c40f; color:#333; }
        .tut-rank.r2 { background:#bdc3c7; }
        .tut-rank.r3 { background:#cd7f32; }
        .tut-info { flex:1; min-width:0; }
        .tut-info strong { display:block; font-size:13px; color:#2c3e50; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .tut-info span { font-size:11px; color:#7f8c8d; }
        .bar-wrap { background:#f0f2f5; border-radius:6px; height:7px; flex:1; margin:0 10px; }
        .bar-fill { height:100%; border-radius:6px; background:linear-gradient(90deg,#667eea,#764ba2); }
        .tut-views { font-size:13px; font-weight:600; color:#667eea; white-space:nowrap; }
        .chart-container { display:flex; align-items:flex-end; gap:10px; height:130px; padding-bottom:4px; }
        .chart-bar-group { flex:1; display:flex; flex-direction:column; align-items:center; gap:4px; }
        .chart-bar { width:100%; border-radius:4px 4px 0 0; background:linear-gradient(180deg,#667eea,#764ba2); min-height:4px; }
        .chart-label { font-size:10px; color:#7f8c8d; }
        .chart-value { font-size:10px; color:#2c3e50; font-weight:600; }
        .empty-note { text-align:center; padding:36px 20px; color:#7f8c8d; }
        .empty-note i { font-size:32px; margin-bottom:10px; display:block; color:#ddd; }
        .btn-small { padding:6px 14px; font-size:12px; border-radius:6px; text-decoration:none;
                     background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; font-weight:600; }
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
                <h1><i class="fas fa-chart-line" style="color:#667eea;"></i> Analytics</h1>
                <p>Track the performance of your tutorials</p>
            </div>
        </div>

        <?php displayFlashMessage(); ?>

        <!-- six summary stat cards -->
        <div class="analytics-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-eye"></i></div>
                <div class="stat-info"><h3><?= number_format($total_views) ?></h3><p>Total Views</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon teal"><i class="fas fa-book"></i></div>
                <div class="stat-info"><h3><?= count($my_tutorials) ?></h3><p>Tutorials</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-star"></i></div>
                <div class="stat-info">
                    <h3><?= $avg_rating > 0 ? $avg_rating : 'N/A' ?></h3>
                    <p>Avg Rating (<?= $total_ratings ?> votes)</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-comments"></i></div>
                <div class="stat-info"><h3><?= number_format($total_comments) ?></h3><p>Comments</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info"><h3><?= $published_count ?></h3><p>Published</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-pencil-alt"></i></div>
                <div class="stat-info"><h3><?= $draft_count ?></h3><p>Drafts</p></div>
            </div>
        </div>

        <!-- top tutorials and monthly views side by side -->
        <div class="two-col">
            <!-- top five by views -->
            <div class="section-card">
                <div class="section-header">
                    <h2><i class="fas fa-trophy" style="color:#f1c40f; margin-right:6px;"></i> Top by Views</h2>
                </div>
                <div class="section-body" style="padding:14px 20px;">
                    <?php if (empty($top_tutorials)): ?>
                        <div class="empty-note"><i class="fas fa-chart-bar"></i><p>No tutorials yet.</p></div>
                    <?php else:
                        $max_views = max(1, (int)($top_tutorials[0]['view_count'] ?? 1));
                        foreach ($top_tutorials as $i => $t):
                            // gold silver bronze rank classes
                            $rankClass = $i===0?'r1':($i===1?'r2':($i===2?'r3':''));
                            $pct = $max_views > 0 ? round((int)$t['view_count'] / $max_views * 100) : 0;
                    ?>
                        <div class="tut-row">
                            <div class="tut-rank <?= $rankClass ?>"><?= $i+1 ?></div>
                            <div class="tut-info">
                                <strong title="<?= e($t['title']) ?>"><?= e(truncate($t['title'],36)) ?></strong>
                                <span><?= ucfirst(e($t['status'])) ?></span>
                            </div>
                            <!-- relative view bar -->
                            <div class="bar-wrap"><div class="bar-fill" style="width:<?= $pct ?>%;"></div></div>
                            <div class="tut-views"><i class="fas fa-eye"></i> <?= number_format($t['view_count']) ?></div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- monthly views bar chart -->
            <div class="section-card">
                <div class="section-header">
                    <h2><i class="fas fa-calendar-alt" style="color:#667eea; margin-right:6px;"></i> Views Last 6 Months</h2>
                </div>
                <div class="section-body">
                    <?php if (empty($monthly_views)): ?>
                        <div class="empty-note"><i class="fas fa-chart-area"></i><p>No activity data yet.</p></div>
                    <?php else:
                        $max_mv = max(1, max(array_column($monthly_views,'views')));
                    ?>
                        <div class="chart-container">
                            <?php foreach ($monthly_views as $mv):
                                // bar height scaled to busiest month
                                $h = max(4, round($mv['views'] / $max_mv * 110));
                            ?>
                            <div class="chart-bar-group">
                                <div class="chart-value"><?= number_format($mv['views']) ?></div>
                                <div class="chart-bar" style="height:<?= $h ?>px;" title="<?= e($mv['month']) ?>: <?= $mv['views'] ?> views"></div>
                                <div class="chart-label"><?= date('M', strtotime($mv['month'].'-01')) ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- all tutorials performance table -->
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fas fa-list" style="margin-right:6px;"></i> All Tutorial Performance</h2>
                <a href="create-tutorial.php" class="btn-small"><i class="fas fa-plus"></i> New</a>
            </div>
            <?php if (empty($my_tutorials)): ?>
                <!-- no tutorials yet -->
                <div class="empty-note" style="padding:50px 20px;">
                    <i class="fas fa-book-open"></i>
                    <p>You haven't created any tutorials yet.</p>
                    <a href="create-tutorial.php" class="btn-small" style="display:inline-block; margin-top:12px;">Create Tutorial</a>
                </div>
            <?php else: ?>
            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:14px;">
                    <thead>
                        <tr style="background:#f8f9fa;">
                            <th style="padding:12px 16px; text-align:left; color:#7f8c8d; font-weight:600; border-bottom:2px solid #f0f0f0;">Title</th>
                            <th style="padding:12px 16px; text-align:center; color:#7f8c8d; font-weight:600; border-bottom:2px solid #f0f0f0;">Status</th>
                            <th style="padding:12px 16px; text-align:center; color:#7f8c8d; font-weight:600; border-bottom:2px solid #f0f0f0;">Views</th>
                            <th style="padding:12px 16px; text-align:center; color:#7f8c8d; font-weight:600; border-bottom:2px solid #f0f0f0;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($my_tutorials as $t): ?>
                        <tr style="border-bottom:1px solid #f5f5f5;">
                            <td style="padding:12px 16px;">
                                <strong><?= e(truncate($t['title'],50)) ?></strong>
                                <div style="font-size:12px; color:#7f8c8d;"><?= formatDate($t['created_at']) ?></div>
                            </td>
                            <td style="padding:12px 16px; text-align:center;">
                                <!-- status badge colour -->
                                <span style="padding:3px 10px; border-radius:20px; font-size:12px; font-weight:600;
                                    background:<?= $t['status']==='published'?'#e8f8f0':'#fff3e0' ?>;
                                    color:<?= $t['status']==='published'?'#27ae60':'#f39c12' ?>;">
                                    <?= ucfirst(e($t['status'])) ?>
                                </span>
                            </td>
                            <td style="padding:12px 16px; text-align:center; font-weight:600; color:#667eea;">
                                <i class="fas fa-eye" style="font-size:12px;"></i> <?= number_format($t['view_count']) ?>
                            </td>
                            <td style="padding:12px 16px; text-align:center;">
                                <a href="edit-tutorial.php?id=<?= $t['tutorial_id'] ?>" title="Edit" style="color:#667eea; margin:0 6px;"><i class="fas fa-edit"></i></a>
                                <?php if ($t['status']==='published'): ?>
                                <!-- view link published only -->
                                <a href="<?= SITE_URL ?>/viewer/tutorial-view.php?slug=<?= urlencode($t['slug']) ?>"
                                   title="View" style="color:#27ae60; margin:0 6px;" target="_blank">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </main>
</div>
<script src="<?= asset('js/creator.js') ?>"></script>
</body>
</html>
