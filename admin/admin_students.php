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

// Pagination parameters
$perPage = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $perPage;

// Search parameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// CCS filter parameter
$ccsFilter = isset($_GET['ccs']) ? $_GET['ccs'] : 'all'; // 'all', 'ccs', 'non-ccs'

// Sort parameters
$sortColumn = isset($_GET['sort']) ? $_GET['sort'] : 'lastname';
$sortDirection = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'ASC' : 'DESC';

// Allowed sort columns
$allowedSortColumns = ['id_number', 'firstname', 'lastname', 'level', 'course', 'remaining_sessions'];
if (!in_array($sortColumn, $allowedSortColumns)) {
    $sortColumn = 'lastname';
}

// Define CCS courses
$ccsCourses = ['BSIT', 'BSCpE', 'BSCE'];

// Build WHERE clause for search and CCS filter
$whereClause = "u.id_number != '2664388'";
$params = [];

if ($search !== '') {
    $whereClause .= " AND (u.id_number LIKE ? OR u.firstname LIKE ? OR u.lastname LIKE ? OR u.course LIKE ? OR u.level LIKE ?)";
    $searchParam = "%$search%";
    $params = array_fill(0, 5, $searchParam);
}

if ($ccsFilter === 'ccs') {
    $whereClause .= " AND u.course IN (" . implode(',', array_fill(0, count($ccsCourses), '?')) . ")";
    $params = array_merge($params, $ccsCourses);
} elseif ($ccsFilter === 'non-ccs') {
    $whereClause .= " AND u.course NOT IN (" . implode(',', array_fill(0, count($ccsCourses), '?')) . ")";
    $params = array_merge($params, $ccsCourses);
}

// Get total count for pagination
$countSql = "SELECT COUNT(*) FROM users u LEFT JOIN user_sessions us ON u.id = us.user_id WHERE $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalStudents = $countStmt->fetchColumn();
$totalPages = ceil($totalStudents / $perPage);

// If page exceeds total pages, adjust
if ($page > $totalPages && $totalPages > 0) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Get students with LIMIT/OFFSET
$students = [];
try {
    $sql = "
        SELECT u.id, u.id_number, u.firstname, u.lastname, u.middlename, u.course, u.level, u.email, u.address, u.created_at,
               COALESCE(us.remaining_sessions, 30) as remaining_sessions,
               COALESCE(u.can_reserve, 1) as can_reserve
        FROM users u
        LEFT JOIN user_sessions us ON u.id = us.user_id
        WHERE $whereClause
        ORDER BY u.$sortColumn $sortDirection
        LIMIT $perPage OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    // Ignore errors
}

