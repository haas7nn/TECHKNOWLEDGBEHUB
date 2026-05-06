<?php
/**
 * Create Tutorial Page
 * form for instructors to make new tutorials
 * Hasan Fardan - 202301686
 */

require_once '../includes/auth-check.php';

$page_title = 'Create New Tutorial';
$error = '';
$success = '';

// grabbing categories for the dropdown will fix the query later
$database = new Database();
$conn = $database->connect();

$categoriesQuery = "SELECT category_id, category_name FROM techknow_categories ORDER BY category_name";
$categoriesStmt = $conn->query($categoriesQuery);
$categories = $categoriesStmt->fetchAll();

// getting the list of tags
$tagsQuery = "SELECT tag_id, tag_name FROM techknow_tags ORDER BY tag_name";
$tagsStmt = $conn->query($tagsQuery);
$tags = $tagsStmt->fetchAll();

// keeping the data here so it doesnt disappear on reload
$form_data = [
    'title' => '',
    'short_description' => '',
    'content' => '',
    'category_id' => '',
    'difficulty' => 'beginner',
    'duration_minutes' => '',
    'status' => 'draft'
];

// handling the post request will do this once the tutorial class is done
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // waiting for samana to finish her part before i code this
    $success = 'Tutorial creation will be implemented when Tutorial class is ready!';
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
    <!-- editor for the tutorial content -->
    <script src="https://cdn.tiny.mce.com/1/tinymce/5/tinymce.min.js" referrerpolicy="origin"></script>
