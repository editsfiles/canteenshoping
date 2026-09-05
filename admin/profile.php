<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . "/../php/db.php";

$adminUsername = $_SESSION['admin'];
$successMsg = "";
$errorMsg = "";

// 1. Fetch current admin details from database
$adminId = $_SESSION['admin_id'] ?? 1;
$currentDbAdmin = null;

$stmt = mysqli_prepare($conn, "SELECT id, username, password, created_at FROM admins WHERE username = ? LIMIT 1");
if (!$stmt) {
    $stmt = mysqli_prepare($conn, "SELECT id, username, password, created_at FROM admin WHERE username = ? LIMIT 1");
}

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $adminUsername);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $currentDbAdmin = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
}

// Fallback by ID if username was just changed
if (!$currentDbAdmin && $adminId) {
    $stmt = mysqli_prepare($conn, "SELECT id, username, password, created_at FROM admins WHERE id = ? LIMIT 1");
    if (!$stmt) {
        $stmt = mysqli_prepare($conn, "SELECT id, username, password, created_at FROM admin WHERE id = ? LIMIT 1");
    }
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $adminId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $currentDbAdmin = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
    }
}

// Helper to verify admin password
function verifyAdminPassword($inputPassword, $storedPassword) {
    return password_verify($inputPassword, $storedPassword) || $inputPassword === $storedPassword;
}

// -------------------------------------------------------------
// ACTION 1: UPDATE USERNAME
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_username'])) {
    $newUsername = trim($_POST['new_username'] ?? '');
    $confirmPass = $_POST['current_password_for_user'] ?? '';

    if (empty($newUsername) || empty($confirmPass)) {
        $errorMsg = "Please enter both the new username and your current password.";
    } elseif (strlen($newUsername) < 3) {
        $errorMsg = "Username must be at least 3 characters long.";
    } elseif (!preg_match('/^[a-zA-Z0-9_.-]+$/', $newUsername)) {
        $errorMsg = "Username can only contain letters, numbers, underscores, and hyphens.";
    } elseif (!$currentDbAdmin || !verifyAdminPassword($confirmPass, $currentDbAdmin['password'])) {
        $errorMsg = "Current password is incorrect. Username was not changed.";
    } else {
        // Check if new username is already taken by another admin
        $checkStmt = mysqli_prepare($conn, "SELECT id FROM admins WHERE username = ? AND id != ? LIMIT 1");
        if ($checkStmt) {
            mysqli_stmt_bind_param($checkStmt, "si", $newUsername, $currentDbAdmin['id']);
            mysqli_stmt_execute($checkStmt);
            $checkRes = mysqli_stmt_get_result($checkStmt);
            if (mysqli_num_rows($checkRes) > 0) {
                $errorMsg = "The username '" . htmlspecialchars($newUsername) . "' is already in use.";
            }
            mysqli_stmt_close($checkStmt);
        }

        if (empty($errorMsg)) {
            // Update in both admins and admin tables
            $u1 = mysqli_prepare($conn, "UPDATE admins SET username = ? WHERE id = ?");
            if ($u1) {
                mysqli_stmt_bind_param($u1, "si", $newUsername, $currentDbAdmin['id']);
                mysqli_stmt_execute($u1);
                mysqli_stmt_close($u1);
            }
            $u2 = mysqli_prepare($conn, "UPDATE admin SET username = ? WHERE id = ?");
            if ($u2) {
                mysqli_stmt_bind_param($u2, "si", $newUsername, $currentDbAdmin['id']);
                mysqli_stmt_execute($u2);
                mysqli_stmt_close($u2);
            }

            $_SESSION['admin'] = $newUsername;
            $adminUsername = $newUsername;
            $currentDbAdmin['username'] = $newUsername;
            $successMsg = "Admin username successfully updated to '" . htmlspecialchars($newUsername) . "'!";
        }
    }
}

