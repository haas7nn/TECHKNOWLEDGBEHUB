<?php
/**
 * Edit Tutorial Page
 * form for fixing up tutorials you already made
 * Hasan Fardan - 202301686
 */

require_once '../includes/auth-check.php';

$page_title = 'Edit Tutorial';

// grabbing the id from the link
$tutorial_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$tutorial_id) {
    setFlashMessage('Invalid tutorial ID', 'error');
    redirect('creator/my-tutorials.php');
}

// placeholder data for the tutorial
$tutorial = [
    'tutorial_id' => $tutorial_id,
    'title' => '',
    'short_description' => '',
    'content' => '',
    'category_id' => '',
    'difficulty' => 'beginner',
    'duration_minutes' => '',
    'status' => 'draft',
    'thumbnail' => '',
    'video_url' => ''
];

// getting the categories from the database
$database = new Database();
$conn = $database->connect();

$categoriesQuery = "SELECT category_id, category_name FROM techknow_categories ORDER BY category_name";
$categoriesStmt = $conn->query($categoriesQuery);
$categories = $categoriesStmt->fetchAll();

$error = '';
$success = '';

// checking if the form was sent
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = 'Tutorial update will be implemented when Tutorial class is ready!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= asset('css/creator.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.tiny.mce.com/1/tinymce/5/tinymce.min.js" referrerpolicy="origin"></script>
</head>
<body>
    <?php include '../includes/creator-nav.php'; ?>
    
    <div class="dashboard-container">
        <?php include '../includes/creator-sidebar.php'; ?>
        
        <main class="dashboard-main">
            <div class="dashboard-header">
                <div>
                    <h1><i class="fas fa-edit"></i> Edit Tutorial</h1>
                    <p>Update your tutorial content and settings</p>
                </div>
                <a href="my-tutorials.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i>
                    Back to My Tutorials
                </a>
            </div>
            
            <?php displayFlashMessage(); ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= e($success) ?>
                </div>
            <?php endif; ?>
            
            <!-- the actual edit form -->
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <p>Tutorial editing form will be similar to create form but with pre-filled data from database</p>
            </div>
        </main>
    </div>
</body>
</html>