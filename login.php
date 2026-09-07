<?php
session_start();
include("php/db.php");

$message = "";

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if (isset($_POST['login'])) {
    $identifier = trim($_POST['email'] ?? '');
    $password   = trim($_POST['password'] ?? '');

    if (empty($identifier) || empty($password)) {
        $message = "<div class='alert-card error'><i class='fa-solid fa-circle-exclamation'></i> Please enter your Email / Mobile Number and Password.</div>";
    } else {
        // Extract 10-digit number if user entered phone number
        $clean_digits = preg_replace('/[^0-9]/', '', $identifier);
        $phone_10 = (strlen($clean_digits) >= 10) ? substr($clean_digits, -10) : '';
        $phone_plus91  = $phone_10 ? ('+91' . $phone_10) : '';
        $phone_space91 = $phone_10 ? ('+91 ' . $phone_10) : '';

        // Flexible query matching Email, Register Number, or Phone with/without +91
        $stmt = $conn->prepare("SELECT * FROM users WHERE email=? OR regno=? OR phone=? OR (phone!='' AND (phone=? OR phone=? OR phone=?)) LIMIT 1");

        if (!$stmt) {
            // Fallback if older schema without phone
            $stmt = $conn->prepare("SELECT * FROM users WHERE email=? OR regno=? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("ss", $identifier, $identifier);
                $stmt->execute();
            }
        } else {
            $stmt->bind_param("ssssss", $identifier, $identifier, $identifier, $phone_10, $phone_plus91, $phone_space91);
            $stmt->execute();
        }

        if (!$stmt) {
            $message = "<div class='alert-card error'><i class='fa-solid fa-triangle-exclamation'></i> Database error occurred. Please try again.</div>";
        } else {
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();

                // Verify password (bcrypt hash or plain fallback for legacy)
                if (password_verify($password, $user['password']) || $password === $user['password']) {
                    session_regenerate_id(true);

                    $_SESSION['user_id']    = (int)$user['id'];
                    $_SESSION['user_name']  = $user['name'];
                    $_SESSION['name']       = $user['name'];
                    $_SESSION['user_email'] = $user['email'];

                    // 1-Year Persistent Remember-Me Cookie (Will NEVER automatically log out)
                    $secretKey   = 'canteen_app_secret_key_2026';
                    $token       = hash_hmac('sha256', $user['id'] . $user['password'], $secretKey);
                    $cookieValue = $user['id'] . ':' . $token;
                    $oneYear     = time() + (365 * 24 * 60 * 60);

                    setcookie('canteen_student_auth', $cookieValue, [
                        'expires'  => $oneYear,
                        'path'     => '/',
                        'secure'   => false,
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);

                    header("Location: index.php");
                    exit();
                } else {
                    $message = "<div class='alert-card error'><i class='fa-solid fa-circle-exclamation'></i> Incorrect password. Please try again.</div>";
                }
            } else {
                $message = "<div class='alert-card error'><i class='fa-solid fa-circle-xmark'></i> No student account found with that email, mobile number, or register number.</div>";
            }

            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Student Login | College Canteen</title>
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
            --card-bg: rgba(255, 255, 255, 0.94);
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

        /* Ambient glowing background shapes */
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

        .login-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 440px;
        }

        .login-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            border-radius: var(--radius-lg);
            padding: 38px 32px 32px;
            box-shadow: var(--shadow-card);
            transition: transform 0.3s ease;
        }

        .brand-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .brand-icon-wrap {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 32px;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.35);
            margin-bottom: 14px;
            transform: rotate(-3deg);
            transition: transform 0.3s ease;
        }
        .brand-icon-wrap:hover {
            transform: rotate(0deg) scale(1.05);
        }

        .brand-title {
            font-size: 26px;
            font-weight: 800;
            color: var(--dark);
            letter-spacing: -0.5px;
            line-height: 1.2;
        }

        .brand-subtitle {
            font-size: 13.5px;
            color: var(--text-muted);
            margin-top: 6px;
            font-weight: 500;
        }

        .alert-card {
            padding: 12px 16px;
            border-radius: var(--radius-md);
            font-size: 13.5px;
            font-weight: 600;
            margin-bottom: 22px;
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

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 8px;
            letter-spacing: -0.2px;
        }

        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            color: #94a3b8;
            font-size: 16px;
            pointer-events: none;
            transition: color 0.2s ease;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px 14px 46px;
            font-size: 15px;
            font-weight: 500;
            color: var(--dark);
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: var(--radius-md);
            outline: none;
            transition: all 0.25s ease;
        }

        .form-input:focus {
            background: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
        }

        .form-input:focus + .input-icon,
        .input-group:focus-within .input-icon {
            color: var(--primary);
        }

        .password-toggle {
            position: absolute;
            right: 14px;
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 16px;
            cursor: pointer;
            padding: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
        }
        .password-toggle:hover {
            color: var(--dark);
        }

        .btn-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff;
            border: none;
            border-radius: var(--radius-md);
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 10px 20px -5px rgba(16, 185, 129, 0.4);
            transition: all 0.25s ease;
            margin-top: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 24px -5px rgba(16, 185, 129, 0.5);
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .auth-footer {
            margin-top: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13.5px;
            gap: 10px;
        }

        .auth-link {
            color: #059669;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }
        .auth-link:hover {
            color: #047857;
            text-decoration: underline;
        }


        @media (max-width: 480px) {
            body {
                padding: 16px 12px;
            }
            .login-card {
                padding: 30px 20px 24px;
                border-radius: 20px;
            }
            .brand-title {
                font-size: 23px;
            }
            .brand-icon-wrap {
                width: 64px;
                height: 64px;
                font-size: 28px;
            }
            .auth-footer {
                flex-direction: column;
                text-align: center;
                gap: 12px;
            }
        }
    </style>
</head>
<body>

    <div class="ambient-orb orb-1"></div>
    <div class="ambient-orb orb-2"></div>

    <div class="login-wrapper">
        <div class="login-card">

            <div class="brand-header">
                <div class="brand-icon-wrap">
                    <i class="fa-solid fa-utensils"></i>
                </div>
                <h1 class="brand-title">Welcome Back</h1>
                <p class="brand-subtitle">Sign in to order canteen meals without waiting</p>
            </div>

            <?php echo $message; ?>

            <form method="POST" autocomplete="on">

                <div class="form-group">
                    <label class="form-label" for="loginIdentifier">
                        <i class="fa-solid fa-user-circle" style="color:#10b981; margin-right:4px;"></i> Email / Mobile / Reg No
                    </label>
                    <div class="input-group">
                        <input
                            type="text"
                            id="loginIdentifier"
                            name="email"
                            class="form-input"
                            placeholder="e.g. 9876543210 or email"
                            required
                            autocomplete="username"
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                        >
                        <i class="fa-solid fa-envelope input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="loginPassword">
                        <i class="fa-solid fa-lock" style="color:#10b981; margin-right:4px;"></i> Password
                    </label>
                    <div class="input-group">
                        <input
                            type="password"
                            id="loginPassword"
                            name="password"
                            class="form-input"
                            style="padding-right: 46px;"
                            placeholder="Enter your password"
                            required
                            autocomplete="current-password"
                        >
                        <i class="fa-solid fa-shield-halved input-icon"></i>
                        <button type="button" class="password-toggle" onclick="togglePasswordVisibility()" aria-label="Toggle password visibility">
                            <i id="eyeIcon" class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" name="login" class="btn-submit">
                    <span>Sign In</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>

            </form>


            <div class="auth-footer">
                <a href="forgot_password.php" class="auth-link">
                    <i class="fa-solid fa-key" style="font-size:11px;"></i> Forgot Password?
                </a>
                <a href="register.php" class="auth-link" style="color:#0ea5e9;">
                    <i class="fa-solid fa-user-plus" style="font-size:11px;"></i> Create Account
                </a>
            </div>

        </div>
    </div>

    <script>
        function togglePasswordVisibility() {
            const pwdInput = document.getElementById("loginPassword");
            const eyeIcon  = document.getElementById("eyeIcon");

            if (pwdInput.type === "password") {
                pwdInput.type = "text";
                eyeIcon.classList.remove("fa-eye");
                eyeIcon.classList.add("fa-eye-slash");
            } else {
                pwdInput.type = "password";
                eyeIcon.classList.remove("fa-eye-slash");
                eyeIcon.classList.add("fa-eye");
            }
        }
    </script>

    <?php include_once("php/install_pwa_banner.php"); ?>
</body>
</html>