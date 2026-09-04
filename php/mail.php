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

        $smtpUser = getenv('SMTP_USER') ?: (getenv('GMAIL_USER') ?: 'mohanraj.s4211@gmail.com');
        $smtpPass = getenv('SMTP_PASSWORD') ?: (getenv('GMAIL_APP_PASSWORD') ?: 'ssib ifjd ifln vcls');

        $mail->isSMTP();
        $mail->Host = getenv('SMTP_HOST') ?: "smtp.gmail.com";
        $mail->SMTPAuth = true;

        $mail->Username = $smtpUser;
        $mail->Password = str_replace(' ', '', $smtpPass);

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)(getenv('SMTP_PORT') ?: 587);
        $mail->Timeout = 10;

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

function sendOrderInvoiceEmail($orderId, $conn)
{
    $orderId = (int)$orderId;
    if ($orderId <= 0 || !$conn) return false;

    // Fetch order & student info
    $sql = "SELECT orders.*, users.name AS customer_name, users.email AS customer_email, users.reg_no 
            FROM orders 
            LEFT JOIN users ON orders.user_id = users.id 
            WHERE orders.id = $orderId LIMIT 1";
    $res = mysqli_query($conn, $sql);
    if (!$res || mysqli_num_rows($res) === 0) return false;
    $order = mysqli_fetch_assoc($res);

    $toEmail = $order['customer_email'];
    if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) return false;

    // Fetch items
    $itemsSql = "SELECT * FROM order_items WHERE order_id = $orderId";
    $itemsRes = mysqli_query($conn, $itemsSql);
    $itemsHtml = "";
    $subtotal = 0;
    while ($itemsRes && $item = mysqli_fetch_assoc($itemsRes)) {
        $name = htmlspecialchars($item['product_name'] ?? 'Item');
        $qty = (int)($item['quantity'] ?? 1);
        $price = (float)($item['price'] ?? 0);
        $lineTotal = $qty * $price;
        $subtotal += $lineTotal;
        $itemsHtml .= "<tr>
            <td style='padding:10px 12px; border-bottom:1px solid #e2e8f0;'>$name</td>
            <td style='padding:10px 12px; border-bottom:1px solid #e2e8f0; text-align:center;'>$qty</td>
            <td style='padding:10px 12px; border-bottom:1px solid #e2e8f0; text-align:right;'>₹" . number_format($price, 2) . "</td>
            <td style='padding:10px 12px; border-bottom:1px solid #e2e8f0; text-align:right;'>₹" . number_format($lineTotal, 2) . "</td>
        </tr>";
    }

    $grandTotal = (float)$order['total_amount'];
    $gst = max(0, $grandTotal - $subtotal);

    $custName = htmlspecialchars($order['customer_name'] ?? 'Student');
    $utr = htmlspecialchars($order['bank_utr'] ?: ($order['payment_id'] ?: 'Paid via UPI'));
    $orderDate = htmlspecialchars($order['order_date'] ?? date('Y-m-d H:i:s'));

    $mail = new PHPMailer(true);
    try {
        $smtpUser = getenv('SMTP_USER') ?: (getenv('GMAIL_USER') ?: 'mohanraj.s4211@gmail.com');
        $smtpPass = getenv('SMTP_PASSWORD') ?: (getenv('GMAIL_APP_PASSWORD') ?: 'ssib ifjd ifln vcls');

        $mail->isSMTP();
        $mail->Host = getenv('SMTP_HOST') ?: "smtp.gmail.com";
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = str_replace(' ', '', $smtpPass);
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)(getenv('SMTP_PORT') ?: 587);
        $mail->Timeout = 10;
        $mail->setFrom("mohanraj.s4211@gmail.com", "College Canteen");
        $mail->addAddress($toEmail, $custName);

        $mail->isHTML(true);
        $mail->Subject = "🍽 Order #$orderId Confirmed & Paid - College Canteen";

        $mail->Body = "
        <div style='max-width:600px; margin:0 auto; font-family:Arial, sans-serif; background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;'>
            <div style='background:linear-gradient(135deg,#16a34a,#15803d); padding:24px; text-align:center; color:white;'>
                <h1 style='margin:0; font-size:24px;'>🍽 College Canteen</h1>
                <p style='margin:6px 0 0; opacity:0.9;'>Payment Confirmed & Kitchen Invoice</p>
            </div>
            <div style='padding:24px;'>
                <p style='font-size:16px; color:#1e293b; margin:0 0 16px;'>Hello <strong>$custName</strong>,</p>
                <p style='color:#64748b; margin:0 0 20px; line-height:1.5;'>Your payment has been received and your order is sent to the kitchen! Here are your order details:</p>
                
                <div style='background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px 18px; margin-bottom:20px;'>
                    <div style='display:flex; justify-content:space-between; margin-bottom:6px;'>
                        <span style='color:#64748b;'>Order ID:</span>
                        <strong style='color:#0f172a;'>#$orderId</strong>
                    </div>
                    <div style='display:flex; justify-content:space-between; margin-bottom:6px;'>
                        <span style='color:#64748b;'>Date:</span>
                        <span>$orderDate</span>
                    </div>
                    <div style='display:flex; justify-content:space-between; margin-bottom:6px;'>
                        <span style='color:#64748b;'>Payment Status:</span>
                        <strong style='color:#16a34a;'>Completed (Paid)</strong>
                    </div>
                    <div style='display:flex; justify-content:space-between;'>
                        <span style='color:#64748b;'>Bank UTR / Ref:</span>
                        <span>$utr</span>
                    </div>
                </div>

                <table style='width:100%; border-collapse:collapse; font-size:14px; margin-bottom:20px;'>
                    <thead>
                        <tr style='background:#f1f5f9; text-align:left;'>
                            <th style='padding:10px 12px;'>Item</th>
                            <th style='padding:10px 12px; text-align:center;'>Qty</th>
                            <th style='padding:10px 12px; text-align:right;'>Price</th>
                            <th style='padding:10px 12px; text-align:right;'>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        $itemsHtml
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan='3' style='padding:8px 12px; text-align:right; font-weight:600; color:#64748b;'>Subtotal:</td>
                            <td style='padding:8px 12px; text-align:right;'>₹" . number_format($subtotal, 2) . "</td>
                        </tr>
                        <tr>
                            <td colspan='3' style='padding:8px 12px; text-align:right; font-weight:600; color:#64748b;'>GST (5%):</td>
                            <td style='padding:8px 12px; text-align:right;'>₹" . number_format($gst, 2) . "</td>
                        </tr>
                        <tr style='font-size:16px; font-weight:bold; color:#16a34a; background:#f0fdf4;'>
                            <td colspan='3' style='padding:10px 12px; text-align:right;'>Total Paid:</td>
                            <td style='padding:10px 12px; text-align:right;'>₹" . number_format($grandTotal, 2) . "</td>
                        </tr>
                    </tfoot>
                </table>

                <div style='text-align:center; padding:12px; background:#f0fdf4; border-radius:8px;'>
                    <p style='color:#166534; font-size:13px; margin:0;'>Show your Order ID <strong>#$orderId</strong> at the counter when food status is <strong>Ready</strong>.</p>
                </div>
            </div>
            <div style='background:#f8fafc; padding:16px; text-align:center; font-size:12px; color:#94a3b8; border-top:1px solid #e2e8f0;'>
                College Canteen Management System &bull; Thank you for ordering!
            </div>
        </div>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Invoice Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>