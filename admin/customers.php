<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

include("../php/db.php");

// Delete Customer
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    if ($id > 0) {
        @mysqli_query($conn, "DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE user_id='$id')");
        @mysqli_query($conn, "DELETE FROM orders WHERE user_id='$id'");
        mysqli_query($conn, "DELETE FROM users WHERE id='$id'");
    }

    echo "<script>
    alert('Customer Deleted Successfully');
    window.location='customers.php';
    </script>";
    exit();
}

// Search
$search = "";

if(isset($_GET['search'])){
    $search = mysqli_real_escape_string($conn,$_GET['search']);

    $query = mysqli_query($conn,"
    SELECT * FROM users
    WHERE
    name LIKE '%$search%'
    OR regno LIKE '%$search%'
    OR department LIKE '%$search%'
    OR email LIKE '%$search%'
    ORDER BY id DESC
    ");

}else{

    $query = mysqli_query($conn,"
    SELECT * FROM users
    ORDER BY id DESC
    ");

}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Customers</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>

*{
margin:0;
padding:0;
box-sizing:border-box;
font-family:'Poppins',sans-serif;
}

body{
background:#eef2f7;
}

header{

display:flex;
justify-content:space-between;
align-items:center;

padding:18px 20px;

background:linear-gradient(90deg,#6a11cb,#2575fc);

color:#fff;

}

header h2{
font-size:25px;
}

header nav a{

color:white;
text-decoration:none;
margin-left:30px;
font-size:22px;
font-weight:300;

}

header nav a:hover{
color:#ffe082;
}

.container{

width:95%;
margin:40px auto;

}

.title{

font-size:46px;
font-weight:bold;

color:#2c3e50;

margin-bottom:30px;

}

.search-box{

display:flex;
width:420px;

margin-bottom:30px;

}

.search-box input{

flex:1;

padding:15px;

font-size:18px;

border:2px solid #6a11cb;

border-right:none;

border-radius:8px 0 0 8px;

outline:none;

}

.search-box button{

width:130px;

background:#6a11cb;

color:white;

border:none;

font-size:18px;

cursor:pointer;

border-radius:0 8px 8px 0;

}

.search-box button:hover{

background:#4e0fa3;

}

table{

width:100%;

border-collapse:collapse;

background:white;

border-radius:10px;

overflow:hidden;

box-shadow:0 10px 25px rgba(0,0,0,.15);

}

th{

padding:18px;

background:linear-gradient(90deg,#ff512f,#dd2476);

color:white;

font-size:20px;

}

td{

padding:18px;

text-align:center;

font-size:18px;

border-bottom:1px solid #ddd;

}

tr:hover{

background:#f8f8f8;

}

.btn{

padding:10px 18px;

border:none;

color:white;

text-decoration:none;

border-radius:5px;

font-size:16px;

margin:2px;

display:inline-block;

}

.view{

background:#28a745;

}

.view:hover{

background:#218838;

}

.delete{

background:#e53935;

}

.delete:hover{

background:#c62828;

}

@media(max-width:900px){

table{
display:block;
overflow-x:auto;
}

header{
flex-direction:column;
}

header nav{
margin-top:15px;
}

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

<a href="missing_callback.php" style="color:#ffeb3b;font-weight:700;">⚡ Missing Callback</a>

<a href="reports.php">Reports</a>

<a href="messages.php">Messages</a>

<a href="logout.php">Logout</a>

</nav>

</header>

<div class="container">

<div class="title">

Registered Customers

</div>

<form method="GET">

<div class="search-box">

<input
type="text"
name="search"
placeholder="Search Customer"
value="<?php echo htmlspecialchars($search); ?>">

<button>

Search

</button>

</div>

</form>

<table>

<tr>

<th>ID</th>

<th>Name</th>

<th>Register No</th>

<th>Department</th>

<th>Email</th>

<th>Action</th>

</tr>

<?php

if(mysqli_num_rows($query)>0){

while($row=mysqli_fetch_assoc($query)){

?>

<tr>

<td><?php echo $row['id']; ?></td>

<td><?php echo htmlspecialchars($row['name']); ?></td>

<td><?php echo htmlspecialchars($row['regno']); ?></td>

<td><?php echo htmlspecialchars($row['department']); ?></td>

<td><?php echo htmlspecialchars($row['email']); ?></td>

<td>

<a
class="btn view"
href="customer_details.php?id=<?php echo $row['id']; ?>">

View

</a>

<a
class="btn delete"
href="customers.php?delete=<?php echo $row['id']; ?>"
onclick="return confirm('Delete this customer?')">

Delete

</a>

</td>

</tr>

<?php

}

}else{

?>

<tr>

<td colspan="6">

No Customers Found

</td>

</tr>

<?php

}

?>

</table>

</div>

</body>

</html>