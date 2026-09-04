<?php

session_start();

/* =========================================================
   ADMIN LOGIN
   ========================================================= */

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

include("../php/db.php");

/* =========================================================
   LIVE ORDER CHECK ENDPOINT (AJAX)
   ========================================================= */
if (isset($_GET['check_new'])) {
    header('Content-Type: application/json');
    $lastId = (int)($_GET['last_id'] ?? 0);
    $q = mysqli_query($conn, "SELECT id, total_amount, status, food_status FROM orders WHERE id > $lastId AND (status = 'Completed' OR food_status = 'Preparing') ORDER BY id DESC LIMIT 1");
    if ($q && $r = mysqli_fetch_assoc($q)) {
        echo json_encode([
            'has_new' => true,
            'id' => (int)$r['id'],
            'amount' => (float)$r['total_amount'],
            'food_status' => $r['food_status']
        ]);
    } else {
        echo json_encode(['has_new' => false]);
    }
    exit();
}

$maxOrderRes = mysqli_query($conn, "SELECT MAX(id) AS max_id FROM orders");
$maxOrderRow = mysqli_fetch_assoc($maxOrderRes);
$maxOrderId  = (int)($maxOrderRow['max_id'] ?? 0);



/* =========================================================
   UPDATE FOOD & PAYMENT STATUS
   ========================================================= */

