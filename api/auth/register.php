<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Restrict in production
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Adjust include path since we are in api/auth/
include("../../php/db.php");

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid Request', 'data' => null]);
    exit;
}

$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$phone = trim($input['phone'] ?? '');
$password = trim($input['password'] ?? '');

if (empty($name) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Name, email, and password are required', 'data' => null]);
    exit;
}

// Check if user already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Email already registered', 'data' => null]);
    exit;
}
$stmt->close();

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$role = 'student';
$regno = '';

// Attempt insertion
$stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role, regno) VALUES (?, ?, ?, ?, ?, ?)");
if ($stmt) {
    $stmt->bind_param("ssssss", $name, $email, $phone, $hashedPassword, $role, $regno);
    if ($stmt->execute()) {
        $userId = $conn->insert_id;
        $token = hash_hmac('sha256', $userId . $hashedPassword, 'canteen_app_secret_key_2026');
        
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful',
            'data' => [
                'user' => [
                    'id' => $userId,
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'token' => $userId . ':' . $token
                ]
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error during registration', 'data' => null]);
    }
    $stmt->close();
} else {
    // Fallback if role/regno columns are not exactly as expected
    $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $phone, $hashedPassword);
    if ($stmt->execute()) {
        $userId = $conn->insert_id;
        $token = hash_hmac('sha256', $userId . $hashedPassword, 'canteen_app_secret_key_2026');
        
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful',
            'data' => [
                'user' => [
                    'id' => $userId,
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'token' => $userId . ':' . $token
                ]
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error during registration', 'data' => null]);
    }
    $stmt->close();
}

$conn->close();