// -------------------------------------------------------------
// ACTION 2: UPDATE PASSWORD
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_password'])) {
    $currentPass = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
        $errorMsg = "Please fill in all password fields.";
    } elseif (strlen($newPass) < 4) {
        $errorMsg = "New password must be at least 4 characters long.";
    } elseif ($newPass !== $confirmPass) {
        $errorMsg = "New password and confirmation password do not match.";
    } elseif (!$currentDbAdmin || !verifyAdminPassword($currentPass, $currentDbAdmin['password'])) {
        $errorMsg = "Current password is incorrect. Password was not changed.";
    } else {
        // Hash the new password with bcrypt
        $newHashed = password_hash($newPass, PASSWORD_DEFAULT);

        $up1 = mysqli_prepare($conn, "UPDATE admins SET password = ? WHERE id = ?");
        if ($up1) {
            mysqli_stmt_bind_param($up1, "si", $newHashed, $currentDbAdmin['id']);
            mysqli_stmt_execute($up1);
            mysqli_stmt_close($up1);
        }

        $up2 = mysqli_prepare($conn, "UPDATE admin SET password = ? WHERE id = ?");
        if ($up2) {
            mysqli_stmt_bind_param($up2, "si", $newHashed, $currentDbAdmin['id']);
            mysqli_stmt_execute($up2);
            mysqli_stmt_close($up2);
        }

        $currentDbAdmin['password'] = $newHashed;
        $successMsg = "Admin password updated successfully! Use your new password on your next login.";
    }
}

