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
    if ($quantity < 1) $quantity = 1;

    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] += $quantity;
    } else {
        $_SESSION['cart'][$product_id] = $quantity;
    }
    header("Location: cart.php");
    exit();
}

// Update Quantity
if (isset($_POST['update']) || isset($_POST['auto_update'])) {
    if (isset($_POST['qty']) && is_array($_POST['qty'])) {
        foreach ($_POST['qty'] as $id => $qty) {
            $id  = (int)$id;
            $qty = (int)$qty;
            if ($qty <= 0) {
                unset($_SESSION['cart'][$id]);
            } else {
                $_SESSION['cart'][$id] = $qty;
            }
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

// Fetch Cart Details
$cartItems = [];
$total = 0;

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $id => $qty) {
        $productId = (int)$id;
        $qty = (int)$qty;
        if ($productId <= 0 || $qty <= 0) continue;

        $stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id = ? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $productId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res && $row = mysqli_fetch_assoc($res)) {
                $cartImg = (!empty($row['image']) && file_exists(__DIR__ . '/uploads/' . $row['image'])) ? $row['image'] : 'Burger.jpg';
                $cartName = !empty($row['product_name']) ? $row['product_name'] : (!empty($row['name']) ? $row['name'] : 'Food Item');
                $price = (float)$row['price'];
                $sub = $price * $qty;
                $total += $sub;

                $cartItems[] = [
                    'id' => $productId,
                    'name' => $cartName,
                    'price' => $price,
                    'qty' => $qty,
                    'subtotal' => $sub,
                    'image' => $cartImg
                ];
            }
            mysqli_stmt_close($stmt);
        }
    }
}

$gst = round($total * 0.05, 2);
$grandTotal = round($total + $gst, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Shopping Cart - College Canteen</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#16a34a">

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: 'Poppins', sans-serif;
}
body {
    background: #f8fafc;
    color: #1e293b;
    min-height: 100vh;
    padding-bottom: 40px;
}

/* ─── APP HEADER ─────────────────────────────────────────────────────────── */
.app-header {
    background: linear-gradient(135deg, #15803d, #16a34a);
    color: white;
    padding: 14px 18px;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 4px 15px rgba(22, 163, 74, 0.25);
}
.header-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}
.header-brand {
    font-size: 18px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}
.header-brand a {
    color: white;
    text-decoration: none;
}
.header-item-count {
    background: rgba(255,255,255,0.25);
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.header-nav {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 2px;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}
.header-nav::-webkit-scrollbar { display: none; }
.header-nav a {
    color: rgba(255, 255, 255, 0.9);
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    padding: 5px 12px;
    border-radius: 8px;
    white-space: nowrap;
    background: rgba(255, 255, 255, 0.12);
    transition: 0.2s;
}
.header-nav a.active {
    background: white;
    color: #15803d;
    font-weight: 700;
}

/* ─── CART CONTAINER ─────────────────────────────────────────────────────── */
.cart-wrap {
    max-width: 900px;
    margin: 16px auto;
    padding: 0 14px;
}

/* ─── EMPTY STATE ────────────────────────────────────────────────────────── */
.empty-cart-card {
    background: white;
    border-radius: 18px;
    padding: 45px 20px;
    text-align: center;
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
    border: 1px solid #e2e8f0;
    margin-top: 30px;
}
.empty-cart-icon {
    font-size: 56px;
    margin-bottom: 12px;
}
.empty-cart-card h3 {
    font-size: 20px;
    color: #0f172a;
    margin-bottom: 6px;
}
.empty-cart-card p {
    color: #64748b;
    font-size: 14px;
    margin-bottom: 22px;
}
.btn-browse {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #16a34a;
    color: white;
    text-decoration: none;
    padding: 12px 26px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 14px;
    box-shadow: 0 4px 14px rgba(22,163,74,0.3);
}

/* ─── MOBILE FOOD CARDS (<= 768px) ───────────────────────────────────────── */
.mobile-cart-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 16px;
}
.cart-card {
    background: white;
    border-radius: 16px;
    padding: 14px;
    display: flex;
    gap: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.03);
    border: 1px solid #e2e8f0;
    position: relative;
}
.cart-card-img {
    width: 78px;
    height: 78px;
    border-radius: 12px;
    object-fit: cover;
    flex-shrink: 0;
    border: 1px solid #f1f5f9;
}
.cart-card-details {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-width: 0;
}
.cart-card-name {
    font-size: 15px;
    font-weight: 600;
    color: #0f172a;
    line-height: 1.3;
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cart-card-price {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 6px;
}
.cart-card-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 4px;
}
.cart-card-subtotal {
    font-size: 15px;
    font-weight: 700;
    color: #16a34a;
}

