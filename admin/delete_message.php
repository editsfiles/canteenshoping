<?php
session_start();

if(!isset($_SESSION['admin'])){
    header("Location: login.php");
    exit();
}

include("../php/db.php");

if(isset($_GET['id'])){

    $id = (int)$_GET['id'];

    mysqli_query($conn,"DELETE FROM contacts WHERE id='$id'");
}

header("Location: messages.php");
exit();
?>