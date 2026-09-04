<?php

date_default_timezone_set('Asia/Kolkata');

$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASSWORD') ?: '';
$db_name = getenv('DB_NAME') ?: 'canteen_db';
$db_port = (int)(getenv('DB_PORT') ?: 3306);

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);

if(!$conn){
    die("Connection Failed : ".mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8mb4');
mysqli_query($conn, "SET time_zone = '+05:30'");

?>
