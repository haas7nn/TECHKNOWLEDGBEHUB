<?php
require_once '../includes/admin-check.php';

$db   = new Database();
$conn = $db->connect();
if (!$conn) { setFlashMessage("Database error. Please try again.", "error"); redirect("admin/dashboard.php"); }

// site-wide counts
$counts = [];
foreach ([
    'users'     => "SELECT COUNT(*) FROM dbProj_users WHERE status='active'",
    'tutorials' => "SELECT COUNT(*) FROM dbProj_tutorials WHERE status='published'",
    'comments'  => "SELECT COUNT(*) FROM dbProj_comments WHERE status='pending'",
    'ratings'   => "SELECT COUNT(*) FROM dbProj_ratings",
] as $key => $sql) {
    $counts[$key] = $conn->query($sql)->fetchColumn();
}

// recent tutorials
$recent = $conn->query(
    "SELECT t.tutorial_id, t.title, t.status, t.created_at, u.full_name as instructor
     FROM dbProj_tutorials t JOIN dbProj_users u ON t.instructor_id=u.user_id
     ORDER BY t.created_at DESC LIMIT 8"
)->fetchAll();

// recent users
$newUsers = $conn->query(
    "SELECT user_id, full_name, email, role, created_at FROM dbProj_users ORDER BY created_at DESC LIMIT 6"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Dashboard - <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php include '../includes/admin-nav.php'; ?>
<div class="admin-wrap">
    <main class="admin-main">
        <div class="page-header">
            <div><h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1><p>Welcome back, <?= e($current_user_name) ?></p></div>
        </div>
        <?php displayFlashMessage(); ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#667eea,#764ba2)"><i class="fas fa-users"></i></div>
                <div class="stat-details"><h3><?= $counts['users'] ?></h3><p>Active Users</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#f093fb,#f5576c)"><i class="fas fa-book"></i></div>
                <div class="stat-details"><h3><?= $counts['tutorials'] ?></h3><p>Published Tutorials</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#4facfe,#00f2fe)"><i class="fas fa-clock"></i></div>
                <div class="stat-details"><h3><?= $counts['comments'] ?></h3><p>Pending Comments</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#fa709a,#fee140)"><i class="fas fa-star"></i></div>
                <div class="stat-details"><h3><?= $counts['ratings'] ?></h3><p>Total Ratings</p></div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
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
                        <tr>
                            <td><?= e(truncate($t['title'],40)) ?></td>
                            <td><?= e($t['instructor']) ?></td>
                            <td><span class="badge badge-<?= $t['status']==='published'?'success':($t['status']==='draft'?'warning':'gray') ?>"><?= ucfirst($t['status']) ?></span></td>
                            <td style="font-size:12px;color:#718096;"><?= formatDate($t['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

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
                        <tr>
                            <td>
                                <strong><?= e($u['full_name']) ?></strong><br>
                                <small style="color:#718096;"><?= e($u['email']) ?></small>
                            </td>
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
