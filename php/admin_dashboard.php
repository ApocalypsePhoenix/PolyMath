<?php
require_once 'db.php';

// Access Control
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$message = ""; $messageType = "";

/**
 * Image Processor
 */
function pm_upload($base64, $existing = null) {
    if (empty($base64) || !str_contains($base64, ',')) return $existing;
    $data = explode(',', $base64);
    $imgData = base64_decode($data[1]);
    $name = uniqid('asset_', true) . '.png';
    // Directory relative to this file (php/uploads)
    if (!is_dir('uploads')) mkdir('uploads', 0777, true);
    file_put_contents('uploads/' . $name, $imgData);
    return $name;
}

// --- FULL CRUD LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add_class') {
            $pdo->prepare("INSERT INTO classes (class_name) VALUES (?)")->execute([trim($_POST['new_class_name'])]);
            $message = "Group created!";
        } elseif ($action === 'edit_class') {
            $pdo->prepare("UPDATE classes SET class_name=? WHERE class_id=?")->execute([trim($_POST['class_name']), $_POST['class_id']]);
            $message = "Group updated!";
        } elseif ($action === 'delete_class') {
            $pdo->prepare("DELETE FROM classes WHERE class_id = ?")->execute([$_POST['class_id']]);
            $message = "Group deleted!";
        } elseif ($action === 'create_quiz' || $action === 'edit_quiz') {
            if ($action === 'create_quiz') {
                $pdo->prepare("INSERT INTO quizzes (quiz_name, level_id, is_timed, time_limit, is_published) VALUES (?, ?, ?, ?, 0)")
                    ->execute([trim($_POST['quiz_name']), $_POST['level_id'], isset($_POST['is_timed'])?1:0, (int)$_POST['time_limit']]);
                $message = "Quiz Created!";
            } else {
                $pdo->prepare("UPDATE quizzes SET quiz_name=?, level_id=?, is_timed=?, time_limit=? WHERE quiz_id=?")
                    ->execute([trim($_POST['quiz_name']), $_POST['level_id'], isset($_POST['is_timed'])?1:0, (int)$_POST['time_limit'], $_POST['quiz_id']]);
                $message = "Quiz Updated!";
            }
        } elseif ($action === 'toggle_publish') {
            $status = (int)$_POST['publish_status'];
            $id = $_POST['quiz_id'];
            if ($status == 1) {
                $cnt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE quiz_id = ?");
                $cnt->execute([$id]);
                if ($cnt->fetchColumn() < 10) throw new Exception("Quizzes need 10 questions to go LIVE!");
            }
            $pdo->prepare("UPDATE quizzes SET is_published = ? WHERE quiz_id = ?")->execute([$status, $id]);
            $message = $status ? "QUIZ LIVE! 🚀" : "Saved as Draft.";
        } elseif ($action === 'add_question' || $action === 'edit_question') {
            $qid = $_POST['quiz_id'];
            
            if ($action === 'add_question') {
                $q_img = pm_upload($_POST['cropped_question_image'] ?? '');
                $s_img = pm_upload($_POST['cropped_solution_image'] ?? '');
                
                $st = $pdo->prepare("SELECT level_id FROM quizzes WHERE quiz_id=?");
                $st->execute([$qid]); $lvl = $st->fetchColumn();
                $sql = "INSERT INTO questions (quiz_id, level_id, question_text, option_a, option_b, option_c, option_d, correct_option, solution_text, question_image, solution_image, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
                $pdo->prepare($sql)->execute([$qid, $lvl, trim($_POST['question_text']), $_POST['option_a'], $_POST['option_b'], $_POST['option_c'], $_POST['option_d'], $_POST['correct_option'], $_POST['solution_text'], $q_img, $s_img, $_SESSION['user_id']]);
            } else {
                // Fetch existing images so we don't overwrite them if no new image is provided
                $st = $pdo->prepare("SELECT question_image, solution_image FROM questions WHERE question_id=?");
                $st->execute([$_POST['question_id']]);
                $existing = $st->fetch();
                
                $q_img = pm_upload($_POST['cropped_question_image'] ?? '', $existing['question_image']);
                $s_img = pm_upload($_POST['cropped_solution_image'] ?? '', $existing['solution_image']);

                $pdo->prepare("UPDATE questions SET question_text=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_option=?, solution_text=?, question_image=?, solution_image=? WHERE question_id=?")
                    ->execute([trim($_POST['question_text']), $_POST['option_a'], $_POST['option_b'], $_POST['option_c'], $_POST['option_d'], $_POST['correct_option'], $_POST['solution_text'], $q_img, $s_img, $_POST['question_id']]);
            }
            $message = "Question saved!";
        } elseif ($action === 'delete_quiz') {
            $pdo->prepare("DELETE FROM quizzes WHERE quiz_id = ?")->execute([$_POST['quiz_id']]);
            $message = "Quiz Deleted!";
        } elseif ($action === 'delete_question') {
            $pdo->prepare("DELETE FROM questions WHERE question_id = ?")->execute([$_POST['question_id']]);
            $message = "Question Deleted!";
        }

        $_SESSION['admin_msg'] = $message;
        $url = "admin_dashboard.php" . (isset($qid) ? "?managed_quiz=".$qid : "");
        header("Location: " . $url); exit();
    } catch (Exception $e) { $message = "Error: " . $e->getMessage(); $messageType = "error"; }
}

