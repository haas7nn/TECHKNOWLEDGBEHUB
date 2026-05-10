/**
 * Form Validation JavaScript
 * Making sure users fill out the forms correctly before they hit submit
 * Hasan Fardan - 202301686
 */

// ==================== REGISTER FORM VALIDATION ====================
function validateRegisterForm() {
    // wipe the slate clean and hide old error messages
    clearErrors();
    
    let isValid = true;
    
    // grabbing everything the user typed
    const fullName = document.getElementById('full_name').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const terms = document.getElementById('terms').checked;
    
    // check if the name is too short
    if (fullName.length < 3) {
        showError('name-error', 'Name must be at least 3 characters');
        isValid = false;
    }
    
    // basic check to see if the email looks real
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        showError('email-error', 'Please enter a valid email address');
        isValid = false;
    }
    
    // check password length and complexity
    if (password.length < 8) {
        showError('password-error', 'Password must be at least 8 characters');
        isValid = false;
    } else if (!validatePasswordStrength(password)) {
        showError('password-error', 'Password must contain uppercase, lowercase, and number');
        isValid = false;
    }
    
    // making sure they typed the same password twice
    if (password !== confirmPassword) {
        showError('confirm-error', 'Passwords do not match');
        isValid = false;
    }
    
    // they have to check the terms box to move forward
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
    
    // checking email field
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!email) {
        showError('email-error', 'Email is required');
        isValid = false;
    } else if (!emailRegex.test(email)) {
        showError('email-error', 'Please enter a valid email address');
        isValid = false;
    }
    
    // checking password field
    if (!password) {
        showError('password-error', 'Password is required');
        isValid = false;
    }
    
    return isValid;
}

// ==================== PASSWORD STRENGTH VALIDATOR ====================
function validatePasswordStrength(password) {
    // we want a mix of uppercase, lowercase, and numbers
    const hasUppercase = /[A-Z]/.test(password);
    const hasLowercase = /[a-z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    
    return hasUppercase && hasLowercase && hasNumber;
}

// ==================== HELPER FUNCTIONS ====================
// just a quick way to show an error message on the screen
function showError(elementId, message) {
    const errorElement = document.getElementById(elementId);
    if (errorElement) {
        errorElement.textContent = message;
        errorElement.style.display = 'block';
    }
}

// hides all the error messages so we can start fresh
function clearErrors() {
    const errorElements = document.querySelectorAll('.error-message');
    errorElements.forEach(element => {
        element.textContent = '';
        element.style.display = 'none';
    });
}

// ==================== REAL-TIME VALIDATION ====================
// we'll set this up once the page is fully loaded
document.addEventListener('DOMContentLoaded', function() {
    
    // check the email format as soon as they click away from the field
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
    
    // check if passwords match while they are typing it in
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
    
    // stop the forms from sending if the validation fails
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