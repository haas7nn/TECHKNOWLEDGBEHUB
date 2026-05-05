<?php
// signup page where new users create their account

require_once '../config/config.php';
require_once '../classes/User.php';

// if they are already logged in just send them to their dashboard
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

// default values for the error message and form fields
$error = '';
$form_data = [
    'full_name' => '',
    'email' => '',
    'role' => 'viewer'
];

// handle the logic when they hit create account
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // csrf check
    if (!verifyCsrfFromPost()) {
        $error = 'Invalid security token. Please try again.';
        // repopulate form data so the fields do not go blank after the error
        $form_data = [
            'full_name' => clean($_POST['full_name'] ?? ''),
            'email'     => clean($_POST['email']     ?? ''),
            'role'      => clean($_POST['role']      ?? 'viewer'),
        ];
    } else {
        // clean all the submitted inputs
        $full_name = clean($_POST['full_name'] ?? '');
        $email = clean($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = clean($_POST['role'] ?? '');

        // remember what they typed so the form keeps their values if there is an error
        $form_data = [
            'full_name' => $full_name,
            'email' => $email,
            'role' => $role
        ];

        // run all server side validation checks in order
        // check the terms checkbox first since html required can be bypassed
        if (!isset($_POST['terms'])) {
            $error = 'You must agree to the Terms & Conditions to register.';
        } elseif (empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
            // make sure every field has something in it
            $error = 'All fields are required';
        } elseif (strlen($full_name) < 3) {
            // names shorter than 3 characters are not valid
            $error = 'Name must be at least 3 characters';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // check the email format
            $error = 'Please enter a valid email address';
        } elseif (strlen($password) < 8) {
            // password must be at least 8 characters long
            $error = 'Password must be at least 8 characters';
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
            // password needs uppercase lowercase and a number
            $error = 'Password must contain at least one uppercase letter, one lowercase letter, and one number';
        } elseif ($password !== $confirm_password) {
            // both password fields must match
            $error = 'Passwords do not match';
        } elseif (!in_array($role, ['viewer', 'creator'])) {
            // only allow the two valid role options
            $error = 'Invalid role selected';
        } else {
            // everything looks good so try to save them to the database
            $user = new User();
            $result = $user->register($full_name, $email, $password, $role);

            if ($result['success']) {
                // redirect right away after success to prevent double submission on refresh
                setFlashMessage('Account created successfully! Please login.', 'success');
                redirect('auth/login.php');
            } else {
                // something went wrong like email already taken
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
    <title>Register | <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= asset('css/auth.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            <h1>Start Your Learning Journey</h1>
            <p>Join a community of learners and instructors. Master new skills at your own pace, anytime, anywhere.</p>
            <ul class="features">
                <li class="feature-item"><i class="fas fa-book-open"></i><span>Access curated technology tutorials</span></li>
                <li class="feature-item"><i class="fas fa-clock"></i><span>Learn at your own pace</span></li>
                <li class="feature-item"><i class="fas fa-chalkboard-teacher"></i><span>Create and share your knowledge</span></li>
                <li class="feature-item"><i class="fas fa-certificate"></i><span>Track completed tutorials</span></li>
            </ul>
        </div>
    </div>

    <!-- Right form panel -->
    <div class="auth-container">
        <div class="auth-box">

            <a href="<?= SITE_URL ?>/public/search.php" class="auth-logo">
                <i class="fas fa-graduation-cap"></i>
                <span><?= SITE_NAME ?></span>
            </a>

            <div class="auth-header">
                <h2>Create your account</h2>
                <p>Fill in your details to get started</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- actual registration form starts here -->
            <form method="POST" action="" id="registerForm" class="auth-form" novalidate>
                <?php csrfField(); ?>

                <!-- name input -->
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        class="form-control"
                        value="<?= e($form_data['full_name']) ?>"
                        placeholder="Enter your full name"
                        required
                        autofocus
                    >
                    <span class="error-message" id="name-error"></span>
                </div>

                <!-- email input -->
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        value="<?= e($form_data['email']) ?>"
                        placeholder="Enter your email"
                        required
                    >
                    <span class="error-message" id="email-error"></span>
                </div>

                <!-- password field with the strength meter below it -->
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-input-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            placeholder="Create a strong password"
                            required
                        >
                        <button type="button" class="toggle-password" onclick="togglePassword('password')">
                            <i class="fas fa-eye" id="password-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength" id="password-strength">
                        <div class="strength-bar">
                            <div class="strength-bar-fill" id="strength-bar"></div>
                        </div>
                        <span class="strength-text" id="strength-text"></span>
                    </div>
                    <span class="error-message" id="password-error"></span>
                    <small class="form-hint">
                        Must be at least 8 characters with uppercase, lowercase, and number
                    </small>
                </div>

                <!-- confirm password so they do not mistype it -->
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="password-input-wrapper">
                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            class="form-control"
                            placeholder="Re-enter your password"
                            required
                        >
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                            <i class="fas fa-eye" id="confirm_password-eye"></i>
                        </button>
                    </div>
                    <span class="error-message" id="confirm-error"></span>
                </div>

                <!-- let them pick if they want to learn or teach -->
                <div class="form-group">
                    <label for="role">I want to</label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="viewer" <?= $form_data['role'] === 'viewer' ? 'selected' : '' ?>>
                            Learn (Student)
                        </option>
                        <option value="creator" <?= $form_data['role'] === 'creator' ? 'selected' : '' ?>>
                            Teach (Instructor)
                        </option>
                    </select>
                    <small class="form-hint">
                        You can change this later in your profile settings
                    </small>
                </div>

                <!-- they must agree to the terms before they can register -->
                <div class="form-group checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="terms" name="terms" required>
                        <span>I agree to the <a href="#" class="link">Terms & Conditions</a> and <a href="#" class="link">Privacy Policy</a></span>
                    </label>
                    <span class="error-message" id="terms-error"></span>
                </div>

                <!-- submit button to create the account -->
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-user-plus"></i>
                    Create Account
                </button>
            </form>

            <div class="auth-divider">
                <span>or</span>
            </div>

            <div class="auth-footer">
                <p>Already have an account? <a href="login.php" class="link">Login here</a></p>
            </div>

        </div>
    </div>

</div>

<script src="<?= asset('js/validation.js') ?>"></script>
<script>
    // update the strength bar as the user types their password
    document.getElementById('password').addEventListener('input', function() {
        const password = this.value;
        const strengthBar = document.getElementById('strength-bar');
        const strengthText = document.getElementById('strength-text');

        // start at zero and add a point for each requirement met
        let strength = 0;
        let text = '';
        let color = '';

        // these checks match what the server validates so they stay consistent
        if (password.length >= 8) strength++;
        if (password.match(/[a-z]/)) strength++;
        if (password.match(/[A-Z]/)) strength++;
        if (password.match(/[0-9]/)) strength++;
        // special characters earn a bonus point but are not required
        if (password.match(/[^a-zA-Z0-9]/)) strength++;

        // pick the label and color based on the final score
        switch(strength) {
            case 0:
            case 1:
                text = 'Weak';
                color = '#e74c3c';
                break;
            case 2:
            case 3:
                text = 'Medium';
                color = '#f39c12';
                break;
            case 4:
                text = 'Strong';
                color = '#27ae60';
                break;
            case 5:
                text = 'Very Strong';
                color = '#2ecc71';
                break;
        }

        // update the bar width and the text label
        strengthBar.style.width = (strength * 20) + '%';
        strengthBar.style.backgroundColor = color;
        strengthText.textContent = text;
        strengthText.style.color = color;
    });

    // show or hide the password when they click the eye icon
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        const eye = document.getElementById(fieldId + '-eye');

        if (field.type === 'password') {
            field.type = 'text';
            eye.classList.remove('fa-eye');
            eye.classList.add('fa-eye-slash');
        } else {
            field.type = 'password';
            eye.classList.remove('fa-eye-slash');
            eye.classList.add('fa-eye');
        }
    }
</script>

</body>
</html>
