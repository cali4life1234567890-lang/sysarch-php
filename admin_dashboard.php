<?php
// Admin Dashboard Handler
header('Content-Type: application/json');

require_once 'db.php';
startSession();

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['token'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Verify admin
$stmt = $pdo->prepare("SELECT id_number FROM users WHERE id = ? AND id_number = '2664388'");
$stmt->execute([$_SESSION['user_id']]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'stats':
        getStats();
        break;
    case 'search':
        searchStudents();
        break;
    case 'start_sitin':
        startSitIn();
        break;
    case 'end_sitin':
        endSitIn();
        break;
    case 'records':
        getRecords();
        break;
    case 'students':
        getStudents();
        break;
    case 'feedback':
        getFeedback();
        break;
    case 'reservations':
        getReservations();
        break;
    case 'report':
        generateReport();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getStats() {
    global $pdo;
    
    try {
        // Total students
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE id_number != '2664388'");
        $totalStudents = $stmt->fetch()['count'];
        
        // Today's sit-in
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM sitin_records WHERE date(time_in) = date('now') AND time_out IS NULL");
        $todaySitIn = $stmt->fetch()['count'];
        
        // Total records
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM sitin_records");
        $totalRecords = $stmt->fetch()['count'];
        
        // Pending reservations (placeholder)
        $pendingReservations = 0;
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'total_students' => $totalStudents,
                'today_sitin' => $todaySitIn,
                'total_records' => $totalRecords,
                'pending_reservations' => $pendingReservations
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function searchStudents() {
    global $pdo;
    $query = $_GET['q'] ?? '';
    
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
            LIMIT 20
        ");
        $searchTerm = "%$query%";
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        $results = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'results' => $results]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function startSitIn() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    
    $studentId = $input['student_id'] ?? '';
    $lab = $input['lab'] ?? '';
    $purpose = $input['purpose'] ?? '';
    
    if (empty($studentId) || empty($lab) || empty($purpose)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }
    
    try {
        // Get user ID from id_number
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id_number = ?");
        $stmt->execute([$studentId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            return;
        }
        
        // Insert sit-in record
        $insertStmt = $pdo->prepare("INSERT INTO sitin_records (user_id, lab_number, purpose) VALUES (?, ?, ?)");
        $insertStmt->execute([$user['id'], $lab, $purpose]);
        
        echo json_encode(['success' => true, 'message' => 'Sit-In started successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function endSitIn() {
    global $pdo;
    $recordId = $_GET['record_id'] ?? '';
    
    try {
        $stmt = $pdo->prepare("UPDATE sitin_records SET time_out = datetime('now') WHERE id = ? AND time_out IS NULL");
        $stmt->execute([$recordId]);
        
        echo json_encode(['success' => true, 'message' => 'Sit-In ended successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getRecords() {
    global $pdo;
    $date = $_GET['date'] ?? '';
    $filter = $_GET['filter'] ?? 'all';
    
    try {
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
        
        $query .= " ORDER BY r.time_in DESC LIMIT 100";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $records = $stmt->fetchAll();
        
        // Format for display
        $formatted = array_map(function($r) {
            return [
                'id_number' => $r['id_number'],
                'name' => $r['firstname'] . ' ' . $r['lastname'],
                'lab' => $r['lab_number'],
                'time_in' => $r['time_in'],
                'time_out' => $r['time_out'],
                'purpose' => $r['purpose']
            ];
        }, $records);
        
        echo json_encode(['success' => true, 'records' => $formatted]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getStudents() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT id, id_number, firstname, lastname, course, level, email
            FROM users 
            WHERE id_number != '2664388'
            ORDER BY lastname, firstname
        ");
        $students = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'students' => $students]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getFeedback() {
    // Placeholder - would need a feedback table
    echo json_encode(['success' => true, 'feedback' => []]);
}

function getReservations() {
    // Placeholder - would need a reservations table
    echo json_encode(['success' => true, 'reservations' => []]);
}

function generateReport() {
    $type = $_GET['type'] ?? '';
    
    // For now, redirect to records with parameters
    header('Location: index.php?section=admin-records');
    exit;
}
