<?php
// Admin Home/Dashboard Page
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

// Get stats
$stats = [
    'total_students' => 0,
    'today_sitin' => 0,
    'total_records' => 0,
    'pending_reservations' => 0
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE id_number != '2664388'");
    $stats['total_students'] = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sitin_records WHERE date(time_in) = date('now') AND time_out IS NULL");
    $stats['today_sitin'] = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sitin_records");
    $stats['total_records'] = $stmt->fetch()['count'];
} catch (PDOException $e) {
    // Ignore errors
}

$adminName = $_SESSION['name'] ?? 'Admin';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CCS Sit-In System</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin.css">
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
            <a href="admin_home.php" class="active">Home</a>
            <a href="#" onclick="openSearchModal(); return false;">Search</a>
            <a href="admin_leaderboard.php">Leaderboard</a>
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
        <h1>Admin Dashboard</h1>
        
        <div class="dashboard-grid">
            <!-- Left Column: Statistics -->
            <div class="dashboard-left">
                <!-- Statistics Card -->
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h2>Statistics</h2>
                    </div>
                    <div class="stat-rows">
                        <div class="stat-row">
                            <span class="stat-label">Total Students</span>
                            <span class="stat-value"><?php echo $stats['total_students']; ?></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Today's Sit-In</span>
                            <span class="stat-value"><?php echo $stats['today_sitin']; ?></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Total Records</span>
                            <span class="stat-value"><?php echo $stats['total_records']; ?></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Pending Reservations</span>
                            <span class="stat-value"><?php echo $stats['pending_reservations']; ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Language Used Section -->
                <div class="language-card">
                    <div class="language-card-header">
                        <h2>Language Used</h2>
                    </div>
                    <div class="language-grid">
                        <div class="language-item">
                            <div class="language-icon">C#</div>
                            <span class="language-count">0</span>
                        </div>
                        <div class="language-item">
                            <div class="language-icon">C</div>
                            <span class="language-count">0</span>
                        </div>
                        <div class="language-item">
                            <div class="language-icon">Java</div>
                            <span class="language-count">0</span>
                        </div>
                        <div class="language-item">
                            <div class="language-icon">ASP.Net</div>
                            <span class="language-count">0</span>
                        </div>
                        <div class="language-item">
                            <div class="language-icon">PHP</div>
                            <span class="language-count">0</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Announcements -->
            <div class="dashboard-right">
                <!-- New Assignment Input Section -->
                <div class="assignment-card">
                    <div class="assignment-card-header">
                        <h2>Announcement</h2>                    
                    </div>
                    <div class="assignment-form">
                        <textarea id="admin-announcement-text" class="assignment-textarea" placeholder="Post Announcement Here..."></textarea>
                        <button class="btn-submit" onclick="postAdminAnnouncement()">Submit</button>
                    </div>
                </div>
                
                <!-- Posted Announcements Feed -->
                <div class="announcements-feed">
                    <div class="feed-header">
                        <h3>Posted Announcements</h3>
                    </div>
                    <div id="admin-announcement-list" class="feed-list">
                        <p class="no-announcements">No announcements yet</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Load announcements on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadAdminAnnouncements();
    });

    // Load admin announcements
    function loadAdminAnnouncements() {
        const announcements = JSON.parse(localStorage.getItem('admin_announcements') || '[]');
        displayAdminAnnouncements(announcements);
    }

    // Display admin announcements
    function displayAdminAnnouncements(announcements) {
        const list = document.getElementById('admin-announcement-list');
        if (!list) return;

        if (announcements.length === 0) {
            list.innerHTML = '<p class="no-announcements">No announcements yet</p>';
            return;
        }

        let html = '';
        announcements.forEach(announcement => {
            html += `<div class="feed-item">
                <div class="feed-item-accent"></div>
                <div class="feed-item-content">
                    <div class="feed-item-header">
                        <strong class="feed-item-title">CCS Admin</strong>
                        <span class="feed-item-time">${announcement.date}</span>
                    </div>
                    <p class="feed-item-body">${announcement.text}</p>
                </div>
            </div>`;
        });
        list.innerHTML = html;
    }

    // Post new announcement
    function postAdminAnnouncement() {
        const text = document.getElementById('admin-announcement-text').value.trim();
        if (!text) {
            alert('Please enter an announcement');
            return;
        }

        // Get current date in yyyy-mon-dd format
        const now = new Date();
        const year = now.getFullYear();
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const mon = months[now.getMonth()];
        const day = String(now.getDate()).padStart(2, '0');
        const dateStr = `${year}-${mon}-${day}`;

        // Save to localStorage (for admin view)
        const announcements = JSON.parse(localStorage.getItem('admin_announcements') || '[]');
        announcements.unshift({
            text: text,
            date: dateStr,
            postedAt: now.toISOString()
        });
        localStorage.setItem('admin_announcements', JSON.stringify(announcements));

        // Also save to database for user notifications
        fetch('admin_dashboard.php?action=post_announcement', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                title: 'New Announcement',
                message: text
            })
        })
        .then(res => res.json())
        .then(data => {
            console.log('Announcement saved:', data);
        })
        .catch(err => console.error('Error saving announcement:', err));

        // Clear textarea
        document.getElementById('admin-announcement-text').value = '';

        // Reload announcements
        loadAdminAnnouncements();

        alert('Announcement posted successfully!');
    }
    </script>
    
    <?php require_once 'search_modal.php'; ?>
</body>
</html>
