<?php
/**
 * CampusFix — Submit Complaint
 * Form for lodging facility issues with category, location, priority, and optional photo.
 */

$pageTitle = 'Submit Complaint';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('Student');

$user = currentUser();
$db = getDB();
$error = '';

$title = '';
$category = '';
$location = '';
$description = '';
$priority = 'Medium';

$allowedCategories = [
    'Electrical',
    'Plumbing',
    'Internet/Wi-Fi',
    'Classroom Equipment',
    'Furniture',
    'Cleanliness',
    'Other'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $category    = trim($_POST['category'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority    = trim($_POST['priority'] ?? 'Medium');

    if (empty($title) || empty($category) || empty($location) || empty($description)) {
        $error = 'Please fill in all mandatory fields.';
    } elseif (!in_array($category, $allowedCategories)) {
        $error = 'Please select a valid category.';
    } elseif (!in_array($priority, ['Low', 'Medium', 'High'])) {
        $error = 'Please select a valid priority.';
    } else {
        // Handle optional image upload
        $imageFileName = null;

        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['image'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'Error during file upload. Code: ' . $file['error'];
            } elseif ($file['size'] > 3 * 1024 * 1024) {
                $error = 'Attached image exceeds 3MB limit.';
            } else {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

                if (!in_array($ext, $allowedExts)) {
                    $error = 'Only JPG, PNG, WEBP, or GIF image formats are accepted.';
                } else {
                    $newFileName = 'img_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
                    $uploadDir = dirname(__DIR__) . '/uploads/complaints';

                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    if (move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $newFileName)) {
                        $imageFileName = $newFileName;
                    } else {
                        $error = 'Could not save the uploaded image.';
                    }
                }
            }
        }

        if (empty($error)) {
            $complaintId = 'CF-' . date('Ymd') . '-' . mt_rand(1000, 9999);

            try {
                $db->beginTransaction();

                $now = date('Y-m-d H:i:s');

                $stmt = $db->prepare("
                    INSERT INTO complaints (complaint_id, user_id, title, category, location, description, image, priority, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Submitted', ?, ?)
                ");
                $stmt->execute([
                    $complaintId,
                    $user['id'],
                    $title,
                    $category,
                    $location,
                    $description,
                    $imageFileName,
                    $priority,
                    $now,
                    $now
                ]);

                // Record initial history log
                $hist = $db->prepare("
                    INSERT INTO complaint_history (complaint_id, old_status, new_status, remarks, updated_by, created_at)
                    VALUES (?, NULL, 'Submitted', 'Complaint submitted by student', ?, ?)
                ");
                $hist->execute([$complaintId, $user['name'] . ' (' . $user['student_id'] . ')', $now]);

                $db->commit();

                setFlash('success', 'Complaint ' . $complaintId . ' successfully lodged!');
                header('Location: track_complaint.php?id=' . urlencode($complaintId));
                exit;

            } catch (PDOException $e) {
                if ($db->inTransaction()) $db->rollBack();
                $error = 'Failed to submit complaint: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 820px; margin: 0 auto;">
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Lodge Maintenance Complaint</span>
        </div>

        <p style="font-size: 14px; color: #64748b; margin-bottom: 22px;">
            Please provide accurate details about the facility issue. Our maintenance team will review the ticket, assign technicians, and dispatch repairs.
        </p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="submit_complaint.php" enctype="multipart/form-data">
            
            <!-- Complaint Title (Full Width) -->
            <div class="form-group">
                <label for="title">Complaint Title: <span style="color: #dc2626;">*</span></label>
                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($title); ?>" placeholder="e.g. Overhead projector lamp flickering in Lab 304" required>
            </div>

            <!-- Category & Priority (Side-by-Side on Desktop) -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                <div class="form-group">
                    <label for="category">Category: <span style="color: #dc2626;">*</span></label>
                    <select id="category" name="category" required>
                        <option value="">-- Select Issue Category --</option>
                        <?php foreach ($allowedCategories as $cat): ?>
                            <option value="<?php echo $cat; ?>" <?php echo ($category === $cat) ? 'selected' : ''; ?>>
                                <?php echo $cat; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="priority">Priority Level: <span style="color: #dc2626;">*</span></label>
                    <select id="priority" name="priority" required>
                        <option value="Low" <?php echo ($priority === 'Low') ? 'selected' : ''; ?>>Low (Routine fix, no immediate disruption)</option>
                        <option value="Medium" <?php echo ($priority === 'Medium') ? 'selected' : ''; ?>>Medium (Disrupts normal daily activity)</option>
                        <option value="High" <?php echo ($priority === 'High') ? 'selected' : ''; ?>>High (Safety hazard / urgent stoppage)</option>
                    </select>
                </div>
            </div>

            <!-- Exact Location (Full Width) -->
            <div class="form-group">
                <label for="location">Exact Campus Location: <span style="color: #dc2626;">*</span></label>
                <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($location); ?>" placeholder="e.g. Science Block A, 2nd Floor, Room 208 or Hostel 2 Room 104" required>
                <p class="field-help">Specify building name, floor number, and room/corridor so technicians can easily locate the fault.</p>
            </div>

            <!-- Description (Large Textarea) -->
            <div class="form-group">
                <label for="description">Detailed Problem Description: <span style="color: #dc2626;">*</span></label>
                <textarea id="description" name="description" rows="5" placeholder="Explain the symptoms, when the problem started, and any damage observed..." required><?php echo htmlspecialchars($description); ?></textarea>
            </div>

            <!-- File Upload Section -->
            <div class="form-group" style="background: #f8fafc; border: 1px dashed #cbd5e1; padding: 18px; border-radius: 4px;">
                <label for="complaintImage" style="margin-bottom: 6px;">Attach Issue Photo (Optional):</label>
                <input type="file" id="complaintImage" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
                <p class="field-help">Accepted formats: JPG, PNG, WEBP, GIF (Max size: 3MB). Photos help staff carry appropriate spare parts.</p>

                <div id="imagePreviewContainer" style="display: none; margin-top: 12px;">
                    <p style="font-size: 13px; font-weight: bold; margin-bottom: 5px; color: #0b3c68;">Photo Preview:</p>
                    <img id="imagePreview" src="#" alt="Preview" style="max-width: 240px; max-height: 180px; border: 1px solid #cbd5e1; border-radius: 4px; object-fit: contain; background: #fff;">
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 14px; margin-top: 25px; align-items: center;">
                <button type="submit" class="btn btn-primary" style="padding: 12px 28px; font-size: 15px;">Submit Ticket</button>
                <a href="dashboard.php" class="btn btn-secondary" style="padding: 12px 24px; font-size: 15px;">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
