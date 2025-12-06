<?php
include 'config.php';
if(session_status()===PHP_SESSION_NONE){ session_start(); }

if(!isset($_SESSION['user_id']) || $_SESSION['role']!='manager'){
    header("Location: login.php"); exit;
}

$manager_id = $_SESSION['user_id'];
$manager_name = $conn->query("SELECT username FROM users WHERE id=$manager_id")->fetch_assoc()['username'];

// =====================
// Inventory Operations
// =====================

// Add inventory
if(isset($_POST['add_inventory'])){
    $item_name = $conn->real_escape_string($_POST['item_name']);
    $quantity = intval($_POST['quantity']);
    $conn->query("INSERT INTO inventory (item_name, quantity, added_by) VALUES ('$item_name', $quantity, $manager_id)");
    $inventory_id = $conn->insert_id;
    if($inventory_id > 0){
        $conn->query("INSERT INTO inventory_log (inventory_id, action, quantity_before, quantity_after, changed_by) VALUES ($inventory_id,'added',0,$quantity,$manager_id)");
    }
    header("Location: manager_dashboard.php"); exit;
}

// Edit inventory
if(isset($_POST['edit_inventory']) && isset($_POST['id'])){
    $inv_id = intval($_POST['id']);
    $new_qty = intval($_POST['quantity']);
    $inv_res = $conn->query("SELECT quantity FROM inventory WHERE id=$inv_id");
    $old_qty = $inv_res->num_rows>0 ? $inv_res->fetch_assoc()['quantity'] : 0;
    $conn->query("UPDATE inventory SET quantity=$new_qty WHERE id=$inv_id");
    $conn->query("INSERT INTO inventory_log (inventory_id, action, quantity_before, quantity_after, changed_by) VALUES ($inv_id,'updated',$old_qty,$new_qty,$manager_id)");
    header("Location: manager_dashboard.php"); exit;
}

// Delete inventory
if(isset($_POST['delete_inventory']) && isset($_POST['id'])){
    $inv_id = intval($_POST['id']);
    $inv_res = $conn->query("SELECT quantity FROM inventory WHERE id=$inv_id");
    $old_qty = $inv_res->num_rows>0 ? $inv_res->fetch_assoc()['quantity'] : 0;
    $conn->query("DELETE FROM inventory WHERE id=$inv_id");
    $conn->query("INSERT INTO inventory_log (inventory_id, action, quantity_before, quantity_after, changed_by) VALUES ($inv_id,'deleted',$old_qty,0,$manager_id)");
    header("Location: manager_dashboard.php"); exit;
}

// Confirm employee shift
if(isset($_POST['confirm_shift']) && isset($_POST['attendance_id'])){
    $att_id = intval($_POST['attendance_id']);
    $conn->query("UPDATE attendance SET confirmed=1 WHERE id=$att_id");
    header("Location: manager_dashboard.php"); exit;
}

// =====================
// Fetch Data
// =====================

// Inventory
$inventory = $conn->query("SELECT i.*, u.username as added_by_name FROM inventory i JOIN users u ON i.added_by=u.id ORDER BY i.id DESC");

// Inventory log notifications (last 5)
$notifications = $conn->query("SELECT il.*, u.username as changed_by_name, i.item_name FROM inventory_log il LEFT JOIN inventory i ON il.inventory_id=i.id JOIN users u ON il.changed_by=u.id ORDER BY il.changed_at DESC LIMIT 5");

// Employees currently clocked in
$employees_clocked_in = $conn->query("SELECT u.id,u.username,a.id as attendance_id,a.clock_in FROM attendance a JOIN users u ON a.user_id=u.id WHERE a.clock_out IS NULL");

// Analytics data
$inventory_chart=[];
$res=$conn->query("SELECT item_name, quantity FROM inventory");
while($row=$res->fetch_assoc()){$inventory_chart[$row['item_name']]=$row['quantity'];}

$attendance_chart=[];
$res=$conn->query("SELECT DATE(clock_in) as date, COUNT(*) as total FROM attendance WHERE clock_in>=DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY DATE(clock_in) ORDER BY DATE(clock_in) ASC");
while($row=$res->fetch_assoc()){$attendance_chart[$row['date']]=$row['total'];}
?>

<!DOCTYPE html>
<html>
<head>
<title>Manager Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
tailwind.config={theme:{extend:{colors:{'911-yellow':'#FFD700','911-black':'#121212','911-gray':'#E5E5E5'}}}}
</script>
</head>
<body class="bg-911-black text-911-gray min-h-screen">

<div class="max-w-7xl mx-auto p-6">
    <div class="flex items-center justify-center gap-6 p-4 bg-911-black border-b border-911-yellow">
    <!-- Logo -->
    <img src="icon.jpg" alt="911 Logo" class="w-24 h-16">
    
    <!-- Welcome Message -->
    <h1 class="text-4xl md:text-5xl font-bold text-911-yellow">
        Welcome, <?= $manager_name ?>
    </h1>
