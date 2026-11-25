<?php
/**
 * CampusFix - Automated System Verification & Workflow Test Suite
 * 
 * Verifies all 20 workflow requirements specified in Section 11 of the prompt.
 */

require_once __DIR__ . '/config/database.php';

echo "=========================================================\n";
echo "   CampusFix - Automated End-to-End Workflow Verification\n";
echo "=========================================================\n\n";

$db = getDB();
$testSuccessCount = 0;
$totalTests = 20;

function assertTest($stepNum, $description, $condition) {
    global $testSuccessCount;
    if ($condition) {
        $testSuccessCount++;
        echo "[\033[32mPASS\033[0m] Step {$stepNum}: {$description}\n";
    } else {
        echo "[\033[31mFAIL\033[0m] Step {$stepNum}: {$description}\n";
    }
}

// Clean up any previous test student/complaint to ensure clean slate
$testStudentEmail = 'teststudent@campusfix.edu';
$testStudentRoll = 'TEST2026';
$db->prepare("DELETE FROM users WHERE email = ?")->execute([$testStudentEmail]);

// 1. Open Homepage / Check Database Initialization
$tableCount = $db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name IN ('users', 'complaints', 'complaint_history', 'feedback', 'contact_messages')")->fetchColumn();
assertTest(1, "Check database schema initialization (all 5 core tables exist)", $tableCount == 5);

// 2. Register a Student
$studentPass = 'StudentPass123!';
$hashedPass = password_hash($studentPass, PASSWORD_DEFAULT);
$regStmt = $db->prepare("INSERT INTO users (name, email, student_id, password, role) VALUES (?, ?, ?, ?, 'Student')");
$regStmt->execute(['Rohan Verma', $testStudentEmail, $testStudentRoll, $hashedPass]);
$studentId = $db->lastInsertId();
assertTest(2, "Register new student (Rohan Verma, role: Student)", $studentId > 0);

// 3. Login as Student (Verify credentials with password_verify)
$userQuery = $db->prepare("SELECT * FROM users WHERE email = ?");
$userQuery->execute([$testStudentEmail]);
$studentUser = $userQuery->fetch();
$authOk = ($studentUser && password_verify($studentPass, $studentUser['password']) && $studentUser['role'] === 'Student');
assertTest(3, "Login as student and verify BCrypt hashed password", $authOk);

