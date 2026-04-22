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

// Check if user has an active sit-in
$sitinCheckStmt = $pdo->prepare("SELECT id FROM sitin_records WHERE user_id = ? AND time_out IS NULL");
$sitinCheckStmt->execute([$_SESSION['user_id']]);
$hasActiveSitIn = $sitinCheckStmt->fetch() !== false;

// Check if user has a pending reservation
$reservationCheckStmt = $pdo->prepare("SELECT id FROM reservations WHERE user_id = ? AND status = 'pending'");
$reservationCheckStmt->execute([$_SESSION['user_id']]);
$hasPendingReservation = $reservationCheckStmt->fetch() !== false;
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
      let hasActiveSitIn = <?php echo $hasActiveSitIn ? 'true' : 'false'; ?>;
      let hasPendingReservation = <?php echo $hasPendingReservation ? 'true' : 'false'; ?>;
      let activeSitInData = null;
    </script>
    <style>
      .reservation-page {
        padding: 20px;
        max-width: 1200px;
        margin: 0 auto;
        position: relative;
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
      .alert-message {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        text-align: center;
        color: #333;
        font-size: 16px;
        border: 1px solid #ddd;
      }
      .info-display {
        background: #f8f9fa;
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 15px;
      }
      .pc-grid-container {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 20px;
      }
      .pc-grid-title {
        font-size: 18px;
        font-weight: bold;
        margin-bottom: 15px;
        color: #333;
      }
      .pc-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 10px;
        max-width: 800px;
        margin: 0 auto;
      }
      .pc-item {
        aspect-ratio: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        font-weight: bold;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s ease;
        border: 2px solid transparent;
      }
      .pc-item.available {
        background: #28a745;
        color: white;
      }
      .pc-item.available:hover {
        background: #218838;
        transform: scale(1.05);
      }
      .pc-item.occupied {
        background: #dc3545;
        color: white;
        cursor: not-allowed;
      }
      .pc-item.reserved {
        background: #ffc107;
        color: #333;
        cursor: not-allowed;
      }
      .pc-item.selected {
        border-color: #007bff;
        box-shadow: 0 0 0 3px rgba(0,123,255,0.3);
      }
      .pc-legend {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin-top: 15px;
        font-size: 14px;
      }
      .legend-item {
        display: flex;
        align-items: center;
        gap: 5px;
      }
      .legend-color {
        width: 20px;
        height: 20px;
        border-radius: 4px;
      }
      .legend-color.available {
        background: #28a745;
      }
      .legend-color.occupied {
        background: #dc3545;
      }
      .legend-color.reserved {
        background: #ffc107;
      }
      .legend-item span {
        color: #333;
      }
      .pc-details {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-top: 15px;
      }
      .pc-details p {
        margin: 5px 0;
        color: #333;
      }
      .selected-pc-info {
        background: #e7f3ff;
        padding: 10px;
        border-radius: 4px;
        margin-top: 10px;
        text-align: center;
        font-weight: bold;
        color: #007bff;
      }
      .pc-select-btn {
        padding: 8px 16px;
        background: #007bff;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
      }
      .pc-select-btn:hover {
        background: #0056b3;
      }
