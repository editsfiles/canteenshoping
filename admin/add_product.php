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
?>

<!DOCTYPE html>
<html>
<head>

<title>Add Product</title>

<link rel="stylesheet" href="../css/style.css">

<style>

body{
font-family:Arial;
background:#f5f5f5;
}

.container{
width:500px;
margin:40px auto;
background:white;
padding:20px;
box-shadow:0 0 10px gray;
}

input,textarea,select{
width:100%;
padding:10px;
margin:10px 0;
}

button{
background:green;
color:white;
padding:10px;
width:100%;
border:none;
cursor:pointer;
}

a{
text-decoration:none;
}

</style>

</head>

<body>

<div class="container">

<h2>Add Product</h2>

<form method="POST" enctype="multipart/form-data">

<input
type="text"
name="product_name"
placeholder="Product Name"
required>

<textarea
name="description"
placeholder="Description"
required></textarea>

<input
type="number"
name="price"
placeholder="Price"
step="0.01"
required>

<select name="status">

<option>Available</option>

<option>Out of Stock</option>

</select>

<input
type="file"
name="image"
required>

<button
type="submit"
name="add">

Add Product

</button>

</form>

<br>

<a href="products.php">

← Back to Products

</a>

<h3 style="color:green;">
<?php echo $message; ?>
</h3>

</div>

</body>
</html>