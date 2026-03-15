<?php
// Registration handler
header('Content-Type: application/json');

require_once 'db.php';
startSession();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $idNumber = trim($input['id_number'] ?? '');
    $lastname = trim($input['lastname'] ?? '');
    $firstname = trim($input['firstname'] ?? '');
    $middlename = trim($input['middlename'] ?? '');
    $course = trim($input['course'] ?? '');
    $level = intval($input['level'] ?? 0);
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';
    $address = trim($input['address'] ?? '');
    
    // Validate input
    $errors = [];
    
    if (empty($idNumber)) {
        $errors[] = 'ID Number is required';
    }
    if (empty($lastname)) {
        $errors[] = 'Last Name is required';
    }
    if (empty($firstname)) {
        $errors[] = 'First Name is required';
    }
    if (empty($course)) {
        $errors[] = 'Course is required';
    }
    if ($level < 1 || $level > 4) {
        $errors[] = 'Valid Course Level is required (1-4)';
    }
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match';
    }
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
        exit;
    }
    
    try {
        // Check if ID number already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id_number = ?");
        $stmt->execute([$idNumber]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'ID Number already registered']);
            exit;
        }
        
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Email already registered']);
            exit;
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        $insertStmt = $pdo->prepare("
            INSERT INTO users (id_number, lastname, firstname, middlename, course, level, email, password, address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insertStmt->execute([
            $idNumber,
            $lastname,
            $firstname,
            $middlename,
            $course,
            $level,
            $email,
            $hashedPassword,
            $address
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Registration successful! You can now login.']);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
