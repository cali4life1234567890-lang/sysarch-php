<?php
// Community Page
require_once '../database/db.php';
startSession();

// Check if user is logged in - redirect to main index if logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['token'])) {
    $stmt = $pdo->prepare("SELECT session_token FROM sessions WHERE user_id = ? AND session_token = ? AND expires_at > datetime('now')");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['token']]);
    if ($stmt->fetch()) {
        header('Location: ../index.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Community - CCS Sit-In Monitoring System</title>
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
        <a href="community.php" class="active">Community</a>

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

    <div class="content-section">
      <div class="community-page">
        <h1>Community</h1>
        <div class="community-content">
          <div class="community-card">
            <h2>Welcome to the CCS Community</h2>
            <p>This is the community page. This is where the community can see the events and activities of the university.</p>
          </div>
          
          <div class="community-card">
            <h2>Upcoming Events</h2>
            <p>No upcoming events at this time. Check back soon!</p>
          </div>
          
          <div class="community-card">
            <h2>Recent Activities</h2>
            <p>No recent activities to display. Stay tuned for updates!</p>
          </div>
        </div>
      </div>
    </div>
  </body>
</html>
