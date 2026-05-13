function makeReservation() {
     global $pdo;
     $input = json_decode(file_get_contents('php://input'), true);

     $lab = $input['lab'] ?? '';
     $date = $input['date'] ?? '';
     $startTime = $input['start_time'] ?? '';
     $endTime = $input['end_time'] ?? '';
     $purpose = $input['purpose'] ?? '';

     if (empty($lab) || empty($date) || empty($startTime) || empty($endTime)) {
         echo json_encode(['success' => false, 'message' => 'All fields are required']);
         return;
     }

     // Check if user is allowed to make reservations
     $stmt = $pdo->prepare("SELECT can_reserve FROM users WHERE id = ?");
     $stmt->execute([$_SESSION['user_id']]);
     $user = $stmt->fetch();
     if ($user && !$user['can_reserve']) {
         echo json_encode(['success' => false, 'message' => 'Reservation is disabled for your account. Please contact admin.']);
         return;
     }

     // Create reservations table if not exists with pc_number column
     $pdo->exec("
         CREATE TABLE IF NOT EXISTS reservations (
             id INTEGER PRIMARY KEY AUTOINCREMENT,
             user_id INTEGER NOT NULL,
             lab_number TEXT NOT NULL,
             pc_number INTEGER,
             reservation_date DATE NOT NULL,
             start_time TIME NOT NULL,
             end_time TIME NOT NULL,
             purpose TEXT,
             status TEXT DEFAULT 'pending',
             created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
             FOREIGN KEY (user_id) REFERENCES users(id)
         )
     ");

     // Add pc_number column if it doesn't exist
     try {
         $pdo->exec("ALTER TABLE reservations ADD COLUMN pc_number INTEGER");
     } catch (PDOException $e) {
         // Column might already exist, ignore
     }

     try {
         $stmt = $pdo->prepare("
             INSERT INTO reservations (user_id, lab_number, pc_number, reservation_date, start_time, end_time, purpose)
             VALUES (?, ?, ?, ?, ?, ?, ?)
         ");
         $stmt->execute([$_SESSION['user_id'], $lab, null, $date, $startTime, $endTime, $purpose]);

         echo json_encode(['success' => true, 'message' => 'Reservation submitted successfully']);
     } catch (PDOException $e) {
         echo json_encode(['success' => false, 'message' => $e->getMessage()]);
     }
 }