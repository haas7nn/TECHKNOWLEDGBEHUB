<?php
// reset password page

require_once '../config/config.php';
require_once '../classes/User.php';

// redirect if already logged in
if (isLoggedIn()) {
    redirect('public/index.php');
}

// init page vars
$page_title = 'Reset Password';
$error      = '';
$success    = '';

// validate token against session
$token       = clean($_GET['token'] ?? '');
$valid_token = false;

// hash_equals prevents timing attacks on token comparison
if (!empty($token)
    && isset($_SESSION['reset_token'], $_SESSION['reset_user_id'], $_SESSION['reset_expires_at'])
    && hash_equals($_SESSION['reset_token'], $token)
    && time() < (int)$_SESSION['reset_expires_at']
) {
    $valid_token = true;
}

// invalid token on GET — send back to request a new link
if (!$valid_token && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('This password-reset link is invalid or has expired. Please request a new one.', 'warning');
    redirect('auth/forgot-password.php');
}

// handle POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // recheck token on POST too
    if (!$valid_token) {
        $error = 'Invalid or expired reset link. Please request a new password reset.';
    } elseif (!verifyCsrfFromPost()) {
        // csrf check
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        // get password fields
        $new_password     = $_POST['new_password']     ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($new_password) || empty($confirm_password)) {
            $error = 'Both password fields are required.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            $pw_check = validatePassword($new_password);
            if (!$pw_check['valid']) {
                $error = $pw_check['message'];
            } else {
                $uid  = (int)$_SESSION['reset_user_id'];
                $hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);

                $db   = new Database();
                $conn = $db->connect();
                if (!$conn) {
                    $error = 'Database connection failed. Please try again.';
                } else {
                    try {
                        $stmt = $conn->prepare(
                            "UPDATE dbProj_users SET password_hash = :pw WHERE user_id = :uid"
                        );
                        $stmt->bindParam(':pw',  $hash, PDO::PARAM_STR);
                        $stmt->bindParam(':uid', $uid,  PDO::PARAM_INT);
                        $stmt->execute();

                        // invalidate token so it can't be reused
                        unset($_SESSION['reset_token'], $_SESSION['reset_user_id'], $_SESSION['reset_expires_at']);

                        $success = 'Your password has been reset successfully. You can now log in.';
                    } catch (PDOException $e) {
                        $error = 'Failed to update password. Please try again.';
                    }
                }
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
    <title><?= $page_title ?> | <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= asset('css/auth.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="auth-wrapper">
    <!-- auth-side must be sibling of auth-container not nested inside it -->
    <div class="auth-side">
        <div class="auth-side-content">
            <div class="auth-side-brand">
                <i class="fas fa-graduation-cap"></i>
                <span><?= SITE_NAME ?></span>
            </div>
            <h2>Create a Strong Password</h2>
            <p>Your account security matters. Choose a password you haven't used before.</p>
            <ul class="features">
                <li class="feature-item"><i class="fas fa-check-circle"></i><span>At least 8 characters</span></li>
                <li class="feature-item"><i class="fas fa-check-circle"></i><span>One uppercase letter</span></li>
                <li class="feature-item"><i class="fas fa-check-circle"></i><span>One number</span></li>
                <li class="feature-item"><i class="fas fa-check-circle"></i><span>Avoid common words</span></li>
            </ul>
        </div>
    </div>
    <div class="auth-container login-container">
        <div class="auth-box">

            <div class="auth-logo">
                <i class="fas fa-graduation-cap"></i>
                <h1><?= SITE_NAME ?></h1>
            </div>

            <div class="auth-header">
                <h2>Reset Password</h2>
                <p>Enter and confirm your new password below</p>
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
                <div style="text-align:center;">
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Go to Login
                    </a>
                </div>
            <?php else: ?>

            <form method="POST" action="?token=<?= urlencode($token) ?>" class="auth-form" id="resetForm" novalidate>
                <?php csrfField(); ?>

                <div class="form-group">
                    <label for="new_password">
                        <i class="fas fa-lock"></i>
                        New Password
                    </label>
                    <div class="password-input-wrapper">
                        <input type="password" id="new_password" name="new_password" class="form-control"
                               placeholder="Min 8 chars, uppercase, number" required>
                        <button type="button" class="toggle-password" onclick="togglePw('new_password','eye1')">
                            <i class="fas fa-eye" id="eye1"></i>
                        </button>
                    </div>
                    <span class="error-message" id="pw-error"></span>
                </div>

                <div class="form-group">
                    <label for="confirm_password">
                        <i class="fas fa-lock"></i>
                        Confirm New Password
                    </label>
                    <div class="password-input-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                               placeholder="Repeat new password" required>
                        <button type="button" class="toggle-password" onclick="togglePw('confirm_password','eye2')">
                            <i class="fas fa-eye" id="eye2"></i>
                        </button>
                    </div>
                    <span class="error-message" id="confirm-error"></span>
                </div>

                <!-- strength indicator -->
                <div style="margin-bottom:18px;">
                    <div class="strength-bar">
                        <div class="strength-bar-fill" id="strength-bar"></div>
                    </div>
                    <small id="strength-text" class="strength-text"></small>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-key"></i>
                    Reset Password
                </button>
            </form>

            <div class="auth-footer" style="margin-top:20px; text-align:center;">
                <p><a href="login.php" class="link"><i class="fas fa-arrow-left"></i> Back to Login</a></p>
            </div>

            <?php endif; ?>

        </div><!-- /auth-box -->
    </div><!-- /auth-container -->
</div><!-- /auth-wrapper -->

<script>
// toggle password visibility
function togglePw(fieldId, iconId) {
    const inp  = document.getElementById(fieldId);
    const icon = document.getElementById(iconId);
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        inp.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

// live strength bar
const pwInput   = document.getElementById('new_password');
const bar       = document.getElementById('strength-bar');
const strengthTxt = document.getElementById('strength-text');
if (pwInput) {
    pwInput.addEventListener('input', function() {
        const val = this.value;
        // one point per requirement met
        let score = 0;
        if (val.length >= 8)            score++;
        if (/[A-Z]/.test(val))          score++;
        if (/[0-9]/.test(val))          score++;
        if (/[^A-Za-z0-9]/.test(val))   score++;
        // map score to color and label
        const colors = ['#e74c3c','#f39c12','#f1c40f','#27ae60'];
        const labels = ['Weak','Fair','Good','Strong'];
        bar.style.width  = (score * 25) + '%';
        bar.style.background = colors[score - 1] || '#f0f0f0';
        strengthTxt.textContent = score > 0 ? 'Strength: ' + (labels[score - 1] || '') : '';
    });
}

// client-side validation before submit
document.getElementById('resetForm') && document.getElementById('resetForm').addEventListener('submit', function(e) {
    const pw   = document.getElementById('new_password').value;
    const conf = document.getElementById('confirm_password').value;
    const pwErr   = document.getElementById('pw-error');
    const confErr = document.getElementById('confirm-error');
    let ok = true;

    // reset error messages
    pwErr.textContent = '';
    confErr.textContent = '';

    // check minimum password rules
    if (pw.length < 8) {
        pwErr.textContent = 'Password must be at least 8 characters.';
        ok = false;
    } else if (!/[A-Z]/.test(pw)) {
        pwErr.textContent = 'Password must contain at least one uppercase letter.';
        ok = false;
    } else if (!/[0-9]/.test(pw)) {
        pwErr.textContent = 'Password must contain at least one number.';
        ok = false;
    }

    // both fields must match
    if (pw !== conf) {
        confErr.textContent = 'Passwords do not match.';
        ok = false;
    }

    // block submit if validation failed
    if (!ok) e.preventDefault();
});
</script>
</body>
</html>
