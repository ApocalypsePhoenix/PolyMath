<?php
session_start();
require_once 'db.php';

// Access Control: Ensure only students can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user's class info
$stmt = $pdo->prepare("SELECT u.*, c.class_name FROM users u JOIN classes c ON u.class_id = c.class_id WHERE u.user_id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();

// Fetch all difficulty levels
$levels = $pdo->query("SELECT * FROM levels ORDER BY difficulty_rank ASC")->fetchAll();

// Fetch recent quiz attempts
$stmt = $pdo->prepare("SELECT q.*, l.level_name 
                       FROM quiz_attempts q 
                       JOIN levels l ON q.level_id = l.level_id 
                       WHERE q.user_id = ? 
                       ORDER BY q.completed_at DESC LIMIT 5");
$stmt->execute([$user_id]);
$recent_attempts = $stmt->fetchAll();

// Calculate total score or average
$stmt = $pdo->prepare("SELECT AVG(score) as avg_score, COUNT(*) as total_quizzes FROM quiz_attempts WHERE user_id = ?");
$stmt->execute([$user_id]);
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PolyMath - Student Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .poly-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">

    <!-- Navigation -->
    <nav class="poly-gradient text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
            <a href="<?php echo BASE_URL; ?>" class="text-2xl font-bold tracking-tighter italic">PolyMath</a>
            <div class="flex items-center gap-6">
                <div class="text-right hidden sm:block">
                    <p class="text-sm font-bold"><?php echo htmlspecialchars($user_data['username']); ?></p>
                    <p class="text-xs opacity-75"><?php echo htmlspecialchars($user_data['class_name']); ?></p>
                </div>
                <a href="logout.php" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg text-sm font-semibold transition-all">Logout</a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 py-10">
        
        <!-- Welcome Section -->
        <div class="mb-10">
            <h2 class="text-3xl font-bold text-gray-800">Welcome back, <?php echo explode(' ', htmlspecialchars($user_data['username']))[0]; ?>! 👋</h2>
            <p class="text-gray-500 mt-2">Ready to level up your math skills today?</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
            
            <!-- Level Selection -->
            <div class="lg:col-span-2">
                <h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                    <span class="p-2 bg-indigo-100 text-indigo-600 rounded-lg">🚀</span> 
                    Choose Your Level
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php foreach ($levels as $lvl): 
                        $color = ($lvl['level_name'] == 'Easy') ? 'green' : (($lvl['level_name'] == 'Intermediate') ? 'yellow' : 'red');
                    ?>
                    <div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm card-hover transition-all text-center">
                        <div class="w-16 h-16 bg-<?php echo $color; ?>-100 text-<?php echo $color; ?>-600 rounded-2xl flex items-center justify-center mx-auto mb-6 text-2xl">
                            <?php if($lvl['level_name'] == 'Easy') echo '🌱'; 
                                  elseif($lvl['level_name'] == 'Intermediate') echo '⚔️'; 
                                  else echo '🔥'; ?>
                        </div>
                        <h4 class="text-xl font-bold text-gray-800"><?php echo $lvl['level_name']; ?></h4>
                        <p class="text-sm text-gray-400 mt-2">10 Challenges</p>
                        <a href="quiz.php?level=<?php echo $lvl['level_id']; ?>" 
                           class="mt-8 block w-full py-3 px-4 bg-gray-900 text-white font-bold rounded-xl hover:bg-indigo-600 transition-colors">
                            START
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Recent Activity Table -->
                <div class="mt-12 bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="p-6 border-b border-gray-50 flex justify-between items-center">
                        <h3 class="font-bold text-gray-800">Recent Quiz Activity</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50 text-gray-400 text-xs uppercase font-bold">
                                <tr>
                                    <th class="px-6 py-4">Level</th>
                                    <th class="px-6 py-4">Score</th>
                                    <th class="px-6 py-4">Time</th>
                                    <th class="px-6 py-4">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php if (empty($recent_attempts)): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-10 text-center text-gray-400">No quizzes completed yet. Start your first one!</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_attempts as $attempt): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4">
                                            <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($attempt['level_name']); ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-indigo-600"><?php echo $attempt['score']; ?>/<?php echo $attempt['total_questions']; ?></span>
                                                <?php 
                                                    $pct = ($attempt['score'] / $attempt['total_questions']) * 100;
                                                    $barColor = ($pct >= 80) ? 'bg-green-500' : (($pct >= 50) ? 'bg-yellow-500' : 'bg-red-500');
                                                ?>
                                                <div class="w-16 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                                    <div class="<?php echo $barColor; ?> h-full" style="width: <?php echo $pct; ?>%"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-gray-500"><?php echo floor($attempt['time_taken_seconds'] / 60); ?>m <?php echo $attempt['time_taken_seconds'] % 60; ?>s</td>
                                        <td class="px-6 py-4 text-gray-400 text-sm"><?php echo date('M d, Y', strtotime($attempt['completed_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Profile & Stats Sidebar -->
            <div class="space-y-8">
                <div class="bg-indigo-600 rounded-3xl p-8 text-white shadow-xl relative overflow-hidden">
                    <!-- Decorative Circle -->
                    <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full"></div>
                    
                    <h3 class="text-xl font-bold mb-6">Your Progress</h3>
                    <div class="space-y-6 relative z-10">
                        <div>
                            <p class="text-indigo-200 text-xs font-bold uppercase tracking-widest">Average Score</p>
                            <h4 class="text-4xl font-bold"><?php echo round($stats['avg_score'] ?? 0, 1); ?>%</h4>
                        </div>
                        <div>
                            <p class="text-indigo-200 text-xs font-bold uppercase tracking-widest">Quizzes Completed</p>
                            <h4 class="text-4xl font-bold"><?php echo $stats['total_quizzes']; ?></h4>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-3xl p-8 shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Class Leaderboard</h3>
                    <p class="text-sm text-gray-400 mb-6">Top performers in <?php echo htmlspecialchars($user_data['class_name']); ?></p>
                    <div class="space-y-4">
                        <p class="text-xs text-center text-gray-400 italic">Leaderboard coming soon after more students join!</p>
                    </div>
                </div>
            </div>

        </div>
    </main>

</body>
</html>