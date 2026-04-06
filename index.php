<?php
// Start session and check for logged in user
require_once 'database/db.php';
startSession();

// Check if user is already logged in via session
$currentUser = null;
$isAdmin = false;
if (isset($_SESSION['user_id']) && isset($_SESSION['token'])) {
    // Verify token exists in database
    $stmt = $pdo->prepare("SELECT session_token FROM sessions WHERE user_id = ? AND session_token = ? AND expires_at > datetime('now')");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['token']]);
    if ($stmt->fetch()) {
        // Get user data with remaining sessions
        $userStmt = $pdo->prepare("SELECT u.id_number, u.lastname, u.firstname, u.middlename, u.course, u.level, u.email, u.address, COALESCE(us.remaining_sessions, 30) as remaining_sessions FROM users u LEFT JOIN user_sessions us ON u.id = us.user_id WHERE u.id = ?");
        $userStmt->execute([$_SESSION['user_id']]);
        $user = $userStmt->fetch();
        if ($user) {
            $isAdmin = ($user['id_number'] === '2664388');
            $fullName = $user['firstname'];
            if (!empty($user['middlename'])) {
                $fullName .= ' ' . $user['middlename'];
            }
            $fullName .= ' ' . $user['lastname'];
            $currentUser = [
                'id_number' => $user['id_number'],
                'name' => $fullName,
                'firstname' => $user['firstname'],
                'middlename' => $user['middlename'],
                'lastname' => $user['lastname'],
                'course' => $user['course'],
                'level' => $user['level'],
                'email' => $user['email'],
                'address' => $user['address'],
                'sessions_left' => $user['remaining_sessions'],
                'is_admin' => $isAdmin
            ];
        }
    }
}

