<?php
// Admin Reports Page
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

// Get report data
$reportType = $_GET['type'] ?? 'daily';
$reportDate = $_GET['date'] ?? date('Y-m-d');
$reportWeek = $_GET['week'] ?? date('Y-\WW');
$reportMonth = $_GET['month'] ?? date('Y-m');
$reportYear = $_GET['year'] ?? date('Y');

$reportData = [];
$summary = ['total' => 0, 'labs' => []];

// Initialize all laboratory counts to 0 to ensure uniform grid representation
$labsList = ['524', '526', '528', '530', 'MAC'];
foreach ($labsList as $l) {
    $summary['labs']['Lab ' . $l] = 0;
}

if ($reportType === 'daily' && !empty($reportDate)) {
    try {
        $stmt = $pdo->prepare("
            SELECT r.lab_number, COUNT(*) as count
            FROM sitin_records r
            WHERE date(r.time_in) = ?
            GROUP BY r.lab_number
        ");
        $stmt->execute([$reportDate]);
        $reportData = $stmt->fetchAll();
        
        foreach ($reportData as $row) {
            $labName = 'Lab ' . $row['lab_number'];
            $summary['total'] += $row['count'];
            $summary['labs'][$labName] = $row['count'];
        }
    } catch (PDOException $e) {
        // Ignore
    }
} elseif ($reportType === 'weekly') {
    try {
        // Fetch logs over past 7 days
        $stmt = $pdo->query("
            SELECT r.lab_number, COUNT(*) as count
            FROM sitin_records r
            WHERE r.time_in >= datetime('now', '-7 days')
            GROUP BY r.lab_number
        ");
        $reportData = $stmt->fetchAll();
        
        foreach ($reportData as $row) {
            $labName = 'Lab ' . $row['lab_number'];
            $summary['total'] += $row['count'];
            $summary['labs'][$labName] = $row['count'];
        }
    } catch (PDOException $e) {
        // Ignore
    }
} elseif ($reportType === 'monthly' && !empty($reportMonth)) {
    try {
        $stmt = $pdo->prepare("
            SELECT r.lab_number, COUNT(*) as count
            FROM sitin_records r
            WHERE strftime('%Y-%m', r.time_in) = ?
            GROUP BY r.lab_number
        ");
        $stmt->execute([$reportMonth]);
        $reportData = $stmt->fetchAll();
        
        foreach ($reportData as $row) {
            $labName = 'Lab ' . $row['lab_number'];
            $summary['total'] += $row['count'];
            $summary['labs'][$labName] = $row['count'];
        }
    } catch (PDOException $e) {
        // Ignore
    }
} elseif ($reportType === 'yearly' && !empty($reportYear)) {
    try {
        $stmt = $pdo->prepare("
            SELECT r.lab_number, COUNT(*) as count
            FROM sitin_records r
            WHERE strftime('%Y', r.time_in) = ?
            GROUP BY r.lab_number
        ");
        $stmt->execute([$reportYear]);
        $reportData = $stmt->fetchAll();
        
        foreach ($reportData as $row) {
            $labName = 'Lab ' . $row['lab_number'];
            $summary['total'] += $row['count'];
            $summary['labs'][$labName] = $row['count'];
        }
    } catch (PDOException $e) {
        // Ignore
    }
}

$adminName = $_SESSION['name'] ?? 'Admin';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sit-In Reports - CCS Sit-In System</title>
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
            <a href="admin_reports.php" class="active">Sit-In Reports</a>
            <a href="admin_feedback.php">Feedback</a>
            <a href="admin_reservations.php">Reservations</a>
            <a href="../reg-log-prof/logout.php">Logout</a>
        </div>
    </nav>

    <div class="admin-content premium-admin-dashboard">
        <div class="dashboard-header-flex">
            <div>
                <span class="welcome-badge">LOG REPORTS & AUDITS</span>
                <h1>Sit-In Reports</h1>
            </div>
            <div class="admin-profile-badge">
                <span class="avatar">📊</span>
                <div>
                    <strong><?php echo htmlspecialchars($adminName); ?></strong>
                    <span>System Administrator</span>
                </div>
            </div>
        </div>

        <div class="reports-tabs">
            <a href="?type=daily" class="<?php echo $reportType === 'daily' ? 'active' : ''; ?>">Daily Report</a>
            <a href="?type=weekly" class="<?php echo $reportType === 'weekly' ? 'active' : ''; ?>">Weekly Report</a>
            <a href="?type=monthly" class="<?php echo $reportType === 'monthly' ? 'active' : ''; ?>">Monthly Report</a>
            <a href="?type=yearly" class="<?php echo $reportType === 'yearly' ? 'active' : ''; ?>">Yearly Report</a>
        </div>

        <div class="chart-container-card report-criteria-card">
            <form method="GET" class="report-form-premium">
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($reportType); ?>">
                
                <div class="report-inputs-row">
                    <?php if ($reportType === 'daily'): ?>
                    <div class="form-item">
                        <label>Select Audit Date:</label>
                        <input type="date" name="date" value="<?php echo htmlspecialchars($reportDate); ?>" class="search-box">
                    </div>
                    <?php elseif ($reportType === 'weekly'): ?>
                    <div class="form-item">
                        <label>Select Audit Week:</label>
                        <input type="week" name="week" value="<?php echo htmlspecialchars($reportWeek); ?>" class="search-box">
                    </div>
                    <?php elseif ($reportType === 'monthly'): ?>
                    <div class="form-item">
                        <label>Select Audit Month:</label>
                        <input type="month" name="month" value="<?php echo htmlspecialchars($reportMonth); ?>" class="search-box">
                    </div>
                    <?php elseif ($reportType === 'yearly'): ?>
                    <div class="form-item">
                        <label>Select Audit Year:</label>
                        <select name="year" class="search-box select-year-input">
                            <?php 
                            $curr = date('Y');
                            for ($y = $curr; $y >= $curr - 5; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo $reportYear == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="report-buttons-row">
                    <button type="submit" class="btn-submit">⚡ Generate Report</button>
                    <button type="button" class="btn-secondary" onclick="window.print()">🖨️ Print Form</button>
                    <button type="button" class="btn-ai-generate" style="background: var(--accent-gradient); width: auto;" onclick="downloadCSVReport()">📥 Download CSV Report</button>
                </div>
            </form>
        </div>

        <div class="chart-container-card report-summary-card">
            <div class="report-summary-header">
                <h2>Laboratory Logs Summary</h2>
                <span class="report-range-badge">
                    <?php 
                    if ($reportType === 'daily') echo 'Date: ' . date('M d, Y', strtotime($reportDate));
                    elseif ($reportType === 'weekly') echo 'Current Weekly Cycle';
                    elseif ($reportType === 'monthly') echo 'Period: ' . date('F Y', strtotime($reportMonth . '-01'));
                    elseif ($reportType === 'yearly') echo 'Annual Cycle: ' . $reportYear;
                    ?>
                </span>
            </div>
            
            <div class="summary-stats-grid">
                <div class="stat-box-large">
                    <span class="stat-box-label">Aggregated Check-Ins</span>
                    <h3 class="stat-box-value"><?php echo $summary['total']; ?></h3>
                    <span class="stat-box-sub">Logs logged under selected timeframe</span>
                </div>
            </div>

            <h3 style="margin-top: 30px; font-family: 'Outfit'; font-size: 20px;">Check-ins Distribution by Room</h3>
            <div class="table-wrapper-outer">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Laboratory Room</th>
                            <th>Total Logged Check-Ins</th>
                            <th style="text-align: right;">Percentage Distribution</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($summary['labs'] as $lab => $count): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($lab); ?></strong></td>
                            <td><code><?php echo $count; ?> check-ins</code></td>
                            <td style="text-align: right; font-weight: bold; color: var(--primary-color);">
                                <?php echo $summary['total'] > 0 ? round(($count / $summary['total']) * 100, 1) : 0; ?>%
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        function downloadCSVReport() {
            const type = '<?php echo $reportType; ?>';
            let params = `type=${type}`;
            
            if (type === 'daily') {
                params += `&date=<?php echo $reportDate; ?>`;
            } else if (type === 'weekly') {
                params += `&week=<?php echo $reportWeek; ?>`;
            } else if (type === 'monthly') {
                params += `&month=<?php echo $reportMonth; ?>`;
            } else if (type === 'yearly') {
                params += `&year=<?php echo $reportYear; ?>`;
            }
            
            window.location.href = `admin_dashboard.php?action=download_report&${params}`;
        }
    </script>

    <?php require_once 'search_modal.php'; ?>
</body>
</html>
