<?php
/**
 * Live Excel Data Feed - Registered Students & App Users
 * 
 * Provides live real-time synchronization of registered students/customers
 * directly into Microsoft Excel, Google Sheets, or Power BI.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../php/db.php';

// Access Control: Allow if Admin is logged in, OR valid Sync Key provided, OR accessed locally / private LAN
$validKey = 'canteen_live_sync_2026';
$providedKey = $_GET['key'] ?? '';
$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocal = in_array($remoteAddr, ['127.0.0.1', '::1', 'localhost'], true) || 
           (!empty($_SERVER['SERVER_ADDR']) && $remoteAddr === $_SERVER['SERVER_ADDR']) ||
           preg_match('/^(192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $remoteAddr);

$isAuthorized = isset($_SESSION['admin']) || ($providedKey === $validKey) || $isLocal;

if (!$isAuthorized) {
    http_response_code(401);
    die("<h3>Unauthorized Access</h3><p>Please log in as admin or provide a valid sync key: ?key=$validKey</p>");
}

// Format parameter: html (default table), csv, json, or iqy (Excel Web Query connection)
$format = strtolower(trim($_GET['format'] ?? 'html'));

// ─────────────────────────────────────────────────────────────────────────────
// 0. EXCEL WEB QUERY CONNECTION FILE (.iqy)
// ─────────────────────────────────────────────────────────────────────────────
if ($format === 'iqy') {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = $protocol . $host . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $feedUrl = $baseUrl . '/export_users_live.php?key=' . $validKey;

    header("Content-Type: text/x-ms-iqy; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"Canteen_Registered_Users_Live.iqy\"");
    header("Cache-Control: no-cache, no-store, must-revalidate");

    echo "WEB\r\n";
    echo "1\r\n";
    echo $feedUrl . "\r\n\r\n";
    echo "Selection=registered_users_table\r\n";
    echo "Formatting=All\r\n";
    echo "PreFormattedTextToColumns=True\r\n";
    echo "ConsecutiveDelimitersAsOne=True\r\n";
    echo "SingleBlockTextImport=False\r\n";
    echo "DisableDateRecognition=False\r\n";
    echo "DisableRedirections=False\r\n";
    exit();
}

// Ensure no caching so Excel always gets live data
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Query all registered users with live order aggregates
$sql = "SELECT 
            u.id AS student_id,
            u.name AS student_name,
            u.regno AS register_number,
            u.department,
            u.email,
            COALESCE(u.phone, 'N/A') AS mobile_number,
            u.created_at AS registered_at,
            COUNT(o.id) AS total_orders,
            SUM(CASE WHEN o.status = 'Completed' THEN 1 ELSE 0 END) AS completed_orders,
            COALESCE(SUM(CASE WHEN o.status = 'Completed' THEN o.total_amount ELSE 0 END), 0) AS total_spent,
            MAX(o.order_date) AS last_order_date
        FROM users u
        LEFT JOIN orders o ON u.id = o.user_id
        GROUP BY u.id, u.name, u.regno, u.department, u.email, u.phone, u.created_at
        ORDER BY u.id DESC";

$result = mysqli_query($conn, $sql);
$rows = [];
if ($result) {
    while ($r = mysqli_fetch_assoc($result)) {
        $rows[] = $r;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. CSV FORMAT
// ─────────────────────────────────────────────────────────────────────────────
if ($format === 'csv') {
    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"canteen_registered_students_" . date('Y-m-d_His') . ".csv\"");
    
    // Output UTF-8 BOM for Excel UTF-8 compatibility
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'Student ID',
        'Student Name',
        'Register Number',
        'Department',
        'Email Address',
        'Mobile Number',
        'Registered Date & Time',
        'Total Orders',
        'Completed Orders',
        'Total Spent (INR)',
        'Last Order Date',
        'Registration Source'
    ]);

    foreach ($rows as $row) {
        fputcsv($output, [
            $row['student_id'],
            $row['student_name'],
            $row['register_number'] ?: 'N/A',
            $row['department'] ?: 'General',
            $row['email'],
            $row['mobile_number'],
            $row['registered_at'],
            (int)$row['total_orders'],
            (int)$row['completed_orders'],
            number_format((float)$row['total_spent'], 2, '.', ''),
            $row['last_order_date'] ?: 'No orders yet',
            'Mobile App & Web'
        ]);
    }

    fclose($output);
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. JSON FORMAT
// ─────────────────────────────────────────────────────────────────────────────
if ($format === 'json') {
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode([
        'status' => 'success',
        'generated_at' => date('Y-m-d H:i:s'),
        'total_registered_users' => count($rows),
        'data' => $rows
    ], JSON_PRETTY_PRINT);
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. HTML TABLE FORMAT (DEFAULT: Native for Excel "Get Data -> From Web" and .iqy)
// ─────────────────────────────────────────────────────────────────────────────
header("Content-Type: text/html; charset=UTF-8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registered Students - Live Database Feed</title>
    <style>
        body {
            font-family: Calibri, 'Segoe UI', Arial, sans-serif;
            margin: 20px;
            background-color: #f8fafc;
            color: #1e293b;
        }
        .header-bar {
            background: #1e293b;
            color: #ffffff;
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .title {
            font-size: 20px;
            font-weight: bold;
        }
        .meta {
            font-size: 13px;
            color: #94a3b8;
        }
        .badge {
            background: #10b981;
            color: white;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        th {
            background-color: #f1f5f9;
            color: #334155;
            text-align: left;
            padding: 12px 14px;
            font-size: 13px;
            font-weight: 700;
            border-bottom: 2px solid #e2e8f0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        td {
            padding: 12px 14px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            color: #334155;
        }
        tr:hover {
            background-color: #f8fafc;
        }
        .num {
            text-align: right;
        }
        .currency {
            font-weight: 600;
            color: #16a34a;
        }
    </style>
</head>
<body>

    <div class="header-bar">
        <div>
            <div class="title">College Canteen - Registered Students & Users</div>
            <div class="meta">Live Database Sync Feed &bull; Last Generated: <?php echo date('d-m-Y H:i:s'); ?> (Auto-Refreshed)</div>
        </div>
        <div>
            <span class="badge">&bull; Live Database Connected</span>
            <span style="margin-left: 10px; font-size: 14px; font-weight: bold;">Total: <?php echo count($rows); ?> Registered</span>
        </div>
    </div>

    <!-- MAIN TABLE FOR EXCEL WEB QUERY -->
    <table id="registered_users_table" border="1">
        <thead>
            <tr>
                <th style="width: 80px;">Student ID</th>
                <th>Student Name</th>
                <th>Register Number</th>
                <th>Department</th>
                <th>Email Address</th>
                <th>Mobile Number</th>
                <th>Registration Date</th>
                <th class="num">Total Orders</th>
                <th class="num">Completed Orders</th>
                <th class="num">Total Spent (₹)</th>
                <th>Last Order Date</th>
                <th>Registration Source</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($rows)): ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><strong>#<?php echo htmlspecialchars($row['student_id']); ?></strong></td>
                        <td><strong><?php echo htmlspecialchars($row['student_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['register_number'] ?: 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['department'] ?: 'General'); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><strong style="color: #0369a1; font-family: monospace;"><?php echo htmlspecialchars($row['mobile_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars(date('d-m-Y H:i', strtotime($row['registered_at']))); ?></td>
                        <td class="num"><?php echo (int)$row['total_orders']; ?></td>
                        <td class="num"><?php echo (int)$row['completed_orders']; ?></td>
                        <td class="num currency">₹<?php echo number_format((float)$row['total_spent'], 2); ?></td>
                        <td><?php echo !empty($row['last_order_date']) ? htmlspecialchars(date('d-m-Y H:i', strtotime($row['last_order_date']))) : 'No orders yet'; ?></td>
                        <td>App & Web</td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="12" style="text-align: center; padding: 20px; color: #94a3b8;">No registered students found in database.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>
