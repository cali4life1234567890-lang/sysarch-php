<?php
// Admin Students Page
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

// Get all students
$students = [];
try {
    $stmt = $pdo->query("
        SELECT id, id_number, firstname, lastname, course, level, email, address, created_at
        FROM users 
        WHERE id_number != '2664388'
        ORDER BY lastname, firstname
    ");
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    // Ignore errors
}

$adminName = $_SESSION['name'] ?? 'Admin';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students - CCS Sit-In System</title>
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
            <a href="admin_students.php" class="active">Students</a>
            <a href="admin_sitin.php">Sit-In</a>
            <a href="admin_records.php">View Sit-In Records</a>
            <a href="admin_reports.php">Sit-In Reports</a>
            <a href="admin_feedback.php">Feedback</a>
            <a href="admin_reservations.php">Reservations</a>
            <a href="../logout.php">Logout (<?php echo htmlspecialchars($adminName); ?>)</a>
        </div>
    </nav>

    <div class="admin-content">
        <h1>Registered Students</h1>
        
        <div class="toolbar">
            <a href="admin_search.php" class="btn-primary">Add New Student</a>
            <span class="student-count">Total: <?php echo count($students); ?> students</span>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>ID Number</th>
                    <th>Name</th>
                    <th>Course</th>
                    <th>Level</th>
                    <th>Email</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                <tr>
                    <td><?php echo htmlspecialchars($student['id_number']); ?></td>
                    <td><?php echo htmlspecialchars($student['firstname'] . ' ' . $student['lastname']); ?></td>
                    <td><?php echo htmlspecialchars($student['course']); ?></td>
                    <td><?php echo htmlspecialchars($student['level']); ?></td>
                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime($student['created_at']))); ?></td>
                    <td>
                        <a href="admin_sitin.php?student=<?php echo urlencode($student['id_number']); ?>" class="btn-small">Sit-In</a>
                        <a href="admin_records.php?student=<?php echo urlencode($student['id_number']); ?>" class="btn-small">Records</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if (empty($students)): ?>
        <p class="no-results">No students registered yet.</p>
        <?php endif; ?>
    </div>
</body>
</html>
