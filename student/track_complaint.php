<?php
/**
 * CampusFix — Track Complaint Progress
 * Real-time status lookup, 3-step progress bar, and maintenance remarks.
 */

$pageTitle = 'Track Complaint';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$searchId = trim($_GET['id'] ?? '');
$complaint = null;
$history = [];
$feedback = null;
$error = '';

$db = getDB();

if (!empty($searchId)) {
    $stmt = $db->prepare("
        SELECT c.*, u.name AS student_name, u.student_id AS student_roll
        FROM complaints c
        JOIN users u ON c.user_id = u.id
        WHERE c.complaint_id = ?
        LIMIT 1
    ");
    $stmt->execute([$searchId]);
    $complaint = $stmt->fetch();

    if ($complaint) {
        $histStmt = $db->prepare("SELECT * FROM complaint_history WHERE complaint_id = ? ORDER BY created_at ASC");
        $histStmt->execute([$searchId]);
        $history = $histStmt->fetchAll();

        $fbStmt = $db->prepare("SELECT * FROM feedback WHERE complaint_id = ? LIMIT 1");
        $fbStmt->execute([$searchId]);
        $feedback = $fbStmt->fetch();
    } else {
        $error = 'No complaint record found matching ID: "' . htmlspecialchars($searchId) . '".';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Search Bar Box -->
<div class="panel" style="max-width: 860px; margin: 0 auto 24px;">
    <div class="panel-header">
        <span class="panel-title">Track Complaint Status</span>
    </div>
    <p style="font-size: 14px; color: #64748b; margin-bottom: 16px;">
        Enter your unique Complaint ID (e.g. <code>CF-20261107-1042</code>) to view the live maintenance progress and technician notes.
    </p>
    <form method="GET" action="track_complaint.php" style="display: flex; gap: 10px; flex-wrap: wrap;">
        <input type="text" name="id" value="<?php echo htmlspecialchars($searchId); ?>" placeholder="Enter Complaint ID (e.g. CF-20261107-1042)" required style="flex: 1; min-width: 240px; min-height: 46px; font-size: 15px;">
        <button type="submit" class="btn btn-primary" style="padding: 12px 26px; font-size: 15px;">Track Ticket</button>
    </form>
</div>

<?php if (!empty($error)): ?>
    <div style="max-width: 860px; margin: 0 auto 24px;">
        <div class="alert alert-error"><?php echo $error; ?></div>
    </div>
<?php endif; ?>

<?php if ($complaint): ?>
    <?php
        $status = $complaint['status'];
        $step1 = 'done';
        $step2 = ($status === 'In Progress' || $status === 'Resolved') ? ($status === 'Resolved' ? 'done' : 'active') : '';
        $step3 = ($status === 'Resolved') ? 'done active' : '';

        $statusBadge = 'badge-submitted';
        if ($status === 'In Progress') $statusBadge = 'badge-progress';
        if ($status === 'Resolved') $statusBadge = 'badge-resolved';

        $priorityBadge = 'badge-low';
        if ($complaint['priority'] === 'Medium') $priorityBadge = 'badge-medium';
        if ($complaint['priority'] === 'High') $priorityBadge = 'badge-high';
    ?>

    <div style="max-width: 860px; margin: 0 auto;">
        <!-- Status Progression Stepper -->
        <div class="panel">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 10px;">
                <div>
                    <span style="font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: bold;">Complaint Ticket</span>
                    <h3 style="color: #0b3c68; font-size: 22px; margin-top: 2px;"><?php echo htmlspecialchars($complaint['complaint_id']); ?></h3>
                </div>
                <div>
                    <span class="badge <?php echo $statusBadge; ?>" style="font-size: 13px; padding: 6px 12px;"><?php echo htmlspecialchars($complaint['status']); ?></span>
                    <span class="badge <?php echo $priorityBadge; ?>" style="font-size: 13px; padding: 6px 12px; margin-left: 6px;"><?php echo htmlspecialchars($complaint['priority']); ?> Priority</span>
                </div>
            </div>

            <!-- Stepper -->
            <div class="stepper-box">
                <div class="step-col <?php echo $step1; ?>">
                    <div class="step-num">&#10003;</div>
                    <div class="step-title">1. Submitted</div>
                    <div style="font-size: 12px; color: #64748b; margin-top: 4px;"><?php echo date('d M Y, h:i A', strtotime($complaint['created_at'])); ?></div>
                </div>
                <div class="step-col <?php echo $step2; ?>">
                    <div class="step-num"><?php echo ($status === 'In Progress' || $status === 'Resolved') ? '&#10003;' : '2'; ?></div>
                    <div class="step-title">2. In Progress</div>
                    <div style="font-size: 12px; color: #64748b; margin-top: 4px;">
                        <?php echo !empty($complaint['assigned_to']) ? htmlspecialchars($complaint['assigned_to']) : 'Awaiting Staff'; ?>
                    </div>
                </div>
                <div class="step-col <?php echo $step3; ?>">
                    <div class="step-num"><?php echo ($status === 'Resolved') ? '&#10003;' : '3'; ?></div>
                    <div class="step-title">3. Resolved</div>
                    <div style="font-size: 12px; color: #64748b; margin-top: 4px;">
                        <?php echo ($status === 'Resolved') ? date('d M Y, h:i A', strtotime($complaint['updated_at'])) : 'Pending Fix'; ?>
                    </div>
                </div>
            </div>

            <!-- Details Table -->
            <table class="grid-table" style="margin-top: 20px;">
                <tr>
                    <th style="width: 170px;">Complaint Title</th>
                    <td><strong><?php echo htmlspecialchars($complaint['title']); ?></strong></td>
                </tr>
                <tr>
                    <th>Category</th>
                    <td><?php echo htmlspecialchars($complaint['category']); ?></td>
                </tr>
                <tr>
                    <th>Campus Location</th>
                    <td><?php echo htmlspecialchars($complaint['location']); ?></td>
                </tr>
                <tr>
                    <th>Submitted By</th>
                    <td><?php echo htmlspecialchars($complaint['student_name']); ?> (Roll: <?php echo htmlspecialchars($complaint['student_roll']); ?>)</td>
                </tr>
                <tr>
                    <th>Assigned Staff</th>
                    <td>
                        <?php echo !empty($complaint['assigned_to']) ? '<strong>' . htmlspecialchars($complaint['assigned_to']) . '</strong>' : '<em style="color:#888;">Not assigned yet</em>'; ?>
                    </td>
                </tr>
                <tr>
                    <th>Problem Description</th>
                    <td style="white-space: pre-wrap; line-height: 1.6;"><?php echo htmlspecialchars($complaint['description']); ?></td>
                </tr>
                <?php if (!empty($complaint['image'])): ?>
                    <tr>
                        <th>Attached Photo</th>
                        <td>
                            <a href="../uploads/complaints/<?php echo htmlspecialchars($complaint['image']); ?>" target="_blank">
                                <img src="../uploads/complaints/<?php echo htmlspecialchars($complaint['image']); ?>" alt="Photo" style="max-width: 260px; max-height: 200px; border: 1px solid #cbd5e1; border-radius: 4px; object-fit: contain;">
                            </a>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>

        <!-- Maintenance History & Remarks -->
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Maintenance Progression Log</span>
            </div>

            <?php if (empty($history)): ?>
                <p style="color: #64748b; font-size: 14px;">No history records available.</p>
            <?php else: ?>
                <table class="grid-table">
                    <thead>
                        <tr>
                            <th style="width: 180px;">Date &amp; Time</th>
                            <th style="width: 160px;">Status Transition</th>
                            <th style="width: 180px;">Updated By</th>
                            <th>Remarks &amp; Work Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $h): ?>
                            <tr>
                                <td style="white-space: nowrap;"><?php echo date('d M Y, h:i A', strtotime($h['created_at'])); ?></td>
                                <td>
                                    <?php echo !empty($h['old_status']) ? htmlspecialchars($h['old_status']) . ' &rarr; ' : ''; ?>
                                    <strong><?php echo htmlspecialchars($h['new_status']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($h['updated_by']); ?></td>
                                <td><?php echo !empty($h['remarks']) ? htmlspecialchars($h['remarks']) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <!-- Feedback Section -->
            <div style="margin-top: 25px; padding-top: 18px; border-top: 1px solid #e2e8f0;">
                <h4 style="color: #0b3c68; margin-bottom: 10px; font-size: 16px;">Student Satisfaction Feedback</h4>
                <?php if ($status === 'Resolved'): ?>
                    <?php if ($feedback): ?>
                        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 16px; border-radius: 4px;">
                            <div style="color: #f59e0b; font-size: 20px; margin-bottom: 6px;">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php echo ($i <= $feedback['rating']) ? '&#9733;' : '&#9734;'; ?>
                                <?php endfor; ?>
                                <span style="font-size: 14px; color: #166534; font-weight: bold; margin-left: 8px;">(<?php echo $feedback['rating']; ?> / 5 Stars)</span>
                            </div>
                            <?php if (!empty($feedback['message'])): ?>
                                <p style="font-size: 14px; color: #166534; line-height: 1.5;">&ldquo;<?php echo htmlspecialchars($feedback['message']); ?>&rdquo;</p>
                            <?php endif; ?>
                            <span style="font-size: 12px; color: #64748b; display: block; margin-top: 6px;">Submitted on <?php echo date('d M Y, h:i A', strtotime($feedback['created_at'])); ?></span>
                        </div>
                    <?php else: ?>
                        <div style="background: #eef6ff; border: 1px solid #bbdefb; padding: 16px; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                            <div>
                                <strong style="color: #0b3c68; font-size: 15px;">Maintenance Repair Completed!</strong>
                                <p style="font-size: 13px; color: #475569; margin-top: 2px;">Please rate the technician's work to help us improve campus facility services.</p>
                            </div>
                            <a href="feedback.php?id=<?php echo urlencode($complaint['complaint_id']); ?>" class="btn btn-success" style="padding: 10px 20px;">Rate Resolution &rarr;</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="font-size: 14px; color: #64748b;">Feedback unlocks once the maintenance status reaches <em>Resolved</em>.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Guidance & Tracking Information (Displayed before a ticket is searched) -->
    <div class="panel" style="max-width: 860px; margin: 0 auto;">
        <div class="panel-header">
            <span class="panel-title">How Campus Ticket Tracking Works</span>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr)); gap: 16px; margin-bottom: 20px;">
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 18px;">
                <div style="color: #0369a1; font-weight: bold; font-size: 15px; margin-bottom: 6px;">1. Submitted</div>
                <p style="font-size: 13px; color: #475569; line-height: 1.5;">Your maintenance ticket is logged into the central database and enters the administrative triage queue.</p>
            </div>
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 18px;">
                <div style="color: #b45309; font-weight: bold; font-size: 15px; margin-bottom: 6px;">2. In Progress</div>
                <p style="font-size: 13px; color: #475569; line-height: 1.5;">Maintenance staff or electricians are assigned, replacement parts are arranged, and repair work begins.</p>
            </div>
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 18px;">
                <div style="color: #15803d; font-weight: bold; font-size: 15px; margin-bottom: 6px;">3. Resolved</div>
                <p style="font-size: 13px; color: #475569; line-height: 1.5;">Repairs are completed and tested. Students can review technician notes and provide a 1-to-5 star quality rating.</p>
            </div>
        </div>

        <div style="background: #f0f7ff; border: 1px solid #bbdefb; border-radius: 6px; padding: 16px 18px; font-size: 14px; color: #1e3a8a; line-height: 1.6;">
            <strong>Need to find your Complaint ID?</strong>
            <p style="margin-top: 4px; color: #334155; font-size: 13px;">
                Your unique ID follows the format <code>CF-YYYYMMDD-XXXX</code>. You can find it on your lodging confirmation or by viewing <a href="complaint_history.php" style="color: #1976d2; font-weight: bold;">My Complaints</a> if you are signed in.
            </p>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
