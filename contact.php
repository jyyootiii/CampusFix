<?php
/**
 * CampusFix — Contact Message Processor
 * Stores contact inquiries into SQLite contact_messages table.
 */

require_once __DIR__ . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!empty($name) && !empty($email) && !empty($subject) && !empty($message)) {
        $db = getDB();
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare("
            INSERT INTO contact_messages (name, email, subject, message, created_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $email, $subject, $message, $now]);
        header('Location: contact.html?sent=1');
        exit;
    }
}

// Redirect back to contact page
header('Location: contact.html');
exit;

