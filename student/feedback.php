<?php
/**
 * CampusFix — Student Feedback
 * Allows students to rate resolved maintenance issues from 1 to 5 stars with comments.
 */

$pageTitle = 'Submit Feedback';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('Student');

$user = currentUser();
$db = getDB();

$complaintId = trim($_GET['id'] ?? '');
$error = '';

if (empty($complaintId)) {
    header('Location: complaint_history.php');
    exit;
}

// Fetch the complaint
$stmt = $db->prepare("SELECT * FROM complaints WHERE complaint_id = ? AND user_id = ?");
$stmt->execute([$complaintId, $user['id']]);
$complaint = $stmt->fetch();

if (!$complaint) {
    setFlash('error', 'Complaint not found or unauthorized.');
    header('Location: complaint_history.php');
    exit;
}

if ($complaint['status'] !== 'Resolved') {
    setFlash('error', 'Feedback can only be submitted after the issue is resolved.');
    header('Location: track_complaint.php?id=' . urlencode($complaintId));
    exit;
}

// Check for duplicate feedback
$check = $db->prepare("SELECT id FROM feedback WHERE complaint_id = ?");
$check->execute([$complaintId]);
if ($check->fetch()) {
    setFlash('info', 'Feedback already submitted for this complaint.');
    header('Location: track_complaint.php?id=' . urlencode($complaintId));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating  = intval($_POST['rating'] ?? 0);
    $message = trim($_POST['message'] ?? '');

    if ($rating < 1 || $rating > 5) {
        $error = 'Please select a rating between 1 and 5 stars.';
    } else {
        $now = date('Y-m-d H:i:s');
        $ins = $db->prepare("
            INSERT INTO feedback (complaint_id, user_id, rating, message, created_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        $ins->execute([$complaintId, $user['id'], $rating, $message, $now]);

        // Audit trail log
        $hist = $db->prepare("
            INSERT INTO complaint_history (complaint_id, old_status, new_status, remarks, updated_by, created_at)
            VALUES (?, 'Resolved', 'Resolved', ?, ?, ?)
        ");
        $hist->execute([
            $complaintId,
            'Student rated ' . $rating . '/5 stars',
            $user['name'] . ' (Student)',
            $now
        ]);

        setFlash('success', 'Thank you! Your feedback has been recorded.');
        header('Location: track_complaint.php?id=' . urlencode($complaintId));
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 700px; margin: 25px auto;">
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Maintenance Resolution Feedback</span>
        </div>

        <p style="font-size: 14px; color: #64748b; margin-bottom: 20px;">
            Your rating and comments help the Estate Office monitor technician response times and quality of service.
        </p>

        <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 16px 20px; border-radius: 4px; margin-bottom: 22px; font-size: 14px; line-height: 1.8;">
            <p><strong>Complaint Ticket:</strong> <?php echo htmlspecialchars($complaint['complaint_id']); ?></p>
            <p><strong>Title:</strong> <?php echo htmlspecialchars($complaint['title']); ?></p>
            <p><strong>Category:</strong> <?php echo htmlspecialchars($complaint['category']); ?> &bull; <strong>Location:</strong> <?php echo htmlspecialchars($complaint['location']); ?></p>
            <?php if (!empty($complaint['assigned_to'])): ?>
                <p><strong>Assigned Technician:</strong> <strong><?php echo htmlspecialchars($complaint['assigned_to']); ?></strong></p>
            <?php endif; ?>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="feedback.php?id=<?php echo urlencode($complaintId); ?>">
            <div class="form-group">
                <label style="font-size: 16px;">Overall Satisfaction Rating: <span style="color: #dc2626;">*</span></label>
                <div class="star-rating" style="margin: 10px 0 6px;">
                    <input type="radio" id="s5" name="rating" value="5" required><label for="s5" title="5 Stars - Excellent">&#9733;</label>
                    <input type="radio" id="s4" name="rating" value="4"><label for="s4" title="4 Stars - Good">&#9733;</label>
                    <input type="radio" id="s3" name="rating" value="3"><label for="s3" title="3 Stars - Average">&#9733;</label>
                    <input type="radio" id="s2" name="rating" value="2"><label for="s2" title="2 Stars - Poor">&#9733;</label>
                    <input type="radio" id="s1" name="rating" value="1"><label for="s1" title="1 Star - Unsatisfactory">&#9733;</label>
                </div>
                <p class="field-help">Click to choose a rating from 1 to 5 stars.</p>
            </div>

            <div class="form-group" style="margin-top: 22px;">
                <label for="message" style="font-size: 15px;">Comments / Suggestions (Optional):</label>
                <textarea id="message" name="message" rows="4" placeholder="Was the issue resolved completely? Did the staff arrive on time?"></textarea>
            </div>

            <div style="display: flex; gap: 14px; margin-top: 25px;">
                <button type="submit" class="btn btn-success" style="padding: 12px 28px; font-size: 15px;">Submit Feedback</button>
                <a href="track_complaint.php?id=<?php echo urlencode($complaintId); ?>" class="btn btn-secondary" style="padding: 12px 24px; font-size: 15px;">Back to Ticket</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
