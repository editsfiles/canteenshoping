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
$identifier = trim($input['email'] ?? $_POST['email'] ?? '');
$password   = trim($input['password'] ?? $_POST['password'] ?? '');

if (empty($identifier) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Please enter your Email / Mobile Number and Password.', 'data' => null]);
    exit();
}

$clean_digits = preg_replace('/[^0-9]/', '', $identifier);
$phone_10 = (strlen($clean_digits) >= 10) ? substr($clean_digits, -10) : '';
$phone_plus91  = $phone_10 ? ('+91' . $phone_10) : '';
$phone_space91 = $phone_10 ? ('+91 ' . $phone_10) : '';

$stmt = $conn->prepare("SELECT * FROM users WHERE email=? OR regno=? OR phone=? OR (phone!='' AND (phone=? OR phone=? OR phone=?)) LIMIT 1");
if (!$stmt) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE email=? OR regno=? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
    }
} else {
    $stmt->bind_param("ssssss", $identifier, $identifier, $identifier, $phone_10, $phone_plus91, $phone_space91);
    $stmt->execute();
}

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error occurred.', 'data' => null]);
    exit();
}

$result = $stmt->get_result();
if ($result && $result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    // Secure verification using password_verify
    if (password_verify($password, $user['password']) || $password === $user['password']) {
        // Generate a token for stateless API authentication
        $secretKey = 'canteen_app_secret_key_2026';
        $token = hash_hmac('sha256', $user['id'] . $user['password'], $secretKey);
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => (int)$user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'phone' => $user['phone'] ?? '',
                    'token' => $user['id'] . ':' . $token
                ]
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Incorrect password. Please try again.', 'data' => null]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No student account found.', 'data' => null]);
}
$stmt->close();
