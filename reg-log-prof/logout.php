<?php
// Logout handler
require_once '../database/db.php';
startSession();

// Handle GET requests (direct links from admin pages)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Delete session from database if token exists
    if (isset($_SESSION['token'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM sessions WHERE session_token = ?");
            $stmt->execute([$_SESSION['token']]);
        } catch (PDOException $e) {
            // Ignore errors during cleanup
        }
    }
    
    // Destroy session
    session_unset();
    session_destroy();
    
    // Redirect to index
    header('Location: ../index.php');
    exit;
}

// Handle POST requests (AJAX from JavaScript)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Delete session from database if token exists
    if (isset($_SESSION['token'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM sessions WHERE session_token = ?");
            $stmt->execute([$_SESSION['token']]);
        } catch (PDOException $e) {
            // Ignore errors during cleanup
        }
    }
    
    // Destroy session
    session_unset();
    session_destroy();
    
    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
