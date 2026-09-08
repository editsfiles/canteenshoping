<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Restrict in production
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

include("../../php/db.php");

// Assume authentication is passed via Authorization header or session
// For now, we will extract user_id from the JSON body for simplicity,
// but in a real app, it MUST come from a secure token.

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['user_id']) || !isset($data['token']) || !isset($data['items']) || empty($data['items'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data or missing credentials']);
    exit;
}

$user_id = (int)$data['user_id'];
$token = $data['token'];
$items = $data['items']; // Array of {product_id, quantity}

// Validate Token
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows === 1) {
    $user = $res->fetch_assoc();
    $expectedToken = $user_id . ':' . hash_hmac('sha256', $user_id . $user['password'], 'canteen_app_secret_key_2026');
    if ($token !== $expectedToken) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Invalid token']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}
$stmt->close();

mysqli_begin_transaction($conn);

try {
    $total_amount = 0;
    
    // Calculate total amount from database prices (do not trust client price)
    foreach ($items as $item) {
        $product_id = (int)$item['product_id'];
        $quantity = (int)$item['quantity'];
        
        $stmt = $conn->prepare("SELECT price, status FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows === 0) {
            throw new Exception("Product ID $product_id not found.");
        }
        
        $product = $res->fetch_assoc();
        if ($product['status'] !== 'Available') {
            throw new Exception("Product ID $product_id is not available.");
        }
        
        $total_amount += (float)$product['price'] * $quantity;
    }
    
    // Create order
    $status = 'Pending';
    $stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, status) VALUES (?, ?, ?)");
    $stmt->bind_param("ids", $user_id, $total_amount, $status);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to create order.");
    }
    
    $order_id = $stmt->insert_id;
    
    // Insert order items
    $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
    foreach ($items as $item) {
        $product_id = (int)$item['product_id'];
        $quantity = (int)$item['quantity'];
        
        // Fetch price again or use cached
        $p_stmt = $conn->prepare("SELECT price FROM products WHERE id = ?");
        $p_stmt->bind_param("i", $product_id);
        $p_stmt->execute();
        $p_res = $p_stmt->get_result();
        $p_price = (float)$p_res->fetch_assoc()['price'];
        
        $stmt_item->bind_param("iiid", $order_id, $product_id, $quantity, $p_price);
        if (!$stmt_item->execute()) {
            throw new Exception("Failed to insert order item.");
        }
    }
    
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully',
        'data' => [
            'order_id' => $order_id,
            'total_amount' => $total_amount,
            'status' => $status
        ]
    ]);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
