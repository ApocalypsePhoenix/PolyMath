<?php
session_start();
require_once 'db.php';

// Access Control: Ensure only admins can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

$message = "";
$messageType = "";

// Handle Question Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    $level_id = $_POST['level_id'];
    $question_text = trim($_POST['question_text']);
    $option_a = trim($_POST['option_a']);
    $option_b = trim($_POST['option_b']);
    $option_c = trim($_POST['option_c']);
    $option_d = trim($_POST['option_d']);
    $correct_option = $_POST['correct_option'];
    $solution_text = trim($_POST['solution_text']);
    $admin_id = $_SESSION['user_id'];

    try {
        $sql = "INSERT INTO questions (level_id, question_text, option_a, option_b, option_c, option_d, correct_option, solution_text, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$level_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_option, $solution_text, $admin_id]);
        
        $message = "Question added successfully!";
        $messageType = "success";
    } catch (PDOException $e) {
        $message = "Error adding question: " . $e->getMessage();
        $messageType = "error";
    }
}

// Fetch Levels for the form
$levels = $pdo->query("SELECT * FROM levels ORDER BY difficulty_rank ASC")->fetchAll();

// Fetch Stats for Dashboard
$total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$total_questions = $pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn();
$class_stats = $pdo->query("SELECT c.class_name, COUNT(u.user_id) as count 
                            FROM classes c 
                            LEFT JOIN users u ON c.class_id = u.class_id 
                            GROUP BY c.class_id")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PolyMath Admin - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .poly-gradient {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 231, 235, 0.5);
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">

    <!-- Navigation -->
    <nav class="poly-gradient text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
            <a href="<?php echo BASE_URL; ?>" class="text-2xl font-bold tracking-tighter italic">PolyMath <span class="font-light text-indigo-200">Admin</span></a>
            <div class="flex items-center gap-6">
                <span class="text-sm opacity-80">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="logout.php" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg text-sm font-semibold transition-all">Logout</a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 py-10">
        
        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
            <div class="glass-card p-6 rounded-3xl shadow-sm">
                <p class="text-sm text-gray-500 font-semibold uppercase tracking-wider">Total Students</p>
                <h3 class="text-3xl font-bold text-indigo-600 mt-1"><?php echo $total_users; ?></h3>
            </div>
            <div class="glass-card p-6 rounded-3xl shadow-sm">
                <p class="text-sm text-gray-500 font-semibold uppercase tracking-wider">Total Questions</p>
                <h3 class="text-3xl font-bold text-purple-600 mt-1"><?php echo $total_questions; ?></h3>
            </div>
            <?php foreach ($class_stats as $stat): ?>
            <div class="glass-card p-6 rounded-3xl shadow-sm border-l-4 border-indigo-400">
                <p class="text-sm text-gray-500 font-semibold uppercase tracking-wider"><?php echo $stat['class_name']; ?></p>
                <h3 class="text-3xl font-bold text-gray-800 mt-1"><?php echo $stat['count']; ?></h3>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
            
            <!-- Add Question Form -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-3xl shadow-xl overflow-hidden">
                    <div class="p-8 border-b border-gray-100 flex justify-between items-center">
                        <h2 class="text-xl font-bold text-gray-800 text-center">Add New Math Challenge</h2>
                    </div>
                    
                    <form action="admin_dashboard.php" method="POST" class="p-8 space-y-6">
                        <?php if ($message): ?>
                            <div class="p-4 rounded-xl text-sm <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                <?php echo $message; ?>
                            </div>
                        <?php endif; ?>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Difficulty Level</label>
                                <select name="level_id" required class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-200 outline-none transition-all">
                                    <?php foreach ($levels as $lvl): ?>
                                        <option value="<?php echo $lvl['level_id']; ?>"><?php echo $lvl['level_name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Question Text</label>
                                <textarea name="question_text" required rows="3" placeholder="e.g., What is the value of x in 2x + 5 = 15?"
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-200 outline-none transition-all"></textarea>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Option A</label>
                                <input type="text" name="option_a" required class="w-full px-4 py-3 rounded-xl border border-gray-100 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-200 outline-none transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Option B</label>
                                <input type="text" name="option_b" required class="w-full px-4 py-3 rounded-xl border border-gray-100 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-200 outline-none transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Option C</label>
                                <input type="text" name="option_c" required class="w-full px-4 py-3 rounded-xl border border-gray-100 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-200 outline-none transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Option D</label>
                                <input type="text" name="option_d" required class="w-full px-4 py-3 rounded-xl border border-gray-100 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-200 outline-none transition-all">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Correct Answer</label>
                            <div class="flex gap-4">
                                <?php foreach(['A', 'B', 'C', 'D'] as $opt): ?>
                                    <label class="flex-1">
                                        <input type="radio" name="correct_option" value="<?php echo $opt; ?>" required class="hidden peer">
                                        <div class="text-center py-3 rounded-xl border-2 border-gray-100 peer-checked:border-indigo-500 peer-checked:bg-indigo-50 peer-checked:text-indigo-700 cursor-pointer transition-all font-bold">
                                            Option <?php echo $opt; ?>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Step-by-Step Solution</label>
                            <textarea name="solution_text" required rows="4" placeholder="Explain how to solve this problem..."
                                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-200 outline-none transition-all"></textarea>
                        </div>

                        <button type="submit" name="add_question" 
                            class="w-full poly-gradient text-white font-bold py-4 rounded-2xl shadow-xl hover:shadow-indigo-200 hover:-translate-y-1 transition-all">
                            UPLOAD CHALLENGE
                        </button>
                    </form>
                </div>
            </div>

            <!-- Quick Tips/Sidebar -->
            <div class="space-y-6">
                <div class="bg-indigo-900 text-white p-8 rounded-3xl shadow-xl">
                    <h3 class="text-lg font-bold mb-4">Admin Quick Tips</h3>
                    <ul class="space-y-4 text-sm opacity-90">
                        <li class="flex gap-3">
                            <span class="bg-white/20 w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 text-xs">1</span>
                            <span>Ensure questions are clear and relevant to the selected level.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="bg-white/20 w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 text-xs">2</span>
                            <span>Double-check the Correct Option before uploading.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="bg-white/20 w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 text-xs">3</span>
                            <span>Use the Solution field to provide value to students who get it wrong.</span>
                        </li>
                    </ul>
                </div>
                
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Difficulty Guide</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center p-3 bg-green-50 rounded-xl">
                            <span class="text-green-700 font-bold text-sm uppercase">Easy</span>
                            <span class="text-xs text-green-600">Basic Arithmetic</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-yellow-50 rounded-xl">
                            <span class="text-yellow-700 font-bold text-sm uppercase">Intermediate</span>
                            <span class="text-xs text-yellow-600">Pre-Algebra</span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-red-50 rounded-xl">
                            <span class="text-red-700 font-bold text-sm uppercase">Expert</span>
                            <span class="text-xs text-red-600">Complex Equations</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

</body>
</html>