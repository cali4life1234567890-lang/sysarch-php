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
    case 'end_reservation_session':
        endReservationSession();
        break;
    case 'start_reservation_sitin':
        startReservationSitIn();
        break;
    case 'physical_sitin':
        handlePhysicalSitInReq();
        break;
    case 'report':
        generateReport();
        break;
    case 'post_announcement':
        postAnnouncement();
        break;
    case 'get_announcements':
        getAnnouncements();
        break;
    case 'edit_announcement':
        editAnnouncement();
        break;
    case 'delete_announcement':
        deleteAnnouncement();
        break;
    case 'delete_all_feedback':
        deleteAllFeedback();
        break;
    case 'leaderboard':
        getLeaderboard();
        break;
    case 'analytics_data':
        getAnalyticsData();
        break;
    case 'ai_recommendations':
        getAIRecommendations();
        break;
    case 'download_report':
        downloadReport();
        break;
    case 'import_software':
        importSoftware();
        break;
    case 'get_software':
        getSoftware();
        break;
    case 'add_software':
        addSoftware();
        break;
    case 'delete_software':
        deleteSoftware();
        break;
    case 'get_pcs':
        getPcs();
        break;
    case 'update_pc_status':
        updatePcStatus();
        break;
    case 'get_pc_details':
        getPcDetails();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getStats() {
    global $pdo;
    
    try {
        // Auto-terminate sit-ins that exceed 2 hours
        autoTerminateExpiredSitIns();
        
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

function getLeaderboard() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT 
                u.id_number,
                u.lastname,
                u.firstname,
                u.middlename,
                u.course,
                u.level,
                COALESCE(SUM(
                    CASE 
                        WHEN sr.time_out IS NOT NULL 
                        THEN (julianday(sr.time_out) - julianday(sr.time_in)) * 24 
                        ELSE 0 
                    END
                ), 0) as total_hours,
                COALESCE((30 - us.remaining_sessions), 30) as used_sessions
            FROM users u
            LEFT JOIN sitin_records sr ON u.id = sr.user_id
            LEFT JOIN user_sessions us ON u.id = us.user_id
            WHERE u.id_number != '2664388'
            GROUP BY u.id
            ORDER BY total_hours DESC
        ");
        
        $leaderboardData = [];
        $rank = 1;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $totalHours = round($row['total_hours'], 2);
            $usedSessions = intval($row['used_sessions']);
            $totalScore = (0.60 * $totalHours) + (0.40 * $usedSessions);
            
            $name = trim($row['firstname'] . ' ' . ($row['middlename'] ? $row['middlename'] . ' ' : '') . $row['lastname']);
            
            $leaderboardData[] = [
                'rank' => $rank,
                'id_number' => $row['id_number'],
                'name' => $name,
                'course' => $row['course'],
                'level' => $row['level'],
                'hours_spent' => $totalHours,
                'sessions_used' => $usedSessions,
                'total_score' => round($totalScore, 2)
            ];
            $rank++;
        }
        
        // Sort by total score
        usort($leaderboardData, function($a, $b) {
            return $b['total_score'] - $a['total_score'];
        });
        
        // Re-assign ranks after sorting
        $rankedData = [];
        $rank = 1;
        foreach ($leaderboardData as &$entry) {
            $entry['rank'] = $rank;
            $rankedData[] = $entry;
            $rank++;
        }
        
        echo json_encode(['success' => true, 'leaderboard' => $rankedData]);
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
         $stmt = $pdo->prepare("SELECT user_id, lab_number, pc_number, purpose, reservation_date, start_time, end_time FROM reservations WHERE id = ?");
         $stmt->execute([$reservationId]);
         $reservation = $stmt->fetch();
         
         if (!$reservation) {
              echo json_encode(['success' => false, 'message' => 'Reservation not found']);
              return;
          }

          // Check if user is allowed to have reservations
          $canReserveStmt = $pdo->prepare("SELECT can_reserve FROM users WHERE id = ?");
          $canReserveStmt->execute([$reservation['user_id']]);
          $canReserveUser = $canReserveStmt->fetch();
          if ($canReserveUser && !$canReserveUser['can_reserve']) {
              echo json_encode(['success' => false, 'message' => 'Reservation is disabled for this student. Cannot approve.']);
              return;
          }

          // Check if user already has an active sit-in
         $checkStmt = $pdo->prepare("SELECT id FROM sitin_records WHERE user_id = ? AND reservation_id = ?");
         $checkStmt->execute([$reservation['user_id'], $reservationId]);
         if ($checkStmt->fetch()) {
             echo json_encode(['success' => false, 'message' => 'Reservation already approved and sit-in started']);
             return;
         }
         
         // Update reservation status to approved
         $stmt = $pdo->prepare("UPDATE reservations SET status = 'approved' WHERE id = ?");
         $stmt->execute([$reservationId]);
         
         // Create notification for user
         createUserNotification($reservation['user_id'], 'Reservation Approved', 'Your reservation for Lab ' . $reservation['lab_number'] . ' on ' . $reservation['reservation_date'] . ' from ' . $reservation['start_time'] . ' has been approved.', 'success');
         
         echo json_encode(['success' => true, 'message' => 'Reservation approved.']);
     } catch (PDOException $e) {
         echo json_encode(['success' => false, 'message' => $e->getMessage()]);
     }
 }

