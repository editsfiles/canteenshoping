<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Restrict in production
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Adjust include path since we are in api/orders/
include("../../php/db.php");

$input = json_decode(file_get_contents('php://input'), true);
$userId = $input['user_id'] ?? $_GET['user_id'] ?? null;
$token = $input['token'] ?? $_GET['token'] ?? null;

if (!$userId || !$token) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Missing credentials', 'data' => null]);
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

$stmt = mysqli_prepare($conn, "SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$orders = [];
while ($row = mysqli_fetch_assoc($result)) {
    $order = [
        'id' => (int)$row['id'],
        'total_amount' => (float)$row['total_amount'],
        'payment_method' => $row['payment_method'],
        'status' => $row['status'],
        'food_status' => $row['food_status'] ?? 'Preparing',
        'order_date' => $row['order_date'] ?? $row['created_at'] ?? ''
    ];
    $orders[] = $order;
}
mysqli_stmt_close($stmt);

echo json_encode([
    'success' => true,
    'message' => 'Orders fetched successfully',
    'data' => [
        'orders' => $orders
    ]
]);
