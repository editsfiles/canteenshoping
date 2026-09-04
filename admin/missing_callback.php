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
                $message = "<div class='alert-material success'><i class='fa-solid fa-circle-check'></i> Missing Callback Resolved: Order #$orderId is marked as PAID / Completed!</div>";
            } else {
                $statusText = !empty($uroStatus) ? $uroStatus : "UNRESOLVED / NOT PAID";
                $message = "<div class='alert-material warning'><i class='fa-solid fa-triangle-exclamation'></i> Gateway reports status: <strong>$statusText</strong> for Order #$orderId. Not yet paid.</div>";
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
    $message = "<div class='alert-material success'><i class='fa-solid fa-bolt'></i> Bulk Callback Sync Completed: <strong>$reconciledCount</strong> missing callback(s) recovered and marked Paid.</div>";
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
        $message = "<div class='alert-material success'><i class='fa-solid fa-circle-check'></i> Order #$orderId manually confirmed and marked Paid!</div>";
    }
}

// Fetch all pending orders
$pendingOrders = mysqli_query($conn, "SELECT orders.*, users.name as customer_name, users.email as customer_email FROM orders LEFT JOIN users ON orders.user_id = users.id WHERE orders.status = 'Pending' ORDER BY orders.id DESC");

// Read recent webhook log
$logFile = "../webhook_log.txt";
$webhookLogs = file_exists($logFile) ? file_get_contents($logFile) : "No webhook activity recorded yet.";
$webhookLogs = mb_substr($webhookLogs, -3000); // Last 3KB

$activePage = 'missing_callback';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missing Callback & Reconcile - College Canteen Admin</title>
    <!-- Material Font & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <!-- Standardized Admin Material CSS -->
    <link rel="stylesheet" href="css/admin_material.css">
</head>
<body>

<?php include("header_nav.php"); ?>

<div class="admin-container">
    <div class="admin-header-row">
        <div>
            <h1 class="admin-page-title"><i class="fa-solid fa-bolt"></i> Missing Callback Recovery</h1>
            <p class="admin-subtitle">Reconcile pending payment transactions and force sync with UroPay Gateway</p>
        </div>
        <form method="POST" style="margin:0;">
            <button type="submit" name="reconcile_all" class="btn-material btn-primary">
                <i class="fa-solid fa-arrows-rotate"></i> Auto-Sync All Gateway Callbacks
            </button>
        </form>
    </div>

    <?php if (!empty($message)): ?>
        <div style="margin-bottom: 20px;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- PENDING CALLBACKS TABLE CARD -->
    <div class="table-card">
        <div style="padding: 18px 24px; border-bottom: 1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="font-size:16px; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-clock-rotate-left" style="color:#d97706;"></i> Unconfirmed / Pending Orders
            </h3>
            <span style="font-size:13px; color:#64748b; font-weight:600;">
                Pending count: <?php echo mysqli_num_rows($pendingOrders); ?>
            </span>
        </div>

        <div class="table-responsive">
            <table class="material-table">
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
                    <?php if ($pendingOrders && mysqli_num_rows($pendingOrders) > 0): ?>
                        <?php while ($o = mysqli_fetch_assoc($pendingOrders)): ?>
                            <tr>
                                <td><strong>#<?php echo $o['id']; ?></strong></td>
                                <td>
                                    <div style="font-weight:600; color:#0f172a;"><?php echo htmlspecialchars($o['customer_name'] ?? 'N/A'); ?></div>
                                    <small style="color:#64748b; font-size:12px;"><?php echo htmlspecialchars($o['customer_email'] ?? ''); ?></small>
                                </td>
                                <td><strong style="color:#ea580c; font-size:15px;">₹<?php echo number_format((float)$o['total_amount'], 2); ?></strong></td>
                                <td><code style="background:#f1f5f9; padding:3px 8px; border-radius:6px; font-size:12px; color:#0369a1;"><?php echo htmlspecialchars($o['payment_id'] ?: ($o['merchant_order_id'] ?? 'N/A')); ?></code></td>
                                <td><?php echo date("d M Y, h:i A", strtotime($o['order_date'])); ?></td>
                                <td>
                                    <span class="badge-status badge-pending">
                                        <i class="fa-regular fa-clock"></i> Pending
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex; gap:8px; align-items:center;">
                                        <a href="missing_callback.php?sync_id=<?php echo $o['id']; ?>" class="btn-material btn-primary" style="padding:6px 12px; font-size:12px;" title="Query Gateway Status">
                                            <i class="fa-solid fa-bolt"></i> Check Gateway
                                        </a>
                                        <form method="POST" style="display:inline; margin:0;">
                                            <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                            <button type="submit" name="manual_confirm" class="btn-material btn-success" style="padding:6px 12px; font-size:12px;" onclick="return confirm('Force confirm Order #<?php echo $o['id']; ?> as PAID?');">
                                                <i class="fa-solid fa-check"></i> Force Paid
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:35px 20px; color:#64748b;">
                                <i class="fa-solid fa-circle-check" style="color:#16a34a; font-size:32px; display:block; margin-bottom:10px;"></i>
                                <strong style="font-size:16px; color:#0f172a;">All Orders Reconciled!</strong>
                                <p style="margin-top:4px; font-size:13px;">No pending callbacks found. All active orders are up to date.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- LIVE WEBHOOK LOGS -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">
                <i class="fa-solid fa-terminal" style="color:#0284c7;"></i> Live Webhook Activity Logs
            </h3>
            <span style="font-size:12px; color:#64748b;"><i class="fa-regular fa-file-lines"></i> webhook_log.txt (Last 3KB)</span>
        </div>
        <div class="log-terminal"><?php echo htmlspecialchars($webhookLogs); ?></div>
    </div>
</div>

</body>
</html>
