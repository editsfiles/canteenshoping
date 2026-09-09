<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

include("../../php/db.php");
include("../../php/mail.php");

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? $_POST['email'] ?? '');

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Please enter your email.', 'data' => null]);
    exit();
}

$stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Email not registered.', 'data' => null]);
    exit();
}

$otp = random_int(100000, 999999);
$expires = date("Y-m-d H:i:s", strtotime("+10 minutes"));

@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `otp` varchar(10) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$delete = $conn->prepare("DELETE FROM password_resets WHERE email=?");
$delete->bind_param("s", $email);
$delete->execute();

$insert = $conn->prepare("INSERT INTO password_resets (email, otp, expires_at) VALUES (?, ?, ?)");
$insert->bind_param("sss", $email, $otp, $expires);
if (!$insert->execute()) {
    echo json_encode(['success' => false, 'message' => 'Database error.', 'data' => null]);
    exit();
}

$sent = @sendOTP($email, $otp);

if ($sent) {
    echo json_encode(['success' => true, 'message' => "OTP has been sent to your email ($email)!", 'data' => null]);
} else {
    // Return OTP directly for testing if mail server is unverified
    echo json_encode(['success' => true, 'message' => "Mail disabled. For testing, your OTP code is: $otp", 'data' => ['otp' => $otp]]);
}
?>
