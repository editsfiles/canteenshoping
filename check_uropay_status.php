<?php

session_start();

include("php/db.php");
include("config_uropay.php");

header("Content-Type: application/json");

function normalizeUroPayStatus($status) {
    if ($status === null) {
        return "";
    }

    return strtoupper(trim((string)$status));
}

function isSuccessfulUroPayStatus($status) {
    $status = normalizeUroPayStatus($status);

    return in_array($status, [
        "COMPLETED",
        "SUCCESS",
        "SUCCESSFUL",
        "PAID",
        "PAYMENT_SUCCESS",
        "PAYMENT_COMPLETED",
        "PAYMENT_SUCCEEDED",
        "TRANSACTION_SUCCESS",
        "TRANSACTION_COMPLETED",
        "CAPTURED",
        "SETTLED",
        "APPROVED"
    ], true);
}

function isCancelledUroPayStatus($status) {
    $status = normalizeUroPayStatus($status);

    return in_array($status, [
        "FAILED",
        "FAILURE",
        "PAYMENT_FAILED",
        "TRANSACTION_FAILED",
        "CANCELLED",
        "CANCELED",
        "PAYMENT_CANCELLED",
        "PAYMENT_CANCELED",
        "EXPIRED",
        "REJECTED"
    ], true);
}

function findMatchingOrderId($conn, $merchantOrderId = null, $paymentId = null) {
    $merchantOrderId = trim((string)($merchantOrderId ?? ""));
    $paymentId = trim((string)($paymentId ?? ""));

    if ($merchantOrderId === "" && $paymentId === "") {
        return 0;
    }

    $sql = "SELECT id FROM orders WHERE 1=1";
    $types = "";
    $values = [];

    if ($merchantOrderId !== "") {
        $sql .= " AND merchant_order_id = ?";
        $types .= "s";
        $values[] = $merchantOrderId;
    }

    if ($paymentId !== "") {
        $sql .= " AND payment_id = ?";
        $types .= "s";
        $values[] = $paymentId;
    }

    $sql .= " LIMIT 1";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return 0;
    }

    if ($values !== []) {
        mysqli_stmt_bind_param($stmt, $types, ...$values);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $orderId = 0;
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $orderId = (int)($row['id'] ?? 0);
    }

    mysqli_stmt_close($stmt);
    return $orderId;
}

$uroPayOrderId = trim($_GET['order_id'] ?? ($_SESSION['uropay_order_id'] ?? ''));

if (empty($uroPayOrderId)) {
    session_write_close();
    echo json_encode([
        "success" => false,
        "message" => "Payment session or order ID not found."
    ]);
    exit();
}

$_SESSION['uropay_order_id'] = $uroPayOrderId;

$localOrderId = (int)($_SESSION['local_order_id'] ?? 0);
$merchantOrderId = trim((string)($_SESSION['merchant_order_id'] ?? ''));

// Release session lock early to prevent blocking subsequent polling requests
session_write_close();

if ($localOrderId <= 0) {
    $stmt = mysqli_prepare($conn, "SELECT id FROM orders WHERE payment_id = ? OR merchant_order_id = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ss", $uroPayOrderId, $uroPayOrderId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && $r = mysqli_fetch_assoc($res)) {
            $localOrderId = (int)$r['id'];
        }
        mysqli_stmt_close($stmt);
    }
}

if ($localOrderId <= 0) {
    $localOrderId = findMatchingOrderId(
        $conn,
        $merchantOrderId,
        $uroPayOrderId
    );
}

if ($localOrderId <= 0) {
    echo json_encode([
        "success" => false,
        "status" => "UNKNOWN",
        "message" => "Local order could not be matched."
    ]);
    exit();
}

$stmtCheck = mysqli_prepare($conn, "SELECT id, status, food_status, bank_utr, payment_id, merchant_order_id FROM orders WHERE id = ? OR payment_id = ? OR merchant_order_id = ? LIMIT 1");
if ($stmtCheck) {
    mysqli_stmt_bind_param($stmtCheck, "iss", $localOrderId, $uroPayOrderId, $uroPayOrderId);
    mysqli_stmt_execute($stmtCheck);
    $resCheck = mysqli_stmt_get_result($stmtCheck);
    if ($resCheck && $rowCheck = mysqli_fetch_assoc($resCheck)) {
        $dbStatus = strtoupper(trim((string)$rowCheck['status']));
        if ($dbStatus === 'COMPLETED' || $dbStatus === 'PAID' || $dbStatus === 'SUCCESS') {
            mysqli_stmt_close($stmtCheck);
            echo json_encode([
                "success" => true,
                "status" => "PAID",
                "uropay_status" => "PAID",
                "local_order_id" => (int)$rowCheck['id'],
                "uropay_order_id" => $uroPayOrderId,
                "payment_id" => $rowCheck['bank_utr'] ?: ($rowCheck['payment_id'] ?: $uroPayOrderId)
            ]);
            exit();
        }
    }
    mysqli_stmt_close($stmtCheck);
}

