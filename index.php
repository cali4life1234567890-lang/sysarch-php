<?php
// Start session and check for logged in user
require_once 'database/db.php';
startSession();

// Auto-copy uploaded campus background image if it exists in Gemini app data folder
$source_img = 'C:\\Users\\cali4\\.gemini\\antigravity\\brain\\04c11c9c-d006-42f4-923a-e9af740bd287\\media__1779062561131.png';
$dest_img = __DIR__ . '/imgs/uc-campus.png';
if (file_exists($source_img) && !file_exists($dest_img)) {
    @copy($source_img, $dest_img);
}

// Check if user is already logged in via session
$currentUser = null;
$isAdmin = false;
if (isset($_SESSION['user_id']) && isset($_SESSION['token'])) {
    // Verify token exists in database
    $stmt = $pdo->prepare("SELECT session_token FROM sessions WHERE user_id = ? AND session_token = ? AND expires_at > datetime('now')");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['token']]);
    if ($stmt->fetch()) {
        // Get user data with remaining sessions
        $userStmt = $pdo->prepare("SELECT u.id_number, u.lastname, u.firstname, u.middlename, u.course, u.level, u.email, u.address, u.profile_pic, COALESCE(us.remaining_sessions, 30) as remaining_sessions FROM users u LEFT JOIN user_sessions us ON u.id = us.user_id WHERE u.id = ?");
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
                'profile_pic' => $user['profile_pic'],
                'is_admin' => $isAdmin
            ];
        }
    }
}

// Pass user data to JavaScript
$userJson = json_encode($currentUser);

