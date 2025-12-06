<?php
include 'config.php';

// Only admin can access
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin'){
    header("Location: login.php");
    exit;
}

// Get month & year from GET parameters
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="monthly_payroll_'.$month.'_'.$year.'.csv"');

$output = fopen('php://output','w');

// CSV Header
fputcsv($output, ['Username','Total Pay (Ksh)']);

// Fetch payroll data
$payroll = $conn->query("SELECT users.username, SUM(pay.amount) as total_amount
                         FROM pay
                         JOIN users ON pay.user_id = users.id
                         WHERE pay.month = $month AND pay.year = $year
                         GROUP BY pay.user_id");

while($row = $payroll->fetch_assoc()){
    fputcsv($output, [$row['username'], number_format($row['total_amount'], 2)]);
}

fclose($output);
exit;
?>
