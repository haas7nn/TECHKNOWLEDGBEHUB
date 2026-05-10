<?php
/**
 * User Login Page
 * Hasan Fardan - 202301686
 */

// Pull in our config and core classes
require_once '../config/config.php';
require_once '../classes/User.php';
require_once '../classes/RateLimiter.php';

// === IF ALREADY LOGGED IN, SEND THEM TO THEIR DASHBOARD ===
// No point showing the login page to someone who's already signed in
if (isLoggedIn()) {
    $role = getCurrentUserRole();
    if ($role === 'admin') {
        redirect('admin/dashboard.php');
    } elseif ($role === 'creator') {
        redirect('creator/dashboard.php');
    } else {
        redirect('viewer/dashboard.php');
    }
}

// === SET UP VARIABLES ===
$error = '';           // holds any error message to show the user
$email_value = '';     // remembers their email so they don't have to retype it
$rateLimiter = new RateLimiter();  // stops brute force attacks

// === HANDLE LOGIN FORM SUBMISSION ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // First, check the CSRF token so this is a legit request from our form
    if (!verifyCsrfFromPost()) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {

        // Grab and clean the form inputs
        $email    = clean($_POST['email']);
        $password = $_POST['password'];  // don't clean passwords, just use raw
        $remember = isset($_POST['remember']);  // did they check "remember me"?
        $email_value = $email;  // save for repopulating the form if login fails

        // === VALIDATION ===
        // Check for empty fields first
        if (empty($email) || empty($password)) {
            $error = 'Email and password are required';
        // Make sure the email looks like an actual email
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
        // Check if this email is temporarily locked out from too many failed attempts
        } elseif ($rateLimiter->isRateLimited($email)) {
            $mins  = ceil($rateLimiter->getLockoutTimeRemaining($email) / 60);
            $error = 'Too many failed login attempts. Please try again in ' . $mins . ' minute(s).';
        } else {
            // === ATTEMPT LOGIN ===
            $user   = new User();
            $result = $user->login($email, $password);

            if ($result['success']) {
                // Login worked! Reset their failed attempt counter
                $rateLimiter->reset($email);

                // === HANDLE "REMEMBER ME" COOKIE ===
                // Bug 9 fix: only one setcookie call, using the secure array syntax
                // This stores their email in a secure cookie for 30 days
                if ($remember) {
                    setcookie('remember_user', $email, [
                        'expires'  => time() + (86400 * 30),  // 30 days
                        'path'     => '/',                    // available site-wide
                        'secure'   => true,                   // only send over HTTPS
                        'httponly' => true,                   // JS can't read it
                        'samesite' => 'Strict'                // prevents CSRF attacks
                    ]);
                }

                // === REDIRECT AFTER LOGIN ===
                // If they were trying to access a page before logging in, send them there
                if (isset($_SESSION['redirect_after_login'])) {
                    $redirect_url = $_SESSION['redirect_after_login'];
                    unset($_SESSION['redirect_after_login']);
                    redirect($redirect_url);
                }

                // Otherwise, send them to the right dashboard based on their role
                if ($result['role'] === 'admin') {
                    redirect('admin/dashboard.php');
                } elseif ($result['role'] === 'creator') {
                    redirect('creator/dashboard.php');
                } else {
                    redirect('viewer/dashboard.php');
                }

            } else {
                // Login failed — record this attempt and show the error
                $rateLimiter->recordAttempt($email);
                $error = $result['message'];
            }
        }
    }
}