/* ─── QUANTITY STEPPER ───────────────────────────────────────────────────── */
.stepper {
    display: inline-flex;
    align-items: center;
    background: #f1f5f9;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
}
.stepper-btn {
    width: 32px;
    height: 32px;
    background: white;
    border: none;
    color: #0f172a;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: 0.2s;
}
.stepper-btn:hover {
    background: #e2e8f0;
}
.stepper-input {
    width: 38px;
    height: 32px;
    text-align: center;
    border: none;
    background: transparent;
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
    outline: none;
    -moz-appearance: textfield;
}
.stepper-input::-webkit-outer-spin-button,
.stepper-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.btn-card-remove {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #fee2e2;
    color: #dc2626;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    font-size: 13px;
    transition: 0.2s;
}
.btn-card-remove:hover {
    background: #fca5a5;
    transform: scale(1.08);
}

/* ─── DESKTOP TABLE VIEW (> 768px) ───────────────────────────────────────── */
.desktop-cart-table {
    display: none;
    width: 100%;
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
    border: 1px solid #e2e8f0;
    border-collapse: collapse;
    margin-bottom: 20px;
}
.desktop-cart-table th {
    background: #f8fafc;
    padding: 14px 16px;
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}
.desktop-cart-table td {
    padding: 14px 16px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
}
.desktop-cart-table tr:last-child td {
    border-bottom: none;
}
.desktop-img {
    width: 64px;
    height: 64px;
    border-radius: 10px;
    object-fit: cover;
}

@media (min-width: 769px) {
    .mobile-cart-list { display: none; }
    .desktop-cart-table { display: table; }
}

