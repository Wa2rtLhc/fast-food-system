<?php
include 'config.php';
if(session_status()===PHP_SESSION_NONE){ session_start(); }

if(!isset($_SESSION['user_id']) || $_SESSION['role']!='employee'){
    header("Location: login.php"); exit;
}

$user_id = $_SESSION['user_id'];

/* Employee info */
$user = $conn->query("SELECT u.username, u.daily_rate, b.name AS branch FROM users u JOIN branches b ON u.branch_id=b.id WHERE u.id=$user_id")->fetch_assoc();

/* Clock In */
if(isset($_POST['clock_in'])){
    $rate = $user['daily_rate'];
    $conn->query("INSERT INTO attendance (user_id, clock_in, daily_pay) VALUES ($user_id, NOW(), $rate)");
    header("Location: employee_dashboard.php"); exit;
}

/* Clock Out */
if(isset($_POST['clock_out'])){
    $open = $conn->query("SELECT id FROM attendance WHERE user_id=$user_id AND clock_out IS NULL ORDER BY id DESC LIMIT 1")->fetch_assoc();
    if($open){
        $conn->query("UPDATE attendance SET clock_out=NOW() WHERE id={$open['id']}");
    }
    header("Location: employee_dashboard.php"); exit;
}

/* Optional filters */
$conditions = "user_id=$user_id";
$pay_filter = '';

if(isset($_GET['month']) && $_GET['month'] !== ""){
    $m = intval($_GET['month']);
    $conditions .= " AND MONTH(clock_in)=$m";
    $pay_filter .= " AND MONTH(clock_in)=$m";
}
if(isset($_GET['year']) && $_GET['year'] !== ""){
    $y = intval($_GET['year']);
    $conditions .= " AND YEAR(clock_in)=$y";
    $pay_filter .= " AND YEAR(clock_in)=$y";
}

/* Attendance records */
$rows = $conn->query("SELECT * FROM attendance WHERE $conditions ORDER BY clock_in DESC");

/* Confirmed Pay */
$pay_summary = $conn->query("SELECT IFNULL(SUM(daily_pay),0) AS total FROM attendance WHERE user_id=$user_id AND confirmed=1 $pay_filter")->fetch_assoc()['total'];
?><!DOCTYPE html><html lang="en">
<head>
<meta charset="UTF-8">
<title>Employee Dashboard</title>
<link rel="manifest" href="manifest.json">
<link rel="apple-touch-icon" href="icon.jpg">
<meta name="theme-color" content="#d6b928ff">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-black text-gray-100">
<div class="w-full min-h-screen px-2 sm:px-4 md:px-6 py-4"><!-- Header -->
<div class="bg-gray-900 border border-yellow-500 rounded p-4 mb-4 flex justify-between items-center">
    <div>
        <h1 class="text-xl font-bold text-yellow-400">Employee Dashboard</h1>
        <p class="text-sm text-gray-400"><?= htmlspecialchars($user['username']) ?> • <?= htmlspecialchars($user['branch']) ?></p>
</div>
<button id="installBtn" class="hidden fixed top-4 right-4 bg-red-600 text-white px-4 py-2 rounded shadow-lg">
    Install App
</button>
    <a href="logout.php" class="text-red-500 text-sm">Logout</a>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
    <div class="bg-gray-900 border border-yellow-500 rounded p-4">
        <p class="text-sm text-gray-400">Confirmed Pay</p>
        <p class="text-2xl font-bold text-yellow-400">KSH <?= number_format($pay_summary,2) ?></p>
        <p class="text-xs text-gray-500 mt-1">Confirmed days only</p>
    </div>

    <div class="bg-gray-900 border border-yellow-500 rounded p-4">
        <p class="text-sm text-gray-400">Daily Rate</p>
        <p class="text-xl font-semibold text-gray-100">KSH <?= number_format($user['daily_rate'],2) ?></p>
    </div>

    <div class="bg-gray-900 border border-yellow-500 rounded p-4">
        <p class="text-sm text-gray-400">Today</p>
        <p class="text-lg font-semibold">
            <?php
            $open = $conn->query("SELECT * FROM attendance WHERE user_id=$user_id AND clock_out IS NULL ORDER BY id DESC LIMIT 1")->fetch_assoc();
            echo $open ? 'Shift Open' : 'No Active Shift';
            ?>
        </p>
    </div>
</div>

<!-- Clock In/Out -->
<div class="bg-gray-900 border border-yellow-500 rounded p-4 mb-4">
    <form method="POST" class="flex flex-col sm:flex-row gap-2">
        <button name="clock_in" class="bg-yellow-500 text-black px-4 py-2 rounded font-semibold">Clock In</button>
        <button name="clock_out" class="bg-red-600 text-white px-4 py-2 rounded font-semibold">Clock Out</button>
    </form>
</div>

<!-- Filters -->
<form method="GET" class="mb-4 flex gap-2">
    <select name="month" class="bg-gray-800 text-white p-2 rounded">
        <option value="">All Months</option>
        <?php for($m=1; $m<=12; $m++): ?>
            <option value="<?= $m ?>" <?= (isset($_GET['month']) && $_GET['month']==$m?'selected':'') ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
        <?php endfor; ?>
    </select>

    <select name="year" class="bg-gray-800 text-white p-2 rounded">
        <option value="">All Years</option>
        <?php for($y=date('Y'); $y>=2020; $y--): ?>
            <option value="<?= $y ?>" <?= (isset($_GET['year']) && $_GET['year']==$y?'selected':'') ?>><?= $y ?></option>
        <?php endfor; ?>
    </select>

    <button class="bg-yellow-500 text-black px-4 rounded">Filter</button>
</form>

<!-- Attendance Table -->
<div class="bg-gray-900 border border-yellow-500 rounded p-4">
    <h2 class="font-semibold text-yellow-400 mb-2">Attendance History</h2>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-black text-yellow-400">
                <tr>
                    <th class="px-3 py-2 text-left">Date</th>
                    <th class="px-3 py-2 text-left">Clock In</th>
                    <th class="px-3 py-2 text-left">Clock Out</th>
                    <th class="px-3 py-2 text-left">Pay</th>
                    <th class="px-3 py-2 text-left">Status</th>
                </tr>
            </thead>
            <tbody>
            <?php while($r = $rows->fetch_assoc()): ?>
                <tr class="border-b border-gray-700">
                    <td class="px-3 py-2"><?= date('d M Y',strtotime($r['clock_in'])) ?></td>
                    <td class="px-3 py-2"><?= date('H:i',strtotime($r['clock_in'])) ?></td>
                    <td class="px-3 py-2"><?= $r['clock_out'] ? date('H:i',strtotime($r['clock_out'])) : '-' ?></td>
                    <td class="px-3 py-2"><?= number_format($r['daily_pay'],2) ?></td>
                    <td class="px-3 py-2"><?= $r['confirmed'] ? '<span class="text-green-500">Confirmed</span>' : '<span class="text-yellow-400">Pending</span>' ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

</div>
<script>
let deferredPrompt;
const installBtn = document.getElementById('installBtn');
installBtn.style.display = 'none';

window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  deferredPrompt = e;
  installBtn.style.display = 'inline-block';
});

installBtn.addEventListener('click', () => {
  installBtn.style.display = 'none';
  deferredPrompt.prompt();
  deferredPrompt.userChoice.then(choice => {
    deferredPrompt = null;
  });
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