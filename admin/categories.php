<?php
// start output buffer
// avoid early output
ob_start();

// set the page identifier so the admin sidebar can highlight the categories link
$page = 'categories';

// admin only
require_once '../includes/admin-check.php';

// load the category class for all database operations
require_once '../classes/Category.php';

// db connect
$db   = new Database();
$conn = $db->connect();
// stop if db down
if (!$conn) { setFlashMessage("Database error. Please try again.", "error"); redirect("auth/login.php"); }

$categoryObj = new Category();

// handle admin action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfFromPost()) {
    // read which action and which category the admin targeted
    $action      = clean($_POST['action']      ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $name        = trim(clean($_POST['name']   ?? ''));

    if ($action === 'add') {
        // create a new category with the submitted name
        if ($name === '') {
            setFlashMessage('Category name cannot be empty.', 'error');
        } else {
            $result = $categoryObj->create($name);
            if ($result['success']) {
                setFlashMessage('Category "' . e($name) . '" has been added successfully.', 'success');
            } else {
                setFlashMessage('Failed to add category. It may already exist.', 'error');
            }
        }

    } elseif ($action === 'edit' && $category_id) {
        // update the name of an existing category
        if ($name === '') {
            setFlashMessage('Category name cannot be empty.', 'error');
        } else {
            $ok = (bool)$categoryObj->update($category_id, $name);
            if ($ok) {
                setFlashMessage('Category updated successfully.', 'success');
            } else {
                setFlashMessage('Failed to update category. Please try again.', 'error');
            }
        }

    } elseif ($action === 'delete' && $category_id) {
        // delete the category only if no tutorials are assigned to it
        $ok = (bool)$categoryObj->delete($category_id);
        if ($ok) {
            setFlashMessage('Category deleted successfully.', 'success');
        } else {
            setFlashMessage('Cannot delete this category because tutorials are still assigned to it.', 'error');
        }
    }

    // redirect back
    redirect('admin/categories.php');
}

// load all categories with their tutorial counts for the table
$categories = $categoryObj->getAllWithCount();
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manage Categories | <?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head><body>
<?php require_once '../includes/admin-nav.php'; ?>
<div class="admin-wrap"><main class="admin-main">

    <!-- page header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-tags"></i> Manage Categories</h1>
            <p><?= count($categories) ?> <?= count($categories) === 1 ? 'category' : 'categories' ?> total</p>
        </div>
    </div>
    <?php displayFlashMessage(); ?>

    <!-- add category form -->
    <div class="admin-card" style="margin-bottom:24px;">
        <div class="admin-card-header"><h2 style="margin:0;font-size:1rem;"><i class="fas fa-plus"></i> Add New Category</h2></div>
        <div class="admin-card-body">
            <form method="POST" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="add">
                <div style="flex:1;min-width:200px;">
                    <label for="new_name" style="display:block;margin-bottom:6px;font-size:.875rem;color:#a0aec0;">Category Name</label>
                    <input type="text" id="new_name" name="name" placeholder="e.g. Web Development"
                           required maxlength="100"
                           style="width:100%;padding:8px 12px;background:#2d3748;border:1px solid #4a5568;border-radius:6px;color:#e2e8f0;font-size:.9rem;">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Category</button>
            </form>
        </div>
    </div>

    <!-- categories table -->
    <div class="admin-card">
        <div class="admin-card-body" style="padding:0;">
        <?php if (empty($categories)): ?>
            <!-- friendly message when no categories exist yet -->
            <div class="empty-state"><i class="fas fa-tags"></i><h3>No categories found</h3><p>Add your first category using the form above.</p></div>
        <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Tutorial Count</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($categories as $cat): ?>
            <!-- one row per category -->
            <tr>
                <td>
                    <!-- inline edit form — clicking Edit reveals the input pre-filled with the current name -->
                    <span class="cat-label-<?= $cat['category_id'] ?>"><?= e($cat['category_name']) ?></span>
                    <form method="POST"
                          id="edit-form-<?= $cat['category_id'] ?>"
                          style="display:none;margin-top:6px;"
                          onsubmit="return validateEditName(<?= $cat['category_id'] ?>)">
                        <?php csrfField(); ?>
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="category_id" value="<?= $cat['category_id'] ?>">
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                            <input type="text"
                                   id="edit-name-<?= $cat['category_id'] ?>"
                                   name="name"
                                   value="<?= e($cat['category_name']) ?>"
                                   required maxlength="100"
                                   style="padding:6px 10px;background:#2d3748;border:1px solid #4a5568;border-radius:6px;color:#e2e8f0;font-size:.875rem;min-width:180px;">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Save</button>
                            <button type="button" class="btn btn-outline btn-sm"
                                    onclick="toggleEdit(<?= $cat['category_id'] ?>, false)">Cancel</button>
                        </div>
                    </form>
                </td>
                <td>
                    <!-- show the count of published tutorials in this category -->
                    <span class="badge badge-info"><?= (int)$cat['tutorial_count'] ?> <?= (int)$cat['tutorial_count'] === 1 ? 'tutorial' : 'tutorials' ?></span>
                </td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <!-- edit button toggles the inline edit form -->
                        <button type="button"
                                class="btn-icon"
                                title="Edit"
                                id="edit-btn-<?= $cat['category_id'] ?>"
                                onclick="toggleEdit(<?= $cat['category_id'] ?>, true)">
                            <i class="fas fa-pencil-alt"></i>
                        </button>
                        <!-- delete button only shown when the category has no tutorials assigned -->
                        <?php if ((int)$cat['tutorial_count'] === 0): ?>
                        <form method="POST" style="display:inline;"
                              onsubmit="return confirm('Delete category \'<?= e(addslashes($cat['category_name'])) ?>\'? This cannot be undone.')">
                            <?php csrfField(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="category_id" value="<?= $cat['category_id'] ?>">
                            <button type="submit" class="btn-icon danger" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                        <?php else: ?>
                        <!-- greyed-out delete icon with a tooltip explaining why it is disabled -->
                        <span class="btn-icon" title="Cannot delete: tutorials are assigned to this category"
                              style="opacity:.35;cursor:not-allowed;">
                            <i class="fas fa-trash"></i>
                        </span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        </div>
    </div>

</main></div>

<script>
// show or hide the inline edit form for a given category row
function toggleEdit(id, show) {
    var form  = document.getElementById('edit-form-' + id);
    var label = document.querySelector('.cat-label-' + id);
    var btn   = document.getElementById('edit-btn-' + id);
    if (show) {
        form.style.display  = 'block';
        label.style.display = 'none';
        btn.style.display   = 'none';
        document.getElementById('edit-name-' + id).focus();
    } else {
        form.style.display  = 'none';
        label.style.display = '';
        btn.style.display   = '';
    }
}

// make sure the edit name field is not blank before submitting
function validateEditName(id) {
    var val = document.getElementById('edit-name-' + id).value.trim();
    if (!val) {
        alert('Category name cannot be empty.');
        return false;
    }
    return true;
}
</script>
</body></html>