function startReservationSitIn() {
     global $pdo;
     $reservationId = $_GET['id'] ?? '';
     
     if (empty($reservationId)) {
         echo json_encode(['success' => false, 'message' => 'Reservation ID is required']);
         return;
     }
     
     try {
         // Get reservation details
         $stmt = $pdo->prepare("SELECT user_id, lab_number, pc_number, purpose, reservation_date, start_time, end_time, status FROM reservations WHERE id = ?");
         $stmt->execute([$reservationId]);
         $reservation = $stmt->fetch();
         
         if (!$reservation) {
              echo json_encode(['success' => false, 'message' => 'Reservation not found']);
              return;
          }

          if ($reservation['status'] !== 'approved') {
              echo json_encode(['success' => false, 'message' => 'Reservation must be approved first']);
              return;
          }

          // Check if user already has an active sit-in
         $checkStmt = $pdo->prepare("SELECT id FROM sitin_records WHERE user_id = ? AND time_out IS NULL");
         $checkStmt->execute([$reservation['user_id']]);
         if ($checkStmt->fetch()) {
             echo json_encode(['success' => false, 'message' => 'Student already has an active sit-in session']);
             return;
         }
         
         // Check user's remaining sessions
         $sessionStmt = $pdo->prepare("SELECT remaining_sessions FROM user_sessions WHERE user_id = ?");
         $sessionStmt->execute([$reservation['user_id']]);
         $session = $sessionStmt->fetch();
         
         $remainingSessions = $session ? $session['remaining_sessions'] : 30;
         
         if ($remainingSessions <= 0) {
             echo json_encode(['success' => false, 'message' => 'User has no remaining sessions']);
             return;
         }
         
         // Create sitin_records table if not exists
         $pdo->exec("CREATE TABLE IF NOT EXISTS sitin_records (
             id INTEGER PRIMARY KEY AUTOINCREMENT,
             user_id INTEGER NOT NULL,
             lab_number TEXT NOT NULL,
             pc_number INTEGER,
             time_in DATETIME DEFAULT CURRENT_TIMESTAMP,
             time_out DATETIME,
             purpose TEXT,
             start_time TEXT,
             end_time TEXT,
             FOREIGN KEY (user_id) REFERENCES users(id)
         )");
         
         // Add pc_number column if it doesn't exist
         try {
             $pdo->exec("ALTER TABLE sitin_records ADD COLUMN pc_number INTEGER");
         } catch (PDOException $e) {
             // Column might already exist, ignore
         }
         
         // Add reservation_id column if it doesn't exist
         try {
             $pdo->exec("ALTER TABLE sitin_records ADD COLUMN reservation_id INTEGER");
         } catch (PDOException $e) {
             // Column might already exist, ignore
         }
         
         // Add start_time column if it doesn't exist
         try {
             $pdo->exec("ALTER TABLE sitin_records ADD COLUMN start_time TEXT");
         } catch (PDOException $e) {
             // Column might already exist, ignore
         }

         // Add end_time column if it doesn't exist
         try {
             $pdo->exec("ALTER TABLE sitin_records ADD COLUMN end_time TEXT");
         } catch (PDOException $e) {
             // Column might already exist, ignore
         }
         
         // Create sit-in record
         $insertStmt = $pdo->prepare("INSERT INTO sitin_records (user_id, lab_number, pc_number, purpose, reservation_id, time_in, start_time, end_time) VALUES (?, ?, ?, ?, ?, datetime('now'), ?, ?)");
         $insertStmt->execute([$reservation['user_id'], $reservation['lab_number'], $reservation['pc_number'], $reservation['purpose'], $reservationId, $reservation['start_time'], $reservation['end_time']]);
         
         // Update reservation status to active
         $stmt = $pdo->prepare("UPDATE reservations SET status = 'active' WHERE id = ?");
         $stmt->execute([$reservationId]);
         
         // Deduct remaining sessions
         if ($session) {
             $updateStmt = $pdo->prepare("UPDATE user_sessions SET remaining_sessions = remaining_sessions - 1 WHERE user_id = ?");
             $updateStmt->execute([$reservation['user_id']]);
         } else {
             $insertSessionStmt = $pdo->prepare("INSERT INTO user_sessions (user_id, remaining_sessions) VALUES (?, 29)");
             $insertSessionStmt->execute([$reservation['user_id']]);
         }
         
         // Update PC status to occupied if PC number is provided
         // No global PC status update here; occupancy is handled per reservation timeslot via sitin_records

         
         // Create notification for user
         createUserNotification($reservation['user_id'], 'Sit-In Started', 'Your sit-in session for Lab ' . $reservation['lab_number'] . ' has started. 1 session has been deducted.', 'success');
         
         echo json_encode(['success' => true, 'message' => 'Sit-in started and 1 session deducted.']);
     } catch (PDOException $e) {
         echo json_encode(['success' => false, 'message' => $e->getMessage()]);
     }
}

