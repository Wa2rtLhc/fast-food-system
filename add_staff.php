<?php
include 'config.php';

$username = $conn->real_escape_string($_POST['username']);
$phone = $conn->real_escape_string($_POST['phone']);
$national_id = $conn->real_escape_string($_POST['national_id']);
$role = $conn->real_escape_string($_POST['role']);

// Generate a random 4-digit PIN
$pin = rand(1000, 9999);

// Hash the PIN before saving
$pin_hashed = password_hash($pin, PASSWORD_DEFAULT);

// Insert into database
$stmt = $conn->prepare("INSERT INTO users(username, phone, national_id, role, pin, status) VALUES (?, ?, ?, ?, ?, 'active')");
$stmt->bind_param("sssss", $username, $phone, $national_id, $role, $pin_hashed);

if($stmt->execute()){
    // Return the plain PIN to the frontend
    echo json_encode(['success'=>true, 'pin'=>$pin]);
} else {
    echo json_encode(['success'=>false, 'error'=>$stmt->error]);
}
?>