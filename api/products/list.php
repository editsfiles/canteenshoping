<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Restrict in production

// Adjust include path since we are in api/products/
include("../../php/db.php");

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

$products = [];
if($result && mysqli_num_rows($result) > 0){
    while($row = mysqli_fetch_assoc($result)){
        $imageName = (!empty($row['image']) && file_exists(__DIR__ . '/../../uploads/' . $row['image'])) ? $row['image'] : 'Burger.jpg';
        $row['image_url'] = 'uploads/' . $imageName;
        
        $row['name'] = !empty($row['product_name']) ? $row['product_name'] : (!empty($row['name']) ? $row['name'] : 'Food Item');
        
        $products[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'price' => (float)$row['price'],
            'image_url' => $row['image_url'],
            'status' => $row['status']
        ];
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Products fetched successfully',
    'data' => [
        'products' => $products
    ]
]);
