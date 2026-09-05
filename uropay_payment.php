<?php

session_start();

include("php/db.php");
include("config_uropay.php");

// Resolve order ID from GET or session
$uroPayOrderId = trim($_GET['order_id'] ?? ($_SESSION['uropay_order_id'] ?? ''));
$localOrderId  = (int)($_GET['local_id'] ?? ($_SESSION['local_order_id'] ?? 0));

// Fetch order from DB by ref ID or local ID
$orderRow = null;
if (!empty($uroPayOrderId) || $localOrderId > 0) {
    $sql = "SELECT id, total_amount, payment_id, merchant_order_id, status, food_status, qr_code
            FROM orders WHERE ";
    if (!empty($uroPayOrderId) && $localOrderId > 0) {
        $sql .= "(payment_id = ? OR merchant_order_id = ? OR id = ?) ORDER BY id DESC LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssi", $uroPayOrderId, $uroPayOrderId, $localOrderId);
    } elseif (!empty($uroPayOrderId)) {
        $sql .= "payment_id = ? OR merchant_order_id = ? ORDER BY id DESC LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $uroPayOrderId, $uroPayOrderId);
    } else {
        $sql .= "id = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $localOrderId);
    }
    if ($stmt) {
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res) $orderRow = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
    }
}

if ($orderRow) {
    $localOrderId  = (int)$orderRow['id'];
    $displayAmount = (float)$orderRow['total_amount'];
    if (!empty($orderRow['payment_id'])) $uroPayOrderId = $orderRow['payment_id'];

    // If order is ALREADY completed in DB (by webhook, UTR, or admin), auto-redirect to success screen
    $dbStatus = strtoupper(trim((string)$orderRow['status']));
    if (in_array($dbStatus, ['COMPLETED', 'PAID', 'SUCCESS'], true)) {
        header("Location: payment_success.php?order_id=" . $localOrderId . "&uropay_id=" . urlencode($uroPayOrderId));
        exit();
    }

    // Restore QR from database if not in session
    if (empty($_SESSION['uropay_qr']) && !empty($orderRow['qr_code'])) {
        $_SESSION['uropay_qr'] = $orderRow['qr_code'];
    }
    $_SESSION['local_order_id']   = $localOrderId;
    $_SESSION['order_amount']     = $displayAmount;
    $_SESSION['uropay_order_id']  = $uroPayOrderId;
} else {
    $displayAmount = (float)($_SESSION['order_amount'] ?? 0);
}

if (empty($uroPayOrderId) || $localOrderId <= 0) {
    die("<div style='font-family:sans-serif;padding:30px;text-align:center;'>
        <h2>Payment session not found.</h2>
        <p style='color:#666;'>The order may have already been processed or the link has expired.</p>
        <a href='my_orders.php' style='display:inline-block;margin:12px 5px;padding:12px 22px;background:#2563eb;color:white;text-decoration:none;border-radius:8px;font-weight:600;'>My Orders</a>
        <a href='menu.php' style='display:inline-block;margin:12px 5px;padding:12px 22px;background:#f1f5f9;color:#334155;text-decoration:none;border-radius:8px;font-weight:600;'>Back to Menu</a>
    </div>");
}

// Try to get QR code from session first, otherwise fetch from UroPay API
$qrCode = $_SESSION['uropay_qr'] ?? '';

if (empty($qrCode) && !empty($uroPayOrderId)) {
    // Fetch order details from UroPay to get QR code
    $apiUrl = UROPAY_API_URL . "/order/status/" . rawurlencode($uroPayOrderId);
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Accept: application/json",
        "Content-Type: application/json",
        "X-API-KEY: " . UROPAY_API_KEY
    ]);
    $apiRes = curl_exec($ch);
    curl_close($ch);

    if ($apiRes) {
        $apiData = json_decode($apiRes, true);
        $d = $apiData['data'] ?? [];
        $qrCode = $d['qrCode'] ?? ($d['qr_code'] ?? ($d['qrImage'] ?? ''));
        if (!empty($qrCode)) {
            $_SESSION['uropay_qr'] = $qrCode;
        }
        // Also check if already paid
        $apiStatus = strtoupper(trim($d['orderStatus'] ?? ($d['status'] ?? '')));
        if (in_array($apiStatus, ['COMPLETED','SUCCESS','SUCCESSFUL','PAID','CAPTURED','SETTLED','APPROVED'], true)) {
            // Already paid - update DB and show success directly
            $upd = mysqli_prepare($conn, "UPDATE orders SET status='Completed', food_status=CASE WHEN food_status IS NULL OR food_status='' THEN 'Preparing' ELSE food_status END WHERE id=?");
            if ($upd) { mysqli_stmt_bind_param($upd,"i",$localOrderId); mysqli_stmt_execute($upd); mysqli_stmt_close($upd); }
            header("Location: payment_success.php");
            exit();
        }
        // Update display amount from API if not set
        if ($displayAmount <= 0 && isset($d['amountInRupees'])) {
            $displayAmount = (float)$d['amountInRupees'];
            $_SESSION['order_amount'] = $displayAmount;
        }
    }
}

