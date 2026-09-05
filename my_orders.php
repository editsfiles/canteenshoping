<?php
session_start();

include("php/db.php");

// =========================================================
// CHECK LOGIN
// =========================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// =========================================================
// PROCESS ORDER CANCELLATION & 24-48 HR AUTOMATIC REFUND
// =========================================================
$cancelMsg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order_id'])) {
    $cancelOrderId = (int)$_POST['cancel_order_id'];

    $checkStmt = mysqli_prepare($conn, "SELECT id, total_amount, payment_id, status, food_status FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
    if ($checkStmt) {
        mysqli_stmt_bind_param($checkStmt, "ii", $cancelOrderId, $user_id);
        mysqli_stmt_execute($checkStmt);
        $checkRes = mysqli_stmt_get_result($checkStmt);

        if ($checkRes && $orderToCancel = mysqli_fetch_assoc($checkRes)) {
            $curStatus = strtolower(trim($orderToCancel['status']));
            $curFoodStatus = strtolower(trim($orderToCancel['food_status']));

            if ($curStatus === 'cancelled' || $curStatus === 'canceled') {
                $cancelMsg = "<div class='cancel-alert warning'><i class='fa-solid fa-triangle-exclamation'></i> Order #$cancelOrderId is already cancelled. Refund is processing automatically within 10 minutes.</div>";
            } elseif ($curFoodStatus === 'delivered') {
                $cancelMsg = "<div class='cancel-alert danger'><i class='fa-solid fa-circle-xmark'></i> Cannot cancel Order #$cancelOrderId: The food has already been delivered.</div>";
            } else {
                $refundNotes = "Order cancelled. Amount ₹" . number_format($orderToCancel['total_amount'], 2) . " will be refunded automatically within 10 minutes directly to your source UPI account.";
                $upd = mysqli_prepare($conn, "UPDATE orders SET status = 'Cancelled', food_status = 'Cancelled', refund_status = 'Refund Processing (Within 10 mins)', refund_notes = ? WHERE id = ? AND user_id = ?");
                if ($upd) {
                    mysqli_stmt_bind_param($upd, "sii", $refundNotes, $cancelOrderId, $user_id);
                    mysqli_stmt_execute($upd);
                    mysqli_stmt_close($upd);
                }

                // Log automatic refund request
                $logLine = "[" . date('Y-m-d H:i:s') . "] ORDER CANCELLED: Order #" . $cancelOrderId . " | Amount: ₹" . $orderToCancel['total_amount'] . " | Fast Auto-Refund Policy: Within 10 Minutes\n";
                @file_put_contents("webhook_log.txt", $logLine, FILE_APPEND);

                $cancelMsg = "<div class='cancel-alert success'>
                    <h4 style='margin:0 0 6px; font-size:16px;'><i class='fa-solid fa-circle-check'></i> Order #$cancelOrderId Cancelled Successfully</h4>
                    <p style='margin:0;'>The order has been cancelled. Your amount of <strong>₹" . number_format($orderToCancel['total_amount'], 2) . "</strong> will be <strong>refunded automatically within 10 minutes</strong> directly to your original payment method / UPI account.</p>
                </div>";
            }
        }
        mysqli_stmt_close($checkStmt);
    }
}

// =========================================================
// GET ORDERS
// =========================================================

$stmt = mysqli_prepare($conn, "SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");

if (!$stmt) {
    die("Database Error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result) {
    mysqli_stmt_close($stmt);
    die("Database Error: " . mysqli_error($conn));
}


// =========================================================
// FIND DATE COLUMN AUTOMATICALLY
// =========================================================

$columns = [];

$fieldInfo = mysqli_fetch_fields($result);

foreach ($fieldInfo as $field) {
    $columns[] = $field->name;
}

$dateColumn = null;

$possibleDateColumns = [
    "created_at",
    "order_date",
    "ordered_at",
    "date",
    "created_date"
];

foreach ($possibleDateColumns as $column) {

    if (in_array($column, $columns)) {
        $dateColumn = $column;
        break;
    }
}

// Fetch all orders into array for dual desktop / mobile rendering
$orders = [];
while ($r = mysqli_fetch_assoc($result)) {
    $orders[] = $r;
}
mysqli_stmt_close($stmt);

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>My Orders</title>

<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<style>

/* =========================================================
   VIEW INVOICE BUTTON
   ========================================================= */

.invoice-btn {

    display: inline-block;

    padding: 8px 14px;

    background: #3498db;

    color: white;

    text-decoration: none;

    border-radius: 5px;

    font-weight: bold;

    font-size: 13px;

    transition: 0.3s ease;

    white-space: nowrap;
}


.invoice-btn:hover {

    background: #2980b9;

    transform: translateY(-2px);

    box-shadow:
        0 5px 10px rgba(52, 152, 219, 0.25);

}

.cancel-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 7px 13px;
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fca5a5;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 600;
    font-size: 12px;
    cursor: pointer;
    transition: 0.2s ease;
    white-space: nowrap;
}

