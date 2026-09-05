<?php
session_start();
include("php/db.php");

$message = "";

if(isset($_SESSION['user_id'])){
    header("Location: index.php");
    exit();
}

if(isset($_POST['login'])){

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if(empty($email) || empty($password)){

        $message = "<div class='error'>Please enter Email and Password.</div>";

    }else{

        // Get user by email
        $stmt = $conn->prepare("SELECT * FROM users WHERE email=? LIMIT 1");

        if(!$stmt){
            die("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("s",$email);
        $stmt->execute();

        $result = $stmt->get_result();

        if($result->num_rows == 1){

            $user = $result->fetch_assoc();

            // Verify password (supports bcrypt hash and plaintext fallback)
            if (password_verify($password, $user['password']) || $password === $user['password']) {

                session_regenerate_id(true);

                $_SESSION['user_id']    = (int)$user['id'];
                $_SESSION['user_name']  = $user['name'];
                $_SESSION['name']       = $user['name'];
                $_SESSION['user_email'] = $user['email'];

                header("Location: index.php");
                exit();

            }else{

                $message = "<div class='error'>Invalid Email or Password.</div>";

            }

        }else{

            $message = "<div class='error'>Invalid Email or Password.</div>";

        }

        $stmt->close();

    }

}
?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Student Login</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

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

.login-box{

width:420px;
padding:40px;
border-radius:20px;

background:rgba(255,255,255,.15);

backdrop-filter:blur(18px);

border:1px solid rgba(255,255,255,.3);

box-shadow:0 15px 30px rgba(0,0,0,.25);

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
margin-bottom:30px;

}

.input-box{

position:relative;
margin-bottom:20px;

}

.input-box input{

width:100%;
padding:15px 50px;

border:none;
outline:none;

border-radius:10px;

font-size:16px;

}

.input-box i:first-child{

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

border-radius:10px;

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

margin-bottom:20px;

text-align:center;

}

.links{

margin-top:20px;

display:flex;

justify-content:space-between;

}

.links a{

color:white;

text-decoration:none;

font-size:14px;

}

.links a:hover{

text-decoration:underline;

}

@media(max-width:500px){

.login-box{

width:90%;
padding:30px;

}

}

</style>

</head>

<body>

<div class="login-box">

<div class="logo">

<i class="fa-solid fa-user-graduate"></i>

</div>

<h2>Student Login</h2>

<?php echo $message; ?>

<form method="POST">

<div class="input-box">

<i class="fa-solid fa-envelope"></i>

<input
type="email"
name="email"
placeholder="Email Address"
required>

</div>

<div class="input-box">

<i class="fa-solid fa-lock"></i>

<input
type="password"
id="password"
name="password"
placeholder="Password"
required>

<i class="fa-solid fa-eye eye"
onclick="showPassword()"></i>

</div>

<button
type="submit"
name="login">

Login

</button>

</form>

<div class="links">

<a href="forgot_password.php">
Forgot Password?
</a>

<a href="register.php">
Create Account
</a>

</div>

</div>

<script>

function showPassword(){

let password=document.getElementById("password");

if(password.type==="password"){

password.type="text";

}else{

password.type="password";

}

}

</script>

<?php include_once("php/install_pwa_banner.php"); ?>
</body>
</html>