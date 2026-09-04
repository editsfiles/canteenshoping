<?php

session_start();

include("php/db.php");

/* =========================================================
   CHECK LOGIN
   ========================================================= */

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];


/* =========================================================
   CHECK LOCAL ORDER ID
   ========================================================= */

$localOrderId = (int)($_GET['order_id'] ?? ($_SESSION['local_order_id'] ?? 0));
$uroPayOrderId = trim($_GET['uropay_id'] ?? ($_SESSION['uropay_order_id'] ?? ''));

if ($localOrderId <= 0) {
    header("Location: menu.php");
    exit();
}

$_SESSION['local_order_id'] = $localOrderId;


/* =========================================================
   GET LOCAL ORDER
   ========================================================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT id, user_id, status, payment_id, bank_utr, total_amount, payment_method
     FROM orders
     WHERE id = ?
     LIMIT 1"
);

if (!$stmt) {
    die("Database Prepare Error: " . htmlspecialchars(mysqli_error($conn)));
}

mysqli_stmt_bind_param($stmt, "i", $localOrderId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);


/* =========================================================
   ORDER NOT FOUND
   ========================================================= */

if (!$order) {
    header("Location: menu.php");
    exit();
}


/* =========================================================
   SECURITY CHECK
   ========================================================= */

if ((int)$order['user_id'] !== (int)$_SESSION['user_id']) {
    die("Unauthorized order access.");
}


/* =========================================================
   IF ALREADY PAID - REDIRECT IMMEDIATELY
   ========================================================= */

$dbStatus = strtoupper(trim($order['status'] ?? 'PENDING'));

if (in_array($dbStatus, ["COMPLETED", "SUCCESS", "SUCCESSFUL", "PAID"], true)) {
    header("Location: payment_success.php?order_id=" . $localOrderId);
    exit();
}

if (in_array($dbStatus, ["CANCELLED", "CANCELED", "FAILED"], true)) {
    header("Location: payment_failed.php");
    exit();
}

// Use payment_id as the UroPay order ref for polling
$pollOrderId = !empty($uroPayOrderId) ? $uroPayOrderId : ($order['payment_id'] ?? '');

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checking Payment – Order #<?php echo $localOrderId; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Poppins', Arial, sans-serif;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.box {
    width: 440px;
    max-width: 100%;
    background: #ffffff;
    border-radius: 22px;
    padding: 40px 32px 32px;
    text-align: center;
    box-shadow: 0 25px 60px rgba(0,0,0,0.35);
    animation: slideUp 0.5s ease;
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

.icon-ring {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    animation: pulse 2s infinite;
    font-size: 32px;
    color: white;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(59,130,246,0.5); }
    50% { transform: scale(1.05); box-shadow: 0 0 0 12px rgba(59,130,246,0); }
}

h2 {
    font-size: 22px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 6px;
}