.pc-select-btn:disabled {
        background: #ccc;
        cursor: not-allowed;
      }

      .tab-btn {
        padding: 12px 20px;
        background: #f8f9fa;
        border: none;
        border-radius: 5px 5px 0 0;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        color: #6c757d;
        transition: all 0.2s ease;
        border-top: 3px solid transparent;
      }
      .tab-btn:hover {
        background: #e9ecef;
        color: #495057;
      }
      .tab-btn.active {
        background: #007bff;
        color: white;
        border-top: 3px solid #0056b3;
        box-shadow: 0 -2px 5px rgba(0, 123, 255, 0.2);
      }
      .tab-content {
        display: none;
      }
      .tab-content.active {
        display: block;
      }
      .log-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
      }
      .log-table th, .log-table td {
        padding: 10px 12px;
        text-align: left;
        border-bottom: 1px solid #dee2e6;
      }
      .log-table th {
        background: #f8f9fa;
        font-weight: 600;
        color: #495057;
      }
      .log-table tr:hover {
        background: #f8f9fa;
      }
      .status-badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
      }
      .status-pending { background: #fff3cd; color: #856404; }
      .status-approved { background: #d4edda; color: #155724; }
      .status-active { background: #cce5ff; color: #004085; }
      .status-denied { background: #f8d7da; color: #721c24; }
      .status-completed { background: #e2e3e5; color: #383d41; }

      /* Modal Styles */
      .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        justify-content: center;
        align-items: center;
      }
      .modal-overlay.active {
        display: flex;
      }
      .modal-content {
        background: white;
        border-radius: 10px;
        padding: 20px;
        max-width: 600px;
        width: 95%;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
      }
      .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        padding-bottom: 8px;
        border-bottom: 1px solid #dee2e6;
      }
      .modal-header h3 {
        margin: 0;
        color: #333;
        font-size: 16px;
      }
      .modal-close {
        background: none;
        border: none;
        font-size: 20px;
        color: #666;
        cursor: pointer;
        padding: 0;
        line-height: 1;
      }
      .modal-close:hover {
        color: #333;
      }
      .pc-grid-modal {
        display: grid;
        grid-template-columns: repeat(8, 1fr);
        gap: 4px;
        margin-bottom: 10px;
      }
      .pc-item-modal {
        aspect-ratio: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 5px;
        font-weight: bold;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s ease;
        border: 2px solid transparent;
        padding: 4px;
        min-width: 45px;
        min-height: 45px;
      }
      .pc-item-modal.available {
        background: #28a745;
        color: white;
        font-size: 11px;
      }
      .pc-item-modal.occupied {
        background: #dc3545;
        color: white;
        cursor: not-allowed;
      }
      .pc-item-modal.reserved {
        background: #ffc107;
        color: #333;
        cursor: not-allowed;
      }
      .pc-item-modal.selected {
        border-color: #007bff;
        box-shadow: 0 0 10px rgba(0, 123, 255, 0.5);
      }
      .pc-legend-modal {
        display: flex;
        gap: 15px;
        justify-content: center;
        margin-bottom: 10px;
        font-size: 12px;
      }
      .legend-item-modal {
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 11px;
      }
      .legend-color-modal {
        width: 14px;
        height: 14px;
        border-radius: 3px;
      }
      .modal-footer {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
        padding-top: 10px;
        border-top: 1px solid #dee2e6;
      }
      .modal-cancel {
        padding: 6px 12px;
        background: #6c757d;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
      }
      .modal-cancel:hover {
        background: #5a6268;
      }
      .modal-confirm {
        padding: 6px 12px;
        background: #007bff;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
      }
      .modal-confirm:hover {
        background: #0056b3;
      }
      .modal-confirm:disabled {
        background: #ccc;
        cursor: not-allowed;
      }

      /* Floating Action Button Timer */
      .timer-fab {
        position: fixed;
        bottom: 80px;
        right: 30px;
        background: #007bff;
        color: white;
        padding: 15px 20px;
        border-radius: 50px;
        box-shadow: 0 4px 15px rgba(0, 123, 255, 0.4);
        z-index: 1000;
        display: none;
        align-items: center;
        gap: 10px;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
      }
      .timer-fab.active {
        display: flex;
      }
      .timer-fab.expiring {
        background: #dc3545;
        box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4);
        animation: pulse 1s infinite;
      }
      .timer-fab-label {
        font-size: 12px;
        font-weight: normal;
        opacity: 0.9;
      }
      .timer-fab-time {
        font-size: 18px;
        font-family: monospace;
      }
      @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
      }
      
      /* Reservation Started Banner */
      .reservation-started-banner {
        position: fixed;
        top: 100px;
        left: 50%;
        transform: translateX(-50%);
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        padding: 20px 30px;
        border-radius: 10px;
        box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
        z-index: 10000;
        max-width: 600px;
        width: 90%;
        text-align: center;
        animation: slideDown 0.5s ease-out;
      }
      
      .reservation-started-banner h3 {
        margin: 0 0 10px 0;
        font-size: 22px;
        font-weight: bold;
      }
      
      .reservation-started-banner p {
        margin: 0;
        font-size: 16px;
        line-height: 1.5;
      }
      
      .reservation-started-banner .close-banner {
        position: absolute;
        top: 10px;
        right: 15px;
        background: none;
        border: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
        opacity: 0.8;
      }
      
      .reservation-started-banner .close-banner:hover {
        opacity: 1;
      }
      
      @keyframes slideDown {
        from {
          top: -100px;
          opacity: 0;
        }
        to {
          top: 100px;
          opacity: 1;
        }
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

    <!-- User Reservation Page Content -->
    <div class="reservation-page">
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
      </div>

      <!-- Tab Navigation -->
      <div style="display: flex; gap: 5px; margin-bottom: 15px;">
        <button class="tab-btn active" id="tab-instant" onclick="switchTab('instant')">Instant Sit-In</button>
        <button class="tab-btn" id="tab-reserve" onclick="switchTab('reserve')">Make Reservation</button>
        <button class="tab-btn" id="tab-log" onclick="switchTab('log')">Reservation History</button>
      </div>

      <!-- Instant Sit-In Tab Content -->
      <div class="tab-content active" id="tab-content-instant">
        <!-- Sit-In Form Section -->
        <div class="form-section" id="sitin-section">
          <h2>Instant Sit-In</h2>
          <form id="sitin-form" onsubmit="submitSitIn(event)">
            <div class="form-row">
              <div class="form-group">
                <label for="sitin-purpose">Purpose:</label>
                <input type="text" id="sitin-purpose" placeholder="Enter purpose" required />
              </div>
              <div class="form-group">
                <label for="sitin-lab">Laboratory:</label>
                <select id="sitin-lab" onchange="handleSitInLabChange()" required style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px; box-sizing: border-box; background: white; cursor: pointer;">
                  <option value="">Select Laboratory</option>
                  <option value="524">Lab 524</option>
                  <option value="526">Lab 526</option>
                  <option value="528">Lab 528</option>
                  <option value="530">Lab 530</option>
                  <option value="MAC">MAC Lab</option>
                </select>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Select PC:</label>
                <button type="button" id="sitin-select-pc-btn" class="pc-select-btn" onclick="openSitInPcModal()">Select a PC</button>
                <div class="selected-pc-info" id="sitin-selected-pc-info"></div>
              </div>
            </div>
            <button type="submit" class="btn-primary">Submit</button>
          </form>
          <div class="sessions-display">
            <div>Remaining Sessions</div>
            <div class="sessions-count" id="remaining-sessions"><?php echo $remainingSessions; ?></div>
          </div>
        </div>
      </div>

      <!-- Make a Reservation Tab Content -->
      <div class="tab-content" id="tab-content-reserve">
        <!-- Reservation Section with PC Grid -->
        <div class="form-section" id="reservation-section">
          <h2>Make a Reservation</h2>
          <div class="form-row">
            <div class="form-group">
              <label for="reservation-lab-select">Select Laboratory:</label>
              <select id="reservation-lab-select" onchange="handleLabChange()" style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px; box-sizing: border-box; background: white; cursor: pointer;">
                <option value="">Select Laboratory</option>
                <option value="524">Lab 524</option>
                <option value="526">Lab 526</option>
                <option value="528">Lab 528</option>
                <option value="530">Lab 530</option>
                <option value="MAC">MAC Lab</option>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Select PC:</label>
              <button type="button" id="select-pc-btn" class="pc-select-btn" onclick="openPcModal()">Select a PC</button>
              <div class="selected-pc-info" id="selected-pc-info"></div>
            </div>
          </div>

          <form id="reservation-form" onsubmit="submitReservation(event)" style="margin-top: 20px;">
            <input type="hidden" id="reservation-lab" />
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
                <label for="reservation-purpose">Purpose:</label>
                <input type="text" id="reservation-purpose" placeholder="Enter purpose" required />
              </div>
            </div>
            <button type="submit" class="btn-primary" onclick="submitReservation(event)">Reserve</button>
          </form>
        </div>
      </div>

      <!-- Reservation Log Tab Content -->
      <div class="tab-content" id="tab-content-log">
        <div class="form-section">
          <h2>Reservation History</h2>
          <table class="log-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Laboratory</th>
                <th>PC</th>
                <th>Purpose</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="log-table-body">
              <tr>
                <td colspan="6" style="text-align: center;">Loading...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Timer Floating Action Button -->
    <div class="timer-fab" id="timer-fab" onclick="toggleTimerDetails()">
      <div>
        <div class="timer-fab-label">Sit-In Timer</div>
        <div class="timer-fab-time" id="timer-fab-time">--:--</div>
      </div>
    </div>

    <!-- PC Selection Modal -->
    <div class="modal-overlay" id="pc-modal">
      <div class="modal-content">
        <div class="modal-header">
          <h3>Select a PC - <span id="modal-lab-name"></span></h3>
          <button class="modal-close" onclick="closePcModal()">&times;</button>
        </div>
        <div id="modal-pc-grid" class="pc-grid-modal">
          <p>Select a laboratory first</p>
        </div>
        <div class="pc-legend-modal">
          <div class="legend-item-modal">
            <div class="legend-color available" style="width: 20px; height: 20px; border-radius: 4px; background: #28a745;"></div>
            <span style="color: #333;">Available</span>
          </div>
          <div class="legend-item-modal">
            <div class="legend-color occupied" style="width: 20px; height: 20px; border-radius: 4px; background: #dc3545;"></div>
            <span style="color: #333;">Unavailable</span>
          </div>
          <div class="legend-item-modal">
            <div class="legend-color reserved" style="width: 20px; height: 20px; border-radius: 4px; background: #ffc107;"></div>
            <span style="color: #333;">Reserved</span>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="modal-cancel" onclick="closePcModal()">Cancel</button>
          <button type="button" class="modal-confirm" id="modal-confirm-btn" onclick="confirmPcSelection()" disabled>Confirm Selection</button>
        </div>
      </div>
    </div>

    <!-- Sit-In PC Selection Modal -->
    <div class="modal-overlay" id="sitin-pc-modal">
      <div class="modal-content">
        <div class="modal-header">
          <h3>Select a PC - <span id="sitin-modal-lab-name"></span></h3>
          <button class="modal-close" onclick="closeSitInPcModal()">&times;</button>
        </div>
        <div id="sitin-modal-pc-grid" class="pc-grid-modal">
          <p>Select a laboratory first</p>
        </div>
        <div class="pc-legend-modal">
          <div class="legend-item-modal">
            <div class="legend-color available" style="width: 20px; height: 20px; border-radius: 4px; background: #28a745;"></div>
            <span style="color: #333;">Available</span>
          </div>
          <div class="legend-item-modal">
            <div class="legend-color occupied" style="width: 20px; height: 20px; border-radius: 4px; background: #dc3545;"></div>
            <span style="color: #333;">Unavailable</span>
          </div>
          <div class="legend-item-modal">
            <div class="legend-color reserved" style="width: 20px; height: 20px; border-radius: 4px; background: #ffc107;"></div>
            <span style="color: #333;">Reserved</span>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="modal-cancel" onclick="closeSitInPcModal()">Cancel</button>
          <button type="button" class="modal-confirm" id="sitin-modal-confirm-btn" onclick="confirmSitInPcSelection()" disabled>Confirm Selection</button>
        </div>
      </div>
    </div>

<script>
      // Tab switching
      let currentTab = 'instant';
      
      function switchTab(tabName) {
        // Don't reload if clicking the same tab
        if (currentTab === tabName) {
          return;
        }
        
        currentTab = tabName;
        
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        document.getElementById('tab-' + tabName).classList.add('active');
        document.getElementById('tab-content-' + tabName).classList.add('active');
        
        if (tabName === 'log') {
          loadReservationLog();
        }
      }

      // Load Reservation Log
      function loadReservationLog() {
        console.log('loadReservationLog called');
        fetch('user_dashboard.php?action=reservations')
          .then(res => res.json())
          .then(data => {
            console.log('Reservation data:', data);
            if (data.success) {
              displayReservationLog(data.reservations);
            } else {
              console.log('Error:', data.message);
            }
          })
          .catch(err => console.error('Error:', err));
      }

      // Display Reservation Log
      function displayReservationLog(reservations) {
        console.log('displayReservationLog called with:', reservations);
        const tbody = document.getElementById('log-table-body');
        console.log('tbody element:', tbody);
        
        if (reservations.length === 0) {
          tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">No reservations found</td></tr>';
          return;
        }
        
        tbody.innerHTML = reservations.map(res => {
          let statusClass = 'status-' + res.status;
          let statusText = res.status.charAt(0).toUpperCase() + res.status.slice(1);
          return `
            <tr>
              <td>${res.reservation_date}</td>
              <td>${res.start_time} - ${res.end_time}</td>
              <td>Lab ${res.lab_number}</td>
              <td>${res.pc_number ? 'PC ' + res.pc_number : '-'}</td>
              <td>${res.purpose}</td>
              <td><span class="status-badge ${statusClass}">${statusText}</span></td>
            </tr>
          `;
        }).join('');
      }

      // Initialize on page load
      document.addEventListener('DOMContentLoaded', function() {
        // Set initial tab state without calling switchTab
        currentTab = 'instant';
        
        loadNavNotifications();
        loadUserReservations();
        loadRemainingSessions();
        
        // Preload reservation log data so it's ready when user clicks the tab
        loadReservationLog();
        
        // Initialize notification count
        lastNotificationCount = 0;
        
        // Poll for new notifications every 30 seconds
        setInterval(loadNavNotifications, 30000);
        
        // Poll for reservation updates every 15 seconds (checks if reservations should auto-start)
        setInterval(function() {
          loadReservationLog();
          loadRemainingSessions();
          loadNavNotifications(); // Check for new notifications (reservation started)
          
          // Check if there's an active sit-in that might have started from a reservation
          if (hasActiveSitIn) {
            fetchActiveSitInDetails();
          }
        }, 15000);
        
        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('reservation-date').setAttribute('min', today);

        // Set current time as default for reservation time
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        document.getElementById('reservation-time').value = hours + ':' + minutes;

        // Disable PC select buttons initially
        document.getElementById('select-pc-btn').disabled = true;
        document.getElementById('sitin-select-pc-btn').disabled = true;

        // Apply restrictions based on current status
        applyRestrictions();

        // Initialize sit-in timer if there's an active sit-in
        if (hasActiveSitIn) {
          fetchActiveSitInDetails();
        }
      });

      let currentSitInDetails = null;
      let timerInterval = null;

      function fetchActiveSitInDetails() {
        fetch('user_dashboard.php?action=get_active_sitin')
          .then(res => res.json())
          .then(data => {
            if (data.success && data.sitin) {
              currentSitInDetails = data.sitin;
              initSitInTimer(currentSitInDetails);
            }
          })
          .catch(err => console.error('Error fetching sit-in details:', err));
      }

      function initSitInTimer(sitInData) {
        const fab = document.getElementById('timer-fab');
        const timeDisplay = document.getElementById('timer-fab-time');
        
        if (!fab || !timeDisplay) return;

        fab.classList.add('active');
        
        let remainingSeconds = sitInData.remaining_seconds;
        
        updateTimerDisplay(remainingSeconds);
        
        timerInterval = setInterval(function() {
          remainingSeconds--;
          
          if (remainingSeconds <= 0) {
            clearInterval(timerInterval);
            timeDisplay.textContent = '00:00';
            fab.classList.add('expiring');
            return;
          }
          
          updateTimerDisplay(remainingSeconds);
          
          if (remainingSeconds <= 300) {
            fab.classList.add('expiring');
          }
        }, 1000);
      }

      function updateTimerDisplay(seconds) {
        const timeDisplay = document.getElementById('timer-fab-time');
        const fab = document.getElementById('timer-fab');
        
        if (!timeDisplay) return;
        
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        timeDisplay.textContent = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
      }

      function toggleTimerDetails() {
        if (currentSitInDetails) {
          const pcInfo = currentSitInDetails.pc_number ? 'PC ' + currentSitInDetails.pc_number : 'Not assigned';
          alert('Active Sit-In\nLab: ' + currentSitInDetails.lab_number + '\nPC: ' + pcInfo + '\nStarted: ' + currentSitInDetails.time_in + '\nEnds: ' + currentSitInDetails.end_time);
        }
      }

      function applyRestrictions() {
        // If user has active sit-in, disable instant sit-in
        if (hasActiveSitIn) {
          const sitinSection = document.getElementById('sitin-section');
          sitinSection.innerHTML = '<div class="alert-message">You currently have an active sit-in. Please end it before starting a new one.</div>';
        }

        // If user has pending reservation, disable reservation form
        if (hasPendingReservation) {
          const reservationSection = document.getElementById('reservation-section');
          reservationSection.innerHTML = '<div class="alert-message">You already have a pending reservation. Please wait for it to be completed or cancelled before making another.</div>';
        }
      }

      // Load remaining sessions
      function loadRemainingSessions() {
        fetch('user_dashboard.php?action=remaining_sessions')
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              remainingSessions = data.remaining_sessions;
              document.getElementById('remaining-sessions').textContent = remainingSessions;
            }
          })
          .catch(err => console.error('Error loading sessions:', err));
      }

      // Load notifications for navigation dropdown
      let lastNotificationCount = 0;
      
      function showReservationStartedBanner(message) {
        // Remove existing banner if any
        const existingBanner = document.querySelector('.reservation-started-banner');
        if (existingBanner) {
          existingBanner.remove();
        }
        
        // Create banner
        const banner = document.createElement('div');
        banner.className = 'reservation-started-banner';
        banner.innerHTML = `
          <button class="close-banner" onclick="this.parentElement.remove()">&times;</button>
          <h3>🎉 Your Reservation Has Started!</h3>
          <p>${message}</p>
        `;
        
        document.body.appendChild(banner);
        
        // Auto-remove after 10 seconds
        setTimeout(() => {
          if (banner.parentElement) {
            banner.remove();
          }
        }, 10000);
      }
      
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
              
              // Check if there are new notifications (potential reservation start)
              if (data.unread_count > lastNotificationCount && lastNotificationCount > 0) {
                // Check for reservation started notification
                const reservationStarted = data.notifications.find(n => 
                  n.title && n.title.includes('Reservation Started') && !n.is_read
                );
                
                if (reservationStarted) {
                  // Show beautiful notification banner
                  showReservationStartedBanner(reservationStarted.message);
                  
                  // Update the active sit-in flag and reload page to show sit-in
                  hasActiveSitIn = true;
                  hasPendingReservation = false;
                  
                  // Refresh active sit-in details
                  fetchActiveSitInDetails();
                }
              }
              
              lastNotificationCount = data.unread_count;
            }
          })
          .catch(err => console.error('Error loading notifications:', err));
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

        // Close modals when clicking outside
        const pcModal = document.getElementById('pc-modal');
        const sitinPcModal = document.getElementById('sitin-pc-modal');
        if (pcModal && !pcModal.contains(event.target) && event.target.classList.contains('modal-overlay')) {
          pcModal.classList.remove('active');
        }
        if (sitinPcModal && !sitinPcModal.contains(event.target) && event.target.classList.contains('modal-overlay')) {
          sitinPcModal.classList.remove('active');
        }
      });

      // Submit Sit-In
      function submitSitIn(event) {
        event.preventDefault();
        
        const purpose = document.getElementById('sitin-purpose').value;
        const lab = document.getElementById('sitin-lab').value;
        const pcNumber = sitInSelectedPc;

        if (!purpose || !lab) {
          alert('Please fill in all fields');
          return;
        }

        fetch('user_dashboard.php?action=start_sitin', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            lab: lab,
            purpose: purpose,
            pc_number: pcNumber
          })
        })
        .then(res => res.json())
        .then(data => {
          alert(data.message);
          if (data.success) {
            document.getElementById('sitin-form').reset();
            sitInSelectedPc = null;
            document.getElementById('sitin-selected-pc-info').textContent = '';
            document.getElementById('sitin-select-pc-btn').textContent = 'Select a PC';
            loadRemainingSessions();
            
            // Update the active sit-in flag and apply restrictions
            hasActiveSitIn = true;
            applyRestrictions();
          }
        });
      }

      // Sit-In PC Modal Functions
      function handleSitInLabChange() {
        const labSelect = document.getElementById('sitin-lab');
        const lab = labSelect.value;
        const btn = document.getElementById('sitin-select-pc-btn');
        
        if (!lab) {
          sitInSelectedPc = null;
          document.getElementById('sitin-selected-pc-info').textContent = '';
          btn.disabled = true;
          return;
        }

        btn.disabled = false;
        btn.textContent = 'Select a PC';
        sitInSelectedPc = null;
        document.getElementById('sitin-selected-pc-info').textContent = '';
      }

      function openSitInPcModal() {
        console.log('openSitInPcModal called');
        const lab = document.getElementById('sitin-lab').value;
        if (!lab) {
          alert('Please select a laboratory first');
          return;
        }

        const modal = document.getElementById('sitin-pc-modal');
        console.log('Modal element:', modal);
        const labName = document.getElementById('sitin-modal-lab-name');
        const pcGrid = document.getElementById('sitin-modal-pc-grid');
        
        labName.textContent = 'Lab ' + lab;
        pcGrid.innerHTML = '<p>Loading...</p>';
        modal.classList.add('active');
        console.log('Modal should now be active');

        fetch('user_dashboard.php?action=get_lab_pc_status&lab=' + lab)
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              renderSitInModalPcGrid(data.pcs);
            } else {
              pcGrid.innerHTML = '<p>Error: ' + (data.message || 'Unknown error') + '</p>';
            }
          })
          .catch(err => {
            console.error('Fetch error:', err);
            pcGrid.innerHTML = '<p>Error loading PC status. Please try again.</p>';
          });
      }

      function closeSitInPcModal() {
        document.getElementById('sitin-pc-modal').classList.remove('active');
      }

      function renderSitInModalPcGrid(pcs) {
        const grid = document.getElementById('sitin-modal-pc-grid');
        grid.innerHTML = '';

        pcs.forEach(pc => {
          const pcDiv = document.createElement('div');
          pcDiv.className = 'pc-item-modal ' + pc.status;
          pcDiv.textContent = pc.pc_number;
          pcDiv.dataset.pcNumber = pc.pc_number;
          
          if (pc.status === 'available') {
            pcDiv.onclick = () => selectSitInPcInModal(pc.pc_number);
          }
          
          grid.appendChild(pcDiv);
        });
      }

      function selectSitInPcInModal(pcNumber) {
        document.querySelectorAll('#sitin-modal-pc-grid .pc-item-modal').forEach(el => el.classList.remove('selected'));
        
        const pcElements = document.querySelectorAll('#sitin-modal-pc-grid .pc-item-modal');
        pcElements.forEach(el => {
          if (el.dataset.pcNumber == pcNumber) {
            el.classList.add('selected');
          }
        });

        sitInSelectedPc = pcNumber;
        document.getElementById('sitin-modal-confirm-btn').disabled = false;
      }

      function confirmSitInPcSelection() {
        if (!sitInSelectedPc) {
          alert('Please select a PC');
          return;
        }

        document.getElementById('sitin-selected-pc-info').textContent = 'Selected PC: ' + sitInSelectedPc;
        document.getElementById('sitin-select-pc-btn').textContent = 'Change PC (' + sitInSelectedPc + ')';
        closeSitInPcModal();
      }

      // Submit Reservation
      function submitReservation(event) {
        event.preventDefault();
        
        const lab = document.getElementById('reservation-lab').value;
        const date = document.getElementById('reservation-date').value;
        const startTime = document.getElementById('reservation-time').value;
        const purpose = document.getElementById('reservation-purpose').value;
        const pcNumber = selectedPc;

        if (!lab || !date || !startTime || !purpose) {
          alert('Please fill in all fields');
          return;
        }

        if (!pcNumber) {
          alert('Please select a PC from the grid');
          return;
        }

        const endTime = calculateEndTime(startTime, 2);

        fetch('user_dashboard.php?action=reserve_pc', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            lab: lab,
            pc_number: pcNumber,
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
            document.getElementById('reservation-form').reset();
            selectedPc = null;
            document.getElementById('selected-pc-info').textContent = '';
            document.getElementById('select-pc-btn').textContent = 'Select a PC';
            // Also reset the lab select
            document.getElementById('reservation-lab-select').value = '';
            document.getElementById('reservation-lab').value = '';
            document.getElementById('select-pc-btn').disabled = true;
            
            // Update the pending reservation flag and apply restrictions
            hasPendingReservation = true;
            applyRestrictions();
            
            // Refresh the reservation log to show the new reservation
            loadReservationLog();
          }
        });
      }

      let selectedPc = null;
      let sitInSelectedPc = null;

      function calculateEndTime(startTime, hoursToAdd) {
        const [hours, minutes] = startTime.split(':').map(Number);
        const date = new Date();
        date.setHours(hours, minutes, 0, 0);
        date.setHours(date.getHours() + hoursToAdd);
        return date.getHours().toString().padStart(2, '0') + ':' + date.getMinutes().toString().padStart(2, '0');
      }

      function handleLabChange() {
        const labSelect = document.getElementById('reservation-lab-select');
        const lab = labSelect.value;
        const btn = document.getElementById('select-pc-btn');
        
        if (!lab) {
          selectedPc = null;
          document.getElementById('selected-pc-info').textContent = '';
          document.getElementById('reservation-lab').value = '';
          btn.disabled = true;
          return;
        }

        document.getElementById('reservation-lab').value = lab;
        btn.disabled = false;
        btn.textContent = 'Select a PC';
        selectedPc = null;
        document.getElementById('selected-pc-info').textContent = '';
      }

      function openPcModal() {
        console.log('openPcModal called');
        const lab = document.getElementById('reservation-lab-select').value;
        if (!lab) {
          alert('Please select a laboratory first');
          return;
        }

        const modal = document.getElementById('pc-modal');
        console.log('Modal element:', modal);
        const labName = document.getElementById('modal-lab-name');
        const pcGrid = document.getElementById('modal-pc-grid');
        
        labName.textContent = 'Lab ' + lab;
        pcGrid.innerHTML = '<p>Loading...</p>';
        modal.classList.add('active');
        console.log('Modal should now be active');

        fetch('user_dashboard.php?action=get_lab_pc_status&lab=' + lab)
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              renderModalPcGrid(data.pcs);
            } else {
              pcGrid.innerHTML = '<p>Error: ' + (data.message || 'Unknown error') + '</p>';
            }
          })
          .catch(err => {
            console.error('Fetch error:', err);
            pcGrid.innerHTML = '<p>Error loading PC status. Please try again.</p>';
          });
      }

      function closePcModal() {
        document.getElementById('pc-modal').classList.remove('active');
      }

      function renderModalPcGrid(pcs) {
        const grid = document.getElementById('modal-pc-grid');
        grid.innerHTML = '';

        pcs.forEach(pc => {
          const pcDiv = document.createElement('div');
          pcDiv.className = 'pc-item-modal ' + pc.status;
          pcDiv.textContent = pc.pc_number;
          pcDiv.dataset.pcNumber = pc.pc_number;
          
          if (pc.status === 'available') {
            pcDiv.onclick = () => selectPcInModal(pc.pc_number);
          }
          
          grid.appendChild(pcDiv);
        });
      }

      function selectPcInModal(pcNumber) {
        document.querySelectorAll('.pc-item-modal').forEach(el => el.classList.remove('selected'));
        
        const pcElements = document.querySelectorAll('.pc-item-modal');
        pcElements.forEach(el => {
          if (el.dataset.pcNumber == pcNumber) {
            el.classList.add('selected');
          }
        });

        selectedPc = pcNumber;
        document.getElementById('modal-confirm-btn').disabled = false;
      }

      function confirmPcSelection() {
        if (!selectedPc) {
          alert('Please select a PC');
          return;
        }

        document.getElementById('selected-pc-info').textContent = 'Selected PC: ' + selectedPc;
        document.getElementById('select-pc-btn').textContent = 'Change PC (' + selectedPc + ')';
        closePcModal();
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
            <td>${res.pc_number || 'N/A'}</td>
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
