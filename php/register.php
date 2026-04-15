<?php
require_once 'db.php';

$message = "";
$messageType = "";

// Fetch classes for the dropdown
try {
    $stmt = $pdo->query("SELECT * FROM classes");
    $classes = $stmt->fetchAll();
} catch (PDOException $e) {
    $classes = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $student_name = trim($_POST['student_name'] ?? '');
    $matric_number = trim($_POST['matric_number'] ?? '');
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $class_id = $_POST['class_id'];

    if (!empty($username) && !empty($student_name) && !empty($matric_number) && !empty($email) && !empty($password) && !empty($class_id)) {
        
        // Strict Alphanumeric Check for Matric Number
        if (!preg_match('/^[a-zA-Z0-9]+$/', $matric_number)) {
            $message = "Matric Number must be alphanumeric only (no spaces or symbols).";
            $messageType = "error";
        } else {
            // Hash the password for security
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, student_name, matric_number, email, password_hash, class_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $student_name, strtoupper($matric_number), $email, $password_hash, $class_id]);
                
                $message = "Registration successful! You can now <a href='login.php' class='underline'>login</a>.";
                $messageType = "success";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $message = "Username or Email already exists.";
                } else {
                    $message = "An error occurred. Please try again.";
                }
                $messageType = "error";
            }
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
    <title>PolyMath - Register</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .poly-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-6">

    <div class="max-w-md w-full bg-white rounded-3xl shadow-2xl overflow-hidden">
        <div class="poly-gradient p-8 text-white text-center">
            <h1 class="text-3xl font-bold uppercase tracking-wider">PolyMath</h1>
            <p class="mt-2 opacity-90">Join a class and start your math journey</p>
        </div>

        <div class="p-8">
            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-xl text-sm <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST" class="space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Username</label>
                    <input type="text" name="username" required 
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition-all">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Full Student Name</label>
                    <input type="text" name="student_name" required placeholder="e.g. John Doe"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition-all">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Matric Number</label>
                    <input type="text" name="matric_number" required placeholder="e.g. 21DDT21F1010" pattern="[A-Za-z0-9]+" title="Alphanumeric characters only"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition-all uppercase">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Email Address</label>
                    <input type="email" name="email" required 
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition-all">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Select Class</label>
                    <select name="class_id" required 
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition-all bg-white">
                        <option value="">-- Choose your class --</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Password</label>
                    <input type="password" name="password" required 
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition-all">
                </div>

                <button type="submit" 
                    class="w-full poly-gradient text-white font-bold py-4 rounded-xl shadow-lg hover:opacity-90 transform hover:-translate-y-0.5 transition-all">
                    CREATE ACCOUNT
                </button>
            </form>

            <div class="mt-8 text-center text-gray-500 text-sm">
                Already have an account? 
                <a href="login.php" class="text-indigo-600 font-semibold hover:underline">Log In</a>
            </div>
        </div>
    </div>

</body>
</html>