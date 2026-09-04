<?php
session_start();

if(!isset($_SESSION['admin'])){
    header("Location: login.php");
    exit();
}

include("../php/db.php");

if(!isset($_GET['id'])){
    die("Message ID not found.");
}

$id = intval($_GET['id']);

$result = mysqli_query($conn,"SELECT * FROM contacts WHERE id='$id'");

if(mysqli_num_rows($result)==0){
    die("Message not found.");
}

$row=mysqli_fetch_assoc($result);
?>

<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">
<title>View Message</title>

<style>
body{
    margin:0;
    padding:0;
    font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;
    background:linear-gradient(135deg,#eef2f7,#dfe9f3);
}

.container{
    width:700px;
    margin:50px auto;
    background:#ffffff;
    border-radius:18px;
    padding:35px;
    box-shadow:0 12px 30px rgba(0,0,0,.18);
}

h2{
    margin:0 0 30px;
    color:#2c3e50;
    font-size:34px;
    border-bottom:3px solid #6a11cb;
    padding-bottom:12px;
}

.info{
    margin-bottom:18px;
    font-size:18px;
    line-height:30px;
}

.info strong{
    color:#6a11cb;
    display:inline-block;
    width:110px;
}

.message-box{
    background:#f8f9ff;
    border-left:6px solid #6a11cb;
    padding:20px;
    border-radius:10px;
    margin:20px 0;
    color:#333;
    line-height:28px;
}

.date{
    color:#666;
    font-size:16px;
    margin-top:20px;
}

.back{
    display:inline-block;
    margin-top:30px;
    background:linear-gradient(90deg,#6a11cb,#2575fc);
    color:white;
    text-decoration:none;
    padding:14px 28px;
    border-radius:8px;
    font-size:17px;
    font-weight:bold;
    transition:.3s;
}

.back:hover{
    transform:translateY(-3px);
    box-shadow:0 8px 20px rgba(37,117,252,.4);
}

@media(max-width:768px){

.container{
    width:90%;
    padding:25px;
}

h2{
    font-size:28px;
}

.info strong{
    width:90px;
}

}

</style>

</head>

<body>

<div class="container">

<h2>📩 Contact Message</h2>

<div class="info">
<strong>Name:</strong>
<?php echo htmlspecialchars($row['name']); ?>
</div>

<div class="info">
<strong>Email:</strong>
<?php echo htmlspecialchars($row['email']); ?>
</div>

<div class="info">
<strong>Subject:</strong>
<?php echo htmlspecialchars($row['subject']); ?>
</div>

<div class="message-box">
<strong>Message</strong><br><br>
<?php echo nl2br(htmlspecialchars($row['message'])); ?>
</div>

<div class="date">
<strong>Date:</strong>
<?php echo $row['created_at']; ?>
</div>

<a href="messages.php" class="back">
← Back to Messages
</a>
</div>

</body>
</html>