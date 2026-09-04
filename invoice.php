<?php
session_start();

include("php/db.php");


// Check login (allow student or admin)
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$isAdmin = isset($_SESSION['admin']);

// Get Order ID
if (
    !isset($_GET['order_id']) ||
    !is_numeric($_GET['order_id'])
) {
    die("Invalid Order ID");
}

$order_id = (int)$_GET['order_id'];

// Get order belonging to this customer (or any order if admin)
if ($isAdmin) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM orders WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $order_id);
} else {
    $stmt = mysqli_prepare($conn, "SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ii", $order_id, $user_id);
}

if (!$stmt) {
    die("Database Error: " . mysqli_error($conn));
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) == 0) {
    die("Order not found or you do not have permission to view this order.");
}


$order = mysqli_fetch_assoc($result);


// -----------------------------
// ORDER DETAILS
// -----------------------------

$orderId = $order['id'] ?? 'N/A';

$totalAmount = isset($order['total_amount'])
    ? (float)$order['total_amount']
    : 0;

$paymentMethod = $order['payment_method'] ?? 'N/A';

$paymentId = $order['payment_id'] ?? 'N/A';

$status = $order['status'] ?? 'Pending';

// Fetch customer name
$customerName = 'College Canteen Customer';
$customerDetails = '';
if (!empty($order['user_id'])) {
    $uStmt = mysqli_prepare($conn, "SELECT name, email, regno, department FROM users WHERE id = ? LIMIT 1");
    if ($uStmt) {
        mysqli_stmt_bind_param($uStmt, "i", $order['user_id']);
        mysqli_stmt_execute($uStmt);
        $uRes = mysqli_stmt_get_result($uStmt);
        if ($uRes && $uRow = mysqli_fetch_assoc($uRes)) {
            $customerName = $uRow['name'];
            $customerDetails = trim(($uRow['regno'] ? 'Reg No: ' . $uRow['regno'] . ' | ' : '') . ($uRow['department'] ?? ''));
        }
        mysqli_stmt_close($uStmt);
    }
}

// Fetch order items
$invoiceItems = [];
$itStmt = mysqli_prepare($conn, "SELECT product_name, price, quantity, subtotal FROM order_items WHERE order_id = ?");
if ($itStmt) {
    mysqli_stmt_bind_param($itStmt, "i", $order_id);
    mysqli_stmt_execute($itStmt);
    $itRes = mysqli_stmt_get_result($itStmt);
    while ($itRow = mysqli_fetch_assoc($itRes)) {
        $invoiceItems[] = $itRow;
    }
    mysqli_stmt_close($itStmt);
}


// -----------------------------
// FIND DATE COLUMN
// -----------------------------

$dateColumn = null;

$possibleDateColumns = [
    "created_at",
    "order_date",
    "ordered_at",
    "date",
    "created_date"
];

foreach ($possibleDateColumns as $column) {

    if (isset($order[$column])) {

        $dateColumn = $column;
        break;
    }
}

if ($dateColumn !== null && !empty($order[$dateColumn])) {
    $timeVal = strtotime($order[$dateColumn]);
    $orderDate = ($timeVal !== false) ? date("d M Y, h:i:s A", $timeVal) : $order[$dateColumn];
} else {
    $orderDate = date("d M Y, h:i:s A");
}

$bankUtr = $order['bank_utr'] ?? '';


// -----------------------------
// STATUS CLASS
// -----------------------------

$statusClass = "pending";

