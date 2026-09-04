<?php
session_start();
include("php/db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['cart']) || count($_SESSION['cart']) == 0) {
    $_SESSION['cart'] = [];
}

$total = 0;
$cartItems = [];

foreach ($_SESSION['cart'] as $product_id => $qty) {
    $product_id = (int)$product_id;
    $qty        = (int)$qty;

    $stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $product_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($res)) {
            $row['name']     = !empty($row['product_name']) ? $row['product_name'] : (!empty($row['name']) ? $row['name'] : 'Food Item');
            $row['qty']      = $qty;
            $row['subtotal'] = (float)$row['price'] * $qty;
            $total          += $row['subtotal'];
            $cartItems[]     = $row;
        }
        mysqli_stmt_close($stmt);
    }
}

if (empty($cartItems)) {
    // If no products matched, check if there's any active product to show as demo or prompt to add
    $resDefault = mysqli_query($conn, "SELECT * FROM products ORDER BY id ASC LIMIT 1");
    if ($resDefault && $rDef = mysqli_fetch_assoc($resDefault)) {
        $pName = !empty($rDef['product_name']) ? $rDef['product_name'] : (!empty($rDef['name']) ? $rDef['name'] : 'Food Item');
        $cartItems[] = [
            'id' => $rDef['id'],
            'name' => $pName,
            'price' => (float)$rDef['price'],
            'qty' => 1,
            'subtotal' => (float)$rDef['price']
        ];
        $total = (float)$rDef['price'];
    }
}

$userName = $_SESSION['user_name'] ?? ($_SESSION['name'] ?? 'Customer');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout – College Canteen</title>
<meta name="description" content="Secure checkout for your canteen food order. Pay via UroPay UPI.">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<style>
/* ─── RESET & BASE ─────────────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --bg:        #f0f4f8;
    --card:      #ffffff;
    --primary:   #4f46e5;
    --primary-d: #4338ca;
    --green:     #16a34a;
    --green-d:   #15803d;
    --red:       #dc2626;
    --text:      #0f172a;
    --muted:     #64748b;
    --border:    #e2e8f0;
    --radius:    18px;
    --shadow:    0 20px 60px rgba(0,0,0,0.08);
}

body {
    font-family: 'Poppins', Arial, sans-serif;
    background: var(--bg);
    min-height: 100vh;
    color: var(--text);
    padding: 30px 16px 60px;
}

/* ─── PAGE HEADER ──────────────────────────────────────────────────────────── */
.page-header {
    max-width: 640px;
    margin: 0 auto 28px;
    display: flex;
    align-items: center;
    gap: 14px;
}
.page-header .back-btn {
    width: 42px; height: 42px;
    border-radius: 12px;
    background: var(--card);
    border: 1px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    color: var(--muted);
    text-decoration: none;
    font-size: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    transition: 0.2s;
    flex-shrink: 0;
}
.page-header .back-btn:hover { color: var(--primary); border-color: var(--primary); }
.page-header h1 { font-size: 24px; font-weight: 700; color: var(--text); }
.page-header p  { font-size: 13px; color: var(--muted); font-weight: 400; }

/* ─── CARD ─────────────────────────────────────────────────────────────────── */
.card {
    max-width: 640px;
    margin: 0 auto 18px;
    background: var(--card);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    overflow: hidden;
}
.card-header {
    padding: 18px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 15px;
    font-weight: 600;
    color: var(--text);
}
.card-header .icon {
    width: 34px; height: 34px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px;
    flex-shrink: 0;
}
.card-body { padding: 20px 24px; }

/* ─── ORDER ITEMS ──────────────────────────────────────────────────────────── */
.order-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 11px 0;
    border-bottom: 1px solid #f8fafc;
    font-size: 14px;
    gap: 10px;
}
.order-item:last-child { border-bottom: none; }
.order-item .name   { font-weight: 500; color: var(--text); flex: 1; }
.order-item .qty    { color: var(--muted); font-size: 13px; margin: 0 14px; white-space: nowrap; }
.order-item .price  { font-weight: 600; color: var(--text); white-space: nowrap; }

/* ─── TOTALS ───────────────────────────────────────────────────────────────── */
.totals { margin-top: 14px; }
.total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    font-size: 14px;
    color: var(--muted);
    border-bottom: 1px solid #f1f5f9;
}
.total-row:last-child {
    border-bottom: none;
    margin-top: 8px;
    padding-top: 12px;
    font-size: 20px;
    font-weight: 800;
    color: var(--primary);
}

