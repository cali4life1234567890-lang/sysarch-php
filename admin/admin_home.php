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

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM reservations WHERE status = 'pending'");
    $stats['pending_reservations'] = $stmt->fetch()['count'];
} catch (PDOException $e) {
    // Ignore errors
}

// Get current active sit-ins (quick control board)
$activeSitIns = [];
try {
    $stmt = $pdo->query("
        SELECT r.id, r.lab_number, r.time_in, r.purpose, u.id_number, u.firstname, u.lastname
        FROM sitin_records r
        JOIN users u ON r.user_id = u.id
        WHERE r.time_out IS NULL
        ORDER BY r.time_in DESC
        LIMIT 5
    ");
    $activeSitIns = $stmt->fetchAll();
} catch (PDOException $e) {
    // Ignore
}

$adminName = $_SESSION['name'] ?? 'Admin';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CCS Sit-In System</title>
    <link rel="icon" href="../imgs/ccslogo.png" type="image/png" />
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin.css">
    <!-- Google Fonts for premium typography -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <span class="welcome-badge">SYSTEM OVERVIEW</span>
                <h1>Admin Control Panel</h1>
            </div>
            <div class="admin-profile-badge">
                <span class="avatar">👨‍💼</span>
                <div>
                    <strong><?php echo htmlspecialchars($adminName); ?></strong>
                    <span>System Administrator</span>
                </div>
            </div>
        </div>
        
        <!-- Metrics Row -->
        <div class="metrics-grid">
            <div class="metric-card card-blue">
                <div class="metric-header">
                    <span class="metric-icon">👥</span>
                    <span class="metric-trend">Registered</span>
                </div>
                <h3 class="metric-value"><?php echo $stats['total_students']; ?></h3>
                <span class="metric-label">Total Students</span>
            </div>
            <div class="metric-card card-green">
                <div class="metric-header">
                    <span class="metric-icon">🖥️</span>
                    <span class="metric-trend active-pulse">Live Now</span>
                </div>
                <h3 class="metric-value"><?php echo $stats['today_sitin']; ?></h3>
                <span class="metric-label">Today's Active Sit-Ins</span>
            </div>
            <div class="metric-card card-orange">
                <div class="metric-header">
                    <span class="metric-icon">📁</span>
                    <span class="metric-trend">All-Time</span>
                </div>
                <h3 class="metric-value"><?php echo $stats['total_records']; ?></h3>
                <span class="metric-label">Total Sit-In Logs</span>
            </div>
            <div class="metric-card card-purple">
                <div class="metric-header">
                    <span class="metric-icon">📅</span>
                    <span class="metric-trend action-required">Action Required</span>
                </div>
                <h3 class="metric-value"><?php echo $stats['pending_reservations']; ?></h3>
                <span class="metric-label">Pending Reservations</span>
            </div>
        </div>

        <!-- Charts Section (Two columns) -->
        <div class="analytics-grid">
            <div class="chart-container-card">
                <div class="chart-card-header">
                    <h3>🕒 Daily Sit-In Traffic Trend</h3>
                    <p>Check-ins logged over the past 7 days</p>
                </div>
                <div class="chart-wrapper">
                    <canvas id="trafficTrendChart"></canvas>
                </div>
            </div>

            <div class="chart-container-card">
                <div class="chart-card-header">
                    <h3>📊 Laboratory Occupancy Levels</h3>
                    <p>Current PC occupancy per laboratory</p>
                </div>
                <div class="chart-wrapper">
                    <canvas id="labOccupancyChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Third Chart & AI Adviser Row -->
        <div class="analytics-sub-grid">
            <div class="chart-container-card purpose-distribution-card">
                <div class="chart-card-header">
                    <h3>🎯 Top Sit-In Purposes</h3>
                    <p>Most common programming goals & study subjects</p>
                </div>
                <div class="chart-wrapper purpose-chart-wrapper">
                    <canvas id="purposeDistributionChart"></canvas>
                </div>
            </div>

            <div class="chart-container-card ai-advisor-card">
                <div class="ai-advisor-header">
                    <div class="ai-sparkles-logo">
                        <div class="ai-logo-inner">🧠</div>
                        <div class="ai-glow-pulse"></div>
                    </div>
                    <div class="ai-header-text">
                        <h3>AI Lab & Software Advisor</h3>
                        <span class="ai-badge">Dynamic Insights</span>
                    </div>
                </div>
                <p class="ai-advisor-subtitle">Dynamic data analysis formulated from database traffic logs, software gap analysis, and peak hours.</p>
                <button class="btn-ai-generate" onclick="generateAIRecommendations()">
                    <span>⚡ Generate AI Optimization Plan</span>
                </button>
                <div id="ai-advisor-results" class="ai-recommendations-list">
                    <p class="ai-empty-state">Click the button above to synthesize live analytics and generate AI optimization recommendations.</p>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <!-- Left Column: Active Sit-Ins Mini-Board -->
            <div class="dashboard-left">
                <div class="quick-control-card">
                    <div class="quick-control-header">
                        <h2>🖥️ Active Sit-in Control Board</h2>
                        <a href="admin_sitin.php" class="view-all-link">Manage All &rarr;</a>
                    </div>
                    <div class="quick-control-body">
                        <?php if (empty($activeSitIns)): ?>
                        <div class="empty-state-mini">
                            <span>📡</span>
                            <p>No students currently checked in.</p>
                        </div>
                        <?php else: ?>
                        <div class="mini-table-wrapper">
                            <table class="mini-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Room</th>
                                        <th>Purpose</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activeSitIns as $sitin): ?>
                                    <tr>
                                        <td>
                                            <div class="student-mini-info">
                                                <strong><?php echo htmlspecialchars($sitin['firstname'] . ' ' . $sitin['lastname']); ?></strong>
                                                <span><?php echo htmlspecialchars($sitin['id_number']); ?></span>
                                            </div>
                                        </td>
                                        <td><span class="lab-badge">Lab <?php echo htmlspecialchars($sitin['lab_number']); ?></span></td>
                                        <td><?php echo htmlspecialchars($sitin['purpose']); ?></td>
                                        <td>
                                            <button class="btn-small btn-danger" onclick="endDashboardSitIn(<?php echo $sitin['id']; ?>)">End</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Announcements -->
            <div class="dashboard-right">
                <!-- New Assignment Input Section -->
                <div class="assignment-card">
                    <div class="assignment-card-header">
                        <h2>📢 Broadcast Announcement</h2>                    
                    </div>
                    <div class="assignment-form">
                        <textarea id="admin-announcement-text" class="assignment-textarea" placeholder="Write announcement text here to notify all students..."></textarea>
                        <button class="btn-submit" onclick="postAdminAnnouncement()">Post Announcement</button>
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
    let trafficChart, occupancyChart, purposeChart;

    // Load announcements on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadAdminAnnouncements();
        loadAnalyticsData();
    });

    async function loadAnalyticsData() {
        try {
            const response = await fetch('admin_dashboard.php?action=analytics_data');
            const data = await response.json();
            if (data.success) {
                renderTrafficTrendChart(data.weekly_traffic);
                renderLabOccupancyChart(data.lab_occupancy);
                renderPurposeDistributionChart(data.purpose_distribution);
            } else {
                console.error('Failed to load analytics data:', data.message);
            }
        } catch (err) {
            console.error('Error loading analytics:', err);
        }
    }

    function renderTrafficTrendChart(weeklyData) {
        const ctx = document.getElementById('trafficTrendChart').getContext('2d');
        const labels = weeklyData.map(item => item.date);
        const counts = weeklyData.map(item => item.count);
        
        // Create gradient
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(20, 77, 148, 0.4)');
        gradient.addColorStop(1, 'rgba(20, 77, 148, 0.0)');
        
        if (trafficChart) trafficChart.destroy();
        
        trafficChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Check-ins',
                    data: counts,
                    borderColor: '#144d94',
                    borderWidth: 3,
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#144d94',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, color: '#888' },
                        grid: { borderDash: [5, 5], color: '#eef' }
                    },
                    x: {
                        ticks: { color: '#888' },
                        grid: { display: false }
                    }
                }
            }
        });
    }

    function renderLabOccupancyChart(occupancyData) {
        const ctx = document.getElementById('labOccupancyChart').getContext('2d');
        const labels = occupancyData.map(item => item.lab);
        const rates = occupancyData.map(item => item.occupancy_rate);
        
        if (occupancyChart) occupancyChart.destroy();
        
        occupancyChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Occupancy Rate (%)',
                    data: rates,
                    backgroundColor: rates.map(rate => {
                        if (rate > 80) return 'rgba(220, 53, 69, 0.85)'; // red
                        if (rate > 50) return 'rgba(255, 193, 7, 0.85)'; // yellow
                        return 'rgba(40, 167, 69, 0.85)'; // green
                    }),
                    borderRadius: 6,
                    borderWidth: 0,
                    maxBarThickness: 45
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const item = occupancyData[context.dataIndex];
                                return `Occupancy: ${context.raw}% (${item.active}/${item.capacity} PCs)`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: { color: '#888', callback: value => value + '%' },
                        grid: { borderDash: [5, 5], color: '#eef' }
                    },
                    x: {
                        ticks: { color: '#888' },
                        grid: { display: false }
                    }
                }
            }
        });
    }

    function renderPurposeDistributionChart(purposeData) {
        const ctx = document.getElementById('purposeDistributionChart').getContext('2d');
        
        if (purposeData.length === 0) {
            purposeData = [{ purpose: 'No sit-ins yet', count: 1 }];
        }
        
        const labels = purposeData.map(item => item.purpose);
        const counts = purposeData.map(item => item.count);
        
        if (purposeChart) purposeChart.destroy();
        
        purposeChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: counts,
                    backgroundColor: [
                        '#144d94',
                        '#28a745',
                        '#ffc107',
                        '#fd7e14',
                        '#6f42c1',
                        '#17a2b8'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { boxWidth: 12, padding: 15, font: { family: 'Inter' } }
                    }
                },
                cutout: '65%'
            }
        });
    }

    async function generateAIRecommendations() {
        const btn = document.querySelector('.btn-ai-generate');
        const resultsDiv = document.getElementById('ai-advisor-results');
        
        btn.disabled = true;
        resultsDiv.innerHTML = `
            <div class="ai-loading-state">
                <div class="ai-spinner"></div>
                <p>Analyzing physical logs, active sit-ins, database traffic trends, and checking software inventories...</p>
            </div>
        `;
        
        try {
            const response = await fetch('admin_dashboard.php?action=ai_recommendations');
            const data = await response.json();
            
            // Add artificial delay for organic AI feeling
            await new Promise(resolve => setTimeout(resolve, 800));
            
            if (data.success && data.recommendations.length > 0) {
                let html = '';
                data.recommendations.forEach(rec => {
                    let badgeClass = 'badge-medium';
                    if (rec.impact === 'High Impact') badgeClass = 'badge-high';
                    if (rec.impact === 'Low Impact') badgeClass = 'badge-low';
                    
                    let icon = '💡';
                    if (rec.type === 'occupancy') icon = '📈';
                    if (rec.type === 'software') icon = '🛠️';
                    if (rec.type === 'schedule') icon = '⏱️';
                    
                    html += `
                        <div class="ai-recommendation-card ai-rec-${rec.type}">
                            <div class="ai-rec-header">
                                <span class="ai-rec-icon">${icon}</span>
                                <div class="ai-rec-title-group">
                                    <h4>${rec.title}</h4>
                                    <span class="ai-impact-badge ${badgeClass}">${rec.impact}</span>
                                </div>
                            </div>
                            <p class="ai-rec-desc">${rec.description}</p>
                            <a href="${rec.type === 'software' ? 'admin_software.php' : 'admin_sitin.php'}" class="ai-rec-action-btn">${rec.action_label} &rarr;</a>
                        </div>
                    `;
                });
                resultsDiv.innerHTML = html;
            } else {
                resultsDiv.innerHTML = '<p class="ai-empty-state">Failed to compile recommendations: ' + (data.message || 'database is empty') + '</p>';
            }
        } catch (err) {
            console.error('Error generating AI plan:', err);
            resultsDiv.innerHTML = '<p class="ai-empty-state text-danger">Error connecting to recommendation service.</p>';
        } finally {
            btn.disabled = false;
        }
    }

    function endDashboardSitIn(recordId) {
        if (!confirm('End this sit-in session?')) {
            return;
        }

        fetch('admin_dashboard.php?action=end_sitin&record_id=' + recordId)
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    location.reload();
                }
            });
    }

    // Load admin announcements
    function loadAdminAnnouncements() {
        fetch('admin_dashboard.php?action=get_announcements')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    displayAdminAnnouncements(data.announcements);
                } else {
                    console.error('Failed to load announcements:', data.message);
                }
            })
            .catch(err => console.error('Error loading announcements:', err));
    }

    let activeAnnouncements = [];

    // Display admin announcements
    function displayAdminAnnouncements(announcements) {
        const list = document.getElementById('admin-announcement-list');
        if (!list) return;

        activeAnnouncements = announcements;

        if (announcements.length === 0) {
            list.innerHTML = '<p class="no-announcements">No announcements yet</p>';
            return;
        }

        let html = '';
        announcements.forEach(announcement => {
            html += `<div class="feed-item">
                <div class="feed-item-accent"></div>
                <div class="feed-item-content">
                    <div class="feed-item-header" style="display: flex; align-items: center; width: 100%;">
                        <strong class="feed-item-title">CCS Admin</strong>
                        <span class="feed-item-time" style="margin-left: 8px;">${announcement.date}</span>
                        <div class="feed-item-actions" style="margin-left: auto; display: flex; gap: 8px;">
                            <button class="action-btn edit-btn" onclick="openEditAnnouncementModal(${announcement.id})" title="Edit Announcement" style="background: none; border: none; cursor: pointer; font-size: 1rem; padding: 4px; border-radius: 4px; transition: transform 0.2s;">✏️</button>
                            <button class="action-btn delete-btn" onclick="confirmDeleteAnnouncement(${announcement.id})" title="Delete Announcement" style="background: none; border: none; cursor: pointer; font-size: 1rem; padding: 4px; border-radius: 4px; transition: transform 0.2s;">🗑️</button>
                        </div>
                    </div>
                    <p class="feed-item-body">${announcement.message}</p>
                </div>
            </div>`;
        });
        list.innerHTML = html;
    }

    // Open Edit Announcement Modal
    function openEditAnnouncementModal(id) {
        const announcement = activeAnnouncements.find(ann => ann.id == id);
        if (!announcement) return;
        
        // Remove existing edit modal if any
        const existing = document.getElementById('editAnnouncementModal');
        if (existing) existing.remove();

        const modalHtml = `
        <div id="editAnnouncementModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 10000; display: flex; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
            <div style="background: var(--bg-card, #2b2b36); border: 1px solid var(--border-color, #3f3f4e); border-radius: 12px; width: 500px; max-width: 90%; padding: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); position: relative; color: var(--text-main, #ffffff);">
                <span onclick="closeEditAnnouncementModal()" style="position: absolute; top: 16px; right: 20px; font-size: 24px; cursor: pointer; color: var(--text-muted, #8b8b9e); transition: color 0.2s;" onmouseover="this.style.color='#ff4a4a'" onmouseout="this.style.color='var(--text-muted, #8b8b9e)'">&times;</span>
                <h3 style="margin-top: 0; margin-bottom: 16px; font-size: 1.25rem; display: flex; align-items: center; gap: 8px;">✏️ Edit Announcement</h3>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 0.875rem; color: var(--text-muted, #8b8b9e);">Announcement Message</label>
                    <textarea id="edit-announcement-text" style="width: 100%; padding: 12px; border: 1px solid var(--border-color, #3f3f4e); border-radius: 8px; background: var(--bg-dark, #1c1c24); color: var(--text-main, #ffffff); font-family: inherit; font-size: 0.95rem; resize: vertical; box-sizing: border-box;" rows="5">${announcement.message}</textarea>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 12px;">
                    <button class="btn-secondary" onclick="closeEditAnnouncementModal()" style="padding: 10px 16px; border-radius: 6px; border: 1px solid var(--border-color, #3f3f4e); background: transparent; color: var(--text-main, #ffffff); cursor: pointer;">Cancel</button>
                    <button class="btn-primary" onclick="submitEditAnnouncement(${id})" style="padding: 10px 20px; border-radius: 6px; border: none; background: var(--accent-color, #3b82f6); color: white; cursor: pointer; font-weight: 600;">Save Changes</button>
                </div>
            </div>
        </div>`;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }

    // Close Edit Announcement Modal
    function closeEditAnnouncementModal() {
        const modal = document.getElementById('editAnnouncementModal');
        if (modal) modal.remove();
    }

    // Submit Edit Announcement
    function submitEditAnnouncement(id) {
        const text = document.getElementById('edit-announcement-text').value.trim();
        if (!text) {
            alert('Please enter announcement text');
            return;
        }

        fetch('admin_dashboard.php?action=edit_announcement', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                id: id,
                message: text
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeEditAnnouncementModal();
                loadAdminAnnouncements();
                alert('Announcement updated successfully!');
            } else {
                alert('Error updating announcement: ' + data.message);
            }
        })
        .catch(err => {
            console.error('Error updating announcement:', err);
            alert('Failed to update announcement due to a network or server error.');
        });
    }

    // Confirm Delete Announcement
    function confirmDeleteAnnouncement(id) {
        if (!confirm('Are you sure you want to delete this announcement? This action cannot be undone.')) {
            return;
        }

        fetch('admin_dashboard.php?action=delete_announcement', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id: id })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                loadAdminAnnouncements();
                alert('Announcement deleted successfully!');
            } else {
                alert('Error deleting announcement: ' + data.message);
            }
        })
        .catch(err => {
            console.error('Error deleting announcement:', err);
            alert('Failed to delete announcement due to a network or server error.');
        });
    }

    // Post new announcement
    function postAdminAnnouncement() {
        const text = document.getElementById('admin-announcement-text').value.trim();
        if (!text) {
            alert('Please enter an announcement');
            return;
        }

        // Save to database for user notifications and announcements list
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
            if (data.success) {
                // Clear textarea
                document.getElementById('admin-announcement-text').value = '';
                // Reload announcements
                loadAdminAnnouncements();
                alert('Announcement posted successfully!');
            } else {
                alert('Error posting announcement: ' + data.message);
            }
        })
        .catch(err => {
            console.error('Error saving announcement:', err);
            alert('Failed to post announcement due to a network or server error.');
        });
    }
    </script>
    
    <?php require_once 'search_modal.php'; ?>
</body>
</html>
