<?php
session_start();

if (!isset($_SESSION['uropay_order'])) {
    die("No payment data found.");
}

$order = $_SESSION['uropay_order'];
$isMobile = preg_match('/Android|iPhone|iPad|iPod/i', $_SERVER['HTTP_USER_AGENT']);

// Timer calculation
if (!isset($_SESSION['payment_expires_at'])) {
    $_SESSION['payment_expires_at'] = time() + 300;
}
$expiresAt = (int)$_SESSION['payment_expires_at'];
$remainingSeconds = max(0, $expiresAt - time());
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>UPI QR Payment - College Canteen</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: 'Poppins', sans-serif;
}

body {
    background: #f4f6f9;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    padding: 20px 10px;
}

.box {
    width: 440px;
    max-width: 100%;
    background: #fff;
    padding: 30px;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    text-align: center;
}

h2 {
    color: #1e293b;
    margin-bottom: 12px;
}

.timer-card {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 10px;
    padding: 10px;
    margin: 12px 0 18px;
}

.timer-card.warning {
    background: #fef2f2;
    border-color: #fecdd3;
    color: #dc2626;
}

.timer-val {
    font-size: 22px;
    font-weight: 700;
    color: #2563eb;
}

.timer-card.warning .timer-val {
    color: #dc2626;
}

.qr-img {
    width: 240px;
    height: 240px;
    margin: 0 auto;
    display: block;
    border-radius: 10px;
    border: 1px solid #eee;
}

.amount {
    font-size: 24px;
    font-weight: 700;
    color: #16a34a;
    margin: 12px 0;
}

.meta-info {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 12px;
}

.notice-box {
    margin: 15px 0;
    padding: 12px 14px;
    background: #f8f9fa;
    border: 1px solid #dfe3e8;
    border-radius: 8px;
    color: #333;
    font-size: 13px;
    line-height: 1.4;
    text-align: left;
}

.pay-btn {
    display: block;
    width: 100%;
    margin-top: 15px;
    padding: 14px;
    background: #2563eb;
    color: #fff;
    text-decoration: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: 0.2s;
}

.pay-btn:hover {
    background: #1d4ed8;
}

.refresh {
    display: inline-block;
    margin-top: 15px;
    color: #0284c7;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
}

.refresh:hover {
    text-decoration: underline;
}
</style>
</head>
<body>

<div class="box">

    <h2>Scan QR Code</h2>

    <div id="timerCard" class="timer-card">
        <div style="font-size:12px;color:#64748b;">Time Remaining to Pay</div>
        <div id="timerDisplay" class="timer-val">
            <?php
                $mins = floor($remainingSeconds / 60);
                $secs = $remainingSeconds % 60;
                printf("%02d:%02d", $mins, $secs);
            ?>
        </div>
    </div>

    <img src="<?php echo htmlspecialchars($order['qrCode']); ?>" class="qr-img" alt="UPI QR">

    <div class="amount">
        ₹<?php echo number_format((float)$order['amountInRupees'], 2); ?>
    </div>

    <div class="meta-info">
        Status: <b><?php echo htmlspecialchars($order['orderStatus']); ?></b> &bull; Ref: <b><?php echo htmlspecialchars($order['uroPayOrderId']); ?></b>
    </div>

    <div class="notice-box">
        <i class="fa-solid fa-mobile-screen-button"></i> Scan the QR code using Google Pay, PhonePe, Paytm, or any UPI app.
    </div>

    <a href="check_payment.php" class="pay-btn">
        <i class="fa-solid fa-arrows-rotate"></i> Check Payment Status
    </a>

    <a href="check_payment.php" class="refresh">
        Automatic verification active
    </a>

</div>

<script>
let remainingSeconds = <?php echo (int)$remainingSeconds; ?>;
const timerDisplay = document.getElementById("timerDisplay");
const timerCard = document.getElementById("timerCard");

const interval = setInterval(function() {
    if (remainingSeconds <= 0) {
        clearInterval(interval);
        if (timerDisplay) timerDisplay.innerText = "00:00 (Expired)";
        if (timerCard) {
            timerCard.classList.add("warning");
        }
        return;
    }

    remainingSeconds--;
    const mins = Math.floor(remainingSeconds / 60);
    const secs = remainingSeconds % 60;
    if (timerDisplay) {
        timerDisplay.innerText = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }

    if (remainingSeconds <= 60 && timerCard) {
        timerCard.classList.add("warning");
    }
}, 1000);

// Auto check payment status every 2 seconds
const pollInterval = setInterval(function() {
    fetch("check_uropay_status.php?order_id=" + encodeURIComponent(<?php echo json_encode($order['uroPayOrderId']); ?>) + "&t=" + Date.now())
        .then(res => res.json())
        .then(data => {
            const statusValue = (data && (data.status || data.uropay_status || "")).toString().trim().toUpperCase();
            if (data.success && (statusValue === "COMPLETED" || statusValue === "PAID" || statusValue === "SUCCESS")) {
                clearInterval(pollInterval);
                clearInterval(interval);
                try {
                    localStorage.removeItem('cart');
                    localStorage.removeItem('canteen_cart');
                    localStorage.removeItem('food_cart');
                    sessionStorage.removeItem('cart');
                } catch(e) {}
                window.location.replace("payment_success.php");
            }
        })
        .catch(err => console.error(err));
}, 1800);
</script>

</body>
</html>