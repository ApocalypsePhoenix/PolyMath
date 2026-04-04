<?php
session_start();
require_once 'db.php';

// Access Control
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Original User Info query
    $stmt = $pdo->prepare("SELECT u.*, c.class_name FROM users u LEFT JOIN classes c ON u.class_id = c.class_id WHERE u.user_id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();

    if (!$user_data) { session_destroy(); header("Location: login.php"); exit(); }

    // Quizzes (Published only)
    $quizzes = $pdo->query("
        SELECT q.*, l.level_name, (SELECT COUNT(*) FROM questions qn WHERE qn.quiz_id = q.quiz_id) as q_count 
        FROM quizzes q JOIN levels l ON q.level_id = l.level_id 
        WHERE q.is_published = 1 HAVING q_count >= 10
        ORDER BY l.difficulty_rank ASC
    ")->fetchAll();

    // FIXED History Query
    $stmt = $pdo->prepare("
        SELECT qa.*, q.quiz_name, l.level_name 
        FROM quiz_attempts qa 
        LEFT JOIN quizzes q ON qa.quiz_id = q.quiz_id
        LEFT JOIN levels l ON qa.level_id = l.level_id 
        WHERE qa.user_id = ? 
        ORDER BY qa.completed_at DESC LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_attempts = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT AVG(score / total_questions * 100) as avg_score, COUNT(*) as total FROM quiz_attempts WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();

} catch (PDOException $e) { die("System Error: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hub - PolyMath</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f0f2f5; }
        .poly-gradient { background: linear-gradient(135deg, #46178f 0%, #1368ce 100%); }
        .kahoot-blue { background-color: #1368ce; }
        .card-hover:hover { transform: translateY(-8px); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15); }
    </style>
</head>
<body class="pb-16">

    <nav class="poly-gradient text-white shadow-2xl p-5 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-white rounded-2xl flex items-center justify-center shadow-lg transform -rotate-3"><span class="text-[#46178f] font-black text-2xl">P</span></div>
                <h1 class="text-3xl font-black italic tracking-tighter uppercase">PolyMath</h1>
            </div>
            <div class="flex items-center gap-6">
                <div class="text-right hidden sm:block">
                    <p class="text-[10px] font-black uppercase opacity-60">Math Warrior</p>
                    <p class="text-sm font-black"><?php echo htmlspecialchars($user_data['username']); ?></p>
                </div>
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 px-6 py-3 rounded-2xl text-xs font-black transition-all shadow-xl active:translate-y-1">LOGOUT</a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 py-12">
        <div class="mb-16"><h2 class="text-5xl font-black text-gray-800 tracking-tight leading-none mb-4">Hello, <?php echo explode(' ', htmlspecialchars($user_data['username']))[0]; ?>! 🚀</h2><p class="text-xl font-bold text-gray-400">Ready to dominate the leaderboard today?</p></div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">
            <div class="lg:col-span-2">
                <h3 class="text-3xl font-black text-gray-800 italic uppercase mb-8 tracking-tighter">Available Challenges</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <?php foreach ($quizzes as $q): 
                        $col = ($q['level_name'] == 'Easy') ? 'border-green-600' : (($q['level_name'] == 'Intermediate') ? 'border-amber-500' : 'border-red-600');
                    ?>
                        <div class="bg-white rounded-[2.5rem] p-8 border shadow-lg card-hover transition-all border-b-8 <?php echo $col; ?> flex flex-col justify-between">
                            <div class="mb-6">
                                <span class="px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest bg-gray-100 text-gray-500"><?php echo $q['level_name']; ?></span>
                                <h4 class="text-3xl font-black text-gray-800 mt-4"><?php echo htmlspecialchars($q['quiz_name']); ?></h4>
                                <p class="text-sm font-bold text-gray-400 uppercase mt-2"><?php echo $q['q_count']; ?> Problems</p>
                            </div>
                            <a href="quiz.php?id=<?php echo $q['quiz_id']; ?>" class="block w-full py-5 poly-gradient text-white text-center font-black rounded-2xl shadow-xl transition-all uppercase tracking-widest text-lg">ENTER BATTLE</a>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-16 bg-white rounded-[3rem] shadow-2xl overflow-hidden border-t-8 border-indigo-600">
                    <div class="p-10 border-b bg-gray-50/50 flex justify-between items-center"><h3 class="text-2xl font-black text-gray-800 italic uppercase">Quiz History</h3></div>
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 text-gray-400 text-[10px] uppercase font-black tracking-widest"><tr><th class="px-10 py-6">Mission</th><th class="px-10 py-6 text-center">Score</th><th class="px-10 py-6 text-right">Completion</th></tr></thead>
                        <tbody class="divide-y">
                            <?php foreach ($recent_attempts as $att): ?>
                            <tr>
                                <td class="px-10 py-8 font-black text-xl text-gray-800"><?php echo htmlspecialchars($att['quiz_name'] ?? 'Mission'); ?></td>
                                <td class="px-10 py-8 text-center"><span class="text-3xl font-black text-indigo-700"><?php echo $att['score']; ?>/<?php echo $att['total_questions']; ?></span></td>
                                <td class="px-10 py-8 text-right text-gray-400 text-xs font-black uppercase"><?php echo date('M d', strtotime($att['completed_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="space-y-10">
                <div class="poly-gradient rounded-[3rem] p-10 text-white shadow-2xl relative overflow-hidden border-b-8 border-indigo-900">
                    <h3 class="text-2xl font-black italic uppercase mb-10 tracking-widest">Mastery Level</h3>
                    <div class="space-y-12 relative z-10">
                        <div class="flex items-center gap-6">
                            <div class="w-20 h-20 bg-white/20 rounded-[2rem] flex items-center justify-center text-4xl shadow-inner">⚡</div>
                            <div><p class="text-indigo-200 text-[10px] font-black uppercase mb-1">Accuracy</p><h4 class="text-6xl font-black"><?php echo round($stats['avg_score'] ?? 0, 1); ?><span class="text-2xl opacity-50">%</span></h4></div>
                        </div>
                        <div class="flex items-center gap-6">
                            <div class="w-20 h-20 bg-white/20 rounded-[2rem] flex items-center justify-center text-4xl shadow-inner">🏆</div>
                            <div><p class="text-indigo-200 text-[10px] font-black uppercase mb-1">Total Victories</p><h4 class="text-6xl font-black"><?php echo $stats['total']; ?></h4></div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-[3rem] p-10 shadow-xl border-4 border-indigo-50 card-hover">
                    <h3 class="text-2xl font-black text-gray-800 uppercase italic tracking-tighter mb-8">Class Rank</h3>
                    <div class="flex items-center justify-between p-5 kahoot-blue rounded-[1.5rem] shadow-xl text-white">
                        <div class="flex items-center gap-4"><span class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center font-black">1</span><span class="text-lg font-black tracking-tight"><?php echo htmlspecialchars($user_data['username']); ?></span></div>
                        <span class="text-xl">🔥</span>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>