<?php

session_start();

include("php/db.php");
include("config_uropay.php");


/* ==============================
   CHECK LOGIN
   ============================== */

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];


/* ==============================
   CHECK CART
   ============================== */

if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {

    die("Your cart is empty.");
}


/* ==============================
   CALCULATE CART TOTAL
   ============================== */

$total = 0.00;
$orderedItemsData = [];

foreach ($_SESSION['cart'] as $id => $qty) {

    $id  = (int)$id;
    $qty = (int)$qty;

    if ($id <= 0 || $qty <= 0) {
        continue;
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT * FROM products WHERE id = ? LIMIT 1"
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result && mysqli_num_rows($result) > 0) {
            $product = mysqli_fetch_assoc($result);
            $productName = !empty($product['product_name']) ? $product['product_name'] : (!empty($product['name']) ? $product['name'] : 'Food Item');
            $price = (float)$product['price'];
            $subtotal = round($price * $qty, 2);
            $total += $subtotal;

            $orderedItemsData[] = [
                'product_id' => $id,
                'product_name' => $productName,
                'price' => $price,
                'quantity' => $qty,
                'subtotal' => $subtotal
            ];
        }
        mysqli_stmt_close($stmt);
    }
}


/* ==============================
   GST
   ============================== */

$gst =
    round(
        $total * 0.05,
        2
    );


/* ==============================
   GRAND TOTAL
   ============================== */

$grandTotal =
    round(
        $total + $gst,
        2
    );


if ($grandTotal <= 0) {

    die("Invalid order amount.");
}


/* ==============================
   CUSTOMER INFORMATION
   ============================== */

$customerName =
    $_SESSION['user_name']
    ?? "Customer";

$customerEmail =
    $_SESSION['user_email']
    ?? "";


/* ==============================
   CREATE UNIQUE MERCHANT ORDER ID
   ============================== */

$merchantOrderId =
    "CANTEEN" .
    date("YmdHis") .
    rand(1000, 9999);


/* ==============================
   UROPAY AMOUNT
   ============================== */

/*
   UroPay expects amount in paise for Indian payments,
   so convert rupees to integer paise.
*/
$amount =
    (int)round($grandTotal * 100);


/* ==============================
   SECRET HASH
   ============================== */

$secretHash =
    hash(
        "sha512",
        UROPAY_SECRET
    );


/* ==============================
   REQUEST DATA
   ============================== */

$data = [

    "amount" =>
        $amount,

    "amountInRupees" =>
        (float)$grandTotal,

    "merchantOrderId" =>
        (string)$merchantOrderId,

    "redirectUrl" =>
        UROPAY_REDIRECT_URL,

    "redirect_url" =>
        UROPAY_REDIRECT_URL,

    "successUrl" =>
        UROPAY_SUCCESS_URL,

    "success_url" =>
        UROPAY_SUCCESS_URL,

    "failureUrl" =>
        UROPAY_FAILURE_URL,

    "failure_url" =>
        UROPAY_FAILURE_URL,

    "webhookUrl" =>
        UROPAY_WEBHOOK_URL,

    "webhook_url" =>
        UROPAY_WEBHOOK_URL,

    "customerName" =>
        (string)$customerName,

    "customerEmail" =>
        (string)$customerEmail,

    "transactionNote" =>
        "College Canteen Order " .
        $merchantOrderId,

    "notes" => [

        "user_id" =>
            (string)$user_id,

        "gst" =>
            (string)$gst,

        "total" =>
            (string)$total
    ]
];


/* ==============================
   JSON
   ============================== */

$json =
    json_encode(
        $data,
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE |
        JSON_PRESERVE_ZERO_FRACTION
    );


if ($json === false) {

    die(
        "JSON Error: " .
        htmlspecialchars(
            json_last_error_msg()
        )
    );
}


/* ==============================
   CURL
   ============================== */

$ch = curl_init();

curl_setopt_array(
    $ch,
    [

        CURLOPT_URL =>
            UROPAY_API_URL .
            "/order/generate",

        CURLOPT_RETURNTRANSFER =>
            true,

        CURLOPT_POST =>
            true,

        CURLOPT_POSTFIELDS =>
            $json,

        CURLOPT_HTTPHEADER => [

            "Content-Type: application/json",

            "Accept: application/json",

            "X-API-KEY: " .
                UROPAY_API_KEY,

            "Authorization: Bearer " .
                $secretHash
        ],

        CURLOPT_CONNECTTIMEOUT =>
            15,

        CURLOPT_TIMEOUT =>
            60
    ]
);


/* ==============================
   EXECUTE
   ============================== */

$response =
    curl_exec($ch);

$httpCode =
    curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

$curlError =
    curl_error($ch);

curl_close($ch);


/* ==============================
   CURL ERROR
   ============================== */

if ($curlError) {

    die(
        "<h2>Connection Error</h2>" .
        "<pre>" .
        htmlspecialchars($curlError) .
        "</pre>"
    );
}


/* ==============================
   DECODE RESPONSE
   ============================== */

$result =
    json_decode(
        $response,
        true
    );


/* ==============================
   API ERROR
   ============================== */

if (
    $httpCode < 200 ||
    $httpCode >= 300 ||
    !is_array($result)
) {

    echo "<h2>UroPay API Error</h2>";

    echo "<h3>HTTP Status: " .
        htmlspecialchars(
            (string)$httpCode
        ) .
        "</h3>";

    echo "<h3>Request Sent</h3>";

    echo "<pre>";
    echo htmlspecialchars($json);
    echo "</pre>";

    echo "<h3>API Response</h3>";

    echo "<pre>";
    echo htmlspecialchars($response);
    echo "</pre>";

    exit();
}


