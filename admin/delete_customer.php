<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

include("../php/db.php");

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    if ($id > 0) {
        // Delete customer's order items
        $delItems = mysqli_prepare($conn, "DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE user_id = ?)");
        if ($delItems) {
            mysqli_stmt_bind_param($delItems, "i", $id);
            mysqli_stmt_execute($delItems);
            mysqli_stmt_close($delItems);
        }

        // Delete customer's orders
        $delOrders = mysqli_prepare($conn, "DELETE FROM orders WHERE user_id = ?");
        if ($delOrders) {
            mysqli_stmt_bind_param($delOrders, "i", $id);
            mysqli_stmt_execute($delOrders);
            mysqli_stmt_close($delOrders);
        }

        // Delete customer
        $delUser = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
        if ($delUser) {
            mysqli_stmt_bind_param($delUser, "i", $id);
            mysqli_stmt_execute($delUser);
            mysqli_stmt_close($delUser);
        }
    }
}

header("Location: customers.php");
exit();
?>