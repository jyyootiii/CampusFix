<?php
/**
 * CampusFix — Admin Manage Complaints
 * 
 * View all campus complaints, search and filter by category/priority/status.
 * When a complaint ID is selected (?id=...), administrators can update priority,
 * assign technician staff, change status (Submitted -> In Progress -> Resolved),
 * and record administrative audit remarks.
 */

$pageTitle = 'Manage Complaints';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('Admin');

$user = currentUser();
$db = getDB();

$selectedId = trim($_GET['id'] ?? '');
$editComplaint = null;
$history = [];
$feedback = null;
$error = '';

// Handle Status & Assignment Update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetId    = trim($_POST['complaint_id'] ?? '');
    $newPriority = trim($_POST['priority'] ?? 'Medium');
    $assignedTo  = trim($_POST['assigned_to'] ?? '');
    $newStatus   = trim($_POST['status'] ?? 'Submitted');
    $remarks     = trim($_POST['remarks'] ?? '');

    $validStatuses = ['Submitted', 'In Progress', 'Resolved'];
    $validPriorities = ['Low', 'Medium', 'High'];

    if (!in_array($newStatus, $validStatuses) || !in_array($newPriority, $validPriorities)) {
        $error = 'Invalid status or priority value.';
    } else {
        // Fetch current status
        $curStmt = $db->prepare("SELECT status FROM complaints WHERE complaint_id = ?");
        $curStmt->execute([$targetId]);
        $oldStatus = $curStmt->fetchColumn();

        if ($oldStatus !== false) {
            try {
                $db->beginTransaction();

                $now = date('Y-m-d H:i:s');

                // 1. Update complaints table
                $up = $db->prepare("
                    UPDATE complaints
                    SET priority = ?, assigned_to = ?, status = ?, updated_at = ?
                    WHERE complaint_id = ?
                ");
                $up->execute([$newPriority, $assignedTo, $newStatus, $now, $targetId]);

                // 2. Insert into complaint_history audit trail
                if (empty($remarks)) {
                    $remarks = "Status updated from {$oldStatus} to {$newStatus}";
                    if (!empty($assignedTo)) {
                        $remarks .= " | Staff: {$assignedTo}";
                    }
                }

                $hStmt = $db->prepare("
                    INSERT INTO complaint_history (complaint_id, old_status, new_status, remarks, updated_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $hStmt->execute([
                    $targetId,
                    $oldStatus,
                    $newStatus,
                    $remarks,
                    $user['name'] . ' (Admin)',
                    $now
                ]);

                $db->commit();
                setFlash('success', "Complaint {$targetId} updated successfully!");
                header("Location: manage_complaints.php?id=" . urlencode($targetId));
                exit;

            } catch (PDOException $e) {
                if ($db->inTransaction()) $db->rollBack();
                $error = 'Update failed: ' . $e->getMessage();
            }
        } else {
            $error = 'Complaint not found.';
        }
    }
}

// If a specific complaint is selected for triage
if (!empty($selectedId)) {
    $stmt = $db->prepare("
        SELECT c.*, u.name AS student_name, u.student_id AS student_roll, u.email AS student_email
        FROM complaints c
        JOIN users u ON c.user_id = u.id
        WHERE c.complaint_id = ?
        LIMIT 1
    ");
    $stmt->execute([$selectedId]);
    $editComplaint = $stmt->fetch();

    if ($editComplaint) {
        $hQuery = $db->prepare("SELECT * FROM complaint_history WHERE complaint_id = ? ORDER BY created_at DESC");
        $hQuery->execute([$selectedId]);
        $history = $hQuery->fetchAll();

        $fbQuery = $db->prepare("SELECT * FROM feedback WHERE complaint_id = ? LIMIT 1");
        $fbQuery->execute([$selectedId]);
        $feedback = $fbQuery->fetch();
    }
}

// Fetch all complaints for the master table
$filterStatus = trim($_GET['status'] ?? '');
$filterCategory = trim($_GET['category'] ?? '');
$filterPriority = trim($_GET['priority'] ?? '');

$sql = "
    SELECT c.*, u.name AS student_name, u.student_id AS student_roll
    FROM complaints c
    JOIN users u ON c.user_id = u.id
    WHERE 1=1
";
$params = [];

if (!empty($filterStatus)) {
    $sql .= " AND c.status = ?";
    $params[] = $filterStatus;
}
if (!empty($filterCategory)) {
    $sql .= " AND c.category = ?";
    $params[] = $filterCategory;
}
if (!empty($filterPriority)) {
    $sql .= " AND c.priority = ?";
    $params[] = $filterPriority;
}

$sql .= " ORDER BY c.created_at DESC";
$listStmt = $db->prepare($sql);
$listStmt->execute($params);
$allComplaints = $listStmt->fetchAll();

$categories = ['Electrical', 'Plumbing', 'Internet/Wi-Fi', 'Classroom Equipment', 'Furniture', 'Cleanliness', 'Other'];

require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Triage / Edit Section if a complaint is selected -->
<?php if ($editComplaint): ?>
    <div class="panel" style="border: 2px solid #0b3c68; background: #ffffff;">
        <div class="panel-header">
            <span class="panel-title">Triage &amp; Update Ticket: <?php echo htmlspecialchars($editComplaint['complaint_id']); ?></span>
            <a href="manage_complaints.php" class="btn btn-secondary btn-sm">&larr; Close Triage Form</a>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 340px), 1fr)); gap: 20px;">
            <!-- Left: Complaint Info -->
            <div>
                <table class="grid-table">
                    <tr>
                        <th style="width: 140px;">Title</th>
                        <td><strong><?php echo htmlspecialchars($editComplaint['title']); ?></strong></td>
                    </tr>
                    <tr>
                        <th>Category</th>
                        <td><?php echo htmlspecialchars($editComplaint['category']); ?></td>
                    </tr>
                    <tr>
                        <th>Location</th>
                        <td><?php echo htmlspecialchars($editComplaint['location']); ?></td>
                    </tr>
                    <tr>
                        <th>Student</th>
                        <td><?php echo htmlspecialchars($editComplaint['student_name']); ?> (Roll: <?php echo htmlspecialchars($editComplaint['student_roll']); ?>)</td>
                    </tr>
                    <tr>
                        <th>Student Email</th>
                        <td><a href="mailto:<?php echo htmlspecialchars($editComplaint['student_email']); ?>"><?php echo htmlspecialchars($editComplaint['student_email']); ?></a></td>
                    </tr>
                    <tr>
                        <th>Description</th>
                        <td style="white-space: pre-wrap; line-height: 1.6;"><?php echo htmlspecialchars($editComplaint['description']); ?></td>
                    </tr>
                    <?php if (!empty($editComplaint['image'])): ?>
                        <tr>
                            <th>Attachment</th>
                            <td>
                                <a href="../uploads/complaints/<?php echo htmlspecialchars($editComplaint['image']); ?>" target="_blank">
                                    <img src="../uploads/complaints/<?php echo htmlspecialchars($editComplaint['image']); ?>" alt="Photo" style="max-width: 220px; max-height: 160px; border: 1px solid #cbd5e1; border-radius: 4px; object-fit: contain;">
                                </a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>

                <?php if ($feedback): ?>
                    <div style="margin-top: 15px; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 14px; border-radius: 4px; font-size: 14px;">
                        <strong style="color: #166534;">Student Feedback:</strong>
                        <span style="color: #f59e0b; margin-left: 8px;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php echo ($i <= $feedback['rating']) ? '&#9733;' : '&#9734;'; ?>
                            <?php endfor; ?>
                        </span>
                        <?php if (!empty($feedback['message'])): ?>
                            <p style="margin-top: 6px; color: #166534; line-height: 1.5;">&ldquo;<?php echo htmlspecialchars($feedback['message']); ?>&rdquo;</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right: Admin Action Form -->
            <div>
                <form method="POST" action="manage_complaints.php?id=<?php echo urlencode($selectedId); ?>" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 4px;">
                    <input type="hidden" name="complaint_id" value="<?php echo htmlspecialchars($selectedId); ?>">

                    <div class="form-group">
                        <label for="status">Update Status: <span style="color: #dc2626;">*</span></label>
                        <select id="status" name="status" required>
                            <option value="Submitted" <?php echo ($editComplaint['status'] === 'Submitted') ? 'selected' : ''; ?>>Submitted (Awaiting Assignment)</option>
                            <option value="In Progress" <?php echo ($editComplaint['status'] === 'In Progress') ? 'selected' : ''; ?>>In Progress (Work Dispatched)</option>
                            <option value="Resolved" <?php echo ($editComplaint['status'] === 'Resolved') ? 'selected' : ''; ?>>Resolved (Repairs Finished)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="priority">Priority Level: <span style="color: #dc2626;">*</span></label>
                        <select id="priority" name="priority" required>
                            <option value="Low" <?php echo ($editComplaint['priority'] === 'Low') ? 'selected' : ''; ?>>Low</option>
                            <option value="Medium" <?php echo ($editComplaint['priority'] === 'Medium') ? 'selected' : ''; ?>>Medium</option>
                            <option value="High" <?php echo ($editComplaint['priority'] === 'High') ? 'selected' : ''; ?>>High</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="assigned_to">Assign Technician / Staff <span style="font-weight: normal; color: #64748b; font-size: 13px;">(Optional)</span>:</label>
                        <input type="text" id="assigned_to" name="assigned_to" value="<?php echo htmlspecialchars($editComplaint['assigned_to'] ?? ''); ?>" placeholder="e.g. Ramesh Kumar (Electrician) — or leave blank if unassigned">
                        <p class="field-help">Optional: You may leave this empty if work is handled internally or technician is not yet assigned.</p>
                    </div>

                    <div class="form-group">
                        <label for="remarks">Admin Remarks / Work Notes:</label>
                        <textarea id="remarks" name="remarks" rows="3" placeholder="e.g. Dispatched technician to replace capacitor and test at full speed..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" style="padding: 12px; font-size: 15px;">Save &amp; Update Status</button>
                </form>

                <!-- History Log Table -->
                <div style="margin-top: 18px;">
                    <strong style="font-size: 13px; color: #475569; text-transform: uppercase;">Ticket Audit History</strong>
                    <table class="grid-table" style="font-size: 13px; margin-top: 6px;">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Updated By</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $h): ?>
                                <tr>
                                    <td style="white-space: nowrap; font-size: 12px; color: #64748b;"><?php echo date('d M Y, h:i A', strtotime($h['created_at'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($h['new_status']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($h['updated_by']); ?></td>
                                    <td><?php echo htmlspecialchars($h['remarks'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Master Complaints Table -->
<div class="panel">
    <div class="panel-header">
        <span class="panel-title">Master Complaint Management</span>
        <span style="font-size: 13px; color: #64748b;">Showing <strong><?php echo count($allComplaints); ?></strong> record(s)</span>
    </div>

    <!-- Filter & Live Search Bar -->
    <div class="filter-row">
        <input type="text" id="tableSearch" placeholder="Search by Complaint ID, Student, Title, Location..." style="flex: 2; min-width: 220px;">

        <select id="filterCategory">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat; ?>" <?php echo ($filterCategory === $cat) ? 'selected' : ''; ?>><?php echo $cat; ?></option>
            <?php endforeach; ?>
        </select>

        <select id="filterStatus">
            <option value="">All Statuses</option>
            <option value="Submitted" <?php echo ($filterStatus === 'Submitted') ? 'selected' : ''; ?>>Submitted</option>
            <option value="In Progress" <?php echo ($filterStatus === 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
            <option value="Resolved" <?php echo ($filterStatus === 'Resolved') ? 'selected' : ''; ?>>Resolved</option>
        </select>

        <select id="filterPriority">
            <option value="">All Priorities</option>
            <option value="Low" <?php echo ($filterPriority === 'Low') ? 'selected' : ''; ?>>Low</option>
            <option value="Medium" <?php echo ($filterPriority === 'Medium') ? 'selected' : ''; ?>>Medium</option>
            <option value="High" <?php echo ($filterPriority === 'High') ? 'selected' : ''; ?>>High</option>
        </select>

        <?php if (!empty($filterStatus) || !empty($filterCategory) || !empty($filterPriority)): ?>
            <a href="manage_complaints.php" class="btn btn-secondary btn-sm" style="padding: 9px 14px;">Clear Filter</a>
        <?php endif; ?>
    </div>

    <?php if (empty($allComplaints)): ?>
        <p style="text-align: center; color: #777777; padding: 40px; font-size: 15px;">No complaints found matching the selected criteria.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="grid-table">
                <thead>
                    <tr>
                        <th>Complaint ID</th>
                        <th>Student Name</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Assigned Staff</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allComplaints as $row): ?>
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
                            <td>
                                <strong><?php echo htmlspecialchars($row['student_name']); ?></strong><br>
                                <span style="font-size: 12px; color: #64748b;">Roll: <?php echo htmlspecialchars($row['student_roll']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                            <td><?php echo htmlspecialchars($row['location']); ?></td>
                            <td><span class="badge <?php echo $priorityClass; ?>"><?php echo htmlspecialchars($row['priority']); ?></span></td>
                            <td><span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                            <td><?php echo !empty($row['assigned_to']) ? '<strong>' . htmlspecialchars($row['assigned_to']) . '</strong>' : '<em style="color:#94a3b8;">Unassigned</em>'; ?></td>
                            <td><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></td>
                            <td>
                                <a href="manage_complaints.php?id=<?php echo urlencode($row['complaint_id']); ?>" class="btn btn-primary btn-sm" style="padding: 6px 14px;">Manage</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
