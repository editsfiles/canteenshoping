<?php
session_start();

if(isset($_SESSION['user_id'])){
    header("Location: index.php");
    exit();
}

$message = "";

if (isset($_GET['error'])) {
    $message = "<div class='error'>" . htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') . "</div>";
}

if (isset($_GET['success'])) {
    $message = "<div class='success'>" . htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8') . "</div>";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Forgot Password</title>

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
overflow-y:auto;
padding:25px 12px;

background:linear-gradient(-45deg,
#00c853,
#00e676,
#00bcd4,
#1de9b6);

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
animation:float 12s linear infinite;

}

.circle:nth-child(1){

width:180px;
height:180px;
left:-70px;
top:-70px;

}

.circle:nth-child(2){

width:230px;
height:230px;
right:-80px;
bottom:-80px;

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

width:100%;
max-width:430px;

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
margin-bottom:30px;

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

.input i{

position:absolute;

left:18px;

top:17px;

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

.links{

margin-top:20px;
text-align:center;

}

.links a{

color:white;
text-decoration:none;

}

.links a:hover{

text-decoration:underline;

}

.error{

background:#ffebee;
color:#c62828;
padding:10px;
border-radius:8px;
margin-bottom:15px;
text-align:center;

}

.success{

background:#e8f5e9;
color:#2e7d32;
padding:10px;
border-radius:8px;
margin-bottom:15px;
text-align:center;

}

@media(max-width:500px){

.box{

width:100%;
max-width:380px;
padding:26px 20px;

}

.logo{
font-size:48px;
}

h2{
font-size:22px;
margin-bottom:20px;
}

.input input{
padding:13px 45px;
font-size:15px;
}

button{
padding:13px;
font-size:16px;
}

}

</style>

</head>

<body>

<div class="circle"></div>
<div class="circle"></div>

<div class="box">

<div class="logo">

<i class="fa-solid fa-key"></i>

</div>

<h2>Forgot Password</h2>

<?php echo $message; ?>

<form action="php/send_otp.php" method="POST">

<div class="input">

<i class="fa-solid fa-envelope"></i>

<input
type="email"
name="email"
placeholder="Enter Registered Email"
required>

</div>

<button
type="submit"
name="send">

Send OTP

</button>

</form>

<div class="links">

<a href="login.php">

← Back to Login

</a>

</div>

</div>

</body>
</html>