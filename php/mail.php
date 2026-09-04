<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . "/../phpmailer/PHPMailer.php";
require_once __DIR__ . "/../phpmailer/SMTP.php";
require_once __DIR__ . "/../phpmailer/Exception.php";

function sendOTP($toEmail, $otp)
{
    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();
        $mail->Host = "smtp.gmail.com";
        $mail->SMTPAuth = true;

        $mail->Username = "mohanraj.s4211@gmail.com";

        // Replace with your Gmail App Password
        $mail->Password = "ssib ifjd ifln vcls";

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom("mohanraj.s4211@gmail.com", "College Canteen");
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = "College Canteen Password Reset OTP";

        $mail->Body = "
            <h2>College Canteen</h2>
            <p>Your OTP is:</p>
            <h1 style='color:#00c853;'>$otp</h1>
            <p>This OTP is valid for 10 minutes.</p>
        ";

        $mail->send();
        return true;

    } catch (Exception $e) {

        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;

    }
}
?>