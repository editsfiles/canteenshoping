<?php

date_default_timezone_set('Asia/Kolkata');

// 1. Support DATABASE_URL or MYSQL_URL if provided (e.g. from cloud providers like Render / Railway)
$db_url = getenv('DATABASE_URL') ?: (getenv('MYSQL_URL') ?: ($_ENV['DATABASE_URL'] ?? ($_ENV['MYSQL_URL'] ?? ($_SERVER['DATABASE_URL'] ?? ($_SERVER['MYSQL_URL'] ?? '')))));

$db_host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? ($_SERVER['DB_HOST'] ?? ''));
$db_user = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? ($_SERVER['DB_USER'] ?? ''));
$db_pass = getenv('DB_PASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? ($_SERVER['DB_PASSWORD'] ?? ''));
$db_name = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? ($_SERVER['DB_NAME'] ?? ''));
$db_port = (int)(getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? ($_SERVER['DB_PORT'] ?? 0)));

if (!empty($db_url) && (strpos($db_url, 'mysql://') === 0 || strpos($db_url, 'mysqli://') === 0)) {
    $parsed = parse_url($db_url);
    if (!empty($parsed['host'])) $db_host = $parsed['host'];
    if (!empty($parsed['user'])) $db_user = $parsed['user'];
    if (isset($parsed['pass']))  $db_pass = $parsed['pass'];
    if (!empty($parsed['port'])) $db_port = (int)$parsed['port'];
    if (!empty($parsed['path'])) $db_name = ltrim($parsed['path'], '/');
}

// Defaults for local environment
if (empty($db_host)) $db_host = '127.0.0.1';
if (empty($db_user)) $db_user = 'root';
if (empty($db_name)) $db_name = 'canteen_db';
if ($db_port <= 0)   $db_port = 3306;

// On Linux / Docker, 'localhost' makes mysqli search for a local UNIX socket file (/var/run/mysqld/mysqld.sock).
// Using '127.0.0.1' forces TCP connection to port 3306, avoiding 'No such file or directory' errors.
if ($db_host === 'localhost' && DIRECTORY_SEPARATOR === '/') {
    $db_host = '127.0.0.1';
}

// Disable automatic uncaught mysqli exceptions so we can catch and handle connection issues gracefully
mysqli_report(MYSQLI_REPORT_OFF);

// Attempt connection
$conn = @mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);

// If connecting via 127.0.0.1 failed, also attempt 'localhost' fallback
if (!$conn && $db_host === '127.0.0.1') {
    $conn = @mysqli_connect('localhost', $db_user, $db_pass, $db_name, $db_port);
}

// If connection still failed, display a helpful diagnostic guide instead of crashing with a raw Fatal Error
if (!$conn) {
    $connectError = mysqli_connect_error() ?: 'Unknown MySQL connection failure';
    
    // For CLI or JSON API requests, return plain error
    if (php_sapi_name() === 'cli' || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
        header('Content-Type: application/json');
        echo json_encode([
            "success" => false,
            "error" => "Database connection failed",
            "details" => $connectError,
            "host" => $db_host,
            "port" => $db_port
        ]);
        exit();
    }

    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Connection Error - College Canteen</title>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Poppins', sans-serif; }
            body { background: #0f172a; color: #f8fafc; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
            .card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 32px 28px; max-width: 580px; width: 100%; box-shadow: 0 20px 40px rgba(0,0,0,0.4); }
            .icon { font-size: 40px; margin-bottom: 12px; }
            h2 { color: #f87171; font-size: 22px; margin-bottom: 8px; }
            p { color: #94a3b8; font-size: 14px; line-height: 1.5; margin-bottom: 16px; }
            .err-box { background: #0f172a; border-left: 4px solid #ef4444; padding: 12px 14px; border-radius: 6px; font-family: monospace; font-size: 13px; color: #fca5a5; margin-bottom: 20px; word-break: break-all; }
            .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 13px; }
            .info-table td { padding: 8px 10px; border-bottom: 1px solid #334155; }
            .info-table td:first-child { color: #94a3b8; width: 35%; }
            .info-table td:last-child { color: #38bdf8; font-family: monospace; }
            .steps-title { font-weight: 600; color: #f8fafc; margin-bottom: 8px; font-size: 14px; }
            .steps-box { background: #0f172a; border-radius: 8px; padding: 14px; margin-bottom: 20px; font-size: 12px; color: #cbd5e1; line-height: 1.6; }
            .steps-box code { background: #334155; color: #fbbf24; padding: 2px 6px; border-radius: 4px; font-family: monospace; }
            .btn { display: inline-block; background: #2563eb; color: white; text-decoration: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 13px; transition: 0.2s; border: none; cursor: pointer; }
            .btn:hover { background: #1d4ed8; }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="icon">🔌</div>
            <h2>Database Connection Failed</h2>
            <p>The canteen application could not connect to MySQL server. Please verify your database configuration.</p>
            
            <div class="err-box"><?php echo htmlspecialchars($connectError); ?></div>
            
            <table class="info-table">
                <tr><td>Target Host</td><td><?php echo htmlspecialchars($db_host); ?></td></tr>
                <tr><td>Target Port</td><td><?php echo (int)$db_port; ?></td></tr>
                <tr><td>Database Name</td><td><?php echo htmlspecialchars($db_name); ?></td></tr>
                <tr><td>Database User</td><td><?php echo htmlspecialchars($db_user); ?></td></tr>
            </table>

            <div class="steps-title">💡 How to resolve this:</div>
            <div class="steps-box">
                <strong>For Render / Cloud Hosting:</strong><br>
                1. Go to your Render Dashboard &rarr; <strong>canteenshoping</strong> service &rarr; <strong>Environment</strong> tab.<br>
                2. Add the following Environment Variables with your cloud MySQL credentials (e.g. from TiDB Cloud, Aiven, or Remote MySQL):<br>
                &bull; <code>DB_HOST</code> : Your remote MySQL host<br>
                &bull; <code>DB_USER</code> : Your MySQL username<br>
                &bull; <code>DB_PASSWORD</code> : Your MySQL password<br>
                &bull; <code>DB_NAME</code> : <code>canteen_db</code><br>
                &bull; <code>DB_PORT</code> : <code>3306</code><br>
                <em>(Or set <code>MYSQL_URL</code> = <code>mysql://user:pass@host:port/dbname</code>)</em><br><br>
                <strong>For Localhost (XAMPP):</strong><br>
                Ensure the <strong>MySQL</strong> module is started in the XAMPP Control Panel.
            </div>

            <button onclick="window.location.reload();" class="btn">↻ Retry Connection</button>
        </div>
    </body>
    </html>
    <?php
    exit();
}

mysqli_set_charset($conn, 'utf8mb4');
@mysqli_query($conn, "SET time_zone = '+05:30'");

?>
