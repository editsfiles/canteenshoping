<?php
session_start();
include("php/db.php");

if (!isset($_SESSION['reset_email']) || !isset($_SESSION['otp_verified'])) {
    header("Location: forgot_password.php");
    exit();
}

$message = "";

if (isset($_POST['reset'])) {

    $password = trim($_POST['password']);
    $confirm  = trim($_POST['confirm_password']);

    if (empty($password) || empty($confirm)) {

        $message = "<div class='error'>Please fill all fields.</div>";

    } elseif (strlen($password) < 6) {

        $message = "<div class='error'>Password must be at least 6 characters.</div>";

    } elseif ($password != $confirm) {

        $message = "<div class='error'>Passwords do not match.</div>";

    } else {

        $email = $_SESSION['reset_email'];

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password=? WHERE email=?");
        $stmt->bind_param("ss", $hash, $email);

        if ($stmt->execute()) {

            $delete = $conn->prepare("DELETE FROM password_resets WHERE email=?");
            $delete->bind_param("s", $email);
            $delete->execute();

            session_destroy();

            echo "<script>
alert('Password Updated Successfully');
window.location='login.php';
</script>";
exit();

        } else {

            $message = "<div class='error'>Unable to update password.</div>";

        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Reset Password</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

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
overflow:hidden;

background:linear-gradient(-45deg,#00c853,#00e676,#00bcd4,#1de9b6);
background-size:400% 400%;
animation:bgMove 12s ease infinite;

}

@keyframes bgMove{

0%{background-position:0% 50%;}
50%{background-position:100% 50%;}
100%{background-position:0% 50%;}

}

.circle{

position:absolute;
border-radius:50%;
background:rgba(255,255,255,.15);
animation:float 10s linear infinite;

}

.circle:nth-child(1){

width:180px;
height:180px;
left:-60px;
top:-60px;

}

.circle:nth-child(2){

width:220px;
height:220px;
right:-80px;
bottom:-80px;

animation-duration:15s;

}

@keyframes float{

0%{
transform:translateY(0) rotate(0deg);
}

50%{
transform:translateY(-30px) rotate(180deg);
}

100%{
transform:translateY(0) rotate(360deg);
}

}

.box{

width:430px;
padding:40px;

background:rgba(255,255,255,.15);

backdrop-filter:blur(20px);

border-radius:25px;

border:1px solid rgba(255,255,255,.3);

box-shadow:0 15px 35px rgba(0,0,0,.25);

z-index:2;

}

.logo{

text-align:center;
font-size:60px;
color:white;
margin-bottom:10px;

}

h2{

text-align:center;
color:white;
margin-bottom:25px;

}

.input{

position:relative;
margin-bottom:20px;

}

.input input{

width:100%;
padding:15px 50px;

border:none;
outline:none;

border-radius:12px;

font-size:16px;

}

.input i:first-child{

position:absolute;

left:18px;

top:17px;

color:#00b894;

}

.eye{

position:absolute;

right:18px;

top:17px;

cursor:pointer;

color:#00b894;

}

button{

width:100%;
padding:15px;

border:none;

border-radius:12px;

background:#00c853;

color:white;

font-size:18px;

font-weight:bold;

cursor:pointer;

transition:.3s;

}

button:hover{

background:#009624;

}

.error{

background:#ffebee;

color:#c62828;

padding:10px;

border-radius:8px;

margin-bottom:15px;

text-align:center;

}

</style>

</head>

<body>

<div class="circle"></div>
<div class="circle"></div>

<div class="box">

<div class="logo">
<i class="fa-solid fa-lock"></i>
</div>

<h2>Create New Password</h2>

<?php echo $message; ?>

<form method="POST">

<div class="input">

<i class="fa-solid fa-lock"></i>

<input
type="password"
id="password"
name="password"
placeholder="New Password"
required>

<i class="fa-solid fa-eye eye"
onclick="togglePassword('password',this)"></i>

</div>

<div class="input">

<i class="fa-solid fa-lock"></i>

<input
type="password"
id="confirm_password"
name="confirm_password"
placeholder="Confirm Password"
required>

<i class="fa-solid fa-eye eye"
onclick="togglePassword('confirm_password',this)"></i>

</div>

<button
type="submit"
name="reset">

Update Password

</button>

</form>

</div>

<script>

function togglePassword(id,icon){

let input=document.getElementById(id);

if(input.type==="password"){

input.type="text";

icon.classList.remove("fa-eye");
icon.classList.add("fa-eye-slash");

}else{

input.type="password";

icon.classList.remove("fa-eye-slash");
icon.classList.add("fa-eye");

}

}

</script>

</body>
</html>