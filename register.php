<?php
session_start();
include("php/db.php");

$message = "";

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if (isset($_POST['register'])) {
    $name       = trim($_POST['name'] ?? '');
    $regno      = trim($_POST['regno'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $raw_phone  = trim($_POST['phone'] ?? '');
    $password   = $_POST['password'] ?? '';

    // Extract 10-digit number and format with default +91 prefix
    $clean_digits = preg_replace('/[^0-9]/', '', $raw_phone);
    $phone_10 = (strlen($clean_digits) >= 10) ? substr($clean_digits, -10) : $clean_digits;
    $phone_formatted = '+91 ' . $phone_10;
    $phone_compact   = '+91' . $phone_10;

    if (empty($name) || empty($regno) || empty($department) || empty($email) || empty($phone_10) || empty($password)) {
        $message = "<div class='alert-card error'><i class='fa-solid fa-circle-exclamation'></i> All fields including a valid 10-digit mobile number are required.</div>";
    } elseif (strlen($phone_10) !== 10) {
        $message = "<div class='alert-card error'><i class='fa-solid fa-circle-exclamation'></i> Please enter a valid 10-digit mobile number.</div>";
    } else {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // Check for duplicates: email, regno, or mobile (in any formatting)
        $checkStmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? OR regno = ? OR phone = ? OR phone = ? OR phone = ? LIMIT 1");

        if (!$checkStmt) {
            // Fallback for older schema
            $checkStmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? OR regno = ? LIMIT 1");
            if ($checkStmt) {
                mysqli_stmt_bind_param($checkStmt, "ss", $email, $regno);
            }
        } else {
            mysqli_stmt_bind_param($checkStmt, "sssss", $email, $regno, $phone_10, $phone_formatted, $phone_compact);
        }

        if (!$checkStmt) {
            $message = "<div class='alert-card error'><i class='fa-solid fa-triangle-exclamation'></i> Registration system error. Please try again.</div>";
        } else {
            mysqli_stmt_execute($checkStmt);
            $checkResult = mysqli_stmt_get_result($checkStmt);

            if ($checkResult && mysqli_num_rows($checkResult) > 0) {
                $message = "<div class='alert-card error'><i class='fa-solid fa-circle-xmark'></i> Email, Register Number, or Mobile Number is already registered. <a href='login.php' style='color:#dc2626;font-weight:700;margin-left:5px;'>Login Here</a></div>";
            } else {
                // Insert with default +91 prefix
                $insertStmt = mysqli_prepare($conn, "INSERT INTO users(name, regno, department, email, phone, password) VALUES (?, ?, ?, ?, ?, ?)");
                if ($insertStmt) {
                    mysqli_stmt_bind_param($insertStmt, "ssssss", $name, $regno, $department, $email, $phone_formatted, $passwordHash);
                } else {
                    // Fallback if no phone column
                    $insertStmt = mysqli_prepare($conn, "INSERT INTO users(name, regno, department, email, password) VALUES (?, ?, ?, ?, ?)");
                    if ($insertStmt) {
                        mysqli_stmt_bind_param($insertStmt, "sssss", $name, $regno, $department, $email, $passwordHash);
                    }
                }

                if (!$insertStmt) {
                    $message = "<div class='alert-card error'><i class='fa-solid fa-triangle-exclamation'></i> Database error during account creation.</div>";
                } else {
                    if (mysqli_stmt_execute($insertStmt)) {
                        $message = "<div class='alert-card success'><i class='fa-solid fa-circle-check'></i> Account created successfully! <a href='login.php' style='color:#15803d;font-weight:700;margin-left:6px;text-decoration:underline;'>Sign In Now &rarr;</a></div>";
                        // Clear POST values on success
                        $_POST = [];
                    } else {
                        $message = "<div class='alert-card error'><i class='fa-solid fa-circle-xmark'></i> Registration failed: " . htmlspecialchars(mysqli_error($conn)) . "</div>";
                    }
                    mysqli_stmt_close($insertStmt);
                }
            }
            mysqli_stmt_close($checkStmt);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Student Registration | College Canteen</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <style>
        :root {
            --primary: #10b981;
            --primary-dark: #059669;
            --primary-light: #ecfdf5;
            --accent: #0ea5e9;
            --dark: #0f172a;
            --text-muted: #64748b;
            --card-bg: rgba(255, 255, 255, 0.95);
            --border-color: rgba(226, 232, 240, 0.8);
            --radius-lg: 24px;
            --radius-md: 14px;
            --shadow-card: 0 20px 40px -15px rgba(16, 185, 129, 0.15), 0 10px 25px -5px rgba(15, 23, 42, 0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', 'Poppins', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #059669 0%, #10b981 35%, #0ea5e9 100%);
            background-size: 200% 200%;
            animation: gradientShift 10s ease infinite alternate;
            padding: 24px 16px;
            position: relative;
            overflow-x: hidden;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            100% { background-position: 100% 50%; }
        }

        .ambient-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            pointer-events: none;
            opacity: 0.45;
            z-index: 0;
        }
        .orb-1 {
            width: 320px;
            height: 320px;
            background: #34d399;
            top: -60px;
            left: -60px;
        }
        .orb-2 {
            width: 300px;
            height: 300px;
            background: #38bdf8;
            bottom: -50px;
            right: -50px;
        }

        .register-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 480px;
        }

        .register-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            border-radius: var(--radius-lg);
            padding: 34px 28px 28px;
            box-shadow: var(--shadow-card);
        }

        .brand-header {
            text-align: center;
            margin-bottom: 24px;
        }

        .brand-icon-wrap {
            width: 66px;
            height: 66px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 30px;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.35);
            margin-bottom: 12px;
            transform: rotate(-3deg);
        }

        .brand-title {
            font-size: 24px;
            font-weight: 800;
            color: var(--dark);
            letter-spacing: -0.5px;
        }

        .brand-subtitle {
            font-size: 13.5px;
            color: var(--text-muted);
            margin-top: 4px;
            font-weight: 500;
        }

        /* SEGMENTED AUTH TABS: SIGN IN / REGISTER */
        .auth-tabs {
            display: flex;
            background: #f1f5f9;
            border-radius: 14px;
            padding: 4px;
            margin-top: 18px;
            margin-bottom: 20px;
            gap: 4px;
            border: 1px solid #e2e8f0;
        }
        .auth-tab {
            flex: 1;
            text-align: center;
            padding: 10px 14px;
            font-size: 13.5px;
            font-weight: 700;
            color: #64748b;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
        }
        .auth-tab:hover {
            color: #0f172a;
        }
        .auth-tab.active {
            background: #ffffff;
            color: #059669;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        }

        .alert-card {
            padding: 12px 16px;
            border-radius: var(--radius-md);
            font-size: 13.5px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            line-height: 1.4;
        }
        .alert-card.error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fee2e2;
        }
        .alert-card.success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 7px;
            letter-spacing: -0.2px;
        }
        .form-label i {
            color: var(--primary);
            font-size: 12px;
        }

        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 12px 14px;
            font-size: 14.5px;
            font-weight: 500;
            color: var(--dark);
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: var(--radius-md);
            outline: none;
            transition: all 0.25s ease;
        }

        .form-input:focus, .form-select:focus {
            background: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
        }

        /* Default +91 Country Code Badge Input Group */
        .phone-input-wrap {
            display: flex;
            align-items: center;
            border: 1.5px solid #e2e8f0;
            border-radius: var(--radius-md);
            background: #f8fafc;
            overflow: hidden;
            transition: all 0.25s ease;
        }
        .phone-input-wrap:focus-within {
            background: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
        }

        .country-prefix-badge {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 0 12px;
            height: 46px;
            background: #f1f5f9;
            border-right: 1.5px solid #e2e8f0;
            font-size: 13.5px;
            font-weight: 700;
            color: #1e293b;
            user-select: none;
            white-space: nowrap;
        }

        .phone-input {
            border: none !important;
            background: transparent !important;
            box-shadow: none !important;
            padding: 12px 14px;
            letter-spacing: 0.5px;
        }

        .password-toggle {
            position: absolute;
            right: 14px;
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 15px;
            cursor: pointer;
            padding: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .password-toggle:hover {
            color: var(--dark);
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff;
            border: none;
            border-radius: var(--radius-md);
            font-size: 15.5px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 10px 20px -5px rgba(16, 185, 129, 0.4);
            transition: all 0.25s ease;
            margin-top: 8px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 24px -5px rgba(16, 185, 129, 0.5);
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
        }

        .auth-footer {
            margin-top: 20px;
            text-align: center;
            font-size: 13.5px;
            color: var(--text-muted);
        }

        .auth-link {
            color: #059669;
            text-decoration: none;
            font-weight: 700;
            margin-left: 4px;
        }
        .auth-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            body {
                padding: 16px 12px;
            }
            .register-card {
                padding: 26px 18px 22px;
                border-radius: 20px;
            }
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .brand-title {
                font-size: 21px;
            }
        }
    </style>
