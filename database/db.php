<?php
// Database configuration using SQLite
// This creates a simple file-based database for the sit-in monitoring system

define('DB_PATH', __DIR__ . '/database.sqlite');

// Create database connection
try {
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Create tables if they don't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            id_number TEXT UNIQUE NOT NULL,
            lastname TEXT NOT NULL,
            firstname TEXT NOT NULL,
            middlename TEXT,
            course TEXT NOT NULL,
            level INTEGER NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            address TEXT,
            can_reserve INTEGER DEFAULT 1,
            profile_pic TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            session_token TEXT UNIQUE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sitin_records (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            lab_number TEXT NOT NULL,
            pc_number INTEGER,
            time_in DATETIME DEFAULT CURRENT_TIMESTAMP,
            time_out DATETIME,
            purpose TEXT,
            start_time TEXT,
            end_time TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");

    // Add pc_number column if it doesn't exist (for existing tables)
    try {
        $pdo->exec("ALTER TABLE sitin_records ADD COLUMN pc_number INTEGER");
    } catch (PDOException $e) {
        // Column might already exist, ignore
    }

    // Add start_time column if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE sitin_records ADD COLUMN start_time TEXT");
    } catch (PDOException $e) {
        // Column might already exist, ignore
    }

    // Add end_time column if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE sitin_records ADD COLUMN end_time TEXT");
    } catch (PDOException $e) {
        // Column might already exist, ignore
    }

    // Add can_reserve column to users if it doesn't exist (for existing databases)
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN can_reserve INTEGER DEFAULT 1");
    } catch (PDOException $e) {
        // Column might already exist, ignore
    }

    // Add profile_pic column to users if it doesn't exist (for existing databases)
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_pic TEXT");
    } catch (PDOException $e) {
        // Column might already exist, ignore
    }

    // Create user_sessions table for tracking remaining sit-in sessions
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER UNIQUE NOT NULL,
            remaining_sessions INTEGER DEFAULT 30,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");

    // Create feedback table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS feedback (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            sitin_record_id INTEGER,
            feedback_text TEXT NOT NULL,
            rating INTEGER DEFAULT 5,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (sitin_record_id) REFERENCES sitin_records(id)
        )
    ");

    // Create notifications table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            message TEXT,
            type TEXT DEFAULT 'info',
            is_read INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");

    // Create announcements table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS announcements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            message TEXT NOT NULL,
            date TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Create admin account if not exists
    $adminCheck = $pdo->query("SELECT id FROM users WHERE id_number = '2664388'");
    if (!$adminCheck->fetch()) {
        $hashedPassword = password_hash('admin123!', PASSWORD_DEFAULT);
        $pdo->exec("
            INSERT INTO users (id_number, lastname, firstname, middlename, course, level, email, password, address)
            VALUES ('2664388', 'Admin', 'System', 'Admin', 'BSIT', 4, 'admin@uc.edu', '$hashedPassword', 'Admin Office')
        ");
    }

    // Create lab_pc_status table for tracking PC availability
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lab_pc_status (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            lab_number TEXT NOT NULL,
            pc_number INTEGER NOT NULL,
            status TEXT DEFAULT 'available'
        )
    ");

    // Initialize PC status for each lab if not exists
    $labs = ['524', '526', '528', '530', 'MAC'];
    $totalPcsPerLab = 56;

    foreach ($labs as $lab) {
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM lab_pc_status WHERE lab_number = ?");
        $checkStmt->execute([$lab]);
        if ($checkStmt->fetchColumn() == 0) {
            for ($pc = 1; $pc <= $totalPcsPerLab; $pc++) {
                $insertPc = $pdo->prepare("INSERT INTO lab_pc_status (lab_number, pc_number, status) VALUES (?, ?, 'available')");
                $insertPc->execute([$lab, $pc]);
            }
        }
    }

    // Create reservations table if not exists
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

    // Create lab_software table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lab_software (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            lab_number TEXT NOT NULL,
            software_name TEXT NOT NULL,
            version TEXT,
            status TEXT DEFAULT 'available'
        )
    ");

    // Populate lab_software with default data if empty
    $checkSoftware = $pdo->query("SELECT COUNT(*) FROM lab_software");
    if ($checkSoftware->fetchColumn() == 0) {
        $softwareList = [
            // Lab 524 (Web & Systems Development)
            ['524', 'Visual Studio Code', '1.85.0', 'available'],
            ['524', 'Python', '3.10.11', 'available'],
            ['524', 'Git', '2.43.0', 'available'],
            ['524', 'Node.js', '20.10.0', 'available'],
            ['524', 'XAMPP (PHP, MySQL)', '8.2.12', 'available'],
            ['524', 'Google Chrome', '120.0.6', 'available'],

            // Lab 526 (Java & OOP Programming)
            ['526', 'Java JDK', '17.0.9', 'available'],
            ['526', 'NetBeans IDE', '20.0', 'available'],
            ['526', 'Eclipse IDE', '2023-09', 'available'],
            ['526', 'MySQL Workbench', '8.0.34', 'available'],
            ['526', 'IntelliJ IDEA Community', '2023.2.5', 'available'],

            // Lab 528 (Networking & Cisco)
            ['528', 'Cisco Packet Tracer', '8.2.1', 'available'],
            ['528', 'Wireshark', '4.2.0', 'available'],
            ['528', 'GNS3', '2.2.43', 'available'],
            ['528', 'PuTTY', '0.80', 'available'],
            ['528', 'VirtualBox', '7.0.12', 'available'],

            // Lab 530 (Mobile & Advanced Dev)
            ['530', 'Android Studio', '2023.1.1', 'available'],
            ['530', 'IntelliJ IDEA Ultimate', '2023.2.5', 'available'],
            ['530', 'Flutter SDK', '3.16.5', 'available'],
            ['530', 'Visual Studio Community', '2022', 'available'],
            ['530', 'Unity Hub & Editor', '2022.3.15', 'available'],

            // MAC Lab (iOS Dev & Design)
            ['MAC', 'Xcode', '15.1', 'available'],
            ['MAC', 'VS Code for Mac', '1.85.0', 'available'],
            ['MAC', 'Python', '3.11.5', 'available'],
            ['MAC', 'Adobe Photoshop', '2024', 'available'],
            ['MAC', 'Adobe Illustrator', '2024', 'available'],
            ['MAC', 'Figma (Desktop)', '116.15', 'available'],
        ];

        $insertStmt = $pdo->prepare("INSERT INTO lab_software (lab_number, software_name, version, status) VALUES (?, ?, ?, ?)");
        foreach ($softwareList as $sw) {
            $insertStmt->execute($sw);
        }
    }
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Session management
function startSession()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function generateToken()
{
    return bin2hex(random_bytes(32));
}

function cleanupExpiredSessions()
{
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM sessions WHERE expires_at < datetime('now')");
    $stmt->execute();
}

// Run cleanup on each page load
cleanupExpiredSessions();
