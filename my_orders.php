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

    <th>Date</th>

    <th>Invoice</th>

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

            <?php
            echo htmlspecialchars($status);
            ?>

        </span>

    </td>



    <!-- =================================================
         DATE
         ================================================= -->

    <td>

        <?php

        if (
            $orderDate !== "N/A" &&
            !empty($orderDate)
        ) {

            echo htmlspecialchars($orderDate);

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


</body>

</html>