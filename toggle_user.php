<?php
include 'config.php';
$id=$_POST['id'];
$status=$_POST['status'];
$conn->query("UPDATE users SET status='$status' WHERE id=$id");