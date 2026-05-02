<?php
// global config file for the whole site
// has all the main settings and helper functions we need

// start the session if its not running already
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'secure'   => false,  // set to true when deployed on HTTPS
        'httponly' => true,
        'samesite' => 'Lax',  // Strict breaks redirects on plain HTTP localhost
        'path'     => '/',
    ]);
    session_start();
}


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

// security stuff like timeouts and password rules
define('SESSION_TIMEOUT', 3600);  // 1 hour in seconds
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);

// set the timezone to bahrain
date_default_timezone_set('Asia/Bahrain');

// hide errors from the screen and write them to a log file instead
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// pull in the database class
require_once __DIR__ . '/database.php';


// check if the user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// check if the logged in user has a specific role
function hasRole($role) {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// check if the user is an admin
function isAdmin() {
    return hasRole('admin');
}

// check if the user is a creator or instructor
function isCreator() {
    return hasRole('creator');
}

// check if the user is a viewer or student
function isViewer() {
    return hasRole('viewer');
}

// send them to login if they are not signed in yet
function requireLogin($redirect_to = '') {
    if (!isLoggedIn()) {
        // save where they wanted to go so we can bring them back after login
        if (!empty($redirect_to)) {
            $_SESSION['redirect_after_login'] = $redirect_to;
        }
        redirect('auth/login.php');
    }
}

// make sure the user has the right role before letting them see a page
function requireRole($role, $error_message = 'Access denied') {
    // first make sure they are even logged in
    requireLogin();
    // then check if their role matches what we need
    if (!hasRole($role)) {
        setFlashMessage($error_message, 'error');
        redirect('public/index.php');
    }
}

// get the id of whoever is logged in right now
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// get the full name of whoever is logged in right now
function getCurrentUserName() {
    return $_SESSION['full_name'] ?? null;
}

// get the email of whoever is logged in right now
function getCurrentUserEmail() {
    return $_SESSION['email'] ?? null;
}

// get the role of whoever is logged in right now
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}


// send the user to another page automatically
function redirect($url) {
    // strip any leading slash they might have added
    $url = ltrim($url, '/');

    // if it is a local path add the full site url in front of it
    if (!preg_match('/^https?:\/\//', $url)) {
        // avoid double slash when the url is empty
        $url = empty($url) ? SITE_URL : SITE_URL . '/' . $url;
    }

    header("Location: " . $url);
    exit();
}

// build the full url to the home or any page
function baseUrl($path = '') {
    return SITE_URL . '/' . ltrim($path, '/');
}

// build the full url to any asset like an image or css file
function asset($path) {
    return SITE_URL . '/assets/' . ltrim($path, '/');
}


// clean user input so nothing dangerous gets through
function sanitize($data) {
    // handle arrays by running each item through this same function
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    // strip whitespace then slashes then encode special characters
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// strip all html tags and trim whitespace from plain text fields
function clean($data) {
    return strip_tags(trim(stripslashes($data)));
}


// turn a title into a url friendly slug
function generateSlug($string) {
    // lowercase everything first
    $slug = strtolower(trim($string));
    // swap anything that is not a letter or number for a dash
    $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
    // collapse multiple dashes into one
    $slug = preg_replace('/-+/', '-', $slug);
    // remove any leading or trailing dashes
    $slug = trim($slug, '-');
    return $slug;
}

// shorten a long piece of text and add dots at the end if it gets cut
function truncate($text, $length = 100, $suffix = '...') {
    // if it is already short enough just return it as is
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

// safely escape a string before printing it to the page
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}


// format a date into something people can actually read
function formatDate($date, $format = 'M d, Y') {
    // return a placeholder if the date is empty or invalid
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    return date($format, strtotime($date));
}

// show a friendly relative time like 5 minutes ago or 2 days ago
function timeAgo($datetime) {
    // get how many seconds have passed since that time
    $time = strtotime($datetime);
    $diff = time() - $time;

    // pick the right unit based on how long ago it was
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
        // fall back to a normal formatted date if it was a long time ago
        return formatDate($datetime);
    }
}


// store a one time message to show on the next page
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

// grab the stored flash message and remove it so it only shows once
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        // read the message and its type
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';

        // delete it from the session so it does not show again
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);

        return ['message' => $message, 'type' => $type];
    }
    return null;
}

// print the flash message alert box to the screen if there is one
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


// check if the email address looks like a real one
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// check if the password meets all our requirements
function validatePassword($password) {
    // check minimum length first
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return [
            'valid' => false,
            'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'
        ];
    }

    // make sure there is at least one uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        return [
            'valid' => false,
            'message' => 'Password must contain at least one uppercase letter'
        ];
    }

    // make sure there is at least one lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        return [
            'valid' => false,
            'message' => 'Password must contain at least one lowercase letter'
        ];
    }

    // make sure there is at least one number
    if (!preg_match('/[0-9]/', $password)) {
        return [
            'valid' => false,
            'message' => 'Password must contain at least one number'
        ];
    }

    return ['valid' => true, 'message' => 'Password is strong'];
}


// grab just the extension from a filename like jpg or pdf
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

// check if the file type is in our allowed list
function isAllowedFileType($filename, $allowed_types) {
    $extension = getFileExtension($filename);
    return in_array($extension, $allowed_types);
}

// turn a raw byte count into something readable like 2 mb or 500 kb
function formatFileSize($bytes) {
    // check from largest unit down to smallest
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


// create a unique token for the current session to protect forms
function generateCSRFToken() {
    // only make a new one if there is not one already
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// check if the token from the form matches the one we stored in the session
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// print a hidden form field with the csrf token in it
function csrfField() {
    echo '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

// verify the csrf token that came in through post
function verifyCsrfFromPost() {
    // if the token is missing in the post data then fail straight away
    if (!isset($_POST['csrf_token'])) {
        return false;
    }
    return verifyCSRFToken($_POST['csrf_token']);
}

// generate a random string we can use for tokens or secrets
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}


// check if the user has been idle too long and log them out if so
if (isLoggedIn() && isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        // wipe the session and redirect them to login
        $_SESSION = [];
        session_destroy();
        setFlashMessage('Session expired. Please login again.', 'warning');
        session_write_close();
        redirect('auth/login.php');
    }
}

// update their last active time so the timeout resets while they are using the site
if (isLoggedIn()) {
    $_SESSION['last_activity'] = time();
}

?>
