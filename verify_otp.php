<?php
session_start();
include("php/db.php");

if(!isset($_SESSION['reset_email'])){
    header("Location: forgot_password.php");
    exit();
}

$message = "";

if (!empty($_SESSION['otp_notice'])) {
    $type = $_SESSION['otp_type'] ?? 'info';
    $style = ($type === 'success')
        ? "background:#e8f5e9; color:#2e7d32; border:1px solid #a5d6a7;"
        : "background:#fff3e0; color:#e65100; border:1px solid #ffcc80;";
    $message = "<div style='padding:12px 14px; border-radius:10px; margin-bottom:18px; font-size:14px; text-align:center; line-height:1.5; $style'>" . $_SESSION['otp_notice'] . "</div>";
    unset($_SESSION['otp_notice'], $_SESSION['otp_type']);
}

$prefillOtp = $_SESSION['otp_fallback_code'] ?? '';
unset($_SESSION['otp_fallback_code']);

if(isset($_POST['verify'])){

    $email = $_SESSION['reset_email'];
$otp = trim($_POST['otp']);

$stmt = $conn->prepare("SELECT otp, expires_at FROM password_resets WHERE email=? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();

$result = $stmt->get_result();

if($result->num_rows == 1){

    $row = $result->fetch_assoc();

    if($row['otp'] == $otp){

        if(strtotime($row['expires_at']) >= time()){

            $_SESSION['otp_verified'] = true;
            header("Location: reset_password.php");
            exit();

        }else{

            $message = "<div class='error'>OTP Expired.</div>";

        }

    }else{

        $message = "<div class='error'>Incorrect OTP.</div>";

    }

}else{

    $message = "<div class='error'>No OTP found.</div>";

}
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Verify OTP</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

<style>

*{
margin:0;
padding:0;
box-sizing:border-box;
font-family:'Poppins',sans-serif;
}

body{

display:flex;
justify-content:center;
align-items:center;
min-height:100vh;

background:linear-gradient(-45deg,#00c853,#00e676,#00bcd4,#1de9b6);
background-size:400% 400%;
animation:bg 10s infinite;

}

@keyframes bg{

0%{background-position:0% 50%;}
50%{background-position:100% 50%;}
100%{background-position:0% 50%;}

}

.box{

width:420px;
padding:40px;
border-radius:20px;
background:rgba(255,255,255,.15);
backdrop-filter:blur(18px);
box-shadow:0 15px 30px rgba(0,0,0,.25);

}

h2{

text-align:center;
color:white;
margin-bottom:30px;

}

.input{

position:relative;
margin-bottom:20px;

}

.input input{

width:100%;
padding:15px;
border:none;
border-radius:10px;
outline:none;
font-size:16px;

}

button{

width:100%;
padding:15px;
border:none;
border-radius:10px;
background:#00c853;
color:white;
font-size:18px;
cursor:pointer;
transition:.3s;

}

button:hover{

background:#009624;

}

.error{

margin-bottom:15px;
padding:10px;
background:#ffebee;
color:#c62828;
border-radius:8px;
text-align:center;

}

</style>

</head>

<body>

<div class="box">

<h2>Verify OTP</h2>

<?php echo $message; ?>

<form method="POST">

<div class="input">

<input
type="text"
name="otp"
maxlength="6"
placeholder="Enter 6-digit OTP"
value="<?php echo htmlspecialchars($prefillOtp); ?>"
required>

</div>

<button
type="submit"
name="verify">

Verify OTP

</button>

</form>

<div style="margin-top:20px; text-align:center;">
    <a href="forgot_password.php" style="color:white; text-decoration:none; font-size:14px; opacity:0.9;">← Try Another Email</a>
    <span style="color:rgba(255,255,255,0.4); margin:0 8px;">|</span>
    <a href="login.php" style="color:white; text-decoration:none; font-size:14px; opacity:0.9;">Back to Login</a>
</div>

</div>

</body>
</html>