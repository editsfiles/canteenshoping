<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

include("../php/db.php");

if (!isset($_GET['id'])) {
    header("Location: customers.php");
    exit();
}

$id = intval($_GET['id']);

$sql = "SELECT * FROM users WHERE id='$id'";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) == 0) {
    die("Customer Not Found");
}

$row = mysqli_fetch_assoc($result);

$successMsg = "";
$errorMsg = "";

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. UPDATE STUDENT USERNAME / INFO
    if (isset($_POST['action_update_student_profile'])) {
        $name = trim($_POST['name'] ?? '');
        $regno = trim($_POST['regno'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($name) || empty($email)) {
            $errorMsg = "Full Name and Email Address are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = "Please enter a valid email address.";
        } else {
            // Check if email already taken by another student
            $chkStmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            if ($chkStmt) {
                mysqli_stmt_bind_param($chkStmt, "si", $email, $id);
                mysqli_stmt_execute($chkStmt);
                $chkRes = mysqli_stmt_get_result($chkStmt);
                if (mysqli_num_rows($chkRes) > 0) {
                    $errorMsg = "The email '" . htmlspecialchars($email) . "' is already in use by another student.";
                }
                mysqli_stmt_close($chkStmt);
            }

            if (empty($errorMsg)) {
                $upStmt = mysqli_prepare($conn, "UPDATE users SET name = ?, regno = ?, department = ?, email = ? WHERE id = ?");
                if ($upStmt) {
                    mysqli_stmt_bind_param($upStmt, "ssssi", $name, $regno, $department, $email, $id);
                    mysqli_stmt_execute($upStmt);
                    mysqli_stmt_close($upStmt);
                    $successMsg = "Student profile and login email updated successfully!";

                    // Refresh row
                    $ref = mysqli_query($conn, "SELECT * FROM users WHERE id='$id'");
                    if ($ref) $row = mysqli_fetch_assoc($ref);
                } else {
                    $errorMsg = "Database error: " . mysqli_error($conn);
                }
            }
        }
    }

    // 2. CHANGE / RESET STUDENT PASSWORD
    if (isset($_POST['action_update_student_password'])) {
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($newPass) || empty($confirmPass)) {
            $errorMsg = "Please enter and confirm the new student password.";
        } elseif (strlen($newPass) < 4) {
            $errorMsg = "Password must be at least 4 characters long.";
        } elseif ($newPass !== $confirmPass) {
            $errorMsg = "New password and confirmation password do not match.";
        } else {
            $newHashed = password_hash($newPass, PASSWORD_DEFAULT);
            $pStmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
            if ($pStmt) {
                mysqli_stmt_bind_param($pStmt, "si", $newHashed, $id);
                mysqli_stmt_execute($pStmt);
                mysqli_stmt_close($pStmt);
                $successMsg = "Student password successfully changed! The student can now log in using the new password.";
            } else {
                $errorMsg = "Database error: " . mysqli_error($conn);
            }
        }
    }
}

// Total Orders
$orderQuery = mysqli_query($conn, "SELECT COUNT(*) AS total_orders FROM orders WHERE user_id='$id'");
$orderData = mysqli_fetch_assoc($orderQuery);

// Total Amount Spent (Completed Orders)
$totalQuery = mysqli_query($conn, "SELECT IFNULL(SUM(total_amount), 0) AS total_amount FROM orders WHERE user_id='$id' AND status='Completed'");
$totalData = mysqli_fetch_assoc($totalQuery);

