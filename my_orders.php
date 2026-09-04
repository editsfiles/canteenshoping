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
                $cancelMsg = "<div class='cancel-alert warning'><i class='fa-solid fa-triangle-exclamation'></i> Order #$cancelOrderId is already cancelled. Refund is processing within 24 to 48 hours.</div>";
            } elseif ($curFoodStatus === 'delivered') {
                $cancelMsg = "<div class='cancel-alert danger'><i class='fa-solid fa-circle-xmark'></i> Cannot cancel Order #$cancelOrderId: The food has already been delivered.</div>";
            } else {
                $refundNotes = "Order cancelled. Amount ₹" . number_format($orderToCancel['total_amount'], 2) . " will be refunded automatically within 24 to 48 hours.";
                $upd = mysqli_prepare($conn, "UPDATE orders SET status = 'Cancelled', food_status = 'Cancelled', refund_status = 'Refund Processing (24-48 hrs)', refund_notes = ? WHERE id = ? AND user_id = ?");
                if ($upd) {
                    mysqli_stmt_bind_param($upd, "sii", $refundNotes, $cancelOrderId, $user_id);
                    mysqli_stmt_execute($upd);
                    mysqli_stmt_close($upd);
                }

                // Log automatic refund request
                $logLine = "[" . date('Y-m-d H:i:s') . "] ORDER CANCELLED: Order #" . $cancelOrderId . " | Amount: ₹" . $orderToCancel['total_amount'] . " | Auto-Refund Policy: 24 to 48 Hours\n";
                @file_put_contents("webhook_log.txt", $logLine, FILE_APPEND);

                $cancelMsg = "<div class='cancel-alert success'>
                    <h4 style='margin:0 0 6px; font-size:16px;'><i class='fa-solid fa-circle-check'></i> Order #$cancelOrderId Cancelled Successfully</h4>
                    <p style='margin:0;'>The order has been cancelled. Your amount of <strong>₹" . number_format($orderToCancel['total_amount'], 2) . "</strong> will be <strong>refunded automatically within 24 to 48 hours</strong> to your original payment method / UPI account.</p>
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
   TABLE RESPONSIVE
   ========================================================= */

@media (max-width: 900px) {

    .table-container {
        overflow-x: auto;
    }

    table {
        min-width: 950px;
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

if (mysqli_num_rows($result) == 0) {

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
     ORDERS TABLE
     ========================================================= -->

<div class="table-container">

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

while ($row = mysqli_fetch_assoc($result)) {


    // =====================================================
    // ORDER ID
    // =====================================================

    $orderId = isset($row['id'])
        ? (int)$row['id']
        : 0;


    // =====================================================
    // TOTAL AMOUNT
    // =====================================================

    $totalAmount = isset($row['total_amount'])
        ? (float)$row['total_amount']
        : 0;


    // =====================================================
    // PAYMENT METHOD
    // =====================================================

    $paymentMethod = isset($row['payment_method'])
        ? $row['payment_method']
        : "N/A";


    // =====================================================
    // PAYMENT ID
    // =====================================================

    $paymentId = isset($row['payment_id'])
        ? $row['payment_id']
        : "N/A";


    // =====================================================
    // STATUS
    // =====================================================

    $status = isset($row['status'])
        ? $row['status']
        : "Pending";


    // =====================================================
    // STATUS CSS CLASS
    // =====================================================

    $class = "pending";


    switch (strtolower(trim($status))) {

        case "paid":

            $class = "paid";

            break;


        case "preparing":

            $class = "preparing";

            break;


        case "completed":

            $class = "completed";

            break;


        case "cancelled":

        case "canceled":

            $class = "cancelled";

            break;


        case "failed":

            $class = "failed";

            break;


        default:

            $class = "pending";

            break;
    }


    // =====================================================
    // ORDER DATE
    // =====================================================

    if (
        $dateColumn !== null &&
        isset($row[$dateColumn]) &&
        !empty($row[$dateColumn])
    ) {

        $orderDate = $row[$dateColumn];

    } else {

        $orderDate = "N/A";

    }

?>


<tr>


    <!-- =================================================
         ORDER ID
         ================================================= -->

    <td>

        <strong>

            #<?php
            echo htmlspecialchars((string)$orderId);
            ?>

        </strong>

    </td>



    <!-- =================================================
         TOTAL
         ================================================= -->

    <td>

        <strong style="color:#27ae60;">

            ₹<?php
            echo number_format($totalAmount, 2);
            ?>

        </strong>

    </td>



    <!-- =================================================
         PAYMENT
         ================================================= -->

    <td>

        <?php

        if (strtolower(trim($paymentMethod)) === "uropay") {

            echo '<span class="payment-uropay">📱 UroPay</span>';

        } else {

            echo htmlspecialchars($paymentMethod);

        }

        ?>

    </td>



    <!-- =================================================
         PAYMENT ID
         ================================================= -->

    <td style="white-space:nowrap;">

        <?php if ($paymentId !== "N/A" && $paymentId !== ""): ?>

            <span style="display:block; font-size:12px; color:#888; margin-bottom:5px;">
                <?php echo htmlspecialchars($paymentId); ?>
            </span>

            <a
                href="verify_ref.php?ref=<?php echo urlencode($paymentId); ?>"
                target="_blank"
                title="Check bank payment status for this Ref ID"
                style="
                    display:inline-flex;
                    align-items:center;
                    gap:5px;
                    background:linear-gradient(90deg,#6a11cb,#2575fc);
                    color:white;
                    padding:5px 13px;
                    border-radius:20px;
                    font-size:12px;
                    font-weight:600;
                    text-decoration:none;
                "
            >
                ⚡ Check Bank Status
            </a>

        <?php else: ?>

            <span style="color:#ccc;">—</span>

        <?php endif; ?>

    </td>



    <!-- =================================================
         STATUS
         ================================================= -->

    <td>

        <span class="status <?php echo $class; ?>">
            <?php echo htmlspecialchars($status); ?>
        </span>

        <?php if (in_array(strtolower(trim($status)), ['cancelled', 'canceled', 'failed'], true)): ?>
            <div style="font-size:11px; color:#b91c1c; font-weight:700; margin-top:5px; line-height:1.3;">
                <i class="fa-solid fa-clock-rotate-left"></i> Refund in 24-48 hrs
            </div>
        <?php endif; ?>

    </td>



    <!-- =================================================
         DATE & REAL TIME ZONE
         ================================================= -->

    <td>

        <?php
        if ($orderDate !== "N/A" && !empty($orderDate)) {
            $ts = strtotime($orderDate);
            if ($ts !== false) {
                $formattedDate = date("d M Y", $ts);
                $formattedTime = date("h:i A", $ts);
                
                // Relative time calculation
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
                ?>
                <div class="order-time-wrapper">
                    <div class="order-date-primary"><?php echo htmlspecialchars($formattedDate); ?></div>
                    <div class="order-time-secondary">
                        <span><?php echo htmlspecialchars($formattedTime); ?></span>
                        <span class="badge-ist-pill" title="Indian Standard Time (UTC+05:30)">IST</span>
                    </div>
                    <span class="badge-time-ago"><i class="fa-solid fa-clock-rotate-left"></i> <?php echo htmlspecialchars($timeAgo); ?></span>
                </div>
                <?php
            } else {
                echo htmlspecialchars($orderDate) . ' <span class="badge-ist-pill">IST</span>';
            }
        } else {
            echo '<span style="color:#999;">N/A</span>';
        }
        ?>

    </td>



    <!-- =================================================
         VIEW INVOICE
         ================================================= -->

    <td>

        <?php if (strtolower($status) === 'pending' && !empty($paymentId) && $paymentId !== 'N/A') { ?>

            <a
                href="uropay_payment.php?order_id=<?php echo urlencode($paymentId); ?>&local_id=<?php echo $orderId; ?>"
                class="invoice-btn"
                style="background:#27ae60; margin-bottom:5px; display:inline-block;"
            >
                ⚡ Verify / Pay
            </a>
            <br>

        <?php } ?>

        <?php if ($orderId > 0) { ?>

            <a
                href="invoice.php?order_id=<?php echo $orderId; ?>"
                class="invoice-btn"
                target="_blank"
            >
                🧾 View Invoice
            </a>

        <?php } else { ?>

            <span style="color:#999;">
                N/A
            </span>

        <?php } ?>

    </td>


    <!-- =================================================
         ACTION / CANCEL ORDER & 24-48 HR REFUND
         ================================================= -->

    <td>
        <?php 
        $foodSt = strtolower(trim($row['food_status'] ?? ''));
        $orderSt = strtolower(trim($status));
        $isCancelled = in_array($orderSt, ['cancelled', 'canceled', 'failed'], true);
        $isDelivered = ($foodSt === 'delivered');
        ?>

        <?php if ($isCancelled): ?>
            <span style="display:inline-flex; align-items:center; gap:5px; font-size:11px; color:#b91c1c; font-weight:600; padding:5px 10px; background:#fee2e2; border-radius:6px; border:1px solid #fecaca; white-space:nowrap;">
                <i class="fa-solid fa-rotate-left"></i> Auto-Refund Initiated
            </span>
        <?php elseif (!$isDelivered): ?>
            <form method="POST" style="margin:0; display:inline;" onsubmit="return confirm('Cancel Order #<?php echo $orderId; ?>?\n\nIf you have already paid, the total amount of ₹<?php echo number_format($totalAmount, 2); ?> will be refunded automatically to your original UPI / Bank account within 24 to 48 hours.');">
                <input type="hidden" name="cancel_order_id" value="<?php echo $orderId; ?>">
                <button type="submit" class="cancel-btn" title="Cancel order and trigger automatic 24-48 hr refund">
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