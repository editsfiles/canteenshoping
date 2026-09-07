<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include("php/db.php");
include("config_uropay.php");

// Resolve order ID from GET or session
$uroPayOrderId = trim($_GET['order_id'] ?? ($_SESSION['uropay_order_id'] ?? ''));
$localOrderId  = (int)($_GET['local_id'] ?? ($_SESSION['local_order_id'] ?? 0));
if ($localOrderId === 0 && is_numeric($uroPayOrderId)) {
    $localOrderId = (int)$uroPayOrderId;
}

// Fetch order from DB by ref ID or local ID
$orderRow = null;
if (!empty($uroPayOrderId) || $localOrderId > 0) {
    $sql = "SELECT id, total_amount, payment_id, merchant_order_id, upi_id, status, food_status, qr_code
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
    if (!empty($orderRow['payment_id'])) {
        $uroPayOrderId = $orderRow['payment_id'];
    } elseif (!empty($orderRow['merchant_order_id'])) {
        $uroPayOrderId = $orderRow['merchant_order_id'];
    } elseif (empty($uroPayOrderId)) {
        $uroPayOrderId = "CANTEEN" . $localOrderId;
    }

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
        $apiStatus = strtoupper(trim($d['orderStatus'] ?? ($d['status'] ?? '')));
        if (in_array($apiStatus, ['COMPLETED','SUCCESS','SUCCESSFUL','PAID','CAPTURED','SETTLED','APPROVED'], true)) {
            $upd = mysqli_prepare($conn, "UPDATE orders SET status='Completed', food_status=CASE WHEN food_status IS NULL OR food_status='' THEN 'Preparing' ELSE food_status END WHERE id=?");
            if ($upd) { mysqli_stmt_bind_param($upd,"i",$localOrderId); mysqli_stmt_execute($upd); mysqli_stmt_close($upd); }
            header("Location: payment_success.php");
            exit();
        }
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
$upiId = !empty($orderRow['upi_id']) ? $orderRow['upi_id'] : ($_SESSION['uropay_upi_id'] ?? '');
if (empty($upiId) && !empty($orderRow['qr_code'])) {
    if (preg_match('/[?&]pa=([^&]+)/i', $orderRow['qr_code'], $m)) {
        $upiId = urldecode($m[1]);
    }
}
if (empty($upiId)) {
    $upiId = defined('CANTEEN_UPI_ID') ? CANTEEN_UPI_ID : '9952611859@slc';
}

if ($localOrderId > 0 && (empty($orderRow['upi_id']) || $orderRow['upi_id'] !== $upiId)) {
    $updUpi = mysqli_prepare($conn, "UPDATE orders SET upi_id=? WHERE id=?");
    if ($updUpi) {
        mysqli_stmt_bind_param($updUpi, "si", $upiId, $localOrderId);
        mysqli_stmt_execute($updUpi);
        mysqli_stmt_close($updUpi);
    }
}

$canteenMerchantName = "College Canteen";
$formattedAmount = number_format((float)$displayAmount, 2, '.', '');
$upiDeepLink = !empty($upiString) ? $upiString : ("upi://pay?pa=" . urlencode($upiId) . 
               "&pn=" . urlencode($canteenMerchantName) . 
               "&am=" . $formattedAmount . 
               "&tr=" . urlencode($uroPayOrderId) . 
               "&tn=" . urlencode("Order #" . $localOrderId . " Canteen") . 
               "&cu=INR");

if (empty($qrCode)) {
    $qrCode = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($upiDeepLink);
}

$mins = floor($remainingSeconds / 60);
$secs = $remainingSeconds % 60;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<title>Scan & Pay ₹<?php echo number_format((float)$displayAmount, 2); ?> - College Canteen</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
<style>
:root {
    --primary: #059669;
    --primary-hover: #047857;
    --primary-light: #ecfdf5;
    --accent: #2563eb;
    --text-main: #0f172a;
    --text-muted: #64748b;
    --border-subtle: #e2e8f0;
    --card-bg: #ffffff;
    --app-font: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    --mono-font: 'JetBrains Mono', monospace;
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: var(--app-font);
    -webkit-font-smoothing: antialiased;
}

body {
    background: radial-gradient(circle at 50% -10%, #dcfce7 0%, #f0fdf4 30%, #f8fafc 70%, #f1f5f9 100%);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 24px 16px;
    color: var(--text-main);
}

/* ─── PAYMENT CARD ─── */
.payment-card {
    position: relative;
    width: 440px;
    max-width: 100%;
    background: var(--card-bg);
    border-radius: 28px;
    padding: 28px 24px 22px;
    text-align: center;
    box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.12), 0 0 0 1px rgba(15, 23, 42, 0.05);
    animation: cardEntrance 0.45s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes cardEntrance {
    from { opacity: 0; transform: translateY(18px) scale(0.98); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}

/* ─── TOP BRAND HEADER ─── */
.card-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid #f1f5f9;
}

.brand-identity {
    display: flex;
    align-items: center;
    gap: 12px;
    text-align: left;
}

.brand-avatar {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    box-shadow: 0 6px 14px rgba(16, 185, 129, 0.28);
}

.brand-info h1 {
    font-size: 15px;
    font-weight: 800;
    color: var(--text-main);
    letter-spacing: -0.2px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.verified-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #ecfdf5;
    color: #059669;
    font-size: 10.5px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 20px;
    border: 1px solid #a7f3d0;
}

.brand-sub {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 1px;
}

.btn-card-close {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    font-size: 14px;
    transition: all 0.2s ease;
    cursor: pointer;
}

.btn-card-close:hover {
    background: #fee2e2;
    color: #dc2626;
    border-color: #fecdd3;
    transform: rotate(90deg);
}

/* ─── AMOUNT HERO ─── */
.amount-hero {
    margin-bottom: 16px;
}

.amount-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--text-muted);
    margin-bottom: 4px;
}

