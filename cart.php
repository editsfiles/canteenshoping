<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include("php/db.php");

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = array();
}

// Add Product
if (isset($_POST['add_cart'])) {

    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];

    if ($quantity < 1) {
        $quantity = 1;
    }

    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] += $quantity;
    } else {
        $_SESSION['cart'][$product_id] = $quantity;
    }

    header("Location: cart.php");
    exit();
}

// Update Quantity
if (isset($_POST['update'])) {

    foreach ($_POST['qty'] as $id => $qty) {

        $qty = (int)$qty;

        if ($qty <= 0) {
            unset($_SESSION['cart'][$id]);
        } else {
            $_SESSION['cart'][$id] = $qty;
        }
    }

    header("Location: cart.php");
    exit();
}

// Remove Item
if (isset($_GET['remove'])) {

    $id = (int)$_GET['remove'];

    unset($_SESSION['cart'][$id]);

    header("Location: cart.php");
    exit();
}

$total = 0;
?>

<!DOCTYPE html>
<html>

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Shopping Cart</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<header>

<h2>🛒 Shopping Cart</h2>

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

<div class="cart-container">

<?php

if (count($_SESSION['cart']) == 0) {

echo "<div class='empty'><div class='empty-icon'>🛒</div><div>Your cart is empty.</div><p style='margin-top:10px; font-size:14px; color:#999;'>Start adding items from the menu!</p></div>";

} else {

?>

<form method="POST">

<table class="cart-table">

<tr>

<th>Image</th>

<th>Food Item</th>

<th>Price</th>

<th>Quantity</th>

<th>Subtotal</th>

<th>Action</th>

</tr>

<?php

foreach ($_SESSION['cart'] as $id => $qty) {

    $productId = (int)$id;
    $productStmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id = ? LIMIT 1");

    if (!$productStmt) {
        continue;
    }

    mysqli_stmt_bind_param($productStmt, "i", $productId);
    mysqli_stmt_execute($productStmt);
    $productResult = mysqli_stmt_get_result($productStmt);

    if (mysqli_num_rows($productResult) == 0) {
        mysqli_stmt_close($productStmt);
        continue;
    }

    $row = mysqli_fetch_assoc($productResult);
    mysqli_stmt_close($productStmt);

    $sub = $row['price'] * $qty;

    $total += $sub;

?>

<tr>

<td>
<?php
$cartImg = (!empty($row['image']) && file_exists(__DIR__ . '/uploads/' . $row['image'])) ? $row['image'] : 'Burger.jpg';
$cartName = !empty($row['product_name']) ? $row['product_name'] : (!empty($row['name']) ? $row['name'] : 'Food Item');
?>
<img src="uploads/<?php echo htmlspecialchars($cartImg); ?>" width="80" alt="<?php echo htmlspecialchars($cartName); ?>">

</td>

<td>

<?php echo htmlspecialchars($cartName); ?>

</td>

<td>

₹<?php echo $row['price']; ?>

</td>

<td>

<div class="qty-spinner">

<button type="button" class="qty-minus" onclick="decreaseQty(this)">−</button>

<input
type="number"
name="qty[<?php echo $id; ?>]"
value="<?php echo $qty; ?>"
min="1"
class="qty-input">

<button type="button" class="qty-plus" onclick="increaseQty(this)">+</button>

</div>

</td>

<td>

₹<?php echo $sub; ?>

</td>

<td>

<a class="remove"
href="cart.php?remove=<?php echo $id; ?>"
onclick="return confirm('Remove this item?')">

Remove

</a>

</td>

</tr>

<?php

}

?>

</table>

<div class="cart-buttons">

<button name="update" type="submit">

🔄 Update Cart

</button>

<div class="total">

💰 Total: ₹<?php echo number_format($total, 2); ?>

</div>

</div>

</form>

<div class="checkout">

<a href="checkout.php">

🛍️ Proceed to Checkout →

</a>

</div>

<?php

}

?>

</div>

<script>
function decreaseQty(btn) {
    const input = btn.nextElementSibling;
    const currentVal = parseInt(input.value);
    if (currentVal > 1) {
        input.value = currentVal - 1;
    }
}

function increaseQty(btn) {
    const input = btn.previousElementSibling;
    const currentVal = parseInt(input.value);
    input.value = currentVal + 1;
}
</script>

</body>

</html>