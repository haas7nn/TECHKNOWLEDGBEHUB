<?php
require_once 'config/config.php';
require_once 'classes/FileUpload.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    $upload = new FileUpload();
    $result = $upload->uploadThumbnail($_FILES['test_file'], 'test');
    
    echo '<pre>';
    print_r($result);
    echo '</pre>';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>File Upload Test</title>
</head>
<body>
    <h2>Test File Upload</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="test_file" accept="image/*" required>
        <button type="submit">Upload Test Image</button>
    </form>
</body>
</html>