if (isset($_SESSION['admin_msg'])) { $message = $_SESSION['admin_msg']; $messageType = "success"; unset($_SESSION['admin_msg']); }

// --- DATA FETCHING ---
$levels = $pdo->query("SELECT * FROM levels ORDER BY difficulty_rank ASC")->fetchAll();
$classes = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM users u WHERE u.class_id = c.class_id AND u.role = 'student') as student_count FROM classes c ORDER BY class_name ASC")->fetchAll();
$quizzes = $pdo->query("SELECT q.*, l.level_name, l.difficulty_rank, (SELECT COUNT(*) FROM questions qn WHERE qn.quiz_id = q.quiz_id) as q_count FROM quizzes q JOIN levels l ON q.level_id = l.level_id ORDER BY q.created_at DESC")->fetchAll();

// --- INTELLIGENCE HUB DATA PREP ---
// 1. Map Questions
$questions_raw = $pdo->query("SELECT question_id, quiz_id, question_text, question_image, solution_image FROM questions")->fetchAll();
$questions_by_quiz = [];
foreach($questions_raw as $q) { $questions_by_quiz[$q['quiz_id']][] = $q; }

// 2. Map Quizzes
$quiz_info = [];
foreach($quizzes as $q) { $quiz_info[$q['quiz_id']] = $q; }

