<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

include("../php/db.php");

/* Dashboard Counts */
$productCount = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM products"));
$customerCount = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM users"));
$orderCount = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM orders"));

/* Total Sales (Completed Orders Only) */
$sales = mysqli_query($conn, "SELECT IFNULL(SUM(total_amount), 0) AS total FROM orders WHERE status='Completed'");
$salesRow = mysqli_fetch_assoc($sales);
$totalSales = $salesRow['total'] ?? 0;

/* Today's Orders */
$todayOrders = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM orders WHERE DATE(order_date)=CURDATE()"));

/* Today's Sales (Completed Orders Only) */
$todaySale = mysqli_query($conn, "SELECT IFNULL(SUM(total_amount), 0) AS total FROM orders WHERE status='Completed' AND DATE(order_date)=CURDATE()");
$todayRow = mysqli_fetch_assoc($todaySale);
$todaySales = $todayRow['total'] ?? 0;

/* Latest Orders */
$result = mysqli_query($conn,
"SELECT
orders.id,
IFNULL(users.name, 'Customer') AS name,
orders.total_amount,
orders.payment_method,
orders.status,
orders.food_status,
orders.order_date
FROM orders
LEFT JOIN users
ON orders.user_id = users.id
ORDER BY orders.id DESC
LIMIT 10");

$activePage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - College Canteen Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <link rel="stylesheet" href="css/admin_material.css">
</head>
<body>

<?php include("header_nav.php"); ?>

<main class="admin-container">
    
    <div class="admin-header-row">
        <div>
            <h1 class="admin-page-title">
                <i class="fa-solid fa-gauge-high"></i> Dashboard Overview
            </h1>
            <p class="admin-subtitle">Live analytics & order management for College Canteen</p>
        </div>
        <div>
            <a href="orders.php" class="btn-material btn-orange">
                <i class="fa-solid fa-bell-concierge"></i> Kitchen Live Orders
            </a>
        </div>
    </div>

    <!-- MAIN AREA ROUNDED INFO CARDS WITH COLORFUL GRADIENTS -->
    <div class="cards-grid">
        
        <!-- Total Products (Orange-Red) -->
        <div class="info-card card-products">
            <div class="card-header">
                <span class="card-title">Total Products</span>
                <i class="fa-solid fa-burger card-icon"></i>
            </div>
            <div class="card-value"><?php echo $productCount; ?></div>
        </div>

        <!-- Total Customers (Cyan-Blue) -->
        <div class="info-card card-customers">
            <div class="card-header">
                <span class="card-title">Total Customers</span>
                <i class="fa-solid fa-users card-icon"></i>
            </div>
            <div class="card-value"><?php echo $customerCount; ?></div>
        </div>

        <!-- Total Orders (Green) -->
        <div class="info-card card-orders">
            <div class="card-header">
                <span class="card-title">Total Orders</span>
                <i class="fa-solid fa-cart-shopping card-icon"></i>
            </div>
            <div class="card-value"><?php echo $orderCount; ?></div>
        </div>

        <!-- Total Sales (Gold) -->
        <div class="info-card card-sales">
            <div class="card-header">
                <span class="card-title">Total Sales</span>
                <i class="fa-solid fa-coins card-icon"></i>
            </div>
            <div class="card-value">₹<?php echo number_format($totalSales, 2); ?></div>
        </div>

        <!-- Today's Orders (Purple) -->
        <div class="info-card card-today-orders">
            <div class="card-header">
                <span class="card-title">Today's Orders</span>
                <i class="fa-solid fa-clock-rotate-left card-icon"></i>
            </div>
            <div class="card-value"><?php echo $todayOrders; ?></div>
        </div>

        <!-- Today's Sales (Purple) -->
        <div class="info-card card-today-sales">
            <div class="card-header">
                <span class="card-title">Today's Sales</span>
                <i class="fa-solid fa-chart-line card-icon"></i>
            </div>
            <div class="card-value">₹<?php echo number_format($todaySales, 2); ?></div>
        </div>

    </div>

    <!-- STRUCTURED TABLE WITH RED-ORANGE HEADER -->
    <div class="table-card">
        <div style="padding: 16px 20px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9;">
            <h2 style="font-size:18px; font-weight:700; color:#0f172a; margin:0;">
                <i class="fa-solid fa-list-check" style="color:#ea580c; margin-right:8px;"></i> Recent Orders
            </h2>
            <a href="orders.php" style="color:#2563eb; font-size:13px; font-weight:600; text-decoration:none;">
                View All Orders &rarr;
            </a>
        </div>

        <div class="table-responsive">
            <table class="material-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Total Amount</th>
                        <th>Payment Method</th>
                        <th>Status</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($result)): 
                            $status = trim($row['status']);
                            $badgeClass = (strtolower($status) === 'completed' || strtolower($status) === 'paid') ? 'badge-completed' : (strtolower($status) === 'pending' ? 'badge-pending' : 'badge-cancelled');
                        ?>
                        <tr>
                            <td><strong>#<?php echo $row['id']; ?></strong></td>
                            <td>
                                <div style="font-weight:600; color:#0f172a;"><?php echo htmlspecialchars($row['name']); ?></div>
                            </td>
                            <td>
                                <strong style="color:#0f172a;">₹<?php echo number_format($row['total_amount'], 2); ?></strong>
                            </td>
                            <td>
                                <span style="font-size:13px; color:#475569;">
                                    <i class="fa-solid fa-qrcode" style="color:#2563eb; margin-right:4px;"></i> <?php echo htmlspecialchars($row['payment_method'] ?? 'UPI'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge-status <?php echo $badgeClass; ?>">
                                    <?php if (strtolower($status) === 'completed' || strtolower($status) === 'paid'): ?>
                                        <i class="fa-solid fa-circle-check"></i> Completed
                                    <?php elseif (strtolower($status) === 'pending'): ?>
                                        <i class="fa-solid fa-clock"></i> Pending
                                    <?php else: ?>
                                        <i class="fa-solid fa-circle-xmark"></i> <?php echo htmlspecialchars($status); ?>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td style="color:#64748b; font-size:13px;">
                                <?php echo htmlspecialchars($row['order_date']); ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding:30px; color:#94a3b8;">
                                No orders received yet.
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