<?php
/**
 * CampusFix — Reports & Analytics
 * Simple, clear operational reports and statistics for college presentation.
 */

$pageTitle = 'Reports & Analytics';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('Admin');

$db = getDB();

$totalComplaints      = (int)$db->query("SELECT COUNT(*) FROM complaints")->fetchColumn();
$pendingComplaints    = (int)$db->query("SELECT COUNT(*) FROM complaints WHERE status = 'Submitted'")->fetchColumn();
$inProgressComplaints = (int)$db->query("SELECT COUNT(*) FROM complaints WHERE status = 'In Progress'")->fetchColumn();
$resolvedComplaints   = (int)$db->query("SELECT COUNT(*) FROM complaints WHERE status = 'Resolved'")->fetchColumn();

$resolutionRate = ($totalComplaints > 0) ? round(($resolvedComplaints / $totalComplaints) * 100, 1) : 0;

// Category statistics
$catStmt = $db->query("
    SELECT 
        category,
        COUNT(*) AS total_count,
        SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) AS resolved_count,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) AS progress_count,
        SUM(CASE WHEN status = 'Submitted' THEN 1 ELSE 0 END) AS pending_count
    FROM complaints
    GROUP BY category
    ORDER BY total_count DESC
");
$categoryReport = $catStmt->fetchAll();

// Priority statistics
$prioStmt = $db->query("
    SELECT 
        priority,
        COUNT(*) AS total_count,
        SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) AS resolved_count
    FROM complaints
    GROUP BY priority
    ORDER BY CASE priority WHEN 'High' THEN 1 WHEN 'Medium' THEN 2 WHEN 'Low' THEN 3 ELSE 4 END
");
$priorityReport = $prioStmt->fetchAll();

// Feedback statistics
$totalFeedback = (int)$db->query("SELECT COUNT(*) FROM feedback")->fetchColumn();
$avgRating     = $db->query("SELECT AVG(rating) FROM feedback")->fetchColumn();
$avgRatingFmt  = $avgRating ? number_format((float)$avgRating, 1) : '0.0';

