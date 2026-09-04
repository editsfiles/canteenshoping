<?php

define("UROPAY_API_URL", getenv('UROPAY_API_URL') ?: "https://api.uropay.me");
define("UROPAY_API_KEY", getenv('UROPAY_API_KEY') ?: "");
define("UROPAY_SECRET", getenv('UROPAY_SECRET') ?: "");
define("CANTEEN_UPI_ID", getenv('CANTEEN_UPI_ID') ?: "canteen@upi");

define("SITE_URL", rtrim(getenv('SITE_URL') ?: 'http://127.0.0.1:8080', '/'));
define("UROPAY_REDIRECT_URL", SITE_URL . "/payment_success.php");
define("UROPAY_SUCCESS_URL",  SITE_URL . "/payment_success.php");
define("UROPAY_FAILURE_URL",  SITE_URL . "/payment_failed.php");
define("UROPAY_WEBHOOK_URL", getenv('UROPAY_WEBHOOK_URL') ?: (SITE_URL . "/webhook.php"));

?>
