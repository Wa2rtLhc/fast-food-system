<?php
include 'config.php';
if(session_status()===PHP_SESSION_NONE) session_start();

// Only admin can reset PIN
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
    echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit;
}

if(isset($_POST['user_id'])){
    $user_id = (int)$_POST['user_id'];

    // Generate new 4-digit PIN
    $new_pin = rand(1000,9999);

    // Hash it
    $hashed_pin = password_hash($new_pin, PASSWORD_DEFAULT);

    // Update in DB
    $update = $conn->query("UPDATE users SET pin='$hashed_pin' WHERE id=$user_id");

    if($update){
        echo json_encode(['success'=>true, 'pin'=>$new_pin]);
    } else {
        echo json_encode(['success'=>false,'message'=>'Database update failed']);
    }
} else {
    echo json_encode(['success'=>false,'message'=>'No user_id provided']);
}
?>