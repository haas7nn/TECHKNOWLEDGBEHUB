<?php
/**
 * signup page 
 * where new people can join the squad
 * Hasan Fardan - 202301686
 */

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
        redirect('public/index.php');
    }
}

$error = '';
$success = '';
$form_data = [
    'full_name' => '',
    'email' => '',
    'role' => 'viewer'
];

// handle the logic when they hit create account
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // clean the inputs so we stay safe
    $full_name = clean($_POST['full_name']);
    $email = clean($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = clean($_POST['role']);
    
    // save what they typed so they dont have to redo everything if there is an error
    $form_data = [
        'full_name' => $full_name,
        'email' => $email,
        'role' => $role
    ];
    
    // check all the fields on the server side
    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'All fields are required';
    } elseif (strlen($full_name) < 3) {
        $error = 'Name must be at least 3 characters';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
        $error = 'Password must contain at least one uppercase letter, one lowercase letter, and one number';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (!in_array($role, ['viewer', 'creator'])) {
        $error = 'Invalid role selected';
    } else {
        // try to save the user to the database
        $user = new User();
        $result = $user->register($full_name, $email, $password, $role);
        
        if ($result['success']) {
            $success = $result['message'];
            // clear the form since we are good to go
            $form_data = ['full_name' => '', 'email' => '', 'role' => 'viewer'];
            // let them see the success message for 2 seconds then move to login
            header("refresh:2;url=login.php");
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= asset('css/auth.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-container">
            <div class="auth-box">
                <!-- branding part -->
                <div class="auth-logo">
                    <i class="fas fa-graduation-cap"></i>
                    <h1><?= SITE_NAME ?></h1>
                </div>
                
                <!-- welcome message -->
                <div class="auth-header">
                    <h2>Create Your Account</h2>
                    <p>Join our learning community today!</p>
                </div>
                
                <!-- show the error or success alerts here -->
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
                        <p style="margin-top: 10px; font-size: 14px;">Stay still , We're redirecting you to login page </p>
                    </div>
                <?php endif; ?>
                
                <!-- actual registration form starts here -->
                <form method="POST" action="" id="registerForm" class="auth-form" novalidate>
                    
                    <!-- name input -->
                    <div class="form-group">
                        <label for="full_name">
                            <i class="fas fa-user"></i>
                            Full Name
                        </label>
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
                        <label for="email">
                            <i class="fas fa-envelope"></i>
                            Email Address
                        </label>
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
                    
                    <!-- password and that strength meter -->
                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-lock"></i>
                            Password
                        </label>
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
                            OOPS , Must be at least 8 characters with uppercase, lowercase, and number
                        </small>
                    </div>
                    
                    <!-- make sure they typed the same password -->
                    <div class="form-group">
                        <label for="confirm_password">
                            <i class="fas fa-lock"></i>
                            Confirm Password
                        </label>
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
                    
                    <!-- pick a role -->
                    <div class="form-group">
                        <label for="role">
                            <i class="fas fa-user-tag"></i>
                            I want to
                        </label>
                        <select id="role" name="role" class="form-control" required>
                            <option value="viewer" <?= $form_data['role'] === 'viewer' ? 'selected' : '' ?>>
                                Learn (Student)
                            </option>
                            <option value="creator" <?= $form_data['role'] === 'creator' ? 'selected' : '' ?>>
                                Teach (Instructor)
                            </option>
                        </select>
                        <small class="form-hint">
                            DW , You can change this later in your profile
                        </small>
                    </div>
                    
                    <!-- agreement part -->
                    <div class="form-group checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="terms" required>
                            <span>I agree to the <a href="#" class="link">Terms & Conditions</a> and <a href="#" class="link">Privacy Policy</a></span>
                        </label>
                        <span class="error-message" id="terms-error"></span>
                    </div>
                    
                    <!-- the submit button -->
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-user-plus"></i>
                        Create Account
                    </button>
                </form>
                
                <!-- divider -->
                <div class="auth-divider">
                    <span>or</span>
                </div>
                
                <!-- link back to login page -->
                <div class="auth-footer">
                    <p>Already have an account? <a href="login.php" class="link">Login here</a></p>
                </div>
            </div>
            
            <!-- the side panel with all the cool features -->
            <div class="auth-side">
                <div class="auth-side-content">
                    <h2>Join TechKnowledge Hub</h2>
                    <p>Learn from expert instructors, master new skills, and advance your career , Its a guranteed A+</p>
                    
                    <div class="features">
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Access to 1000+ tutorials</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Learn at your own pace</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Expert instructors</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Community support</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="<?= asset('js/validation.js') ?>"></script>
    <script>
        // logic for that cool password strength meter
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strength-bar');
            const strengthText = document.getElementById('strength-text');
            
            let strength = 0;
            let text = '';
            let color = '';
            
            // check for symbols and numbers to see how strong it is
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            // choose the color and label based on the strength score
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
            
            strengthBar.style.width = (strength * 20) + '%';
            strengthBar.style.backgroundColor = color;
            strengthText.textContent = text;
            strengthText.style.color = color;
        });
        
        // show or hide the password when they click the eye
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