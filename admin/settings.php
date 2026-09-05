<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . "/../php/db.php";
require_once __DIR__ . "/../config_uropay.php";

$adminUsername = $_SESSION['admin'];
$adminId = $_SESSION['admin_id'] ?? 1;

$successMsg = "";
$errorMsg = "";

// 1. Fetch current admin details from database
$stmt = mysqli_prepare($conn, "SELECT id, username, password, created_at FROM admins WHERE username = ? LIMIT 1");
if (!$stmt) {
    $stmt = mysqli_prepare($conn, "SELECT id, username, password, created_at FROM admin WHERE username = ? LIMIT 1");
}

$currentDbAdmin = null;
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $adminUsername);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $currentDbAdmin = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
}

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

function verifyAdminPassword($input, $stored) {
    return password_verify($input, $stored) || $input === $stored;
}

// -------------------------------------------------------------
// POST ACTIONS
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. UPDATE USERNAME
    if (isset($_POST['action_update_username'])) {
        $newUsername = trim($_POST['new_username'] ?? '');
        $currentPass = $_POST['current_password_for_user'] ?? '';

        if (empty($newUsername) || empty($currentPass)) {
            $errorMsg = "Please enter both the new username and your current password.";
        } elseif (strlen($newUsername) < 3) {
            $errorMsg = "Username must be at least 3 characters long.";
        } elseif (!preg_match('/^[a-zA-Z0-9_.-]+$/', $newUsername)) {
            $errorMsg = "Username can only contain letters, numbers, underscores, and hyphens.";
        } elseif (!$currentDbAdmin || !verifyAdminPassword($currentPass, $currentDbAdmin['password'])) {
            $errorMsg = "Current password is incorrect. Username was not changed.";
        } else {
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

    // 2. UPDATE PASSWORD
    if (isset($_POST['action_update_password'])) {
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
}

$activePage = 'settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - College Canteen Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <link rel="stylesheet" href="css/admin_material.css">
    <style>
        .settings-nav-tabs {
            display: flex;
            gap: 8px;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 24px;
            overflow-x: auto;
        }
        .settings-tab-btn {
            padding: 12px 20px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .settings-tab-btn:hover {
            color: #0284c7;
        }
        .settings-tab-btn.active {
            color: #0284c7;
            border-bottom-color: #0284c7;
            background: rgba(2, 132, 199, 0.05);
            border-radius: 8px 8px 0 0;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .grid-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        @media (max-width: 900px) {
            .grid-2col {
                grid-template-columns: 1fr;
            }
        }
        .form-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 24px;
        }
        .form-card-header {
            padding: 18px 24px;
            background: #f8fafc;
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
        .input-wrapper input, .input-wrapper select {
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
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #64748b;
            font-weight: 500;
        }
        .info-value {
            color: #0f172a;
            font-weight: 700;
        }
    </style>
</head>
<body>

<?php include("header_nav.php"); ?>

<main class="admin-container">

    <div class="admin-header-row">
        <div>
            <h1 class="admin-page-title">
                <i class="fa-solid fa-gears"></i> Canteen System & Admin Settings
            </h1>
            <p class="admin-subtitle">Manage Administrator account, UPI payments, Excel live sync, and store configurations</p>
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

    <!-- TABS NAVIGATION -->
    <div class="settings-nav-tabs">
        <button type="button" class="settings-tab-btn active" onclick="switchSettingsTab('tab-admin', this)">
            <i class="fa-solid fa-user-shield"></i> Admin Account & Password
        </button>
        <button type="button" class="settings-tab-btn" onclick="switchSettingsTab('tab-upi', this)">
            <i class="fa-solid fa-qrcode"></i> UPI & Payment Gateway
        </button>
        <button type="button" class="settings-tab-btn" onclick="switchSettingsTab('tab-excel', this)">
            <i class="fa-solid fa-file-excel"></i> Live Excel Auto-Sync
        </button>
        <button type="button" class="settings-tab-btn" onclick="switchSettingsTab('tab-store', this)">
            <i class="fa-solid fa-store"></i> Canteen Information
        </button>
    </div>

    <!-- ========================================================= -->
    <!-- TAB 1: ADMIN PROFILE & PASSWORD SETTINGS -->
    <!-- ========================================================= -->
    <div id="tab-admin" class="tab-content active">
        
        <!-- Identity Banner -->
        <div style="background:linear-gradient(135deg, #1e1b4b, #312e81); color:#fff; border-radius:16px; padding:20px 24px; margin-bottom:24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
            <div style="display:flex; align-items:center; gap:16px;">
                <div style="width:50px; height:50px; border-radius:12px; background:linear-gradient(135deg, #0284c7, #06b6d4); display:flex; align-items:center; justify-content:center; font-size:22px; color:#fff;">
                    <i class="fa-solid fa-crown"></i>
                </div>
                <div>
                    <div style="font-size:11px; color:#a5f3fc; text-transform:uppercase; letter-spacing:1px; font-weight:700;">Active Administrator</div>
                    <h2 style="margin:2px 0 0 0; font-size:20px; font-weight:700; color:#fff;"><?php echo htmlspecialchars($adminUsername); ?></h2>
                    <div style="font-size:12px; color:#cbd5e1; margin-top:2px;">Role: <strong style="color:#67e8f9;">Super Admin</strong> &bull; Status: <span style="color:#4ade80;">Active</span></div>
                </div>
            </div>
            <div>
                <span style="background:rgba(255,255,255,0.12); padding:6px 14px; border-radius:999px; font-size:12px; font-weight:600; color:#e2e8f0; display:inline-flex; align-items:center; gap:6px;">
                    <i class="fa-solid fa-shield-halved" style="color:#34d399;"></i> Full Privileges
                </span>
            </div>
        </div>

        <div class="grid-2col">
            <!-- Change Username -->
            <div class="form-card">
                <div class="form-card-header">
                    <div style="width:36px; height:36px; border-radius:8px; background:#0284c7; color:#fff; display:flex; align-items:center; justify-content:center; font-size:16px;">
                        <i class="fa-solid fa-user-pen"></i>
                    </div>
                    <div>
                        <h3>Change Admin Username</h3>
                        <p>Change your admin login username at any time</p>
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
                                <input type="password" id="current_password_for_user" name="current_password_for_user" required placeholder="Enter current password">
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

            <!-- Change Password -->
            <div class="form-card">
                <div class="form-card-header">
                    <div style="width:36px; height:36px; border-radius:8px; background:#16a34a; color:#fff; display:flex; align-items:center; justify-content:center; font-size:16px;">
                        <i class="fa-solid fa-key"></i>
                    </div>
                    <div>
                        <h3>Change Admin Password</h3>
                        <p>Change your admin secret password with reveal toggle</p>
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
                            <small style="font-size:11px; color:#64748b; margin-top:4px; display:block;">Minimum 4 characters.</small>
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
    </div>

    <!-- ========================================================= -->
    <!-- TAB 2: UPI & PAYMENT GATEWAY SETTINGS -->
    <!-- ========================================================= -->
    <div id="tab-upi" class="tab-content">
        <div class="form-card">
            <div class="form-card-header">
                <div style="width:36px; height:36px; border-radius:8px; background:#7c3aed; color:#fff; display:flex; align-items:center; justify-content:center; font-size:16px;">
                    <i class="fa-solid fa-qrcode"></i>
                </div>
                <div>
                    <h3>Merchant UPI & UroPay Gateway Configuration</h3>
                    <p>Live payment credentials receiving student food payments</p>
                </div>
            </div>
            <div class="form-card-body">
                <div class="info-row">
                    <span class="info-label">Merchant Active UPI VPA:</span>
                    <span class="info-value" style="font-family:monospace; background:#f1f5f9; padding:4px 10px; border-radius:6px; color:#7c3aed; font-size:15px;">
                        <?php echo htmlspecialchars(CANTEEN_UPI_ID); ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Payment Receiver Name:</span>
                    <span class="info-value">College Canteen</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Default Currency:</span>
                    <span class="info-value">INR (₹ Indian Rupee)</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Supported Apps:</span>
                    <span class="info-value" style="color:#059669;">Google Pay, PhonePe, Paytm, BHIM, slice, Navi, Amazon Pay</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Gateway API Host:</span>
                    <span class="info-value" style="font-family:monospace;"><?php echo htmlspecialchars(UROPAY_API_URL); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Webhook Callback URL:</span>
                    <span class="info-value" style="font-family:monospace;"><?php echo htmlspecialchars(UROPAY_WEBHOOK_URL); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Payment Security:</span>
                    <span class="info-value" style="color:#16a34a;"><i class="fa-solid fa-circle-check"></i> Strict Bank UTR Hash &amp; Status Guard Active</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- TAB 3: LIVE EXCEL AUTO-SYNC SETTINGS -->
    <!-- ========================================================= -->
    <div id="tab-excel" class="tab-content">
        <div class="form-card">
            <div class="form-card-header">
                <div style="width:36px; height:36px; border-radius:8px; background:#16a34a; color:#fff; display:flex; align-items:center; justify-content:center; font-size:16px;">
                    <i class="fa-solid fa-file-excel"></i>
                </div>
                <div>
                    <h3>Live Excel Database Sync Connector</h3>
                    <p>Connect Microsoft Excel for real-time automatic registration feeds</p>
                </div>
            </div>
            <div class="form-card-body">
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:18px; margin-bottom:20px;">
                    <h4 style="margin:0 0 8px 0; color:#0f172a; font-size:15px;">Option 1: One-Click Web Query Connector (.iqy)</h4>
                    <p style="margin:0 0 12px 0; font-size:13px; color:#64748b;">
                        Pre-configured file for Microsoft Excel with automatic background database queries enabled.
                    </p>
                    <a href="export_registrations_excel.php?format=iqy" class="btn-material btn-success" style="text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
                        <i class="fa-solid fa-download"></i> Download canteen_registrations_live.iqy
                    </a>
                </div>

                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:18px;">
                    <h4 style="margin:0 0 8px 0; color:#0f172a; font-size:15px;">Option 2: Excel Power Query "From Web" Live URL</h4>
                    <p style="margin:0 0 10px 0; font-size:13px; color:#64748b;">
                        Paste into Excel: <strong>Data &rarr; From Web</strong> and set connection properties to refresh every 1 minute.
                    </p>
                    <div style="display:flex; gap:8px;">
                        <input type="text" id="liveSyncUrlInput" readonly value="<?php 
                            $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                            echo htmlspecialchars($proto . $host . dirname($_SERVER['SCRIPT_NAME']) . '/export_registrations_excel.php?format=web&key=canteen_live_sync');
                        ?>" style="flex:1; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-family:monospace; font-size:12px; background:#fff; color:#334155;">
                        <button type="button" onclick="copyLiveSyncUrl()" id="copySyncBtn" style="background:#0f172a; color:#fff; border:none; padding:8px 16px; border-radius:6px; font-weight:600; font-size:13px; cursor:pointer;">
                            <i class="fa-regular fa-copy"></i> Copy Link
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- TAB 4: CANTEEN INFORMATION SETTINGS -->
    <!-- ========================================================= -->
    <div id="tab-store" class="tab-content">
        <div class="form-card">
            <div class="form-card-header">
                <div style="width:36px; height:36px; border-radius:8px; background:#f97316; color:#fff; display:flex; align-items:center; justify-content:center; font-size:16px;">
                    <i class="fa-solid fa-store"></i>
                </div>
                <div>
                    <h3>Campus Canteen Details</h3>
                    <p>Operating schedules and student customer service contacts</p>
                </div>
            </div>
            <div class="form-card-body">
                <div class="info-row">
                    <span class="info-label">Canteen Name:</span>
                    <span class="info-value">College Canteen</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Daily Timings:</span>
                    <span class="info-value">08:00 AM – 07:30 PM (Mon – Sat)</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Student Support Contact:</span>
                    <span class="info-value">+91 9952611859</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Support Email:</span>
                    <span class="info-value">canteen@college.edu / mohanraj.s4211@gmail.com</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Service Options:</span>
                    <span class="info-value">Campus Dine-In &bull; Fast Counter Takeaway</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Online Platforms:</span>
                    <span class="info-value">Android Mobile App + Responsive Progressive Web App</span>
                </div>
            </div>
        </div>
    </div>

</main>

<script>
function switchSettingsTab(tabId, btn) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.settings-tab-btn').forEach(el => el.classList.remove('active'));
    
    const target = document.getElementById(tabId);
    if (target) {
        target.classList.add('active');
        btn.classList.add('active');
    }
}

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

function copyLiveSyncUrl() {
    const input = document.getElementById('liveSyncUrlInput');
    const btn = document.getElementById('copySyncBtn');
    if (!input) return;
    
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(function() {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
        btn.style.background = '#16a34a';
        setTimeout(function() {
            btn.innerHTML = orig;
            btn.style.background = '#0f172a';
        }, 2000);
    });
}
</script>

</body>
</html>
