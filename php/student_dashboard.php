<?php
session_start();
require_once 'db.php';

// Access Control: Ensure only students can access this page
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

    // Fetch all quizzes with their level info and question counts
    $quizzes = $pdo->query("
        SELECT q.*, l.level_name, l.difficulty_rank,
        (SELECT COUNT(*) FROM questions qn WHERE qn.quiz_id = q.quiz_id) as question_count 
        FROM quizzes q 
        JOIN levels l ON q.level_id = l.level_id 
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
    <title>PolyMath - Student Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f0f2f5; }
        .kahoot-purple { background-color: #46178f; }
        .kahoot-blue { background-color: #1368ce; }
        .kahoot-green { background-color: #26890c; }
        .kahoot-red { background-color: #e21b3c; }
        .kahoot-yellow { background-color: #ffa602; }
        .poly-gradient {
            background: linear-gradient(135deg, #46178f 0%, #1368ce 100%);
        }
        .card-hover { transition: transform 0.2s, box-shadow 0.2s; }
        .card-hover:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
        }
        .level-card { border-bottom-width: 8px; }
    </style>
</head>
<body class="pb-16">

    <!-- Navigation -->
    <nav class="poly-gradient text-white shadow-2xl p-5 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-white rounded-2xl flex items-center justify-center shadow-lg transform -rotate-3">
                    <span class="text-[#46178f] font-black text-2xl">P</span>
                </div>
                <h1 class="text-3xl font-black tracking-tighter italic uppercase">PolyMath</h1>
            </div>
            <div class="flex items-center gap-6">
                <div class="text-right hidden sm:block">
                    <p class="text-[10px] font-black uppercase opacity-60">Math Warrior</p>
                    <p class="text-sm font-black"><?php echo htmlspecialchars($user_data['username']); ?></p>
                </div>
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 px-6 py-3 rounded-2xl text-xs font-black transition-all shadow-xl shadow-red-900/20 active:translate-y-1">LOGOUT</a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 py-12">
        
        <!-- Welcome Section -->
        <div class="mb-16 flex flex-col md:flex-row justify-between items-end gap-6">
            <div>
                <h2 class="text-5xl font-black text-gray-800 tracking-tight leading-none mb-4">Hello, <?php echo explode(' ', htmlspecialchars($user_data['username']))[0]; ?>! 🚀</h2>
                <p class="text-xl font-bold text-gray-400">Ready to dominate the leaderboard today?</p>
            </div>
            <div class="bg-white px-8 py-4 rounded-[2rem] shadow-xl flex items-center gap-4 border-2 border-indigo-50">
                <span class="w-12 h-12 kahoot-purple rounded-2xl flex items-center justify-center text-2xl">🏆</span>
                <div>
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Global Rank</p>
                    <p class="text-2xl font-black text-gray-800">Elite Scout</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">
            
            <!-- Quiz Selection -->
            <div class="lg:col-span-2">
                <div class="flex items-center justify-between mb-8">
                    <h3 class="text-3xl font-black text-gray-800 italic uppercase tracking-tighter">Available Challenges</h3>
                    <span class="bg-indigo-100 text-indigo-700 px-4 py-2 rounded-xl text-xs font-black uppercase"><?php echo count($quizzes); ?> Quizzes</span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <?php if (empty($quizzes)): ?>
                        <div class="col-span-full bg-white p-20 rounded-[3rem] text-center border-4 border-dashed border-gray-100">
                            <p class="text-3xl font-black text-gray-200 uppercase tracking-widest">No Quizzes Found</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($quizzes as $q): 
                            $bg = ($q['level_name'] == 'Easy') ? 'border-green-600' : (($q['level_name'] == 'Intermediate') ? 'border-amber-500' : 'border-red-600');
                            $icon = ($q['level_name'] == 'Easy') ? '🌱' : (($q['level_name'] == 'Intermediate') ? '⚔️' : '🔥');
                            $btn = ($q['level_name'] == 'Easy') ? 'kahoot-green' : (($q['level_name'] == 'Intermediate') ? 'kahoot-yellow' : 'kahoot-red');
                        ?>
                        <div class="bg-white rounded-[2.5rem] p-8 border shadow-lg card-hover level-card <?php echo $bg; ?> flex flex-col justify-between">
                            <div class="mb-6">
                                <div class="flex justify-between items-start mb-6">
                                    <span class="px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest <?php echo str_replace('border-', 'bg-', $bg); ?> text-white">
                                        <?php echo $q['level_name']; ?>
                                    </span>
                                    <?php if($q['is_timed']): ?>
                                        <div class="flex items-center gap-2 bg-red-50 text-red-600 px-3 py-1 rounded-xl text-[10px] font-black">
                                            ⏱ <?php echo floor($q['time_limit'] / 60); ?> MIN
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <h4 class="text-3xl font-black text-gray-800 leading-tight mb-2"><?php echo htmlspecialchars($q['quiz_name']); ?></h4>
                                <p class="text-sm font-bold text-gray-400 uppercase tracking-widest"><?php echo $q['question_count']; ?> Challenges Included</p>
                            </div>
                            
                            <a href="quiz.php?id=<?php echo $q['quiz_id']; ?>" 
                               class="block w-full py-5 <?php echo $btn; ?> text-white text-center font-black rounded-2xl shadow-xl hover:opacity-90 active:translate-y-1 active:shadow-none transition-all uppercase tracking-widest text-lg">
                                ENTER <?php echo $icon; ?>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Recent Activity -->
                <div class="mt-16 bg-white rounded-[3rem] shadow-2xl overflow-hidden border-t-8 border-indigo-600">
                    <div class="p-10 border-b bg-gray-50/50 flex justify-between items-center">
                        <h3 class="text-2xl font-black text-gray-800 italic uppercase">Battle History</h3>
                        <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Last 5 Sessions</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50 text-gray-400 text-[10px] uppercase font-black tracking-widest">
                                <tr>
                                    <th class="px-10 py-6">Mission</th>
                                    <th class="px-10 py-6">Performance</th>
                                    <th class="px-10 py-6">Speed</th>
                                    <th class="px-10 py-6 text-right">Completion</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php if (empty($recent_attempts)): ?>
                                    <tr>
                                        <td colspan="4" class="px-10 py-16 text-center text-gray-300 font-black uppercase text-xs tracking-widest italic">No battle records found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_attempts as $attempt): ?>
                                    <tr class="hover:bg-gray-50/80 transition-all group">
                                        <td class="px-10 py-8">
                                            <div class="flex flex-col">
                                                <span class="font-black text-xl text-gray-800 group-hover:text-indigo-600 transition-colors"><?php echo htmlspecialchars($attempt['quiz_name'] ?? 'Survival Mode'); ?></span>
                                                <span class="text-[10px] uppercase font-black text-gray-400 tracking-widest"><?php echo htmlspecialchars($attempt['level_name']); ?> RANK</span>
                                            </div>
                                        </td>
                                        <td class="px-10 py-8">
                                            <div class="flex items-center gap-4">
                                                <span class="text-3xl font-black text-indigo-700"><?php echo $attempt['score']; ?><span class="text-sm opacity-30">/<?php echo $attempt['total_questions']; ?></span></span>
                                                <?php 
                                                    $pct = ($attempt['total_questions'] > 0) ? ($attempt['score'] / $attempt['total_questions']) * 100 : 0;
                                                    $barColor = ($pct >= 80) ? 'bg-green-500' : (($pct >= 50) ? 'bg-amber-500' : 'bg-red-500');
                                                ?>
                                                <div class="w-20 h-2 bg-gray-100 rounded-full overflow-hidden hidden sm:block">
                                                    <div class="<?php echo $barColor; ?> h-full" style="width: <?php echo $pct; ?>%"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-10 py-8 text-gray-500 font-black text-sm uppercase">
                                            <?php echo floor($attempt['time_taken_seconds'] / 60); ?>m <?php echo $attempt['time_taken_seconds'] % 60; ?>s
                                        </td>
                                        <td class="px-10 py-8 text-right text-gray-400 text-xs font-black uppercase tracking-tighter">
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

            <!-- Profile & Stats -->
            <div class="space-y-10">
                <div class="poly-gradient rounded-[3rem] p-10 text-white shadow-2xl relative overflow-hidden card-hover border-b-8 border-indigo-900">
                    <div class="absolute -right-16 -top-16 w-56 h-56 bg-white/10 rounded-full blur-3xl"></div>
                    
                    <h3 class="text-2xl font-black italic uppercase mb-10 tracking-widest">Mastery Level</h3>
                    <div class="space-y-12 relative z-10">
                        <div class="flex items-center gap-6">
                            <div class="w-20 h-20 bg-white/20 rounded-[2rem] flex items-center justify-center text-4xl shadow-inner">⚡</div>
                            <div>
                                <p class="text-indigo-200 text-[10px] font-black uppercase tracking-widest mb-1">Average Accuracy</p>
                                <h4 class="text-6xl font-black leading-none"><?php echo round($stats['avg_score'] ?? 0, 1); ?><span class="text-2xl font-light opacity-50">%</span></h4>
                            </div>
                        </div>
                        <div class="flex items-center gap-6">
                            <div class="w-20 h-20 bg-white/20 rounded-[2rem] flex items-center justify-center text-4xl shadow-inner">🎒</div>
                            <div>
                                <p class="text-indigo-200 text-[10px] font-black uppercase tracking-widest mb-1">Total Victories</p>
                                <h4 class="text-6xl font-black leading-none"><?php echo $stats['total_quizzes']; ?></h4>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-[3rem] p-10 shadow-xl border-4 border-indigo-50 card-hover">
                    <div class="flex items-center justify-between mb-10">
                        <h3 class="text-2xl font-black text-gray-800 uppercase italic tracking-tighter">Leaderboard</h3>
                        <span class="bg-gray-100 text-gray-400 px-4 py-1.5 rounded-full text-[10px] font-black"><?php echo htmlspecialchars($user_data['class_name'] ?? 'ELITE'); ?></span>
                    </div>
                    
                    <div class="space-y-6">
                        <div class="flex items-center justify-between p-5 kahoot-blue rounded-[1.5rem] shadow-xl transform hover:scale-105 transition-all text-white">
                            <div class="flex items-center gap-4">
                                <span class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center font-black">1</span>
                                <span class="text-lg font-black tracking-tight"><?php echo htmlspecialchars($user_data['username']); ?></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-black opacity-60">MVP</span>
                                <span class="text-xl">🔥</span>
                            </div>
                        </div>
                        
                        <div class="p-6 bg-gray-50 rounded-[1.5rem] border-2 border-dashed border-gray-200 text-center">
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest leading-relaxed">More contenders will appear here once battles begin!</p>
                        </div>
                    </div>
                </div>

                <!-- Kahoot-style decorative footer -->
                <div class="grid grid-cols-4 gap-2 px-2 opacity-10">
                    <div class="h-4 kahoot-red rounded-full"></div>
                    <div class="h-4 kahoot-blue rounded-full"></div>
                    <div class="h-4 kahoot-yellow rounded-full"></div>
                    <div class="h-4 kahoot-green rounded-full"></div>
                </div>
            </div>

        </div>
    </main>

</body>
</html>