<?php
session_start();

if(!isset($_SESSION['admin'])){
    header("Location: login.php");
    exit();
}

include("../php/db.php");

$id = intval($_GET['id'] ?? 0);

$stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if(!$result || mysqli_num_rows($result) == 0){
    die("Product not found.");
}
$row = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

$message="";

if(isset($_POST['update'])){

$product_name = trim($_POST['product_name']);
$description = trim($_POST['description']);
$price = (float)$_POST['price'];
$status = trim($_POST['status']);

$image = $row['image'];

if(!empty($_FILES['image']['name'])){

$image = basename($_FILES['image']['name']);

$tmp = $_FILES['image']['tmp_name'];

move_uploaded_file($tmp,"../uploads/".$image);

}

$updateStmt = mysqli_prepare($conn, "UPDATE products SET product_name=?, description=?, price=?, image=?, status=? WHERE id=?");
mysqli_stmt_bind_param($updateStmt, "ssdssi", $product_name, $description, $price, $image, $status, $id);

if(mysqli_stmt_execute($updateStmt)){
    mysqli_stmt_close($updateStmt);
    @mysqli_query($conn, "UPDATE products SET name = product_name WHERE id = $id");
    header("Location: products.php");
    exit();
}else{
    $message="Update Failed";
    mysqli_stmt_close($updateStmt);
}

}
?>

$activePage = 'products';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product #<?php echo $id; ?> - College Canteen Admin</title>
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
            <h1 class="admin-page-title"><i class="fa-solid fa-pen-to-square"></i> Edit Product #<?php echo $id; ?></h1>
            <p class="admin-subtitle">Modify canteen menu item details, price, or availability</p>
        </div>
        <a href="products.php" class="btn-material" style="background:#e2e8f0; color:#334155;">
            <i class="fa-solid fa-arrow-left"></i> Back to Products
        </a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert-material danger">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>

    <div class="admin-card">
        <form method="POST" enctype="multipart/form-data">
            <?php $prodNameField = !empty($row['product_name']) ? $row['product_name'] : (!empty($row['name']) ? $row['name'] : ''); ?>
            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-burger"></i> Product Name *</label>
                <input type="text" name="product_name" class="form-control" value="<?php echo htmlspecialchars($prodNameField); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-align-left"></i> Description *</label>
                <textarea name="description" class="form-control" required><?php echo htmlspecialchars($row['description'] ?? ''); ?></textarea>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                <div class="form-group">
                    <label class="form-label"><i class="fa-solid fa-indian-rupee-sign"></i> Price (₹) *</label>
                    <input type="number" name="price" class="form-control" value="<?php echo htmlspecialchars($row['price'] ?? ''); ?>" step="0.01" min="0" required>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fa-solid fa-toggle-on"></i> Availability Status *</label>
                    <select name="status" class="form-control">
                        <option value="Available" <?php if(($row['status'] ?? '') === 'Available') echo 'selected'; ?>>Available</option>
                        <option value="Out of Stock" <?php if(($row['status'] ?? '') === 'Out of Stock') echo 'selected'; ?>>Out of Stock</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-image"></i> Product Image</label>
                <div style="display:flex; align-items:center; gap:18px; margin-bottom:12px;">
                    <?php if (!empty($row['image'])): ?>
                        <img src="../uploads/<?php echo htmlspecialchars($row['image']); ?>" alt="Current Product Image" style="width:75px; height:75px; object-fit:cover; border-radius:10px; border:2px solid #e2e8f0;">
                    <?php endif; ?>
                    <div style="flex:1;">
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <small style="color:#64748b; font-size:12px; margin-top:4px; display:block;">Leave blank to keep current image. Upload a new image to replace.</small>
                    </div>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:28px;">
                <a href="products.php" class="btn-material" style="background:#e2e8f0; color:#334155;">Cancel</a>
                <button type="submit" name="update" class="btn-material btn-primary" style="padding:10px 24px; font-size:14px;">
                    <i class="fa-solid fa-floppy-disk"></i> Update Product
                </button>
            </div>
        </form>
    </div>
</div>

</body>
</html>