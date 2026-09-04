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

$todayOrders = mysqli_num_rows(mysqli_query($conn,
"SELECT * FROM orders WHERE DATE(order_date)=CURDATE()"));

/* Today's Sales (Completed Orders Only) */

$todaySale = mysqli_query($conn,
"SELECT IFNULL(SUM(total_amount), 0) AS total
FROM orders
WHERE status='Completed' AND DATE(order_date)=CURDATE()");

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
orders.order_date
FROM orders
LEFT JOIN users
ON orders.user_id = users.id
ORDER BY orders.id DESC
LIMIT 10");
?>

<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">
<title>Admin Dashboard</title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Segoe UI',sans-serif;
}

body{
    background:linear-gradient(135deg,#e3f2fd,#f3e5f5,#fff8e1);
}

/* Header */

header{
    background:linear-gradient(90deg,#4facfe,#00f2fe,#43e97b);
    color:#fff;
    padding:18px 40px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    box-shadow:0 4px 12px rgba(0,0,0,.25);
}

header h2{
    font-size:28px;
}

header nav a{
    color:white;
    text-decoration:none;
    margin-left:18px;
    font-weight:bold;
    transition:.3s;
}

header nav a:hover{
    color:#ffeb3b;
}

/* Dashboard */

.dashboard{
    width:95%;
    margin:35px auto;
}

.dashboard h2{
    color:#2c3e50;
    margin-bottom:20px;
}

/* Cards */

.cards{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(230px,1fr));
    gap:25px;
    margin-bottom:35px;
}

.card{
    color:white;
    padding:25px;
    border-radius:15px;
    text-align:center;
    box-shadow:0 8px 20px rgba(0,0,0,.2);
    transition:.3s;
}

.card:hover{
    transform:translateY(-8px) scale(1.03);
}

.card h3{
    font-size:18px;
}

.card h2{
    margin-top:15px;
    font-size:34px;
    color:white;
}

/* Different Card Colors */

.card:nth-child(1){
    background:linear-gradient(135deg,#ff9966,#ff5e62);
}

.card:nth-child(2){
    background:linear-gradient(135deg,#36d1dc,#5b86e5);
}

.card:nth-child(3){
    background:linear-gradient(135deg,#11998e,#38ef7d);
}

.card:nth-child(4){
    background:linear-gradient(135deg,#f7971e,#ffd200);
}

.card:nth-child(5){
    background:linear-gradient(135deg,#8e2de2,#4a00e0);
}

.card:nth-child(6){
    background:linear-gradient(135deg,#fc466b,#3f5efb);
}

/* Table */

table{
    width:100%;
    border-collapse:collapse;
    background:white;
    border-radius:15px;
    overflow:hidden;
    box-shadow:0 8px 20px rgba(0,0,0,.15);
}

table th{
    background:linear-gradient(90deg,#ff512f,#dd2476);
    color:white;
    padding:15px;
    font-size:16px;
}

table td{
    padding:14px;
    text-align:center;
    border-bottom:1px solid #ddd;
}

table tr:nth-child(even){
    background:#f9f9f9;
}

table tr:hover{
    background:#e8f5e9;
    transition:.3s;
}

/* Status */

.Pending{
    color:#ff9800;
    font-weight:bold;
    background:#fff3cd;
    padding:6px 12px;
    border-radius:20px;
}

.Preparing{
    color:#1976d2;
    font-weight:bold;
    background:#e3f2fd;
    padding:6px 12px;
    border-radius:20px;
}

.Paid{
    color:#00796b;
    font-weight:bold;
    background:#e0f2f1;
    padding:6px 12px;
    border-radius:20px;
}

.Completed{
    color:#2e7d32;
    font-weight:bold;
    background:#d4edda;
    padding:6px 12px;
    border-radius:20px;
}

.Cancelled{
    color:#c62828;
    font-weight:bold;
    background:#f8d7da;
    padding:6px 12px;
    border-radius:20px;
}
</style>

</head>

<body>

<header>

<h2>🍽 College Canteen Admin</h2>

<nav>
<a href="dashboard.php">Dashboard</a>
<a href="products.php">Products</a>
<a href="customers.php">Customers</a>
<a href="orders.php">Orders</a>
<a href="missing_callback.php">Missing Callback</a>
<a href="reports.php">Reports</a>
<a href="messages.php">Messages</a>
<a href="logout.php">Logout</a>
</nav>

</header>

<div class="dashboard">

<div class="cards">

<div class="card">
<h3>Total Products</h3>
<h2><?php echo $productCount; ?></h2>
</div>

<div class="card">
<h3>Total Customers</h3>
<h2><?php echo $customerCount; ?></h2>
</div>

<div class="card">
<h3>Total Orders</h3>
<h2><?php echo $orderCount; ?></h2>
</div>

<div class="card">
<h3>Total Sales</h3>
<h2>₹<?php echo number_format($totalSales,2); ?></h2>
</div>

<div class="card">
<h3>Today's Orders</h3>
<h2><?php echo $todayOrders; ?></h2>
</div>

<div class="card">
<h3>Today's Sales</h3>
<h2>₹<?php echo number_format($todaySales,2); ?></h2>
</div>

</div>

<h2 style="margin-bottom:20px;">Latest Orders</h2>

<table>

<tr>
<th>Order ID</th>
<th>Customer</th>
<th>Total Amount</th>
<th>Payment</th>
<th>Status</th>
<th>Date</th>
</tr>

<?php while($row=mysqli_fetch_assoc($result)){ ?>

<tr>

<td><?php echo $row['id']; ?></td>

<td><?php echo htmlspecialchars($row['name']); ?></td>

<td>₹<?php echo number_format($row['total_amount'],2); ?></td>

<td><?php echo $row['payment_method']; ?></td>

<td class="<?php echo $row['status']; ?>">
<?php echo $row['status']; ?>
</td>

<td><?php echo $row['order_date']; ?></td>

</tr>

<?php } ?>

</table>

</div>

</body>
</html>