// Pass user data to JavaScript
$userJson = json_encode($currentUser);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>University of Cebu - Home</title>
    <link rel="stylesheet" href="style.css" />
    <script src="script.js"></script>
    <script>
      // Initialize current user from PHP session
      const phpUser = <?php echo $userJson; ?>;
      if (phpUser) {
        currentUser = phpUser;
      }
    </script>
  </head>
  <body>
    <!-- Admin Navigation -->
    <?php if ($isAdmin): ?>
    <nav class="navbar admin-navbar">
      <div class="nav-brand"> 
        <a href="admin/admin_home.php" class="logo-group"> 
          <img src="imgs/ccslogo.png" alt="Department Logo" class="logo-sub" />
          <h1 class="system-title">
            CCS Sit-In Monitoring System (Admin)
          </h1>
        </a>
      </div>
      <div class="nav-links admin-links">
        <a href="#" onclick="showSection('admin-home')" class="active">Home</a>
        <a href="#" onclick="showSection('admin-search')">Search</a>
        <a href="#" onclick="showSection('admin-students')">Students</a>
        <a href="#" onclick="showSection('admin-sitin')">Sit-In</a>
        <a href="#" onclick="showSection('admin-records')">View Sit-In Records</a>
        <a href="#" onclick="showSection('admin-reports')">Sit-In Reports</a>
        <a href="#" onclick="showSection('admin-feedback')">Feedback</a>
        <a href="#" onclick="showSection('admin-reservations')">Reservations</a>
        <a href="#" onclick="logout()">Logout</a>
      </div>
    </nav>

    <!-- Admin Sections -->
    <div id="admin-home" class="content-section admin-section">
      <h1>Admin Dashboard</h1>
      <div class="admin-home-layout">
        <!-- Left Column: Dashboard Stats -->
        <div class="admin-home-left">
          <div class="dashboard-cards">
            <div class="dash-card">
              <h3>Total Students</h3>
              <p class="dash-number" id="total-students">0</p>
            </div>
            <div class="dash-card">
              <h3>Today's Sit-In</h3>
              <p class="dash-number" id="today-sitin">0</p>
            </div>
            <div class="dash-card">
              <h3>Total Records</h3>
              <p class="dash-number" id="total-records">0</p>
            </div>
            <div class="dash-card">
              <h3>Pending Reservations</h3>
              <p class="dash-number" id="pending-reservations">0</p>
            </div>
          </div>
        </div>

        <!-- Right Column: Announcements -->
        <div class="admin-home-right">
          <div class="announcement-admin-section">
            <h2>📢 Post Announcement</h2>
            <div class="announcement-form">
              <textarea id="admin-announcement-text" placeholder="Write your announcement here..." rows="4"></textarea>
              <button class="btn-primary" onclick="postAnnouncement()">Post Announcement</button>
            </div>

            <h3>Posted Announcements</h3>
            <div id="admin-announcement-list" class="admin-announcement-list">
              <p class="no-announcements">No announcements yet</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div id="admin-search" class="content-section admin-section" style="display: none">
      <h1>Search</h1>
      <div class="search-box">
        <input type="text" id="admin-search-input" placeholder="Search by ID, Name, or Course..." />
        <button class="btn-primary" onclick="adminSearch()">Search</button>
      </div>
      <div id="search-results" class="results-container"></div>
    </div>

    <div id="admin-students" class="content-section admin-section" style="display: none">
      <h1>Students</h1>
      <div class="students-list">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID Number</th>
              <th>Name</th>
              <th>Course</th>
              <th>Level</th>
              <th>Email</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="students-table-body">
            <!-- Students will be loaded here -->
          </tbody>
        </table>
      </div>
    </div>

    <div id="admin-sitin" class="content-section admin-section" style="display: none">
      <h1>Sit-In Management</h1>
      <div class="sitin-form">
        <select id="sit-in-student" class="auth-input">
          <option value="">Select Student</option>
        </select>
        <select id="sit-in-lab" class="auth-input">
          <option value="">Select Lab</option>
          <option value="Lab 1">Lab 1</option>
          <option value="Lab 2">Lab 2</option>
          <option value="Lab 3">Lab 3</option>
          <option value="Lab 4">Lab 4</option>
          <option value="Lab 5">Lab 5</option>
        </select>
        <input type="text" id="sit-in-purpose" placeholder="Purpose" />
        <button class="btn-primary" onclick="startSitIn()">Start Sit-In</button>
      </div>
    </div>

    <div id="admin-records" class="content-section admin-section" style="display: none">
      <h1>View Sit-In Records</h1>
      <div class="records-filters">
        <input type="date" id="record-date" />
        <select id="record-filter" class="auth-input">
          <option value="all">All Records</option>
          <option value="today">Today</option>
          <option value="week">This Week</option>
          <option value="month">This Month</option>
        </select>
        <button class="btn-primary" onclick="loadRecords()">Filter</button>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>ID Number</th>
            <th>Name</th>
            <th>Lab</th>
            <th>Time In</th>
            <th>Time Out</th>
            <th>Purpose</th>
          </tr>
        </thead>
        <tbody id="records-table-body">
          <!-- Records will be loaded here -->
        </tbody>
      </table>
    </div>

    <div id="admin-reports" class="content-section admin-section" style="display: none">
      <h1>Sit-In Reports</h1>
      <div class="reports-section">
        <div class="report-card">
          <h3>Daily Report</h3>
          <input type="date" id="report-date" />
          <button class="btn-primary" onclick="generateDailyReport()">Generate</button>
        </div>
        <div class="report-card">
          <h3>Weekly Report</h3>
          <input type="week" id="report-week" />
          <button class="btn-primary" onclick="generateWeeklyReport()">Generate</button>
        </div>
        <div class="report-card">
          <h3>Monthly Report</h3>
          <input type="month" id="report-month" />
          <button class="btn-primary" onclick="generateMonthlyReport()">Generate</button>
        </div>
      </div>
    </div>

    <div id="admin-feedback" class="content-section admin-section" style="display: none">
      <h1>Feedback</h1>
      <div class="feedback-list">
        <table class="data-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Student</th>
              <th>Feedback</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody id="feedback-table-body">
            <!-- Feedback will be loaded here -->
          </tbody>
        </table>
      </div>
    </div>

    <div id="admin-reservations" class="content-section admin-section" style="display: none">
      <h1>Reservations</h1>
      <div class="reservations-list">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Student</th>
              <th>Lab</th>
              <th>Date</th>
              <th>Time</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="reservations-table-body">
            <!-- Reservations will be loaded here -->
          </tbody>
        </table>
      </div>
    </div>

    <?php else: ?>
    <!-- Regular User Navigation with Dashboard -->
    <nav class="navbar user-navbar">
      <div class="nav-brand"> 
        <?php if ($currentUser && !$isAdmin): ?>
          <a href="#" onclick="showSection('user-home')" class="logo-group"> 
        <?php else: ?>
          <a href="index.php" class="logo-group"> 
        <?php endif; ?>
          <img src="imgs/ccslogo.png" alt="Department Logo" class="logo-sub" />
          <h1 class="system-title">
            CCS Sit-In Monitoring System
          </h1>
        </a>
      </div>
      <div class="nav-links user-links">
        <?php if ($currentUser): ?>
          <div class="nav-notification-dropdown">
            <a href="#" class="nav-notification-link" onclick="toggleNavNotificationDropdown(event)">
              <span>Notifications</span>
              <span class="nav-notification-badge" id="nav-notification-badge">0</span>
            </a>
            <div class="nav-notification-content" id="nav-notification-content">
              <div id="nav-notification-list">
                <p class="no-notifications">No notifications</p>
              </div>
            </div>
          </div>
          <a href="index.php?section=user-home" id="nav-home">Home</a>
          <a href="index.php?section=user-profile" id="nav-profile">Edit Profile</a>
          <a href="users/user_history.php" id="nav-history">History</a>
          <a href="users/user_reservation.php" id="nav-reservation">Reservation</a>
          <a href="reg-log-prof/logout.php" id="nav-logout">Logout</a>
        <?php else: ?>
          <!-- Guest Navigation -->
          <a href="#" onclick="showSection('home')">Home</a>
          <a href="#" onclick="showSection('community')">Community</a>
          <a href="#" onclick="showSection('about')">About Us</a>
          <a href="#" onclick="showPage('login')" class="btn-login">Login</a>
          <a href="#" onclick="showPage('register')" class="btn-register">Register</a>
        <?php endif; ?>
      </div>
    </nav>
    <?php endif; ?>

    <!-- Auth Container (for non-admin) -->
    <?php if (!$isAdmin): ?>
    <div id="auth-container" style="display: none">
      <div id="login-page" class="auth-card">
        <h2>Login</h2>
        <div id="login-error" class="error-msg"></div>
        <input type="text" id="login-id" placeholder="ID Number" />
        <input type="password" id="login-pass" placeholder="Password" />
        <button class="btn-primary" onclick="validateLogin()">Login</button>
        <p>
          Don't have an account?
          <span onclick="showPage('register')">Register</span>
        </p>
      </div>

      <div id="register-page" class="auth-card">
        <h2>Register</h2>
        <div id="register-error" class="error-msg"></div>

        <input type="text" id="reg-id" placeholder="ID Number" />
        <input type="text" id="reg-lname" placeholder="Last Name" />
        <input type="text" id="reg-fname" placeholder="First Name" />
        <input type="text" id="reg-mname" placeholder="Middle Name" />

        <select id="reg-level" class="auth-input">
          <option value="" disabled selected>Select Course Level</option>
          <option value="1">1</option>
          <option value="2">2</option>
          <option value="3">3</option>
          <option value="4">4</option>
        </select>

        <input type="password" id="reg-pass" placeholder="Password" />
        <input
          type="password"
          id="reg-confirm-pass"
          placeholder="Repeat Your Password"
        />
        <input type="email" id="reg-email" placeholder="Email" />

        <select id="reg-course" class="auth-input">
          <option value="" disabled selected>Select Course</option>
          <option value="BSIT">BSIT</option>
          <option value="BSCpE">BSCpE</option>
          <option value="BSCE">BSCE</option>
          <option value="BSCrim">BSCrim</option>
          <option value="BSA">BSA</option>
          <option value="BSEd">BSEd</option>
          <option value="BSHRM">BSHRM</option>
        </select>

        <input type="text" id="reg-address" placeholder="Address" />

        <button class="btn-primary" onclick="validateRegister()">
          Create Account
        </button>
        <p>
          Already have an account?
          <span onclick="showPage('login')">Login</span>
        </p>
      </div>
    </div>
    <?php endif; ?>
    
    <!-- User Profile / Edit Profile -->
    <?php if ($currentUser && !$isAdmin): ?>
    <div id="user-profile" class="content-section user-section" style="display: none">
      <div class="profile-card">
        <h2 class="profile-title">EDIT PROFILE</h2>
        <div class="profile-layout">
          <div class="profile-left">
            <img
              src="imgs/emp-prof.png"
              alt="Profile Picture"
              class="profile-pic"
              id="profile-picture"
            />
            <div class="profile-picture-upload">
              <label for="profile-pic-input" class="upload-btn">Choose Photo</label>
              <input type="file" id="profile-pic-input" accept="image/*" onchange="previewProfilePicture(this)" />
              <button type="button" class="btn-small" onclick="uploadProfilePicture()">Upload</button>
            </div>
          </div>
          <div class="profile-right">
            <div id="profile-message" class="message-box"></div>
            <div class="info-row">
              <span class="label">ID NUMBER:</span>
              <span class="value" id="prof-id"><?php echo htmlspecialchars($currentUser['id_number']); ?></span>
            </div>
            <div class="info-row">
              <span class="label">LAST NAME:</span>
              <input type="text" id="prof-lastname" class="profile-input" value="<?php echo htmlspecialchars($currentUser['lastname']); ?>" />
            </div>
            <div class="info-row">
              <span class="label">FIRST NAME:</span>
              <input type="text" id="prof-firstname" class="profile-input" value="<?php echo htmlspecialchars($currentUser['firstname']); ?>" />
            </div>
            <div class="info-row">
              <span class="label">MIDDLE NAME:</span>
              <input type="text" id="prof-middlename" class="profile-input" value="<?php echo htmlspecialchars($currentUser['middlename'] ?? ''); ?>" />
            </div>
            <div class="info-row">
              <span class="label">COURSE & LEVEL:</span>
              <span class="value"><?php echo htmlspecialchars($currentUser['course'] . ' - Level ' . $currentUser['level']); ?></span>
            </div>
            <div class="info-row">
              <span class="label">EMAIL:</span>
              <input type="email" id="prof-email" class="profile-input" value="<?php echo htmlspecialchars($currentUser['email']); ?>" />
            </div>
            <div class="info-row">
              <span class="label">SESSIONS LEFT:</span>
              <span class="value"><?php echo htmlspecialchars($currentUser['sessions_left']); ?></span>
            </div>
            <div class="info-row">
              <span class="label">ADDRESS:</span>
              <input type="text" id="prof-address" class="profile-input" value="<?php echo htmlspecialchars($currentUser['address'] ?? ''); ?>" />
            </div>

            <div class="profile-footer">
              <button class="edit-btn" onclick="updateProfile()">Save Changes</button>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- User Home Dashboard -->
    <?php if ($currentUser && !$isAdmin): ?>
    <div id="user-home" class="content-section user-section">
      <h1>Welcome, <?php echo htmlspecialchars($currentUser['firstname']); ?>!</h1>
      
      <div class="user-home-layout">
        <!-- Left Column: Student Info + Notification -->
        <div class="user-home-left">
          <div class="student-info-card">
            <h3>Student Information</h3>
            <div class="student-info-header">
              <img src="imgs/emp-prof.png" alt="Student Photo" class="student-photo" />
              <div class="student-info-details">
                <div class="info-item">
                  <span class="label">ID Number:</span>
                  <span class="value" id="home-id-number"><?php echo htmlspecialchars($currentUser['id_number']); ?></span>
                </div>
                <div class="info-item">
                  <span class="label">Name:</span>
                  <span class="value" id="home-student-name"><?php echo htmlspecialchars($currentUser['name']); ?></span>
                </div>
                <div class="info-item">
                  <span class="label">Course:</span>
                  <span class="value" id="home-student-course"><?php echo htmlspecialchars($currentUser['course']); ?></span>
                </div>
                <div class="info-item">
                  <span class="label">Level:</span>
                  <span class="value" id="home-student-level"><?php echo htmlspecialchars($currentUser['level']); ?></span>
                </div>
                <div class="info-item">
                  <span class="label">Email:</span>
                  <span class="value" id="home-student-email"><?php echo htmlspecialchars($currentUser['email']); ?></span>
                </div>
                <div class="info-item">
                  <span class="label">Remaining Sessions:</span>
                  <span class="value" id="home-remaining-sessions"><?php echo htmlspecialchars($currentUser['sessions_left']); ?></span>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Middle Column: Announcements -->
        <div class="user-home-middle">
          <div class="announcement-card">
            <h3>📢 Announcements</h3>
            <div id="announcement-list" class="announcement-list">
              <p class="no-announcements">No announcements from admin</p>
            </div>
          </div>
        </div>
        
        <!-- Right Column: Rules and Regulations -->
        <div class="user-home-right">
          <div class="rules-card">
            <h3>📋 Laboratory Rules and Regulations</h3>
            <div class="rules-list">
              <p><strong>University of Cebu</strong></p>
              <p><strong>COLLEGE OF INFORMATION & COMPUTER STUDIES</strong></p>
              <p>To avoid embaressment and maintaion camaraderie with your friends and superiors at the laboratory, please observe the following:</p>
              <ul>
                <li>Maintain silence proper decorum, and discipline inside the laboratory. Mobile phones, walkmans, and other personal piece of equpment must be switched off.</li>
                <li>Games are not allowed inside the lab. This includes computer-related games, card games, and other games that may distrub the operation of the lab.</li>
                <li>Surfing the internet is allowed only with the permission of the instructor. Downloading and installing of software is strictly prohibited.</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>



    <!-- Regular User Sections (Guest Home - Only for non-logged-in users) -->
    <?php if (!$currentUser && !$isAdmin): ?>
    <div id="home" class="content-section">
      <div class="home-hero">
        <h1>Welcome to CCS Sit-In Monitoring System</h1>
        <p class="home-tagline">University of Cebu - College of Computer Studies</p>
        <div class="home-cta">
          <p>Track your laboratory sessions efficiently</p>
        </div>
      </div>
      
      <div class="home-features">
        <div class="feature-card">
          <div class="feature-icon">📋</div>
          <h3>Easy Check-In</h3>
          <p>Simply log in and check in to any laboratory room</p>
        </div>
        
        <div class="feature-card">
          <div class="feature-icon">⏱️</div>
          <h3>Track Time</h3>
          <p>Monitor your sit-in sessions and remaining balance</p>
        </div>
        
        <div class="feature-card">
          <div class="feature-icon">📊</div>
          <h3>View History</h3>
          <p>Access your complete sit-in history anytime</p>
        </div>
      </div>
    </div>

    <div id="about" class="content-section" style="display: none">
      <h1>About Us</h1>
      <div class="about-content">
        <div class="about-card">
          <h2>College of Computer Studies</h2>
          <p>The CCS Sit-In Monitoring System is designed to help students and faculty manage laboratory sessions effectively.</p>
        </div>
      </div>
    </div>

    <div id="community" class="content-section" style="display: none">
      <h1>Community</h1>
      <div class="community-content">
        <div class="community-card">
          <h2>This is the Community Page</h2>
          <p>This is where the community can see the events and activities of the university.</p>
        </div>
      </div>
    </div>
    <?php endif; ?>
    
    <script>
      // Initialize UI based on PHP session data
      document.addEventListener('DOMContentLoaded', function() {
        // Check for section parameter in URL FIRST (before default UI initialization)
        const urlParams = new URLSearchParams(window.location.search);
        const sectionParam = urlParams.get('section');
        
        // If section parameter exists, show that section; otherwise use default
        if (sectionParam) {
          console.log('[Navigation] Showing section from URL:', sectionParam);
          showSection(sectionParam);
        } else {
          // Default: show user home for logged in users
          <?php if ($currentUser): ?>
            updateUIForLoggedInUser();
          <?php else: ?>
            updateUIForGuestUser();
          <?php endif; ?>
        }
        
        // Admin specific initialization
        <?php if ($isAdmin): ?>
          loadAdminDashboard();
        <?php elseif ($currentUser): ?>
          // Load user dashboard
          loadUserDashboard();
        <?php endif; ?>
      });

      // Admin functions
      function loadAdminDashboard() {
        // Load dashboard stats
        fetch('admin/admin_dashboard.php?action=stats')
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              document.getElementById('total-students').textContent = data.stats.total_students;
              document.getElementById('today-sitin').textContent = data.stats.today_sitin;
              document.getElementById('total-records').textContent = data.stats.total_records;
              document.getElementById('pending-reservations').textContent = data.stats.pending_reservations;
            }
          });
        // Load admin announcements
        loadAdminAnnouncements();
        // Load feedback
        loadAdminFeedback();
      }

      // Load admin announcements
      function loadAdminAnnouncements() {
        const announcements = JSON.parse(localStorage.getItem('admin_announcements') || '[]');
        displayAdminAnnouncements(announcements);
      }

      // Load feedback for admin
      function loadAdminFeedback() {
        fetch('admin/admin_dashboard.php?action=feedback')
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              displayAdminFeedback(data.feedback);
            }
          });
      }

      // Display feedback in admin section
      function displayAdminFeedback(feedbackList) {
        const tbody = document.getElementById('feedback-table-body');
        if (!tbody) return;

        if (!feedbackList || feedbackList.length === 0) {
          tbody.innerHTML = '<tr><td colspan="4">No feedback submitted yet</td></tr>';
          return;
        }

        let html = '';
        feedbackList.forEach(item => {
          const stars = '★'.repeat(item.rating) + '☆'.repeat(5 - item.rating);
          const fullName = (item.firstname || '') + ' ' + (item.lastname || '');
          html += `<tr>
            <td>${item.created_at ? item.created_at.split(' ')[0] : ''}</td>
            <td>${item.id_number} - ${fullName}</td>
            <td>${item.feedback_text}</td>
            <td>${stars}</td>
          </tr>`;
        });
        tbody.innerHTML = html;
      }

      // Display admin announcements
      function displayAdminAnnouncements(announcements) {
        const list = document.getElementById('admin-announcement-list');
        if (!list) return;

        if (announcements.length === 0) {
          list.innerHTML = '<p class="no-announcements">No announcements yet</p>';
          return;
        }

        let html = '';
        announcements.forEach(announcement => {
          html += `<div class="admin-announcement-item">
            <div class="admin-announcement-header">
              <strong>CCS Admin</strong>
              <span class="admin-announcement-date">${announcement.date}</span>
            </div>
            <p>${announcement.text}</p>
          </div>`;
        });
        list.innerHTML = html;
      }

      // Post new announcement
      function postAnnouncement() {
        const text = document.getElementById('admin-announcement-text').value.trim();
        if (!text) {
          alert('Please enter an announcement');
          return;
        }

        // Get current date in yyyy-mon-dd format
        const now = new Date();
        const year = now.getFullYear();
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const mon = months[now.getMonth()];
        const day = String(now.getDate()).padStart(2, '0');
        const dateStr = `${year}-${mon}-${day}`;

        // Get existing announcements
        const announcements = JSON.parse(localStorage.getItem('admin_announcements') || '[]');

        // Add new announcement at the beginning
        announcements.unshift({
          text: text,
          date: dateStr,
          postedAt: now.toISOString()
        });

        // Save to localStorage
        localStorage.setItem('admin_announcements', JSON.stringify(announcements));

        // Clear textarea
        document.getElementById('admin-announcement-text').value = '';

        // Reload announcements
        loadAdminAnnouncements();

        alert('Announcement posted successfully!');
      }

      function adminSearch() {
        const query = document.getElementById('admin-search-input').value;
        fetch('admin/admin_dashboard.php?action=search&q=' + encodeURIComponent(query))
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              displaySearchResults(data.results);
            }
          });
      }

      function displaySearchResults(results) {
        const container = document.getElementById('search-results');
        if (results.length === 0) {
          container.innerHTML = '<p>No results found</p>';
          return;
        }
        let html = '<table class="data-table"><thead><tr><th>ID</th><th>Name</th><th>Course</th><th>Level</th></tr></thead><tbody>';
        results.forEach(student => {
          html += `<tr>
            <td>${student.id_number}</td>
            <td>${student.firstname} ${student.lastname}</td>
            <td>${student.course}</td>
            <td>${student.level}</td>
          </tr>`;
        });
        html += '</tbody></table>';
        container.innerHTML = html;
      }

      function startSitIn() {
        const studentId = document.getElementById('sit-in-student').value;
        const lab = document.getElementById('sit-in-lab').value;
        const purpose = document.getElementById('sit-in-purpose').value;
        
        if (!studentId || !lab || !purpose) {
          alert('Please fill all fields');
          return;
        }

        fetch('admin/admin_dashboard.php?action=start_sitin', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({student_id: studentId, lab: lab, purpose: purpose})
        })
        .then(res => res.json())
        .then(data => {
          alert(data.message);
          if (data.success) {
            document.getElementById('sit-in-purpose').value = '';
          }
        });
      }

      function loadRecords() {
        const date = document.getElementById('record-date').value;
        const filter = document.getElementById('record-filter').value;
        
        fetch(`admin/admin_dashboard.php?action=records&date=${date}&filter=${filter}`)
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              displayRecords(data.records);
            }
          });
      }

      function displayRecords(records) {
        const tbody = document.getElementById('records-table-body');
        if (records.length === 0) {
          tbody.innerHTML = '<tr><td colspan="6">No records found</td></tr>';
          return;
        }
        let html = '';
        records.forEach(record => {
          html += `<tr>
            <td>${record.id_number}</td>
            <td>${record.name}</td>
            <td>${record.lab}</td>
            <td>${record.time_in}</td>
            <td>${record.time_out || 'Ongoing'}</td>
            <td>${record.purpose}</td>
          </tr>`;
        });
        tbody.innerHTML = html;
      }

      function generateDailyReport() {
        const date = document.getElementById('report-date').value;
        window.open(`admin_dashboard.php?action=report&type=daily&date=${date}`, '_blank');
      }

      function generateWeeklyReport() {
        const week = document.getElementById('report-week').value;
        window.open(`admin_dashboard.php?action=report&type=weekly&week=${week}`, '_blank');
      }

      function generateMonthlyReport() {
        const month = document.getElementById('report-month').value;
        window.open(`admin_dashboard.php?action=report&type=monthly&month=${month}`, '_blank');
      }

      // ============================================
      // USER DASHBOARD FUNCTIONS
      // ============================================

      // Load user dashboard data
      function loadUserDashboard() {
        loadCurrentSitIn();
        loadNavNotifications();
        loadAnnouncements();
        loadUserProfilePicture();
      }

      // Load user profile picture from localStorage
      function loadUserProfilePicture() {
        const userId = currentUser?.id_number;
        if (!userId) return;
        
        const storedPhoto = localStorage.getItem('user_profile_picture_' + userId);
        const photoElements = document.querySelectorAll('.student-photo, #profile-picture');
        
        photoElements.forEach(img => {
          if (storedPhoto) {
            img.src = storedPhoto;
          }
        });
      }

      // Load notifications for navigation dropdown
      function loadNavNotifications() {
        fetch('users/user_dashboard.php?action=notifications')
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              displayNavNotifications(data.notifications);
              // Update badge count in nav
              const navBadge = document.getElementById('nav-notification-badge');
              if (navBadge) {
                navBadge.textContent = data.unread_count;
                navBadge.style.display = data.unread_count > 0 ? 'inline-block' : 'none';
              }
              // Also update home badge if exists
              const homeBadge = document.getElementById('home-notification-badge');
              if (homeBadge) {
                homeBadge.textContent = data.unread_count;
                homeBadge.style.display = data.unread_count > 0 ? 'inline-block' : 'none';
              }
            }
          });
      }

      // Toggle navigation notification dropdown
      function toggleNavNotificationDropdown(event) {
        event.preventDefault();
        event.stopPropagation();
        const dropdown = document.getElementById('nav-notification-content');
        if (dropdown) {
          dropdown.classList.toggle('show');
        }
        // Close other dropdowns
        const otherDropdown = document.getElementById('user-notification-content');
        if (otherDropdown && otherDropdown.classList.contains('show')) {
          otherDropdown.classList.remove('show');
        }
      }

      // Display notifications in navigation dropdown
      function displayNavNotifications(notifications) {
        const list = document.getElementById('nav-notification-list');
        if (!list) return;

        if (notifications.length === 0) {
          list.innerHTML = '<p class="no-notifications">No notifications</p>';
          return;
        }

        let html = '';
        notifications.slice(0, 5).forEach(notif => {
          html += `<div class="nav-notification-item ${notif.is_read ? '' : 'unread'}" onclick="markNotificationRead(${notif.id})">
            <strong>${notif.title}</strong>
            <p>${notif.message || ''}</p>
            <small>${notif.created_at}</small>
          </div>`;
        });
        list.innerHTML = html;
      }

      // Mark notification as read
      function markNotificationRead(notifId) {
        fetch('users/user_dashboard.php?action=mark_notification_read&id=' + notifId)
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              loadNavNotifications();
            }
          });
      }

      // Close dropdowns when clicking outside
      document.addEventListener('click', function(event) {
        const navDropdown = document.getElementById('nav-notification-content');
        const navLink = document.querySelector('.nav-notification-link');
        if (navDropdown && navLink && !navDropdown.contains(event.target) && !navLink.contains(event.target)) {
          navDropdown.classList.remove('show');
        }
      });

      // Load notifications for home page (compact version)
      function loadNotificationsCompact() {
        fetch('users/user_dashboard.php?action=notifications')
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              displayNotificationsCompact(data.notifications);
              // Update badge count
              const badge = document.getElementById('home-notification-badge');
              if (badge) {
                badge.textContent = data.unread_count;
                badge.style.display = data.unread_count > 0 ? 'inline-block' : 'none';
              }
            }
          });
      }

      // Toggle user notification dropdown on home page
      function toggleUserNotificationDropdown() {
        const dropdown = document.getElementById('user-notification-content');
        if (dropdown) {
          dropdown.classList.toggle('show');
        }
      }

      // Display notifications in compact format on home page
      function displayNotificationsCompact(notifications) {
        const list = document.getElementById('user-notification-list');
        if (!list) return;

        if (notifications.length === 0) {
          list.innerHTML = '<p class="no-notifications">No notifications</p>';
          return;
        }

        let html = '';
        notifications.slice(0, 5).forEach(notif => {
          html += `<div class="notification-item-compact ${notif.is_read ? '' : 'unread'}">
            <strong>${notif.title}</strong>
            <p>${notif.message || ''}</p>
          </div>`;
        });
        list.innerHTML = html;
      }

      // Load announcements from admin
      function loadAnnouncements() {
        // For now, announcements will come from a placeholder - in production, this would fetch from a database
        const announcementList = document.getElementById('announcement-list');
        if (!announcementList) return;

        // Check if there's a stored announcement
        let announcements = [];
        try {
          const stored = localStorage.getItem('admin_announcements');
          if (stored) {
            announcements = JSON.parse(stored);
          }
        } catch (e) {}

        if (announcements.length === 0) {
          announcementList.innerHTML = '<p class="no-announcements">No announcements from admin</p>';
          return;
        }

        let html = '';
        announcements.forEach(ann => {
          html += `<div class="announcement-item">
            <strong>CCS Admin</strong>
            <p>${ann.text || ''}</p>
            <small>${ann.date || ''}</small>
          </div>`;
        });
        announcementList.innerHTML = html;
      }

      // Preview profile picture before upload
      function previewProfilePicture(input) {
        if (input.files && input.files[0]) {
          const reader = new FileReader();
          reader.onload = function(e) {
            document.getElementById('profile-picture').src = e.target.result;
          };
          reader.readAsDataURL(input.files[0]);
        }
      }

      // Upload profile picture
      function uploadProfilePicture() {
        const input = document.getElementById('profile-pic-input');
        if (!input.files || !input.files[0]) {
          alert('Please select a photo first');
          return;
        }

        const userId = currentUser?.id_number || document.getElementById('prof-id')?.textContent;
        
        // Store in localStorage as a demo - in production, upload to server
        const reader = new FileReader();
        reader.onload = function(e) {
          localStorage.setItem('user_profile_picture_' + userId, e.target.result);
          alert('Profile picture updated successfully!');
          // Reload the profile picture on the home page
          loadUserProfilePicture();
        };
        reader.readAsDataURL(input.files[0]);
      }

      // Load current sit-in status
      function loadCurrentSitIn() {
        fetch('users/user_dashboard.php?action=current_sitin')
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              const statusEl = document.getElementById('user-current-status');
              const btnEl = document.getElementById('sitin-action-btn');
              
              if (data.current_sitin) {
                statusEl.textContent = 'Currently in ' + data.current_sitin.lab + ' (Since: ' + data.current_sitin.time_in + ')';
                btnEl.textContent = 'End Sit-In';
                btnEl.className = 'btn-danger';
                btnEl.onclick = endSitIn;
              } else {
                statusEl.textContent = 'Not in Lab';
                btnEl.textContent = 'Start Sit-In';
                btnEl.className = 'btn-primary';
                btnEl.onclick = handleSitIn;
              }
            }
          });
      }

      // Load user statistics
      function loadUserStats() {
        fetch('users/user_dashboard.php?action=history')
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              document.getElementById('user-total-visits').textContent = data.history.length;
            }
          });
        
        fetch('users/user_dashboard.php?action=reservations')
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              document.getElementById('user-reservations-count').textContent = data.reservations.length;
            }
          });
      }

      // Handle sit-in start/end
      function handleSitIn() {
        const lab = document.getElementById('user-sitin-lab').value;
        const purpose = document.getElementById('user-sitin-purpose').value;
        
        if (!lab || !purpose) {
          alert('Please select a lab and enter a purpose');
          return;
        }

        fetch('users/user_dashboard.php?action=start_sitin', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({lab: lab, purpose: purpose})
        })
        .then(res => res.json())
        .then(data => {
          alert(data.message);
          if (data.success) {
            document.getElementById('user-sitin-purpose').value = '';
            loadCurrentSitIn();
            loadUserStats();
            // Update remaining sessions display
            if (data.remaining_sessions !== undefined) {
              currentUser.sessions_left = data.remaining_sessions;
              const homeSessionsEl = document.getElementById('home-remaining-sessions');
              if (homeSessionsEl) homeSessionsEl.textContent = data.remaining_sessions;
              const profSessionsEl = document.getElementById('prof-sessions-left');
              if (profSessionsEl) profSessionsEl.textContent = data.remaining_sessions;
            }
          }
        });
      }

      // End sit-in
      function endSitIn() {
        if (!confirm('Are you sure you want to end your sit-in?')) {
          return;
        }

        fetch('users/user_dashboard.php?action=end_sitin', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'}
        })
        .then(res => res.json())
        .then(data => {
          alert(data.message);
          if (data.success) {
            loadCurrentSitIn();
            loadUserHistory();
            loadUserStats();
          }
        });
      }

      // Load user history
      function loadUserHistory() {
        const filter = document.getElementById('history-filter')?.value || 'all';
        
        fetch(`user_dashboard.php?action=history&filter=${filter}`)
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              displayUserHistory(data.history);
            }
          });
      }

      // Display user history
      function displayUserHistory(history) {
        const tbody = document.getElementById('history-table-body');
        if (!tbody) return;
        
        if (history.length === 0) {
          tbody.innerHTML = '<tr><td colspan="6">No records found</td></tr>';
          return;
        }
        
        let html = '';
        history.forEach(record => {
          html += `<tr>
            <td>${record.lab}</td>
            <td>${record.time_in}</td>
            <td>${record.time_out || 'Ongoing'}</td>
            <td>${record.duration || 'In progress'}</td>
            <td>${record.purpose}</td>
            <td><span class="status-badge ${record.status === 'Completed' ? 'status-completed' : 'status-ongoing'}">${record.status}</span></td>
          </tr>`;
        });
        tbody.innerHTML = html;
      }

      // Load user reservations
      function loadUserReservations() {
        fetch('user_dashboard.php?action=reservations')
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              displayUserReservations(data.reservations);
            }
          });
      }

      // Display user reservations
      function displayUserReservations(reservations) {
        const tbody = document.getElementById('reservations-table-body');
        if (!tbody) return;
        
        if (reservations.length === 0) {
          tbody.innerHTML = '<tr><td colspan="7">No reservations found</td></tr>';
          return;
        }
        
        let html = '';
        reservations.forEach(res => {
          const canCancel = res.status === 'pending';
          html += `<tr>
            <td>${res.lab_number}</td>
            <td>${res.reservation_date}</td>
            <td>${res.start_time}</td>
            <td>${res.end_time}</td>
            <td>${res.purpose || 'N/A'}</td>
            <td><span class="status-badge status-${res.status}">${res.status}</span></td>
            <td>${canCancel ? `<button class="btn-small btn-danger" onclick="cancelReservation(${res.id})">Cancel</button>` : '-'}</td>
          </tr>`;
        });
        tbody.innerHTML = html;
      }

      // Make reservation
      function makeReservation() {
        const lab = document.getElementById('reservation-lab').value;
        const date = document.getElementById('reservation-date').value;
        const startTime = document.getElementById('reservation-start').value;
        const endTime = document.getElementById('reservation-end').value;
        const purpose = document.getElementById('reservation-purpose').value;

        if (!lab || !date || !startTime || !endTime) {
          alert('Please fill all required fields');
          return;
        }

        fetch('user_dashboard.php?action=make_reservation', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            lab: lab,
            date: date,
            start_time: startTime,
            end_time: endTime,
            purpose: purpose
          })
        })
        .then(res => res.json())
        .then(data => {
          alert(data.message);
          if (data.success) {
            document.getElementById('reservation-purpose').value = '';
            loadUserReservations();
            loadUserStats();
          }
        });
      }

      // Cancel reservation
      function cancelReservation(id) {
        if (!confirm('Are you sure you want to cancel this reservation?')) {
          return;
        }

        fetch(`user_dashboard.php?action=cancel_reservation&id=${id}`, {
          method: 'POST',
          headers: {'Content-Type': 'application/json'}
        })
        .then(res => res.json())
        .then(data => {
          alert(data.message);
          if (data.success) {
            loadUserReservations();
            loadUserStats();
          }
        });
      }

      // Update profile
      function updateProfile() {
        console.log('[Profile] updateProfile called');
        const lastname = document.getElementById('prof-lastname').value;
        const firstname = document.getElementById('prof-firstname').value;
        const middlename = document.getElementById('prof-middlename').value;
        const email = document.getElementById('prof-email').value;
        const address = document.getElementById('prof-address').value;

        console.log('[Profile] Form values collected:', { lastname, firstname, middlename, email, address });

        if (!lastname || !firstname || !email) {
          alert('Last name, first name, and email are required');
          return;
        }

        console.log('[Profile] Sending update request to users/user_dashboard.php');
        fetch('users/user_dashboard.php?action=update_profile', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            lastname: lastname,
            firstname: firstname,
            middlename: middlename,
            email: email,
            address: address
          })
        })
        .then(res => {
          console.log('[Profile] Response status:', res.status);
          return res.json();
        })
        .then(data => {
          console.log('[Profile] Response data:', data);
          const msgBox = document.getElementById('profile-message');
          if (data.success) {
            msgBox.className = 'message-box success';
            msgBox.textContent = data.message;
            
            // Dynamically update student information on home page without reload
            const fullName = firstname + ' ' + (middlename ? middlename + ' ' : '') + lastname;
            
            // Update navbar username
            document.getElementById('display-username').textContent = fullName + ' ▼';
            
            // Update home page student info if visible
            const homeName = document.getElementById('home-student-name');
            const homeEmail = document.getElementById('home-student-email');
            if (homeName) homeName.textContent = fullName;
            if (homeEmail) homeEmail.textContent = email;
            
            console.log('[Profile] Student info updated dynamically');
          } else {
            msgBox.className = 'message-box error';
            msgBox.textContent = data.message;
          }
        })
        .catch(error => {
          console.error('[Profile] Error during update:', error);
        });
      }

      // ============================================
      // NOTIFICATION FUNCTIONS
      // ============================================

      // Toggle notification dropdown
      function toggleNotificationDropdown() {
        const dropdown = document.getElementById('notification-content');
        dropdown.classList.toggle('show');
        loadNotifications();
      }

      // Load notifications
      function loadNotifications() {
        fetch('user_dashboard.php?action=notifications')
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              displayNotifications(data.notifications, data.unread_count);
            }
          });
      }

      // Display notifications
      function displayNotifications(notifications, unreadCount) {
        const badge = document.getElementById('notification-badge');
        const list = document.getElementById('notification-list');
        
        badge.textContent = unreadCount;
        badge.style.display = unreadCount > 0 ? 'block' : 'none';

        if (notifications.length === 0) {
          list.innerHTML = '<p class="no-notifications">No notifications</p>';
          return;
        }

        let html = '';
        notifications.forEach(notif => {
          const icon = notif.type === 'success' ? '✓' : notif.type === 'error' ? '✕' : 'ℹ';
          html += `<div class="notification-item ${notif.is_read ? '' : 'unread'}" onclick="markNotificationRead(${notif.id})">
            <span class="notif-icon">${icon}</span>
            <div class="notif-content">
              <strong>${notif.title}</strong>
              <p>${notif.message || ''}</p>
              <small>${notif.created_at}</small>
            </div>
          </div>`;
        });
        list.innerHTML = html;
      }

      // Mark notification as read
      function markNotificationRead(id) {
        fetch(`user_dashboard.php?action=mark_notification_read&id=${id}`, {
          method: 'POST',
          headers: {'Content-Type': 'application/json'}
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            loadNotifications();
          }
        });
      }

      // Mark all notifications as read
      function markAllNotificationsRead() {
        const list = document.getElementById('notification-list');
        const items = list.querySelectorAll('.notification-item.unread');
        items.forEach(item => {
          const id = item.getAttribute('onclick').match(/\d+/)[0];
          markNotificationRead(id);
        });
      }

      // Close dropdowns when clicking outside
      window.onclick = function(event) {
        // Close notification dropdown in navbar
        if (!event.target.matches('.notification-btn') && !event.target.closest('.notification-content')) {
          const dropdown = document.getElementById('notification-content');
          if (dropdown && dropdown.classList.contains('show')) {
            dropdown.classList.remove('show');
          }
        }
        // Close user notification dropdown on home page
        if (!event.target.matches('.notification-dropbtn') && !event.target.closest('.notification-dropdown-content')) {
          const userDropdown = document.getElementById('user-notification-content');
          if (userDropdown && userDropdown.classList.contains('show')) {
            userDropdown.classList.remove('show');
          }
        }
      }

      // ============================================
      // SECTION SHOWING FUNCTIONS
      // ============================================

      // Show user section
      function showUserSection(sectionId) {
        // Hide all user sections
        document.querySelectorAll('.user-section').forEach(section => {
          section.style.display = 'none';
        });
        
        // Show the selected section
        const section = document.getElementById(sectionId);
        if (section) {
          section.style.display = 'block';
        }

        // Load data based on section
        if (sectionId === 'user-home') {
          loadUserDashboard();
        } else if (sectionId === 'user-profile') {
          // Profile is already loaded from PHP
        }
      }

      // Override showSection to handle user sections
      const originalShowSection = showSection;
      showSection = function(sectionId) {
        // Check if it's a user section
        if (sectionId.startsWith('user-')) {
          showUserSection(sectionId);
        } else {
          originalShowSection(sectionId);
        }
      };
    </script>
  </body>
</html>
