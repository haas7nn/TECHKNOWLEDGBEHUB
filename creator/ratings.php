<?php
// ratings for own tutorials

require_once '../includes/auth-check.php';
require_once '../classes/Tutorial.php';

$page_title = 'Ratings';

// db connect
$db   = new Database();
$conn = $db->connect();
if (!$conn) {
    setFlashMessage('Database error. Please try again.', 'error');
    redirect('creator/dashboard.php');
}

// get own tutorial ids
$tutObj = new Tutorial();
$my_tutorials = $tutObj->getByInstructor($current_user_id);
$tutorial_ids = array_column($my_tutorials, 'tutorial_id');

// default values before queries run
$ratings      = [];
$overall_avg  = 0;
$total_ratings = 0;
$dist = [5=>0, 4=>0, 3=>0, 2=>0, 1=>0];

// safe subquery for own tutorials
$subquery = "SELECT tutorial_id FROM dbProj_tutorials WHERE instructor_id = :uid";

if (!empty($tutorial_ids)) {
    // avg rating per tutorial
    $tRatingStmt = $conn->prepare(
        "SELECT t.tutorial_id, t.title, t.slug, t.status,
                COALESCE(AVG(r.rating),0) AS avg_rating,
                COUNT(r.rating_id) AS rating_count
         FROM dbProj_tutorials t
         LEFT JOIN dbProj_ratings r ON t.tutorial_id = r.tutorial_id
         WHERE t.tutorial_id IN ($subquery)
         GROUP BY t.tutorial_id
         ORDER BY avg_rating DESC"
    );
    $tRatingStmt->execute([':uid' => $current_user_id]);
    $ratings = $tRatingStmt->fetchAll(PDO::FETCH_ASSOC);

    // weighted overall average
    foreach ($ratings as $r) {
        $total_ratings += (int)$r['rating_count'];
        $overall_avg   += (float)$r['avg_rating'] * (int)$r['rating_count'];
    }
    $overall_avg = $total_ratings > 0 ? round($overall_avg / $total_ratings, 2) : 0;

    // count ratings by star level
    $distStmt = $conn->prepare(
        "SELECT rating, COUNT(*) AS cnt
         FROM dbProj_ratings
         WHERE tutorial_id IN ($subquery)
         GROUP BY rating"
    );
    $distStmt->execute([':uid' => $current_user_id]);
    foreach ($distStmt->fetchAll() as $row) {
        $dist[(int)$row['rating']] = (int)$row['cnt'];
    }
}
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
        .ratings-top { display: grid; grid-template-columns: 1fr 2fr; gap: 24px; margin-bottom: 28px; }
        @media(max-width:700px){ .ratings-top{ grid-template-columns:1fr; } }
        .big-rating-card { background: linear-gradient(135deg,#667eea,#764ba2); border-radius: 16px;
                           padding: 32px; text-align: center; color: #fff; }
        .big-rating-num { font-size: 64px; font-weight: 800; line-height: 1; }
        .big-rating-stars { font-size: 24px; margin: 8px 0; color: #ffd700; }
        .big-rating-sub { font-size: 14px; opacity: .8; }
        .dist-card { background: #fff; border-radius: 16px; padding: 28px;
                     box-shadow: 0 2px 8px rgba(0,0,0,.07); }
        .dist-card h3 { font-size: 16px; font-weight: 600; margin-bottom: 18px; color: #2c3e50; }
        .dist-row { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .dist-label { font-size: 13px; color: #7f8c8d; width: 40px; flex-shrink: 0; text-align: right; }
        .dist-bar-wrap { flex: 1; background: #f0f2f5; border-radius: 6px; height: 10px; }
        .dist-bar { height: 100%; border-radius: 6px; background: linear-gradient(90deg,#f39c12,#e67e22); }
        .dist-count { font-size: 13px; color: #2c3e50; font-weight: 600; width: 30px; }
        .tut-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .tut-table th { padding: 12px 16px; text-align: left; color: #7f8c8d; font-weight: 600;
                        border-bottom: 2px solid #f0f0f0; background: #f8f9fa; }
        .tut-table td { padding: 14px 16px; border-bottom: 1px solid #f5f5f5; }
        .star-filled { color: #f1c40f; }
        .star-empty  { color: #ddd; }
        .section-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.07);
                        overflow: hidden; margin-bottom: 24px; }
        .section-header { padding: 18px 24px; border-bottom: 1px solid #f0f0f0; }
        .section-header h2 { font-size: 16px; font-weight: 600; color: #2c3e50; margin: 0; }
        .empty-state { text-align: center; padding: 60px 20px; color: #7f8c8d; }
        .empty-state i { font-size: 48px; color: #ddd; margin-bottom: 16px; display: block; }
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
                <h1><i class="fas fa-star" style="color:#f1c40f;"></i> Ratings</h1>
                <p>See how learners are rating your tutorials</p>
            </div>
        </div>

        <?php displayFlashMessage(); ?>

        <?php if ($total_ratings === 0): ?>
            <!-- no ratings yet -->
            <div class="empty-state" style="background:#fff; border-radius:16px; box-shadow:0 2px 8px rgba(0,0,0,.07);">
                <i class="fas fa-star"></i>
                <h3>No Ratings Yet</h3>
                <p>Once learners rate your tutorials, the data will appear here.</p>
                <?php if (empty($my_tutorials)): ?>
                    <a href="create-tutorial.php" class="btn btn-primary" style="margin-top:16px;">Create Your First Tutorial</a>
                <?php endif; ?>
            </div>
        <?php else: ?>

        <!-- avg rating card and distribution chart -->
        <div class="ratings-top">

            <!-- big overall average card -->
            <div class="big-rating-card">
                <div class="big-rating-num"><?= number_format($overall_avg, 1) ?></div>
                <div class="big-rating-stars">
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                        <i class="<?= $s <= round($overall_avg) ? 'fas' : 'far' ?> fa-star" style="color:<?= $s <= round($overall_avg)?'#ffd700':'rgba(255,255,255,.4)' ?>;"></i>
                    <?php endfor; ?>
                </div>
                <div class="big-rating-sub"><?= number_format($total_ratings) ?> total rating<?= $total_ratings!==1?'s':'' ?></div>
            </div>

            <!-- star distribution bars -->
            <div class="dist-card">
                <h3><i class="fas fa-chart-bar" style="color:#667eea; margin-right:6px;"></i> Rating Distribution</h3>
                <?php for ($star = 5; $star >= 1; $star--): ?>
                <div class="dist-row">
                    <div class="dist-label"><i class="fas fa-star star-filled"></i> <?= $star ?></div>
                    <div class="dist-bar-wrap">
                        <div class="dist-bar" style="width:<?= $total_ratings > 0 ? round($dist[$star]/$total_ratings*100) : 0 ?>%;"></div>
                    </div>
                    <div class="dist-count"><?= $dist[$star] ?></div>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- ratings broken down by tutorial -->
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fas fa-list" style="margin-right:8px;"></i> Ratings by Tutorial</h2>
            </div>
            <div style="overflow-x:auto;">
                <table class="tut-table">
                    <thead>
                        <tr>
                            <th>Tutorial</th>
                            <th>Status</th>
                            <th>Avg Rating</th>
                            <th>Total Votes</th>
                            <th>Stars</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($ratings as $r): ?>
                        <tr>
                            <td>
                                <strong><?= e(truncate($r['title'], 50)) ?></strong>
                            </td>
                            <td>
                                <!-- status badge colour -->
                                <span style="padding:3px 10px; border-radius:20px; font-size:12px; font-weight:600;
                                    background:<?= $r['status']==='published'?'#e8f8f0':'#fff3e0' ?>;
                                    color:<?= $r['status']==='published'?'#27ae60':'#f39c12' ?>;">
                                    <?= ucfirst(e($r['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($r['rating_count'] > 0): ?>
                                    <strong style="color:#2c3e50;"><?= number_format($r['avg_rating'], 1) ?></strong>
                                <?php else: ?>
                                    <span style="color:#bbb;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="color:#7f8c8d;"><?= $r['rating_count'] ?> vote<?= $r['rating_count']!==1?'s':'' ?></td>
                            <td>
                                <?php
                                // filled stars up to rounded avg
                                $filled = round($r['avg_rating']);
                                for ($s = 1; $s <= 5; $s++):
                                ?>
                                    <i class="fas fa-star" style="color:<?= $s <= $filled ? '#f1c40f' : '#ddd' ?>; font-size:14px;"></i>
                                <?php endfor; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>
<script src="<?= asset('js/creator.js') ?>"></script>
</body>
</html>
