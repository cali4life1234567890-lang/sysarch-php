<?php
// Admin Feedback Page
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

$adminName = $_SESSION['name'] ?? 'Admin';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - CCS Sit-In System</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin.css">
    <style>
        .feedback-cards {
            display: grid;
            gap: 15px;
            margin-top: 20px;
        }
        .feedback-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
        }
        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .feedback-student {
            font-weight: bold;
            color: #333;
        }
        .feedback-rating {
            color: #ffc107;
        }
        .feedback-date {
            color: #666;
            font-size: 12px;
        }
        .feedback-text {
            color: #333;
            line-height: 1.5;
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
            <a href="admin_leaderboard.php">Leaderboard</a>
            <a href="admin_students.php">Students</a>
            <a href="admin_sitin.php">Sit-In</a>
            <a href="admin_records.php">View Sit-In Records</a>
            <a href="admin_reports.php">Sit-In Reports</a>
            <a href="admin_feedback.php" class="active">Feedback</a>
            <a href="admin_reservations.php">Reservations</a>
            <a href="../reg-log-prof/logout.php">Logout</a>
        </div>
    </nav>

    <div class="admin-content">
        <h1>Feedback</h1>
        
        <div id="feedback-container">
            <p>Loading feedback...</p>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            loadFeedback();
        });

        function loadFeedback() {
            fetch('admin_dashboard.php?action=feedback')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        displayFeedback(data.feedback);
                    } else {
                        document.getElementById('feedback-container').innerHTML = '<p>Error loading feedback</p>';
                    }
                })
                .catch(err => {
                    document.getElementById('feedback-container').innerHTML = '<p>Error loading feedback</p>';
                });
        }

        function displayFeedback(feedbackList) {
            const container = document.getElementById('feedback-container');
            
            if (!feedbackList || feedbackList.length === 0) {
                container.innerHTML = '<div class="empty-state"><p>No feedback submitted yet.</p><p class="sub-text">Feedback from students will appear here.</p></div>';
                return;
            }

            let html = '<div class="feedback-cards">';
            feedbackList.forEach(item => {
                const stars = '★'.repeat(item.rating) + '☆'.repeat(5 - item.rating);
                const fullName = (item.firstname || '') + ' ' + (item.lastname || '');
                html += `<div class="feedback-card">
                    <div class="feedback-header">
                        <span class="feedback-student">${item.id_number} - ${fullName}</span>
                        <span class="feedback-rating">${stars}</span>
                    </div>
                    <div class="feedback-date">${item.created_at}</div>
                    <p class="feedback-text">${item.feedback_text}</p>
                </div>`;
            });
            html += '</div>';
            container.innerHTML = html;
        }
    </script>
    
    <?php require_once 'search_modal.php'; ?>
</body>
</html>
