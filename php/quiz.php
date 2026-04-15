<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$quiz_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM quizzes WHERE quiz_id = ?");
$stmt->execute([$quiz_id]);
$quiz = $stmt->fetch();

if (!$quiz) { header("Location: student_dashboard.php"); exit(); }

// Check strictly for attempt limits and exact session token validation
$stmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = ? AND user_id = ?");
$stmt->execute([$quiz_id, $_SESSION['user_id']]);
$attempt_count = (int)$stmt->fetchColumn();

// Eject if they completed both attempts
if ($attempt_count >= 2) { 
    header("Location: student_dashboard.php"); 
    exit(); 
}

// Eject if they are trying to access Attempt 2 (Post-Lesson) without the password token
if ($attempt_count == 1 && !isset($_SESSION['unlocked_quiz_' . $quiz_id])) { 
    header("Location: student_dashboard.php"); 
    exit(); 
}

$attempt_label = ($attempt_count == 0) ? "Pre-Lesson" : "Post-Lesson";

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
        <span class="text-xl font-black italic tracking-tighter uppercase flex items-center">
            PolyMath 
            <span class="text-[10px] font-bold text-fuchsia-300 ml-3 border border-fuchsia-300/50 bg-fuchsia-900/30 rounded-full px-3 py-1 tracking-widest"><?php echo $attempt_label; ?></span>
        </span>
        <div class="flex items-center gap-4">
            <div id="timer-display" class="hidden bg-black/30 border border-white/20 px-4 py-1.5 rounded-full font-bold text-sm tracking-widest flex items-center gap-2 transition-colors duration-500">
                ⏱️ <span id="time-left"></span>
            </div>
            <div id="q-counter" class="bg-white/20 px-4 py-1.5 rounded-full font-bold text-sm">Challenge 1/10</div>
        </div>
    </div>

    <main class="pt-32 pb-12 px-6 max-w-4xl mx-auto flex flex-col items-center">
        <div id="quiz-ui" class="w-full transition-opacity duration-500">
            <div class="text-center mb-12">
                <div id="q-img-container" class="hidden mb-8">
                    <img id="q-img" src="" class="max-h-64 mx-auto rounded-3xl shadow-2xl border-4 border-white/20 bg-white object-contain">
                </div>
                <h2 id="q-text" class="text-2xl md:text-4xl font-extrabold leading-tight"></h2>
            </div>

            <div id="options" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach(['A','B','C','D'] as $o): ?>
                    <button id="btn-opt-<?php echo $o; ?>" onclick="handleAnswer('<?php echo $o; ?>')" class="p-6 md:p-8 rounded-[2rem] text-left font-bold text-xl md:text-2xl transition-all active:scale-95 border-2 group">
                        <span class="bg-white/20 px-4 py-2 rounded-xl mr-4 group-hover:bg-white/30 transition-all"><?php echo $o; ?></span>
                        <span id="opt-<?php echo strtolower($o); ?>"></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Quiz Navigation -->
            <div class="mt-12 flex justify-between items-center w-full border-t border-white/10 pt-8">
                <button onclick="prevStep()" id="btn-prev" class="px-6 py-4 md:px-8 rounded-2xl bg-white/10 hover:bg-white/20 font-black transition-all disabled:opacity-30 disabled:cursor-not-allowed uppercase text-sm md:text-base">Previous</button>
                <div class="flex gap-4">
                    <button onclick="nextStep()" id="btn-next" class="px-6 py-4 md:px-8 rounded-2xl bg-white/20 hover:bg-white/30 font-black transition-all uppercase text-sm md:text-base">Next</button>
                    <button onclick="showReviewModal()" id="btn-submit" class="hidden px-6 py-4 md:px-8 rounded-2xl bg-green-500 hover:bg-green-600 font-black shadow-xl transition-all uppercase text-sm md:text-base">Complete Quiz</button>
                </div>
            </div>
        </div>

        <!-- Review Modal -->
        <div id="review-modal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-6 bg-black/90 backdrop-blur-md">
            <div class="bg-white text-gray-900 rounded-[3rem] p-8 md:p-10 max-w-lg w-full text-center shadow-2xl border-b-8 border-indigo-600 relative">
                <button onclick="closeReviewModal()" class="absolute top-6 right-6 text-gray-400 hover:text-gray-800 font-black text-2xl">&times;</button>
                <h3 class="text-3xl font-black mb-2 uppercase italic text-[#46178f]">Mission Review</h3>
                <p id="review-msg" class="text-sm font-bold mb-6"></p>
                
                <div id="review-grid" class="grid grid-cols-5 gap-3 mb-8">
                    <!-- JS will populate this -->
                </div>
                
                <div class="flex flex-col sm:flex-row gap-4">
                    <button onclick="closeReviewModal()" class="w-full py-4 bg-gray-200 hover:bg-gray-300 text-gray-700 font-black rounded-2xl shadow-sm transition-all uppercase tracking-widest text-sm">Return</button>
                    <button onclick="finish()" class="w-full py-4 bg-green-500 hover:bg-green-600 text-white font-black rounded-2xl shadow-xl transition-all uppercase tracking-widest text-sm">Submit Now</button>
                </div>
            </div>
        </div>
    </main>

    <script>
        const questions = <?php echo $questions_json; ?>;
        const qid = <?php echo $quiz_id; ?>;
        
        // Timer Configurations extracted from DB
        const quizConfig = <?php echo json_encode(['is_timed' => $quiz['is_timed'] ? true : false, 'time_limit' => (int)$quiz['time_limit']]); ?>;
        
        let idx = 0, startTime = Date.now();
        let userChoices = new Array(questions.length).fill(null);
        let timerInterval = null;
        let timeLeft = quizConfig.time_limit;
        let isFinishing = false; // Prevent double submission

        function loadQ() {
            if (idx >= questions.length) return;
            const q = questions[idx];
            document.getElementById('q-counter').innerText = `Challenge ${idx+1}/${questions.length}`;
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
            
            // Visual Updates for selected options
            ['A','B','C','D'].forEach(opt => {
                const btn = document.getElementById('btn-opt-' + opt);
                if (userChoices[idx] && userChoices[idx].chosen === opt) {
                    btn.classList.add('bg-white/30', 'border-white', 'ring-4', 'ring-white/50');
                    btn.classList.remove('bg-white/10', 'border-white/5');
                } else {
                    btn.classList.remove('bg-white/30', 'border-white', 'ring-4', 'ring-white/50');
                    btn.classList.add('bg-white/10', 'border-white/5');
                }
            });

            // Update Navigation buttons
            document.getElementById('btn-prev').disabled = (idx === 0);
            
            if (idx === questions.length - 1) {
                document.getElementById('btn-next').classList.add('hidden');
                document.getElementById('btn-submit').classList.remove('hidden');
            } else {
                document.getElementById('btn-next').classList.remove('hidden');
                document.getElementById('btn-submit').classList.add('hidden');
            }
        }

        function handleAnswer(choice) {
            const q = questions[idx];
            const correct = (choice === q.correct_option);
            userChoices[idx] = { qid: q.question_id, correct: correct, chosen: choice };
            loadQ(); // Re-render to show selection highlight
        }

        function nextStep() {
            if (idx < questions.length - 1) { idx++; loadQ(); }
        }

        function prevStep() {
            if (idx > 0) { idx--; loadQ(); }
        }

        function showReviewModal() {
            const grid = document.getElementById('review-grid');
            grid.innerHTML = '';
            let allAnswered = true;

            for(let i = 0; i < questions.length; i++) {
                const isAnswered = userChoices[i] !== null;
                if(!isAnswered) allAnswered = false;

                const box = document.createElement('button');
                // Clicking a box takes the user directly back to that question
                box.onclick = () => { closeReviewModal(); idx = i; loadQ(); };
                box.className = `p-3 rounded-xl font-black text-sm transition-all flex flex-col items-center justify-center active:scale-95 ${isAnswered ? 'bg-green-100 text-green-700 border-2 border-green-500 hover:bg-green-200' : 'bg-red-50 text-red-500 border-2 border-red-300 border-dashed hover:bg-red-100'}`;
                box.innerHTML = `<span>${i + 1}</span>`;
                grid.appendChild(box);
            }

            const msg = document.getElementById('review-msg');
            if(allAnswered) {
                msg.innerText = "All challenges answered! Ready to submit?";
                msg.className = "text-green-600 font-bold mb-6 uppercase tracking-widest text-xs";
            } else {
                msg.innerText = "Warning: You have unanswered challenges!";
                msg.className = "text-red-500 font-bold mb-6 uppercase tracking-widest text-xs animate-pulse";
            }

            document.getElementById('review-modal').classList.remove('hidden');
        }

        function closeReviewModal() {
            document.getElementById('review-modal').classList.add('hidden');
        }

        async function finish() {
            if (isFinishing) return;
            isFinishing = true;
            
            if (timerInterval) clearInterval(timerInterval);
            
            // Lock UI
            document.getElementById('quiz-ui').classList.add('opacity-50', 'pointer-events-none');
            document.getElementById('review-modal').classList.add('hidden');

            // Calculate score and build final payload from userChoices array
            let finalScore = 0;
            let submittedAnswers = [];
            
            for (let i = 0; i < questions.length; i++) {
                if (userChoices[i]) {
                    if (userChoices[i].correct) finalScore++;
                    submittedAnswers.push(userChoices[i]);
                }
            }

            const time = Math.floor((Date.now() - startTime) / 1000);
            const finalTime = (quizConfig.is_timed && time > quizConfig.time_limit) ? quizConfig.time_limit : time;

            try {
                const r = await fetch('submit_quiz.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        quiz_id: qid, 
                        score: finalScore, 
                        total_questions: questions.length, 
                        time_taken: finalTime,
                        answers: submittedAnswers 
                    })
                });
                const d = await r.json();
                window.location.href = d.status === 'success' ? 'quiz_results.php?id=' + d.attempt_id : 'student_dashboard.php';
            } catch (e) { window.location.href = 'student_dashboard.php'; }
        }

        function updateTimerDisplay() {
            let m = Math.floor(timeLeft / 60).toString().padStart(2, '0');
            let s = (timeLeft % 60).toString().padStart(2, '0');
            document.getElementById('time-left').innerText = `${m}:${s}`;
            
            // Pulse Red when time is critically low (30 seconds or less)
            if (timeLeft <= 30) {
                const tDisp = document.getElementById('timer-display');
                tDisp.classList.remove('bg-black/30', 'border-white/20');
                tDisp.classList.add('bg-red-600', 'border-red-500', 'animate-pulse');
            }
        }

        // Initialize Quiz
        loadQ();

        // Initialize Timer
        if (quizConfig.is_timed && quizConfig.time_limit > 0) {
            document.getElementById('timer-display').classList.remove('hidden');
            updateTimerDisplay();
            
            timerInterval = setInterval(() => {
                timeLeft--;
                updateTimerDisplay();
                
                if (timeLeft <= 0) {
                    clearInterval(timerInterval);
                    finish(); // Auto-submit when time is up
                }
            }, 1000);
        }
    </script>
</body>
</html>