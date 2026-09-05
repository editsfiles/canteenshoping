<?php
/**
 * Live Excel Sync & Export for Registered Students and Users
 * Supports:
 * - Direct Excel CSV download (?format=csv)
 * - Live Web Query HTML Table for Excel "Data -> From Web" (?format=web)
 * - Microsoft Excel Web Query (.iqy) auto-refresh connection file (?format=iqy)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../php/db.php";

// Allowed sync token for Excel Power Query (so Excel can fetch live background updates without browser cookie)
$syncKey = "canteen_live_sync";
$isAuthorized = isset($_SESSION['admin']) || (isset($_GET['key']) && $_GET['key'] === $syncKey);

if (!$isAuthorized) {
    http_response_code(403);
    die("Access denied. Admin session or valid live sync key required.");
}

$format = strtolower(trim($_GET['format'] ?? 'csv'));

// Fetch all registered users with their total orders and total spend
$sql = "
    SELECT 
        u.id,
        u.name,
        u.regno,
        u.department,
        u.email,
        u.created_at,
        COUNT(o.id) AS total_orders,
        IFNULL(SUM(CASE WHEN o.status IN ('Completed','Paid') THEN o.total_amount ELSE 0 END), 0) AS total_spent
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id
    GROUP BY u.id
    ORDER BY u.id ASC
";

$result = mysqli_query($conn, $sql);
$students = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $students[] = $row;
    }
}

// -------------------------------------------------------------
// 1. FORMAT: EXCEL WEB QUERY FILE (.iqy)
// -------------------------------------------------------------
if ($format === 'iqy') {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
             (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
             ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '/admin/export_registrations_excel.php';
    $webDataUrl = $proto . $host . $script . "?format=web&key=" . urlencode($syncKey);

    header('Content-Type: text/x-ms-iqy');
    header('Content-Disposition: attachment; filename="canteen_registrations_live.iqy"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    echo "WEB\r\n";
    echo "1\r\n";
    echo $webDataUrl . "\r\n";
    echo "\r\n";
    echo "Selection=EntirePage\r\n";
    echo "Formatting=None\r\n";
    echo "PreFormattedTextToColumns=True\r\n";
    echo "ConsecutiveDelimitersAsOne=True\r\n";
    echo "SingleBlockTextImport=False\r\n";
    echo "DisableDateRecognition=False\r\n";
    echo "DisableRedirections=False\r\n";
    exit();
}

// -------------------------------------------------------------
// 2. FORMAT: LIVE WEB HTML TABLE (FOR EXCEL "DATA -> FROM WEB")
// -------------------------------------------------------------
if ($format === 'web' || $format === 'html') {
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>College Canteen - Live Registrations Feed</title>
        <style>
            body { font-family: Calibri, Arial, sans-serif; margin: 20px; }
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #cbd5e1; padding: 8px 12px; text-align: left; }
            th { background-color: #ea580c; color: #ffffff; font-weight: bold; }
            tr:nth-child(even) { background-color: #f8fafc; }
            .number { text-align: right; }
        </style>
    </head>
    <body>
        <h2>College Canteen - Registered Students Live Feed</h2>
        <p>Last Live Sync: <?php echo date('Y-m-d H:i:s'); ?> | Total Registrations: <?php echo count($students); ?></p>
        <table id="registrationsTable">
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Full Name</th>
                    <th>Register Number</th>
                    <th>Department</th>
                    <th>Email Address</th>
                    <th>Registration Date</th>
                    <th>Total Orders</th>
                    <th>Total Spent (INR)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr><td colspan="8">No registered users found.</td></tr>
                <?php else: ?>
                    <?php foreach ($students as $s): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($s['id']); ?></td>
                            <td><?php echo htmlspecialchars($s['name']); ?></td>
                            <td><?php echo htmlspecialchars($s['regno'] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($s['department'] ?: 'General'); ?></td>
                            <td><?php echo htmlspecialchars($s['email']); ?></td>
                            <td><?php echo htmlspecialchars($s['created_at']); ?></td>
                            <td class="number"><?php echo (int)$s['total_orders']; ?></td>
                            <td class="number"><?php echo number_format((float)$s['total_spent'], 2, '.', ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    exit();
}

// -------------------------------------------------------------
// 3. FORMAT: DIRECT EXCEL CSV DOWNLOAD (DEFAULT)
// -------------------------------------------------------------
$filename = "canteen_registrations_" . date('Ymd_His') . ".csv";
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Output UTF-8 BOM so Excel opens with proper character encoding
echo "\xEF\xBB\xBF";

$fp = fopen('php://output', 'w');

// Header row
fputcsv($fp, [
    'Student ID',
    'Full Name',
    'Register Number',
    'Department',
    'Email Address',
    'Registration Date',
    'Total Orders',
    'Total Spent (INR)'
]);

foreach ($students as $s) {
    fputcsv($fp, [
        $s['id'],
        $s['name'],
        $s['regno'] ?: 'N/A',
        $s['department'] ?: 'General',
        $s['email'],
        $s['created_at'],
        (int)$s['total_orders'],
        number_format((float)$s['total_spent'], 2, '.', '')
    ]);
}

fclose($fp);
exit();
