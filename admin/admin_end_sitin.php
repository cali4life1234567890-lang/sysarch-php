<?php
// End Sit-In Handler
require_once '../database/db.php';
startSession();

// Check if admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['token'])) {
    header('Location: ../index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id_number FROM users WHERE id = ? AND id_number = '2664388'");
$stmt->execute([$_SESSION['user_id']]);
if (!$stmt->fetch()) {
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recordId = $_POST['record_id'] ?? '';
    
    if (!empty($recordId)) {
        try {
            $stmt = $pdo->prepare("UPDATE sitin_records SET time_out = datetime('now') WHERE id = ? AND time_out IS NULL");
            $stmt->execute([$recordId]);
        } catch (PDOException $e) {
            // Ignore errors
        }
    }
}

header('Location: admin_sitin.php');
exit;
