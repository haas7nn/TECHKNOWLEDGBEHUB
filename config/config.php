<?php
// site-wide settings and helpers

// start session only if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'secure'   => false,  // flip to true on HTTPS
        'httponly' => true,
        'samesite' => 'Lax',  // Strict breaks redirects on local HTTP
        'path'     => '/',
    ]);
    session_start();
}


// basic site constants
define('SITE_NAME', 'TechKnowledge Hub');
define('SITE_URL', 'http://localhost/techknowledgehub');
define('ADMIN_EMAIL', 'admin@techknow.com');

// upload limits and allowed types
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10485760);  // 10MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_VIDEO_TYPES', ['mp4', 'webm', 'ogg', 'avi']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx', 'zip', 'txt']);

// pagination defaults
define('ITEMS_PER_PAGE', 10);
define('SEARCH_RESULTS_PER_PAGE', 12);

// session and password rules
define('SESSION_TIMEOUT', 3600);  // 1 hour
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);

date_default_timezone_set('Asia/Bahrain');

// log errors silently instead of showing them
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/database.php';


// session has a valid user id
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// check session role matches
function hasRole($role) {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// admin role check
function isAdmin() {
    return hasRole('admin');
}

// creator role check
function isCreator() {
    return hasRole('creator');
}

// viewer role check
function isViewer() {
    return hasRole('viewer');
}

// bounce to login if not signed in
function requireLogin($redirect_to = '') {
    if (!isLoggedIn()) {
        // remember destination to redirect back after login
        if (!empty($redirect_to)) {
            $_SESSION['redirect_after_login'] = $redirect_to;
        }
        redirect('auth/login.php');
    }
}

// must be logged in with matching role
function requireRole($role, $error_message = 'Access denied') {
    requireLogin();
    if (!hasRole($role)) {
        setFlashMessage($error_message, 'error');
        redirect('public/index.php');
    }
}

// current user id from session
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// current user full name from session
function getCurrentUserName() {
    return $_SESSION['full_name'] ?? null;
}

// current user email from session
function getCurrentUserEmail() {
    return $_SESSION['email'] ?? null;
}

// current user role from session
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}


// redirect to a url and stop
function redirect($url) {
    // strip leading slash to avoid double slash
    $url = ltrim($url, '/');

    // prepend site url for local paths
    if (!preg_match('/^https?:\/\//', $url)) {
        $url = empty($url) ? SITE_URL : SITE_URL . '/' . $url;
    }

    header("Location: " . $url);
    exit();
}

// full url for any page path
function baseUrl($path = '') {
    return SITE_URL . '/' . ltrim($path, '/');
}

// full url for an asset file
function asset($path) {
    return SITE_URL . '/assets/' . ltrim($path, '/');
}


// trim slash and encode special chars
function sanitize($data) {
    // recursively handle arrays
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    // trim slashes encode html chars
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// strip tags for plain text fields
function clean($data) {
    return strip_tags(trim(stripslashes($data)));
}


// url-friendly slug from a title
function generateSlug($string) {
    $slug = strtolower(trim($string));
    // non-alphanumeric becomes dash
    $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
    // collapse repeated dashes
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

// cut text to max length and append suffix
function truncate($text, $length = 100, $suffix = '...') {
    // already fits no need to cut
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

// escape output to the page
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}


// readable date string from db value
function formatDate($date, $format = 'M d, Y') {
    // guard against empty or zero dates
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    return date($format, strtotime($date));
}

// relative time string like 5 minutes ago
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;

    // pick unit based on elapsed seconds
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
        // older than a month just show the date
        return formatDate($datetime);
    }
}


// save message to show on next page load
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

// read and clear flash message so it shows once
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';

        // clear after reading
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);

        return ['message' => $message, 'type' => $type];
    }
    return null;
}

// render flash alert box if one exists
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


// validate email format
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// password strength check returns result array
function validatePassword($password) {
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return [
            'valid' => false,
            'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'
        ];
    }

    // need at least one uppercase
    if (!preg_match('/[A-Z]/', $password)) {
        return [
            'valid' => false,
            'message' => 'Password must contain at least one uppercase letter'
        ];
    }

    // need at least one lowercase
    if (!preg_match('/[a-z]/', $password)) {
        return [
            'valid' => false,
            'message' => 'Password must contain at least one lowercase letter'
        ];
    }

    // need at least one digit
    if (!preg_match('/[0-9]/', $password)) {
        return [
            'valid' => false,
            'message' => 'Password must contain at least one number'
        ];
    }

    return ['valid' => true, 'message' => 'Password is strong'];
}


// get lowercase file extension
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

// extension must be in allowed list
function isAllowedFileType($filename, $allowed_types) {
    $extension = getFileExtension($filename);
    return in_array($extension, $allowed_types);
}

// human readable file size string
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


// get or create csrf token for this session
function generateCSRFToken() {
    // reuse existing token if already set
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// constant-time compare of csrf token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// output hidden csrf input field
function csrfField() {
    echo '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

// check csrf token from POST data
function verifyCsrfFromPost() {
    // missing token means fail
    if (!isset($_POST['csrf_token'])) {
        return false;
    }
    return verifyCSRFToken($_POST['csrf_token']);
}

// random hex string for tokens
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}


// expire session after idle timeout
if (isLoggedIn() && isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        // destroy session and force re-login
        $_SESSION = [];
        session_destroy();
        setFlashMessage('Session expired. Please login again.', 'warning');
        session_write_close();
        redirect('auth/login.php');
    }
}

// keep session alive while user is active
if (isLoggedIn()) {
    $_SESSION['last_activity'] = time();
}

?>
