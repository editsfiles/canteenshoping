<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

include("../php/db.php");

if (!isset($_GET['id'])) {
    header("Location: customers.php");
    exit();
}

$id = intval($_GET['id']);

$sql = "SELECT * FROM users WHERE id='$id'";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) == 0) {
    die("Customer Not Found");
}

$row = mysqli_fetch_assoc($result);

// Total Orders
$orderQuery = mysqli_query($conn, "SELECT COUNT(*) AS total_orders FROM orders WHERE user_id='$id'");
$orderData = mysqli_fetch_assoc($orderQuery);

// Total Amount Spent (Completed Orders)
$totalQuery = mysqli_query($conn, "SELECT IFNULL(SUM(total_amount), 0) AS total_amount FROM orders WHERE user_id='$id' AND status='Completed'");
$totalData = mysqli_fetch_assoc($totalQuery);

$activePage = 'customers';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Profile - College Canteen Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <link rel="stylesheet" href="css/admin_material.css">
</head>
<body>

<?php include("header_nav.php"); ?>

<main class="admin-container">

    <div class="admin-header-row">
        <div>
            <h1 class="admin-page-title">
                <i class="fa-solid fa-id-card"></i> Student Profile: <?php echo htmlspecialchars($row['name']); ?>
            </h1>
            <p class="admin-subtitle">Registration details and complete purchase history</p>
        </div>

        <a href="customers.php" class="btn-material btn-primary">
            <i class="fa-solid fa-arrow-left"></i> Back to Customers
        </a>
    </div>

    <!-- SUMMARY MINI CARDS -->
    <div class="cards-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom:25px;">
        <div class="info-card card-customers">
            <div class="card-header">
                <span class="card-title">Register Number</span>
                <i class="fa-solid fa-graduation-cap card-icon"></i>
            </div>
            <div class="card-value" style="font-size:24px;"><?php echo htmlspecialchars($row['regno'] ?: 'N/A'); ?></div>
        </div>

        <div class="info-card card-orders">
            <div class="card-header">
                <span class="card-title">Total Orders</span>
                <i class="fa-solid fa-cart-shopping card-icon"></i>
            </div>
            <div class="card-value"><?php echo $orderData['total_orders']; ?></div>
        </div>

        <div class="info-card card-sales">
            <div class="card-header">
                <span class="card-title">Total Spent</span>
                <i class="fa-solid fa-indian-rupee-sign card-icon"></i>
            </div>
            <div class="card-value">₹<?php echo number_format((float)$totalData['total_amount'], 2); ?></div>
        </div>
    </div>

    <!-- ACCOUNT DETAILS CARD -->
    <div class="table-card" style="margin-bottom:25px;">
        <div style="padding:14px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0;">
            <h3 style="margin:0; font-size:16px; font-weight:700; color:#0f172a;">Account Information</h3>
        </div>
        <div class="table-responsive">
            <table class="material-table">
                <tbody>
                    <tr>
                        <td style="width:220px; font-weight:600; color:#64748b;">Full Name</td>
                        <td style="font-weight:700; color:#0f172a;"><?php echo htmlspecialchars($row['name']); ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight:600; color:#64748b;">Register Number</td>
                        <td><span style="font-family:monospace; background:#f1f5f9; padding:2px 8px; border-radius:4px;"><?php echo htmlspecialchars($row['regno'] ?: 'N/A'); ?></span></td>
                    </tr>
                    <tr>
                        <td style="font-weight:600; color:#64748b;">Department</td>
                        <td><?php echo htmlspecialchars($row['department'] ?: 'General'); ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight:600; color:#64748b;">Email Address</td>
                        <td><a href="mailto:<?php echo htmlspecialchars($row['email']); ?>" style="color:#2563eb;"><?php echo htmlspecialchars($row['email']); ?></a></td>
                    </tr>
                    <tr>
                        <td style="font-weight:600; color:#64748b;">Account Created</td>
                        <td><?php echo htmlspecialchars($row['created_at'] ?? 'N/A'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- RECENT ORDER HISTORY (Structured Table with Red-Orange Header) -->
    <?php
    $custOrders = mysqli_query($conn, "SELECT * FROM orders WHERE user_id='$id' ORDER BY id DESC LIMIT 15");
    ?>
    <div class="table-card">
        <div style="padding:14px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:16px; font-weight:700; color:#0f172a;">
                <i class="fa-solid fa-clock-rotate-left" style="color:#ea580c; margin-right:6px;"></i> Recent Order History
            </h3>
        </div>
        <div class="table-responsive">
            <table class="material-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Amount</th>
                        <th>Payment Status</th>
                        <th>Kitchen Status</th>
                        <th>Date & Time</th>
                        <th style="text-align:right;">Invoice</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($custOrders && mysqli_num_rows($custOrders) > 0): ?>
                        <?php while ($co = mysqli_fetch_assoc($custOrders)): 
                            $st = trim($co['status']);
                            $isPaid = (strtolower($st) === 'completed' || strtolower($st) === 'paid');
                            $fst = trim($co['food_status'] ?? 'Preparing');
                        ?>
                        <tr>
                            <td><strong>#<?php echo (int)$co['id']; ?></strong></td>
                            <td><strong style="color:#0f172a;">₹<?php echo number_format((float)$co['total_amount'], 2); ?></strong></td>
                            <td>
                                <span class="badge-status <?php echo $isPaid ? 'badge-completed' : 'badge-pending'; ?>">
                                    <?php if ($isPaid): ?>
                                        <i class="fa-solid fa-circle-check"></i> Completed
                                    <?php else: ?>
                                        <i class="fa-solid fa-clock"></i> <?php echo htmlspecialchars($st); ?>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge-status <?php echo strtolower($fst) === 'delivered' ? 'badge-completed' : 'badge-pending'; ?>">
                                    <?php echo htmlspecialchars($fst); ?>
                                </span>
                            </td>
                            <td style="color:#64748b; font-size:13px;"><?php echo htmlspecialchars($co['order_date'] ?? '-'); ?></td>
                            <td style="text-align:right;">
                                <a href="../invoice.php?order_id=<?php echo (int)$co['id']; ?>" target="_blank" class="btn-material btn-primary" style="padding:5px 12px; font-size:12px;">
                                    <i class="fa-solid fa-file-invoice"></i> View Invoice
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding:30px; color:#94a3b8;">
                                No orders placed by this student yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

</body>
</html>