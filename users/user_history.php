<?php
// User History Page
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

$userJson = json_encode($currentUser);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Sit-In History - CCS Sit-In Monitoring System</title>
    <link rel="stylesheet" href="../style.css" />
    <script src="../script.js"></script>
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
        <a href="../index.php" class="logo-group"> 
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
        <a href="leaderboard.php" id="nav-leaderboard">Leaderboard</a>
        <a href="../index.php?section=user-home" id="nav-home">Home</a>
        <a href="../index.php?section=user-profile" id="nav-profile">Edit Profile</a>
        <a href="user_history.php" id="nav-history">History</a>
        <a href="user_reservation.php" id="nav-reservation">Reservation</a>
        <a href="../reg-log-prof/logout.php" id="nav-logout">Logout</a>
      </div>
    </nav>

    <!-- User History Page Content -->
    <div class="history-page">
      <div class="history-header">
        <h1>My Sit-In History</h1>
</div>
      
      <table class="data-table">
        <thead>
          <tr>
            <th onclick="sortHistory('id_number')">ID Number ↕</th>
            <th onclick="sortHistory('name')">Name ↕</th>
            <th>Purpose</th>
            <th>Lab</th>
            <th>Login (Time In)</th>
            <th>Logout (Time Out)</th>
            <th onclick="sortHistory('date')">Date ↕</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody id="history-table-body">
          <!-- History will be loaded here -->
        </tbody>
      </table>
    </div>

    <!-- Feedback Modal -->
    <div id="feedback-modal" class="modal" style="display: none;">
      <div class="modal-content">
        <span class="close" onclick="closeFeedbackModal()">&times;</span>
        <h2>Submit Feedback</h2>
        <p class="feedback-subtitle">We value your feedback about your sit-in experience</p>
        <div class="form-group">
          <label>Rating:</label>
          <div class="rating-stars">
            <span class="star" data-rating="1" onclick="setRating(1)">★</span>
            <span class="star" data-rating="2" onclick="setRating(2)">★</span>
            <span class="star" data-rating="3" onclick="setRating(3)">★</span>
            <span class="star" data-rating="4" onclick="setRating(4)">★</span>
            <span class="star" data-rating="5" onclick="setRating(5)">★</span>
          </div>
        </div>
        <div class="form-group">
          <label for="feedback-text">Your Feedback:</label>
          <textarea id="feedback-text" rows="4" placeholder="Tell us about your experience..."></textarea>
        </div>
        <button class="btn-primary" onclick="submitFeedback()">Submit Feedback</button>
        <div id="feedback-message"></div>
      </div>
    </div>

    <style>
      .modal {
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
      }
      .modal-content {
        background-color: #fff;
        margin: 10% auto;
        padding: 20px;
        border-radius: 8px;
        width: 400px;
        max-width: 90%;
      }
      .close {
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
      }
      .close:hover {
        color: #000;
      }
      .rating-stars {
        font-size: 24px;
        cursor: pointer;
      }
      .star {
        color: #ccc;
        transition: color 0.2s;
      }
      .star.active {
        color: #ffc107;
      }
      .form-group {
        margin-bottom: 15px;
      }
      .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
      }
      .form-group textarea {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        resize: vertical;
      }
      .feedback-subtitle {
        color: #666;
        font-size: 14px;
        margin-bottom: 20px;
      }
      #feedback-message {
        margin-top: 10px;
      }
      #feedback-message.success {
        color: green;
      }
      #feedback-message.error {
        color: red;
      }
    </style>

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
      }

      // Mark all notifications as read
      function markAllNotificationsRead() {
        fetch('user_dashboard.php?action=mark_all_notifications_read', {
          method: 'POST'
        })
        .then(res => res.json())
        .then(data => {
          console.log('Mark all read response:', data);
          if (data.success) {
            // Hide the badge
            const navBadge = document.getElementById('nav-notification-badge');
            if (navBadge) {
              navBadge.textContent = '0';
              navBadge.style.display = 'none';
            }
          }
        })
        .catch(err => console.error('Error marking notifications read:', err));
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
      let historyData = [];
      let sortDirection = 1; // 1 for ascending, -1 for descending
      
      function displayUserHistory(history) {
        historyData = history;
        const tbody = document.getElementById('history-table-body');
        if (!tbody) return;
        
        if (history.length === 0) {
          tbody.innerHTML = '<tr><td colspan="8">No records found</td></tr>';
          return;
        }
        
        renderHistoryTable(history);
      }
      
      function renderHistoryTable(data) {
        const tbody = document.getElementById('history-table-body');
        if (!tbody) return;
        
        let html = '';
        data.forEach(record => {
          const timeIn = record.time_in ? record.time_in.split(' ')[1] : '';
          const timeOut = record.time_out ? record.time_out.split(' ')[1] : 'Ongoing';
          html += `<tr>
            <td>${record.id_number}</td>
            <td>${record.name}</td>
            <td>${record.purpose}</td>
            <td>${record.lab}</td>
            <td>${timeIn}</td>
            <td>${timeOut}</td>
            <td>${record.date}</td>
            <td><button class="btn-primary" onclick="openFeedback(${record.id})">Feedback</button></td>
          </tr>`;
        });
        tbody.innerHTML = html;
      }
      
      // Sort history by column
      function sortHistory(column) {
        sortDirection *= -1;
        const sorted = [...historyData].sort((a, b) => {
          let valA = a[column];
          let valB = b[column];
          if (column === 'id_number' || column === 'date') {
            valA = valA || '';
            valB = valB || '';
            return valA.localeCompare(valB) * sortDirection;
          }
          if (typeof valA === 'string') {
            return valA.localeCompare(valB) * sortDirection;
          }
          return (valA > valB ? 1 : -1) * sortDirection;
        });
        renderHistoryTable(sorted);
      }
      
      // Open feedback modal
      let currentFeedbackRecordId = null;
      let currentRating = 5;
      
      function openFeedback(recordId) {
        currentFeedbackRecordId = recordId;
        currentRating = 5;
        document.getElementById('feedback-text').value = '';
        document.getElementById('feedback-message').textContent = '';
        document.getElementById('feedback-message').className = '';
        updateStars(5);
        document.getElementById('feedback-modal').style.display = 'block';
      }
      
      function closeFeedbackModal() {
        document.getElementById('feedback-modal').style.display = 'none';
      }
      
      function setRating(rating) {
        currentRating = rating;
        updateStars(rating);
      }
      
      function updateStars(rating) {
        const stars = document.querySelectorAll('.star');
        stars.forEach(star => {
          if (parseInt(star.dataset.rating) <= rating) {
            star.classList.add('active');
          } else {
            star.classList.remove('active');
          }
        });
      }
      
      function submitFeedback() {
        const feedbackText = document.getElementById('feedback-text').value.trim();
        
        if (!feedbackText) {
          document.getElementById('feedback-message').textContent = 'Please enter your feedback';
          document.getElementById('feedback-message').className = 'error';
          return;
        }
        
        fetch('user_dashboard.php?action=submit_feedback', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            feedback_text: feedbackText,
            rating: currentRating,
            sitin_record_id: currentFeedbackRecordId
          })
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            document.getElementById('feedback-message').textContent = 'Thank you for your feedback!';
            document.getElementById('feedback-message').className = 'success';
            setTimeout(() => {
              closeFeedbackModal();
            }, 1500);
          } else {
            document.getElementById('feedback-message').textContent = data.message || 'Error submitting feedback';
            document.getElementById('feedback-message').className = 'error';
          }
        })
        .catch(error => {
          document.getElementById('feedback-message').textContent = 'Error submitting feedback';
          document.getElementById('feedback-message').className = 'error';
        });
      }
      
      // Close modal when clicking outside
      window.onclick = function(event) {
        const modal = document.getElementById('feedback-modal');
        if (event.target === modal) {
          closeFeedbackModal();
        }
      }
    </script>
  </body>
</html>
