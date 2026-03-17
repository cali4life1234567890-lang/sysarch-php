<?php
// Search Student API - AJAX endpoint
require_once '../database/db.php';
startSession();

// Check if admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['token'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$stmt = $pdo->prepare("SELECT id_number FROM users WHERE id = ? AND id_number = '2664388'");
$stmt->execute([$_SESSION['user_id']]);
if (!$stmt->fetch()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get search query
$searchQuery = $_GET['q'] ?? '';

if (empty($searchQuery)) {
    echo json_encode(['error' => 'No search query provided']);
    exit;
}

try {
    // Search by ID number, firstname, or lastname
    $stmt = $pdo->prepare("
        SELECT u.id, u.id_number, u.firstname, u.lastname, u.middlename, u.course, u.level, u.email, u.address, u.created_at,
               COALESCE(us.remaining_sessions, 30) as remaining_sessions
        FROM users u 
        LEFT JOIN user_sessions us ON u.id = us.user_id
        WHERE u.id_number != '2664388' AND (
            u.id_number LIKE ? OR 
            u.firstname LIKE ? OR 
            u.lastname LIKE ?
        )
        LIMIT 10
    ");
    $searchTerm = "%$searchQuery%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $results = $stmt->fetchAll();
    
    if (count($results) > 0) {
        // Return the first exact match or the first result
        $student = $results[0];
        
        // Check for exact match on ID number or name
        $exactMatch = false;
        foreach ($results as $r) {
            if (strtolower($r['id_number']) === strtolower($searchQuery) || 
                strtolower($r['firstname'] . ' ' . $r['lastname']) === strtolower($searchQuery)) {
                $student = $r;
                $exactMatch = true;
                break;
            }
        }
        
        echo json_encode([
            'found' => true,
            'student' => [
                'id' => $student['id'],
                'id_number' => $student['id_number'],
                'firstname' => $student['firstname'],
                'lastname' => $student['lastname'],
                'middlename' => $student['middlename'],
                'course' => $student['course'],
                'level' => $student['level'],
                'email' => $student['email'],
                'address' => $student['address'],
                'remaining_sessions' => $student['remaining_sessions'],
                'created_at' => $student['created_at']
            ]
        ]);
    } else {
        echo json_encode(['found' => false]);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error']);
}
