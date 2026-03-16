// JavaScript for Sit-In Monitoring System
// Handles frontend logic and communicates with PHP backend

// Current user data
let currentUser = null;

// Show a specific section (home, about, community, user-*, admin-*)
function showSection(sectionId) {
    // Hide all sections - regular user sections
    const regularSections = ['home', 'about', 'community'];
    regularSections.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.style.display = 'none';
        }
    });
    
    // Hide all user sections
    const userSections = ['user-home', 'user-profile'];
    userSections.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.style.display = 'none';
        }
    });
    
    // Hide all admin sections
    const adminSections = ['admin-home', 'admin-search', 'admin-students', 'admin-sitin', 'admin-records', 'admin-reports', 'admin-feedback', 'admin-reservations'];
    adminSections.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.style.display = 'none';
        }
    });
    
    // Hide auth container
    const authContainer = document.getElementById('auth-container');
    if (authContainer) {
        authContainer.style.display = 'none';
    }
    
    // Show the selected section
    const selectedSection = document.getElementById(sectionId);
    if (selectedSection) {
        selectedSection.style.display = 'block';
    }
    
    // Load admin data if admin-home is shown
    if (sectionId === 'admin-home' && typeof loadAdminDashboard === 'function') {
        loadAdminDashboard();
    }
}

// Show a specific page (login, register)
function showPage(page) {
    // Hide all sections - regular user sections
    const regularSections = ['home', 'about', 'community'];
    regularSections.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.style.display = 'none';
        }
    });
    
    // Hide all user sections
    const userSections = ['user-home', 'user-profile'];
    userSections.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.style.display = 'none';
        }
    });
    
    // Show auth container
    const authContainer = document.getElementById('auth-container');
    if (authContainer) {
        authContainer.style.display = 'flex';
    }
    
    // Show the selected page
    const loginPage = document.getElementById('login-page');
    const registerPage = document.getElementById('register-page');
    
    if (loginPage && registerPage) {
        if (page === 'login') {
            loginPage.style.display = 'block';
            registerPage.style.display = 'none';
        } else if (page === 'register') {
            loginPage.style.display = 'none';
            registerPage.style.display = 'block';
        }
    }
}

// Validate and process login
async function validateLogin() {
    const idNumber = document.getElementById('login-id').value.trim();
    const password = document.getElementById('login-pass').value;
    const errorDiv = document.getElementById('login-error');
    
    // Clear previous errors
    if (errorDiv) {
        errorDiv.textContent = '';
        errorDiv.style.display = 'none';
    }
    
    // Basic validation
    if (!idNumber || !password) {
        if (errorDiv) {
            errorDiv.textContent = 'Please enter ID Number and Password';
            errorDiv.style.display = 'block';
        }
        return;
    }
    
    try {
        const response = await fetch('login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id_number: idNumber,
                password: password
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Login successful
            currentUser = data.user;
            
            // If admin, redirect to admin page
            if (data.is_admin) {
                window.location.href = 'admin/admin_home.php';
            } else {
                window.location.reload();
            }
        } else {
            if (errorDiv) {
                errorDiv.textContent = data.message;
                errorDiv.style.display = 'block';
            }
        }
    } catch (error) {
        if (errorDiv) {
            errorDiv.textContent = 'Connection error. Please try again.';
            errorDiv.style.display = 'block';
        }
        console.error('Login error:', error);
    }
}