$activePage = 'profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile & Password Settings - College Canteen</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <link rel="stylesheet" href="css/admin_material.css">
    <style>
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 20px;
        }
        @media (max-width: 900px) {
            .profile-grid {
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
            padding: 18px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .form-card-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
        }
        .form-card-header p {
            margin: 2px 0 0 0;
            font-size: 12px;
            color: #64748b;
        }
        .form-card-body {
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
        .input-wrapper input {
            width: 100%;
            padding: 11px 40px 11px 40px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            color: #0f172a;
            transition: all 0.2s;
            outline: none;
        }
        .input-wrapper input:focus {
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
        .admin-badge-card {
            background: linear-gradient(135deg, #1e1b4b, #312e81);
            color: #ffffff;
            border-radius: 16px;
            padding: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: 0 10px 25px -5px rgba(49, 46, 129, 0.3);
            margin-bottom: 24px;
        }
    </style>
</head>
<body>

<?php include("header_nav.php"); ?>

<main class="admin-container">

    <div class="admin-header-row">
        <div>
            <h1 class="admin-page-title">
                <i class="fa-solid fa-user-shield"></i> Admin Profile & Security
            </h1>
            <p class="admin-subtitle">Change your Administrator username and password anytime</p>
        </div>
        <div>
            <a href="dashboard.php" class="btn-material" style="background:#475569; color:#fff; text-decoration:none;">
                <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
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

    <!-- Current Admin Identity Banner -->
    <div class="admin-badge-card">
        <div style="display:flex; align-items:center; gap:16px;">
            <div style="width:56px; height:56px; border-radius:12px; background:linear-gradient(135deg, #0284c7, #06b6d4); display:flex; align-items:center; justify-content:center; font-size:24px; color:#fff; box-shadow:0 4px 12px rgba(6, 182, 212, 0.4);">
                <i class="fa-solid fa-crown"></i>
            </div>
            <div>
                <div style="font-size:12px; color:#a5f3fc; text-transform:uppercase; letter-spacing:1px; font-weight:700;">Active Account</div>
                <h2 style="margin:2px 0 0 0; font-size:22px; font-weight:700; color:#ffffff;">
                    <?php echo htmlspecialchars($adminUsername); ?>
                </h2>
                <div style="font-size:12px; color:#cbd5e1; margin-top:4px;">
                    Role: <strong style="color:#67e8f9;">Super Administrator</strong> &bull; Access: <strong>Full System Control</strong>
                </div>
            </div>
        </div>
        <div style="text-align:right;">
            <span style="background:rgba(255,255,255,0.12); padding:6px 14px; border-radius:999px; font-size:12px; font-weight:600; color:#e2e8f0; display:inline-flex; align-items:center; gap:6px;">
                <i class="fa-solid fa-shield-halved" style="color:#34d399;"></i> Protected by Security Protocol
            </span>
        </div>
    </div>

    <!-- 2 Forms Side-by-Side: Change Username & Change Password -->
    <div class="profile-grid">
        
        <!-- FORM 1: CHANGE USERNAME -->
        <div class="form-card">
            <div class="form-card-header" style="background:#f8fafc;">
                <div style="width:36px; height:36px; border-radius:8px; background:#0284c7; color:#fff; display:flex; align-items:center; justify-content:center; font-size:16px;">
                    <i class="fa-solid fa-user-pen"></i>
                </div>
                <div>
                    <h3>Change Admin Username</h3>
                    <p>Update the login username used to access this admin portal</p>
                </div>
            </div>
            <div class="form-card-body">
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="action_update_username" value="1">
                    
                    <div class="form-group">
                        <label>Current Username</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-user-lock left-icon"></i>
                            <input type="text" value="<?php echo htmlspecialchars($adminUsername); ?>" disabled style="background:#f1f5f9; color:#64748b; cursor:not-allowed;">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="new_username">New Username <span style="color:#ef4444;">*</span></label>
                        <div class="input-wrapper">
                            <i class="fa-regular fa-user left-icon"></i>
                            <input type="text" id="new_username" name="new_username" required placeholder="Enter new username" minlength="3" autocomplete="off">
                        </div>
                        <small style="font-size:11px; color:#64748b; margin-top:4px; display:block;">Minimum 3 characters (letters, numbers, underscore).</small>
                    </div>

                    <div class="form-group">
                        <label for="current_password_for_user">Current Password <span style="color:#ef4444;">*</span></label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-lock left-icon"></i>
                            <input type="password" id="current_password_for_user" name="current_password_for_user" required placeholder="Enter current password to verify">
                            <button type="button" class="toggle-pwd-btn" onclick="togglePasswordVisibility('current_password_for_user', this)" aria-label="Toggle password">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-material btn-primary" style="width:100%; background:#0284c7; border:none; padding:12px; font-weight:600; font-size:14px; border-radius:8px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; gap:8px;">
                        <i class="fa-solid fa-check"></i> Save New Username
                    </button>
                </form>
            </div>
        </div>

        <!-- FORM 2: CHANGE PASSWORD -->
        <div class="form-card">
            <div class="form-card-header" style="background:#f8fafc;">
                <div style="width:36px; height:36px; border-radius:8px; background:#16a34a; color:#fff; display:flex; align-items:center; justify-content:center; font-size:16px;">
                    <i class="fa-solid fa-key"></i>
                </div>
                <div>
                    <h3>Change Admin Password</h3>
                    <p>Update your secret security password with instant reveal toggle</p>
                </div>
            </div>
            <div class="form-card-body">
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="action_update_password" value="1">

                    <div class="form-group">
                        <label for="current_password">Current Password <span style="color:#ef4444;">*</span></label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-shield-halved left-icon"></i>
                            <input type="password" id="current_password" name="current_password" required placeholder="Enter current password">
                            <button type="button" class="toggle-pwd-btn" onclick="togglePasswordVisibility('current_password', this)" aria-label="Toggle password">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="new_password">New Password <span style="color:#ef4444;">*</span></label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-lock left-icon"></i>
                            <input type="password" id="new_password" name="new_password" required placeholder="Enter new password" minlength="4">
                            <button type="button" class="toggle-pwd-btn" onclick="togglePasswordVisibility('new_password', this)" aria-label="Toggle password">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                        <small style="font-size:11px; color:#64748b; margin-top:4px; display:block;">Must be at least 4 characters.</small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password <span style="color:#ef4444;">*</span></label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-check-double left-icon"></i>
                            <input type="password" id="confirm_password" name="confirm_password" required placeholder="Re-type new password" minlength="4">
                            <button type="button" class="toggle-pwd-btn" onclick="togglePasswordVisibility('confirm_password', this)" aria-label="Toggle password">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-material btn-success" style="width:100%; background:#16a34a; border:none; padding:12px; font-weight:600; font-size:14px; border-radius:8px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; gap:8px;">
                        <i class="fa-solid fa-lock"></i> Update Admin Password
                    </button>
                </form>
            </div>
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
