<?php
// Admin Sit-In Management Page
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

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = trim($_POST['student_id'] ?? '');
    $lab = trim($_POST['lab'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    
    if (empty($studentId) || empty($lab) || empty($purpose)) {
        $error = 'All fields are required';
    } else {
        try {
            // Get user ID from id_number
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id_number = ?");
            $stmt->execute([$studentId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $error = 'Student not found';
            } else {
                // Insert sit-in record
                $insertStmt = $pdo->prepare("INSERT INTO sitin_records (user_id, lab_number, purpose) VALUES (?, ?, ?)");
                $insertStmt->execute([$user['id'], $lab, $purpose]);
                $message = 'Sit-In started successfully!';
            }
        } catch (PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get all students for dropdown
$students = [];
try {
    $stmt = $pdo->query("SELECT id_number, firstname, lastname FROM users WHERE id_number != '2664388' ORDER BY lastname, firstname");
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    // Ignore
}

// Get current sit-ins
$currentSitIns = [];
try {
    $stmt = $pdo->query("
        SELECT r.id, r.lab_number, r.time_in, r.purpose, u.id_number, u.firstname, u.lastname
        FROM sitin_records r
        JOIN users u ON r.user_id = u.id
        WHERE r.time_out IS NULL
        ORDER BY r.time_in DESC
    ");
    $currentSitIns = $stmt->fetchAll();
} catch (PDOException $e) {
    // Ignore
}

$adminName = $_SESSION['name'] ?? 'Admin';
$selectedStudent = $_GET['student'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sit-In Management - CCS Sit-In System</title>
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
            <a href="admin_sitin.php" class="active">Sit-In</a>
            <a href="admin_records.php">View Sit-In Records</a>
            <a href="admin_reports.php">Sit-In Reports</a>
            <a href="admin_feedback.php">Feedback</a>
            <a href="admin_reservations.php">Reservations</a>
            <a href="../logout.php">Logout (<?php echo htmlspecialchars($adminName); ?>)</a>
        </div>
    </nav>

    <div class="admin-content">
        <h1>Sit-In Management</h1>
        
        <?php if ($message): ?>
        <div class="success-msg"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="sit-in-container">
            <div class="sit-in-form-card">
                <h2>Start New Sit-In</h2>
                <form method="POST">
                    <select name="student_id" class="auth-input" required>
                        <option value="">Select Student</option>
                        <?php foreach ($students as $student): ?>
                        <option value="<?php echo htmlspecialchars($student['id_number']); ?>" <?php echo $selectedStudent === $student['id_number'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($student['id_number'] . ' - ' . $student['firstname'] . ' ' . $student['lastname']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="lab" class="auth-input" required>
                        <option value="">Select Lab</option>
                        <option value="Lab 1">Lab 1</option>
                        <option value="Lab 2">Lab 2</option>
                        <option value="Lab 3">Lab 3</option>
                        <option value="Lab 4">Lab 4</option>
                        <option value="Lab 5">Lab 5</option>
                    </select>
                    
                    <input type="text" name="purpose" class="auth-input" placeholder="Purpose (e.g., Programming, Research, Internet)" required>
                    
                    <button type="submit" class="btn-primary">Start Sit-In</button>
                </form>
            </div>

            <div class="current-sitin-card">
                <h2>Current Sit-Ins (<?php echo count($currentSitIns); ?>)</h2>
                <?php if (empty($currentSitIns)): ?>
                <p class="no-results">No active sit-ins</p>
                <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Lab</th>
                            <th>Time In</th>
                            <th>Purpose</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($currentSitIns as $sitin): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sitin['id_number'] . ' - ' . $sitin['firstname'] . ' ' . $sitin['lastname']); ?></td>
                            <td><?php echo htmlspecialchars($sitin['lab_number']); ?></td>
                            <td><?php echo htmlspecialchars(date('h:i A', strtotime($sitin['time_in']))); ?></td>
                            <td><?php echo htmlspecialchars($sitin['purpose']); ?></td>
                            <td>
                                <form method="POST" action="admin_end_sitin.php" style="display:inline;">
                                    <input type="hidden" name="record_id" value="<?php echo $sitin['id']; ?>">
                                    <button type="submit" class="btn-small btn-danger">End</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