// Payment window: 10 minutes from first load (or restore existing timer)
if (!isset($_SESSION['payment_expires_at']) || $_SESSION['payment_expires_at'] < (time() - 3600)) {
    $_SESSION['payment_expires_at'] = time() + 600;
}
$expiresAt        = (int)$_SESSION['payment_expires_at'];
$remainingSeconds = max(0, $expiresAt - time());

// Resolve UPI ID / VPA
$upiId = $_SESSION['uropay_upi_id'] ?? '';
if (empty($upiId) && !empty($orderRow['qr_code'])) {
    if (preg_match('/[?&]pa=([^&]+)/i', $orderRow['qr_code'], $m)) {
        $upiId = urldecode($m[1]);
    }
}
if (empty($upiId)) {
    $upiId = defined('CANTEEN_UPI_ID') ? CANTEEN_UPI_ID : 'canteen@upi';
}

// Generate standard NPCI UPI Intent Deep Link for Mobile Devices
$canteenMerchantName = "College Canteen";
$formattedAmount = number_format((float)$displayAmount, 2, '.', '');
$upiDeepLink = !empty($upiString) ? $upiString : ("upi://pay?pa=" . urlencode($upiId) . 
               "&pn=" . urlencode($canteenMerchantName) . 
               "&am=" . $formattedAmount . 
               "&tr=" . urlencode($uroPayOrderId) . 
               "&tn=" . urlencode("Order #" . $localOrderId . " Canteen") . 
               "&cu=INR");

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>UPI Payment - College Canteen</title>
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
    background: linear-gradient(135deg, #f0fdf4, #e0f2fe, #f8fafc);
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    padding: 20px 12px;
}

.box {
    position: relative;
    width: 450px;
    max-width: 100%;
    background: white;
    padding: 32px 25px 26px;
    border-radius: 20px;
    text-align: center;
    box-shadow: 0 15px 35px rgba(0,0,0,0.12);
    animation: fadeIn 0.4s ease;
    border: 1px solid rgba(0,0,0,0.06);
}

.card-close-btn {
    position: absolute;
    top: 14px;
    right: 14px;
    width: 32px;
    height: 32px;
    background: #f1f5f9;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    text-decoration: none;
    font-size: 15px;
    transition: all 0.2s ease;
    border: 1px solid #e2e8f0;
    cursor: pointer;
    z-index: 10;
}
.card-close-btn:hover {
    background: #fee2e2;
    color: #dc2626;
    border-color: #fca5a5;
    transform: scale(1.08);
}

