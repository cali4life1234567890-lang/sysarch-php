<?php
// Leaderboard Page
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
$userStmt = $pdo->prepare("SELECT id_number, lastname, firstname, middlename, course, level FROM users WHERE id = ?");
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

// Get leaderboard data - all users ranked by the formula
$leaderboardStmt = $pdo->query("
    SELECT 
        u.id_number,
        u.lastname,
        u.firstname,
        u.middlename,
        u.course,
        u.level,
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

$leaderboardData = [];
while ($row = $leaderboardStmt->fetch(PDO::FETCH_ASSOC)) {
    $totalHours = round($row['total_hours'], 2);
    $usedSessions = $row['used_sessions'];
    $totalScore = (0.60 * $totalHours) + (0.40 * $usedSessions);
    
    $leaderboardData[] = [
        'id_number' => $row['id_number'],
        'name' => trim($row['firstname'] . ' ' . ($row['middlename'] ? $row['middlename'] . ' ' : '') . $row['lastname']),
        'course' => $row['course'],
        'level' => $row['level'],
        'hours_spent' => $totalHours,
        'sessions_used' => $usedSessions,
        'total_score' => round($totalScore, 2)
    ];
}

// Sort by total score descending
usort($leaderboardData, function($a, $b) {
    return $b['total_score'] - $a['total_score'];
});

// Assign ranks
$rankedData = [];
$rank = 1;
foreach ($leaderboardData as $entry) {
    $entry['rank'] = $rank;
    $rankedData[] = $entry;
    $rank++;
}

// Get current user's rank
$currentUserId = $user['id_number'];
$userRank = 1;
foreach ($rankedData as $entry) {
    if ($entry['id_number'] === $currentUserId) {
        $userRank = $entry['rank'];
        break;
    }
}

$leaderboardJson = json_encode($rankedData);
$userRankJson = json_encode($userRank);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Leaderboard - CCS Sit-In Monitoring System</title>
    <link rel="stylesheet" href="../style.css" />
    <script src="../script.js"></script>
    <style>
      .leaderboard-page {
        padding: 20px;
        max-width: 1000px;
        margin: 0 auto;
      }
      .leaderboard-header {
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
      .your-rank-card {
        background: linear-gradient(135deg, #144d94 0%, #1e6fd9 100%);
        color: white;
        padding: 25px;
        border-radius: 10px;
        margin-bottom: 25px;
        text-align: center;
      }
      .your-rank-card h2 {
        margin: 0 0 10px 0;
        font-size: 18px;
      }
      .your-rank-number {
        font-size: 48px;
        font-weight: bold;
        color: #ffd700;
      }
      .your-rank-details {
        display: flex;
        justify-content: center;
        gap: 30px;
        margin-top: 15px;
      }
      .your-rank-item {
        text-align: center;
      }
      .your-rank-label {
        font-size: 12px;
        opacity: 0.8;
      }
      .your-rank-value {
        font-size: 20px;
        font-weight: bold;
      }
      .leaderboard-table {
        width: 100%;
        background: white;
        border-collapse: collapse;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      }
      .leaderboard-table th {
        background: #144d94;
        color: white;
        padding: 15px;
        text-align: left;
        font-weight: 600;
      }
      .leaderboard-table td {
        padding: 12px 15px;
        border-bottom: 1px solid #eee;
      }
      .leaderboard-table tr:hover {
        background: #f8f9fa;
      }
      .leaderboard-table tr.current-user {
        background: #e7f3ff;
      }
      .rank-cell {
        font-weight: bold;
        width: 60px;
      }
      .rank-1 { color: #ffd700; }
      .rank-2 { color: #c0c0c0; }
      .rank-3 { color: #cd7f32; }
      .name-cell {
        font-weight: 500;
      }
      .score-cell {
        font-weight: bold;
        color: #144d94;
        font-size: 18px;
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

    <!-- Leaderboard Page Content -->
    <div class="leaderboard-page">
      <div class="leaderboard-header">
        <h1>Leaderboard</h1>
      </div>

      <!-- Your Rank Card -->
      <div class="your-rank-card">
        <h2>Your Rank</h2>
        <div class="your-rank-number">#<?php echo $userRank; ?></div>
        <div class="your-rank-details">
          <div class="your-rank-item">
            <div class="your-rank-label">Hours Spent</div>
            <div class="your-rank-value" id="your-hours">0</div>
          </div>
          <div class="your-rank-item">
            <div class="your-rank-label">Sessions Used</div>
            <div class="your-rank-value" id="your-sessions">0</div>
          </div>
          <div class="your-rank-item">
            <div class="your-rank-label">Total Score</div>
            <div class="your-rank-value" id="your-score">0</div>
          </div>
        </div>
      </div>

      <!-- Leaderboard Table -->
      <table class="leaderboard-table">
        <thead>
          <tr>
            <th>Rank</th>
            <th>Student</th>
            <th>Course</th>
            <th>Hours</th>
            <th>Sessions</th>
            <th>Total Score</th>
          </tr>
        </thead>
        <tbody id="leaderboard-body">
          <!-- Leaderboard data will be loaded here -->
        </tbody>
      </table>
    </div>

    <script>
      const leaderboardData = <?php echo $leaderboardJson; ?>;
      const currentUserRank = <?php echo $userRankJson; ?>;
      const currentUserId = '<?php echo $currentUserId; ?>';

      document.addEventListener('DOMContentLoaded', function() {
        renderLeaderboard();
        loadYourStats();
      });

      function renderLeaderboard() {
        const tbody = document.getElementById('leaderboard-body');
        
        if (leaderboardData.length === 0) {
          tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">No data available</td></tr>';
          return;
        }

        tbody.innerHTML = leaderboardData.map(entry => {
          const isCurrentUser = entry.id_number === currentUserId;
          const rankClass = entry.rank <= 3 ? 'rank-' + entry.rank : '';
          return `
            <tr class="${isCurrentUser ? 'current-user' : ''}">
              <td class="rank-cell ${rankClass}">#${entry.rank}</td>
              <td class="name-cell">${entry.name}</td>
              <td>${entry.course} - ${entry.level}</td>
              <td>${entry.hours_spent}</td>
              <td>${entry.sessions_used}</td>
              <td class="score-cell">${entry.total_score}</td>
            </tr>
          `;
        }).join('');
      }

      function loadYourStats() {
        const yourEntry = leaderboardData.find(e => e.id_number === currentUserId);
        if (yourEntry) {
          document.getElementById('your-hours').textContent = yourEntry.hours_spent;
          document.getElementById('your-sessions').textContent = yourEntry.sessions_used;
          document.getElementById('your-score').textContent = yourEntry.total_score;
        }
      }
    </script>
  </body>
</html>