.amount-val {
    font-size: 38px;
    font-weight: 800;
    color: var(--text-main);
    letter-spacing: -1px;
    line-height: 1.1;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    gap: 3px;
}

.amount-val sup {
    font-size: 22px;
    font-weight: 700;
    margin-top: 5px;
    color: var(--text-muted);
}

.order-badges-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 6px;
    flex-wrap: wrap;
}

.badge-item {
    font-size: 11.5px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 20px;
    background: #f1f5f9;
    color: #475569;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.badge-item.highlight {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}

/* ─── COUNTDOWN CAPSULE ─── */
.timer-capsule {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 9px 14px;
    margin-bottom: 18px;
    transition: all 0.3s ease;
}

.timer-capsule.warning {
    background: #fff1f2;
    border-color: #fecdd3;
}

.timer-top-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 12px;
    color: var(--text-muted);
    font-weight: 600;
}

.timer-display {
    font-family: var(--mono-font);
    font-weight: 700;
    font-size: 13.5px;
    color: #0284c7;
    letter-spacing: 0.5px;
}

.timer-capsule.warning .timer-display {
    color: #e11d48;
}

.timer-track {
    height: 4px;
    width: 100%;
    background: #e2e8f0;
    border-radius: 10px;
    margin-top: 6px;
    overflow: hidden;
}

.timer-fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981, #0284c7);
    border-radius: 10px;
    transition: width 1s linear, background 0.3s;
}

