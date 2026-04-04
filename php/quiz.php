<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$quiz_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM quizzes WHERE quiz_id = ?");
$stmt->execute([$quiz_id]);
$quiz = $stmt->fetch();

if (!$quiz) { header("Location: student_dashboard.php"); exit(); }

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
    <title>Mission - PolyMath</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #46178f; color: white; }
        .poly-gradient { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    </style>
</head>
<body class="min-h-screen">
    <div class="fixed top-0 w-full z-50 bg-black/20 backdrop-blur-lg px-8 py-4 flex justify-between items-center border-b border-white/10">
        <span class="text-xl font-black italic tracking-tighter uppercase">PolyMath</span>
        <div id="q-counter" class="bg-white/20 px-4 py-1 rounded-full font-bold text-sm">Challenge 1/10</div>
    </div>

    <main class="pt-32 pb-12 px-6 max-w-4xl mx-auto flex flex-col items-center">
        <div id="quiz-ui" class="w-full">
            <div class="text-center mb-12">
                <div id="q-img-container" class="hidden mb-8">
                    <img id="q-img" src="" class="max-h-64 mx-auto rounded-3xl shadow-2xl border-4 border-white/20">
                </div>
                <h2 id="q-text" class="text-4xl font-extrabold leading-tight"></h2>
            </div>

            <div id="options" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach(['A','B','C','D'] as $o): ?>
                    <button onclick="handleAnswer('<?php echo $o; ?>')" class="p-8 rounded-[2rem] text-left font-bold text-2xl transition-all active:scale-95 bg-white/10 hover:bg-white/20 border-2 border-white/5 group">
                        <span class="bg-white/20 px-4 py-2 rounded-xl mr-4 group-hover:bg-white/30"><?php echo $o; ?></span>
                        <span id="opt-<?php echo strtolower($o); ?>"></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Feedback Overlay -->
        <div id="res-overlay" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-6 bg-black/90 backdrop-blur-md">
            <div class="bg-white text-gray-900 rounded-[3rem] p-10 max-w-md w-full text-center shadow-2xl border-b-8 border-indigo-600">
                <div id="res-icon" class="text-7xl mb-6"></div>
                <h3 id="res-title" class="text-4xl font-black mb-4"></h3>
                <div class="bg-gray-50 rounded-[2rem] p-6 text-left mb-8 border border-gray-100">
                    <p class="text-[10px] font-black text-gray-400 uppercase mb-2 tracking-widest text-center">Intelligence Report</p>
                    <p id="res-sol" class="text-sm text-gray-600 leading-relaxed font-bold"></p>
                </div>
                <button onclick="nextStep()" class="w-full py-5 poly-gradient text-white font-black rounded-2xl shadow-xl hover:opacity-90 transition-all uppercase tracking-widest">CONTINUE MISSION</button>
            </div>
        </div>
    </main>

    <script>
        const questions = <?php echo $questions_json; ?>;
        const qid = <?php echo $quiz_id; ?>;
        let idx = 0, score = 0, startTime = Date.now();
        let userChoices = [];

        function loadQ() {
            if (idx >= questions.length) { finish(); return; }
            const q = questions[idx];
            document.getElementById('q-counter').innerText = `Challenge ${idx+1}/10`;
            document.getElementById('q-text').innerText = q.question_text;
            document.getElementById('opt-a').innerText = q.option_a;
            document.getElementById('opt-b').innerText = q.option_b;
            document.getElementById('opt-c').innerText = q.option_c;
            document.getElementById('opt-d').innerText = q.option_d;
            
            const img = document.getElementById('q-img-container');
            if (q.question_image) {
                img.classList.remove('hidden');
                document.getElementById('q-img').src = 'uploads/' + q.question_image;
            } else { img.classList.add('hidden'); }
            document.getElementById('res-overlay').classList.add('hidden');
        }

        function handleAnswer(choice) {
            const q = questions[idx];
            const correct = (choice === q.correct_option);
            if (correct) score++;
            
            userChoices.push({ qid: q.question_id, correct: correct, chosen: choice });
            
            document.getElementById('res-icon').innerText = correct ? "🌟" : "⚠️";
            document.getElementById('res-title').innerText = correct ? "Correct!" : "Incorrect";
            document.getElementById('res-sol').innerText = q.solution_text || "The correct answer is " + q.correct_option;
            document.getElementById('res-overlay').classList.remove('hidden');
        }

        function nextStep() { idx++; loadQ(); }

        async function finish() {
            const time = Math.floor((Date.now() - startTime) / 1000);
            try {
                const r = await fetch('submit_quiz.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        quiz_id: qid, 
                        score: score, 
                        total_questions: questions.length, 
                        time_taken: time,
                        answers: userChoices 
                    })
                });
                const d = await r.json();
                window.location.href = d.status === 'success' ? 'quiz_results.php?id=' + d.attempt_id : 'student_dashboard.php';
            } catch (e) { window.location.href = 'student_dashboard.php'; }
        }

        loadQ();
    </script>
</body>
</html>