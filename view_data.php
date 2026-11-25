<?php
/**
 * CampusFix — Database Records Viewer (Admin / Development Tool)
 * 
 * Allows authorized administrators and developers to inspect live SQLite records,
 * view schemas, search rows, and verify data persistence.
 * Passwords are masked for security.
 */

// Disable browser caching for live database queries
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Accessible via direct URL for project inspection and demonstration
$db = getDB();

// Available tables
$allowedTables = ['users', 'complaints', 'complaint_history', 'feedback', 'contact_messages'];
$activeTable = trim($_GET['table'] ?? 'users');

if (!in_array($activeTable, $allowedTables)) {
    $activeTable = 'users';
}

$search = trim($_GET['q'] ?? '');

// Fetch total count
$totalCount = $db->query("SELECT COUNT(*) FROM {$activeTable}")->fetchColumn();

// Fetch records with optional search
$sql = "SELECT * FROM {$activeTable}";
$params = [];

if (!empty($search)) {
    // Determine table columns dynamically
    $colStmt = $db->query("PRAGMA table_info({$activeTable})");
    $cols = $colStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $whereParts = [];
    foreach ($cols as $c) {
        $colName = $c['name'];
        if ($colName !== 'password') {
            $whereParts[] = "{$colName} LIKE ?";
            $params[] = '%' . $search . '%';
        }
    }
    if (!empty($whereParts)) {
        $sql .= " WHERE " . implode(" OR ", $whereParts);
    }
}

$sql .= " ORDER BY id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Viewer — CampusFix</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .table-nav {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .table-nav a {
            padding: 10px 18px;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #0b3c68;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 700;
            transition: all 0.15s;
        }
        .table-nav a:hover {
            background: #e2e8f0;
        }
        .table-nav a.active-tab {
            background: #0b3c68;
            color: #ffffff;
            border-color: #0b3c68;
        }
        .search-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>

    <header class="site-header">
        <h1>CampusFix Database Inspector</h1>
        <p>Live SQLite Data Viewer &bull; File: <code>database/campusfix.db</code></p>
    </header>

    <nav class="site-nav">
        <div class="nav-wrap">
            <ul class="nav-links">
                <li><a href="home.html">Home</a></li>
                <?php if (isLoggedIn() && isStudent()): ?>
                    <li><a href="student/dashboard.php">Student Dashboard</a></li>
                <?php endif; ?>
                <?php if (isLoggedIn() && isAdmin()): ?>
                    <li><a href="admin/dashboard.php">Admin Dashboard</a></li>
                    <li><a href="admin/manage_complaints.php">Manage Complaints</a></li>
                    <li><a href="admin/reports.php">Reports</a></li>
                <?php endif; ?>
                <li><a href="view_data.php" class="active">DB Viewer</a></li>
            </ul>
            <div class="user-nav-status">
                <?php if (isLoggedIn()): ?>
                    <span>Role: <strong><?php echo htmlspecialchars($_SESSION['user_role'] ?? ''); ?></strong></span>
                    <a href="logout.php" class="btn btn-secondary btn-sm">Logout</a>
                <?php else: ?>
                    <a href="login.html" class="btn btn-secondary btn-sm">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="container">

        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">SQLite Table Records</span>
                <span style="font-size: 14px; color: #64748b;">
                    Total Records in <strong><?php echo htmlspecialchars($activeTable); ?></strong>: <strong><?php echo (int)$totalCount; ?></strong>
                </span>
            </div>

            <!-- Table Navigation Tabs -->
            <div class="table-nav">
                <?php foreach ($allowedTables as $tab): ?>
                    <a href="view_data.php?table=<?php echo $tab; ?>" class="<?php echo ($activeTable === $tab) ? 'active-tab' : ''; ?>">
                        <?php echo $tab; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Search and Refresh Bar -->
            <form method="GET" action="view_data.php" class="search-bar">
                <input type="hidden" name="table" value="<?php echo htmlspecialchars($activeTable); ?>">
                <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search records in <?php echo htmlspecialchars($activeTable); ?>..." style="flex: 1; min-width: 240px; min-height: 44px; font-size: 15px;">
                <button type="submit" class="btn btn-primary" style="padding: 10px 22px;">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="view_data.php?table=<?php echo htmlspecialchars($activeTable); ?>" class="btn btn-secondary" style="padding: 10px 18px;">Clear</a>
                <?php endif; ?>
                <a href="view_data.php?table=<?php echo htmlspecialchars($activeTable); ?>" class="btn btn-secondary" style="padding: 10px 18px;">Refresh Table</a>
            </form>

            <!-- Records Table -->
            <?php if (empty($rows)): ?>
                <p style="text-align: center; color: #64748b; padding: 40px; font-size: 15px;">
                    <?php echo !empty($search) ? 'No records match search: "' . htmlspecialchars($search) . '"' : 'Table is currently empty.'; ?>
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="grid-table">
                        <thead>
                            <tr>
                                <?php foreach (array_keys($rows[0]) as $column): ?>
                                    <th><?php echo htmlspecialchars($column); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <?php foreach ($row as $colName => $val): ?>
                                        <td>
                                            <?php 
                                                if ($colName === 'password') {
                                                    echo '<span style="color: #94a3b8; font-style: italic; font-size: 13px;">[Protected Hash]</span>';
                                                } elseif (($colName === 'created_at' || $colName === 'updated_at') && !empty($val)) {
                                                    $ts = strtotime($val);
                                                    echo '<strong>' . htmlspecialchars(date('d M Y, h:i:s A', $ts)) . '</strong>';
                                                    echo '<br><span style="font-size: 11px; color: #94a3b8;">(24h: ' . htmlspecialchars($val) . ')</span>';
                                                } else {
                                                    echo htmlspecialchars((string)$val);
                                                }
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div style="margin-top: 20px; font-size: 13px; color: #64748b;">
                Note: Passwords are encrypted with one-way BCrypt hashes and are masked in this inspector for security.
            </div>
        </div>

    </main>

    <footer class="site-footer">
        <p><strong>CampusFix</strong> &copy; 2026 &mdash; Database Inspection Utility</p>
    </footer>

</body>
</html>
