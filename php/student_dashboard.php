<?php
require_once 'db.php';

// Access Control
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error_msg = null;

try {
    // Check for Post-Lesson password submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unlock_quiz'])) {
        $check_quiz_id = (int)$_POST['quiz_id'];
        $entered_password = $_POST['password'];
        
        $stmt = $pdo->prepare("SELECT quiz_password FROM quizzes WHERE quiz_id = ?");
        $stmt->execute([$check_quiz_id]);
        $correct_password = $stmt->fetchColumn();
        
        if ($correct_password === $entered_password) {
            $_SESSION['unlocked_quiz_' . $check_quiz_id] = true;
            header("Location: quiz.php?id=" . $check_quiz_id);
            exit();
        } else {
            $error_msg = "Incorrect Post-Lesson Password!";
        }
    }

    // User Context
    $stmt = $pdo->prepare("SELECT u.*, c.class_name FROM users u LEFT JOIN classes c ON u.class_id = c.class_id WHERE u.user_id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();

    if (!$user_data) { session_destroy(); header("Location: login.php"); exit(); }

    // Quizzes (Published missions with 10+ questions + attempt counts)
    $stmt = $pdo->prepare("
        SELECT q.*, l.level_name, 
        (SELECT COUNT(*) FROM questions qn WHERE qn.quiz_id = q.quiz_id) as q_count,
        (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.quiz_id = q.quiz_id AND qa.user_id = ?) as attempt_count
        FROM quizzes q 
        JOIN levels l ON q.level_id = l.level_id 
        WHERE q.is_published = 1
        HAVING q_count >= 10
        ORDER BY l.difficulty_rank ASC
    ");
    $stmt->execute([$user_id]);
    $quizzes = $stmt->fetchAll();

    // History Logic
    $stmt = $pdo->prepare("
        SELECT qa.*, q.quiz_name, l.level_name,
        (
            SELECT COUNT(*) 
            FROM quiz_attempts qa2 
            WHERE qa2.quiz_id = qa.quiz_id 
              AND qa2.user_id = qa.user_id 
              AND qa2.completed_at <= qa.completed_at
        ) as attempt_number
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
        SELECT u.user_id, u.username, IFNULL(SUM(best_attempts.max_score), 0) as total_score
        FROM users u
        LEFT JOIN (
            SELECT user_id, quiz_id, MAX(score) as max_score
            FROM quiz_attempts
            GROUP BY user_id, quiz_id
        ) as best_attempts ON u.user_id = best_attempts.user_id
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
        .kahoot-yellow { background-color: #ffa602; }
        .card-hover:hover { transform: translateY(-8px); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15); }
    </style>
</head>
<body class="pb-16">

    <?php if ($error_msg): ?>
        <div id="toast" class="fixed top-24 left-1/2 transform -translate-x-1/2 z-50 bg-red-100 border-2 border-red-500 text-red-700 px-6 py-3 rounded-2xl shadow-xl font-black text-sm"><?php echo $error_msg; ?></div>
        <script>setTimeout(() => { document.getElementById('toast').style.display = 'none'; }, 5000);</script>
    <?php endif; ?>

    <nav class="poly-gradient text-white shadow-2xl p-4 sm:p-5 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-2 sm:gap-3">
                <div class="w-10 h-10 sm:w-12 sm:h-12 bg-white rounded-xl sm:rounded-2xl flex items-center justify-center shadow-lg transform -rotate-3"><span class="text-[#46178f] font-black text-xl sm:text-2xl">P</span></div>
                <h1 class="text-2xl sm:text-3xl font-black italic uppercase tracking-tighter">PolyMath</h1>
            </div>
            <div class="flex items-center gap-4 sm:gap-6">
                <div class="text-right hidden sm:block">
                    <p class="text-[10px] font-black uppercase opacity-60">Warrior</p>
                    <p class="text-sm font-black"><?php echo htmlspecialchars($user_data['username']); ?></p>
                </div>
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 px-4 py-2 sm:px-6 sm:py-3 rounded-xl sm:rounded-2xl text-[10px] sm:text-xs font-black shadow-xl active:translate-y-1 transition-all">LOGOUT</a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 sm:py-12">
        <div class="mb-8 sm:mb-16">
            <h2 class="text-3xl sm:text-5xl font-black text-gray-800 tracking-tight leading-none mb-2 sm:mb-4">Hello, <?php echo explode(' ', htmlspecialchars($user_data['username']))[0]; ?>! 🚀</h2>
            <p class="text-base sm:text-xl font-bold text-gray-400">Ready to dominate the leaderboard today?</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 sm:gap-12">
            <div class="lg:col-span-2">
                <h3 class="text-2xl sm:text-3xl font-black text-gray-800 italic uppercase mb-6 sm:mb-8 tracking-tighter">Available Challenges</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8">
                    <?php if (empty($quizzes)): ?><div class="col-span-full bg-white p-12 sm:p-20 rounded-[2rem] sm:rounded-[3rem] text-center border-4 border-dashed opacity-30 font-black uppercase tracking-widest text-sm sm:text-base">No Missions Logged</div>
                    <?php else: foreach ($quizzes as $q): 
                        $col = ($q['level_name'] == 'Easy') ? 'border-green-600' : (($q['level_name'] == 'Intermediate') ? 'border-amber-500' : 'border-red-600');
                        $attempts = (int)$q['attempt_count'];
                    ?>
                        <div class="bg-white rounded-[2rem] sm:rounded-[2.5rem] p-6 sm:p-8 border shadow-lg card-hover transition-all border-b-8 <?php echo $col; ?> flex flex-col justify-between">
                            <div class="mb-6">
                                <span class="px-3 sm:px-4 py-1 sm:py-1.5 rounded-full text-[9px] sm:text-[10px] font-black uppercase tracking-widest bg-gray-100 text-gray-500"><?php echo $q['level_name']; ?></span>
                                <h4 class="text-2xl sm:text-3xl font-black text-gray-800 mt-4"><?php echo htmlspecialchars($q['quiz_name']); ?></h4>
                                <p class="text-xs sm:text-sm font-bold text-gray-400 uppercase mt-2 tracking-tighter"><?php echo $q['q_count']; ?> Battle Points Available</p>
                            </div>
                            
                            <?php if ($attempts === 0): ?>
                                <a href="quiz.php?id=<?php echo $q['quiz_id']; ?>" class="block w-full py-4 sm:py-5 poly-gradient text-white text-center font-black rounded-xl sm:rounded-2xl shadow-xl hover:opacity-90 active:scale-95 transition-all uppercase tracking-widest text-sm sm:text-base">PRE-LESSON (Attempt 1)</a>
                            <?php elseif ($attempts === 1): ?>
                                <button onclick="openPasswordModal(<?php echo $q['quiz_id']; ?>, '<?php echo addslashes($q['quiz_name']); ?>')" class="w-full py-4 sm:py-5 kahoot-yellow text-white text-center font-black rounded-xl sm:rounded-2xl shadow-xl hover:opacity-90 active:scale-95 transition-all uppercase tracking-widest text-sm sm:text-base">POST-LESSON (Attempt 2)</button>
                            <?php else: ?>
                                <div class="w-full py-4 sm:py-5 bg-gray-200 text-gray-400 text-center font-black rounded-xl sm:rounded-2xl uppercase tracking-widest text-sm sm:text-base cursor-not-allowed border-2 border-dashed border-gray-300">MISSION COMPLETED</div>
                            <?php endif; ?>
                            
                        </div>
                    <?php endforeach; endif; ?>
                </div>

                <!-- History -->
                <div class="mt-10 sm:mt-16 bg-white rounded-[2rem] sm:rounded-[3rem] shadow-2xl overflow-hidden border-t-8 border-indigo-600">
                    <div class="p-6 sm:p-10 border-b bg-gray-50/50 flex justify-between items-center"><h3 class="text-xl sm:text-2xl font-black text-gray-800 italic uppercase">Quiz History</h3></div>
                    <div class="overflow-x-auto w-full">
                        <table class="w-full text-left min-w-[600px]">
                            <thead class="bg-gray-50 text-gray-400 text-[9px] sm:text-[10px] uppercase font-black tracking-widest">
                                <tr><th class="px-6 sm:px-10 py-4 sm:py-6">Mission</th><th class="px-6 sm:px-10 py-4 sm:py-6 text-center">Phase</th><th class="px-6 sm:px-10 py-4 sm:py-6 text-center">Result</th><th class="px-6 sm:px-10 py-4 sm:py-6 text-right">Date</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php if(empty($recent_attempts)): ?><tr><td colspan="4" class="px-6 sm:px-10 py-12 sm:py-16 text-center text-gray-300 font-bold italic text-sm sm:text-base">No battle records yet.</td></tr><?php endif; ?>
                                <?php foreach ($recent_attempts as $att): ?>
                                <tr class="hover:bg-gray-50/50 transition-all">
                                    <td class="px-6 sm:px-10 py-5 sm:py-8 font-black text-lg sm:text-xl text-gray-800"><?php echo htmlspecialchars($att['quiz_name'] ?? 'Survival Mission'); ?></td>
                                    <td class="px-6 sm:px-10 py-5 sm:py-8 text-center">
                                        <?php if ($att['attempt_number'] == 1): ?>
                                            <span class="bg-blue-50 border border-blue-200 text-blue-600 px-3 py-1.5 rounded-full text-[9px] sm:text-[10px] font-black uppercase tracking-widest whitespace-nowrap">Pre-Lesson</span>
                                        <?php else: ?>
                                            <span class="bg-amber-50 border border-amber-200 text-amber-600 px-3 py-1.5 rounded-full text-[9px] sm:text-[10px] font-black uppercase tracking-widest whitespace-nowrap">Post-Lesson</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 sm:px-10 py-5 sm:py-8 text-center"><span class="text-2xl sm:text-3xl font-black text-indigo-700"><?php echo $att['score']; ?>/<?php echo $att['total_questions']; ?></span></td>
                                    <td class="px-6 sm:px-10 py-5 sm:py-8 text-right text-gray-400 text-[10px] sm:text-xs font-black uppercase"><?php echo date('M d', strtotime($att['completed_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="space-y-8 sm:space-y-10">
                <div class="bg-white rounded-[2.5rem] sm:rounded-[3rem] p-6 sm:p-10 shadow-xl border-4 border-indigo-50 card-hover transition-all">
                    <h3 class="text-xl sm:text-2xl font-black text-gray-800 uppercase italic tracking-tighter mb-6 sm:mb-8">Class Standing</h3>
                    <div class="space-y-3 sm:space-y-4">
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
                        <div class="flex items-center justify-between p-4 sm:p-5 rounded-[1.25rem] sm:rounded-[1.5rem] transition-all <?php echo $bg_class; ?>">
                            <div class="flex items-center gap-3 sm:gap-4 overflow-hidden">
                                <span class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg sm:rounded-xl flex items-center justify-center font-black shrink-0 <?php echo $num_bg; ?>"><?php echo $rank; ?></span>
                                <span class="text-base sm:text-lg font-black tracking-tight truncate max-w-[100px] sm:max-w-[120px]"><?php echo htmlspecialchars($leader['username']); ?></span>
                            </div>
                            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                                <span class="font-black text-xs sm:text-sm <?php echo $is_me ? 'text-indigo-200' : 'text-gray-400'; ?>"><?php echo $leader['total_score']; ?> PTS</span>
                                <?php if($icon): ?><span class="text-lg sm:text-xl"><?php echo $icon; ?></span><?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <?php if(empty($leaderboard)): ?>
                            <div class="p-4 sm:p-5 text-center text-gray-400 font-bold italic text-sm sm:text-base">No ranked students yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Password Modal for Post-Lesson -->
    <div id="password-modal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-6 bg-black/90 backdrop-blur-md">
        <div class="bg-white rounded-[3rem] p-10 max-w-md w-full shadow-2xl border-b-8 border-[#ffa602] relative">
            <button onclick="closePasswordModal()" class="absolute top-6 right-6 text-gray-400 hover:text-gray-800 font-black text-2xl">&times;</button>
            <h3 class="text-3xl font-black mb-2 text-gray-800 uppercase italic">Post-Lesson</h3>
            <p id="modal-quiz-name" class="text-gray-500 font-bold mb-8 uppercase tracking-widest text-xs"></p>
            <form method="POST">
                <input type="hidden" name="unlock_quiz" value="1">
                <input type="hidden" name="quiz_id" id="modal-quiz-id">
                <input type="text" name="password" required placeholder="Enter Secret Password" class="w-full px-5 py-4 rounded-2xl border-2 font-bold mb-6 focus:border-[#ffa602] outline-none text-center text-xl tracking-widest">
                <button type="submit" class="w-full py-5 kahoot-yellow text-white font-black rounded-2xl shadow-xl hover:opacity-90 transition-all uppercase tracking-widest">UNLOCK MISSION</button>
            </form>
        </div>
    </div>

    <script>
        function openPasswordModal(id, name) {
            document.getElementById('modal-quiz-id').value = id;
            document.getElementById('modal-quiz-name').innerText = name;
            document.getElementById('password-modal').classList.remove('hidden');
        }
        function closePasswordModal() {
            document.getElementById('password-modal').classList.add('hidden');
        }
    </script>
</body>
</html>