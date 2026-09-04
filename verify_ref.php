<?php
session_start();
include("php/db.php");
include("config_uropay.php");

$refId = trim($_GET['ref'] ?? ($_POST['ref'] ?? ''));
$checkResult = null;
$errorMsg = null;
$updatedOrder = null;

// 1. First check if this Ref ID or Bank UTR is in local database
$stmt = mysqli_prepare($conn, "SELECT orders.*, users.name AS customer_name, users.email AS customer_email FROM orders LEFT JOIN users ON orders.user_id = users.id WHERE orders.bank_utr = ? OR orders.payment_id = ? OR orders.merchant_order_id = ? LIMIT 1");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "sss", $refId, $refId, $refId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && $row = mysqli_fetch_assoc($res)) {
        $updatedOrder = $row;
    }
    mysqli_stmt_close($stmt);
}

if (!empty($refId)) {
    // 2. Query UroPay API
    $url = UROPAY_API_URL . "/order/status/" . rawurlencode($refId);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Accept: application/json",
        "Content-Type: application/json",
        "X-API-KEY: " . UROPAY_API_KEY
    ]);
    
    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response && !$curlErr && $httpCode === 200) {
        $json = json_decode($response, true);
        if (is_array($json)) {
            $data = $json['data'] ?? $json;
            $uroStatus = strtoupper(trim((string)($data['orderStatus'] ?? ($data['status'] ?? ($data['paymentStatus'] ?? 'UNKNOWN')))));
            $refNum = $data['referenceNumber'] ?? ($data['transactionId'] ?? ($data['utr'] ?? $refId));
            
            $isPaid = in_array($uroStatus, [
                "COMPLETED", "SUCCESS", "SUCCESSFUL", "PAID", "PAYMENT_SUCCESS", 
                "PAYMENT_COMPLETED", "PAYMENT_SUCCEEDED", "TRANSACTION_SUCCESS", 
                "TRANSACTION_COMPLETED", "CAPTURED", "SETTLED", "APPROVED"
            ], true);
            
            $checkResult = [
                'raw' => $json,
                'status' => $uroStatus,
                'is_paid' => $isPaid,
                'ref' => $refId,
                'transaction_ref' => $refNum,
                'http_code' => $httpCode
            ];
            
            if ($isPaid) {
                $stmt = mysqli_prepare($conn, "UPDATE orders SET status = 'Completed', food_status = 'Preparing', payment_id = ? WHERE payment_id = ? OR merchant_order_id = ? OR bank_utr = ?");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "ssss", $refNum, $refId, $refId, $refId);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }

    // If we have a local order record
    if ($updatedOrder) {
        $localIsPaid = in_array(strtoupper(trim((string)$updatedOrder['status'])), ['COMPLETED', 'PAID', 'SUCCESS'], true);
        if (!$checkResult || $localIsPaid) {
            $checkResult = [
                'raw' => $updatedOrder,
                'status' => $localIsPaid ? 'COMPLETED' : strtoupper($updatedOrder['status']),
                'is_paid' => $localIsPaid,
                'ref' => $refId,
                'transaction_ref' => $updatedOrder['bank_utr'] ?: ($updatedOrder['payment_id'] ?: $refId),
                'http_code' => 200
            ];
            $errorMsg = null;
        }
    } elseif (!$checkResult && ($httpCode !== 200 || $curlErr)) {
        $errorMsg = "Payment Reference ID / UTR not found on gateway. Please verify the 12-digit UPI number or UroPay Order ID.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Check Payment Reference ID - College Canteen</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: 'Poppins', sans-serif;
}

body {
    background: #f1f5f9;
    color: #1e293b;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

header {
    background: #27ae60;
    color: white;
    padding: 16px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

header h2 {
    font-size: 20px;
}

header nav a {
    color: white;
    text-decoration: none;
    font-weight: 600;
    margin-left: 20px;
}

.container {
    max-width: 680px;
    width: 92%;
    margin: 40px auto;
    background: white;
    padding: 35px 30px;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
}

h1 {
    font-size: 24px;
    color: #0f172a;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
}

p.subtitle {
    font-size: 14px;
    color: #64748b;
    margin-bottom: 24px;
}

.search-form {
    display: flex;
    gap: 10px;
    margin-bottom: 25px;
}

.search-input {
    flex: 1;
    padding: 14px 18px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 15px;
    outline: none;
    transition: 0.2s;
}

.search-input:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}

.search-btn {
    padding: 14px 24px;
    background: #2563eb;
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.search-btn:hover {
    background: #1d4ed8;
}

.result-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 22px;
    margin-top: 20px;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

.status-badge {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
}

.status-badge.success {
    background: #dcfce7;
    color: #15803d;
}

.status-badge.pending {
    background: #fef3c7;
    color: #92400e;
}

.status-badge.failed {
    background: #fee2e2;
    color: #b91c1c;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-top: 18px;
    font-size: 14px;
}

.info-item {
    background: white;
    padding: 12px 14px;
    border-radius: 10px;
    border: 1px solid #edf2f7;
}

.info-item span {
    display: block;
    font-size: 12px;
    color: #64748b;
    margin-bottom: 2px;
}

.info-item strong {
    color: #1e293b;
}

.alert-box {
    padding: 14px 18px;
    border-radius: 12px;
    margin-top: 20px;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: #dcfce7;
    border: 1px solid #bbf7d0;
    color: #166534;
}

.alert-warning {
    background: #fef3c7;
    border: 1px solid #fde68a;
    color: #854d0e;
}

.alert-error {
    background: #fee2e2;
    border: 1px solid #fecaca;
    color: #991b1b;
}

.actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 18px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
}

.btn-action.primary {
    background: #16a34a;
    color: white;
}

.btn-action.secondary {
    background: #e2e8f0;
    color: #334155;
}
</style>
</head>
<body>

<header>
    <h2>🍽 College Canteen</h2>
    <nav>
        <a href="index.php">Home</a>
        <a href="menu.php">Menu</a>
        <a href="my_orders.php">My Orders</a>
    </nav>
</header>

<div class="container">
    <h1>
        <i class="fa-solid fa-magnifying-glass-dollar" style="color:#2563eb;"></i>
        Verify Payment by Reference ID
    </h1>
    <p class="subtitle">Enter the UroPay Reference ID (e.g. <code>URPYKILO252051</code>) to check bank receipt status in real time.</p>

    <form method="GET" class="search-form">
        <input 
            type="text" 
            name="ref" 
            class="search-input" 
            placeholder="Enter Payment Ref ID (e.g. URPYKILO252051)"
            value="<?php echo htmlspecialchars($refId); ?>"
            required
        >
        <button type="submit" class="search-btn">
            <i class="fa-solid fa-bolt"></i> Verify Now
        </button>
    </form>

    <?php if ($errorMsg): ?>
        <div class="alert-box alert-error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?php echo htmlspecialchars($errorMsg); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($checkResult): ?>
        <div class="result-card">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:14px;">
                <div>
                    <span style="font-size:12px; color:#64748b;">Reference ID:</span>
                    <h3 style="font-size:18px; color:#0f172a;"><?php echo htmlspecialchars($checkResult['ref']); ?></h3>
                </div>
                <div>
                    <?php if ($checkResult['is_paid']): ?>
                        <span class="status-badge success">
                            <i class="fa-solid fa-circle-check"></i> Paid / Completed
                        </span>
                    <?php elseif ($checkResult['status'] === 'CREATED' || $checkResult['status'] === 'PENDING'): ?>
                        <span class="status-badge pending">
                            <i class="fa-solid fa-clock"></i> Payment Pending
                        </span>
                    <?php else: ?>
                        <span class="status-badge failed">
                            <i class="fa-solid fa-circle-xmark"></i> <?php echo htmlspecialchars($checkResult['status']); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <span>Gateway Order Status</span>
                    <strong><?php echo htmlspecialchars($checkResult['status']); ?></strong>
                </div>
                <div class="info-item">
                    <span>Bank Transaction / UTR Ref</span>
                    <strong><?php echo htmlspecialchars((string)$checkResult['transaction_ref']); ?></strong>
                </div>

                <?php if ($updatedOrder): ?>
                    <div class="info-item">
                        <span>Local Canteen Order ID</span>
                        <strong>#<?php echo (int)$updatedOrder['id']; ?></strong>
                    </div>
                    <div class="info-item">
                        <span>Order Total Amount</span>
                        <strong style="color:#16a34a;">₹<?php echo number_format((float)$updatedOrder['total_amount'], 2); ?></strong>
                    </div>
                    <div class="info-item">
                        <span>Customer Name</span>
                        <strong><?php echo htmlspecialchars($updatedOrder['customer_name'] ?? 'N/A'); ?></strong>
                    </div>
                    <div class="info-item">
                        <span>Kitchen / Food Status</span>
                        <strong style="color:#0284c7;"><?php echo htmlspecialchars($updatedOrder['food_status'] ?? 'Preparing'); ?></strong>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($checkResult['is_paid']): ?>
                <div class="alert-box alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <div>
                        <strong>Payment Successfully Verified!</strong>
                        <p style="font-size:13px; margin-top:2px;">Bank confirmed this payment. Your order has been marked as <strong>Paid</strong> and sent to the kitchen.</p>
                    </div>
                </div>

                <div class="actions">
                    <?php if ($updatedOrder): ?>
                        <a href="invoice.php?order_id=<?php echo $updatedOrder['id']; ?>" class="btn-action primary">
                            <i class="fa-solid fa-receipt"></i> View Invoice
                        </a>
                    <?php endif; ?>
                    <a href="my_orders.php" class="btn-action secondary">
                        <i class="fa-solid fa-list"></i> My Orders
                    </a>
                </div>
            <?php else: ?>
                <div class="alert-box alert-warning">
                    <i class="fa-solid fa-info-circle"></i>
                    <div>
                        <strong>Bank Status: Payment Not Yet Received</strong>
                        <p style="font-size:13px; margin-top:2px;">If you already debited the amount, please allow 10-30 seconds for the bank network to update, then click <strong>Verify Now</strong> again.</p>
                    </div>
                </div>

                <div class="actions">
                    <a href="uropay_payment.php?order_id=<?php echo urlencode($refId); ?>" class="btn-action primary">
                        <i class="fa-solid fa-qrcode"></i> Return to QR Payment Screen
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
