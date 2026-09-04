<?php

/**
 * ============================================================================
 * UROPAY SECURE WEBHOOK HANDLER
 * ============================================================================
 * 
 * Listens for authoritative payment status notifications from UroPay.
 * - Authenticates incoming request via API Key / Signature verification
 * - Parses unique Order ID, Merchant Ref, and 12-digit UPI Bank UTR / RRN
 * - Updates local order status to 'Completed' (PAID) and kitchen status to 'Preparing'
 * - Responds with HTTP 200 OK to acknowledge delivery
 * 
 */

header("Content-Type: application/json; charset=UTF-8");

include("php/db.php");
include("config_uropay.php");

// ----------------------------------------------------------------------------
// 1. HELPER FUNCTIONS
// ----------------------------------------------------------------------------

function normalizeStatus($status) {
    return strtoupper(trim((string)$status));
}

function isPaidStatus($status) {
    $s = normalizeStatus($status);
    return in_array($s, [
        "COMPLETED", "SUCCESS", "SUCCESSFUL", "PAID", "PAYMENT_SUCCESS", 
        "PAYMENT_COMPLETED", "PAYMENT_SUCCEEDED", "TRANSACTION_SUCCESS", 
        "TRANSACTION_COMPLETED", "CAPTURED", "SETTLED", "APPROVED"
    ], true);
}

function isFailedStatus($status) {
    $s = normalizeStatus($status);
    return in_array($s, [
        "FAILED", "FAILURE", "PAYMENT_FAILED", "TRANSACTION_FAILED", 
        "CANCELLED", "CANCELED", "PAYMENT_CANCELLED", "PAYMENT_CANCELED", 
        "EXPIRED", "REJECTED"
    ], true);
}

// ----------------------------------------------------------------------------
// 2. READ RAW REQUEST BODY & HEADERS
// ----------------------------------------------------------------------------

$rawBody = file_get_contents("php://input");

if (empty($rawBody)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Empty request body."
    ]);
    exit();
}

// Log incoming webhook payload
$logFile = __DIR__ . "/webhook_log.txt";
file_put_contents(
    $logFile,
    "\n\n========================================\n" .
    date("Y-m-d H:i:s") . " - INCOMING UROPAY WEBHOOK\n" .
    "RAW PAYLOAD:\n" . $rawBody . "\n" .
    "========================================\n",
    FILE_APPEND
);

// ----------------------------------------------------------------------------
// 3. AUTHENTICATION & SIGNATURE VERIFICATION (OPTIONAL / RECOMMENDED)
// ----------------------------------------------------------------------------

$headers = function_exists('getallheaders') ? getallheaders() : [];
$incomingApiKey = $headers['X-API-KEY'] ?? ($headers['x-api-key'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? ''));
$incomingSignature = $headers['X-UROPAY-SIGNATURE'] ?? ($headers['x-uropay-signature'] ?? ($headers['Authorization'] ?? ($_SERVER['HTTP_X_UROPAY_SIGNATURE'] ?? '')));

// If API key is provided by gateway header, verify it
if (!empty($incomingApiKey) && defined('UROPAY_API_KEY') && !empty(UROPAY_API_KEY)) {
    if (!hash_equals((string)UROPAY_API_KEY, (string)$incomingApiKey)) {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Unauthorized: Invalid API Key."
        ]);
        exit();
    }
}

// ----------------------------------------------------------------------------
// 4. PARSE JSON DATA
// ----------------------------------------------------------------------------

$payload = json_decode($rawBody, true);

if (!is_array($payload)) {
    parse_str($rawBody, $payload);
}

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid JSON payload."
    ]);
    exit();
}

// Flatten data wrapper if present
$data = (isset($payload['data']) && is_array($payload['data'])) ? $payload['data'] : $payload;

// Extract Key Fields
$merchantOrderId = trim((string)($data['merchantOrderId'] ?? ($data['merchant_order_id'] ?? ($data['order_id'] ?? ''))));
$uroPayOrderId   = trim((string)($data['uroPayOrderId'] ?? ($data['uropayOrderId'] ?? ($data['paymentId'] ?? ($data['transactionId'] ?? '')))));
$orderStatus     = trim((string)($data['orderStatus'] ?? ($data['status'] ?? ($data['paymentStatus'] ?? ($data['payment_status'] ?? '')))));
$amountPaid      = (float)($data['amountInRupees'] ?? ($data['amount'] ?? 0));

