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
$activePage = 'products';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - College Canteen Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <link rel="stylesheet" href="css/admin_material.css">
    <style>
        .product-thumb {
            width: 54px;
            height: 54px;
            object-fit: cover;
            border-radius: 10px;
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
            border: 2px solid #ffffff;
        }
    </style>
</head>
<body>

<?php include("header_nav.php"); ?>

<main class="admin-container">

    <div class="admin-header-row">
        <div>
            <h1 class="admin-page-title">
                <i class="fa-solid fa-burger"></i> Product Management
            </h1>
            <p class="admin-subtitle">Add, edit, and control food items & prices in the canteen catalog</p>
        </div>

        <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
            <form method="GET" class="search-container">
                <i class="fa-solid fa-magnifying-glass" style="color:#94a3b8;"></i>
                <input 
                    type="text" 
                    name="search" 
                    placeholder="Search by food name or description..." 
                    value="<?php echo htmlspecialchars($search); ?>"
                >
                <?php if (!empty($search)): ?>
                    <a href="products.php" style="color:#94a3b8; text-decoration:none;"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </form>

            <a href="add_product.php" class="btn-material btn-orange">
                <i class="fa-solid fa-plus"></i> Add New Product
            </a>
        </div>
    </div>

    <!-- STRUCTURED TABLE WITH RED-ORANGE HEADER -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="material-table">
                <thead>
                    <tr>
                        <th style="width:70px;">ID</th>
                        <th style="width:80px;">Image</th>
                        <th>Product Name</th>
                        <th>Description</th>
                        <th>Price</th>
                        <th>Availability</th>
                        <th style="text-align:right; width:160px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($result) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($result)): 
                            $admImg = (!empty($row['image']) && file_exists(__DIR__ . '/../uploads/' . $row['image'])) ? $row['image'] : 'Burger.jpg';
                            $admName = !empty($row['product_name']) ? $row['product_name'] : (!empty($row['name']) ? $row['name'] : 'Food Item');
                            $isAvail = strtolower(trim($row['status'])) === 'available';
                        ?>
                        <tr>
                            <td><strong>#<?php echo $row['id']; ?></strong></td>
                            <td>
                                <img src="../uploads/<?php echo htmlspecialchars($admImg); ?>" class="product-thumb" alt="<?php echo htmlspecialchars($admName); ?>">
                            </td>
                            <td>
                                <div style="font-weight:700; color:#0f172a; font-size:15px;"><?php echo htmlspecialchars($admName); ?></div>
                            </td>
                            <td style="color:#64748b; font-size:13px; max-width:320px;">
                                <?php echo htmlspecialchars($row['description'] ?? ''); ?>
                            </td>
                            <td>
                                <strong style="font-size:16px; color:#16a34a;">₹<?php echo number_format($row['price'], 2); ?></strong>
                            </td>
                            <td>
                                <span class="badge-status <?php echo $isAvail ? 'badge-completed' : 'badge-pending'; ?>">
                                    <?php if ($isAvail): ?>
                                        <i class="fa-solid fa-circle-check"></i> Available
                                    <?php else: ?>
                                        <i class="fa-solid fa-circle-xmark"></i> Out of Stock
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <a href="edit_product.php?id=<?php echo $row['id']; ?>" class="btn-material btn-primary" style="padding:6px 12px; font-size:12px; margin-right:4px;">
                                    <i class="fa-solid fa-pen-to-square"></i> Edit
                                </a>
                                <a href="delete_product.php?id=<?php echo $row['id']; ?>" class="btn-material btn-danger" style="padding:6px 12px; font-size:12px;" onclick="return confirm('Are you sure you want to delete this food item?');">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:40px; color:#94a3b8;">
                                <i class="fa-solid fa-utensils" style="font-size:36px; margin-bottom:10px; display:block; opacity:0.4;"></i>
                                No food products found matching your search.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

</body>
</html>