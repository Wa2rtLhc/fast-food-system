<?php
include 'config.php';
if(session_status()===PHP_SESSION_NONE){ session_start(); }

header('Content-Type: application/json');

if(!isset($_SESSION['role']) || $_SESSION['role']!='admin'){
    echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit;
}

$employee_id = intval($_POST['employee_id'] ?? 0);
$daily_rate = floatval($_POST['daily_rate'] ?? 0);

if($employee_id > 0 && $daily_rate >= 0){
    $conn->query("UPDATE users SET daily_rate=$daily_rate WHERE id=$employee_id");
    echo json_encode(['success'=>true,'message'=>'Daily rate updated successfully']);
}else{
    echo json_encode(['success'=>false,'message'=>'Invalid input']);
}