<?php
session_start();

require_once "db.php";
require_once "mail.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST['email']);

    if (empty($email)) {
        header("Location: ../forgot_password.php?error=Please enter your email.");
        exit();
    }

    // Check if email exists
    $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        header("Location: ../forgot_password.php?error=Email not registered.");
        exit();
    }

    // Generate 6-digit OTP
    $otp = random_int(100000, 999999);

    // OTP expires after 10 minutes
    $expires = date("Y-m-d H:i:s", strtotime("+10 minutes"));

    // Auto-create password_resets table if not exists
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `password_resets` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `email` varchar(150) NOT NULL,
      `otp` varchar(10) NOT NULL,
      `expires_at` datetime NOT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Delete any previous OTP for this email
    $delete = $conn->prepare("DELETE FROM password_resets WHERE email=?");
    $delete->bind_param("s", $email);
    $delete->execute();

    // Insert new OTP
    $insert = $conn->prepare(
        "INSERT INTO password_resets (email, otp, expires_at)
         VALUES (?, ?, ?)"
    );
    $insert->bind_param("sss", $email, $otp, $expires);

    if (!$insert->execute()) {
        header("Location: ../forgot_password.php?error=Database error.");
        exit();
    }

    // Attempt to send OTP email
    $sent = @sendOTP($email, $otp);

    $_SESSION['reset_email'] = $email;

    if ($sent) {
        $_SESSION['otp_notice'] = "OTP has been sent to your email (" . htmlspecialchars($email) . ")!";
        $_SESSION['otp_type'] = "success";
    } else {
        // Fallback for development / when Gmail App Password needs refresh
        $_SESSION['otp_fallback_code'] = $otp;
        $_SESSION['otp_notice'] = "Gmail SMTP is currently unverified. For testing, your OTP code is: <strong style='font-size:18px; text-decoration:underline;'>$otp</strong>";
        $_SESSION['otp_type'] = "warning";
    }

    header("Location: ../verify_otp.php");
    exit();

} else {

    header("Location: ../forgot_password.php");
    exit();

}
?>