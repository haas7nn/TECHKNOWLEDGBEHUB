<?php
// login page

require_once '../config/config.php';
require_once '../classes/User.php';
require_once '../classes/RateLimiter.php';

// redirect if already logged in
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

// init form defaults and rate limiter
$error = '';
$email_value = '';
$rateLimiter = new RateLimiter();

// handle POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // csrf check
    if (!verifyCsrfFromPost()) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {

        // sanitize inputs
        $email    = clean($_POST['email']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']);
        $email_value = $email;

        // combine email and IP as rate limit key — X-Forwarded-For is spoofable
        $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rate_key  = $email . '|' . $client_ip;

        if (empty($email) || empty($password)) {
            $error = 'Email and password are required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
        } elseif ($rateLimiter->isRateLimited($rate_key)) {
            // show wait time before next allowed attempt
            $mins  = ceil($rateLimiter->getLockoutTimeRemaining($rate_key) / 60);
            $error = 'Too many failed login attempts. Please try again in ' . $mins . ' minute(s).';
        } else {
            $user   = new User();
            $result = $user->login($email, $password);

            if ($result['success']) {
                // clear rate limit on successful login
                $rateLimiter->reset($rate_key);

                // remember me — full token-DB lookup is a future extension
                if ($remember) {
                    setcookie('remember_me', '1', [
                        'expires'  => time() + (86400 * 30),
                        'path'     => '/',
                        'secure'   => false,   // set true on HTTPS deployment
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]);
                } else {
                    // expire cookie if remember unchecked
                    setcookie('remember_me', '', ['expires' => time() - 3600, 'path' => '/']);
                }

                // only allow relative redirects — block open redirect attacks
                $redirect = $_SESSION['redirect_after_login'] ?? '';
                unset($_SESSION['redirect_after_login']);
                if (!empty($redirect)) {
                    $parsed = parse_url($redirect);
                    if (empty($parsed['scheme']) && empty($parsed['host'])) {
                        redirect($redirect);
                    }
                }

                // route to role dashboard
                if ($result['role'] === 'admin') {
                    redirect('admin/dashboard.php');
                } elseif ($result['role'] === 'creator') {
                    redirect('creator/dashboard.php');
                } else {
                    redirect('viewer/dashboard.php');
                }
            } else {
                // record failed attempt for rate limiter
                $rateLimiter->recordAttempt($rate_key);
                $error = $result['message'];
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
    <title>Login | <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= asset('css/auth.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="<?= asset('js/validation.js') ?>" defer></script>
</head>
<body>
<div class="auth-wrapper">

    <!-- left panel -->
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

    <!-- right form panel -->
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
                            <input type="checkbox" id="remember" name="remember"
                               <?= isset($_COOKIE['remember_me']) ? 'checked' : '' ?>>
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
    // toggle password visibility
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