function handlePhysicalSitInReq() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    $studentId = trim($input['student_id'] ?? '');

    if (empty($studentId)) {
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        return;
    }

    try {
        // 1. Get user ID from student ID
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id_number = ?");
        $stmt->execute([$studentId]);
        $user = $stmt->fetch();

        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            return;
        }

        $userId = $user['id'];

        // 2. Check if student already has an active sit-in
        $checkStmt = $pdo->prepare("SELECT id FROM sitin_records WHERE user_id = ? AND time_out IS NULL");
        $checkStmt->execute([$userId]);
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Student already has an active sit-in session']);
            return;
        }

        // 3. Find an approved reservation for today
        // Assuming reservation_date format allows direct string comparison with current date, or simply picking the first approved reservation
        $resStmt = $pdo->prepare("SELECT id, lab_number, pc_number, purpose, reservation_date, start_time, end_time FROM reservations WHERE user_id = ? AND status = 'approved' ORDER BY reservation_date ASC LIMIT 1");
        $resStmt->execute([$userId]);
        $reservation = $resStmt->fetch();

        if (!$reservation) {
            echo json_encode(['success' => false, 'message' => 'No approved reservation found for this student']);
            return;
        }

        $reservationId = $reservation['id'];

        // 4. Start the sit-in (similar to startReservationSitIn)
        // Check sessions
        $sessionStmt = $pdo->prepare("SELECT remaining_sessions FROM user_sessions WHERE user_id = ?");
        $sessionStmt->execute([$userId]);
        $session = $sessionStmt->fetch();
        $remainingSessions = $session ? $session['remaining_sessions'] : 30;

        if ($remainingSessions <= 0) {
            echo json_encode(['success' => false, 'message' => 'User has no remaining sessions']);
            return;
        }

        // Create tables/columns if missing (same as before)
        $pdo->exec("CREATE TABLE IF NOT EXISTS sitin_records (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            lab_number TEXT NOT NULL,
            pc_number INTEGER,
            time_in DATETIME DEFAULT CURRENT_TIMESTAMP,
            time_out DATETIME,
            purpose TEXT,
            start_time TEXT,
            end_time TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )");
        try { $pdo->exec("ALTER TABLE sitin_records ADD COLUMN pc_number INTEGER"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE sitin_records ADD COLUMN reservation_id INTEGER"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE sitin_records ADD COLUMN start_time TEXT"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE sitin_records ADD COLUMN end_time TEXT"); } catch (PDOException $e) {}

        // Insert sitin
        $insertStmt = $pdo->prepare("INSERT INTO sitin_records (user_id, lab_number, pc_number, purpose, reservation_id, time_in, start_time, end_time) VALUES (?, ?, ?, ?, ?, datetime('now'), ?, ?)");
        $insertStmt->execute([$userId, $reservation['lab_number'], $reservation['pc_number'], $reservation['purpose'], $reservationId, $reservation['start_time'], $reservation['end_time']]);

        // Update reservation to active
        $updateStmt = $pdo->prepare("UPDATE reservations SET status = 'active' WHERE id = ?");
        $updateStmt->execute([$reservationId]);

        // Deduct session
        if ($session) {
            $deductStmt = $pdo->prepare("UPDATE user_sessions SET remaining_sessions = remaining_sessions - 1 WHERE user_id = ?");
            $deductStmt->execute([$userId]);
        } else {
            $insertSessionStmt = $pdo->prepare("INSERT INTO user_sessions (user_id, remaining_sessions) VALUES (?, 29)");
            $insertSessionStmt->execute([$userId]);
        }

        createUserNotification($userId, 'Sit-In Started', 'Your sit-in session for Lab ' . $reservation['lab_number'] . ' has started via ID Scan. 1 session has been deducted.', 'success');

        echo json_encode(['success' => true, 'message' => 'Physical sit-in successful for reservation #' . $reservationId]);
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

function endReservationSession() {
    global $pdo;
    $reservationId = $_GET['id'] ?? '';
    
    if (empty($reservationId)) {
        echo json_encode(['success' => false, 'message' => 'Reservation ID is required']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Find active sit-in record associated with this reservation (time_out IS NULL)
        $stmt = $pdo->prepare("SELECT id, lab_number, pc_number, user_id FROM sitin_records WHERE reservation_id = ? AND time_out IS NULL");
        $stmt->execute([$reservationId]);
        $record = $stmt->fetch();
        
        // Update reservation status to completed
        $stmt = $pdo->prepare("UPDATE reservations SET status = 'completed' WHERE id = ?");
        $stmt->execute([$reservationId]);
        
        if ($record) {
            // Update sit-in record to end it
            $stmt = $pdo->prepare("UPDATE sitin_records SET time_out = datetime('now') WHERE id = ?");
            $stmt->execute([$record['id']]);
            
            // Release PC if it was assigned
            if ($record['pc_number']) {
                $stmt = $pdo->prepare("UPDATE lab_pc_status SET status = 'available' WHERE lab_number = ? AND pc_number = ?");
                $stmt->execute([$record['lab_number'], $record['pc_number']]);
            }
            
            // Send notification to user that the session has ended
            createUserNotification($record['user_id'], 'Sit-In Session Ended', 'Your active sit-in session for Lab ' . $record['lab_number'] . ' PC ' . $record['pc_number'] . ' has been ended by the administrator.', 'info');
        } else {
            // If no active sit-in is found but reservation is active, release PC associated with the reservation if any
            $resStmt = $pdo->prepare("SELECT lab_number, pc_number, user_id FROM reservations WHERE id = ?");
            $resStmt->execute([$reservationId]);
            $resDetails = $resStmt->fetch();
            if ($resDetails) {
                if ($resDetails['pc_number']) {
                    $stmt = $pdo->prepare("UPDATE lab_pc_status SET status = 'available' WHERE lab_number = ? AND pc_number = ?");
                    $stmt->execute([$resDetails['lab_number'], $resDetails['pc_number']]);
                }
                createUserNotification($resDetails['user_id'], 'Reservation Session Ended', 'Your active reservation has been ended by the administrator.', 'info');
            }
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Session ended successfully']);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
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
    
    // Generate date in format: YYYY-Mon-DD (e.g. 2026-May-18)
    $dateStr = date('Y-M-d');
    
    try {
        $pdo->beginTransaction();
        
        // 1. Insert into announcements table
        $announceStmt = $pdo->prepare("INSERT INTO announcements (title, message, date) VALUES (?, ?, ?)");
        $announceStmt->execute([$title, $message, $dateStr]);
        
        // 2. Get all user IDs
        $stmt = $pdo->query("SELECT id FROM users WHERE id_number != '2664388'");
        $users = $stmt->fetchAll();
        
        // 3. Create notification for each user
        foreach ($users as $user) {
            $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'announcement')");
            $notifStmt->execute([$user['id'], $title, $message]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Announcement posted successfully']);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getAnnouncements() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT id, title, message, date, created_at FROM announcements ORDER BY id DESC LIMIT 50");
        $announcements = $stmt->fetchAll();
        echo json_encode(['success' => true, 'announcements' => $announcements]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function editAnnouncement() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $message = trim($input['message'] ?? '');
    
    if (empty($id) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'ID and message are required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE announcements SET message = ? WHERE id = ?");
        $stmt->execute([$message, $id]);
        
        echo json_encode(['success' => true, 'message' => 'Announcement updated successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteAnnouncement() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Announcement deleted successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function autoTerminateExpiredSitIns() {
    global $pdo;
    $maxDuration = 7200;
    
    try {
        $stmt = $pdo->query("
            SELECT id, user_id, lab_number, pc_number, time_in
            FROM sitin_records
            WHERE time_out IS NULL
        ");
        $activeSitIns = $stmt->fetchAll();
        
        foreach ($activeSitIns as $record) {
            $timeIn = strtotime($record['time_in']);
            $now = time();
            $duration = $now - $timeIn;
            
            if ($duration >= $maxDuration) {
                $endStmt = $pdo->prepare("UPDATE sitin_records SET time_out = datetime('now') WHERE id = ?");
                $endStmt->execute([$record['id']]);
                
                if ($record['pc_number']) {
                    $pcStmt = $pdo->prepare("UPDATE lab_pc_status SET status = 'available' WHERE lab_number = ? AND pc_number = ?");
                    $pcStmt->execute([$record['lab_number'], $record['pc_number']]);
                }
            }
        }
    } catch (PDOException $e) {
        // Ignore errors, just log
    }
}

function getAnalyticsData() {
    global $pdo;
    try {
        // 1. Weekly traffic (last 7 days of sit-ins)
        $weeklyTraffic = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sitin_records WHERE date(time_in) = date(?)");
            $stmt->execute([$date]);
            $weeklyTraffic[] = [
                'date' => date('M d', strtotime($date)),
                'count' => intval($stmt->fetch()['count'])
            ];
        }

        // 2. Lab occupancy (current active sit-ins per lab)
        $labOccupancy = [];
        $labs = ['524', '526', '528', '530', 'MAC'];
        foreach ($labs as $lab) {
            // Count active sit-ins
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sitin_records WHERE lab_number = ? AND time_out IS NULL");
            $stmt->execute([$lab]);
            $activeCount = intval($stmt->fetch()['count']);
            
            // Count total capacity (occupied + available)
            $stmt2 = $pdo->prepare("SELECT COUNT(*) as count FROM lab_pc_status WHERE lab_number = ?");
            $stmt2->execute([$lab]);
            $totalCapacity = intval($stmt2->fetch()['count']);
            if ($totalCapacity == 0) $totalCapacity = 56; // Fallback
            
            $labOccupancy[] = [
                'lab' => 'Lab ' . $lab,
                'active' => $activeCount,
                'capacity' => $totalCapacity,
                'occupancy_rate' => $totalCapacity > 0 ? round(($activeCount / $totalCapacity) * 100, 1) : 0
            ];
        }

        // 3. Purpose distribution - Categorize non-programming languages as "Others"
        $stmt = $pdo->query("
            SELECT purpose, COUNT(*) as count 
            FROM sitin_records 
            WHERE purpose IS NOT NULL AND purpose != ''
            GROUP BY purpose
        ");
        $purposes = $stmt->fetchAll();
        
        $grouped = [];
        $othersCount = 0;
        
        $programmingLanguages = [
            'java', 'python', 'c++', 'c#', 'c', 'php', 'javascript', 'html/css', 'sql', 'asp.net', 'ruby', 'swift', 'kotlin', 'go', 'typescript', 'rust', 'perl', 'scala', 'haskell', 'r', 'dart', 'assembly', 'cobol', 'fortran', 'matlab', 'vb.net', 'visual basic', 'bash', 'powershell', 'objective-c', 'html', 'css', 'programming'
        ];
        
        foreach ($purposes as $row) {
            $rawPurpose = trim($row['purpose']);
            $count = intval($row['count']);
            
            // Clean up "Others: " prefix if present
            $cleanedPurpose = $rawPurpose;
            if (stripos($rawPurpose, 'others:') === 0) {
                $cleanedPurpose = trim(substr($rawPurpose, 7));
            }
            
            $lower = strtolower($cleanedPurpose);
            
            // Check if it matches a known programming language
            if (in_array($lower, $programmingLanguages)) {
                // Normalize names for perfect presentation
                $display = $cleanedPurpose;
                if ($lower === 'java') $display = 'Java';
                elseif ($lower === 'python') $display = 'Python';
                elseif ($lower === 'c++') $display = 'C++';
                elseif ($lower === 'c#') $display = 'C#';
                elseif ($lower === 'c') $display = 'C';
                elseif ($lower === 'php') $display = 'PHP';
                elseif ($lower === 'javascript') $display = 'JavaScript';
                elseif ($lower === 'html/css' || $lower === 'html' || $lower === 'css') $display = 'HTML/CSS';
                elseif ($lower === 'sql') $display = 'SQL';
                elseif ($lower === 'asp.net') $display = 'ASP.NET';
                elseif ($lower === 'ruby') $display = 'Ruby';
                elseif ($lower === 'swift') $display = 'Swift';
                elseif ($lower === 'kotlin') $display = 'Kotlin';
                elseif ($lower === 'go') $display = 'Go';
                elseif ($lower === 'typescript') $display = 'TypeScript';
                else $display = ucfirst($cleanedPurpose);
                
                if (!isset($grouped[$display])) {
                    $grouped[$display] = 0;
                }
                $grouped[$display] += $count;
            } else {
                $othersCount += $count;
            }
        }
        
        // Sort programming languages by count descending
        arsort($grouped);
        
        $purposeData = [];
        foreach ($grouped as $purpose => $count) {
            $purposeData[] = [
                'purpose' => $purpose,
                'count' => $count
            ];
        }
        
        // Add others count if present
        if ($othersCount > 0) {
            $purposeData[] = [
                'purpose' => 'Others',
                'count' => $othersCount
            ];
        }
        
        // Re-sort the final purposeData so that it remains sorted by count descending
        usort($purposeData, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        
        // Limit to 6 items to match original behavior
        $purposeData = array_slice($purposeData, 0, 6);

        // 4. Monthly Lab Usage (total records per lab)
        $labTotalUsage = [];
        foreach ($labs as $lab) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sitin_records WHERE lab_number = ?");
            $stmt->execute([$lab]);
            $labTotalUsage[] = [
                'lab' => 'Lab ' . $lab,
                'count' => intval($stmt->fetch()['count'])
            ];
        }

        echo json_encode([
            'success' => true,
            'weekly_traffic' => $weeklyTraffic,
            'lab_occupancy' => $labOccupancy,
            'purpose_distribution' => $purposeData,
            'lab_total_usage' => $labTotalUsage
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getAIRecommendations() {
    global $pdo;
    try {
        $recommendations = [];

        // 1. Query for traffic stats per lab
        $stmt = $pdo->query("SELECT lab_number, COUNT(*) as count FROM sitin_records GROUP BY lab_number ORDER BY count DESC");
        $labTraffic = $stmt->fetchAll();

        // 2. Query for popular purposes
        $stmt = $pdo->query("SELECT purpose, COUNT(*) as count FROM sitin_records WHERE purpose IS NOT NULL AND purpose != '' GROUP BY purpose ORDER BY count DESC LIMIT 3");
        $popularPurposes = $stmt->fetchAll();

        // 3. Lab Occupancy Rate Check
        $labs = ['524', '526', '528', '530', 'MAC'];
        $highestOccupancyLab = '';
        $highestOccupancyRate = -1;
        $lowestOccupancyLab = '';
        $lowestOccupancyRate = 999;

        foreach ($labs as $lab) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sitin_records WHERE lab_number = ? AND time_out IS NULL");
            $stmt->execute([$lab]);
            $activeCount = intval($stmt->fetch()['count']);
            
            $stmt2 = $pdo->prepare("SELECT COUNT(*) as count FROM lab_pc_status WHERE lab_number = ?");
            $stmt2->execute([$lab]);
            $totalCapacity = intval($stmt2->fetch()['count']);
            if ($totalCapacity == 0) $totalCapacity = 56;
            
            $rate = ($activeCount / $totalCapacity) * 100;
            if ($rate > $highestOccupancyRate) {
                $highestOccupancyRate = $rate;
                $highestOccupancyLab = $lab;
            }
            if ($rate < $lowestOccupancyRate) {
                $lowestOccupancyRate = $rate;
                $lowestOccupancyLab = $lab;
            }
        }

        // Generate recommendations dynamically based on stats
        if (!empty($labTraffic)) {
            $busiestLab = $labTraffic[0]['lab_number'];
            $busiestCount = $labTraffic[0]['count'];
            $totalRecordsStmt = $pdo->query("SELECT COUNT(*) as count FROM sitin_records");
            $totalRecords = max(1, intval($totalRecordsStmt->fetch()['count']));
            $busiestPercentage = round(($busiestCount / $totalRecords) * 100, 1);

            $recommendations[] = [
                'type' => 'occupancy',
                'title' => 'Lab Traffic Redistribution',
                'description' => "Lab $busiestLab represents $busiestPercentage% of total logged check-ins ($busiestCount logs), causing higher wear and queue times. We recommend shifting general sit-in students (non-specialized labs) to underutilized rooms such as Lab " . ($lowestOccupancyLab ?: '526') . ".",
                'impact' => 'Medium Impact',
                'action_label' => 'View Lab Utilization'
            ];
        } else {
            $recommendations[] = [
                'type' => 'occupancy',
                'title' => 'Lab Utilization Optimization',
                'description' => 'System logs show uniform traffic. Recommend keeping current lab layouts and advising students to sit in Lab 526 and 524 during peak noon hours to prevent local overcrowding.',
                'impact' => 'Low Impact',
                'action_label' => 'View Utilization'
            ];
        }

        // Software Gap Analysis
        if (!empty($popularPurposes)) {
            $topPurpose = strtolower($popularPurposes[0]['purpose']);
            $topPurposeCount = $popularPurposes[0]['count'];
            
            if (strpos($topPurpose, 'python') !== false) {
                // Python gap analysis
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM lab_software WHERE software_name LIKE '%Python%'");
                $stmt->execute();
                $pythonCount = intval($stmt->fetch()['count']);
                if ($pythonCount < 3) {
                    $recommendations[] = [
                        'type' => 'software',
                        'title' => 'Deploy Python Development Packages',
                        'description' => "Python programming is currently a top purpose ($topPurposeCount check-ins). However, Python is only deployed in select labs. We recommend deploying Python 3.10+ and VS Code extensions to Lab 526 and Lab 530.",
                        'impact' => 'High Impact',
                        'action_label' => 'Deploy Software'
                    ];
                }
            } else if (strpos($topPurpose, 'c#') !== false || strpos($topPurpose, 'net') !== false || strpos($topPurpose, 'visual') !== false) {
                // C# / Visual Studio gap analysis
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM lab_software WHERE software_name LIKE '%Visual Studio%' OR software_name LIKE '%C#%'");
                $stmt->execute();
                $csCount = intval($stmt->fetch()['count']);
                if ($csCount < 3) {
                    $recommendations[] = [
                        'type' => 'software',
                        'title' => 'Expand Visual Studio Deploys',
                        'description' => "C# / ASP.NET development is a key student focus. Visual Studio is only fully configured in Lab 530 and MAC Lab. Recommend provisioning Visual Studio Community to Lab 524 for Web development overflow.",
                        'impact' => 'High Impact',
                        'action_label' => 'View Catalog'
                    ];
                }
            }
        }

        // If we didn't add a software one, add a general recommendation
        if (count($recommendations) < 2) {
            $recommendations[] = [
                'type' => 'software',
                'title' => 'Pre-emptive Software Audit',
                'description' => 'A large number of students (over 30% this month) sit-in for undefined self-study. We advise publishing a department-wide software catalog update and installing Visual Studio Code across all 5 laboratory rooms.',
                'impact' => 'Medium Impact',
                'action_label' => 'View Catalog'
            ];
        }

        // 3. Peak Hour / Scheduling Recommendation
        // Query to find busiest hour
        $stmt = $pdo->query("
            SELECT strftime('%H', time_in) as hour, COUNT(*) as count 
            FROM sitin_records 
            GROUP BY hour 
            ORDER BY count DESC 
            LIMIT 1
        ");
        $peakHourRow = $stmt->fetch();
        if ($peakHourRow) {
            $peakHour = intval($peakHourRow['hour']);
            $formattedHour = ($peakHour % 12 ?: 12) . ':00 ' . ($peakHour >= 12 ? 'PM' : 'AM');
            $recommendations[] = [
                'type' => 'schedule',
                'title' => 'Peak Hours Congestion Relief',
                'description' => "Data analysis identifies peak laboratory bottleneck at $formattedHour. Recommend launching an automated dashboard advisory notifying students to book reservations between 8:00 AM - 11:00 AM or after 4:00 PM to bypass peak hours.",
                'impact' => 'Medium Impact',
                'action_label' => 'Post Advisory'
            ];
        } else {
            $recommendations[] = [
                'type' => 'schedule',
                'title' => 'Shift Scheduling Recommendations',
                'description' => 'Busiest times are usually early afternoon. Encourage students to check lab capacity in real-time on their student dashboard before checking in to reduce front desk delays.',
                'impact' => 'Low Impact',
                'action_label' => 'View Schedule'
            ];
        }

        echo json_encode(['success' => true, 'recommendations' => $recommendations]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function downloadReport() {
    global $pdo;
    
    // Auth Check inside function as well just to be extremely secure
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['token'])) {
        die("Not authenticated");
    }
    
    $type = $_GET['type'] ?? 'daily';
    $date = $_GET['date'] ?? date('Y-m-d');
    $week = $_GET['week'] ?? date('Y-\WW');
    $month = $_GET['month'] ?? date('Y-m');
    $year = $_GET['year'] ?? date('Y');
    
    try {
        $query = "
            SELECT r.id, u.id_number, u.firstname, u.lastname, u.course, u.level, 
                   r.lab_number, r.pc_number, r.time_in, r.time_out, r.purpose
            FROM sitin_records r
            JOIN users u ON r.user_id = u.id
            WHERE 1=1
        ";
        $params = [];
        
        if ($type === 'daily' && !empty($date)) {
            $query .= " AND date(r.time_in) = ?";
            $params[] = $date;
            $filename = "CCS_Daily_Report_" . $date;
        } elseif ($type === 'weekly') {
            $query .= " AND r.time_in >= datetime('now', '-7 days')";
            $filename = "CCS_Weekly_Report_" . date('Ymd');
        } elseif ($type === 'monthly' && !empty($month)) {
            $query .= " AND strftime('%Y-%m', r.time_in) = ?";
            $params[] = $month;
            $filename = "CCS_Monthly_Report_" . $month;
        } elseif ($type === 'yearly' && !empty($year)) {
            $query .= " AND strftime('%Y', r.time_in) = ?";
            $params[] = $year;
            $filename = "CCS_Yearly_Report_" . $year;
        } else {
            $query .= " AND date(r.time_in) = date('now')";
            $filename = "CCS_SitIn_Report_" . date('Ymd');
        }
        
        $query .= " ORDER BY r.time_in DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $records = $stmt->fetchAll();
        
        // Clean headers and clear output buffer to ensure pure CSV stream
        if (ob_get_level()) ob_end_clean();
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // Output CSV Headers
        fputcsv($output, [
            'Record ID', 
            'Student ID', 
            'Last Name', 
            'First Name', 
            'Course & Year', 
            'Laboratory', 
            'PC Number', 
            'Time In', 
            'Time Out', 
            'Duration (Hours)', 
            'Purpose'
        ]);
        
        // Output CSV Data Rows
        foreach ($records as $row) {
            $duration = 'Ongoing';
            if ($row['time_out']) {
                $diff = strtotime($row['time_out']) - strtotime($row['time_in']);
                $duration = round($diff / 3600, 2);
            }
            
            fputcsv($output, [
                $row['id'],
                $row['id_number'],
                $row['lastname'],
                $row['firstname'],
                $row['course'] . ' - ' . $row['level'],
                'Lab ' . $row['lab_number'],
                $row['pc_number'] ?: 'N/A',
                $row['time_in'],
                $row['time_out'] ?: 'Ongoing',
                $duration,
                ucfirst($row['purpose'] ?: 'N/A')
            ]);
        }
        
        fclose($output);
        exit;
    } catch (PDOException $e) {
        die("Export failed: " . $e->getMessage());
    }
}

function importSoftware() {
    global $pdo;
    
    if (!isset($_FILES['software_csv']) || $_FILES['software_csv']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded or file upload error.']);
        return;
    }
    
    $fileTmpPath = $_FILES['software_csv']['tmp_path'] ?? $_FILES['software_csv']['tmp_name'];
    $fileName = $_FILES['software_csv']['name'];
    
    $importedCount = 0;
    $errors = [];
    
    try {
        if (($handle = fopen($fileTmpPath, 'r')) !== FALSE) {
            // Read headers
            $headers = fgetcsv($handle, 1000, ',');
            
            // Expected columns: lab_number, software_name, version, status
            // Trim headers to be safe
            $headers = array_map('trim', $headers);
            
            // Map headers to indices
            $labIdx = array_search('lab_number', $headers);
            $nameIdx = array_search('software_name', $headers);
            $verIdx = array_search('version', $headers);
            $statIdx = array_search('status', $headers);
            
            // If headers are missing, fall back to index-based mapping (0=lab, 1=name, 2=version, 3=status)
            if ($labIdx === false || $nameIdx === false) {
                $labIdx = 0;
                $nameIdx = 1;
                $verIdx = 2;
                $statIdx = 3;
            }
            
            $pdo->beginTransaction();
            
            $rowNum = 1;
            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                $rowNum++;
                $lab = trim($data[$labIdx] ?? '');
                $name = trim($data[$nameIdx] ?? '');
                $version = trim($data[$verIdx] ?? '1.0');
                $status = trim($data[$statIdx] ?? 'available');
                
                if (empty($lab) || empty($name)) {
                    $errors[] = "Row $rowNum skipped: lab_number and software_name are required.";
                    continue;
                }
                
                // Clean up lab number string (e.g. if student inputs "Lab 524", trim it to "524")
                $lab = str_ireplace('Lab ', '', $lab);
                
                // Check if already exists in lab_software
                $checkStmt = $pdo->prepare("SELECT id FROM lab_software WHERE lab_number = ? AND software_name = ?");
                $checkStmt->execute([$lab, $name]);
                $existing = $checkStmt->fetch();
                
                if ($existing) {
                    // Update
                    $updateStmt = $pdo->prepare("UPDATE lab_software SET version = ?, status = ? WHERE id = ?");
                    $updateStmt->execute([$version, $status, $existing['id']]);
                } else {
                    // Insert new software
                    $insertStmt = $pdo->prepare("INSERT INTO lab_software (lab_number, software_name, version, status) VALUES (?, ?, ?, ?)");
                    $insertStmt->execute([$lab, $name, $version, $status]);
                }
                $importedCount++;
            }
            
            fclose($handle);
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => "Successfully imported $importedCount software applications.",
                'errors' => $errors
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to read uploaded file.']);
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Import error: ' . $e->getMessage()]);
    }
}

function getSoftware() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT * FROM lab_software ORDER BY lab_number, software_name");
        $software = $stmt->fetchAll();
        echo json_encode(['success' => true, 'software' => $software]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function addSoftware() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    
    $lab = trim($input['lab_number'] ?? '');
    $name = trim($input['software_name'] ?? '');
    $version = trim($input['version'] ?? '1.0');
    $status = trim($input['status'] ?? 'available');
    
    if (empty($lab) || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Lab and Name are required.']);
        return;
    }
    
    $lab = str_ireplace('Lab ', '', $lab);
    
    try {
        // Insert
        $stmt = $pdo->prepare("INSERT INTO lab_software (lab_number, software_name, version, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$lab, $name, $version, $status]);
        echo json_encode(['success' => true, 'message' => 'Software added successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteSoftware() {
    global $pdo;
    $id = $_GET['id'] ?? '';
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID is required.']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM lab_software WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Software deleted successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getPcs() {
    global $pdo;
    $lab = $_GET['lab'] ?? '';
    try {
        if ($lab && $lab !== 'all') {
            $stmt = $pdo->prepare("SELECT id, lab_number, pc_number, status FROM lab_pc_status WHERE lab_number = ? ORDER BY pc_number ASC");
            $stmt->execute([$lab]);
        } else {
            $stmt = $pdo->query("SELECT id, lab_number, pc_number, status FROM lab_pc_status ORDER BY lab_number ASC, pc_number ASC");
        }
        $pcs = $stmt->fetchAll();
        echo json_encode(['success' => true, 'pcs' => $pcs]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updatePcStatus() {
    global $pdo;
    $input = json_decode(file_get_contents('php://input'), true);
    $pcId = $input['id'] ?? '';
    $status = $input['status'] ?? '';
    
    if (empty($pcId) || empty($status)) {
        echo json_encode(['success' => false, 'message' => 'PC ID and status are required']);
        return;
    }
    
    try {
        if ($status === 'maintenance') {
            // Get lab and pc number
            $stmt = $pdo->prepare("SELECT lab_number, pc_number FROM lab_pc_status WHERE id = ?");
            $stmt->execute([$pcId]);
            $pcInfo = $stmt->fetch();
            
            if ($pcInfo) {
                // Find active sit-in
                $sitinStmt = $pdo->prepare("SELECT id, user_id FROM sitin_records WHERE lab_number = ? AND pc_number = ? AND time_out IS NULL");
                $sitinStmt->execute([$pcInfo['lab_number'], $pcInfo['pc_number']]);
                $activeSitin = $sitinStmt->fetch();
                
                if ($activeSitin) {
                    // End the sit-in
                    $endStmt = $pdo->prepare("UPDATE sitin_records SET time_out = datetime('now') WHERE id = ?");
                    $endStmt->execute([$activeSitin['id']]);
                    
                    // Add notification
                    $msg = "Your sit-in on Lab {$pcInfo['lab_number']} PC {$pcInfo['pc_number']} was forced to end. Reason: admin ended, disabled pc";
                    $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, 'Sit-in Forced to End', ?, 'system', 0, datetime('now'))");
                    $notifStmt->execute([$activeSitin['user_id'], $msg]);
                }
            }
        }

        $stmt = $pdo->prepare("UPDATE lab_pc_status SET status = ? WHERE id = ?");
        $stmt->execute([$status, $pcId]);
        echo json_encode(['success' => true, 'message' => 'PC status updated successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getPcDetails() {
    global $pdo;
    $lab = $_GET['lab'] ?? '';
    $pc = $_GET['pc'] ?? '';
    
    if (empty($lab) || empty($pc)) {
        echo json_encode(['success' => false, 'message' => 'Lab and PC number are required']);
        return;
    }
    
    try {
        $details = [];
        
        // 1. Get current active sit-in if occupied
        $stmt = $pdo->prepare("
            SELECT u.id_number, u.firstname, u.lastname, s.time_in, s.purpose 
            FROM sitin_records s 
            JOIN users u ON s.user_id = u.id 
            WHERE s.lab_number = ? AND s.pc_number = ? AND s.time_out IS NULL 
            LIMIT 1
        ");
        $stmt->execute([$lab, $pc]);
        $activeSession = $stmt->fetch();
        if ($activeSession) {
            $details['active_session'] = [
                'id_number' => $activeSession['id_number'],
                'name' => $activeSession['firstname'] . ' ' . $activeSession['lastname'],
                'time_in' => $activeSession['time_in'],
                'purpose' => $activeSession['purpose']
            ];
        } else {
            $details['active_session'] = null;
        }
        
        // 2. Get today's upcoming/active reservations
        $stmt = $pdo->prepare("
            SELECT u.id_number, u.firstname, u.lastname, r.start_time, r.end_time, r.status 
            FROM reservations r 
            JOIN users u ON r.user_id = u.id 
            WHERE r.lab_number = ? AND r.pc_number = ? AND DATE(r.reservation_date) = DATE('now')
            ORDER BY r.start_time ASC
        ");
        $stmt->execute([$lab, $pc]);
        $reservations = $stmt->fetchAll();
        
        $details['reservations'] = [];
        foreach ($reservations as $res) {
            $details['reservations'][] = [
                'id_number' => $res['id_number'],
                'name' => $res['firstname'] . ' ' . $res['lastname'],
                'start_time' => $res['start_time'],
                'end_time' => $res['end_time'],
                'status' => $res['status']
            ];
        }
        
        echo json_encode(['success' => true, 'details' => $details]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
