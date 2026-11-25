<?php
/**
 * CampusFix - Database Configuration & Connection
 * 
 * Uses PHP Data Objects (PDO) with SQLite.
 * Automatically creates database directory, file, and tables if they do not exist.
 */

// Ensure timezone is set
date_default_timezone_set('Asia/Kolkata');

function getDB() {
    static $db = null;

    if ($db === null) {
        $dbDir = dirname(__DIR__) . '/database';
        $dbPath = $dbDir . '/campusfix.db';

        // Ensure database directory exists
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }

        try {
            $db = new PDO('sqlite:' . $dbPath);
            // Throw exceptions on SQL errors for reliable error handling
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // Return associative arrays by default
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            // Enable SQLite Foreign Key constraint enforcement
            $db->exec('PRAGMA foreign_keys = ON;');

            // Initialize database tables if not already created
            initializeDatabase($db);

        } catch (PDOException $e) {
            die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
        }
    }

    return $db;
}

/**
 * Creates required tables if they don't already exist.
 */
function initializeDatabase($db) {
    // 1. Users Table
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            student_id TEXT UNIQUE,
            password TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'Student',
            created_at DATETIME DEFAULT (datetime('now', 'localtime'))
        );
    ");

    // 2. Complaints Table
    $db->exec("
        CREATE TABLE IF NOT EXISTS complaints (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            complaint_id TEXT NOT NULL UNIQUE,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            category TEXT NOT NULL,
            location TEXT NOT NULL,
            description TEXT NOT NULL,
            image TEXT,
            priority TEXT NOT NULL DEFAULT 'Medium',
            status TEXT NOT NULL DEFAULT 'Submitted',
            assigned_to TEXT,
            created_at DATETIME DEFAULT (datetime('now', 'localtime')),
            updated_at DATETIME DEFAULT (datetime('now', 'localtime')),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ");

    // 3. Complaint History / Audit Trail Table
    $db->exec("
        CREATE TABLE IF NOT EXISTS complaint_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            complaint_id TEXT NOT NULL,
            old_status TEXT,
            new_status TEXT,
            remarks TEXT,
            updated_by TEXT,
            created_at DATETIME DEFAULT (datetime('now', 'localtime')),
            FOREIGN KEY (complaint_id) REFERENCES complaints(complaint_id) ON DELETE CASCADE
        );
    ");

    // 4. Feedback Table
    $db->exec("
        CREATE TABLE IF NOT EXISTS feedback (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            complaint_id TEXT NOT NULL UNIQUE,
            user_id INTEGER NOT NULL,
            rating INTEGER NOT NULL,
            message TEXT,
            created_at DATETIME DEFAULT (datetime('now', 'localtime')),
            FOREIGN KEY (complaint_id) REFERENCES complaints(complaint_id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ");

    // 5. Contact Messages Table
    $db->exec("
        CREATE TABLE IF NOT EXISTS contact_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            subject TEXT NOT NULL,
            message TEXT NOT NULL,
            created_at DATETIME DEFAULT (datetime('now', 'localtime'))
        );
    ");

    // Seed default admin if no admin exists
    $checkAdmin = $db->query("SELECT id FROM users WHERE role = 'Admin' LIMIT 1");
    if (!$checkAdmin->fetch()) {
        $adminPass = password_hash('AdminPassword123!', PASSWORD_DEFAULT);
        $insertAdmin = $db->prepare("
            INSERT INTO users (name, email, student_id, password, role)
            VALUES (?, ?, ?, ?, ?)
        ");
        $insertAdmin->execute([
            'Campus Administrator',
            'admin@campusfix.edu',
            'ADMIN01',
            $adminPass,
            'Admin'
        ]);
    }
}

