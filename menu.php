<?php
session_start();

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

include("php/db.php");

$search = "";
if (isset($_GET['search']) && trim($_GET['search']) != "") {
    $search = trim($_GET['search']);
    $keyword = "%" . $search . "%";

    $stmt = @$conn->prepare("SELECT * FROM products WHERE status='Available' AND product_name LIKE ? ORDER BY id DESC");
    if (!$stmt) {
        $stmt = $conn->prepare("SELECT * FROM products WHERE status='Available' AND name LIKE ? ORDER BY id DESC");
    }

    if ($stmt) {
        $stmt->bind_param("s", $keyword);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = mysqli_query($conn, "SELECT * FROM products WHERE status='Available' ORDER BY id DESC");
    }
} else {
    $result = mysqli_query($conn, "SELECT * FROM products WHERE status='Available' ORDER BY id DESC");
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Food Menu</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<header>
<h2>🍽 College Canteen</h2>
<div>
<nav>

<a href="index.php">Home</a>

<a href="menu.php">Menu</a>

<a href="cart.php">Cart</a>

<a href="my_orders.php">My Orders</a>

<a href="contact.php">Contact</a>

<a href="logout.php">Logout</a>

</nav>
</div>
</header>

<div class="container">
<div class="search-box">
<form method="GET">
<input type="text" name="search" placeholder="Search Food..." value="<?php echo htmlspecialchars($search); ?>">
<button type="submit">Search</button>
</form>
</div>

<div class="products">
<?php
if(mysqli_num_rows($result)>0){
while($row=mysqli_fetch_assoc($result)){
    $imageName = (!empty($row['image']) && file_exists(__DIR__ . '/uploads/' . $row['image'])) ? $row['image'] : 'Burger.jpg';
    $productTitle = !empty($row['product_name']) ? $row['product_name'] : (!empty($row['name']) ? $row['name'] : 'Food Item');
?>
<div class="card">
<img src="uploads/<?php echo htmlspecialchars($imageName); ?>" alt="<?php echo htmlspecialchars($productTitle); ?>">
<h3><?php echo htmlspecialchars($productTitle); ?></h3>
<p><?php echo htmlspecialchars($row['description']); ?></p>
<div class="price">₹<?php echo number_format($row['price'],2); ?></div>

<form action="cart.php" method="POST">
<input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">
<div class="qty-box">
<button type="button" class="qty-btn" onclick="changeQty('q<?php echo $row['id'];?>',-1)">-</button>
<input type="text" id="q<?php echo $row['id'];?>" name="quantity" value="1" readonly>
<button type="button" class="qty-btn" onclick="changeQty('q<?php echo $row['id'];?>',1)">+</button>
</div>
<button type="submit" name="add_cart">🛒 Add to Cart</button>
</form>
</div>
<?php }} else { echo "<h2>No Food Items Available.</h2>"; } ?>
</div>
</div>

<script>
function changeQty(id,val){
 let e=document.getElementById(id);
 let q=parseInt(e.value)+val;
 if(q<1) q=1;
 e.value=q;
}
</script>
</body>
</html>
