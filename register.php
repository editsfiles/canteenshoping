<?php
session_start();
include("php/db.php");

$message = "";

if(isset($_POST['register'])){

    $name = trim($_POST['name']);
    $regno = trim($_POST['regno']);
    $department = trim($_POST['department']);
    $email = trim($_POST['email']);
    $password = $_POST['password'] ?? '';

    if($name=="" || $regno=="" || $department=="" || $email=="" || $password==""){

        $message = "<p style='color:red;'>All fields are required.</p>";

    }else{

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $checkStmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? OR regno = ? LIMIT 1");

        if (!$checkStmt) {
            $message = "<p style='color:red;'>Registration Failed.</p>";
        } else {
            mysqli_stmt_bind_param($checkStmt, "ss", $email, $regno);
            mysqli_stmt_execute($checkStmt);
            $checkResult = mysqli_stmt_get_result($checkStmt);

            if(mysqli_num_rows($checkResult) > 0){

                $message = "<p style='color:red;'>Email or Register Number already exists.</p>";

            } else {

                $insertStmt = mysqli_prepare($conn, "INSERT INTO users(name, regno, department, email, password) VALUES (?, ?, ?, ?, ?)");

                if (!$insertStmt) {
                    $message = "<p style='color:red;'>Registration Failed.</p>";
                } else {
                    mysqli_stmt_bind_param($insertStmt, "sssss", $name, $regno, $department, $email, $passwordHash);

                    if(mysqli_stmt_execute($insertStmt)){

                        $message = "<p style='color:green;'>Registration Successful. <a href='login.php'>Login Here</a></p>";

                    }else{

                        $message = "<p style='color:red;'>Registration Failed.</p>";

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
<html>
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Student Registration</title>

<style>

*{
margin:0;
padding:0;
box-sizing:border-box;
font-family:Arial,Helvetica,sans-serif;
}

body{
background:#f4f6f9;
display:flex;
justify-content:center;
align-items:center;
min-height:100vh;
overflow-y:auto;
padding:25px 12px;
}

.container{
width:100%;
max-width:440px;
background:#fff;
padding:30px 24px;
border-radius:14px;
box-shadow:0 10px 25px rgba(0,0,0,.08);
}

h2{
text-align:center;
margin-bottom:20px;
color:#2c3e50;
font-size:24px;
}

.form-group{
margin-bottom:15px;
}

label{
display:block;
margin-bottom:6px;
font-weight:bold;
font-size:14px;
color:#334155;
}

input, select{
width:100%;
padding:11px 14px;
border:1.5px solid #cbd5e1;
border-radius:8px;
font-size:15px;
outline:none;
background:#fafafa;
transition:0.2s ease;
box-sizing:border-box;
}

input:focus, select:focus{
border-color:#27ae60;
background:#fff;
box-shadow:0 0 0 3px rgba(39,174,96,0.12);
}

button{
width:100%;
padding:13px;
background:#27ae60;
color:white;
border:none;
border-radius:8px;
font-size:16px;
font-weight:700;
cursor:pointer;
transition:0.2s ease;
margin-top:5px;
}

button:hover{
background:#219150;
}

.login-link{
text-align:center;
margin-top:16px;
font-size:14px;
}

@media(max-width:480px){
.container{
padding:22px 18px;
}
h2{
font-size:21px;
}
}

</style>

</head>

<body>

<div class="container">

<h2>Student Registration</h2>

<?php echo $message; ?>

<form method="POST">

<div class="form-group">
<label>Student Name</label>
<input type="text" name="name" required>
</div>

<div class="form-group">
<label>Register Number</label>
<input type="text" name="regno" required>
</div>

<div class="form-group">
    <label>Department</label>

    <select name="department" required>
        <option value="">-- Select Department --</option>
        <option value="BCA">BCA</option>
        <option value="B.Com">B.Com</option>
        <option value="B.Com A&F">B.Com A&F</option>
        <option value="B.Com CA">B.Com CA</option>
        <option value="B.Sc Computer Science">B.Sc Computer Science</option>
        <option value="B.Sc Information Technology">B.Sc Information Technology</option>
        <option value="BBA">BBA</option>
        <option value="BA English">BA English</option>
        <option value="BA Tamil">BA Tamil</option>
        <option value="MCA">MCA</option>
        <option value="M.Com">M.Com</option>
        <option value="MBA">MBA</option>
    </select>
</div>
<div class="form-group">
<label>Email</label>
<input type="email" name="email" required>
</div>

<div class="form-group">
<label>Password</label>
<input type="password" name="password" required>
</div>

<button type="submit" name="register">Register</button>

</form>

<div class="login-link">
Already have an account?
<a href="login.php">Login</a>
</div>

</div>

</body>
</html>