<?php
// Admin Reservations Page
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
    <title>Reservations - CCS Sit-In System</title>
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
            <a href="admin_leaderboard.php">Leaderboard</a>
            <a href="admin_students.php">Students</a>
            <a href="admin_sitin.php">Sit-In</a>
            <a href="admin_software.php">Software Manager</a>
            <a href="admin_records.php">View Sit-In Records</a>
            <a href="admin_reports.php">Sit-In Reports</a>
            <a href="admin_feedback.php">Feedback</a>
            <a href="admin_reservations.php" class="active">Reservations</a>
            <a href="../reg-log-prof/logout.php">Logout</a>
        </div>
    </nav>

    <div class="admin-content premium-admin-dashboard">
        <div class="dashboard-header-flex">
            <div>
                <span class="welcome-badge">BOOKING WORKFLOWS</span>
                <h1>Seat Reservations</h1>
            </div>
            <div class="admin-profile-badge">
                <span class="avatar">📅</span>
                <div>
                    <strong><?php echo htmlspecialchars($adminName); ?></strong>
                    <span>System Administrator</span>
                </div>
            </div>
        </div>

        <div class="chart-container-card catalog-viewer-card">
            <div class="catalog-viewer-header">
                <h2>Reservation Queue</h2>
                <div class="search-filter-box">
                    <input type="text" id="reservationSearch" placeholder="Search reservations..." onkeyup="filterReservations()" class="search-box">
                </div>
            </div>

            <div class="table-wrapper-outer">
                <table class="data-table" id="reservationsTable">
                    <thead>
                        <tr>
                            <th>Reservation ID</th>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Lab</th>
                            <th>PC</th>
                            <th>Date</th>
                            <th>Time Block</th>
                            <th>Purpose</th>
                            <th>Status</th>
                            <th style="text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="reservations-table-body">
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 30px;">
                                <div class="spinner-mini" style="display: inline-block;"></div>
                                <span style="margin-left: 10px; color: #888;">Fetching reservations queue...</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', loadReservations);
        
        function loadReservations() {
            fetch('admin_dashboard.php?action=reservations')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        displayReservations(data.reservations);
                    } else {
                        document.getElementById('reservations-table-body').innerHTML = '<tr><td colspan="10" style="text-align: center; color: var(--danger-color); padding: 20px;">Error loading reservations queue: ' + data.message + '</td></tr>';
                    }
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('reservations-table-body').innerHTML = '<tr><td colspan="10" style="text-align: center; color: var(--danger-color); padding: 20px;">Error connecting to API.</td></tr>';
                });
        }
        
        function displayReservations(reservations) {
            const tbody = document.getElementById('reservations-table-body');
            if (reservations.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10" style="text-align: center; color: #888; padding: 30px;">No seat bookings logged.</td></tr>';
                return;
            }
            
            let html = '';
            reservations.forEach(res => {
                const name = res.firstname + ' ' + res.lastname;
                const time = res.start_time + ' - ' + res.end_time;
                let actions = '';
                
                if (res.status === 'pending') {
                    actions = `<div class="reservation-actions">
                        <button class="btn-small" style="background: var(--success-color); color: white;" onclick="approveReservation(${res.id})">Approve</button>
                        <button class="btn-small btn-danger" onclick="denyReservation(${res.id})">Deny</button>
                    </div>`;
                } else {
                    actions = `<span style="color: #999; font-size: 13px;">No actions available</span>`;
                }
                
                let badgeClass = 'status-badge status-pending';
                if (res.status === 'approved') badgeClass = 'status-badge status-active';
                if (res.status === 'denied') badgeClass = 'status-badge status-denied';
                
                html += `<tr>
                    <td><strong>#${res.id}</strong></td>
                    <td><code>${res.id_number}</code></td>
                    <td><strong>${escapeHtml(name)}</strong></td>
                    <td><span class="lab-badge">Lab ${res.lab_number}</span></td>
                    <td><code>PC #${res.pc_number || 'N/A'}</code></td>
                    <td>${res.reservation_date}</td>
                    <td><code>${time}</code></td>
                    <td>${escapeHtml(res.purpose || 'N/A')}</td>
                    <td><span class="${badgeClass}">${res.status.charAt(0).toUpperCase() + res.status.slice(1)}</span></td>
                    <td style="text-align: center;">${actions}</td>
                </tr>`;
            });
            tbody.innerHTML = html;
        }
        
        function approveReservation(id) {
            if (!confirm('Approve this reservation? This will start a sit-in session and deduct 1 session token from the student.')) {
                return;
            }
            
            fetch('admin_dashboard.php?action=approve_reservation&id=' + id)
                .then(res => res.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) {
                        loadReservations();
                    }
                });
        }
        
        function denyReservation(id) {
            if (!confirm('Deny this reservation slot?')) {
                return;
            }
            
            fetch('admin_dashboard.php?action=deny_reservation&id=' + id)
                .then(res => res.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) {
                        loadReservations();
                    }
                });
        }
        
        function filterReservations() {
            const input = document.getElementById('reservationSearch');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('reservationsTable');
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                // Check if search matches or if it's the empty/loading state row
                if (row.cells.length === 1) return; 
                row.style.display = text.indexOf(filter) > -1 ? '' : 'none';
            });
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
