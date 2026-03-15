<?php
// Login handler
header('Content-Type: application/json');

require_once 'db.php';
startSession();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $idNumber = trim($input['id_number'] ?? '');
    $password = $input['password'] ?? '';
    
    // Validate input
    if (empty($idNumber) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'ID Number and Password are required']);
        exit;
    }
    
    try {
        // Find user by ID number
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id_number = ?");
        $stmt->execute([$idNumber]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Generate session token
            $token = generateToken();
            $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            // Store session
            $insertStmt = $pdo->prepare("INSERT INTO sessions (user_id, session_token, expires_at) VALUES (?, ?, ?)");
            $insertStmt->execute([$user['id'], $token, $expiresAt]);
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['id_number'] = $user['id_number'];
            $_SESSION['name'] = $user['firstname'] . ' ' . $user['lastname'];
            $_SESSION['token'] = $token;
            
            // Check if admin
            $isAdmin = ($user['id_number'] === '2664388');
            
            echo json_encode([
                'success' => true, 
                'message' => 'Login successful',
                'is_admin' => $isAdmin,
                'user' => [
                    'id_number' => $user['id_number'],
                    'name' => $user['firstname'] . ' ' . $user['lastname'],
                    'course' => $user['course'],
                    'level' => $user['level'],
                    'email' => $user['email'],
                    'address' => $user['address'],
                    'is_admin' => $isAdmin
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid ID Number or Password']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Login failed: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
