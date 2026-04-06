<?php
// User Dashboard Handler
header('Content-Type: application/json');

require_once '../database/db.php';
startSession();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['token'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Verify user session
$stmt = $pdo->prepare("SELECT session_token FROM sessions WHERE user_id = ? AND session_token = ? AND expires_at > datetime('now')");
$stmt->execute([$_SESSION['user_id'], $_SESSION['token']]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Session expired']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'profile':
        getProfile();
        break;
    case 'update_profile':
        updateProfile();
        break;
    case 'history':
        getHistory();
        break;
    case 'reservations':
        getReservations();
        break;
    case 'make_reservation':
        makeReservation();
        break;
    case 'cancel_reservation':
        cancelReservation();
        break;
    case 'notifications':
        getNotifications();
        break;
    case 'mark_notification_read':
        markNotificationRead();
        break;
    case 'current_sitin':
        getCurrentSitIn();
        break;
    case 'start_sitin':
        startSitIn();
        break;
    case 'end_sitin':
        endSitIn();
        break;
    case 'remaining_sessions':
        getRemainingSessions();
        break;
    case 'submit_feedback':
        submitFeedback();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getProfile() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, id_number, lastname, firstname, middlename, course, level, email, address 
            FROM users WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo json_encode([
                'success' => true,
                'profile' => [
                    'id_number' => $user['id_number'],
                    'lastname' => $user['lastname'],
                    'firstname' => $user['firstname'],
                    'middlename' => $user['middlename'],
                    'course' => $user['course'],
                    'level' => $user['level'],
                    'email' => $user['email'],
                    'address' => $user['address']
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateProfile() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    
    $lastname = trim($input['lastname'] ?? '');
    $firstname = trim($input['firstname'] ?? '');
    $middlename = trim($input['middlename'] ?? '');
    $email = trim($input['email'] ?? '');
    $address = trim($input['address'] ?? '');
    
    if (empty($lastname) || empty($firstname) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Last name, first name, and email are required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET lastname = ?, firstname = ?, middlename = ?, email = ?, address = ?
            WHERE id = ?
        ");
        $stmt->execute([$lastname, $firstname, $middlename, $email, $address, $_SESSION['user_id']]);
        
        // Update session name
        $fullName = $firstname;
        if (!empty($middlename)) {
            $fullName .= ' ' . $middlename;
        }
        $fullName .= ' ' . $lastname;
        $_SESSION['name'] = $fullName;
        
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getHistory() {
    global $pdo;
    $filter = $_GET['filter'] ?? 'all';
    
    try {
        $query = "
            SELECT sr.id, sr.lab_number, sr.time_in, sr.time_out, sr.purpose, u.id_number, u.lastname, u.firstname, u.middlename
            FROM sitin_records sr
            JOIN users u ON sr.user_id = u.id
            WHERE sr.user_id = ?
        ";
        
        $params = [$_SESSION['user_id']];
        
        if ($filter === 'today') {
            $query .= " AND date(sr.time_in) = date('now')";
        } elseif ($filter === 'week') {
            $query .= " AND sr.time_in >= datetime('now', '-7 days')";
        } elseif ($filter === 'month') {
            $query .= " AND sr.time_in >= datetime('now', '-30 days')";
        } elseif ($filter === 'ongoing') {
            $query .= " AND sr.time_out IS NULL";
        }
        
        $query .= " ORDER BY sr.time_in DESC LIMIT 50";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $records = $stmt->fetchAll();
        
        $formatted = array_map(function($r) {
            $duration = '';
            if ($r['time_out']) {
                $start = strtotime($r['time_in']);
                $end = strtotime($r['time_out']);
                $hours = floor(($end - $start) / 3600);
                $minutes = floor((($end - $start) % 3600) / 60);
                $duration = $hours . 'h ' . $minutes . 'm';
            }
            $fullName = $r['firstname'];
            if (!empty($r['middlename'])) {
                $fullName .= ' ' . $r['middlename'];
            }
            $fullName .= ' ' . $r['lastname'];
            return [
                'id' => $r['id'],
                'id_number' => $r['id_number'],
                'name' => $fullName,
                'lab' => $r['lab_number'],
                'time_in' => $r['time_in'],
                'time_out' => $r['time_out'],
                'purpose' => $r['purpose'],
                'duration' => $duration,
                'status' => $r['time_out'] ? 'Completed' : 'Ongoing',
                'date' => date('Y-m-d', strtotime($r['time_in']))
            ];
        }, $records);
        
        echo json_encode(['success' => true, 'history' => $formatted]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getCurrentSitIn() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, lab_number, time_in, purpose
            FROM sitin_records
            WHERE user_id = ? AND time_out IS NULL
            ORDER BY time_in DESC
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $record = $stmt->fetch();
        
        if ($record) {
            echo json_encode([
                'success' => true,
                'current_sitin' => [
                    'id' => $record['id'],
                    'lab' => $record['lab_number'],
                    'time_in' => $record['time_in'],
                    'purpose' => $record['purpose']
                ]
            ]);
        } else {
            echo json_encode(['success' => true, 'current_sitin' => null]);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function startSitIn() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    
    $lab = $input['lab'] ?? '';
    $purpose = $input['purpose'] ?? '';
    
    if (empty($lab) || empty($purpose)) {
        echo json_encode(['success' => false, 'message' => 'Lab and purpose are required']);
        return;
    }
    
    // Check if already has ongoing sit-in
    $stmt = $pdo->prepare("SELECT id FROM sitin_records WHERE user_id = ? AND time_out IS NULL");
    $stmt->execute([$_SESSION['user_id']]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You already have an ongoing sit-in']);
        return;
    }
    
    // Check remaining sessions
    $sessionStmt = $pdo->prepare("SELECT remaining_sessions FROM user_sessions WHERE user_id = ?");
    $sessionStmt->execute([$_SESSION['user_id']]);
    $sessionResult = $sessionStmt->fetch();
    
    $remainingSessions = $sessionResult ? $sessionResult['remaining_sessions'] : 30;
    
    if ($remainingSessions <= 0) {
        echo json_encode(['success' => false, 'message' => 'No remaining sessions available']);
        return;
    }
    
    try {
        $insertStmt = $pdo->prepare("INSERT INTO sitin_records (user_id, lab_number, purpose) VALUES (?, ?, ?)");
        $insertStmt->execute([$_SESSION['user_id'], $lab, $purpose]);
        
        // Decrement remaining sessions
        if ($sessionResult) {
            $updateStmt = $pdo->prepare("UPDATE user_sessions SET remaining_sessions = remaining_sessions - 1 WHERE user_id = ?");
            $updateStmt->execute([$_SESSION['user_id']]);
        } else {
            $insertSessionStmt = $pdo->prepare("INSERT INTO user_sessions (user_id, remaining_sessions) VALUES (?, 29)");
            $insertSessionStmt->execute([$_SESSION['user_id']]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Sit-In started successfully', 'remaining_sessions' => $remainingSessions - 1]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function endSitIn() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE sitin_records SET time_out = datetime('now') WHERE user_id = ? AND time_out IS NULL");
        $stmt->execute([$_SESSION['user_id']]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Sit-In ended successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No ongoing sit-in found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getRemainingSessions() {
    global $pdo;
    
    // Create user_sessions table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER UNIQUE NOT NULL,
            remaining_sessions INTEGER DEFAULT 30,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
    
    try {
        $stmt = $pdo->prepare("SELECT remaining_sessions FROM user_sessions WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch();
        
        if ($result) {
            echo json_encode(['success' => true, 'remaining_sessions' => $result['remaining_sessions']]);
        } else {
            // Create new user session with 30 sessions
            $insertStmt = $pdo->prepare("INSERT INTO user_sessions (user_id, remaining_sessions) VALUES (?, 30)");
            $insertStmt->execute([$_SESSION['user_id']]);
            echo json_encode(['success' => true, 'remaining_sessions' => 30]);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getReservations() {
    global $pdo;
    
    // Create reservations table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reservations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            lab_number TEXT NOT NULL,
            reservation_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            purpose TEXT,
            status TEXT DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, lab_number, reservation_date, start_time, end_time, purpose, status, created_at
            FROM reservations
            WHERE user_id = ?
            ORDER BY reservation_date DESC, start_time DESC
            LIMIT 20
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $reservations = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'reservations' => $reservations]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function makeReservation() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    
    $lab = $input['lab'] ?? '';
    $date = $input['date'] ?? '';
    $startTime = $input['start_time'] ?? '';
    $endTime = $input['end_time'] ?? '';
    $purpose = $input['purpose'] ?? '';
    
    if (empty($lab) || empty($date) || empty($startTime) || empty($endTime)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }
    
    // Create reservations table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reservations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            lab_number TEXT NOT NULL,
            reservation_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            purpose TEXT,
            status TEXT DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO reservations (user_id, lab_number, reservation_date, start_time, end_time, purpose)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$_SESSION['user_id'], $lab, $date, $startTime, $endTime, $purpose]);
        
        echo json_encode(['success' => true, 'message' => 'Reservation submitted successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function cancelReservation() {
    global $pdo;
    $reservationId = $_GET['id'] ?? '';
    
    if (empty($reservationId)) {
        echo json_encode(['success' => false, 'message' => 'Reservation ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM reservations WHERE id = ? AND user_id = ? AND status = 'pending'");
        $stmt->execute([$reservationId, $_SESSION['user_id']]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Reservation cancelled successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Reservation not found or cannot be cancelled']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getNotifications() {
    global $pdo;
    
    // Create notifications table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            message TEXT,
            type TEXT DEFAULT 'info',
            is_read INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, title, message, type, is_read, created_at
            FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $notifications = $stmt->fetchAll();
        
        // Get unread count
        $countStmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $countStmt->execute([$_SESSION['user_id']]);
        $unreadCount = $countStmt->fetch()['count'];
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unreadCount
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function markNotificationRead() {
    global $pdo;
    $notificationId = $_GET['id'] ?? '';
    
    if (empty($notificationId)) {
        echo json_encode(['success' => false, 'message' => 'Notification ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notificationId, $_SESSION['user_id']]);
        
        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function submitFeedback() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $feedbackText = $data['feedback_text'] ?? '';
    $rating = $data['rating'] ?? 5;
    $sitinRecordId = $data['sitin_record_id'] ?? null;
    
    if (empty($feedbackText)) {
        echo json_encode(['success' => false, 'message' => 'Feedback text is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO feedback (user_id, sitin_record_id, feedback_text, rating) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $sitinRecordId, $feedbackText, $rating]);
        
        echo json_encode(['success' => true, 'message' => 'Feedback submitted successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