</div>

    <!-- Inventory Management -->
    <div class="grid md:grid-cols-2 gap-6 mb-8">

        <!-- Add Inventory -->
        <div class="bg-911-black border border-911-yellow rounded-lg p-4 shadow">
            <h2 class="text-911-yellow font-bold text-xl mb-2">Add Inventory</h2>
            <form method="POST" class="flex flex-col gap-2">
                <input type="text" name="item_name" placeholder="Item Name" required class="px-2 py-1 rounded text-black">
                <input type="number" name="quantity" placeholder="Quantity" required class="px-2 py-1 rounded text-black">
                <button type="submit" name="add_inventory" class="bg-911-yellow text-911-black px-4 py-2 rounded">Add</button>
            </form>
        </div>

        <!-- Inventory List -->
        <div class="bg-911-black border border-911-yellow rounded-lg p-4 shadow overflow-x-auto">
            <h2 class="text-911-yellow font-bold text-xl mb-2">Inventory List</h2>
            <table class="min-w-full text-911-gray">
                <thead>
                    <tr class="text-911-yellow">
                        <th class="px-2 py-1">ID</th>
                        <th class="px-2 py-1">Item</th>
                        <th class="px-2 py-1">Qty</th>
                        <th class="px-2 py-1">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($inventory as $i): ?>
                    <tr>
                        <td class="px-2 py-1"><?= $i['id'] ?></td>
                        <td class="px-2 py-1"><?= $i['item_name'] ?></td>
                        <td class="px-2 py-1"><?= $i['quantity'] ?></td>
                        <td class="px-2 py-1 flex gap-2 flex-wrap">
                            <!-- Edit Form -->
                            <form method="POST" class="flex gap-1">
                                <input type="hidden" name="id" value="<?= $i['id'] ?>">
                                <input type="number" name="quantity" value="<?= $i['quantity'] ?>" class="px-1 py-1 text-black w-16" required>
                                <button type="submit" name="edit_inventory" class="bg-911-yellow text-911-black px-2 rounded">Edit</button>
                            </form>
                            <!-- Delete Form -->
                            <form method="POST">
                                <input type="hidden" name="id" value="<?= $i['id'] ?>">
                                <button type="submit" name="delete_inventory" class="bg-red-600 text-911-black px-2 rounded">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Employees Clocked In -->
    <div class="bg-911-black border border-911-yellow rounded-lg p-4 shadow mb-8 overflow-x-auto">
        <h2 class="text-911-yellow font-bold text-xl mb-2">Employees Clocked In</h2>
        <table class="min-w-full text-911-gray">
            <thead>
                <tr class="text-911-yellow">
                    <th class="px-2 py-1">Employee</th>
                    <th class="px-2 py-1">Clock In</th>
                    <th class="px-2 py-1">Confirm</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($employees_clocked_in as $e): ?>
                <tr>
                    <td class="px-2 py-1"><?= $e['username'] ?></td>
                    <td class="px-2 py-1"><?= $e['clock_in'] ?></td>
                    <td class="px-2 py-1">
                        <?php if(!$e['confirmed']): ?>
                        <form method="POST">
                            <input type="hidden" name="attendance_id" value="<?= $e['attendance_id'] ?>">
                            <button type="submit" name="confirm_shift" class="bg-911-yellow text-911-black px-2 rounded">Confirm</button>
                        </form>
                        <?php else: ?>
                        <span class="text-green-500 font-bold">Confirmed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Analytics Charts -->
    <div class="grid md:grid-cols-2 gap-6 mb-8">
        <div class="bg-911-black border border-911-yellow rounded-lg p-4 shadow">
            <h2 class="text-911-yellow font-bold text-xl mb-2 text-center">Inventory Levels</h2>
            <canvas id="inventoryChart" height="150"></canvas>
        </div>
        <div class="bg-911-black border border-911-yellow rounded-lg p-4 shadow">
            <h2 class="text-911-yellow font-bold text-xl mb-2 text-center">Attendance Last 7 Days</h2>
            <canvas id="attendanceChart" height="150"></canvas>
        </div>
    </div>

    <!-- Notifications -->
    <div class="bg-911-black border border-911-yellow rounded-lg p-4 shadow">
        <h2 class="text-911-yellow font-bold text-xl mb-2">Notifications</h2>
        <ul>
            <?php foreach($notifications as $n): ?>
            <li class="mb-1 text-911-gray">
                <?= $n['changed_by_name'] ?> <?= $n['action'] ?> <?= $n['item_name'] ?? 'Item' ?> at <?= $n['changed_at'] ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="text-center mt-6">
        <a href="logout.php" class="inline-block bg-gray-800 text-911-yellow px-6 py-3 rounded-lg font-bold hover:brightness-110 transition">Logout</a>
    </div>
</div>

<script>
const inventoryData=<?= json_encode($inventory_chart) ?>;
const attendanceData=<?= json_encode($attendance_chart) ?>;

new Chart(document.getElementById('inventoryChart'),{
    type:'bar',
    data:{ labels:Object.keys(inventoryData), datasets:[{label:'Quantity', data:Object.values(inventoryData), backgroundColor:'#FFD700'}] },
    options:{ responsive:true, plugins:{ legend:{ display:false } }, scales:{ x:{ ticks:{ color:'#FFD700' }, grid:{ color:'#444' } }, y:{ ticks:{ color:'#FFD700' }, grid:{ color:'#444' } } } }
});

new Chart(document.getElementById('attendanceChart'),{
    type:'line',
    data:{ labels:Object.keys(attendanceData), datasets:[{label:'Clock-ins', data:Object.values(attendanceData), borderColor:'#FFD700', backgroundColor:'rgba(255,215,0,0.2)', fill:true, tension:0.4}] },
    options:{ responsive:true, plugins:{ legend:{ labels:{ color:'#FFD700' } } }, scales:{ x:{ ticks:{ color:'#FFD700' }, grid:{ color:'#444' } }, y:{ ticks:{ color:'#FFD700' }, grid:{ color:'#444' } } } }
});
</script>

</body>
</html>
