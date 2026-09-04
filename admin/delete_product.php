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
        // Get image name
        $stmt = mysqli_prepare($conn, "SELECT image FROM products WHERE id = ? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($result && $row = mysqli_fetch_assoc($result)) {
                if (!empty($row['image'])) {
                    $image = "../uploads/" . basename($row['image']);
                    if (file_exists($image) && is_file($image)) {
                        @unlink($image);
                    }
                }

                // Delete product
                $delStmt = mysqli_prepare($conn, "DELETE FROM products WHERE id = ?");
                if ($delStmt) {
                    mysqli_stmt_bind_param($delStmt, "i", $id);
                    mysqli_stmt_execute($delStmt);
                    mysqli_stmt_close($delStmt);
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
}

header("Location: products.php");
exit();
?>