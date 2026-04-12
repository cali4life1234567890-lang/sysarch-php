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
    case 'mark_all_notifications_read':
        markAllNotificationsRead();
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
    case 'get_active_sitin':
        getActiveSitIn();
        break;
    case 'submit_feedback':
        submitFeedback();
        break;
    case 'get_lab_pc_status':
        getLabPcStatus();
        break;
    case 'reserve_pc':
        reservePc();
        break;
    case 'sync_pc_status':
        syncPcStatus();
        break;
    case 'change_password':
        changePassword();
        break;
    case 'reset_all_passwords':
        resetAllPasswords();
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

function changePassword() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    
    $oldPassword = $input['old_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';
    
    if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
        echo json_encode(['success' => false, 'message' => 'All password fields are required']);
        return;
    }
    
    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'New password and confirm password do not match']);
        return;
    }
    
    if (strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters']);
        return;
    }
    
    try {
        // Get current password hash
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            return;
        }
        
        // Verify old password
        if (!password_verify($oldPassword, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Old password is incorrect']);
            return;
        }
        
        // Update to new password
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $updateStmt->execute([$newHash, $_SESSION['user_id']]);
        
        echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
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

function checkAndStartApprovedReservations() {
    global $pdo;
    
    try {
        $checkActiveStmt = $pdo->prepare("SELECT id FROM sitin_records WHERE user_id = ? AND time_out IS NULL");
        $checkActiveStmt->execute([$_SESSION['user_id']]);
        if ($checkActiveStmt->fetch()) {
            return;
        }
        
        // Check for approved reservations that are due
        $stmt = $pdo->prepare("
            SELECT id, user_id, lab_number, pc_number, reservation_date, start_time, end_time, purpose
            FROM reservations
            WHERE user_id = ? AND status = 'approved' AND date(reservation_date) = date('now') AND time(start_time) <= time('now')
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $approvedReservations = $stmt->fetchAll();
        
        if (empty($approvedReservations)) {
            // Also check for pending reservations that have reached their time
            $stmt = $pdo->prepare("
                SELECT id, user_id, lab_number, pc_number, reservation_date, start_time, end_time, purpose
                FROM reservations
                WHERE user_id = ? AND status = 'pending' AND date(reservation_date) = date('now') AND time(start_time) <= time('now')
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $approvedReservations = $stmt->fetchAll();
        }
        
        if (empty($approvedReservations)) {
            return;
        }
        
        foreach ($approvedReservations as $res) {
            $checkStmt = $pdo->prepare("SELECT id FROM sitin_records WHERE reservation_id = ? AND time_out IS NULL");
            $checkStmt->execute([$res['id']]);
            if ($checkStmt->fetch()) {
                continue;
            }
            
            $sessionStmt = $pdo->prepare("SELECT remaining_sessions FROM user_sessions WHERE user_id = ?");
            $sessionStmt->execute([$_SESSION['user_id']]);
            $session = $sessionStmt->fetch();
            
            $remainingSessions = $session ? $session['remaining_sessions'] : 30;
            
            if ($remainingSessions <= 0) {
                continue;
            }
            
            try {
                $pdo->exec("ALTER TABLE sitin_records ADD COLUMN pc_number INTEGER");
            } catch (PDOException $e) {
            }
            
            try {
                $pdo->exec("ALTER TABLE sitin_records ADD COLUMN reservation_id INTEGER");
            } catch (PDOException $e) {
            }
            
            $insertStmt = $pdo->prepare("INSERT INTO sitin_records (user_id, lab_number, pc_number, purpose, reservation_id, time_in) VALUES (?, ?, ?, ?, ?, datetime('now'))");
            $insertStmt->execute([$_SESSION['user_id'], $res['lab_number'], $res['pc_number'], $res['purpose'], $res['id']]);
            
            if ($session) {
                $updateStmt = $pdo->prepare("UPDATE user_sessions SET remaining_sessions = remaining_sessions - 1 WHERE user_id = ?");
                $updateStmt->execute([$_SESSION['user_id']]);
            } else {
                $insertSessionStmt = $pdo->prepare("INSERT INTO user_sessions (user_id, remaining_sessions) VALUES (?, 29)");
                $insertSessionStmt->execute([$_SESSION['user_id']]);
            }
            
            if ($res['pc_number']) {
                $pcStmt = $pdo->prepare("UPDATE lab_pc_status SET status = 'occupied' WHERE lab_number = ? AND pc_number = ?");
                $pcStmt->execute([$res['lab_number'], $res['pc_number']]);
            }
            
            // Update reservation status to active
            $pdo->prepare("UPDATE reservations SET status = 'active' WHERE id = ?")->execute([$res['id']]);
            
            // Create notification for user
            $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
            $notifStmt->execute([$_SESSION['user_id'], 'Reservation Started!', 'Your reservation for Lab ' . $res['lab_number'] . ' has started! You are now signed in. PC: ' . ($res['pc_number'] ? $res['pc_number'] : 'Not assigned'), 'success']);
        }
    } catch (PDOException $e) {
        error_log('Error in checkAndStartApprovedReservations: ' . $e->getMessage());
    }
}

function getCurrentSitIn() {
    global $pdo;
    
    checkAndStartApprovedReservations();
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, lab_number, pc_number, time_in, purpose
            FROM sitin_records
            WHERE user_id = ? AND time_out IS NULL
            ORDER BY time_in DESC
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $record = $stmt->fetch();
        
        if ($record) {
            $timeIn = strtotime($record['time_in']);
            $now = time();
            $duration = $now - $timeIn;
            $maxDuration = 7200;
            
            if ($duration >= $maxDuration) {
                $endStmt = $pdo->prepare("UPDATE sitin_records SET time_out = datetime('now') WHERE id = ?");
                $endStmt->execute([$record['id']]);
                
                if ($record['pc_number']) {
                    $pcStmt = $pdo->prepare("UPDATE lab_pc_status SET status = 'available' WHERE lab_number = ? AND pc_number = ?");
                    $pcStmt->execute([$record['lab_number'], $record['pc_number']]);
                }
                
                echo json_encode([
                    'success' => true,
                    'current_sitin' => null,
                    'auto_ended' => true,
                    'message' => 'Your sit-in has been automatically ended due to the 2-hour limit'
                ]);
                return;
            }
            
            $remainingSeconds = $maxDuration - $duration;
            
            echo json_encode([
                'success' => true,
                'current_sitin' => [
                    'id' => $record['id'],
                    'lab' => $record['lab_number'],
                    'time_in' => $record['time_in'],
                    'purpose' => $record['purpose']
                ],
                'remaining_seconds' => $remainingSeconds
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
    $pc_number = $input['pc_number'] ?? null;
    
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
        // Ensure pc_number column exists in sitin_records
        try {
            $pdo->exec("ALTER TABLE sitin_records ADD COLUMN pc_number INTEGER");
        } catch (PDOException $e) {
            // Column might already exist, ignore
        }
        
        $insertStmt = $pdo->prepare("INSERT INTO sitin_records (user_id, lab_number, pc_number, purpose) VALUES (?, ?, ?, ?)");
        $insertStmt->execute([$_SESSION['user_id'], $lab, $pc_number, $purpose]);
        
        // Decrement remaining sessions
        if ($sessionResult) {
            $updateStmt = $pdo->prepare("UPDATE user_sessions SET remaining_sessions = remaining_sessions - 1 WHERE user_id = ?");
            $updateStmt->execute([$_SESSION['user_id']]);
        } else {
            $insertSessionStmt = $pdo->prepare("INSERT INTO user_sessions (user_id, remaining_sessions) VALUES (?, 29)");
            $insertSessionStmt->execute([$_SESSION['user_id']]);
        }
        
        // Update PC status to occupied if PC number is provided
        if ($pc_number) {
            $stmt = $pdo->prepare("UPDATE lab_pc_status SET status = 'occupied' WHERE lab_number = ? AND pc_number = ?");
            $stmt->execute([$lab, $pc_number]);
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
    
    // Check and start any approved reservations that are due
    checkAndStartApprovedReservations();
    
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

function getActiveSitIn() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id, lab_number, pc_number, time_in FROM sitin_records WHERE user_id = ? AND time_out IS NULL");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch();
        
        if ($result) {
            $timeIn = strtotime($result['time_in']);
            $endTime = strtotime('+2 hours', $timeIn);
            $currentTime = time();
            $remainingSeconds = max(0, $endTime - $currentTime);
            
            echo json_encode([
                'success' => true,
                'sitin' => [
                    'id' => (int)$result['id'],
                    'lab_number' => $result['lab_number'],
                    'pc_number' => $result['pc_number'] ? (int)$result['pc_number'] : null,
                    'time_in' => $result['time_in'],
                    'end_time' => date('Y-m-d H:i:s', $endTime),
                    'remaining_seconds' => (int)$remainingSeconds
                ]
            ]);
        } else {
            echo json_encode(['success' => true, 'sitin' => null]);
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
            pc_number INTEGER,
            reservation_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            purpose TEXT,
            status TEXT DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
    
    // Check and start any approved reservations that are due
    checkAndStartApprovedReservations();
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, lab_number, pc_number, reservation_date, start_time, end_time, purpose, status, created_at
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
    
    // Create reservations table if not exists with pc_number column
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reservations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            lab_number TEXT NOT NULL,
            pc_number INTEGER,
            reservation_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            purpose TEXT,
            status TEXT DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
    
    // Add pc_number column if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE reservations ADD COLUMN pc_number INTEGER");
    } catch (PDOException $e) {
        // Column might already exist, ignore
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO reservations (user_id, lab_number, pc_number, reservation_date, start_time, end_time, purpose)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$_SESSION['user_id'], $lab, null, $date, $startTime, $endTime, $purpose]);
        
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
        // Get reservation details first
        $stmt = $pdo->prepare("SELECT lab_number, pc_number FROM reservations WHERE id = ? AND user_id = ? AND status = 'pending'");
        $stmt->execute([$reservationId, $_SESSION['user_id']]);
        $reservation = $stmt->fetch();
        
        // Delete the reservation
        $stmt = $pdo->prepare("DELETE FROM reservations WHERE id = ? AND user_id = ? AND status = 'pending'");
        $stmt->execute([$reservationId, $_SESSION['user_id']]);
        
        if ($stmt->rowCount() > 0) {
            // Release the PC status if PC was reserved
            if ($reservation && $reservation['pc_number']) {
                $stmt = $pdo->prepare("UPDATE lab_pc_status SET status = 'available' WHERE lab_number = ? AND pc_number = ?");
                $stmt->execute([$reservation['lab_number'], $reservation['pc_number']]);
            }
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

function markAllNotificationsRead() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        
        echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
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

function getLabPcStatus() {
    global $pdo;
    $lab = $_GET['lab'] ?? '';
    
    if (empty($lab)) {
        echo json_encode(['success' => false, 'message' => 'Lab parameter is required']);
        return;
    }
    
    // Ensure lab_pc_status table exists and has data
    try {
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM lab_pc_status WHERE lab_number = ?");
        $checkStmt->execute([$lab]);
        if ($checkStmt->fetchColumn() == 0) {
            // Initialize 56 PCs for this lab
            for ($pc = 1; $pc <= 56; $pc++) {
                $insertPc = $pdo->prepare("INSERT INTO lab_pc_status (lab_number, pc_number, status) VALUES (?, ?, 'available')");
                $insertPc->execute([$lab, $pc]);
            }
        }
    } catch (PDOException $e) {
        // Table might not exist, create it
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS lab_pc_status (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lab_number TEXT NOT NULL,
                pc_number INTEGER NOT NULL,
                status TEXT DEFAULT 'available'
            )");
            // Initialize PCs
            for ($pc = 1; $pc <= 56; $pc++) {
                $insertPc = $pdo->prepare("INSERT INTO lab_pc_status (lab_number, pc_number, status) VALUES (?, ?, 'available')");
                $insertPc->execute([$lab, $pc]);
            }
        } catch (PDOException $e2) {
            echo json_encode(['success' => false, 'message' => 'Failed to initialize PC status: ' . $e2->getMessage()]);
            return;
        }
    }
    
    // First, sync PC status based on current sit-in records and reservations
    try {
        syncPcStatusForLab($lab);
    } catch (PDOException $e) {
        // Continue even if sync fails - just use current DB status
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT pc_number, status 
            FROM lab_pc_status 
            WHERE lab_number = ?
            ORDER BY pc_number
        ");
        $stmt->execute([$lab]);
        $pcs = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'pcs' => $pcs]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function syncPcStatusForLab($lab) {
    global $pdo;
    
    // Reset all PCs in this lab to available
    $stmt = $pdo->prepare("UPDATE lab_pc_status SET status = 'available' WHERE lab_number = ?");
    $stmt->execute([$lab]);
    
    // Mark PCs as occupied that have current sit-ins
    $stmt = $pdo->prepare("
        SELECT DISTINCT pc_number 
        FROM sitin_records 
        WHERE lab_number = ? AND time_out IS NULL AND pc_number IS NOT NULL
    ");
    $stmt->execute([$lab]);
    $occupiedPcs = $stmt->fetchAll();
    $occupiedPcNumbers = array_filter(array_map('intval', array_column($occupiedPcs, 'pc_number')));
    
    if (!empty($occupiedPcNumbers)) {
        $placeholders = implode(',', array_fill(0, count($occupiedPcNumbers), '?'));
        $stmt = $pdo->prepare("UPDATE lab_pc_status SET status = 'occupied' WHERE lab_number = ? AND pc_number IN ($placeholders)");
        $stmt->execute(array_merge([$lab], $occupiedPcNumbers));
    }
    
    // Check if reservations table exists
    try {
        $tableCheck = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='reservations'");
        if ($tableCheck && $tableCheck->fetch()) {
            // Mark PCs as reserved that have pending reservations for today
            $stmt = $pdo->prepare("
                SELECT DISTINCT pc_number 
                FROM reservations 
                WHERE lab_number = ? AND status = 'pending' AND reservation_date = date('now')
            ");
            $stmt->execute([$lab]);
            $reservedPcs = $stmt->fetchAll();
            $reservedPcNumbers = array_filter(array_map('intval', array_column($reservedPcs, 'pc_number')));
            
            if (!empty($reservedPcNumbers)) {
                $placeholders = implode(',', array_fill(0, count($reservedPcNumbers), '?'));
                $stmt = $pdo->prepare("UPDATE lab_pc_status SET status = 'reserved' WHERE lab_number = ? AND pc_number IN ($placeholders)");
                $stmt->execute(array_merge([$lab], $reservedPcNumbers));
            }
        }
    } catch (PDOException $e) {
        // Reservations table might not exist, ignore
    }
}

function syncPcStatus() {
    global $pdo;
    $lab = $_GET['lab'] ?? '';
    
    if (empty($lab)) {
        echo json_encode(['success' => false, 'message' => 'Lab parameter is required']);
        return;
    }
    
    try {
        syncPcStatusForLab($lab);
        echo json_encode(['success' => true, 'message' => 'PC status synced']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function reservePc() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    
    $lab = $input['lab'] ?? '';
    $pcNumber = $input['pc_number'] ?? '';
    $date = $input['date'] ?? '';
    $startTime = $input['start_time'] ?? '';
    $endTime = $input['end_time'] ?? '';
    $purpose = $input['purpose'] ?? '';
    
    if (empty($lab) || empty($pcNumber) || empty($date) || empty($startTime) || empty($endTime)) {
        echo json_encode(['success' => false, 'message' => 'All fields including PC selection are required']);
        return;
    }
    
    // Check if PC is available
    $checkStmt = $pdo->prepare("SELECT status FROM lab_pc_status WHERE lab_number = ? AND pc_number = ?");
    $checkStmt->execute([$lab, $pcNumber]);
    $pcStatus = $checkStmt->fetch();
    
    if (!$pcStatus || $pcStatus['status'] !== 'available') {
        echo json_encode(['success' => false, 'message' => 'Selected PC is not available']);
        return;
    }
    
    // Create reservations table if not exists with pc_number column
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reservations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            lab_number TEXT NOT NULL,
            pc_number INTEGER,
            reservation_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            purpose TEXT,
            status TEXT DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
    
    // Add pc_number column if it doesn't exist (for existing tables)
    try {
        $pdo->exec("ALTER TABLE reservations ADD COLUMN pc_number INTEGER");
    } catch (PDOException $e) {
        // Column might already exist, ignore
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO reservations (user_id, lab_number, pc_number, reservation_date, start_time, end_time, purpose)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$_SESSION['user_id'], $lab, $pcNumber, $date, $startTime, $endTime, $purpose]);
        
        // Update PC status to reserved
        $updateStmt = $pdo->prepare("UPDATE lab_pc_status SET status = 'reserved' WHERE lab_number = ? AND pc_number = ?");
        $updateStmt->execute([$lab, $pcNumber]);
        
        echo json_encode(['success' => true, 'message' => 'Reservation submitted successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function resetAllPasswords() {
    global $pdo;
    
    // Check if admin
    $stmt = $pdo->prepare("SELECT id_number FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user || $user['id_number'] !== '2664388') {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }
    
    $newPassword = password_hash('123456', PASSWORD_DEFAULT);
    
    try {
        $updateStmt = $pdo->prepare("UPDATE users SET password = ?");
        $updateStmt->execute([$newPassword]);
        
        echo json_encode(['success' => true, 'message' => 'All passwords reset to 123456']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