// 3. Get best attempt per user per quiz (Highest Score Filter)
$attempts_raw = $pdo->query("
    SELECT qa.attempt_id, qa.user_id, qa.quiz_id, qa.score, qa.answers_json, u.class_id 
    FROM quiz_attempts qa
    JOIN users u ON qa.user_id = u.user_id
    WHERE qa.answers_json IS NOT NULL AND u.role = 'student'
")->fetchAll();

$best_attempts = []; // Structure: [class_id][quiz_id][user_id] = highest_attempt
foreach($attempts_raw as $att) {
    $cid = $att['class_id'];
    $qid = $att['quiz_id'];
    $uid = $att['user_id'];
    
    if(!isset($best_attempts[$cid][$qid][$uid])) {
        $best_attempts[$cid][$qid][$uid] = $att;
    } else {
        // If this retake has a higher score, overwrite it
        if($att['score'] > $best_attempts[$cid][$qid][$uid]['score']) {
            $best_attempts[$cid][$qid][$uid] = $att;
        }
    }
}

// 4. Build deeply nested JSON array for JS frontend
$intelligence_data = [];
foreach($classes as $cls) {
    $cid = $cls['class_id'];
    $class_node = [
        'class_id' => $cid,
        'class_name' => $cls['class_name'],
        'student_count' => $cls['student_count'],
        'quizzes' => []
    ];
    
    if(isset($best_attempts[$cid])) {
        $class_quizzes = [];
        foreach($best_attempts[$cid] as $qid => $user_attempts) {
            if(!isset($quiz_info[$qid])) continue;
            
            $quiz_node = [
                'quiz_id' => $qid,
                'quiz_name' => $quiz_info[$qid]['quiz_name'],
                'level_name' => $quiz_info[$qid]['level_name'],
                'difficulty_rank' => $quiz_info[$qid]['difficulty_rank'],
                'participation' => count($user_attempts),
                'questions' => []
            ];
            
            $q_stats = [];
            if(isset($questions_by_quiz[$qid])) {
                foreach($questions_by_quiz[$qid] as $q) {
                    $q_stats[$q['question_id']] = [
                        'question_id' => $q['question_id'],
                        'question_text' => $q['question_text'],
                        'question_image' => $q['question_image'],
                        'solution_image' => $q['solution_image'],
                        'attempts' => 0,
                        'correct' => 0
                    ];
                }
            }

            // Tally up highest score JSON arrays
            foreach($user_attempts as $uid => $att) {
                $answers = json_decode($att['answers_json'], true) ?: [];
                foreach($answers as $ans) {
                    $ans_qid = $ans['qid'];
                    if(isset($q_stats[$ans_qid])) {
                        $q_stats[$ans_qid]['attempts']++;
                        if($ans['correct']) $q_stats[$ans_qid]['correct']++;
                    }
                }
            }
            
            foreach($q_stats as $qs) {
                $qs['accuracy'] = ($qs['attempts'] > 0) ? round(($qs['correct'] / $qs['attempts']) * 100) : 0;
                $quiz_node['questions'][] = $qs;
            }
            $class_quizzes[] = $quiz_node;
        }
        
        // Sort quizzes Easy -> Intermediate -> Expert
        usort($class_quizzes, function($a, $b) {
            return $a['difficulty_rank'] <=> $b['difficulty_rank'];
        });
        
        $class_node['quizzes'] = $class_quizzes;
    }
    $intelligence_data[] = $class_node;
}
$intel_json = json_encode($intelligence_data);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PolyMath Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f0f2f5; }
        .kahoot-purple { background-color: #46178f; }
        .kahoot-blue { background-color: #1368ce; }
        .kahoot-yellow { background-color: #ffa602; }
        .tab-btn.active { background-color: white; color: #46178f; box-shadow: 0 4px 0 0 #46178f; }
        .fade-out { transition: opacity 0.5s ease-out; opacity: 0; pointer-events: none; }
        #cropModal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 1000; align-items: center; justify-content: center; padding: 20px; }
    </style>
</head>
<body class="pb-12">

    <div id="cropModal">
        <div class="bg-white rounded-[2rem] p-8 w-full max-w-2xl flex flex-col max-h-[95vh]">
            <h3 class="text-2xl font-black mb-4 text-[#46178f] shrink-0">Crop Asset</h3>
            <div class="w-full h-[50vh] mb-6 bg-gray-100 rounded-2xl overflow-hidden relative">
                <img id="cropTarget" src="" class="block max-w-full max-h-full mx-auto">
            </div>
            <div class="flex justify-end gap-4 shrink-0"><button onclick="closeCrop()" class="px-8 py-3 rounded-2xl bg-gray-200 font-bold">Cancel</button><button onclick="saveCrop()" class="px-8 py-3 rounded-2xl kahoot-purple text-white font-bold">Apply</button></div>
        </div>
    </div>

    <nav class="kahoot-purple text-white shadow-xl p-5 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto flex justify-between items-center"><h1 class="text-2xl font-black italic tracking-tighter uppercase">PolyMath <span class="font-light text-fuchsia-300">Command</span></h1><a href="logout.php" class="bg-red-500 hover:bg-red-600 px-6 py-2.5 rounded-xl text-sm font-black transition-all">LOGOUT</a></div>
    </nav>

    <main class="max-w-7xl mx-auto p-6 md:p-10">
        <?php if ($message): ?>
            <div id="toast" class="mb-8 p-5 rounded-[1.5rem] text-sm font-bold <?php echo $messageType === 'error' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?> border-2 shadow-sm transition-opacity duration-500"><?php echo $message; ?></div>
            <script>setTimeout(() => { const el = document.getElementById('toast'); if(el) { el.classList.add('fade-out'); setTimeout(() => el.remove(), 500); } }, 20000);</script>
        <?php endif; ?>

        <div class="flex bg-gray-200 p-2 rounded-2xl mb-10 w-fit mx-auto gap-2">
            <button onclick="showTab('quizzes')" class="tab-btn px-8 py-3 rounded-xl font-black text-sm active" id="tab-quizzes">QUIZZES</button>
            <button onclick="showTab('classes')" class="tab-btn px-8 py-3 rounded-xl font-black text-sm" id="tab-classes">CLASSES</button>
            <button onclick="showTab('analytics')" class="tab-btn px-8 py-3 rounded-xl font-black text-sm" id="tab-analytics">INTELLIGENCE REPORT</button>
        </div>

        <!-- Intelligence Section -->
        <section id="sect-analytics" class="tab-content hidden space-y-12">
            <div class="bg-white rounded-[3rem] p-12 shadow-2xl border-4 border-indigo-50" id="intel-container">
                <!-- JS will inject drill-down views here -->
            </div>
        </section>

        <!-- Quizzes Section -->
        <section id="sect-quizzes" class="tab-content space-y-12">
            <div id="list-view" class="grid grid-cols-1 lg:grid-cols-3 gap-10">
                <div class="bg-white rounded-[2rem] p-8 shadow-xl border-b-8 border-indigo-600 h-fit">
                    <h2 class="text-2xl font-black mb-6 text-indigo-700" id="quiz-form-title">Quiz Settings</h2>
                    <form action="admin_dashboard.php" method="POST" class="space-y-5">
                        <input type="hidden" name="action" value="create_quiz" id="quiz-action">
                        <input type="hidden" name="quiz_id" id="quiz-id">
                        <input type="text" name="quiz_name" id="quiz-name" required placeholder="Quiz Name" class="w-full px-5 py-3 rounded-2xl border-2 font-bold outline-none focus:border-indigo-500">
                        <select name="level_id" id="quiz-level" class="w-full px-5 py-3 rounded-2xl border-2 font-bold">
                            <?php foreach($levels as $l): ?><option value="<?php echo $l['level_id']; ?>"><?php echo $l['level_name']; ?></option><?php endforeach; ?>
                        </select>
                        <div class="p-4 bg-indigo-50 rounded-2xl border-2">
                            <label class="flex items-center gap-3 cursor-pointer"><input type="checkbox" name="is_timed" id="quiz-timed" class="w-5 h-5 accent-indigo-600" onchange="document.getElementById('limit-box').classList.toggle('hidden', !this.checked)"><span class="text-sm font-black">Timed</span></label>
                            <div id="limit-box" class="hidden mt-4"><input type="number" name="time_limit" id="quiz-limit" value="300" class="w-full px-4 py-2 rounded-xl border-2 font-bold"></div>
                        </div>
                        <button type="submit" class="w-full kahoot-blue text-white py-4 rounded-2xl font-black shadow-lg">SAVE QUIZ</button>
                    </form>
                </div>

                <div class="lg:col-span-2 bg-white rounded-[2rem] shadow-xl border-b-8 border-purple-700 overflow-hidden">
                    <table class="w-full text-left">
                        <thead><tr class="bg-gray-50 text-[10px] font-black uppercase text-gray-400"><th class="px-8 py-5">Quiz</th><th class="px-8 py-5 text-center">Qs</th><th class="px-8 py-5">Status</th><th class="px-8 py-5 text-right">Actions</th></tr></thead>
                        <tbody class="divide-y">
                            <?php foreach($quizzes as $q): ?>
                            <tr class="hover:bg-gray-50 transition-all">
                                <td class="px-8 py-6 font-black text-gray-800"><?php echo htmlspecialchars($q['quiz_name']); ?></td>
                                <td class="px-8 py-6 text-center font-bold text-gray-400"><?php echo $q['q_count']; ?>/10</td>
                                <td class="px-8 py-6"><?php echo ($q['is_published']) ? '<span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-tighter">LIVE 🚀</span>' : '<span class="bg-amber-100 text-amber-700 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-tighter">DRAFT</span>'; ?></td>
                                <td class="px-8 py-6 text-right space-x-3">
                                    <button onclick='editQuiz(<?php echo json_encode($q); ?>)' class="text-indigo-600 font-black text-xs uppercase">Edit</button>
                                    <button onclick="manageQuestions(<?php echo $q['quiz_id']; ?>, '<?php echo addslashes($q['quiz_name']); ?>', <?php echo $q['q_count']; ?>, <?php echo $q['is_published']; ?>)" class="kahoot-purple text-white px-5 py-2.5 rounded-xl text-xs font-black shadow-md transition-all active:scale-95">BUILD</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Builder Hub -->
            <div id="builder-hub" class="hidden bg-white rounded-[3rem] p-10 shadow-2xl border-4 border-[#46178f]">
                <div class="flex justify-between items-center mb-10 pb-6 border-b-2">
                    <h2 class="text-3xl font-black text-gray-800">Quiz Builder: <span id="managed-name" class="text-[#46178f]"></span></h2>
                    <div class="flex gap-4">
                        <form action="admin_dashboard.php" method="POST">
                            <input type="hidden" name="action" value="toggle_publish"><input type="hidden" name="quiz_id" id="pub-id"><input type="hidden" name="publish_status" id="pub-status">
                            <button type="submit" id="pub-btn" class="hidden px-8 py-4 rounded-2xl font-black text-white shadow-xl transition-all uppercase"></button>
                        </form>
                        <button onclick="document.getElementById('builder-hub').classList.add('hidden')" class="kahoot-yellow text-white px-8 py-4 rounded-2xl font-black shadow-xl uppercase transition-all">SAVE AS DRAFT</button>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-12">
                    <form action="admin_dashboard.php" id="q-form" method="POST" class="space-y-6 bg-gray-50 p-8 rounded-[2rem] border-2">
                        <input type="hidden" name="action" value="add_question" id="q-action"><input type="hidden" name="quiz_id" id="q-quiz-id"><input type="hidden" name="question_id" id="q-id">
                        <input type="hidden" name="cropped_question_image" id="q-img-val"><input type="hidden" name="cropped_solution_image" id="s-img-val">
                        <textarea name="question_text" id="q-text" placeholder="Quiz question..." required class="w-full px-5 py-4 rounded-2xl border-2 font-bold h-32 focus:border-indigo-500 outline-none"></textarea>
                        <div class="grid grid-cols-2 gap-3">
                            <input type="text" name="option_a" id="q-a" placeholder="A" required class="px-5 py-3 rounded-xl border-2 font-bold text-red-600">
                            <input type="text" name="option_b" id="q-b" placeholder="B" required class="px-5 py-3 rounded-xl border-2 font-bold text-blue-600">
                            <input type="text" name="option_c" id="q-c" placeholder="C" required class="px-5 py-3 rounded-xl border-2 font-bold text-amber-500">
                            <input type="text" name="option_d" id="q-d" placeholder="D" required class="px-5 py-3 rounded-xl border-2 font-bold text-green-600">
                        </div>
                        <select name="correct_option" id="q-correct" class="w-full px-5 py-3 rounded-2xl border-2 font-black text-indigo-700 bg-white">
                            <option value="A">Answer A</option><option value="B">Answer B</option><option value="C">Answer C</option><option value="D">Answer D</option>
                        </select>
                        <div class="grid grid-cols-2 gap-4">
                            <button type="button" onclick="document.getElementById('f-q').click()" class="p-4 bg-white rounded-2xl border-2 border-dashed flex flex-col items-center"><span class="text-[9px] font-black opacity-30 uppercase">Q Image</span><input type="file" id="f-q" class="hidden" onchange="initCrop(this, 'q')"><div id="p-q" class="h-10 mt-2 hidden"><img src="" class="h-full"></div></button>
                            <button type="button" onclick="document.getElementById('f-s').click()" class="p-4 bg-white rounded-2xl border-2 border-dashed flex flex-col items-center"><span class="text-[9px] font-black opacity-30 uppercase">S Image</span><input type="file" id="f-s" class="hidden" onchange="initCrop(this, 's')"><div id="p-s" class="h-10 mt-2 hidden"><img src="" class="h-full"></div></button>
                        </div>
                        <textarea name="solution_text" id="q-sol" placeholder="Intelligence solution..." class="w-full px-5 py-4 rounded-2xl border-2 font-bold h-24"></textarea>
                        <button type="submit" class="w-full kahoot-purple text-white py-4 rounded-2xl font-black shadow-xl">UPLOAD QUIZ DATA</button>
                    </form>
                    <div class="xl:col-span-2 space-y-4 overflow-y-auto max-h-[800px] pr-4" id="questions-list"></div>
                </div>
            </div>
        </section>

        <!-- Classes Section -->
        <section id="sect-classes" class="tab-content hidden space-y-10">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
                <div class="bg-white rounded-[2.5rem] p-8 shadow-xl border-b-8 border-green-600 h-fit">
                    <h2 class="text-2xl font-black mb-6 text-green-700" id="cl-form-title">New Group</h2>
                    <form action="admin_dashboard.php" method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="add_class" id="cl-action"><input type="hidden" name="class_id" id="cl-id">
                        <input type="text" name="new_class_name" id="cl-name" required placeholder="Academic Group" class="w-full px-5 py-4 rounded-2xl border-2 font-bold outline-none focus:border-green-500">
                        <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white py-4 rounded-2xl font-black transition-all active:scale-95 shadow-lg">CREATE GROUP</button>
                    </form>
                </div>
                <div class="lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach($classes as $c): ?>
                    <div class="bg-white rounded-[2rem] p-8 shadow-lg border flex items-center justify-between transition-all hover:border-indigo-200">
                        <div><h3 class="text-2xl font-black text-gray-800"><?php echo htmlspecialchars($c['class_name']); ?></h3><p class="text-xs font-black text-indigo-500 uppercase tracking-widest"><?php echo $c['student_count']; ?> Students</p></div>
                        <div class="flex gap-2">
                            <button onclick='editCl(<?php echo json_encode($c); ?>)' class="text-indigo-600 font-bold text-xs uppercase">Edit</button>
                            <form action="admin_dashboard.php" method="POST" onsubmit="return confirm('Delete?')"><input type="hidden" name="action" value="delete_class"><input type="hidden" name="class_id" value="<?php echo $c['class_id']; ?>"><button type="submit" class="w-10 h-10 bg-red-50 text-red-500 rounded-xl flex items-center justify-center transition-all hover:bg-red-500 hover:text-white">🗑️</button></form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <script>
        // --- UI TOGGLES ---
        function showTab(t) {
            document.querySelectorAll('.tab-content').forEach(s => s.classList.add('hidden'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('sect-' + t).classList.remove('hidden');
            document.getElementById('tab-' + t).classList.add('active');
        }

        // --- INTELLIGENCE HUB (DRILL-DOWN LOGIC) ---
        const intelData = <?php echo $intel_json; ?>;

        function renderIntelClasses() {
            let html = `<h2 class="text-3xl font-black text-gray-800 italic uppercase mb-12">Intelligence Hub: Select a Group</h2><div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-12">`;
            intelData.forEach((cls, idx) => {
                html += `
                <div onclick="renderIntelQuizzes(${idx})" class="bg-indigo-50 hover:bg-indigo-100 cursor-pointer p-6 rounded-3xl border-2 border-white shadow-sm text-center transition-all transform hover:-translate-y-1">
                    <p class="text-xs font-black text-gray-500 uppercase mb-2">${cls.class_name}</p>
                    <p class="text-4xl font-black text-indigo-700">${cls.student_count}</p>
                    <p class="text-[10px] font-bold text-indigo-400 mt-1 uppercase">Students Enrolled</p>
                </div>`;
            });
            html += `</div>`;
            document.getElementById('intel-container').innerHTML = html;
        }

        function renderIntelQuizzes(classIdx) {
            const cls = intelData[classIdx];
            let html = `
            <div class="flex items-center gap-4 mb-10">
                <button onclick="renderIntelClasses()" class="px-5 py-2 bg-gray-200 hover:bg-gray-300 rounded-xl font-black text-xs uppercase transition-all">← Back</button>
                <h2 class="text-3xl font-black text-gray-800 italic uppercase">${cls.class_name} Quizzes</h2>
            </div>`;
            
            if(!cls.quizzes || cls.quizzes.length === 0) {
                html += `<div class="p-10 text-center border-4 border-dashed rounded-3xl opacity-50"><p class="font-black text-xl uppercase">No quiz data yet.</p></div>`;
            } else {
                html += `<div class="grid grid-cols-1 md:grid-cols-3 gap-6">`;
                cls.quizzes.forEach((quiz, qIdx) => {
                    const col = quiz.level_name === 'Easy' ? 'border-green-500' : (quiz.level_name === 'Intermediate' ? 'border-amber-500' : 'border-red-500');
                    html += `
                    <div onclick="renderIntelStats(${classIdx}, ${qIdx})" class="bg-white hover:bg-gray-50 cursor-pointer p-6 rounded-[2rem] border border-b-8 shadow-sm transition-all transform hover:-translate-y-1 ${col}">
                        <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-gray-100 text-gray-500">${quiz.level_name}</span>
                        <h4 class="text-2xl font-black text-gray-800 mt-4">${quiz.quiz_name}</h4>
                        <p class="text-[10px] font-bold text-gray-400 uppercase mt-2 tracking-tighter">${quiz.participation} Students Attempted</p>
                    </div>`;
                });
                html += `</div>`;
            }
            document.getElementById('intel-container').innerHTML = html;
        }

        function renderIntelStats(classIdx, quizIdx) {
            const cls = intelData[classIdx];
            const quiz = cls.quizzes[quizIdx];
            
            let html = `
            <div class="flex items-center gap-4 mb-10">
                <button onclick="renderIntelQuizzes(${classIdx})" class="px-5 py-2 bg-gray-200 hover:bg-gray-300 rounded-xl font-black text-xs uppercase transition-all">← Back</button>
                <h2 class="text-3xl font-black text-gray-800 italic uppercase">${cls.class_name} - ${quiz.quiz_name} Analytics</h2>
            </div>
            <div class="space-y-6">`;

            if(!quiz.questions || quiz.questions.length === 0) {
                 html += `<p class="font-bold text-gray-500 opacity-50 text-center italic">No question stats available.</p>`;
            } else {
                 quiz.questions.forEach(q => {
                     const pct = q.accuracy;
                     const wrong = q.attempts - q.correct;
                     
                     // Health Status Badges
                     let badgeStyle, badgeText, barCol;
                     if (pct > 75) {
                         badgeStyle = 'bg-green-100 text-green-700 border-green-200';
                         badgeText = 'Mastered';
                         barCol = 'bg-green-500';
                     } else if (pct > 40) {
                         badgeStyle = 'bg-amber-100 text-amber-700 border-amber-200';
                         badgeText = 'Review Needed';
                         barCol = 'bg-amber-500';
                     } else {
                         badgeStyle = 'bg-red-100 text-red-700 border-red-200';
                         badgeText = 'Critical Alert';
                         barCol = 'bg-red-500';
                     }
                     
                     // Enhanced Image Display Layout
                     let imgHtml = '';
                     if (q.question_image || q.solution_image) {
                         imgHtml += `<div class="flex flex-col md:flex-row gap-4 mb-6">`;
                         if (q.question_image) {
                             imgHtml += `<div class="flex-1 bg-gray-50 rounded-[1.5rem] p-4 border border-gray-200 flex flex-col justify-center"><p class="text-[10px] font-black text-gray-400 uppercase mb-3 text-center tracking-widest">Question Asset</p><img src="uploads/${q.question_image}" class="max-h-32 mx-auto rounded-xl shadow-sm object-contain bg-white"></div>`;
                         }
                         if (q.solution_image) {
                             imgHtml += `<div class="flex-1 bg-indigo-50/50 rounded-[1.5rem] p-4 border border-indigo-100 flex flex-col justify-center"><p class="text-[10px] font-black text-indigo-400 uppercase mb-3 text-center tracking-widest">Solution Asset</p><img src="uploads/${q.solution_image}" class="max-h-32 mx-auto rounded-xl shadow-sm object-contain bg-white"></div>`;
                         }
                         imgHtml += `</div>`;
                     }

                     html += `
                     <div class="bg-white p-8 rounded-[2.5rem] border border-gray-200 shadow-sm relative overflow-hidden transition-all hover:shadow-xl hover:-translate-y-1 hover:border-indigo-200 group">
                        <!-- Decorative left color band -->
                        <div class="absolute left-0 top-0 bottom-0 w-2 ${barCol} opacity-80 group-hover:opacity-100 transition-opacity"></div>
                        
                        <div class="pl-4 flex flex-col h-full">
                            <!-- Top Section: Text & Badge -->
                            <div class="flex flex-col md:flex-row justify-between items-start gap-6 mb-6">
                                <h4 class="text-xl font-black text-gray-800 leading-snug">"${q.question_text}"</h4>
                                <span class="px-4 py-2 rounded-full border text-[10px] font-black uppercase tracking-widest whitespace-nowrap shrink-0 ${badgeStyle}">
                                    ${badgeText} &bull; ${pct}%
                                </span>
                            </div>

                            <!-- Assets Section -->
                            ${imgHtml}

                            <!-- Stats Grid -->
                            <div class="bg-gray-50 rounded-[2rem] p-6 border border-gray-100 mt-auto">
                                <div class="grid grid-cols-3 gap-4 mb-5 text-center divide-x divide-gray-200">
                                    <div>
                                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Correct</p>
                                        <p class="text-3xl font-black text-green-500">${q.correct}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Incorrect</p>
                                        <p class="text-3xl font-black text-red-500">${wrong}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Total</p>
                                        <p class="text-3xl font-black text-gray-700">${q.attempts}</p>
                                    </div>
                                </div>
                                
                                <!-- Progress Bar -->
                                <div class="w-full h-3 bg-gray-200 rounded-full overflow-hidden shadow-inner">
                                    <div class="${barCol} h-full rounded-full transition-all duration-1000" style="width: ${pct}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>`;
                 });
            }
            html += `</div>`;
            document.getElementById('intel-container').innerHTML = html;
        }

        // Initialize Intelligence Hub
        document.addEventListener('DOMContentLoaded', () => {
            if(document.getElementById('intel-container')) renderIntelClasses();
        });


        // --- CRUD LOGIC FOR FORMS ---
        function editQuiz(q) {
            document.getElementById('quiz-form-title').innerText = "Update Quiz";
            document.getElementById('quiz-action').value = "edit_quiz";
            document.getElementById('quiz-id').value = q.quiz_id;
            document.getElementById('quiz-name').value = q.quiz_name;
            document.getElementById('quiz-level').value = q.level_id;
            document.getElementById('quiz-timed').checked = q.is_timed == 1;
            document.getElementById('quiz-limit').value = q.time_limit;
            window.scrollTo({top:0, behavior:'smooth'});
        }

        function editCl(c) {
            document.getElementById('cl-form-title').innerText = "Modify Group";
            document.getElementById('cl-action').value = "edit_class";
            document.getElementById('cl-id').value = c.class_id;
            document.getElementById('cl-name').value = c.class_name;
        }

        async function manageQuestions(quizId, quizName, qCount, isPub) {
            document.getElementById('managed-name').innerText = quizName;
            document.getElementById('q-quiz-id').value = quizId;
            document.getElementById('pub-id').value = quizId;
            document.getElementById('builder-hub').classList.remove('hidden');
            document.getElementById('builder-hub').scrollIntoView({behavior:'smooth'});
            
            // Reset the form just in case a user was previously editing a question
            document.getElementById('q-form').reset();
            document.getElementById('q-action').value = "add_question";
            document.getElementById('q-id').value = "";
            document.getElementById('q-img-val').value = "";
            document.getElementById('s-img-val').value = "";
            document.getElementById('p-q').classList.add('hidden');
            document.getElementById('p-s').classList.add('hidden');
            
            const btn = document.getElementById('pub-btn'); const val = document.getElementById('pub-status');
            if(qCount >= 10) {
                btn.classList.remove('hidden');
                if(isPub) { btn.innerText = "UNPUBLISH"; btn.className = "bg-red-500 px-8 py-4 rounded-2xl font-black text-white shadow-xl"; val.value = 0; }
                else { btn.innerText = "PUBLISH LIVE"; btn.className = "bg-green-500 px-8 py-4 rounded-2xl font-black text-white shadow-xl"; val.value = 1; }
            } else { btn.classList.add('hidden'); }

            const list = document.getElementById('questions-list');
            list.innerHTML = "<p class='p-10 text-center opacity-30 font-black uppercase tracking-widest'>Syncing Intel...</p>";
            try {
                const res = await fetch(`get_questions.php?quiz_id=${quizId}`);
                const data = await res.json();
                list.innerHTML = data.length ? "" : "<p class='p-10 text-center opacity-20 font-black'>No intelligence data</p>";
                data.forEach((q, i) => {
                    list.innerHTML += `<div class="bg-white p-6 rounded-2xl border-2 flex items-center gap-6 shadow-sm"><div class="w-10 h-10 bg-indigo-50 text-indigo-700 flex items-center justify-center font-black rounded-lg">${i+1}</div><div class="flex-grow font-bold text-gray-700 truncate">${q.question_text}</div><div class="flex gap-3"><button onclick='loadQ(${JSON.stringify(q).replace(/'/g, "&apos;")})' class="text-indigo-600 font-bold text-xs uppercase">Edit</button><form action="admin_dashboard.php" method="POST" class="inline"><input type="hidden" name="action" value="delete_question"><input type="hidden" name="question_id" value="${q.question_id}"><button type="submit" class="text-red-500 font-black text-xs uppercase" onclick="return confirm('Del?')">Del</button></form></div></div>`;
                });
            } catch (e) { list.innerHTML = "Sync Error."; }
        }

        function loadQ(q) {
            document.getElementById('q-action').value = "edit_question"; document.getElementById('q-id').value = q.question_id;
            document.getElementById('q-text').value = q.question_text; document.getElementById('q-a').value = q.option_a;
            document.getElementById('q-b').value = q.option_b; document.getElementById('q-c').value = q.option_c;
            document.getElementById('q-d').value = q.option_d; document.getElementById('q-correct').value = q.correct_option;
            document.getElementById('q-sol').value = q.solution_text; 
            
            // Clear current crop values
            document.getElementById('q-img-val').value = "";
            document.getElementById('s-img-val').value = "";
            
            // Load existing previews if available
            const pq = document.getElementById('p-q');
            if (q.question_image) {
                pq.classList.remove('hidden');
                pq.querySelector('img').src = 'uploads/' + q.question_image;
            } else { pq.classList.add('hidden'); }
            
            const ps = document.getElementById('p-s');
            if (q.solution_image) {
                ps.classList.remove('hidden');
                ps.querySelector('img').src = 'uploads/' + q.solution_image;
            } else { ps.classList.add('hidden'); }

            document.getElementById('q-form').scrollIntoView({behavior:'smooth'});
        }

        let cropper; let currType;
        function initCrop(input, type) {
            if (input.files && input.files[0]) {
                currType = type; const reader = new FileReader();
                reader.onload = (e) => {
                    const img = document.getElementById('cropTarget');
                    img.src = e.target.result;
                    document.getElementById('cropModal').style.display = 'flex';
                    
                    // Added setTimeout to fix cropper miscalculation when container switches from display none to flex
                    setTimeout(() => {
                        if(cropper) cropper.destroy(); 
                        cropper = new Cropper(img, {viewMode: 1});
                    }, 50);
                }; 
                reader.readAsDataURL(input.files[0]);
                input.value = ""; // Reset the input allowing identical files to be selected again
            }
        }
        function closeCrop() { document.getElementById('cropModal').style.display = 'none'; }
        function saveCrop() {
            if(cropper) {
                const b64 = cropper.getCroppedCanvas().toDataURL('image/png');
                document.getElementById(currType + '-img-val').value = b64;
                document.getElementById('p-' + currType).classList.remove('hidden');
                document.getElementById('p-' + currType).querySelector('img').src = b64;
            }
            closeCrop();
        }
    </script>
</body>
</html>