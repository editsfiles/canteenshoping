<?php
session_start();

if(!isset($_SESSION['admin'])){
    header("Location: login.php");
    exit();
}

include("../php/db.php");

if(!isset($_GET['id'])){
    die("Message ID not found.");
}

$id = intval($_GET['id']);

$result = mysqli_query($conn,"SELECT * FROM contacts WHERE id='$id'");

if(mysqli_num_rows($result)==0){
    die("Message not found.");
}

$row=mysqli_fetch_assoc($result);
$activePage = 'messages';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Message #<?php echo $row['id']; ?> - College Canteen Admin</title>
    <!-- Material Font & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <!-- Standardized Admin Material CSS -->
    <link rel="stylesheet" href="css/admin_material.css">
</head>
<body>

<?php include("header_nav.php"); ?>

<div class="admin-container" style="max-width: 860px;">
    <div class="admin-header-row">
        <div>
            <h1 class="admin-page-title"><i class="fa-solid fa-envelope-open"></i> View Inquiry #<?php echo $row['id']; ?></h1>
            <p class="admin-subtitle">Submitted on <?php echo date("d M Y \a\\t h:i A", strtotime($row['created_at'])); ?></p>
        </div>
        <a href="messages.php" class="btn-material" style="background:#e2e8f0; color:#334155;">
            <i class="fa-solid fa-arrow-left"></i> Back to Messages
        </a>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">
                <i class="fa-solid fa-circle-info" style="color:#0284c7;"></i> Message Details
            </h3>
            <span style="font-size:12px; color:#64748b;">
                <i class="fa-regular fa-clock"></i> <?php echo date("d M Y, h:i A", strtotime($row['created_at'])); ?>
            </span>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:20px; margin-bottom:24px;">
            <div style="background:#f8fafc; padding:16px; border-radius:12px; border:1px solid #e2e8f0;">
                <span class="form-label" style="margin-bottom:4px;"><i class="fa-solid fa-user"></i> Sender Name</span>
                <strong style="font-size:16px; color:#0f172a;"><?php echo htmlspecialchars($row['name']); ?></strong>
            </div>
            <div style="background:#f8fafc; padding:16px; border-radius:12px; border:1px solid #e2e8f0;">
                <span class="form-label" style="margin-bottom:4px;"><i class="fa-solid fa-envelope"></i> Email Address</span>
                <a href="mailto:<?php echo htmlspecialchars($row['email']); ?>" style="font-size:15px; color:#0284c7; text-decoration:none; font-weight:600;">
                    <?php echo htmlspecialchars($row['email']); ?>
                </a>
            </div>
        </div>

        <div style="margin-bottom: 24px;">
            <span class="form-label"><i class="fa-solid fa-heading"></i> Subject</span>
            <div style="background:#fff7ed; border-left:4px solid #ea580c; padding:14px 18px; border-radius:8px; font-weight:600; color:#c2410c; font-size:16px;">
                <?php echo htmlspecialchars($row['subject']); ?>
            </div>
        </div>

        <div style="margin-bottom: 28px;">
            <span class="form-label"><i class="fa-solid fa-comment-dots"></i> Message Content</span>
            <div style="background:#ffffff; border:1.5px solid #e2e8f0; padding:22px; border-radius:12px; font-size:15px; line-height:1.7; color:#334155; min-height:140px; white-space:pre-wrap;">
                <?php echo htmlspecialchars($row['message']); ?>
            </div>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #f1f5f9; padding-top:20px; flex-wrap:wrap; gap:12px;">
            <a href="mailto:<?php echo htmlspecialchars($row['email']); ?>?subject=Re: <?php echo rawurlencode($row['subject']); ?>" class="btn-material btn-primary">
                <i class="fa-solid fa-reply"></i> Reply via Email
            </a>

            <a href="delete_message.php?id=<?php echo $row['id']; ?>" class="btn-material btn-danger" onclick="return confirm('Are you sure you want to permanently delete this message?');">
                <i class="fa-solid fa-trash"></i> Delete Message
            </a>
        </div>
    </div>
</div>

</body>
</html>