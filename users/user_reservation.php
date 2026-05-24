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
$userStmt = $pdo->prepare("SELECT id_number, lastname, firstname, middlename, course, level, email, address, can_reserve FROM users WHERE id = ?");
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

$canReserve = (bool)$user['can_reserve'];

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
    <link rel="icon" href="../imgs/ccslogo.png" type="image/png" />
    <link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>" />
    <script src="../script.js"></script>
    <script>
      const phpUser = <?php echo $userJson; ?>;
      if (phpUser) {
        currentUser = phpUser;
      }
let remainingSessions = <?php echo $remainingSessions; ?>;
       let hasActiveSitIn = <?php echo $hasActiveSitIn ? 'true' : 'false'; ?>;
       let hasPendingReservation = <?php echo $hasPendingReservation ? 'true' : 'false'; ?>;
       let canReserve = <?php echo $canReserve ? 'true' : 'false'; ?>;
       let activeSitInData = null;
    </script>
    <style>
      /* Premium Slate-Indigo Styling Schema */
      :root {
        --slate-50: #f8fafc;
        --slate-100: #f1f5f9;
        --slate-200: #e2e8f0;
        --slate-300: #cbd5e1;
        --slate-400: #94a3b8;
        --slate-600: #475569;
        --slate-700: #334155;
        --slate-800: #1e293b;
        --slate-900: #0f172a;
        --indigo-50: #eef2ff;
        --indigo-100: #e0e7ff;
        --indigo-600: #4f46e5;
        --indigo-700: #4338ca;
        --indigo-800: #3730a3;
        --emerald-500: #10b981;
        --rose-500: #ef4444;
        --amber-500: #f59e0b;
        --transition-all: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
      }

      body {
        background-color: var(--slate-50);
        color: var(--slate-800);
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
      }

      .reservation-page {
        padding: 30px 24px;
        max-width: 1280px;
        margin: 0 auto;
      }

      /* Dashboard Welcome Hero */
      .hero-banner-card {
        background: linear-gradient(135deg, var(--slate-900) 0%, #1e1b4b 100%);
        color: white;
        border-radius: 16px;
        padding: 28px 36px;
        margin-bottom: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.15), 0 8px 10px -6px rgba(15, 23, 42, 0.15);
        border: 1px solid rgba(255, 255, 255, 0.05);
        position: relative;
        overflow: hidden;
      }
      .hero-banner-card::before {
        content: '';
        position: absolute;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(79, 70, 229, 0.12) 0%, transparent 70%);
        top: -100px;
        right: -50px;
        border-radius: 50%;
      }
      .hero-welcome h2 {
        margin: 0 0 8px 0;
        font-size: 1.75rem;
        font-weight: 800;
        letter-spacing: -0.025em;
      }
      .hero-welcome p {
        margin: 0;
        color: var(--slate-300);
        font-size: 0.95rem;
      }
      .hero-status-pill {
        display: flex;
        align-items: center;
        gap: 8px;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.1);
        padding: 6px 14px;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
      }
      .pulse-indicator {
        width: 8px;
        height: 8px;
        background-color: var(--emerald-500);
        border-radius: 50%;
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
        animation: pulseWave 2s infinite;
      }
      @keyframes pulseWave {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
      }

      /* Responsive Grid Layout */
      .reservation-grid {
        display: grid;
        grid-template-columns: 1.6fr 1fr;
        gap: 28px;
      }
      @media (max-width: 992px) {
        .reservation-grid {
          grid-template-columns: 1fr;
        }
      }

      /* Premium Card Base */
      .card-premium {
        background: white;
        border: 1px solid var(--slate-200);
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
        margin-bottom: 24px;
        transition: var(--transition-all);
      }
      .card-premium:hover {
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.04), 0 4px 6px -2px rgba(0, 0, 0, 0.02);
      }
      .card-title-premium {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--slate-900);
        margin-top: 0;
        margin-bottom: 20px;
        border-bottom: 1px solid var(--slate-100);
        padding-bottom: 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
      }

      /* Toggle switch wrapper */
      .toggle-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 18px;
        background: var(--slate-100);
        border-radius: 12px;
        margin-bottom: 20px;
        border: 1px solid var(--slate-200);
      }
      .toggle-info strong {
        display: block;
        font-size: 0.95rem;
        color: var(--slate-900);
      }
      .toggle-info span {
        font-size: 0.8rem;
        color: var(--slate-600);
      }

      /* Premium Tab Navigation */
      .tab-panel-nav {
        display: flex;
        background: var(--slate-100);
        padding: 6px;
        border-radius: 12px;
        gap: 4px;
        margin-bottom: 24px;
        border: 1px solid var(--slate-200);
      }
      .tab-trigger-btn {
        flex: 1;
        padding: 10px 16px;
        background: transparent;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--slate-600);
        transition: var(--transition-all);
        text-align: center;
      }
      .tab-trigger-btn:hover {
        color: var(--slate-900);
        background: rgba(255,255,255,0.5);
      }
      .tab-trigger-btn.active {
        background: white;
        color: var(--indigo-600);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
      }

      /* Forms and Controls */
      .form-group-premium {
        margin-bottom: 18px;
        display: flex;
        flex-direction: column;
        gap: 6px;
      }
      .form-group-premium label {
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--slate-700);
        text-transform: uppercase;
        letter-spacing: 0.05em;
      }
      .form-control-premium {
        width: 100%;
        padding: 12px 14px;
        border: 2px solid var(--slate-200);
        border-radius: 10px;
        font-size: 0.95rem;
        font-family: inherit;
        color: var(--slate-800);
        background: white;
        outline: none;
        box-sizing: border-box;
        transition: var(--transition-all);
        cursor: pointer;
      }
      .form-control-premium:focus {
        border-color: var(--indigo-600);
        box-shadow: 0 0 0 4px var(--indigo-100);
      }

      /* Embedded PC Grid Map container */
      .embedded-map-card {
        border: 2px solid var(--slate-200);
        border-radius: 14px;
        padding: 20px;
        margin-top: 15px;
        margin-bottom: 20px;
        background: var(--slate-50);
        display: none; /* Controlled via JS when Lab is selected */
        animation: slideDownFade 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      }
      @keyframes slideDownFade {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
      }
      .embedded-map-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--slate-900);
        margin-bottom: 12px;
        text-align: center;
        text-transform: uppercase;
        letter-spacing: 0.05em;
      }
      .pc-grid-embedded {
        display: grid;
        grid-template-columns: repeat(8, 1fr);
        gap: 6px;
        max-width: 500px;
        margin: 0 auto 15px auto;
      }
      @media (max-width: 480px) {
        .pc-grid-embedded {
          grid-template-columns: repeat(6, 1fr);
        }
      }
      .pc-node-embedded {
        aspect-ratio: 1;
        background: var(--slate-200);
        color: var(--slate-700);
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 11px;
        cursor: pointer;
        transition: var(--transition-all);
        border: 2px solid transparent;
        user-select: none;
      }
      .pc-node-embedded.available {
        background: #d1fae5;
        color: #065f46;
      }
      .pc-node-embedded.available:hover {
        background: #10b981;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2);
      }
      .pc-node-embedded.occupied {
        background: #fee2e2;
        color: #991b1b;
      }
      .pc-node-embedded.occupied:hover {
        background: #ef4444;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.2);
      }
      .pc-node-embedded.reserved {
        background: #fef3c7;
        color: #92400e;
      }
      .pc-node-embedded.reserved:hover {
        background: #f59e0b;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(245, 158, 11, 0.2);
      }
      .pc-node-embedded.selected {
        border-color: var(--indigo-600) !important;
        background: var(--indigo-600) !important;
        color: white !important;
        transform: scale(1.1) translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.3) !important;
      }

      /* Map Legend */
      .embedded-map-legend {
        display: flex;
        justify-content: center;
        gap: 16px;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--slate-600);
        margin-top: 10px;
      }
      .legend-pill {
        display: flex;
        align-items: center;
        gap: 6px;
      }
      .legend-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
      }
      .legend-dot.available { background-color: var(--emerald-500); }
      .legend-dot.occupied { background-color: var(--rose-500); }
      .legend-dot.reserved { background-color: var(--amber-500); }

      /* PC Details Drawer */
      .pc-details-drawer {
        margin-top: 15px;
        border-top: 1px dashed var(--slate-200);
        padding-top: 15px;
        display: none; /* Appears when PC is selected */
        animation: slideDownFade 0.25s ease;
      }
      .slots-list-embedded {
        display: grid;
        grid-template-columns: 1fr;
        gap: 8px;
        margin-top: 10px;
      }

      /* Radial Stats Gauge Card */
      .session-stats-card {
        background: white;
        border: 1px solid var(--slate-200);
        border-radius: 16px;
        padding: 24px;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
      }
      .session-gauge-wrapper {
        position: relative;
        width: 140px;
        height: 140px;
        margin-bottom: 16px;
      }
      .session-gauge svg {
        width: 100%;
        height: 100%;
        transform: rotate(-90deg);
      }
      .session-gauge circle {
        fill: none;
        stroke-width: 8;
        stroke-linecap: round;
      }
      .session-gauge .gauge-bg {
        stroke: var(--slate-100);
      }
      .session-gauge .gauge-fill {
        stroke: var(--indigo-600);
        transition: stroke-dashoffset 0.6s ease;
      }
      .gauge-text {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        display: flex;
        flex-direction: column;
        align-items: center;
      }
      .gauge-number {
        font-size: 2rem;
        font-weight: 800;
        color: var(--slate-900);
        line-height: 1;
      }
      .gauge-label {
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--slate-400);
        text-transform: uppercase;
        margin-top: 2px;
      }
      .sessions-hint {
        font-size: 0.85rem;
        color: var(--slate-600);
        margin: 0;
        line-height: 1.4;
      }

      /* Active booking widget */
      .active-booking-card {
        background: linear-gradient(135deg, var(--indigo-50) 0%, #e0e7ff 100%);
        border: 1px solid var(--indigo-100);
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 20px;
        display: flex;
        gap: 12px;
        align-items: center;
      }
      .booking-icon {
        background: var(--indigo-600);
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
      }
      .booking-details strong {
        display: block;
        font-size: 0.9rem;
        color: var(--indigo-800);
      }
      .booking-details span {
        font-size: 0.8rem;
        color: var(--indigo-700);
      }

      /* Action Buttons */
      .btn-primary-premium {
        width: 100%;
        padding: 12px 20px;
        background: var(--indigo-600);
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 0.95rem;
        font-weight: 700;
        cursor: pointer;
        transition: var(--transition-all);
        box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
      }
      .btn-primary-premium:hover {
        background: var(--indigo-700);
        box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.25);
        transform: translateY(-1px);
      }
      .btn-primary-premium:active {
        transform: translateY(0);
      }
      .btn-primary-premium:disabled {
        background: var(--slate-300);
        cursor: not-allowed;
        box-shadow: none;
      }

      /* Glassmorphic dropdown notification */
      .nav-notification-content {
        border-radius: 12px !important;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1) !important;
        border: 1px solid var(--slate-200) !important;
      }

      /* Quick Help Sidebar Card */
      .help-card {
        background: white;
        border: 1px solid var(--slate-200);
        border-radius: 16px;
        padding: 20px;
      }
      .help-title {
        font-weight: 700;
        color: var(--slate-900);
        font-size: 0.9rem;
        margin-bottom: 12px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
      }
      .help-list {
        margin: 0;
        padding-left: 18px;
        font-size: 0.8rem;
        color: var(--slate-600);
        display: flex;
        flex-direction: column;
        gap: 8px;
      }

      /* Premium Table Design */
      .table-premium-wrapper {
        overflow-x: auto;
        border-radius: 12px;
        border: 1px solid var(--slate-200);
      }
      .table-premium {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
      }
      .table-premium th,
      .table-premium td {
        padding: 14px 16px;
        text-align: left;
        border-bottom: 1px solid var(--slate-200);
      }
      .table-premium th {
        background: var(--slate-100);
        font-weight: 700;
        color: var(--slate-700);
      }
      .table-premium tr:last-child td {
        border-bottom: none;
      }
      .table-premium tr:hover {
        background: var(--slate-50);
      }

      /* Custom toggle slider details */
      .toggle-switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 26px;
      }
      .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
      }
      .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: var(--slate-300);
        transition: .3s;
        border-radius: 34px;
      }
      .toggle-slider:before {
        position: absolute;
        content: "";
        height: 20px;
        width: 20px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .3s;
        border-radius: 50%;
      }
      .toggle-switch input:checked + .toggle-slider {
        background-color: var(--indigo-600);
      }
      .toggle-switch input:checked + .toggle-slider:before {
        transform: translateX(24px);
      }

      /* Premium Tab Switcher Visibility Rules */
      .tab-content {
        display: none;
      }
      .tab-content.active {
        display: block;
        animation: fadeInTab 0.35s cubic-bezier(0.4, 0, 0.2, 1);
      }
      @keyframes fadeInTab {
        from { opacity: 0; transform: translateY(4px); }
        to { opacity: 1; transform: translateY(0); }
      }
    </style>
    <script>
      // Universal Mobile Menu Toggle
      function toggleMobileMenu(btn) {
        btn.classList.toggle('active');
        const navLinks = btn.closest('.navbar').querySelector('.nav-links');
        if (navLinks) {
          navLinks.classList.toggle('active');
        }
      }
    </script>
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
      <button class="menu-toggle" onclick="toggleMobileMenu(this)" aria-label="Toggle Navigation Menu">
        <span></span>
        <span></span>
        <span></span>
      </button>
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
      
      <!-- Welcome Dashboard Hero Header -->
      <div class="hero-banner-card">
        <div class="hero-welcome">
          <h2>Welcome back, <span style="color: var(--indigo-100);"><?php echo htmlspecialchars($currentUser['name']); ?></span>! 👋</h2>
          <p>Ready to code? Reserve a PC slot or instantly start an in-lab sit-in session.</p>
        </div>
        <div class="hero-status-pill">
          <span class="pulse-indicator"></span>
          <span class="status-text">Active Lab Mode</span>
        </div>
      </div>

      <!-- Modern Workspace Grid -->
      <div class="reservation-grid">
        
        <!-- Left Column: Forms, Tab Triggers, and Inline Maps -->
        <div class="grid-main-column">
          
          <!-- Premium Tab switcher -->
          <div class="tab-panel-nav">
            <button class="tab-trigger-btn active" id="tab-instant" onclick="switchTab('instant')">Instant Sit-In</button>
            <button class="tab-trigger-btn" id="tab-reserve" onclick="switchTab('reserve')">Make Reservation</button>
            <button class="tab-trigger-btn" id="tab-log" onclick="switchTab('log')">Reservation History</button>
          </div>

          <!-- Instant Sit-In Tab Container -->
          <div class="tab-content active" id="tab-content-instant">
            <div class="card-premium">
              <h2 class="card-title-premium">Instant Sit-In</h2>
              <form id="sitin-form" onsubmit="submitSitIn(event)">
                <div class="form-group-premium">
                  <label for="sitin-purpose">Select Purpose:</label>
                  <select id="sitin-purpose" required class="form-control-premium" onchange="handleSitInPurposeChange()">
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
                  <input type="text" id="sitin-purpose-other" placeholder="Specify your custom purpose..." style="display: none; margin-top: 10px;" class="form-control-premium" />
                </div>

                <div class="form-group-premium">
                  <label for="sitin-lab">Laboratory Room:</label>
                  <select id="sitin-lab" onchange="handleSitInLabChange()" required class="form-control-premium">
                    <option value="">Select Laboratory</option>
                    <option value="524">Lab 524</option>
                    <option value="526">Lab 526</option>
                    <option value="528">Lab 528</option>
                    <option value="530">Lab 530</option>
                    <option value="MAC">MAC Lab</option>
                  </select>
                </div>

                <!-- Embedded PC Grid Map for Sit-In -->
                <div class="embedded-map-card" id="sitin-embedded-map-container">
                  <div class="embedded-map-title">Click a PC to Select</div>
                  <div class="pc-grid-embedded" id="sitin-embedded-pc-grid">
                    <!-- Dynamically populated via JS -->
                  </div>
                  <div class="embedded-map-legend">
                    <div class="legend-pill"><span class="legend-dot available"></span> Available</div>
                    <div class="legend-pill"><span class="legend-dot occupied"></span> Occupied</div>
                    <div class="legend-pill"><span class="legend-dot reserved"></span> Reserved</div>
                  </div>
                  <div class="selected-pc-info" id="sitin-selected-pc-info" style="margin-top: 15px; font-weight: bold; text-align: center; color: var(--indigo-600);">No PC Selected</div>
                </div>

                <button type="submit" class="btn-primary-premium" id="sitin-submit-btn" style="margin-top: 10px;">Start Session Now</button>
              </form>
            </div>
          </div>

          <!-- Make Reservation Tab Container -->
          <div class="tab-content" id="tab-content-reserve">
            <div class="card-premium">
              <h2 class="card-title-premium">Make a Reservation</h2>
              <form id="reservation-form" onsubmit="submitReservation(event)">
                <input type="hidden" id="reservation-lab" />
                
                <div class="form-group-premium">
                  <label for="reservation-lab-select">Laboratory Room:</label>
                  <select id="reservation-lab-select" onchange="handleLabChange()" required class="form-control-premium">
                    <option value="">Select Laboratory</option>
                    <option value="524">Lab 524</option>
                    <option value="526">Lab 526</option>
                    <option value="528">Lab 528</option>
                    <option value="530">Lab 530</option>
                    <option value="MAC">MAC Lab</option>
                  </select>
                </div>

                <!-- Embedded PC Grid Map for Reservation -->
                <div class="embedded-map-card" id="reserve-embedded-map-container">
                  <div class="embedded-map-title">Select PC & Check Availability</div>
                  <div class="pc-grid-embedded" id="reserve-embedded-pc-grid">
                    <!-- Dynamically populated via JS -->
                  </div>
                  <div class="embedded-map-legend">
                    <div class="legend-pill"><span class="legend-dot available"></span> Available</div>
                    <div class="legend-pill"><span class="legend-dot occupied"></span> Occupied</div>
                    <div class="legend-pill"><span class="legend-dot reserved"></span> Reserved</div>
                  </div>
                  <div class="selected-pc-info" id="selected-pc-info" style="margin-top: 15px; font-weight: bold; text-align: center; color: var(--indigo-600);">No PC Selected</div>
                </div>

                <!-- Drawer for Reservation Details (Toggled when a PC is clicked!) -->
                <div class="pc-details-drawer" id="pc-details-drawer">
                  <div class="form-group-premium">
                    <label>Selected Reservation Date:</label>
                    <input type="date" id="reservation-date" class="form-control-premium" required onchange="loadPcSlotStatusesForEmbedded()" />
                  </div>

                  <div class="form-group-premium">
                    <label>Select Predefined Time Slot:</label>
                    <select id="reservation-time" required class="form-control-premium" onchange="highlightSelectedSlotCard()">
                      <option value="" disabled selected>Choose a session slot</option>
                      <option value="08:00-09:30">08:00 AM - 09:30 AM</option>
                      <option value="09:30-11:00">09:30 AM - 11:00 AM</option>
                      <option value="11:00-12:30">11:00 AM - 12:30 PM</option>
                      <option value="12:30-14:00">12:30 PM - 02:00 PM</option>
                      <option value="14:00-15:30">02:00 PM - 03:30 PM</option>
                      <option value="15:30-17:00">03:30 PM - 05:00 PM</option>
                      <option value="17:00-18:00">05:00 PM - 06:00 PM</option>
                    </select>
                  </div>

                  <!-- Schedule cards drawer displayed below -->
                  <div class="form-group-premium" style="margin-top: 15px;">
                    <label>Visual Daily Schedule:</label>
                    <div id="reserve-slots-container" class="slots-list-embedded">
                      <p style="font-size: 13px; color: var(--slate-600);">Please select a PC to view its visual daily schedule.</p>
                    </div>
                  </div>

                  <div class="form-group-premium" style="margin-top: 15px;">
                    <label for="reservation-purpose">Purpose:</label>
                    <select id="reservation-purpose" required class="form-control-premium" onchange="handleReservationPurposeChange()">
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
                    <input type="text" id="reservation-purpose-other" placeholder="Specify your custom purpose..." style="display: none; margin-top: 10px;" class="form-control-premium" />
                  </div>

                  <button type="submit" class="btn-primary-premium" id="reserve-submit-btn" style="margin-top: 15px;">Confirm Reservation</button>
                </div>
              </form>
            </div>
          </div>

          <!-- History Tab Container -->
          <div class="tab-content" id="tab-content-log">
            <div class="card-premium">
              <h2 class="card-title-premium">Reservation History</h2>
              <div class="table-premium-wrapper">
                <table class="table-premium">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>Time</th>
                      <th>Laboratory</th>
                      <th>PC Number</th>
                      <th>Purpose</th>
                      <th>Status</th>
                      <th style="text-align: center;">Action</th>
                    </tr>
                  </thead>
                  <tbody id="log-table-body">
                    <tr>
                      <td colspan="7" style="text-align: center; color: var(--slate-600);">Loading reservation records...</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

        </div>

        <!-- Right Column: Sidebar Stats Widgets & Settings -->
        <div class="grid-sidebar-column">
          
          <!-- Privileges configuration widget -->
          <div class="card-premium" style="padding: 20px;">
            <div class="toggle-row">
              <div class="toggle-info">
                <strong>Enable Reservations</strong>
                <span>Allow bookings and sit-ins</span>
              </div>
              <label class="toggle-switch">
                <input type="checkbox" id="reservation-toggle" <?php echo $canReserve ? 'checked' : ''; ?> onchange="toggleReservation()">
                <span class="toggle-slider"></span>
              </label>
            </div>
          </div>

          <!-- Glowing Radial Sessions Left Indicator widget -->
          <div class="session-stats-card">
            <div class="session-gauge-wrapper">
              <div class="session-gauge">
                <svg viewBox="0 0 100 100">
                  <circle class="gauge-bg" cx="50" cy="50" r="40" />
                  <circle class="gauge-fill" id="session-radial-fill" cx="50" cy="50" r="40" style="stroke-dasharray: 251.2; stroke-dashoffset: <?php echo (251.2 * (1 - ($remainingSessions / 30))); ?>;" />
                </svg>
                <div class="gauge-text">
                  <span class="gauge-number" id="remaining-sessions-val"><?php echo $remainingSessions; ?></span>
                  <span class="gauge-label">Sessions</span>
                </div>
              </div>
            </div>
            <p class="sessions-hint">Remaining standard session allowances for current academic cycle. Usage is automatically validated by department coordinators.</p>
          </div>

          <!-- Quick Help sidebar list -->
          <div class="help-card" style="margin-top: 24px;">
            <div class="help-title">Laboratory Guidelines</div>
            <ul class="help-list">
              <li>All sessions are allocated strictly in <strong>1 hour and 30 minute</strong> blocks.</li>
              <li>You cannot book sessions for previous dates.</li>
              <li>Be on time. Reserved seats are automatically released after 15 minutes of session start.</li>
              <li>Report any laboratory hardware anomalies immediately to the sit-in supervisor.</li>
            </ul>
          </div>

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

