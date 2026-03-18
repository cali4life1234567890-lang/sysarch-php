<?php
// Reset All Sessions API
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

try {
    // Get all non-admin users
    $stmt = $pdo->query("SELECT id FROM users WHERE id_number != '2664388'");
    $users = $stmt->fetchAll();
    
    foreach ($users as $user) {
        // Check if user has sessions record
        $checkStmt = $pdo->prepare("SELECT id FROM user_sessions WHERE user_id = ?");
        $checkStmt->execute([$user['id']]);
        
        if ($checkStmt->fetch()) {
            // Update existing record
            $updateStmt = $pdo->prepare("UPDATE user_sessions SET remaining_sessions = 30 WHERE user_id = ?");
            $updateStmt->execute([$user['id']]);
        } else {
            // Insert new record
            $insertStmt = $pdo->prepare("INSERT INTO user_sessions (user_id, remaining_sessions) VALUES (?, 30)");
            $insertStmt->execute([$user['id']]);
        }
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
