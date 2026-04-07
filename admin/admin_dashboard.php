<?php
// Admin Dashboard Handler
header('Content-Type: application/json');

require_once '../database/db.php';
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
    case 'approve_reservation':
        approveReservation();
        break;
    case 'deny_reservation':
        denyReservation();
        break;
    case 'report':
        generateReport();
        break;
    case 'post_announcement':
        postAnnouncement();
        break;
    case 'delete_all_feedback':
        deleteAllFeedback();
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
    $pc_number = $input['pc_number'] ?? null;
    
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
        
        // Ensure pc_number column exists in sitin_records
        try {
            $pdo->exec("ALTER TABLE sitin_records ADD COLUMN pc_number INTEGER");
        } catch (PDOException $e) {
            // Column might already exist, ignore
        }
        
        // Insert sit-in record
        $insertStmt = $pdo->prepare("INSERT INTO sitin_records (user_id, lab_number, pc_number, purpose) VALUES (?, ?, ?, ?)");
        $insertStmt->execute([$user['id'], $lab, $pc_number, $purpose]);
        
        // Update PC status to occupied if PC number is provided
        if ($pc_number) {
            $stmt = $pdo->prepare("UPDATE lab_pc_status SET status = 'occupied' WHERE lab_number = ? AND pc_number = ?");
            $stmt->execute([$lab, $pc_number]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Sit-In started successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function endSitIn() {
    global $pdo;
    $recordId = $_GET['record_id'] ?? '';
    
    if (empty($recordId)) {
        echo json_encode(['success' => false, 'message' => 'Record ID is required']);
        return;
    }
    
    try {
        // Get the sit-in record to get lab and pc info
        $stmt = $pdo->prepare("SELECT lab_number, pc_number FROM sitin_records WHERE id = ?");
        $stmt->execute([$recordId]);
        $record = $stmt->fetch();
        
        if (!$record) {
            echo json_encode(['success' => false, 'message' => 'Sit-in record not found']);
            return;
        }
        
        // Update the sit-in record to set time_out
        $stmt = $pdo->prepare("UPDATE sitin_records SET time_out = datetime('now') WHERE id = ? AND time_out IS NULL");
        $stmt->execute([$recordId]);
        
        // If PC was assigned, release it
        if ($record && $record['pc_number']) {
            $stmt = $pdo->prepare("UPDATE lab_pc_status SET status = 'available' WHERE lab_number = ? AND pc_number = ?");
            $stmt->execute([$record['lab_number'], $record['pc_number']]);
        }
        
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
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT f.id, f.feedback_text, f.rating, f.created_at, 
                   u.id_number, u.firstname, u.lastname
            FROM feedback f
            JOIN users u ON f.user_id = u.id
            ORDER BY f.created_at DESC
            LIMIT 50
        ");
        $stmt->execute();
        $feedback = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'feedback' => $feedback]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteAllFeedback() {
    global $pdo;
    
    try {
        $pdo->exec("DELETE FROM feedback");
        
        echo json_encode(['success' => true, 'message' => 'All feedback deleted successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getReservations() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT r.id, r.lab_number, r.pc_number, r.reservation_date, r.start_time, r.end_time, r.purpose, r.status, r.created_at,
                   u.id_number, u.firstname, u.lastname
            FROM reservations r
            JOIN users u ON r.user_id = u.id
            ORDER BY r.created_at DESC
            LIMIT 50
        ");
        $reservations = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'reservations' => $reservations]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function approveReservation() {
    global $pdo;
    $reservationId = $_GET['id'] ?? '';
    
    if (empty($reservationId)) {
        echo json_encode(['success' => false, 'message' => 'Reservation ID is required']);
        return;
    }
    
    try {
        // Get reservation details
        $stmt = $pdo->prepare("SELECT user_id, lab_number, pc_number, purpose FROM reservations WHERE id = ?");
        $stmt->execute([$reservationId]);
        $reservation = $stmt->fetch();
        
        if (!$reservation) {
            echo json_encode(['success' => false, 'message' => 'Reservation not found']);
            return;
        }
        
        // Ensure pc_number column exists in sitin_records
        try {
            $pdo->exec("ALTER TABLE sitin_records ADD COLUMN pc_number INTEGER");
        } catch (PDOException $e) {
            // Column might already exist, ignore
        }
        
        // Get user remaining sessions
        $stmt = $pdo->prepare("SELECT remaining_sessions FROM user_sessions WHERE user_id = ?");
        $stmt->execute([$reservation['user_id']]);
        $session = $stmt->fetch();
        
        $remainingSessions = $session ? $session['remaining_sessions'] : 30;
        
        if ($remainingSessions <= 0) {
            echo json_encode(['success' => false, 'message' => 'User has no remaining sessions']);
            return;
        }
        
        // Start sit-in for the user
        $stmt = $pdo->prepare("INSERT INTO sitin_records (user_id, lab_number, pc_number, purpose, time_in) VALUES (?, ?, ?, ?, datetime('now'))");
        $stmt->execute([$reservation['user_id'], $reservation['lab_number'], $reservation['pc_number'], $reservation['purpose']]);
        
        // Decrement remaining sessions
        if ($session) {
            $stmt = $pdo->prepare("UPDATE user_sessions SET remaining_sessions = remaining_sessions - 1 WHERE user_id = ?");
            $stmt->execute([$reservation['user_id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, remaining_sessions) VALUES (?, 29)");
            $stmt->execute([$reservation['user_id']]);
        }
        
        // Update PC status to occupied
        $stmt = $pdo->prepare("UPDATE lab_pc_status SET status = 'occupied' WHERE lab_number = ? AND pc_number = ?");
        $stmt->execute([$reservation['lab_number'], $reservation['pc_number']]);
        
        // Update reservation status to approved
        $stmt = $pdo->prepare("UPDATE reservations SET status = 'approved' WHERE id = ?");
        $stmt->execute([$reservationId]);
        
        // Create notification for user
        createUserNotification($reservation['user_id'], 'Reservation Approved', 'Your reservation for Lab ' . $reservation['lab_number'] . ' PC ' . $reservation['pc_number'] . ' has been approved.', 'success');
        
        echo json_encode(['success' => true, 'message' => 'Reservation approved and sit-in started']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function denyReservation() {
    global $pdo;
    $reservationId = $_GET['id'] ?? '';
    
    if (empty($reservationId)) {
        echo json_encode(['success' => false, 'message' => 'Reservation ID is required']);
        return;
    }
    
    try {
        // Get reservation details first
        $stmt = $pdo->prepare("SELECT user_id, lab_number, pc_number FROM reservations WHERE id = ?");
        $stmt->execute([$reservationId]);
        $reservation = $stmt->fetch();
        
        // Update reservation status to denied
        $stmt = $pdo->prepare("UPDATE reservations SET status = 'denied' WHERE id = ?");
        $stmt->execute([$reservationId]);
        
        // Release PC if it was reserved
        if ($reservation && $reservation['pc_number']) {
            $stmt = $pdo->prepare("UPDATE lab_pc_status SET status = 'available' WHERE lab_number = ? AND pc_number = ?");
            $stmt->execute([$reservation['lab_number'], $reservation['pc_number']]);
        }
        
        // Create notification for user
        if ($reservation) {
            createUserNotification($reservation['user_id'], 'Reservation Denied', 'Your reservation for Lab ' . $reservation['lab_number'] . ' PC ' . $reservation['pc_number'] . ' has been denied.', 'error');
        }
        
        echo json_encode(['success' => true, 'message' => 'Reservation denied']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function createUserNotification($userId, $title, $message, $type = 'info') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $title, $message, $type]);
    } catch (PDOException $e) {
        // Ignore notification errors
    }
}

function generateReport() {
    $type = $_GET['type'] ?? '';
    
    // For now, redirect to records with parameters
    header('Location: index.php?section=admin-records');
    exit;
}

function postAnnouncement() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    
    $title = trim($input['title'] ?? 'Announcement');
    $message = trim($input['message'] ?? '');
    
    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Message is required']);
        return;
    }
    
    try {
        // Get all user IDs
        $stmt = $pdo->query("SELECT id FROM users WHERE id_number != '2664388'");
        $users = $stmt->fetchAll();
        
        // Create notification for each user
        foreach ($users as $user) {
            $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'announcement')");
            $notifStmt->execute([$user['id'], $title, $message]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Announcement posted to ' . count($users) . ' users']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
