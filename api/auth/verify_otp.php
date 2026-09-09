<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

include("../../php/db.php");

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? $_POST['email'] ?? '');
$otp = trim($input['otp'] ?? $_POST['otp'] ?? '');

if (empty($email) || empty($otp)) {
    echo json_encode(['success' => false, 'message' => 'Email and OTP are required.', 'data' => null]);
    exit();
}

$stmt = $conn->prepare("SELECT * FROM password_resets WHERE email = ? AND otp = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("ss", $email, $otp);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $expires = strtotime($row['expires_at']);
    $now = time();

    if ($now > $expires) {
        echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.', 'data' => null]);
    } else {
        echo json_encode(['success' => true, 'message' => 'OTP verified successfully.', 'data' => null]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid OTP.', 'data' => null]);
}
$stmt->close();
?>
