<?php
// Add Student API
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
$firstname = $_POST['firstname'] ?? '';
$lastname = $_POST['lastname'] ?? '';
$middlename = $_POST['middlename'] ?? '';
$course = $_POST['course'] ?? '';
$level = $_POST['level'] ?? '';
$email = $_POST['email'] ?? '';
$address = $_POST['address'] ?? '';
$password = $_POST['password'] ?? '';

if (!$id_number || !$firstname || !$lastname || !$course || !$level || !$password) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Check if ID number already exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE id_number = ?");
$stmt->execute([$id_number]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'ID Number already exists']);
    exit;
}

try {
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user
    $stmt = $pdo->prepare("
        INSERT INTO users (id_number, firstname, lastname, middlename, course, level, email, address, password)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$id_number, $firstname, $lastname, $middlename, $course, $level, $email, $address, $hashedPassword]);
    
    $userId = $pdo->lastInsertId();
    
    // Insert default sessions (30)
    $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, remaining_sessions) VALUES (?, 30)");
    $stmt->execute([$userId]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
