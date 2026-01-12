<?php
include 'config.php'; 

// Start session only if not already started
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

$login_error = '';
if(isset($_POST['login'])){
    $username = $conn->real_escape_string($_POST['username']);
    $pin = $_POST['pin']; // no need to escape, we won't put it directly in SQL

    // Fetch user by username and status
    $res = $conn->query("SELECT * FROM users WHERE username='$username' AND status='active' LIMIT 1");

    if($res && $res->num_rows === 1){
        $user = $res->fetch_assoc();

        // Verify PIN
        if(password_verify($pin, $user['pin'])){
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];

            // Redirect based on role
            if($user['role']=='admin') header("Location: admin_dashboard.php");
            elseif($user['role']=='manager') header("Location: manager_dashboard.php");
            else header("Location: employee_dashboard.php");
            exit;
        } else {
            $login_error = "Invalid username or PIN";
        }
    } else {
        $login_error = "Invalid username or PIN";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>911 Fast Food System</title>
<link rel="manifest" href="manifest.json">
<link rel="apple-touch-icon" href="icon.jpg">
<meta name="theme-color" content="#d6b928ff">
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
<style>
body {
    background: url('path/to/your/fastfood-background.jpg') center/cover no-repeat;
}
</style>
</head>
<body class="min-h-screen flex flex-col">

<!-- Hero Section -->
<div class="flex-1 flex flex-col justify-center items-center text-center px-4 bg-black bg-opacity-70">
    <img src="icon.jpg" alt="911 Logo" class="h-32 mx-auto mb-6">

    <h1 class="text-5xl md:text-6xl font-bold text-911-yellow mb-4">911 Fast Food</h1>
    <p class="text-911-gray text-xl md:text-2xl mb-8">Delicious meals, fast delivery, happy customers!</p>

    <!-- Login Card -->
    <div class="bg-911-black bg-opacity-90 p-8 rounded-xl shadow-xl max-w-md w-full">
        <?php if($login_error): ?>
        <p class="text-red-500 mb-4"><?= htmlspecialchars($login_error) ?></p>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <input type="text" name="username" placeholder="Username / ID" required
                class="w-full px-4 py-2 rounded-md bg-gray-800 text-911-gray placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-911-yellow">
            <input type="password" name="pin" placeholder="PIN" required
                class="w-full px-4 py-2 rounded-md bg-gray-800 text-911-gray placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-911-yellow">
            <button type="submit" name="login"
                class="w-full py-2 bg-911-yellow text-911-black font-bold rounded-md hover:brightness-110 transition">
                Login
            </button>
        </form>

        <p class="text-911-gray mt-4 text-sm">
            Don't have an account? <strong>Contact Admin to register.</strong>
        </p>
    </div>
</div>

<footer class="text-center p-4 bg-911-black text-911-gray">
    &copy; <?= date('Y') ?> 911 Fast Food. All Rights Reserved.
</footer>
<script>
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('service-worker.js')
    .then(() => console.log('Service Worker Registered'))
    .catch(err => console.log('Service Worker failed:', err));
}
</script>
</body>
</html>
