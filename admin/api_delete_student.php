<?php
// Delete Student API
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
$ids = $_POST['ids'] ?? null;

if (!$id && !$ids) {
    echo json_encode(['success' => false, 'message' => 'Missing student ID(s)']);
    exit;
}

$idList = [];
if ($ids) {
    if (is_array($ids)) {
        $idList = $ids;
    } else {
        $idList = explode(',', $ids);
    }
} elseif ($id) {
    $idList = [$id];
}

$idList = array_filter(array_map('intval', $idList));

if (empty($idList)) {
    echo json_encode(['success' => false, 'message' => 'No valid student IDs provided']);
    exit;
}

try {
    $pdo->beginTransaction();

    $placeholders = implode(',', array_fill(0, count($idList), '?'));

    // Delete user sessions first
    $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id IN ($placeholders)");
    $stmt->execute($idList);

    // Delete sit-in records
    $stmt = $pdo->prepare("DELETE FROM sitin_records WHERE user_id IN ($placeholders)");
    $stmt->execute($idList);

    // Delete reservations
    $stmt = $pdo->prepare("DELETE FROM reservations WHERE user_id IN ($placeholders)");
    $stmt->execute($idList);

    // Delete notifications
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id IN ($placeholders)");
    $stmt->execute($idList);

    // Delete user
    $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders)");
    $stmt->execute($idList);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
