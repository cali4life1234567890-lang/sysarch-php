<?php
// Admin Students Page
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
$autoOpenModal = isset($_GET['open_search']) && $_GET['open_search'] === 'modal';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students - CCS Sit-In System</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin.css">
    <style>
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .modal-close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 28px;
            cursor: pointer;
            color: #666;
            line-height: 1;
        }

        .modal-close:hover {
            color: #333;
        }

        .search-modal-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .search-modal-header h2 {
            margin: 0;
            color: #333;
        }

        .search-input-group {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .search-input-group input {
            flex: 1;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }

        .search-input-group input:focus {
            outline: none;
            border-color: #007bff;
        }

        .search-input-group button {
            padding: 12px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }

        .search-input-group button:hover {
            background: #0056b3;
        }

        .student-info-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 15px;
        }

        .student-info-card h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: bold;
            color: #555;
        }

        .info-value {
            color: #333;
        }

        .sessions-badge {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
        }

        .sessions-badge.low {
            background: #dc3545;
        }

        .sessions-badge.medium {
            background: #ffc107;
            color: #333;
        }

        .no-result-message {
            text-align: center;
            padding: 30px;
            color: #666;
        }

        .no-result-message .icon {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .modal-actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .modal-actions a {
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            text-align: center;
        }

        .btn-sitin {
            background: #007bff;
            color: white;
        }

        .btn-records {
            background: #6c757d;
            color: white;
        }

        .loading-spinner {
            text-align: center;
            padding: 30px;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Toast Notification */
        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #dc3545;
            color: white;
            padding: 15px 25px;
            border-radius: 5px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
            z-index: 2000;
            display: none;
            animation: slideIn 0.3s ease;
        }

        .toast-notification.show {
            display: block;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
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
            <a href="admin_students.php?open_search=modal">Search</a>
            <a href="admin_students.php" class="active">Students</a>
            <a href="admin_sitin.php">Sit-In</a>
            <a href="admin_records.php">View Sit-In Records</a>
            <a href="admin_reports.php">Sit-In Reports</a>
            <a href="admin_feedback.php">Feedback</a>
            <a href="admin_reservations.php">Reservations</a>
            <a href="../reg-log-prof/logout.php">Logout</a>
        </div>
    </nav>

    <div class="admin-content">
        <h1>Registered Students</h1>
        
        <div class="toolbar">
            <a href="admin_students.php?open_search=modal" class="btn-primary">Search Student</a>
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

    <!-- Search Student Modal -->
    <div class="modal-overlay" id="searchModal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeSearchModal()">&times;</span>
            <div class="search-modal-header">
                <h2>Search Student</h2>
            </div>
            
            <div class="search-input-group">
                <input type="text" id="studentSearchInput" placeholder="Enter ID Number or Name" autocomplete="off">
                <button onclick="searchStudent()">Search</button>
            </div>

            <div id="searchResults">
                <!-- Results will be displayed here -->
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast-notification" id="toastNotification">
        Student not found in the system
    </div>

    <script>
        // Open search modal
        function openSearchModal() {
            document.getElementById('searchModal').classList.add('active');
            document.getElementById('studentSearchInput').focus();
            document.getElementById('searchResults').innerHTML = '';
        }

        // Close search modal
        function closeSearchModal() {
            document.getElementById('searchModal').classList.remove('active');
            document.getElementById('studentSearchInput').value = '';
            document.getElementById('searchResults').innerHTML = '';
        }

        // Search student via AJAX
        async function searchStudent() {
            var query = document.getElementById('studentSearchInput').value.trim();
            var resultsContainer = document.getElementById('searchResults');
            
            if (!query) {
                return;
            }

            // Show loading spinner
            resultsContainer.innerHTML = '<div class="loading-spinner"><div class="spinner"></div><p>Searching...</p></div>';

            try {
                var response = await fetch('api_search_student.php?q=' + encodeURIComponent(query));
                var data = await response.json();

                if (data.error) {
                    resultsContainer.innerHTML = '<div class="no-result-message"><p>Error: ' + data.error + '</p></div>';
                    return;
                }

                if (data.found) {
                    var student = data.student;
                    var fullName = student.firstname + ' ' + (student.middlename ? student.middlename + ' ' : '') + student.lastname;
                    
                    // Determine session badge color
                    var sessionClass = '';
                    if (student.remaining_sessions <= 5) {
                        sessionClass = 'low';
                    } else if (student.remaining_sessions <= 15) {
                        sessionClass = 'medium';
                    }

                    var registeredDate = new Date(student.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

                    resultsContainer.innerHTML = '<div class="student-info-card">' +
                        '<h3>Student Information</h3>' +
                        '<div class="info-row"><span class="info-label">ID Number:</span><span class="info-value">' + student.id_number + '</span></div>' +
                        '<div class="info-row"><span class="info-label">Name:</span><span class="info-value">' + fullName + '</span></div>' +
                        '<div class="info-row"><span class="info-label">Course & Level:</span><span class="info-value">' + student.course + ' - ' + student.level + '</span></div>' +
                        '<div class="info-row"><span class="info-label">Email:</span><span class="info-value">' + (student.email || 'N/A') + '</span></div>' +
                        '<div class="info-row"><span class="info-label">Address:</span><span class="info-value">' + (student.address || 'N/A') + '</span></div>' +
                        '<div class="info-row"><span class="info-label">Sessions Left:</span><span class="info-value"><span class="sessions-badge ' + sessionClass + '">' + student.remaining_sessions + '</span></span></div>' +
                        '<div class="info-row"><span class="info-label">Registered:</span><span class="info-value">' + registeredDate + '</span></div>' +
                        '<div class="modal-actions">' +
                        '<a href="admin_sitin.php?student=' + encodeURIComponent(student.id_number) + '" class="btn-sitin">Start Sit-In</a>' +
                        '<a href="admin_records.php?student=' + encodeURIComponent(student.id_number) + '" class="btn-records">View Records</a>' +
                        '</div></div>';
                } else {
                    // Show toast notification
                    showToast();
                    resultsContainer.innerHTML = '<div class="no-result-message"><div class="icon">X</div><p>No student found matching "' + escapeHtml(query) + '"</p></div>';
                }
            } catch (error) {
                resultsContainer.innerHTML = '<div class="no-result-message"><p>Error searching for student</p></div>';
            }
        }

        // Show toast notification
        function showToast() {
            var toast = document.getElementById('toastNotification');
            toast.classList.add('show');
            setTimeout(function() {
                toast.classList.remove('show');
            }, 3000);
        }

        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Allow pressing Enter to search
        document.getElementById('studentSearchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchStudent();
            }
        });

        // Close modal when clicking outside
        document.getElementById('searchModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeSearchModal();
            }
        });

        // Auto-open modal if requested
        <?php if ($autoOpenModal): ?>
        window.onload = function() {
            openSearchModal();
        };
        <?php endif; ?>
    </script>
</body>
</html>
