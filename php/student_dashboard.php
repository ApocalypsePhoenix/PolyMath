<?php
require_once 'db.php';

// Access Control
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // User Context
    $stmt = $pdo->prepare("SELECT u.*, c.class_name FROM users u LEFT JOIN classes c ON u.class_id = c.class_id WHERE u.user_id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();

    if (!$user_data) { session_destroy(); header("Location: login.php"); exit(); }

    // Quizzes (Only Published missions with 10+ questions)
    $quizzes = $pdo->query("
        SELECT q.*, l.level_name, 
        (SELECT COUNT(*) FROM questions qn WHERE qn.quiz_id = q.quiz_id) as q_count 
        FROM quizzes q 
        JOIN levels l ON q.level_id = l.level_id 
        WHERE q.is_published = 1
        HAVING q_count >= 10
        ORDER BY l.difficulty_rank ASC
    ")->fetchAll();

    // FIXED: History Logic
    $stmt = $pdo->prepare("
        SELECT qa.*, q.quiz_name, l.level_name 
        FROM quiz_attempts qa 
        LEFT JOIN quizzes q ON qa.quiz_id = q.quiz_id
        LEFT JOIN levels l ON qa.level_id = l.level_id 
        WHERE qa.user_id = ? 
        ORDER BY qa.completed_at DESC LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $recent_attempts = $stmt->fetchAll();

    // Metrics
    $stmt = $pdo->prepare("SELECT AVG(score / total_questions * 100) as avg_score, COUNT(*) as total FROM quiz_attempts WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();

    // Leaderboard Logic
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.username, IFNULL(SUM(qa.score), 0) as total_score
        FROM users u
        LEFT JOIN quiz_attempts qa ON u.user_id = qa.user_id
        WHERE u.class_id = ? AND u.role = 'student'
        GROUP BY u.user_id, u.username
        ORDER BY total_score DESC, u.username ASC
        LIMIT 5
    ");
    $stmt->execute([$user_data['class_id']]);
    $leaderboard = $stmt->fetchAll();

} catch (PDOException $e) { die("System Error: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PolyMath - Portal</title>
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
                <h1 class="text-3xl font-black italic uppercase tracking-tighter">PolyMath</h1>
            </div>
            <div class="flex items-center gap-6">
                <div class="text-right hidden sm:block">
                    <p class="text-[10px] font-black uppercase opacity-60">Warrior</p>
                    <p class="text-sm font-black"><?php echo htmlspecialchars($user_data['username']); ?></p>
                </div>
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 px-6 py-3 rounded-2xl text-xs font-black shadow-xl active:translate-y-1 transition-all">LOGOUT</a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 py-12">
        <div class="mb-16"><h2 class="text-5xl font-black text-gray-800 tracking-tight leading-none mb-4">Hello, <?php echo explode(' ', htmlspecialchars($user_data['username']))[0]; ?>! 🚀</h2><p class="text-xl font-bold text-gray-400">Ready to dominate the leaderboard today?</p></div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">
            <div class="lg:col-span-2">
                <h3 class="text-3xl font-black text-gray-800 italic uppercase mb-8 tracking-tighter">Available Challenges</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <?php if (empty($quizzes)): ?><div class="col-span-full bg-white p-20 rounded-[3rem] text-center border-4 border-dashed opacity-30 font-black uppercase tracking-widest">No Missions Logged</div>
                    <?php else: foreach ($quizzes as $q): 
                        $col = ($q['level_name'] == 'Easy') ? 'border-green-600' : (($q['level_name'] == 'Intermediate') ? 'border-amber-500' : 'border-red-600');
                    ?>
                        <div class="bg-white rounded-[2.5rem] p-8 border shadow-lg card-hover transition-all border-b-8 <?php echo $col; ?> flex flex-col justify-between">
                            <div class="mb-6">
                                <span class="px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest bg-gray-100 text-gray-500"><?php echo $q['level_name']; ?></span>
                                <h4 class="text-3xl font-black text-gray-800 mt-4"><?php echo htmlspecialchars($q['quiz_name']); ?></h4>
                                <p class="text-sm font-bold text-gray-400 uppercase mt-2 tracking-tighter"><?php echo $q['q_count']; ?> Battle Points Available</p>
                            </div>
                            <a href="quiz.php?id=<?php echo $q['quiz_id']; ?>" class="block w-full py-5 poly-gradient text-white text-center font-black rounded-2xl shadow-xl hover:opacity-90 active:scale-95 transition-all uppercase tracking-widest text-lg">ENTER BATTLE</a>
                        </div>
                    <?php endforeach; endif; ?>
                </div>

                <!-- History -->
                <div class="mt-16 bg-white rounded-[3rem] shadow-2xl overflow-hidden border-t-8 border-indigo-600">
                    <div class="p-10 border-b bg-gray-50/50 flex justify-between items-center"><h3 class="text-2xl font-black text-gray-800 italic uppercase">Quiz History</h3></div>
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 text-gray-400 text-[10px] uppercase font-black tracking-widest"><tr><th class="px-10 py-6">Mission</th><th class="px-10 py-6 text-center">Result</th><th class="px-10 py-6 text-right">Date</th></tr></thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php if(empty($recent_attempts)): ?><tr><td colspan="3" class="px-10 py-16 text-center text-gray-300 font-bold italic">No battle records yet.</td></tr><?php endif; ?>
                            <?php foreach ($recent_attempts as $att): ?>
                            <tr class="hover:bg-gray-50/50 transition-all">
                                <td class="px-10 py-8 font-black text-xl text-gray-800"><?php echo htmlspecialchars($att['quiz_name'] ?? 'Survival Mission'); ?></td>
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
                    <div class="absolute -right-16 -top-16 w-56 h-56 bg-white/10 rounded-full blur-3xl"></div>
                    <h3 class="text-2xl font-black italic uppercase mb-10 tracking-widest">Mastery Stats</h3>
                    <div class="space-y-12 relative z-10">
                        <div class="flex items-center gap-6">
                            <div class="w-20 h-20 bg-white/20 rounded-[2rem] flex items-center justify-center text-4xl shadow-inner">⚡</div>
                            <div><p class="text-indigo-200 text-[10px] font-black uppercase mb-1">Global Accuracy</p><h4 class="text-6xl font-black"><?php echo round($stats['avg_score'] ?? 0, 1); ?><span class="text-2xl opacity-50">%</span></h4></div>
                        </div>
                        <div class="flex items-center gap-6">
                            <div class="w-20 h-20 bg-white/20 rounded-[2rem] flex items-center justify-center text-4xl shadow-inner">🏆</div>
                            <div><p class="text-indigo-200 text-[10px] font-black uppercase mb-1">Total Victories</p><h4 class="text-6xl font-black"><?php echo $stats['total']; ?></h4></div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-[3rem] p-10 shadow-xl border-4 border-indigo-50 card-hover transition-all">
                    <h3 class="text-2xl font-black text-gray-800 uppercase italic tracking-tighter mb-8">Class Standing</h3>
                    <div class="space-y-4">
                        <?php foreach ($leaderboard as $index => $leader): 
                            $rank = $index + 1;
                            $is_me = ($leader['user_id'] === $user_id);
                            $bg_class = $is_me ? "kahoot-blue text-white shadow-xl transform scale-105" : "bg-gray-50 text-gray-700 border-2 border-transparent hover:border-indigo-100";
                            $num_bg = $is_me ? "bg-white/20" : "bg-gray-200 text-gray-500 font-bold";
                            $icon = "";
                            if ($rank == 1) $icon = "👑";
                            else if ($rank == 2) $icon = "🥈";
                            else if ($rank == 3) $icon = "🥉";
                            else if ($is_me) $icon = "🔥";
                        ?>
                        <div class="flex items-center justify-between p-5 rounded-[1.5rem] transition-all <?php echo $bg_class; ?>">
                            <div class="flex items-center gap-4">
                                <span class="w-10 h-10 rounded-xl flex items-center justify-center font-black <?php echo $num_bg; ?>"><?php echo $rank; ?></span>
                                <span class="text-lg font-black tracking-tight truncate max-w-[120px]"><?php echo htmlspecialchars($leader['username']); ?></span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="font-black text-sm <?php echo $is_me ? 'text-indigo-200' : 'text-gray-400'; ?>"><?php echo $leader['total_score']; ?> PTS</span>
                                <?php if($icon): ?><span class="text-xl"><?php echo $icon; ?></span><?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <?php if(empty($leaderboard)): ?>
                            <div class="p-5 text-center text-gray-400 font-bold italic">No ranked students yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>