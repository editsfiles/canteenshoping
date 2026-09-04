<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

include("../php/db.php");

if(!isset($_GET['id'])){
    header("Location: customers.php");
    exit();
}

$id = intval($_GET['id']);

$sql = "SELECT * FROM users WHERE id='$id'";
$result = mysqli_query($conn,$sql);

if(mysqli_num_rows($result)==0){
    die("Customer Not Found");
}

$row = mysqli_fetch_assoc($result);

// Total Orders
$orderQuery = mysqli_query($conn,"SELECT COUNT(*) AS total_orders FROM orders WHERE user_id='$id'");
$orderData = mysqli_fetch_assoc($orderQuery);

// Total Amount Spent (Completed Orders)
$totalQuery = mysqli_query($conn,"SELECT IFNULL(SUM(total_amount), 0) AS total_amount FROM orders WHERE user_id='$id' AND status='Completed'");
$totalData = mysqli_fetch_assoc($totalQuery);
?>

<!DOCTYPE html>
<html>

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Customer Details</title>

<link rel="stylesheet" href="../css/style.css">

<style>

body{
font-family:Arial;
background:#f5f5f5;
}

.container{

width:700px;
margin:40px auto;
background:white;
padding:30px;
box-shadow:0 0 10px gray;

}

table{

width:100%;
border-collapse:collapse;

}

table td{

padding:12px;
border:1px solid #ddd;

}

.title{

background:#2c3e50;
color:white;
padding:15px;
margin-bottom:20px;

}

.back{

display:inline-block;
margin-top:20px;
padding:10px 20px;
background:#3498db;
color:white;
text-decoration:none;

}

</style>

</head>

<body>

<div class="container">

<div class="title">

<h2>Customer Details</h2>

</div>

<table>

<tr>

<td><b>Student Name</b></td>

<td><?php echo $row['name']; ?></td>

</tr>

<tr>

<td><b>Register Number</b></td>

<td><?php echo $row['regno']; ?></td>

</tr>

<tr>

<td><b>Department</b></td>

<td><?php echo $row['department']; ?></td>

</tr>

<tr>

<td><b>Email</b></td>

<td><?php echo $row['email']; ?></td>

</tr>

<tr>

<td><b>Registered Date</b></td>

<td><?php echo $row['created_at']; ?></td>

</tr>

<tr>

<td><b>Total Orders</b></td>

<td><?php echo $orderData['total_orders']; ?></td>

</tr>

<tr>

<td><b>Total Amount Spent</b></td>

<td>₹<?php echo ($totalData['total_amount']) ? $totalData['total_amount'] : 0; ?></td>

</tr>

</table>

<?php
$custOrders = mysqli_query($conn, "SELECT * FROM orders WHERE user_id='$id' ORDER BY id DESC LIMIT 10");
if ($custOrders && mysqli_num_rows($custOrders) > 0):
?>
<h3 style="margin:25px 0 12px; color:#2c3e50; font-size:18px;">Recent Order History</h3>
<table style="margin-bottom:20px; font-size:14px;">
<tr style="background:#f1f5f9;">
    <th>Order ID</th>
    <th>Amount</th>
    <th>Payment Status</th>
    <th>Kitchen Status</th>
    <th>Date</th>
    <th>Invoice</th>
</tr>
<?php while($co = mysqli_fetch_assoc($custOrders)): ?>
<tr>
    <td><strong>#<?php echo (int)$co['id']; ?></strong></td>
    <td>₹<?php echo number_format((float)$co['total_amount'], 2); ?></td>
    <td><span style="font-weight:600; color:<?php echo (strtolower($co['status'])==='completed'||strtolower($co['status'])==='paid') ? '#16a34a' : '#d97706'; ?>;"><?php echo htmlspecialchars($co['status']); ?></span></td>
    <td><?php echo htmlspecialchars($co['food_status'] ?? 'Preparing'); ?></td>
    <td><?php echo htmlspecialchars($co['order_date'] ?? '-'); ?></td>
    <td><a href="../invoice.php?order_id=<?php echo (int)$co['id']; ?>" target="_blank" style="color:#2563eb; font-weight:600; text-decoration:none;">📄 Invoice</a></td>
</tr>
<?php endwhile; ?>
</table>
<?php endif; ?>

<a href="customers.php" class="back">

← Back to Customers

</a>

</div>

</body>

</html>