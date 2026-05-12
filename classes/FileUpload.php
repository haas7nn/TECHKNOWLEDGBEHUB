<?php
/**
 * File Upload Handler
 * Handles all file uploads with validation
 * Hasan Fardan - 202301686
 */

class FileUpload {
    
    /** @var array<string, array<string>> */
    private $allowed_types = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'video' => ['mp4', 'webm', 'ogg', 'avi', 'mov'],
        'document' => ['pdf', 'doc', 'docx', 'zip', 'txt', 'pptx']
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
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->upload_base_path = __DIR__ . '/../uploads/';
        
        // create upload directories if they don't exist
        $this->createDirectories();
    }
    
    /**
     * Create necessary upload directories
     */
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
                
                // add index.php to prevent browsing
                file_put_contents($path . '/index.php', "<?php header('HTTP/1.0 403 Forbidden'); die('Access denied'); ?>");
            }
        }
    }
    
    /**
     * Public wrapper for uploadFile — used by Tutorial::uploadMedia for videos
     */
    public function uploadFile_public($file, $type, $directory, $prefix = '') {
        return $this->uploadFile($file, $type, $directory, $prefix);
    }

    /**
     * Upload thumbnail image
     * @param array<string, mixed> $file The $_FILES array element
     * @param string $prefix Filename prefix
     * @return array<string, mixed> Upload result
     */
    public function uploadThumbnail($file, $prefix = 'thumb') {
        return $this->uploadFile($file, 'image', 'tutorials/thumbnails/', $prefix);
    }
    
    /**
     * Upload document file
     * @param array<string, mixed> $file The $_FILES array element
     * @param string $prefix Filename prefix
     * @return array<string, mixed> Upload result
     */
    public function uploadDocument($file, $prefix = 'doc') {
        return $this->uploadFile($file, 'document', 'tutorials/documents/', $prefix);
    }
    
    /**
     * Upload profile picture
     * @param array<string, mixed> $file The $_FILES array element
     * @param int $user_id User ID for filename
     * @return array<string, mixed> Upload result
     */
    public function uploadProfilePicture($file, $user_id) {
        return $this->uploadFile($file, 'image', 'profiles/', 'user_' . $user_id);
    }
    
    /**
     * Main upload handler
     * @param array<string, mixed> $file The $_FILES array element
     * @param string $type File type category
     * @param string $directory Upload subdirectory
     * @param string $prefix Filename prefix
     * @return array<string, mixed>
     */
    private function uploadFile($file, $type, $directory, $prefix = '') {
        $this->errors = [];
        
        // check if file was uploaded
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return ['success' => false, 'message' => 'No file uploaded'];
        }
        
        // check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => $this->getUploadErrorMessage($file['error'])];
        }
        
        // validate file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowed_types[$type])) {
            return [
                'success' => false, 
                'message' => 'Invalid file type. Allowed: ' . implode(', ', $this->allowed_types[$type])
            ];
        }
        
        // validate file size
        if ($file['size'] > $this->max_sizes[$type]) {
            return [
                'success' => false, 
                'message' => 'File too large. Maximum: ' . $this->formatFileSize($this->max_sizes[$type])
            ];
        }
        
        // validate actual file type (security check)
        if ($type === 'image' && !$this->isValidImage($file['tmp_name'])) {
            return ['success' => false, 'message' => 'Invalid image file'];
        }
        
        // generate unique filename
        $filename = $this->generateFilename($prefix, $extension);
        $full_path = $this->upload_base_path . $directory . $filename;
        
        // move uploaded file
        if (move_uploaded_file($file['tmp_name'], $full_path)) {
            // set proper permissions
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
            return ['success' => false, 'message' => 'Failed to move uploaded file'];
        }
    }
    
    /**
     * Upload multiple files
     * @param array<int, array<string, mixed>> $files Multiple $_FILES elements
     * @param string $type File type category
     * @param string $directory Upload subdirectory
     * @return array<string, mixed>
     */
    public function uploadMultiple($files, $type, $directory) {
        $results = [];
        $success_count = 0;
        
        foreach ($files['name'] as $key => $name) {
            if (empty($files['tmp_name'][$key])) {
                continue;
            }
            
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
    
/**
 * Delete uploaded file
 * @param string $filepath Relative path from uploads directory
 * @return bool
 */
public function deleteFile($filepath) {
    // Bug 6 fix: block null bytes, absolute paths, and traversal sequences
    if (
        strpos($filepath, "\0") !== false ||   // null byte
        strpos($filepath, '..') !== false ||   // traversal
        strpos($filepath, './') !== false ||   // relative traversal
        preg_match('/^\/|^[A-Za-z]:\\\\/', $filepath) // absolute path (Unix or Windows)
    ) {
        return false;
    }

    $full_path = realpath($this->upload_base_path . $filepath);

    // realpath returns false if path doesn't exist — treat as safe failure
    if ($full_path === false) {
        return false;
    }

    // ensure resolved path is strictly inside the upload directory
    $upload_real = realpath($this->upload_base_path);
    if ($upload_real === false || strpos($full_path, $upload_real . DIRECTORY_SEPARATOR) !== 0) {
        return false;
    }

    if (file_exists($full_path) && is_file($full_path)) {
        return unlink($full_path);
    }

    return false;
}
    
    /**
     * Validate image file
     * @param string $filepath
     * @return bool
     */
    private function isValidImage($filepath) {
        $image_info = @getimagesize($filepath);
        return $image_info !== false;
    }
    
    /**
     * Generate unique filename
     * @param string $prefix
     * @param string $extension
     * @return string
     */
    private function generateFilename($prefix, $extension) {
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        return $prefix . '_' . $timestamp . '_' . $random . '.' . $extension;
    }
    
    /**
     * Get readable upload error message
     * @param int $error_code
     * @return string
     */
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
    
    /**
     * Format file size for display
     * @param int $bytes
     * @return string
     */
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
    
    /**
     * Get file URL for browser access
     * @param string $filepath Relative path from uploads directory
     * @return string
     */
    public function getFileUrl($filepath) {
        return SITE_URL . '/uploads/' . $filepath;
    }
}
?>