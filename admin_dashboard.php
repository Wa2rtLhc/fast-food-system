<?php
include 'config.php';
if(session_status()===PHP_SESSION_NONE){ session_start(); }

if(!isset($_SESSION['user_id']) || $_SESSION['role']!='admin'){
    header("Location: login.php"); exit;
}

$admin_id = $_SESSION['user_id'];
$admin_name = $conn->query("SELECT username FROM users WHERE id=$admin_id")->fetch_assoc()['username'];

// =====================
// Tabs
// =====================
$active_tab = $_GET['tab'] ?? 'employees';

// =====================
// Search Inputs
// =====================
$employee_search = $conn->real_escape_string($_GET['employee_search'] ?? '');
$inventory_search = $conn->real_escape_string($_GET['inventory_search'] ?? '');
$inventory_start = $_GET['start_date'] ?? date('Y-m-01');
$inventory_end = $_GET['end_date'] ?? date('Y-m-t');
$inventory_keyword = $conn->real_escape_string($_GET['inventory_keyword'] ?? '');
$emp_report_search = $conn->real_escape_string($_GET['emp_report_search'] ?? '');

// =====================
// Fetch Data
// =====================

// Employees
$employees = $conn->query("SELECT id, username, phone, national_id, daily_rate FROM users WHERE role='employee' AND username LIKE '%$employee_search%' ORDER BY id DESC");

// Inventory
$inventory_query = "SELECT i.*, b.name as branch_name, u.username as added_by_name 
                    FROM inventory i 
                    JOIN branches b ON i.branch_id=b.id 
                    JOIN users u ON i.added_by=u.id
                    WHERE (b.name LIKE '%$inventory_search%' OR i.item_name LIKE '%$inventory_keyword%') 
                    ORDER BY i.id DESC";
$inventory = $conn->query($inventory_query);

