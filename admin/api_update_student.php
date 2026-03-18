<?php
// Update Student API
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
$id = $_POST['id'] ?? null;
$firstname = $_POST['firstname'] ?? '';
$lastname = $_POST['lastname'] ?? '';
$middlename = $_POST['middlename'] ?? '';
$course = $_POST['course'] ?? '';
$level = $_POST['level'] ?? '';
$email = $_POST['email'] ?? '';
$address = $_POST['address'] ?? '';
$sessions = $_POST['sessions'] ?? 30;

if (!$id || !$firstname || !$lastname || !$course || !$level) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    // Update user
    $stmt = $pdo->prepare("
        UPDATE users 
        SET firstname = ?, lastname = ?, middlename = ?, course = ?, level = ?, email = ?, address = ?
        WHERE id = ?
    ");
    $stmt->execute([$firstname, $lastname, $middlename, $course, $level, $email, $address, $id]);

    // Update or insert remaining sessions
    $stmt = $pdo->prepare("SELECT id FROM user_sessions WHERE user_id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("UPDATE user_sessions SET remaining_sessions = ? WHERE user_id = ?");
        $stmt->execute([$sessions, $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, remaining_sessions) VALUES (?, ?)");
        $stmt->execute([$id, $sessions]);
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
