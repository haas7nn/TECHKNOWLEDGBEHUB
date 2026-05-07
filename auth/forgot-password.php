<?php
// forgot password page where users request a reset link by email

require_once '../config/config.php';
require_once '../classes/User.php';

// if they are already logged in just send them to the home page
if (isLoggedIn()) {
    redirect('public/index.php');
}

// set up page title and default message variables
$page_title = 'Forgot Password';
$error      = '';
$success    = '';

// handle the form when they click send reset instructions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // csrf check
    if (!verifyCsrfFromPost()) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        // grab and clean the email they typed
        $email = clean($_POST['email'] ?? '');

        // make sure the email is not empty and looks like a real address
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // look up the user by their email address
            $userObj = new User();
            $user    = $userObj->getUserByEmail($email);

            // we always show the same success message whether the email exists or not
            // this stops anyone from finding out which emails are registered
            if ($user && $user['status'] === 'active') {
                // make token
                // demo session token
                $token = bin2hex(random_bytes(32));
                $_SESSION['reset_token']      = $token;
                $_SESSION['reset_user_id']    = $user['user_id'];
                $_SESSION['reset_expires_at'] = time() + 900; // 15 minutes
            }

            // generic success
            $success = 'If your email is registered, you will receive a password reset link.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> | <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= asset('css/auth.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="auth-wrapper">

    <div class="auth-side">
        <div class="auth-side-content">
            <i class="fas fa-lock" style="font-size:60px; color:rgba(255,255,255,.3); margin-bottom:20px;"></i>
            <h2>Account Security</h2>
            <p>We take your security seriously. Password reset links expire after 15 minutes.</p>
            <ul style="text-align:left; margin-top:20px; list-style:none; padding:0;">
                <li style="padding:8px 0; color:rgba(255,255,255,.85);">
                    <i class="fas fa-check-circle" style="margin-right:8px;"></i> Check your inbox
                </li>
                <li style="padding:8px 0; color:rgba(255,255,255,.85);">
                    <i class="fas fa-check-circle" style="margin-right:8px;"></i> Link expires in 15 minutes
                </li>
                <li style="padding:8px 0; color:rgba(255,255,255,.85);">
                    <i class="fas fa-check-circle" style="margin-right:8px;"></i> Contact admin if no email arrives
                </li>
            </ul>
        </div>
    </div>

    <div class="auth-container login-container">
        <div class="auth-box">

            <a href="../index.php" class="auth-logo">
                <i class="fas fa-graduation-cap"></i>
                <span><?= SITE_NAME ?></span>
            </a>

            <div class="auth-header">
                <h2>Forgot Your Password?</h2>
                <p>Enter your email and we'll help you reset it</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?= e($success) ?></span>
                </div>
                <div style="text-align:center; margin-top:10px;">
                    <a href="login.php" class="link">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                </div>
            <?php else: ?>

            <form method="POST" action="" class="auth-form" id="forgotForm" novalidate>
                <?php csrfField(); ?>

                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i>
                        Email Address
                    </label>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="Enter your registered email"
                           value="<?= isset($_POST['email']) ? e(clean($_POST['email'])) : '' ?>"
                           required autofocus>
                    <span class="error-message" id="email-error"></span>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-paper-plane"></i>
                    Send Reset Instructions
                </button>
            </form>

            <div class="auth-footer" style="margin-top:20px; text-align:center;">
                <p>Remembered your password? <a href="login.php" class="link">Login here</a></p>
                <p style="margin-top:8px;">Don't have an account? <a href="register.php" class="link">Register</a></p>
            </div>

            <?php endif; ?>

        </div><!-- /auth-box -->
    </div><!-- /auth-container -->

</div><!-- /auth-wrapper -->

<script>
// check the email field before the form is submitted
document.getElementById('forgotForm') && document.getElementById('forgotForm').addEventListener('submit', function(e) {
    const email = document.getElementById('email');
    const errEl = document.getElementById('email-error');
    // stop submission if the field is empty
    if (!email.value.trim()) {
        e.preventDefault();
        errEl.textContent = 'Email address is required.';
        errEl.style.display = 'block';
        email.classList.add('error');
        return;
    }
    // also check that it looks like a real email address
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email.value.trim())) {
        e.preventDefault();
        errEl.textContent = 'Please enter a valid email address.';
        errEl.style.display = 'block';
        email.classList.add('error');
    }
});
</script>
</body>
</html>
