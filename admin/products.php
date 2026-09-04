<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

include("../php/db.php");

// Search
$search = "";

if (isset($_GET['search']) && trim($_GET['search']) != "") {
    $search = mysqli_real_escape_string($conn, trim($_GET['search']));

    $sql = "SELECT * FROM products
            WHERE product_name LIKE '%$search%'
               OR description LIKE '%$search%'
            ORDER BY id DESC";
} else {
    $sql = "SELECT * FROM products ORDER BY id DESC";
}

$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Products</title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Segoe UI',sans-serif;
}

body{
    background:linear-gradient(135deg,#eef2ff,#f8fafc,#fff7ed);
}

/* Header */

header{
    background:linear-gradient(90deg,#6a11cb,#2575fc);
    color:#fff;
    padding:18px 35px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    box-shadow:0 3px 10px rgba(0,0,0,.2);
}

header h2{
    font-size:28px;
}

header nav a{
    color:white;
    text-decoration:none;
    margin-left:20px;
    font-weight:bold;
    transition:.3s;
}

header nav a:hover{
    color:#ffe082;
}

/* Container */

.container{
    width:95%;
    margin:30px auto;
}

/* Search Area */

.top{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:25px;
}

.top form{
    display:flex;
}

.top input{
    width:280px;
    padding:12px;
    border:2px solid #6a11cb;
    border-radius:8px 0 0 8px;
    outline:none;
}

.searchBtn{
    background:#6a11cb;
    color:white;
    border:none;
    padding:12px 25px;
    border-radius:0 8px 8px 0;
    cursor:pointer;
    transition:.3s;
}

.searchBtn:hover{
    background:#4e0ca8;
}

.addBtn{
    background:linear-gradient(90deg,#00b09b,#96c93d);
    color:white;
    padding:12px 22px;
    border-radius:8px;
    text-decoration:none;
    font-weight:bold;
    transition:.3s;
}

.addBtn:hover{
    transform:scale(1.05);
}

/* Table */

table{
    width:100%;
    border-collapse:collapse;
    background:white;
    border-radius:12px;
    overflow:hidden;
    box-shadow:0 8px 20px rgba(0,0,0,.15);
}

table th{
    background:linear-gradient(90deg,#ff512f,#dd2476);
    color:white;
    padding:14px;
    font-size:17px;
}

table td{
    padding:15px;
    text-align:center;
    border-bottom:1px solid #eee;
}

table tr:nth-child(even){
    background:#f9f9f9;
}

table tr:hover{
    background:#fff3cd;
    transition:.3s;
}

table img{
    width:90px;
    height:90px;
    border-radius:10px;
    object-fit:cover;
    border:3px solid #6a11cb;
}

/* Buttons */

.edit{
    background:#ffc107;
    color:black;
    padding:8px 15px;
    border-radius:5px;
    text-decoration:none;
    font-weight:bold;
}

.edit:hover{
    background:#ff9800;
    color:white;
}

.delete{
    background:#e53935;
    color:white;
    padding:8px 15px;
    border-radius:5px;
    text-decoration:none;
    font-weight:bold;
}

.delete:hover{
    background:#b71c1c;
}

.status{
    background:#4CAF50;
    color:white;
    padding:5px 12px;
    border-radius:20px;
    font-size:14px;
}</style>

</head>

<body>

<header>

<h2>🍽 College Canteen Admin</h2>

<nav>
<a href="dashboard.php">Dashboard</a>
<a href="products.php">Products</a>
<a href="customers.php">Customers</a>
<a href="orders.php">Orders</a>
<a href="missing_callback.php" style="color:#ffeb3b;font-weight:700;">⚡ Missing Callback</a>
<a href="reports.php">Reports</a>
<a href="messages.php">Messages</a>
<a href="logout.php">Logout</a>
</nav>

</header>

<div class="container">

<div class="top">

<form method="GET">

<input
type="text"
name="search"
placeholder="Search Product"
value="<?php echo htmlspecialchars($search); ?>">

<button type="submit" class="searchBtn">
Search
</button>

</form>

<a href="add_product.php" class="addBtn">
+ Add Product
</a>

</div>

<table>

<tr>
<th>ID</th>
<th>Image</th>
<th>Product</th>
<th>Description</th>
<th>Price</th>
<th>Status</th>
<th>Action</th>
</tr>

<?php

if(mysqli_num_rows($result)>0)
{
    while($row=mysqli_fetch_assoc($result))
    {
?>

<tr>

<td><?php echo $row['id']; ?></td>

<td>
<?php
$admImg = (!empty($row['image']) && file_exists(__DIR__ . '/../uploads/' . $row['image'])) ? $row['image'] : 'Burger.jpg';
$admName = !empty($row['product_name']) ? $row['product_name'] : (!empty($row['name']) ? $row['name'] : 'Food Item');
?>
<img src="../uploads/<?php echo htmlspecialchars($admImg); ?>" alt="<?php echo htmlspecialchars($admName); ?>">
</td>

<td><?php echo htmlspecialchars($admName); ?></td>

<td><?php echo htmlspecialchars($row['description']); ?></td>

<td>₹<?php echo number_format($row['price'],2); ?></td>

<td><?php echo htmlspecialchars($row['status']); ?></td>

<td>

<a class="edit"
href="edit_product.php?id=<?php echo $row['id']; ?>">
Edit
</a>

<a class="delete"
href="delete_product.php?id=<?php echo $row['id']; ?>"
onclick="return confirm('Delete this product?')">
Delete
</a>

</td>

</tr>

<?php
    }
}
else
{
?>

<tr>
<td colspan="7">No Products Found.</td>
</tr>

<?php
}
?>

</table>

</div>

</body>
</html>