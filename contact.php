<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include("php/db.php");

/* Always define message first */
$message = "";

/* Get logged-in user details safely */
$userName = isset($_SESSION['user_name'])
    ? $_SESSION['user_name']
    : "";

$userEmail = isset($_SESSION['user_email'])
    ? $_SESSION['user_email']
    : "";


/* Send message */
if (isset($_POST['send'])) {

    $name = mysqli_real_escape_string(
        $conn,
        $_POST['name'] ?? ''
    );

    $email = mysqli_real_escape_string(
        $conn,
        $_POST['email'] ?? ''
    );

    $subject = mysqli_real_escape_string(
        $conn,
        $_POST['subject'] ?? ''
    );

    $messageText = mysqli_real_escape_string(
        $conn,
        $_POST['message'] ?? ''
    );


    if (
        empty($name) ||
        empty($email) ||
        empty($subject) ||
        empty($messageText)
    ) {

        $message = "
            <div class='message error'>
                ⚠️ Please fill in all fields.
            </div>
        ";

    } else {

        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `contacts` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(150) NOT NULL,
          `email` varchar(150) NOT NULL,
          `subject` varchar(200) NOT NULL,
          `message` text NOT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmt = mysqli_prepare($conn, "INSERT INTO contacts (name, email, subject, message) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $subject, $messageText);
            if (mysqli_stmt_execute($stmt)) {
                $message = "<div class='message success'>✅ Your message has been sent successfully!</div>";
            } else {
                $message = "<div class='message error'>❌ Failed to send message.</div>";
            }
            mysqli_stmt_close($stmt);
        } else {
            $message = "<div class='message error'>❌ Database error.</div>";
        }
    }
}
?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Contact Us - College Canteen</title>

<style>

/* =========================================================
   GLOBAL
========================================================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, Helvetica, sans-serif;
}

html {
    scroll-behavior: smooth;
}

body {
    min-height: 100vh;
    background:
        linear-gradient(
            135deg,
            #eef7f1,
            #f4f6f9
        );
    color: #222;
}


/* =========================================================
   HEADER
========================================================= */

header {
    background: #27ae60;
    color: white;

    padding: 15px 40px;

    display: flex;
    justify-content: space-between;
    align-items: center;

    min-height: 66px;

    animation: headerDown 0.7s ease;
}

header h2 {
    color: white;
    font-size: 24px;
}

nav {
    display: flex;
    gap: 24px;
}

nav a {
    color: white;
    text-decoration: none;
    font-weight: bold;

    position: relative;

    transition: 0.3s ease;
}

nav a::after {
    content: "";

    position: absolute;

    left: 0;
    bottom: -6px;

    width: 0;
    height: 2px;

    background: white;

    transition: 0.3s ease;
}

nav a:hover::after {
    width: 100%;
}

nav a:hover {
    transform: translateY(-2px);
}


/* =========================================================
   MAIN CONTACT AREA
========================================================= */

.contact-wrapper {

    width: 92%;
    max-width: 1050px;

    margin: 50px auto;

    display: grid;

    grid-template-columns:
        0.9fr
        1.1fr;

    background: white;

    border-radius: 18px;

    overflow: hidden;

    box-shadow:
        0 15px 40px rgba(0,0,0,0.12);

    animation: pageAppear 0.8s ease;
}


/* =========================================================
   LEFT SIDE
========================================================= */

.contact-info {

    background:
        linear-gradient(
            145deg,
            #27ae60,
            #1e8449
        );

    color: white;

    padding: 45px 35px;

    position: relative;

    overflow: hidden;
}

.contact-info::before {

    content: "";

    position: absolute;

    width: 220px;
    height: 220px;

    border-radius: 50%;

    background: rgba(255,255,255,0.08);

    top: -80px;
    right: -70px;

    animation: floatCircle 5s ease-in-out infinite;
}

.contact-info::after {

    content: "";

    position: absolute;

    width: 150px;
    height: 150px;

    border-radius: 50%;

    background: rgba(255,255,255,0.07);

    bottom: -60px;
    left: -40px;

    animation: floatCircle2 6s ease-in-out infinite;
}

.contact-info h1 {

    font-size: 35px;

    margin-bottom: 15px;

    position: relative;

    animation: titleSlide 0.8s ease;
}

.contact-info p {

    line-height: 1.7;

    margin-bottom: 35px;

    position: relative;

    color: #eafff1;
}


