<?php
include 'config.php';
if(session_status()===PHP_SESSION_NONE){ session_start(); }

if(!isset($_SESSION['user_id']) || $_SESSION['role']!='employee'){
    header("Location: login.php"); exit;
}

$user_id = $_SESSION['user_id'];

/* Employee info */
$user = $conn->query("
    SELECT u.username, u.daily_rate, b.name AS branch
    FROM users u
    JOIN branches b ON u.branch_id=b.id
    WHERE u.id=$user_id
")->fetch_assoc();

/* Today's attendance */
$today = $conn->query("
    SELECT * FROM attendance
    WHERE user_id=$user_id AND DATE(clock_in)=CURDATE()
")->fetch_assoc();

/* Clock in */
if(isset($_POST['clock_in']) && !$today){
    $rate = $user['daily_rate'];
    $conn->query("
        INSERT INTO attendance (user_id, clock_in, daily_pay)
        VALUES ($user_id, NOW(), $rate)
    ");
    header("Location: employee_dashboard.php"); exit;
}

/* Clock out */
if(isset($_POST['clock_out']) && $today && !$today['clock_out']){
    $conn->query("
        UPDATE attendance SET clock_out=NOW() WHERE id={$today['id']}
    ");
    header("Location: employee_dashboard.php"); exit;
}

/* ✅ Confirmed Monthly Pay (SYNCED WITH ADMIN & MANAGER) */
$monthly_pay = $conn->query("
    SELECT IFNULL(SUM(daily_pay),0) AS total
    FROM attendance
    WHERE user_id=$user_id
    AND confirmed=1
    AND MONTH(clock_in)=MONTH(CURDATE())
    AND YEAR(clock_in)=YEAR(CURDATE())
")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Employee Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-black text-gray-100">

<div class="w-full min-h-screen px-2 sm:px-4 md:px-6 py-4">

    <!-- Header -->
    <div class="bg-gray-900 border border-yellow-500 rounded p-4 mb-4 flex justify-between items-center">
        <div>
            <h1 class="text-xl font-bold text-yellow-400">Employee Dashboard</h1>
            <p class="text-sm text-gray-400">
                <?= htmlspecialchars($user['username']) ?> • <?= htmlspecialchars($user['branch']) ?>
            </p>
        </div>
        <a href="logout.php" class="text-red-500 text-sm">Logout</a>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">

        <div class="bg-gray-900 border border-yellow-500 rounded p-4">
            <p class="text-sm text-gray-400">Confirmed Monthly Pay</p>
            <p class="text-2xl font-bold text-yellow-400">
                KSH <?= number_format($monthly_pay,2) ?>
            </p>
            <p class="text-xs text-gray-500 mt-1">Confirmed days only</p>
        </div>

        <div class="bg-gray-900 border border-yellow-500 rounded p-4">
            <p class="text-sm text-gray-400">Daily Rate</p>
            <p class="text-xl font-semibold text-gray-100">
                KSH <?= number_format($user['daily_rate'],2) ?>
            </p>
        </div>

        <div class="bg-gray-900 border border-yellow-500 rounded p-4">
            <p class="text-sm text-gray-400">Today</p>
            <p class="text-lg font-semibold">
                <?= $today ? 'Clocked In' : 'Not Clocked In' ?>
            </p>
        </div>
    </div>

    <!-- Clock Actions -->
    <div class="bg-gray-900 border border-yellow-500 rounded p-4 mb-4">
        <form method="POST" class="flex flex-col sm:flex-row gap-2">

            <?php if(!$today): ?>
                <button name="clock_in"
                        class="bg-yellow-500 text-black px-4 py-2 rounded font-semibold">
                    Clock In
                </button>

            <?php elseif($today && !$today['clock_out']): ?>
                <button name="clock_out"
                        class="bg-red-600 text-white px-4 py-2 rounded font-semibold">
                    Clock Out
                </button>

            <?php else: ?>
                <p class="text-green-500 font-semibold">
                    Attendance Completed ✅
                </p>
            <?php endif; ?>

        </form>
    </div>

    <!-- Attendance History -->
    <div class="bg-gray-900 border border-yellow-500 rounded p-4">
        <h2 class="font-semibold text-yellow-400 mb-2">
            Attendance History (This Month)
        </h2>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-black text-yellow-400">
                    <tr>
                        <th class="px-3 py-2 text-left">Date</th>
                        <th class="px-3 py-2 text-left">In</th>
                        <th class="px-3 py-2 text-left">Out</th>
                        <th class="px-3 py-2 text-left">Pay</th>
                        <th class="px-3 py-2 text-left">Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $rows = $conn->query("
                    SELECT * FROM attendance
                    WHERE user_id=$user_id
                    AND MONTH(clock_in)=MONTH(CURDATE())
                    AND YEAR(clock_in)=YEAR(CURDATE())
                ");
                while($r=$rows->fetch_assoc()):
                ?>
                    <tr class="border-b border-gray-700">
                        <td class="px-3 py-2"><?= date('d M',strtotime($r['clock_in'])) ?></td>
                        <td class="px-3 py-2"><?= date('H:i',strtotime($r['clock_in'])) ?></td>
                        <td class="px-3 py-2">
                            <?= $r['clock_out'] ? date('H:i',strtotime($r['clock_out'])) : '-' ?>
                        </td>
                        <td class="px-3 py-2">
                            <?= number_format($r['daily_pay'],2) ?>
                        </td>
                        <td class="px-3 py-2">
                            <?= $r['confirmed']
                                ? "<span class='text-green-500'>Confirmed</span>"
                                : "<span class='text-yellow-400'>Pending</span>" ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