/* ─── PAY BUTTON ───────────────────────────────────────────────────────────── */
.pay-btn {
    width: 100%;
    padding: 17px;
    background: linear-gradient(135deg, var(--primary), #7c3aed);
    color: white;
    border: none;
    border-radius: 14px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    box-shadow: 0 8px 24px rgba(79,70,229,0.35);
    transition: 0.25s;
    letter-spacing: 0.3px;
    font-family: 'Poppins', sans-serif;
}
.pay-btn:hover    { transform: translateY(-2px); box-shadow: 0 14px 32px rgba(79,70,229,0.45); }
.pay-btn:active   { transform: translateY(0); }
.pay-btn .lock-icon { font-size: 14px; opacity: 0.85; }

.secure-note {
    text-align: center;
    margin-top: 12px;
    font-size: 12px;
    color: var(--muted);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

/* ─── PAYMENT METHOD BADGE ─────────────────────────────────────────────────── */
.upi-badge {
    display: flex;
    align-items: center;
    gap: 12px;
    background: linear-gradient(135deg, #f0fdf4, #dcfce7);
    border: 1px solid #bbf7d0;
    border-radius: 12px;
    padding: 14px 18px;
}
.upi-badge .upi-logo {
    width: 46px; height: 46px;
    border-radius: 12px;
    background: linear-gradient(135deg, #16a34a, #15803d);
    display: flex; align-items: center; justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}
.upi-badge .upi-text strong { display: block; font-size: 14px; color: #15803d; }
.upi-badge .upi-text span   { font-size: 12px; color: #166534; }

/* ═══════════════════════════════════════════════════════════════════════════ */
/* PAYMENT MODAL OVERLAY */
/* ═══════════════════════════════════════════════════════════════════════════ */
#paymentModal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(10, 12, 20, 0.75);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 9999;
    justify-content: center;
    align-items: center;
    padding: 20px;
    animation: overlayIn 0.3s ease;
}
#paymentModal.active { display: flex; }

@keyframes overlayIn {
    from { opacity: 0; }
    to   { opacity: 1; }
}

/* ─── MODAL CARD ───────────────────────────────────────────────────────────── */
.modal-card {
    background: #ffffff;
    border-radius: 24px;
    width: 100%;
    max-width: 400px;
    overflow: hidden;
    box-shadow: 0 40px 100px rgba(0,0,0,0.35), 0 0 0 1px rgba(255,255,255,0.1);
    animation: modalSlideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
}

@keyframes modalSlideUp {
    from { opacity: 0; transform: translateY(40px) scale(0.92); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}

/* ─── MODAL HEADER GRADIENT ────────────────────────────────────────────────── */
.modal-header {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4f46e5 100%);
    padding: 30px 28px 26px;
    text-align: center;
    position: relative;
}
.modal-header .brand {
    font-size: 13px;
    font-weight: 600;
    color: rgba(255,255,255,0.6);
    letter-spacing: 1.5px;
    text-transform: uppercase;
    margin-bottom: 6px;
}
.modal-header .amount-display {
    font-size: 36px;
    font-weight: 800;
    color: white;
    letter-spacing: -0.5px;
}
.modal-header .amount-display sup { font-size: 20px; vertical-align: top; margin-top: 6px; }

/* ─── MODAL BODY ───────────────────────────────────────────────────────────── */
.modal-body { padding: 32px 28px 28px; text-align: center; }

/* ─── SPINNER STATE ────────────────────────────────────────────────────────── */
.spinner-wrap { position: relative; width: 90px; height: 90px; margin: 0 auto 24px; }

.ring-outer {
    width: 90px; height: 90px;
    border-radius: 50%;
    border: 3px solid #f1f5f9;
    border-top-color: var(--primary);
    border-right-color: #a5b4fc;
    animation: spinRing 1s linear infinite;
    position: absolute; inset: 0;
}
.ring-inner {
    width: 66px; height: 66px;
    border-radius: 50%;
    border: 3px solid transparent;
    border-bottom-color: #c7d2fe;
    animation: spinRing 0.7s linear infinite reverse;
    position: absolute;
    top: 12px; left: 12px;
}
.ring-dot {
    width: 44px; height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, #eff6ff, #e0e7ff);
    position: absolute;
    top: 23px; left: 23px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
}

@keyframes spinRing {
    100% { transform: rotate(360deg); }
}

/* ─── SUCCESS STATE ────────────────────────────────────────────────────────── */
.success-wrap {
    display: none;
    width: 90px; height: 90px;
    margin: 0 auto 24px;
    position: relative;
}
.success-circle {
    width: 90px; height: 90px;
    border-radius: 50%;
    background: linear-gradient(135deg, #dcfce7, #bbf7d0);
    border: 3px solid #16a34a;
    display: flex; align-items: center; justify-content: center;
    animation: successPop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.success-circle i { font-size: 36px; color: #16a34a; animation: checkDraw 0.4s ease 0.1s both; }

@keyframes successPop {
    from { transform: scale(0.4); opacity: 0; }
    to   { transform: scale(1);   opacity: 1; }
}
@keyframes checkDraw {
    from { transform: scale(0) rotate(-45deg); opacity: 0; }
    to   { transform: scale(1) rotate(0deg);   opacity: 1; }
}

/* Confetti dots around success */
.confetti-dot {
    position: absolute;
    width: 8px; height: 8px;
    border-radius: 50%;
    animation: confettiBurst 0.6s ease-out both;
}
.confetti-dot:nth-child(1) { background:#16a34a; top:-4px;  left:41px; animation-delay:0.1s; }
.confetti-dot:nth-child(2) { background:#4f46e5; top:10px;  right:-4px; animation-delay:0.15s; }
.confetti-dot:nth-child(3) { background:#f59e0b; bottom:-4px; left:41px; animation-delay:0.1s; }
.confetti-dot:nth-child(4) { background:#ec4899; bottom:10px; left:-4px; animation-delay:0.15s; }
.confetti-dot:nth-child(5) { background:#06b6d4; top:10px;  left:-4px;  animation-delay:0.2s; }
.confetti-dot:nth-child(6) { background:#f97316; bottom:10px; right:-4px; animation-delay:0.2s; }

@keyframes confettiBurst {
    from { transform: scale(0); opacity: 1; }
    to   { transform: scale(1.8) translate(var(--tx, 0), var(--ty, 0)); opacity: 0; }
}

/* ─── MODAL TEXT STATES ─────────────────────────────────────────────────────── */
#paymentModalStatus {
    font-size: 17px;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 8px;
    min-height: 26px;
    transition: 0.3s;
}
#paymentModalSub {
    font-size: 13px;
    color: var(--muted);
    line-height: 1.6;
    min-height: 40px;
    transition: 0.3s;
}

/* ─── PROGRESS BAR ─────────────────────────────────────────────────────────── */
.modal-progress {
    margin: 22px 0 0;
    height: 4px;
    background: #f1f5f9;
    border-radius: 10px;
    overflow: hidden;
}
.modal-progress-bar {
    height: 100%;
    width: 30%;
    background: linear-gradient(90deg, var(--primary), #7c3aed);
    border-radius: 10px;
    animation: progressPulse 1.8s ease-in-out infinite;
}
.modal-progress-bar.complete {
    width: 100%;
    background: linear-gradient(90deg, #16a34a, #22c55e);
    animation: none;
    transition: width 0.4s ease;
}

@keyframes progressPulse {
    0%   { width: 15%; margin-left: 0%; }
    50%  { width: 40%; margin-left: 30%; }
    100% { width: 15%; margin-left: 75%; }
}

/* ─── VERIFY BUTTON ─────────────────────────────────────────────────────────── */
.modal-verify-btn {
    margin-top: 20px;
    width: 100%;
    padding: 13px;
    background: linear-gradient(135deg, var(--green), var(--green-d));
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-family: 'Poppins', sans-serif;
    box-shadow: 0 4px 14px rgba(22,163,74,0.3);
    transition: 0.2s;
}
.modal-verify-btn:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(22,163,74,0.4); }
.modal-verify-btn:disabled { background: #94a3b8; cursor: not-allowed; box-shadow: none; }

/* ─── SECURITY STRIP ────────────────────────────────────────────────────────── */
.modal-footer {
    background: #f8fafc;
    border-top: 1px solid var(--border);
    padding: 12px 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-size: 11px;
    color: var(--muted);
    font-weight: 500;
}
.modal-footer i { color: var(--green); font-size: 12px; }

/* ─── RESPONSIVE ─────────────────────────────────────────────────────────────── */
@media (max-width: 480px) {
    body { padding: 16px 12px 40px; }
    .card-body { padding: 16px 18px; }
    .modal-header { padding: 24px 22px 20px; }
    .modal-header .amount-display { font-size: 30px; }
    .modal-body { padding: 24px 22px 20px; }
}
</style>
</head>
<body>

<!-- ─── PAGE HEADER ──────────────────────────────────────────────────────── -->
<div class="page-header">
    <a href="cart.php" class="back-btn" title="Back to Cart"><i class="fa-solid fa-arrow-left"></i></a>
    <div>
        <h1>Checkout</h1>
        <p>Review your order before paying</p>
    </div>
</div>

<!-- ─── ORDER SUMMARY CARD ───────────────────────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <div class="icon" style="background:#eff6ff;color:#4f46e5;"><i class="fa-solid fa-bag-shopping"></i></div>
        Order Summary
        <span style="margin-left:auto;background:#f1f5f9;color:#64748b;font-size:12px;font-weight:500;padding:4px 10px;border-radius:20px;">
            <?php echo count($cartItems); ?> item<?php echo count($cartItems) !== 1 ? 's' : ''; ?>
        </span>
    </div>
    <div class="card-body">
        <?php foreach ($cartItems as $item): ?>
        <div class="order-item">
            <span class="name"><?php echo htmlspecialchars($item['name']); ?></span>
            <span class="qty">× <?php echo $item['qty']; ?></span>
            <span class="price">₹<?php echo number_format($item['subtotal'], 2); ?></span>
        </div>
        <?php endforeach; ?>

        <div class="totals">
            <div class="total-row">
                <span>Subtotal</span>
                <span>₹<?php echo number_format($total, 2); ?></span>
            </div>
            <div class="total-row">
                <span>Platform Fee</span>
                <span style="color:#16a34a;font-weight:600;">FREE</span>
            </div>
            <div class="total-row">
                <span>Total Payable</span>
                <span>₹<?php echo number_format($total, 2); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- ─── PAYMENT METHOD CARD ──────────────────────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <div class="icon" style="background:#f0fdf4;color:#16a34a;"><i class="fa-solid fa-shield-halved"></i></div>
        Payment Method
    </div>
    <div class="card-body">
        <div class="upi-badge">
            <div class="upi-logo">📱</div>
            <div class="upi-text">
                <strong>UroPay Secure UPI</strong>
                <span>Instant bank transfer · 256-bit encrypted</span>
            </div>
        </div>
    </div>
</div>

<!-- ─── PAY BUTTON ───────────────────────────────────────────────────────── -->
<div style="max-width:640px;margin:0 auto;">
    <form action="create_order.php" method="POST" id="checkoutForm">
        <input type="hidden" name="amount" value="<?php echo $total; ?>">
        <button type="button" class="pay-btn" id="payNowBtn" onclick="openPaymentModal()">
            <i class="fa-solid fa-lock lock-icon"></i>
            Pay ₹<?php echo number_format($total, 2); ?> Securely
            <i class="fa-solid fa-arrow-right" style="margin-left:auto;font-size:13px;opacity:0.7;"></i>
        </button>
    </form>
    <p class="secure-note">
        <i class="fa-solid fa-shield-check" style="color:#16a34a;"></i>
        Secured by UroPay · No card details stored
    </p>
</div>


<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- PAYMENT MODAL -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div id="paymentModal" role="dialog" aria-modal="true" aria-labelledby="paymentModalStatus">

    <div class="modal-card">

        <!-- Header with amount -->
        <div class="modal-header">
            <div class="brand">🍴 College Canteen</div>
            <div class="amount-display">
                <sup>₹</sup><?php echo number_format($total, 2); ?>
            </div>
        </div>

        <!-- Body -->
        <div class="modal-body">

            <!-- Spinner (default state) -->
            <div class="spinner-wrap" id="spinnerWrap">
                <div class="ring-outer"></div>
                <div class="ring-inner"></div>
                <div class="ring-dot">🔒</div>
            </div>

            <!-- Success (hidden until paid) -->
            <div class="success-wrap" id="successWrap">
                <div class="success-circle">
                    <i class="fa-solid fa-check"></i>
                </div>
                <span class="confetti-dot"></span>
                <span class="confetti-dot"></span>
                <span class="confetti-dot"></span>
                <span class="confetti-dot"></span>
                <span class="confetti-dot"></span>
                <span class="confetti-dot"></span>
            </div>

            <div id="paymentModalStatus">Securing your payment details...</div>
            <div id="paymentModalSub">
                Please complete the UPI payment in your banking app.<br>
                <strong>Do not close this window.</strong>
            </div>

            <!-- Progress bar -->
            <div class="modal-progress">
                <div class="modal-progress-bar" id="modalProgressBar"></div>
            </div>

            <!-- Manual verify button -->
            <button class="modal-verify-btn" id="modalVerifyBtn" onclick="manualVerify()">
                <i class="fa-solid fa-bolt"></i> Already Paid? Verify Now
            </button>
        </div>

        <!-- Footer security strip -->
        <div class="modal-footer">
            <i class="fa-solid fa-lock"></i>
            256-bit SSL Encrypted · UroPay Certified · Powered by NPCI
        </div>
    </div>

</div>


<script>
// ─────────────────────────────────────────────────────────────────────────────
// STATE
// ─────────────────────────────────────────────────────────────────────────────
let paymentOrderId   = null;
let paymentInterval  = null;
let safetyTimeout    = null;
let isVerified       = false;
let isSubmitting     = false;

const PAID_STATUSES  = [
    "COMPLETED","SUCCESS","SUCCESSFUL","PAID",
    "PAYMENT_SUCCESS","PAYMENT_COMPLETED","CAPTURED","SETTLED","APPROVED"
];
const FAIL_STATUSES  = [
    "CANCELLED","CANCELED","FAILED","FAILURE","PAYMENT_FAILED","REJECTED"
];

function openPaymentModal() {
    if (isSubmitting) return;
    isSubmitting = true;

    const btn = document.getElementById("payNowBtn");
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating UPI QR Code...';
    btn.disabled  = true;

    // Submit checkoutForm directly to create_order.php
    document.getElementById("checkoutForm").submit();
}

// ─────────────────────────────────────────────────────────────────────────────
// startPaymentTracking(orderId)
// Call this the moment the user clicks "Pay Now" or finishes scanning UPI QR.
// ─────────────────────────────────────────────────────────────────────────────
function startPaymentTracking(orderId) {
    console.log("📡 Tracking payment for Order #" + orderId);
    paymentOrderId = orderId;

    // Update modal sub-text
    setModalText(
        "Securing your payment details...",
        "Please complete the UPI payment in your banking app.<br><strong>Do not close this window.</strong>"
    );

    // Clear any existing interval
    if (paymentInterval) clearInterval(paymentInterval);
    if (safetyTimeout)   clearTimeout(safetyTimeout);

    // Start high-speed polling every 1.5 seconds
    paymentInterval = setInterval(async () => {
        try {
            const response = await fetch(
                "check_uropay_status.php?order_id=" + encodeURIComponent(orderId) + "&t=" + Date.now(),
                { cache: "no-store" }
            );
            const data = await response.json();
            const statusVal = ((data.status || data.uropay_status || "")).toString().trim().toUpperCase();

            if (data.success && PAID_STATUSES.includes(statusVal)) {
                // ─── PAYMENT CONFIRMED ───
                onPaymentSuccess(orderId);
            } else if (data.success && FAIL_STATUSES.includes(statusVal)) {
                // ─── PAYMENT FAILED ───
                onPaymentFailed();
            } else {
                console.log("⏳ Still pending... re-checking soon.");
            }
        } catch (error) {
            console.error("❌ Status check failed:", error);
        }
    }, 1500); // 1.5 seconds → instant 2-3s detection after bank confirms

    // Safety timeout: stop polling after 4 minutes if user abandons checkout
    safetyTimeout = setTimeout(() => {
        clearInterval(paymentInterval);
        console.warn("⏰ Payment tracking stopped after 4-minute safety timeout.");
        setModalText(
            "Payment window expired",
            "If you have already paid, <a href='my_orders.php' style='color:#4f46e5;'>check your orders</a>."
        );
    }, 240000);
}

// ─────────────────────────────────────────────────────────────────────────────
// ON PAYMENT SUCCESS: animate checkmark → close modal → redirect
// ─────────────────────────────────────────────────────────────────────────────
function onPaymentSuccess(orderId) {
    if (isVerified) return;
    isVerified = true;

    // Stop all polling
    clearInterval(paymentInterval);
    clearTimeout(safetyTimeout);

    // 1. Clear active food cart items from localStorage & sessionStorage
    try {
        ['cart','canteen_cart','food_cart','shopping_cart'].forEach(k => {
            localStorage.removeItem(k);
            sessionStorage.removeItem(k);
        });
    } catch(e) {}

    // 2. Show success animation checkmark
    document.getElementById("spinnerWrap").style.display = "none";
    document.getElementById("successWrap").style.display = "block";

    // 3. Update progress bar to complete (green)
    const bar = document.getElementById("modalProgressBar");
    if (bar) bar.className = "modal-progress-bar complete";

    // 4. Update text
    setModalText("Payment Confirmed! 🎉", "Your order has been received.<br>Redirecting to your order confirmation...");

    // 5. Disable verify button
    const vBtn = document.getElementById("modalVerifyBtn");
    if (vBtn) {
        vBtn.disabled = true;
        vBtn.innerHTML = "<i class='fa-solid fa-check-double'></i> Confirmed by Bank!";
    }

    // 6. Auto-close the modal frame after 500ms → redirect to success page
    setTimeout(() => {
        document.getElementById("paymentModal").classList.remove("active");
        document.body.style.overflow = "";

        // Redirect to order_success / payment_success
        const successUrl = orderId
            ? "payment_success.php?order_id=" + encodeURIComponent(orderId)
            : "payment_success.php";
        window.location.href = successUrl;
    }, 500);
}

// ─────────────────────────────────────────────────────────────────────────────
// ON PAYMENT FAILED
// ─────────────────────────────────────────────────────────────────────────────
function onPaymentFailed() {
    if (isVerified) return;
    isVerified = true;

    clearInterval(paymentInterval);
    clearTimeout(safetyTimeout);

    // Shake animation on modal card
    const card = document.querySelector(".modal-card");
    if (card) { card.style.animation = "shake 0.4s ease"; }

    setModalText("Payment Declined", "The transaction was cancelled or rejected.<br>Please try again.");

    const vBtn = document.getElementById("modalVerifyBtn");
    if (vBtn) {
        vBtn.innerHTML = "<i class='fa-solid fa-rotate-right'></i> Try Again";
        vBtn.disabled  = false;
        vBtn.onclick   = () => { closeModal(); location.reload(); };
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// MANUAL VERIFY BUTTON
// ─────────────────────────────────────────────────────────────────────────────
async function manualVerify() {
    const btn = document.getElementById("modalVerifyBtn");
    if (btn) { btn.disabled = true; btn.innerHTML = "<i class='fa-solid fa-spinner fa-spin'></i> Checking bank..."; }

    setModalText("Querying bank in real-time...", "Connecting to payment gateway...");

    try {
        const res  = await fetch(
            "check_uropay_status.php?order_id=" + encodeURIComponent(paymentOrderId) + "&t=" + Date.now(),
            { cache: "no-store" }
        );
        const data = await res.json();
        const statusVal = ((data.status || data.uropay_status || "")).toString().trim().toUpperCase();

        if (data.success && PAID_STATUSES.includes(statusVal)) {
            onPaymentSuccess(paymentOrderId);
        } else {
            setModalText("Securing your payment details...", "Payment not yet detected by bank.<br>Please wait and try again in 10 seconds.");
            if (btn) { btn.disabled = false; btn.innerHTML = "<i class='fa-solid fa-bolt'></i> Already Paid? Verify Now"; }
        }
    } catch(e) {
        setModalText("Securing your payment details...", "Network error. Retrying automatically...");
        if (btn) { btn.disabled = false; btn.innerHTML = "<i class='fa-solid fa-bolt'></i> Already Paid? Verify Now"; }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────────────────────────────────────
function setModalText(title, sub) {
    const s = document.getElementById("paymentModalStatus");
    const b = document.getElementById("paymentModalSub");
    if (s) s.textContent  = title;
    if (b) b.innerHTML    = sub;
}

function closeModal() {
    document.getElementById("paymentModal").classList.remove("active");
    document.body.style.overflow = "";
    clearInterval(paymentInterval);
    clearTimeout(safetyTimeout);
}

// Close on backdrop click (only if not yet verified)
document.getElementById("paymentModal").addEventListener("click", function(e) {
    if (e.target === this && !isVerified) {
        if (confirm("Cancel payment? Your order has not been confirmed yet.")) {
            closeModal();
        }
    }
});

// Shake keyframe
const style = document.createElement("style");
style.textContent = `@keyframes shake {
    0%,100%{transform:translateX(0)} 20%{transform:translateX(-8px)} 40%{transform:translateX(8px)} 60%{transform:translateX(-6px)} 80%{transform:translateX(6px)}
}`;
document.head.appendChild(style);
</script>

</body>
</html>