/* =========================================================
   CONTACT DETAILS
========================================================= */

.contact-detail {

    display: flex;

    align-items: center;

    gap: 15px;

    margin: 22px 0;

    position: relative;

    animation: detailSlide 0.8s ease both;
}

.contact-detail:nth-child(1) {
    animation-delay: 0.2s;
}

.contact-detail:nth-child(2) {
    animation-delay: 0.35s;
}

.contact-detail:nth-child(3) {
    animation-delay: 0.5s;
}

.contact-icon {

    width: 45px;
    height: 45px;

    border-radius: 50%;

    background: rgba(255,255,255,0.18);

    display: flex;

    justify-content: center;
    align-items: center;

    font-size: 21px;
}

.contact-detail span {

    font-size: 15px;
}


/* =========================================================
   RIGHT SIDE FORM
========================================================= */

.contact-form {

    padding: 45px 40px;

    animation: formSlide 0.9s ease;
}

.contact-form h2 {

    font-size: 30px;

    color: #2c3e50;

    margin-bottom: 8px;
}

.contact-form .subtitle {

    color: #777;

    margin-bottom: 28px;
}


/* =========================================================
   SUCCESS / ERROR MESSAGE
========================================================= */

.message {

    padding: 13px 16px;

    border-radius: 8px;

    margin-bottom: 20px;

    font-weight: bold;

    animation: messagePop 0.5s ease;
}

.message.success {

    background: #d4edda;

    color: #155724;

    border-left: 5px solid #27ae60;
}

.message.error {

    background: #f8d7da;

    color: #721c24;

    border-left: 5px solid #e74c3c;
}


/* =========================================================
   FORM GROUP
========================================================= */

.form-group {

    margin-bottom: 20px;

    animation: inputAppear 0.7s ease both;
}

.form-group:nth-child(2) {
    animation-delay: 0.1s;
}

.form-group:nth-child(3) {
    animation-delay: 0.2s;
}

.form-group:nth-child(4) {
    animation-delay: 0.3s;
}

.form-group:nth-child(5) {
    animation-delay: 0.4s;
}

.form-group label {

    display: block;

    margin-bottom: 8px;

    color: #2c3e50;

    font-weight: bold;

    font-size: 14px;
}


/* =========================================================
   INPUTS
========================================================= */

.form-group input,
.form-group textarea {

    width: 100%;

    padding: 13px 15px;

    border: 1px solid #ddd;

    border-radius: 8px;

    background: #fafafa;

    font-size: 15px;

    outline: none;

    transition:
        border-color 0.3s ease,
        box-shadow 0.3s ease,
        background 0.3s ease;
}

.form-group input {

    height: 48px;
}

.form-group textarea {

    min-height: 130px;

    resize: vertical;
}


/* Focus */

.form-group input:focus,
.form-group textarea:focus {

    border-color: #27ae60;

    background: white;

    box-shadow:
        0 0 0 4px
        rgba(39,174,96,0.12);

    transform: translateY(-1px);
}


/* =========================================================
   SEND BUTTON
========================================================= */

.send-button {

    width: 100%;

    padding: 14px;

    border: none;

    border-radius: 8px;

    background:
        linear-gradient(
            135deg,
            #27ae60,
            #1e8449
        );

    color: white;

    font-size: 16px;

    font-weight: bold;

    cursor: pointer;

    transition:
        transform 0.3s ease,
        box-shadow 0.3s ease;
}

.send-button:hover {

    transform:
        translateY(-3px);

    box-shadow:
        0 10px 20px
        rgba(39,174,96,0.30);
}

.send-button:active {

    transform:
        translateY(0)
        scale(0.98);
}


/* =========================================================
   ANIMATIONS
========================================================= */