switch (strtolower($status)) {

    case "paid":
        $statusClass = "paid";
        break;

    case "preparing":
        $statusClass = "preparing";
        break;

    case "completed":
        $statusClass = "completed";
        break;

    case "cancelled":
    case "canceled":
        $statusClass = "cancelled";
        break;

    case "failed":
        $statusClass = "failed";
        break;
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

<title>Invoice #<?php echo htmlspecialchars($orderId); ?></title>


<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {

    font-family: Arial, Helvetica, sans-serif;

    background: #eef1f5;

    color: #222;

    padding: 40px 15px;

}


/* =========================
   INVOICE
========================= */

.invoice {

    max-width: 850px;

    margin: auto;

    background: white;

    border-radius: 12px;

    overflow: hidden;

    box-shadow:
        0 10px 35px rgba(0,0,0,0.12);

}


/* =========================
   TOP HEADER
========================= */

.invoice-header {

    background: #27ae60;

    color: white;

    padding: 30px 40px;

    display: flex;

    justify-content: space-between;

    align-items: center;

}


.logo-section h1 {

    font-size: 32px;

    margin-bottom: 5px;

}

.logo-section p {

    font-size: 14px;

    opacity: 0.9;

}


.invoice-title {

    text-align: right;

}

.invoice-title h2 {

    font-size: 30px;

    margin-bottom: 5px;

}

.invoice-title p {

    font-size: 14px;

}


/* =========================
   CONTENT
========================= */

.invoice-body {

    padding: 35px 40px;

}


/* =========================
   BILL INFO
========================= */

.info-section {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 20px;

    margin-bottom: 30px;

}


.info-box {

    background: #f7f9fa;

    border: 1px solid #e5e8eb;

    border-radius: 8px;

    padding: 18px;

}


.info-box h3 {

    font-size: 14px;

    color: #27ae60;

    text-transform: uppercase;

    margin-bottom: 8px;

}


.info-box p {

    font-size: 16px;

    line-height: 1.6;

}


/* =========================
   ORDER TABLE
========================= */

.invoice-table {

    width: 100%;

    border-collapse: collapse;

    margin-top: 10px;

}


.invoice-table thead {

    background: #2c3e50;

    color: white;

}


.invoice-table th {

    padding: 14px;

    text-align: left;

    font-size: 14px;

}


.invoice-table td {

    padding: 16px 14px;

    border-bottom: 1px solid #ddd;

}


.invoice-table th:last-child,
.invoice-table td:last-child {

    text-align: right;

}


/* =========================
   TOTAL
========================= */

.total-section {

    margin-top: 20px;

    margin-left: auto;

    width: 350px;

}


.total-row {

    display: flex;

    justify-content: space-between;

    padding: 10px 0;

    border-bottom: 1px solid #ddd;

}


.total-row.grand-total {

    border-bottom: none;

    padding-top: 18px;

    font-size: 22px;

    font-weight: bold;

    color: #27ae60;

}


/* =========================
   STATUS
========================= */

.status {

    display: inline-block;

    padding: 7px 16px;

    border-radius: 20px;

    color: white;

    font-size: 13px;

    font-weight: bold;

}


.pending {
    background: #f39c12;
}

.paid {
    background: #16a085;
}

.preparing {
    background: #3498db;
}

.completed {
    background: #27ae60;
}

.cancelled {
    background: #e74c3c;
}

.failed {
    background: #c0392b;
}


/* =========================
   FOOTER
========================= */

.invoice-footer {

    margin-top: 35px;

    padding-top: 20px;

    border-top: 1px solid #ddd;

    text-align: center;

    color: #777;

    font-size: 13px;

}


/* =========================
   BUTTONS
========================= */

.actions {

    display: flex;

    gap: 12px;

    margin-top: 30px;

}


.btn {

    display: inline-block;

    padding: 12px 20px;

    border-radius: 6px;

    text-decoration: none;

    font-weight: bold;

    border: none;

    cursor: pointer;

    font-size: 14px;

}


.back-btn {

    background: #2c3e50;

    color: white;

}


.print-btn {

    background: #3498db;

    color: white;

}


/* =========================
   PRINT
========================= */

@media print {

    body {

        background: white;

        padding: 0;

    }

    .invoice {

        box-shadow: none;

        max-width: 100%;

    }

    .actions {

        display: none;

    }

}


/* =========================
   MOBILE
========================= */

@media (max-width: 600px) {

    body {

        padding: 10px;

    }

    .invoice-header {

        padding: 25px 20px;

        flex-direction: column;

        gap: 20px;

        text-align: center;

    }

    .invoice-title {

        text-align: center;

    }

    .invoice-body {

        padding: 25px 20px;

    }

    .info-section {

        grid-template-columns: 1fr;

    }

    .total-section {

        width: 100%;

    }

    .actions {

        flex-direction: column;

    }

    .btn {

        text-align: center;

    }

}

</style>

</head>


<body>


<div class="invoice">


<!-- =========================
     HEADER
========================= -->

<div class="invoice-header">

    <div class="logo-section">

        <h1>🍴 College Canteen</h1>

        <p>Food & Beverage Services</p>

    </div>


    <div class="invoice-title">

        <h2>INVOICE</h2>

        <p>
            Order #<?php echo htmlspecialchars($orderId); ?>
        </p>

    </div>

</div>


<!-- =========================
     BODY
========================= -->

<div class="invoice-body">

<?php if ($statusClass === 'cancelled' || strtolower($status) === 'cancelled') { ?>
<div style="background:#fee2e2; border-left:5px solid #ef4444; border-radius:8px; padding:16px 20px; margin-bottom:25px; color:#991b1b;">
    <h4 style="margin:0 0 6px; font-size:16px; font-weight:700; display:flex; align-items:center; gap:8px;">
        ⚠️ Order Cancelled - Automatic Refund Policy Active
    </h4>
    <p style="margin:0; font-size:14px; line-height:1.5;">
        This order has been cancelled. The total amount of <strong>₹<?php echo number_format($totalAmount, 2); ?></strong> will be <strong>refunded automatically within 24 to 48 hours</strong> directly to your original payment method / UPI account.
    </p>
</div>
<?php } ?>

<!-- ORDER INFORMATION -->

<div class="info-section">


    <div class="info-box">

        <h3>Order Information</h3>

        <p>
            <strong>Order ID:</strong>
            #<?php echo htmlspecialchars($orderId); ?>
        </p>

        <p>
            <strong>Date:</strong>
            <?php echo htmlspecialchars($orderDate); ?>
        </p>

    </div>


    <div class="info-box">

        <h3>Payment Information</h3>

        <p>
            <strong>Method:</strong>
            <?php echo htmlspecialchars($paymentMethod); ?>
        </p>

        <p>
            <strong>Payment ID:</strong>
            <?php echo htmlspecialchars($paymentId); ?>
        </p>

        <?php if (!empty($bankUtr)) { ?>
        <p>
            <strong>Bank UTR / Ref:</strong>
            <?php echo htmlspecialchars($bankUtr); ?>
        </p>
        <?php } ?>

    </div>


    <div class="info-box">

        <h3>Order Status</h3>

        <p>

            <span class="status <?php echo $statusClass; ?>">

                <?php echo htmlspecialchars($status); ?>

            </span>

        </p>

    </div>


    <div class="info-box">

        <h3>Customer</h3>

        <p>
            <strong><?php echo htmlspecialchars($customerName); ?></strong>
        </p>

        <?php if (!empty($customerDetails)) { ?>
        <p style="font-size:13px; color:#64748b;">
            <?php echo htmlspecialchars($customerDetails); ?>
        </p>
        <?php } ?>

        <p style="font-size:12px; color:#94a3b8;">
            Customer ID: #<?php echo htmlspecialchars((string)($order['user_id'] ?? $user_id)); ?>
        </p>

    </div>


</div>


<!-- ORDER DETAILS -->

<table class="invoice-table">

<thead>

<tr>

    <th>Item Description</th>

    <th style="text-align:center;">Qty</th>

    <th style="text-align:right;">Price</th>

    <th style="text-align:right;">Subtotal</th>

</tr>

</thead>


<tbody>

<?php if (!empty($invoiceItems)) { ?>
    <?php foreach ($invoiceItems as $item): ?>
    <tr>
        <td>
            <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
        </td>
        <td style="text-align:center;">
            <?php echo (int)$item['quantity']; ?>
        </td>
        <td style="text-align:right;">
            ₹<?php echo number_format((float)$item['price'], 2); ?>
        </td>
        <td style="text-align:right;">
            <strong>₹<?php echo number_format((float)$item['subtotal'], 2); ?></strong>
        </td>
    </tr>
    <?php endforeach; ?>
<?php } else { ?>
    <tr>
        <td>
            <strong>College Canteen Food Order</strong>
            <br>
            <small style="color:#64748b;">Food and beverage purchase</small>
        </td>
        <td style="text-align:center;">1</td>
        <td style="text-align:right;">₹<?php echo number_format($totalAmount, 2); ?></td>
        <td style="text-align:right;"><strong>₹<?php echo number_format($totalAmount, 2); ?></strong></td>
    </tr>
<?php } ?>

</tbody>

</table>


<!-- TOTAL -->

<div class="total-section">

    <div class="total-row">

        <span>Subtotal</span>

        <strong>
            ₹<?php echo number_format($totalAmount, 2); ?>
        </strong>

    </div>


    <div class="total-row">

        <span>Tax</span>

        <strong>₹0.00</strong>

    </div>


    <div class="total-row grand-total">

        <span>Total</span>

        <span>
            ₹<?php echo number_format($totalAmount, 2); ?>
        </span>

    </div>

</div>


<!-- FOOTER -->

<div class="invoice-footer">

    <p>
        Thank you for ordering from College Canteen!
    </p>

    <p>
        Please keep this invoice for your records.
    </p>

</div>


<!-- BUTTONS -->

<div class="actions">

    <a href="my_orders.php" class="btn back-btn">
        ← My Orders
    </a>


    <button
        onclick="window.print()"
        class="btn print-btn"
    >
        🖨 Print Invoice
    </button>

</div>


</div>

</div>


</body>

</html>