<?php

date_default_timezone_set('Asia/Kolkata');

$conn = mysqli_connect("localhost","root","","canteen_db");

if(!$conn){
    die("Connection Failed : ".mysqli_connect_error());
}

mysqli_query($conn, "SET time_zone = '+05:30'");

?>