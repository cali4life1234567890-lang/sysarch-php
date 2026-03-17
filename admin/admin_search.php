<?php
// Admin Search Page
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

$searchResults = [];
$searchQuery = $_GET['q'] ?? '';

if (!empty($searchQuery)) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, id_number, firstname, lastname, middlename, course, level, email 
            FROM users 
            WHERE id_number != '2664388' AND (
                id_number LIKE ? OR 
                firstname LIKE ? OR 
                lastname LIKE ? OR 
                course LIKE ?
            )
            LIMIT 50
        ");
        $searchTerm = "%$searchQuery%";
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        $searchResults = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Ignore errors
    }
}

$adminName = $_SESSION['name'] ?? 'Admin';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search - CCS Sit-In System</title>
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
            <a href="admin_search.php" class="active">Search</a>
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
        <h1>Search Students</h1>
        
        <form method="GET" class="search-box">
            <input type="text" name="q" placeholder="Search by ID, Name, or Course..." value="<?php echo htmlspecialchars($searchQuery); ?>">
            <button type="submit" class="btn-primary">Search</button>
        </form>

        <?php if (!empty($searchResults)): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID Number</th>
                    <th>Name</th>
                    <th>Course</th>
                    <th>Level</th>
                    <th>Email</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($searchResults as $student): ?>
                <tr>
                    <td><?php echo htmlspecialchars($student['id_number']); ?></td>
                    <td><?php echo htmlspecialchars($student['firstname'] . ' ' . $student['lastname']); ?></td>
                    <td><?php echo htmlspecialchars($student['course']); ?></td>
                    <td><?php echo htmlspecialchars($student['level']); ?></td>
                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                    <td>
                        <a href="admin_sitin.php?student=<?php echo urlencode($student['id_number']); ?>" class="btn-small">Start Sit-In</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php elseif (!empty($searchQuery)): ?>
        <p class="no-results">No students found matching "<?php echo htmlspecialchars($searchQuery); ?>"</p>
        <?php endif; ?>
    </div>
</body>
</html>