// Recent feedback
$recentFeedback = $db->query("
    SELECT f.*, c.title AS complaint_title, c.category, u.name AS student_name
    FROM feedback f
    JOIN complaints c ON f.complaint_id = c.complaint_id
    JOIN users u ON f.user_id = u.id
    ORDER BY f.created_at DESC
    LIMIT 5
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="panel" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
    <div>
        <h2 style="color: #0b3c68; font-size: 20px;">Maintenance Reports &amp; Analytics</h2>
        <p style="color: #666666; font-size: 13px; margin-top: 3px;">Category breakdowns, resolution metrics, and student feedback summary.</p>
    </div>
    <div>
        <a href="manage_complaints.php" class="btn btn-secondary btn-sm">&larr; Back to Management</a>
    </div>
</div>

<!-- KPI Stat Row -->
<div class="stat-row">
    <div class="stat-box">
        <h4>Total Volume</h4>
        <div class="count"><?php echo $totalComplaints; ?></div>
        <div class="desc">All complaints</div>
    </div>
    <div class="stat-box pending">
        <h4>Pending</h4>
        <div class="count"><?php echo $pendingComplaints; ?></div>
        <div class="desc">Awaiting staff</div>
    </div>
    <div class="stat-box progress">
        <h4>In Progress</h4>
        <div class="count"><?php echo $inProgressComplaints; ?></div>
        <div class="desc">Active work</div>
    </div>
    <div class="stat-box resolved">
        <h4>Resolution Efficiency</h4>
        <div class="count"><?php echo $resolutionRate; ?>%</div>
        <div class="desc"><?php echo $resolvedComplaints; ?> issues resolved</div>
    </div>
</div>

<!-- Category Breakdown Table -->
<div class="panel">
    <div class="panel-header">
        <span class="panel-title">1. Complaints by Category</span>
    </div>

    <?php if (empty($categoryReport)): ?>
        <p style="text-align: center; color: #777777; padding: 25px;">No complaints recorded yet.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="grid-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Total Lodged</th>
                        <th>Share (%)</th>
                        <th>Pending</th>
                        <th>In Progress</th>
                        <th>Resolved</th>
                        <th>Resolution %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categoryReport as $row): ?>
                        <?php
                            $share = ($totalComplaints > 0) ? round(($row['total_count'] / $totalComplaints) * 100, 1) : 0;
                            $rate = ($row['total_count'] > 0) ? round(($row['resolved_count'] / $row['total_count']) * 100) : 0;
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['category']); ?></strong></td>
                            <td><?php echo (int)$row['total_count']; ?></td>
                            <td><?php echo $share; ?>%</td>
                            <td><span class="badge badge-submitted"><?php echo (int)$row['pending_count']; ?></span></td>
                            <td><span class="badge badge-progress"><?php echo (int)$row['progress_count']; ?></span></td>
                            <td><span class="badge badge-resolved"><?php echo (int)$row['resolved_count']; ?></span></td>
                            <td><strong><?php echo $rate; ?>%</strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Priority & Feedback Row -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 15px; margin-bottom: 20px;">
    
    <!-- Priority Table -->
    <div class="panel" style="margin-bottom: 0;">
        <div class="panel-header">
            <span class="panel-title">2. Priority Distribution</span>
        </div>

        <?php if (empty($priorityReport)): ?>
            <p style="text-align: center; color: #777777; padding: 20px;">No priority data recorded.</p>
        <?php else: ?>
            <table class="grid-table">
                <thead>
                    <tr>
                        <th>Priority</th>
                        <th>Total</th>
                        <th>Resolved</th>
                        <th>Resolution %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($priorityReport as $p): ?>
                        <?php
                            $pBadge = 'badge-low';
                            if ($p['priority'] === 'Medium') $pBadge = 'badge-medium';
                            if ($p['priority'] === 'High') $pBadge = 'badge-high';
                            $pRate = ($p['total_count'] > 0) ? round(($p['resolved_count'] / $p['total_count']) * 100) : 0;
                        ?>
                        <tr>
                            <td><span class="badge <?php echo $pBadge; ?>"><?php echo htmlspecialchars($p['priority']); ?></span></td>
                            <td><strong><?php echo (int)$p['total_count']; ?></strong></td>
                            <td><?php echo (int)$p['resolved_count']; ?></td>
                            <td><?php echo $pRate; ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Student Satisfaction -->
    <div class="panel" style="margin-bottom: 0;">
        <div class="panel-header">
            <span class="panel-title">3. Student Satisfaction Score</span>
        </div>

        <div style="display: flex; align-items: center; gap: 15px; padding: 12px; background: #fffbe8; border: 1px solid #fde68a; border-radius: 4px; margin-bottom: 12px;">
            <div style="font-size: 32px; font-weight: bold; color: #d97706;"><?php echo $avgRatingFmt; ?></div>
            <div>
                <div style="color: #f59e0b; font-size: 16px;">
                    <?php
                        $rounded = (int)round((float)$avgRating);
                        for ($s = 1; $s <= 5; $s++) {
                            echo ($s <= $rounded) ? '&#9733;' : '&#9734;';
                        }
                    ?>
                </div>
                <div style="font-size: 12px; color: #666666; margin-top: 2px;">Average from <?php echo $totalFeedback; ?> student review(s)</div>
            </div>
        </div>

        <p style="font-size: 12px; color: #555555; line-height: 1.5;">
            Students rate completed repairs from 1 to 5 stars once marked as Resolved.
        </p>
    </div>

</div>

<!-- Recent Feedback List -->
<div class="panel">
    <div class="panel-header">
        <span class="panel-title">4. Recent Student Feedback &amp; Comments</span>
    </div>

    <?php if (empty($recentFeedback)): ?>
        <p style="text-align: center; color: #777777; padding: 25px;">No student feedback submitted yet.</p>
    <?php else: ?>
        <table class="grid-table">
            <thead>
                <tr>
                    <th>Complaint ID &amp; Title</th>
                    <th>Student</th>
                    <th>Category</th>
                    <th>Rating</th>
                    <th>Comment</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentFeedback as $fb): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($fb['complaint_id']); ?></strong><br>
                            <span style="font-size: 11px; color: #666666;"><?php echo htmlspecialchars($fb['complaint_title']); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($fb['student_name']); ?></td>
                        <td><?php echo htmlspecialchars($fb['category']); ?></td>
                        <td>
                            <span style="color: #f59e0b; font-weight: bold;">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php echo ($i <= $fb['rating']) ? '&#9733;' : '&#9734;'; ?>
                                <?php endfor; ?>
                            </span>
                        </td>
                        <td><?php echo !empty($fb['message']) ? htmlspecialchars($fb['message']) : '<em style="color:#999;">No written comment</em>'; ?></td>
                        <td><?php echo date('d M Y, h:i A', strtotime($fb['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
