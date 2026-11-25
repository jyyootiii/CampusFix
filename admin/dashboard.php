<?php
/**
 * CampusFix — Administrator Dashboard
 * Summary metrics and recent complaints for campus facilities management.
 */

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('Admin');

$user = currentUser();
$db = getDB();

$totalComplaints      = (int)$db->query("SELECT COUNT(*) FROM complaints")->fetchColumn();
$pendingComplaints    = (int)$db->query("SELECT COUNT(*) FROM complaints WHERE status = 'Submitted'")->fetchColumn();
$inProgressComplaints = (int)$db->query("SELECT COUNT(*) FROM complaints WHERE status = 'In Progress'")->fetchColumn();
$resolvedComplaints   = (int)$db->query("SELECT COUNT(*) FROM complaints WHERE status = 'Resolved'")->fetchColumn();

$totalStudents        = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'Student'")->fetchColumn();
$totalFeedback        = (int)$db->query("SELECT COUNT(*) FROM feedback")->fetchColumn();
$avgRating            = $db->query("SELECT AVG(rating) FROM feedback")->fetchColumn();
$avgRatingFormatted   = $avgRating ? number_format((float)$avgRating, 1) : 'N/A';

// Fetch Recent 6 Complaints
$stmt = $db->query("
    SELECT c.*, u.name AS student_name, u.student_id AS student_roll
    FROM complaints c
    JOIN users u ON c.user_id = u.id
    ORDER BY c.created_at DESC
    LIMIT 6
");
$recentComplaints = $stmt->fetchAll();

// Fetch Recent Contact Messages
$recentMessages = $db->query("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 5")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Admin Greeting Panel -->
<div class="panel" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
    <div>
        <h2 style="color: #0b3c68; font-size: 20px;">Administrator Operations Dashboard</h2>
        <p style="color: #666666; font-size: 13px; margin-top: 3px;">
            Facility maintenance dispatch, technician assignment, and resolution monitoring.
        </p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="manage_complaints.php" class="btn btn-primary btn-sm">Manage All Complaints</a>
        <a href="reports.php" class="btn btn-secondary btn-sm">Reports &amp; Analytics</a>
    </div>
</div>

<!-- Primary Statistics Row -->
<div class="stat-row">
    <div class="stat-box">
        <h4>Total Complaints</h4>
        <div class="count"><?php echo $totalComplaints; ?></div>
        <div class="desc">All-time lodged</div>
    </div>
    <div class="stat-box pending">
        <h4>Pending (Submitted)</h4>
        <div class="count"><?php echo $pendingComplaints; ?></div>
        <div class="desc">Requires triage</div>
    </div>
    <div class="stat-box progress">
        <h4>In Progress</h4>
        <div class="count"><?php echo $inProgressComplaints; ?></div>
        <div class="desc">Under active repair</div>
    </div>
    <div class="stat-box resolved">
        <h4>Resolved</h4>
        <div class="count"><?php echo $resolvedComplaints; ?></div>
        <div class="desc">Completed repairs</div>
    </div>
</div>

<!-- Quick Statistics Banner -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap: 12px; margin-bottom: 20px;">
    <div class="panel" style="margin-bottom: 0; padding: 14px;">
        <strong style="color: #555555; font-size: 12px; text-transform: uppercase;">Registered Students</strong>
        <div style="font-size: 20px; font-weight: bold; color: #0b3c68; margin-top: 3px;"><?php echo $totalStudents; ?> Students</div>
    </div>
    <div class="panel" style="margin-bottom: 0; padding: 14px;">
        <strong style="color: #555555; font-size: 12px; text-transform: uppercase;">Average Student Rating</strong>
        <div style="font-size: 20px; font-weight: bold; color: #d97706; margin-top: 3px;">
            &starf; <?php echo $avgRatingFormatted; ?> / 5.0
            <span style="font-size: 11px; color: #777777; font-weight: normal;">(<?php echo $totalFeedback; ?> reviews)</span>
        </div>
    </div>
    <div class="panel" style="margin-bottom: 0; padding: 14px;">
        <strong style="color: #555555; font-size: 12px; text-transform: uppercase;">Unassigned Queue</strong>
        <div style="margin-top: 4px;">
            <a href="manage_complaints.php?status=Submitted" class="btn btn-secondary btn-sm" style="width: 100%;">View <?php echo $pendingComplaints; ?> Pending Tickets</a>
        </div>
    </div>
</div>

<!-- Recent Complaints Table -->
<div class="panel">
    <div class="panel-header">
        <span class="panel-title">Recent Maintenance Queue</span>
        <a href="manage_complaints.php" class="btn btn-secondary btn-sm">View Complete List &rarr;</a>
    </div>

    <?php if (empty($recentComplaints)): ?>
        <p style="text-align: center; color: #777777; padding: 30px;">No complaints currently recorded in database.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="grid-table">
                <thead>
                    <tr>
                        <th>Complaint ID</th>
                        <th>Student Name &amp; Roll</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Assigned Staff</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentComplaints as $row): ?>
                        <?php
                            $statusClass = 'badge-submitted';
                            if ($row['status'] === 'In Progress') $statusClass = 'badge-progress';
                            if ($row['status'] === 'Resolved') $statusClass = 'badge-resolved';

                            $priorityClass = 'badge-low';
                            if ($row['priority'] === 'Medium') $priorityClass = 'badge-medium';
                            if ($row['priority'] === 'High') $priorityClass = 'badge-high';
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['complaint_id']); ?></strong></td>
                            <td>
                                <?php echo htmlspecialchars($row['student_name']); ?><br>
                                <span style="font-size: 11px; color: #666666;">Roll: <?php echo htmlspecialchars($row['student_roll']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                            <td><span class="badge <?php echo $priorityClass; ?>"><?php echo htmlspecialchars($row['priority']); ?></span></td>
                            <td><span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                            <td><?php echo !empty($row['assigned_to']) ? htmlspecialchars($row['assigned_to']) : '<em style="color:#999;">Unassigned</em>'; ?></td>
                            <td><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></td>
                            <td>
                                <a href="manage_complaints.php?id=<?php echo urlencode($row['complaint_id']); ?>" class="btn btn-primary btn-sm">Manage</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Contact Us Inquiries Panel -->
<div class="panel" style="margin-top: 25px;">
    <div class="panel-header">
        <span class="panel-title">Estate Office Inquiries (Contact Us Messages)</span>
        <span style="font-size: 13px; color: #64748b;">Showing latest <strong><?php echo count($recentMessages); ?></strong> inquiry message(s)</span>
    </div>

    <?php if (empty($recentMessages)): ?>
        <p style="padding: 15px; color: #64748b; font-size: 14px;">No inquiries or messages received yet.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="grid-table">
                <thead>
                    <tr>
                        <th style="width: 170px;">Date &amp; Time</th>
                        <th>Sender Name</th>
                        <th>Email Address</th>
                        <th>Subject</th>
                        <th>Message Content</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentMessages as $msg): ?>
                        <tr>
                            <td style="white-space: nowrap; font-size: 13px; color: #64748b;">
                                <?php echo date('d M Y, h:i A', strtotime($msg['created_at'])); ?>
                            </td>
                            <td><strong><?php echo htmlspecialchars($msg['name']); ?></strong></td>
                            <td><a href="mailto:<?php echo htmlspecialchars($msg['email']); ?>" style="color: #1976d2;"><?php echo htmlspecialchars($msg['email']); ?></a></td>
                            <td><strong><?php echo htmlspecialchars($msg['subject']); ?></strong></td>
                            <td style="font-size: 13px; line-height: 1.5; max-width: 380px;">
                                <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