// === PRE-FILL EMAIL FROM "REMEMBER ME" COOKIE ===
// If they have a remember cookie and the form isn't already filled, use it
if (isset($_COOKIE['remember_user']) && empty($email_value)) {
    $email_value = $_COOKIE['remember_user'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= SITE_NAME ?></title>
    
    <!-- Our auth styles -->
    <link rel="stylesheet" href="<?= asset('css/auth.css') ?>">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-container login-container">
            <div class="auth-box">
                <!-- Site logo and name -->
                <div class="auth-logo">
                    <i class="fas fa-graduation-cap"></i>
                    <h1><?= SITE_NAME ?></h1>
                </div>

                <!-- Welcome text -->
                <div class="auth-header">
                    <h2>Welcome Back!</h2>
                    <p>Login to continue your learning journey</p>
                </div>

                <!-- Show any error message (wrong password, rate limited, etc.) -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?= e($error) ?></span>
                    </div>
                <?php endif; ?>

                <!-- Show session timeout warning if they were kicked out for inactivity -->
                <?php if (isset($_GET['error']) && $_GET['error'] === 'session_timeout'): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-clock"></i>
                        <span>Your session has expired. Please login again.</span>
                    </div>
                <?php endif; ?>

                <!-- Login form -->
                <form method="POST" action="" id="loginForm" class="auth-form" novalidate>
                    <?php csrfField(); ?>

                    <!-- Email field -->
                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i>
                            Email Address
                        </label>
                        <input type="email" id="email" name="email" class="form-control"
                               value="<?= e($email_value) ?>" placeholder="Enter your email"
                               required autofocus>
                        <!-- Client-side error message (shown by JS) -->
                        <span class="error-message" id="email-error"></span>
                    </div>

                    <!-- Password field with show/hide toggle -->
                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-lock"></i>
                            Password
                        </label>
                        <div class="password-input-wrapper">
                            <input type="password" id="password" name="password" class="form-control"
                                   placeholder="Enter your password" required>
                            <!-- Eye icon to toggle password visibility -->
                            <button type="button" class="toggle-password" onclick="togglePassword()">
                                <i class="fas fa-eye" id="password-eye"></i>
                            </button>
                        </div>
                        <span class="error-message" id="password-error"></span>
                    </div>

                    <!-- Remember me checkbox + forgot password link -->
                    <div class="form-group form-options">
                        <label class="checkbox-label">
                            <input type="checkbox" id="remember" name="remember"
                                   <?= isset($_COOKIE['remember_user']) ? 'checked' : '' ?>>
                            <span>Remember me</span>
                        </label>
                        <a href="forgot-password.php" class="link forgot-link">Forgot Password?</a>
                    </div>

                    <!-- Submit button -->
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-sign-in-alt"></i>
                        Login
                    </button>
                </form>

                <!-- Divider -->
                <div class="auth-divider"><span>or</span></div>

                <!-- Link to registration -->
                <div class="auth-footer">
                    <p>Don't have an account? <a href="register.php" class="link">Register here</a></p>
                </div>
            </div>

            <!-- Right side panel with marketing info -->
            <div class="auth-side">
                <div class="auth-side-content">
                    <h2>Continue Learning</h2>
                    <p>Access your tutorials, track your progress, and continue your educational journey.</p>
                    <div class="stats">
                        <div class="stat-item"><h3>1000+</h3><p>Tutorials</p></div>
                        <div class="stat-item"><h3>500+</h3><p>Instructors</p></div>
                        <div class="stat-item"><h3>10K+</h3><p>Students</p></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility (show/hide the text)
        function togglePassword() {
            const field = document.getElementById('password');
            const eye   = document.getElementById('password-eye');
            if (field.type === 'password') {
                field.type = 'text';
                eye.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                field.type = 'password';
                eye.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // Client-side form validation before submitting
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email    = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            let hasError   = false;

            // Clear previous errors
            document.getElementById('email-error').textContent    = '';
            document.getElementById('password-error').textContent = '';

            // Validate email
            if (!email) {
                document.getElementById('email-error').textContent = 'Email is required';
                hasError = true;
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                document.getElementById('email-error').textContent = 'Invalid email format';
                hasError = true;
            }

            // Validate password
            if (!password) {
                document.getElementById('password-error').textContent = 'Password is required';
                hasError = true;
            }

            // Stop form submission if there are client-side errors
            if (hasError) e.preventDefault();
        });
    </script>
</body>
</html>