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

if (!$userId || !$token) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Missing credentials', 'data' => null]);
    exit;
}

$stmt = $conn->prepare("SELECT id, name, email, phone, password FROM users WHERE id = ? LIMIT 1");
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

    echo json_encode([
        'success' => true,
        'message' => 'Profile fetched successfully',
        'data' => [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'] ?? ''
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'User not found', 'data' => null]);
}
$stmt->close();
?>
