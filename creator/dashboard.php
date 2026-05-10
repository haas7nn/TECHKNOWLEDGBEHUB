<?php
/**
 * Creator Dashboard
 * Shows actual statistics from database
 * Hasan Fardan - 202301686
 */

require_once '../includes/auth-check.php';
require_once '../classes/User.php';
require_once '../classes/Tutorial.php';

$page_title = 'Creator Dashboard';

// Get real user stats
$user = new User();
$user_stats = $user->getUserStats($current_user_id);

// Get instructor's tutorials
$tutorial = new Tutorial();
$my_tutorials = $tutorial->getByInstructor($current_user_id);

// Calculate statistics from actual data
$total_tutorials = count($my_tutorials);
$total_views = 0;
$total_ratings_count = 0;
$total_rating_sum = 0;

foreach ($my_tutorials as $tut) {
    $total_views += $tut['view_count'];
    $total_ratings_count += $tut['rating_count'];
    $total_rating_sum += ($tut['avg_rating'] * $tut['rating_count']);
}

$avg_rating = $total_ratings_count > 0 ? $total_rating_sum / $total_ratings_count : 0;

// Get recent 5 tutorials for display
$recent_tutorials = array_slice($my_tutorials, 0, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= asset('css/creator.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/creator-nav.php'; ?>
    
    <div class="dashboard-container">
        <?php include '../includes/creator-sidebar.php'; ?>
        
        <main class="dashboard-main">
            <div class="dashboard-header">
                <div>
                    <h1>Welcome back, <?= e($current_user_name) ?>! 👋</h1>
                    <p>Here's what's happening with your tutorials today.</p>
                </div>
                <a href="create-tutorial.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Create New Tutorial
                </a>
            </div>
            
            <?php displayFlashMessage(); ?>
            
            <!-- Real Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
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
                    <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
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
                    <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?= number_format($avg_rating, 1) ?></h3>
                        <p>Average Rating</p>
                        <div class="star-rating-small">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?= $i <= round($avg_rating) ? 'filled' : '' ?>"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
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
            
            <!-- Quick Actions -->
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
            
            <!-- Recent Tutorials Table -->
            <div class="recent-section">
                <div class="section-header">
                    <h2>Recent Tutorials</h2>
                    <a href="my-tutorials.php" class="btn btn-outline">View All</a>
                </div>
                
                <?php if (empty($recent_tutorials)): ?>
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
                                            <span style="color: #ffc107;">⭐</span>
                                            <?= number_format($tut['avg_rating'] ?? 0, 1) ?>
                                            <small>(<?= $tut['rating_count'] ?>)</small>
                                        </td>
                                        <td><?= formatDate($tut['created_at']) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="edit-tutorial.php?id=<?= $tut['tutorial_id'] ?>" class="btn-icon" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="../public/search.php?q=<?= urlencode($tut['title']) ?>" class="btn-icon" title="View" target="_blank">
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
            
            <!-- Performance Chart Placeholder -->
            <div class="chart-section">
                <h2><i class="fas fa-chart-line"></i> Performance Overview</h2>
                <div class="chart-placeholder">
                    <div style="text-align: center; padding: 40px; color: #999;">
                        <i class="fas fa-chart-area" style="font-size: 60px; margin-bottom: 20px;"></i>
                        <p><strong>Total Views Trend:</strong> <?= number_format($total_views) ?></p>
                        <p><strong>Avg Rating:</strong> <?= number_format($avg_rating, 2) ?>/5.0</p>
                        <p><strong>Total Engagement:</strong> <?= $total_ratings_count ?> ratings</p>
                        <small>Advanced analytics coming soon</small>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="<?= asset('js/creator.js') ?>"></script>
</body>
</html>