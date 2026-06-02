<?php
// create tutorial form for instructors

require_once '../includes/auth-check.php';
require_once '../classes/User.php';

$page_title = 'Create New Tutorial';
$error = '';
$success = '';

// db connect
$database = new Database();
$conn = $database->connect();

// load saved creator preferences for sensible form defaults
$userPrefs = (new User())->getPreferences($current_user_id);
$pref_difficulty = in_array($userPrefs['default_difficulty'] ?? '', ['beginner','intermediate','advanced'])
    ? $userPrefs['default_difficulty'] : 'beginner';

// get categories
$categoriesQuery = "SELECT category_id, category_name FROM dbProj_categories ORDER BY category_name";
$categoriesStmt = $conn->query($categoriesQuery);
$categories = $categoriesStmt->fetchAll();

// get tags
$tagsQuery = "SELECT tag_id, tag_name FROM dbProj_tags ORDER BY tag_name";
$tagsStmt = $conn->query($tagsQuery);
$tags = $tagsStmt->fetchAll();

// default values
$form_data = [
    'title'             => '',
    'short_description' => '',
    'content'           => '',
    'category_id'       => '',
    'difficulty'        => $pref_difficulty,
    'duration_minutes'  => '',
    'video_url'         => '',
    'status'            => 'draft'
];
$selected_tags_repop = [];

// handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // check csrf first
    if (!verifyCsrfFromPost()) {
        $error = 'Invalid security token. Please try again.';
    } else {
        require_once '../classes/Tutorial.php';

        // get and clean form data
        $title             = clean($_POST['title']             ?? '');
        $short_description = clean($_POST['short_description'] ?? '');
        $content           = $_POST['content']                  ?? '';
        $category_id       = (int)($_POST['category_id']        ?? 0);
        $difficulty        = clean($_POST['difficulty']         ?? 'beginner');
        $duration_minutes  = !empty($_POST['duration_minutes']) ? (int)$_POST['duration_minutes'] : null;
        $video_url         = clean($_POST['video_url']          ?? '');
        $status            = clean($_POST['status']             ?? 'draft');
        $selected_tags     = $_POST['tags']                     ?? [];

        // keep values for repopulation
        $form_data = [
            'title'             => $title,
            'short_description' => $short_description,
            'content'           => $content,
            'category_id'       => $category_id,
            'difficulty'        => $difficulty,
            'duration_minutes'  => $duration_minutes,
            'video_url'         => $video_url,
            'status'            => $status,
        ];
        // keep the selected tags so the checkboxes stay ticked on error
        $selected_tags_repop = $selected_tags;

        // server validation
        if (empty($title)) {
            $error = 'Title is required.';
        } elseif (strlen($title) > 255) {
            $error = 'Title must be 255 characters or less.';
        } elseif (empty($short_description)) {
            $error = 'Short description is required.';
        } elseif (strlen($short_description) > 300) {
            $error = 'Short description must be 300 characters or less.';
        } elseif (empty($content)) {
            $error = 'Tutorial content is required.';
        } elseif (empty($category_id)) {
            $error = 'Please select a category.';
        } elseif (!in_array($difficulty, ['beginner', 'intermediate', 'advanced'])) {
            $error = 'Invalid difficulty level.';
        } elseif (!in_array($status, ['draft', 'published'])) {
            $error = 'Invalid status.';
        // duration min 1 minute
        } elseif (!empty($duration_minutes) && $duration_minutes < 1) {
            $error = 'Duration must be at least 1 minute.';
        // validate youtube or vimeo embed url
        } elseif (!empty($video_url) && !preg_match('/^https:\/\/(www\.)?(youtube\.com\/embed\/|youtu\.be\/|player\.vimeo\.com\/video\/)/', $video_url)) {
            $error = 'Video URL must be a valid YouTube or Vimeo embed URL (e.g. https://www.youtube.com/embed/VIDEO_ID).';
        } else {
            // validation passed create it
            $tutorialObj = new Tutorial();
            $result = $tutorialObj->create([
                'title'             => $title,
                'short_description' => $short_description,
                'content'           => $content,
                'category_id'       => $category_id,
                'instructor_id'     => $current_user_id,
                'difficulty'        => $difficulty,
                'duration_minutes'  => $duration_minutes,
                'video_url'         => $video_url,
                'status'            => $status,
                'tags'              => $selected_tags,
            ]);

            // try to upload a thumbnail if one was attached
            if ($result['success'] && !empty($_FILES['thumbnail']['name'])) {
                $thumb_result = $tutorialObj->uploadMedia($result['tutorial_id'], $_FILES['thumbnail'], 'image');
                if (!$thumb_result['success']) {
                    // tutorial saved fine but warn them the thumbnail did not upload
                    setFlashMessage('Tutorial saved but thumbnail upload failed: ' . $thumb_result['message'], 'warning');
                }
            }

            // try upload extra files
            if ($result['success'] && !empty($_FILES['additional_files']['name'][0])) {
                $upload_errors = [];
                foreach ($_FILES['additional_files']['tmp_name'] as $key => $tmp) {
                    if ($_FILES['additional_files']['error'][$key] === UPLOAD_ERR_OK) {
                        $single = [
                            'name'     => $_FILES['additional_files']['name'][$key],
                            'type'     => $_FILES['additional_files']['type'][$key],
                            'tmp_name' => $tmp,
                            'error'    => $_FILES['additional_files']['error'][$key],
                            'size'     => $_FILES['additional_files']['size'][$key],
                        ];
                        // track the filename if a particular file fails to upload
                        $file_result = $tutorialObj->uploadMedia($result['tutorial_id'], $single, 'document');
                        if (!$file_result['success']) {
                            $upload_errors[] = $_FILES['additional_files']['name'][$key];
                        }
                    }
                }
                if (!empty($upload_errors)) {
                    setFlashMessage('Tutorial saved but these files failed to upload: ' . implode(', ', $upload_errors), 'warning');
                }
            }

            // success redirect
            if ($result['success']) {
                setFlashMessage('Tutorial created successfully!', 'success');
                redirect('creator/my-tutorials.php');
            } else {
                $error = $result['message'] ?? 'Failed to create tutorial. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> | <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= asset('css/creator.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- TinyMCE rich text editor for the tutorial content field -->
    <script src="https://cdn.jsdelivr.net/npm/tinymce@5.10.7/tinymce.min.js" referrerpolicy="origin"></script>
</head>
<body>
    <?php include '../includes/creator-nav.php'; ?>

    <div class="dashboard-container">
        <?php include '../includes/creator-sidebar.php'; ?>

        <main class="dashboard-main">

            <!-- top heading and back button -->
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

            <!-- flash messages and error alerts -->
            <?php displayFlashMessage(); ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <!-- the main tutorial creation form with file upload support -->
            <form method="POST" action="" enctype="multipart/form-data" class="tutorial-form" id="createTutorialForm">
                    <?php csrfField(); ?>

                <!-- basic info section with title, description, category, difficulty and duration -->
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
                            <span id="title-error" class="field-error" style="display:none;color:var(--c-danger);font-size:12px;margin-top:4px;"></span>
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
                            <span id="desc-error" class="field-error" style="display:none;color:var(--c-danger);font-size:12px;margin-top:4px;"></span>
                        </div>
                    </div>

                    <!-- category and difficulty dropdowns side by side -->
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
                            <span id="category-error" class="field-error" style="display:none;color:var(--c-danger);font-size:12px;margin-top:4px;"></span>
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

                    <!-- optional duration field -->
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

                <!-- tutorial body section with the TinyMCE rich text editor -->
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
                        <span id="content-error" class="field-error" style="display:none;color:var(--c-danger);font-size:12px;margin-top:4px;"></span>
                    </div>
                </div>

                <!-- media section for thumbnail upload, video URL and additional file uploads -->
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
                                value="<?= e($form_data['video_url'] ?? '') ?>"
                            >
                            <span id="video-error" class="field-error" style="display:none;color:var(--c-danger);font-size:12px;margin-top:4px;"></span>
                        </div>
                    </div>

                    <!-- multiple additional file uploads for PDFs and other resources -->
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

                <!-- tags section with checkboxes for each available tag -->
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
                                        <?= in_array($tag['tag_id'], $selected_tags_repop) ? 'checked' : '' ?>
                                    >
                                    <span class="tag-label"><?= e($tag['tag_name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- publishing section to pick draft or published status -->
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

                <!-- form action buttons for cancel, preview and save -->
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
        // start tinymce on the content textarea
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

        // update the title character counter as the user types
        document.getElementById('title').addEventListener('input', function() {
            document.getElementById('title-count').textContent = this.value.length;
        });

        // update the short description character counter as the user types
        document.getElementById('short_description').addEventListener('input', function() {
            document.getElementById('desc-count').textContent = this.value.length;
        });

        // show a thumbnail preview image when the user picks a file
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

        // list the selected additional files so the user can see what will be uploaded
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

        // helper to show a validation error below a specific field
        function showFieldError(id, msg) {
            var el = document.getElementById(id);
            if (el) { el.textContent = msg; el.style.display = 'block'; }
        }

        // clear all field level error messages before revalidating
        function clearFieldErrors() {
            document.querySelectorAll('.field-error').forEach(function(el) {
                el.textContent = ''; el.style.display = 'none';
            });
        }

        // run client side validation when the form is submitted
        document.getElementById('createTutorialForm').addEventListener('submit', function(e) {
            clearFieldErrors();
            var valid = true;

            // check the title field is filled and not too long
            var title = document.getElementById('title').value.trim();
            if (!title) {
                showFieldError('title-error', 'Title is required.');
                valid = false;
            } else if (title.length > 255) {
                showFieldError('title-error', 'Title must be 255 characters or less.');
                valid = false;
            }

            // check the short description field is filled and not too long
            var shortDesc = document.getElementById('short_description').value.trim();
            if (!shortDesc) {
                showFieldError('desc-error', 'Short description is required.');
                valid = false;
            } else if (shortDesc.length > 300) {
                showFieldError('desc-error', 'Short description must be 300 characters or less.');
                valid = false;
            }

            // check a category was selected
            if (!document.getElementById('category_id').value) {
                showFieldError('category-error', 'Please select a category.');
                valid = false;
            }

            // check the content field is not empty using tinymce if it is active
            var content = '';
            if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                content = tinymce.get('content').getContent({ format: 'text' }).trim();
            } else {
                content = document.getElementById('content').value.trim();
            }
            if (!content) {
                showFieldError('content-error', 'Tutorial content is required.');
                valid = false;
            }

            // check the video url format if one was provided
            var videoUrl = document.getElementById('video_url').value.trim();
            if (videoUrl && !/^https:\/\/(www\.)?(youtube\.com\/embed\/|youtu\.be\/|player\.vimeo\.com\/video\/)/.test(videoUrl)) {
                showFieldError('video-error', 'Use a valid YouTube or Vimeo embed URL.');
                valid = false;
            }

            // if anything failed prevent submission and scroll the first error into view
            if (!valid) {
                e.preventDefault();
                var first = document.querySelector('.field-error[style*="block"]');
                if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    </script>
    <script src="<?= asset('js/creator.js') ?>"></script>
    <script>
        // preview button opens the tinymce content in a new window 
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('previewBtn')?.addEventListener('click', function() {
                if (typeof tinymce !== 'undefined') {
                    var content = tinymce.get('content')?.getContent() || document.getElementById('content').value;
                    var win = window.open('', '_blank');
                    win.document.write('<html><body>' + content + '</body></html>');
                    win.document.close();
                }
            });
        });
    </script>
</body>
</html>