.btn-close-safe {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    margin-top: 12px;
    padding: 10px 14px;
    background: #f8fafc;
    border: 1.5px solid #cbd5e1;
    color: #334155;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: 0.2s ease;
}
.btn-close-safe:hover {
    background: #f1f5f9;
    color: #0f172a;
    border-color: #94a3b8;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}

.header-title {
    font-size: 20px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

/* TIMER BAR */
.timer-container {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 10px 14px;
    margin: 12px 0 15px;
    text-align: center;
    transition: all 0.3s ease;
}

.timer-container.warning {
    background: #fff1f2;
    border-color: #fecdd3;
}

.timer-label {
    font-size: 12px;
    color: #64748b;
    font-weight: 500;
}

.timer-display {
    font-size: 22px;
    font-weight: 700;
    color: #0284c7;
    letter-spacing: 1px;
    margin-top: 2px;
}

.timer-container.warning .timer-display {
    color: #e11d48;
}

.timer-progress {
    width: 100%;
    height: 5px;
    background: #e2e8f0;
    border-radius: 10px;
    margin-top: 6px;
    overflow: hidden;
}

.timer-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #0284c7, #10b981);
    border-radius: 10px;
    width: 100%;
    transition: width 1s linear, background 0.3s;
}

.timer-container.warning .timer-progress-bar {
    background: linear-gradient(90deg, #f59e0b, #ef4444);
}

/* QR WRAPPER */
.qr-wrapper {
    position: relative;
    display: inline-block;
    margin: 5px auto 10px;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    border: 2px solid #f1f5f9;
}

.qr-img {
    width: 230px;
    height: 230px;
    display: block;
    object-fit: contain;
    transition: filter 0.3s ease;
}

.amount {
    font-size: 28px;
    font-weight: 700;
    color: #16a34a;
    margin: 6px 0;
}

.order-info {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 10px;
}

.order-info strong {
    color: #334155;
}

.instruction-box {
    margin: 10px 0;
    padding: 10px 12px;
    border: 1px solid #dcfce7;
    background: #f0fdf4;
    color: #166534;
    border-radius: 10px;
    font-size: 12px;
    line-height: 1.4;
    text-align: left;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* STATUS BOX */
.status-box {
    margin-top: 12px;
    padding: 12px 14px;
    border-radius: 10px;
    background: #eff6ff;
    color: #1e40af;
    font-weight: 600;
    font-size: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.3s;
}

.status-box.success {
    background: #dcfce7;
    color: #15803d;
}

.status-box.failed {
    background: #fee2e2;
    color: #b91c1c;
}

.status-box.checking {
    background: #fef3c7;
    color: #92400e;
}

.spinner {
    width: 16px;
    height: 16px;
    border: 2px solid #bfdbfe;
    border-top: 2px solid #2563eb;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    100% { transform: rotate(360deg); }
}

.btn-verify {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    margin-top: 12px;
    padding: 14px;
    background: #16a34a;
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    transition: 0.2s;
    text-decoration: none;
    box-shadow: 0 4px 12px rgba(22, 163, 74, 0.25);
}

.btn-verify:hover {
    background: #15803d;
    transform: translateY(-1px);
}

.btn-verify:disabled {
    background: #94a3b8;
    cursor: not-allowed;
}

.actions-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 16px;
    font-size: 13px;
}

.actions-row a {
    color: #64748b;
    text-decoration: none;
    font-weight: 500;
    transition: 0.2s;
}

.actions-row a:hover {
    color: #0f172a;
    text-decoration: underline;
}

.actions-row a.cancel {
    color: #ef4444;
}

/* =========================================================
   DESKTOP VS MOBILE PAYMENT FLOWS
   ========================================================= */

.desktop-pay-flow {
    display: block;
}

.mobile-pay-flow {
    display: none;
}

@media (max-width: 768px) {
    .desktop-pay-flow {
        display: none !important;
    }

    .mobile-pay-flow {
        display: block !important;
    }
}

/* 1-Tap Mobile UPI Button */
.btn-mobile-pay-now {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    width: 100%;
    padding: 15px 18px;
    background: linear-gradient(135deg, #16a34a, #15803d);
    color: white;
    font-size: 15px;
    font-weight: 700;
    text-decoration: none;
    border-radius: 14px;
    box-shadow: 0 6px 20px rgba(22, 163, 74, 0.35);
    margin: 12px 0 14px;
    transition: transform 0.2s, box-shadow 0.2s;
    box-sizing: border-box;
}

.btn-mobile-pay-now:active {
    transform: scale(0.98);
}

.upi-apps-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-bottom: 14px;
}

.app-tile {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px 12px;
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    text-decoration: none;
    color: #1e293b;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.2s ease;
    box-sizing: border-box;
}

.app-tile:active {
    background: #eff6ff;
    border-color: #3b82f6;
    transform: scale(0.98);
}

.app-tile.gpay {
    border-color: #bfdbfe;
    background: #f0f9ff;
    color: #0369a1;
}

.app-tile.phonepe {
    border-color: #ddd6fe;
    background: #faf5ff;
    color: #6b21a8;
}

.app-tile.paytm {
    border-color: #bae6fd;
    background: #f0fdfa;
    color: #0e7490;
}

.app-tile.bhim {
    border-color: #fed7aa;
    background: #fffbeb;
    color: #c2410c;
}

.btn-toggle-qr {
    background: none;
    border: 1px dashed #94a3b8;
    color: #64748b;
    padding: 9px 14px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    width: 100%;
    margin-bottom: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    box-sizing: border-box;
}

.btn-toggle-qr:hover {
    background: #f1f5f9;
    color: #1e293b;
}

.mobile-auto-alert {
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #065f46;
    padding: 10px 12px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    text-align: left;
}

/* UPI ID CARD */
.upi-id-card {
    background: #f8fafc;
    border: 1px dashed #94a3b8;
    border-radius: 12px;
    padding: 10px 14px;
    margin: 12px 0 8px;
    text-align: left;
}
.upi-id-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 11px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.upi-badge {
    background: #dcfce7;
    color: #16a34a;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 600;
}
.upi-id-box {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: white;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 8px 12px;
    gap: 8px;
}
.upi-id-text {
    font-family: monospace;
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
    word-break: break-all;
}
.btn-copy {
    background: #2563eb;
    color: white;
    border: none;
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: 0.2s;
    white-space: nowrap;
}
.btn-copy:hover {
    background: #1d4ed8;
}

/* MANUAL UTR BOX */
.utr-box {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 12px;
    padding: 12px 14px;
    margin-top: 14px;
    text-align: left;
}
.utr-title {
    font-size: 12px;
    font-weight: 700;
    color: #166534;
    margin-bottom: 3px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.utr-desc {
    font-size: 11px;
    color: #4b5563;
    margin-bottom: 8px;
    line-height: 1.35;
}
.utr-input-group {
    display: flex;
    gap: 6px;
}
.utr-input-group input {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #86efac;
    border-radius: 8px;
    font-size: 13px;
    outline: none;
    font-family: monospace;
    background: white;
}
.utr-input-group input:focus {
    border-color: #16a34a;
    box-shadow: 0 0 0 2px rgba(22, 163, 74, 0.2);
}
.utr-input-group button {
    background: #16a34a;
    color: white;
    border: none;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: 0.2s;
}
.utr-input-group button:hover {
    background: #15803d;
}
.utr-msg {
    margin-top: 6px;
    font-size: 12px;
    font-weight: 600;
}
</style>
</head>
<body>

<div class="box">

    <!-- TOP CLOSE BUTTON (Safe Exit to Orders) -->
    <a href="my_orders.php" class="card-close-btn" title="Close and return to My Orders" aria-label="Close">
        <i class="fa-solid fa-xmark"></i>
    </a>

    <div class="header-title">
        <i class="fa-solid fa-qrcode" style="color:#2563eb;"></i>
        <span>Scan & Pay with UPI</span>
    </div>

    <!-- COUNTDOWN TIMER BANNER -->
    <div id="timerContainer" class="timer-container">
        <div class="timer-label">
            <i class="fa-regular fa-clock"></i> Payment Window Remaining
        </div>
        <div id="timerDisplay" class="timer-display">
            <?php
                $mins = floor($remainingSeconds / 60);
                $secs = $remainingSeconds % 60;
                printf("%02d:%02d", $mins, $secs);
            ?>
        </div>
        <div class="timer-progress">
            <div id="progressBar" class="timer-progress-bar" style="width: <?php echo min(100, round(($remainingSeconds / 600) * 100)); ?>%;"></div>
        </div>
    </div>

    <!-- DESKTOP PAYMENT FLOW (> 768px): DISPLAY SCANNER QR CODE -->
    <div class="desktop-pay-flow">
        <div class="qr-wrapper">
            <?php if (!empty($qrCode)) { ?>
                <img id="qrImg" src="<?php echo htmlspecialchars($qrCode); ?>" class="qr-img" alt="UPI QR Code">
            <?php } else { ?>
                <div style="width:230px;height:230px;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:13px;padding:20px;">
                    Loading QR Code...
                </div>
            <?php } ?>
        </div>

        <div class="amount">
            ₹<?php echo number_format((float)$displayAmount, 2); ?>
        </div>

        <!-- UPI ID BOX WITH ONE-CLICK COPY -->
        <div class="upi-id-card">
            <div class="upi-id-header">
                <span><i class="fa-solid fa-at"></i> Pay to UPI ID</span>
                <span class="upi-badge"><i class="fa-solid fa-circle-check"></i> Merchant VPA</span>
            </div>
            <div class="upi-id-box">
                <span id="upiIdDisplay" class="upi-id-text"><?php echo htmlspecialchars($upiId); ?></span>
                <button type="button" class="btn-copy" onclick="copyUpiId()" title="Copy UPI ID">
                    <i class="fa-regular fa-copy"></i> <span id="copyBtnLabel">Copy</span>
                </button>
            </div>
        </div>

        <div class="instruction-box">
            <i class="fa-solid fa-mobile-screen-button"></i>
            <span>Scan this QR code with Google Pay, PhonePe, Paytm or BHIM on your phone.</span>
        </div>
    </div>

    <!-- MOBILE PAYMENT FLOW (<= 768px): 1-TAP NATIVE UPI APP PAYMENT -->
    <div class="mobile-pay-flow">
        <div class="amount" style="margin: 6px 0 10px;">
            ₹<?php echo number_format((float)$displayAmount, 2); ?>
        </div>

        <div class="mobile-auto-alert" id="mobileAutoPrompt">
            <i class="fa-solid fa-bolt" style="color:#16a34a; font-size:16px;"></i>
            <div>
                <strong>Tap below to pay with your UPI app:</strong>
                <div style="font-size:11px; color:#15803d; font-weight:normal; margin-top:2px;">Opens Google Pay, PhonePe, or Paytm automatically.</div>
            </div>
        </div>

        <!-- PRIMARY 1-TAP OPEN UPI APP BUTTON -->
        <a href="<?php echo htmlspecialchars($upiDeepLink); ?>" class="btn-mobile-pay-now" id="btnMobilePayNow">
            <div style="display:flex; align-items:center; gap:10px;">
                <i class="fa-solid fa-bolt" style="font-size:20px;"></i>
                <div style="text-align:left;">
                    <div style="font-size:15px; font-weight:800; line-height:1.2;">Pay ₹<?php echo number_format((float)$displayAmount, 2); ?> via UPI App</div>
                    <div style="font-size:11px; opacity:0.9; font-weight:500;">Google Pay &bull; PhonePe &bull; Paytm &bull; BHIM</div>
                </div>
            </div>
            <i class="fa-solid fa-arrow-up-right-from-square"></i>
        </a>

        <!-- SPECIFIC APP TILES -->
        <div class="upi-apps-grid">
            <a href="<?php echo htmlspecialchars($upiDeepLink); ?>" class="app-tile gpay" title="Pay with Google Pay">
                <i class="fa-brands fa-google" style="color:#4285f4; font-size:16px;"></i>
                <span>Google Pay</span>
            </a>
            <a href="<?php echo htmlspecialchars($upiDeepLink); ?>" class="app-tile phonepe" title="Pay with PhonePe">
                <i class="fa-solid fa-mobile-screen" style="color:#6739b7; font-size:16px;"></i>
                <span>PhonePe</span>
            </a>
            <a href="<?php echo htmlspecialchars($upiDeepLink); ?>" class="app-tile paytm" title="Pay with Paytm">
                <i class="fa-solid fa-wallet" style="color:#00b9f5; font-size:16px;"></i>
                <span>Paytm</span>
            </a>
            <a href="<?php echo htmlspecialchars($upiDeepLink); ?>" class="app-tile bhim" title="Pay with BHIM or Other UPI">
                <i class="fa-solid fa-building-columns" style="color:#ea580c; font-size:16px;"></i>
                <span>Other UPI</span>
            </a>
        </div>

        <!-- OPTIONAL QR ACCORDION ON MOBILE -->
        <button type="button" class="btn-toggle-qr" onclick="toggleMobileQr()">
            <i class="fa-solid fa-qrcode"></i> <span id="toggleQrLabel">Scan from another phone? Show QR</span>
        </button>
        <div id="mobileQrWrap" style="display:none; margin-bottom:12px;">
            <div class="qr-wrapper" style="margin:0 auto 8px;">
                <?php if (!empty($qrCode)) { ?>
                    <img src="<?php echo htmlspecialchars($qrCode); ?>" class="qr-img" alt="UPI QR Code">
                <?php } ?>
            </div>
            <p style="font-size:11px; color:#64748b;">Have a friend scan this QR with their UPI app</p>
        </div>

        <!-- MOBILE UPI ID CARD -->
        <div class="upi-id-card">
            <div class="upi-id-header">
                <span><i class="fa-solid fa-at"></i> Pay to UPI ID</span>
                <span class="upi-badge"><i class="fa-solid fa-circle-check"></i> Merchant</span>
            </div>
            <div class="upi-id-box">
                <span class="upi-id-text"><?php echo htmlspecialchars($upiId); ?></span>
                <button type="button" class="btn-copy" onclick="copyUpiId()" title="Copy UPI ID">
                    <i class="fa-regular fa-copy"></i> Copy
                </button>
            </div>
        </div>
    </div>

    <div class="order-info">
        Order: <strong>#<?php echo htmlspecialchars((string)$localOrderId); ?></strong> &bull; Ref: <strong><?php echo htmlspecialchars($uroPayOrderId); ?></strong>
    </div>

    <div id="statusBox" class="status-box">
        <div class="spinner"></div>
        <span id="statusText">Waiting for payment confirmation...</span>
    </div>

    <!-- PRIMARY VERIFY BUTTON -->
    <button id="btnFastVerify" class="btn-verify" onclick="checkPaymentManual()">
        <i class="fa-solid fa-circle-check"></i> Already Paid? Check Payment Status
    </button>

    <!-- MANUAL 12-DIGIT UTR / REF SUBMISSION BOX -->
    <div class="utr-box">
        <div class="utr-title">
            <i class="fa-solid fa-receipt"></i> Paid via UPI App? Enter 12-Digit Bank UTR:
        </div>
        <div class="utr-desc">
            If already transferred via GPay / PhonePe / Paytm, enter the 12-digit UTR to instantly confirm:
        </div>
        <div class="utr-input-group">
            <input 
                type="text" 
                id="manualUtrInput" 
                placeholder="Enter 12-digit UTR / Ref No." 
                maxlength="25"
                autocomplete="off"
            >
            <button type="button" id="btnSubmitUtr" onclick="submitManualUtr()">
                <i class="fa-solid fa-bolt"></i> Confirm Order
            </button>
        </div>
        <div id="utrMessage" class="utr-msg" style="display:none;"></div>
    </div>

    <!-- SAFE EXIT LINK -->
    <a href="my_orders.php" class="btn-close-safe">
        <i class="fa-solid fa-arrow-left"></i> Already Paid? Close & Go to My Orders
    </a>

    <div class="actions-row">
        <a href="my_orders.php">
            <i class="fa-solid fa-list"></i> My Orders
        </a>
        <a href="verify_ref.php?ref=<?php echo urlencode($uroPayOrderId); ?>" target="_blank" style="color:#2563eb; font-weight:600;">
            <i class="fa-solid fa-magnifying-glass"></i> Check Gateway
        </a>
        <a href="payment_failed.php" class="cancel">
            <i class="fa-solid fa-xmark"></i> Cancel
        </a>
    </div>

</div>

<!-- ===== PAYMENT SUCCESS PANEL (hidden until payment confirmed) ===== -->
<div id="successPanel" style="
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,0.45);
    z-index:999;
    justify-content:center;
    align-items:center;
    padding:20px;
">
    <div style="
        background:white;
        border-radius:22px;
        padding:36px 28px;
        max-width:420px;
        width:100%;
        text-align:center;
        box-shadow:0 20px 50px rgba(0,0,0,0.25);
        animation:popIn 0.4s cubic-bezier(0.16,1,0.3,1);
    ">
        <div style="
            width:72px; height:72px;
            background:#dcfce7;
            border-radius:50%;
            display:flex;
            align-items:center;
            justify-content:center;
            margin:0 auto 16px;
            font-size:34px;
            box-shadow:0 6px 18px rgba(22,163,74,0.25);
        ">✅</div>

        <h2 style="font-size:22px; color:#0f172a; margin-bottom:6px;">Payment Successful!</h2>
        <p style="color:#64748b; font-size:14px; margin-bottom:20px;">Your order has been confirmed and sent to the kitchen.</p>

        <div style="
            background:#f8fafc;
            border:1px solid #e2e8f0;
            border-radius:12px;
            padding:14px 18px;
            margin-bottom:20px;
            text-align:left;
        ">
            <div style="display:flex;justify-content:space-between;font-size:14px;padding:5px 0;">
                <span style="color:#64748b;">Order ID</span>
                <strong id="doneOrderId" style="color:#0f172a;">#<?php echo (int)$localOrderId; ?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:14px;padding:5px 0;">
                <span style="color:#64748b;">Amount Paid</span>
                <strong style="color:#16a34a;">₹<?php echo number_format($displayAmount, 2); ?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:14px;padding:5px 0;">
                <span style="color:#64748b;">Kitchen Status</span>
                <strong style="color:#0284c7;">🍳 Preparing</strong>
            </div>
        </div>

        <a href="my_orders.php" style="
            display:flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            width:100%;
            padding:14px;
            background:#16a34a;
            color:white;
            border-radius:12px;
            font-size:15px;
            font-weight:700;
            text-decoration:none;
            margin-bottom:10px;
            box-shadow:0 4px 14px rgba(22,163,74,0.3);
        ">
            <i class="fa-solid fa-receipt"></i> View My Orders
        </a>

        <a href="invoice.php?order_id=<?php echo (int)$localOrderId; ?>" style="
            display:flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            width:100%;
            padding:13px;
            background:#f1f5f9;
            color:#334155;
            border-radius:12px;
            font-size:14px;
            font-weight:600;
            text-decoration:none;
            margin-bottom:10px;
        ">
            <i class="fa-solid fa-file-invoice"></i> View Invoice
        </a>

        <a href="menu.php" style="
            display:block;
            color:#94a3b8;
            font-size:13px;
            text-decoration:none;
            margin-top:6px;
        ">
            <i class="fa-solid fa-utensils"></i> Back to Menu
        </a>
    </div>
</div>

<style>
@keyframes popIn {
    from { opacity:0; transform:scale(0.88); }
    to   { opacity:1; transform:scale(1); }
}
</style>

<script>
const orderId      = <?php echo json_encode($uroPayOrderId); ?>;
const localOrderId = <?php echo (int)$localOrderId; ?>;
const totalDuration = 600; // 10 minutes
let remainingSeconds = <?php echo (int)$remainingSeconds; ?>;
let checking = false;
let paymentTimer = null;
let countdownTimer = null;

// Toggle mobile QR code view
function toggleMobileQr() {
    const wrap = document.getElementById('mobileQrWrap');
    const lbl = document.getElementById('toggleQrLabel');
    if (!wrap) return;
    if (wrap.style.display === 'none' || wrap.style.display === '') {
        wrap.style.display = 'block';
        if (lbl) lbl.textContent = "Hide QR Code";
    } else {
        wrap.style.display = 'none';
        if (lbl) lbl.textContent = "Scan from another phone? Show QR";
    }
}

// Auto-trigger UPI payment on mobile devices
(function() {
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || window.innerWidth <= 768;
    if (isMobile) {
        // Automatically attempt to invoke the UPI deep link after 1.5s
        setTimeout(function() {
            const btn = document.getElementById("btnMobilePayNow");
            if (btn && btn.href) {
                // If in Android app or mobile browser, auto-navigate to upi intent
                window.location.href = btn.href;
            }
        }, 1500);
    }
})();

// ─────────────────────────────────────────────────────────────────────────────
// COUNTDOWN TIMER LOGIC
// ─────────────────────────────────────────────────────────────────────────────
function updateCountdown() {
    if (remainingSeconds <= 0) {
        const displayEl = document.getElementById("timerDisplay");
        if (displayEl) displayEl.innerText = "Time Expired - Still checking bank...";
        
        const containerEl = document.getElementById("timerContainer");
        if (containerEl) containerEl.classList.add("warning");

        // Even when timer hits 0, DO NOT stop checking bank! 
        // Student might have just paid. Continue polling every 3 seconds!
        return;
    }

    remainingSeconds--;

    const minutes = Math.floor(remainingSeconds / 60);
    const seconds = remainingSeconds % 60;
    const formatted = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    
    const displayEl = document.getElementById("timerDisplay");
    const containerEl = document.getElementById("timerContainer");
    const progressEl = document.getElementById("progressBar");

    if (displayEl) displayEl.innerText = formatted;
    
    if (progressEl) {
        const percent = Math.max(0, Math.min(100, (remainingSeconds / totalDuration) * 100));
        progressEl.style.width = percent + "%";
    }

    if (remainingSeconds <= 60 && containerEl) {
        containerEl.classList.add("warning");
    }
}

countdownTimer = setInterval(updateCountdown, 1000);

// ─────────────────────────────────────────────────────────────────────────────
// SUCCESS REDIRECT: Transition away from spinner, clear cart, push to success
// ─────────────────────────────────────────────────────────────────────────────
function redirectToSuccess(paymentId) {
    // Stop all timers
    if (paymentTimer)   clearInterval(paymentTimer);
    if (countdownTimer) clearInterval(countdownTimer);

    // 1. Clear out active food cart items from localStorage & sessionStorage
    try {
        localStorage.removeItem('cart');
        localStorage.removeItem('canteen_cart');
        localStorage.removeItem('food_cart');
        localStorage.removeItem('shopping_cart');
        sessionStorage.removeItem('cart');
    } catch (e) {
        console.warn("Storage clear warning:", e);
    }

    // 2. Transition away from payment spinner — show confirmed state
    const box = document.getElementById("statusBox");
    if (box) {
        box.className = "status-box success";
        box.innerHTML = "<i class='fa-solid fa-circle-check' style='font-size:18px;'></i> <strong>Payment Confirmed! Order Placed Successfully.</strong>";
    }

    // 3. Update the verify button to show confirmed
    const btn = document.getElementById("btnFastVerify");
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = "<i class='fa-solid fa-check-double'></i> Confirmed by Bank!";
        btn.style.background = "#16a34a";
    }

    // 4. Hide/close payment modal UI elements (timer, QR)
    const tc = document.getElementById("timerContainer");
    if (tc) tc.style.display = "none";

    const qr = document.getElementById("qrImg");
    if (qr) qr.style.opacity = "0.5";

    // Also close any paymentModal overlay if it exists in page
    const modal = document.getElementById("paymentModal");
    if (modal) modal.style.display = "none";

    const modalStatus = document.getElementById("paymentModalStatus");
    if (modalStatus) modalStatus.innerText = "Payment Verified ✅";

    // 5. Force push the browser tab to the success confirmation page
    setTimeout(function() {
        window.location.href = "payment_success.php?order_id=" + localOrderId + "&uropay_id=" + encodeURIComponent(orderId);
    }, 600);
}

function redirectToFailed() {
    if (paymentTimer)   clearInterval(paymentTimer);
    if (countdownTimer) clearInterval(countdownTimer);

    const box = document.getElementById("statusBox");
    if (box) {
        box.className = "status-box failed";
        box.innerHTML = "<i class='fa-solid fa-circle-xmark'></i> Payment Cancelled or Rejected.";
    }

    setTimeout(function() {
        window.location.href = "payment_failed.php";
    }, 1500);
}

// ─────────────────────────────────────────────────────────────────────────────
// CORE STATUS FETCH — called by both auto-poll and manual verify button
// ─────────────────────────────────────────────────────────────────────────────
async function checkPayment(isManual = false) {
    if (checking) return;
    checking = true;

    const statusText = document.getElementById("statusText");
    const statusBox  = document.getElementById("statusBox");

    if (isManual && statusText) {
        statusText.innerText = "Checking bank status in real-time...";
        if (statusBox) statusBox.className = "status-box checking";
    }

    try {
        const response = await fetch(
            "check_uropay_status.php?order_id=" + encodeURIComponent(orderId) + "&t=" + Date.now(),
            { method: "GET", cache: "no-store" }
        );

        const data = await response.json();
        const statusValue = (data && (data.status || data.uropay_status || "")).toString().trim().toUpperCase();

        const successStatuses = [
            "COMPLETED", "SUCCESS", "SUCCESSFUL", "PAID", "PAYMENT_SUCCESS",
            "PAYMENT_COMPLETED", "PAYMENT_SUCCEEDED", "TRANSACTION_SUCCESS",
            "TRANSACTION_COMPLETED", "CAPTURED", "SETTLED", "APPROVED"
        ];

        const failedStatuses = [
            "CANCELLED", "CANCELED", "FAILED", "FAILURE", "PAYMENT_FAILED",
            "TRANSACTION_FAILED", "REJECTED"
        ];

        if (data.success && successStatuses.includes(statusValue)) {
            redirectToSuccess(data.payment_id);
            return;
        }

        if (data.success && failedStatuses.includes(statusValue)) {
            redirectToFailed();
            return;
        }

        if (isManual && statusText) {
            statusText.innerHTML = "<i class='fa-solid fa-clock-rotate-left'></i> Bank: Pending. If already transferred via UPI app, enter the 12-digit UTR below to confirm!";
            if (statusBox) statusBox.className = "status-box checking";
            
            const utrBox = document.getElementById("manualUtrInput");
            if (utrBox) {
                utrBox.style.borderColor = "#16a34a";
                utrBox.style.boxShadow = "0 0 0 3px rgba(22, 163, 74, 0.35)";
                utrBox.focus();
            }
        }
    } catch (error) {
        console.error("Payment check error:", error);
        if (isManual && document.getElementById("statusText")) {
            document.getElementById("statusText").innerText = "Network check in progress...";
        }
    } finally {
        checking = false;
    }
}

function checkPaymentManual() {
    checkPayment(true);
}

// ─────────────────────────────────────────────────────────────────────────────
// startPaymentTracking(orderId)
// Call this function the moment the user clicks "Pay Now" or finishes
// scanning the UPI QR code. It kicks off the high-speed polling loop.
// ─────────────────────────────────────────────────────────────────────────────
function startPaymentTracking(trackOrderId) {
    console.log("Tracking payment for Order #" + trackOrderId);

    // Show a loading status to the customer
    const modalStatus = document.getElementById("paymentModalStatus");
    if (modalStatus) modalStatus.innerText = "Verifying payment, please wait...";

    // Clear any existing timer to avoid duplicates
    if (paymentTimer) clearInterval(paymentTimer);

    // Start a high-speed loop checking every 1.5 seconds
    paymentTimer = setInterval(async () => {
        try {
            const response = await fetch(
                "check_uropay_status.php?order_id=" + encodeURIComponent(trackOrderId) + "&t=" + Date.now(),
                { cache: "no-store" }
            );
            const data = await response.json();
            const statusValue = (data.status || data.uropay_status || "").toString().trim().toUpperCase();

            const successStatuses = [
                "COMPLETED", "SUCCESS", "SUCCESSFUL", "PAID",
                "PAYMENT_SUCCESS", "PAYMENT_COMPLETED", "CAPTURED", "SETTLED", "APPROVED"
            ];

            if (data.success && successStatuses.includes(statusValue)) {
                // Stop checking immediately
                clearInterval(paymentTimer);

                // 1. Hide/Close payment modal UI element
                const modal = document.getElementById("paymentModal");
                if (modal) modal.style.display = "none";

                // 2. Clear cart from localStorage
                try {
                    localStorage.removeItem('cart');
                    localStorage.removeItem('canteen_cart');
                    localStorage.removeItem('food_cart');
                    localStorage.removeItem('shopping_cart');
                    sessionStorage.removeItem('cart');
                } catch(e) {}

                // 3. Redirect to success/tracking screen
                redirectToSuccess(data.payment_id);
            } else {
                console.log("Still pending... re-checking soon.");
            }
        } catch (error) {
            console.error("Status check failed:", error);
        }
    }, 1500); // 1.5 seconds interval ensures instant 2–3 sec detection

    // Safety timeout: 10 minutes matches the order payment window
    setTimeout(() => {
        clearInterval(paymentTimer);
        console.warn("Payment tracking stopped after 10-minute timeout.");
    }, 600000);
}

// ─────────────────────────────────────────────────────────────────────────────
// COPY UPI ID HELPER
// ─────────────────────────────────────────────────────────────────────────────
function copyUpiId() {
    const upiEl = document.getElementById("upiIdDisplay");
    const text  = upiEl ? upiEl.innerText.trim() : "";
    if (!text) return;

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => showCopiedFeedback());
    } else {
        const ta = document.createElement("textarea");
        ta.value = text;
        ta.style.position = "fixed";
        ta.style.left = "-9999px";
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        try {
            document.execCommand('copy');
            showCopiedFeedback();
        } catch (err) {
            console.error('Fallback copy failed', err);
        }
        document.body.removeChild(ta);
    }
}

