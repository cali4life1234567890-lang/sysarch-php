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
                <img src="../imgs/uclogo.png" alt="University Logo" class="logo-main" />
                <img src="../imgs/ccslogo.png" alt="Department Logo" class="logo-sub" />
                <h1 class="system-title">CCS Sit-In Monitoring System</h1>
            </a>
        </div>
        <div class="nav-links admin-links">
            <a href="admin_home.php" class="active">Home</a>
            <a href="#" onclick="openSearchModal(); return false;">Search</a>
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
        
        <div class="dashboard-cards">
            <div class="dash-card">
                <h3>Total Students</h3>
                <p class="dash-number"><?php echo $stats['total_students']; ?></p>
            </div>
            <div class="dash-card">
                <h3>Today's Sit-In</h3>
                <p class="dash-number"><?php echo $stats['today_sitin']; ?></p>
            </div>
            <div class="dash-card">
                <h3>Total Records</h3>
                <p class="dash-number"><?php echo $stats['total_records']; ?></p>
            </div>
            <div class="dash-card">
                <h3>Pending Reservations</h3>
                <p class="dash-number"><?php echo $stats['pending_reservations']; ?></p>
            </div>
        </div>

        <!-- Announcement Section -->
        <div class="announcement-admin-section">
            <h2>📢 Post Announcement</h2>
            <div class="announcement-form">
                <textarea id="admin-announcement-text" placeholder="Write your announcement here..." rows="4"></textarea>
                <button class="btn-primary" onclick="postAdminAnnouncement()">Post Announcement</button>
            </div>

            <h3>Posted Announcements</h3>
            <div id="admin-announcement-list" class="admin-announcement-list">
                <p class="no-announcements">No announcements yet</p>
            </div>
        </div>

        <div class="quick-actions">
            <h2>Quick Actions</h2>
            <div class="action-buttons">
                <a href="admin_sitin.php" class="action-btn">Start New Sit-In</a>
                <a href="admin_students.php" class="action-btn">View All Students</a>
                <a href="admin_records.php" class="action-btn">View Records</a>
                <a href="admin_reports.php" class="action-btn">Generate Reports</a>
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
            html += `<div class="admin-announcement-item">
                <div class="admin-announcement-header">
                    <strong>CCS Admin</strong>
                    <span class="admin-announcement-date">${announcement.date}</span>
                </div>
                <p>${announcement.text}</p>
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

        // Get existing announcements
        const announcements = JSON.parse(localStorage.getItem('admin_announcements') || '[]');

        // Add new announcement at the beginning
        announcements.unshift({
            text: text,
            date: dateStr,
            postedAt: now.toISOString()
        });

        // Save to localStorage
        localStorage.setItem('admin_announcements', JSON.stringify(announcements));

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