$activePage = 'customers';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile & Security - College Canteen Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <link rel="stylesheet" href="css/admin_material.css">
    <style>
        .edit-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 25px;
        }
        @media (max-width: 900px) {
            .edit-grid {
                grid-template-columns: 1fr;
            }
        }
        .form-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .form-card-header {
            padding: 16px 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .form-card-header h3 {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }
        .form-card-body {
            padding: 20px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
        }
        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-wrapper i.left-icon {
            position: absolute;
            left: 14px;
            color: #94a3b8;
            font-size: 14px;
        }
        .input-wrapper input, .input-wrapper select {
            width: 100%;
            padding: 10px 40px 10px 40px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            color: #0f172a;
            outline: none;
            transition: all 0.2s;
            background: #ffffff;
        }
        .input-wrapper input:focus, .input-wrapper select:focus {
            border-color: #0284c7;
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
        }
        .toggle-pwd-btn {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 4px;
            font-size: 14px;
        }
        .toggle-pwd-btn:hover {
            color: #0284c7;
        }
        .alert-box {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 500;
        }
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
    </style>
</head>
<body>

<?php include("header_nav.php"); ?>

<main class="admin-container">

    <div class="admin-header-row">
        <div>
            <h1 class="admin-page-title">
                <i class="fa-solid fa-id-card"></i> Student Profile: <?php echo htmlspecialchars($row['name']); ?>
            </h1>
            <p class="admin-subtitle">Manage student profile credentials, password, and order history</p>
        </div>

        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <a href="#securitySection" class="btn-material btn-primary" style="background:#0284c7; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                <i class="fa-solid fa-key"></i> Change Login & Password
            </a>
            <a href="customers.php" class="btn-material btn-primary" style="text-decoration:none;">
                <i class="fa-solid fa-arrow-left"></i> Back to Customers
            </a>
        </div>
    </div>

    <?php if (!empty($successMsg)): ?>
        <div class="alert-box alert-success">
            <i class="fa-solid fa-circle-check" style="font-size:18px;"></i>
            <span><?php echo $successMsg; ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMsg)): ?>
        <div class="alert-box alert-error">
            <i class="fa-solid fa-triangle-exclamation" style="font-size:18px;"></i>
            <span><?php echo $errorMsg; ?></span>
        </div>
    <?php endif; ?>

    <!-- SUMMARY MINI CARDS -->
    <div class="cards-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom:25px;">
        <div class="info-card card-customers">
            <div class="card-header">
                <span class="card-title">Register Number</span>
                <i class="fa-solid fa-graduation-cap card-icon"></i>
            </div>
            <div class="card-value" style="font-size:24px;"><?php echo htmlspecialchars($row['regno'] ?: 'N/A'); ?></div>
        </div>

        <div class="info-card card-orders">
            <div class="card-header">
                <span class="card-title">Total Orders</span>
                <i class="fa-solid fa-cart-shopping card-icon"></i>
            </div>
            <div class="card-value"><?php echo $orderData['total_orders']; ?></div>
        </div>

        <div class="info-card card-sales">
            <div class="card-header">
                <span class="card-title">Total Spent</span>
                <i class="fa-solid fa-indian-rupee-sign card-icon"></i>
            </div>
            <div class="card-value">₹<?php echo number_format((float)$totalData['total_amount'], 2); ?></div>
        </div>
    </div>

    <!-- STUDENT SECURITY & EDIT SECTION -->
    <div id="securitySection" class="edit-grid">
        
        <!-- CARD 1: EDIT STUDENT USER DETAILS & LOGIN EMAIL -->
        <div class="form-card">
            <div class="form-card-header">
                <div style="width:36px; height:36px; border-radius:8px; background:#0284c7; color:#fff; display:flex; align-items:center; justify-content:center; font-size:16px;">
                    <i class="fa-solid fa-user-pen"></i>
                </div>
                <div>
                    <h3>Edit Student User Info</h3>
                    <p>Update student's name, register number, department, or login email</p>
                </div>
            </div>
            <div class="form-card-body">
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="action_update_student_profile" value="1">

                    <div class="form-group">
                        <label for="student_name">Full Name <span style="color:#ef4444;">*</span></label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-user left-icon"></i>
                            <input type="text" id="student_name" name="name" required value="<?php echo htmlspecialchars($row['name']); ?>" placeholder="Enter student's full name">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="student_regno">Register Number</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-graduation-cap left-icon"></i>
                            <input type="text" id="student_regno" name="regno" value="<?php echo htmlspecialchars($row['regno'] ?? ''); ?>" placeholder="e.g. 13562">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="student_department">Department</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-building-columns left-icon"></i>
                            <input type="text" id="student_department" name="department" value="<?php echo htmlspecialchars($row['department'] ?? 'General'); ?>" placeholder="e.g. BCA, CSE, B.Com">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="student_email">Login Email Address <span style="color:#ef4444;">*</span></label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-envelope left-icon"></i>
                            <input type="email" id="student_email" name="email" required value="<?php echo htmlspecialchars($row['email']); ?>" placeholder="student@example.com">
                        </div>
                        <small style="font-size:11px; color:#64748b; margin-top:4px; display:block;">This email is used by the student to log in on the app & website.</small>
                    </div>

                    <button type="submit" class="btn-material btn-primary" style="width:100%; background:#0284c7; border:none; padding:11px; font-weight:600; font-size:14px; border-radius:8px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; gap:8px;">
                        <i class="fa-solid fa-check"></i> Save Student Details
                    </button>
                </form>
            </div>
        </div>

        <!-- CARD 2: RESET / CHANGE STUDENT PASSWORD -->
        <div class="form-card">
            <div class="form-card-header">
                <div style="width:36px; height:36px; border-radius:8px; background:#16a34a; color:#fff; display:flex; align-items:center; justify-content:center; font-size:16px;">
                    <i class="fa-solid fa-key"></i>
                </div>
                <div>
                    <h3>Change / Reset Student Password</h3>
                    <p>Set a new password anytime with instant eye reveal toggle</p>
                </div>
            </div>
            <div class="form-card-body">
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="action_update_student_password" value="1">

                    <div class="form-group">
                        <label for="new_student_password">New Password <span style="color:#ef4444;">*</span></label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-lock left-icon"></i>
                            <input type="password" id="new_student_password" name="new_password" required minlength="4" placeholder="Enter new password for student">
                            <button type="button" class="toggle-pwd-btn" onclick="togglePasswordVisibility('new_student_password', this)" aria-label="Toggle password">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                        <small style="font-size:11px; color:#64748b; margin-top:4px; display:block;">Minimum 4 characters.</small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_student_password">Confirm New Password <span style="color:#ef4444;">*</span></label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-check-double left-icon"></i>
                            <input type="password" id="confirm_student_password" name="confirm_password" required minlength="4" placeholder="Re-type new password">
                            <button type="button" class="toggle-pwd-btn" onclick="togglePasswordVisibility('confirm_student_password', this)" aria-label="Toggle password">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div style="background:#f1f5f9; border-radius:8px; padding:12px; margin-bottom:18px; font-size:12px; color:#475569; line-height:1.5;">
                        <i class="fa-solid fa-circle-info" style="color:#0284c7; margin-right:4px;"></i>
                        The student can immediately log in with this new password on both the Android Mobile App and the Website.
                    </div>

                    <button type="submit" class="btn-material btn-success" style="width:100%; background:#16a34a; border:none; padding:11px; font-weight:600; font-size:14px; border-radius:8px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; gap:8px;">
                        <i class="fa-solid fa-lock"></i> Set New Student Password
                    </button>
                </form>
            </div>
        </div>

    </div>

    <!-- RECENT ORDER HISTORY (Structured Table with Red-Orange Header) -->
    <?php
    $custOrders = mysqli_query($conn, "SELECT * FROM orders WHERE user_id='$id' ORDER BY id DESC LIMIT 15");
    ?>
    <div class="table-card">
        <div style="padding:14px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:16px; font-weight:700; color:#0f172a;">
                <i class="fa-solid fa-clock-rotate-left" style="color:#ea580c; margin-right:6px;"></i> Recent Order History
            </h3>
        </div>
        <div class="table-responsive">
            <table class="material-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Amount</th>
                        <th>Payment Status</th>
                        <th>Kitchen Status</th>
                        <th>Date & Time</th>
                        <th style="text-align:right;">Invoice</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($custOrders && mysqli_num_rows($custOrders) > 0): ?>
                        <?php while ($co = mysqli_fetch_assoc($custOrders)): 
                            $st = trim($co['status']);
                            $isPaid = (strtolower($st) === 'completed' || strtolower($st) === 'paid');
                            $fst = trim($co['food_status'] ?? 'Preparing');
                        ?>
                        <tr>
                            <td><strong>#<?php echo (int)$co['id']; ?></strong></td>
                            <td><strong style="color:#0f172a;">₹<?php echo number_format((float)$co['total_amount'], 2); ?></strong></td>
                            <td>
                                <span class="badge-status <?php echo $isPaid ? 'badge-completed' : 'badge-pending'; ?>">
                                    <?php if ($isPaid): ?>
                                        <i class="fa-solid fa-circle-check"></i> Completed
                                    <?php else: ?>
                                        <i class="fa-solid fa-clock"></i> <?php echo htmlspecialchars($st); ?>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge-status <?php echo strtolower($fst) === 'delivered' ? 'badge-completed' : 'badge-pending'; ?>">
                                    <?php echo htmlspecialchars($fst); ?>
                                </span>
                            </td>
                            <td style="color:#64748b; font-size:13px;"><?php echo htmlspecialchars($co['order_date'] ?? '-'); ?></td>
                            <td style="text-align:right;">
                                <a href="../invoice.php?order_id=<?php echo (int)$co['id']; ?>" target="_blank" class="btn-material btn-primary" style="padding:5px 12px; font-size:12px;">
                                    <i class="fa-solid fa-file-invoice"></i> View Invoice
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding:30px; color:#94a3b8;">
                                No orders placed by this student yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<script>
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) {
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        }
    } else {
        input.type = 'password';
        if (icon) {
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
}
</script>

</body>
</html>