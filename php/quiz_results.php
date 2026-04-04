<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$attempt_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("
    SELECT qa.*, l.level_name 
    FROM quiz_attempts qa 
    JOIN levels l ON qa.level_id = l.level_id 
    WHERE qa.attempt_id = ?
");
$stmt->execute([$attempt_id]);
$attempt = $stmt->fetch();

if (!$attempt) { header("Location: student_dashboard.php"); exit(); }

// Simple logic to show questions from that level for review
$stmt = $pdo->prepare("SELECT * FROM questions WHERE level_id = ?");
$stmt->execute([$attempt['level_id']]);
$questions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PolyMath - Results</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .poly-gradient { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <nav class="poly-gradient text-white p-4 shadow-lg">
        <div class="max-w-4xl mx-auto flex justify-between items-center">
            <span class="font-bold text-xl">Results</span>
            <a href="student_dashboard.php" class="text-sm font-semibold hover:underline">Dashboard</a>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto py-10 px-6">
        <div class="bg-white rounded-3xl shadow-xl overflow-hidden mb-10">
            <div class="poly-gradient p-10 text-white text-center">
                <p class="uppercase text-xs font-bold opacity-80 mb-2">Final Score</p>
                <h1 class="text-6xl font-black"><?php echo $attempt['score']; ?> / <?php echo $attempt['total_questions']; ?></h1>
            </div>
            <div class="p-6 grid grid-cols-2 text-center bg-gray-50">
                <div>
                    <p class="text-xs font-bold text-gray-400">Level</p>
                    <p class="font-bold"><?php echo $attempt['level_name']; ?></p>
                </div>
                <div>
                    <p class="text-xs font-bold text-gray-400">Time</p>
                    <p class="font-bold"><?php echo floor($attempt['time_taken_seconds']/60); ?>m <?php echo $attempt['time_taken_seconds']%60; ?>s</p>
                </div>
            </div>
        </div>

        <h2 class="text-2xl font-bold mb-6">Question Review</h2>
        <div class="space-y-6">
            <?php foreach ($questions as $q): ?>
            <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
                <p class="text-lg font-bold mb-4"><?php echo htmlspecialchars($q['question_text']); ?></p>
                
                <?php if ($q['question_image']): ?>
                    <img src="uploads/<?php echo $q['question_image']; ?>" class="max-w-full h-auto rounded-xl mb-4 border border-gray-100">
                <?php endif; ?>

                <div class="grid grid-cols-2 gap-2 mb-6">
                    <?php foreach(['A','B','C','D'] as $o): 
                        $is_correct = ($o === $q['correct_option']);
                    ?>
                        <div class="p-3 rounded-xl border <?php echo $is_correct ? 'bg-green-50 border-green-200 text-green-700 font-bold' : 'bg-gray-50 border-gray-100 text-gray-400'; ?>">
                            <?php echo $o; ?>. <?php echo htmlspecialchars($q['option_'.strtolower($o)]); ?>
                            <?php if($is_correct) echo " ✓"; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="bg-indigo-50 p-6 rounded-xl border border-indigo-100">
                    <p class="text-xs font-bold text-indigo-400 uppercase mb-2">Step-by-step Solution</p>
                    <p class="text-sm text-gray-700"><?php echo nl2br(htmlspecialchars($q['solution_text'])); ?></p>
                    <?php if ($q['solution_image']): ?>
                        <img src="uploads/<?php echo $q['solution_image']; ?>" class="mt-4 max-w-full h-auto rounded-lg shadow-sm">
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</body>
</html>