.timer-capsule.warning .timer-fill {
    background: linear-gradient(90deg, #f59e0b, #ef4444);
}

/* ─── FINTECH SCANNER FRAME ─── */
.scanner-container {
    position: relative;
    display: inline-block;
    padding: 16px;
    background: #ffffff;
    border-radius: 24px;
    box-shadow: 0 12px 32px -8px rgba(15, 23, 42, 0.1), 0 0 0 1px rgba(15, 23, 42, 0.04);
    margin: 0 auto 14px;
}

.scanner-bracket {
    position: absolute;
    width: 24px;
    height: 24px;
    border-color: #10b981;
    border-style: solid;
    pointer-events: none;
    transition: border-color 0.3s;
}

.bracket-tl { top: 8px; left: 8px; border-width: 3px 0 0 3px; border-top-left-radius: 10px; }
.bracket-tr { top: 8px; right: 8px; border-width: 3px 3px 0 0; border-top-right-radius: 10px; }
.bracket-bl { bottom: 8px; left: 8px; border-width: 0 0 3px 3px; border-bottom-left-radius: 10px; }
.bracket-br { bottom: 8px; right: 8px; border-width: 0 3px 3px 0; border-bottom-right-radius: 10px; }

.scanner-laser {
    position: absolute;
    left: 20px;
    right: 20px;
    top: 20px;
    height: 2px;
    background: linear-gradient(90deg, transparent, #10b981, #34d399, transparent);
    box-shadow: 0 0 12px #10b981;
    animation: sweepLaser 2.5s ease-in-out infinite;
    pointer-events: none;
    opacity: 0.85;
}

@keyframes sweepLaser {
    0%, 100% { top: 20px; opacity: 0; }
    15% { opacity: 1; }
    85% { opacity: 1; }
    90% { top: calc(100% - 22px); opacity: 0; }
}

.qr-image {
    width: 210px;
    height: 210px;
    display: block;
    border-radius: 12px;
    object-fit: contain;
}

/* ─── UPI APPS RIBBON ─── */
.upi-apps-ribbon {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-bottom: 14px;
    flex-wrap: wrap;
}

.upi-app-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 700;
    padding: 5px 9px;
    border-radius: 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #334155;
    transition: transform 0.2s ease;
}

.upi-app-badge.gpay { color: #1a73e8; background: #f8fbff; border-color: #d2e3fc; }
.upi-app-badge.phonepe { color: #5f259f; background: #faf5ff; border-color: #e9d5ff; }
.upi-app-badge.paytm { color: #00b9f5; background: #f0fdfa; border-color: #ccfbf1; }
.upi-app-badge.bhim { color: #ea580c; background: #fff7ed; border-color: #ffedd5; }

/* ─── UPI ID COPY CHIP ─── */
.upi-chip-card {
    background: #f8fafc;
    border: 1.5px dashed #cbd5e1;
    border-radius: 14px;
    padding: 10px 14px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.upi-chip-details {
    display: flex;
    align-items: center;
    gap: 8px;
    overflow: hidden;
}

.upi-tag {
    font-size: 9.5px;
    font-weight: 800;
    text-transform: uppercase;
    padding: 2px 6px;
    border-radius: 6px;
    background: #e2e8f0;
    color: #475569;
}

.upi-id-value {
    font-family: var(--mono-font);
    font-size: 13px;
    font-weight: 700;
    color: var(--text-main);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.btn-chip-copy {
    background: var(--text-main);
    color: white;
    border: none;
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.btn-chip-copy:hover {
    background: #1e293b;
    transform: scale(1.03);
}

.btn-chip-copy.copied {
    background: #059669;
}

/* ─── MOBILE NATIVE INTENT FLOW (<= 768px) ─── */
.mobile-flow {
    display: none;
}
.desktop-flow {
    display: block;
}

@media (max-width: 768px) {
    .desktop-flow {
        display: none !important;
    }
    .mobile-flow {
        display: block !important;
    }
}

@media (max-width: 480px) {
    body {
        padding: 12px 10px;
    }
    .payment-card {
        padding: 20px 16px 18px;
        border-radius: 22px;
    }
    .amount-val {
        font-size: 32px;
    }
    .card-topbar {
        margin-bottom: 14px;
        padding-bottom: 12px;
    }
    .scanner-container {
        padding: 10px;
    }
    .qr-image {
        width: 180px;
        height: 180px;
    }
}

.btn-native-pay {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: linear-gradient(135deg, #059669, #10b981);
    color: white;
    text-decoration: none;
    padding: 16px 20px;
    border-radius: 16px;
    box-shadow: 0 8px 24px rgba(5, 150, 105, 0.35);
    margin-bottom: 14px;
    transition: transform 0.2s, box-shadow 0.2s;
}

.btn-native-pay:active {
    transform: scale(0.98);
}

.native-pay-left {
    display: flex;
    align-items: center;
    gap: 12px;
    text-align: left;
}

.native-pay-title {
    font-size: 15.5px;
    font-weight: 800;
    line-height: 1.2;
}

.native-pay-sub {
    font-size: 11.5px;
    opacity: 0.9;
    font-weight: 500;
    margin-top: 2px;
}

.btn-toggle-qr-view {
    background: none;
    border: 1px dashed #94a3b8;
    color: var(--text-muted);
    padding: 9px 14px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    width: 100%;
    margin-bottom: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

/* ─── LIVE PULSING STATUS ─── */
.live-status-pill {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 30px;
    padding: 8px 14px;
    font-size: 12px;
    font-weight: 600;
    color: #475569;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    margin-bottom: 14px;
    width: 100%;
    transition: all 0.3s ease;
}

.live-status-pill.checking {
    background: #eff6ff;
    border-color: #bfdbfe;
    color: #1d4ed8;
}

.live-status-pill.success {
    background: #ecfdf5;
    border-color: #a7f3d0;
    color: #059669;
}

.live-status-pill.failed {
    background: #fef2f2;
    border-color: #fecdd3;
    color: #dc2626;
}

.radar-ping {
    position: relative;
    width: 9px;
    height: 9px;
    background: #10b981;
    border-radius: 50%;
}

.radar-ping::after {
    content: '';
    position: absolute;
    inset: -4px;
    border-radius: 50%;
    border: 2px solid #10b981;
    animation: radarRipple 1.6s cubic-bezier(0, 0.2, 0.8, 1) infinite;
}

@keyframes radarRipple {
    0% { transform: scale(0.6); opacity: 1; }
    100% { transform: scale(2.2); opacity: 0; }
}

/* ─── PRIMARY CONFIRM BUTTON ─── */
.btn-primary-confirm {
    width: 100%;
    background: linear-gradient(135deg, #059669, #10b981);
    color: white;
    border: none;
    padding: 14px 18px;
    border-radius: 14px;
    font-size: 14.5px;
    font-weight: 800;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    box-shadow: 0 8px 20px -4px rgba(5, 150, 105, 0.4);
    transition: all 0.2s ease;
    margin-bottom: 12px;
}

.btn-primary-confirm:hover {
    background: linear-gradient(135deg, #047857, #059669);
    transform: translateY(-1px);
    box-shadow: 0 10px 24px -4px rgba(5, 150, 105, 0.45);
}

.btn-primary-confirm:active {
    transform: scale(0.99);
}

/* ─── DISCREET UTR ACCORDION ─── */
.utr-accordion {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #f8fafc;
    overflow: hidden;
    margin-bottom: 16px;
    transition: border-color 0.2s ease;
}

.utr-accordion-btn {
    width: 100%;
    background: none;
    border: none;
    padding: 10px 14px;
    font-size: 12px;
    color: var(--text-muted);
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
}

.utr-accordion-btn strong {
    color: var(--text-main);
}

.utr-accordion-btn i {
    transition: transform 0.25s ease;
    font-size: 11px;
}

.utr-accordion-btn.open i {
    transform: rotate(180deg);
}

.utr-drawer-body {
    padding: 0 14px 14px;
    border-top: 1px solid #f1f5f9;
    background: #ffffff;
    text-align: left;
}

.utr-hint {
    font-size: 11px;
    color: var(--text-muted);
    margin: 10px 0 8px;
    line-height: 1.4;
}

.utr-input-group {
    display: flex;
    gap: 6px;
}

.utr-input-group input {
    flex: 1;
    padding: 9px 12px;
    border: 1.5px solid #cbd5e1;
    border-radius: 8px;
    font-size: 13px;
    font-family: var(--mono-font);
    outline: none;
    transition: border-color 0.2s;
}

.utr-input-group input:focus {
    border-color: #059669;
    box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
}

.utr-input-group button {
    background: #059669;
    color: white;
    border: none;
    padding: 9px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.2s;
    white-space: nowrap;
}

.utr-input-group button:hover {
    background: #047857;
}

.utr-feedback {
    margin-top: 6px;
    font-size: 11.5px;
    font-weight: 600;
}

/* ─── FOOTER ACTIONS & TRUST ─── */
.footer-actions {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    font-size: 12.5px;
    margin-bottom: 12px;
}

.footer-actions a {
    color: var(--text-muted);
    text-decoration: none;
    font-weight: 600;
    transition: color 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.footer-actions a:hover {
    color: var(--text-main);
}

.footer-actions .cancel-link {
    color: #ef4444;
}

.footer-actions .cancel-link:hover {
    color: #b91c1c;
}

.footer-divider {
    color: #cbd5e1;
    font-size: 10px;
}

.trust-shield {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 600;
    color: #94a3b8;
    padding-top: 10px;
    border-top: 1px solid #f8fafc;
}

.trust-shield i {
    color: #10b981;
    font-size: 12px;
}
</style>
</head>
<body>

<div class="payment-card">

    <!-- ─── TOP BRAND BAR ─── -->
    <div class="card-topbar">
        <div class="brand-identity">
            <div class="brand-avatar">🍴</div>
            <div class="brand-info">
                <h1>
                    College Canteen
                    <span class="verified-pill"><i class="fa-solid fa-circle-check"></i> Verified</span>
                </h1>
                <div class="brand-sub">Instant UPI Checkout Gateway</div>
            </div>
        </div>
        <a href="javascript:void(0)" onclick="confirmAndGoToOrders()" class="btn-card-close" title="Close and return to My Orders" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </a>
    </div>

    <!-- ─── AMOUNT HERO ─── -->
    <div class="amount-hero">
        <div class="amount-label">Amount Payable</div>
        <div class="amount-val">
            <sup>₹</sup><?php echo number_format((float)$displayAmount, 2); ?>
        </div>
        <div class="order-badges-row">
            <span class="badge-item highlight"><i class="fa-solid fa-receipt"></i> Order #<?php echo htmlspecialchars((string)$localOrderId); ?></span>
            <span class="badge-item"><i class="fa-solid fa-shield-halved"></i> 3% GST Included</span>
            <span class="badge-item" style="color:#059669; background:#ecfdf5;"><i class="fa-solid fa-bolt"></i> Fast Prep</span>
        </div>
    </div>

    <!-- ─── COUNTDOWN CAPSULE ─── -->
    <div class="timer-capsule" id="timerContainer">
        <div class="timer-top-line">
            <span><i class="fa-regular fa-clock"></i> Payment Window</span>
            <span class="timer-display" id="timerDisplay"><?php printf("%02d:%02d", $mins, $secs); ?></span>
        </div>
        <div class="timer-track">
            <div id="progressBar" class="timer-fill" style="width: <?php echo min(100, round(($remainingSeconds / 600) * 100)); ?>%;"></div>
        </div>
    </div>

    <!-- ─── DESKTOP PAYMENT FLOW (> 768px): SCANNER QR CODE ─── -->
    <div class="desktop-flow">
        <div class="scanner-container">
            <div class="scanner-bracket bracket-tl"></div>
            <div class="scanner-bracket bracket-tr"></div>
            <div class="scanner-bracket bracket-bl"></div>
            <div class="scanner-bracket bracket-br"></div>
            <div class="scanner-laser"></div>
            <?php if (!empty($qrCode)) { ?>
                <img id="qrImg" src="<?php echo htmlspecialchars($qrCode); ?>" class="qr-image" alt="UPI QR Code">
            <?php } else { ?>
                <div style="width:210px;height:210px;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:13px;">
                    <i class="fa-solid fa-spinner fa-spin"></i> Loading QR...
                </div>
            <?php } ?>
        </div>

        <div class="upi-apps-ribbon">
            <span class="upi-app-badge gpay"><i class="fa-brands fa-google"></i> GPay</span>
            <span class="upi-app-badge phonepe"><i class="fa-solid fa-mobile-screen"></i> PhonePe</span>
            <span class="upi-app-badge paytm"><i class="fa-solid fa-wallet"></i> Paytm</span>
            <span class="upi-app-badge bhim"><i class="fa-solid fa-building-columns"></i> BHIM</span>
        </div>
    </div>

    <!-- ─── MOBILE PAYMENT FLOW (<= 768px): 1-TAP NATIVE UPI ─── -->
    <div class="mobile-flow">
        <a href="<?php echo htmlspecialchars($upiDeepLink); ?>" class="btn-native-pay" id="btnMobilePayNow">
            <div class="native-pay-left">
                <i class="fa-solid fa-bolt" style="font-size:22px;"></i>
                <div>
                    <div class="native-pay-title">Pay ₹<?php echo number_format((float)$displayAmount, 2); ?> via UPI App</div>
                    <div class="native-pay-sub">Google Pay &bull; PhonePe &bull; Paytm &bull; BHIM</div>
                </div>
            </div>
            <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:16px;"></i>
        </a>

        <button type="button" class="btn-toggle-qr-view" onclick="toggleMobileQr()">
            <i class="fa-solid fa-qrcode"></i> <span id="toggleQrLabel">Scan from another phone? Show QR</span>
        </button>

        <div id="mobileQrWrap" style="display:none; margin-bottom:14px;">
            <div class="scanner-container" style="padding:12px;">
                <div class="scanner-bracket bracket-tl"></div>
                <div class="scanner-bracket bracket-tr"></div>
                <div class="scanner-bracket bracket-bl"></div>
                <div class="scanner-bracket bracket-br"></div>
                <?php if (!empty($qrCode)) { ?>
                    <img src="<?php echo htmlspecialchars($qrCode); ?>" class="qr-image" style="width:190px;height:190px;" alt="UPI QR Code">
                <?php } ?>
            </div>
            <p style="font-size:11.5px; color:#64748b;">Have a friend scan this QR with their UPI app</p>
        </div>
    </div>

    <!-- ─── UPI ID COPY CHIP ─── -->
    <div class="upi-chip-card">
        <div class="upi-chip-details">
            <span class="upi-tag">UPI ID</span>
            <span id="upiIdDisplay" class="upi-id-value"><?php echo htmlspecialchars($upiId); ?></span>
        </div>
        <button type="button" class="btn-chip-copy" onclick="copyUpiId()" id="btnCopy" title="Copy UPI ID">
            <i class="fa-regular fa-copy" id="copyIcon"></i> <span id="copyBtnLabel">Copy</span>
        </button>
    </div>

    <!-- ─── LIVE PULSING STATUS ─── -->
    <div id="statusBox" class="live-status-pill">
        <span class="radar-ping"></span>
        <span id="statusText">Listening for payment in real-time...</span>
    </div>

    <!-- ─── PRIMARY VERIFY CTA ─── -->
    <button id="btnFastVerify" class="btn-primary-confirm" onclick="checkPaymentManual()">
        <i class="fa-solid fa-circle-check"></i> I Have Paid · Confirm Order
    </button>

    <!-- ─── DISCREET UTR ACCORDION ─── -->
    <div class="utr-accordion">
        <button type="button" class="utr-accordion-btn" id="utrAccordionBtn" onclick="toggleUtrDrawer()">
            <span>Paid via UPI? <strong>Enter 12-Digit Bank UTR</strong></span>
            <i class="fa-solid fa-chevron-down" id="utrChevron"></i>
        </button>
        <div id="utrDrawerContent" class="utr-drawer-body" style="display:none;">
            <p class="utr-hint">If money was transferred but not yet confirmed, enter your 12-digit UTR from GPay / PhonePe / Paytm:</p>
            <div class="utr-input-group">
                <input 
                    type="text" 
                    id="manualUtrInput" 
                    placeholder="Enter 12-digit UTR / Ref No." 
                    maxlength="25"
                    autocomplete="off"
                >
                <button type="button" id="btnSubmitUtr" onclick="submitManualUtr()">
                    <i class="fa-solid fa-bolt"></i> Confirm
                </button>
            </div>
            <div id="utrMessage" class="utr-feedback" style="display:none;"></div>
        </div>
    </div>

    <!-- ─── FOOTER ACTIONS & TRUST ─── -->
    <div class="footer-actions">
        <a href="my_orders.php"><i class="fa-solid fa-clock-rotate-left"></i> My Orders</a>
        <span class="footer-divider">&bull;</span>
        <a href="verify_ref.php?ref=<?php echo urlencode($uroPayOrderId); ?>" target="_blank"><i class="fa-solid fa-file-shield"></i> Verify Receipt</a>
        <span class="footer-divider">&bull;</span>
        <a href="payment_failed.php" class="cancel-link"><i class="fa-solid fa-xmark"></i> Cancel</a>
    </div>

    <div class="trust-shield">
        <i class="fa-solid fa-shield-halved"></i>
        <span>256-bit SSL Encrypted &bull; Powered by NPCI BHIM UPI</span>
    </div>

</div>

<!-- ═════════════════════════════════════════════════════════════════════════ -->
<!-- PAYMENT SUCCESS MODAL (Triggered on instant confirmation) -->
<!-- ═════════════════════════════════════════════════════════════════════════ -->
<div id="successPanel" style="
    display:none;
    position:fixed;
    inset:0;
    background:rgba(15,23,42,0.6);
    backdrop-filter:blur(6px);
    z-index:9999;
    justify-content:center;
    align-items:center;
    padding:20px;
">
    <div style="
        background:white;
        border-radius:26px;
        padding:36px 28px;
        max-width:420px;
        width:100%;
        text-align:center;
        box-shadow:0 25px 60px rgba(0,0,0,0.3);
        animation:modalPop 0.4s cubic-bezier(0.16,1,0.3,1);
    ">
        <div style="
            width:76px; height:76px;
            background:#dcfce7;
            border-radius:50%;
            display:flex;
            align-items:center;
            justify-content:center;
            margin:0 auto 16px;
            font-size:36px;
            color:#059669;
            box-shadow:0 8px 20px rgba(5,150,105,0.25);
        ">
            <i class="fa-solid fa-check"></i>
        </div>

        <h2 style="font-size:23px; font-weight:800; color:#0f172a; margin-bottom:6px; letter-spacing:-0.3px;">Payment Successful!</h2>
        <p style="color:#64748b; font-size:14px; margin-bottom:20px;">Your food order has been confirmed & sent to the kitchen.</p>

        <div style="
            background:#f8fafc;
            border:1px solid #e2e8f0;
            border-radius:14px;
            padding:14px 18px;
            margin-bottom:20px;
            text-align:left;
        ">
            <div style="display:flex;justify-content:space-between;font-size:14px;padding:5px 0;">
                <span style="color:#64748b;">Order ID</span>
                <strong style="color:#0f172a;">#<?php echo (int)$localOrderId; ?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:14px;padding:5px 0;">
                <span style="color:#64748b;">Amount Paid</span>
                <strong style="color:#059669;">₹<?php echo number_format($displayAmount, 2); ?></strong>
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
            background:#059669;
            color:white;
            border-radius:12px;
            font-size:15px;
            font-weight:700;
            text-decoration:none;
            margin-bottom:10px;
            box-shadow:0 6px 16px rgba(5,150,105,0.3);
        ">
            <i class="fa-solid fa-receipt"></i> Track Order in Kitchen
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
        ">
            <i class="fa-solid fa-file-invoice"></i> View Printable Invoice
        </a>
    </div>
</div>

<style>
@keyframes modalPop {
    from { opacity: 0; transform: scale(0.9); }
    to   { opacity: 1; transform: scale(1); }
}
</style>

<script>
const orderId       = <?php echo json_encode($uroPayOrderId); ?>;
const localOrderId  = <?php echo (int)$localOrderId; ?>;
const displayAmount = <?php echo (float)$displayAmount; ?>;
const totalDuration = 600; // 10 minutes
let remainingSeconds = <?php echo (int)$remainingSeconds; ?>;
let checking        = false;
let paymentTimer    = null;
let countdownTimer  = null;

// ─────────────────────────────────────────────────────────────────────────────
// ACCORDION DRAWER TOGGLE FOR UTR
// ─────────────────────────────────────────────────────────────────────────────
function toggleUtrDrawer() {
    const content = document.getElementById('utrDrawerContent');
    const btn     = document.getElementById('utrAccordionBtn');
    if (!content || !btn) return;
    
    if (content.style.display === 'none' || content.style.display === '') {
        content.style.display = 'block';
        btn.classList.add('open');
        const input = document.getElementById('manualUtrInput');
        if (input) setTimeout(() => input.focus(), 150);
    } else {
        content.style.display = 'none';
        btn.classList.remove('open');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// TOGGLE MOBILE QR CODE
// ─────────────────────────────────────────────────────────────────────────────
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
        setTimeout(function() {
            const btn = document.getElementById("btnMobilePayNow");
            if (btn && btn.href) {
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
        clearInterval(countdownTimer);
        const containerEl = document.getElementById("timerContainer");
        if (containerEl) {
            containerEl.classList.add("warning");
            containerEl.innerHTML = `
                <div style="padding:10px 6px; text-align:center;">
                    <div style="font-weight:700; color:#b45309; font-size:13.5px; margin-bottom:4px;">
                        <i class="fa-solid fa-clock-rotate-left"></i> Payment Window Ended
                    </div>
                    <div style="font-size:12px; color:#475569; margin-bottom:10px;">
                        Did you complete the UPI transfer of ₹${Number(displayAmount).toFixed(2)}?
                    </div>
                    <div style="display:flex; gap:8px; justify-content:center;">
                        <button type="button" onclick="confirmPaymentNow()" style="background:#059669; color:white; border:none; padding:8px 16px; border-radius:8px; font-weight:700; font-size:13px; cursor:pointer; box-shadow:0 2px 8px rgba(5,150,105,0.3);">
                            <i class="fa-solid fa-check"></i> Yes, I Have Paid
                        </button>
                        <a href="my_orders.php" style="background:#f1f5f9; color:#334155; padding:8px 14px; border-radius:8px; text-decoration:none; font-size:13px; font-weight:600;">
                            View Orders
                        </a>
                    </div>
                </div>
            `;
        }
        return;
    }

    remainingSeconds--;

    const minutes = Math.floor(remainingSeconds / 60);
    const seconds = remainingSeconds % 60;
    const formatted = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    
    const displayEl   = document.getElementById("timerDisplay");
    const containerEl = document.getElementById("timerContainer");
    const progressEl  = document.getElementById("progressBar");

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
// SUCCESS REDIRECT
// ─────────────────────────────────────────────────────────────────────────────
function redirectToSuccess(paymentId) {
    if (paymentTimer)   clearInterval(paymentTimer);
    if (countdownTimer) clearInterval(countdownTimer);

    try {
        ['cart','canteen_cart','food_cart','shopping_cart'].forEach(k => {
            localStorage.removeItem(k);
            sessionStorage.removeItem(k);
        });
    } catch (e) {}

    const box = document.getElementById("statusBox");
    if (box) {
        box.className = "live-status-pill success";
        box.innerHTML = "<i class='fa-solid fa-circle-check' style='color:#059669;font-size:15px;'></i> <strong>Payment Confirmed! Order placed successfully.</strong>";
    }

    const btn = document.getElementById("btnFastVerify");
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = "<i class='fa-solid fa-check-double'></i> Confirmed by Bank!";
        btn.style.background = "#059669";
    }

    const tc = document.getElementById("timerContainer");
    if (tc) tc.style.display = "none";

    const panel = document.getElementById("successPanel");
    if (panel) {
        panel.style.display = "flex";
    }

    setTimeout(function() {
        window.location.href = "payment_success.php?order_id=" + localOrderId + "&uropay_id=" + encodeURIComponent(orderId);
    }, 1200);
}

function redirectToFailed() {
    if (paymentTimer)   clearInterval(paymentTimer);
    if (countdownTimer) clearInterval(countdownTimer);

    const box = document.getElementById("statusBox");
    if (box) {
        box.className = "live-status-pill failed";
        box.innerHTML = "<i class='fa-solid fa-circle-xmark'></i> Payment Cancelled or Rejected.";
    }

    setTimeout(function() {
        window.location.href = "payment_failed.php";
    }, 1500);
}

// ─────────────────────────────────────────────────────────────────────────────
// CORE PAYMENT CHECKING
// ─────────────────────────────────────────────────────────────────────────────
async function checkPayment(isManual = false) {
    if (checking) return;
    checking = true;

    const statusText = document.getElementById("statusText");
    const statusBox  = document.getElementById("statusBox");

    if (isManual && statusText) {
        statusText.innerText = "Querying bank status in real-time...";
        if (statusBox) statusBox.className = "live-status-pill checking";
    }

    try {
        const response = await fetch(
            "check_uropay_status.php?order_id=" + encodeURIComponent(orderId) + "&local_id=" + localOrderId + "&t=" + Date.now(),
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
            statusText.innerHTML = `Payment received? Confirming with kitchen...`;
            if (statusBox) statusBox.className = "live-status-pill checking";
            setTimeout(() => confirmPaymentNow(), 600);
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
// CONFIRM PAYMENT INSTANTLY
// ─────────────────────────────────────────────────────────────────────────────
async function confirmPaymentNow(utr = '') {
    if (paymentTimer)   clearInterval(paymentTimer);
    if (countdownTimer) clearInterval(countdownTimer);

    const statusText = document.getElementById("statusText");
    const statusBox  = document.getElementById("statusBox");
    if (statusText) statusText.innerHTML = "<i class='fa-solid fa-spinner fa-spin'></i> Confirming order with canteen...";
    if (statusBox)  statusBox.className = "live-status-pill checking";

    try {
        let url = "check_uropay_status.php?order_id=" + encodeURIComponent(orderId) + "&local_id=" + localOrderId + "&confirm_paid=1&t=" + Date.now();
        if (utr) url += "&utr=" + encodeURIComponent(utr);
        const res = await fetch(url, { method: "GET", cache: "no-store" });
        const data = await res.json();
        redirectToSuccess(data.payment_id || orderId);
    } catch(e) {
        console.error("Auto confirm error:", e);
        redirectToSuccess(orderId);
    }
}

function confirmAndGoToOrders() {
    confirmPaymentNow();
}

// ─────────────────────────────────────────────────────────────────────────────
// START HIGH-SPEED BACKGROUND TRACKING
// ─────────────────────────────────────────────────────────────────────────────
function startPaymentTracking(trackOrderId) {
    if (paymentTimer) clearInterval(paymentTimer);

    paymentTimer = setInterval(async () => {
        try {
            const response = await fetch(
                "check_uropay_status.php?order_id=" + encodeURIComponent(trackOrderId) + "&local_id=" + localOrderId + "&t=" + Date.now(),
                { cache: "no-store" }
            );
            const data = await response.json();
            const statusValue = (data.status || data.uropay_status || "").toString().trim().toUpperCase();

            const successStatuses = [
                "COMPLETED", "SUCCESS", "SUCCESSFUL", "PAID",
                "PAYMENT_SUCCESS", "PAYMENT_COMPLETED", "CAPTURED", "SETTLED", "APPROVED"
            ];

            if (data.success && successStatuses.includes(statusValue)) {
                clearInterval(paymentTimer);
                redirectToSuccess(data.payment_id);
            }
        } catch (error) {
            console.error("Status check failed:", error);
        }
    }, 1500);

    setTimeout(() => {
        clearInterval(paymentTimer);
    }, 600000);
}

// ─────────────────────────────────────────────────────────────────────────────
// 1-TAP COPY UPI ID
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
    const icon  = document.getElementById("copyIcon");
    const btn   = document.getElementById("btnCopy");
    if (label && btn) {
        label.innerText = "Copied!";
        if (icon) icon.className = "fa-solid fa-check";
        btn.classList.add("copied");
        setTimeout(() => {
            label.innerText = "Copy";
            if (icon) icon.className = "fa-regular fa-copy";
            btn.classList.remove("copied");
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
        msg.innerText = "⚠️ Please enter a valid 12-digit UTR / Ref number.";
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
    msg.style.display = "block";
    msg.style.color = "#0284c7";
    msg.innerText = "Verifying UTR with bank...";

    try {
        const res = await fetch("check_uropay_status.php?order_id=" + encodeURIComponent(orderId) + "&local_id=" + localOrderId + "&utr=" + encodeURIComponent(utr) + "&t=" + Date.now(), { cache: "no-store" });
        const data = await res.json();

        if (data.success && (data.status === "PAID" || data.status === "Completed")) {
            msg.style.color = "#059669";
            msg.innerHTML = "✅ Verified with Bank UTR: " + utr;
            setTimeout(() => {
                redirectToSuccess(data.payment_id || utr);
            }, 800);
        } else {
            msg.style.color = "#dc2626";
            msg.innerText = "⚠️ " + (data.message || "UTR verification in progress. Confirming order...");
            setTimeout(() => confirmPaymentNow(utr), 1000);
        }
    } catch (e) {
        console.error("Manual UTR check error:", e);
        confirmPaymentNow(utr);
    }
}

// Auto-detect 12-digit UTR input
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

// Auto check when user switches back from UPI app
document.addEventListener("visibilitychange", function() {
    if (document.visibilityState === "visible") {
        checkPayment(false);
    }
});

window.addEventListener("focus", function() {
    checkPayment(false);
});

// Begin background tracking
startPaymentTracking(orderId);
</script>

</body>
</html>
