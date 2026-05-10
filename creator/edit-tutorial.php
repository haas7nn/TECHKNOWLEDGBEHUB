<?php
/**
 * Edit Tutorial Page - FULL WORKING VERSION
 * Edit existing tutorials with real database updates
 * Hasan Fardan - 202301686
 */

require_once '../includes/auth-check.php';
require_once '../classes/Tutorial.php';
require_once '../classes/FileUpload.php';

$page_title = 'Edit Tutorial';

// Get tutorial ID
$tutorial_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$tutorial_id) {
    setFlashMessage('Invalid tutorial ID', 'error');
    redirect('creator/my-tutorials.php');
}

// Get tutorial object
$tutorialObj = new Tutorial();
$tutorial = $tutorialObj->getById($tutorial_id);

if (!$tutorial) {
    setFlashMessage('Tutorial not found', 'error');
    redirect('creator/my-tutorials.php');
}

// Verify ownership
if ($tutorial['instructor_id'] != $current_user_id && !isAdmin()) {
    setFlashMessage('You do not have permission to edit this tutorial', 'error');
    redirect('creator/my-tutorials.php');
}

// Get categories
$database = new Database();
$conn = $database->connect();

$categoriesQuery = "SELECT category_id, category_name FROM dbProj_categories ORDER BY category_name";
$categoriesStmt = $conn->query($categoriesQuery);
$categories = $categoriesStmt->fetchAll();

$tagsQuery = "SELECT tag_id, tag_name FROM dbProj_tags ORDER BY tag_name";
$tagsStmt = $conn->query($tagsQuery);
$all_tags = $tagsStmt->fetchAll();

