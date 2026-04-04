<?php
session_start();
require_once 'db.php';

// Access Control
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

$message = "";
$messageType = "";

// --- POST/REDIRECT/GET PATTERN HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $success = false;

    try {
        // Class Management
        if ($action === 'add_class') {
            $name = trim($_POST['new_class_name']);
            $stmt = $pdo->prepare("INSERT INTO classes (class_name) VALUES (?)");
            $stmt->execute([$name]);
            $message = "Class created!";
            $success = true;
        } elseif ($action === 'edit_class') {
            $id = $_POST['class_id'];
            $name = trim($_POST['class_name']);
            $stmt = $pdo->prepare("UPDATE classes SET class_name = ? WHERE class_id = ?");
            $stmt->execute([$name, $id]);
            $message = "Class updated!";
            $success = true;
        } elseif ($action === 'delete_class') {
            $id = $_POST['class_id'];
            $stmt = $pdo->prepare("DELETE FROM classes WHERE class_id = ?");
            $stmt->execute([$id]);
            $message = "Class deleted!";
            $success = true;
        }

        // Quiz Management
        elseif ($action === 'create_quiz') {
            $name = trim($_POST['quiz_name']);
            $lvl = $_POST['level_id'];
            $timed = isset($_POST['is_timed']) ? 1 : 0;
            $limit = (int)$_POST['time_limit'];
            $stmt = $pdo->prepare("INSERT INTO quizzes (quiz_name, level_id, is_timed, time_limit) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $lvl, $timed, $limit]);
            $message = "Quiz created!";
            $success = true;
        } elseif ($action === 'edit_quiz') {
            $id = $_POST['quiz_id'];
            $name = trim($_POST['quiz_name']);
            $lvl = $_POST['level_id'];
            $timed = isset($_POST['is_timed']) ? 1 : 0;
            $limit = (int)$_POST['time_limit'];
            $stmt = $pdo->prepare("UPDATE quizzes SET quiz_name = ?, level_id = ?, is_timed = ?, time_limit = ? WHERE quiz_id = ?");
            $stmt->execute([$name, $lvl, $timed, $limit, $id]);
            $message = "Quiz updated!";
            $success = true;
        } elseif ($action === 'delete_quiz') {
            $id = $_POST['quiz_id'];
            $stmt = $pdo->prepare("DELETE FROM quizzes WHERE quiz_id = ?");
            $stmt->execute([$id]);
            $message = "Quiz deleted!";
            $success = true;
        }

        // Question Management
        elseif ($action === 'add_question' || $action === 'edit_question') {
            $quiz_id = $_POST['quiz_id'];
            $text = trim($_POST['question_text']);
            $opt_a = trim($_POST['option_a']);
            $opt_b = trim($_POST['option_b']);
            $opt_c = trim($_POST['option_c']);
            $opt_d = trim($_POST['option_d']);
            $correct = $_POST['correct_option'];
            $sol_text = trim($_POST['solution_text']);
            
            $q_img = $_POST['cropped_question_image'] ?? null;
            $s_img = $_POST['cropped_solution_image'] ?? null;
            
            function processImg($base64, $existing = null) {
                if (empty($base64)) return $existing;
                $data = explode(',', $base64);
                $imgData = base64_decode($data[1]);
                $name = uniqid('img_', true) . '.png';
                if (!is_dir('uploads')) mkdir('uploads', 0777, true);
                file_put_contents('uploads/' . $name, $imgData);
                return $name;
            }

            $existing_q_img = null; $existing_s_img = null;
            if ($action === 'edit_question') {
                $st = $pdo->prepare("SELECT question_image, solution_image, level_id FROM questions WHERE question_id = ?");
                $st->execute([$_POST['question_id']]);
                $row = $st->fetch();
                $existing_q_img = $row['question_image'];
                $existing_s_img = $row['solution_image'];
                $level_id = $row['level_id'];
            } else {
                $st = $pdo->prepare("SELECT level_id FROM quizzes WHERE quiz_id = ?");
                $st->execute([$quiz_id]);
                $level_id = $st->fetchColumn();
            }

            $q_img_name = processImg($q_img, $existing_q_img);
            $s_img_name = processImg($s_img, $existing_s_img);

            if ($action === 'add_question') {
                $sql = "INSERT INTO questions (quiz_id, level_id, question_text, option_a, option_b, option_c, option_d, correct_option, solution_text, question_image, solution_image, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$quiz_id, $level_id, $text, $opt_a, $opt_b, $opt_c, $opt_d, $correct, $sol_text, $q_img_name, $s_img_name, $_SESSION['user_id']]);
            } else {
                $sql = "UPDATE questions SET quiz_id = ?, question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_option = ?, solution_text = ?, question_image = ?, solution_image = ? WHERE question_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$quiz_id, $text, $opt_a, $opt_b, $opt_c, $opt_d, $correct, $sol_text, $q_img_name, $s_img_name, $_POST['question_id']]);
            }
            $message = "Question saved!";
            $success = true;
        } elseif ($action === 'delete_question') {
            $id = $_POST['question_id'];
            $stmt = $pdo->prepare("DELETE FROM questions WHERE question_id = ?");
            $stmt->execute([$id]);
            $message = "Question removed!";
            $success = true;
        }

        if ($success) {
            $_SESSION['admin_msg'] = $message;
            header("Location: admin_dashboard.php");
            exit();
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

if (isset($_SESSION['admin_msg'])) {
    $message = $_SESSION['admin_msg'];
    $messageType = "success";
    unset($_SESSION['admin_msg']);
}

// Data Fetching
$levels = $pdo->query("SELECT * FROM levels ORDER BY difficulty_rank ASC")->fetchAll();
$classes = $pdo->query("SELECT * FROM classes ORDER BY class_name ASC")->fetchAll();
$quizzes = $pdo->query("SELECT q.*, l.level_name, (SELECT COUNT(*) FROM questions qn WHERE qn.quiz_id = q.quiz_id) as q_count FROM quizzes q JOIN levels l ON q.level_id = l.level_id ORDER BY q.created_at DESC")->fetchAll();

$class_perf = $pdo->query("
    SELECT c.class_id, c.class_name, 
    AVG(qa.score / qa.total_questions * 100) as avg_score,
    COUNT(qa.attempt_id) as attempts
    FROM classes c
    LEFT JOIN users u ON c.class_id = u.class_id
    LEFT JOIN quiz_attempts qa ON u.user_id = qa.user_id
    GROUP BY c.class_id
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PolyMath Admin - Kahoot Edition</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f0f2f5; }
        .kahoot-purple { background-color: #46178f; }
        .kahoot-blue { background-color: #1368ce; }
        .kahoot-green { background-color: #26890c; }
        .kahoot-red { background-color: #e21b3c; }
        .kahoot-yellow { background-color: #ffa602; }
        .tab-btn.active { 
            background-color: white; 
            color: #46178f; 
            box-shadow: 0 4px 0 0 #46178f;
        }
        #cropModal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 1000; align-items: center; justify-content: center; padding: 20px; }
        .card-hover { transition: transform 0.2s; }
        .card-hover:hover { transform: translateY(-4px); }
    </style>
</head>
<body class="pb-12">

    <div id="cropModal">
        <div class="bg-white rounded-[2rem] p-8 w-full max-w-2xl">
            <h3 class="text-2xl font-extrabold mb-4 text-[#46178f]">Crop Image</h3>
            <div class="overflow-hidden rounded-2xl bg-gray-100 mb-6 border-2 border-dashed border-gray-300">
                <img id="cropTarget" src="" class="max-w-full">
            </div>
            <div class="flex justify-end gap-4">
                <button onclick="closeCropModal()" class="px-8 py-3 rounded-2xl bg-gray-200 font-bold text-gray-600 hover:bg-gray-300 transition-all">Cancel</button>
                <button onclick="applyCrop()" class="px-8 py-3 rounded-2xl kahoot-purple text-white font-bold hover:opacity-90 transition-all shadow-lg">Save Crop</button>
            </div>
        </div>
    </div>

    <nav class="kahoot-purple text-white shadow-xl p-5 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-2">
                <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
                    <span class="text-[#46178f] font-black text-xl">P</span>
                </div>
                <h1 class="text-2xl font-black italic tracking-tighter uppercase">PolyMath <span class="font-light text-fuchsia-300">Admin</span></h1>
            </div>
            <div class="flex gap-6 items-center">
                <div class="hidden md:block text-right">
                    <p class="text-[10px] uppercase font-black opacity-60">System Operator</p>
                    <p class="text-sm font-bold"><?php echo htmlspecialchars($_SESSION['username']); ?></p>
                </div>
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 px-6 py-2.5 rounded-xl text-sm font-black transition-all shadow-lg shadow-red-900/20">LOGOUT</a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto p-6 md:p-10">
        
        <?php if ($message): ?>
            <div class="mb-8 p-5 rounded-[1.5rem] text-sm font-bold <?php echo $messageType === 'error' ? 'bg-red-100 text-red-700 border-red-200' : 'bg-green-100 text-green-700 border-green-200'; ?> border-2 shadow-sm animate-bounce">
                <?php echo ($messageType === 'error' ? '⚠️ ' : '✅ ') . $message; ?>
            </div>
        <?php endif; ?>

        <!-- Kahoot-style Tabs -->
        <div class="flex bg-gray-200 p-2 rounded-2xl mb-10 w-fit mx-auto gap-2">
            <button onclick="showTab('quizzes')" class="tab-btn px-8 py-3 rounded-xl font-black text-sm transition-all active" id="tab-quizzes">QUIZZES</button>
            <button onclick="showTab('classes')" class="tab-btn px-8 py-3 rounded-xl font-black text-sm transition-all" id="tab-classes">CLASSES</button>
            <button onclick="showTab('analytics')" class="tab-btn px-8 py-3 rounded-xl font-black text-sm transition-all" id="tab-analytics">REPORTS</button>
        </div>

        <!-- QUIZZES TAB -->
        <section id="sect-quizzes" class="tab-content space-y-12">
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
                <!-- Form Side -->
                <div class="space-y-8">
                    <div class="bg-white rounded-[2rem] p-8 shadow-xl border-b-8 border-indigo-600 card-hover">
                        <h2 class="text-2xl font-black mb-6 text-indigo-700" id="quiz-form-title">Create Quiz</h2>
                        <form action="admin_dashboard.php" method="POST" class="space-y-5">
                            <input type="hidden" name="action" value="create_quiz" id="quiz-action">
                            <input type="hidden" name="quiz_id" id="quiz-id">
                            <div>
                                <label class="block text-xs font-black text-gray-400 uppercase mb-2">Quiz Name</label>
                                <input type="text" name="quiz_name" id="quiz-name" required placeholder="e.g. Algebra Masters" class="w-full px-5 py-3 rounded-2xl border-2 border-gray-100 focus:border-indigo-500 outline-none transition-all font-bold">
                            </div>
                            <div>
                                <label class="block text-xs font-black text-gray-400 uppercase mb-2">Difficulty</label>
                                <select name="level_id" id="quiz-level" class="w-full px-5 py-3 rounded-2xl border-2 border-gray-100 focus:border-indigo-500 outline-none transition-all font-bold">
                                    <?php foreach($levels as $l): ?>
                                        <option value="<?php echo $l['level_id']; ?>"><?php echo $l['level_name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="p-4 bg-indigo-50 rounded-2xl border-2 border-indigo-100">
                                <div class="flex items-center gap-3">
                                    <input type="checkbox" name="is_timed" id="quiz-timed" class="w-5 h-5 accent-indigo-600" onchange="document.getElementById('limit-box').classList.toggle('hidden', !this.checked)">
                                    <label class="text-sm font-black text-indigo-700">Timed Quiz</label>
                                </div>
                                <div id="limit-box" class="hidden mt-4">
                                    <label class="block text-[10px] font-black text-indigo-400 uppercase mb-1">Time Limit (Sec)</label>
                                    <input type="number" name="time_limit" id="quiz-limit" value="300" class="w-full px-4 py-2 rounded-xl border-2 border-indigo-200 outline-none font-bold">
                                </div>
                            </div>
                            <div class="flex flex-col gap-3 pt-2">
                                <button type="submit" class="w-full kahoot-blue text-white py-4 rounded-2xl font-black shadow-[0_4px_0_0_#0a3d7a] hover:opacity-95 active:translate-y-1 active:shadow-none transition-all">SAVE QUIZ</button>
                                <button type="button" onclick="resetQuizForm()" id="quiz-cancel" class="hidden w-full bg-gray-100 text-gray-500 py-3 rounded-2xl font-black">CANCEL</button>
                            </div>
                        </form>
                    </div>

                    <div class="kahoot-purple text-white p-8 rounded-[2rem] shadow-xl relative overflow-hidden card-hover">
                        <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-white/10 rounded-full"></div>
                        <h3 class="text-lg font-black mb-4 flex items-center gap-2"><span>💡</span> Pro Tip</h3>
                        <p class="text-sm font-bold opacity-80 leading-relaxed">Add at least 10 questions to each quiz for the best randomized experience for your students!</p>
                    </div>
                </div>

                <!-- List Side -->
                <div class="lg:col-span-2 bg-white rounded-[2rem] shadow-xl border-b-8 border-purple-700 overflow-hidden">
                    <div class="p-8 border-b bg-gray-50/50 flex justify-between items-center">
                        <h2 class="text-2xl font-black text-gray-800">Quiz Library</h2>
                        <a href="export_csv.php" class="bg-gray-800 text-white px-5 py-2 rounded-xl text-xs font-black hover:bg-black transition-all">EXPORT DATA</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="text-[10px] uppercase text-gray-400 font-black tracking-widest bg-gray-50/80">
                                    <th class="px-8 py-5">Title</th>
                                    <th class="px-8 py-5">Difficulty</th>
                                    <th class="px-8 py-5 text-center">Questions</th>
                                    <th class="px-8 py-5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach($quizzes as $q): ?>
                                <tr class="hover:bg-gray-50 transition-all group">
                                    <td class="px-8 py-6 font-black text-gray-800 group-hover:text-indigo-600"><?php echo htmlspecialchars($q['quiz_name']); ?></td>
                                    <td class="px-8 py-6">
                                        <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase <?php echo strtolower($q['level_name']) == 'expert' ? 'bg-red-100 text-red-600' : (strtolower($q['level_name']) == 'easy' ? 'bg-green-100 text-green-600' : 'bg-amber-100 text-amber-600'); ?>">
                                            <?php echo $q['level_name']; ?>
                                        </span>
                                    </td>
                                    <td class="px-8 py-6 text-center font-black text-gray-400"><?php echo $q['q_count']; ?></td>
                                    <td class="px-8 py-6 text-right space-x-3">
                                        <button onclick='editQuiz(<?php echo json_encode($q); ?>)' class="text-indigo-600 hover:text-indigo-800 font-black text-xs uppercase">Edit</button>
                                        <form action="admin_dashboard.php" method="POST" class="inline" onsubmit="return confirm('Delete quiz?')">
                                            <input type="hidden" name="action" value="delete_quiz">
                                            <input type="hidden" name="quiz_id" value="<?php echo $q['quiz_id']; ?>">
                                            <button type="submit" class="text-red-500 hover:text-red-700 font-black text-xs uppercase">Del</button>
                                        </form>
                                        <button onclick="manageQuestions(<?php echo $q['quiz_id']; ?>, '<?php echo addslashes($q['quiz_name']); ?>')" class="kahoot-purple text-white px-5 py-2.5 rounded-xl text-xs font-black shadow-lg hover:opacity-90">QUESTIONS</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Question Manager Section -->
            <div id="question-manager" class="hidden bg-white rounded-[2.5rem] p-10 shadow-2xl border-4 border-[#46178f] animate-in slide-in-from-bottom duration-500">
                <div class="flex justify-between items-center mb-10 border-b-4 border-gray-100 pb-6">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 kahoot-purple text-white rounded-2xl flex items-center justify-center text-3xl font-black italic">?</div>
                        <h2 class="text-3xl font-black text-gray-800">Quiz Content: <span id="managed-quiz-name" class="text-[#46178f]"></span></h2>
                    </div>
                    <button onclick="document.getElementById('question-manager').classList.add('hidden')" class="bg-gray-100 hover:bg-gray-200 p-3 rounded-2xl transition-all">
                        <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-12">
                    <!-- Question Form -->
                    <form action="admin_dashboard.php" id="q-form" method="POST" class="space-y-6 bg-gray-50 p-8 rounded-[2rem] border-2 border-gray-100">
                        <input type="hidden" name="action" value="add_question" id="q-action">
                        <input type="hidden" name="quiz_id" id="q-quiz-id">
                        <input type="hidden" name="question_id" id="q-id">
                        <input type="hidden" name="cropped_question_image" id="q-img-input">
                        <input type="hidden" name="cropped_solution_image" id="s-img-input">

                        <h3 class="font-black text-indigo-700 uppercase text-sm tracking-widest mb-2" id="q-form-title">New Challenge</h3>
                        
                        <textarea name="question_text" id="q-text" placeholder="Write your question here..." required class="w-full px-5 py-4 rounded-2xl border-2 border-white bg-white focus:border-indigo-500 outline-none font-bold shadow-sm min-h-[100px]"></textarea>
                        
                        <div class="grid grid-cols-2 gap-3">
                            <input type="text" name="option_a" id="q-a" placeholder="Answer A" required class="px-5 py-3 rounded-2xl border-2 border-white bg-white shadow-sm font-bold text-red-600 focus:border-red-300 outline-none">
                            <input type="text" name="option_b" id="q-b" placeholder="Answer B" required class="px-5 py-3 rounded-2xl border-2 border-white bg-white shadow-sm font-bold text-blue-600 focus:border-blue-300 outline-none">
                            <input type="text" name="option_c" id="q-c" placeholder="Answer C" required class="px-5 py-3 rounded-2xl border-2 border-white bg-white shadow-sm font-bold text-amber-500 focus:border-amber-300 outline-none">
                            <input type="text" name="option_d" id="q-d" placeholder="Answer D" required class="px-5 py-3 rounded-2xl border-2 border-white bg-white shadow-sm font-bold text-green-600 focus:border-green-300 outline-none">
                        </div>

                        <div class="flex gap-4">
                            <div class="flex-grow">
                                <label class="block text-[10px] font-black text-gray-400 uppercase mb-1 ml-1">Correct Answer</label>
                                <select name="correct_option" id="q-correct" class="w-full px-5 py-3 rounded-2xl border-2 border-white bg-white shadow-sm font-black text-indigo-600 outline-none">
                                    <option value="A">A (Red)</option>
                                    <option value="B">B (Blue)</option>
                                    <option value="C">C (Yellow)</option>
                                    <option value="D">D (Green)</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <button type="button" onclick="document.getElementById('file-q').click()" class="p-4 bg-white rounded-2xl border-2 border-dashed border-gray-200 hover:border-indigo-400 transition-all flex flex-col items-center gap-2 group">
                                <span class="text-[10px] font-black text-gray-400 group-hover:text-indigo-500">Q IMAGE</span>
                                <input type="file" id="file-q" class="hidden" onchange="initCrop(this, 'q')">
                                <div id="preview-q" class="h-12 hidden"><img src="" class="h-full rounded shadow-md"></div>
                                <div id="icon-q" class="text-xl opacity-20">📷</div>
                            </button>
                            <button type="button" onclick="document.getElementById('file-s').click()" class="p-4 bg-white rounded-2xl border-2 border-dashed border-gray-200 hover:border-indigo-400 transition-all flex flex-col items-center gap-2 group">
                                <span class="text-[10px] font-black text-gray-400 group-hover:text-indigo-500">S IMAGE</span>
                                <input type="file" id="file-s" class="hidden" onchange="initCrop(this, 's')">
                                <div id="preview-s" class="h-12 hidden"><img src="" class="h-full rounded shadow-md"></div>
                                <div id="icon-s" class="text-xl opacity-20">📖</div>
                            </button>
                        </div>

                        <textarea name="solution_text" id="q-sol" placeholder="Explain the solution..." class="w-full px-5 py-4 rounded-2xl border-2 border-white bg-white focus:border-indigo-500 outline-none font-bold shadow-sm min-h-[80px]"></textarea>

                        <div class="flex flex-col gap-3 pt-4">
                            <button type="submit" class="w-full kahoot-purple text-white py-4 rounded-2xl font-black shadow-[0_4px_0_0_#2b0e59] hover:opacity-95 active:translate-y-1 active:shadow-none transition-all">PUBLISH QUESTION</button>
                            <button type="button" onclick="resetQForm()" id="q-cancel" class="hidden w-full bg-white text-gray-400 py-3 rounded-2xl font-black border-2">ABORT</button>
                        </div>
                    </form>

                    <!-- Question List -->
                    <div class="xl:col-span-2 space-y-4 overflow-y-auto max-h-[800px] pr-4 custom-scrollbar" id="questions-list">
                        <!-- Questions injected here -->
                    </div>
                </div>
            </div>
        </section>

        <!-- CLASSES TAB -->
        <section id="sect-classes" class="tab-content hidden space-y-10">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
                <div class="bg-white rounded-[2.5rem] p-8 shadow-xl border-b-8 border-green-600 h-fit card-hover">
                    <h2 class="text-2xl font-black mb-6 text-green-700" id="class-form-title">Add New Class</h2>
                    <form action="admin_dashboard.php" method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="add_class" id="class-action">
                        <input type="hidden" name="class_id" id="class-id">
                        <input type="text" name="new_class_name" id="class-name" placeholder="e.g. Physics 101" required class="w-full px-5 py-4 rounded-2xl border-2 border-gray-100 focus:border-green-500 outline-none font-bold">
                        <div class="flex flex-col gap-2 pt-2">
                            <button type="submit" class="w-full kahoot-green text-white py-4 rounded-2xl font-black shadow-[0_4px_0_0_#1a6008] transition-all">SAVE CLASS</button>
                            <button type="button" onclick="resetClassForm()" id="class-cancel" class="hidden w-full bg-gray-100 py-3 rounded-2xl font-black">X</button>
                        </div>
                    </form>
                </div>

                <div class="lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach($classes as $c): ?>
                    <div class="bg-white rounded-[2rem] p-8 shadow-lg border border-gray-100 flex items-center justify-between card-hover">
                        <div>
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Academic Group</p>
                            <h3 class="text-2xl font-black text-gray-800"><?php echo htmlspecialchars($c['class_name']); ?></h3>
                        </div>
                        <div class="flex gap-4">
                            <button onclick='editClass(<?php echo json_encode($c); ?>)' class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center hover:bg-indigo-100 transition-all">✏️</button>
                            <form action="admin_dashboard.php" method="POST" class="inline" onsubmit="return confirm('Delete class?')">
                                <input type="hidden" name="action" value="delete_class">
                                <input type="hidden" name="class_id" value="<?php echo $c['class_id']; ?>">
                                <button type="submit" class="w-10 h-10 bg-red-50 text-red-500 rounded-xl flex items-center justify-center hover:bg-red-100 transition-all">🗑️</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- ANALYTICS TAB -->
        <section id="sect-analytics" class="tab-content hidden space-y-12">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <?php foreach($class_perf as $perf): ?>
                <div class="bg-white p-10 rounded-[3rem] shadow-xl border-b-8 border-indigo-600 card-hover text-center relative overflow-hidden">
                    <div class="absolute top-0 right-0 p-4 opacity-5 text-8xl font-black"><?php echo substr($perf['class_name'], -1); ?></div>
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2"><?php echo htmlspecialchars($perf['class_name']); ?></p>
                    <h3 class="text-6xl font-black text-indigo-700"><?php echo round($perf['avg_score'] ?? 0, 1); ?><span class="text-2xl opacity-40">%</span></h3>
                    <div class="mt-6 w-full h-4 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full kahoot-blue rounded-full transition-all duration-1000" style="width: <?php echo $perf['avg_score']; ?>%"></div>
                    </div>
                    <p class="mt-6 text-xs font-black text-gray-400 bg-gray-50 py-2 px-4 rounded-xl inline-block"><?php echo $perf['attempts']; ?> ATTEMPTS LOGGED</p>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="bg-white rounded-[3rem] p-12 shadow-2xl border-4 border-indigo-50">
                <div class="flex items-center gap-4 mb-10">
                    <div class="w-16 h-16 bg-indigo-100 rounded-[1.5rem] flex items-center justify-center text-4xl">📈</div>
                    <h2 class="text-3xl font-black text-gray-800 italic">Deep Intelligence Report</h2>
                </div>
                
                <div class="space-y-16">
                    <?php foreach($classes as $c): 
                        $c_id = $c['class_id'];
                        $dist = $pdo->prepare("SELECT l.level_name, AVG(qa.score/qa.total_questions*100) as score FROM quiz_attempts qa JOIN users u ON qa.user_id = u.user_id JOIN levels l ON qa.level_id = l.level_id WHERE u.class_id = ? GROUP BY l.level_id");
                        $dist->execute([$c_id]);
                        $rows = $dist->fetchAll();
                    ?>
                    <div class="relative pl-10 before:absolute before:left-0 before:top-0 before:bottom-0 before:w-1.5 before:bg-gray-100 before:rounded-full">
                        <h3 class="text-2xl font-black text-gray-800 mb-8 border-b-2 border-dashed border-gray-100 pb-4"><?php echo htmlspecialchars($c['class_name']); ?> Analytics</h3>
                        
                        <?php if(!$rows): ?>
                            <div class="p-10 text-center bg-gray-50 rounded-3xl border-2 border-dashed border-gray-200">
                                <p class="text-gray-400 font-black uppercase text-xs tracking-widest">No Intelligence Data Found</p>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                                <?php foreach($rows as $r): 
                                    $p = round($r['score'], 1);
                                    $col = ($r['level_name'] == 'Easy') ? 'kahoot-green' : (($r['level_name'] == 'Expert') ? 'kahoot-red' : 'kahoot-blue');
                                ?>
                                <div class="p-8 bg-gray-50 rounded-[2rem] border-2 border-white shadow-sm flex flex-col items-center">
                                    <p class="text-[10px] font-black text-gray-400 uppercase mb-6"><?php echo $r['level_name']; ?> DIFFICULTY</p>
                                    <div class="relative w-24 h-24 flex items-center justify-center">
                                        <svg class="w-full h-full transform -rotate-90">
                                            <circle cx="48" cy="48" r="40" stroke="currentColor" stroke-width="8" fill="transparent" class="text-gray-200" />
                                            <circle cx="48" cy="48" r="40" stroke="currentColor" stroke-width="8" fill="transparent" class="<?php echo str_replace('kahoot', 'text', $col); ?>" stroke-dasharray="251.2" stroke-dashoffset="<?php echo 251.2 * (1 - ($p/100)); ?>" />
                                        </svg>
                                        <span class="absolute text-xl font-black"><?php echo $p; ?>%</span>
                                    </div>
                                    <p class="mt-6 text-[10px] font-black opacity-40 uppercase">AVG ACCURACY</p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <script>
        function showTab(tab) {
            document.querySelectorAll('.tab-content').forEach(s => s.classList.add('hidden'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('sect-' + tab).classList.remove('hidden');
            document.getElementById('tab-' + tab).classList.add('active');
        }

        function editQuiz(q) {
            document.getElementById('quiz-form-title').innerText = "Update Quiz";
            document.getElementById('quiz-action').value = "edit_quiz";
            document.getElementById('quiz-id').value = q.quiz_id;
            document.getElementById('quiz-name').value = q.quiz_name;
            document.getElementById('quiz-level').value = q.level_id;
            document.getElementById('quiz-timed').checked = q.is_timed == 1;
            document.getElementById('quiz-limit').value = q.time_limit;
            document.getElementById('limit-box').classList.toggle('hidden', q.is_timed != 1);
            document.getElementById('quiz-cancel').classList.remove('hidden');
            window.scrollTo({top: 0, behavior: 'smooth'});
        }

        function resetQuizForm() {
            document.getElementById('quiz-form-title').innerText = "Create Quiz";
            document.getElementById('quiz-action').value = "create_quiz";
            document.getElementById('quiz-id').value = "";
            document.getElementById('quiz-name').value = "";
            document.getElementById('quiz-timed').checked = false;
            document.getElementById('limit-box').classList.add('hidden');
            document.getElementById('quiz-cancel').classList.add('hidden');
        }

        function editClass(c) {
            document.getElementById('class-form-title').innerText = "Edit Class";
            document.getElementById('class-action').value = "edit_class";
            document.getElementById('class-id').value = c.class_id;
            document.getElementById('class-name').value = c.class_name;
            document.getElementById('class-cancel').classList.remove('hidden');
        }

        function resetClassForm() {
            document.getElementById('class-form-title').innerText = "Add New Class";
            document.getElementById('class-action').value = "add_class";
            document.getElementById('class-id').value = "";
            document.getElementById('class-name').value = "";
            document.getElementById('class-cancel').classList.add('hidden');
        }

        async function manageQuestions(quizId, quizName) {
            document.getElementById('managed-quiz-name').innerText = quizName;
            document.getElementById('q-quiz-id').value = quizId;
            document.getElementById('question-manager').classList.remove('hidden');
            document.getElementById('question-manager').scrollIntoView({behavior: 'smooth'});
            resetQForm();
            
            const list = document.getElementById('questions-list');
            list.innerHTML = "<div class='flex flex-col items-center py-20 opacity-30'><div class='w-10 h-10 border-4 border-indigo-500 border-t-transparent animate-spin rounded-full mb-4'></div><p class='font-black uppercase text-xs'>Gathering Questions...</p></div>";

            try {
                const res = await fetch(`get_questions.php?quiz_id=${quizId}`);
                const data = await res.json();
                
                list.innerHTML = "";
                if (data.length === 0) {
                    list.innerHTML = "<div class='p-16 border-4 border-dashed rounded-[3rem] text-center text-gray-300 font-black uppercase text-sm tracking-widest'>Empty Quiz</div>";
                    return;
                }

                data.forEach((q, i) => {
                    const colMap = {A: 'red-500', B: 'blue-500', C: 'amber-500', D: 'green-600'};
                    list.innerHTML += `
                        <div class="bg-white p-8 rounded-[2rem] border-2 border-gray-100 shadow-sm flex flex-col md:flex-row gap-8 hover:border-indigo-200 transition-all card-hover group">
                            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-700 flex items-center justify-center font-black text-xl flex-shrink-0">${i+1}</div>
                            <div class="flex-grow">
                                <p class="font-black text-gray-800 text-lg mb-6 leading-tight">${q.question_text}</p>
                                <div class="grid grid-cols-2 gap-4 text-xs font-bold mb-8">
                                    <div class="flex items-center gap-2 p-2 rounded-xl bg-red-50 ${q.correct_option == 'A' ? 'ring-2 ring-red-500' : ''}">
                                        <div class="w-5 h-5 bg-red-500 rounded text-white text-[8px] flex items-center justify-center">▲</div>
                                        <span class="text-red-700 truncate">${q.option_a}</span>
                                    </div>
                                    <div class="flex items-center gap-2 p-2 rounded-xl bg-blue-50 ${q.correct_option == 'B' ? 'ring-2 ring-blue-500' : ''}">
                                        <div class="w-5 h-5 bg-blue-500 rounded text-white text-[8px] flex items-center justify-center">◆</div>
                                        <span class="text-blue-700 truncate">${q.option_b}</span>
                                    </div>
                                    <div class="flex items-center gap-2 p-2 rounded-xl bg-amber-50 ${q.correct_option == 'C' ? 'ring-2 ring-amber-500' : ''}">
                                        <div class="w-5 h-5 bg-amber-500 rounded text-white text-[8px] flex items-center justify-center">●</div>
                                        <span class="text-amber-700 truncate">${q.option_c}</span>
                                    </div>
                                    <div class="flex items-center gap-2 p-2 rounded-xl bg-green-50 ${q.correct_option == 'D' ? 'ring-2 ring-green-600' : ''}">
                                        <div class="w-5 h-5 bg-green-600 rounded text-white text-[8px] flex items-center justify-center">■</div>
                                        <span class="text-green-700 truncate">${q.option_d}</span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between border-t border-gray-50 pt-6">
                                    <div class="flex gap-4">
                                        <button onclick='loadQuestionForEdit(${JSON.stringify(q).replace(/'/g, "&apos;")})' class="bg-indigo-600 text-white px-6 py-2 rounded-xl text-[10px] font-black uppercase shadow-lg shadow-indigo-200">Edit</button>
                                        <form action="admin_dashboard.php" method="POST" class="inline" onsubmit="return confirm('Delete question?')">
                                            <input type="hidden" name="action" value="delete_question">
                                            <input type="hidden" name="question_id" value="${q.question_id}">
                                            <button type="submit" class="bg-gray-100 text-gray-400 hover:text-red-500 px-6 py-2 rounded-xl text-[10px] font-black uppercase">Delete</button>
                                        </form>
                                    </div>
                                    <div class="flex gap-2">
                                        ${q.question_image ? '<span class="text-xs">📷</span>' : ''}
                                        ${q.solution_image ? '<span class="text-xs">📖</span>' : ''}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
            } catch (e) {
                list.innerHTML = "<p class='text-red-500 font-black p-10 text-center'>Sync Error: Failed to load challenges.</p>";
            }
        }

        function loadQuestionForEdit(q) {
            document.getElementById('q-form-title').innerText = "Modify Challenge";
            document.getElementById('q-action').value = "edit_question";
            document.getElementById('q-id').value = q.question_id;
            document.getElementById('q-text').value = q.question_text;
            document.getElementById('q-a').value = q.option_a;
            document.getElementById('q-b').value = q.option_b;
            document.getElementById('q-c').value = q.option_c;
            document.getElementById('q-d').value = q.option_d;
            document.getElementById('q-correct').value = q.correct_option;
            document.getElementById('q-sol').value = q.solution_text;
            
            document.getElementById('preview-q').classList.add('hidden');
            document.getElementById('preview-s').classList.add('hidden');
            document.getElementById('icon-q').classList.remove('hidden');
            document.getElementById('icon-s').classList.remove('hidden');

            if(q.question_image) {
                document.getElementById('preview-q').classList.remove('hidden');
                document.getElementById('preview-q').querySelector('img').src = 'uploads/' + q.question_image;
                document.getElementById('icon-q').classList.add('hidden');
            }
            if(q.solution_image) {
                document.getElementById('preview-s').classList.remove('hidden');
                document.getElementById('preview-s').querySelector('img').src = 'uploads/' + q.solution_image;
                document.getElementById('icon-s').classList.add('hidden');
            }

            document.getElementById('q-cancel').classList.remove('hidden');
            document.getElementById('q-form').scrollIntoView({behavior: 'smooth'});
        }

        function resetQForm() {
            document.getElementById('q-form-title').innerText = "New Challenge";
            document.getElementById('q-action').value = "add_question";
            document.getElementById('q-id').value = "";
            document.getElementById('q-text').value = "";
            document.getElementById('q-a').value = "";
            document.getElementById('q-b').value = "";
            document.getElementById('q-c').value = "";
            document.getElementById('q-d').value = "";
            document.getElementById('q-correct').value = "A";
            document.getElementById('q-sol').value = "";
            document.getElementById('q-img-input').value = "";
            document.getElementById('s-img-input').value = "";
            document.getElementById('preview-q').classList.add('hidden');
            document.getElementById('preview-s').classList.add('hidden');
            document.getElementById('icon-q').classList.remove('hidden');
            document.getElementById('icon-s').classList.remove('hidden');
            document.getElementById('q-cancel').classList.add('hidden');
        }

        let cropper;
        let currentImgType;
        function initCrop(input, type) {
            if (input.files && input.files[0]) {
                currentImgType = type;
                const reader = new FileReader();
                reader.onload = (e) => {
                    document.getElementById('cropTarget').src = e.target.result;
                    document.getElementById('cropModal').style.display = 'flex';
                    if(cropper) cropper.destroy();
                    cropper = new Cropper(document.getElementById('cropTarget'), {viewMode: 1, aspectRatio: NaN});
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        function closeCropModal() { document.getElementById('cropModal').style.display = 'none'; }
        function applyCrop() {
            const base64 = cropper.getCroppedCanvas().toDataURL('image/png');
            document.getElementById(currentImgType + '-img-input').value = base64;
            document.getElementById('preview-' + currentImgType).classList.remove('hidden');
            document.getElementById('preview-' + currentImgType).querySelector('img').src = base64;
            document.getElementById('icon-' + currentImgType).classList.add('hidden');
            closeCropModal();
        }
    </script>
</body>
</html>