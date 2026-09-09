<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

include("../../php/db.php");

$input = json_decode(file_get_contents('php://input'), true);
$userId = $input['user_id'] ?? $_GET['user_id'] ?? null;
$token = $input['token'] ?? $_GET['token'] ?? null;
$orderId = $input['order_id'] ?? $_GET['order_id'] ?? null;

if (!$userId || !$token || !$orderId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Missing credentials or order ID', 'data' => null]);
    exit;
}

// Validate Token
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows === 1) {
    $user = $res->fetch_assoc();
    $expectedToken = $userId . ':' . hash_hmac('sha256', $userId . $user['password'], 'canteen_app_secret_key_2026');
    if ($token !== $expectedToken) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Invalid token', 'data' => null]);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'User not found', 'data' => null]);
    exit;
}
$stmt->close();

// Fetch Order Items
$stmt = $conn->prepare("
    SELECT oi.id, oi.product_id, oi.quantity, oi.price, p.name as product_name, p.image_url 
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ? AND (SELECT user_id FROM orders WHERE id = ?) = ?
");
$stmt->bind_param("iii", $orderId, $orderId, $userId);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = [
        'id' => (int)$row['id'],
        'product_id' => (int)$row['product_id'],
        'product_name' => $row['product_name'],
        'quantity' => (int)$row['quantity'],
        'price' => (float)$row['price'],
        'image_url' => $row['image_url']
    ];
}
$stmt->close();

echo json_encode([
    'success' => true,
    'message' => 'Order details fetched successfully',
    'data' => [
        'order_id' => (int)$orderId,
        'items' => $items
    ]
]);
?>
