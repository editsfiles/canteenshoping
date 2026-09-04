<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

include("../php/db.php");
include("../config_uropay.php");

$message = "";
$reconciledCount = 0;

// 1. Single Order Callback Sync
if (isset($_GET['sync_id'])) {
    $orderId = (int)$_GET['sync_id'];
    $stmt = mysqli_prepare($conn, "SELECT id, payment_id, merchant_order_id, status FROM orders WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $orderId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    
    if ($res && $order = mysqli_fetch_assoc($res)) {
        $refId = $order['payment_id'] ?: $order['merchant_order_id'];
        if (!empty($refId)) {
            $url = UROPAY_API_URL . "/order/status/" . rawurlencode($refId);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => [
                    "Accept: application/json",
                    "X-API-KEY: " . UROPAY_API_KEY
                ]
            ]);
            $resp = curl_exec($ch);
            curl_close($ch);
            
            $data = json_decode($resp, true);
            $uroData = $data['data'] ?? $data;
            $uroStatus = strtoupper(trim((string)($uroData['orderStatus'] ?? ($uroData['status'] ?? ''))));
            $bankUtr = $uroData['referenceNumber'] ?? ($uroData['transactionId'] ?? ($uroData['utr'] ?? ''));
            
            $isPaid = in_array($uroStatus, ["COMPLETED", "SUCCESS", "SUCCESSFUL", "PAID", "CAPTURED", "SETTLED", "APPROVED"], true);
            
            if ($isPaid) {
                $upd = mysqli_prepare($conn, "UPDATE orders SET status = 'Completed', food_status = 'Preparing', bank_utr = CASE WHEN ? != '' THEN ? ELSE bank_utr END WHERE id = ?");
                mysqli_stmt_bind_param($upd, "ssi", $bankUtr, $bankUtr, $orderId);
                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);
                $message = "<div class='alert success'>✅ Missing Callback Resolved: Order #$orderId is marked as PAID / Completed!</div>";
            } else {
                $message = "<div class='alert warning'>⚠️ Gateway reports status: <strong>$uroStatus</strong> for Order #$orderId. Not yet paid.</div>";
            }
        }
    }
    mysqli_stmt_close($stmt);
}

// 2. Bulk Reconcile All Pending Orders
if (isset($_POST['reconcile_all'])) {
    $resPending = mysqli_query($conn, "SELECT id, payment_id, merchant_order_id FROM orders WHERE status = 'Pending' AND order_date >= DATE_SUB(NOW(), INTERVAL 48 HOUR)");
    while ($row = mysqli_fetch_assoc($resPending)) {
        $refId = $row['payment_id'] ?: $row['merchant_order_id'];
        if (empty($refId)) continue;
        
        $url = UROPAY_API_URL . "/order/status/" . rawurlencode($refId);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_HTTPHEADER => ["Accept: application/json", "X-API-KEY: " . UROPAY_API_KEY]
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($resp, true);
        $uroData = $data['data'] ?? $data;
        $uroStatus = strtoupper(trim((string)($uroData['orderStatus'] ?? ($uroData['status'] ?? ''))));
        $bankUtr = $uroData['referenceNumber'] ?? ($uroData['transactionId'] ?? ($uroData['utr'] ?? ''));
        
        if (in_array($uroStatus, ["COMPLETED", "SUCCESS", "SUCCESSFUL", "PAID", "CAPTURED", "SETTLED", "APPROVED"], true)) {
            $oid = (int)$row['id'];
            $upd = mysqli_prepare($conn, "UPDATE orders SET status = 'Completed', food_status = 'Preparing', bank_utr = CASE WHEN ? != '' THEN ? ELSE bank_utr END WHERE id = ?");
            mysqli_stmt_bind_param($upd, "ssi", $bankUtr, $bankUtr, $oid);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
            $reconciledCount++;
        }
    }
    $message = "<div class='alert success'>⚡ Bulk Callback Sync Completed: <strong>$reconciledCount</strong> missing callback(s) recovered and marked Paid.</div>";
}

// 3. Manual Force Mark as Paid with UTR
if (isset($_POST['manual_confirm'])) {
    $orderId = (int)$_POST['order_id'];
    $bankUtr = trim($_POST['bank_utr'] ?? '');
    
    if ($orderId > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE orders SET status = 'Completed', food_status = 'Preparing', bank_utr = CASE WHEN ? != '' THEN ? ELSE bank_utr END WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ssi", $bankUtr, $bankUtr, $orderId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $message = "<div class='alert success'>✅ Order #$orderId manually confirmed and marked Paid!</div>";
    }
}

// Fetch all pending orders
$pendingOrders = mysqli_query($conn, "SELECT orders.*, users.name as customer_name, users.email as customer_email FROM orders LEFT JOIN users ON orders.user_id = users.id WHERE orders.status = 'Pending' ORDER BY orders.id DESC");