.sub { color: #64748b; font-size: 14px; margin-bottom: 22px; }

.order-info {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px 18px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 14px;
    color: #475569;
}
.order-info strong { color: #0f172a; font-size: 16px; }

/* Spinner */
.spinner-wrap { margin: 18px 0; }
.spinner {
    width: 42px; height: 42px;
    border: 4px solid #e2e8f0;
    border-top-color: #3b82f6;
    border-radius: 50%;
    margin: 0 auto 10px;
    animation: spin 0.9s linear infinite;
}
@keyframes spin { 100% { transform: rotate(360deg); } }

.status-text {
    font-size: 13px;
    color: #64748b;
    min-height: 20px;
    transition: 0.3s;
}

/* Status Box */
.status-box {
    margin: 14px 0;
    padding: 12px 16px;
    border-radius: 10px;
    background: #fef3c7;
    color: #854d0e;
    font-size: 13px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: 0.4s;
}
.status-box.success { background: #dcfce7; color: #15803d; }
.status-box.failed  { background: #fee2e2; color: #b91c1c; }
.status-box.checking { background: #eff6ff; color: #1d4ed8; }

/* Verify Button */
.verify-btn {
    width: 100%;
    padding: 13px;
    margin-top: 6px;
    background: linear-gradient(135deg, #16a34a, #15803d);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: 0.2s;
    box-shadow: 0 4px 14px rgba(22,163,74,0.35);
    text-decoration: none;
}
.verify-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(22,163,74,0.4); }
.verify-btn:disabled { background: #6b7280; cursor: not-allowed; transform: none; box-shadow: none; }

.back-link { display: block; margin-top: 14px; color: #64748b; font-size: 12px; text-decoration: none; }
.back-link:hover { color: #1e293b; }

.countdown {
    font-size: 12px;
    color: #94a3b8;
    margin-top: 12px;
}
</style>
</head>
<body>

<div class="box">

    <div class="icon-ring">
        <i class="fa-solid fa-credit-card"></i>
    </div>

    <h2>Checking Payment Status</h2>
    <p class="sub">We're verifying your UPI transaction...</p>

    <div class="order-info">
        <span>Order ID</span>
        <strong>#<?php echo $localOrderId; ?></strong>
    </div>

    <div class="order-info" style="margin-bottom:0;">
        <span>Amount</span>
        <strong style="color:#16a34a;">₹<?php echo number_format((float)$order['total_amount'], 2); ?></strong>
    </div>

    <div class="spinner-wrap">
        <div class="spinner" id="spinner"></div>
        <div class="status-text" id="statusText">Connecting to bank gateway...</div>
    </div>

    <div class="status-box" id="statusBox">
        <i class="fa-solid fa-clock-rotate-left"></i>
        <span>Waiting for UPI payment confirmation...</span>
    </div>

    <button class="verify-btn" id="btnVerify" onclick="checkPayment(true)">
        <i class="fa-solid fa-bolt"></i> Already Paid? Verify Bank Status
    </button>

    <div class="countdown" id="countdownText">Auto-checking every 2 seconds...</div>

    <a href="menu.php" class="back-link">← Back to Menu</a>
</div>

<script>
const orderId   = "<?php echo htmlspecialchars($pollOrderId, ENT_QUOTES); ?>";
const localOrderId = <?php echo $localOrderId; ?>;

let checking = false;
let pollTimer = null;
let redirected = false;

const PAID_STATUSES = [
    "COMPLETED","SUCCESS","SUCCESSFUL","PAID",
    "PAYMENT_SUCCESS","PAYMENT_COMPLETED","CAPTURED","SETTLED","APPROVED"
];

function clearCartAndRedirect() {
    if (redirected) return;
    redirected = true;

    // Clear active cart items from storage
    try {
        localStorage.removeItem('cart');
        localStorage.removeItem('canteen_cart');
        localStorage.removeItem('food_cart');
        localStorage.removeItem('shopping_cart');
        sessionStorage.removeItem('cart');
    } catch(e) {}

    // Stop polling
    if (pollTimer) clearInterval(pollTimer);

    // Show confirmed
    const box = document.getElementById("statusBox");
    if (box) {
        box.className = "status-box success";
        box.innerHTML = "<i class='fa-solid fa-circle-check'></i> <span>Payment Confirmed! Redirecting...</span>";
    }
    const spinner = document.getElementById("spinner");
    if (spinner) spinner.style.borderTopColor = "#16a34a";

    const btn = document.getElementById("btnVerify");
    if (btn) { btn.disabled = true; btn.innerHTML = "<i class='fa-solid fa-check-double'></i> Confirmed by Bank!"; }

    // Push to success page with order_id
    setTimeout(function() {
        window.location.href = "payment_success.php?order_id=" + localOrderId;
    }, 500);
}

async function checkPayment(isManual = false) {
    if (checking || redirected) return;
    checking = true;

    const statusText = document.getElementById("statusText");
    const statusBox  = document.getElementById("statusBox");

    if (isManual && statusText) {
        statusText.innerText = "Querying bank & database...";
        if (statusBox) { statusBox.className = "status-box checking"; statusBox.innerHTML = "<i class='fa-solid fa-satellite-dish'></i> <span>Checking bank status in real-time...</span>"; }
    }

    try {
        const res = await fetch(
            "check_uropay_status.php?order_id=" + encodeURIComponent(orderId) + "&t=" + Date.now(),
            { method: "GET", cache: "no-store" }
        );

        const data = await res.json();
        const statusVal = ((data.status || data.uropay_status || "")).toString().trim().toUpperCase();

        if (data.success && PAID_STATUSES.includes(statusVal)) {
            clearCartAndRedirect();
            return;
        }

        const failedStatuses = ["CANCELLED","CANCELED","FAILED","FAILURE","PAYMENT_FAILED","REJECTED"];
        if (data.success && failedStatuses.includes(statusVal)) {
            if (pollTimer) clearInterval(pollTimer);
            if (statusBox) { statusBox.className = "status-box failed"; statusBox.innerHTML = "<i class='fa-solid fa-circle-xmark'></i> <span>Payment Cancelled or Rejected.</span>"; }
            setTimeout(() => { window.location.href = "payment_failed.php"; }, 1500);
            return;
        }

        if (isManual && statusText) {
            statusText.innerText = "Bank: Pending — Payment not yet detected. Retrying...";
            if (statusBox) { statusBox.className = "status-box"; statusBox.innerHTML = "<i class='fa-solid fa-clock-rotate-left'></i> <span>Waiting for UPI confirmation from bank...</span>"; }
        }
    } catch(e) {
        console.warn("Check error:", e);
        if (isManual && document.getElementById("statusText")) {
            document.getElementById("statusText").innerText = "Network error. Retrying...";
        }
    } finally {
        checking = false;
    }
}

// Start polling immediately and every 2 seconds
checkPayment(false);
pollTimer = setInterval(function() { checkPayment(false); }, 2000);
</script>

</body>
</html>