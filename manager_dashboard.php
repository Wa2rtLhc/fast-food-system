<?php
include 'config.php';
if(session_status()===PHP_SESSION_NONE){ session_start(); }

if(!isset($_SESSION['user_id']) || $_SESSION['role']!='manager'){
    header("Location: login.php"); exit;
}

$manager_id = $_SESSION['user_id'];
$manager_name = $conn->query("SELECT username FROM users WHERE id=$manager_id")->fetch_assoc()['username'];

// =====================
// Tabs
// =====================
$active_tab = $_GET['tab'] ?? 'confirmations';

// =====================
// Search Inputs
// =====================
$inventory_search = $conn->real_escape_string($_GET['inventory_search'] ?? '');
$employee_search = $conn->real_escape_string($_GET['employee_search'] ?? '');

// =====================
// Inventory Operations
// =====================
if(isset($_POST['add_inventory'])){
    $item_name = $conn->real_escape_string($_POST['item_name']);
    $quantity = intval($_POST['quantity']);
    $branch_id_post = intval($_POST['branch_id']);
    
    $conn->query("INSERT INTO inventory (item_name, quantity, branch_id, added_by) VALUES ('$item_name', $quantity, $branch_id_post, $manager_id)");
    $inventory_id = $conn->insert_id;
    if($inventory_id > 0){
        $conn->query("INSERT INTO inventory_log (inventory_id, action, quantity_before, quantity_after, changed_by) VALUES ($inventory_id,'added',0,$quantity,$manager_id)");
    }
    header("Location: manager_dashboard.php?tab=inventory"); exit;
}

if(isset($_POST['edit_inventory']) && isset($_POST['id'])){
    $inv_id = intval($_POST['id']);
    $new_qty = intval($_POST['quantity']);
    $inv_res = $conn->query("SELECT quantity FROM inventory WHERE id=$inv_id");
    if($inv_res->num_rows > 0){
        $old_qty = $inv_res->fetch_assoc()['quantity'];
        $conn->query("UPDATE inventory SET quantity=$new_qty WHERE id=$inv_id");
        $conn->query("INSERT INTO inventory_log (inventory_id, action, quantity_before, quantity_after, changed_by) VALUES ($inv_id,'updated',$old_qty,$new_qty,$manager_id)");
    }
    header("Location: manager_dashboard.php?tab=inventory"); exit;
}

if(isset($_POST['delete_inventory']) && isset($_POST['id'])){
    $inv_id = intval($_POST['id']);
    $inv_res = $conn->query("SELECT quantity FROM inventory WHERE id=$inv_id");
    if($inv_res->num_rows > 0){
        $old_qty = $inv_res->fetch_assoc()['quantity'];
        // Insert log BEFORE deletion
        $conn->query("INSERT INTO inventory_log (inventory_id, action, quantity_before, quantity_after, changed_by) VALUES ($inv_id,'deleted',$old_qty,0,$manager_id)");
        $conn->query("DELETE FROM inventory WHERE id=$inv_id");
    }
    header("Location: manager_dashboard.php?tab=inventory"); exit;
}

if(isset($_POST['confirm_shift']) && isset($_POST['attendance_id'])){
    $att_id = intval($_POST['attendance_id']);
    $conn->query("UPDATE attendance SET confirmed=1 WHERE id=$att_id");
    header("Location: manager_dashboard.php?tab=confirmations"); exit;
}

// =====================
// Fetch Data
// =====================

// Branches
$branches = $conn->query("SELECT * FROM branches");

// Inventory per search
$inventory_query = "SELECT i.*, u.username as added_by_name, b.name as branch_name 
                    FROM inventory i 
                    JOIN users u ON i.added_by=u.id 
                    JOIN branches b ON i.branch_id=b.id 
                    WHERE b.name LIKE '%$inventory_search%' 
                    ORDER BY i.id DESC";
$inventory = $conn->query($inventory_query);

