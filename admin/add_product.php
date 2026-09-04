<?php
session_start();

if(!isset($_SESSION['admin'])){
    header("Location: login.php");
    exit();
}

include("../php/db.php");

$message="";

if(isset($_POST['add'])){

$product_name = trim($_POST['product_name']);
$description = trim($_POST['description']);
$price = (float)$_POST['price'];
$status = trim($_POST['status']);

$image = "";
if(!empty($_FILES['image']['name'])){
    $image = basename($_FILES['image']['name']);
    $tmp = $_FILES['image']['tmp_name'];
    move_uploaded_file($tmp,"../uploads/".$image);
}

$stmt = mysqli_prepare($conn, "INSERT INTO products(product_name,description,price,image,status) VALUES(?, ?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "ssdss", $product_name, $description, $price, $image, $status);

if(mysqli_stmt_execute($stmt)){
    $newId = mysqli_insert_id($conn);
    @mysqli_query($conn, "UPDATE products SET name = product_name WHERE id = $newId");
    $message="Product Added Successfully";
}else{
    $message="Error Adding Product";
}
mysqli_stmt_close($stmt);

}

$activePage = 'products';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Product - College Canteen Admin</title>
    <!-- Material Font & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <!-- Standardized Admin Material CSS -->
    <link rel="stylesheet" href="css/admin_material.css">
</head>
<body>

<?php include("header_nav.php"); ?>

<div class="admin-container" style="max-width: 680px;">
    <div class="admin-header-row">
        <div>
            <h1 class="admin-page-title"><i class="fa-solid fa-plus-circle"></i> Add New Product</h1>
            <p class="admin-subtitle">Create and publish a new delicious item to the canteen menu</p>
        </div>
        <a href="products.php" class="btn-material" style="background:#e2e8f0; color:#334155;">
            <i class="fa-solid fa-arrow-left"></i> Back to Products
        </a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert-material <?php echo strpos($message, 'Successfully') !== false ? 'success' : 'danger'; ?>">
            <i class="fa-solid <?php echo strpos($message, 'Successfully') !== false ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>"></i>
            <span><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>

    <div class="admin-card">
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-burger"></i> Product Name *</label>
                <input type="text" name="product_name" class="form-control" placeholder="e.g. Crispy Veg Burger" required>
            </div>

            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-align-left"></i> Description *</label>
                <textarea name="description" class="form-control" placeholder="Describe ingredients, taste, or portion size..." required></textarea>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label class="form-label"><i class="fa-solid fa-indian-rupee-sign"></i> Price (₹) *</label>
                    <input type="number" name="price" class="form-control" placeholder="0.00" step="0.01" min="0" required>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fa-solid fa-toggle-on"></i> Availability Status *</label>
                    <select name="status" class="form-control">
                        <option value="Available">Available</option>
                        <option value="Out of Stock">Out of Stock</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-image"></i> Product Image *</label>
                <input type="file" name="image" class="form-control" accept="image/*" required>
                <small style="color:#64748b; font-size:12px; margin-top:4px; display:block;">Supported formats: JPG, PNG, WEBP (Square images work best).</small>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:28px;">
                <a href="products.php" class="btn-material" style="background:#e2e8f0; color:#334155;">Cancel</a>
                <button type="submit" name="add" class="btn-material btn-orange" style="padding:10px 24px; font-size:14px;">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Save & Publish Product
                </button>
            </div>
        </form>
    </div>
</div>

</body>
</html>