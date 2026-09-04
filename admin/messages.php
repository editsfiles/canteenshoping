<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

include("../php/db.php");

/* ===============================
   SEARCH
================================ */

$search = "";

if(isset($_GET['search']))
{
    $search = mysqli_real_escape_string($conn, $_GET['search']);

    $result = mysqli_query($conn,"
    SELECT *
    FROM contacts
    WHERE
    name LIKE '%$search%'
    OR email LIKE '%$search%'
    OR subject LIKE '%$search%'
    OR message LIKE '%$search%'
    ORDER BY id DESC
    ");

}
else
{

    $result = mysqli_query($conn,"
    SELECT *
    FROM contacts
    ORDER BY id DESC
    ");

}

/* ===============================
   TOTAL MESSAGES
================================ */

$totalMessages = mysqli_num_rows(
mysqli_query($conn,"SELECT * FROM contacts")
);

/* ===============================
   TODAY MESSAGES
================================ */

$todayMessages = mysqli_num_rows(

mysqli_query($conn,"
SELECT *
FROM contacts
WHERE DATE(created_at)=CURDATE()
")

);

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Contact Messages | College Canteen Admin</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

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

/* ===========================
   HEADER
=========================== */

header{

background:linear-gradient(90deg,#6a11cb,#2575fc);

padding:18px 40px;

display:flex;

justify-content:space-between;

align-items:center;

box-shadow:0 5px 15px rgba(0,0,0,.15);

}

.logo{

font-size:24px;

font-weight:bold;

color:#fff;

}

nav{

display:flex;

align-items:center;

gap:30px;

}

nav a{

text-decoration:none;

color:#fff;

font-size:17px;

font-weight:600;

transition:.3s;

}

nav a:hover{

color:#ffe082;

}

/* ===========================
   PAGE
=========================== */

.container{

width:95%;

margin:30px auto;

}

.page-title{

display:flex;

justify-content:space-between;

align-items:center;

margin-bottom:25px;

}

.page-title h1{

font-size:36px;

color:#2c3e50;

}

.page-title p{

color:#666;

margin-top:5px;

}

/* ===========================
   SEARCH
=========================== */

.search-box{

display:flex;

justify-content:flex-end;

margin-bottom:25px;

}

.search-box form{

display:flex;

}

.search-box input{

width:320px;

padding:12px 15px;

border:2px solid #6a11cb;

border-right:none;

outline:none;

border-radius:8px 0 0 8px;

font-size:15px;

}

.search-box button{

padding:12px 20px;

background:#6a11cb;

border:none;

color:#fff;

cursor:pointer;

font-weight:bold;

border-radius:0 8px 8px 0;

transition:.3s;

}

.search-box button:hover{

background:#5520c8;

}
/* ===========================
   STATISTICS CARDS
=========================== */

.stats{

display:grid;

grid-template-columns:repeat(auto-fit,minmax(250px,1fr));

gap:25px;

margin-bottom:30px;

}

.card{

background:#fff;

padding:25px;

border-radius:15px;

box-shadow:0 8px 20px rgba(0,0,0,.12);

display:flex;

align-items:center;

gap:20px;

transition:.3s;

}

.card:hover{

transform:translateY(-6px);

}

.card i{

font-size:40px;

color:#6a11cb;

}

.card h3{

font-size:18px;

color:#666;

margin-bottom:8px;

}

.card h2{

font-size:32px;

color:#2c3e50;

}

/* ===========================
   TABLE
=========================== */

.table-box{

background:#fff;

border-radius:15px;

overflow:hidden;

box-shadow:0 8px 20px rgba(0,0,0,.12);

}

table{

width:100%;

border-collapse:collapse;

}

table th{

background:linear-gradient(90deg,#ff512f,#dd2476);

color:#fff;

padding:18px;

font-size:16px;

}

table td{

padding:18px;

text-align:center;

border-bottom:1px solid #eee;

}

table tr:hover{

background:#f8f9ff;

}

/* ===========================
   BUTTONS
=========================== */

.btn-view{

display:inline-block;

padding:10px 18px;

background:#28a745;

color:#fff;

text-decoration:none;

border-radius:6px;

font-weight:bold;

margin-right:8px;

transition:.3s;

}

.btn-view:hover{

background:#218838;

}

.btn-delete{

display:inline-block;

padding:10px 18px;

background:#e53935;

color:#fff;

text-decoration:none;

border-radius:6px;

font-weight:bold;

transition:.3s;

}

.btn-delete:hover{

background:#c62828;

}

/* ===========================
   RESPONSIVE
=========================== */

@media(max-width:768px){

header{

flex-direction:column;

gap:15px;

}

nav{

flex-wrap:wrap;

justify-content:center;

}

.search-box{

justify-content:center;

}

.search-box input{

width:220px;

}

.page-title{

flex-direction:column;

align-items:flex-start;

gap:10px;

}

.stats{

grid-template-columns:1fr;

}

.table-box{

overflow-x:auto;

}

table{

min-width:900px;

}

}

/* ===========================
   END CSS
=========================== */

</style>

</head>
<body>

<!-- ===========================
     HEADER
=========================== -->

<header>

    <div class="logo">
        🍽 College Canteen Admin
    </div>

    <nav>

        <a href="dashboard.php">
            <i class="fa-solid fa-house"></i> Dashboard
        </a>

        <a href="products.php">
            <i class="fa-solid fa-burger"></i> Products
        </a>

        <a href="customers.php">
            <i class="fa-solid fa-users"></i> Customers
        </a>

        <a href="orders.php">
            <i class="fa-solid fa-cart-shopping"></i> Orders
        </a>

        <a href="missing_callback.php">
            <i class="fa-solid fa-bolt"></i> Missing Callback
        </a>

        <a href="reports.php">
            <i class="fa-solid fa-chart-line"></i> Reports
        </a>

        <a href="messages.php" style="color:#ffe082;">
            <i class="fa-solid fa-envelope"></i> Messages
        </a>

        <a href="logout.php">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>

    </nav>

</header>

<!-- ===========================
     CONTAINER
=========================== -->

<div class="container">

    <div class="page-title">

        <div>

            <h1>📩 Contact Messages</h1>

            <p>View and manage all customer contact messages.</p>

        </div>

    </div>

<!-- ===========================
     SEARCH BOX
=========================== -->

<div class="search-box">

<form method="GET">

<input
type="text"
name="search"
placeholder="Search by Name, Email, Subject..."
value="<?php echo htmlspecialchars($search); ?>">

<button type="submit">

<i class="fa-solid fa-magnifying-glass"></i>

Search

</button>

</form>

</div>

<!-- ===========================
     STATISTICS
=========================== -->

<div class="stats">

<div class="card">

<i class="fa-solid fa-envelope"></i>

<div>

<h3>Total Messages</h3>

<h2><?php echo $totalMessages; ?></h2>

</div>

</div>

<div class="card">

<i class="fa-solid fa-calendar-day"></i>

<div>

<h3>Today's Messages</h3>

<h2><?php echo $todayMessages; ?></h2>

</div>

</div>

</div>

<!-- ===========================
     TABLE STARTS HERE
=========================== -->

<div class="table-box">

<table>

<thead>

<tr>

<th>ID</th>

<th>Name</th>

<th>Email</th>

<th>Subject</th>

<th>Message</th>

<th>Date</th>

<th>Action</th>

</tr>

</thead>

<tbody>
    <?php

if(mysqli_num_rows($result) > 0)
{

while($row = mysqli_fetch_assoc($result))
{

?>

<tr>

<td>
<?php echo $row['id']; ?>
</td>

<td>
<?php echo htmlspecialchars($row['name']); ?>
</td>

<td>
<?php echo htmlspecialchars($row['email']); ?>
</td>

<td>
<?php echo htmlspecialchars($row['subject']); ?>
</td>

<td style="text-align:left;max-width:350px;">

<?php

echo nl2br(htmlspecialchars($row['message']));

?>

</td>

<td>

<?php echo date("d-m-Y",strtotime($row['created_at'])); ?>

<br>

<small>

<?php echo date("h:i A",strtotime($row['created_at'])); ?>

</small>

</td>

<td>

<a
href="view_message.php?id=<?php echo $row['id']; ?>"
class="btn-view">

<i class="fa-solid fa-eye"></i>

View

</a>

<br><br>

<a

href="delete_message.php?id=<?php echo $row['id']; ?>"

class="btn-delete"

onclick="return confirm('Are you sure you want to delete this message?');">

<i class="fa-solid fa-trash"></i>

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

<td colspan="7" style="padding:40px;">

<i class="fa-solid fa-envelope-open-text"
style="font-size:50px;color:#999;"></i>

<br><br>

<h2>No Messages Found</h2>

<p style="color:#777;">

There are no contact messages available.

</p>

</td>

</tr>

<?php

}

?>

</tbody>

</table>

</div>

</div>

<footer style="

margin-top:40px;

background:#2c3e50;

padding:20px;

text-align:center;

color:#fff;

">

© <?php echo date("Y"); ?>

College Canteen Management System

</footer>

</body>

</html>