/*
|--------------------------------------------------------------------------
| MANUAL UTR SUBMISSION CHECK
|--------------------------------------------------------------------------
*/
$userUtr = trim($_REQUEST['utr'] ?? '');
if (!empty($userUtr)) {
    $cleanUtr = preg_replace('/[^a-zA-Z0-9]/', '', $userUtr);
    if (strlen($cleanUtr) >= 6) {
        $upd = mysqli_prepare($conn, "UPDATE orders SET status = 'Completed', food_status = CASE WHEN food_status IS NULL OR food_status = '' OR food_status = 'Pending' THEN 'Preparing' ELSE food_status END, bank_utr = ? WHERE id = ?");
        if ($upd) {
            mysqli_stmt_bind_param($upd, "si", $cleanUtr, $localOrderId);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);

            @include_once(__DIR__ . "/php/mail.php");
            if (function_exists('sendOrderInvoiceEmail')) {
                @sendOrderInvoiceEmail($localOrderId, $conn);
            }
        }
        echo json_encode([
            "success" => true,
            "status" => "PAID",
            "uropay_status" => "PAID",
            "local_order_id" => $localOrderId,
            "uropay_order_id" => $uroPayOrderId,
            "payment_id" => $cleanUtr,
            "message" => "Payment verified successfully with UTR: " . $cleanUtr
        ]);
        exit();
    } else {
        echo json_encode([
            "success" => false,
            "status" => "INVALID_UTR",
            "message" => "Please enter a valid 12-digit UPI reference / UTR number."
        ]);
        exit();
    }
}

$url = UROPAY_API_URL . "/order/status/" . rawurlencode($uroPayOrderId);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 6);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Accept: application/json",
    "Content-Type: application/json",
    "X-API-KEY: " . UROPAY_API_KEY
]);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

/*
|--------------------------------------------------------------------------
| CURL ERROR & RESPONSE VALIDATION
|--------------------------------------------------------------------------
*/
if ($response === false || $curlError) {
    echo json_encode([
        "success" => false,
        "status" => "UNKNOWN",
        "message" => "Unable to contact UroPay gateway.",
        "error" => $curlError
    ]);
    exit();
}

$result = json_decode($response, true);
if (!is_array($result)) {
    echo json_encode([
        "success" => false,
        "status" => "UNKNOWN",
        "message" => "Invalid response from UroPay.",
        "http_code" => $httpCode
    ]);
    exit();
}

/*
|--------------------------------------------------------------------------
| GET UROPAY STATUS (STRICT GATEWAY VERIFICATION)
|--------------------------------------------------------------------------
*/
$uroStatus = "";
$payloadContainer = $result;

if (isset($result['data']) && is_array($result['data'])) {
    $payloadContainer = $result['data'];
}

$uroStatus = $payloadContainer['orderStatus']
    ?? $payloadContainer['status']
    ?? $payloadContainer['paymentStatus']
    ?? $result['orderStatus']
    ?? $result['status']
    ?? $result['paymentStatus']
    ?? "";

$uroStatus = normalizeUroPayStatus($uroStatus);
$localStatus = "Pending";

if (isSuccessfulUroPayStatus($uroStatus)) {
    $localStatus = "Completed";
} elseif (isCancelledUroPayStatus($uroStatus)) {
    $localStatus = "Cancelled";
}


/*
|--------------------------------------------------------------------------
| PAYMENT ID
|--------------------------------------------------------------------------
|
| Keep UroPay order ID as fallback.
| If UroPay gives transaction/reference ID,
| save that instead.
|
*/

$paymentId = $uroPayOrderId;


if (
    isset($result['data']) &&
    is_array($result['data'])
) {

    if (!empty($result['data']['referenceNumber'])) {

        $paymentId =
            $result['data']['referenceNumber'];

    }

    elseif (!empty($result['data']['transactionId'])) {

        $paymentId =
            $result['data']['transactionId'];

    }

    elseif (!empty($result['data']['utr'])) {

        $paymentId =
            $result['data']['utr'];

    }

}


/*
|--------------------------------------------------------------------------
| UPDATE DATABASE
|--------------------------------------------------------------------------
*/

$bankUtr = '';
if (isset($result['data']) && is_array($result['data'])) {
    $bankUtr = trim((string)($result['data']['referenceNumber'] ?? ($result['data']['utr'] ?? ($result['data']['transactionId'] ?? ''))));
}

$stmt = mysqli_prepare(
    $conn,
    "UPDATE orders
     SET
        status = ?,
        food_status = CASE WHEN ? = 'Completed' AND (food_status IS NULL OR food_status = '' OR food_status = 'Pending') THEN 'Preparing' ELSE food_status END,
        payment_id = ?,
        bank_utr = CASE WHEN ? != '' THEN ? ELSE bank_utr END
     WHERE id = ?
        OR merchant_order_id = ?
        OR payment_id = ?
     LIMIT 1"
);


if (!$stmt) {

    echo json_encode([
        "success" => false,
        "status" => $uroStatus,
        "message" => "Database prepare error.",
        "error" => mysqli_error($conn)
    ]);

    exit();

}


$merchantOrderId = $_SESSION['merchant_order_id'] ?? '';

mysqli_stmt_bind_param(
    $stmt,
    "sssssiss",
    $localStatus,
    $localStatus,
    $paymentId,
    $bankUtr,
    $bankUtr,
    $localOrderId,
    $merchantOrderId,
    $paymentId
);


if (!mysqli_stmt_execute($stmt)) {
    $error = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode([
        "success" => false,
        "status" => $uroStatus,
        "message" => "Database update failed.",
        "error" => $error
    ]);
    exit();
}

mysqli_stmt_close($stmt);

if ($localStatus === 'Completed') {
    @include_once(__DIR__ . "/php/mail.php");
    if (function_exists('sendOrderInvoiceEmail')) {
        @sendOrderInvoiceEmail($localOrderId, $conn);
    }
}

$_SESSION['payment_status'] = $localStatus;
$_SESSION['local_order_id'] = $localOrderId;


/*
|--------------------------------------------------------------------------
| RETURN RESULT TO JAVASCRIPT
|--------------------------------------------------------------------------
*/

echo json_encode([

    "success" => true,

    "uropay_status" => $uroStatus,

    "status" => $localStatus,

    "local_order_id" => $localOrderId,

    "uropay_order_id" => $uroPayOrderId,

    "payment_id" => $paymentId

]);

exit();

?>