<?php
session_start();
include("php/db.php");

// Resolve order ID from GET or session
$orderId = trim($_GET['order_id'] ?? ($_SESSION['uropay_order_id'] ?? ($_SESSION['local_order_id'] ?? '16')));
$localOrderId = (int)($_GET['local_id'] ?? ($_SESSION['local_order_id'] ?? 16));
$totalAmount = 21.00;

if ($localOrderId > 0 && isset($conn)) {
    $stmt = mysqli_prepare($conn, "SELECT total_amount, payment_id FROM orders WHERE id = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $localOrderId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($r = mysqli_fetch_assoc($res)) {
            $totalAmount = (float)$r['total_amount'];
            if (!empty($r['payment_id'])) {
                $orderId = $r['payment_id'];
            }
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Checkout | Processing Payment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <style>
        :root {
            --primary: #2ecc71;
            --dark: #2c3e50;
            --bg: #f8f9fa;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg);
            color: var(--dark);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        .checkout-container {
            background: #ffffff;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            text-align: center;
            max-width: 420px;
            width: 100%;
        }
        .checkout-container h2 {
            margin-top: 0;
            margin-bottom: 12px;
            font-size: 24px;
            color: #1e293b;
        }
        .checkout-container p {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 24px;
            line-height: 1.5;
        }
        .order-badge {
            background: #f1f5f9;
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 15px;
        }
        .order-badge strong {
            color: #0f172a;
        }
        .btn-pay {
            background: linear-gradient(135deg, #2c3e50, #1a252f);
            color: white;
            border: none;
            padding: 16px 28px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(44, 62, 80, 0.25);
        }
        .btn-pay:hover {
            opacity: 0.95;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(44, 62, 80, 0.35);
        }
        .btn-pay:active {
            transform: translateY(0);
        }
        
        /* Modal Overlay Structural Rules */
        .payment-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(44, 62, 80, 0.92);
            backdrop-filter: blur(6px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        .payment-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }
        .modal-card {
            background: #ffffff;
            padding: 35px 30px;
            border-radius: 20px;
            max-width: 340px;
            width: 90%;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
            animation: popIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes popIn {
            from { transform: scale(0.85); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        
        /* Circular CSS Spinner */
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #e2e8f0;
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            margin: 0 auto 20px;
            animation: spin 0.9s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .status-txt {
            font-size: 17px;
            font-weight: 700;
            margin: 12px 0 8px;
            color: #0f172a;
        }
        .sub-txt {
            font-size: 13px;
            color: #64748b;
            line-height: 1.5;
        }
        .success-anim {
            font-size: 48px;
            color: #2ecc71;
            margin-bottom: 15px;
            animation: successPop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes successPop {
            from { transform: scale(0); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body>

<div class="checkout-container">
    <h2>Food Order Checkout</h2>
    <p>Complete your payment via UroPay Gateway below.</p>
    
    <div class="order-badge">
        <span>Order Total</span>
        <strong>₹<?php echo number_format($totalAmount, 2); ?></strong>
    </div>

    <!-- Simulating dynamic order data passing -->
    <button class="btn-pay" onclick="openPaymentGateway('<?php echo htmlspecialchars($orderId, ENT_QUOTES); ?>')">
        <i class="fa-solid fa-lock"></i> Simulate UPI Payment
    </button>
</div>

<!-- Real-time Verification Overlay Frame -->
<div id="paymentModal" class="payment-overlay">
    <div class="modal-card">
        <div id="modalVisual" class="spinner"></div>
        <div id="statusMessage" class="status-txt">Securing your payment details...</div>
        <div class="sub-txt">Please do not close this window or click back.</div>
    </div>
</div>

<script>
let verificationLoop = null;
let securityTimeout = null;

function openPaymentGateway(orderId) {
    // 1. Activate processing overlay window instantly
    document.getElementById("paymentModal").classList.add("active");
    
    // Reset visual in case of re-run
    const modalVisual = document.getElementById("modalVisual");
    modalVisual.className = "spinner";
    modalVisual.innerHTML = "";
    document.getElementById("statusMessage").innerText = "Securing your payment details...";

    // 2. Clear any lingering interface processes
    if(verificationLoop) clearInterval(verificationLoop);
    if(securityTimeout) clearTimeout(securityTimeout);

    console.log(`Polling protocol activated for order parameters: #${orderId}`);
    
    // 3. Low-Latency 1.2 Second High Frequency Checking Engine
    verificationLoop = setInterval(() => {
        fetch(`check_payment_status.php?order_id=${encodeURIComponent(orderId)}&t=${Date.now()}`, {
            method: 'GET',
            headers: { 'Cache-Control': 'no-cache' }
        })
        .then(response => response.json())
        .then(data => {
            const status = (data && (data.status || data.uropay_status || '')).toString().trim();
            const isCompleted = (status === 'Completed' || status === 'PAID' || status === 'SUCCESS' || status === 'COMPLETED');

            if (isCompleted) {
                clearInterval(verificationLoop);
                clearTimeout(securityTimeout);

                // Update UI to checkmark
                modalVisual.className = "success-anim";
                modalVisual.innerHTML = "<i class='fa-solid fa-circle-check'></i>";

                document.getElementById("statusMessage").innerText = "Payment Successful!";

                // Clear cart from storage
                try {
                    localStorage.removeItem('cart');
                    localStorage.removeItem('canteen_cart');
                    localStorage.removeItem('food_cart');
                    sessionStorage.removeItem('cart');
                } catch(e) {
                    console.warn("Storage clear error:", e);
                }

                // Auto-close modal after 500ms and redirect
                setTimeout(() => {
                    document.getElementById("paymentModal").classList.remove("active");
                    const targetId = data.local_order_id || orderId;
                    window.location.href = `order_success.php?id=${encodeURIComponent(targetId)}`;
                }, 500);
            } else {
                console.log("Status pending... checking again in 1.2s");
            }
        })
        .catch(err => {
            console.error("Verification network check error:", err);
        });
    }, 1200);

    // Safety timeout: 4 minutes
    securityTimeout = setTimeout(() => {
        clearInterval(verificationLoop);
        document.getElementById("statusMessage").innerText = "Payment window expired.";
    }, 240000);
}
</script>

</body>
</html>