/* ─── BILL SUMMARY CARD ──────────────────────────────────────────────────── */
.summary-card {
    background: white;
    border-radius: 16px;
    padding: 18px 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.03);
    border: 1px solid #e2e8f0;
    margin-bottom: 16px;
}
.summary-title {
    font-size: 15px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.summary-row {
    display: flex;
    justify-content: space-between;
    font-size: 14px;
    color: #64748b;
    padding: 6px 0;
}
.summary-row.total-row {
    border-top: 1px dashed #cbd5e1;
    margin-top: 8px;
    padding-top: 12px;
    font-size: 17px;
    font-weight: 700;
    color: #0f172a;
}
.summary-row.total-row span:last-child {
    color: #16a34a;
}

/* ─── ACTION BUTTONS ─────────────────────────────────────────────────────── */
.cart-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
@media (min-width: 600px) {
    .cart-actions {
        flex-direction: row;
        justify-content: space-between;
    }
}
.btn-update {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #cbd5e1;
    padding: 12px 18px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s;
}
.btn-update:hover {
    background: #e2e8f0;
}
.btn-checkout {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: linear-gradient(135deg, #16a34a, #15803d);
    color: white;
    text-decoration: none;
    padding: 14px 24px;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 700;
    box-shadow: 0 4px 16px rgba(22,163,74,0.3);
    transition: 0.2s;
    text-align: center;
    flex: 1;
}
.btn-checkout:hover {
    background: #15803d;
    transform: translateY(-1px);
}
</style>
</head>
<body>

<!-- ─── MOBILE APP HEADER ────────────────────────────────────────────────── -->
<header class="app-header">
    <div class="header-top">
        <div class="header-brand">
            <a href="menu.php"><i class="fa-solid fa-arrow-left" style="margin-right:4px;"></i></a>
            <span>🛒 My Cart</span>
        </div>
        <div class="header-item-count">
            <?php echo count($cartItems); ?> Items
        </div>
    </div>
    <nav class="header-nav">
        <a href="index.php"><i class="fa-solid fa-house"></i> Home</a>
        <a href="menu.php"><i class="fa-solid fa-utensils"></i> Menu</a>
        <a href="cart.php" class="active"><i class="fa-solid fa-cart-shopping"></i> Cart</a>
        <a href="my_orders.php"><i class="fa-solid fa-receipt"></i> Orders</a>
        <a href="profile.php"><i class="fa-solid fa-user"></i> Profile</a>
        <a href="contact.php"><i class="fa-solid fa-envelope"></i> Contact</a>
        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </nav>
</header>

<div class="cart-wrap">

<?php if (empty($cartItems)) { ?>
    
    <div class="empty-cart-card">
        <div class="empty-cart-icon">🛒</div>
        <h3>Your Cart is Empty</h3>
        <p>Looks like you haven't added any delicious food yet!</p>
        <a href="menu.php" class="btn-browse">
            <i class="fa-solid fa-utensils"></i> Explore Menu
        </a>
    </div>

<?php } else { ?>

    <form method="POST" id="cartForm">
        <input type="hidden" name="update" value="1">

        <!-- ─── MOBILE CARD VIEW (Phones) ────────────────────────────────── -->
        <div class="mobile-cart-list">
            <?php foreach ($cartItems as $item) { ?>
                <div class="cart-card">
                    <img src="uploads/<?php echo htmlspecialchars($item['image']); ?>" 
                         alt="<?php echo htmlspecialchars($item['name']); ?>" 
                         class="cart-card-img"
                         onerror="this.src='uploads/Burger.jpg'">
                    
                    <div class="cart-card-details">
                        <div class="cart-card-name"><?php echo htmlspecialchars($item['name']); ?></div>
                        <div class="cart-card-price">₹<?php echo number_format($item['price'], 2); ?> each</div>
                        
                        <div class="cart-card-bottom">
                            <!-- Stepper -->
                            <div class="stepper">
                                <button type="button" class="stepper-btn" onclick="stepQty(<?php echo $item['id']; ?>, -1)">−</button>
                                <input type="number" 
                                       name="qty[<?php echo $item['id']; ?>]" 
                                       id="qty_m_<?php echo $item['id']; ?>" 
                                       value="<?php echo $item['qty']; ?>" 
                                       min="1" 
                                       class="stepper-input"
                                       onchange="document.getElementById('cartForm').submit();">
                                <button type="button" class="stepper-btn" onclick="stepQty(<?php echo $item['id']; ?>, 1)">+</button>
                            </div>
                            
                            <div class="cart-card-subtotal">₹<?php echo number_format($item['subtotal'], 2); ?></div>
                        </div>
                    </div>

                    <a href="cart.php?remove=<?php echo $item['id']; ?>" 
                       class="btn-card-remove" 
                       title="Remove item"
                       onclick="return confirm('Remove <?php echo htmlspecialchars($item['name'], ENT_QUOTES); ?> from cart?');">
                        <i class="fa-solid fa-trash-can"></i>
                    </a>
                </div>
            <?php } ?>
        </div>

        <!-- ─── DESKTOP TABLE VIEW (Tablets / Laptops) ────────────────────── -->
        <table class="desktop-cart-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Name</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Subtotal</th>
                    <th style="text-align:center;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cartItems as $item) { ?>
                    <tr>
                        <td style="width:80px;">
                            <img src="uploads/<?php echo htmlspecialchars($item['image']); ?>" 
                                 alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                 class="desktop-img"
                                 onerror="this.src='uploads/Burger.jpg'">
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                        </td>
                        <td>₹<?php echo number_format($item['price'], 2); ?></td>
                        <td>
                            <div class="stepper">
                                <button type="button" class="stepper-btn" onclick="stepQty(<?php echo $item['id']; ?>, -1)">−</button>
                                <input type="number" 
                                       name="qty[<?php echo $item['id']; ?>]" 
                                       id="qty_d_<?php echo $item['id']; ?>" 
                                       value="<?php echo $item['qty']; ?>" 
                                       min="1" 
                                       class="stepper-input"
                                       onchange="document.getElementById('cartForm').submit();">
                                <button type="button" class="stepper-btn" onclick="stepQty(<?php echo $item['id']; ?>, 1)">+</button>
                            </div>
                        </td>
                        <td><strong style="color:#16a34a;">₹<?php echo number_format($item['subtotal'], 2); ?></strong></td>
                        <td style="text-align:center;">
                            <a href="cart.php?remove=<?php echo $item['id']; ?>" 
                               style="color:#dc2626; text-decoration:none; font-size:16px;" 
                               title="Remove"
                               onclick="return confirm('Remove <?php echo htmlspecialchars($item['name'], ENT_QUOTES); ?>?');">
                                <i class="fa-solid fa-trash-can"></i>
                            </a>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

        <!-- ─── BILL SUMMARY ─────────────────────────────────────────────── -->
        <div class="summary-card">
            <div class="summary-title">
                <i class="fa-solid fa-receipt" style="color:#16a34a;"></i>
                <span>Bill Summary</span>
            </div>
            <div class="summary-row">
                <span>Items Subtotal</span>
                <span>₹<?php echo number_format($total, 2); ?></span>
            </div>
            <div class="summary-row">
                <span>GST & Taxes (5%)</span>
                <span>₹<?php echo number_format($gst, 2); ?></span>
            </div>
            <div class="summary-row total-row">
                <span>Total Amount</span>
                <span>₹<?php echo number_format($grandTotal, 2); ?></span>
            </div>
        </div>

        <!-- ─── ACTIONS ROW ──────────────────────────────────────────────── -->
        <div class="cart-actions">
            <button type="submit" name="update" class="btn-update">
                <i class="fa-solid fa-rotate"></i> Update Cart
            </button>
            <a href="checkout.php" class="btn-checkout">
                <span>Proceed to Pay ₹<?php echo number_format($grandTotal, 2); ?></span>
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>

    </form>

<?php } ?>

</div>

<script>
function stepQty(id, change) {
    const inputM = document.getElementById('qty_m_' + id);
    const inputD = document.getElementById('qty_d_' + id);
    
    let current = parseInt(inputM ? inputM.value : (inputD ? inputD.value : 1)) || 1;
    let nextVal = current + change;
    if (nextVal < 1) nextVal = 1;
    
    if (inputM) inputM.value = nextVal;
    if (inputD) inputD.value = nextVal;
    
    document.getElementById('cartForm').submit();
}
</script>

<?php include_once("php/install_pwa_banner.php"); ?>
</body>
</html>