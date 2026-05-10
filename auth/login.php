<?php
/**
 * User Login Page
 * Hasan Fardan - 202301686
 */

require_once '../config/config.php';
require_once '../classes/User.php';
require_once '../classes/RateLimiter.php';

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

$error = '';
$email_value = '';
$rateLimiter = new RateLimiter();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfFromPost()) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {

        $email    = clean($_POST['email']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']);
        $email_value = $email;

        if (empty($email) || empty($password)) {
            $error = 'Email and password are required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
        } elseif ($rateLimiter->isRateLimited($email)) {
            $mins  = ceil($rateLimiter->getLockoutTimeRemaining($email) / 60);
            $error = 'Too many failed login attempts. Please try again in ' . $mins . ' minute(s).';
        } else {
            $user   = new User();
            $result = $user->login($email, $password);

            if ($result['success']) {
                $rateLimiter->reset($email);

                // Bug 9 fix: only one setcookie call, using the secure array syntax
                if ($remember) {
                    setcookie('remember_user', $email, [
                        'expires'  => time() + (86400 * 30),
                        'path'     => '/',
                        'secure'   => true,
                        'httponly' => true,
                        'samesite' => 'Strict'
                    ]);
                }

                if (isset($_SESSION['redirect_after_login'])) {
                    $redirect_url = $_SESSION['redirect_after_login'];
                    unset($_SESSION['redirect_after_login']);
                    redirect($redirect_url);
                }

                if ($result['role'] === 'admin') {
                    redirect('admin/dashboard.php');
                } elseif ($result['role'] === 'creator') {
                    redirect('creator/dashboard.php');
                } else {
                    redirect('viewer/dashboard.php');
                }
            } else {
                $rateLimiter->recordAttempt($email);
                $error = $result['message'];
            }
        }
    }
}

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
    <link rel="stylesheet" href="<?= asset('css/auth.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-container login-container">
            <div class="auth-box">
                <div class="auth-logo">
                    <i class="fas fa-graduation-cap"></i>
                    <h1><?= SITE_NAME ?></h1>
                </div>

                <div class="auth-header">
                    <h2>Welcome Back!</h2>
                    <p>Login to continue your learning journey</p>
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
                        <label for="email">
                            <i class="fas fa-envelope"></i>
                            Email Address
                        </label>
                        <input type="email" id="email" name="email" class="form-control"
                               value="<?= e($email_value) ?>" placeholder="Enter your email"
                               required autofocus>
                        <span class="error-message" id="email-error"></span>
                    </div>

                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-lock"></i>
                            Password
                        </label>
                        <div class="password-input-wrapper">
                            <input type="password" id="password" name="password" class="form-control"
                                   placeholder="Enter your password" required>
                            <button type="button" class="toggle-password" onclick="togglePassword()">
                                <i class="fas fa-eye" id="password-eye"></i>
                            </button>
                        </div>
                        <span class="error-message" id="password-error"></span>
                    </div>

                    <div class="form-group form-options">
                        <label class="checkbox-label">
                            <input type="checkbox" id="remember" name="remember"
                                   <?= isset($_COOKIE['remember_user']) ? 'checked' : '' ?>>
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
                    <p>Don't have an account? <a href="register.php" class="link">Register here</a></p>
                </div>
            </div>

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

        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email    = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            let hasError   = false;

            document.getElementById('email-error').textContent    = '';
            document.getElementById('password-error').textContent = '';

            if (!email) {
                document.getElementById('email-error').textContent = 'Email is required';
                hasError = true;
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                document.getElementById('email-error').textContent = 'Invalid email format';
                hasError = true;
            }

            if (!password) {
                document.getElementById('password-error').textContent = 'Password is required';
                hasError = true;
            }

            if (hasError) e.preventDefault();
        });
    </script>
</body>
</html>