</head>
<body>
    <?php include '../includes/creator-nav.php'; ?>
    
    <div class="dashboard-container">
        <?php include '../includes/creator-sidebar.php'; ?>
        
        <main class="dashboard-main">
            <!-- top section -->
            <div class="dashboard-header">
                <div>
                    <h1><i class="fas fa-plus-circle"></i> Create New Tutorial</h1>
                    <p>Share your knowledge with students around the world</p>
                </div>
                <a href="my-tutorials.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i>
                    Back to My Tutorials
                </a>
            </div>
            
            <!-- alerts and messages -->
            <?php displayFlashMessage(); ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= e($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= e($success) ?>
                </div>
            <?php endif; ?>
            
            <!-- main tutorial form starts here -->
            <form method="POST" action="" enctype="multipart/form-data" class="tutorial-form" id="createTutorialForm">
                
                <!-- basic info section -->
                <div class="form-section">
                    <h2><i class="fas fa-info-circle"></i> Basic Information</h2>
                    
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="title">
                                Tutorial Title *
                                <span class="label-hint">Choose a clear, descriptive title</span>
                            </label>
                            <input 
                                type="text" 
                                id="title" 
                                name="title" 
                                class="form-control" 
                                placeholder="e.g., Complete PHP & MySQL Course for Beginners"
                                value="<?= e($form_data['title']) ?>"
                                required
                                maxlength="255"
                            >
                            <small class="char-count">
                                <span id="title-count">0</span>/255 characters
                            </small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="short_description">
                                Short Description *
                                <span class="label-hint">Brief summary shown in listings</span>
                            </label>
                            <textarea 
                                id="short_description" 
                                name="short_description" 
                                class="form-control" 
                                rows="3"
                                placeholder="Write a compelling summary of what students will learn..."
                                required
                                maxlength="300"
                            ><?= e($form_data['short_description']) ?></textarea>
                            <small class="char-count">
                                <span id="desc-count">0</span>/300 characters
                            </small>
                        </div>
                    </div>
                    
                    <div class="form-row two-cols">
                        <div class="form-group">
                            <label for="category_id">
                                Category *
                            </label>
                            <select id="category_id" name="category_id" class="form-control" required>
                                <option value="">Select a category...</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['category_id'] ?>" 
                                            <?= $form_data['category_id'] == $category['category_id'] ? 'selected' : '' ?>>
                                        <?= e($category['category_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="difficulty">
                                Difficulty Level *
                            </label>
                            <select id="difficulty" name="difficulty" class="form-control" required>
                                <option value="beginner" <?= $form_data['difficulty'] === 'beginner' ? 'selected' : '' ?>>
                                    Beginner
                                </option>
                                <option value="intermediate" <?= $form_data['difficulty'] === 'intermediate' ? 'selected' : '' ?>>
                                    Intermediate
                                </option>
                                <option value="advanced" <?= $form_data['difficulty'] === 'advanced' ? 'selected' : '' ?>>
                                    Advanced
                                </option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="duration_minutes">
                                Estimated Duration (minutes)
                                <span class="label-hint">How long to complete?</span>
                            </label>
                            <input 
                                type="number" 
                                id="duration_minutes" 
                                name="duration_minutes" 
                                class="form-control" 
                                placeholder="e.g., 120"
                                value="<?= e($form_data['duration_minutes']) ?>"
                                min="1"
                                max="999"
                            >
                        </div>
                    </div>
                </div>
                
                <!-- body of the tutorial -->
                <div class="form-section">
                    <h2><i class="fas fa-file-alt"></i> Tutorial Content</h2>
                    
                    <div class="form-group full-width">
                        <label for="content">
                            Full Tutorial Content *
                            <span class="label-hint">Write your complete tutorial with examples and explanations</span>
                        </label>
                        <textarea 
                            id="content" 
                            name="content" 
                            class="form-control tinymce-editor"
                            rows="15"
                        ><?= e($form_data['content']) ?></textarea>
                        <small class="form-hint">
                            Use the rich text editor to format your content, add images, code blocks, etc.
                        </small>
                    </div>
                </div>
                
                <!-- uploads and links section -->
                <div class="form-section">
                    <h2><i class="fas fa-images"></i> Media Files</h2>
                    
                    <div class="form-row two-cols">
                        <div class="form-group">
                            <label for="thumbnail">
                                Thumbnail Image
                                <span class="label-hint">Recommended: 1200x630px</span>
                            </label>
                            <div class="file-upload-wrapper">
                                <input 
                                    type="file" 
                                    id="thumbnail" 
                                    name="thumbnail" 
                                    class="file-input"
                                    accept="image/jpeg,image/png,image/jpg,image/webp"
                                >
                                <label for="thumbnail" class="file-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Choose thumbnail image</span>
                                </label>
                                <div class="file-preview" id="thumbnail-preview"></div>
                            </div>
                            <small class="form-hint">Max size: 2MB. Formats: JPG, PNG, WEBP</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="video_url">
                                Video URL 
                                <span class="label-hint">YouTube, Vimeo, or direct link</span>
                            </label>
                            <input 
                                type="url" 
                                id="video_url" 
                                name="video_url" 
                                class="form-control" 
                                placeholder="https://www.youtube.com/watch?v=..."
                            >
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            Additional Files 
                            <span class="label-hint">PDFs, code files, resources</span>
                        </label>
                        <div class="file-upload-wrapper">
                            <input 
                                type="file" 
                                id="additional_files" 
                                name="additional_files[]" 
                                class="file-input"
                                accept=".pdf,.doc,.docx,.zip,.txt"
                                multiple
                            >
                            <label for="additional_files" class="file-label">
                                <i class="fas fa-paperclip"></i>
                                <span>Choose files to upload</span>
                            </label>
                            <div class="file-list" id="files-list"></div>
                        </div>
                        <small class="form-hint">Max 5 files, 10MB each. Formats: PDF, DOC, ZIP, TXT</small>
                    </div>
                </div>
                
                <!-- adding tags section -->
                <div class="form-section">
                    <h2><i class="fas fa-tags"></i> Tags</h2>
                    
                    <div class="form-group">
                        <label>
                            Select Tags
                            <span class="label-hint">Help students find your tutorial</span>
                        </label>
                        <div class="tags-selector">
                            <?php foreach ($tags as $tag): ?>
                                <label class="tag-checkbox">
                                    <input 
                                        type="checkbox" 
                                        name="tags[]" 
                                        value="<?= $tag['tag_id'] ?>"
                                    >
                                    <span class="tag-label"><?= e($tag['tag_name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- status settings section -->
                <div class="form-section">
                    <h2><i class="fas fa-globe"></i> Publishing Options</h2>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <div class="radio-group">
                            <label class="radio-label">
                                <input 
                                    type="radio" 
                                    name="status" 
                                    value="draft" 
                                    <?= $form_data['status'] === 'draft' ? 'checked' : '' ?>
                                >
                                <span>
                                    <strong>Save as Draft</strong>
                                    <small>Not visible to students. You can edit later.</small>
                                </span>
                            </label>
                            
                            <label class="radio-label">
                                <input 
                                    type="radio" 
                                    name="status" 
                                    value="published"
                                    <?= $form_data['status'] === 'published' ? 'checked' : '' ?>
                                >
                                <span>
                                    <strong>Publish Now</strong>
                                    <small>Immediately visible to all students.</small>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- buttons for save and cancel -->
                <div class="form-actions">
                    <button type="button" class="btn btn-outline" onclick="history.back()">
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                    
                    <button type="button" class="btn btn-secondary" id="previewBtn">
                        <i class="fas fa-eye"></i>
                        Preview
                    </button>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Save Tutorial
                    </button>
                </div>
            </form>
        </main>
    </div>
    
    <script>
        // starting the editor keeping it light for now
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
        
        // counting characters while typing
        document.getElementById('title').addEventListener('input', function() {
            document.getElementById('title-count').textContent = this.value.length;
        });
        
        document.getElementById('short_description').addEventListener('input', function() {
            document.getElementById('desc-count').textContent = this.value.length;
        });
        
        // show the image before uploading
        document.getElementById('thumbnail').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('thumbnail-preview').innerHTML = 
                        '<img src="' + e.target.result + '" alt="Preview" style="max-width: 200px; border-radius: 8px;">';
                };
                reader.readAsDataURL(file);
            }
        });
        
        // listing all the extra files
        document.getElementById('additional_files').addEventListener('change', function(e) {
            const filesList = document.getElementById('files-list');
            filesList.innerHTML = '';
            
            Array.from(e.target.files).forEach(file => {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                fileItem.innerHTML = `
                    <i class="fas fa-file"></i>
                    <span>${file.name}</span>
                    <small>(${(file.size / 1024).toFixed(2)} KB)</small>
                `;
                filesList.appendChild(fileItem);
            });
        });
        
        // check if everything is filled before saving
        document.getElementById('createTutorialForm').addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            const shortDesc = document.getElementById('short_description').value.trim();
            const category = document.getElementById('category_id').value;
            
            if (!title || !shortDesc || !category) {
                e.preventDefault();
                alert('Please fill in all required fields (marked with *)');
            }
        });
    </script>
</body>
</html>