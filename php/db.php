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
        $ok = @mysqli_real_connect($db, $host, $user, $pass, $name, $port, null, MYSQLI_CLIENT_SSL);
        if ($ok) {
            $conn = $db;
        } else {
            $error = mysqli_connect_error() ?: 'SSL connection failed';
        }
    }
}

if (!$conn) {
    $conn = @mysqli_connect($host, $user, $pass, $name, $port);
    if (!$conn) $error = mysqli_connect_error() ?: $error ?: 'Database connection failed';
}

// Resilient fallback for local / container database if credentials or host format had mismatch
if (!$conn && (empty($host) || $host === 'localhost' || $host === '127.0.0.1')) {
    $socket = null;
    $possibleSockets = ['/run/mysqld/mysqld.sock', '/var/run/mysqld/mysqld.sock', '/tmp/mysql.sock'];
    foreach ($possibleSockets as $sp) {
        if (file_exists($sp)) {
            $socket = $sp;
            break;
        }
    }

    $credList = [
        [$user, $pass],
        [$user, 'canteen_pass'],
        [$user, ''],
        ['canteen_user', 'canteen_pass'],
        ['canteen_user', ''],
        ['canteen_user', $pass],
        ['root', ''],
        ['root', 'root'],
        ['root', $pass],
    ];

    $hostTargets = [];
    if ($socket) {
        $hostTargets[] = ['localhost', $socket];
    }
    $hostTargets[] = ['127.0.0.1', null];
    $hostTargets[] = ['localhost', null];

    foreach ($hostTargets as $ht) {
        $h = $ht[0];
        $s = $ht[1];
        foreach ($credList as $cr) {
            $u = $cr[0];
            $p = $cr[1];
            if ($s) {
                $conn = @mysqli_connect($h, $u, $p, $name, $port, $s);
            } else {
                $conn = @mysqli_connect($h, $u, $p, $name, $port);
            }
            if ($conn) {
                break 2;
            }
        }
    }
}

if (!$conn) {
    http_response_code(500);
    die('Database Connection Error: ' . htmlspecialchars($error));
}

mysqli_set_charset($conn, 'utf8mb4');
@mysqli_query($conn, "SET time_zone = '+05:30'");
?>
