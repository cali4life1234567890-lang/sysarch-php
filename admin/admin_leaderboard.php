<?php
// Admin Leaderboard Page
require_once '../database/db.php';
startSession();

// Check if admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['token'])) {
    header('Location: ../index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id_number FROM users WHERE id = ? AND id_number = '2664388'");
$stmt->execute([$_SESSION['user_id']]);
if (!$stmt->fetch()) {
    header('Location: ../index.php');
    exit;
}

// Get leaderboard data
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
$rank = 1;
while ($row = $leaderboardStmt->fetch(PDO::FETCH_ASSOC)) {
    $totalHours = round($row['total_hours'], 2);
    $usedSessions = intval($row['used_sessions']);
    $totalScore = (0.60 * $totalHours) + (0.40 * $usedSessions);
    
    $name = trim($row['firstname'] . ' ' . ($row['middlename'] ? $row['middlename'] . ' ' : '') . $row['lastname']);
    
    $leaderboardData[] = [
        'rank' => $rank,
        'id_number' => $row['id_number'],
        'name' => $name,
        'course' => $row['course'],
        'level' => $row['level'],
        'hours_spent' => $totalHours,
        'sessions_used' => $usedSessions,
        'total_score' => round($totalScore, 2)
    ];
    $rank++;
}

// Sort by total score
usort($leaderboardData, function($a, $b) {
    return $b['total_score'] - $a['total_score'];
});

// Re-assign ranks after sorting
$rankedData = [];
$rank = 1;
foreach ($leaderboardData as &$entry) {
    $entry['rank'] = $rank;
    $rankedData[] = $entry;
    $rank++;
}

$leaderboardJson = json_encode($rankedData);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - CCS Sit-In System</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin.css">
    <style>
        .leaderboard-container {
            padding: 20px;
            max-width: 1000px;
            margin: 0 auto;
        }
        .leaderboard-header {
            margin-bottom: 20px;
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
        .rank-cell {
            font-weight: bold;
        }
        .rank-1 { color: #ffd700; }
        .rank-2 { color: #c0c0c0; }
        .rank-3 { color: #cd7f32; }
        .score-cell {
            font-weight: bold;
            color: #144d94;
        }
    </style>
</head>
<body>
    <nav class="navbar admin-navbar">
        <div class="nav-brand"> 
            <a href="admin_home.php" class="logo-group"> 
                <img src="../imgs/ccslogo.png" alt="Department Logo" class="logo-sub" />
                <h1 class="system-title">CCS Sit-In Monitoring System</h1>
            </a>
        </div>
        <div class="nav-links admin-links">
            <a href="admin_home.php">Home</a>
            <a href="#" onclick="openSearchModal(); return false;">Search</a>
            <a href="admin_leaderboard.php" class="active">Leaderboard</a>
            <a href="admin_students.php">Students</a>
            <a href="admin_sitin.php">Sit-In</a>
            <a href="admin_records.php">View Sit-In Records</a>
            <a href="admin_reports.php">Sit-In Reports</a>
            <a href="admin_feedback.php">Feedback</a>
            <a href="admin_reservations.php">Reservations</a>
            <a href="../reg-log-prof/logout.php">Logout</a>
        </div>
    </nav>

    <div class="admin-content">
        <div class="leaderboard-container">
            <div class="leaderboard-header">
                <h1>Leaderboard</h1>
            </div>
            
            <table class="leaderboard-table">
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
                <tbody id="leaderboard-body">
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const leaderboardData = <?php echo $leaderboardJson; ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            renderLeaderboard();
        });
        
        function renderLeaderboard() {
            const tbody = document.getElementById('leaderboard-body');
            
            if (leaderboardData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">No data available</td></tr>';
                return;
            }
            
            tbody.innerHTML = leaderboardData.map(entry => {
                const rankClass = entry.rank <= 3 ? 'rank-' + entry.rank : '';
                return `
                    <tr>
                        <td class="rank-cell ${rankClass}">#${entry.rank}</td>
                        <td>${entry.name}</td>
                        <td>${entry.course} - ${entry.level}</td>
                        <td>${entry.hours_spent}</td>
                        <td>${entry.sessions_used}</td>
                        <td class="score-cell">${entry.total_score}</td>
                    </tr>
                `;
            }).join('');
        }
    </script>
</body>
</html>