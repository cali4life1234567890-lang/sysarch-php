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

// Fetch all leaderboard stats
$leaderboardStmt = $pdo->query("
    SELECT u.id, u.id_number, u.firstname, u.middlename, u.lastname, u.course, u.level, u.profile_pic,
        COALESCE(SUM(
            (strftime('%s', COALESCE(sr.time_out, datetime('now'))) - strftime('%s', sr.time_in)) / 3600.0
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
    // Sit-in score formula: 60% hours spent, 40% sessions used
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
        'total_score' => round($totalScore, 2),
        'profile_pic' => $row['profile_pic']
    ];
    $rank++;
}

// Sort by total score descending
usort($leaderboardData, function($a, $b) {
    if ($b['total_score'] == $a['total_score']) {
        return $b['hours_spent'] - $a['hours_spent'];
    }
    return ($b['total_score'] - $a['total_score']) > 0 ? 1 : -1;
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

$adminName = $_SESSION['name'] ?? 'Admin';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - CCS Sit-In System</title>
    <link rel="icon" href="../imgs/ccslogo.png" type="image/png" />
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
            <a href="admin_software.php">Software Manager</a>
            <a href="admin_records.php">View Sit-In Records</a>
            <a href="admin_reports.php">Sit-In Reports</a>
            <a href="admin_feedback.php">Feedback</a>
            <a href="admin_reservations.php">Reservations</a>
            <a href="../reg-log-prof/logout.php">Logout</a>
        </div>
    </nav>

    <div class="admin-content premium-admin-dashboard">
        <div class="dashboard-header-flex">
            <div>
                <span class="welcome-badge">GAMIFIED STATISTICS</span>
                <h1>Student Leaderboard</h1>
            </div>
            <div class="admin-profile-badge">
                <span class="avatar">🏆</span>
                <div>
                    <strong><?php echo htmlspecialchars($adminName); ?></strong>
                    <span>System Administrator</span>
                </div>
            </div>
        </div>

        <div class="leaderboard-outer-container">
            <!-- Dynamic Podium for Top 3 Students -->
            <?php if (count($rankedData) >= 1): ?>
            <div class="podium-section">
                <!-- 2nd Place (Left) -->
                <?php if (isset($rankedData[1])): ?>
                <div class="podium-card place-2">
                    <div class="podium-badge badge-silver">🥈</div>
                    <div class="podium-avatar-ring">
                        <?php 
                        $avatarUrl = !empty($rankedData[1]['profile_pic']) ? '../' . htmlspecialchars($rankedData[1]['profile_pic']) : '../imgs/emp-prof.png';
                        ?>
                        <img src="<?php echo $avatarUrl; ?>" class="podium-avatar-img" alt="2nd Place">
                    </div>
                    <h4 class="podium-name"><?php echo htmlspecialchars($rankedData[1]['name']); ?></h4>
                    <span class="podium-id"><?php echo htmlspecialchars($rankedData[1]['id_number']); ?></span>
                    <span class="podium-course"><?php echo htmlspecialchars($rankedData[1]['course'] . '-' . $rankedData[1]['level']); ?></span>
                    <div class="podium-metrics">
                        <div>
                            <strong><?php echo $rankedData[1]['hours_spent']; ?>h</strong>
                            <span>Spent</span>
                        </div>
                        <div>
                            <strong><?php echo $rankedData[1]['total_score']; ?></strong>
                            <span>Score</span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 1st Place (Center - taller & crowned) -->
                <?php if (isset($rankedData[0])): ?>
                <div class="podium-card place-1">
                    <div class="crown-icon">👑</div>
                    <div class="podium-badge badge-gold">🥇</div>
                    <div class="podium-avatar-ring">
                        <?php 
                        $avatarUrl = !empty($rankedData[0]['profile_pic']) ? '../' . htmlspecialchars($rankedData[0]['profile_pic']) : '../imgs/emp-prof.png';
                        ?>
                        <img src="<?php echo $avatarUrl; ?>" class="podium-avatar-img" alt="1st Place">
                    </div>
                    <h4 class="podium-name"><?php echo htmlspecialchars($rankedData[0]['name']); ?></h4>
                    <span class="podium-id"><?php echo htmlspecialchars($rankedData[0]['id_number']); ?></span>
                    <span class="podium-course"><?php echo htmlspecialchars($rankedData[0]['course'] . '-' . $rankedData[0]['level']); ?></span>
                    <div class="podium-metrics">
                        <div>
                            <strong><?php echo $rankedData[0]['hours_spent']; ?>h</strong>
                            <span>Spent</span>
                        </div>
                        <div>
                            <strong><?php echo $rankedData[0]['total_score']; ?></strong>
                            <span>Score</span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 3rd Place (Right) -->
                <?php if (isset($rankedData[2])): ?>
                <div class="podium-card place-3">
                    <div class="podium-badge badge-bronze">🥉</div>
                    <div class="podium-avatar-ring">
                        <?php 
                        $avatarUrl = !empty($rankedData[2]['profile_pic']) ? '../' . htmlspecialchars($rankedData[2]['profile_pic']) : '../imgs/emp-prof.png';
                        ?>
                        <img src="<?php echo $avatarUrl; ?>" class="podium-avatar-img" alt="3rd Place">
                    </div>
                    <h4 class="podium-name"><?php echo htmlspecialchars($rankedData[2]['name']); ?></h4>
                    <span class="podium-id"><?php echo htmlspecialchars($rankedData[2]['id_number']); ?></span>
                    <span class="podium-course"><?php echo htmlspecialchars($rankedData[2]['course'] . '-' . $rankedData[2]['level']); ?></span>
                    <div class="podium-metrics">
                        <div>
                            <strong><?php echo $rankedData[2]['hours_spent']; ?>h</strong>
                            <span>Spent</span>
                        </div>
                        <div>
                            <strong><?php echo $rankedData[2]['total_score']; ?></strong>
                            <span>Score</span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Table of Rankings -->
            <div class="chart-container-card leaderboard-table-card">
                <div class="catalog-viewer-header">
                    <h2>Overall Student Rankings</h2>
                    <div class="search-filter-box">
                        <input type="text" id="leaderboardSearch" placeholder="Search student name or course..." onkeyup="filterLeaderboard()" class="search-box">
                    </div>
                </div>

                <div class="table-wrapper-outer">
                    <table class="data-table" id="leaderboardTable">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Student Name</th>
                                <th>Course & Level</th>
                                <th>Hours Spent</th>
                                <th>Sessions Logged</th>
                                <th style="text-align: right;">Activity Score</th>
                            </tr>
                        </thead>
                        <tbody id="leaderboard-body">
                            <!-- Loaded Dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        const leaderboardData = <?php echo $leaderboardJson; ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            renderLeaderboard();
        });
        
        function renderLeaderboard() {
            const tbody = document.getElementById('leaderboard-body');
            const searchVal = document.getElementById('leaderboardSearch').value.toLowerCase();
            
            const filtered = leaderboardData.filter(entry => {
                return entry.name.toLowerCase().includes(searchVal) || 
                       entry.course.toLowerCase().includes(searchVal) || 
                       entry.id_number.toLowerCase().includes(searchVal);
            });
            
            if (filtered.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: #888; padding: 25px;">No students match your query.</td></tr>';
                return;
            }
            
            tbody.innerHTML = filtered.map(entry => {
                let rankClass = '';
                let rankDisplay = `#${entry.rank}`;
                
                if (entry.rank === 1) {
                    rankClass = 'rank-1';
                    rankDisplay = '🏆 1st';
                } else if (entry.rank === 2) {
                    rankClass = 'rank-2';
                    rankDisplay = '🥈 2nd';
                } else if (entry.rank === 3) {
                    rankClass = 'rank-3';
                    rankDisplay = '🥉 3rd';
                }
                
                const avatarSrc = entry.profile_pic ? '../' + entry.profile_pic : '../imgs/emp-prof.png';
                return `
                    <tr class="${entry.rank <= 3 ? 'top-three-row' : ''}">
                        <td><span class="rank-cell ${rankClass}">${rankDisplay}</span></td>
                        <td>
                            <div class="student-leaderboard-identity">
                                <img src="${avatarSrc}" alt="Avatar" class="leaderboard-table-avatar" />
                                <div class="student-mini-info">
                                    <strong>${escapeHtml(entry.name)}</strong>
                                    <span>ID: ${escapeHtml(entry.id_number)}</span>
                                </div>
                            </div>
                        </td>
                        <td><span class="course-badge">${escapeHtml(entry.course)} - Lvl ${entry.level}</span></td>
                        <td><strong>${entry.hours_spent} hrs</strong></td>
                        <td><code>${entry.sessions_used} sessions</code></td>
                        <td class="score-cell" style="text-align: right; font-weight: bold; color: var(--primary-color);">${entry.total_score}</td>
                    </tr>
                `;
            }).join('');
        }

        function filterLeaderboard() {
            renderLeaderboard();
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    </script>
    <?php require_once 'search_modal.php'; ?>
</body>
</html>