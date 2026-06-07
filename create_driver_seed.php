<?php
require_once __DIR__ . '/includes/db.php';

$name = 'John Driver';
$email = 'driver@example.com';
$role = 'driver';
$password = 'driver123';
$plate_no = 'XYZ 7890';
$reference_no = 'REF-1002';
$address = '123 Driver St, Manila';

// Check if user exists
$stmt = $mysqli->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        echo "User with email {$email} already exists.\n";
        $stmt->close();
        exit;
    }
    $stmt->close();
}

$hash = password_hash($password, PASSWORD_BCRYPT);
$insert = $mysqli->prepare("INSERT INTO users (name, email, role, password_hash, plate_no, reference_no, address) VALUES (?, ?, ?, ?, ?, ?, ?)");
if ($insert) {
    $insert->bind_param("sssssss", $name, $email, $role, $hash, $plate_no, $reference_no, $address);
    if ($insert->execute()) {
        echo "Successfully created driver user:\n";
        echo "Email: {$email}\n";
        echo "Password: {$password}\n";
        echo "Plate No: {$plate_no}\n";
        echo "Reference No: {$reference_no}\n";
        echo "Address: {$address}\n";
    } else {
        echo "Error creating user: " . $insert->error . "\n";
    }
    $insert->close();
} else {
    echo "Prepare failed: " . $mysqli->error . "\n";
}
?>
