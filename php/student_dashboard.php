<?php
session_start();
require_once 'db.php';

// Access Control
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Fetch user's class info
    $stmt = $pdo->prepare("SELECT u.*, c.class_name FROM users u LEFT JOIN classes c ON u.class_id = c.class_id WHERE u.user_id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();

    if (!$user_data) {
        session_destroy();
        header("Location: login.php");
        exit();
    }

    // VALIDATION: Only show quizzes with 10+ questions
    $quizzes = $pdo->query("
        SELECT q.*, l.level_name, l.difficulty_rank,
        (SELECT COUNT(*) FROM questions qn WHERE qn.quiz_id = q.quiz_id) as question_count 
        FROM quizzes q 
        JOIN levels l ON q.level_id = l.level_id 
        HAVING question_count >= 10
        ORDER BY l.difficulty_rank ASC, q.created_at DESC
    ")->fetchAll();

    // Fetch recent quiz attempts
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

    // Calculate overall stats
    $stmt = $pdo->prepare("SELECT AVG(score / total_questions * 100) as avg_score, COUNT(*) as total_quizzes FROM quiz_attempts WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PolyMath Student Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f0f2f5; }
        .poly-gradient { background: linear-gradient(135deg, #46178f 0%, #1368ce 100%); }
        .kahoot-purple { background-color: #46178f; }
        .kahoot-blue { background-color: #1368ce; }
        .kahoot-green { background-color: #26890c; }
        .kahoot-red { background-color: #e21b3c; }
        .kahoot-yellow { background-color: #ffa602; }
    </style>
</head>
<body class="pb-16">

    <nav class="poly-gradient text-white shadow-2xl p-5 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-white rounded-2xl flex items-center justify-center transform -rotate-3">
                    <span class="text-[#46178f] font-black text-2xl">P</span>
                </div>
                <h1 class="text-3xl font-black tracking-tighter italic uppercase">PolyMath</h1>
            </div>
            <div class="flex items-center gap-6">
                <div class="text-right hidden sm:block">
                    <p class="text-[10px] font-black uppercase opacity-60">Warrior Status</p>
                    <p class="text-sm font-black"><?php echo htmlspecialchars($user_data['username']); ?></p>
                </div>
                <a href="logout.php" class="bg-red-500 px-6 py-3 rounded-2xl text-xs font-black shadow-xl shadow-red-900/20">LOGOUT</a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 py-12">
        
        <div class="mb-16">
            <h2 class="text-5xl font-black text-gray-800 tracking-tight leading-none mb-4">Hello, <?php echo explode(' ', htmlspecialchars($user_data['username']))[0]; ?>! 🚀</h2>
            <p class="text-xl font-bold text-gray-400">Ready to dominate the leaderboard?</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">
            
            <div class="lg:col-span-2">
                <h3 class="text-3xl font-black text-gray-800 italic uppercase mb-8">Available Challenges</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <?php if (empty($quizzes)): ?>
                        <div class="col-span-full bg-white p-20 rounded-[3rem] text-center border-4 border-dashed">
                            <p class="text-2xl font-black text-gray-200 uppercase tracking-widest">No published quizzes yet!</p>
                            <p class="text-xs text-gray-400 font-bold uppercase mt-2">Check back later once the teacher completes some missions.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($quizzes as $q): 
                            $bg = ($q['level_name'] == 'Easy') ? 'border-green-600' : (($q['level_name'] == 'Intermediate') ? 'border-amber-500' : 'border-red-600');
                            $icon = ($q['level_name'] == 'Easy') ? '🌱' : (($q['level_name'] == 'Intermediate') ? '⚔️' : '🔥');
                        ?>
                        <div class="bg-white rounded-[2.5rem] p-8 border-b-8 shadow-lg <?php echo $bg; ?> flex flex-col justify-between">
                            <div class="mb-6">
                                <span class="px-4 py-1.5 rounded-full text-[10px] font-black uppercase <?php echo str_replace('border-', 'bg-', $bg); ?> text-white"><?php echo $q['level_name']; ?></span>
                                <h4 class="text-3xl font-black text-gray-800 mt-4"><?php echo htmlspecialchars($q['quiz_name']); ?></h4>
                                <p class="text-sm font-bold text-gray-400 mt-2 uppercase"><?php echo $q['question_count']; ?> Challenges</p>
                            </div>
                            <a href="quiz.php?id=<?php echo $q['quiz_id']; ?>" class="block w-full py-5 kahoot-purple text-white text-center font-black rounded-2xl shadow-xl uppercase">ENTER <?php echo $icon; ?></a>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="mt-16 bg-white rounded-[3rem] shadow-2xl overflow-hidden">
                    <div class="p-10 border-b bg-gray-50 flex justify-between items-center">
                        <h3 class="text-2xl font-black text-gray-800 italic uppercase">Quiz History</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50 text-[10px] uppercase font-black text-gray-400 tracking-widest">
                                <tr>
                                    <th class="px-10 py-6">Mission</th>
                                    <th class="px-10 py-6">Performance</th>
                                    <th class="px-10 py-6 text-right">Completion</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php if (empty($recent_attempts)): ?>
                                    <tr><td colspan="3" class="px-10 py-16 text-center text-gray-300 font-black italic">No records found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent_attempts as $attempt): ?>
                                    <tr>
                                        <td class="px-10 py-8">
                                            <span class="font-black text-xl text-gray-800"><?php echo htmlspecialchars($attempt['quiz_name'] ?? 'Mission'); ?></span>
                                        </td>
                                        <td class="px-10 py-8">
                                            <span class="text-3xl font-black text-indigo-700"><?php echo $attempt['score']; ?><span class="text-sm opacity-30">/<?php echo $attempt['total_questions']; ?></span></span>
                                        </td>
                                        <td class="px-10 py-8 text-right text-gray-400 text-xs font-black uppercase">
                                            <?php echo date('M d', strtotime($attempt['completed_at'])); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="space-y-10">
                <div class="poly-gradient rounded-[3rem] p-10 text-white shadow-2xl relative overflow-hidden border-b-8 border-indigo-900">
                    <h3 class="text-2xl font-black italic uppercase mb-10">Mastery Level</h3>
                    <div class="space-y-12 relative z-10">
                        <div class="flex items-center gap-6">
                            <div class="w-20 h-20 bg-white/20 rounded-[2rem] flex items-center justify-center text-4xl shadow-inner">⚡</div>
                            <div>
                                <p class="text-indigo-200 text-[10px] font-black uppercase mb-1">Accuracy</p>
                                <h4 class="text-6xl font-black"><?php echo round($stats['avg_score'] ?? 0, 1); ?><span class="text-2xl opacity-50">%</span></h4>
                            </div>
                        </div>
                        <div class="flex items-center gap-6">
                            <div class="w-20 h-20 bg-white/20 rounded-[2rem] flex items-center justify-center text-4xl shadow-inner">🏆</div>
                            <div>
                                <p class="text-indigo-200 text-[10px] font-black uppercase mb-1">Total Victories</p>
                                <h4 class="text-6xl font-black"><?php echo $stats['total_quizzes']; ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>