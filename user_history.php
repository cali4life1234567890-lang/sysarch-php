<?php
// User History Page
require_once 'db.php';
startSession();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['token'])) {
    header('Location: index.php');
    exit;
}

// Verify user session
$stmt = $pdo->prepare("SELECT session_token FROM sessions WHERE user_id = ? AND session_token = ? AND expires_at > datetime('now')");
$stmt->execute([$_SESSION['user_id'], $_SESSION['token']]);
if (!$stmt->fetch()) {
    header('Location: index.php');
    exit;
}

// Get user data
$userStmt = $pdo->prepare("SELECT id_number, lastname, firstname, middlename, course, level, email, address FROM users WHERE id = ?");
$userStmt->execute([$_SESSION['user_id']]);
$user = $userStmt->fetch();

if (!$user) {
    header('Location: index.php');
    exit;
}

$isAdmin = ($user['id_number'] === '2664388');
if ($isAdmin) {
    header('Location: admin/admin_home.php');
    exit;
}

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
    'address' => $user['address']
];

$userJson = json_encode($currentUser);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Sit-In History - CCS Sit-In Monitoring System</title>
    <link rel="stylesheet" href="style.css" />
    <script src="script.js"></script>
    <script>
      const phpUser = <?php echo $userJson; ?>;
      if (phpUser) {
        currentUser = phpUser;
      }
    </script>
    <style>
      .history-page {
        padding: 20px;
        max-width: 1200px;
        margin: 0 auto;
      }
      .history-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
      }
      .back-link {
        color: #007bff;
        text-decoration: none;
        font-size: 14px;
      }
      .back-link:hover {
        text-decoration: underline;
      }
    </style>
  </head>
  <body>
    <!-- User Navigation -->
    <nav class="navbar user-navbar">
      <div class="nav-brand"> 
        <a href="index.php" class="logo-group"> 
          <img src="imgs/uclogo.png" alt="University Logo" class="logo-main" />
          <img src="imgs/ccslogo.png" alt="Department Logo" class="logo-sub" />
          <h1 class="system-title">
            CCS Sit-In Monitoring System
          </h1>
        </a>
      </div>
      <div class="nav-links user-links">
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
        <a href="index.php" id="nav-home">Home</a>
        <a href="index.php" onclick="showSection('user-profile')" id="nav-profile">Edit Profile</a>
        <a href="user_history.php" id="nav-history">History</a>
        <a href="user_reservation.php" id="nav-reservation">Reservation</a>
        <a href="logout.php">Logout</a>
      </div>
    </nav>

    <!-- User History Page Content -->
    <div class="history-page">
      <div class="history-header">
        <h1>My Sit-In History</h1>
        <a href="index.php" class="back-link">← Back to Home</a>
      </div>
      
      <div class="records-filters">
        <select id="history-filter" class="auth-input" onchange="loadUserHistory()">
          <option value="all">All Records</option>
          <option value="today">Today</option>
          <option value="week">This Week</option>
          <option value="month">This Month</option>
          <option value="ongoing">Ongoing</option>
        </select>
        <button class="btn-primary" onclick="loadUserHistory()">Filter</button>
      </div>
      
      <table class="data-table">
        <thead>
          <tr>
            <th>Lab</th>
            <th>Time In</th>
            <th>Time Out</th>
            <th>Duration</th>
            <th>Purpose</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody id="history-table-body">
          <!-- History will be loaded here -->
        </tbody>
      </table>
    </div>

    <script>
      // Initialize on page load
      document.addEventListener('DOMContentLoaded', function() {
        loadNavNotifications();
        loadUserHistory();
      });

      // Load notifications for navigation dropdown
      function loadNavNotifications() {
        fetch('user_dashboard.php?action=notifications')
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              displayNavNotifications(data.notifications);
              const navBadge = document.getElementById('nav-notification-badge');
              if (navBadge) {
                navBadge.textContent = data.unread_count;
                navBadge.style.display = data.unread_count > 0 ? 'inline-block' : 'none';
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
        fetch('user_dashboard.php?action=mark_notification_read&id=' + notifId)
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
    </script>
  </body>
</html>
