<?php
// Get Occupied PCs API
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

$lab = $_GET['lab'] ?? '';

if (empty($lab)) {
    echo json_encode(['success' => false, 'message' => 'Laboratory number is required']);
    exit;
}

try {
    // Ensure pc_number column exists in sitin_records
    try {
        $pdo->exec("ALTER TABLE sitin_records ADD COLUMN pc_number INTEGER");
    } catch (PDOException $e) {
        // Ignore if exists
    }

    // Query active check-ins with pc numbers, optionally filter by timeslot
$timeSlot = $_GET['time_slot'] ?? '';
if ($timeSlot) {
    $parts = explode('-', $timeSlot);
    $slotStart = trim($parts[0] ?? '');
    $slotEnd = trim($parts[1] ?? '');
    $stmt = $pdo->prepare("SELECT pc_number FROM sitin_records WHERE lab_number = ? AND time_out IS NULL AND pc_number IS NOT NULL AND ((start_time IS NULL AND end_time IS NULL) OR (start_time <= ? AND end_time >= ?))");
    $stmt->execute([$lab, $slotEnd, $slotStart]);
} else {
    $stmt = $pdo->prepare("SELECT pc_number FROM sitin_records WHERE lab_number = ? AND time_out IS NULL AND pc_number IS NOT NULL");
    $stmt->execute([$lab]);
}
$occupied = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'occupied' => array_map('intval', $occupied)
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
