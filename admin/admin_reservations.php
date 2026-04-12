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
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin.css">
    <style>
        .reservation-actions {
            display: flex;
            gap: 5px;
        }
        .btn-approve {
            background: #28a745;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-deny {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-approve:hover {
            background: #218838;
        }
        .btn-deny:hover {
            background: #c82333;
        }
        .status-approved {
            color: #28a745;
            font-weight: bold;
            border: 1px solid #28a745;
            padding: 2px 8px;
            border-radius: 3px;
        }
        .status-denied {
            color: #dc3545;
            font-weight: bold;
            border: 1px solid #dc3545;
            padding: 2px 8px;
            border-radius: 3px;
        }
        .status-pending {
            color: #ffc107;
            font-weight: bold;
            border: 1px solid #ffc107;
            padding: 2px 8px;
            border-radius: 3px;
        }
        .action-status {
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: bold;
            display: inline-block;
        }
        .action-approved {
            background-color: #d4edda;
            color: #155724;
        }
        .action-denied {
            background-color: #f8d7da;
            color: #721c24;
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
            <a href="admin_students.php">Students</a>
            <a href="admin_sitin.php">Sit-In</a>
            <a href="admin_records.php">View Sit-In Records</a>
            <a href="admin_reports.php">Sit-In Reports</a>
            <a href="admin_feedback.php">Feedback</a>
            <a href="admin_reservations.php" class="active">Reservations</a>
            <a href="../reg-log-prof/logout.php">Logout</a>
        </div>
    </nav>

    <div class="admin-content">
        <h1>Reservations</h1>
        
        <div class="table-toolbar">
            <input type="text" id="reservationSearch" placeholder="Search..." onkeyup="filterReservations()" class="search-box">
        </div>
        
        <table class="data-table" id="reservationsTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Lab</th>
                    <th>PC</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Purpose</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="reservations-table-body">
                <tr><td colspan="10">Loading...</td></tr>
            </tbody>
        </table>
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
                        document.getElementById('reservations-table-body').innerHTML = '<tr><td colspan="10">Error loading reservations</td></tr>';
                    }
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('reservations-table-body').innerHTML = '<tr><td colspan="10">Error loading reservations</td></tr>';
                });
        }
        
        function displayReservations(reservations) {
            const tbody = document.getElementById('reservations-table-body');
            if (reservations.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10">No reservations found</td></tr>';
                return;
            }
            
            let html = '';
            reservations.forEach(res => {
                const name = res.firstname + ' ' + res.lastname;
                const time = res.start_time + ' - ' + res.end_time;
                let actions = '';
                
                if (res.status === 'pending') {
                    actions = `<div class="reservation-actions">
                        <button class="btn-approve" onclick="approveReservation(${res.id})">Approve</button>
                        <button class="btn-deny" onclick="denyReservation(${res.id})">Deny</button>
                    </div>`;
                } else {
                    actions = '';
                }
                
                let statusClass = res.status;
                html += `<tr>
                    <td>${res.id}</td>
                    <td>${res.id_number}</td>
                    <td>${name}</td>
                    <td>${res.lab_number}</td>
                    <td>${res.pc_number || 'N/A'}</td>
                    <td>${res.reservation_date}</td>
                    <td>${time}</td>
                    <td>${res.purpose || 'N/A'}</td>
                    <td><span class="status-${statusClass}">${res.status.charAt(0).toUpperCase() + res.status.slice(1)}</span></td>
                    <td>${actions}</td>
                </tr>`;
            });
            tbody.innerHTML = html;
        }
        
        function approveReservation(id) {
            if (!confirm('Approve this reservation? This will start a sit-in and deduct 1 session from the user.')) {
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
            if (!confirm('Deny this reservation?')) {
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
                row.style.display = text.indexOf(filter) > -1 ? '' : 'none';
            });
        }
    </script>
    
    <?php require_once 'search_modal.php'; ?>
</body>
</html>
