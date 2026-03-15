<?php
// Admin Reports Page
require_once '../db.php';
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
$reportWeek = $_GET['week'] ?? date('Y-W');
$reportMonth = $_GET['month'] ?? date('Y-m');

$reportData = [];
$summary = ['total' => 0, 'labs' => []];

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
            $summary['total'] += $row['count'];
            $summary['labs'][$row['lab_number']] = $row['count'];
        }
    } catch (PDOException $e) {
        // Ignore
    }
} elseif ($reportType === 'weekly') {
    try {
        $stmt = $pdo->query("
            SELECT r.lab_number, COUNT(*) as count
            FROM sitin_records r
            WHERE r.time_in >= datetime('now', '-7 days')
            GROUP BY r.lab_number
        ");
        $reportData = $stmt->fetchAll();
        
        foreach ($reportData as $row) {
            $summary['total'] += $row['count'];
            $summary['labs'][$row['lab_number']] = $row['count'];
        }
    } catch (PDOException $e) {
        // Ignore
    }
} elseif ($reportType === 'monthly') {
    try {
        $stmt = $pdo->query("
            SELECT r.lab_number, COUNT(*) as count
            FROM sitin_records r
            WHERE r.time_in >= datetime('now', '-30 days')
            GROUP BY r.lab_number
        ");
        $reportData = $stmt->fetchAll();
        
        foreach ($reportData as $row) {
            $summary['total'] += $row['count'];
            $summary['labs'][$row['lab_number']] = $row['count'];
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
            <a href="admin_home.php">Home</a>
            <a href="admin_search.php">Search</a>
            <a href="admin_students.php">Students</a>
            <a href="admin_sitin.php">Sit-In</a>
            <a href="admin_records.php">View Sit-In Records</a>
            <a href="admin_reports.php" class="active">Sit-In Reports</a>
            <a href="admin_feedback.php">Feedback</a>
            <a href="admin_reservations.php">Reservations</a>
            <a href="../logout.php">Logout (<?php echo htmlspecialchars($adminName); ?>)</a>
        </div>
    </nav>

    <div class="admin-content">
        <h1>Sit-In Reports</h1>
        
        <div class="reports-tabs">
            <a href="?type=daily" class="<?php echo $reportType === 'daily' ? 'active' : ''; ?>">Daily Report</a>
            <a href="?type=weekly" class="<?php echo $reportType === 'weekly' ? 'active' : ''; ?>">Weekly Report</a>
            <a href="?type=monthly" class="<?php echo $reportType === 'monthly' ? 'active' : ''; ?>">Monthly Report</a>
        </div>

        <form method="GET" class="report-form">
            <input type="hidden" name="type" value="<?php echo htmlspecialchars($reportType); ?>">
            
            <?php if ($reportType === 'daily'): ?>
            <label>Select Date:</label>
            <input type="date" name="date" value="<?php echo htmlspecialchars($reportDate); ?>">
            <?php elseif ($reportType === 'weekly'): ?>
            <label>Select Week:</label>
            <input type="week" name="week" value="<?php echo htmlspecialchars($reportWeek); ?>">
            <?php else: ?>
            <label>Select Month:</label>
            <input type="month" name="month" value="<?php echo htmlspecialchars($reportMonth); ?>">
            <?php endif; ?>
            
            <button type="submit" class="btn-primary">Generate Report</button>
            <button type="button" class="btn-secondary" onclick="window.print()">Print</button>
        </form>

        <div class="report-summary">
            <h2>Summary</h2>
            <div class="summary-stats">
                <div class="stat-box">
                    <h3>Total Sit-Ins</h3>
                    <p class="stat-number"><?php echo $summary['total']; ?></p>
                </div>
            </div>

            <h3>By Laboratory</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Laboratory</th>
                        <th>Total Sit-Ins</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($summary['labs'] as $lab => $count): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($lab); ?></td>
                        <td><?php echo $count; ?></td>
                        <td><?php echo $summary['total'] > 0 ? round(($count / $summary['total']) * 100, 1) : 0; ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
