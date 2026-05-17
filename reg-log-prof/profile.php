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
            $stmt = $pdo->prepare("SELECT id_number, lastname, firstname, middlename, course, level, email, address, profile_pic FROM users WHERE id = ?");
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
                        'address' => $user['address'],
                        'profile_pic' => $user['profile_pic']
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'User not found']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Check for specific action first
        $action = $_GET['action'] ?? '';
        if ($action === 'upload_pic') {
            if (isset($_FILES['profile_pic'])) {
                $file = $_FILES['profile_pic'];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    echo json_encode(['success' => false, 'message' => 'File upload error']);
                    exit;
                }
                
                // Check file type
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($file['type'], $allowedTypes)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.']);
                    exit;
                }
                
                // Generate filename using the user's ID
                $stmt = $pdo->prepare("SELECT id_number FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $userRow = $stmt->fetch();
                if (!$userRow) {
                    echo json_encode(['success' => false, 'message' => 'User not found']);
                    exit;
                }
                
                $idNumber = $userRow['id_number'];
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                if (empty($ext)) {
                    $ext = 'png';
                }
                
                $targetDir = "../uploads/profile_pics/";
                if (!file_exists($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }
                
                // Use unique timestamp to prevent caching issues in browser
                $fileName = $idNumber . "_" . time() . "." . $ext;
                $targetFile = $targetDir . $fileName;
                
                // Remove old profile pictures for this user to save space
                $existingFiles = glob($targetDir . $idNumber . "_*");
                if ($existingFiles) {
                    foreach ($existingFiles as $exFile) {
                        @unlink($exFile);
                    }
                }
                
                if (move_uploaded_file($file['tmp_name'], $targetFile)) {
                    // Update database
                    $dbPath = "uploads/profile_pics/" . $fileName;
                    $updateStmt = $pdo->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                    $updateStmt->execute([$dbPath, $_SESSION['user_id']]);
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Profile picture uploaded successfully',
                        'profile_pic' => $dbPath
                    ]);
                    exit;
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
                    exit;
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'No file uploaded']);
                exit;
            }
        }

        // Update user profile (standard form update)
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
