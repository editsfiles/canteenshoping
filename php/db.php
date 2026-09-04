<?php

date_default_timezone_set('Asia/Kolkata');

$url = getenv('DATABASE_URL') ?: getenv('MYSQL_URL');
$host = getenv('DB_HOST') ?: '127.0.0.1';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';
$name = getenv('DB_NAME') ?: 'canteen_db';
$port = (int)(getenv('DB_PORT') ?: 3306);
$ssl = false;

if ($url) {
    $p = parse_url($url);
    if (!empty($p['host'])) $host = $p['host'];
    if (!empty($p['user'])) $user = $p['user'];
    if (isset($p['pass'])) $pass = urldecode($p['pass']);
    if (!empty($p['path'])) $name = ltrim($p['path'], '/');
    if (!empty($p['port'])) $port = (int)$p['port'];
    if (!empty($p['query'])) {
        parse_str($p['query'], $q);
        $ssl = isset($q['ssl-mode']) && strtoupper($q['ssl-mode']) === 'REQUIRED';
    }
}

mysqli_report(MYSQLI_REPORT_OFF);
$conn = false;
$error = '';

if ($ssl) {
    $db = mysqli_init();
    if ($db) {
        mysqli_ssl_set($db, null, null, null, null, null);
        $conn = @mysqli_real_connect($db, $host, $user, $pass, $name, $port, null, MYSQLI_CLIENT_SSL);
        if (!$conn) $error = mysqli_connect_error() ?: 'SSL connection failed';
    }
}

if (!$conn) {
    $conn = @mysqli_connect($host, $user, $pass, $name, $port);
    if (!$conn) $error = mysqli_connect_error() ?: $error ?: 'Database connection failed';
}

if (!$conn) {
    http_response_code(500);
    die('Database Connection Error: ' . htmlspecialchars($error));
}

mysqli_set_charset($conn, 'utf8mb4');
@mysqli_query($conn, "SET time_zone = '+05:30'");
?>