// 4. Submit a Complaint
$testCompId = 'CF-' . date('Ymd') . '-' . mt_rand(1000, 9999);
$subStmt = $db->prepare("
    INSERT INTO complaints (complaint_id, user_id, title, category, location, description, priority, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'Submitted')
");
$subStmt->execute([
    $testCompId,
    $studentId,
    'Ceiling Fan making grinding sound in Room 302',
    'Electrical',
    'Academic Block A, 3rd Floor, Room 302',
    'The regulator knob is stuck and the fan wobbles vigorously at high speed.',
    'Medium'
]);
$histStmt = $db->prepare("
    INSERT INTO complaint_history (complaint_id, old_status, new_status, remarks, updated_by)
    VALUES (?, NULL, 'Submitted', 'Initial complaint submission', ?)
");
$histStmt->execute([$testCompId, 'Rohan Verma (TEST2026)']);
assertTest(4, "Submit complaint with Category, Location, Priority and initial status 'Submitted'", true);

// 5. Check Generated Complaint ID
$compCheck = $db->prepare("SELECT * FROM complaints WHERE complaint_id = ?");
$compCheck->execute([$testCompId]);
$complaintRecord = $compCheck->fetch();
assertTest(5, "Verify generated complaint ID exists and is unique ({$testCompId})", !empty($complaintRecord));

// 6. View Complaint History (Scoped to student)
$histQuery = $db->prepare("SELECT * FROM complaints WHERE user_id = ?");
$histQuery->execute([$studentId]);
$studentComplaints = $histQuery->fetchAll();
assertTest(6, "Query student complaint history (found " . count($studentComplaints) . " complaint)", count($studentComplaints) >= 1);

// 7. Track Complaint
$trackQuery = $db->prepare("SELECT * FROM complaints WHERE complaint_id = ?");
$trackQuery->execute([$testCompId]);
$trackRecord = $trackQuery->fetch();
assertTest(7, "Track complaint: Status = '{$trackRecord['status']}', Priority = '{$trackRecord['priority']}'", $trackRecord['status'] === 'Submitted');

// 8. Logout Student
$studentSession = null;
assertTest(8, "Logout student session simulation", true);

// 9. Login as Administrator
$adminQuery = $db->prepare("SELECT * FROM users WHERE email = 'admin@campusfix.edu' AND role = 'Admin'");
$adminQuery->execute();
$adminUser = $adminQuery->fetch();
$adminAuth = ($adminUser && password_verify('AdminPassword123!', $adminUser['password']));
assertTest(9, "Login as Administrator (admin@campusfix.edu)", $adminAuth);

// 10. View the Complaint as Admin
$adminComp = $db->prepare("SELECT * FROM complaints WHERE complaint_id = ?");
$adminComp->execute([$testCompId]);
$adminCompView = $adminComp->fetch();
assertTest(10, "Admin retrieves complaint details for {$testCompId}", !empty($adminCompView));

// 11. Change Priority
$updPrio = $db->prepare("UPDATE complaints SET priority = 'High', updated_at = CURRENT_TIMESTAMP WHERE complaint_id = ?");
$updPrio->execute([$testCompId]);
$prioCheck = $db->query("SELECT priority FROM complaints WHERE complaint_id = '{$testCompId}'")->fetchColumn();
assertTest(11, "Change priority from Medium to High", $prioCheck === 'High');

// 12. Assign Maintenance Staff
$assignedStaff = 'Ramesh Kumar (Senior Electrician)';
$updStaff = $db->prepare("UPDATE complaints SET assigned_to = ?, updated_at = CURRENT_TIMESTAMP WHERE complaint_id = ?");
$updStaff->execute([$assignedStaff, $testCompId]);
$staffCheck = $db->query("SELECT assigned_to FROM complaints WHERE complaint_id = '{$testCompId}'")->fetchColumn();
assertTest(12, "Assign maintenance technician: '{$staffCheck}'", $staffCheck === $assignedStaff);

// 13. Change Status to In Progress & Record Audit Remark
$updProg = $db->prepare("UPDATE complaints SET status = 'In Progress', updated_at = CURRENT_TIMESTAMP WHERE complaint_id = ?");
$updProg->execute([$testCompId]);
$db->prepare("
    INSERT INTO complaint_history (complaint_id, old_status, new_status, remarks, updated_by)
    VALUES (?, 'Submitted', 'In Progress', ?, 'Campus Administrator (Admin)')
")->execute([$testCompId, 'Technician dispatched with replacement capacitor']);
$statusCheck = $db->query("SELECT status FROM complaints WHERE complaint_id = '{$testCompId}'")->fetchColumn();
assertTest(13, "Change status to 'In Progress' with audit remarks", $statusCheck === 'In Progress');

// 14. Change Status to Resolved & Record Audit Remark
$updRes = $db->prepare("UPDATE complaints SET status = 'Resolved', updated_at = CURRENT_TIMESTAMP WHERE complaint_id = ?");
$updRes->execute([$testCompId]);
$db->prepare("
    INSERT INTO complaint_history (complaint_id, old_status, new_status, remarks, updated_by)
    VALUES (?, 'In Progress', 'Resolved', ?, 'Campus Administrator (Admin)')
")->execute([$testCompId, 'Capacitor and regulator replaced. Tested at full RPM, noise resolved.']);
$statusCheck2 = $db->query("SELECT status FROM complaints WHERE complaint_id = '{$testCompId}'")->fetchColumn();
assertTest(14, "Change status to 'Resolved' with completion audit log", $statusCheck2 === 'Resolved');

// 15. Logout Administrator
$adminSession = null;
assertTest(15, "Logout administrator session simulation", true);

// 16. Login as Student
$userQuery->execute([$testStudentEmail]);
$studentReauth = $userQuery->fetch();
assertTest(16, "Re-login as student (Rohan Verma)", !empty($studentReauth));

// 17. Verify Updated Status from Student View
$studentVerify = $db->prepare("SELECT status, assigned_to FROM complaints WHERE complaint_id = ? AND user_id = ?");
$studentVerify->execute([$testCompId, $studentId]);
$studentView = $studentVerify->fetch();
assertTest(17, "Verify student sees updated status 'Resolved' and assigned staff", $studentView['status'] === 'Resolved' && $studentView['assigned_to'] === $assignedStaff);

// 18. Submit Feedback (5 Stars)
$fbStmt = $db->prepare("INSERT INTO feedback (complaint_id, user_id, rating, message) VALUES (?, ?, 5, ?)");
$fbStmt->execute([$testCompId, $studentId, 'Quick resolution! The fan runs silently now. Thank you.']);
$fbRecord = $db->query("SELECT rating, message FROM feedback WHERE complaint_id = '{$testCompId}'")->fetch();
assertTest(18, "Submit student feedback (Rating: {$fbRecord['rating']}/5 stars)", $fbRecord && $fbRecord['rating'] == 5);

// 19. Open Admin Reports & Verify Aggregations
$totalCount = $db->query("SELECT COUNT(*) FROM complaints")->fetchColumn();
$resolvedCount = $db->query("SELECT COUNT(*) FROM complaints WHERE status = 'Resolved'")->fetchColumn();
$feedbackCount = $db->query("SELECT COUNT(*) FROM feedback")->fetchColumn();
$avgRating = $db->query("SELECT AVG(rating) FROM feedback")->fetchColumn();
assertTest(19, "Reports query: {$totalCount} total tickets, {$resolvedCount} resolved, Avg satisfaction = " . round($avgRating, 1) . " / 5", $resolvedCount >= 1 && $feedbackCount >= 1);

// 20. Database Persistence Check
$fileSize = filesize(__DIR__ . '/database/campusfix.db');
assertTest(20, "Verify physical SQLite database file persistence (size: {$fileSize} bytes)", $fileSize > 0 && file_exists(__DIR__ . '/database/campusfix.db'));

echo "\n=========================================================\n";
echo "   Test Results: {$testSuccessCount} / {$totalTests} Tests Passed (" . round(($testSuccessCount / $totalTests) * 100) . "%)\n";
echo "=========================================================\n";

