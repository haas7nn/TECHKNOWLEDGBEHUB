<?php
// login page where users sign into their account

require_once '../config/config.php';
require_once '../classes/User.php';
require_once '../classes/RateLimiter.php';

// if they are already logged in send them straight to their dashboard
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

// set up the default values for the form and rate limiter
$error = '';
$email_value = '';
$rateLimiter = new RateLimiter();

// handle the form when the user clicks login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // csrf check
    if (!verifyCsrfFromPost()) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {

        // grab and clean the submitted values
        $email    = clean($_POST['email']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']);
        $email_value = $email;

        // use both email and ip address as the rate limit key to block abuse
        // f2 use only remote_addr — do not trust xforwardedfor which can be spoofed
        $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rate_key  = $email . '|' . $client_ip;

        // make sure they actually filled in both fields
        if (empty($email) || empty($password)) {
            $error = 'Email and password are required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // check the email format before going any further
            $error = 'Please enter a valid email address';
        } elseif ($rateLimiter->isRateLimited($rate_key)) {
            // too many failed attempts so tell them how long to wait
            $mins  = ceil($rateLimiter->getLockoutTimeRemaining($rate_key) / 60);
            $error = 'Too many failed login attempts. Please try again in ' . $mins . ' minute(s).';
        } else {
            // attempt to log them in using the user class
            $user   = new User();
            $result = $user->login($email, $password);

            if ($result['success']) {
                // login worked so clear any failed attempt count
                $rateLimiter->reset($rate_key);

                // f19 set a long lasting cookie using a random token — never store the email directly
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, [
                        'expires'  => time() + (86400 * 30),
                        'path'     => '/',
                        'secure'   => true,
                        'httponly' => true,
                        'samesite' => 'Strict'
                    ]);
                    // note in production persist token → user_id in a dedicated db table
                    // storing in session here is a temporary standin so the mapping is not lost
                    $_SESSION['remember_token_user'] = $result['user']['user_id'];
                }

                // f3 validate redirect destination — only allow relative paths on this host
                $redirect = $_SESSION['redirect_after_login'] ?? '';
                unset($_SESSION['redirect_after_login']);
                if (!empty($redirect)) {
                    $parsed = parse_url($redirect);
                    if (empty($parsed['scheme']) && empty($parsed['host'])) {
                        // safe relative path with no scheme or host — send them back where they came from
                        redirect($redirect);
                    }
                }

                // otherwise send them to the right dashboard for their role
                if ($result['role'] === 'admin') {
                    redirect('admin/dashboard.php');
                } elseif ($result['role'] === 'creator') {
                    redirect('creator/dashboard.php');
                } else {
                    redirect('viewer/dashboard.php');
                }
            } else {
                // login failed so count this attempt and show the error message
                $rateLimiter->recordAttempt($rate_key);
                $error = $result['message'];
            }
        }
    }
}

// f19 the old remember_user cookie that stored a plain email is no longer used
// the new remember_token cookie holds only an opaque token so there is nothing
// to prefill in the email field from a cookie
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= asset('css/auth.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="<?= asset('js/validation.js') ?>" defer></script>
</head>
<body>
<div class="auth-wrapper">

    <!-- Left gradient panel -->
    <div class="auth-side">
        <div class="auth-side-content">
            <div class="auth-side-brand">
                <i class="fas fa-graduation-cap"></i>
                <span><?= SITE_NAME ?></span>
            </div>
            <h1>Welcome Back</h1>
            <p>Continue your learning journey. Access your tutorials, track your progress, and grow your skills every day.</p>
            <ul class="features">
                <li class="feature-item"><i class="fas fa-play-circle"></i><span>Resume exactly where you left off</span></li>
                <li class="feature-item"><i class="fas fa-chart-line"></i><span>Track your learning progress</span></li>
                <li class="feature-item"><i class="fas fa-star"></i><span>Rate and review tutorials</span></li>
                <li class="feature-item"><i class="fas fa-users"></i><span>Learn from expert instructors</span></li>
            </ul>
        </div>
    </div>

    <!-- Right form panel -->
    <div class="auth-container login-container">
        <div class="auth-box">

            <a href="<?= SITE_URL ?>/public/search.php" class="auth-logo">
                <i class="fas fa-graduation-cap"></i>
                <span><?= SITE_NAME ?></span>
            </a>

            <div class="auth-header">
                <h2>Sign in to your account</h2>
                <p>Enter your credentials to continue learning</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error']) && $_GET['error'] === 'session_timeout'): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-clock"></i>
                    <span>Your session has expired. Please login again.</span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm" class="auth-form" novalidate>
                <?php csrfField(); ?>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control"
                           value="<?= e($email_value) ?>" placeholder="Enter your email"
                           required autofocus>
                    <span class="error-message" id="email-error"></span>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="Enter your password" required>
                        <button type="button" class="toggle-password" onclick="toggleLoginPassword()">
                            <i class="fas fa-eye" id="password-eye"></i>
                        </button>
                    </div>
                    <span class="error-message" id="password-error"></span>
                </div>

                <div class="form-group form-options">
                    <label class="checkbox-label">
                        <!-- F19: do not pre-check from the old plain-email cookie -->
                        <input type="checkbox" id="remember" name="remember"
                               <?= isset($_COOKIE['remember_token']) ? 'checked' : '' ?>>
                        <span>Remember me</span>
                    </label>
                    <a href="forgot-password.php" class="link forgot-link">Forgot Password?</a>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-sign-in-alt"></i>
                    Login
                </button>
            </form>

            <div class="auth-divider"><span>or</span></div>

            <div class="auth-footer">
                <p>Don't have an account? <a href="register.php" class="link">Create one free</a></p>
            </div>

        </div>
    </div>

</div>

<script>
    // toggle the password field between hidden and visible
    function toggleLoginPassword() {
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
</script>
</body>
</html>
