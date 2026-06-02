<?php
// handles file uploads validate size and move to right folder

class FileUpload {

    /** @var array<string, array<string>> */
    private $allowed_types = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'video' => ['mp4', 'webm', 'ogg', 'avi', 'mov'],
        'document' => ['pdf', 'doc', 'docx', 'zip', // never extract zips server-side zip slip risk
                       'txt', 'pptx']
    ];

    /** @var array<string, int> */
    private $max_sizes = [
        'image' => 5242880,      // 5MB
        'video' => 52428800,     // 50MB
        'document' => 10485760   // 10MB
    ];

    /** @var string */
    private $upload_base_path;

    /** @var array<string> */
    private $errors = [];

    // init upload path and ensure folders exist
    public function __construct() {
        $this->upload_base_path = __DIR__ . '/../uploads/';
        $this->createDirectories();
    }

    // create upload subfolders and block directory browsing
    private function createDirectories() {
        $dirs = [
            'tutorials/thumbnails',
            'tutorials/videos',
            'tutorials/documents',
            'profiles',
            'temp'
        ];

        foreach ($dirs as $dir) {
            $path = $this->upload_base_path . $dir;
            if (!file_exists($path)) {
                mkdir($path, 0755, true);

                // drop index.php to block direct browsing
                file_put_contents($path . '/index.php', "<?php header('HTTP/1.0 403 Forbidden'); die('Access denied'); ?>");

                // disable php execution and dir listing
                $htaccess = $path . '/.htaccess';
                if (!file_exists($htaccess)) {
                    file_put_contents($htaccess, "php_flag engine off\nOptions -Indexes\n");
                }
            }
        }
    }

    // public wrapper so Tutorial can call uploadFile for videos
    public function uploadFile_public($file, $type, $directory, $prefix = '') {
        return $this->uploadFile($file, $type, $directory, $prefix);
    }

    // save thumbnail to thumbnails folder
    public function uploadThumbnail($file, $prefix = 'thumb') {
        return $this->uploadFile($file, 'image', 'tutorials/thumbnails/', $prefix);
    }

    // save document to documents folder
    public function uploadDocument($file, $prefix = 'doc') {
        return $this->uploadFile($file, 'document', 'tutorials/documents/', $prefix);
    }

    // save profile pic with user id in name
    public function uploadProfilePicture($file, $user_id) {
        return $this->uploadFile($file, 'image', 'profiles/', 'user_' . $user_id);
    }

    // core upload handler validates and moves one file
    private function uploadFile($file, $type, $directory, $prefix = '') {
        $this->errors = [];

        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return ['success' => false, 'message' => 'No file uploaded'];
        }

        // php upload error code check
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => $this->getUploadErrorMessage($file['error'])];
        }

        // extension must be in allowed list for this type
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowed_types[$type])) {
            return [
                'success' => false,
                'message' => 'Invalid file type. Allowed: ' . implode(', ', $this->allowed_types[$type])
            ];
        }

        // reject if over size limit for this type
        if ($file['size'] > $this->max_sizes[$type]) {
            return [
                'success' => false,
                'message' => 'File too large. Maximum: ' . $this->formatFileSize($this->max_sizes[$type])
            ];
        }

        // extra check image headers not just extension
        if ($type === 'image' && !$this->isValidImage($file['tmp_name'])) {
            return ['success' => false, 'message' => 'Invalid image file'];
        }

        // unique name prevents overwrites
        $filename = $this->generateFilename($prefix, $extension);
        $full_path = $this->upload_base_path . $directory . $filename;

        if (move_uploaded_file($file['tmp_name'], $full_path)) {
            // readable but not executable
            chmod($full_path, 0644);

            return [
                'success' => true,
                'message' => 'File uploaded successfully',
                'filename' => $filename,
                'filepath' => $directory . $filename,
                'size' => $file['size'],
                'type' => $type,
                'extension' => $extension
            ];
        } else {
            // usually a folder permissions issue
            return ['success' => false, 'message' => 'Failed to move uploaded file'];
        }
    }

    // upload multiple files and return summary
    public function uploadMultiple($files, $type, $directory) {
        $results = [];
        $success_count = 0;

        foreach ($files['name'] as $key => $name) {
            // skip empty slots in multi-upload
            if (empty($files['tmp_name'][$key])) {
                continue;
            }

            // reshape multi-upload array to single file format
            $file = [
                'name' => $files['name'][$key],
                'type' => $files['type'][$key],
                'tmp_name' => $files['tmp_name'][$key],
                'error' => $files['error'][$key],
                'size' => $files['size'][$key]
            ];

            $result = $this->uploadFile($file, $type, $directory, 'file_' . $key);

            if ($result['success']) {
                $success_count++;
            }

            $results[] = $result;
        }

        return [
            'success' => $success_count > 0,
            'total' => count($files['name']),
            'uploaded' => $success_count,
            'results' => $results
        ];
    }

    // safely delete a file blocking path traversal
    public function deleteFile($filepath) {
        // reject null bytes traversal dots or absolute paths
        if (
            strpos($filepath, "\0") !== false ||   // null byte
            strpos($filepath, '..') !== false ||   // directory traversal
            strpos($filepath, './') !== false ||   // relative traversal
            preg_match('/^\/|^[A-Za-z]:\\\\/', $filepath) // absolute path unix or windows
        ) {
            return false;
        }

        // realpath false means file does not exist treat as safe no-op
        $full_path = realpath($this->upload_base_path . $filepath);

        if ($full_path === false) {
            return false;
        }

        // path must stay inside uploads folder
        $upload_real = realpath($this->upload_base_path);
        if ($upload_real === false || strpos($full_path, $upload_real . DIRECTORY_SEPARATOR) !== 0) {
            return false;
        }

        // only delete regular files not directories
        if (file_exists($full_path) && is_file($full_path)) {
            return unlink($full_path);
        }

        return false;
    }

    // verify file is real image not just renamed
    private function isValidImage($filepath) {
        $image_info = @getimagesize($filepath);
        return $image_info !== false;
    }

    // timestamp plus random bytes so names never collide
    private function generateFilename($prefix, $extension) {
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        return $prefix . '_' . $timestamp . '_' . $random . '.' . $extension;
    }

    // map php upload error code to readable string
    private function getUploadErrorMessage($error_code) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];

        return $errors[$error_code] ?? 'Unknown upload error';
    }

    // bytes to human readable size string
    private function formatFileSize($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    // public url for a given upload path
    public function getFileUrl($filepath) {
        return SITE_URL . '/uploads/' . $filepath;
    }
}
?>
