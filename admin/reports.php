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
?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Admin Reports</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

<style>

*{
margin:0;
padding:0;
box-sizing:border-box;
font-family:'Poppins',sans-serif;
}

body{
background:#eef2f7;
color:#2c3e50;
}

/* ===== Header ===== */
header{
background:linear-gradient(90deg,#6a11cb,#2575fc);
padding:18px 40px;
display:flex;
justify-content:space-between;
align-items:center;
box-shadow:0 4px 12px rgba(0,0,0,.2);
}

header h2{
color:white;
font-size:26px;
font-weight:700;
}

header nav a{
color:white;
text-decoration:none;
margin-left:25px;
font-size:16px;
font-weight:600;
transition:.3s;
}

header nav a:hover{
color:#ffe082;
}

/* ===== Container ===== */
.container{
width:95%;
margin:30px auto;
}

/* ===== Page Title ===== */
.page-title{
margin-bottom:30px;
}

.page-title h1{
font-size:38px;
color:#2c3e50;
margin-bottom:5px;
}

.page-title p{
color:#777;
font-size:16px;
}

/* ===== Dashboard Cards ===== */
.stats{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(230px,1fr));
gap:25px;
margin-bottom:30px;
}

.card{
padding:25px;
border-radius:18px;
color:#fff;
box-shadow:0 10px 25px rgba(0,0,0,.15);
transition:.3s;
position:relative;
overflow:hidden;
}

.card:hover{
transform:translateY(-8px);
}

.card .icon{
font-size:48px;
position:absolute;
right:20px;
top:20px;
opacity:.2;
}

.card h3{
font-size:18px;
font-weight:500;
margin-bottom:12px;
}

.card p{
font-size:32px;
font-weight:700;
}

