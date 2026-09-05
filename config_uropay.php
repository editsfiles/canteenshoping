<?php

define("UROPAY_API_URL", getenv('UROPAY_API_URL') ?: "https://api.uropay.me");
define("UROPAY_API_KEY", getenv('UROPAY_API_KEY') ?: 'G994K1P445AJ28UL');
define("UROPAY_SECRET", getenv('UROPAY_SECRET') ?: 'XUJCZN35BLV431DUKMBJHX8M3UW3RXMN8ZYMHL7SAUG4WYAPWJ');
define("CANTEEN_UPI_ID", getenv('CANTEEN_UPI_ID') ?: "9952611859@slc");

$detectedSiteUrl = '';
if (!empty($_SERVER['HTTP_HOST'])) {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
             (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
             ? 'https://' : 'http://';
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '.') {
        $scriptDir = '';
    }
    if (substr($scriptDir, -6) === '/admin') {
        $scriptDir = substr($scriptDir, 0, -6);
    }
    $detectedSiteUrl = $proto . $_SERVER['HTTP_HOST'] . $scriptDir;
}

define("SITE_URL", rtrim(getenv('SITE_URL') ?: ($detectedSiteUrl ?: 'http://127.0.0.1:8080'), '/'));
define("UROPAY_REDIRECT_URL", SITE_URL . "/payment_success.php");
define("UROPAY_SUCCESS_URL", SITE_URL . "/payment_success.php");
define("UROPAY_FAILURE_URL", SITE_URL . "/payment_failed.php");
define("UROPAY_WEBHOOK_URL", getenv('UROPAY_WEBHOOK_URL') ?: (SITE_URL . "/webhook.php"));

?>
