<?php

date_default_timezone_set('Asia/Kolkata');

// Configure 1-Year Persistent Sessions (Never Logout Automatically)
if (session_status() === PHP_SESSION_NONE) {
    $sessionLifetime = 365 * 24 * 60 * 60; // 1 year (31,536,000 seconds)
    @ini_set('session.gc_maxlifetime', (string)$sessionLifetime);
    @session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    @session_start();
}

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

// If initial connection failed and target host is local/container,
// try sockets, TCP 127.0.0.1, and localhost with candidate credentials
if (!$conn && ($host === 'localhost' || $host === '127.0.0.1' || empty($host))) {
    $possibleSockets = [
        '/run/mysqld/mysqld.sock',
        '/var/run/mysqld/mysqld.sock',
        '/tmp/mysql.sock'
    ];
    $foundSocket = null;
    foreach ($possibleSockets as $socket) {
        if (file_exists($socket)) {
            $foundSocket = $socket;
            break;
        }
    }

    $credList = [
        [$user, $pass],
        ['root', ''],
        ['root', 'root'],
        ['canteen_user', 'canteen_pass'],
        ['canteen_user', ''],
        [$user, 'canteen_pass'],
        [$user, ''],
    ];

    $targets = [];
    if ($foundSocket) {
        $targets[] = ['localhost', $foundSocket];
    }
    $targets[] = ['127.0.0.1', null];
    $targets[] = ['localhost', null];

    foreach ($targets as $t) {
        $h = $t[0];
        $s = $t[1];
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
    $finalError = mysqli_connect_error() ?: $error ?: 'Database connection failed';
    die('Database Connection Error: ' . htmlspecialchars($finalError));
}

mysqli_set_charset($conn, 'utf8mb4');
@mysqli_query($conn, "SET time_zone = '+05:30'");

// Auto-migrate schema updates if missing on live deployments (e.g. Render)
if ($conn) {
    // 1. Ensure users columns
    $uCols = @mysqli_query($conn, "SHOW COLUMNS FROM users");
    if ($uCols) {
        $uFields = [];
        while ($r = mysqli_fetch_assoc($uCols)) {
            $uFields[] = $r['Field'];
        }
        if (!in_array('phone', $uFields)) {
            @mysqli_query($conn, "ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL AFTER email");
        }
    }

    // 2. Ensure orders columns
    $oCols = @mysqli_query($conn, "SHOW COLUMNS FROM orders");
    if ($oCols) {
        $oFields = [];
        while ($r = mysqli_fetch_assoc($oCols)) {
            $oFields[] = $r['Field'];
        }
        if (!in_array('upi_id', $oFields)) {
            @mysqli_query($conn, "ALTER TABLE orders ADD COLUMN upi_id VARCHAR(100) DEFAULT '9952611859@slc' AFTER payment_method");
        }
        if (!in_array('bank_utr', $oFields)) {
            @mysqli_query($conn, "ALTER TABLE orders ADD COLUMN bank_utr VARCHAR(100) DEFAULT NULL AFTER payment_id");
        }
        if (!in_array('merchant_order_id', $oFields)) {
            @mysqli_query($conn, "ALTER TABLE orders ADD COLUMN merchant_order_id VARCHAR(255) DEFAULT NULL AFTER bank_utr");
        }
        if (!in_array('food_status', $oFields)) {
            @mysqli_query($conn, "ALTER TABLE orders ADD COLUMN food_status VARCHAR(50) NOT NULL DEFAULT 'Preparing' AFTER status");
        }
        if (!in_array('qr_code', $oFields)) {
            @mysqli_query($conn, "ALTER TABLE orders ADD COLUMN qr_code MEDIUMTEXT DEFAULT NULL AFTER upi_id");
        }
        if (!in_array('refund_status', $oFields)) {
            @mysqli_query($conn, "ALTER TABLE orders ADD COLUMN refund_status VARCHAR(100) DEFAULT NULL AFTER order_date");
        }
        if (!in_array('refund_notes', $oFields)) {
            @mysqli_query($conn, "ALTER TABLE orders ADD COLUMN refund_notes VARCHAR(255) DEFAULT NULL AFTER refund_status");
        }
    }
}

// Permanent Session Restorer: Auto-login from persistent cookie if session was cleared
if ($conn && empty($_SESSION['user_id']) && !empty($_COOKIE['canteen_student_auth'])) {
    $authParts = explode(':', $_COOKIE['canteen_student_auth'], 2);
    if (count($authParts) === 2) {
        $cUserId = (int)$authParts[0];
        $cToken  = $authParts[1];
        if ($cUserId > 0 && !empty($cToken)) {
            $cStmt = @mysqli_prepare($conn, "SELECT id, name, email, password FROM users WHERE id = ? LIMIT 1");
            if ($cStmt) {
                mysqli_stmt_bind_param($cStmt, "i", $cUserId);
                mysqli_stmt_execute($cStmt);
                $cRes = mysqli_stmt_get_result($cStmt);
                if ($cRow = mysqli_fetch_assoc($cRes)) {
                    $expectedToken = hash_hmac('sha256', (string)$cRow['id'] . (string)$cRow['password'], 'canteen_app_secret_key_2026');
                    if (hash_equals($expectedToken, $cToken)) {
                        $_SESSION['user_id']    = (int)$cRow['id'];
                        $_SESSION['user_name']  = $cRow['name'];
                        $_SESSION['name']       = $cRow['name'];
                        $_SESSION['user_email'] = $cRow['email'];
                    }
                }
                mysqli_stmt_close($cStmt);
            }
        }
    }
}
?>
