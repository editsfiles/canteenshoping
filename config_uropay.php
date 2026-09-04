<?php

/*
|--------------------------------------------------------------------------
| UroPay Configuration
|--------------------------------------------------------------------------
*/

define("UROPAY_API_URL", "https://api.uropay.me");

define("UROPAY_API_KEY", "G994K1P445AJ28UL");

define("UROPAY_SECRET", "XUJCZN35BLV431DUKMBJHX8M3UW3RXMN8ZYMHL7SAUG4WYAPWJ");

/*
|--------------------------------------------------------------------------
| Merchant UPI ID / VPA
|--------------------------------------------------------------------------
*/
define("CANTEEN_UPI_ID", "canteen@upi");

/*
|--------------------------------------------------------------------------
| Website URL & Redirects
|--------------------------------------------------------------------------
|
| Public HTTPS URL or Frontend URL (e.g. your hosted domain / ngrok / Vercel)
|
*/

define("SITE_URL", "http://127.0.0.1:8080");

define("UROPAY_REDIRECT_URL", SITE_URL . "/payment_success.php");
define("UROPAY_SUCCESS_URL",  SITE_URL . "/payment_success.php");
define("UROPAY_FAILURE_URL",  SITE_URL . "/payment_failed.php");

/*
|--------------------------------------------------------------------------
| Webhook
|--------------------------------------------------------------------------
|
| UroPay must be able to reach this URL from outside your local machine.
|
*/

define(
    "UROPAY_WEBHOOK_URL",
    "https://jaybird-trowel-pusher.ngrok-free.dev/webhook.php"
);

?>