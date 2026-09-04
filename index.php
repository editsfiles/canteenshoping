<?php
session_start();

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

include("php/db.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>College Canteen</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<header>

<div class="logo">
🍽 College Canteen
</div>

<nav>

<a href="index.php">Home</a>

<a href="menu.php">Menu</a>

<a href="cart.php">Cart</a>

<a href="my_orders.php">My Orders</a>

<a href="contact.php">Contact</a>

<a href="logout.php">Logout</a>

</nav>

</header>

<div class="hero">

<h1>
Welcome,
<?php echo htmlspecialchars($_SESSION['user_name']); ?> 👋
</h1>

<p>

Order your favorite food quickly from the college canteen.

</p>

<a href="menu.php">

Order Now

</a>

</div>

<h2 class="section-title">

Food Categories

</h2>

<div class="categories">

<div class="card">

<h3>🍔 Burgers</h3>

<p>Fresh & Delicious Burgers</p>

</div>

<div class="card">

<h3>🍕 Pizza</h3>

<p>Hot Cheese Pizza</p>

</div>

<div class="card">

<h3>🥤 Drinks</h3>

<p>Juices & Soft Drinks</p>

</div>

<div class="card">

<h3>🍟 Snacks</h3>

<p>French Fries & More</p>

</div>

<div class="card">

<h3>🍰 Desserts</h3>

<p>Cakes & Ice Cream</p>

</div>

<div class="card">

<h3>☕ Coffee & Tea</h3>

<p>Hot Beverages</p>

</div>

</div>

<footer>

<center>College Canteen Management System
</center>
</footer>

</body>

</html>