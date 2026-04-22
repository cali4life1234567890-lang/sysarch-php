<?php
// Admin Sit-In Records Page
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

// Get filter values
$filter = $_GET['filter'] ?? 'all';
$date = $_GET['date'] ?? '';
$studentFilter = $_GET['student'] ?? '';

// Build query
$query = "
    SELECT r.id, u.id_number, u.firstname, u.lastname, r.lab_number, r.time_in, r.time_out, r.purpose
    FROM sitin_records r
    JOIN users u ON r.user_id = u.id
    WHERE 1=1
";

$params = [];

if ($filter === 'today') {
    $query .= " AND date(r.time_in) = date('now')";
} elseif ($filter === 'week') {
    $query .= " AND r.time_in >= datetime('now', '-7 days')";
} elseif ($filter === 'month') {
    $query .= " AND r.time_in >= datetime('now', '-30 days')";
} elseif (!empty($date)) {
    $query .= " AND date(r.time_in) = ?";
    $params[] = $date;
}

if (!empty($studentFilter)) {
    $query .= " AND u.id_number = ?";
    $params[] = $studentFilter;
}

$query .= " ORDER BY r.time_in DESC LIMIT 200";

$records = [];
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
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
    <title>Sit-In Records - CCS Sit-In System</title>
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
            <a href="admin_home.php">Home</a>
            <a href="#" onclick="openSearchModal(); return false;">Search</a>
            <a href="admin_leaderboard.php">Leaderboard</a>
            <a href="admin_students.php">Students</a>
            <a href="admin_sitin.php">Sit-In</a>
            <a href="admin_records.php" class="active">View Sit-In Records</a>
            <a href="admin_reports.php">Sit-In Reports</a>
            <a href="admin_feedback.php">Feedback</a>
            <a href="admin_reservations.php">Reservations</a>
            <a href="../reg-log-prof/logout.php">Logout</a>
        </div>
    </nav>

    <div class="admin-content">
        <h1>Sit-In Records</h1>
        
        <form method="GET" class="records-filters">
            <input type="date" name="date" value="<?php echo htmlspecialchars($date); ?>">
            <select name="filter">
                <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Records</option>
                <option value="today" <?php echo $filter === 'today' ? 'selected' : ''; ?>>Today</option>
                <option value="week" <?php echo $filter === 'week' ? 'selected' : ''; ?>>This Week</option>
                <option value="month" <?php echo $filter === 'month' ? 'selected' : ''; ?>>This Month</option>
            </select>
            <button type="submit" class="btn-primary">Filter</button>
            <a href="admin_records.php" class="btn-secondary">Clear</a>
        </form>

        <table class="data-table">
            <thead>
                <tr>
                    <th>ID Number</th>
                    <th>Name</th>
                    <th>Lab</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Duration</th>
                    <th>Purpose</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $record): ?>
                <tr>
                    <td><?php echo htmlspecialchars($record['id_number']); ?></td>
                    <td><?php echo htmlspecialchars($record['firstname'] . ' ' . $record['lastname']); ?></td>
                    <td><?php echo htmlspecialchars($record['lab_number']); ?></td>
                    <td><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($record['time_in']))); ?></td>
                    <td><?php echo $record['time_out'] ? htmlspecialchars(date('h:i A', strtotime($record['time_out']))) : '<span class="ongoing">Ongoing</span>'; ?></td>
                    <td>
                        <?php
                        if ($record['time_out']) {
                            $start = strtotime($record['time_in']);
                            $end = strtotime($record['time_out']);
                            $duration = $end - $start;
                            $hours = floor($duration / 3600);
                            $minutes = floor(($duration % 3600) / 60);
                            echo $hours . 'h ' . $minutes . 'm';
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td><?php echo htmlspecialchars($record['purpose']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if (empty($records)): ?>
        <p class="no-results">No records found.</p>
        <?php endif; ?>
    </div>
    
    <?php require_once 'search_modal.php'; ?>
</body>
</html>