// Read recent webhook log
$logFile = "../webhook_log.txt";
$webhookLogs = file_exists($logFile) ? file_get_contents($logFile) : "No webhook activity recorded yet.";
$webhookLogs = mb_substr($webhookLogs, -3000); // Last 3KB
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Missing Callback & Reconcile - Canteen Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Poppins', sans-serif; }
body { background: #f1f5f9; color: #1e293b; padding-bottom: 50px; }
header { background: #1e293b; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
header h2 { font-size: 20px; font-weight: 700; }
nav a { color: #cbd5e1; text-decoration: none; margin-left: 18px; font-size: 14px; font-weight: 500; }
nav a:hover, nav a.active { color: white; }
.container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
.card { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 25px; }
.card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; }
.card-header h3 { font-size: 18px; color: #0f172a; display: flex; align-items: center; gap: 8px; }
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; border-radius: 10px; font-size: 13px; font-weight: 600; text-decoration: none; cursor: pointer; border: none; transition: 0.2s; }
.btn-primary { background: #2563eb; color: white; }
.btn-primary:hover { background: #1d4ed8; }
.btn-success { background: #16a34a; color: white; }
.btn-success:hover { background: #15803d; }
.alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; }
.alert.success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
.alert.warning { background: #fef3c7; color: #854d0e; border: 1px solid #fde68a; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; }
th, td { padding: 12px 14px; text-align: left; font-size: 13px; border-bottom: 1px solid #f1f5f9; }
th { background: #f8fafc; color: #64748b; font-weight: 600; }
.badge-pending { background: #fef3c7; color: #d97706; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.log-box { background: #0f172a; color: #38bdf8; font-family: monospace; font-size: 12px; padding: 16px; border-radius: 12px; max-height: 250px; overflow-y: auto; white-space: pre-wrap; line-height: 1.5; }
</style>
</head>
<body>

<header>
    <h2>🍽 College Canteen Admin</h2>
    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="products.php">Products</a>
        <a href="customers.php">Customers</a>
        <a href="orders.php">Orders</a>
        <a href="missing_callback.php" class="active" style="color:#38bdf8;font-weight:700;">⚡ Missing Callback</a>
        <a href="reports.php">Reports</a>
        <a href="messages.php">Messages</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<div class="container">
    <?php echo $message; ?>

    <!-- PENDING CALLBACKS CARD -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-clock-rotate-left" style="color:#d97706;"></i> Unconfirmed / Pending Orders (Missing Callback Recovery)</h3>
            <form method="POST" style="margin:0;">
                <button type="submit" name="reconcile_all" class="btn btn-primary">
                    <i class="fa-solid fa-arrows-rotate"></i> Auto-Fetch All Gateway Callbacks
                </button>
            </form>
        </div>

        <?php if ($pendingOrders && mysqli_num_rows($pendingOrders) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Payment Ref ID</th>
                        <th>Order Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($o = mysqli_fetch_assoc($pendingOrders)): ?>
                        <tr>
                            <td><strong>#<?php echo $o['id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($o['customer_name'] ?? 'N/A'); ?></td>
                            <td><strong>₹<?php echo number_format((float)$o['total_amount'], 2); ?></strong></td>
                            <td><code><?php echo htmlspecialchars($o['payment_id'] ?? 'N/A'); ?></code></td>
                            <td><?php echo date("d M Y, h:i A", strtotime($o['order_date'])); ?></td>
                            <td><span class="badge-pending">Pending</span></td>
                            <td style="display:flex;gap:6px;">
                                <a href="missing_callback.php?sync_id=<?php echo $o['id']; ?>" class="btn btn-primary" style="padding:6px 12px;font-size:12px;">
                                    <i class="fa-solid fa-bolt"></i> Check Gateway
                                </a>
                                <form method="POST" style="display:inline;margin:0;">
                                    <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                    <button type="submit" name="manual_confirm" class="btn btn-success" style="padding:6px 12px;font-size:12px;" onclick="return confirm('Force confirm Order #<?php echo $o['id']; ?> as PAID?');">
                                        <i class="fa-solid fa-check"></i> Force Paid
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color:#64748b;font-size:14px;padding:20px 0;text-align:center;">
                <i class="fa-solid fa-circle-check" style="color:#16a34a;font-size:24px;display:block;margin-bottom:8px;"></i>
                All orders are fully reconciled! No pending callbacks found.
            </p>
        <?php endif; ?>
    </div>

    <!-- RECENT WEBHOOK LOGS CARD -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-terminal" style="color:#2563eb;"></i> Live Webhook Activity Logs</h3>
            <span style="font-size:12px;color:#64748b;">Stored in webhook_log.txt</span>
        </div>
        <div class="log-box"><?php echo htmlspecialchars($webhookLogs); ?></div>
    </div>
</div>

</body>
</html>
