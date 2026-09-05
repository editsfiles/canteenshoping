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

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment Failed - College Canteen</title>

<style>

body{

    font-family: Arial, Helvetica, sans-serif;

    background:#f4f6f9;

    display:flex;

    justify-content:center;

    align-items:center;

    min-height:100vh;

    padding: 20px 12px;

}

.box{

    background:white;

    width:420px;

    max-width:100%;

    padding:35px 25px;

    text-align:center;

    border-radius:18px;

    box-shadow:0 10px 30px rgba(0,0,0,.1);

}

.failed{

    font-size:65px;
    margin-bottom: 10px;

}

h1{

    color:#e74c3c;
    font-size: 22px;
    margin-bottom: 8px;

}

p{
    color: #64748b;
    font-size: 14px;
}

.btn{

    display:block;

    padding:13px;

    margin-top:14px;

    background:#27ae60;

    color:white;

    text-decoration:none;

    border-radius:10px;

    font-weight: 600;

    font-size: 14px;

    transition: 0.2s ease;

}

.btn:hover{
    background: #219150;
}

.btn.btn-secondary {
    background: #f1f5f9;
    color: #334155;
}

.btn.btn-secondary:hover {
    background: #e2e8f0;
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
class="btn btn-secondary">

Continue Shopping

</a>

</div>

</body>

</html>