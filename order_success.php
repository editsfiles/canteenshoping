<?php
// Alias for payment_success.php accepting either ?id= or ?order_id=
$orderId = $_GET['id'] ?? ($_GET['order_id'] ?? '');
if (!empty($orderId)) {
    header("Location: payment_success.php?order_id=" . urlencode($orderId));
} else {
    header("Location: payment_success.php");
}
exit();
