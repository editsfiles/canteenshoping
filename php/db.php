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

// Cloud database connection with SSL when requested.
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

// Normal TCP connection.
if (!$conn) {
    $conn = @mysqli_connect($host, $user, $pass, $name, $port);
    if (!$conn) {
        $error = mysqli_connect_error() ?: $error ?: 'Database connection failed';
    }
}

// Render's Docker image runs MariaDB locally when no external DB is configured.
// MariaDB's default root account may authenticate through its Unix socket, so try
// the socket explicitly before trying other local credentials.
if (!$conn && ($host === 'localhost' || $host === '127.0.0.1' || empty($host))) {
    $possibleSockets = [
        '/run/mysqld/mysqld.sock',
        '/var/run/mysqld/mysqld.sock',
        '/tmp/mysql.sock'
    ];

    foreach ($possibleSockets as $socket) {
        if (!file_exists($socket)) {
            continue;
        }

        $conn = @mysqli_connect('localhost', $user, $pass, $name, $port, $socket);
        if ($conn) {
            break;
        }

        // MariaDB's socket-authenticated root commonly uses an empty password.
        if ($user === 'root') {
            $conn = @mysqli_connect('localhost', 'root', '', $name, $port, $socket);
            if ($conn) {
                break;
            }
        }
    }
}

// Last-resort compatibility attempts for older local/container databases.
if (!$conn && ($host === 'localhost' || $host === '127.0.0.1' || empty($host))) {
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

    foreach ($credList as $cr) {
        $conn = @mysqli_connect('127.0.0.1', $cr[0], $cr[1], $name, $port);
        if ($conn) {
            break;
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
