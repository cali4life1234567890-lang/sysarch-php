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
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
    
    // Add pc_number column if it doesn't exist (for existing tables)
    try {
        $pdo->exec("ALTER TABLE sitin_records ADD COLUMN pc_number INTEGER");
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
    
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Session management
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function generateToken() {
    return bin2hex(random_bytes(32));
}

function cleanupExpiredSessions() {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM sessions WHERE expires_at < datetime('now')");
    $stmt->execute();
}

// Run cleanup on each page load
cleanupExpiredSessions();
