<?php
// Admin Sit-In Management Page
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
                // Ensure pc_number column exists in sitin_records
                try {
                    $pdo->exec("ALTER TABLE sitin_records ADD COLUMN pc_number INTEGER");
                } catch (PDOException $e) {
                    // Column might already exist, ignore
                }
                
                // Insert sit-in record
                $insertStmt = $pdo->prepare("INSERT INTO sitin_records (user_id, lab_number, pc_number, purpose) VALUES (?, ?, ?, ?)");
                $insertStmt->execute([$user['id'], $lab, null, $purpose]);
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

// Get current sit-ins with session info
$currentSitIns = [];
try {
    $stmt = $pdo->query("
        SELECT r.id, r.lab_number, r.time_in, r.purpose, r.time_out, u.id_number, u.firstname, u.lastname,
               COALESCE(us.remaining_sessions, 30) as remaining_sessions
        FROM sitin_records r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN user_sessions us ON u.id = us.user_id
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
    <style>
        .table-toolbar {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .table-toolbar .search-box {
            padding: 8px 15px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            width: 200px;
        }
        
        .table-toolbar .search-box:focus {
            outline: none;
            border-color: #007bff;
        }
        
        th {
            cursor: pointer;
        }
        
        th:hover {
            background-color: #f0f0f0;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-active {
            background: #28a745;
            color: white;
        }
        
        .sit-in-container {
            width: 100% !important;
            max-width: 100% !important;
            display: block !important;
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
            <a href="admin_sitin.php" class="active">Sit-In</a>
            <a href="admin_records.php">View Sit-In Records</a>
            <a href="admin_reports.php">Sit-In Reports</a>
            <a href="admin_feedback.php">Feedback</a>
            <a href="admin_reservations.php">Reservations</a>
            <a href="../reg-log-prof/logout.php">Logout</a>
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
            <div class="current-sitin-card">
                <h2>Current Sit-Ins (<?php echo count($currentSitIns); ?>)</h2>
                
                <div class="table-toolbar">
                    <span></span>
                    <input type="text" id="sitinSearch" placeholder="Search..." onkeyup="filterSitinTable()" class="search-box">
                </div>
                
                <?php if (empty($currentSitIns)): ?>
                <p class="no-results">No active sit-ins</p>
                <?php else: ?>
                <table class="data-table" id="sitinTable">
                    <thead>
                        <tr>
                            <th onclick="sortSitinTable(0)">Sit ID &#x2195;</th>
                            <th onclick="sortSitinTable(1)">ID Number &#x2195;</th>
                            <th onclick="sortSitinTable(2)">Name &#x2195;</th>
                            <th onclick="sortSitinTable(3)">Purpose &#x2195;</th>
                            <th onclick="sortSitinTable(4)">Laboratory &#x2195;</th>
                            <th onclick="sortSitinTable(5)">Session &#x2195;</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($currentSitIns as $sitin): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sitin['id']); ?></td>
                            <td><?php echo htmlspecialchars($sitin['id_number']); ?></td>
                            <td><?php echo htmlspecialchars($sitin['firstname'] . ' ' . $sitin['lastname']); ?></td>
                            <td><?php echo htmlspecialchars($sitin['purpose']); ?></td>
                            <td><?php echo htmlspecialchars($sitin['lab_number']); ?></td>
                            <td><?php echo htmlspecialchars($sitin['remaining_sessions']); ?></td>
                            <td><span class="status-badge status-active">Active</span></td>
                            <td>
                                <button class="btn-small btn-danger" onclick="endSitIn(<?php echo $sitin['id']; ?>)">End</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Filter table
        function filterSitinTable() {
            var input = document.getElementById('sitinSearch');
            var filter = input.value.toLowerCase();
            var table = document.getElementById('sitinTable');
            var tbody = table.querySelector('tbody');
            var rows = tbody.querySelectorAll('tr');
            
            rows.forEach(function(row) {
                var text = row.textContent.toLowerCase();
                if (text.indexOf(filter) > -1) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        // Sort table
        var sortDirections = [true, true, true, true, true, true];
        
        function sortSitinTable(columnIndex) {
            var table = document.getElementById('sitinTable');
            var tbody = table.querySelector('tbody');
            var rows = Array.from(tbody.querySelectorAll('tr'));
            
            sortDirections[columnIndex] = !sortDirections[columnIndex];
            var direction = sortDirections[columnIndex] ? 1 : -1;
            
            rows.sort(function(a, b) {
                var aText = a.cells[columnIndex].textContent.trim();
                var bText = b.cells[columnIndex].textContent.trim();
                
                // For numeric columns (Sit ID, ID Number, Session)
                if (columnIndex === 0 || columnIndex === 1 || columnIndex === 5) {
                    var aNum = parseInt(aText) || 0;
                    var bNum = parseInt(bText) || 0;
                    return direction * (aNum - bNum);
                }
                
                return direction * aText.localeCompare(bText);
            });
            
            rows.forEach(function(row) {
                tbody.appendChild(row);
            });
        }

        function endSitIn(recordId) {
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
    </script>
    
    <?php require_once 'search_modal.php'; ?>
</body>
</html>
