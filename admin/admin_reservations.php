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
            <a href="admin_pcs.php">PC Management</a>
            <a href="admin_records.php">View Sit-In Records</a>
            <a href="admin_reports.php">Sit-In Reports</a>
            <a href="admin_feedback.php">Feedback</a>
            <a href="admin_reservations.php" class="active">Reservations</a>
            <a href="../reg-log-prof/logout.php">Logout</a>
        </div>
    </nav>

    <style>
        .premium-admin-dashboard .dashboard-header-flex h1 {
            color: white;
            font-family: 'Outfit', sans-serif;
            font-size: 28px;
            margin: 10px 0 0 0;
        }

        .premium-admin-dashboard .welcome-badge {
            background: rgba(255, 255, 255, 0.15);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 1px;
            color: #d1d5db;
        }

        .card-premium {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .card-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .form-control-premium {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 14px;
            width: 100%;
            transition: all 0.2s;
            box-sizing: border-box;
            background: #f9fafb;
        }

        .form-control-premium:focus {
            outline: none;
            border-color: #4f46e5;
            background: white;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .btn-premium {
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
        }

        .btn-indigo {
            background: #4f46e5;
            color: white;
        }

        .btn-indigo:hover {
            background: #4338ca;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }

        .btn-emerald {
            background: #10b981;
            color: white;
        }

        .btn-emerald:hover {
            background: #059669;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .table-premium-wrapper {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .premium-table {
            width: 100%;
            border-collapse: collapse;
        }

        .premium-table th {
            background: #f8fafc;
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }

        .premium-table td {
            padding: 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            font-size: 14px;
        }

        .premium-table tr:hover td {
            background: #f8fafc;
        }

        .pill-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .pill-pending {
            background: #fef3c7;
            color: #b45309;
        }

        .pill-approved {
            background: #d1fae5;
            color: #047857;
        }

        .pill-active {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .pill-completed {
            background: #e2e8f0;
            color: #475569;
        }

        .pill-denied {
            background: #fee2e2;
            color: #b91c1c;
        }

        .action-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 30px;
        }
    </style>

    <div class="admin-content premium-admin-dashboard">
        <div class="dashboard-header-flex">
            <div class="admin-profile-badge">
                <span class="avatar">📅</span>
                <div>
                    <strong><?php echo htmlspecialchars($adminName); ?></strong>
                    <span style="color: #cbd5e1;">System Administrator</span>
                </div>
            </div>
        </div>

        <div class="card-premium" style="margin-bottom: 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0; font-family: 'Outfit', sans-serif; color: black;">Reservation Queue</h2>
                <input type="text" id="reservationSearch" placeholder="Search reservations..." onkeyup="filterReservations()" class="form-control-premium" style="width: 300px;">
            </div>

            <div class="table-premium-wrapper">
                <table class="premium-table" id="reservationsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>Lab</th>
                            <th>PC</th>
                            <th>Date / Time</th>
                            <th>Purpose</th>
                            <th>Status</th>
                            <th style="text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="reservations-table-body">
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px;">
                                <div class="spinner-mini" style="display: inline-block;"></div>
                                <span style="margin-left: 10px; color: #64748b;">Loading queue...</span>
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
                    actions = `<div style="display: flex; gap: 8px; justify-content: center;">
                        <button class="btn-premium btn-emerald" style="padding: 6px 12px; font-size: 12px;" onclick="approveReservation(${res.id})">Approve</button>
                        <button class="btn-premium" style="background: #fee2e2; color: #b91c1c; padding: 6px 12px; font-size: 12px;" onclick="denyReservation(${res.id})">Deny</button>
                    </div>`;
                } else if (res.status === 'approved') {
                    actions = `<div style="display: flex; gap: 8px; justify-content: center;">
                        <button class="btn-premium btn-indigo" style="padding: 6px 12px; font-size: 12px;" onclick="startReservationSitIn(${res.id})">Start Sit-In</button>
                        <button class="btn-premium" style="background: #f1f5f9; color: #64748b; padding: 6px 12px; font-size: 12px;" onclick="denyReservation(${res.id})">Cancel</button>
                    </div>`;
                } else if (res.status === 'active') {
                    actions = `<div style="display: flex; justify-content: center;">
                        <button class="btn-premium" style="background: #fee2e2; color: #b91c1c; padding: 6px 12px; font-size: 12px;" onclick="endReservationSession(${res.id})">End Session</button>
                    </div>`;
                } else {
                    actions = `<span style="color: #94a3b8; font-size: 12px;">N/A</span>`;
                }

                let badgeClass = 'pill-badge pill-pending';
                if (res.status === 'approved') badgeClass = 'pill-badge pill-approved';
                if (res.status === 'active') badgeClass = 'pill-badge pill-active';
                if (res.status === 'denied') badgeClass = 'pill-badge pill-denied';
                if (res.status === 'completed') badgeClass = 'pill-badge pill-completed';

                html += `<tr>
                    <td><strong>#${res.id}</strong></td>
                    <td>
                        <div style="font-weight: 600;">${escapeHtml(name)}</div>
                        <div style="color: #64748b; font-size: 12px; margin-top: 2px;">${res.id_number}</div>
                    </td>
                    <td><span style="font-weight: 500;">Lab ${res.lab_number}</span></td>
                    <td><code style="background: #f1f5f9; padding: 4px 8px; border-radius: 4px; color: #475569;">PC ${res.pc_number || '-'}</code></td>
                    <td>
                        <div>${res.reservation_date}</div>
                        <div style="color: #64748b; font-size: 12px; margin-top: 2px;">${time}</div>
                    </td>
                    <td>${escapeHtml(res.purpose || '-')}</td>
                    <td><span class="${badgeClass}">${res.status.charAt(0).toUpperCase() + res.status.slice(1)}</span></td>
                    <td style="text-align: center;">${actions}</td>
                </tr>`;
            });
            tbody.innerHTML = html;
        }

        function approveReservation(id) {
            if (!confirm('Approve this reservation? (This will also automatically start the sit-in and deduct 1 session token)')) {
                return;
            }

            fetch('admin_dashboard.php?action=approve_reservation&id=' + id)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        fetch('admin_dashboard.php?action=start_reservation_sitin&id=' + id)
                            .then(res2 => res2.json())
                            .then(data2 => {
                                alert(data.message + '\n' + data2.message);
                                loadReservations();
                            });
                    } else {
                        alert(data.message);
                    }
                });
        }

        function startReservationSitIn(id) {
            if (!confirm('Start sit-in for this reservation? This will start the session and deduct 1 session token.')) {
                return;
            }

            fetch('admin_dashboard.php?action=start_reservation_sitin&id=' + id)
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

        function endReservationSession(id) {
            if (!confirm('End this active student session? This will complete the reservation, end the active sit-in, and release the PC.')) {
                return;
            }

            fetch('admin_dashboard.php?action=end_reservation_session&id=' + id)
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

        function handlePhysicalSitIn(e) {
            e.preventDefault();
            const studentId = document.getElementById('scanStudentId').value.trim();
            if (!studentId) return;

            const msgDiv = document.getElementById('scanMessage');
            msgDiv.textContent = 'Processing...';
            msgDiv.style.color = '#666';

            fetch('admin_dashboard.php?action=physical_sitin', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        student_id: studentId
                    })
                })
                .then(res => res.json())
                .then(data => {
                    msgDiv.textContent = data.message;
                    if (data.success) {
                        msgDiv.style.color = 'var(--success-color)';
                        document.getElementById('scanStudentId').value = '';
                        loadReservations();
                    } else {
                        msgDiv.style.color = 'var(--danger-color)';
                    }

                    // Clear message after 5 seconds
                    setTimeout(() => {
                        if (msgDiv.textContent === data.message) {
                            msgDiv.textContent = '';
                        }
                    }, 5000);
                })
                .catch(err => {
                    msgDiv.textContent = 'Connection error.';
                    msgDiv.style.color = 'var(--danger-color)';
                });
        }

        function handleInstantPurposeChange() {
            var select = document.getElementById('instantPurpose');
            var otherInput = document.getElementById('instantPurposeOther');
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

        async function handleInstantSitIn(e) {
            e.preventDefault();
            const studentId = document.getElementById('instantStudentId').value.trim();
            const lab = document.getElementById('instantLab').value;
            let purpose = document.getElementById('instantPurpose').value;
            const pc = document.getElementById('instantSelectedPc').value;
            const timeSlot = document.getElementById('instantTimeSlot').value;

            if (purpose === 'Others') {
                const otherText = document.getElementById('instantPurposeOther').value.trim();
                if (!otherText) {
                    alert('Please specify custom purpose');
                    return;
                }
                purpose = 'Others: ' + otherText;
            }

            if (!studentId || !lab || !purpose || !timeSlot) {
                alert('Please fill all required fields');
                return;
            }

            const msgDiv = document.getElementById('instantMessage');
            msgDiv.textContent = 'Processing...';
            msgDiv.style.color = '#666';

            const formData = new FormData();
            formData.append('id_number', studentId);
            formData.append('purpose', purpose);
            formData.append('lab', lab);
            if (pc) formData.append('pc_number', pc);
            if (timeSlot) formData.append('time_slot', timeSlot);

            try {
                const response = await fetch('api_start_sitin.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    msgDiv.textContent = 'Instant Sit-In started successfully!';
                    msgDiv.style.color = 'var(--success-color)';
                    document.getElementById('instantSitInForm').reset();
                    document.getElementById('instantPurposeOther').style.display = 'none';
                    document.getElementById('instantPurposeOther').required = false;
                    document.getElementById('instantSelectedPc').value = '';
                    document.getElementById('instantSelectedPcDisplay').textContent = '';
                    document.getElementById('instantSelectPcBtn').disabled = true;
                    document.getElementById('instantSelectPcBtn').style.background = '#6c757d';
                    loadReservations();
                } else {
                    msgDiv.textContent = data.message || 'Error starting sit-in.';
                    msgDiv.style.color = 'var(--danger-color)';
                }
            } catch (err) {
                msgDiv.textContent = 'Connection error.';
                msgDiv.style.color = 'var(--danger-color)';
            }

            setTimeout(() => {
                if (msgDiv.textContent.includes('successfully') || msgDiv.textContent.includes('Error')) {
                    msgDiv.textContent = '';
                }
            }, 5000);
        }

        function onInstantLabChange() {
            var lab = document.getElementById('instantLab').value;
            var pcBtn = document.getElementById('instantSelectPcBtn');
            var pcDisplay = document.getElementById('instantSelectedPcDisplay');
            var selectedPc = document.getElementById('instantSelectedPc');

            if (lab) {
                pcBtn.disabled = false;
                pcBtn.style.background = '#144d94';
                selectedPc.value = '';
                pcDisplay.textContent = '';
            } else {
                pcBtn.disabled = true;
                pcBtn.style.background = '#6c757d';
                selectedPc.value = '';
                pcDisplay.textContent = '';
            }
        }

        async function openInstantPCModal() {
            var lab = document.getElementById('instantLab').value;
            if (!lab) return;

            var pcModalHtml = `
            <div id="instantPcSelectModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; justify-content: center; align-items: center;">
                <div style="background: white; padding: 30px; border-radius: 10px; width: 90%; max-width: 500px; position: relative; box-shadow: 0 4px 20px rgba(0,0,0,0.3); max-height: 80vh; overflow-y: auto;">
                    <span onclick="closeInstantPCModal()" style="position: absolute; top: 10px; right: 20px; font-size: 28px; cursor: pointer; color: #666;">&times;</span>
                    <h2 style="margin-top: 0; font-family: 'Outfit', sans-serif; color: black;">Select PC - Lab ${lab}</h2>
                    <div id="instantPcGridLoader" style="text-align: center; padding: 40px 10px; color: #144d94;">
                        <p style="margin: 0; font-weight: 600;">Loading PC status...</p>
                    </div>
                    <div id="instantPcOptionsGrid" class="pc-grid" style="display: none;"></div>
                </div>
            </div>
            `;

            var existing = document.getElementById('instantPcSelectModal');
            if (existing) {
                existing.remove();
            }

            document.body.insertAdjacentHTML('beforeend', pcModalHtml);

            try {
                var timeSlot = document.getElementById('instantTimeSlot').value.trim();
                var url = 'api_get_occupied_pcs.php?lab=' + encodeURIComponent(lab);
                if (timeSlot) {
                    url += '&time_slot=' + encodeURIComponent(timeSlot);
                }
                var response = await fetch(url);
                var data = await response.json();

                var occupied = [];
                if (data.success) {
                    occupied = data.occupied || [];
                }

                var pcCount = 56;
                var pcOptionsHtml = '';

                for (var i = 1; i <= pcCount; i++) {
                    var isOccupied = occupied.includes(i);
                    var occupiedClass = isOccupied ? 'occupied' : '';
                    var clickHandler = isOccupied ? '' : 'onclick="selectInstantPC(' + i + ')"';
                    var titleText = isOccupied ? 'PC ' + i + ' (Occupied)' : 'PC ' + i;

                    pcOptionsHtml += `<div class="pc-option ${occupiedClass}" ${clickHandler} title="${titleText}">${i}</div>`;
                }

                document.getElementById('instantPcGridLoader').style.display = 'none';
                var grid = document.getElementById('instantPcOptionsGrid');
                grid.innerHTML = pcOptionsHtml;
                grid.style.display = 'grid';

            } catch (error) {
                document.getElementById('instantPcGridLoader').innerHTML = '<p style="color: #dc3545; margin: 0;">Error loading PC status. Please close and try again.</p>';
            }
        }

        function selectInstantPC(pcNum) {
            document.getElementById('instantSelectedPc').value = pcNum;
            document.getElementById('instantSelectedPcDisplay').textContent = 'PC ' + pcNum;
            closeInstantPCModal();
        }

        function closeInstantPCModal() {
            var modal = document.getElementById('instantPcSelectModal');
            if (modal) {
                modal.remove();
            }
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