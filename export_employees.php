<?php
include 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("Unauthorized access");
}

// CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=employee_report.csv');

$output = fopen('php://output', 'w');

// Column titles
fputcsv($output, [
    'Employee Name',
    'Branch',
    'Daily Rate',
    'Days Worked',
    'Total Pay'
]);

$query = "
    SELECT 
        u.username,
        b.name AS branch,
        u.daily_rate,
        COUNT(DISTINCT DATE(a.clock_in)) AS days_worked,
        (COUNT(DISTINCT DATE(a.clock_in)) * u.daily_rate) AS total_pay
    FROM users u
    JOIN branches b ON u.branch_id = b.id
    LEFT JOIN attendance a ON u.id = a.user_id
    WHERE u.role = 'employee'
    GROUP BY u.id
";

$result = $conn->query($query);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}

fclose($output);
exit;