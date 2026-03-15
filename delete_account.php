<?php
// Delete account handler
header('Content-Type: application/json');

require_once 'db.php';
startSession();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $idNumber = $input['id_number'] ?? '';
    
    try {
        // Delete user's sessions first
        $stmt = $pdo->prepare("DELETE FROM sessions WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        
        // Delete user's sit-in records
        $stmt = $pdo->prepare("DELETE FROM sitin_records WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        
        // Delete the user
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND id_number = ?");
        $stmt->execute([$_SESSION['user_id'], $idNumber]);
        
        // Destroy session
        session_unset();
        session_destroy();
        
        echo json_encode(['success' => true, 'message' => 'Account deleted successfully']);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