if (isset($_POST['update'])) {

    $id = (int)($_POST['id'] ?? 0);

    $foodStatus = trim($_POST['food_status'] ?? 'Preparing');
    $paymentStatus = trim($_POST['payment_status'] ?? '');

    $allowedFoodStatus = [
        'Preparing',
        'Ready',
        'Delivered'
    ];

    $allowedPaymentStatus = [
        'Pending',
        'Completed',
        'Cancelled'
    ];

    if ($id > 0 && in_array($foodStatus, $allowedFoodStatus, true)) {
        if (!empty($paymentStatus) && in_array($paymentStatus, $allowedPaymentStatus, true)) {
            $stmt = mysqli_prepare($conn, "UPDATE orders SET food_status = ?, status = ? WHERE id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ssi", $foodStatus, $paymentStatus, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE orders SET food_status = ? WHERE id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "si", $foodStatus, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
    }

    header("Location: orders.php");
    exit();
}


/* =========================================================
   GET ORDERS
   ========================================================= */

$result = mysqli_query(
    $conn,
    "SELECT
        orders.id,
        users.name AS customer_name,
        orders.total_amount,
        orders.payment_method,
        orders.payment_id,
        orders.status,
        orders.food_status,
        orders.order_date
     FROM orders
     LEFT JOIN users
        ON orders.user_id = users.id
     ORDER BY orders.id DESC"
);

if (!$result) {

    die(
        "Database Error: " .
        htmlspecialchars(mysqli_error($conn))
    );
}

?>


<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Manage Orders - College Canteen Admin</title>


<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f5f6fa;
    color: #222;
}


/* ==============================
   HEADER
   ============================== */

.header {
    width: 100%;
    min-height: 70px;

    background: linear-gradient(
        90deg,
        #6a11cb,
        #2575fc
    );

    display: flex;
    align-items: center;
    justify-content: space-between;

    padding: 0 35px;

    color: white;

    box-shadow:
        0 3px 12px rgba(0, 0, 0, 0.15);
}

.logo {
    font-size: 25px;
    font-weight: bold;
    white-space: nowrap;
}

.nav {
    display: flex;
    align-items: center;
    gap: 28px;
}

.nav a {
    color: white;
    text-decoration: none;
    font-weight: bold;
    font-size: 15px;
}

.nav a:hover {
    color: #ffeb3b;
}


/* ==============================
   MAIN
   ============================== */

.container {
    width: 95%;
    max-width: 1400px;

    margin: 35px auto;
}

.page-title {
    font-size: 28px;
    margin-bottom: 25px;
    color: #222;
}


/* ==============================
   TABLE
   ============================== */

.table-container {
    width: 100%;
    overflow-x: auto;

    background: white;

    border-radius: 15px;

    box-shadow:
        0 5px 20px rgba(0, 0, 0, 0.10);
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1150px;
}

thead {
    background: linear-gradient(
        90deg,
        #ff4938,
        #df2370
    );

    color: white;
}

th {
    padding: 17px 14px;
    text-align: center;
    font-size: 15px;
}

td {
    padding: 15px 14px;
    text-align: center;
    border-bottom: 1px solid #eeeeee;
    font-size: 14px;
}

tbody tr:hover {
    background: #f8f9ff;
}


/* ==============================
   PAYMENT STATUS
   ============================== */

.status {
    display: inline-block;

    min-width: 105px;

    padding: 8px 14px;

    border-radius: 20px;

    font-weight: bold;

    text-transform: capitalize;
}

.status-completed {
    background: #d4edda;
    color: #16803c;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-cancelled {
    background: #f8d7da;
    color: #b02a37;
}

.status-failed {
    background: #f8d7da;
    color: #b02a37;
}

.status-processing {
    background: #cff4fc;
    color: #055160;
}


/* ==============================
   FOOD STATUS
   ============================== */

.food-status {
    display: inline-block;

    min-width: 100px;

    padding: 7px 12px;

    border-radius: 20px;

    font-weight: bold;
}

.food-preparing {
    background: #fff3cd;
    color: #856404;
}

.food-ready {
    background: #d4edda;
    color: #16803c;
}

.food-delivered {
    background: #cfe2ff;
    color: #084298;
}


/* ==============================
   UPDATE FORM
   ============================== */

.update-form {
    display: flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    flex-wrap: wrap;
}

.update-form select {
    padding: 8px 10px;

    border: 1px solid #ccc;

    border-radius: 6px;

    background: white;

    font-size: 13px;

    outline: none;

    cursor: pointer;
}

.update-form select:focus {
    border-color: #6a11cb;
}

.update-btn {
    padding: 8px 12px;

    border: none;

    border-radius: 6px;

    background: linear-gradient(
        90deg,
        #6a11cb,
        #2575fc
    );

    color: white;

    font-weight: bold;

    cursor: pointer;

    transition: .3s;
}

.update-btn:hover {
    transform: translateY(-2px);

    box-shadow:
        0 4px 10px rgba(0,0,0,.2);
}


/* ==============================
   EMPTY
   ============================== */

.empty {
    text-align: center;

    padding: 50px;

    color: #777;

    font-size: 18px;
}


/* ==============================
   RESPONSIVE
   ============================== */

@media (max-width: 900px) {

    .header {
        flex-direction: column;

        gap: 15px;

        padding: 20px;
    }

    .nav {
        flex-wrap: wrap;

        justify-content: center;

        gap: 15px;
    }

    .container {
        width: 94%;
    }

}

</style>

</head>


<body>


<!-- ==============================
     HEADER
     ============================== -->

<header class="header">

    <div class="logo">
        🍴 College Canteen Admin
    </div>

    <nav class="nav">

        <a href="dashboard.php">
            Dashboard
        </a>

        <a href="products.php">
            Products
        </a>

        <a href="customers.php">
            Customers
        </a>

        <a href="orders.php">
            Orders
        </a>

        <a href="missing_callback.php" style="color:#38bdf8;font-weight:700;">
            ⚡ Missing Callback
        </a>

        <a href="reports.php">
            Reports
        </a>

        <a href="messages.php">
            Messages
        </a>

        <a href="logout.php">
            Logout
        </a>

    </nav>

</header>


<main class="container">

    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:25px;">
        <h1 class="page-title" style="margin-bottom:0;">
            Order Management
        </h1>

        <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
            <!-- QUICK PAYMENT REF ID VERIFIER -->
            <form action="../verify_ref.php" method="GET" target="_blank" style="display:flex; gap:8px; margin:0;">
                <input 
                    type="text" 
                    name="ref" 
                    placeholder="Enter Payment Ref / Order ID..." 
                    style="padding:10px 14px; border:2px solid #6a11cb; border-radius:8px; font-size:14px; width:260px; outline:none;"
                    required
                >
                <button 
                    type="submit" 
                    style="background:linear-gradient(90deg,#6a11cb,#2575fc); color:white; border:none; padding:10px 18px; border-radius:8px; font-weight:600; cursor:pointer; font-size:14px; display:flex; align-items:center; gap:6px; white-space:nowrap;"
                >
                    <i class="fa-solid fa-bolt"></i> Check Gateway
                </button>
            </form>

            <a href="missing_callback.php" style="background:#0284c7; color:white; padding:10px 16px; border-radius:8px; font-weight:600; font-size:14px; text-decoration:none; display:flex; align-items:center; gap:6px; white-space:nowrap;">
                <i class="fa-solid fa-clock-rotate-left"></i> Missing Callbacks
            </a>

            <button 
                id="btnSoundToggle" 
                type="button" 
                onclick="toggleSoundAlert()" 
                style="background:#10b981; color:white; border:none; padding:10px 16px; border-radius:8px; font-weight:600; font-size:14px; cursor:pointer; display:flex; align-items:center; gap:6px; white-space:nowrap;"
                title="Toggle kitchen bell chime alert"
            >
                <i class="fa-solid fa-bell"></i> <span id="soundStatusText">Kitchen Bell: ON</span>
            </button>
        </div>
    </div>

    <div class="table-container">

        <?php if (mysqli_num_rows($result) > 0): ?>

            <table>

                <thead>

                    <tr>

                        <th>
                            Order ID
                        </th>

                        <th>
                            Customer
                        </th>

                        <th>
                            Items Ordered
                        </th>

                        <th>
                            Total Amount
                        </th>

                        <th>
                            Payment
                        </th>

                        <th>
                            Payment ID
                        </th>

                        <th>
                            Payment Status
                        </th>

                        <th>
                            Food Status
                        </th>

                        <th>
                            Date
                        </th>

                        <th>
                            Update
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php while ($order = mysqli_fetch_assoc($result)): ?>

                    <?php

                    /* ==============================
                       PAYMENT STATUS
                       ============================== */

                    $status = trim(
                        $order['status'] ?? 'Pending'
                    );

                    $statusClass = strtolower(
                        preg_replace(
                            '/[^a-zA-Z0-9]+/',
                            '-',
                            $status
                        )
                    );


                    /* ==============================
                       FOOD STATUS
                       ============================== */

                    $foodStatus = trim(
                        $order['food_status']
                        ?? 'Preparing'
                    );

                    $foodStatusClass = strtolower(
                        preg_replace(
                            '/[^a-zA-Z0-9]+/',
                            '-',
                            $foodStatus
                        )
                    );

                    /* ==============================
                       ITEMS ORDERED
                       ============================== */
                    $itemsList = [];
                    $itQ = mysqli_query($conn, "SELECT product_name, quantity FROM order_items WHERE order_id = " . (int)$order['id']);
                    if ($itQ) {
                        while ($it = mysqli_fetch_assoc($itQ)) {
                            $itemsList[] = htmlspecialchars($it['quantity'] . 'x ' . $it['product_name']);
                        }
                    }
                    $itemsDisplay = !empty($itemsList)
                        ? implode("<br>", $itemsList)
                        : '<span style="color:#94a3b8; font-size:12px;">Canteen Order</span>';

                    ?>


                    <tr>


                        <!-- ORDER ID -->

                        <td>

                            <strong>#<?php echo (int)$order['id']; ?></strong>
                            <a href="../invoice.php?order_id=<?php echo (int)$order['id']; ?>" target="_blank" style="display:block; font-size:11px; color:#2563eb; text-decoration:none; margin-top:3px;">
                                📄 Invoice
                            </a>

                        </td>


                        <!-- CUSTOMER -->

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $order['customer_name']
                                ?? 'Unknown'
                            );

                            ?>

                        </td>


                        <!-- ITEMS ORDERED -->

                        <td style="font-size:13px; line-height:1.4;">

                            <?php echo $itemsDisplay; ?>

                        </td>


                        <!-- TOTAL -->

                        <td>

                            <strong>₹<?php

                            echo number_format(
                                (float)$order['total_amount'],
                                2
                            );

                            ?></strong>

                        </td>


                        <!-- PAYMENT METHOD -->

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $order['payment_method']
                                ?? '-'
                            );

                            ?>

                        </td>


                        <!-- PAYMENT ID / REF CHECK -->

                        <td style="white-space:nowrap;">

                            <?php
                            $paymentId = trim($order['payment_id'] ?? '');
                            if (!empty($paymentId) && $paymentId !== '-'):
                            ?>

                                <span style="display:block; font-size:11px; color:#94a3b8; margin-bottom:4px;">
                                    <?php echo htmlspecialchars($paymentId); ?>
                                </span>

                                <a
                                    href="../verify_ref.php?ref=<?php echo urlencode($paymentId); ?>"
                                    target="_blank"
                                    style="
                                        display:inline-flex;
                                        align-items:center;
                                        gap:5px;
                                        background:linear-gradient(90deg,#6a11cb,#2575fc);
                                        color:white;
                                        padding:5px 12px;
                                        border-radius:20px;
                                        font-size:12px;
                                        font-weight:600;
                                        text-decoration:none;
                                    "
                                >
                                    ⚡ Verify Ref
                                </a>

                            <?php else: ?>

                                <span style="color:#ccc;">—</span>

                            <?php endif; ?>

                        </td>


                        <!-- PAYMENT STATUS -->

                        <td>

                            <span
                                class="status status-<?php

                                echo htmlspecialchars(
                                    $statusClass
                                );

                                ?>"
                            >

                                <?php

                                echo htmlspecialchars(
                                    $status
                                );

                                ?>

                            </span>

                        </td>


                        <!-- FOOD STATUS -->

                        <td>

                            <span
                                class="food-status food-<?php

                                echo htmlspecialchars(
                                    $foodStatusClass
                                );

                                ?>"
                            >

                                <?php

                                echo htmlspecialchars(
                                    $foodStatus
                                );

                                ?>

                            </span>

                        </td>


                        <!-- DATE -->

                        <td>

                            <?php

                            if (
                                !empty(
                                    $order['order_date']
                                )
                            ) {

                                echo htmlspecialchars(
                                    date(
                                        "Y-m-d H:i:s",
                                        strtotime(
                                            $order['order_date']
                                        )
                                    )
                                );

                            } else {

                                echo "-";

                            }

                            ?>

                        </td>


                        <!-- UPDATE FOOD STATUS -->

                        <td>

                            <form
                                method="POST"
                                class="update-form"
                            >

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?php

                                    echo (int)$order['id'];

                                    ?>"
                                >


                                <select
                                    name="food_status"
                                    style="padding:5px 8px; border-radius:6px; font-size:12px; margin-bottom:4px; width:100%; border:1px solid #cbd5e1;"
                                    title="Kitchen Status"
                                >

                                    <option
                                        value="Preparing"
                                        <?php echo $foodStatus === 'Preparing' ? 'selected' : ''; ?>
                                    >
                                        🍳 Preparing
                                    </option>

                                    <option
                                        value="Ready"
                                        <?php echo $foodStatus === 'Ready' ? 'selected' : ''; ?>
                                    >
                                        🔔 Ready
                                    </option>

                                    <option
                                        value="Delivered"
                                        <?php echo $foodStatus === 'Delivered' ? 'selected' : ''; ?>
                                    >
                                        ✅ Delivered
                                    </option>

                                </select>

                                <select
                                    name="payment_status"
                                    style="padding:5px 8px; border-radius:6px; font-size:12px; margin-bottom:6px; width:100%; border:1px solid #cbd5e1;"
                                    title="Payment Status"
                                >
                                    <option value="Pending" <?php echo strtolower($status) === 'pending' ? 'selected' : ''; ?>>
                                        ⏳ Pending
                                    </option>
                                    <option value="Completed" <?php echo (strtolower($status) === 'completed' || strtolower($status) === 'paid') ? 'selected' : ''; ?>>
                                        💳 Completed
                                    </option>
                                    <option value="Cancelled" <?php echo (strtolower($status) === 'cancelled' || strtolower($status) === 'canceled' || strtolower($status) === 'failed') ? 'selected' : ''; ?>>
                                        ❌ Cancelled
                                    </option>
                                </select>

                                <button
                                    type="submit"
                                    name="update"
                                    class="update-btn"
                                    style="width:100%;"
                                >
                                    Save
                                </button>

                            </form>

                        </td>


                    </tr>

                <?php endwhile; ?>

                </tbody>

            </table>

        <?php else: ?>

            <div class="empty">
                No orders found.
            </div>

        <?php endif; ?>

    </div>

</main>

<!-- FLOATING NEW ORDER NOTIFICATION TOAST -->
<div id="newOrderToast" style="
    display:none; 
    position:fixed; 
    bottom:30px; 
    right:30px; 
    background:#16a34a; 
    color:white; 
    padding:16px 24px; 
    border-radius:12px; 
    box-shadow:0 10px 25px rgba(0,0,0,0.25); 
    font-family:sans-serif; 
    z-index:9999;
    animation:slideUp 0.3s ease;
">
    <div style="display:flex; align-items:center; gap:12px;">
        <span style="font-size:26px;">🔔</span>
        <div>
            <div style="font-weight:700; font-size:16px;" id="toastTitle">New Order Received!</div>
            <div style="font-size:13px; opacity:0.9;" id="toastDesc">Order placed just now.</div>
        </div>
        <button onclick="window.location.reload()" style="background:white; color:#16a34a; border:none; padding:8px 14px; border-radius:6px; font-weight:700; cursor:pointer; margin-left:10px;">Refresh</button>
    </div>
</div>

<style>
@keyframes slideUp {
    from { transform:translateY(50px); opacity:0; }
    to { transform:translateY(0); opacity:1; }
}
</style>

<script>
let soundEnabled = true;
let latestOrderId = <?php echo (int)$maxOrderId; ?>;

function toggleSoundAlert() {
    soundEnabled = !soundEnabled;
    const btn = document.getElementById("btnSoundToggle");
    const txt = document.getElementById("soundStatusText");
    if (soundEnabled) {
        btn.style.background = "#10b981";
        txt.innerText = "Kitchen Bell: ON";
        playKitchenChime();
    } else {
        btn.style.background = "#64748b";
        txt.innerText = "Kitchen Bell: OFF";
    }
}

function playKitchenChime() {
    if (!soundEnabled) return;
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        
        // Ding
        const osc1 = ctx.createOscillator();
        const gain1 = ctx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(659.25, ctx.currentTime);
        gain1.gain.setValueAtTime(0.35, ctx.currentTime);
        gain1.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.7);
        osc1.connect(gain1);
        gain1.connect(ctx.destination);
        osc1.start();
        osc1.stop(ctx.currentTime + 0.7);

        // Dong
        setTimeout(() => {
            const osc2 = ctx.createOscillator();
            const gain2 = ctx.createGain();
            osc2.type = 'sine';
            osc2.frequency.setValueAtTime(880, ctx.currentTime);
            gain2.gain.setValueAtTime(0.4, ctx.currentTime);
            gain2.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 1.2);
            osc2.connect(gain2);
            gain2.connect(ctx.destination);
            osc2.start();
            osc2.stop(ctx.currentTime + 1.2);
        }, 180);
    } catch(e) {
        console.warn("Chime notice:", e);
    }
}

// Poll every 5 seconds for new orders
setInterval(async () => {
    try {
        const res = await fetch("orders.php?check_new=1&last_id=" + latestOrderId + "&t=" + Date.now());
        const data = await res.json();
        if (data && data.has_new && data.id > latestOrderId) {
            latestOrderId = data.id;
            playKitchenChime();
            
            const toast = document.getElementById("newOrderToast");
            const title = document.getElementById("toastTitle");
            const desc = document.getElementById("toastDesc");
            if (toast && title && desc) {
                title.innerText = "🔔 New Order #" + data.id + " (" + (data.food_status || "Preparing") + ")";
                desc.innerText = "Amount: ₹" + (data.amount || 0).toFixed(2) + " • Refreshing list...";
                toast.style.display = "block";
            }
            
            setTimeout(() => {
                window.location.reload();
            }, 2500);
        }
    } catch(err) {
        // silent retry
    }
}, 5000);
</script>

</body>

</html>