// Validate and process registration
async function validateRegister() {
    const idNumber = document.getElementById('reg-id').value.trim();
    const lastname = document.getElementById('reg-lname').value.trim();
    const firstname = document.getElementById('reg-fname').value.trim();
    const middlename = document.getElementById('reg-mname').value.trim();
    const course = document.getElementById('reg-course').value;
    const level = document.getElementById('reg-level').value;
    const email = document.getElementById('reg-email').value.trim();
    const password = document.getElementById('reg-pass').value;
    const confirmPassword = document.getElementById('reg-confirm-pass').value;
    const address = document.getElementById('reg-address').value.trim();
    const errorDiv = document.getElementById('register-error');
    
    // Clear previous errors
    if (errorDiv) {
        errorDiv.textContent = '';
        errorDiv.style.display = 'none';
    }
    
    // Basic validation
    if (!idNumber || !lastname || !firstname || !course || !level || !email || !password) {
        if (errorDiv) {
            errorDiv.textContent = 'Please fill in all required fields';
            errorDiv.style.display = 'block';
        }
        return;
    }
    
    if (password !== confirmPassword) {
        if (errorDiv) {
            errorDiv.textContent = 'Passwords do not match';
            errorDiv.style.display = 'block';
        }
        return;
    }
    
    if (password.length < 6) {
        if (errorDiv) {
            errorDiv.textContent = 'Password must be at least 6 characters';
            errorDiv.style.display = 'block';
        }
        return;
    }
    
    try {
        const response = await fetch('register.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id_number: idNumber,
                lastname: lastname,
                firstname: firstname,
                middlename: middlename,
                course: course,
                level: parseInt(level),
                email: email,
                password: password,
                confirm_password: confirmPassword,
                address: address
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(data.message);
            // Clear form and switch to login
            clearRegisterForm();
            showPage('login');
        } else {
            if (errorDiv) {
                errorDiv.textContent = data.message;
                errorDiv.style.display = 'block';
            }
        }
    } catch (error) {
        if (errorDiv) {
            errorDiv.textContent = 'Connection error. Please try again.';
            errorDiv.style.display = 'block';
        }
        console.error('Registration error:', error);
    }
}

// Clear registration form
function clearRegisterForm() {
    document.getElementById('reg-id').value = '';
    document.getElementById('reg-lname').value = '';
    document.getElementById('reg-fname').value = '';
    document.getElementById('reg-mname').value = '';
    document.getElementById('reg-level').selectedIndex = 0;
    document.getElementById('reg-pass').value = '';
    document.getElementById('reg-confirm-pass').value = '';
    document.getElementById('reg-email').value = '';
    document.getElementById('reg-course').selectedIndex = 0;
    document.getElementById('reg-address').value = '';
    
    const errorDiv = document.getElementById('register-error');
    if (errorDiv) {
        errorDiv.textContent = '';
    }
}

// Update UI for logged in user
function updateUIForLoggedInUser() {
    const guestLinks = document.getElementById('guest-links');
    const userDropdown = document.getElementById('user-dropdown');
    const displayUsername = document.getElementById('display-username');
    
    if (guestLinks && userDropdown && displayUsername && currentUser) {
        guestLinks.style.display = 'none';
        userDropdown.style.display = 'block';
        displayUsername.textContent = currentUser.name + ' ▼';
    }
    
    // Show user home section by default
    showSection('user-home');
}

// Update UI for logged out user
function updateUIForGuestUser() {
    const guestLinks = document.getElementById('guest-links');
    const userDropdown = document.getElementById('user-dropdown');
    
    if (guestLinks && userDropdown) {
        guestLinks.style.display = 'block';
        userDropdown.style.display = 'none';
    }
}

// Show user profile (redirects to user-profile section)
async function showProfile() {
    if (!currentUser) {
        showPage('login');
        return;
    }
    
    showSection('user-profile');
}

// Populate profile data
function populateProfileData() {
    if (!currentUser) return;
    
    document.getElementById('prof-id').textContent = currentUser.id_number;
    document.getElementById('prof-name').textContent = currentUser.name;
    document.getElementById('prof-course-level').textContent = currentUser.course + ' - Level ' + currentUser.level;
    document.getElementById('prof-email').textContent = currentUser.email;
    document.getElementById('prof-address').textContent = currentUser.address || 'N/A';
}

// Logout function
async function logout() {
    try {
        const response = await fetch('logout.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentUser = null;
            
            // Reload the page to reset PHP session state and show guest view
            window.location.href = 'index.php';
        }
    } catch (error) {
        console.error('Logout error:', error);
    }
}

// Delete account function
async function deleteAccount() {
    if (!currentUser) {
        showPage('login');
        return;
    }
    
    if (confirm('Are you sure you want to delete your account? This action cannot be undone.')) {
        try {
            const response = await fetch('delete_account.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id_number: currentUser.id_number
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert('Account deleted successfully');
                logout();
            } else {
                alert('Failed to delete account: ' + data.message);
            }
        } catch (error) {
            console.error('Delete account error:', error);
            alert('Error deleting account');
        }
    }
}

// Handle Enter key for login form
document.addEventListener('DOMContentLoaded', function() {
    const loginPass = document.getElementById('login-pass');
    if (loginPass) {
        loginPass.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                validateLogin();
            }
        });
    }
    
    // Initialize UI state
    updateUIForGuestUser();
    showSection('home');
});
