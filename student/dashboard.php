<?php
/**
 * CampusFix — Student Dashboard
 * Displays metrics, quick submit link, and recent complaints for the logged-in student.
 */

$pageTitle = 'Student Dashboard';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('Student');

$user = currentUser();
$db = getDB();

// 1. Fetch Student Statistics
$stmt = $db->prepare("SELECT COUNT(*) FROM complaints WHERE user_id = ?");
$stmt->execute([$user['id']]);
$totalComplaints = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM complaints WHERE user_id = ? AND status = 'Submitted'");
$stmt->execute([$user['id']]);
$pendingComplaints = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM complaints WHERE user_id = ? AND status = 'In Progress'");
$stmt->execute([$user['id']]);
$inProgressComplaints = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM complaints WHERE user_id = ? AND status = 'Resolved'");
$stmt->execute([$user['id']]);
$resolvedComplaints = (int)$stmt->fetchColumn();

// 2. Fetch Recent Complaints
$stmt = $db->prepare("
    SELECT c.*, f.id AS feedback_id 
    FROM complaints c
    LEFT JOIN feedback f ON c.complaint_id = f.complaint_id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
    LIMIT 5
");
$stmt->execute([$user['id']]);
$recentComplaints = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Welcome Banner -->
<div class="panel" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
    <div>
        <h2 style="color: #0b3c68; font-size: 20px;">Welcome, <?php echo htmlspecialchars($user['name']); ?></h2>
        <p style="color: #666666; font-size: 13px; margin-top: 3px;">
            Student ID: <strong><?php echo htmlspecialchars($user['student_id']); ?></strong> &bull; Email: <strong><?php echo htmlspecialchars($user['email']); ?></strong>
        </p>
    </div>
    <div>
        <a href="submit_complaint.php" class="btn btn-primary">+ Lodge New Complaint</a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="stat-row">
    <div class="stat-box">
        <h4>Total Complaints</h4>
        <div class="count"><?php echo $totalComplaints; ?></div>
        <div class="desc">Lifetime submitted</div>
    </div>
    <div class="stat-box pending">
        <h4>Pending Review</h4>
        <div class="count"><?php echo $pendingComplaints; ?></div>
        <div class="desc">Awaiting triage</div>
    </div>
    <div class="stat-box progress">
        <h4>In Progress</h4>
        <div class="count"><?php echo $inProgressComplaints; ?></div>
        <div class="desc">Technician working</div>
    </div>
    <div class="stat-box resolved">
        <h4>Resolved</h4>
        <div class="count"><?php echo $resolvedComplaints; ?></div>
        <div class="desc">Repairs completed</div>
    </div>
</div>

<!-- Recent Complaints Section -->
<div class="panel">
    <div class="panel-header">
        <span class="panel-title">My Recent Complaints</span>
        <a href="complaint_history.php" class="btn btn-secondary btn-sm">View Full History &rarr;</a>
    </div>

    <?php if (empty($recentComplaints)): ?>
        <div style="text-align: center; padding: 30px; color: #777777;">
            <p style="margin-bottom: 10px;">You have not lodged any maintenance complaints yet.</p>
            <a href="submit_complaint.php" class="btn btn-primary btn-sm">Lodge your first complaint</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="grid-table">
                <thead>
                    <tr>
                        <th>Complaint ID</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Date Submitted</th>
                        <th>Priority</th>
                        <th>Status</th>
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
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                            <td><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></td>
                            <td><span class="badge <?php echo $priorityClass; ?>"><?php echo htmlspecialchars($row['priority']); ?></span></td>
                            <td><span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                            <td>
                                <a href="track_complaint.php?id=<?php echo urlencode($row['complaint_id']); ?>" class="btn btn-secondary btn-sm">Track</a>
                                <?php if ($row['status'] === 'Resolved'): ?>
                                    <?php if (!empty($row['feedback_id'])): ?>
                                        <span class="badge" style="background:#e2e8f0; color:#475569;">Rated</span>
                                    <?php else: ?>
                                        <a href="feedback.php?id=<?php echo urlencode($row['complaint_id']); ?>" class="btn btn-success btn-sm">Rate</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