.cancel-btn:hover {
    background: #ef4444;
    color: #ffffff;
    border-color: #ef4444;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(239, 68, 68, 0.25);
}

.cancel-alert {
    padding: 16px 20px;
    border-radius: 10px;
    font-size: 14px;
    margin-bottom: 25px;
    line-height: 1.5;
}

.cancel-alert.success {
    background: #ecfdf5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.cancel-alert.warning {
    background: #fffbeb;
    color: #92400e;
    border: 1px solid #fde68a;
}

.cancel-alert.danger {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
}


/* =========================================================
   REAL TIME ZONE BANNER & ORDER TIMESTAMP STYLING
   ========================================================= */
.timezone-banner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #1e1b4b, #312e81, #3730a3);
    color: white;
    padding: 16px 22px;
    border-radius: 14px;
    margin-bottom: 24px;
    box-shadow: 0 6px 20px rgba(49, 46, 129, 0.25);
    flex-wrap: wrap;
    gap: 12px;
}

.tz-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.tz-pulse-dot {
    width: 12px;
    height: 12px;
    background: #34d399;
    border-radius: 50%;
    box-shadow: 0 0 0 0 rgba(52, 211, 153, 0.7);
    animation: tzPulse 1.8s infinite;
}

@keyframes tzPulse {
    0% { box-shadow: 0 0 0 0 rgba(52, 211, 153, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(52, 211, 153, 0); }
    100% { box-shadow: 0 0 0 0 rgba(52, 211, 153, 0); }
}

.tz-title {
    font-size: 16px;
    font-weight: 700;
    letter-spacing: 0.3px;
    display: block;
    color: #f8fafc;
}

.tz-sub {
    font-size: 12px;
    color: #a5b4fc;
    display: block;
    margin-top: 2px;
}

.tz-right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.tz-clock-pill {
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.25);
    padding: 7px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
    color: #38bdf8;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    letter-spacing: 0.5px;
    backdrop-filter: blur(4px);
}

.tz-today-badge {
    background: rgba(16, 185, 129, 0.2);
    border: 1px solid rgba(16, 185, 129, 0.35);
    padding: 7px 13px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    color: #a7f3d0;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

/* Order Time in Table */
.order-time-wrapper {
    display: flex;
    flex-direction: column;
    gap: 3px;
    text-align: left;
}

.order-date-primary {
    font-weight: 600;
    color: #0f172a;
    font-size: 13px;
}

.order-time-secondary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #475569;
}

.badge-ist-pill {
    background: #e0e7ff;
    color: #4338ca;
    font-size: 10px;
    font-weight: 800;
    padding: 1px 6px;
    border-radius: 6px;
    letter-spacing: 0.4px;
    border: 1px solid #c7d2fe;
}

.badge-time-ago {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    color: #059669;
    font-weight: 600;
    background: #ecfdf5;
    padding: 2px 7px;
    border-radius: 8px;
    width: fit-content;
    border: 1px solid #a7f3d0;
    margin-top: 2px;
}

/* =========================================================
   DESKTOP VS MOBILE SYSTEM
   ========================================================= */

.desktop-orders-table {
    display: block;
}

.mobile-orders-list {
    display: none;
}

/* Mobile Order Card Component */
.mobile-order-card {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.mobile-order-card:active {
    transform: scale(0.99);
}

.mobile-card-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.mobile-order-id {
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
}

.mobile-card-time {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #64748b;
}

.mobile-card-details {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8fafc;
    padding: 10px 14px;
    border-radius: 12px;
    margin-top: 2px;
    border: 1px solid #f1f5f9;
}

.mobile-amount {
    font-size: 19px;
    font-weight: 800;
    color: #16a34a;
}

.mobile-pay-badge {
    font-size: 12px;
    color: #475569;
    font-weight: 600;
}

.mobile-refund-alert {
    background: #fee2e2;
    border: 1px solid #fecaca;
    color: #991b1b;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
}

.mobile-actions-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 6px;
}

