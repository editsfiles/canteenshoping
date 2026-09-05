<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include("php/db.php");

$user_id = (int)$_SESSION['user_id'];
$successMsg = "";
$errorMsg = "";

// 1. Fetch current student details
$stmt = mysqli_prepare($conn, "SELECT id, name, regno, department, email, password, created_at FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// 2. Fetch Order Statistics
$orderStatsQuery = mysqli_query($conn, "
    SELECT 
        COUNT(id) AS total_orders,
        IFNULL(SUM(CASE WHEN status IN ('Completed','Paid') THEN total_amount ELSE 0 END), 0) AS total_spent
    FROM orders 
    WHERE user_id = '$user_id'
");
$orderStats = mysqli_fetch_assoc($orderStatsQuery);
$totalOrders = (int)($orderStats['total_orders'] ?? 0);
$totalSpent = (float)($orderStats['total_spent'] ?? 0);

// Helper to verify student password
function verifyStudentPassword($input, $stored) {
    return password_verify($input, $stored) || $input === $stored;
}

// -------------------------------------------------------------
// POST HANDLERS
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // A. UPDATE STUDENT PROFILE INFORMATION
    if (isset($_POST['action_update_profile'])) {
        $name = trim($_POST['name'] ?? '');
        $regno = trim($_POST['regno'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($name) || empty($email)) {
            $errorMsg = "Full Name and Email Address are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = "Please provide a valid email address.";
        } else {
            // Check if email already used by another student
            $chk = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            if ($chk) {
                mysqli_stmt_bind_param($chk, "si", $email, $user_id);
                mysqli_stmt_execute($chk);
                $chkRes = mysqli_stmt_get_result($chk);
                if (mysqli_num_rows($chkRes) > 0) {
                    $errorMsg = "This email is already registered with another account.";
                }
                mysqli_stmt_close($chk);
            }

            if (empty($errorMsg)) {
                $up = mysqli_prepare($conn, "UPDATE users SET name = ?, regno = ?, department = ?, email = ? WHERE id = ?");
                if ($up) {
                    mysqli_stmt_bind_param($up, "ssssi", $name, $regno, $department, $email, $user_id);
                    mysqli_stmt_execute($up);
                    mysqli_stmt_close($up);

                    // Update session
                    $_SESSION['user_name'] = $name;
                    $_SESSION['name'] = $name;
                    $_SESSION['user_email'] = $email;

                    $user['name'] = $name;
                    $user['regno'] = $regno;
                    $user['department'] = $department;
                    $user['email'] = $email;

                    $successMsg = "Your profile details have been successfully updated!";
                } else {
                    $errorMsg = "Database error: " . mysqli_error($conn);
                }
            }
        }
    }

    // B. CHANGE STUDENT PASSWORD
    if (isset($_POST['action_change_password'])) {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
            $errorMsg = "Please fill in all password fields.";
        } elseif (strlen($newPass) < 4) {
            $errorMsg = "New password must be at least 4 characters long.";
        } elseif ($newPass !== $confirmPass) {
            $errorMsg = "New password and confirmation password do not match.";
        } elseif (!verifyStudentPassword($currentPass, $user['password'])) {
            $errorMsg = "Your current password is incorrect. Please try again.";
        } else {
            $newHashed = password_hash($newPass, PASSWORD_DEFAULT);
            $pStmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
            if ($pStmt) {
                mysqli_stmt_bind_param($pStmt, "si", $newHashed, $user_id);
                mysqli_stmt_execute($pStmt);
                mysqli_stmt_close($pStmt);

                $user['password'] = $newHashed;
                $successMsg = "Your password has been changed successfully! Remember to use your new password next time you log in.";
            } else {
                $errorMsg = "Database error: " . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile & Settings - College Canteen</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#16a34a">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <style>
        body {
            background-color: #f1f5f9;
            color: #0f172a;
            font-family: Arial, Helvetica, sans-serif;
            min-height: 100vh;
        }
        .container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px;
        }
        /* Profile Banner Card */
        .profile-hero-card {
            background: linear-gradient(135deg, #15803d 0%, #16a34a 50%, #22c55e 100%);
            color: #ffffff;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(22, 163, 74, 0.25);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 25px;
        }
        .hero-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .avatar-circle {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: #ffffff;
            color: #16a34a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: bold;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        .hero-title h1 {
            margin: 0;
            font-size: 24px;
            color: #ffffff;
            font-weight: 700;
        }
        .hero-title p {
            margin: 4px 0 0 0;
            font-size: 14px;
            color: #dcfce7;
        }
        .hero-stats {
            display: flex;
            gap: 15px;
        }
        .stat-badge {
            background: rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(8px);
            padding: 10px 18px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.25);
        }
        .stat-badge .stat-num {
            font-size: 20px;
            font-weight: bold;
            color: #ffffff;
        }
        .stat-badge .stat-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #dcfce7;
        }

        /* 2 Column Settings Grid */
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 40px;
        }
        @media (max-width: 800px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
            .profile-hero-card {
                padding: 20px;
            }
            .hero-left {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            .hero-stats {
                width: 100%;
                justify-content: space-between;
            }
        }
        .card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .card-header {
            padding: 18px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .card-header .icon-wrap {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #ffffff;
        }
        .card-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
        }
        .card-header p {
            margin: 2px 0 0 0;
            font-size: 12px;
            color: #64748b;
        }
        .card-body {
            padding: 24px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
        }
        .input-box {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-box i.left-icon {
            position: absolute;
            left: 14px;
            color: #94a3b8;
            font-size: 14px;
        }
        .input-box input {
            width: 100%;
            padding: 11px 40px 11px 40px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            color: #0f172a;
            outline: none;
            transition: all 0.2s;
            background: #ffffff;
        }
        .input-box input:focus {
            border-color: #16a34a;
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.15);
        }
        .eye-btn {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 4px;
            font-size: 14px;
        }
        .eye-btn:hover {
            color: #16a34a;
        }
        .btn-submit {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            color: #ffffff;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: 0.2s ease;
        }
        .btn-green {
            background: #16a34a;
        }
        .btn-green:hover {
            background: #15803d;
        }
        .btn-blue {
            background: #0284c7;
        }
        .btn-blue:hover {
            background: #0369a1;
        }
        .alert {
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

<header>
    <div class="logo">
        🍽 College Canteen
    </div>
    <nav>
        <a href="index.php">Home</a>
        <a href="menu.php">Menu</a>
        <a href="cart.php">Cart</a>
        <a href="my_orders.php">My Orders</a>
        <a href="profile.php" style="color:#ffffff; text-decoration:underline;">Profile</a>
        <a href="contact.php">Contact</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<div class="container">

    <?php if (!empty($successMsg)): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check" style="font-size:18px;"></i>
            <span><?php echo htmlspecialchars($successMsg); ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-error">
            <i class="fa-solid fa-triangle-exclamation" style="font-size:18px;"></i>
            <span><?php echo htmlspecialchars($errorMsg); ?></span>
        </div>
    <?php endif; ?>

    <!-- HERO PROFILE CARD -->
    <div class="profile-hero-card">
        <div class="hero-left">
            <div class="avatar-circle">
                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
            </div>
            <div class="hero-title">
                <h1><?php echo htmlspecialchars($user['name']); ?></h1>
                <p>
                    Reg No: <strong><?php echo htmlspecialchars($user['regno'] ?: 'N/A'); ?></strong> &bull;
                    Dept: <strong><?php echo htmlspecialchars($user['department'] ?: 'General'); ?></strong>
                </p>
                <p style="font-size:12px; color:#bbf7d0; margin-top:2px;">
                    <i class="fa-solid fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
                </p>
            </div>
        </div>

        <div class="hero-stats">
            <div class="stat-badge">
                <div class="stat-num"><?php echo $totalOrders; ?></div>
                <div class="stat-label">Orders</div>
            </div>
            <div class="stat-badge">
                <div class="stat-num">₹<?php echo number_format($totalSpent, 2); ?></div>
                <div class="stat-label">Total Spent</div>
            </div>
        </div>
    </div>

    <!-- 2 COLUMN SETTINGS FORMS -->
    <div class="settings-grid">
        
        <!-- CARD 1: EDIT PROFILE DETAILS -->
        <div class="card">
            <div class="card-header">
                <div class="icon-wrap" style="background:#0284c7;">
                    <i class="fa-solid fa-user-pen"></i>
                </div>
                <div>
                    <h3>Edit Profile Details</h3>
                    <p>Update your personal information & login email</p>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="action_update_profile" value="1">

                    <div class="form-group">
                        <label for="student_name">Full Name <span style="color:#ef4444;">*</span></label>
                        <div class="input-box">
                            <i class="fa-solid fa-user left-icon"></i>
                            <input type="text" id="student_name" name="name" required value="<?php echo htmlspecialchars($user['name']); ?>" placeholder="Enter your full name">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="student_regno">Register Number</label>
                        <div class="input-box">
                            <i class="fa-solid fa-graduation-cap left-icon"></i>
                            <input type="text" id="student_regno" name="regno" value="<?php echo htmlspecialchars($user['regno'] ?? ''); ?>" placeholder="e.g. 13562">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="student_dept">Department</label>
                        <div class="input-box">
                            <i class="fa-solid fa-building-columns left-icon"></i>
                            <input type="text" id="student_dept" name="department" value="<?php echo htmlspecialchars($user['department'] ?? 'General'); ?>" placeholder="e.g. BCA, CSE, B.Com">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="student_email">Login Email Address <span style="color:#ef4444;">*</span></label>
                        <div class="input-box">
                            <i class="fa-solid fa-envelope left-icon"></i>
                            <input type="email" id="student_email" name="email" required value="<?php echo htmlspecialchars($user['email']); ?>" placeholder="yourname@example.com">
                        </div>
                        <small style="font-size:11px; color:#64748b; margin-top:4px; display:block;">Used to log in to your canteen account on mobile app & website.</small>
                    </div>

                    <button type="submit" class="btn-submit btn-blue">
                        <i class="fa-solid fa-check"></i> Save Profile Details
                    </button>
                </form>
            </div>
        </div>

        <!-- CARD 2: CHANGE PASSWORD -->
        <div class="card">
            <div class="card-header">
                <div class="icon-wrap" style="background:#16a34a;">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <div>
                    <h3>Change Password</h3>
                    <p>Update your secret login password anytime</p>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="action_change_password" value="1">

                    <div class="form-group">
                        <label for="current_password">Current Password <span style="color:#ef4444;">*</span></label>
                        <div class="input-box">
                            <i class="fa-solid fa-shield-halved left-icon"></i>
                            <input type="password" id="current_password" name="current_password" required placeholder="Enter current password">
                            <button type="button" class="eye-btn" onclick="togglePassword('current_password', this)" aria-label="Toggle password">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="new_password">New Password <span style="color:#ef4444;">*</span></label>
                        <div class="input-box">
                            <i class="fa-solid fa-key left-icon"></i>
                            <input type="password" id="new_password" name="new_password" required minlength="4" placeholder="Enter new password">
                            <button type="button" class="eye-btn" onclick="togglePassword('new_password', this)" aria-label="Toggle password">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                        <small style="font-size:11px; color:#64748b; margin-top:4px; display:block;">Minimum 4 characters.</small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password <span style="color:#ef4444;">*</span></label>
                        <div class="input-box">
                            <i class="fa-solid fa-check-double left-icon"></i>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="4" placeholder="Re-type new password">
                            <button type="button" class="eye-btn" onclick="togglePassword('confirm_password', this)" aria-label="Toggle password">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit btn-green">
                        <i class="fa-solid fa-lock"></i> Update My Password
                    </button>
                </form>
            </div>
        </div>

    </div>

</div>

<script>
function togglePassword(inputId, btn) {
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
