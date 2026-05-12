<?php
/**
 * global config file
 * has all the main settings and helper functions we need
 * 
 * @package TechKnowledge Hub
 * @author Hasan Fardan
 * @version 1.0
 */

// start the session if its not running already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==================== ALL THE CONSTANTS ====================

// basic site info
define('SITE_NAME', 'TechKnowledge Hub');
define('SITE_URL', 'http://localhost/techknowledgehub');   
define('ADMIN_EMAIL', 'admin@techknow.com');

// everything for uploading files
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10485760);  // 10MB in bytes
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_VIDEO_TYPES', ['mp4', 'webm', 'ogg', 'avi']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx', 'zip', 'txt']);

// settings for pages and lists
define('ITEMS_PER_PAGE', 10);
define('SEARCH_RESULTS_PER_PAGE', 12);

// security stuff like timeouts
define('SESSION_TIMEOUT', 3600);  // 1 hour in seconds
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);


// setting the time for bahrain
date_default_timezone_set('Asia/Bahrain');

// turn off error display for security — log them to a file instead
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// pull in the database class
require_once __DIR__ . '/database.php';

// ==================== HANDLING SESSIONS ====================

/**
 * simple check to see if they are logged in
 * @return boolean
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * check if the user has the right permissions
 * @param string $role role can be admin or creator or viewer
 * @return boolean
 */