@keyframes headerDown {

    from {
        opacity: 0;
        transform: translateY(-40px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}


@keyframes pageAppear {

    from {
        opacity: 0;
        transform: translateY(40px) scale(0.97);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}


@keyframes titleSlide {

    from {
        opacity: 0;
        transform: translateX(-40px);
    }

    to {
        opacity: 1;
        transform: translateX(0);
    }
}


@keyframes formSlide {

    from {
        opacity: 0;
        transform: translateX(40px);
    }

    to {
        opacity: 1;
        transform: translateX(0);
    }
}


@keyframes detailSlide {

    from {
        opacity: 0;
        transform: translateX(-25px);
    }

    to {
        opacity: 1;
        transform: translateX(0);
    }
}


@keyframes inputAppear {

    from {
        opacity: 0;
        transform: translateY(15px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}


@keyframes messagePop {

    from {
        opacity: 0;
        transform: scale(0.9);
    }

    to {
        opacity: 1;
        transform: scale(1);
    }
}


@keyframes floatCircle {

    0%, 100% {
        transform: translateY(0);
    }

    50% {
        transform: translateY(20px);
    }
}


@keyframes floatCircle2 {

    0%, 100% {
        transform: translateX(0);
    }

    50% {
        transform: translateX(20px);
    }
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 800px) {

    header {
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
        padding: 12px 14px;
    }

    header h2 {
        font-size: 18px;
        text-align: center;
    }

    nav {
        display: flex;
        overflow-x: auto;
        white-space: nowrap;
        gap: 8px;
        padding: 4px 0 2px;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }

    nav::-webkit-scrollbar {
        display: none;
    }

    nav a {
        font-size: 13px;
        padding: 6px 14px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 8px;
        display: inline-block;
        flex-shrink: 0;
        text-decoration: none;
        font-weight: 600;
    }

    nav a::after {
        display: none;
    }

    .contact-wrapper {
        grid-template-columns: 1fr;
        width: 95%;
        margin: 15px auto 40px;
        border-radius: 14px;
    }

    .contact-info {
        padding: 24px 18px;
    }

    .contact-form {
        padding: 24px 18px;
    }
}


/* =========================================================
   SMALL PHONES
========================================================= */

@media (max-width: 450px) {

    .contact-info h1 {
        font-size: 22px;
    }

    .contact-form h2 {
        font-size: 20px;
    }

    .form-group input,
    .form-group textarea {
        font-size: 14px;
        padding: 11px 12px;
    }

    .send-button {
        padding: 12px;
        font-size: 15px;
    }
}

</style>

</head>


<body>


<!-- =========================================================
     HEADER
========================================================= -->

<header>

    <h2>📞 Contact Us</h2>

    <nav>

        <a href="index.php">Home</a>

        <a href="menu.php">Menu</a>

        <a href="cart.php">Cart</a>

        <a href="my_orders.php">My Orders</a>

        <a href="contact.php">Contact</a>

        <a href="logout.php">Logout</a>

    </nav>

</header>


<!-- =========================================================
     CONTACT PAGE
========================================================= -->

<div class="contact-wrapper">


    <!-- LEFT INFORMATION -->

    <div class="contact-info">

        <h1>Let's Talk 👋</h1>

        <p>
            Have a question about your order,
            menu or college canteen?
            Send us a message and our team
            will get back to you.
        </p>


        <div class="contact-detail">

            <div class="contact-icon">
                📞
            </div>

            <span>
                +91 9952611859
            </span>

        </div>


        <div class="contact-detail">

            <div class="contact-icon">
                ✉️
            </div>

            <span>
                .com
            </span>

        </div>


        <div class="contact-detail">

            <div class="contact-icon">
                🕐
            </div>

            <span>
                Mon - Sat : 8:00 AM - 6:00 PM
            </span>

        </div>

    </div>


    <!-- RIGHT FORM -->

    <div class="contact-form">

        <h2>Contact Canteen</h2>

        <p class="subtitle">
            Fill in the form below and we will get back to you.
        </p>


        <!-- SUCCESS / ERROR MESSAGE -->

        <?php echo $message; ?>


        <form method="POST">


            <div class="form-group">

                <label>Your Name</label>

                <input
                    type="text"
                    name="name"
                    value="<?php echo htmlspecialchars($userName); ?>"
                    placeholder="Enter your name"
                    required
                >

            </div>


            <div class="form-group">

                <label>Email Address</label>

                <input
                    type="email"
                    name="email"
                    value="<?php echo htmlspecialchars($userEmail); ?>"
                    placeholder="Enter your email"
                    required
                >

            </div>


            <div class="form-group">

                <label>Subject</label>

                <input
                    type="text"
                    name="subject"
                    placeholder="What can we help you with?"
                    required
                >

            </div>


            <div class="form-group">

                <label>Your Message</label>

                <textarea
                    name="message"
                    placeholder="Write your message here..."
                    required
                ></textarea>

            </div>


            <button
                type="submit"
                name="send"
                class="send-button"
            >
                📩 Send Message
            </button>


        </form>

    </div>

</div>


</body>

</html>