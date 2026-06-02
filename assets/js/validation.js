// form validation

// ==================== REGISTER FORM VALIDATION ====================
function validateRegisterForm() {
    clearErrors();

    let isValid = true;

    // collect form values
    const fullName = document.getElementById('full_name').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const terms = document.getElementById('terms').checked;

    // min length check
    if (fullName.length < 3) {
        showError('name-error', 'Name must be at least 3 characters');
        isValid = false;
    }

    // validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        showError('email-error', 'Please enter a valid email address');
        isValid = false;
    }

    // password length and strength
    if (password.length < 8) {
        showError('password-error', 'Password must be at least 8 characters');
        isValid = false;
    } else if (!validatePasswordStrength(password)) {
        showError('password-error', 'Password must contain uppercase, lowercase, and number');
        isValid = false;
    }

    // confirm passwords match
    if (password !== confirmPassword) {
        showError('confirm-error', 'Passwords do not match');
        isValid = false;
    }

    // terms must be accepted
    if (!terms) {
        showError('terms-error', 'You must accept the terms and conditions');
        isValid = false;
    }

    return isValid;
}

// ==================== LOGIN FORM VALIDATION ====================
function validateLoginForm() {
    clearErrors();

    let isValid = true;

    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;

    // validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!email) {
        showError('email-error', 'Email is required');
        isValid = false;
    } else if (!emailRegex.test(email)) {
        showError('email-error', 'Please enter a valid email address');
        isValid = false;
    }

    // password required
    if (!password) {
        showError('password-error', 'Password is required');
        isValid = false;
    }

    return isValid;
}

// ==================== PASSWORD STRENGTH VALIDATOR ====================
function validatePasswordStrength(password) {
    // requires uppercase lowercase and number
    const hasUppercase = /[A-Z]/.test(password);
    const hasLowercase = /[a-z]/.test(password);
    const hasNumber = /[0-9]/.test(password);

    return hasUppercase && hasLowercase && hasNumber;
}

// ==================== HELPER FUNCTIONS ====================
// show an error message element
function showError(elementId, message) {
    const errorElement = document.getElementById(elementId);
    if (errorElement) {
        errorElement.textContent = message;
        errorElement.style.display = 'block';
    }
}

// clear all error messages
function clearErrors() {
    const errorElements = document.querySelectorAll('.error-message');
    errorElements.forEach(element => {
        element.textContent = '';
        element.style.display = 'none';
    });
}

// ==================== REAL-TIME VALIDATION ====================
document.addEventListener('DOMContentLoaded', function() {

    // blur check on email field
    const emailField = document.getElementById('email');
    if (emailField) {
        emailField.addEventListener('blur', function() {
            const email = this.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (email && !emailRegex.test(email)) {
                showError('email-error', 'Please enter a valid email address');
            } else {
                document.getElementById('email-error').textContent = '';
            }
        });
    }

    // live match check on confirm password
    const confirmPasswordField = document.getElementById('confirm_password');
    if (confirmPasswordField) {
        confirmPasswordField.addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;

            if (confirmPassword && password !== confirmPassword) {
                showError('confirm-error', 'Passwords do not match');
            } else {
                document.getElementById('confirm-error').textContent = '';
            }
        });
    }

    // block submit if validation fails
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            if (!validateRegisterForm()) {
                e.preventDefault();
            }
        });
    }

    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            if (!validateLoginForm()) {
                e.preventDefault();
            }
        });
    }
});