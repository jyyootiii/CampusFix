<?php
/**
 * CampusFix — Student Complaint History
 * Lists all complaints submitted by the logged-in student with live search and filters.
 */

$pageTitle = 'Complaint History';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('Student');

$user = currentUser();
$db = getDB();

$stmt = $db->prepare("
    SELECT c.*, f.id AS feedback_id, f.rating AS feedback_rating
    FROM complaints c
    LEFT JOIN feedback f ON c.complaint_id = f.complaint_id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$user['id']]);
$complaints = $stmt->fetchAll();

$categories = array_unique(array_column($complaints, 'category'));
sort($categories);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div class="panel-header">
        <span class="panel-title">My Lodged Complaints</span>
        <a href="submit_complaint.php" class="btn btn-primary btn-sm">+ Lodge New Complaint</a>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="filter-row">
        <input type="text" id="tableSearch" placeholder="Search by Complaint ID, Title, or Location..." style="flex: 2;">

        <select id="filterCategory">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
            <?php endforeach; ?>
        </select>

        <select id="filterStatus">
            <option value="">All Statuses</option>
            <option value="Submitted">Submitted</option>
            <option value="In Progress">In Progress</option>
            <option value="Resolved">Resolved</option>
        </select>

        <select id="filterPriority">
            <option value="">All Priorities</option>
            <option value="Low">Low</option>
            <option value="Medium">Medium</option>
            <option value="High">High</option>
        </select>
    </div>

    <?php if (empty($complaints)): ?>
        <div style="text-align: center; padding: 30px; color: #777777;">
            <p style="margin-bottom: 10px;">No complaints found in your account.</p>
            <a href="submit_complaint.php" class="btn btn-primary btn-sm">Lodge a complaint</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="grid-table">
                <thead>
                    <tr>
                        <th>Complaint ID</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Date Submitted</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($complaints as $row): ?>
                        <?php
                            $statusClass = 'badge-submitted';
                            if ($row['status'] === 'In Progress') $statusClass = 'badge-progress';
                            if ($row['status'] === 'Resolved') $statusClass = 'badge-resolved';

                            $priorityClass = 'badge-low';
                            if ($row['priority'] === 'Medium') $priorityClass = 'badge-medium';
                            if ($row['priority'] === 'High') $priorityClass = 'badge-high';
                        ?>
                        <tr 
                            data-category="<?php echo htmlspecialchars($row['category']); ?>" 
                            data-status="<?php echo htmlspecialchars($row['status']); ?>" 
                            data-priority="<?php echo htmlspecialchars($row['priority']); ?>"
                        >
                            <td><strong><?php echo htmlspecialchars($row['complaint_id']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                            <td><?php echo htmlspecialchars($row['location']); ?></td>
                            <td><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></td>
                            <td><span class="badge <?php echo $priorityClass; ?>"><?php echo htmlspecialchars($row['priority']); ?></span></td>
                            <td><span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                            <td>
                                <a href="track_complaint.php?id=<?php echo urlencode($row['complaint_id']); ?>" class="btn btn-secondary btn-sm">Track</a>
                                <?php if ($row['status'] === 'Resolved'): ?>
                                    <?php if (!empty($row['feedback_id'])): ?>
                                        <span class="badge" style="background:#e2e8f0; color:#475569;">&starf; <?php echo (int)$row['feedback_rating']; ?>/5</span>
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
