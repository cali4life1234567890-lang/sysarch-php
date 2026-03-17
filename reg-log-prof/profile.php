<?php
// Profile handler
header('Content-Type: application/json');

require_once '../database/db.php';
startSession();

if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get user profile
        try {
            $stmt = $pdo->prepare("SELECT id_number, lastname, firstname, middlename, course, level, email, address FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if ($user) {
                echo json_encode([
                    'success' => true,
                    'user' => [
                        'id_number' => $user['id_number'],
                        'name' => $user['firstname'] . ' ' . ($user['middlename'] ? $user['middlename'] . ' ' : '') . $user['lastname'],
                        'firstname' => $user['firstname'],
                        'lastname' => $user['lastname'],
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
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Update user profile
        $input = json_decode(file_get_contents('php://input'), true);
        
        $firstname = trim($input['firstname'] ?? '');
        $lastname = trim($input['lastname'] ?? '');
        $middlename = trim($input['middlename'] ?? '');
        $email = trim($input['email'] ?? '');
        $address = trim($input['address'] ?? '');
        
        // Validate
        if (empty($firstname) || empty($lastname) || empty($email)) {
            echo json_encode(['success' => false, 'message' => 'First name, last name, and email are required']);
            exit;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email format']);
            exit;
        }
        
        try {
            // Check if email is already taken by another user
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Email already in use']);
                exit;
            }
            
            // Update user
            $updateStmt = $pdo->prepare("
                UPDATE users 
                SET firstname = ?, lastname = ?, middlename = ?, email = ?, address = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$firstname, $lastname, $middlename, $email, $address, $_SESSION['user_id']]);
            
            // Update session name
            $_SESSION['name'] = $firstname . ' ' . $lastname;
            
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Update failed: ' . $e->getMessage()]);
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
