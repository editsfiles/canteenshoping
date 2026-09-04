<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

include("../php/db.php");

/* -----------------------------
   Dashboard Statistics
------------------------------*/

// Total Products
$productQuery = mysqli_query($conn, "SELECT COUNT(*) AS total FROM products");
$productData  = mysqli_fetch_assoc($productQuery);
$totalProducts = $productData['total'];

// Total Customers
$customerQuery = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users");
$customerData  = mysqli_fetch_assoc($customerQuery);
$totalCustomers = $customerData['total'];

// Total Orders
$orderQuery = mysqli_query($conn, "SELECT COUNT(*) AS total FROM orders");
$orderData  = mysqli_fetch_assoc($orderQuery);
$totalOrders = $orderData['total'];

// Total Sales
$salesQuery = mysqli_query($conn, "
SELECT IFNULL(SUM(total_amount),0) AS total
FROM orders
WHERE status='Completed'
");
$salesData = mysqli_fetch_assoc($salesQuery);
$totalSales = $salesData['total'];

// Today's Orders
$todayOrderQuery = mysqli_query($conn, "
SELECT COUNT(*) AS total
FROM orders
WHERE DATE(order_date)=CURDATE()
");
$todayOrderData = mysqli_fetch_assoc($todayOrderQuery);
$todayOrders = $todayOrderData['total'];

// Today's Sales
$todaySalesQuery = mysqli_query($conn, "
SELECT IFNULL(SUM(total_amount),0) AS total
FROM orders
WHERE status='Completed'
AND DATE(order_date)=CURDATE()
");
$todaySalesData = mysqli_fetch_assoc($todaySalesQuery);
$todaySales = $todaySalesData['total'];

// Monthly Sales
$monthSalesQuery = mysqli_query($conn, "
SELECT IFNULL(SUM(total_amount),0) AS total
FROM orders
WHERE status='Completed'
AND MONTH(order_date)=MONTH(CURDATE())
AND YEAR(order_date)=YEAR(CURDATE())
");
$monthSalesData = mysqli_fetch_assoc($monthSalesQuery);
$monthSales = $monthSalesData['total'];

/* -----------------------------
   Search + Date Filter
------------------------------*/

$search = "";
$where = "";

if (isset($_GET['search']) && $_GET['search'] != "") {

    $search = mysqli_real_escape_string($conn, $_GET['search']);

    $where = " WHERE id LIKE '%$search%'
    OR user_id LIKE '%$search%'
    OR payment_id LIKE '%$search%'
    OR payment_method LIKE '%$search%'
    OR status LIKE '%$search%' ";
}

$dateWhere = "";

if (isset($_GET['from']) && isset($_GET['to'])) {

    $from = mysqli_real_escape_string($conn, trim($_GET['from']));
    $to   = mysqli_real_escape_string($conn, trim($_GET['to']));

    if ($from != "" && $to != "") {

        $dateWhere = " DATE(order_date) BETWEEN '$from' AND '$to' ";
    }
}

// Latest Orders Query
$sql = "SELECT * FROM orders";

if ($where != "" && $dateWhere != "") {
    $sql .= $where . " AND " . $dateWhere;
} elseif ($where != "") {
    $sql .= $where;
} elseif ($dateWhere != "") {
    $sql .= " WHERE " . $dateWhere;
}

$sql .= " ORDER BY id DESC LIMIT 50";

$latestOrders = mysqli_query($conn, $sql);

$activePage = 'reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial & Sales Reports - College Canteen Admin</title>
    <!-- Material Font & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <!-- Standardized Admin Material CSS -->
    <link rel="stylesheet" href="css/admin_material.css">
    <style>
        @media print {
            .admin-top-nav, .admin-dark-bar, .admin-header-row, .search-container, .print-btn, .filter-card {
                display: none !important;
            }
            body {
                background: #ffffff !important;
            }
            .table-card {
                box-shadow: none !important;
                border: 1px solid #000 !important;
            }
        }
    </style>
</head>
<body>

<?php include("header_nav.php"); ?>

<div class="admin-container">
    <div class="admin-header-row">
        <div>
            <h1 class="admin-page-title"><i class="fa-solid fa-chart-line"></i> Financial & Sales Reports</h1>
            <p class="admin-subtitle">Comprehensive sales performance, daily totals, and order audit trail</p>
        </div>
        <div style="display:flex; gap:10px; align-items:center;">
            <button class="btn-material btn-success print-btn" onclick="window.print();">
                <i class="fa-solid fa-print"></i> Print Report
            </button>
        </div>
    </div>

    <!-- 6 COLORFUL GRADIENT INFO CARDS (Matching Requested Theme) -->
    <div class="cards-grid">
        <!-- Total Products (Orange-Red) -->
        <div class="info-card card-products">
            <div class="card-header">
                <span class="card-title">Total Products</span>
                <i class="fa-solid fa-burger card-icon"></i>
            </div>
            <div class="card-value"><?php echo number_format($totalProducts); ?></div>
        </div>

        <!-- Total Customers (Cyan-Blue) -->
        <div class="info-card card-customers">
            <div class="card-header">
                <span class="card-title">Total Customers</span>
                <i class="fa-solid fa-users card-icon"></i>
            </div>
            <div class="card-value"><?php echo number_format($totalCustomers); ?></div>
        </div>

        <!-- Total Orders (Green) -->
        <div class="info-card card-orders">
            <div class="card-header">
                <span class="card-title">Total Orders</span>
                <i class="fa-solid fa-cart-shopping card-icon"></i>
            </div>
            <div class="card-value"><?php echo number_format($totalOrders); ?></div>
        </div>

        <!-- Total Sales (Gold) -->
        <div class="info-card card-sales">
            <div class="card-header">
                <span class="card-title">Total Sales</span>
                <i class="fa-solid fa-indian-rupee-sign card-icon"></i>
            </div>
            <div class="card-value">₹<?php echo number_format((float)$totalSales, 2); ?></div>
        </div>

        <!-- Today's Orders (Purple) -->
        <div class="info-card card-today-orders">
            <div class="card-header">
                <span class="card-title">Today's Orders</span>
                <i class="fa-solid fa-calendar-day card-icon"></i>
            </div>
            <div class="card-value"><?php echo number_format($todayOrders); ?></div>
        </div>

        <!-- Today's Sales (Purple) -->
        <div class="info-card card-today-sales">
            <div class="card-header">
                <span class="card-title">Today's Sales</span>
                <i class="fa-solid fa-coins card-icon"></i>
            </div>
            <div class="card-value">₹<?php echo number_format((float)$todaySales, 2); ?></div>
        </div>
    </div>

    <!-- FILTER & SEARCH CARD -->
    <div class="admin-card filter-card" style="padding:18px 24px;">
        <form method="GET" style="display:flex; flex-wrap:wrap; gap:14px; align-items:center; justify-content:space-between;">
            <div style="display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
                <div class="search-container">
                    <i class="fa-solid fa-magnifying-glass" style="color:#94a3b8;"></i>
                    <input type="text" name="search" id="searchInput" placeholder="Search orders, IDs, payment..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <label style="font-size:13px; font-weight:600; color:#475569;">From:</label>
                    <input type="date" name="from" value="<?php echo htmlspecialchars($_GET['from'] ?? ''); ?>" class="form-control" style="width:auto; padding:6px 12px;">
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <label style="font-size:13px; font-weight:600; color:#475569;">To:</label>
                    <input type="date" name="to" value="<?php echo htmlspecialchars($_GET['to'] ?? ''); ?>" class="form-control" style="width:auto; padding:6px 12px;">
                </div>
            </div>
            <div style="display:flex; gap:10px;">
                <button type="submit" class="btn-material btn-orange">
                    <i class="fa-solid fa-filter"></i> Apply Filter
                </button>
                <a href="reports.php" class="btn-material" style="background:#e2e8f0; color:#334155;">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- STRUCTURED TABLE WITH RED-ORANGE HEADER -->
    <div class="table-card">
        <div style="padding: 18px 24px; border-bottom: 1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="font-size:16px; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-file-lines" style="color:#ea580c;"></i> Detailed Orders Audit Log
            </h3>
            <span style="font-size:13px; color:#64748b; font-weight:600;">
                Showing latest <?php echo mysqli_num_rows($latestOrders); ?> records
            </span>
        </div>

        <div class="table-responsive">
            <table class="material-table" id="ordersTable">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>User ID</th>
                        <th>Payment ID</th>
                        <th>Payment Method</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Order Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($latestOrders && mysqli_num_rows($latestOrders) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($latestOrders)): ?>
                            <tr>
                                <td><strong>#<?php echo $row['id']; ?></strong></td>
                                <td><span style="font-weight:600; color:#475569;">#<?php echo $row['user_id']; ?></span></td>
                                <td>
                                    <?php if (!empty($row['payment_id'])): ?>
                                        <code style="background:#f1f5f9; padding:3px 8px; border-radius:6px; font-size:12px; color:#0369a1;"><?php echo htmlspecialchars($row['payment_id']); ?></code>
                                    <?php else: ?>
                                        <span style="color:#94a3b8; font-size:13px;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-weight:500; font-size:13px;">
                                        <i class="fa-solid fa-credit-card" style="color:#94a3b8; margin-right:4px;"></i>
                                        <?php echo htmlspecialchars(!empty($row['payment_method']) ? $row['payment_method'] : 'Online / UPI'); ?>
                                    </span>
                                </td>
                                <td><strong style="color:#ea580c; font-size:15px;">₹<?php echo number_format((float)$row['total_amount'], 2); ?></strong></td>
                                <td>
                                    <?php 
                                    $st = $row['status'];
                                    if ($st === 'Completed' || $st === 'Delivered') {
                                        echo '<span class="badge-status badge-completed"><i class="fa-solid fa-check"></i> Completed</span>';
                                    } elseif ($st === 'Pending') {
                                        echo '<span class="badge-status badge-pending"><i class="fa-regular fa-clock"></i> Pending</span>';
                                    } elseif ($st === 'Cancelled') {
                                        echo '<span class="badge-status badge-cancelled"><i class="fa-solid fa-ban"></i> Cancelled</span>';
                                    } else {
                                        echo '<span class="badge-status badge-pending">' . htmlspecialchars($st) . '</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo date("d M Y, h:i A", strtotime($row['order_date'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:35px 20px; color:#64748b;">
                                <i class="fa-solid fa-inbox" style="font-size:32px; color:#cbd5e1; display:block; margin-bottom:10px;"></i>
                                No matching orders found for the selected filter.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Real-time client search filter
const searchInput = document.getElementById("searchInput");
if (searchInput) {
    searchInput.addEventListener("keyup", function() {
        let filter = this.value.toLowerCase();
        let rows = document.querySelectorAll("#ordersTable tbody tr");
        rows.forEach(function(row) {
            let text = row.innerText.toLowerCase();
            row.style.display = text.indexOf(filter) > -1 ? "" : "none";
        });
    });
}
</script>

</body>
</html>