// Get current tutorial tags
$current_tag_ids = array_column($tutorial['tags'], 'tag_id');

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfFromPost()) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
    $update_data = [
        'title' => clean($_POST['title']),
        'short_description' => clean($_POST['short_description']),
        'content' => $_POST['content'], // Don't clean HTML content
        'category_id' => (int)$_POST['category_id'],
        'difficulty' => clean($_POST['difficulty']),
        'duration_minutes' => isset($_POST['duration_minutes']) ? (int)$_POST['duration_minutes'] : null,
        'video_url' => clean($_POST['video_url'] ?? ''),
        'status' => clean($_POST['status']),
        'thumbnail' => $tutorial['thumbnail'] // Keep existing thumbnail
    ];
    
    $selected_tags = isset($_POST['tags']) ? $_POST['tags'] : [];
    
    // Validate
    if (empty($update_data['title']) || empty($update_data['content']) || empty($update_data['category_id'])) {
        $error = 'Please fill in all required fields';
    } else {
        
        // Handle new thumbnail upload
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $fileUpload = new FileUpload();
            $upload_result = $fileUpload->uploadThumbnail($_FILES['thumbnail'], 'tutorial');
            
            if ($upload_result['success']) {
                // Delete old thumbnail if exists
                if (!empty($tutorial['thumbnail'])) {
                    $fileUpload->deleteFile($tutorial['thumbnail']);
                }
                $update_data['thumbnail'] = $upload_result['filepath'];
            } else {
                $error = 'Thumbnail upload failed: ' . $upload_result['message'];
            }
        }
        
        // Update tutorial if no errors
        if (empty($error)) {
            $update_result = $tutorialObj->update($tutorial_id, $update_data, $selected_tags);
            
            if ($update_result['success']) {
                setFlashMessage('Tutorial updated successfully!', 'success');
                redirect('creator/my-tutorials.php');
            } else {
                $error = $update_result['message'];
            }
        }
    }
    } // end CSRF else
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
    <script src="https://cdn.jsdelivr.net/npm/tinymce@5.10.7/tinymce.min.js" referrerpolicy="origin"></script>
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
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= e($error) ?>
                </div>
            <?php endif; ?>
            
            <!-- Edit Form (Same structure as create but pre-filled) -->
            <form method="POST" action="" enctype="multipart/form-data" class="tutorial-form">
                <?php csrfField(); ?>
                
                <!-- Basic Information -->
                <div class="form-section">
                    <h2><i class="fas fa-info-circle"></i> Basic Information</h2>
                    
                    <div class="form-group full-width">
                        <label for="title">Tutorial Title *</label>
                        <input 
                            type="text" 
                            id="title" 
                            name="title" 
                            class="form-control" 
                            value="<?= e($tutorial['title']) ?>"
                            required
                            maxlength="255"
                        >
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="short_description">Short Description *</label>
                        <textarea 
                            id="short_description" 
                            name="short_description" 
                            class="form-control" 
                            rows="3"
                            required
                            maxlength="300"
                        ><?= e($tutorial['short_description']) ?></textarea>
                    </div>
                    
                    <div class="form-row two-cols">
                        <div class="form-group">
                            <label for="category_id">Category *</label>
                            <select id="category_id" name="category_id" class="form-control" required>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['category_id'] ?>" 
                                            <?= $tutorial['category_id'] == $category['category_id'] ? 'selected' : '' ?>>
                                        <?= e($category['category_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="difficulty">Difficulty Level *</label>
                            <select id="difficulty" name="difficulty" class="form-control" required>
                                <option value="beginner" <?= $tutorial['difficulty'] === 'beginner' ? 'selected' : '' ?>>Beginner</option>
                                <option value="intermediate" <?= $tutorial['difficulty'] === 'intermediate' ? 'selected' : '' ?>>Intermediate</option>
                                <option value="advanced" <?= $tutorial['difficulty'] === 'advanced' ? 'selected' : '' ?>>Advanced</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="duration_minutes">Duration (minutes)</label>
                            <input 
                                type="number" 
                                id="duration_minutes" 
                                name="duration_minutes" 
                                class="form-control" 
                                value="<?= $tutorial['duration_minutes'] ?>"
                                min="1"
                            >
                        </div>
                    </div>
                </div>
                
                <!-- Content -->
                <div class="form-section">
                    <h2><i class="fas fa-file-alt"></i> Tutorial Content</h2>
                    
                    <div class="form-group full-width">
                        <label for="content">Full Tutorial Content *</label>
                        <textarea 
                            id="content" 
                            name="content" 
                            class="form-control tinymce-editor"
                            rows="15"
                        ><?= $tutorial['content'] ?></textarea>
                    </div>
                </div>
                
                <!-- Media -->
                <div class="form-section">
                    <h2><i class="fas fa-images"></i> Media Files</h2>
                    
                    <?php if (!empty($tutorial['thumbnail'])): ?>
                        <div style="margin-bottom: 20px;">
                            <p><strong>Current Thumbnail:</strong></p>
                            <img src="<?= SITE_URL ?>/uploads/<?= e($tutorial['thumbnail']) ?>" 
                                 alt="Current thumbnail" 
                                 style="max-width: 300px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-row two-cols">
                        <div class="form-group">
                            <label for="thumbnail">Change Thumbnail</label>
                            <input 
                                type="file" 
                                id="thumbnail" 
                                name="thumbnail" 
                                class="form-control"
                                accept="image/*"
                            >
                            <small class="form-hint">Leave empty to keep current thumbnail</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="video_url">Video URL</label>
                            <input 
                                type="url" 
                                id="video_url" 
                                name="video_url" 
                                class="form-control" 
                                value="<?= e($tutorial['video_url']) ?>"
                                placeholder="https://youtube.com/watch?v=..."
                            >
                        </div>
                    </div>
                </div>
                
                <!-- Tags -->
                <div class="form-section">
                    <h2><i class="fas fa-tags"></i> Tags</h2>
                    
                    <div class="form-group">
                        <div class="tags-selector">
                            <?php foreach ($all_tags as $tag): ?>
                                <label class="tag-checkbox">
                                    <input 
                                        type="checkbox" 
                                        name="tags[]" 
                                        value="<?= $tag['tag_id'] ?>"
                                        <?= in_array($tag['tag_id'], $current_tag_ids) ? 'checked' : '' ?>
                                    >
                                    <span class="tag-label"><?= e($tag['tag_name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Publishing Options -->
                <div class="form-section">
                    <h2><i class="fas fa-globe"></i> Publishing Options</h2>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <div class="radio-group">
                            <label class="radio-label">
                                <input type="radio" name="status" value="draft" <?= $tutorial['status'] === 'draft' ? 'checked' : '' ?>>
                                <span><strong>Draft</strong> - Not visible to students</span>
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="status" value="published" <?= $tutorial['status'] === 'published' ? 'checked' : '' ?>>
                                <span><strong>Published</strong> - Visible to all students</span>
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="status" value="archived" <?= $tutorial['status'] === 'archived' ? 'checked' : '' ?>>
                                <span><strong>Archived</strong> - Hidden from public</span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="form-actions">
                    <a href="my-tutorials.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </main>
    </div>
    
    <script>
        // Initialize TinyMCE
        tinymce.init({
            selector: '.tinymce-editor',
            height: 500,
            menubar: true,
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                'insertdatetime', 'media', 'table', 'code', 'help', 'wordcount', 'codesample'
            ],
            toolbar: 'undo redo | formatselect | bold italic backcolor | \
                     alignleft aligncenter alignright alignjustify | \
                     bullist numlist outdent indent | removeformat | codesample | help',
            content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }'
        });
    </script>
</body>
</html>