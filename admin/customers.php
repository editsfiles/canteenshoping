<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

include("../php/db.php");

// Delete Customer
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    if ($id > 0) {
        @mysqli_query($conn, "DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE user_id='$id')");
        @mysqli_query($conn, "DELETE FROM orders WHERE user_id='$id'");
        mysqli_query($conn, "DELETE FROM users WHERE id='$id'");
    }

    header("Location: customers.php");
    exit();
}

// Search
$search = "";
if (isset($_GET['search']) && trim($_GET['search']) != "") {
    $search = mysqli_real_escape_string($conn, trim($_GET['search']));
    $query = mysqli_query($conn, "
        SELECT * FROM users
        WHERE name LIKE '%$search%'
           OR regno LIKE '%$search%'
           OR department LIKE '%$search%'
           OR email LIKE '%$search%'
        ORDER BY id DESC
    ");
} else {
    $query = mysqli_query($conn, "SELECT * FROM users ORDER BY id DESC");
}

$activePage = 'customers';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - College Canteen Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <link rel="stylesheet" href="css/admin_material.css">
</head>
<body>

<?php include("header_nav.php"); ?>

<main class="admin-container">

    <div class="admin-header-row">
        <div>
            <h1 class="admin-page-title">
                <i class="fa-solid fa-users"></i> Registered Students & Customers
            </h1>
            <p class="admin-subtitle">View student account profiles, departments, and individual purchase histories</p>
        </div>

        <form method="GET" class="search-container">
            <i class="fa-solid fa-magnifying-glass" style="color:#94a3b8;"></i>
            <input 
                type="text" 
                name="search" 
                placeholder="Search by name, reg no, department..." 
                value="<?php echo htmlspecialchars($search); ?>"
            >
            <?php if (!empty($search)): ?>
                <a href="customers.php" style="color:#94a3b8; text-decoration:none;"><i class="fa-solid fa-xmark"></i></a>
            <?php endif; ?>
        </form>
    </div>

    <!-- STRUCTURED TABLE WITH RED-ORANGE HEADER -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="material-table">
                <thead>
                    <tr>
                        <th style="width:70px;">ID</th>
                        <th>Student Name</th>
                        <th>Register No</th>
                        <th>Department</th>
                        <th>Email Address</th>
                        <th style="text-align:right; width:160px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($query) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($query)): ?>
                        <tr>
                            <td><strong>#<?php echo $row['id']; ?></strong></td>
                            <td>
                                <div style="font-weight:700; color:#0f172a; font-size:15px;"><?php echo htmlspecialchars($row['name']); ?></div>
                            </td>
                            <td>
                                <span style="font-family:monospace; font-weight:600; background:#f1f5f9; padding:3px 8px; border-radius:6px; color:#334155;">
                                    <?php echo htmlspecialchars($row['regno'] ?: 'N/A'); ?>
                                </span>
                            </td>
                            <td>
                                <span style="color:#475569; font-weight:500;">
                                    <?php echo htmlspecialchars($row['department'] ?: 'General'); ?>
                                </span>
                            </td>
                            <td>
                                <span style="color:#2563eb; font-size:13px;">
                                    <i class="fa-regular fa-envelope" style="margin-right:4px;"></i><?php echo htmlspecialchars($row['email']); ?>
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <a href="customer_details.php?id=<?php echo $row['id']; ?>" class="btn-material btn-primary" style="padding:6px 12px; font-size:12px; margin-right:4px;">
                                    <i class="fa-solid fa-eye"></i> Details
                                </a>
                                <a href="customers.php?delete=<?php echo $row['id']; ?>" class="btn-material btn-danger" style="padding:6px 12px; font-size:12px;" onclick="return confirm('Delete this customer and all associated orders?');">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding:40px; color:#94a3b8;">
                                <i class="fa-solid fa-user-slash" style="font-size:36px; margin-bottom:10px; display:block; opacity:0.4;"></i>
                                No customers found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

</body>
</html>