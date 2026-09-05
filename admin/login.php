<?php
session_start();
include("../php/db.php");

$message = "";

if (isset($_SESSION['admin'])) {
    header("Location: dashboard.php");
    exit();
}

if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $message = "Please enter Username and Password.";
    } else {
        $sql = "SELECT id, username, password FROM admins WHERE username=? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            $sql = "SELECT id, username, password FROM admin WHERE username=? LIMIT 1";
            $stmt = mysqli_prepare($conn, $sql);
        }

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $adminUser = $result ? mysqli_fetch_assoc($result) : null;

            if ($adminUser && (password_verify($password, $adminUser['password']) || $password === $adminUser['password'])) {
                session_regenerate_id(true);
                $_SESSION['admin'] = $adminUser['username'];
                $_SESSION['admin_id'] = (int)$adminUser['id'];
                mysqli_stmt_close($stmt);
                header("Location: dashboard.php");
                exit();
            }

            mysqli_stmt_close($stmt);
        }

        $message = "Invalid Username or Password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>College Canteen Admin Login</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif}
body{display:flex;justify-content:center;align-items:center;min-height:100vh;overflow:hidden;background:linear-gradient(-45deg,#00c853,#00e676,#00bcd4,#1de9b6);background-size:400% 400%;animation:bgMove 12s ease infinite}
@keyframes bgMove{0%{background-position:0 50%}50%{background-position:100% 50%}100%{background-position:0 50%}}
.circle{position:absolute;border-radius:50%;background:rgba(255,255,255,.15);animation:float 10s linear infinite}
.circle:nth-child(1){width:180px;height:180px;left:-60px;top:-60px}.circle:nth-child(2){width:220px;height:220px;right:-80px;bottom:-80px;animation-duration:15s}
@keyframes float{0%{transform:translateY(0) rotate(0)}50%{transform:translateY(-30px) rotate(180deg)}100%{transform:translateY(0) rotate(360deg)}}
.login-box{width:420px;padding:40px;background:rgba(255,255,255,.15);backdrop-filter:blur(18px);border-radius:20px;border:1px solid rgba(255,255,255,.3);box-shadow:0 10px 30px rgba(0,0,0,.3);animation:fade .8s}
@keyframes fade{from{opacity:0;transform:translateY(-40px)}to{opacity:1;transform:translateY(0)}}
.logo{text-align:center;font-size:60px;color:#fff;margin-bottom:10px}h2{text-align:center;color:#fff;margin-bottom:30px}
.input-box{position:relative;margin-bottom:20px}.input-box input{width:100%;padding:15px 50px;border:0;outline:0;border-radius:10px;font-size:16px}.input-box input:focus{box-shadow:0 0 15px #00ffd5}.input-box .left{position:absolute;left:18px;top:16px;color:#00b894}.eye{position:absolute;right:18px;top:16px;cursor:pointer;color:#00b894}
button{width:100%;padding:15px;border:0;border-radius:10px;background:#00c853;color:#fff;font-size:18px;font-weight:bold;cursor:pointer;transition:.3s}button:hover{background:#009624;transform:translateY(-2px)}
.error{margin-top:20px;text-align:center;font-weight:bold;color:#ffdddd}
@media(max-width:500px){.login-box{width:90%;padding:30px}}
</style>
</head>
<body>
<div class="circle"></div><div class="circle"></div>
<div class="login-box">
<div class="logo"><i class="fa-solid fa-utensils"></i></div>
<h2>College Canteen Admin</h2>
<form method="POST" autocomplete="off">
<div class="input-box"><i class="fa-solid fa-user left"></i><input type="text" name="username" placeholder="Username" autocomplete="username" required></div>
<div class="input-box"><i class="fa-solid fa-lock left"></i><input type="password" id="password" name="password" placeholder="Password" autocomplete="current-password" required><i class="fa-solid fa-eye eye" onclick="showPassword()" aria-label="Show password"></i></div>
<button type="submit" name="login">Login</button>
</form>
<div class="error"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
</div>
<script>function showPassword(){const x=document.getElementById('password');x.type=x.type==='password'?'text':'password';}</script>
</body>
</html>