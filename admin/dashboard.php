<?php
// admin only
require_once '../includes/admin-check.php';

// db connect
$db   = new Database();
$conn = $db->connect();
// if db failed
if (!$conn) { setFlashMessage("Database error. Please try again.", "error"); redirect("auth/login.php"); }

// get site stats
$counts = [];
foreach ([
    'users'     => "SELECT COUNT(*) FROM dbProj_users WHERE status='active'",
    'tutorials' => "SELECT COUNT(*) FROM dbProj_tutorials WHERE status='published'",
    'comments'  => "SELECT COUNT(*) FROM dbProj_comments WHERE status='approved'",
    'ratings'   => "SELECT COUNT(*) FROM dbProj_ratings",
] as $key => $sql) {
    // run each count query and store the result by its key name
    $q = $conn->query($sql);
    $counts[$key] = $q ? $q->fetchColumn() : 0;
}

// get recent tutorials
$q = $conn->query(
    "SELECT t.tutorial_id, t.title, t.status, t.created_at, u.full_name as instructor
     FROM dbProj_tutorials t JOIN dbProj_users u ON t.instructor_id=u.user_id
     ORDER BY t.created_at DESC LIMIT 8"
);
$recent = $q ? $q->fetchAll() : [];

// get recent users
$q = $conn->query(
    "SELECT user_id, full_name, email, role, created_at FROM dbProj_users ORDER BY created_at DESC LIMIT 6"
);
$newUsers = $q ? $q->fetchAll() : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Dashboard | <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php include '../includes/admin-nav.php'; ?>
<div class="admin-wrap">
    <main class="admin-main">

        <!-- page title and welcome message -->
        <div class="page-header">
            <div><h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1><p>Welcome back, <?= e($current_user_name) ?></p></div>
        </div>

        <?php displayFlashMessage(); ?>

        <!-- four stat cards showing key site numbers -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon si-purple"><i class="fas fa-users"></i></div>
                <div class="stat-details"><h3><?= $counts['users'] ?></h3><p>Active Users</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon si-pink"><i class="fas fa-book"></i></div>
                <div class="stat-details"><h3><?= $counts['tutorials'] ?></h3><p>Published Tutorials</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon si-blue"><i class="fas fa-clock"></i></div>
                <div class="stat-details"><h3><?= $counts['comments'] ?></h3><p>Approved Comments</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon si-gold"><i class="fas fa-star"></i></div>
                <div class="stat-details"><h3><?= $counts['ratings'] ?></h3><p>Total Ratings</p></div>
            </div>
        </div>

        <!-- two side by side tables for recent tutorials and new users -->
        <div class="admin-grid-2">

        <!-- recent tutorials table -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h2><i class="fas fa-book"></i> Recent Tutorials</h2>
                <a href="<?= SITE_URL ?>/admin/tutorials.php" class="btn btn-sm btn-outline">View All</a>
            </div>
            <div class="admin-card-body" style="padding:0;">
                <table class="admin-table">
                    <thead><tr><th>Title</th><th>Instructor</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $t): ?>
                        <!-- one row per tutorial -->
                        <tr>
                            <td><?= e(truncate($t['title'],40)) ?></td>
                            <td><?= e($t['instructor']) ?></td>
                            <!-- pick a badge colour based on status -->
                            <td><span class="badge badge-<?= $t['status']==='published'?'success':($t['status']==='draft'?'warning':'gray') ?>"><?= ucfirst($t['status']) ?></span></td>
                            <td style="font-size:12px;color:#718096;"><?= formatDate($t['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- new users table -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h2><i class="fas fa-users"></i> New Users</h2>
                <a href="<?= SITE_URL ?>/admin/users.php" class="btn btn-sm btn-outline">View All</a>
            </div>
            <div class="admin-card-body" style="padding:0;">
                <table class="admin-table">
                    <thead><tr><th>Name</th><th>Role</th><th>Joined</th></tr></thead>
                    <tbody>
                    <?php foreach ($newUsers as $u): ?>
                        <!-- one row per user showing their name email role and join date -->
                        <tr>
                            <td>
                                <strong><?= e($u['full_name']) ?></strong><br>
                                <small style="color:#718096;"><?= e($u['email']) ?></small>
                            </td>
                            <!-- badge colour differs by role -->
                            <td><span class="badge badge-<?= $u['role']==='admin'?'danger':($u['role']==='creator'?'purple':'info') ?>"><?= ucfirst($u['role']) ?></span></td>
                            <td style="font-size:12px;color:#718096;"><?= formatDate($u['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        </div>
    </main>
</div>
</body></html>