<script>
      // Tab switching
      let currentTab = 'instant';
      
      function switchTab(tabName) {
        // Don't reload if clicking the same tab
        if (currentTab === tabName) {
          return;
        }
        
        currentTab = tabName;
        
        document.querySelectorAll('.tab-btn, .tab-trigger-btn').forEach(btn => btn.classList.remove('active'));
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
          tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;">No reservations found</td></tr>';
          return;
        }
        
        tbody.innerHTML = reservations.map(res => {
          let statusClass = 'status-' + res.status;
          let statusText = res.status.charAt(0).toUpperCase() + res.status.slice(1);
          const canCancel = res.status && res.status.toLowerCase() === 'pending';
          const actionButton = canCancel ? '<button class="btn-small btn-danger" onclick="cancelReservation(' + res.id + ')">Cancel</button>' : '-';
          return `
            <tr>
              <td>${res.reservation_date}</td>
              <td>${res.start_time} - ${res.end_time}</td>
              <td>Lab ${res.lab_number}</td>
              <td>${res.pc_number ? 'PC ' + res.pc_number : '-'}</td>
              <td>${res.purpose}</td>
              <td><span class="status-badge ${statusClass}">${statusText}</span></td>
              <td style="text-align: center;">${actionButton}</td>
            </tr>
          `;
        }).join('');
      }

      // Initialize on page load
      document.addEventListener('DOMContentLoaded', function() {
        // Parse URL query parameters
        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = urlParams.get('tab');
        const labParam = urlParams.get('lab');
        const pcParam = urlParams.get('pc');
        const slotParam = urlParams.get('slot');
        
        let initialTab = 'instant';
        if (tabParam && (tabParam === 'instant' || tabParam === 'reserve' || tabParam === 'log')) {
          initialTab = tabParam;
        }
        
        // If lab and PC parameters are passed, force Make Reservation tab
        if (labParam && pcParam) {
          initialTab = 'reserve';
        }
        
        // Set initial tab state
        currentTab = initialTab;
        
        document.querySelectorAll('.tab-btn, .tab-trigger-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        const activeBtn = document.getElementById('tab-' + initialTab);
        const activeContent = document.getElementById('tab-content-' + initialTab);
        
        if (activeBtn) activeBtn.classList.add('active');
        if (activeContent) activeContent.classList.add('active');
        
        loadNavNotifications();
        loadUserReservations();
        loadRemainingSessions();
        
        // Handle slot routing and auto-fill if query parameters are present
        if (labParam && pcParam) {
          setTimeout(() => {
            const labSelect = document.getElementById('reservation-lab-select');
            if (labSelect) {
              labSelect.value = labParam;
              handleLabChange().then(() => {
                const pcNode = document.querySelector(`#reserve-embedded-pc-grid .pc-node-embedded[data-pc-number="${pcParam}"]`);
                if (pcNode) {
                  selectReserveEmbeddedPc(pcParam, pcNode);
                  
                  if (slotParam) {
                    loadPcSlotStatusesForEmbedded().then(() => {
                      const slotCard = document.querySelector(`#reserve-slots-container .slot-card-embedded[data-time-slot="${slotParam}"]`);
                      if (slotCard) {
                        selectEmbeddedSlotCard(slotParam, slotCard);
                        
                        // Focus on purpose field and scroll smoothly
                        setTimeout(() => {
                          const purposeSelect = document.getElementById('reservation-purpose');
                          if (purposeSelect) {
                            purposeSelect.focus();
                            purposeSelect.scrollIntoView({ behavior: 'smooth', block: 'center' });
                          }
                        }, 300);
                      }
                    });
                  }
                }
              });
            }
          }, 200);
        }
        
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
           } else {
               // Also check for new active sit-ins created by admin approval
               fetch('user_dashboard.php?action=get_active_sitin')
                   .then(res => res.json())
                   .then(data => {
                       if (data.success && data.sitin) {
                           // New active sit-in detected (e.g., from admin reservation approval)
                           hasActiveSitIn = true;
                           hasPendingReservation = false;
                           fetchActiveSitInDetails();
                           applyRestrictions();
                       }
                   })
                   .catch(err => console.error('Error checking for new sit-in:', err));
           }
        }, 15000);
        
        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('reservation-date').setAttribute('min', today);
        document.getElementById('reservation-date').value = today;

        // Time slot selection is required

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
            if (data.success) {
              if (data.sitin) {
                currentSitInDetails = data.sitin;
                initSitInTimer(currentSitInDetails);
              } else if (hasActiveSitIn) {
                // Sit-in has ended
                hasActiveSitIn = false;
                currentSitInDetails = null;
                if (timerInterval) clearInterval(timerInterval);
                const timerFab = document.getElementById('timer-fab');
                if (timerFab) timerFab.classList.remove('active', 'expiring');
                applyRestrictions();
                loadRemainingSessions();
              }
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
        
        if (remainingSeconds === null || typeof remainingSeconds === 'undefined' || remainingSeconds < 0) {
          timeDisplay.textContent = 'Active';
          return;
        }
        
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
          const endsText = currentSitInDetails.end_time ? '\nEnds: ' + currentSitInDetails.end_time : '';
          alert('Active Sit-In\nLab: ' + currentSitInDetails.lab_number + '\nPC: ' + pcInfo + '\nStarted: ' + currentSitInDetails.time_in + endsText);
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

         // Disable reservation tabs if can_reserve is false
         if (!canReserve) {
           const reserveTab = document.getElementById('tab-reserve');
           const instantTab = document.getElementById('tab-instant');
           if (reserveTab) {
             reserveTab.style.display = 'none';
           }
           if (instantTab) {
             instantTab.style.display = 'none';
           }
           // If reserve tab was active, switch to log tab
           if (currentTab === 'reserve' || currentTab === 'instant') {
             switchTab('log');
           }
         }
       }

       function toggleReservation() {
         const toggle = document.getElementById('reservation-toggle');
         const newStatus = toggle.checked ? 1 : 0;

         fetch('user_dashboard.php?action=toggle_reservation', {
           method: 'POST',
           headers: {'Content-Type': 'application/json'},
           body: JSON.stringify({
             user_id: <?php echo $_SESSION['user_id']; ?>,
             can_reserve: newStatus
           })
         })
         .then(res => res.json())
         .then(data => {
           if (data.success) {
             canReserve = toggle.checked;
             if (!canReserve) {
               // Disable tabs and switch to log
               const reserveTab = document.getElementById('tab-reserve');
               const instantTab = document.getElementById('tab-instant');
               if (reserveTab) reserveTab.style.display = 'none';
               if (instantTab) instantTab.style.display = 'none';
               if (currentTab === 'reserve' || currentTab === 'instant') {
                 switchTab('log');
               }
             } else {
               // Re-enable tabs
               const reserveTab = document.getElementById('tab-reserve');
               const instantTab = document.getElementById('tab-instant');
               if (reserveTab) reserveTab.style.display = '';
               if (instantTab) instantTab.style.display = '';
             }
           } else {
             alert(data.message || 'Failed to update reservation setting');
             toggle.checked = !toggle.checked;
           }
         })
         .catch(err => {
           console.error('Error:', err);
           toggle.checked = !toggle.checked;
         });
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

      function showForcedEndBanner(message) {
        // Remove existing banner if any
        const existingBanner = document.querySelector('.reservation-started-banner');
        if (existingBanner) {
          existingBanner.remove();
        }
        
        // Create banner
        const banner = document.createElement('div');
        banner.className = 'reservation-started-banner';
        banner.style.borderLeftColor = '#ef4444';
        banner.innerHTML = `
          <button class="close-banner" onclick="this.parentElement.remove()">&times;</button>
          <h3 style="color: #ef4444;">⚠️ Sit-In Forced to End</h3>
          <p>${message}</p>
        `;
        
        document.body.appendChild(banner);
        
        // Auto-remove after 15 seconds
        setTimeout(() => {
          if (banner.parentElement) {
            banner.remove();
          }
        }, 15000);
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

                // Check for forced end notification
                const forcedEnd = data.notifications.find(n => 
                  n.title && n.title.includes('Forced to End') && !n.is_read
                );

                if (forcedEnd) {
                  showForcedEndBanner(forcedEnd.message);
                  
                  // End active sit in if applicable
                  hasActiveSitIn = false;
                  currentSitInDetails = null;
                  if (timerInterval) clearInterval(timerInterval);
                  const timerFab = document.getElementById('timer-fab');
                  if (timerFab) timerFab.classList.remove('active', 'expiring');
                  applyRestrictions();
                  loadRemainingSessions();
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
        
        let purpose = document.getElementById('sitin-purpose').value;
        if (purpose === 'Others') {
          const otherText = document.getElementById('sitin-purpose-other').value.trim();
          if (!otherText) {
            alert('Please specify your custom purpose');
            return;
          }
          purpose = 'Others: ' + otherText;
        }
        
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
            document.getElementById('sitin-purpose-other').style.display = 'none';
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

      function handleSitInPurposeChange() {
        const select = document.getElementById('sitin-purpose');
        const otherInput = document.getElementById('sitin-purpose-other');
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

      function handleReservationPurposeChange() {
        const select = document.getElementById('reservation-purpose');
        const otherInput = document.getElementById('reservation-purpose-other');
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

      // Sit-In PC Embedded Map Functions
      function handleSitInLabChange() {
        const labSelect = document.getElementById('sitin-lab');
        const lab = labSelect.value;
        const container = document.getElementById('sitin-embedded-map-container');
        const grid = document.getElementById('sitin-embedded-pc-grid');
        
        if (!lab) {
          sitInSelectedPc = null;
          document.getElementById('sitin-selected-pc-info').textContent = 'No PC Selected';
          container.style.display = 'none';
          return;
        }

        grid.innerHTML = '<p style="grid-column: 1/-1; text-align: center; color: var(--slate-600); font-size: 13px; margin: 10px 0;">Loading laboratory PCs...</p>';
        container.style.display = 'block';
        sitInSelectedPc = null;
        document.getElementById('sitin-selected-pc-info').textContent = 'No PC Selected';

        fetch('user_dashboard.php?action=get_lab_pc_status&lab=' + lab)
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              renderSitInEmbeddedPcGrid(data.pcs);
            } else {
              grid.innerHTML = '<p style="grid-column: 1/-1; text-align: center; color: var(--rose-500); font-size: 13px;">Error: ' + (data.message || 'Unknown error') + '</p>';
            }
          })
          .catch(err => {
            console.error('Fetch error:', err);
            grid.innerHTML = '<p style="grid-column: 1/-1; text-align: center; color: var(--rose-500); font-size: 13px;">Error loading PC status. Please try again.</p>';
          });
      }

      function renderSitInEmbeddedPcGrid(pcs) {
        const grid = document.getElementById('sitin-embedded-pc-grid');
        grid.innerHTML = '';

        pcs.forEach(pc => {
          const pcDiv = document.createElement('div');
          pcDiv.className = 'pc-node-embedded ' + pc.status;
          pcDiv.textContent = pc.pc_number;
          pcDiv.dataset.pcNumber = pc.pc_number;
          
          if (pc.status === 'maintenance') {
            pcDiv.style.opacity = '0.4';
            pcDiv.style.cursor = 'not-allowed';
            pcDiv.style.backgroundColor = '#e2e8f0';
            pcDiv.style.borderColor = '#cbd5e1';
            pcDiv.style.color = '#64748b';
            pcDiv.title = 'PC Disabled / Maintenance';
          } else if (pc.status === 'available') {
            pcDiv.onclick = () => selectSitInEmbeddedPc(pc.pc_number, pcDiv);
          }
          
          grid.appendChild(pcDiv);
        });
      }

      function selectSitInEmbeddedPc(pcNumber, element) {
        document.querySelectorAll('#sitin-embedded-pc-grid .pc-node-embedded').forEach(el => el.classList.remove('selected'));
        element.classList.add('selected');
        sitInSelectedPc = pcNumber;
        document.getElementById('sitin-selected-pc-info').textContent = 'Selected PC: ' + pcNumber;
      }

      // Submit Reservation
      function submitReservation(event) {
        event.preventDefault();
        
        const lab = document.getElementById('reservation-lab').value;
        const date = document.getElementById('reservation-date').value;
        const timeSlotVal = document.getElementById('reservation-time').value;
        let purpose = document.getElementById('reservation-purpose').value;
        if (purpose === 'Others') {
          const otherText = document.getElementById('reservation-purpose-other').value.trim();
          if (!otherText) {
            alert('Please specify your custom purpose');
            return;
          }
          purpose = 'Others: ' + otherText;
        }
        const pcNumber = selectedPc;

        if (!lab || !date || !timeSlotVal || !purpose) {
          alert('Please fill in all fields');
          return;
        }

        if (!pcNumber) {
          alert('Please select a PC from the grid');
          return;
        }

        const [startTime, endTime] = timeSlotVal.split('-');

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
            document.getElementById('reservation-purpose-other').style.display = 'none';
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

      // Reservation PC Embedded Map & Details Drawer Functions
      function handleLabChange() {
        const labSelect = document.getElementById('reservation-lab-select');
        const lab = labSelect.value;
        const container = document.getElementById('reserve-embedded-map-container');
        const grid = document.getElementById('reserve-embedded-pc-grid');
        const drawer = document.getElementById('pc-details-drawer');
        
        if (!lab) {
          selectedPc = null;
          document.getElementById('selected-pc-info').textContent = 'No PC Selected';
          document.getElementById('reservation-lab').value = '';
          container.style.display = 'none';
          drawer.style.display = 'none';
          return;
        }

        document.getElementById('reservation-lab').value = lab;
        grid.innerHTML = '<p style="grid-column: 1/-1; text-align: center; color: var(--slate-600); font-size: 13px; margin: 10px 0;">Loading laboratory PCs...</p>';
        container.style.display = 'block';
        drawer.style.display = 'none';
        selectedPc = null;
        document.getElementById('selected-pc-info').textContent = 'No PC Selected';

        return fetch('user_dashboard.php?action=get_lab_pc_status&lab=' + lab)
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              renderReserveEmbeddedPcGrid(data.pcs);
            } else {
              grid.innerHTML = '<p style="grid-column: 1/-1; text-align: center; color: var(--rose-500); font-size: 13px;">Error: ' + (data.message || 'Unknown error') + '</p>';
            }
          })
          .catch(err => {
            console.error('Fetch error:', err);
            grid.innerHTML = '<p style="grid-column: 1/-1; text-align: center; color: var(--rose-500); font-size: 13px;">Error loading PC status. Please try again.</p>';
          });
      }

      function renderReserveEmbeddedPcGrid(pcs) {
        const grid = document.getElementById('reserve-embedded-pc-grid');
        grid.innerHTML = '';

        pcs.forEach(pc => {
          const pcDiv = document.createElement('div');
          pcDiv.className = 'pc-node-embedded ' + pc.status;
          pcDiv.textContent = pc.pc_number;
          pcDiv.dataset.pcNumber = pc.pc_number;
          
          if (pc.status === 'maintenance') {
            pcDiv.style.opacity = '0.4';
            pcDiv.style.cursor = 'not-allowed';
            pcDiv.style.backgroundColor = '#e2e8f0';
            pcDiv.style.borderColor = '#cbd5e1';
            pcDiv.style.color = '#64748b';
            pcDiv.title = 'PC Disabled / Maintenance';
          } else {
            pcDiv.onclick = () => selectReserveEmbeddedPc(pc.pc_number, pcDiv);
          }
          
          grid.appendChild(pcDiv);
        });
      }

      function selectReserveEmbeddedPc(pcNumber, element) {
        document.querySelectorAll('#reserve-embedded-pc-grid .pc-node-embedded').forEach(el => el.classList.remove('selected'));
        element.classList.add('selected');
        
        selectedPc = pcNumber;
        document.getElementById('selected-pc-info').textContent = 'Selected PC: ' + pcNumber;

        // Initialize reservation date to today if it has no value
        const dateInput = document.getElementById('reservation-date');
        const today = new Date().toISOString().split('T')[0];
        dateInput.setAttribute('min', today);
        if (!dateInput.value) {
          dateInput.value = today;
        }

        // Show details drawer
        const drawer = document.getElementById('pc-details-drawer');
        drawer.style.display = 'block';
        
        // Load visual slots schedule
        loadPcSlotStatusesForEmbedded();
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
          const canCancel = res.status && res.status.toLowerCase() === 'pending';
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

      // Embedded Daily Time Slots Functions
      function loadPcSlotStatusesForEmbedded() {
        const lab = document.getElementById('reservation-lab-select').value;
        const pcNumber = selectedPc;
        const date = document.getElementById('reservation-date').value;
        const container = document.getElementById('reserve-slots-container');
        
        if (!lab || !pcNumber || !date) return Promise.resolve();
        
        container.innerHTML = '<p style="text-align: center; color: var(--slate-600); font-size: 13px; margin: 10px 0;">Loading daily schedule...</p>';
        
        return fetch(`user_dashboard.php?action=get_pc_slots_status&lab=${lab}&pc_number=${pcNumber}&date=${date}`)
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              renderPcSlotsForEmbedded(data.slots);
            } else {
              container.innerHTML = `<p style="text-align: center; color: var(--rose-500); font-size: 13px;">Error: ${data.message || 'Failed to fetch status'}</p>`;
            }
          })
          .catch(err => {
            console.error(err);
            container.innerHTML = '<p style="text-align: center; color: var(--rose-500); font-size: 13px;">Failed to load daily schedule.</p>';
          });
      }

      function renderPcSlotsForEmbedded(slots) {
        const container = document.getElementById('reserve-slots-container');
        container.innerHTML = '';
        
        // Find current time slot selection if any to preserve/highlight it
        const currentSelectedSlot = document.getElementById('reservation-time').value;
        
        slots.forEach(slot => {
          const card = document.createElement('div');
          const isSlotSelected = (currentSelectedSlot === (slot.start + '-' + slot.end));
          
          card.className = `slot-card-embedded ${slot.status} ${isSlotSelected ? 'selected' : ''}`;
          card.dataset.timeSlot = slot.start + '-' + slot.end;
          
          let displayStatus = slot.status.charAt(0).toUpperCase() + slot.status.slice(1);
          if (slot.status === 'occupied') {
            displayStatus = 'Occupied';
          } else if (slot.status === 'reserved') {
            displayStatus = 'Reserved';
          } else {
            displayStatus = 'Available';
          }
          
          card.innerHTML = `
            <div class="slot-icon-wrapper">
              <span class="slot-icon">${slot.status === 'available' ? '⚡' : (slot.status === 'occupied' ? '🔒' : '⏳')}</span>
            </div>
            <div class="slot-info-wrapper">
              <div class="slot-card-time">${slot.display}</div>
              <div class="slot-card-badge ${slot.status}">${displayStatus}</div>
            </div>
          `;
          
          if (slot.status === 'available') {
            card.onclick = () => selectEmbeddedSlotCard(slot.start + '-' + slot.end, card);
          }
          
          container.appendChild(card);
        });
      }

      function selectEmbeddedSlotCard(timeSlotVal, cardElement) {
        document.querySelectorAll('#reserve-slots-container .slot-card-embedded').forEach(el => el.classList.remove('selected'));
        cardElement.classList.add('selected');
        
        // Sync to select dropdown
        document.getElementById('reservation-time').value = timeSlotVal;
      }

      function highlightSelectedSlotCard() {
        const val = document.getElementById('reservation-time').value;
        document.querySelectorAll('#reserve-slots-container .slot-card-embedded').forEach(card => {
          if (card.dataset.timeSlot === val) {
            card.classList.add('selected');
          } else {
            card.classList.remove('selected');
          }
        });
      }
    </script>
  </body>
</html>
