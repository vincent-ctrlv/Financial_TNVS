<?php
require 'c:/xampp/htdocs/adminfinance1/db.php';
$name = 'Admin User';
$email = 'admin@example.com';
$password = 'admin123';
$role = 'admin';
$hash = password_hash($password, PASSWORD_BCRYPT);
$stmt = $mysqli->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $name, $email, $hash, $role);
if ($stmt->execute()) {
    echo "User created successfully\n";
} else {
    echo "Error: " . $stmt->error . "\n";
}
$stmt->close();
$mysqli->close();
?>