// Toggle sort direction helper
$oppositeDir = $sortDirection === 'ASC' ? 'desc' : 'asc';
$sortDirectionIcon = $sortDirection === 'ASC' ? '↑' : '↓';

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

        .btn-edit {
            background: #28a745;
            color: white;
            border: none;
            cursor: pointer;
        }

        .btn-edit:hover {
            background: #218838;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
            border: none;
            cursor: pointer;
        }

        .btn-delete:hover {
            background: #c82333;
        }

        .table-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-toolbar .toolbar-left {
            display: flex;
            gap: 10px;
        }

        .table-toolbar .toolbar-right {
            display: flex;
            align-items: center;
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

        .table-toolbar .btn-secondary {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-left: 10px;
        }

        .table-toolbar .btn-secondary:hover {
            background: #138496;
        }

        .filter-dropdown {
            padding: 10px 15px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            background: white;
            cursor: pointer;
            min-width: 150px;
        }

        .filter-dropdown:focus {
            outline: none;
            border-color: #007bff;
        }

        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #007bff;
        }

        .edit-modal-overlay {
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

        /* Pagination Styles */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding: 15px 0;
            flex-wrap: wrap;
            gap: 10px;
        }

        .pagination-info {
            color: #666;
            font-size: 14px;
        }

        .pagination-controls {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .pagination-btn {
            padding: 8px 12px;
            background: #fff;
            border: 1px solid #ddd;
            color: #007bff;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .pagination-btn:hover {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .pagination-btn.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
            font-weight: bold;
        }

        .pagination-btn:disabled {
            color: #ccc;
            pointer-events: none;
            background: #f5f5f5;
            border-color: #ddd;
        }

        .pagination-ellipsis {
            padding: 8px 5px;
            color: #666;
        }

        /* Form styling for search toolbar */
        .table-toolbar form {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .table-toolbar .toolbar-left,
        .table-toolbar .toolbar-right {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .course-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 500;
        }

        .course-badge.ccs {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .course-badge.non-ccs {
            background: #f8f9fa;
            color: #495057;
            border: 1px solid #dee2e6;
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
            <a href="admin_students.php?open_search=modal">Search</a>
            <a href="admin_leaderboard.php">Leaderboard</a>
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
        
        <form method="GET" class="table-toolbar">
            <div class="toolbar-left">
                <button type="button" class="btn-primary" onclick="openAddModal()">+ Add Student</button>
                <button type="button" class="btn-secondary" onclick="resetAllSessions()">Reset All Sessions</button>
            </div>
            <div class="toolbar-right">
                <select name="ccs" class="filter-dropdown" onchange="this.form.submit()">
                    <option value="all" <?php echo $ccsFilter === 'all' ? 'selected' : ''; ?>>All Students</option>
                    <option value="ccs" <?php echo $ccsFilter === 'ccs' ? 'selected' : ''; ?>>CCS Only</option>
                    <option value="non-ccs" <?php echo $ccsFilter === 'non-ccs' ? 'selected' : ''; ?>>Non-CCS</option>
                </select>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search..." class="search-box">
                <button type="submit" class="btn-secondary">Search</button>
                <?php if ($search !== '' || $ccsFilter !== 'all'): ?>
                <a href="admin_students.php?page=1" class="btn-secondary" style="text-decoration:none;margin-left:5px;">Clear</a>
                <?php endif; ?>
            </div>
        </form>
        
        <table class="data-table" id="studentsTable">
            <thead>
                <tr>
                    <th><a href="?page=1&search=<?php echo urlencode($search); ?>&ccs=<?php echo $ccsFilter; ?>&sort=id_number&dir=<?php echo ($sortColumn === 'id_number') ? $oppositeDir : 'asc'; ?>" style="color:white;text-decoration:none;">ID Number <?php echo ($sortColumn === 'id_number') ? $sortDirectionIcon : ''; ?></a></th>
                    <th><a href="?page=1&search=<?php echo urlencode($search); ?>&ccs=<?php echo $ccsFilter; ?>&sort=firstname&dir=<?php echo ($sortColumn === 'firstname') ? $oppositeDir : 'asc'; ?>" style="color:white;text-decoration:none;">Name <?php echo ($sortColumn === 'firstname' || $sortColumn === 'lastname') ? $sortDirectionIcon : ''; ?></a></th>
                    <th><a href="?page=1&search=<?php echo urlencode($search); ?>&ccs=<?php echo $ccsFilter; ?>&sort=level&dir=<?php echo ($sortColumn === 'level') ? $oppositeDir : 'asc'; ?>" style="color:white;text-decoration:none;">Year Level <?php echo ($sortColumn === 'level') ? $sortDirectionIcon : ''; ?></a></th>
                    <th><a href="?page=1&search=<?php echo urlencode($search); ?>&ccs=<?php echo $ccsFilter; ?>&sort=course&dir=<?php echo ($sortColumn === 'course') ? $oppositeDir : 'asc'; ?>" style="color:white;text-decoration:none;">Course <?php echo ($sortColumn === 'course') ? $sortDirectionIcon : ''; ?></a></th>
                    <th><a href="?page=1&search=<?php echo urlencode($search); ?>&ccs=<?php echo $ccsFilter; ?>&sort=remaining_sessions&dir=<?php echo ($sortColumn === 'remaining_sessions') ? $oppositeDir : 'asc'; ?>" style="color:white;text-decoration:none;">Remaining Sessions <?php echo ($sortColumn === 'remaining_sessions') ? $sortDirectionIcon : ''; ?></a></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                <tr>
                    <td><?php echo htmlspecialchars($student['id_number']); ?></td>
                    <td><?php echo htmlspecialchars($student['firstname'] . ' ' . $student['lastname']); ?></td>
                    <td><?php echo htmlspecialchars($student['level']); ?></td>
                    <td><?php
                        $course = htmlspecialchars($student['course']);
                        $isCCS = in_array($student['course'], ['BSIT', 'BSCpE', 'BSCE']);
                        if ($isCCS) {
                            echo '<span class="course-badge ccs">' . $course . '</span>';
                        } else {
                            echo '<span class="course-badge non-ccs">' . $course . '</span>';
                        }
                    ?></td>
                    <td><span class="sessions-badge <?php echo $student['remaining_sessions'] <= 5 ? 'low' : ($student['remaining_sessions'] <= 15 ? 'medium' : ''); ?>"><?php echo $student['remaining_sessions']; ?></span></td>
                    <td>
                        <button class="btn-small btn-edit" onclick="openEditModal(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['id_number']); ?>', '<?php echo htmlspecialchars($student['firstname']); ?>', '<?php echo htmlspecialchars($student['lastname']); ?>', '<?php echo htmlspecialchars($student['middlename'] ?? ''); ?>', '<?php echo htmlspecialchars($student['course']); ?>', '<?php echo htmlspecialchars($student['level']); ?>', '<?php echo htmlspecialchars($student['email'] ?? ''); ?>', '<?php echo htmlspecialchars($student['address'] ?? ''); ?>', <?php echo $student['remaining_sessions']; ?>, <?php echo $student['can_reserve']; ?>)">Edit</button>
                        <button class="btn-small btn-delete" onclick="confirmDelete(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['firstname'] . ' ' . $student['lastname']); ?>')">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
         </table>
         
         <?php if (empty($students)): ?>
         <p class="no-results">No students registered yet.</p>
         <?php endif; ?>

         <!-- Pagination -->
         <?php if ($totalPages > 1): ?>
         <div class="pagination-container">
             <div class="pagination-info">
                 Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalStudents); ?> of <?php echo $totalStudents; ?> students
             </div>
             <div class="pagination-controls">
                 <?php if ($page > 1): ?>
                     <a href="?page=1&search=<?php echo urlencode($search); ?>&ccs=<?php echo $ccsFilter; ?>&sort=<?php echo $sortColumn; ?>&dir=<?php echo $sortDirection; ?>" class="pagination-btn">First</a>
                     <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&ccs=<?php echo $ccsFilter; ?>&sort=<?php echo $sortColumn; ?>&dir=<?php echo $sortDirection; ?>" class="pagination-btn">Prev</a>
                 <?php endif; ?>

                 <?php
                 $startPage = max(1, $page - 2);
                 $endPage = min($totalPages, $page + 2);
                 
                 if ($startPage > 1) {
                     echo '<a href="?page=1&search=' . urlencode($search) . '&ccs=' . $ccsFilter . '&sort=' . $sortColumn . '&dir=' . $sortDirection . '" class="pagination-btn">1</a>';
                     if ($startPage > 2) echo '<span class="pagination-ellipsis">...</span>';
                 }
                 
                 for ($i = $startPage; $i <= $endPage; $i++):
                 ?>
                     <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&ccs=<?php echo $ccsFilter; ?>&sort=<?php echo $sortColumn; ?>&dir=<?php echo $sortDirection; ?>" class="pagination-btn <?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                 <?php endfor; ?>
                 
                 <?php if ($endPage < $totalPages): ?>
                     <?php if ($endPage < $totalPages - 1) echo '<span class="pagination-ellipsis">...</span>'; ?>
                     <a href="?page=<?php echo $totalPages; ?>&search=<?php echo urlencode($search); ?>&ccs=<?php echo $ccsFilter; ?>&sort=<?php echo $sortColumn; ?>&dir=<?php echo $sortDirection; ?>" class="pagination-btn"><?php echo $totalPages; ?></a>
                 <?php endif; ?>

                 <?php if ($page < $totalPages): ?>
                     <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&ccs=<?php echo $ccsFilter; ?>&sort=<?php echo $sortColumn; ?>&dir=<?php echo $sortDirection; ?>" class="pagination-btn">Next</a>
                     <a href="?page=<?php echo $totalPages; ?>&search=<?php echo urlencode($search); ?>&ccs=<?php echo $ccsFilter; ?>&sort=<?php echo $sortColumn; ?>&dir=<?php echo $sortDirection; ?>" class="pagination-btn">Last</a>
                 <?php endif; ?>
             </div>
         </div>
         <?php endif; ?>
     </div>

    <!-- Include shared search modal -->
    <?php include 'search_modal.php'; ?>

    <script>
        // Modal functions - no client-side sorting/filtering needed
        function openAddModal() {
            document.getElementById('addIdNumber').value = '';
            document.getElementById('addFirstname').value = '';
            document.getElementById('addLastname').value = '';
            document.getElementById('addMiddlename').value = '';
            document.getElementById('addCourse').value = '';
            document.getElementById('addLevel').value = '';
            document.getElementById('addEmail').value = '';
            document.getElementById('addAddress').value = '';
            document.getElementById('addPassword').value = '';
            document.getElementById('addModal').classList.add('active');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
        }

        async function addStudent() {
            var idno = document.getElementById('addIdNumber').value.trim();
            var firstname = document.getElementById('addFirstname').value.trim();
            var lastname = document.getElementById('addLastname').value.trim();
            var middlename = document.getElementById('addMiddlename').value.trim();
            var course = document.getElementById('addCourse').value;
            var level = document.getElementById('addLevel').value;
            var email = document.getElementById('addEmail').value.trim();
            var address = document.getElementById('addAddress').value.trim();
            var password = document.getElementById('addPassword').value;

            if (!idno || !firstname || !lastname || !course || !level || !password) {
                alert('Please fill in all required fields');
                return;
            }

            try {
                var formData = new FormData();
                formData.append('id_number', idno);
                formData.append('firstname', firstname);
                formData.append('lastname', lastname);
                formData.append('middlename', middlename);
                formData.append('course', course);
                formData.append('level', level);
                formData.append('email', email);
                formData.append('address', address);
                formData.append('password', password);

                var response = await fetch('api_add_student.php', {
                    method: 'POST',
                    body: formData
                });
                var data = await response.json();

                if (data.success) {
                    alert('Student added successfully!');
                    closeAddModal();
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                alert('Error adding student');
            }
        }

        // Reset All Sessions
        async function resetAllSessions() {
            if (!confirm('Are you sure you want to reset all students\' sessions to 30?')) {
                return;
            }

            try {
                var response = await fetch('api_reset_sessions.php', {
                    method: 'POST'
                });
                var data = await response.json();

                if (data.success) {
                    alert('All sessions have been reset to 30!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                alert('Error resetting sessions');
            }
        }

        // Edit Modal Functions
function openEditModal(id, idno, firstname, lastname, middlename, course, level, email, address, sessions, canReserve) {
             document.getElementById('editStudentId').value = id;
             document.getElementById('editIdNumber').value = idno;
             document.getElementById('editFirstname').value = firstname;
             document.getElementById('editLastname').value = lastname;
             document.getElementById('editMiddlename').value = middlename;
             document.getElementById('editCourse').value = course;
             document.getElementById('editLevel').value = level;
             document.getElementById('editEmail').value = email;
             document.getElementById('editAddress').value = address;
             document.getElementById('editSessions').value = sessions;
             document.getElementById('editCanReserve').checked = canReserve;
             document.getElementById('editModal').classList.add('active');
         }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        async function saveStudent() {
            var id = document.getElementById('editStudentId').value;
            var firstname = document.getElementById('editFirstname').value.trim();
            var lastname = document.getElementById('editLastname').value.trim();
            var middlename = document.getElementById('editMiddlename').value.trim();
            var course = document.getElementById('editCourse').value;
            var level = document.getElementById('editLevel').value;
            var email = document.getElementById('editEmail').value.trim();
            var address = document.getElementById('editAddress').value.trim();
var sessions = parseInt(document.getElementById('editSessions').value);
             var canReserve = document.getElementById('editCanReserve').checked ? 1 : 0;

             if (!firstname || !lastname || !course || !level) {
                alert('Please fill in all required fields');
                return;
            }

            try {
                var formData = new FormData();
                formData.append('id', id);
                formData.append('firstname', firstname);
                formData.append('lastname', lastname);
                formData.append('middlename', middlename);
                formData.append('course', course);
                formData.append('level', level);
                formData.append('email', email);
formData.append('address', address);
                 formData.append('sessions', sessions);
                 formData.append('can_reserve', canReserve);

                 var response = await fetch('api_update_student.php', {
                    method: 'POST',
                    body: formData
                });
                var data = await response.json();

                if (data.success) {
                    alert('Student updated successfully!');
                    closeEditModal();
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                alert('Error updating student');
            }
        }

        // Delete Modal Functions
        var deleteStudentId = null;
        function confirmDelete(id, name) {
            deleteStudentId = id;
            document.getElementById('deleteStudentName').textContent = name;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
            deleteStudentId = null;
        }

        // Sit-In Modal Functions
        function showSitinModal(idno, name, sessions) {
            // Remove existing sit-in modal if any
            var existingModal = document.getElementById('sitinModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Create modal HTML dynamically with proper modal styling
            var modalHtml = '<div id="sitinModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; justify-content: center; align-items: center;">' +
                '<div style="background: white; padding: 30px; border-radius: 10px; width: 90%; max-width: 500px; position: relative; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">' +
                '<span onclick="closeSitinModal()" style="position: absolute; top: 10px; right: 20px; font-size: 28px; cursor: pointer; color: #666;">&times;</span>' +
                '<h2 style="margin-top: 0;">Start Sit-In</h2>' +
                '<div style="margin-bottom: 15px;"><label style="display: block; margin-bottom: 5px; font-weight: bold;">ID Number</label><input type="text" id="sitinIdNumber" value="' + idno + '" readonly style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px; background: #f0f0f0; box-sizing: border-box;"></div>' +
                '<div style="margin-bottom: 15px;"><label style="display: block; margin-bottom: 5px; font-weight: bold;">Student Name</label><input type="text" id="sitinStudentName" value="' + name + '" readonly style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px; background: #f0f0f0; box-sizing: border-box;"></div>' +
                '<div style="margin-bottom: 15px;"><label style="display: block; margin-bottom: 5px; font-weight: bold;">Purpose *</label><input type="text" id="sitinPurpose" placeholder="Enter purpose" required style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px; box-sizing: border-box;"></div>' +
                '<div style="margin-bottom: 15px;"><label style="display: block; margin-bottom: 5px; font-weight: bold;">Laboratory *</label><select id="sitinLab" required style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px; box-sizing: border-box;"><option value="">Select Laboratory</option><option value="524">524</option><option value="526">526</option><option value="528">528</option><option value="530">530</option><option value="MAC">MAC</option></select></div>' +
                '<div style="margin-bottom: 15px;"><label style="display: block; margin-bottom: 5px; font-weight: bold;">Remaining Sessions</label><input type="text" id="sitinRemainingSessions" value="' + sessions + '" readonly style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px; background: #f0f0f0; box-sizing: border-box;"></div>' +
                '<button onclick="submitSitin()" style="width: 100%; padding: 12px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">Start Sit-In</button>' +
                '</div></div>';
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        }

        function closeSitinModal() {
            var modal = document.getElementById('sitinModal');
            if (modal) {
                modal.remove();
            }
        }

        async function submitSitin() {
            var idno = document.getElementById('sitinIdNumber').value;
            var purpose = document.getElementById('sitinPurpose').value.trim();
            var lab = document.getElementById('sitinLab').value;

            if (!purpose || !lab) {
                alert('Please fill in all required fields');
                return;
            }

            try {
                var formData = new FormData();
                formData.append('id_number', idno);
                formData.append('purpose', purpose);
                formData.append('lab', lab);

                var response = await fetch('api_start_sitin.php', {
                    method: 'POST',
                    body: formData
                });
                var data = await response.json();

                if (data.success) {
                    alert('Sit-In started successfully!');
                    closeSitinModal();
                    closeSearchModal();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                alert('Error starting sit-in');
            }
        }

        async function deleteStudent() {
            if (!deleteStudentId) return;

            try {
                var formData = new FormData();
                formData.append('id', deleteStudentId);

                var response = await fetch('api_delete_student.php', {
                    method: 'POST',
                    body: formData
                });
                var data = await response.json();

                if (data.success) {
                    alert('Student deleted successfully!');
                    closeDeleteModal();
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                alert('Error deleting student');
            }
        }
    </script>

    <!-- Add Student Modal -->
    <div class="modal-overlay" id="addModal">
        <div class="modal-content" style="max-width: 600px;">
            <span class="modal-close" onclick="closeAddModal()">&times;</span>
            <h2>Add New Student</h2>
            <div class="form-group">
                <label>ID Number *</label>
                <input type="text" id="addIdNumber" required>
            </div>
            <div class="form-group">
                <label>First Name *</label>
                <input type="text" id="addFirstname" required>
            </div>
            <div class="form-group">
                <label>Last Name *</label>
                <input type="text" id="addLastname" required>
            </div>
            <div class="form-group">
                <label>Middle Name</label>
                <input type="text" id="addMiddlename">
            </div>
            <div class="form-group">
                <label>Course *</label>
                <select id="addCourse" required>
                    <option value="">Select Course</option>
                    <option value="BSIT">BSIT</option>
                    <option value="BSCpE">BSCpE</option>
                    <option value="BSCE">BSCE</option>
                    <option value="BSCrim">BSCrim</option>
                    <option value="BSA">BSA</option>
                    <option value="BSEd">BSEd</option>
                    <option value="BSHRM">BSHRM</option>
                </select>
            </div>
            <div class="form-group">
                <label>Year Level *</label>
                <select id="addLevel" required>
                    <option value="">Select Level</option>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                </select>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" id="addEmail">
            </div>
            <div class="form-group">
                <label>Address</label>
                <input type="text" id="addAddress">
            </div>
            <div class="form-group">
                <label>Password *</label>
                <input type="password" id="addPassword" required>
            </div>
            <button class="btn-primary" onclick="addStudent()">Add Student</button>
        </div>
    </div>

    <!-- Edit Student Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-content" style="max-width: 600px;">
            <span class="modal-close" onclick="closeEditModal()">&times;</span>
            <h2>Edit Student</h2>
            <input type="hidden" id="editStudentId">
            <input type="hidden" id="editIdNumber">
            <div class="form-group">
                <label>First Name *</label>
                <input type="text" id="editFirstname" required>
            </div>
            <div class="form-group">
                <label>Last Name *</label>
                <input type="text" id="editLastname" required>
            </div>
            <div class="form-group">
                <label>Middle Name</label>
                <input type="text" id="editMiddlename">
            </div>
            <div class="form-group">
                <label>Course *</label>
                <select id="editCourse" required>
                    <option value="BSIT">BSIT</option>
                    <option value="BSCpE">BSCpE</option>
                    <option value="BSCE">BSCE</option>
                    <option value="BSCrim">BSCrim</option>
                    <option value="BSA">BSA</option>
                    <option value="BSEd">BSEd</option>
                    <option value="BSHRM">BSHRM</option>
                </select>
            </div>
            <div class="form-group">
                <label>Year Level *</label>
                <select id="editLevel" required>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                </select>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" id="editEmail">
            </div>
            <div class="form-group">
                <label>Address</label>
                <input type="text" id="editAddress">
            </div>
<div class="form-group">
                 <label>Remaining Sessions</label>
                 <input type="number" id="editSessions" min="0" max="999">
             </div>
             <div class="form-group">
                 <label style="display: flex; align-items: center; gap: 8px;">
                     <input type="checkbox" id="editCanReserve" style="width: auto;">
                     <span>Enable Reservation</span>
                 </label>
             </div>
             <button class="btn-primary" onclick="saveStudent()">Save Changes</button>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <span class="modal-close" onclick="closeDeleteModal()">&times;</span>
            <h2>Delete Student</h2>
            <p>Want to delete user?</p>
            <p><strong id="deleteStudentName"></strong></p>
            <p style="color: #dc3545; font-size: 14px;">This action cannot be undone.</p>
            <div class="modal-actions">
                <button class="btn-small btn-delete" id="deleteConfirmBtn" onclick="deleteStudent()">Delete</button>
                <button class="btn-small" onclick="closeDeleteModal()" style="background: #6c757d; color: white;">Cancel</button>
            </div>
        </div>
    </div>
</body>
</html>