function showCopiedFeedback() {
    const label = document.getElementById("copyBtnLabel");
    if (label) {
        label.innerText = "Copied! ✓";
        setTimeout(() => {
            label.innerText = "Copy";
        }, 2000);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// SUBMIT MANUAL 12-DIGIT UTR
// ─────────────────────────────────────────────────────────────────────────────
async function submitManualUtr() {
    const input = document.getElementById("manualUtrInput");
    const msg   = document.getElementById("utrMessage");
    const btn   = document.getElementById("btnSubmitUtr");
    if (!input || !msg || !btn) return;

    const utr = input.value.trim();
    if (!utr || utr.length < 6) {
        msg.style.display = "block";
        msg.style.color = "#dc2626";
        msg.innerText = "⚠️ Please enter a valid 12-digit UTR / UPI Ref number.";
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Checking...';
    msg.style.display = "block";
    msg.style.color = "#0284c7";
    msg.innerText = "Verifying UTR with bank...";

    try {
        const res = await fetch("check_uropay_status.php?order_id=" + encodeURIComponent(orderId) + "&utr=" + encodeURIComponent(utr) + "&t=" + Date.now(), { cache: "no-store" });
        const data = await res.json();

        if (data.success && (data.status === "PAID" || data.status === "Completed")) {
            msg.style.color = "#16a34a";
            msg.innerHTML = "✅ Payment Confirmed with UTR: " + utr;
            setTimeout(() => {
                redirectToSuccess(data.payment_id || utr);
            }, 800);
        } else {
            msg.style.color = "#dc2626";
            msg.innerText = "⚠️ " + (data.message || "UTR could not be verified. Please recheck.");
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-bolt"></i> Confirm Order';
        }
    } catch (e) {
        console.error("Manual UTR check error:", e);
        msg.style.color = "#dc2626";
        msg.innerText = "❌ Network error. Please click 'Verify Bank Status' above.";
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-bolt"></i> Confirm Order';
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// AUTO-DETECT 12-DIGIT UTR INPUT
// ─────────────────────────────────────────────────────────────────────────────
const utrInput = document.getElementById("manualUtrInput");
if (utrInput) {
    utrInput.addEventListener("input", function() {
        const clean = this.value.replace(/[^0-9]/g, '');
        if (clean.length === 12) {
            this.value = clean;
            submitManualUtr();
        }
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// AUTO-TRIGGER STATUS CHECK WHEN STUDENT SWITCHES BACK FROM GPAY / UPI APP
// ─────────────────────────────────────────────────────────────────────────────
document.addEventListener("visibilitychange", function() {
    if (document.visibilityState === "visible") {
        console.log("Tab resumed from UPI app — verifying payment immediately...");
        checkPayment(false);
    }
});

window.addEventListener("focus", function() {
    console.log("Window focused — verifying payment immediately...");
    checkPayment(false);
});

// ─────────────────────────────────────────────────────────────────────────────
// AUTO-START on page load — the QR is already showing, begin tracking now
// ─────────────────────────────────────────────────────────────────────────────
startPaymentTracking(orderId);
</script>

</body>
</html>