// Inventory log notifications (last 5)
$notifications = $conn->query("SELECT il.*, u.username as changed_by_name, i.item_name 
                               FROM inventory_log il 
                               LEFT JOIN inventory i ON il.inventory_id=i.id 
                               JOIN users u ON il.changed_by=u.id 
                               ORDER BY il.changed_at DESC LIMIT 5");

// Employees clocked in (all confirmed and unconfirmed) with search, include phone for manager
$employees_clocked_in = $conn->query("
    SELECT u.id, u.username, u.phone, a.id as attendance_id, a.clock_in, a.clock_out, a.confirmed, b.name as branch_name
    FROM attendance a
    JOIN users u ON a.user_id=u.id
    LEFT JOIN branches b ON u.branch_id=b.id
    WHERE (a.clock_out IS NULL OR a.confirmed=1)
    AND u.username LIKE '%$employee_search%'
    ORDER BY a.clock_in DESC
");

// Analytics data
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
<title>Manager Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            Welcome, <?= $manager_name ?>
        </h1>
        <a href="logout.php" class="bg-gray-800 text-911-yellow px-4 py-2 rounded font-bold">Logout</a>
    </div>

    <!-- Tabs -->
    <div class="flex flex-wrap gap-2 mb-4">
        <a href="?tab=confirmations" class="px-4 py-2 rounded font-semibold <?= $active_tab=='confirmations'?'bg-911-yellow text-911-black':'bg-911-black text-911-yellow border border-911-yellow' ?>">Employee Confirmations</a>
        <a href="?tab=inventory" class="px-4 py-2 rounded font-semibold <?= $active_tab=='inventory'?'bg-911-yellow text-911-black':'bg-911-black text-911-yellow border border-911-yellow' ?>">Inventory Management</a>
    </div>

    <!-- TAB CONTENT -->
    <?php if($active_tab=='confirmations'): ?>
        <!-- Employee Search -->
        <form method="GET" class="mb-2 flex gap-2 flex-wrap">
            <input type="hidden" name="tab" value="confirmations">
            <input type="text" name="employee_search" placeholder="Search by employee name" 
                   value="<?= htmlspecialchars($_GET['employee_search'] ?? '') ?>"
                   class="px-2 py-1 rounded text-black flex-1">
            <button type="submit" class="bg-911-yellow text-911-black px-4 py-1 rounded">Search</button>
        </form>

        <div class="overflow-x-auto bg-911-black border border-911-yellow rounded p-4 shadow">
            <h2 class="text-911-yellow font-bold text-xl mb-2">Employees Clocked In</h2>
            <table class="min-w-full text-911-gray">
                <thead>
                    <tr class="text-911-yellow">
                        <th class="px-2 py-1">Employee</th>
                        <th class="px-2 py-1">Branch</th>
                        <th class="px-2 py-1">Phone</th>
                        <th class="px-2 py-1">Clock In</th>
                        <th class="px-2 py-1">Clock Out</th>
                        <th class="px-2 py-1">Status</th>
                        <th class="px-2 py-1">Confirm</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($employees_clocked_in as $e): ?>
                    <tr>
                        <td class="px-2 py-1"><?= $e['username'] ?></td>
                        <td class="px-2 py-1"><?= $e['branch_name'] ?? '-' ?></td>
                        <td class="px-2 py-1"><?= $e['phone'] ?? '-' ?></td>
                        <td class="px-2 py-1"><?= $e['clock_in'] ?></td>
                        <td class="px-2 py-1"><?= $e['clock_out'] ?? '-' ?></td>
                        <td class="px-2 py-1"><?= $e['confirmed'] ? '<span class="text-green-500 font-bold">Confirmed</span>' : '<span class="text-yellow-400 font-bold">Pending</span>' ?></td>
                        <td class="px-2 py-1">
                            <?php if(!$e['confirmed']): ?>
                            <form method="POST">
                                <input type="hidden" name="attendance_id" value="<?= $e['attendance_id'] ?>">
                                <button type="submit" name="confirm_shift" class="bg-911-yellow text-911-black px-2 py-1 rounded">Confirm</button>
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

    <?php elseif($active_tab=='inventory'): ?>
        <!-- Inventory Search -->
        <form method="GET" class="mb-2 flex gap-2 flex-wrap">
            <input type="hidden" name="tab" value="inventory">
            <input type="text" name="inventory_search" placeholder="Search by branch name" 
                   value="<?= htmlspecialchars($_GET['inventory_search'] ?? '') ?>"
                   class="px-2 py-1 rounded text-black flex-1">
            <button type="submit" class="bg-911-yellow text-911-black px-4 py-1 rounded">Search</button>
        </form>

        <!-- Add Inventory -->
        <div class="bg-911-black border border-911-yellow rounded-lg p-4 mb-4 shadow">
            <h2 class="text-911-yellow font-bold text-xl mb-2">Add Inventory</h2>
            <form method="POST" class="flex flex-col sm:flex-row gap-2 flex-wrap">
                <input type="text" name="item_name" placeholder="Item Name" required class="px-2 py-1 rounded text-black flex-1">
                <input type="number" name="quantity" placeholder="Quantity" required class="px-2 py-1 rounded text-black w-32">
                <select name="branch_id" required class="px-2 py-1 rounded text-black w-40">
                    <?php 
                    $branches_list = $conn->query("SELECT * FROM branches");
                    while($b = $branches_list->fetch_assoc()){
                        echo "<option value='{$b['id']}'>{$b['name']}</option>";
                    }
                    ?>
                </select>
                <button type="submit" name="add_inventory" class="bg-911-yellow text-911-black px-4 py-2 rounded">Add</button>
            </form>
        </div>

        <!-- Inventory Table -->
        <div class="overflow-x-auto bg-911-black border border-911-yellow rounded p-4 shadow mb-4">
            <h2 class="text-911-yellow font-bold text-xl mb-2">Inventory List</h2>
            <table class="min-w-full text-911-gray">
                <thead>
                    <tr class="text-911-yellow">
                        <th class="px-2 py-1">ID</th>
                        <th class="px-2 py-1">Item</th>
                        <th class="px-2 py-1">Qty</th>
                        <th class="px-2 py-1">Branch</th>
                        <th class="px-2 py-1">Added By</th>
                        <th class="px-2 py-1">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($i=$inventory->fetch_assoc()): ?>
                    <tr>
                        <td class="px-2 py-1"><?= $i['id'] ?></td>
                        <td class="px-2 py-1"><?= $i['item_name'] ?></td>
                        <td class="px-2 py-1"><?= $i['quantity'] ?></td>
                        <td class="px-2 py-1"><?= $i['branch_name'] ?></td>
                        <td class="px-2 py-1"><?= $i['added_by_name'] ?></td>
                        <td class="px-2 py-1 flex gap-2 flex-wrap">
                            <form method="POST" class="flex gap-1">
                                <input type="hidden" name="id" value="<?= $i['id'] ?>">
                                <input type="number" name="quantity" value="<?= $i['quantity'] ?>" class="px-1 py-1 text-black w-16" required>
                                <button type="submit" name="edit_inventory" class="bg-911-yellow text-911-black px-2 rounded">Edit</button>
                            </form>
                            <form method="POST">
                                <input type="hidden" name="id" value="<?= $i['id'] ?>">
                                <button type="submit" name="delete_inventory" class="bg-red-600 text-911-black px-2 rounded">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
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

        <!-- Notifications -->
        <div class="bg-911-black border border-911-yellow rounded-lg p-4 shadow mb-4">
            <h2 class="text-911-yellow font-bold text-xl mb-2">Notifications</h2>
            <ul>
                <?php foreach($notifications as $n): ?>
                <li class="mb-1 text-911-gray">
                    <?= $n['changed_by_name'] ?> <?= $n['action'] ?> <?= $n['item_name'] ?? 'Item' ?> at <?= $n['changed_at'] ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

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
    data:{ labels:Object.keys(attendanceData), datasets:[{label:'Total Clock-ins', data:Object.values(attendanceData), backgroundColor:'#FFD700', borderColor:'#FFD700', fill:false}] },
    options:{ responsive:true, plugins:{ legend:{ display:false } }, scales:{ x:{ ticks:{ color:'#FFD700' }, grid:{ color:'#444' } }, y:{ ticks:{ color:'#FFD700' }, grid:{ color:'#444' } } } }
});
</script>

</body>
</html>