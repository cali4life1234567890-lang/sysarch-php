<?php
// Start Sit-In API
require_once '../database/db.php';
startSession();

// Check if admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['token'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$stmt = $pdo->prepare("SELECT id_number FROM users WHERE id = ? AND id_number = '2664388'");
$stmt->execute([$_SESSION['user_id']]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get POST data
$id_number = $_POST['id_number'] ?? '';
$purpose = $_POST['purpose'] ?? '';
$lab = $_POST['lab'] ?? '';

if (!$id_number || !$purpose || !$lab) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    // Get user ID from id_number
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id_number = ?");
    $stmt->execute([$id_number]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }
    
    $userId = $user['id'];
    
    // Get remaining sessions
    $stmt = $pdo->prepare("SELECT remaining_sessions FROM user_sessions WHERE user_id = ?");
    $stmt->execute([$userId]);
    $session = $stmt->fetch();
    
    $remainingSessions = $session ? $session['remaining_sessions'] : 30;
    
    if ($remainingSessions <= 0) {
        echo json_encode(['success' => false, 'message' => 'No remaining sessions']);
        exit;
    }
    
    // Insert sit-in record
    $stmt = $pdo->prepare("INSERT INTO sitin_records (user_id, lab_number, purpose, time_in) VALUES (?, ?, ?, datetime('now'))");
    $stmt->execute([$userId, $lab, $purpose]);
    
    // Decrement remaining sessions
    $newSessions = $remainingSessions - 1;
    
    if ($session) {
        $stmt = $pdo->prepare("UPDATE user_sessions SET remaining_sessions = ? WHERE user_id = ?");
        $stmt->execute([$newSessions, $userId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, remaining_sessions) VALUES (?, ?)");
        $stmt->execute([$userId, $newSessions]);
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
