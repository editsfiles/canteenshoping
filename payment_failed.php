<?php

session_start();

include("php/db.php");


$localOrderId =
    $_SESSION['local_order_id'] ?? 0;


if ($localOrderId > 0) {

    $status =
        "Cancelled";


    $stmt = mysqli_prepare(
        $conn,
        "UPDATE orders
         SET status=?
         WHERE id=?"
    );


    mysqli_stmt_bind_param(
        $stmt,
        "si",
        $status,
        $localOrderId
    );


    mysqli_stmt_execute($stmt);

    mysqli_stmt_close($stmt);

}

?>

<!DOCTYPE html>

<html>

<head>

<title>Payment Failed</title>

<style>

body{

    font-family:Arial;

    background:#f4f6f9;

    display:flex;

    justify-content:center;

    align-items:center;

    min-height:100vh;

}

.box{

    background:white;

    width:400px;

    max-width:90%;

    padding:40px;

    text-align:center;

    border-radius:15px;

    box-shadow:0 5px 20px rgba(0,0,0,.15);

}

.failed{

    font-size:70px;

}

h1{

    color:#e74c3c;

}

.btn{

    display:block;

    padding:14px;

    margin-top:20px;

    background:#27ae60;

    color:white;

    text-decoration:none;

    border-radius:8px;

}

</style>

</head>

<body>

<div class="box">

<div class="failed">

❌

</div>

<h1>

Payment Failed / Cancelled

</h1>

<p>

The payment was not completed.

</p>

<a
href="checkout.php"
class="btn">

Back to Checkout

</a>

<a
href="menu.php"
class="btn">

Continue Shopping

</a>

</div>

</body>

</html>