// Inventory Logs (last 50)
$inventory_logs = $conn->query("SELECT il.*, i.item_name, u.username as changed_by_name
                                FROM inventory_log il
                                LEFT JOIN inventory i ON il.inventory_id=i.id
                                JOIN users u ON il.changed_by=u.id
                                ORDER BY il.changed_at DESC LIMIT 50");

// Monthly Payroll Calculation
$monthly_pay=[];
$start_month=date('Y-m-01');
$end_month=date('Y-m-t');

$emp_res=$conn->query("SELECT id, username, daily_rate FROM users WHERE role='employee'");
while($e=$emp_res->fetch_assoc()){
    $emp_id=$e['id'];
    $days_worked_res=$conn->query("SELECT COUNT(DISTINCT DATE(clock_in)) as days_worked
                                  FROM attendance 
                                  WHERE user_id=$emp_id AND clock_in BETWEEN '$start_month' AND '$end_month'");
    $days_worked=$days_worked_res->fetch_assoc()['days_worked'] ?? 0;
    $total_pay=$days_worked * $e['daily_rate'];
    $monthly_pay[$emp_id]=[
        'username'=>$e['username'],
        'days_worked'=>$days_worked,
        'total_pay'=>$total_pay
    ];
}

// Employee Clock-in/Out Detailed
$attendance_detail=[];
$res=$conn->query("SELECT u.id as emp_id, u.username, DATE(a.clock_in) as date, a.clock_in, a.clock_out
                   FROM attendance a
                   JOIN users u ON a.user_id=u.id
                   WHERE u.role='employee' AND u.username LIKE '%$emp_report_search%' AND a.clock_in BETWEEN '$start_month' AND '$end_month'
                   ORDER BY u.id, a.clock_in ASC");
while($row=$res->fetch_assoc()){
    $attendance_detail[$row['emp_id']][]=$row;
}

// Employee total payroll
$total_payroll=array_sum(array_map(fn($emp)=>$emp['total_pay'],$monthly_pay));

// Inventory totals per branch
$inventory_totals=[];
$res=$conn->query("SELECT b.name as branch_name, SUM(i.quantity) as total_qty, SUM(i.quantity*i.cost) as total_cost
                   FROM inventory i
                   JOIN branches b ON i.branch_id=b.id 
                   GROUP BY b.id");
while($row=$res->fetch_assoc()){
    $inventory_totals[$row['branch_name']]=['total_qty'=>$row['total_qty'],'total_cost'=>$row['total_cost']];
}

// Analytics Charts
$inventory_chart=[];
$res=$conn->query("SELECT item_name, quantity FROM inventory");
while($row=$res->fetch_assoc()){$inventory_chart[$row['item_name']]=$row['quantity'];}

$attendance_chart=[];
$res=$conn->query("SELECT DATE(clock_in) as date, COUNT(*) as total 
                   FROM attendance WHERE clock_in>=DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                   GROUP BY DATE(clock_in) ORDER BY DATE(clock_in) ASC");
while($row=$res->fetch_assoc()){$attendance_chart[$row['date']]=$row['total'];}
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Dashboard</title>
<link rel="manifest" href="manifest.json">
<link rel="apple-touch-icon" href="icon.jpg">
<meta name="theme-color" content="#d6b928ff">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
tailwind.config={theme:{extend:{colors:{'911-yellow':'#FFD700','911-black':'#121212','911-gray':'#E5E5E5'}}}}
</script>
</head>
<body class="bg-911-black text-911-gray min-h-screen">

<div class="max-w-7xl mx-auto p-4 sm:p-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-center justify-between p-4 border-b border-911-yellow mb-4">
        <h1 class="text-3xl sm:text-4xl font-bold text-911-yellow mb-2 sm:mb-0 flex items-center gap-2">
            <img src="icon.jpg" alt="911" class="h-16 w-24">
            Welcome, <?= $admin_name ?>
        </h1>
        <a href="logout.php" class="bg-gray-800 text-911-yellow px-4 py-2 rounded font-bold">Logout</a>
    </div>

    <!-- Analytics Charts -->
    <div class="grid md:grid-cols-2 gap-4 mb-4">
        <div class="bg-911-black border border-911-yellow rounded-lg p-4 shadow">
            <h2 class="text-911-yellow font-bold text-xl mb-2 text-center">Inventory Levels</h2>
            <canvas id="inventoryChart" height="150"></canvas>
        </div>
        <div class="bg-911-black border border-911-yellow rounded-lg p-4 shadow">
            <h2 class="text-911-yellow font-bold text-xl mb-2 text-center">Attendance Last 7 Days</h2>
            <canvas id="attendanceChart" height="150"></canvas>
        </div>
    </div>

    <!-- Tabs -->
    <div class="flex flex-wrap gap-2 mb-4">
        <?php
        $tabs=['employees'=>'Employees','inventory'=>'Inventory','inventory_logs'=>'Inventory Logs','employee_reports'=>'Employee Reports','inventory_reports'=>'Inventory Reports'];
        foreach($tabs as $key=>$label){
            $active_class=$active_tab==$key?'bg-911-yellow text-911-black':'bg-911-black text-911-yellow border border-911-yellow';
            echo "<a href='?tab=$key' class='px-4 py-2 rounded font-semibold $active_class'>$label</a>";
        }
        ?>
    </div>

    <!-- TAB CONTENT -->
    <?php if($active_tab=='employees'): ?>
        <form method="GET" class="mb-2 flex gap-2 flex-wrap">
            <input type="hidden" name="tab" value="employees">
            <input type="text" name="employee_search" placeholder="Search by name" 
                   value="<?= htmlspecialchars($_GET['employee_search'] ?? '') ?>"
                   class="px-2 py-1 rounded text-black flex-1">
            <button type="submit" class="bg-911-yellow text-911-black px-4 py-1 rounded">Search</button>
        </form>

        <div class="overflow-x-auto bg-911-black border border-911-yellow rounded p-4 shadow">
            <table class="min-w-full text-911-gray">
                <thead>
                    <tr class="text-911-yellow">
                        <th>Username</th>
                        <th>Phone</th>
                        <th>National ID</th>
                        <th>Daily Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($e=$employees->fetch_assoc()): ?>
                    <tr>
                        <td><?= $e['username'] ?></td>
                        <td><?= $e['phone'] ?></td>
                        <td>
                            <span class="national-id" id="nid-<?= $e['id'] ?>" style="display:none;"><?= $e['national_id'] ?></span>
                            <button type="button" onclick="toggleNID(<?= $e['id'] ?>)" class="bg-gray-700 px-2 py-1 rounded text-sm">Show/Hide</button>
                        </td>
                        <td>
                            <input type="number" class="px-1 py-1 text-black w-24 daily-rate-input" data-emp-id="<?= $e['id'] ?>" value="<?= $e['daily_rate'] ?>">
                            <button type="button" onclick="updateRate(<?= $e['id'] ?>)" class="bg-911-yellow text-911-black px-2 rounded text-sm">Update</button>
                        </td>
                        <td></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    <?php elseif($active_tab=='inventory'): ?>
        <form method="GET" class="mb-2 flex gap-2 flex-wrap">
            <input type="hidden" name="tab" value="inventory">
            <input type="text" name="inventory_search" placeholder="Search by branch/item" value="<?= htmlspecialchars($inventory_search) ?>" class="px-2 py-1 rounded text-black flex-1">
            <button type="submit" class="bg-911-yellow text-911-black px-4 py-1 rounded">Search</button>
        </form>

        <div class="overflow-x-auto bg-911-black border border-911-yellow rounded p-4 shadow">
            <table class="min-w-full text-911-gray">
                <thead>
                    <tr class="text-911-yellow">
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Cost</th>
                        <th>Branch</th>
                        <th>Added By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($i=$inventory->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i['item_name'] ?></td>
                        <td><?= $i['quantity'] ?></td>
                        <td><?= number_format($i['cost'],2) ?></td>
                        <td><?= $i['branch_name'] ?></td>
                        <td><?= $i['added_by_name'] ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    <?php elseif($active_tab=='inventory_logs'): ?>
        <div class="overflow-x-auto bg-911-black border border-911-yellow rounded p-4 shadow">
            <table class="min-w-full text-911-gray">
                <thead>
                    <tr class="text-911-yellow">
                        <th>Item</th>
                        <th>Action</th>
                        <th>Quantity Before</th>
                        <th>Quantity After</th>
                        <th>Changed By</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($log=$inventory_logs->fetch_assoc()): ?>
                    <tr>
                        <td><?= $log['item_name'] ?? 'Deleted' ?></td>
                        <td><?= $log['action'] ?></td>
                        <td><?= $log['quantity_before'] ?></td>
                        <td><?= $log['quantity_after'] ?></td>
                        <td><?= $log['changed_by_name'] ?></td>
                        <td><?= $log['changed_at'] ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    <?php elseif($active_tab=='employee_reports'): ?>
        <form method="GET" class="mb-2 flex gap-2 flex-wrap">
            <input type="hidden" name="tab" value="employee_reports">
            <input type="text" name="emp_report_search" placeholder="Search employee" value="<?= htmlspecialchars($emp_report_search) ?>" class="px-2 py-1 rounded text-black flex-1">
            <button type="submit" class="bg-911-yellow text-911-black px-4 py-1 rounded">Filter</button>
        </form>
<form method="GET" action="export_employees.php" style="display:inline;">
    <button type="submit" class="btn btn-primary">
        Export Employee Report
    </button>
</form>
        <div class="overflow-x-auto bg-911-black border border-911-yellow rounded p-4 shadow">
            <table class="min-w-full text-911-gray">
                <thead>
                    <tr class="text-911-yellow">
                        <th>Employee</th>
                        <th>Date</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Days Worked</th>
                        <th>Total Pay</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($monthly_pay as $emp_id=>$emp): ?>
                        <?php 
                        $emp_attendance = $attendance_detail[$emp_id] ?? [];
                        foreach($emp_attendance as $att): ?>
                        <tr>
                            <td><?= $emp['username'] ?></td>
                            <td><?= $att['date'] ?></td>
                            <td><?= $att['clock_in'] ?></td>
                            <td><?= $att['clock_out'] ?? '-' ?></td>
                            <td><?= $emp['days_worked'] ?></td>
                            <td><?= number_format($emp['total_pay'],2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    <tr class="text-911-yellow font-bold border-t border-911-yellow">
                        <td colspan="5">Total Payroll</td>
                        <td><?= number_format($total_payroll,2) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

    <?php elseif($active_tab=='inventory_reports'): ?>
        <form method="GET" class="mb-2 flex gap-2 flex-wrap">
            <input type="hidden" name="tab" value="inventory_reports">
            <input type="date" name="start_date" value="<?= $inventory_start ?>" class="px-2 py-1 rounded text-black">
            <input type="date" name="end_date" value="<?= $inventory_end ?>" class="px-2 py-1 rounded text-black">
            <input type="text" name="inventory_keyword" placeholder="Search by item/branch" value="<?= htmlspecialchars($inventory_keyword) ?>" class="px-2 py-1 rounded text-black flex-1">
            <button type="submit" class="bg-911-yellow text-911-black px-4 py-1 rounded">Filter</button>
        </form>
<form method="GET" action="export_inventory.php" style="display:inline;">
    <button type="submit" class="btn btn-success">
        Export Inventory Report
    </button>
</form>
        <div class="overflow-x-auto bg-911-black border border-911-yellow rounded p-4 shadow">
            <table class="min-w-full text-911-gray">
                <thead>
                    <tr class="text-911-yellow">
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Cost</th>
                        <th>Branch</th>
                        <th>Added Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $inventory->data_seek(0);
                    while($i=$inventory->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i['item_name'] ?></td>
                        <td><?= $i['quantity'] ?></td>
                        <td><?= number_format($i['cost'],2) ?></td>
                        <td><?= $i['branch_name'] ?></td>
                        <td><?= $i['created_at'] ?? '-' ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<script>
// Toggle National ID per employee
function toggleNID(empId){
    const el = document.getElementById('nid-'+empId);
    el.style.display = (el.style.display === 'none') ? 'inline' : 'none';
}

// Update daily rate inline using AJAX
function updateRate(empId){
    const rate = document.querySelector('.daily-rate-input[data-emp-id="'+empId+'"]').value;
    $.post('update_daily_rate_ajax.php', {employee_id:empId, daily_rate:rate}, function(res){
        alert(res.message);
        if(res.success) location.reload();
    }, 'json');
}

// Charts
const inventoryData=<?= json_encode($inventory_chart) ?>;
const attendanceData=<?= json_encode($attendance_chart) ?>;

new Chart(document.getElementById('inventoryChart'),{
    type:'bar',
    data:{ labels:Object.keys(inventoryData), datasets:[{label:'Quantity', data:Object.values(inventoryData), backgroundColor:'#FFD700'}] },
    options:{ responsive:true, plugins:{ legend:{ display:false } }, scales:{ x:{ ticks:{ color:'#FFD700' }, grid:{ color:'#444' } }, y:{ ticks:{ color:'#FFD700' }, grid:{ color:'#444' } } } }
});

new Chart(document.getElementById('attendanceChart'),{
    type:'line',
    data:{ labels:Object.keys(attendanceData), datasets:[{label:'Total Clock-ins', data:Object.values(attendanceData), backgroundColor:'#FFD700', borderColor:'#FFD700', fill:false}] },
    options:{ responsive:true, plugins:{ legend:{ display:false } }, scales:{ x:{ ticks:{ color:'#FFD700' }, grid:{ color:'#444' } }, y:{ ticks:{ color:'#FFD700' }, grid:{ color:'#444' } } } }
});
</script>
<script>
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('service-worker.js')
    .then(() => console.log('Service Worker Registered'))
    .catch(err => console.log('Service Worker failed:', err));
}
</script>
</body>
</html>