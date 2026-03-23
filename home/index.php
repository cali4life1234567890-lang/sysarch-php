<?php
// Home page entry point
// This serves as the landing page for the CCS Sit-In Monitoring System

// Start session and check for logged in user
require_once __DIR__ . '/../database/db.php';
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

// Don't redirect - this is the guest landing page
// User pages are available at user_home.php and profile.php for logged-in users

// Pass user data to JavaScript
$userJson = json_encode($currentUser);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>University of Cebu - Home</title>
    <link rel="stylesheet" href="../style.css" />
    <script src="../script.js"></script>
  </head>
  <body>
    <nav class="navbar">
      <div class="nav-brand"> 
        <a href="./" class="logo-group"> 
          <img src="../imgs/ccslogo.png" alt="Department Logo" class="logo-sub" />
          <h1 class="system-title">
            College of Computer Studies Sit-In Monitoring System
          </h1>
        </a>
      </div>
      <div class="nav-links">
        <a href="index.php">Home</a>
        <a href="index.php?section=about">About Us</a>
        <a href="community.php">Community</a>

        <span id="guest-links">
          <a href="#" onclick="showPage('login')">Login</a>
          <a href="#" onclick="showPage('register')">Register</a>
        </span>

        <div id="user-dropdown" class="dropdown" style="display: none">
          <button class="dropbtn" id="display-username">Username ▼</button>
          <div class="dropdown-content">
            <a href="#" onclick="showProfile()">Profile</a>
            <a href="#" onclick="logout()">Logout</a>
          </div>
        </div>
      </div>
    </nav>

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
    <div id="profile" class="content-section" style="display: none">
      <div class="profile-card">
        <h2 class="profile-title">USER INFO</h2>
        <div class="profile-layout">
          <div class="profile-left">
            <img
              src="../imgs/temu_opera.png"
              alt="Profile Picture"
              class="profile-pic"
            />
          </div>
          <div class="profile-right">
            <div class="info-row">
              <span class="label">ID NUMBER:</span>
              <span class="value" id="prof-id"></span>
            </div>
            <div class="info-row">
              <span class="label">NAME:</span>
              <span class="value" id="prof-name"></span>
            </div>
            <div class="info-row">
              <span class="label">COURSE & LEVEL:</span>
              <span class="value" id="prof-course-level"></span>
            </div>
            <div class="info-row">
              <span class="label">EMAIL:</span>
              <span class="value" id="prof-email"></span>
            </div>
            <div class="info-row">
              <span class="label">SESSIONS LEFT:</span>
              <span class="value" id="prof-sessions-left"></span>
            </div>
            <div class="info-row">
              <span class="label">ADDRESS:</span>
              <span class="value" id="prof-address"></span>
            </div>

            <div class="profile-footer">
              <a href="#" class="edit-btn">✎ Edit Profile</a>
              <button class="delete-btn" onclick="deleteAccount()">
                Delete Account
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <?php require_once 'home.php'; ?>

    <script>
      // Initialize UI for guest user
      document.addEventListener('DOMContentLoaded', function() {
        updateUIForGuestUser();
      });
    </script>
  </body>
</html>