// Get leaderboard data for Community Page
$commLeaderboardStmt = $pdo->query("
    SELECT 
        u.id_number,
        u.lastname,
        u.firstname,
        u.middlename,
        u.course,
        u.level,
        u.profile_pic,
        COALESCE(SUM(
            CASE 
                WHEN sr.time_out IS NOT NULL 
                THEN (julianday(sr.time_out) - julianday(sr.time_in)) * 24 
                ELSE 0 
            END
        ), 0) as total_hours,
        COALESCE((30 - us.remaining_sessions), 30) as used_sessions
    FROM users u
    LEFT JOIN sitin_records sr ON u.id = sr.user_id
    LEFT JOIN user_sessions us ON u.id = us.user_id
    WHERE u.id_number != '2664388'
    GROUP BY u.id
    ORDER BY total_hours DESC
");

$commLeaderboardData = [];
while ($row = $commLeaderboardStmt->fetch(PDO::FETCH_ASSOC)) {
    $totalHours = round($row['total_hours'], 2);
    $usedSessions = $row['used_sessions'];
    $totalScore = (0.60 * $totalHours) + (0.40 * $usedSessions);
    
    $commLeaderboardData[] = [
        'id_number' => $row['id_number'],
        'name' => trim($row['firstname'] . ' ' . ($row['middlename'] ? $row['middlename'] . ' ' : '') . $row['lastname']),
        'course' => $row['course'],
        'level' => $row['level'],
        'hours_spent' => $totalHours,
        'sessions_used' => $usedSessions,
        'total_score' => round($totalScore, 2),
        'profile_pic' => $row['profile_pic']
    ];
}

// Sort by total score descending
usort($commLeaderboardData, function($a, $b) {
    if ($b['total_score'] == $a['total_score']) {
        return $b['hours_spent'] - $a['hours_spent'];
    }
    return ($b['total_score'] - $a['total_score']) > 0 ? 1 : -1;
});

// Assign ranks
$rank = 1;
foreach ($commLeaderboardData as &$entry) {
    $entry['rank'] = $rank++;
}

$commLeaderboardJson = json_encode($commLeaderboardData);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>University of Cebu - Home</title>
    <link rel="icon" href="imgs/ccslogo.png" type="image/png" />
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
        <a href="#" onclick="showSection('admin-leaderboard')">Leaderboard</a>
        <a href="#" onclick="showSection('admin-students')">Students</a>
        <a href="#" onclick="showSection('admin-sitin')">Sit-In</a>
        <a href="#" onclick="showSection('admin-records')">View Sit-In Records</a>
        <a href="#" onclick="showSection('admin-reports')">Sit-In Reports</a>
        <a href="#" onclick="showSection('admin-feedback')">Feedback</a>
        <a href="#" onclick="showSection('admin-reservations')">Reservations</a>
        <a href="#" onclick="logout(); return false;">Logout</a>
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

    <div id="admin-leaderboard" class="content-section admin-section" style="display: none">
      <h1>Leaderboard</h1>
      <div class="leaderboard-table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>Rank</th>
              <th>Student</th>
              <th>Course</th>
              <th>Hours Spent</th>
              <th>Sessions Used</th>
              <th>Total Score</th>
            </tr>
          </thead>
          <tbody id="admin-leaderboard-body">
          </tbody>
        </table>
      </div>
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
        <select id="sit-in-purpose" class="auth-input" onchange="handleIndexSitInPurposeChange()">
          <option value="" disabled selected>Select Purpose/Language</option>
          <option value="Java">Java</option>
          <option value="Python">Python</option>
          <option value="C++">C++</option>
          <option value="C#">C#</option>
          <option value="C">C</option>
          <option value="PHP">PHP</option>
          <option value="JavaScript">JavaScript</option>
          <option value="HTML/CSS">HTML/CSS</option>
          <option value="SQL">SQL</option>
          <option value="ASP.NET">ASP.NET</option>
          <option value="Ruby">Ruby</option>
          <option value="Swift">Swift</option>
          <option value="Kotlin">Kotlin</option>
          <option value="Go">Go</option>
          <option value="TypeScript">TypeScript</option>
          <option value="Others">Others</option>
        </select>
        <input type="text" id="sit-in-purpose-other" placeholder="Specify custom purpose..." style="display: none; margin-top: 10px; width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px; box-sizing: border-box;" />
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
              <th>PC</th>
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
          <a href="users/leaderboard.php" id="nav-leaderboard">Leaderboard</a>
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

            <div class="info-row" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd;">
              <span class="label" style="font-weight: bold; color: #007bff;">CHANGE PASSWORD</span>
            </div>
            <div class="info-row">
              <span class="label">OLD PASSWORD:</span>
              <input type="password" id="prof-old-password" class="profile-input" placeholder="Enter current password" />
            </div>
            <div class="info-row">
              <span class="label">NEW PASSWORD:</span>
              <input type="password" id="prof-new-password" class="profile-input" placeholder="Enter new password" />
            </div>
            <div class="info-row">
              <span class="label">CONFIRM PASSWORD:</span>
              <input type="password" id="prof-confirm-password" class="profile-input" placeholder="Confirm new password" />
            </div>

            <div class="profile-footer">
              <button class="edit-btn" onclick="updateProfile()">Save Changes</button>
              <button class="edit-btn" style="background: #28a745; margin-left: 10px;" onclick="changePassword()">Change Password</button>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- User Home Dashboard -->
    <?php if ($currentUser && !$isAdmin): ?>
    <div id="user-home" class="content-section user-section premium-dashboard">
      <!-- Welcome Dashboard Header -->
      <div class="dashboard-header-premium">
        <div class="welcome-banner-text">
          <h1>Welcome back, <span class="student-name-highlight"><?php echo htmlspecialchars($currentUser['firstname']); ?></span>! 👋</h1>
          <p class="dashboard-subtitle">Monitor your sit-in history, software availability, and make PC reservations in real time.</p>
        </div>
        
        <!-- Reservation Enable Toggle Switch -->
        <div class="reservation-toggle-card">
          <div class="toggle-info">
            <strong>Enable Laboratory Reservations</strong>
            <p>Allow quick reservations and automatic check-ins</p>
          </div>
          <label class="switch-ios">
            <input type="checkbox" id="dashboard-reservation-toggle" onchange="toggleDashboardReservation(this)">
            <span class="slider-round"></span>
          </label>
        </div>
      </div>
      
      <!-- Statistics Cards Grid -->
      <div class="stats-premium-grid">
        <!-- Card 1: Remaining Sessions -->
        <div class="stat-card-premium remaining-sessions-card">
          <div class="stat-card-header">
            <span class="stat-icon-bg">⏳</span>
            <span class="stat-badge-premium text-glow">Remaining Balance</span>
          </div>
          <h2 class="stat-value" id="premium-rem-sessions">--</h2>
          <p class="stat-desc">Remaining sessions for this semester</p>
        </div>

        <!-- Card 2: Total Hours -->
        <div class="stat-card-premium hours-card">
          <div class="stat-card-header">
            <span class="stat-icon-bg">⏱️</span>
            <span class="stat-badge-premium text-glow">Total Sit-In</span>
          </div>
          <h2 class="stat-value" id="premium-total-hours">--</h2>
          <p class="stat-desc">Accumulated active laboratory time</p>
        </div>

        <!-- Card 3: Sessions Count -->
        <div class="stat-card-premium sessions-card">
          <div class="stat-card-header">
            <span class="stat-icon-bg">💻</span>
            <span class="stat-badge-premium text-glow">Total Sessions</span>
          </div>
          <h2 class="stat-value" id="premium-session-count">--</h2>
          <p class="stat-desc">Total lab entries logged by system</p>
        </div>

        <!-- Card 4: Average Duration -->
        <div class="stat-card-premium average-card">
          <div class="stat-card-header">
            <span class="stat-icon-bg">📈</span>
            <span class="stat-badge-premium text-glow">Avg. Session</span>
          </div>
          <h2 class="stat-value" id="premium-avg-duration">--</h2>
          <p class="stat-desc">Average physical presence per visit</p>
        </div>

        <!-- Card 5: Longest Session -->
        <div class="stat-card-premium longest-card">
          <div class="stat-card-header">
            <span class="stat-icon-bg">🔥</span>
            <span class="stat-badge-premium text-glow">Longest Session</span>
          </div>
          <h2 class="stat-value" id="premium-longest-session">--</h2>
          <p class="stat-desc">Your longest single laboratory session</p>
        </div>
      </div>

      <!-- Main Dashboard Grid Layout -->
      <div class="dashboard-main-grid">
        
        <!-- Left Pane: Quick Actions & Software Availability -->
        <div class="dashboard-pane-left">
          <!-- Active Sit-In / Current Status Card -->
          <div class="dashboard-content-card active-sitin-card">
            <h3>🖥️ Current Lab Status & Quick Actions</h3>
            <div id="premium-active-sitin-container" class="active-sitin-status-box">
              <p class="status-loading">Checking your active session...</p>
            </div>
            
            <div class="dashboard-quick-links">
              <a href="users/user_reservation.php" class="btn-dashboard-reserve">
                <span>📅 Go to Reservations System</span>
              </a>
            </div>
          </div>

          <!-- Software Availability Explorer Card -->
          <div class="dashboard-content-card software-explorer-card">
            <div class="software-card-header">
              <h3>🔍 Software Availability per Lab</h3>
              <div class="software-search-wrapper">
                <input type="text" id="software-search-input" onkeyup="filterSoftwareList()" placeholder="Search software (e.g. VS Code, NetBeans)...">
              </div>
            </div>

            <!-- Explorer View Toggle Switcher -->
            <div class="explorer-view-toggle">
              <button id="view-software-btn" class="view-toggle-btn active" onclick="setExplorerView('software')">⚙️ Software List</button>
              <button id="view-pcs-btn" class="view-toggle-btn" onclick="setExplorerView('pcs')">💻 PC Availability</button>
            </div>

            <!-- PC Grid Status Legend (hidden by default) -->
            <div id="pc-legend-container" class="pc-legend-container" style="display: none;">
              <div class="legend-item"><span class="legend-dot pc-available"></span> Available</div>
              <div class="legend-item"><span class="legend-dot pc-occupied"></span> Occupied</div>
              <div class="legend-item"><span class="legend-dot pc-reserved"></span> Reserved</div>
            </div>

            <!-- Lab Switcher Tabs -->
            <div class="software-lab-tabs">
              <button class="software-tab active" onclick="switchSoftwareLab('524')">Lab 524</button>
              <button class="software-tab" onclick="switchSoftwareLab('526')">Lab 526</button>
              <button class="software-tab" onclick="switchSoftwareLab('528')">Lab 528</button>
              <button class="software-tab" onclick="switchSoftwareLab('530')">Lab 530</button>
              <button class="software-tab" onclick="switchSoftwareLab('MAC')">MAC Lab</button>
            </div>

            <!-- Software / PC list grid container -->
            <div class="software-grid-container">
              <div id="software-list-grid" class="software-list-grid">
                <p class="loading-placeholder">Loading software lists...</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Pane: Rules & Session Logs -->
        <div class="dashboard-pane-right">
          <!-- Announcements & Rules Container (Stacked) -->
          <div class="announcements-rules-wrapper">
            <div class="dashboard-content-card announcements-card-premium">
              <h3>📢 Announcements</h3>
              <div id="premium-announcements-list" class="announcement-list-premium">
                <p class="no-announcements">No announcements from admin</p>
              </div>
            </div>

            <div class="dashboard-content-card rules-card-premium">
              <h3>📋 Rules and Regulations</h3>
              <div class="rules-list-premium">
                <p><strong>University of Cebu — CCS Sit-In Rules</strong></p>
                <ul>
                  <li>Maintain silence and discipline inside the laboratory.</li>
                  <li>No personal game playing of any form is permitted.</li>
                  <li>Downloading and installing unauthorized software is strictly prohibited.</li>
                </ul>
              </div>
            </div>
          </div>
          
          <!-- Sessions Table Card -->
          <div class="dashboard-content-card sessions-log-card">
            <div class="sessions-header-premium">
              <h3>🕒 Your Sit-In Session Logs</h3>
              <select id="sessions-table-filter" onchange="loadPremiumSessions()" class="sessions-filter-select">
                <option value="all">All Sessions</option>
                <option value="today">Today</option>
                <option value="week">Past Week</option>
                <option value="month">Past Month</option>
              </select>
            </div>
            
            <div class="table-scroll-container">
              <table class="data-table-premium">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Duration</th>
                    <th>PC Number</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody id="premium-sessions-tbody">
                  <tr><td colspan="6" class="table-loading">Loading session history...</td></tr>
                </tbody>
              </table>
            </div>
          </div>

        </div>
      </div>
    </div>
    <?php endif; ?>



    <!-- Regular User Sections (Guest Home - Only for non-logged-in users) -->
    <?php if (!$currentUser && !$isAdmin): ?>
    <div id="home" class="content-section guest-reveal">
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

    <div id="community" class="content-section guest-reveal">
      <div class="community-header-banner">
        <h1>CCS Student Community</h1>
        <p>Connect with peers, view university announcements, and celebrate our most active lab members!</p>
      </div>

      <div class="community-grid">
        <!-- Left Side: Community News & Announcements -->
        <div class="community-left-panel">
          <div class="community-card">
            <h2>Welcome to CCS Sit-In Community</h2>
            <p>Here you can keep track of ongoing events, lab notices, and see which students are dedicating the most time to expanding their programming skills in our laboratory facilities.</p>
          </div>
          <div class="community-card event-highlight">
            <h3>📢 Laboratory Practice & Study Sessions</h3>
            <p>Students are encouraged to maximize their 30 sit-in sessions per semester. Build applications, complete course laboratory tasks, and climb the Leaderboard!</p>
          </div>
        </div>

        <!-- Right Side: Top Students Leaderboard -->
        <div class="community-right-panel">
          <div class="community-card community-leaderboard-card">
            <h2 class="section-title">🏆 Top Sit-In Achievers</h2>
            
            <!-- Podium for top 3 with GIANT profile pictures -->
            <div class="comm-podium">
              <!-- 2nd Place -->
              <div class="comm-podium-card rank-2" id="comm-podium-2">
                <!-- Will be populated dynamically -->
              </div>

              <!-- 1st Place -->
              <div class="comm-podium-card rank-1" id="comm-podium-1">
                <!-- Will be populated dynamically -->
              </div>

              <!-- 3rd Place -->
              <div class="comm-podium-card rank-3" id="comm-podium-3">
                <!-- Will be populated dynamically -->
              </div>
            </div>

            <!-- Rankings Table for Ranks 4+ -->
            <div class="comm-table-container">
              <table class="comm-leaderboard-table">
                <thead>
                  <tr>
                    <th>Rank</th>
                    <th>Student</th>
                    <th>Course</th>
                    <th style="text-align: right;">Score</th>
                  </tr>
                </thead>
                <tbody id="comm-leaderboard-body">
                  <!-- Will be populated dynamically -->
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div id="about" class="content-section guest-reveal">
      <h1>About Us</h1>
      <div class="about-content">
        <div class="about-card">
          <h2>College of Computer Studies</h2>
          <p>The CCS Sit-In Monitoring System is designed to help students and faculty manage laboratory sessions effectively.</p>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <footer class="site-footer">
      <p>Cali John Kiesho Camilo - 2026</p>
    </footer>
    
    <script>
      const commLeaderboardData = <?php echo isset($commLeaderboardJson) ? $commLeaderboardJson : '[]'; ?>;

      function renderCommunityLeaderboard() {
        if (!commLeaderboardData || commLeaderboardData.length === 0) return;
        
        // 1. Render Podium (1st, 2nd, 3rd)
        const first = commLeaderboardData[0];
        const second = commLeaderboardData[1];
        const third = commLeaderboardData[2];

        // 1st Place Card
        const p1Card = document.getElementById('comm-podium-1');
        if (p1Card && first) {
          const avatar = first.profile_pic ? first.profile_pic : 'imgs/emp-prof.png';
          p1Card.innerHTML = `
            <div class="comm-crown">👑</div>
            <div class="comm-avatar-wrapper rank-1-avatar">
              <img src="${avatar}" alt="1st Place" class="comm-giant-avatar" />
              <div class="comm-medal-badge">🥇</div>
            </div>
            <div class="comm-podium-info">
              <h4 class="comm-podium-name">${first.name}</h4>
              <span class="comm-podium-details">${first.course} - Lvl ${first.level}</span>
              <div class="comm-score-tag">${first.total_score} pts</div>
            </div>
          `;
        } else if (p1Card) {
          p1Card.style.display = 'none';
        }

        // 2nd Place Card
        const p2Card = document.getElementById('comm-podium-2');
        if (p2Card && second) {
          const avatar = second.profile_pic ? second.profile_pic : 'imgs/emp-prof.png';
          p2Card.innerHTML = `
            <div class="comm-avatar-wrapper rank-2-avatar">
              <img src="${avatar}" alt="2nd Place" class="comm-giant-avatar" />
              <div class="comm-medal-badge">🥈</div>
            </div>
            <div class="comm-podium-info">
              <h4 class="comm-podium-name">${second.name}</h4>
              <span class="comm-podium-details">${second.course} - Lvl ${second.level}</span>
              <div class="comm-score-tag">${second.total_score} pts</div>
            </div>
          `;
        } else if (p2Card) {
          p2Card.style.display = 'none';
        }

        // 3rd Place Card
        const p3Card = document.getElementById('comm-podium-3');
        if (p3Card && third) {
          const avatar = third.profile_pic ? third.profile_pic : 'imgs/emp-prof.png';
          p3Card.innerHTML = `
            <div class="comm-avatar-wrapper rank-3-avatar">
              <img src="${avatar}" alt="3rd Place" class="comm-giant-avatar" />
              <div class="comm-medal-badge">🥉</div>
            </div>
            <div class="comm-podium-info">
              <h4 class="comm-podium-name">${third.name}</h4>
              <span class="comm-podium-details">${third.course} - Lvl ${third.level}</span>
              <div class="comm-score-tag">${third.total_score} pts</div>
            </div>
          `;
        } else if (p3Card) {
          p3Card.style.display = 'none';
        }

        // 2. Render Ranks 4+ in table
        const tbody = document.getElementById('comm-leaderboard-body');
        if (tbody) {
          const others = commLeaderboardData.slice(3);
          if (others.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: #888;">Complete sit-ins to join ranks!</td></tr>';
            return;
          }
          
          tbody.innerHTML = others.map(entry => {
            const avatar = entry.profile_pic ? entry.profile_pic : 'imgs/emp-prof.png';
            return `
              <tr>
                <td class="comm-rank-number">#${entry.rank}</td>
                <td>
                  <div class="comm-student-row">
                    <img src="${avatar}" alt="Avatar" class="comm-table-regular-avatar" />
                    <span class="comm-student-row-name">${entry.name}</span>
                  </div>
                </td>
                <td><span class="comm-course-badge">${entry.course}-${entry.level}</span></td>
                <td class="comm-score-tag-small" style="text-align: right;"><strong>${entry.total_score}</strong></td>
              </tr>
            `;
          }).join('');
        }
      }

      // Initialize UI based on PHP session data
      document.addEventListener('DOMContentLoaded', function() {
        // Render the premium community leaderboard
        renderCommunityLeaderboard();

        // Initialize Smart Auto-Hiding Navbar
        if (typeof initSmartNavbar === 'function') {
          initSmartNavbar();
        }

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
        // Load leaderboard
        loadAdminLeaderboard();
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

      // Load leaderboard for admin
      function loadAdminLeaderboard() {
        fetch('admin/admin_dashboard.php?action=leaderboard')
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              displayAdminLeaderboard(data.leaderboard);
            }
          });
      }

      // Display leaderboard in admin section
      function displayAdminLeaderboard(leaderboardData) {
        const tbody = document.getElementById('admin-leaderboard-body');
        if (!tbody) return;

        if (!leaderboardData || leaderboardData.length === 0) {
          tbody.innerHTML = '<tr><td colspan="6">No data available</td></tr>';
          return;
        }

        let html = '';
        leaderboardData.forEach((entry, index) => {
          const rankClass = entry.rank <= 3 ? 'rank-' + entry.rank : '';
          html += `<tr>
            <td class="${rankClass}">#${entry.rank}</td>
            <td>${entry.name}</td>
            <td>${entry.course} - ${entry.level}</td>
            <td>${entry.hours_spent}</td>
            <td>${entry.sessions_used}</td>
            <td class="score-cell">${entry.total_score}</td>
          </tr>`;
        });
        tbody.innerHTML = html;
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

      function handleIndexSitInPurposeChange() {
        const select = document.getElementById('sit-in-purpose');
        const otherInput = document.getElementById('sit-in-purpose-other');
        if (select.value === 'Others') {
          otherInput.style.display = 'block';
          otherInput.required = true;
          otherInput.focus();
        } else {
          otherInput.style.display = 'none';
          otherInput.required = false;
          otherInput.value = '';
        }
      }

      function startSitIn() {
        const studentId = document.getElementById('sit-in-student').value;
        const lab = document.getElementById('sit-in-lab').value;
        let purpose = document.getElementById('sit-in-purpose').value;
        if (purpose === 'Others') {
          const otherText = document.getElementById('sit-in-purpose-other').value.trim();
          if (!otherText) {
            alert('Please specify custom purpose');
            return;
          }
          purpose = 'Others: ' + otherText;
        }
        
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
            document.getElementById('sit-in-purpose-other').style.display = 'none';
            document.getElementById('sit-in-purpose-other').value = '';
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
        loadPremiumDashboardData();
        
        // Poll for new notifications every 30 seconds
        setInterval(loadNavNotifications, 30000);
      }

      // Premium Student Dashboard Functions
      let activeSoftwareLab = '524';
      let currentSoftwareList = [];
      let currentExplorerView = 'software';

      function loadPremiumDashboardData() {
        loadPremiumStats();
        loadPremiumSessions();
        switchSoftwareLab(activeSoftwareLab);
        loadPremiumAnnouncements();
      }

      function loadPremiumStats() {
        fetch('users/user_dashboard.php?action=dashboard_stats')
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              const stats = data.stats;
              document.getElementById('premium-rem-sessions').textContent = stats.remaining_sessions;
              document.getElementById('premium-total-hours').textContent = stats.total_hours;
              document.getElementById('premium-session-count').textContent = stats.session_count;
              document.getElementById('premium-avg-duration').textContent = stats.avg_duration;
              document.getElementById('premium-longest-session').textContent = stats.longest_session;
              
              // Set reservation toggle status
              const toggleEl = document.getElementById('dashboard-reservation-toggle');
              if (toggleEl) {
                toggleEl.checked = stats.can_reserve;
              }
            }
          })
          .catch(err => console.error('Error loading stats:', err));
      }

      function toggleDashboardReservation(checkbox) {
        const canReserve = checkbox.checked;
        
        fetch('users/user_dashboard.php?action=toggle_reservation', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            user_id: <?php echo json_encode($_SESSION['user_id'] ?? 0); ?>,
            can_reserve: canReserve
          })
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            showDashboardNotification('success', 'Reservation setting updated!');
          } else {
            checkbox.checked = !canReserve; // Revert
            showDashboardNotification('error', data.message || 'Failed to update setting');
          }
        })
        .catch(err => {
          checkbox.checked = !canReserve; // Revert
          console.error('Error toggling reservation:', err);
        });
      }

      function showDashboardNotification(type, message) {
        // Create dynamic floating notification
        const alertDiv = document.createElement('div');
        alertDiv.className = `dashboard-floating-alert ${type}`;
        alertDiv.textContent = message;
        document.body.appendChild(alertDiv);
        
        setTimeout(() => {
          alertDiv.classList.add('show');
        }, 100);
        
        setTimeout(() => {
          alertDiv.classList.remove('show');
          setTimeout(() => {
            alertDiv.remove();
          }, 300);
        }, 3000);
      }

      function loadPremiumSessions() {
        const filter = document.getElementById('sessions-table-filter')?.value || 'all';
        const tbody = document.getElementById('premium-sessions-tbody');
        if (!tbody) return;
        
        fetch(`users/user_dashboard.php?action=history&filter=${filter}`)
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              const history = data.history;
              if (history.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="table-empty">No laboratory sessions found.</td></tr>';
                return;
              }
              
              let html = '';
              history.forEach(row => {
                const statusClass = row.status === 'Completed' ? 'badge-completed' : 'badge-ongoing';
                const timeOut = row.time_out ? row.time_out.split(' ')[1] : 'Ongoing';
                const timeIn = row.time_in ? row.time_in.split(' ')[1] : '';
                const pcNumber = row.pc_number !== undefined ? row.pc_number : 'N/A';
                
                html += `<tr>
                  <td><strong>${row.date}</strong></td>
                  <td>${timeIn}</td>
                  <td>${timeOut}</td>
                  <td>${row.duration || '--'}</td>
                  <td><span class="pc-badge-premium">${pcNumber}</span></td>
                  <td><span class="premium-status-badge ${statusClass}">${row.status}</span></td>
                </tr>`;
              });
              tbody.innerHTML = html;
            }
          })
          .catch(err => {
            tbody.innerHTML = '<tr><td colspan="6" class="table-error">Failed to load session history.</td></tr>';
            console.error('Error loading sessions:', err);
          });
      }

      function switchSoftwareLab(labNumber) {
        activeSoftwareLab = labNumber;
        // Update active class on tab buttons
        document.querySelectorAll('.software-tab').forEach(tab => {
          tab.classList.remove('active');
          if (tab.textContent.includes(labNumber) || (labNumber === 'MAC' && tab.textContent.includes('MAC'))) {
            tab.classList.add('active');
          }
        });
        
        if (currentExplorerView === 'software') {
          const grid = document.getElementById('software-list-grid');
          if (grid) grid.innerHTML = '<p class="loading-placeholder">Fetching software list...</p>';
          
          fetch(`users/user_dashboard.php?action=lab_software&lab=${labNumber}`)
            .then(res => res.json())
            .then(data => {
              if (data.success) {
                currentSoftwareList = data.software;
                displaySoftwareList(currentSoftwareList);
              }
            })
            .catch(err => {
              if (grid) grid.innerHTML = '<p class="error-placeholder">Failed to load software.</p>';
              console.error('Error loading software:', err);
            });
        } else {
          loadPcsForLab(labNumber);
        }
      }

      function setExplorerView(view) {
        currentExplorerView = view;
        document.querySelectorAll('.view-toggle-btn').forEach(btn => btn.classList.remove('active'));
        
        const searchInput = document.getElementById('software-search-input');
        const legendContainer = document.getElementById('pc-legend-container');
        
        if (view === 'software') {
          document.getElementById('view-software-btn').classList.add('active');
          if (searchInput) searchInput.style.display = 'block';
          if (legendContainer) legendContainer.style.display = 'none';
          
          switchSoftwareLab(activeSoftwareLab);
        } else {
          document.getElementById('view-pcs-btn').classList.add('active');
          if (searchInput) searchInput.style.display = 'none';
          if (legendContainer) legendContainer.style.display = 'flex';
          
          loadPcsForLab(activeSoftwareLab);
        }
      }

      function loadPcsForLab(labNumber) {
        const grid = document.getElementById('software-list-grid');
        if (grid) grid.innerHTML = '<p class="loading-placeholder">Fetching PC availability...</p>';
        
        fetch(`users/user_dashboard.php?action=get_lab_pc_status&lab=${labNumber}`)
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              displayPcGrid(data.pcs);
            } else {
              if (grid) grid.innerHTML = `<p class="error-placeholder">Failed to load PC status: ${data.message || 'Unknown error'}</p>`;
            }
          })
          .catch(err => {
            if (grid) grid.innerHTML = '<p class="error-placeholder">Failed to load PC status.</p>';
            console.error('Error loading PC status:', err);
          });
      }

      function displayPcGrid(pcs) {
        const grid = document.getElementById('software-list-grid');
        if (!grid) return;
        
        if (!pcs || pcs.length === 0) {
          grid.innerHTML = '<p class="no-software">No PCs configured for this laboratory.</p>';
          return;
        }
        
        let html = '<div class="dashboard-pc-grid">';
        pcs.forEach(pc => {
          const statusClass = pc.status === 'available' ? 'pc-available' : (pc.status === 'occupied' ? 'pc-occupied' : 'pc-reserved');
          const statusText = pc.status.charAt(0).toUpperCase() + pc.status.slice(1);
          
          html += `<div class="dashboard-pc-card ${statusClass}">
            <span class="pc-num">${pc.pc_number}</span>
            <span class="pc-status">${statusText}</span>
          </div>`;
        });
        html += '</div>';
        
        grid.innerHTML = html;
      }

      function displaySoftwareList(list) {
        const grid = document.getElementById('software-list-grid');
        if (!grid) return;
        
        if (list.length === 0) {
          grid.innerHTML = '<p class="no-software">No software listed for this laboratory.</p>';
          return;
        }
        
        let html = '';
        list.forEach(sw => {
          const statusClass = sw.status === 'available' ? 'sw-available' : 'sw-unavailable';
          const statusText = sw.status === 'available' ? 'Available' : 'Maintenance';
          html += `<div class="software-item-premium">
            <div class="sw-header-row">
              <span class="sw-icon">⚙️</span>
              <span class="sw-status-indicator ${statusClass}">${statusText}</span>
            </div>
            <h4 class="sw-name">${sw.software_name}</h4>
            <span class="sw-version">v${sw.version || '1.0'}</span>
          </div>`;
        });
        grid.innerHTML = html;
      }

      function filterSoftwareList() {
        const query = document.getElementById('software-search-input').value.toLowerCase();
        const filtered = currentSoftwareList.filter(sw => 
          sw.software_name.toLowerCase().includes(query)
        );
        displaySoftwareList(filtered);
      }

      function loadPremiumAnnouncements() {
        const list = document.getElementById('premium-announcements-list');
        if (!list) return;
        
        fetch('users/user_dashboard.php?action=get_announcements')
          .then(res => res.json())
          .then(data => {
            if (data.success && data.announcements && data.announcements.length > 0) {
              let html = '';
              data.announcements.forEach(announcement => {
                html += `<div class="announcement-item-premium">
                  <div class="announcement-header-premium">
                    <strong>CCS Admin</strong>
                    <span class="announcement-date-premium">${announcement.date}</span>
                  </div>
                  <p>${announcement.message}</p>
                </div>`;
              });
              list.innerHTML = html;
            } else {
              list.innerHTML = '<p class="no-announcements">No announcements from admin</p>';
            }
          })
          .catch(err => {
            console.error('Error loading premium announcements:', err);
            list.innerHTML = '<p class="no-announcements">No announcements from admin</p>';
          });
      }

      // Load user profile picture
      function loadUserProfilePicture() {
        const userId = currentUser?.id_number;
        if (!userId) return;
        
        const dbPhoto = currentUser?.profile_pic;
        const storedPhoto = localStorage.getItem('user_profile_picture_' + userId);
        const photoElements = document.querySelectorAll('.student-photo, #profile-picture');
        
        photoElements.forEach(img => {
          if (dbPhoto) {
            img.src = dbPhoto;
          } else if (storedPhoto) {
            img.src = storedPhoto;
          } else {
            img.src = 'imgs/emp-prof.png';
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
          const isShowing = dropdown.classList.contains('show');
          dropdown.classList.toggle('show');
          
          // Mark all notifications as read when dropdown opens
          if (!isShowing) {
            // Immediately hide badge for better UX
            const navBadge = document.getElementById('nav-notification-badge');
            if (navBadge) {
              navBadge.style.display = 'none';
            }
            // Then send request to mark as read
            markAllNotificationsRead();
          }
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
          const isShowing = dropdown.classList.contains('show');
          dropdown.classList.toggle('show');
          
          // Mark all notifications as read when dropdown opens
          if (!isShowing) {
            // Immediately hide badge for better UX
            const homeBadge = document.getElementById('home-notification-badge');
            if (homeBadge) {
              homeBadge.style.display = 'none';
            }
            // Also hide nav badge if exists
            const navBadge = document.getElementById('nav-notification-badge');
            if (navBadge) {
              navBadge.style.display = 'none';
            }
            // Then send request to mark as read
            markAllNotificationsRead();
          }
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
        const announcementList = document.getElementById('announcement-list');
        if (!announcementList) return;

        fetch('users/user_dashboard.php?action=get_announcements')
          .then(res => res.json())
          .then(data => {
            if (data.success && data.announcements && data.announcements.length > 0) {
              let html = '';
              data.announcements.forEach(ann => {
                html += `<div class="announcement-item">
                  <strong>CCS Admin</strong>
                  <p>${ann.message || ''}</p>
                  <small>${ann.date || ''}</small>
                </div>`;
              });
              announcementList.innerHTML = html;
            } else {
              announcementList.innerHTML = '<p class="no-announcements">No announcements from admin</p>';
            }
          })
          .catch(err => {
            console.error('Error loading announcements:', err);
            announcementList.innerHTML = '<p class="no-announcements">No announcements from admin</p>';
          });
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

        const formData = new FormData();
        formData.append('profile_pic', input.files[0]);

        fetch('reg-log-prof/profile.php?action=upload_pic', {
          method: 'POST',
          body: formData
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            alert(data.message);
            // Update current user's profile_pic
            if (currentUser) {
              currentUser.profile_pic = data.profile_pic;
            }
            // Reload the profile picture on the home page
            loadUserProfilePicture();
          } else {
            alert(data.message || 'Failed to upload photo');
          }
        })
        .catch(err => {
          console.error('Error uploading profile picture:', err);
          alert('An error occurred while uploading. Please try again.');
        });
      }

      // Load current sit-in status
      function loadCurrentSitIn() {
        fetch('users/user_dashboard.php?action=current_sitin')
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              if (data.auto_ended) {
                alert(data.message);
              }
              
              const container = document.getElementById('premium-active-sitin-container');
              if (!container) return;
              
              if (data.current_sitin) {
                let statusText = `<div class="active-session-banner">
                  <div class="active-pulse-ring"></div>
                  <div class="active-session-details">
                    <span class="active-session-title">Active Sit-In Session</span>
                    <span class="active-session-desc">Room: <strong>Lab ${data.current_sitin.lab}</strong> (PC #${data.current_sitin.pc_number || 'N/A'})</span>
                    <span class="active-session-time">Started at: ${data.current_sitin.time_in.split(' ')[1]}</span>
                  </div>
                </div>`;
                
                if (data.remaining_seconds) {
                  const minutesLeft = Math.floor(data.remaining_seconds / 60);
                  statusText += `<p class="session-timer-badge">⏳ Time remaining: <strong>${minutesLeft} min</strong></p>`;
                }
                
                statusText += `<button class="btn-premium-danger" onclick="endDashboardSitIn()">End Sit-In Session</button>`;
                container.innerHTML = statusText;
              } else {
                container.innerHTML = `<div class="offline-session-banner">
                  <span class="offline-icon">🔴</span>
                  <div class="offline-details">
                    <span class="offline-title">No Active Session</span>
                    <span class="offline-desc">You are not currently checked into any laboratory.</span>
                  </div>
                </div>`;
              }
            }
          });
      }

      function endDashboardSitIn() {
        if (!confirm('Are you sure you want to end your active sit-in session?')) {
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
            loadPremiumDashboardData();
          }
        })
        .catch(err => console.error('Error ending sit-in:', err));
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
          tbody.innerHTML = '<tr><td colspan="8">No reservations found</td></tr>';
          return;
        }
        
        let html = '';
        reservations.forEach(res => {
          const canCancel = res.status === 'pending';
          html += `<tr>
            <td>${res.lab_number}</td>
            <td>${res.pc_number || 'N/A'}</td>
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

      // Change password
      function changePassword() {
        const oldPassword = document.getElementById('prof-old-password').value;
        const newPassword = document.getElementById('prof-new-password').value;
        const confirmPassword = document.getElementById('prof-confirm-password').value;

        if (!oldPassword || !newPassword || !confirmPassword) {
          alert('All password fields are required');
          return;
        }

        fetch('users/user_dashboard.php?action=change_password', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            old_password: oldPassword,
            new_password: newPassword,
            confirm_password: confirmPassword
          })
        })
        .then(res => res.json())
        .then(data => {
          const msgBox = document.getElementById('profile-message');
          if (data.success) {
            msgBox.className = 'message-box success';
            msgBox.textContent = data.message;
            // Clear password fields
            document.getElementById('prof-old-password').value = '';
            document.getElementById('prof-new-password').value = '';
            document.getElementById('prof-confirm-password').value = '';
          } else {
            msgBox.className = 'message-box error';
            msgBox.textContent = data.message;
          }
        })
        .catch(error => {
          console.error('[Change Password] Error:', error);
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
        
        // Mark all as read when dropdown opens
        if (dropdown.classList.contains('show')) {
          // Immediately hide badge for better UX
          const navBadge = document.getElementById('nav-notification-badge');
          if (navBadge) {
            navBadge.style.display = 'none';
          }
          markAllNotificationsRead();
        }
      }

// Mark all notifications as read
      function markAllNotificationsRead() {
        fetch('users/user_dashboard.php?action=mark_all_notifications_read', {
          method: 'POST'
        })
        .then(res => res.json())
        .then(data => {
          console.log('Mark all read response:', data);
          if (data.success) {
            // Update nav badge
            const navBadge = document.getElementById('nav-notification-badge');
            if (navBadge) {
              navBadge.textContent = '0';
              navBadge.style.display = 'none';
            }
            // Also update home badge if exists
            const homeBadge = document.getElementById('home-notification-badge');
            if (homeBadge) {
              homeBadge.textContent = '0';
              homeBadge.style.display = 'none';
            }
          }
        })
        .catch(err => console.error('Error marking notifications read:', err));
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
