<?php
// admin only
require_once '../includes/admin-check.php';

// db connect
$db   = new Database();
$conn = $db->connect();
// stop if db down
if (!$conn) { setFlashMessage("Database error. Please try again.", "error"); redirect("auth/login.php"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfFromPost()) {
    $action  = clean($_POST['action']  ?? '');
    $user_id = (int)($_POST['user_id'] ?? 0);

    // block self-action
    if ($user_id && $user_id !== $current_user_id) {
        if ($action === 'activate') {
            $conn->prepare("UPDATE dbProj_users SET status='active' WHERE user_id=:id")->execute([':id'=>$user_id]);
            setFlashMessage('User activated.','success');
        } elseif ($action === 'deactivate') {
            $conn->prepare("UPDATE dbProj_users SET status='inactive' WHERE user_id=:id")->execute([':id'=>$user_id]);
            setFlashMessage('User deactivated.','success');
        } elseif ($action === 'change_role') {
            $new_role = clean($_POST['new_role'] ?? '');
            // validate role before saving
            if (in_array($new_role,['viewer','creator','admin'])) {
                $conn->prepare("UPDATE dbProj_users SET role=:role WHERE user_id=:id")->execute([':role'=>$new_role,':id'=>$user_id]);
                setFlashMessage('Role updated.','success');
            }
        }
    }
    redirect('admin/users.php');
}

// read search filter and page from url
$search   = clean($_GET['search'] ?? '');
$role     = clean($_GET['role']   ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset   = ($page - 1) * $per_page;

// build dynamic where clause
$where  = ['1=1'];
$params = [];
if ($search) {
    $like = '%' . addcslashes($search, '%_') . '%';
    $where[] = "(full_name LIKE :s1 OR email LIKE :s2)";
    $params[':s1'] = $like;
    $params[':s2'] = $like;
}
if ($role) {
    $where[] = "role=:role";
    $params[':role'] = $role;
}
$where_sql = implode(' AND ', $where);

// total rows for pagination
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM dbProj_users WHERE $where_sql");
$count_stmt->execute($params);
$total       = $count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));

// fetch current page of users
$sql = "SELECT user_id, full_name, email, role, status, created_at
        FROM dbProj_users WHERE $where_sql
        ORDER BY created_at DESC
        LIMIT :lim OFFSET :off";
$stmt = $conn->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':lim', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset,   PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manage Users | <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head><body>
<?php include '../includes/admin-nav.php'; ?>
<div class="admin-wrap"><main class="admin-main">

    <div class="page-header">
        <div><h1><i class="fas fa-users"></i> Manage Users</h1><p><?= $total ?> user<?= $total !== 1 ? 's' : '' ?> found</p></div>
    </div>
    <?php displayFlashMessage(); ?>

    <form method="GET" class="filter-bar">
        <input type="text" name="search" placeholder="Search name or email..." value="<?= e($search) ?>">
        <!-- filter by role -->
        <select name="role">
            <option value="">All Roles</option>
            <option value="viewer"  <?= $role==='viewer'  ?'selected':'' ?>>Viewer</option>
            <option value="creator" <?= $role==='creator' ?'selected':'' ?>>Creator</option>
            <option value="admin"   <?= $role==='admin'   ?'selected':'' ?>>Admin</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Search</button>
        <a href="users.php" class="btn btn-outline btn-sm">Clear</a>
    </form>

    <div class="admin-card">
        <div class="admin-card-body" style="padding:0;">
        <table class="admin-table">
            <thead><tr><th>ID</th><th>Name / Email</th><th>Role</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td style="color:#a0aec0;font-size:12px;">#<?= $u['user_id'] ?></td>
                <td>
                    <strong><?= e($u['full_name']) ?></strong><br>
                    <small style="color:#718096;"><?= e($u['email']) ?></small>
                </td>
                <!-- badge colour by role -->
                <td><span class="badge badge-<?= $u['role']==='admin'?'danger':($u['role']==='creator'?'purple':'info') ?>"><?= ucfirst($u['role']) ?></span></td>
                <!-- badge colour by status -->
                <td><span class="badge badge-<?= $u['status']==='active'?'success':'warning' ?>"><?= ucfirst($u['status']) ?></span></td>
                <td style="font-size:12px;color:#718096;"><?= formatDate($u['created_at']) ?></td>
                <td>
                    <!-- hide actions for own account -->
                    <?php if ($u['user_id'] !== $current_user_id): ?>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                        <!-- toggle active or inactive -->
                        <form method="POST" style="display:contents;">
                            <?php csrfField(); ?>
                            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                            <input type="hidden" name="action" value="<?= $u['status']==='active'?'deactivate':'activate' ?>">
                            <button class="btn-icon <?= $u['status']==='active'?'danger':'success' ?>"
                                    title="<?= $u['status']==='active'?'Deactivate':'Activate' ?>">
                                <i class="fas fa-<?= $u['status']==='active'?'ban':'check' ?>"></i>
                            </button>
                        </form>
                        <!-- change role form -->
                        <form method="POST" style="display:contents;">
                            <?php csrfField(); ?>
                            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                            <input type="hidden" name="action" value="change_role">
                            <select name="new_role" class="form-control"
                                    style="padding:5px 8px;font-size:12px;width:auto;display:inline-block;">
                                <option value="viewer"  <?= $u['role']==='viewer'  ?'selected':'' ?>>Viewer</option>
                                <option value="creator" <?= $u['role']==='creator' ?'selected':'' ?>>Creator</option>
                                <option value="admin"   <?= $u['role']==='admin'   ?'selected':'' ?>>Admin</option>
                            </select>
                            <button class="btn-icon" title="Save Role"><i class="fas fa-save"></i></button>
                        </form>
                    </div>
                    <?php else: ?>
                        <small style="color:#a0aec0;">You</small>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <?php if ($total_pages > 1): ?>
    <?php $base = http_build_query(array_filter(['search' => $search, 'role' => $role])); ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?<?= $base ?>&page=<?= $page - 1 ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-chevron-left"></i> Prev
            </a>
        <?php endif; ?>
        <span class="page-info">Page <?= $page ?> of <?= $total_pages ?></span>
        <?php if ($page < $total_pages): ?>
            <a href="?<?= $base ?>&page=<?= $page + 1 ?>" class="btn btn-outline btn-sm">
                Next <i class="fas fa-chevron-right"></i>
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</main></div>
</body></html>
