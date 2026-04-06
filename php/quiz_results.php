<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$attempt_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch Attempt + Quiz Details
$stmt = $pdo->prepare("
    SELECT qa.*, l.level_name, q.quiz_name 
    FROM quiz_attempts qa 
    JOIN levels l ON qa.level_id = l.level_id 
    JOIN quizzes q ON qa.quiz_id = q.quiz_id
    WHERE qa.attempt_id = ? AND qa.user_id = ?
");
$stmt->execute([$attempt_id, $_SESSION['user_id']]);
$attempt = $stmt->fetch();

if (!$attempt) { header("Location: student_dashboard.php"); exit(); }

// Decode JSON answers from memory
$answers_data = json_decode($attempt['answers_json'], true) ?: [];
$student_choices = [];
foreach($answers_data as $ans) {
    $student_choices[$ans['qid']] = $ans;
}

// Fetch only the specific questions linked to this quiz attempt
$stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ?");
$stmt->execute([$attempt['quiz_id']]);
$all_quiz_questions = $stmt->fetchAll();

$questions = [];
foreach($all_quiz_questions as $q) {
    if(isset($student_choices[$q['question_id']])) {
        // Map the stored JSON data onto the question object
        $q['chosen_option'] = $student_choices[$q['question_id']]['chosen'];
        $q['is_correct'] = $student_choices[$q['question_id']]['correct'];
        $questions[] = $q;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PolyMath - Quiz Summary</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; }
        .poly-gradient { background: linear-gradient(135deg, #46178f 0%, #1368ce 100%); }
    </style>
</head>
<body class="pb-12">
    <nav class="poly-gradient text-white p-5 shadow-xl">
        <div class="max-w-5xl mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-black italic tracking-tighter uppercase">QUIZ RESULTS</h1>
            <a href="student_dashboard.php" class="bg-white/20 hover:bg-white/30 px-6 py-2 rounded-xl text-xs font-black transition-all">BACK TO HUB</a>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto py-12 px-6">
        <div class="bg-white rounded-[3rem] shadow-2xl overflow-hidden mb-12 border-b-8 border-indigo-600">
            <div class="poly-gradient p-12 text-white text-center">
                <p class="uppercase text-[10px] font-black tracking-widest opacity-60 mb-2">Final Score</p>
                <h1 class="text-7xl font-black"><?php echo $attempt['score']; ?> / <?php echo $attempt['total_questions']; ?></h1>
                <p class="mt-4 text-xl font-bold uppercase italic tracking-tighter"><?php echo htmlspecialchars($attempt['quiz_name']); ?></p>
            </div>
            <div class="grid grid-cols-2 bg-gray-50 p-6 text-center border-t">
                <div class="border-r border-gray-200"><p class="text-[10px] font-black text-gray-400 uppercase mb-1">Rank</p><p class="font-black text-indigo-700"><?php echo $attempt['level_name']; ?></p></div>
                <div><p class="text-[10px] font-black text-gray-400 uppercase mb-1">Completion Time</p><p class="font-black text-gray-800"><?php echo floor($attempt['time_taken_seconds']/60); ?>m <?php echo $attempt['time_taken_seconds']%60; ?>s</p></div>
            </div>
        </div>

        <h2 class="text-2xl font-black text-gray-800 uppercase italic mb-8">Battle Review</h2>
        <div class="space-y-8">
            <?php foreach ($questions as $q): ?>
            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-100 transition-all hover:border-indigo-200">
                <div class="flex justify-between items-start mb-6">
                    <div class="flex-1 pr-6">
                        <p class="text-lg font-black text-gray-800"><?php echo htmlspecialchars($q['question_text']); ?></p>
                        <?php if (!empty($q['question_image'])): ?>
                            <div class="mt-4">
                                <img src="uploads/<?php echo htmlspecialchars($q['question_image']); ?>" class="max-h-64 rounded-2xl shadow-sm border-2 border-gray-100 object-contain">
                            </div>
                        <?php endif; ?>
                    </div>
                    <span class="<?php echo $q['is_correct'] ? 'text-green-500' : 'text-red-500'; ?> text-3xl font-black shrink-0">
                        <?php echo $q['is_correct'] ? '✓' : '✗'; ?>
                    </span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                    <?php foreach(['A','B','C','D'] as $o): 
                        $is_correct_opt = ($o === $q['correct_option']);
                        $is_student_opt = ($o === $q['chosen_option']);
                        
                        $box_style = "bg-gray-50 border-gray-100 text-gray-400";
                        if ($is_correct_opt) $box_style = "bg-green-100 border-green-500 text-green-700 font-bold";
                        if ($is_student_opt && !$is_correct_opt) $box_style = "bg-red-100 border-red-500 text-red-700 font-bold";
                    ?>
                        <div class="p-5 rounded-2xl border-2 transition-all <?php echo $box_style; ?>">
                            <span class="opacity-50"><?php echo $o; ?>.</span> <?php echo htmlspecialchars($q['option_'.strtolower($o)]); ?>
                            <?php if($is_student_opt) echo " <span class='text-[10px] uppercase ml-2'>(Your choice)</span>"; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="bg-indigo-50 p-6 rounded-2xl border border-indigo-100">
                    <p class="text-[10px] font-black text-indigo-400 uppercase mb-2 tracking-widest italic">Intelligence Solution</p>
                    <p class="text-sm font-bold text-gray-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($q['solution_text'])); ?></p>
                    <?php if (!empty($q['solution_image'])): ?>
                        <div class="mt-4 pt-4 border-t border-indigo-100/50">
                            <img src="uploads/<?php echo htmlspecialchars($q['solution_image']); ?>" class="max-h-64 rounded-2xl shadow-sm border border-indigo-200 object-contain">
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</body>
</html>