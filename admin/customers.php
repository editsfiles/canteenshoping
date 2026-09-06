<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

include("../php/db.php");

// Delete Customer
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    if ($id > 0) {
        @mysqli_query($conn, "DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE user_id='$id')");
        @mysqli_query($conn, "DELETE FROM orders WHERE user_id='$id'");
        mysqli_query($conn, "DELETE FROM users WHERE id='$id'");
    }

    header("Location: customers.php");
    exit();
}

// Search
$search = "";
if (isset($_GET['search']) && trim($_GET['search']) != "") {
    $search = mysqli_real_escape_string($conn, trim($_GET['search']));
    $query = mysqli_query($conn, "
        SELECT * FROM users
        WHERE name LIKE '%$search%'
           OR regno LIKE '%$search%'
           OR department LIKE '%$search%'
           OR email LIKE '%$search%'
        ORDER BY id DESC
    ");
} else {
    $query = mysqli_query($conn, "SELECT * FROM users ORDER BY id DESC");
}

$activePage = 'customers';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - College Canteen Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <link rel="stylesheet" href="css/admin_material.css">
</head>
<body>

<?php include("header_nav.php"); ?>

<main class="admin-container">

    <div class="admin-header-row">
        <div>
            <h1 class="admin-page-title">
                <i class="fa-solid fa-users"></i> Registered Students & Customers
            </h1>
            <p class="admin-subtitle">View student account profiles, departments, and individual purchase histories</p>
        </div>

        <form method="GET" class="search-container">
            <i class="fa-solid fa-magnifying-glass" style="color:#94a3b8;"></i>
            <input 
                type="text" 
                name="search" 
                placeholder="Search by name, reg no, department..." 
                value="<?php echo htmlspecialchars($search); ?>"
            >
            <?php if (!empty($search)): ?>
                <a href="customers.php" style="color:#94a3b8; text-decoration:none;"><i class="fa-solid fa-xmark"></i></a>
            <?php endif; ?>
        </form>
    </div>

    <!-- LIVE EXCEL SYNC & EXPORT BAR -->
    <div style="background: linear-gradient(135deg, #0f172a, #1e293b); border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; color: #fff; box-shadow: 0 4px 16px rgba(0,0,0,0.08);">
        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px;">
            <div>
                <div style="display: flex; align-items: center; gap: 8px; font-weight: 700; font-size: 15px; margin-bottom: 4px;">
                    <i class="fa-solid fa-file-excel" style="color: #22c55e; font-size: 18px;"></i>
                    <span>Microsoft Excel Live Database Sync</span>
                    <span style="background: rgba(34, 197, 94, 0.2); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.4); font-size: 11px; padding: 2px 8px; border-radius: 9999px; font-weight: 600;">
                        <i class="fa-solid fa-circle" style="font-size: 7px; vertical-align: middle;"></i> Live Update
                    </span>
                </div>
                <div style="font-size: 12px; color: #94a3b8;">
                    Connect app & website registered students to Excel. Automatically updates when you click "Refresh All".
                </div>
            </div>
            <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
                <a href="export_users_live.php?format=iqy" class="btn-material btn-success" title="Download Excel Live Connection File">
                    <i class="fa-solid fa-bolt"></i> 1-Click Excel Sync (.iqy)
                </a>
                <a href="export_users_live.php?format=csv" class="btn-material" style="background: #0284c7; color: #ffffff;" title="Download static CSV spreadsheet">
                    <i class="fa-solid fa-file-csv"></i> Download CSV
                </a>
                <button type="button" onclick="copyExcelUrl()" class="btn-material" style="background: #334155; color: #ffffff;" title="Copy URL for Excel Data -> From Web">
                    <i class="fa-solid fa-link"></i> <span id="copyBtnText">Copy Live Feed URL</span>
                </button>
                <button type="button" onclick="toggleExcelHelp()" class="btn-material" style="background: transparent; border: 1px solid #475569; color: #cbd5e1;">
                    <i class="fa-solid fa-circle-question"></i> Help
                </button>
            </div>
        </div>

        <!-- Collapsible Excel Instructions Guide -->
        <div id="excelHelpGuide" style="display: none; margin-top: 14px; padding-top: 14px; border-top: 1px solid rgba(255,255,255,0.1); font-size: 13px; color: #e2e8f0;">
            <div style="font-weight: 600; margin-bottom: 8px; color: #38bdf8;">
                <i class="fa-solid fa-circle-info"></i> How to connect with your open Excel window:
            </div>
            <ol style="margin: 0; padding-left: 20px; line-height: 1.8;">
                <li><strong>Option A (1-Click File):</strong> Click <strong>1-Click Excel Sync (.iqy)</strong> above, or double-click <code>Canteen_Registered_Users_Live.iqy</code> on your Desktop. Excel will open and prompt to enable data connection.</li>
                <li><strong>Option B (Direct from Excel Data tab):</strong> In your open Excel window, click <strong>Data</strong> &rarr; <strong>From Web</strong> (or <strong>Get Data &rarr; From Other Sources &rarr; From Web</strong>).</li>
                <li>Paste URL: <code style="background: #0f172a; padding: 2px 6px; border-radius: 4px; color: #4ade80;">http://localhost/Canteenshoping/admin/export_users_live.php</code> and click <strong>OK</strong> &rarr; <strong>Load</strong>.</li>
                <li><strong>Live Updates:</strong> Whenever a student registers on the mobile app or website, just click <strong>Data &rarr; Refresh All</strong> (or right-click the table &rarr; <em>Refresh</em>) to pull live records!</li>
            </ol>
        </div>
    </div>

    <script>
    function copyExcelUrl() {
        var url = "<?php echo (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/export_users_live.php?key=canteen_live_sync_2026'; ?>";
        navigator.clipboard.writeText(url).then(function() {
            var btn = document.getElementById('copyBtnText');
            var orig = btn.innerText;
            btn.innerText = "Copied to Clipboard!";
            setTimeout(function() { btn.innerText = orig; }, 2500);
        }).catch(function() {
            prompt("Copy this Live Excel Feed URL:", url);
        });
    }

    function toggleExcelHelp() {
        var el = document.getElementById('excelHelpGuide');
        el.style.display = (el.style.display === 'none' || el.style.display === '') ? 'block' : 'none';
    }
    </script>

    <!-- STRUCTURED TABLE WITH RED-ORANGE HEADER -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="material-table">
                <thead>
                    <tr>
                        <th style="width:70px;">ID</th>
                        <th>Student Name</th>
                        <th>Register No</th>
                        <th>Department</th>
                        <th>Email Address</th>
                        <th style="text-align:right; width:160px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($query) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($query)): ?>
                        <tr>
                            <td><strong>#<?php echo $row['id']; ?></strong></td>
                            <td>
                                <div style="font-weight:700; color:#0f172a; font-size:15px;"><?php echo htmlspecialchars($row['name']); ?></div>
                            </td>
                            <td>
                                <span style="font-family:monospace; font-weight:600; background:#f1f5f9; padding:3px 8px; border-radius:6px; color:#334155;">
                                    <?php echo htmlspecialchars($row['regno'] ?: 'N/A'); ?>
                                </span>
                            </td>
                            <td>
                                <span style="color:#475569; font-weight:500;">
                                    <?php echo htmlspecialchars($row['department'] ?: 'General'); ?>
                                </span>
                            </td>
                            <td>
                                <span style="color:#2563eb; font-size:13px;">
                                    <i class="fa-regular fa-envelope" style="margin-right:4px;"></i><?php echo htmlspecialchars($row['email']); ?>
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <a href="customer_details.php?id=<?php echo $row['id']; ?>" class="btn-material btn-primary" style="padding:6px 12px; font-size:12px; margin-right:4px;">
                                    <i class="fa-solid fa-eye"></i> Details
                                </a>
                                <a href="customers.php?delete=<?php echo $row['id']; ?>" class="btn-material btn-danger" style="padding:6px 12px; font-size:12px;" onclick="return confirm('Delete this customer and all associated orders?');">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding:40px; color:#94a3b8;">
                                <i class="fa-solid fa-user-slash" style="font-size:36px; margin-bottom:10px; display:block; opacity:0.4;"></i>
                                No customers found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

</body>
</html>