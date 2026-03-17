<?php
// User Reservation Page
require_once '../database/db.php';
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

// Get or create user sessions
$sessionStmt = $pdo->prepare("SELECT remaining_sessions FROM user_sessions WHERE user_id = ?");
$sessionStmt->execute([$_SESSION['user_id']]);
$userSession = $sessionStmt->fetch();

if (!$userSession) {
    // Create new user session with 30 sessions
    $insertSession = $pdo->prepare("INSERT INTO user_sessions (user_id, remaining_sessions) VALUES (?, 30)");
    $insertSession->execute([$_SESSION['user_id']]);
    $remainingSessions = 30;
} else {
    $remainingSessions = $userSession['remaining_sessions'];
}

$userJson = json_encode($currentUser);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Reservations - CCS Sit-In Monitoring System</title>
    <link rel="stylesheet" href="../style.css" />
    <script src="../script.js"></script>
    <script>
      const phpUser = <?php echo $userJson; ?>;
      if (phpUser) {
        currentUser = phpUser;
      }
      let remainingSessions = <?php echo $remainingSessions; ?>;
    </script>
    <style>
      .reservation-page {
        padding: 20px;
        max-width: 1200px;
        margin: 0 auto;
      }
      .reservation-header {
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
      .user-info-card {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        color: #000000;
      }
      .user-info-card .info-row {
        display: flex;
        margin-bottom: 8px;
        color: #000000;
      }
      .user-info-card .label {
        font-weight: bold;
        width: 120px;
        color: #000000;
      }
      .user-idno {
        color: #000000;
      }
      .user-name {
        color: #000000;
      }
      .form-section {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 20px;
      }
      .form-section h2 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #333;
      }
      .form-row {
        display: flex;
        gap: 15px;
        margin-bottom: 15px;
      }
      .form-row .form-group {
        flex: 1;
      }
      .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
      }
      .form-group input, 
      .form-group select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
      }
      .sessions-display {
        background: #e7f3ff;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        text-align: center;
      }
      .sessions-count {
        font-size: 24px;
        font-weight: bold;
        color: #007bff;
      }
      .info-display {
        background: #f8f9fa;
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 15px;
      }
    </style>
  </head>
  <body>
    <!-- User Navigation -->
    <nav class="navbar user-navbar">
      <div class="nav-brand"> 
        <a href="../index.php" class="logo-group"> 
          <img src="../imgs/uclogo.png" alt="University Logo" class="logo-main" />
          <img src="../imgs/ccslogo.png" alt="Department Logo" class="logo-sub" />
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
        <a href="../index.php" id="nav-home">Home</a>
        <a href="../index.php?section=user-profile" onclick="showSection('user-profile'); return false;" id="nav-profile">Edit Profile</a>
        <a href="user_history.php" id="nav-history">History</a>
        <a href="user_reservation.php" id="nav-reservation">Reservation</a>
        <a href="../reg-log-prof/logout.php">Logout</a>
      </div>
    </nav>

    <!-- User Reservation Page Content -->
    <div class="reservation-page">
      <div class="reservation-header">
        <h1>Reservation</h1>
      </div>

      <!-- User Info Display -->
      <div class="form-section">
        <div class="info-row">
          <span class="label">ID Number:</span>
          <span class="user-idno"><?php echo htmlspecialchars($currentUser['id_number']); ?></span>
        </div>
        <div class="info-row">
          <span class="label">Name:</span>
          <span class="user-name"><?php echo htmlspecialchars($currentUser['name']); ?></span>
        </div>
      <!-- Sit-In Form Section -->
      <div class="form-section">
        <form id="sitin-form" onsubmit="submitSitIn(event)">
          <div class="form-row">
            <div class="form-group">
              <label for="sitin-purpose">Purpose:</label>
              <input type="text" id="sitin-purpose" placeholder="Enter purpose" required />
            </div>
            <div class="form-group">
              <label for="sitin-lab">Lab:</label>
              <input type="text" id="sitin-lab" placeholder="Enter lab" required />
            </div>
          </div>
          <button type="submit" class="btn-primary">Submit</button>
        </form>
        <div class="sessions-display">
          <div>Remaining Sessions</div>
          <div class="sessions-count" id="remaining-sessions"><?php echo $remainingSessions; ?></div>
        </div>

        <form id="reservation-form" onsubmit="submitReservation(event)">
          <div class="form-row">
            <div class="form-group">
              <label for="reservation-time">Time In:</label>
              <input type="time" id="reservation-time" required />
            </div>
            <div class="form-group">
              <label for="reservation-date">Date:</label>
              <input type="date" id="reservation-date" required />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="reservation-lab">Lab:</label>
              <input type="text" id="reservation-lab" placeholder="Enter lab" required />
            </div>
            <div class="form-group">
              <label for="reservation-purpose">Purpose:</label>
              <input type="text" id="reservation-purpose" placeholder="Enter purpose" required />
            </div>
          </div>
          <button type="submit" class="btn-primary">Reserve</button>
        </form>
      </div>
    </div>

    <script>
      // Initialize on page load
      document.addEventListener('DOMContentLoaded', function() {
        loadNavNotifications();
        loadUserReservations();
        loadRemainingSessions();
        
        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('reservation-date').setAttribute('min', today);
      });

      // Load remaining sessions
      function loadRemainingSessions() {
        fetch('user_dashboard.php?action=remaining_sessions')
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              remainingSessions = data.remaining_sessions;
              document.getElementById('remaining-sessions').textContent = remainingSessions;
            }
          });
      }

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

      // Submit Sit-In
      function submitSitIn(event) {
        event.preventDefault();
        
        const purpose = document.getElementById('sitin-purpose').value;
        const lab = document.getElementById('sitin-lab').value;

        if (!purpose || !lab) {
          alert('Please fill in all fields');
          return;
        }

        fetch('user_dashboard.php?action=start_sitin', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            lab: lab,
            purpose: purpose
          })
        })
        .then(res => res.json())
        .then(data => {
          alert(data.message);
          if (data.success) {
            document.getElementById('sitin-form').reset();
            loadRemainingSessions();
          }
        });
      }

      // Submit Reservation
      function submitReservation(event) {
        event.preventDefault();
        
        const time = document.getElementById('reservation-time').value;
        const date = document.getElementById('reservation-date').value;
        const lab = document.getElementById('reservation-lab').value;
        const purpose = document.getElementById('reservation-purpose').value;

        if (!time || !date || !lab || !purpose) {
          alert('Please fill in all fields');
          return;
        }

        fetch('user_dashboard.php?action=make_reservation', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            lab: lab,
            date: date,
            start_time: time,
            end_time: '',
            purpose: purpose
          })
        })
        .then(res => res.json())
        .then(data => {
          alert(data.message);
          if (data.success) {
            document.getElementById('reservation-form').reset();
            loadUserReservations();
          }
        });
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
          tbody.innerHTML = '<tr><td colspan="6">No reservations found</td></tr>';
          return;
        }
        
        let html = '';
        reservations.forEach(res => {
          const canCancel = res.status === 'pending';
          html += `<tr>
            <td>${res.lab_number}</td>
            <td>${res.reservation_date}</td>
            <td>${res.start_time}</td>
            <td>${res.purpose || 'N/A'}</td>
            <td><span class="status-badge status-${res.status}">${res.status}</span></td>
            <td>${canCancel ? '<button class="btn-small btn-danger" onclick="cancelReservation(' + res.id + ')">Cancel</button>' : '-'}</td>
          </tr>`;
        });
        tbody.innerHTML = html;
      }

      // Cancel reservation
      function cancelReservation(reservationId) {
        if (!confirm('Are you sure you want to cancel this reservation?')) {
          return;
        }

        fetch('user_dashboard.php?action=cancel_reservation&id=' + reservationId)
          .then(res => res.json())
          .then(data => {
            alert(data.message);
            if (data.success) {
              loadUserReservations();
            }
          });
      }
    </script>
  </body>
</html>
