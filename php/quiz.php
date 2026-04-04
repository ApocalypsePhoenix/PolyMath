<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$quiz_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch Quiz Data
$stmt = $pdo->prepare("SELECT * FROM quizzes WHERE quiz_id = ?");
$stmt->execute([$quiz_id]);
$quiz = $stmt->fetch();

if (!$quiz) {
    header("Location: student_dashboard.php");
    exit();
}

// Fetch 10 Random Questions for this quiz
$stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY RAND() LIMIT 10");
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll();
$questions_json = json_encode($questions);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PolyMath - <?php echo htmlspecialchars($quiz['quiz_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #46178f; color: white; min-height: 100vh; }
        .global-timer-bar { transition: width 1s linear; }
    </style>
</head>
<body>

    <div class="fixed top-0 w-full z-50 bg-black/30 backdrop-blur-md px-6 py-4 flex justify-between items-center">
        <span class="text-xl font-bold"><?php echo htmlspecialchars($quiz['quiz_name']); ?></span>
        <?php if($quiz['is_timed']): ?>
            <div id="global-timer" class="bg-red-500 px-4 py-1 rounded-full font-bold text-sm">Time Left: --:--</div>
        <?php endif; ?>
        <div id="q-counter" class="text-sm font-bold">Q 1/10</div>
    </div>

    <?php if($quiz['is_timed']): ?>
    <div class="fixed top-[64px] left-0 w-full h-1 bg-white/10 z-50">
        <div id="timer-progress" class="h-full bg-red-400 global-timer-bar" style="width: 100%"></div>
    </div>
    <?php endif; ?>

    <main class="pt-24 pb-12 px-6 max-w-4xl mx-auto flex flex-col items-center min-h-screen">
        <div id="quiz-ui" class="w-full">
            <div class="text-center mb-8">
                <div id="q-img-container" class="hidden mb-6">
                    <img id="q-img" src="" class="max-h-64 mx-auto rounded-3xl shadow-2xl border-4 border-white/10">
                </div>
                <h2 id="q-text" class="text-3xl font-extrabold leading-tight">Loading...</h2>
            </div>

            <div id="options" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach(['A','B','C','D'] as $o): ?>
                    <button onclick="submitAnswer('<?php echo $o; ?>')" class="p-6 rounded-3xl text-left font-bold text-xl transition-all active:scale-95 bg-white/10 hover:bg-white/20 border-2 border-white/5">
                        <span class="bg-white/20 px-3 py-1 rounded-lg mr-3"><?php echo $o; ?></span>
                        <span id="opt-<?php echo strtolower($o); ?>">...</span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="result-overlay" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-6 bg-black/80 backdrop-blur-md">
            <div class="bg-white text-gray-900 rounded-[40px] p-8 max-w-md w-full text-center shadow-2xl">
                <div id="res-icon" class="text-6xl mb-4"></div>
                <h3 id="res-title" class="text-3xl font-black mb-4"></h3>
                <div class="bg-gray-50 rounded-3xl p-6 text-left mb-6 max-h-48 overflow-y-auto border border-gray-100">
                    <p class="text-xs font-black text-gray-400 uppercase mb-2">Solution</p>
                    <p id="res-sol" class="text-sm text-gray-600 leading-relaxed"></p>
                </div>
                <button onclick="nextQ()" class="w-full py-4 poly-gradient text-white font-bold rounded-2xl shadow-xl">CONTINUE</button>
            </div>
        </div>
    </main>

    <script>
        const questions = <?php echo $questions_json; ?>;
        const quizConfig = {
            id: <?php echo $quiz_id; ?>,
            timed: <?php echo $quiz['is_timed']; ?>,
            limit: <?php echo $quiz['time_limit']; ?>
        };
        
        let currentIdx = 0;
        let score = 0;
        let globalTimeLeft = quizConfig.limit;
        let globalTimerId = null;

        function updateGlobalTimer() {
            if (!quizConfig.timed) return;
            const mins = Math.floor(globalTimeLeft / 60);
            const secs = globalTimeLeft % 60;
            document.getElementById('global-timer').innerText = `Time Left: ${mins}:${secs.toString().padStart(2, '0')}`;
            document.getElementById('timer-progress').style.width = `${(globalTimeLeft / quizConfig.limit) * 100}%`;
            
            if (globalTimeLeft <= 0) {
                clearInterval(globalTimerId);
                finishQuiz();
            }
            globalTimeLeft--;
        }

        function loadQ() {
            if (currentIdx >= questions.length) { finishQuiz(); return; }
            const q = questions[currentIdx];
            document.getElementById('q-counter').innerText = `Q ${currentIdx+1}/10`;
            document.getElementById('q-text').innerText = q.question_text;
            document.getElementById('opt-a').innerText = q.option_a;
            document.getElementById('opt-b').innerText = q.option_b;
            document.getElementById('opt-c').innerText = q.option_c;
            document.getElementById('opt-d').innerText = q.option_d;
            
            const imgContainer = document.getElementById('q-img-container');
            if (q.question_image) {
                imgContainer.classList.remove('hidden');
                document.getElementById('q-img').src = 'uploads/' + q.question_image;
            } else {
                imgContainer.classList.add('hidden');
            }
            document.getElementById('result-overlay').classList.add('hidden');
        }

        function submitAnswer(sel) {
            const q = questions[currentIdx];
            const correct = (sel === q.correct_option);
            if (correct) score++;
            
            document.getElementById('res-icon').innerText = correct ? "✅" : "❌";
            document.getElementById('res-title').innerText = correct ? "Great Job!" : "Not Quite";
            document.getElementById('res-sol').innerText = q.solution_text || "The correct answer is " + q.correct_option;
            document.getElementById('result-overlay').classList.remove('hidden');
        }

        function nextQ() { currentIdx++; loadQ(); }

        async function finishQuiz() {
            const timeTaken = quizConfig.limit - globalTimeLeft;
            const resp = await fetch('submit_quiz.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    level_id: questions[0].level_id, 
                    quiz_id: quizConfig.id,
                    score: score, 
                    total_questions: questions.length, 
                    time_taken: timeTaken 
                })
            });
            const data = await resp.json();
            window.location.href = 'quiz_results.php?id=' + data.attempt_id;
        }

        if (quizConfig.timed) globalTimerId = setInterval(updateGlobalTimer, 1000);
        loadQ();
    </script>
</body>
</html>