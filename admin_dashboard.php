<?php
include 'config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Only allow admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin'){
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['user_id'];
$admin_name = $conn->query("SELECT username FROM users WHERE id=$admin_id")->fetch_assoc()['username'];

// Handle updating pay
if(isset($_POST['update_pay'])){
    $user_id = intval($_POST['user_id']);
    $pay = floatval($_POST['pay']);
    if($user_id > 0){
        $conn->query("UPDATE users SET pay=$pay WHERE id=$user_id");
        header("Location: admin_dashboard.php");
        exit;
    }
}

// Fetch data
$users = $conn->query("SELECT * FROM users ORDER BY id DESC");
$inventory = $conn->query("SELECT i.*, u.username as added_by_name FROM inventory i JOIN users u ON i.added_by=u.id ORDER BY i.id DESC");
$history = $conn->query("SELECT il.*, i.item_name, u.username as changed_by_name
                         FROM inventory_log il
                         JOIN inventory i ON il.inventory_id = i.id
                         JOIN users u ON il.changed_by = u.id
                         ORDER BY il.changed_at DESC");
$attendance = $conn->query("SELECT a.id, u.username, u.role, a.clock_in, a.clock_out, u.pay
                            FROM attendance a 
                            JOIN users u ON a.user_id = u.id
                            ORDER BY a.id DESC");

// Analytics
$attendance_data = [];
$result = $conn->query("
    SELECT DATE(clock_in) as date, COUNT(*) as total
    FROM attendance
    WHERE clock_in >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(clock_in)
    ORDER BY DATE(clock_in) ASC
");
while($row = $result->fetch_assoc()){ $attendance_data[$row['date']] = $row['total']; }

$pay_data = [];
$result = $conn->query("SELECT username, pay FROM users WHERE role='employee'");
while($row = $result->fetch_assoc()){ $pay_data[$row['username']] = floatval($row['pay']); }

$inventory_data = [];
$result = $conn->query("SELECT item_name, quantity FROM inventory");
while($row = $result->fetch_assoc()){ $inventory_data[$row['item_name']] = intval($row['quantity']); }
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: { '911-yellow': '#FFD700', '911-black': '#121212', '911-gray': '#E5E5E5' }
    }
  }
}
</script>
</head>
<body class="bg-911-black text-911-gray min-h-screen">

<div class="max-w-7xl mx-auto p-6 space-y-6">

    <!-- Welcome -->
    <div class="p-4 bg-911-black border-b border-911-yellow text-center rounded-lg">
        <h1 class="text-4xl md:text-5xl font-bold text-911-yellow">Welcome, <?= $admin_name ?></h1>
    </div>

    <!-- Analytics Charts -->
    <div class="grid md:grid-cols-3 gap-6">
        <div class="bg-911-black border border-911-yellow rounded-lg p-4 shadow overflow-auto">
            <h2 class="text-911-yellow font-bold text-xl mb-2 text-center">Attendance (Last 7 Days)</h2>
            <canvas id="attendanceChart"></canvas>
        </div>
        <div class="bg-911-black border border-911-yellow rounded-lg p-4 shadow overflow-auto">
            <h2 class="text-911-yellow font-bold text-xl mb-2 text-center">Employee Pay Distribution</h2>
            <canvas id="payChart"></canvas>
        </div>
        <div class="bg-911-black border border-911-yellow rounded-lg p-4 shadow overflow-auto">
            <h2 class="text-911-yellow font-bold text-xl mb-2 text-center">Inventory Levels</h2>
            <canvas id="inventoryChart"></canvas>
        </div>
    </div>

    <!-- Tabs -->
    <div class="flex justify-center gap-4 flex-wrap">
        <button id="tab-employees" class="px-6 py-3 rounded-lg font-bold transition">Employees</button>
        <button id="tab-inventory" class="px-6 py-3 rounded-lg font-bold transition">Inventory</button>
        <button id="tab-history" class="px-6 py-3 rounded-lg font-bold transition">Inventory History</button>
        <button id="tab-reports" class="px-6 py-3 rounded-lg font-bold transition">Reports</button>
    </div>

    <!-- Tab Contents -->
    <div id="content-employees" class="hidden bg-911-black border border-911-yellow rounded-lg p-4 shadow overflow-x-auto max-h-[500px]">
        <table class="min-w-full">
            <thead>
                <tr class="text-911-yellow">
                    <th class="px-4 py-2 border-b border-911-yellow">ID</th>
                    <th class="px-4 py-2 border-b border-911-yellow">Username</th>
                    <th class="px-4 py-2 border-b border-911-yellow">Role</th>
                    <th class="px-4 py-2 border-b border-911-yellow">Pay</th>
                    <th class="px-4 py-2 border-b border-911-yellow">Update Pay</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($users as $u): ?>
                <tr class="text-911-gray">
                    <td class="px-4 py-2 border-b border-911-yellow"><?= $u['id'] ?></td>
                    <td class="px-4 py-2 border-b border-911-yellow"><?= $u['username'] ?></td>
                    <td class="px-4 py-2 border-b border-911-yellow"><?= $u['role'] ?></td>
                    <td class="px-4 py-2 border-b border-911-yellow"><?= number_format($u['pay'],2) ?></td>
                    <td class="px-4 py-2 border-b border-911-yellow">
                        <form method="POST">
                            <input type="number" name="pay" class="px-2 py-1 rounded text-black w-24" placeholder="New Pay" required>
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" name="update_pay" class="bg-911-yellow text-911-black px-2 py-1 rounded">Update</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="content-inventory" class="hidden bg-911-black border border-911-yellow rounded-lg p-4 shadow overflow-x-auto max-h-[500px]">
        <table class="min-w-full">
            <thead>
                <tr class="text-911-yellow">
                    <th class="px-4 py-2 border-b border-911-yellow">ID</th>
                    <th class="px-4 py-2 border-b border-911-yellow">Item Name</th>
                    <th class="px-4 py-2 border-b border-911-yellow">Quantity</th>
                    <th class="px-4 py-2 border-b border-911-yellow">Added By</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($inventory as $i): ?>
                <tr class="text-911-gray">
                    <td class="px-4 py-2 border-b border-911-yellow"><?= $i['id'] ?></td>
                    <td class="px-4 py-2 border-b border-911-yellow"><?= $i['item_name'] ?></td>
                    <td class="px-4 py-2 border-b border-911-yellow"><?= $i['quantity'] ?></td>
                    <td class="px-4 py-2 border-b border-911-yellow"><?= $i['added_by_name'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="content-history" class="hidden bg-911-black border border-911-yellow rounded-lg p-4 shadow overflow-x-auto max-h-[500px]">
        <table class="min-w-full">
            <thead>
                <tr class="text-911-yellow">
                    <th class="px-4 py-2 border-b border-911-yellow">ID</th>
                    <th class="px-4 py-2 border-b border-911-yellow">Item</th>
                    <th class="px-4 py-2 border-b border-911-yellow">Action</th>
                    <th class="px-4 py-2 border-b border-911-yellow">Qty Before</th>
                    <th class="px-4 py-2 border-b border-911-yellow">Qty After</th>
                    <th class="px-4 py-2 border-b border-911-yellow">Changed By</th>
                    <th class="px-4 py-2 border-b border-911-yellow">Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($history as $h): ?>
                <tr class="text-911-gray">
                    <td class="px-4 py-2 border-b border-911-yellow"><?= $h['id'] ?></td>
                    <td class="px-4 py-2 border-b border-911-yellow"><?= $h['item_name'] ?></td>
                    <td class="px-4 py-2 border-b border-911-yellow"><?= $h['action'] ?></td>
                    <td class="px-4 py-2 border-b border-911-yellow"><?= $h['quantity_before'] ?></td>
                    <td class="px-4 py-2 border-b border-911-yellow"><?= $h['quantity_after'] ?></td>
                    <td class="px-4 py-2 border-b border-911-yellow"><?= $h['changed_by_name'] ?></td>
                    <td class="px-4 py-2 border-b border-911-yellow"><?= $h['changed_at'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="content-reports" class="hidden bg-911-black border border-911-yellow rounded-lg p-4 shadow overflow-x-auto max-h-[500px]">
        <table class="min-w-full">
            <thead>
                <tr class="text-911-yellow">
                    <th class="px-4 py-2 border-b border-911-yellow">Username</th>
                    <th class="px-4 py-2 border-b border-911-yellow">Clock In</th>
                    <th class="px-4 py-2 border-b border-911-yellow">Clock Out</th>
                    <th class="px-4 py-2 border-b border-911-yellow">Pay</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($attendance as $a): ?>
                <tr class="text-911-gray">
                    <td class="px-4 py-2 border-b border-911-yellow"><?= $a['username'] ?></td>
                    <td class="px-4 py-2 border-b border-911-yellow"><?= $a['clock_in'] ?></td>
                    <td class="px-4 py-2 border-b border-911-yellow"><?= $a['clock_out'] ?></td>
                    <td class="px-4 py-2 border-b border-911-yellow"><?= number_format($a['pay'],2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Logout -->
    <div class="text-center mt-6">
        <a href="logout.php" class="inline-block bg-gray-800 text-911-yellow px-6 py-3 rounded-lg font-bold hover:brightness-110 transition">Logout</a>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const tabs = {
        'employees': document.getElementById('tab-employees'),
        'inventory': document.getElementById('tab-inventory'),
        'history': document.getElementById('tab-history'),
        'reports': document.getElementById('tab-reports')
    };
    const contents = {
        'employees': document.getElementById('content-employees'),
        'inventory': document.getElementById('content-inventory'),
        'history': document.getElementById('content-history'),
        'reports': document.getElementById('content-reports')
    };
    function activateTab(active){
        for(const key in contents){
            if(key === active){
                contents[key].classList.remove('hidden');
                tabs[key].classList.remove('bg-gray-800','text-911-yellow');
                tabs[key].classList.add('bg-911-yellow','text-911-black');
            } else {
                contents[key].classList.add('hidden');
                tabs[key].classList.remove('bg-911-yellow','text-911-black');
                tabs[key].classList.add('bg-gray-800','text-911-yellow');
            }
        }
    }
    activateTab('employees'); // default
    for(const key in tabs){ tabs[key].addEventListener('click', ()=> activateTab(key)); }

    // Charts
    const attendanceData = <?= json_encode($attendance_data) ?>;
    const payData = <?= json_encode($pay_data) ?>;
    const inventoryData = <?= json_encode($inventory_data) ?>;

    new Chart(document.getElementById('attendanceChart'), {
        type: 'line',
        data: { labels: Object.keys(attendanceData), datasets: [{ label: 'Clock-ins', data: Object.values(attendanceData), borderColor: '#FFD700', backgroundColor: 'rgba(255,215,0,0.2)', fill: true, tension:0.4 }] },
        options: { responsive:true, plugins:{ legend:{ labels:{ color:'#FFD700' } } }, scales:{ x:{ ticks:{ color:'#FFD700' }, grid:{ color:'#444' } }, y:{ ticks:{ color:'#FFD700' }, grid:{ color:'#444' } } } }
    });

    new Chart(document.getElementById('payChart'), {
        type:'bar', data:{ labels:Object.keys(payData), datasets:[{ label:'Pay', data:Object.values(payData), backgroundColor:'#FFD700' }] },
        options:{ responsive:true, plugins:{ legend:{ display:false } }, scales:{ x:{ ticks:{ color:'#FFD700' }, grid:{ color:'#444' } }, y:{ ticks:{ color:'#FFD700' }, grid:{ color:'#444' } } } }
    });

    new Chart(document.getElementById('inventoryChart'), {
        type:'bar', data:{ labels:Object.keys(inventoryData), datasets:[{ label:'Quantity', data:Object.values(inventoryData), backgroundColor:'#FFD700' }] },
        options:{ responsive:true, plugins:{ legend:{ display:false } }, scales:{ x:{ ticks:{ color:'#FFD700' }, grid:{ color:'#444' } }, y:{ ticks:{ color:'#FFD700' }, grid:{ color:'#444' } } } }
    });
});
</script>

</body>
</html>
