<?php
/**
 * User Login Page
 * Handling logins and making sure everyone goes to the right dashboard
 * Hasan Fardan - 202301686
 */

require_once '../config/config.php';
require_once '../classes/User.php';

// if they are already logged in just send them where they need to be
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
$email_value = '';

// handling the form when they hit submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    $email_value = $email;
    
    // making sure they actually typed something in
    if (empty($email) || empty($password)) {
        $error = 'Email and password are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        // checking the credentials against the User class
        $user = new User();
        $result = $user->login($email, $password);
        
        if ($result['success']) {
            // save their email for next time if they checked the box
            if ($remember) {
                setcookie('remember_user', $email, time() + (86400 * 30), '/'); // lasts for 30 days
            }
            
            // if they were trying to visit a specific page before logging in send them back there
            if (isset($_SESSION['redirect_after_login'])) {
                $redirect_url = $_SESSION['redirect_after_login'];
                unset($_SESSION['redirect_after_login']);
                redirect($redirect_url);
            }
            
            // final check to send them to the right dashboard based on their role
            if ($result['role'] === 'admin') {
                redirect('admin/dashboard.php');
            } elseif ($result['role'] === 'creator') {
                redirect('creator/dashboard.php');
            } else {
                redirect('public/index.php');
            }
        } else {
            // error from the User::login method (e.g., "Invalid credentials")
            $error = $result['message'];
        }
    }
}

// pull the saved email from the cookie so they dont have to retype it
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
                <!-- branding stuff -->
                <div class="auth-logo">
                    <i class="fas fa-graduation-cap"></i>
                    <h1><?= SITE_NAME ?></h1>
                </div>
                
                <!-- welcome text -->
                <div class="auth-header">
                    <h2>Welcome Back  !</h2>
                    <p>Login to continue your learning journey</p>
                </div>
                
                <!-- showing errors if something went wrong -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?= e($error) ?></span>
                    </div>
                <?php endif; ?>
                
                <!-- letting them know if they got logged out for being idle -->
                <?php if (isset($_GET['error']) && $_GET['error'] === 'session_timeout'): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-clock"></i>
                        <span>Your session has expired, Please login again.</span>
                    </div>
                <?php endif; ?>
                
                <!-- main login form starts here -->
                <form method="POST" action="" id="loginForm" class="auth-form" novalidate>
                    
                    <!-- email field -->
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
                            value="<?= e($email_value) ?>"
                            placeholder="Enter your email"
                            required
                            autofocus
                        >
                        <span class="error-message" id="email-error"></span>
                    </div>
                    
                    <!-- password field -->
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
                                placeholder="Enter your password"
                                required
                            >
                            <button type="button" class="toggle-password" onclick="togglePassword()">
                                <i class="fas fa-eye" id="password-eye"></i>
                            </button>
                        </div>
                        <span class="error-message" id="password-error"></span>
                    </div>
                    
                    <!-- extra options for remembering user and reset -->
                    <div class="form-group form-options">
                        <label class="checkbox-label">
                            <input type="checkbox" id="remember" name="remember" <?= isset($_COOKIE['remember_user']) ? 'checked' : '' ?>>
                            <span>Remember me</span>
                        </label>
                        <a href="forgot-password.php" class="link forgot-link">Forgot Password?</a>
                    </div>
                    
                    <!-- the big login button -->
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-sign-in-alt"></i>
                        Login
                    </button>
                </form>
                
                <!-- divider between login and registration -->
                <div class="auth-divider">
                    <span>or</span>
                </div>
                
                <!-- just for testing purposes delete this section before submitting --> 
                <div class="demo-accounts">
                    <p><strong>Demo Accounts:</strong></p>
                    <small>
                        Admin: admin@techknow.com<br>
                        Instructor: sarah.j@email.com<br>
                        Student: david.w@email.com<br>
                        Password: Password123!
                    </small>
                </div>
                
                <!-- redirect to register page -->
                <div class="auth-footer">
                    <p>You still don't have an account ??? <a href="register.php" class="link">Register here</a></p>
                </div>
            </div>
            
            <!-- side panel with some motivation and numbers -->
            <div class="auth-side">
                <div class="auth-side-content">
                    <h2>Continue Learning</h2>
                    <p>Welcome back  ! Access your tutorials, track your progress, and continue your educational journey</p>
                    
                    <div class="stats">
                        <div class="stat-item">
                            <h3>1000+</h3>
                            <p>Tutorials</p>
                        </div>
                        <div class="stat-item">
                            <h3>500+</h3>
                            <p>Instructors</p>
                        </div>
                        <div class="stat-item">
                            <h3>10K+</h3>
                            <p>Students</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // swapping between showing and hiding the password
        function togglePassword() {
            const field = document.getElementById('password');
            const eye = document.getElementById('password-eye');
            
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
        
        // checking the form on the browser side before sending it to the server
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            
            let hasError = false;
            
            // wipe out any old error messages
            document.querySelectorAll('.error-message').forEach(el => el.textContent = '');
            
            // make sure email isn't empty or weirdly formatted
            if (!email) {
                document.getElementById('email-error').textContent = 'Email is required';
                hasError = true;
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                document.getElementById('email-error').textContent = 'Invalid email format';
                hasError = true;
            }
            
            // make sure they put something in the password box
            if (!password) {
                document.getElementById('password-error').textContent = 'Password is required';
                hasError = true;
            }
            
            // stop the form from sending if there is an error
            if (hasError) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>