.mobile-act-btn {
    flex: 1 1 auto;
    min-width: 110px;
    padding: 10px 14px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    text-align: center;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    border: none;
    cursor: pointer;
    transition: 0.2s ease;
    box-sizing: border-box;
}

.btn-invoice {
    background: #3498db;
    color: white;
}

.btn-pay {
    background: #16a34a;
    color: white;
}

.btn-bank {
    background: linear-gradient(90deg, #6a11cb, #2575fc);
    color: white;
}

.btn-cancel {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fca5a5;
    width: 100%;
}

.btn-cancel:hover {
    background: #fecaca;
}

@media (max-width: 768px) {
    .container {
        width: 100% !important;
        max-width: 100% !important;
        margin: 12px auto !important;
        padding: 0 10px !important;
    }

    .desktop-orders-table {
        display: none !important;
    }

    .mobile-orders-list {
        display: flex !important;
        flex-direction: column;
        gap: 12px;
    }

    .timezone-banner {
        padding: 12px 14px !important;
        flex-direction: column;
        align-items: flex-start !important;
        gap: 10px !important;
        border-radius: 12px !important;
        margin-bottom: 16px !important;
    }

    .tz-right {
        width: 100%;
        justify-content: space-between;
    }

    .tz-clock-pill {
        font-size: 12px !important;
        padding: 5px 10px !important;
    }

    .tz-today-badge {
        font-size: 11px !important;
        padding: 5px 10px !important;
    }

    .btn {
        display: block !important;
        text-align: center !important;
        width: 100% !important;
    }
}

</style>

</head>


<body>


<!-- =========================================================
     HEADER
     ========================================================= -->

<header>

    <h2>
        🧾 My Orders
    </h2>


    <div>

        <nav>

            <a href="index.php">Home</a>

            <a href="menu.php">Menu</a>

            <a href="cart.php">Cart</a>

            <a href="my_orders.php">My Orders</a>

            <a href="contact.php">Contact</a>

            <a href="logout.php">Logout</a>

        </nav>

    </div>

</header>



<!-- =========================================================
     MAIN CONTAINER
     ========================================================= -->

<div class="container">

<!-- REAL-TIME ZONE BANNER (Modern Vibrant Card Design) -->
<div class="timezone-banner">
    <div class="tz-left">
        <div class="tz-pulse-dot"></div>
        <div>
            <span class="tz-title">Live Indian Standard Time (IST)</span>
            <span class="tz-sub">Real-Time Kitchen Sync & Order Tracking &bull; Asia/Kolkata (UTC+05:30)</span>
        </div>
    </div>
    <div class="tz-right">
        <div class="tz-clock-pill">
            <i class="fa-regular fa-clock"></i>
            <span id="studentLiveClock"><?php echo date('h:i:s A'); ?> IST</span>
        </div>
        <span class="tz-today-badge">
            <i class="fa-regular fa-calendar-check"></i> <?php echo date('D, d M Y'); ?>
        </span>
    </div>
</div>

<?php if (!empty($cancelMsg)) echo $cancelMsg; ?>
<?php

if (empty($orders)) {

?>

    <!-- =====================================================
         NO ORDERS
         ===================================================== -->

    <div class="empty">

        <div class="empty-icon">
            🛒
        </div>

        <h2>
            No Orders Found
        </h2>

        <p style="margin-top:10px;color:#777;">
            You have not placed any orders yet.
        </p>

    </div>

<?php

} else {

?>

<!-- =========================================================
     DESKTOP ORDERS TABLE (> 768px)
     ========================================================= -->

<div class="desktop-orders-table table-container">

<table>

<thead>

<tr>
    <th>Order ID</th>
    <th>Total</th>
    <th>Payment</th>
    <th>Payment ID</th>
    <th>Status</th>
    <th><i class="fa-regular fa-clock"></i> Order Time (IST)</th>
    <th>Invoice</th>
    <th>Action</th>
</tr>

</thead>

<tbody>

<?php

foreach ($orders as $row) {
    $orderId = isset($row['id']) ? (int)$row['id'] : 0;
    $totalAmount = isset($row['total_amount']) ? (float)$row['total_amount'] : 0;
    $paymentMethod = isset($row['payment_method']) ? $row['payment_method'] : "N/A";
    $paymentId = isset($row['payment_id']) ? $row['payment_id'] : "N/A";
    $status = isset($row['status']) ? $row['status'] : "Pending";

    $class = "pending";
    switch (strtolower(trim($status))) {
        case "paid": $class = "paid"; break;
        case "preparing": $class = "preparing"; break;
        case "completed": $class = "completed"; break;
        case "cancelled":
        case "canceled": $class = "cancelled"; break;
        case "failed": $class = "failed"; break;
        default: $class = "pending"; break;
    }

    $orderDate = ($dateColumn !== null && isset($row[$dateColumn]) && !empty($row[$dateColumn])) ? $row[$dateColumn] : "N/A";
    $formattedDate = "N/A";
    $formattedTime = "";
    $timeAgo = "";
    if ($orderDate !== "N/A" && !empty($orderDate)) {
        $ts = strtotime($orderDate);
        if ($ts !== false) {
            $formattedDate = date("d M Y", $ts);
            $formattedTime = date("h:i A", $ts);
            $diff = time() - $ts;
            if ($diff < 60) {
                $timeAgo = "Just now";
            } elseif ($diff < 3600) {
                $timeAgo = floor($diff / 60) . " mins ago";
            } elseif ($diff < 86400) {
                $timeAgo = floor($diff / 3600) . " hrs ago";
            } else {
                $timeAgo = floor($diff / 86400) . " days ago";
            }
        } else {
            $formattedDate = $orderDate;
        }
    }

    $foodSt = strtolower(trim($row['food_status'] ?? ''));
    $orderSt = strtolower(trim($status));
    $isCancelled = in_array($orderSt, ['cancelled', 'canceled', 'failed'], true);
    $isDelivered = ($foodSt === 'delivered');
?>

<tr>
    <td><strong>#<?php echo htmlspecialchars((string)$orderId); ?></strong></td>
    <td><strong style="color:#27ae60;">₹<?php echo number_format($totalAmount, 2); ?></strong></td>
    <td>
        <?php if (strtolower(trim($paymentMethod)) === "uropay"): ?>
            <span class="payment-uropay">📱 UroPay</span>
        <?php else: ?>
            <?php echo htmlspecialchars($paymentMethod); ?>
        <?php endif; ?>
    </td>
    <td style="white-space:nowrap;">
        <?php if ($paymentId !== "N/A" && $paymentId !== ""): ?>
            <span style="display:block; font-size:12px; color:#888; margin-bottom:5px;">
                <?php echo htmlspecialchars($paymentId); ?>
            </span>
            <a href="verify_ref.php?ref=<?php echo urlencode($paymentId); ?>" target="_blank" title="Check bank payment status for this Ref ID" style="display:inline-flex; align-items:center; gap:5px; background:linear-gradient(90deg,#6a11cb,#2575fc); color:white; padding:5px 13px; border-radius:20px; font-size:12px; font-weight:600; text-decoration:none;">
                ⚡ Check Bank Status
            </a>
        <?php else: ?>
            <span style="color:#ccc;">—</span>
        <?php endif; ?>
    </td>
    <td>
        <span class="status <?php echo $class; ?>"><?php echo htmlspecialchars($status); ?></span>
        <?php if ($isCancelled): ?>
            <div style="font-size:11px; color:#b91c1c; font-weight:700; margin-top:5px; line-height:1.3;">
                <i class="fa-solid fa-bolt" style="color:#d97706;"></i> Refund in 10 mins
            </div>
        <?php endif; ?>
    </td>
    <td>
        <?php if ($formattedDate !== "N/A"): ?>
            <div class="order-time-wrapper">
                <div class="order-date-primary"><?php echo htmlspecialchars($formattedDate); ?></div>
                <div class="order-time-secondary">
                    <span><?php echo htmlspecialchars($formattedTime); ?></span>
                    <span class="badge-ist-pill" title="Indian Standard Time (UTC+05:30)">IST</span>
                </div>
                <?php if (!empty($timeAgo)): ?>
                    <span class="badge-time-ago"><i class="fa-solid fa-clock-rotate-left"></i> <?php echo htmlspecialchars($timeAgo); ?></span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <span style="color:#999;">N/A</span>
        <?php endif; ?>
    </td>
    <td>
        <?php if (strtolower($status) === 'pending' && !empty($paymentId) && $paymentId !== 'N/A'): ?>
            <a href="uropay_payment.php?order_id=<?php echo urlencode($paymentId); ?>&local_id=<?php echo $orderId; ?>" class="invoice-btn" style="background:#27ae60; margin-bottom:5px; display:inline-block;">
                ⚡ Verify / Pay
            </a><br>
        <?php endif; ?>
        <?php if ($orderId > 0): ?>
            <a href="invoice.php?order_id=<?php echo $orderId; ?>" class="invoice-btn" target="_blank">
                🧾 View Invoice
            </a>
        <?php else: ?>
            <span style="color:#999;">N/A</span>
        <?php endif; ?>
    </td>
    <td>
        <?php if ($isCancelled): ?>
            <span style="display:inline-flex; align-items:center; gap:5px; font-size:11px; color:#b91c1c; font-weight:600; padding:5px 10px; background:#fee2e2; border-radius:6px; border:1px solid #fecaca; white-space:nowrap;">
                <i class="fa-solid fa-bolt"></i> Auto-Refund in 10 mins
            </span>
        <?php elseif (!$isDelivered): ?>
            <form method="POST" style="margin:0; display:inline;" onsubmit="return confirm('Cancel Order #<?php echo $orderId; ?>?\n\nIf you have already paid, the total amount of ₹<?php echo number_format($totalAmount, 2); ?> will be refunded automatically to your original UPI / Bank account within 10 minutes.');">
                <input type="hidden" name="cancel_order_id" value="<?php echo $orderId; ?>">
                <button type="submit" class="cancel-btn" title="Cancel order and trigger automatic 10-min refund">
                    <i class="fa-solid fa-xmark"></i> Cancel
                </button>
            </form>
        <?php else: ?>
            <span style="font-size:12px; color:#15803d; font-weight:600; display:inline-flex; align-items:center; gap:4px;">
                <i class="fa-solid fa-circle-check"></i> Delivered
            </span>
        <?php endif; ?>
    </td>
</tr>

<?php
}
?>

</tbody>
</table>
</div>

<!-- =========================================================
     MOBILE ORDER CARDS (<= 768px)
     ========================================================= -->

<div class="mobile-orders-list">

<?php
foreach ($orders as $row) {
    $orderId = isset($row['id']) ? (int)$row['id'] : 0;
    $totalAmount = isset($row['total_amount']) ? (float)$row['total_amount'] : 0;
    $paymentMethod = isset($row['payment_method']) ? $row['payment_method'] : "N/A";
    $paymentId = isset($row['payment_id']) ? $row['payment_id'] : "N/A";
    $status = isset($row['status']) ? $row['status'] : "Pending";

    $class = "pending";
    switch (strtolower(trim($status))) {
        case "paid": $class = "paid"; break;
        case "preparing": $class = "preparing"; break;
        case "completed": $class = "completed"; break;
        case "cancelled":
        case "canceled": $class = "cancelled"; break;
        case "failed": $class = "failed"; break;
        default: $class = "pending"; break;
    }

    $orderDate = ($dateColumn !== null && isset($row[$dateColumn]) && !empty($row[$dateColumn])) ? $row[$dateColumn] : "N/A";
    $formattedDate = "N/A";
    $formattedTime = "";
    $timeAgo = "";
    if ($orderDate !== "N/A" && !empty($orderDate)) {
        $ts = strtotime($orderDate);
        if ($ts !== false) {
            $formattedDate = date("d M Y", $ts);
            $formattedTime = date("h:i A", $ts);
            $diff = time() - $ts;
            if ($diff < 60) {
                $timeAgo = "Just now";
            } elseif ($diff < 3600) {
                $timeAgo = floor($diff / 60) . " mins ago";
            } elseif ($diff < 86400) {
                $timeAgo = floor($diff / 3600) . " hrs ago";
            } else {
                $timeAgo = floor($diff / 86400) . " days ago";
            }
        } else {
            $formattedDate = $orderDate;
        }
    }

    $foodSt = strtolower(trim($row['food_status'] ?? ''));
    $orderSt = strtolower(trim($status));
    $isCancelled = in_array($orderSt, ['cancelled', 'canceled', 'failed'], true);
    $isDelivered = ($foodSt === 'delivered');
?>

    <div class="mobile-order-card">
        <div class="mobile-card-top">
            <div class="mobile-order-id">
                <i class="fa-solid fa-receipt" style="color:#27ae60; margin-right:4px;"></i> Order #<?php echo $orderId; ?>
            </div>
            <span class="status <?php echo $class; ?>">
                <?php echo htmlspecialchars($status); ?>
            </span>
        </div>

        <div class="mobile-card-time">
            <span><i class="fa-regular fa-calendar" style="color:#64748b;"></i> <?php echo htmlspecialchars($formattedDate); ?><?php if (!empty($formattedTime)): ?>, <?php echo htmlspecialchars($formattedTime); ?><?php endif; ?></span>
            <span class="badge-ist-pill">IST</span>
            <?php if (!empty($timeAgo)): ?>
                <span class="badge-time-ago"><i class="fa-solid fa-clock-rotate-left"></i> <?php echo htmlspecialchars($timeAgo); ?></span>
            <?php endif; ?>
        </div>

        <div class="mobile-card-details">
            <div>
                <div style="font-size:11px; color:#64748b; text-transform:uppercase; font-weight:600; letter-spacing:0.5px;">Total Bill</div>
                <div class="mobile-amount">₹<?php echo number_format($totalAmount, 2); ?></div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:11px; color:#64748b; text-transform:uppercase; font-weight:600; letter-spacing:0.5px;">Payment</div>
                <div class="mobile-pay-badge">
                    <?php if (strtolower(trim($paymentMethod)) === "uropay"): ?>
                        <span class="payment-uropay" style="padding:3px 8px; font-size:11px;">📱 UroPay</span>
                    <?php else: ?>
                        <?php echo htmlspecialchars($paymentMethod); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($paymentId !== "N/A" && $paymentId !== ""): ?>
            <div style="font-size:11px; color:#64748b; word-break:break-all; background:#f1f5f9; padding:6px 10px; border-radius:8px;">
                <strong>Ref ID:</strong> <?php echo htmlspecialchars($paymentId); ?>
            </div>
        <?php endif; ?>

        <?php if ($isCancelled): ?>
            <div class="mobile-refund-alert">
                <i class="fa-solid fa-bolt" style="color:#f59e0b;"></i> Auto-Refund in 10 mins to source UPI
            </div>
        <?php endif; ?>

        <div class="mobile-actions-bar">
            <?php if ($orderId > 0): ?>
                <a href="invoice.php?order_id=<?php echo $orderId; ?>" class="mobile-act-btn btn-invoice" target="_blank">
                    <i class="fa-solid fa-file-invoice"></i> Invoice
                </a>
            <?php endif; ?>

            <?php if (strtolower($status) === 'pending' && !empty($paymentId) && $paymentId !== 'N/A'): ?>
                <a href="uropay_payment.php?order_id=<?php echo urlencode($paymentId); ?>&local_id=<?php echo $orderId; ?>" class="mobile-act-btn btn-pay">
                    ⚡ Verify / Pay
                </a>
            <?php endif; ?>

            <?php if ($paymentId !== "N/A" && $paymentId !== ""): ?>
                <a href="verify_ref.php?ref=<?php echo urlencode($paymentId); ?>" target="_blank" class="mobile-act-btn btn-bank">
                    ⚡ Check Bank
                </a>
            <?php endif; ?>

            <?php if (!$isCancelled && !$isDelivered): ?>
                <form method="POST" style="margin:0; flex:1 1 100%;" onsubmit="return confirm('Cancel Order #<?php echo $orderId; ?>?\n\nIf already paid, total of ₹<?php echo number_format($totalAmount, 2); ?> will be refunded within 10 minutes.');">
                    <input type="hidden" name="cancel_order_id" value="<?php echo $orderId; ?>">
                    <button type="submit" class="mobile-act-btn btn-cancel">
                        <i class="fa-solid fa-xmark"></i> Cancel Order
                    </button>
                </form>
            <?php elseif ($isDelivered): ?>
                <div style="width:100%; text-align:center; font-size:12px; color:#15803d; font-weight:700; padding:4px 0;">
                    <i class="fa-solid fa-circle-check"></i> Order Delivered
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php
}
?>

</div>

<?php
}
?>


<!-- =========================================================
     CONTINUE SHOPPING
     ========================================================= -->

<br>


<a
    href="menu.php"
    class="btn"
>
    ← Continue Shopping
</a>


</div>


<script>
// Real-Time Ticking Indian Standard Time (IST) Clock
(function() {
    function updateStudentClock() {
        const el = document.getElementById('studentLiveClock');
        if (!el) return;
        try {
            const options = {
                timeZone: 'Asia/Kolkata',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            };
            const formatter = new Intl.DateTimeFormat('en-US', options);
            el.textContent = formatter.format(new Date()) + ' IST';
        } catch (e) {
            const now = new Date();
            el.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true }) + ' IST';
        }
    }
    updateStudentClock();
    setInterval(updateStudentClock, 1000);
})();
</script>

</body>

</html>