</head>
<body>

    <div class="ambient-orb orb-1"></div>
    <div class="ambient-orb orb-2"></div>

    <div class="register-wrapper">
        <div class="register-card">

            <div class="brand-header">
                <div class="brand-icon-wrap">
                    <i class="fa-solid fa-user-graduate"></i>
                </div>
                <h1 class="brand-title">Create Account</h1>
                <p class="brand-subtitle">Join the canteen ordering portal today</p>

                <!-- SWITCHABLE AUTH TABS -->
                <div class="auth-tabs">
                    <a href="login.php" class="auth-tab">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In
                    </a>
                    <a href="register.php" class="auth-tab active">
                        <i class="fa-solid fa-user-plus"></i> Create Account
                    </a>
                </div>
            </div>

            <?php echo $message; ?>

            <form method="POST" autocomplete="on">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="regName">
                            <i class="fa-solid fa-user"></i> Full Name
                        </label>
                        <input
                            type="text"
                            id="regName"
                            name="name"
                            class="form-input"
                            placeholder="e.g. Rahul Kumar"
                            required
                            value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="regNo">
                            <i class="fa-solid fa-id-badge"></i> Register No.
                        </label>
                        <input
                            type="text"
                            id="regNo"
                            name="regno"
                            class="form-input"
                            placeholder="e.g. 21BCA101"
                            required
                            value="<?php echo isset($_POST['regno']) ? htmlspecialchars($_POST['regno']) : ''; ?>"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="regDept">
                        <i class="fa-solid fa-graduation-cap"></i> Department
                    </label>
                    <select id="regDept" name="department" class="form-select" required>
                        <option value="">-- Choose Department --</option>
                        <?php
                            $depts = [
                                "BCA", "B.Com", "B.Com A&F", "B.Com CA",
                                "B.Sc Computer Science", "B.Sc Information Technology",
                                "BBA", "BA English", "BA Tamil", "MCA", "M.Com", "MBA"
                            ];
                            $selectedDept = $_POST['department'] ?? '';
                            foreach ($depts as $dept) {
                                $sel = ($selectedDept === $dept) ? 'selected' : '';
                                echo "<option value=\"" . htmlspecialchars($dept) . "\" $sel>" . htmlspecialchars($dept) . "</option>";
                            }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="regEmail">
                        <i class="fa-solid fa-envelope"></i> Email Address
                    </label>
                    <input
                        type="email"
                        id="regEmail"
                        name="email"
                        class="form-input"
                        placeholder="student@college.edu"
                        required
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    >
                </div>

                <!-- Default +91 Mobile Number Input -->
                <div class="form-group">
                    <label class="form-label" for="regPhone">
                        <i class="fa-solid fa-phone"></i> Mobile Number (Default +91)
                    </label>
                    <div class="phone-input-wrap">
                        <span class="country-prefix-badge">
                            <i class="fa-solid fa-flag" style="color:#f59e0b;font-size:11px;"></i> +91
                        </span>
                        <input
                            type="tel"
                            id="regPhone"
                            name="phone"
                            class="form-input phone-input"
                            placeholder="9876543210"
                            pattern="[0-9]{10}"
                            maxlength="10"
                            required
                            value="<?php echo isset($_POST['phone']) ? htmlspecialchars(preg_replace('/[^0-9]/', '', $_POST['phone'])) : ''; ?>"
                            title="Enter a 10-digit Indian mobile number"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="regPassword">
                        <i class="fa-solid fa-lock"></i> Password
                    </label>
                    <div class="input-group">
                        <input
                            type="password"
                            id="regPassword"
                            name="password"
                            class="form-input"
                            style="padding-right:44px;"
                            placeholder="Create a strong password"
                            required
                        >
                        <button type="button" class="password-toggle" onclick="toggleRegPassword()" aria-label="Toggle password visibility">
                            <i id="regEyeIcon" class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" name="register" class="btn-submit">
                    <i class="fa-solid fa-user-check"></i>
                    <span>Register Account</span>
                </button>

            </form>

            <div class="auth-footer">
                Already registered?
                <a href="login.php" class="auth-link">Sign In &rarr;</a>
            </div>

        </div>
    </div>

    <script>
        function toggleRegPassword() {
            const pwd = document.getElementById("regPassword");
            const eye = document.getElementById("regEyeIcon");
            if (pwd.type === "password") {
                pwd.type = "text";
                eye.classList.replace("fa-eye", "fa-eye-slash");
            } else {
                pwd.type = "password";
                eye.classList.replace("fa-eye-slash", "fa-eye");
            }
        }

        // Restrict phone input strictly to numbers
        document.getElementById("regPhone").addEventListener("input", function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
        });
    </script>

</body>
</html>