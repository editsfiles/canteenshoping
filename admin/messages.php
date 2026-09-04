<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

include("../php/db.php");

/* ===============================
   SEARCH
================================ */

$search = "";

if(isset($_GET['search']))
{
    $search = mysqli_real_escape_string($conn, $_GET['search']);

    $result = mysqli_query($conn,"
    SELECT *
    FROM contacts
    WHERE
    name LIKE '%$search%'
    OR email LIKE '%$search%'
    OR subject LIKE '%$search%'
    OR message LIKE '%$search%'
    ORDER BY id DESC
    ");

}
else
{

    $result = mysqli_query($conn,"
    SELECT *
    FROM contacts
    ORDER BY id DESC
    ");

}

/* ===============================
   TOTAL MESSAGES
================================ */

$totalMessages = mysqli_num_rows(
mysqli_query($conn,"SELECT * FROM contacts")
);

/* ===============================
   TODAY MESSAGES
================================ */

$todayMessages = mysqli_num_rows(

mysqli_query($conn,"
SELECT *
FROM contacts
WHERE DATE(created_at)=CURDATE()
")

);

$activePage = 'messages';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Contact Messages - College Canteen Admin</title>
    <!-- Material Font & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <!-- Standardized Admin Material CSS -->
    <link rel="stylesheet" href="css/admin_material.css">
</head>
<body>

<?php include("header_nav.php"); ?>

<div class="admin-container">
    <div class="admin-header-row">
        <div>
            <h1 class="admin-page-title"><i class="fa-solid fa-envelope"></i> Customer Messages</h1>
            <p class="admin-subtitle">Inquiries, feedback, and support requests submitted by canteen customers</p>
        </div>
        <form method="GET" class="search-container" style="margin:0;">
            <i class="fa-solid fa-magnifying-glass" style="color:#94a3b8;"></i>
            <input type="text" name="search" placeholder="Search by name, email, subject..." value="<?php echo htmlspecialchars($search); ?>">
            <?php if (!empty($search)): ?>
                <a href="messages.php" style="color:#ef4444; font-size:13px; text-decoration:none;"><i class="fa-solid fa-xmark"></i></a>
            <?php endif; ?>
        </form>
    </div>

    <!-- SUMMARY INFO CARDS -->
    <div class="cards-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
        <div class="info-card card-products">
            <div class="card-header">
                <span class="card-title">Total Inquiries</span>
                <i class="fa-solid fa-envelope-open-text card-icon"></i>
            </div>
            <div class="card-value"><?php echo number_format($totalMessages); ?></div>
        </div>

        <div class="info-card card-today-orders">
            <div class="card-header">
                <span class="card-title">Today's Inquiries</span>
                <i class="fa-solid fa-bell card-icon"></i>
            </div>
            <div class="card-value"><?php echo number_format($todayMessages); ?></div>
        </div>
    </div>

    <!-- STRUCTURED TABLE WITH RED-ORANGE HEADER -->
    <div class="table-card">
        <div style="padding: 18px 24px; border-bottom: 1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="font-size:16px; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-inbox" style="color:#ea580c;"></i> Inbox Messages
            </h3>
            <span style="font-size:13px; color:#64748b; font-weight:600;">
                Total: <?php echo mysqli_num_rows($result); ?>
            </span>
        </div>

        <div class="table-responsive">
            <table class="material-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Sender Name</th>
                        <th>Email Address</th>
                        <th>Subject</th>
                        <th>Message Preview</th>
                        <th>Received Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><strong>#<?php echo $row['id']; ?></strong></td>
                                <td><strong style="color:#0f172a;"><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                <td><a href="mailto:<?php echo htmlspecialchars($row['email']); ?>" style="color:#0284c7; text-decoration:none; font-weight:500;"><?php echo htmlspecialchars($row['email']); ?></a></td>
                                <td><span style="font-weight:600; color:#334155;"><?php echo htmlspecialchars($row['subject']); ?></span></td>
                                <td style="max-width:320px; color:#475569; font-size:13px;">
                                    <?php 
                                    $msg = $row['message'];
                                    echo htmlspecialchars(mb_strlen($msg) > 75 ? mb_substr($msg, 0, 75) . '...' : $msg); 
                                    ?>
                                </td>
                                <td>
                                    <div style="font-size:13px; color:#1e293b;"><?php echo date("d M Y", strtotime($row['created_at'])); ?></div>
                                    <small style="color:#64748b;"><?php echo date("h:i A", strtotime($row['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div style="display:flex; gap:8px; align-items:center;">
                                        <a href="view_message.php?id=<?php echo $row['id']; ?>" class="btn-material btn-primary" style="padding:6px 12px; font-size:12px;" title="View Full Message">
                                            <i class="fa-solid fa-eye"></i> View
                                        </a>
                                        <a href="delete_message.php?id=<?php echo $row['id']; ?>" class="btn-material btn-danger" style="padding:6px 12px; font-size:12px;" onclick="return confirm('Are you sure you want to delete this message?');" title="Delete Message">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:40px 20px; color:#64748b;">
                                <i class="fa-solid fa-envelope-open-text" style="font-size:36px; color:#cbd5e1; display:block; margin-bottom:12px;"></i>
                                <strong style="font-size:16px; color:#0f172a;">No Messages Found</strong>
                                <p style="margin-top:4px; font-size:13px;">There are currently no customer messages in the inbox.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>