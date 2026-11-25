<?php
/**
 * CampusFix - Database Reset Utility
 * 
 * Clears all sample complaints, feedback, history, and test students,
 * leaving only the clean default Administrator account.
 * 
 * Run from CLI: php database/reset_database.php
 * Or from browser: http://localhost:8000/database/reset_database.php
 */

require_once __DIR__ . '/../config/database.php';

$isCli = (php_sapi_name() === 'cli');
$db = getDB();

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><title>Reset Database</title>";
    echo "<style>body{font-family:sans-serif;padding:30px;background:#f8fafc;max-width:600px;margin:40px auto;}.card{background:#fff;padding:24px;border-radius:8px;border:1px solid #cbd5e1;box-shadow:0 2px 4px rgba(0,0,0,0.05);}h2{color:#1e3a8a;margin-top:0;}.btn{display:inline-block;padding:10px 18px;background:#2563eb;color:#fff;text-decoration:none;border-radius:5px;font-weight:bold;margin-top:15px;}</style></head><body><div class='card'>";
}

try {
    // 1. Delete all complaints, history, feedback, contact messages
    $db->exec("DELETE FROM feedback;");
    $db->exec("DELETE FROM complaint_history;");
    $db->exec("DELETE FROM complaints;");
    $db->exec("DELETE FROM contact_messages;");

    // 2. Delete test students, keep only the Admin
    $db->exec("DELETE FROM users WHERE role != 'Admin';");

    // Reset auto-increment counters
    $db->exec("UPDATE sqlite_sequence SET seq = 1 WHERE name = 'users';");
    $db->exec("DELETE FROM sqlite_sequence WHERE name IN ('complaints', 'complaint_history', 'feedback', 'contact_messages');");

    // Ensure Admin account exists
    $checkAdmin = $db->query("SELECT id FROM users WHERE role = 'Admin'")->fetch();
    if (!$checkAdmin) {
        $hashed = password_hash('AdminPassword123!', PASSWORD_DEFAULT);
        $db->prepare("
            INSERT INTO users (name, email, student_id, password, role)
            VALUES ('Campus Administrator', 'admin@campusfix.edu', 'ADMIN01', ?, 'Admin')
        ")->execute([$hashed]);
    }

    if ($isCli) {
        echo "[SUCCESS] Database has been reset to a completely clean state!\n";
        echo "All sample complaints, test history, and demo students removed.\n";
        echo "Administrator account preserved: admin@campusfix.edu\n";
    } else {
        echo "<h2>Database Reset Successfully!</h2>";
        echo "<p style='color: #16a34a; font-weight: bold;'>All demo complaints, test students, and histories have been cleared.</p>";
        echo "<p>Your database is now in a 100% clean state, ready for your real testing.</p>";
        echo "<p><strong>Administrator Account:</strong><br>Email: <code>admin@campusfix.edu</code><br>Password: <code>AdminPassword123!</code></p>";
        echo "<a href='../home.html' class='btn'>Go to Home Page &rarr;</a>";
        echo "</div></body></html>";
    }

} catch (Exception $e) {
    if ($isCli) {
        echo "[ERROR] Failed to reset database: " . $e->getMessage() . "\n";
    } else {
        echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p></div></body></html>";
    }
}