/* ==============================
   CHECK API ERROR RESPONSE
   ============================== */

if (
    isset($result['code']) &&
    (int)$result['code'] !== 200 &&
    !isset($result['data'])
) {

    echo "<h2>UroPay API Error</h2>";

    echo "<pre>";

    echo htmlspecialchars(
        json_encode(
            $result,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_SLASHES
        )
    );

    echo "</pre>";

    exit();
}


/* ==============================
   GET UROPAY DATA
   ============================== */

$uroPayData =
    $result['data']
    ?? [];


$uroPayOrderId =
    $uroPayData['uroPayOrderId']
    ?? $uroPayData['orderId']
    ?? null;


$qrCode =
    $uroPayData['qrCode']
    ?? null;

$upiString =
    $uroPayData['upiString']
    ?? $uroPayData['upi_link']
    ?? $uroPayData['upiLink']
    ?? $uroPayData['deepLink']
    ?? $uroPayData['deeplink']
    ?? null;

$amountInRupees =
    $uroPayData['amountInRupees']
    ?? $grandTotal;

if (
    is_numeric($amountInRupees) &&
    (float)$amountInRupees > 0 &&
    (float)$amountInRupees < $grandTotal &&
    $grandTotal > 0 &&
    ((float)$grandTotal / (float)$amountInRupees) > 50
) {
    $amountInRupees = $grandTotal;
}

$orderStatus =
    $uroPayData['orderStatus']
    ?? "CREATED";


/* ==============================
   CHECK UROPAY ORDER ID
   ============================== */

if (!$uroPayOrderId) {

    echo "<h2>UroPay Error</h2>";

    echo "<p>
        UroPay order ID was not returned.
    </p>";

    echo "<pre>";

    echo htmlspecialchars(
        json_encode(
            $result,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_SLASHES
        )
    );

    echo "</pre>";

    exit();
}


/* ==============================
   SAVE PAYMENT INFORMATION
   ============================== */

$_SESSION['merchant_order_id'] =
    $merchantOrderId;

$_SESSION['uropay_order_id'] =
    $uroPayOrderId;

$_SESSION['order_amount'] =
    $grandTotal;

$_SESSION['uropay_qr'] =
    $qrCode;

// Disable direct UPI deeplink flow for mobile phones.
// Many UPI apps reject P2P intent links with the error:
// "Intent Transaction for P2P not allowed".
$_SESSION['uropay_upi'] =
    "";

$_SESSION['uropay_amount_in_rupees'] =
    $grandTotal;


/* ==============================
   SAVE COMPLETE UROPAY SESSION
   ============================== */

$_SESSION['uropay_order'] = [

    "uroPayOrderId" =>
        $uroPayOrderId,

    "merchantOrderId" =>
        $merchantOrderId,

    "amount" =>
        $grandTotal,

    "amountInRupees" =>
        $amountInRupees,

    "orderStatus" =>
        $orderStatus,

    "qrCode" =>
        $qrCode,

    "upiString" =>
        $upiString
];


/* ==============================
   INSERT LOCAL ORDER
   ============================== */

$paymentMethod =
    "UroPay";


$paymentId =
    (string)$uroPayOrderId;


$status =
    "Pending";


$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO orders
    (
        user_id,
        total_amount,
        payment_id,
        merchant_order_id,
        payment_method,
        qr_code,
        status,
        order_date
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
);

if (!$stmt) {
    die(
        "Database Prepare Error: " .
        htmlspecialchars(mysqli_error($conn))
    );
}

mysqli_stmt_bind_param(
    $stmt,
    "idsssss",
    $user_id,
    $grandTotal,
    $paymentId,
    $merchantOrderId,
    $paymentMethod,
    $qrCode,
    $status
);

if (!mysqli_stmt_execute($stmt)) {
    die(
        "Database Insert Error: " .
        htmlspecialchars(mysqli_stmt_error($stmt))
    );
}

$localOrderId = mysqli_insert_id($conn);

mysqli_stmt_close($stmt);

/* ==============================
   INSERT INTO ORDER_ITEMS
   ============================== */

@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(200) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `subtotal` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$itemStmt = mysqli_prepare($conn, "INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
if ($itemStmt) {
    foreach ($orderedItemsData as $item) {
        mysqli_stmt_bind_param($itemStmt, "iisdid", $localOrderId, $item['product_id'], $item['product_name'], $item['price'], $item['quantity'], $item['subtotal']);
        mysqli_stmt_execute($itemStmt);
    }
    mysqli_stmt_close($itemStmt);
}

/* ==============================
   CLEAR CART ONLY AFTER SUCCESSFUL ORDER CREATION
   ============================== */

$_SESSION['cart'] = [];
$_SESSION['payment_status'] = "Pending";
$_SESSION['payment_expires_at'] = time() + 600; // 10 minutes time limit for QR payment


/* ==============================
   IMPORTANT SESSION IDs
   ============================== */

/*
   This is YOUR MySQL orders.id
*/

$_SESSION['local_order_id'] =
    $localOrderId;


/*
   This is UroPay's order ID
*/

$_SESSION['uropay_order_id'] =
    $uroPayOrderId;


/*
   Keep merchant order ID too
*/

$_SESSION['merchant_order_id'] =
    $merchantOrderId;


/* ==============================
   REDIRECT TO PAYMENT PAGE
   ============================== */

header(
    "Location: uropay_payment.php"
);

exit();

?>