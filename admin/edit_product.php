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

<!DOCTYPE html>
<html>

<head>

<title>Edit Product</title>

<link rel="stylesheet" href="../css/style.css">

<style>

body{
font-family:Arial;
background:#f4f4f4;
}

.container{
width:500px;
margin:40px auto;
background:white;
padding:25px;
box-shadow:0 0 10px gray;
}

input,textarea,select{
width:100%;
padding:10px;
margin:10px 0;
}

button{
width:100%;
padding:10px;
background:#007bff;
color:white;
border:none;
cursor:pointer;
}

img{
width:120px;
height:120px;
object-fit:cover;
margin-bottom:10px;
}

</style>

</head>

<body>

<div class="container">

<h2>Edit Product</h2>

<form method="POST" enctype="multipart/form-data">

<?php $prodNameField = !empty($row['product_name']) ? $row['product_name'] : (!empty($row['name']) ? $row['name'] : ''); ?>
<input
type="text"
name="product_name"
value="<?php echo htmlspecialchars($prodNameField); ?>"
required>

<textarea
name="description"
required><?php echo htmlspecialchars($row['description'] ?? ''); ?></textarea>

<input
type="number"
name="price"
value="<?php echo htmlspecialchars($row['price'] ?? ''); ?>"
step="0.01"
required>

<select name="status">

<option
<?php if($row['status']=="Available") echo "selected";?>>

Available

</option>

<option
<?php if($row['status']=="Out of Stock") echo "selected";?>>

Out of Stock

</option>

</select>

<img src="../uploads/<?php echo $row['image'];?>">

<input
type="file"
name="image">

<button
type="submit"
name="update">

Update Product

</button>

</form>

<br>

<a href="products.php">

← Back

</a>

<p style="color:red;">

<?php echo $message; ?>

</p>

</div>

</body>

</html>