<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'canteen_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$password = password_hash('password123', PASSWORD_DEFAULT);
$sql = "INSERT INTO users (name, email, password, role) VALUES ('Test Student', 'student@test.com', '$password', 'student') ON DUPLICATE KEY UPDATE password='$password'";
if ($conn->query($sql) === TRUE) {
    echo "User created successfully";
} else {
    echo "Error: " . $conn->error;
}
$conn->close();
?>
