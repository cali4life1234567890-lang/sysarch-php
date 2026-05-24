<?php
// Admin PC Management Page
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
    <title>PC Management - CCS Sit-In System</title>
    <link rel="icon" href="../imgs/ccslogo.png" type="image/png" />
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .pc-grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .pc-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            transition: all 0.2s;
            position: relative;
        }
        .pc-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .pc-item.available { border-top: 4px solid #10b981; }
        .pc-item.occupied { border-top: 4px solid #f59e0b; }
        .pc-item.maintenance { border-top: 4px solid #ef4444; opacity: 0.8; }
        
        .pc-number {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 5px;
            font-family: 'Outfit', sans-serif;
        }
        
        .pc-status {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 15px;
        }
        .available .pc-status { color: #10b981; }
        .occupied .pc-status { color: #f59e0b; }
        .maintenance .pc-status { color: #ef4444; }
        
        .toggle-btn {
            background: #e2e8f0;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
        }
        .toggle-btn:hover { background: #cbd5e1; }
        .toggle-btn.btn-enable { background: #d1fae5; color: #047857; }
        .toggle-btn.btn-enable:hover { background: #a7f3d0; }
        .toggle-btn.btn-disable { background: #fee2e2; color: #b91c1c; }
        .toggle-btn.btn-disable:hover { background: #fecaca; }
        
        .lab-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .lab-tab {
            padding: 10px 20px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            color: #64748b;
            transition: all 0.2s;
        }
        .lab-tab:hover {
            background: #f8fafc;
        }
        .lab-tab.active {
            background: #4f46e5;
            color: white;
            border-color: #4f46e5;
        }
        
        .stats-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 500;
            color: #475569;
        }
        .stat-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }
        .dot-available { background: #10b981; }
        .dot-occupied { background: #f59e0b; }
        .dot-maintenance { background: #ef4444; }

        /* Modal Styles */
        .pc-modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .pc-modal {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            padding: 25px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .pc-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        .pc-modal-title { font-size: 20px; font-weight: 700; color: #1e293b; }
        .close-modal-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b; }
        .pc-detail-section { margin-bottom: 15px; }
        .pc-detail-title { font-size: 14px; font-weight: 600; color: #64748b; text-transform: uppercase; margin-bottom: 5px; }
        .pc-detail-content { font-size: 15px; color: #1e293b; background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .pc-res-item { padding: 10px; border-bottom: 1px solid #e2e8f0; }
        .pc-res-item:last-child { border-bottom: none; }
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
            <a href="admin_software.php">Software Manager</a>
            <a href="admin_pcs.php" class="active">PC Management</a>
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
                <span class="welcome-badge">HARDWARE CONTROL</span>
                <h1>PC Management</h1>
            </div>
            <div class="admin-profile-badge">
                <span class="avatar">🖥️</span>
                <div>
                    <strong><?php echo htmlspecialchars($adminName); ?></strong>
                    <span>System Administrator</span>
                </div>
            </div>
        </div>

        <div class="chart-container-card">
            <div class="catalog-viewer-header">
                <h2>Laboratory Workstations</h2>
                <p style="color: #64748b; margin-top: 5px; font-size: 14px;">Enable or disable individual computers across all laboratories.</p>
            </div>

            <div class="lab-selector">
                <button class="lab-tab active" onclick="loadLabPCs('524')">Lab 524</button>
                <button class="lab-tab" onclick="loadLabPCs('526')">Lab 526</button>
                <button class="lab-tab" onclick="loadLabPCs('528')">Lab 528</button>
                <button class="lab-tab" onclick="loadLabPCs('530')">Lab 530</button>
                <button class="lab-tab" onclick="loadLabPCs('MAC')">MAC Lab</button>
            </div>
            
            <div class="stats-row">
                <div class="stat-badge"><div class="stat-dot dot-available"></div> <span id="count-available">0</span> Available</div>
                <div class="stat-badge"><div class="stat-dot dot-occupied"></div> <span id="count-occupied">0</span> Occupied</div>
                <div class="stat-badge"><div class="stat-dot dot-maintenance"></div> <span id="count-maintenance">0</span> Disabled</div>
            </div>

            <div id="loading-spinner" style="text-align: center; padding: 40px; display: none;">
                <div class="spinner-mini" style="display: inline-block;"></div>
                <span style="margin-left: 10px; color: #888;">Fetching PC status...</span>
            </div>

            <div class="pc-grid-container" id="pc-grid">
                <!-- PCs will be loaded here -->
            </div>
        </div>
    </div>

    <div id="pcDetailsModal" class="pc-modal-overlay">
        <div class="pc-modal">
            <div class="pc-modal-header">
                <div class="pc-modal-title" id="pcModalTitle">PC Details</div>
                <button class="close-modal-btn" onclick="closePcModal()">&times;</button>
            </div>
            <div id="pcModalBody">
                <!-- Details will be loaded here -->
                <div style="text-align:center; padding: 20px;"><div class="spinner-mini" style="display: inline-block;"></div> Loading...</div>
            </div>
        </div>
    </div>

    <script>
        let currentLab = '524';

        document.addEventListener('DOMContentLoaded', () => {
            loadLabPCs(currentLab);
        });

        function loadLabPCs(lab) {
            currentLab = lab;
            
            // Update tabs
            document.querySelectorAll('.lab-tab').forEach(tab => {
                tab.classList.remove('active');
                if (tab.textContent.includes(lab)) {
                    tab.classList.add('active');
                }
            });
            
            document.getElementById('pc-grid').innerHTML = '';
            document.getElementById('loading-spinner').style.display = 'block';

            fetch(`admin_dashboard.php?action=get_pcs&lab=${encodeURIComponent(lab)}`)
                .then(res => res.json())
                .then(data => {
                    document.getElementById('loading-spinner').style.display = 'none';
                    if (data.success) {
                        renderPCs(data.pcs);
                    } else {
                        document.getElementById('pc-grid').innerHTML = `<p style="color: red; grid-column: 1/-1;">Error: ${data.message}</p>`;
                    }
                })
                .catch(err => {
                    document.getElementById('loading-spinner').style.display = 'none';
                    document.getElementById('pc-grid').innerHTML = `<p style="color: red; grid-column: 1/-1;">Connection Error.</p>`;
                });
        }

        function renderPCs(pcs) {
            const grid = document.getElementById('pc-grid');
            let html = '';
            
            let availableCount = 0;
            let occupiedCount = 0;
            let maintenanceCount = 0;

            pcs.forEach(pc => {
                const statusClass = pc.status === 'available' ? 'available' : 
                                    pc.status === 'occupied' ? 'occupied' : 'maintenance';
                const statusText = pc.status === 'available' ? 'Available' : 
                                   pc.status === 'occupied' ? 'Occupied' : 'Disabled';
                
                if (pc.status === 'available') availableCount++;
                else if (pc.status === 'occupied') occupiedCount++;
                else maintenanceCount++;
                
                let actionBtn = '';
                if (pc.status === 'available' || pc.status === 'occupied') {
                    // It's enabled, show button to disable
                    actionBtn = `<button class="toggle-btn btn-disable" onclick="event.stopPropagation(); togglePCStatus(${pc.id}, 'maintenance')">Disable PC</button>`;
                } else {
                    // It's disabled, show button to enable
                    actionBtn = `<button class="toggle-btn btn-enable" onclick="event.stopPropagation(); togglePCStatus(${pc.id}, 'available')">Enable PC</button>`;
                }

                html += `
                    <div class="pc-item ${statusClass}" onclick="viewPcDetails(${pc.pc_number})" style="cursor: pointer;">
                        <div class="pc-number">${pc.pc_number}</div>
                        <div class="pc-status">${statusText}</div>
                        ${actionBtn}
                    </div>
                `;
            });
            
            grid.innerHTML = html;
            
            document.getElementById('count-available').textContent = availableCount;
            document.getElementById('count-occupied').textContent = occupiedCount;
            document.getElementById('count-maintenance').textContent = maintenanceCount;
        }

        function togglePCStatus(id, newStatus) {
            const actionText = newStatus === 'maintenance' ? 'Disable' : 'Enable';
            if (!confirm(`Are you sure you want to ${actionText} this PC?`)) {
                return;
            }

            fetch('admin_dashboard.php?action=update_pc_status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, status: newStatus })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadLabPCs(currentLab);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => alert('Connection error.'));
        }

        function viewPcDetails(pcNumber) {
            document.getElementById('pcDetailsModal').style.display = 'flex';
            document.getElementById('pcModalTitle').textContent = `Lab ${currentLab} - PC ${pcNumber}`;
            document.getElementById('pcModalBody').innerHTML = `<div style="text-align:center; padding: 20px;"><div class="spinner-mini" style="display: inline-block;"></div> Loading...</div>`;

            fetch(`admin_dashboard.php?action=get_pc_details&lab=${encodeURIComponent(currentLab)}&pc=${encodeURIComponent(pcNumber)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        let html = '';
                        const details = data.details;

                        if (details.active_session) {
                            html += `
                                <div class="pc-detail-section">
                                    <div class="pc-detail-title">Current Active User</div>
                                    <div class="pc-detail-content">
                                        <strong>${details.active_session.name}</strong> (${details.active_session.id_number})<br>
                                        <span style="font-size: 13px; color: #64748b;">Time In: ${details.active_session.time_in}</span><br>
                                        <span style="font-size: 13px; color: #64748b;">Purpose: ${details.active_session.purpose}</span>
                                    </div>
                                </div>
                            `;
                        } else {
                            html += `<div class="pc-detail-section"><div class="pc-detail-title">Current Status</div><div class="pc-detail-content">Currently unoccupied.</div></div>`;
                        }

                        if (details.reservations && details.reservations.length > 0) {
                            html += `<div class="pc-detail-section"><div class="pc-detail-title">Today's Reservations</div><div class="pc-detail-content" style="padding: 0;">`;
                            details.reservations.forEach(res => {
                                html += `
                                    <div class="pc-res-item">
                                        <strong>${res.name}</strong> (${res.id_number})<br>
                                        <span style="font-size: 13px; color: #64748b;">${res.start_time} - ${res.end_time}</span>
                                        <span style="font-size: 12px; float: right; padding: 2px 6px; border-radius: 4px; background: #e2e8f0;">${res.status}</span>
                                    </div>
                                `;
                            });
                            html += `</div></div>`;
                        } else {
                            html += `<div class="pc-detail-section"><div class="pc-detail-title">Today's Reservations</div><div class="pc-detail-content">No upcoming reservations for today.</div></div>`;
                        }

                        document.getElementById('pcModalBody').innerHTML = html;
                    } else {
                        document.getElementById('pcModalBody').innerHTML = `<p style="color: red; padding: 20px;">Error: ${data.message}</p>`;
                    }
                })
                .catch(err => {
                    document.getElementById('pcModalBody').innerHTML = `<p style="color: red; padding: 20px;">Connection Error.</p>`;
                });
        }

        function closePcModal() {
            document.getElementById('pcDetailsModal').style.display = 'none';
        }

        // Close modal when clicking outside
        document.getElementById('pcDetailsModal').addEventListener('click', function(e) {
            if (e.target === this) closePcModal();
        });
    </script>
    <?php require_once 'search_modal.php'; ?>
</body>
</html>
