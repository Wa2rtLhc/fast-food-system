<?php
include 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("Unauthorized access");
}

// CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=inventory_report.csv');

$output = fopen('php://output', 'w');

// Column headings
fputcsv($output, [
    'Item Name',
    'Branch',
    'Quantity',
    'Price',
    'Created At'
]);

$query = "
    SELECT i.item_name, b.name AS branch, i.quantity, i.price, i.created_at
    FROM inventory i
    JOIN branches b ON i.branch_id = b.id
    ORDER BY i.created_at DESC
";

$result = $conn->query($query);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}

fclose($output);
exit;