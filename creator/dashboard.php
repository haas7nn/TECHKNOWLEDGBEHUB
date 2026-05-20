<?php
// creator dashboard that shows real stats pulled straight from the database

require_once '../includes/auth-check.php';
require_once '../classes/User.php';
require_once '../classes/Tutorial.php';

$page_title = 'Creator Dashboard';

// load the user object and get their overall stats
$user = new User();
$user_stats = $user->getUserStats($current_user_id);

// load all tutorials that belong to this instructor
$tutorial = new Tutorial();
$my_tutorials = $tutorial->getByInstructor($current_user_id);

// calculate total views rating count and rating sum by looping through the tutorials
$total_tutorials = count($my_tutorials);
$total_views = 0;
$total_ratings_count = 0;
$total_rating_sum = 0;

foreach ($my_tutorials as $tut) {
    $total_views += $tut['view_count'];
    $total_ratings_count += $tut['rating_count'];
    $total_rating_sum += ($tut['avg_rating'] * $tut['rating_count']);
}

// work out the weighted average rating across all tutorials
$avg_rating = $total_ratings_count > 0 ? $total_rating_sum / $total_ratings_count : 0;

// only show the five most recent tutorials in the recent table
$recent_tutorials = array_slice($my_tutorials, 0, 5);
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

            <!-- welcome heading with the create tutorial shortcut button -->
            <div class="dashboard-header">
                <div>
                    <h1><i class="fas fa-home"></i> Welcome back, <?= e($current_user_name) ?></h1>
                    <p>Here's what's happening with your tutorials today.</p>
                </div>
                <a href="create-tutorial.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Create New Tutorial
                </a>
            </div>

            <?php displayFlashMessage(); ?>

            <!-- four stat cards showing tutorial count, views, average rating and total ratings -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon si-purple">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?= $total_tutorials ?></h3>
                        <p>Total Tutorials</p>
                        <span class="stat-change <?= $total_tutorials > 0 ? 'positive' : '' ?>">
                            <i class="fas fa-<?= $total_tutorials > 0 ? 'check-circle' : 'info-circle' ?>"></i>
                            <?= $total_tutorials > 0 ? 'Active' : 'Get Started' ?>
                        </span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon si-pink">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?= number_format($total_views) ?></h3>
                        <p>Total Views</p>
                        <span class="stat-change <?= $total_views > 0 ? 'positive' : '' ?>">
                            <i class="fas fa-arrow-<?= $total_views > 0 ? 'up' : 'minus' ?>"></i>
                            <?= $total_views > 0 ? 'Growing' : 'No views yet' ?>
                        </span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon si-blue">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?= number_format($avg_rating, 1) ?></h3>
                        <p>Average Rating</p>
                        <!-- small star display showing the rounded average -->
                        <div class="star-rating-small">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?= $i <= round($avg_rating) ? 'filled' : '' ?>"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon si-gold">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?= $total_ratings_count ?></h3>
                        <p>Total Ratings</p>
                        <span class="stat-change">
                            <i class="fas fa-heart"></i>
                            <?= $total_ratings_count > 0 ? 'Engaged' : 'No ratings' ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- quick action shortcut cards for common tasks -->
            <div class="quick-actions">
                <h2>Quick Actions</h2>
                <div class="action-cards">
                    <a href="create-tutorial.php" class="action-card">
                        <i class="fas fa-plus-circle"></i>
                        <h3>Create Tutorial</h3>
                        <p>Share your knowledge with students</p>
                    </a>

                    <a href="my-tutorials.php" class="action-card">
                        <i class="fas fa-list"></i>
                        <h3>My Tutorials</h3>
                        <p>Manage your existing tutorials</p>
                    </a>

                    <a href="../public/search.php" class="action-card" target="_blank">
                        <i class="fas fa-search"></i>
                        <h3>Browse All</h3>
                        <p>See all platform tutorials</p>
                    </a>

                    <a href="../auth/logout.php" class="action-card">
                        <i class="fas fa-sign-out-alt"></i>
                        <h3>Logout</h3>
                        <p>End your session</p>
                    </a>
                </div>
            </div>

            <!-- table showing the five most recently created tutorials -->
            <div class="recent-section">
                <div class="section-header">
                    <h2>Recent Tutorials</h2>
                    <a href="my-tutorials.php" class="btn btn-outline">View All</a>
                </div>

                <?php if (empty($recent_tutorials)): ?>
                    <!-- empty state for instructors who have not created anything yet -->
                    <div class="empty-state">
                        <i class="fas fa-book-open"></i>
                        <h3>No tutorials yet</h3>
                        <p>Start creating your first tutorial to share your knowledge!</p>
                        <a href="create-tutorial.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Create Your First Tutorial
                        </a>
                    </div>
                <?php else: ?>
                    <!-- table listing title, status, views, rating, date and edit/view actions -->
                    <div class="tutorials-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Views</th>
                                    <th>Rating</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_tutorials as $tut): ?>
                                    <tr>
                                        <td>
                                            <div class="tutorial-title">
                                                <i class="fas fa-book"></i>
                                                <?= e($tut['title']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= $tut['status'] ?>">
                                                <?= ucfirst($tut['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= number_format($tut['view_count']) ?></td>
                                        <td>
                                            <i class="fas fa-star star-gold"></i>
                                            <?= number_format($tut['avg_rating'] ?? 0, 1) ?>
                                            <small>(<?= $tut['rating_count'] ?>)</small>
                                        </td>
                                        <td><?= formatDate($tut['created_at']) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="edit-tutorial.php?id=<?= $tut['tutorial_id'] ?>" class="btn-icon" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="<?= SITE_URL ?>/viewer/tutorial-view.php?<?= !empty($tut['slug']) ? 'slug=' . e($tut['slug']) : 'id=' . (int)$tut['tutorial_id'] ?>" class="btn-icon" title="View" target="_blank">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- bar and line chart showing views and average rating per tutorial -->
            <div class="chart-section">
                <h2><i class="fas fa-chart-line"></i> Performance Overview</h2>
                <div class="card-body">
                    <?php if (empty($my_tutorials)): ?>
                        <!-- placeholder when there are no tutorials to chart yet -->
                        <div class="empty-state"><i class="fas fa-chart-area"></i><p>No tutorials yet. Create your first to see performance data.</p></div>
                    <?php else: ?>
                        <canvas id="perfChart" height="120"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <?php if (!empty($my_tutorials)): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    (function() {
        // build the label and data arrays from the php tutorial data
        const labels  = <?= json_encode(array_map(
            fn($t) => strlen($t['title']) > 22 ? substr($t['title'], 0, 22) . '…' : $t['title'],
            $my_tutorials
        )) ?>;
        const views   = <?= json_encode(array_column($my_tutorials, 'view_count')) ?>;
        const ratings = <?= json_encode(array_map(fn($t) => round((float)$t['avg_rating'], 2), $my_tutorials)) ?>;

        // create a combined bar and line chart using two y axes
        new Chart(document.getElementById('perfChart'), {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Views',
                        data: views,
                        backgroundColor: 'rgba(102,126,234,0.7)',
                        borderColor: '#667eea',
                        borderWidth: 1,
                        yAxisID: 'yViews'
                    },
                    {
                        label: 'Avg Rating',
                        data: ratings,
                        type: 'line',
                        borderColor: '#f6ad55',
                        backgroundColor: 'rgba(246,173,85,0.15)',
                        borderWidth: 2,
                        pointRadius: 5,
                        tension: 0.3,
                        yAxisID: 'yRating'
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ' + ctx.parsed.y } }
                },
                scales: {
                    yViews:  { type: 'linear', position: 'left',  beginAtZero: true, title: { display: true, text: 'Views' } },
                    yRating: { type: 'linear', position: 'right', beginAtZero: true, max: 5, title: { display: true, text: 'Rating (0 to 5)' }, grid: { drawOnChartArea: false } }
                }
            }
        });
    })();
    </script>
    <?php endif; ?>

    <script src="<?= asset('js/creator.js') ?>"></script>
</body>
</html>
