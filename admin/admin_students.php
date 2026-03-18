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
        SELECT u.id, u.id_number, u.firstname, u.lastname, u.middlename, u.course, u.level, u.email, u.address, u.created_at,
               COALESCE(us.remaining_sessions, 30) as remaining_sessions
        FROM users u 
        LEFT JOIN user_sessions us ON u.id = us.user_id
        WHERE u.id_number != '2664388'
        ORDER BY u.lastname, u.firstname
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
        
        <div class="table-toolbar">
            <div class="toolbar-left">
                <button class="btn-primary" onclick="openAddModal()">+ Add Student</button>
                <button class="btn-secondary" onclick="resetAllSessions()">Reset All Sessions</button>
            </div>
            <div class="toolbar-right">
                <input type="text" id="tableSearch" placeholder="Search..." onkeyup="filterTable()" class="search-box">
            </div>
        </div>
        
        <table class="data-table" id="studentsTable">
            <thead>
                <tr>
                    <th onclick="sortTable(0)" style="cursor: pointer;">ID Number &#x2195;</th>
                    <th onclick="sortTable(1)" style="cursor: pointer;">Name &#x2195;</th>
                    <th onclick="sortTable(2)" style="cursor: pointer;">Year Level &#x2195;</th>
                    <th onclick="sortTable(3)" style="cursor: pointer;">Course &#x2195;</th>
                    <th onclick="sortTable(4)" style="cursor: pointer;">Remaining Sessions &#x2195;</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                <tr>
                    <td><?php echo htmlspecialchars($student['id_number']); ?></td>
                    <td><?php echo htmlspecialchars($student['firstname'] . ' ' . $student['lastname']); ?></td>
                    <td><?php echo htmlspecialchars($student['level']); ?></td>
                    <td><?php echo htmlspecialchars($student['course']); ?></td>
                    <td><span class="sessions-badge <?php echo $student['remaining_sessions'] <= 5 ? 'low' : ($student['remaining_sessions'] <= 15 ? 'medium' : ''); ?>"><?php echo $student['remaining_sessions']; ?></span></td>
                    <td>
                        <button class="btn-small btn-edit" onclick="openEditModal(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['id_number']); ?>', '<?php echo htmlspecialchars($student['firstname']); ?>', '<?php echo htmlspecialchars($student['lastname']); ?>', '<?php echo htmlspecialchars($student['middlename'] ?? ''); ?>', '<?php echo htmlspecialchars($student['course']); ?>', '<?php echo htmlspecialchars($student['level']); ?>', '<?php echo htmlspecialchars($student['email'] ?? ''); ?>', '<?php echo htmlspecialchars($student['address'] ?? ''); ?>', <?php echo $student['remaining_sessions']; ?>)">Edit</button>
                        <button class="btn-small btn-delete" onclick="confirmDelete(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['firstname'] . ' ' . $student['lastname']); ?>')">Delete</button>
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

                    resultsContainer.innerHTML = '<div class="student-info-card'>' +
                        '<h3>Student Information</h3>' +
                        '<div class="info-row"><span class="info-label">ID Number:</span><span class="info-value">' + student.id_number + '</span></div>' +
                        '<div class="info-row"><span class="info-label">Name:</span><span class="info-value">' + fullName + '</span></div>' +
                        '<div class="info-row"><span class="info-label">Course & Level:</span><span class="info-value">' + student.course + ' - ' + student.level + '</span></div>' +
                        '<div class="info-row"><span class="info-label">Email:</span><span class="info-value">' + (student.email || 'N/A') + '</span></div>' +
                        '<div class="info-row"><span class="info-label">Address:</span><span class="info-value">' + (student.address || 'N/A') + '</span></div>' +
                        '<div class="info-row"><span class="info-label">Sessions Left:</span><span class="info-value"><span class="sessions-badge ' + sessionClass + '">' + student.remaining_sessions + '</span></span></div>' +
                        '<div class="info-row"><span class="info-label">Registered:</span><span class="info-value">' + registeredDate + '</span></div>' +
                        '<div class="modal-actions">' +
                        '<button class="btn-sitin" onclick="showSitinModal(\'' + student.id_number + '\', \'' + escapeHtml(fullName) + '\', ' + student.remaining_sessions + ')">Start Sit-In</button>' +
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

        // Table Sorting
        var sortDirections = [true, true, true, true, true]; // true = ascending, false = descending
        
        // Table Filter
        function filterTable() {
            var input = document.getElementById('tableSearch');
            var filter = input.value.toLowerCase();
            var table = document.getElementById('studentsTable');
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
        
        function sortTable(columnIndex) {
            var table = document.getElementById('studentsTable');
            var tbody = table.querySelector('tbody');
            var rows = Array.from(tbody.querySelectorAll('tr'));
            
            sortDirections[columnIndex] = !sortDirections[columnIndex];
            var direction = sortDirections[columnIndex] ? 1 : -1;
            
            rows.sort(function(a, b) {
                var aText = a.cells[columnIndex].textContent.trim();
                var bText = b.cells[columnIndex].textContent.trim();
                
                // For numeric columns (ID Number and Remaining Sessions)
                if (columnIndex === 0 || columnIndex === 4) {
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

        // Add Student Modal Functions
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
        function openEditModal(id, idno, firstname, lastname, middlename, course, level, email, address, sessions) {
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
                '<div style="margin-bottom: 15px;"><label style="display: block; margin-bottom: 5px; font-weight: bold;">Laboratory *</label><input type="text" id="sitinLab" placeholder="Enter laboratory" required style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px; box-sizing: border-box;"></div>' +
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
            <button class="btn-primary" onclick="saveStudent()">Save Changes</button>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <span class="modal-close" onclick="closeDeleteModal()">&times;</span>
            <h2>Delete Student</h2>
            <p>Are you sure you want to delete this student?</p>
            <p><strong id="deleteStudentName"></strong></p>
            <p style="color: #dc3545; font-size: 14px;">This action cannot be undone.</p>
            <div class="modal-actions">
                <button class="btn-small btn-delete" onclick="deleteStudent()">Delete</button>
                <button class="btn-small" onclick="closeDeleteModal()" style="background: #6c757d; color: white;">Cancel</button>
            </div>
        </div>
    </div>
</body>
</html>