.blue{
background:linear-gradient(135deg,#2193b0,#6dd5ed);
}

.green{
background:linear-gradient(135deg,#11998e,#38ef7d);
}

.orange{
background:linear-gradient(135deg,#ff9966,#ff5e62);
}

.purple{
background:linear-gradient(135deg,#7b4397,#dc2430);
}

.red{
background:linear-gradient(135deg,#cb2d3e,#ef473a);
}

.teal{
background:linear-gradient(135deg,#00b09b,#96c93d);
}

.dark{
background:linear-gradient(135deg,#232526,#414345);
}

/* ===== Search / Filter ===== */
.search-box{
background:white;
padding:20px;
border-radius:15px;
box-shadow:0 8px 20px rgba(0,0,0,.08);
margin-bottom:30px;
}

.search-box form{
display:flex;
flex-wrap:wrap;
gap:12px;
}

.search-box input{
padding:12px 15px;
border:1px solid #ccc;
border-radius:8px;
font-size:15px;
min-width:200px;
flex:1;
}

.search-box button{
padding:12px 20px;
border:none;
border-radius:8px;
background:#2575fc;
color:white;
font-weight:600;
cursor:pointer;
transition:.3s;
}

.search-box button:hover{
background:#1b5ed8;
}

.print-btn{
background:#27ae60 !important;
}

.print-btn:hover{
background:#1f8a4d !important;
}

.export-btn{
background:#f39c12 !important;
}

.export-btn:hover{
background:#d68910 !important;
}

/* ===== Table ===== */
.table-box{
background:white;
padding:20px;
border-radius:15px;
box-shadow:0 8px 20px rgba(0,0,0,.08);
overflow-x:auto;
}

.table-header{
display:flex;
justify-content:space-between;
align-items:center;
margin-bottom:20px;
}

.table-header h2{
color:#2c3e50;
font-size:24px;
}

table{
width:100%;
border-collapse:collapse;
min-width:850px;
}

table th{
background:linear-gradient(90deg,#ff512f,#dd2476);
color:white;
padding:14px;
text-align:center;
font-size:15px;
}

table td{
padding:14px;
text-align:center;
border-bottom:1px solid #eee;
font-size:14px;
}

table tr:hover{
background:#f8fbff;
}

/* ===== Status Badges ===== */
.badge{
padding:6px 14px;
border-radius:20px;
font-size:13px;
color:white;
font-weight:600;
display:inline-block;
}

.pending{
background:#f39c12;
}

.preparing{
background:#3498db;
}

.completed{
background:#27ae60;
}

.cancelled{
background:#e74c3c;
}

/* ===== Footer ===== */
.footer{
margin-top:40px;
text-align:center;
color:#666;
font-size:14px;
padding:20px;
}

/* ===== Responsive ===== */
@media(max-width:768px){

header{
flex-direction:column;
gap:15px;
text-align:center;
}

header nav a{
margin:0 8px;
display:inline-block;
}

.stats{
grid-template-columns:1fr;
}

.search-box form{
flex-direction:column;
}

.table-header{
flex-direction:column;
gap:15px;
}

}

/* ===== Print ===== */
@media print{

header,
.search-box,
.footer{
display:none;
}

body{
background:white;
}

.table-box{
box-shadow:none;
padding:0;
}

}

</style>

</head>

<header>

    <div class="logo">
        <i class="fa-solid fa-utensils"></i>
        <span>College Canteen Admin</span>
    </div>

    <nav>

        <a href="dashboard.php">
            <i class="fa fa-home"></i> Dashboard
        </a>

        <a href="products.php">
            <i class="fa fa-burger"></i> Products
        </a>

        <a href="customers.php">
            <i class="fa fa-users"></i> Customers
        </a>

        <a href="orders.php">
            <i class="fa fa-shopping-cart"></i> Orders
        </a>

        <a href="missing_callback.php">
            <i class="fa fa-bolt"></i> Missing Callback
        </a>

        <a href="reports.php" class="active">
            <i class="fa fa-chart-line"></i> Reports
        </a>
        <a href="messages.php"><i class="fa fa-envelope"></i> Messages</a>
        <a href="logout.php">
            <i class="fa fa-sign-out-alt"></i> Logout
        </a>

    </nav>

</header>

<div class="container">

<div class="page-title">

<h1>📊 Sales Reports</h1>

<p>College Canteen Management System</p>

</div>

<div class="stats">

<div class="card blue">

<i class="fa-solid fa-burger"></i>

<h3>Total Products</h3>

<p><?php echo $totalProducts; ?></p>

</div>

<div class="card green">

<i class="fa-solid fa-users"></i>

<h3>Total Customers</h3>

<p><?php echo $totalCustomers; ?></p>

</div>

<div class="card orange">

<i class="fa-solid fa-cart-shopping"></i>

<h3>Total Orders</h3>

<p><?php echo $totalOrders; ?></p>

</div>

<div class="card purple">

<i class="fa-solid fa-indian-rupee-sign"></i>

<h3>Total Sales</h3>

<p>₹<?php echo number_format($totalSales,2); ?></p>

</div>

</div>

<div class="stats">

<div class="card red">

<i class="fa-solid fa-calendar-day"></i>

<h3>Today's Orders</h3>

<p><?php echo $todayOrders; ?></p>

</div>

<div class="card teal">

<i class="fa-solid fa-wallet"></i>

<h3>Today's Sales</h3>

<p>₹<?php echo number_format($todaySales,2); ?></p>

</div>

<div class="card dark">

<i class="fa-solid fa-chart-column"></i>

<h3>Monthly Sales</h3>

<p>₹<?php echo number_format($monthSales,2); ?></p>

</div>

</div>

<div class="table-box">

<div class="table-header">

<h2>

<i class="fa-solid fa-clock-rotate-left"></i>

Latest Orders

</h2>

<div>

<input
type="text"
id="searchInput"
placeholder="Search Order..."
style="padding:10px;border:1px solid #ccc;border-radius:6px;">

<button
class="print-btn"
onclick="window.print();">

<i class="fa fa-print"></i>

Print Report

</button>

</div>

</div>

<table id="ordersTable">

<thead>

<tr>

<th>ID</th>
<th>User ID</th>
<th>Payment ID</th>
<th>Payment Method</th>
<th>Total Amount</th>
<th>Status</th>
<th>Order Date</th>

</tr>

</thead>

<tbody>
    <?php
if ($latestOrders && mysqli_num_rows($latestOrders) > 0) {
    while($row=mysqli_fetch_assoc($latestOrders)) {
?>

<tr>

<td><?php echo $row['id']; ?></td>

<td><?php echo $row['user_id']; ?></td>

<td>
<?php
echo !empty($row['payment_id']) ? htmlspecialchars($row['payment_id']) : "N/A";
?>
</td>

<td>
<?php
echo !empty($row['payment_method']) ? htmlspecialchars($row['payment_method']) : "Cash";
?>
</td>

<td>
₹<?php echo number_format($row['total_amount'],2); ?>
</td>

<td>

<?php

$status = $row['status'];

if($status=="Completed")
{
    echo "<span class='badge completed'>Completed</span>";
}
elseif($status=="Preparing")
{
    echo "<span class='badge preparing'>Preparing</span>";
}
elseif($status=="Cancelled")
{
    echo "<span class='badge cancelled'>Cancelled</span>";
}
else
{
    echo "<span class='badge pending'>Pending</span>";
}

?>

</td>

<td>
<?php echo date("d-m-Y h:i A",strtotime($row['order_date'])); ?>
</td>

</tr>

<?php
    }
} else {
?>
<tr>
<td colspan="7" style="text-align:center; padding:20px; color:#777;">No Orders Found.</td>
</tr>
<?php
}
?>

</tbody>

</table>

</div>

</div>

<script>

const searchInput = document.getElementById("searchInput");

searchInput.addEventListener("keyup",function(){

let filter = this.value.toLowerCase();

let rows = document.querySelectorAll("#ordersTable tbody tr");

rows.forEach(function(row){

let text = row.innerText.toLowerCase();

if(text.indexOf(filter)>-1){

row.style.display="";

}else{

row.style.display="none";

}

});

});

</script>

</body>
</html>