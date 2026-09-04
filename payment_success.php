<?php
session_start();
include("php/db.php");

$localOrderId = (int)($_GET['order_id'] ?? ($_SESSION['local_order_id'] ?? 0));
$totalAmount = (float)($_SESSION['order_amount'] ?? 0);
$merchantOrderId = $_SESSION['merchant_order_id'] ?? '';
$uropayOrderId = $_GET['uropay_id'] ?? ($_SESSION['uropay_order_id'] ?? '');

// If local order ID is not provided, try to find by UroPay Ref ID
if ($localOrderId <= 0 && !empty($uropayOrderId)) {
    $stmt = mysqli_prepare($conn, "SELECT id, total_amount, payment_id, status FROM orders WHERE payment_id = ? OR merchant_order_id = ? ORDER BY id DESC LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ss", $uropayOrderId, $uropayOrderId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && $row = mysqli_fetch_assoc($res)) {
            $localOrderId = (int)$row['id'];
            $totalAmount = (float)$row['total_amount'];
        }
        mysqli_stmt_close($stmt);
    }
}

// Fetch full order details
$order = null;
if ($localOrderId > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM orders WHERE id = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $localOrderId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && mysqli_num_rows($res) > 0) {
            $order = mysqli_fetch_assoc($res);
            $totalAmount = (float)$order['total_amount'];
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment Successful - College Canteen</title>
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
    background: linear-gradient(135deg, #f0fdf4, #e0f2fe, #f8fafc);
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    padding: 20px;
}

.success-box {
    width: 480px;
    max-width: 100%;
    background: white;
    padding: 40px 30px;
    border-radius: 24px;
    text-align: center;
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
    animation: scaleIn 0.5s cubic-bezier(0.16, 1, 0.3, 1);
    border: 1px solid rgba(0,0,0,0.05);
}

@keyframes scaleIn {
    from { opacity: 0; transform: scale(0.92); }
    to { opacity: 1; transform: scale(1); }
}

.icon-wrapper {
    width: 80px;
    height: 80px;
    background: #dcfce7;
    color: #16a34a;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 38px;
    margin: 0 auto 20px;
    box-shadow: 0 8px 20px rgba(22, 163, 74, 0.2);
    animation: popIcon 0.6s ease;
}

@keyframes popIcon {
    0% { transform: scale(0); }
    70% { transform: scale(1.15); }
    100% { transform: scale(1); }
}

h1 {
    font-size: 24px;
    color: #1e293b;
    margin-bottom: 8px;
}

p.subtext {
    font-size: 14px;
    color: #64748b;
    margin-bottom: 24px;
}

.order-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 18px 20px;
    margin-bottom: 25px;
    text-align: left;
}

.order-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    font-size: 14px;
    color: #475569;
    border-bottom: 1px solid #f1f5f9;
}

.order-row:last-child {
    border-bottom: none;
    padding-top: 12px;
    font-size: 16px;
    font-weight: 700;
    color: #16a34a;
}

.badge-paid {
    background: #dcfce7;
    color: #15803d;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.badge-prep {
    background: #e0f2fe;
    color: #0369a1;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.btn-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 13px;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 600;
    text-decoration: none;
    transition: 0.2s ease;
    border: none;
    cursor: pointer;
}

.btn-primary {
    background: #2563eb;
    color: white;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
}

.btn-primary:hover {
    background: #1d4ed8;
    transform: translateY(-1px);
}

.btn-secondary {
    background: #f1f5f9;
    color: #334155;
}

.btn-secondary:hover {
    background: #e2e8f0;
}

.auto-redirect {
    margin-top: 20px;
    font-size: 12px;
    color: #94a3b8;
}
</style>
</head>
<body>

<div class="success-box">

    <div class="icon-wrapper">
        <i class="fa-solid fa-check"></i>
    </div>

    <h1>Payment Successful!</h1>
    <p class="subtext">Your canteen food order has been placed and confirmed.</p>

    <?php
        $orderDate = !empty($order['order_date']) ? strtotime($order['order_date']) : time();
        $formattedTime = date("d M Y, h:i:s A", $orderDate);
        $bankUtr = !empty($order['bank_utr']) ? $order['bank_utr'] : (!empty($order['payment_id']) ? $order['payment_id'] : 'UPI Payment');
    ?>

    <div class="order-card">
        <div class="order-row">
            <span>Order ID:</span>
            <strong>#<?php echo htmlspecialchars((string)($localOrderId > 0 ? $localOrderId : 'N/A')); ?></strong>
        </div>
        <div class="order-row">
            <span>Payment Status:</span>
            <span class="badge-paid">✅ Paid (UPI)</span>
        </div>
        <div class="order-row">
            <span>Received Time:</span>
            <strong style="color: #0f172a; font-size: 13px;">
                <i class="fa-regular fa-clock" style="color: #2563eb;"></i> <?php echo htmlspecialchars($formattedTime); ?>
            </strong>
        </div>
        <?php if (!empty($order['bank_utr'])) { ?>
        <div class="order-row">
            <span>Bank Ref / UTR:</span>
            <strong style="color: #64748b; font-size: 12px;"><?php echo htmlspecialchars($order['bank_utr']); ?></strong>
        </div>
        <?php } ?>
        <div class="order-row">
            <span>Kitchen Status:</span>
            <span class="badge-prep">🍳 Preparing</span>
        </div>
        <div class="order-row">
            <span>Total Paid:</span>
            <span>₹<?php echo number_format($totalAmount, 2); ?></span>
        </div>
    </div>

    <div class="btn-group">
        <?php if ($localOrderId > 0) { ?>
            <a href="invoice.php?order_id=<?php echo $localOrderId; ?>" class="btn btn-primary">
                <i class="fa-solid fa-file-invoice"></i> View & Print Invoice
            </a>
        <?php } ?>

        <a href="my_orders.php" class="btn btn-secondary">
            <i class="fa-solid fa-receipt"></i> Track in My Orders
        </a>

        <a href="menu.php" class="btn btn-secondary">
            <i class="fa-solid fa-utensils"></i> Back to Menu
        </a>
    </div>

    <div class="auto-redirect">
        Auto-redirecting to My Orders in <span id="countdown">6</span> seconds...
    </div>

</div>

<script>
let seconds = 6;
const countEl = document.getElementById("countdown");
const timer = setInterval(function() {
    seconds--;
    if (countEl) countEl.innerText = seconds;
    if (seconds <= 0) {
        clearInterval(timer);
        window.location.href = "my_orders.php";
    }
}, 1000);
</script>

</body>
</html>