<?php
session_start();
require_once 'db.php';

// Access Control
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

$level_id = isset($_GET['level']) ? (int)$_GET['level'] : 0;

// Fetch Level Name
$stmt = $pdo->prepare("SELECT level_name FROM levels WHERE level_id = ?");
$stmt->execute([$level_id]);
$level_name = $stmt->fetchColumn();

if (!$level_name) {
    header("Location: student_dashboard.php");
    exit();
}

// Fetch 10 Random Questions for this level
$stmt = $pdo->prepare("SELECT question_id, question_text, option_a, option_b, option_c, option_d, correct_option, solution_text 
                       FROM questions WHERE level_id = ? ORDER BY RAND() LIMIT 10");
$stmt->execute([$level_id]);
$questions = $stmt->fetchAll();

// Convert to JSON for JS handling
$questions_json = json_encode($questions);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PolyMath - <?php echo $level_name; ?> Challenge</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; overflow-x: hidden; }
        .quiz-bg { background: #46178f; min-height: 100vh; }
        .timer-bar { transition: width 1s linear; }
        .option-btn { transition: transform 0.1s active; }
        .option-btn:active { transform: scale(0.95); }
    </style>
</head>
<body class="quiz-bg text-white">

    <!-- Header / Progress -->
    <div class="fixed top-0 left-0 w-full z-50">
        <div class="bg-black/20 backdrop-blur-md px-6 py-4 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <span class="text-xl font-bold italic tracking-tighter">PolyMath</span>
                <span class="px-3 py-1 bg-white/20 rounded-full text-xs font-bold uppercase"><?php echo $level_name; ?></span>
            </div>
            <div class="text-lg font-bold" id="question-counter">Question 1/10</div>
            <button onclick="window.location.href='student_dashboard.php'" class="text-sm opacity-70 hover:opacity-100">Exit</button>
        </div>
        <div class="h-2 w-full bg-white/10">
            <div id="progress-bar" class="h-full bg-yellow-400 transition-all duration-500" style="width: 0%"></div>
        </div>
    </div>

    <main class="pt-24 pb-12 px-6 max-w-5xl mx-auto min-h-screen flex flex-col">
        
        <!-- Question Section -->
        <div id="question-container" class="flex-grow flex flex-col items-center justify-center text-center animate-in fade-in duration-500">
            <div id="timer-display" class="w-20 h-20 rounded-full border-4 border-white/20 flex items-center justify-center text-3xl font-bold mb-8">20</div>
            <h2 id="question-text" class="text-3xl md:text-5xl font-extrabold leading-tight mb-12">Loading challenge...</h2>
        </div>

        <!-- Answers Grid -->
        <div id="options-grid" class="grid grid-cols-1 md:grid-cols-2 gap-4 h-64 md:h-80">
            <button onclick="checkAnswer('A')" class="option-btn bg-red-500 hover:bg-red-600 rounded-2xl p-6 flex items-center gap-4 text-left shadow-[0_6px_0_0_#b91c1c]">
                <span class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center font-bold">▲</span>
                <span id="opt-a" class="text-xl font-bold">Option A</span>
            </button>
            <button onclick="checkAnswer('B')" class="option-btn bg-blue-500 hover:bg-blue-600 rounded-2xl p-6 flex items-center gap-4 text-left shadow-[0_6px_0_0_#1d4ed8]">
                <span class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center font-bold">◆</span>
                <span id="opt-b" class="text-xl font-bold">Option B</span>
            </button>
            <button onclick="checkAnswer('C')" class="option-btn bg-yellow-500 hover:bg-yellow-600 rounded-2xl p-6 flex items-center gap-4 text-left shadow-[0_6px_0_0_#a16207]">
                <span class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center font-bold">●</span>
                <span id="opt-c" class="text-xl font-bold">Option C</span>
            </button>
            <button onclick="checkAnswer('D')" class="option-btn bg-green-500 hover:bg-green-600 rounded-2xl p-6 flex items-center gap-4 text-left shadow-[0_6px_0_0_#15803d]">
                <span class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center font-bold">■</span>
                <span id="opt-d" class="text-xl font-bold">Option D</span>
            </button>
        </div>

        <!-- Result Overlay (Hidden) -->
        <div id="result-overlay" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-6 bg-black/80 backdrop-blur-sm">
            <div class="bg-white text-gray-900 rounded-3xl p-10 max-w-md w-full text-center shadow-2xl">
                <div id="result-icon" class="text-6xl mb-4">✅</div>
                <h3 id="result-title" class="text-3xl font-extrabold mb-2">Correct!</h3>
                <p id="result-points" class="text-indigo-600 font-bold text-xl mb-6">+100 Points</p>
                <div class="bg-gray-50 rounded-2xl p-6 text-left mb-8 max-h-48 overflow-y-auto">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Solution</p>
                    <p id="result-explanation" class="text-sm leading-relaxed text-gray-600">...</p>
                </div>
                <button onclick="nextQuestion()" class="w-full py-4 bg-indigo-600 text-white font-bold rounded-2xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-200">
                    NEXT QUESTION
                </button>
            </div>
        </div>
    </main>

    <script>
        const questions = <?php echo $questions_json; ?>;
        const levelId = <?php echo $level_id; ?>;
        let currentIndex = 0;
        let score = 0;
        let timeLeft = 20;
        let timerId = null;
        let startTime = Date.now();

        function startTimer() {
            timeLeft = 20;
            document.getElementById('timer-display').innerText = timeLeft;
            clearInterval(timerId);
            timerId = setInterval(() => {
                timeLeft--;
                document.getElementById('timer-display').innerText = timeLeft;
                if (timeLeft <= 0) {
                    clearInterval(timerId);
                    checkAnswer(null); // Time out
                }
            }, 1000);
        }

        function loadQuestion() {
            if (currentIndex >= questions.length) {
                finishQuiz();
                return;
            }

            const q = questions[currentIndex];
            document.getElementById('question-counter').innerText = `Question ${currentIndex + 1}/${questions.length}`;
            document.getElementById('progress-bar').style.width = `${((currentIndex + 1) / questions.length) * 100}%`;
            document.getElementById('question-text').innerText = q.question_text;
            document.getElementById('opt-a').innerText = q.option_a;
            document.getElementById('opt-b').innerText = q.option_b;
            document.getElementById('opt-c').innerText = q.option_c;
            document.getElementById('opt-d').innerText = q.option_d;
            
            document.getElementById('result-overlay').classList.add('hidden');
            startTimer();
        }

        function checkAnswer(selected) {
            clearInterval(timerId);
            const q = questions[currentIndex];
            const isCorrect = (selected === q.correct_option);
            
            if (isCorrect) {
                score++;
                document.getElementById('result-icon').innerText = "✅";
                document.getElementById('result-title').innerText = "Correct!";
                document.getElementById('result-title').className = "text-3xl font-extrabold mb-2 text-green-600";
                document.getElementById('result-points').innerText = "Awesome job!";
            } else {
                document.getElementById('result-icon').innerText = "❌";
                document.getElementById('result-title').innerText = selected === null ? "Time's Up!" : "Incorrect";
                document.getElementById('result-title').className = "text-3xl font-extrabold mb-2 text-red-600";
                document.getElementById('result-points').innerText = `The correct answer was ${q.correct_option}`;
            }

            document.getElementById('result-explanation').innerText = q.solution_text || "No detailed solution provided for this question.";
            document.getElementById('result-overlay').classList.remove('hidden');
        }

        function nextQuestion() {
            currentIndex++;
            loadQuestion();
        }

        async function finishQuiz() {
            const timeTaken = Math.floor((Date.now() - startTime) / 1000);
            
            // Show loading state
            document.getElementById('question-container').innerHTML = `
                <div class="text-center">
                    <div class="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-white mx-auto mb-4"></div>
                    <p class="text-xl font-bold">Saving your score...</p>
                </div>
            `;
            document.getElementById('options-grid').style.display = 'none';

            try {
                const response = await fetch('submit_quiz.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        level_id: levelId,
                        score: score,
                        total_questions: questions.length,
                        time_taken: timeTaken
                    })
                });
                
                if (response.ok) {
                    window.location.href = 'student_dashboard.php';
                } else {
                    console.error("Failed to save score");
                    window.location.href = 'student_dashboard.php';
                }
            } catch (e) {
                window.location.href = 'student_dashboard.php';
            }
        }

        // Start the quiz
        if (questions.length > 0) {
            loadQuestion();
        } else {
            document.getElementById('question-text').innerText = "No questions found for this level yet.";
            setTimeout(() => window.location.href = 'student_dashboard.php', 3000);
        }
    </script>
</body>
</html>