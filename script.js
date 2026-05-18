// JavaScript for Sit-In Monitoring System
// Handles frontend logic and communicates with PHP backend

// Current user data
let currentUser = null;

// Show a specific section (home, about, community, user-*, admin-*)
function showSection(sectionId) {
    console.log('[Navigation] showSection called with:', sectionId);
    const regularSections = ['home', 'about', 'community'];
    
    // Check if showing one of the guest sections
    const isRegularSection = regularSections.includes(sectionId);
    
    // Hide all sections - regular user sections
    regularSections.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            // If navigating to a guest section, keep all of them displayed!
            element.style.display = isRegularSection ? 'block' : 'none';
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
    if (!isRegularSection) {
        const selectedSection = document.getElementById(sectionId);
        if (selectedSection) {
            selectedSection.style.display = 'block';
        }
    } else {
        // Scroll to the selected regular guest section smoothly
        const targetElement = document.getElementById(sectionId);
        if (targetElement) {
            setTimeout(() => {
                targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 50);
        }
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
        const response = await fetch('reg-log-prof/login.php', {
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
                // Reload page to show user view
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
        const response = await fetch('reg-log-prof/register.php', {
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
    
    // Populate sessions left if element exists
    const sessionsLeftEl = document.getElementById('prof-sessions-left');
    if (sessionsLeftEl && currentUser.sessions_left !== undefined) {
        sessionsLeftEl.textContent = currentUser.sessions_left;
    }
    
    // Also populate home remaining sessions if element exists
    const homeSessionsLeftEl = document.getElementById('home-remaining-sessions');
    if (homeSessionsLeftEl && currentUser.sessions_left !== undefined) {
        homeSessionsLeftEl.textContent = currentUser.sessions_left;
    }
}

// Logout function
function logout() {
    window.location.href = 'reg-log-prof/logout.php';
}

// Make logout available globally
window.logout = logout;

// Delete account function
async function deleteAccount() {
    if (!currentUser) {
        showPage('login');
        return;
    }
    
    if (confirm('Are you sure you want to delete your account? This action cannot be undone.')) {
        try {
            const response = await fetch('database/delete_account.php', {
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
    
    // Initialize AI Chatbot if logged in as regular student
    if (typeof currentUser !== 'undefined' && currentUser && !currentUser.is_admin) {
        initAIChatBot();
    }
});

// ==========================================================================
// CCS Dynamic AI Recommender Chatbot Functionality
// ==========================================================================
function initAIChatBot() {
    if (document.getElementById('ccs-ai-fab')) return;

    // Create FAB
    const fab = document.createElement('div');
    fab.id = 'ccs-ai-fab';
    fab.className = 'ccs-ai-fab';
    fab.innerHTML = '🤖';
    fab.title = 'Chat with CCS AI Recommender';
    document.body.appendChild(fab);

    // Create Chat Window
    const chatWindow = document.createElement('div');
    chatWindow.id = 'ccs-ai-chat-window';
    chatWindow.className = 'ccs-ai-chat-window';
    
    const userName = (currentUser && currentUser.firstname) ? currentUser.firstname : 'Student';
    chatWindow.innerHTML = `
        <div class="ccs-ai-chat-header">
            <div class="ccs-ai-chat-header-info">
                <div class="ccs-ai-chat-avatar">🤖</div>
                <div class="ccs-ai-chat-header-title">
                    <h4>CCS AI Recommender</h4>
                    <span>Active recommender</span>
                </div>
            </div>
            <button class="ccs-ai-chat-close-btn" id="ccs-ai-close-btn">&times;</button>
        </div>
        <div class="ccs-ai-chat-body" id="ccs-ai-chat-body">
            <div class="ccs-ai-msg bot">
                <div class="ccs-ai-msg-bubble">
                    Hello <strong>${userName}</strong>! I am the CCS AI Recommender. 🎓<br><br>
                    I can recommend the absolute best AI models or tools for any programming language you are using for your laboratory classes!<br><br>
                    What language or framework are you coding in today?
                </div>
                <div class="ccs-ai-msg-time">${getCurrentTimeStr()}</div>
            </div>
        </div>
        <div class="ccs-ai-quick-tags">
            <button class="ccs-ai-tag" data-lang="Python">Python 🐍</button>
            <button class="ccs-ai-tag" data-lang="Java">Java ☕</button>
            <button class="ccs-ai-tag" data-lang="JavaScript">JavaScript ⚡</button>
            <button class="ccs-ai-tag" data-lang="PHP">PHP 🐘</button>
            <button class="ccs-ai-tag" data-lang="C++">C++ 🛠️</button>
            <button class="ccs-ai-tag" data-lang="Wizard">AI Wizard 🔮</button>
        </div>
        <div class="ccs-ai-chat-footer">
            <input type="text" class="ccs-ai-chat-input" id="ccs-ai-chat-input" placeholder="Ask about Python, Java, PHP, best AI models..." autocomplete="off">
            <button class="ccs-ai-send-btn" id="ccs-ai-send-btn">✈</button>
        </div>
    `;
    document.body.appendChild(chatWindow);

    // Dynamic Time String Helper
    function getCurrentTimeStr() {
        const now = new Date();
        return now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    // Toggle Chat Window
    fab.addEventListener('click', () => {
        chatWindow.classList.toggle('open');
        if (chatWindow.classList.contains('open')) {
            document.getElementById('ccs-ai-chat-input').focus();
        }
    });

    document.getElementById('ccs-ai-close-btn').addEventListener('click', () => {
        chatWindow.classList.remove('open');
    });

    // Quick Tags click behavior
    document.body.addEventListener('click', (e) => {
        if (e.target && e.target.classList.contains('ccs-ai-tag') && e.target.hasAttribute('data-lang')) {
            const lang = e.target.getAttribute('data-lang');
            if (lang === 'Wizard') {
                startWizard();
            } else {
                handleUserQuery(`Best AI for ${lang}`);
            }
        }
    });

    // Send Button click behavior
    const sendBtn = document.getElementById('ccs-ai-send-btn');
    const chatInput = document.getElementById('ccs-ai-chat-input');

    sendBtn.addEventListener('click', () => {
        const query = chatInput.value.trim();
        if (query) {
            handleUserQuery(query);
            chatInput.value = '';
        }
    });

    chatInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            const query = chatInput.value.trim();
            if (query) {
                handleUserQuery(query);
                chatInput.value = '';
            }
        }
    });

    // Chatbot responses logic
    function handleUserQuery(query) {
        appendMessage(query, 'user');
        showTypingIndicator();

        // Determine correct relative path to PHP proxy based on route depth
        const isSubFolder = window.location.pathname.includes('/users/');
        const proxyUrl = isSubFolder ? '../partials/ai_proxy.php' : 'partials/ai_proxy.php';

        fetch(proxyUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ query: query })
        })
        .then(res => {
            if (!res.ok) throw new Error('Proxy connection error');
            return res.json();
        })
        .then(data => {
            removeTypingIndicator();
            if (data.success && !data.use_fallback) {
                // Display the real dynamic response from Gemini
                appendMessage(data.response, 'bot');
            } else {
                // Fall back to local database recommendation engine
                const response = getAIResponse(query);
                appendMessage(response, 'bot');
            }
        })
        .catch(err => {
            console.error('AI Proxy Error:', err);
            removeTypingIndicator();
            // Secure fallback to local engine if server is offline or errors
            const response = getAIResponse(query);
            appendMessage(response, 'bot');
        });
    }

    function appendMessage(content, sender) {
        const chatBody = document.getElementById('ccs-ai-chat-body');
        if (!chatBody) return;
        const msgDiv = document.createElement('div');
        msgDiv.className = `ccs-ai-msg ${sender}`;
        msgDiv.innerHTML = `
            <div class="ccs-ai-msg-bubble">${content}</div>
            <div class="ccs-ai-msg-time">${getCurrentTimeStr()}</div>
        `;
        chatBody.appendChild(msgDiv);
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    function showTypingIndicator() {
        const chatBody = document.getElementById('ccs-ai-chat-body');
        if (!chatBody) return;
        const indicator = document.createElement('div');
        indicator.id = 'ccs-ai-typing';
        indicator.className = 'ccs-ai-msg bot';
        indicator.innerHTML = `
            <div class="ccs-ai-msg-bubble">
                <div class="ccs-ai-typing-indicator">
                    <span class="ccs-ai-typing-dot"></span>
                    <span class="ccs-ai-typing-dot"></span>
                    <span class="ccs-ai-typing-dot"></span>
                </div>
            </div>
        `;
        chatBody.appendChild(indicator);
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    function removeTypingIndicator() {
        const indicator = document.getElementById('ccs-ai-typing');
        if (indicator) indicator.remove();
    }

    // Recommendation Engine matching logic
    function getAIResponse(query) {
        const q = query.toLowerCase();

        // 1. Python Recommender
        if (q.includes('python') || q.includes('django') || q.includes('flask') || q.includes('py')) {
            return `
                For <strong>Python</strong>, here are the top AI tools:
                <div class="ai-recommendation-card">
                    <div class="ai-recommendation-title">🤖 1. Gemini 1.5 Pro / Flash</div>
                    <div class="ai-recommendation-details">
                        Best for large Python codebases. Gemini's massive 2 million token window allows you to analyze full folders, datasets, or complex Django projects at once. Excellent for debugging stack traces.
                        <br><span class="ai-recommendation-badge badge-primary">Highly Recommended</span>
                    </div>
                </div>
                <div class="ai-recommendation-card">
                    <div class="ai-recommendation-title">💻 2. GitHub Copilot</div>
                    <div class="ai-recommendation-details">
                        Best for real-time autocompletion in VS Code. It writes boilerplate Python code and list comprehensions incredibly fast.
                        <br><span class="ai-recommendation-badge badge-success">Best Autocomplete</span>
                    </div>
                </div>
                <div class="ai-recommendation-card">
                    <div class="ai-recommendation-title">🔮 3. Claude 3.5 Sonnet</div>
                    <div class="ai-recommendation-details">
                        Best for complex algorithmic challenges and logical perfection. Excellent for data science scripts and NumPy code.
                    </div>
                </div>
                <div class="ai-recommendation-card" style="border-left-color: #28a745;">
                    <div class="ai-recommendation-title">🌐 Recommended Learning Sites</div>
                    <div class="ai-recommendation-details">
                        • <a href="https://realpython.com" target="_blank" style="color:#144d94; text-decoration:underline; font-weight:600;">Real Python</a> - Best practical tutorials & guides.<br>
                        • <a href="https://docs.python.org/3/tutorial/" target="_blank" style="color:#144d94; text-decoration:underline; font-weight:600;">Official Python Tutorial</a> - The official language walkthrough.<br>
                        • <a href="https://www.kaggle.com/learn/python" target="_blank" style="color:#144d94; text-decoration:underline; font-weight:600;">Kaggle Learn</a> - Excellent for hands-on scripting.
                    </div>
                </div>
            `;
        }

        // 2. Java Recommender
        if (q.includes('java') || q.includes('spring') || q.includes('servlet') || q.includes('jsp')) {
            return `
                For <strong>Java</strong>, here are the top AI tools:
                <div class="ai-recommendation-card">
                    <div class="ai-recommendation-title">🔮 1. Claude 3.5 Sonnet</div>
                    <div class="ai-recommendation-details">
                        Java is verbose, and Claude is phenomenal at generating structurally correct, complete Java code including boilerplate, Spring Boot configurations, and design patterns.
                        <br><span class="ai-recommendation-badge badge-primary">Highly Recommended</span>
                    </div>
                </div>
                <div class="ai-recommendation-card">
                    <div class="ai-recommendation-title">🤖 2. Gemini 1.5 Pro</div>
                    <div class="ai-recommendation-details">
                        Amazing for exploring large Java enterprise repositories. Drop your entire multi-package codebase into Gemini, and ask it to refactor or map class structures easily.
                        <br><span class="ai-recommendation-badge badge-success">Best for Repos</span>
                    </div>
                </div>
                <div class="ai-recommendation-card" style="border-left-color: #28a745;">
                    <div class="ai-recommendation-title">🌐 Recommended Learning Sites</div>
                    <div class="ai-recommendation-details">
                        • <a href="https://java-programming.mooc.fi" target="_blank" style="color:#144d94; text-decoration:underline; font-weight:600;">Univ. of Helsinki Java MOOC</a> - The gold standard free course.<br>
                        • <a href="https://www.baeldung.com" target="_blank" style="color:#144d94; text-decoration:underline; font-weight:600;">Baeldung</a> - Absolute best Spring & Java reference portal.<br>
                        • <a href="https://www.geeksforgeeks.org/java/" target="_blank" style="color:#144d94; text-decoration:underline; font-weight:600;">GeeksforGeeks Java</a> - Great for structures & algorithms.
                    </div>
                </div>
            `;
        }

        // 3. JavaScript / TS Recommender
        if (q.includes('javascript') || q.includes('typescript') || q.includes('react') || q.includes('node') || q.includes('next.js') || q.includes('js') || q.includes('ts')) {
            return `
                For <strong>JavaScript, TypeScript, and React</strong>:
                <div class="ai-recommendation-card">
                    <div class="ai-recommendation-title">🔮 1. Claude 3.5 Sonnet</div>
                    <div class="ai-recommendation-details">
                        Claude excels at rendering beautiful modern UI components, debugging complex asynchronous React states, and managing TypeScript typing configurations flawlessly.
                        <br><span class="ai-recommendation-badge badge-primary">Best for Frontend</span>
                    </div>
                </div>
                <div class="ai-recommendation-card">
                    <div class="ai-recommendation-title">🤖 2. Gemini 1.5 Flash</div>
                    <div class="ai-recommendation-details">
                        Extremely fast API integrations and standard Node.js server configurations.
                        <br><span class="ai-recommendation-badge badge-success">Super Fast</span>
                    </div>
                </div>
                <div class="ai-recommendation-card" style="border-left-color: #28a745;">
                    <div class="ai-recommendation-title">🌐 Recommended Learning Sites</div>
                    <div class="ai-recommendation-details">
                        • <a href="https://javascript.info" target="_blank" style="color:#144d94; text-decoration:underline; font-weight:600;">JavaScript.info</a> - Extremely thorough modern JS roadmap.<br>
                        • <a href="https://developer.mozilla.org" target="_blank" style="color:#144d94; text-decoration:underline; font-weight:600;">MDN Web Docs</a> - The ultimate industry-standard reference.<br>
                        • <a href="https://scrimba.com" target="_blank" style="color:#144d94; text-decoration:underline; font-weight:600;">Scrimba</a> - Interactive React/JS learning platform.
                    </div>
                </div>
            `;
        }

        // 4. PHP / SQL Recommender (Perfect for this project)
        if (q.includes('php') || q.includes('laravel') || q.includes('mysql') || q.includes('sql') || q.includes('database')) {
            return `
                For <strong>PHP and Databases (SQL/PDO)</strong> (just like this laboratory website!):
                <div class="ai-recommendation-card">
                    <div class="ai-recommendation-title">🤖 1. Gemini 1.5 Pro</div>
                    <div class="ai-recommendation-details">
                        Since PHP involves close database bindings, Gemini's massive context window is perfect. You can paste a full PHP script along with your MySQL schema structure (like your <code>db.php</code> file), and Gemini will write clean, secure PDO queries automatically!
                        <br><span class="ai-recommendation-badge badge-primary">Recommended for this project</span>
                    </div>
                </div>
                <div class="ai-recommendation-card">
                    <div class="ai-recommendation-title">🔮 2. Claude 3.5 Sonnet</div>
                    <div class="ai-recommendation-details">
                        Excellent for refactoring old procedural PHP to Modern Object-Oriented MVC designs or implementing complex Laravel controllers.
                    </div>
                </div>
                <div class="ai-recommendation-card" style="border-left-color: #28a745;">
                    <div class="ai-recommendation-title">🌐 Recommended Learning Sites</div>
                    <div class="ai-recommendation-details">
                        • <a href="https://laracasts.com" target="_blank" style="color:#144d94; text-decoration:underline; font-weight:600;">Laracasts</a> - The absolute gold standard for modern PHP & databases.<br>
                        • <a href="https://www.php.net/manual/en/" target="_blank" style="color:#144d94; text-decoration:underline; font-weight:600;">PHP.net Manual</a> - Official function and PDO reference guides.<br>
                        • <a href="https://www.w3schools.com/php/" target="_blank" style="color:#144d94; text-decoration:underline; font-weight:600;">W3Schools PHP</a> - Excellent for quick syntax references.
                    </div>
                </div>
            `;
        }

        // 5. C++ / C# / C
        if (q.includes('c++') || q.includes('c#') || q.includes('dotnet') || q.includes('unity') || q.includes('cpp')) {
            return `
                For <strong>C++, C#, and C</strong>:
                <div class="ai-recommendation-card">
                    <div class="ai-recommendation-title">🔮 1. Claude 3.5 Sonnet</div>
                    <div class="ai-recommendation-details">
                        Best for pointer handling, memory management in C/C++, and general logical structure which can cause compile-time bugs.
                        <br><span class="ai-recommendation-badge badge-primary">Highly Recommended</span>
                    </div>
                </div>
                <div class="ai-recommendation-card">
                    <div class="ai-recommendation-title">🤖 2. Gemini 1.5 Pro</div>
                    <div class="ai-recommendation-details">
                        Superb for explaining compiler errors, runtime exceptions, and debugging complex build structures (Makefiles/CMake).
                        <br><span class="ai-recommendation-badge badge-success">Best Debugger</span>
                    </div>
                </div>
                <div class="ai-recommendation-card" style="border-left-color: #28a745;">
                    <div class="ai-recommendation-title">🌐 Recommended Learning Sites</div>
                    <div class="ai-recommendation-details">
                        • <a href="https://www.learncpp.com" target="_blank" style="color:#144d94; text-decoration:underline; font-weight:600;">LearnCPP.com</a> - The absolute best C++ learning portal online.<br>
                        • <a href="https://cplusplus.com" target="_blank" style="color:#144d94; text-decoration:underline; font-weight:600;">Cplusplus.com</a> - Classic library and STL reference guide.<br>
                        • <a href="https://www.geeksforgeeks.org/cpp-programming-language/" target="_blank" style="color:#144d94; text-decoration:underline; font-weight:600;">GeeksforGeeks C++</a> - Great for core structures & algorithms.
                    </div>
                </div>
            `;
        }

        // 6. Generic Best AI Questions
        if (q.includes('best ai') || q.includes('which ai') || q.includes('recommend') || q.includes('difference') || q.includes('model') || q.includes('use')) {
            return `
                Here is a summary of the <strong>Top Coding AI Models</strong>:
                <div style="margin-top: 10px; font-size:12px;">
                    🪐 <strong>Gemini 1.5 Pro</strong>: Unrivaled for repository-level exploration due to its 2 million context window (paste full folders of PHP and databases).
                    <br><br>
                    ⚡ <strong>Claude 3.5 Sonnet</strong>: The gold standard for logic, styling UI components (CSS/HTML), and generating elegant code.
                    <br><br>
                    🚀 <strong>GitHub Copilot</strong>: The ultimate real-time IDE autocomplete helper that writes boilerplate as you type.
                </div>
            `;
        }

        // Greetings
        if (q.includes('hello') || q.includes('hi') || q.includes('hey') || q.includes('greetings') || q.includes('yo')) {
            return `
                Hello! I am ready to recommend AI models to elevate your CCS laboratory assignments. 🎓<br><br>
                What programming language or framework are you coding in today? (e.g. Python, Java, PHP, C++, JavaScript)
            `;
        }

        if (q.includes('thank') || q.includes('bye') || q.includes('cool') || q.includes('awesome')) {
            return `
                You're very welcome! If you need more AI recommendations for your assignments, just ask. Happy coding! 🚀💻
            `;
        }

        // Default response
        return `
            I'm a specialized <strong>CCS AI Recommender</strong>. 🤖<br><br>
            Please ask me about a specific programming language (e.g., <strong>Python</strong>, <strong>Java</strong>, <strong>PHP</strong>, <strong>JavaScript</strong>, <strong>C++</strong>) and I will recommend the best AI tools and models for it!
        `;
    }

    // Recommendation wizard interactive flow
    function startWizard() {
        const chatBody = document.getElementById('ccs-ai-chat-body');
        if (!chatBody) return;
        const wizardDiv = document.createElement('div');
        wizardDiv.className = 'ccs-ai-msg bot';
        wizardDiv.id = 'active-wizard-step-1';
        wizardDiv.innerHTML = `
            <div class="ccs-ai-msg-bubble">
                🔮 <strong>Recommendation Wizard Started</strong><br><br>
                Let me guide you. What is the <strong>primary focus</strong> of your coding today?
                <div style="display:flex; flex-direction:column; gap:8px; margin-top:12px;">
                    <button class="ccs-ai-tag" style="background:#144d94; color:white; border:none; text-align:left; border-radius:8px; width:100%;" onclick="document.getElementById('active-wizard-step-1').remove(); window.runWizardStep2('frontend')">🎨 Frontend / UI (HTML/CSS/JS/React)</button>
                    <button class="ccs-ai-tag" style="background:#144d94; color:white; border:none; text-align:left; border-radius:8px; width:100%;" onclick="document.getElementById('active-wizard-step-1').remove(); window.runWizardStep2('backend')">⚙️ Backend / Logic (PHP/Java/Python/C++)</button>
                    <button class="ccs-ai-tag" style="background:#144d94; color:white; border:none; text-align:left; border-radius:8px; width:100%;" onclick="document.getElementById('active-wizard-step-1').remove(); window.runWizardStep2('repo')">📂 Whole Codebase Analysis</button>
                </div>
            </div>
            <div class="ccs-ai-msg-time">${getCurrentTimeStr()}</div>
        `;
        chatBody.appendChild(wizardDiv);
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    // Expose wizard globally so standard inline button onclicks can trigger it
    window.runWizardStep2 = function(choice) {
        showTypingIndicator();
        setTimeout(() => {
            removeTypingIndicator();
            const chatBody = document.getElementById('ccs-ai-chat-body');
            if (!chatBody) return;
            const wizardDiv = document.createElement('div');
            wizardDiv.className = 'ccs-ai-msg bot';
            
            if (choice === 'frontend') {
                wizardDiv.innerHTML = `
                    <div class="ccs-ai-msg-bubble">
                        🎨 <strong>Frontend / UI Recommendation</strong><br><br>
                        Since you are styling and designing user interfaces:
                        <br><br>
                        🏆 Use <strong>Claude 3.5 Sonnet</strong>.
                        <br>
                        Claude is absolute royalty when it comes to visual accuracy, generating premium CSS layouts, responsive grids, and clean component systems. It is by far the most capable model for frontend architecture.
                    </div>
                    <div class="ccs-ai-msg-time">${getCurrentTimeStr()}</div>
                `;
            } else if (choice === 'backend') {
                wizardDiv.innerHTML = `
                    <div class="ccs-ai-msg-bubble">
                        ⚙️ <strong>Backend / Logic Recommendation</strong><br><br>
                        For algorithmic logic, server setups, and secure APIs:
                        <br><br>
                        🏆 Use <strong>Claude 3.5 Sonnet</strong> or <strong>Gemini 1.5 Pro</strong>.
                        <br>
                        For logical correctness, Claude sonnet is unmatched. For database operations, Gemini is perfect because you can feed it your full database script to write PDO queries with perfect syntax.
                    </div>
                    <div class="ccs-ai-msg-time">${getCurrentTimeStr()}</div>
                `;
            } else {
                wizardDiv.innerHTML = `
                    <div class="ccs-ai-msg-bubble">
                        📂 <strong>Whole Codebase Analysis Recommendation</strong><br><br>
                        If you need to analyze multiple files or explain cross-file logic:
                        <br><br>
                        🏆 Use <strong>Gemini 1.5 Pro</strong>.
                        <br>
                        No other AI matches Gemini's massive 2 million token context window. You can upload dozens of files at once to explain or refactor full application flows with ease.
                    </div>
                    <div class="ccs-ai-msg-time">${getCurrentTimeStr()}</div>
                `;
            }
            chatBody.appendChild(wizardDiv);
            chatBody.scrollTop = chatBody.scrollHeight;
        }, 800);
    }
}

// Smart auto-hiding navbar scroll logic
function initSmartNavbar() {
    console.log('[Animation] Initializing smart auto-hiding navbar scroll listener...');
    const navbar = document.querySelector('.navbar');
    if (!navbar) return;
    
    let lastScrollY = window.scrollY;
    const threshold = 10; // minimum scroll distance in pixels to trigger hide/show
    
    window.addEventListener('scroll', () => {
        const currentScrollY = window.scrollY;
        
        // Always show the navbar at the very top of the page
        if (currentScrollY <= 5) {
            navbar.classList.remove('nav-hidden');
            lastScrollY = currentScrollY;
            return;
        }
        
        // Check if we scrolled more than the threshold to avoid micro-adjustments
        const scrollDiff = Math.abs(currentScrollY - lastScrollY);
        if (scrollDiff < threshold) return;
        
        if (currentScrollY > lastScrollY) {
            // Scrolling down -> hide the topbar
            navbar.classList.add('nav-hidden');
        } else {
            // Scrolling up -> reveal the topbar
            navbar.classList.remove('nav-hidden');
        }
        
        lastScrollY = currentScrollY;
    }, { passive: true });
}
