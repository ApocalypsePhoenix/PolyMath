<?php
require_once 'db.php';

$message = "";
$messageType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier']); // Email or Username
    $password = $_POST['password'];

    if (!empty($identifier) && !empty($password)) {
        try {
            // Updated query to select all necessary fields
            $stmt = $pdo->prepare("SELECT user_id, username, password_hash, role FROM users WHERE email = ? OR username = ? LIMIT 1");
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                if ($user['role'] === 'admin') {
                    header("Location: " . BASE_URL . "admin_dashboard.php");
                } else {
                    header("Location: " . BASE_URL . "student_dashboard.php");
                }
                exit();
            } else {
                $message = "Invalid username/email or password.";
                $messageType = "error";
            }
        } catch (PDOException $e) {
            // Providing specific feedback for debugging
            $message = "Database Error: " . $e->getMessage();
            $messageType = "error";
        }
    } else {
        $message = "Please fill in all fields.";
        $messageType = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PolyMath - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .poly-gradient { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-6">
    <div class="max-w-md w-full bg-white rounded-3xl shadow-2xl overflow-hidden">
        <div class="poly-gradient p-8 text-white text-center">
            <h1 class="text-3xl font-bold uppercase tracking-wider">PolyMath</h1>
            <p class="mt-2 opacity-90">Secure Login</p>
        </div>
        <div class="p-8">
            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-xl text-sm bg-red-100 text-red-700 border border-red-200">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            <form action="login.php" method="POST" class="space-y-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Username or Email</label>
                    <input type="text" name="identifier" required placeholder="Enter your credentials"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Password</label>
                    <input type="password" name="password" required 
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition-all">
                </div>
                <button type="submit" class="w-full poly-gradient text-white font-bold py-4 rounded-xl shadow-lg hover:opacity-90 transform hover:-translate-y-0.5 transition-all">
                    LOG IN
                </button>
            </form>
            <div class="mt-8 text-center text-gray-500 text-sm">
                Don't have an account? <a href="register.php" class="text-indigo-600 font-semibold hover:underline">Create one</a>
            </div>
        </div>
    </div>
</body>
</html>