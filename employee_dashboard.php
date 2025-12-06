<?php
include 'config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Only allow employees
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employee'){
    header("Location: login.php");
    exit;
}

$emp_id = $_SESSION['user_id'];
$employee = $conn->query("SELECT username, pay FROM users WHERE id=$emp_id")->fetch_assoc();

// Handle clock-in/out
if(isset($_POST['clock_in'])){
    $conn->query("INSERT INTO attendance (user_id, clock_in) VALUES ($emp_id, NOW())");
    header("Location: employee_dashboard.php");
    exit;
}
if(isset($_POST['clock_out'])){
    // Update latest attendance row with clock_out
    $conn->query("UPDATE attendance SET clock_out=NOW() WHERE user_id=$emp_id AND clock_out IS NULL ORDER BY id DESC LIMIT 1");
    header("Location: employee_dashboard.php");
    exit;
}

// Fetch attendance for this employee
$attendance = $conn->query("SELECT * FROM attendance WHERE user_id=$emp_id ORDER BY id DESC");
?>

<!DOCTYPE html>
<html>
<head>
<title>Employee Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
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

<div class="max-w-5xl mx-auto p-6">

    <!-- Welcome & Pay -->
    <div class="flex items-center justify-center gap-6 p-4 bg-911-black border-b border-911-yellow">
    <!-- Logo -->
    <img src="icon.jpg" alt="911 Logo" class="w-24 h-16">
    
    <!-- Welcome Message -->
    <h1 class="text-4xl md:text-5xl font-bold text-911-yellow">
        Welcome, <?= ($employee['username']) ?>
    </h1>
        <p class="text-911-gray text-xl">Your Pay: <span class="font-bold text-911-yellow"><?= number_format($employee['pay'],2) ?></span></p>
    </div>

    <!-- Clock-in/out buttons -->
    <div class="flex justify-center gap-6 mb-8 flex-wrap">
        <form method="POST">
            <button type="submit" name="clock_in" class="bg-911-yellow text-911-black px-6 py-3 rounded-lg font-bold hover:brightness-110 transition">Clock In</button>
        </form>
        <form method="POST">
            <button type="submit" name="clock_out" class="bg-911-yellow text-911-black px-6 py-3 rounded-lg font-bold hover:brightness-110 transition">Clock Out</button>
        </form>
    </div>

    <!-- Attendance table -->
    <div class="overflow-x-auto">
        <table class="min-w-full bg-911-black border border-911-yellow rounded-lg">
            <thead>
                <tr class="text-911-yellow">
                    <th class="px-4 py-2 border-b border-911-yellow">Clock In</th>
                    <th class="px-4 py-2 border-b border-911-yellow">Clock Out</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($attendance as $a): ?>
                <tr class="text-911-gray">
                    <td class="px-4 py-2 border-b border-911-yellow"><?= $a['clock_in'] ?></td>
                    <td class="px-4 py-2 border-b border-911-yellow"><?= $a['clock_out'] ?? '-' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="text-center mt-6">
        <a href="logout.php" class="inline-block bg-gray-800 text-911-yellow px-6 py-3 rounded-lg font-bold hover:brightness-110 transition">Logout</a>
    </div>
</div>

</body>
</html>