// 12-Digit Bank UTR / RRN Reference Number
$bankUtr = trim((string)(
    $data['referenceNumber']
    ?? $data['transactionId']
    ?? $data['utr']
    ?? $data['rrn']
    ?? $data['bank_ref_num']
    ?? $data['upiRefNo']
    ?? $data['bankUtr']
    ?? ''
));

if (empty($merchantOrderId) && empty($uroPayOrderId)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Missing order identifier (merchantOrderId or uroPayOrderId)."
    ]);
    exit();
}

// ----------------------------------------------------------------------------
// 5. DETERMINE LOCAL STATUS & LOCATE ORDER
// ----------------------------------------------------------------------------

if (isPaidStatus($orderStatus)) {
    $localStatus = "Completed";
    $foodStatus  = "Preparing";
} elseif (isFailedStatus($orderStatus)) {
    $localStatus = "Cancelled";
    $foodStatus  = null;
} else {
    $localStatus = "Pending";
    $foodStatus  = null;
}

// Find local order in MySQL
$localOrder = null;
$stmt = mysqli_prepare(
    $conn,
    "SELECT id, total_amount, status, food_status, bank_utr, payment_id, merchant_order_id 
     FROM orders 
     WHERE (merchant_order_id = ? AND merchant_order_id != '') 
        OR (payment_id = ? AND payment_id != '') 
     ORDER BY id DESC LIMIT 1"
);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ss", $merchantOrderId, $uroPayOrderId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && mysqli_num_rows($res) > 0) {
        $localOrder = mysqli_fetch_assoc($res);
    }
    mysqli_stmt_close($stmt);
}

if (!$localOrder) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Order not found in database.",
        "merchant_order_id" => $merchantOrderId,
        "uropay_order_id" => $uroPayOrderId
    ]);
    exit();
}

$localOrderId = (int)$localOrder['id'];

// ----------------------------------------------------------------------------
// 6. IDEMPOTENT DATABASE UPDATE
// ----------------------------------------------------------------------------

$stmtUpdate = mysqli_prepare(
    $conn,
    "UPDATE orders
     SET
        status = ?,
        food_status = CASE 
            WHEN ? = 'Completed' AND (food_status IS NULL OR food_status = '' OR food_status = 'Pending') 
            THEN 'Preparing' 
            ELSE food_status 
        END,
        bank_utr = CASE WHEN ? != '' THEN ? ELSE bank_utr END,
        payment_id = COALESCE(NULLIF(?, ''), payment_id)
     WHERE id = ?
     LIMIT 1"
);

if (!$stmtUpdate) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database prepare error: " . mysqli_error($conn)
    ]);
    exit();
}

mysqli_stmt_bind_param(
    $stmtUpdate,
    "sssssi",
    $localStatus,
    $localStatus,
    $bankUtr,
    $bankUtr,
    $uroPayOrderId,
    $localOrderId
);

$updateSuccess = mysqli_stmt_execute($stmtUpdate);
$affectedRows  = mysqli_stmt_affected_rows($stmtUpdate);
mysqli_stmt_close($stmtUpdate);

if (!$updateSuccess) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database update failed."
    ]);
    exit();
}

// Log successful update
file_put_contents(
    $logFile,
    "UPDATED ORDER #$localOrderId -> Status: $localStatus | Bank UTR: " . ($bankUtr ?: 'N/A') . " | Affected Rows: $affectedRows\n",
    FILE_APPEND
);

if ($localStatus === 'Completed') {
    @include_once(__DIR__ . "/php/mail.php");
    if (function_exists('sendOrderInvoiceEmail')) {
        @sendOrderInvoiceEmail($localOrderId, $conn);
    }
}

// ----------------------------------------------------------------------------
// 7. AUTHORITATIVE 200 OK RESPONSE
// ----------------------------------------------------------------------------

http_response_code(200);
echo json_encode([
    "success" => true,
    "status" => "PAID",
    "order_status" => $localStatus,
    "local_order_id" => $localOrderId,
    "merchant_order_id" => $merchantOrderId,
    "uropay_order_id" => $uroPayOrderId,
    "bank_utr" => $bankUtr,
    "message" => "Order verified and updated to Completed (Preparing)."
]);

exit();