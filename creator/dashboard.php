<?php
/**
 * main dashboard for creators
 * shows how tutorials are doing and stats
 * Hasan Fardan - 202301686
 */

require_once '../includes/auth-check.php';
require_once '../classes/User.php';

// just basic structure for now
$page_title = 'Creator Dashboard';

// getting user stats will do this right once the tutorial class is finished
$user = new User();
$user_stats = $user->getUserStats($current_user_id);

// using fake data until the tutorial class is ready to query the database
$total_tutorials = $user_stats['total_tutorials'] ?? 0;
$total_views = 0; // total views needs to be calculated from tutorials later
$total_ratings = $user_stats['total_ratings'] ?? 0;
$avg_rating = 0; // avg rating will come from tutorials later

// empty list for now until i pull from db
$recent_tutorials = [];
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
    <!-- top nav -->
    <?php include '../includes/creator-nav.php'; ?>
    
    <div class="dashboard-container">
        <!-- sidebar menu -->
        <?php include '../includes/creator-sidebar.php'; ?>
        
        <!-- the main stuff goes here -->
        <main class="dashboard-main">
            <!-- page header -->
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
            
            <!-- messages and alerts -->
            <?php displayFlashMessage(); ?>
            
            <!-- the 4 cards at the top -->
            <div class="stats-grid">
                <!-- how many tutorials total -->
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?= $total_tutorials ?></h3>
                        <p>Total Tutorials</p>
                        <span class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> Active
                        </span>
                    </div>
                </div>
                
                <!-- view counter -->
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?= number_format($total_views) ?></h3>
                        <p>Total Views</p>
                        <span class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> Growing
                        </span>
                    </div>
                </div>
                
                <!-- rating average -->
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
                
                <!-- how many people rated -->
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?= $total_ratings ?></h3>
                        <p>Total Ratings</p>
                        <span class="stat-change">
                            <i class="fas fa-heart"></i> Engaged
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- shortcut links -->
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
                    
                    <a href="analytics.php" class="action-card">
                        <i class="fas fa-chart-line"></i>
                        <h3>View Analytics</h3>
                        <p>Track your performance</p>
                    </a>
                    
                    <a href="profile.php" class="action-card">
                        <i class="fas fa-user-edit"></i>
                        <h3>Edit Profile</h3>
                        <p>Update your information</p>
                    </a>
                </div>
            </div>
            
            <!-- list of latest tutorials -->
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
                                <?php foreach ($recent_tutorials as $tutorial): ?>
                                    <tr>
                                        <td>
                                            <div class="tutorial-title">
                                                <i class="fas fa-book"></i>
                                                <?= e($tutorial['title']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= $tutorial['status'] ?>">
                                                <?= ucfirst($tutorial['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= number_format($tutorial['view_count']) ?></td>
                                        <td>⭐ <?= number_format($tutorial['avg_rating'], 1) ?></td>
                                        <td><?= formatDate($tutorial['created_at']) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="edit-tutorial.php?id=<?= $tutorial['tutorial_id'] ?>" class="btn-icon" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="view-tutorial.php?id=<?= $tutorial['tutorial_id'] ?>" class="btn-icon" title="View">
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
            
            <!-- chart section for later -->
            <div class="chart-section">
                <h2>Performance Overview</h2>
                <div class="chart-placeholder">
                    <i class="fas fa-chart-area"></i>
                    <p>Tutorial views and engagement chart will appear here</p>
                    <small>Feature coming soon with analytics integration</small>
                </div>
            </div>
        </main>
    </div>
    
    <script src="<?= asset('js/creator.js') ?>"></script>
</body>
</html>