<?php
// this class handles all file uploads for the site
// it validates the file checks the size and moves it to the right folder

class FileUpload {

    // lists of allowed file extensions grouped by type
    /** @var array<string, array<string>> */
    private $allowed_types = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'video' => ['mp4', 'webm', 'ogg', 'avi', 'mov'],
        'document' => ['pdf', 'doc', 'docx', 'zip', // WARNING: ZIP files — never extract server-side (Zip Slip risk)
                       'txt', 'pptx']
    ];

    // maximum allowed file sizes in bytes for each type
    /** @var array<string, int> */
    private $max_sizes = [
        'image' => 5242880,      // 5MB
        'video' => 52428800,     // 50MB
        'document' => 10485760   // 10MB
    ];

    // the base folder where all uploaded files are stored
    /** @var string */
    private $upload_base_path;

    // collects any validation errors that come up during an upload
    /** @var array<string> */
    private $errors = [];

    // set up the upload path and make sure all the needed folders exist
    public function __construct() {
        $this->upload_base_path = __DIR__ . '/../uploads/';

        // create the upload subfolders if they are not already there
        $this->createDirectories();
    }

    // make sure all the upload subfolders exist
    // also drops an indexphp file in each one to block directory browsing
    private function createDirectories() {
        // list of all the subfolders we need under the uploads folder
        $dirs = [
            'tutorials/thumbnails',
            'tutorials/videos',
            'tutorials/documents',
            'profiles',
            'temp'
        ];

        // create each folder and protect it if it does not already exist
        foreach ($dirs as $dir) {
            $path = $this->upload_base_path . $dir;
            if (!file_exists($path)) {
                mkdir($path, 0755, true);

                // put a blocking index file in the folder so no one can browse it
                file_put_contents($path . '/index.php', "<?php header('HTTP/1.0 403 Forbidden'); die('Access denied'); ?>");

                // prevent php execution and directory listing in this folder
                $htaccess = $path . '/.htaccess';
                if (!file_exists($htaccess)) {
                    file_put_contents($htaccess, "php_flag engine off\nOptions -Indexes\n");
                }
            }
        }
    }

    // public wrapper around the private uploadfile method
    // this lets the tutorial uploadmedia method call it for video uploads
    public function uploadFile_public($file, $type, $directory, $prefix = '') {
        return $this->uploadFile($file, $type, $directory, $prefix);
    }

    // upload an image thumbnail to the thumbnails folder
    // returns the result array from the core upload handler
    public function uploadThumbnail($file, $prefix = 'thumb') {
        return $this->uploadFile($file, 'image', 'tutorials/thumbnails/', $prefix);
    }

    // upload a document file to the documents folder
    // returns the result array from the core upload handler
    public function uploadDocument($file, $prefix = 'doc') {
        return $this->uploadFile($file, 'document', 'tutorials/documents/', $prefix);
    }

    // upload a profile picture to the profiles folder using the user id as part of the name
    // returns the result array from the core upload handler
    public function uploadProfilePicture($file, $user_id) {
        return $this->uploadFile($file, 'image', 'profiles/', 'user_' . $user_id);
    }

    // the main upload handler that validates and saves a single file
    // checks for errors validates the extension and size then moves the file into place
    private function uploadFile($file, $type, $directory, $prefix = '') {
        // reset the error list for this upload attempt
        $this->errors = [];

        // make sure a file was actually submitted
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return ['success' => false, 'message' => 'No file uploaded'];
        }

        // check if php reported any upload error code
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => $this->getUploadErrorMessage($file['error'])];
        }

        // pull out the extension and check it is in the allowed list for this type
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowed_types[$type])) {
            return [
                'success' => false,
                'message' => 'Invalid file type. Allowed: ' . implode(', ', $this->allowed_types[$type])
            ];
        }

        // reject the file if it is bigger than the limit for this type
        if ($file['size'] > $this->max_sizes[$type]) {
            return [
                'success' => false,
                'message' => 'File too large. Maximum: ' . $this->formatFileSize($this->max_sizes[$type])
            ];
        }

        // for images do an extra check to make sure the file is actually an image and not a renamed file
        if ($type === 'image' && !$this->isValidImage($file['tmp_name'])) {
            return ['success' => false, 'message' => 'Invalid image file'];
        }

        // build a unique file name so uploads never overwrite each other
        $filename = $this->generateFilename($prefix, $extension);
        $full_path = $this->upload_base_path . $directory . $filename;

        // move the temporary file to its permanent location
        if (move_uploaded_file($file['tmp_name'], $full_path)) {
            // set the file permissions so it is readable but not executable
            chmod($full_path, 0644);

            // return all the info the caller might need about the uploaded file
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
            // the move failed which usually means a permissions problem
            return ['success' => false, 'message' => 'Failed to move uploaded file'];
        }
    }

    // handle uploading multiple files at once
    // loops through each file and calls the single file handler for each one
    // returns a summary of how many succeeded and the individual results
    public function uploadMultiple($files, $type, $directory) {
        $results = [];
        $success_count = 0;

        // go through each file in the array
        foreach ($files['name'] as $key => $name) {
            // skip any slots where no file was chosen
            if (empty($files['tmp_name'][$key])) {
                continue;
            }

            // build a standard single file array from the multiple upload structure
            $file = [
                'name' => $files['name'][$key],
                'type' => $files['type'][$key],
                'tmp_name' => $files['tmp_name'][$key],
                'error' => $files['error'][$key],
                'size' => $files['size'][$key]
            ];

            // try to upload this individual file
            $result = $this->uploadFile($file, $type, $directory, 'file_' . $key);

            // keep track of how many uploaded successfully
            if ($result['success']) {
                $success_count++;
            }

            // collect the result for this file
            $results[] = $result;
        }

        // return a summary along with the individual results
        return [
            'success' => $success_count > 0,
            'total' => count($files['name']),
            'uploaded' => $success_count,
            'results' => $results
        ];
    }

    // delete an uploaded file from the server
    // includes safety checks to make sure no one can delete files outside the upload folder
    public function deleteFile($filepath) {
        // block any path that contains suspicious characters or patterns
        if (
            strpos($filepath, "\0") !== false ||   // null byte in path
            strpos($filepath, '..') !== false ||   // directory traversal attempt
            strpos($filepath, './') !== false ||   // relative traversal attempt
            preg_match('/^\/|^[A-Za-z]:\\\\/', $filepath) // absolute path on Unix or Windows
        ) {
            return false;
        }

        // resolve the full real path so we can do the containment check
        $full_path = realpath($this->upload_base_path . $filepath);

        // realpath returns false if the file does not exist so treat that as a safe no op
        if ($full_path === false) {
            return false;
        }

        // make sure the resolved path is actually inside the upload folder and not somewhere else
        $upload_real = realpath($this->upload_base_path);
        if ($upload_real === false || strpos($full_path, $upload_real . DIRECTORY_SEPARATOR) !== 0) {
            return false;
        }

        // only delete it if it actually exists and is a regular file not a directory
        if (file_exists($full_path) && is_file($full_path)) {
            return unlink($full_path);
        }

        return false;
    }

    // check whether a file is actually a valid image by reading its headers
    // returns true if php can read image info from it
    private function isValidImage($filepath) {
        $image_info = @getimagesize($filepath);
        return $image_info !== false;
    }

    // generate a unique file name using a timestamp and random bytes
    // this prevents name collisions and makes file names hard to guess
    private function generateFilename($prefix, $extension) {
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        return $prefix . '_' . $timestamp . '_' . $random . '.' . $extension;
    }

    // turn a php upload error code into a human readable message
    // returns a string describing what went wrong
    private function getUploadErrorMessage($error_code) {
        // map each php upload error constant to a friendly description
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];

        // return the matching message or a generic fallback
        return $errors[$error_code] ?? 'Unknown upload error';
    }

    // convert a size in bytes to a readable string like 5 mb or 320 kb
    private function formatFileSize($bytes) {
        // pick the right unit based on how large the number is
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

    // build the public url for an uploaded file so the browser can load it
    public function getFileUrl($filepath) {
        return SITE_URL . '/uploads/' . $filepath;
    }
}
?>