function hasRole($role) {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * check if they are admin
 * @return boolean
 */
function isAdmin() {
    return hasRole('admin');
}

/**
 * check if they are a teacher
 * @return boolean
 */
function isCreator() {
    return hasRole('creator');
}

/**
 * check if they are a student
 * @return boolean
 */
function isViewer() {
    return hasRole('viewer');
}

/**
 * kick them out to login page if they arent signed in
 * @param string $redirect_to where to go after they log back in
 */
function requireLogin($redirect_to = '') {
    if (!isLoggedIn()) {
        if (!empty($redirect_to)) {
            $_SESSION['redirect_after_login'] = $redirect_to;
        }
        redirect('auth/login.php');
    }
}

/**
 * make sure they have the right rank before letting them in
 * @param string $role what rank they need
 * @param string $error_message what to tell them if they cant enter
 */
function requireRole($role, $error_message = 'Access denied') {
    requireLogin();
    if (!hasRole($role)) {
        setFlashMessage($error_message, 'error');
        redirect('public/index.php');
    }
}

/**
 * get the logged in users id
 * @return int|null
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * get the users name
 * @return string|null
 */
function getCurrentUserName() {
    return $_SESSION['full_name'] ?? null;
}

/**
 * get the users email
 * @return string|null
 */
function getCurrentUserEmail() {
    return $_SESSION['email'] ?? null;
}

/**
 * get the users role
 * @return string|null
 */
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

// ==================== NAV AND LINKS ====================

/**
 * send the user to another page automatically
 * @param string $url the page path
 */
function redirect($url) {
    // get rid of the first slash if they added it
    $url = ltrim($url, '/');
    
    // if its just a local path add the full site url
    if (!preg_match('/^https?:\/\//', $url)) {
        // Bug 22 fix: avoid double-slash when url is empty
        $url = empty($url) ? SITE_URL : SITE_URL . '/' . $url;
    }
    
    header("Location: " . $url);
    exit();
}

/**
 * get the main site link
 * @return string
 */
function baseUrl($path = '') {
    return SITE_URL . '/' . ltrim($path, '/');
}

/**
 * link to images or css files easily
 * @param string $path path to the asset
 * @return string
 */
function asset($path) {
    return SITE_URL . '/assets/' . ltrim($path, '/');
}

// ==================== CLEANING DATA ====================

/**
 * scrub the data so hackers cant do anything
 * @param mixed $data whatever the user typed
 * @return mixed cleaned data
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * quick trim for inputs — strips HTML tags for non-rich-text fields
 * Bug 33 fix: old version allowed HTML tags through
 * @param string $data
 * @return string
 */
function clean($data) {
    return strip_tags(trim(stripslashes($data)));
}

// string helper functions

/**
 * turn a title into a clean url link
 * @param string $string text to convert
 * @return string nice url slug
 */
function generateSlug($string) {
    $slug = strtolower(trim($string));
    $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

/**
 * cut the text short so it looks neat
 * @param string $text
 * @param int $length
 * @param string $suffix
 * @return string
 */
function truncate($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * keep the output safe from scripts
 * @param string $string
 * @return string
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// ==================== DATE AND TIME ====================

/**
 * make the date look nice for people
 * @param string $date the raw date
 * @param string $format how it should look
 * @return string formatted date
 */
function formatDate($date, $format = 'M d, Y') {
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    return date($format, strtotime($date));
}

/**
 * show how long ago something happened like 5 mins ago
 * @param string $datetime the time it happened
 * @return string relative time text
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } else {
        return formatDate($datetime);
    }
}

// ==================== FLASH ALERTS ====================

/**
 * save a quick message for the next page
 * @param string $message the alert text
 * @param string $type success or error or info
 */
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * grab that message and clear it
 * @return array|null message and its type
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

/**
 * show the alert on the screen
 * @return void
 */
function displayFlashMessage() {
    $flash = getFlashMessage();
    if ($flash) {
        $alertClass = 'alert-' . $flash['type'];
        echo '<div class="alert ' . $alertClass . ' alert-dismissible">';
        echo e($flash['message']);
        echo '<button type="button" class="close" onclick="this.parentElement.style.display=\'none\'">&times;</button>';
        echo '</div>';
    }
}

// ==================== VALIDATION CHECKS ====================

/**
 * check if the email actually looks real
 * @param string $email
 * @return boolean
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * make sure the password is tough enough
 * @param string $password
 * @return array valid status and message
 */
function validatePassword($password) {
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return [
            'valid' => false, 
            'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'
        ];
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        return [
            'valid' => false, 
            'message' => 'Password must contain at least one uppercase letter'
        ];
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        return [
            'valid' => false, 
            'message' => 'Password must contain at least one lowercase letter'
        ];
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        return [
            'valid' => false, 
            'message' => 'Password must contain at least one number'
        ];
    }
    
    return ['valid' => true, 'message' => 'Password is strong'];
}

// ==================== FILE STUFF ====================

/**
 * find out if its a png or jpg
 * @param string $filename
 * @return string
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * check if we allow this type of file
 * @param string $filename
 * @param array $allowed_types
 * @return boolean
 */
function isAllowedFileType($filename, $allowed_types) {
    $extension = getFileExtension($filename);
    return in_array($extension, $allowed_types);
}

/**
 * make the file size easy to read like MB or KB
 * @param int $bytes
 * @return string
 */
function formatFileSize($bytes) {
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

// ==================== SECURITY STUFF ====================

/**
 * create a secret token for forms
 * @return string
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * check if that token is the right one
 * @param string $token
 * @return boolean
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Output CSRF token as hidden input
 * @return void
 */
function csrfField() {
    echo '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

/**
 * Verify CSRF token from POST
 * @return bool
 */
function verifyCsrfFromPost() {
    if (!isset($_POST['csrf_token'])) {
        return false;
    }
    return verifyCSRFToken($_POST['csrf_token']);
}

/**
 * make a random string for secrets
 * @param int $length
 * @return string
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

// ==================== DEBUGGING (delete laterrrr) ====================

/**
 * show whats inside a variable then stop everything
 * @param mixed $var variable to look at
 */
function dd($var) {
    echo '<pre style="background: #f4f4f4; padding: 15px; border: 2px solid #333; border-radius: 5px; margin: 20px;">';
    echo '<strong>DEBUG OUTPUT:</strong><br><br>';
    var_dump($var);
    echo '</pre>';
    die();
}

/**
 * just print the variable so we can see it
 * @param mixed $var
 */
function dp($var) {
    echo '<pre style="background: #f9f9f9; padding: 10px; border-left: 3px solid #3498db; margin: 10px 0;">';
    print_r($var);
    echo '</pre>';
}

// ==================== AUTO LOGIC ====================

// see if they have been away too long
if (isLoggedIn() && isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        session_destroy();
        setFlashMessage('Session expired. Please login again.', 'warning');
        redirect('auth/login.php');
    }
}

// refresh their active time
if (isLoggedIn()) {
    $_SESSION['last_activity'] = time();
}

?>