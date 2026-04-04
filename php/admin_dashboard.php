<?php
session_start();
require_once 'db.php';

// Access Control
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

/**
 * Database Migration / Self-Healing
 * Ensures 'is_published' column exists in the quizzes table
 */
try {
    $checkColumn = $pdo->query("SHOW COLUMNS FROM `quizzes` LIKE 'is_published'");
    if (!$checkColumn->fetch()) {
        $pdo->exec("ALTER TABLE `quizzes` ADD `is_published` TINYINT(1) DEFAULT 0");
    }
} catch (Exception $e) {
    // Silently continue if update fails or table doesn't exist yet
}

/**
 * Helper: Process Base64 Images
 */
function processAdminImg($base64, $existing = null) {
    if (empty($base64) || !str_contains($base64, ',')) return $existing;
    $data = explode(',', $base64);
    if (count($data) < 2) return $existing;
    $imgData = base64_decode($data[1]);
    $name = uniqid('img_', true) . '.png';
    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    file_put_contents($uploadDir . '/' . $name, $imgData);
    return $name;
}

$message = "";
$messageType = "";

// --- POST/REDIRECT/GET PATTERN HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $success = false;
    $redirect_params = "";

    try {
        // --- Class Management ---
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

        // --- Quiz Management ---
        elseif ($action === 'create_quiz' || $action === 'edit_quiz' || $action === 'delete_quiz' || $action === 'toggle_publish') {
            if ($action === 'create_quiz') {
                $name = trim($_POST['quiz_name']);
                $lvl = $_POST['level_id'];
                $timed = isset($_POST['is_timed']) ? 1 : 0;
                $limit = (int)$_POST['time_limit'];
                $stmt = $pdo->prepare("INSERT INTO quizzes (quiz_name, level_id, is_timed, time_limit, is_published) VALUES (?, ?, ?, ?, 0)");
                $stmt->execute([$name, $lvl, $timed, $limit]);
                $message = "Quiz created as draft!";
            } elseif ($action === 'edit_quiz') {
                $id = $_POST['quiz_id'];
                $name = trim($_POST['quiz_name']);
                $lvl = $_POST['level_id'];
                $timed = isset($_POST['is_timed']) ? 1 : 0;
                $limit = (int)$_POST['time_limit'];
                $stmt = $pdo->prepare("UPDATE quizzes SET quiz_name = ?, level_id = ?, is_timed = ?, time_limit = ? WHERE quiz_id = ?");
                $stmt->execute([$name, $lvl, $timed, $limit, $id]);
                $message = "Quiz updated!";
            } elseif ($action === 'delete_quiz') {
                $id = $_POST['quiz_id'];
                $stmt = $pdo->prepare("DELETE FROM quizzes WHERE quiz_id = ?");
                $stmt->execute([$id]);
                $message = "Quiz deleted!";
            } elseif ($action === 'toggle_publish') {
                $id = $_POST['quiz_id'];
                $status = (int)$_POST['status'];
                $redirect_params = "?managed_quiz=" . $id;
                
                $check = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE quiz_id = ?");
                $check->execute([$id]);
                if ($check->fetchColumn() < 10 && $status == 1) {
                    throw new Exception("Quizzes need at least 10 questions to be published.");
                }

                $stmt = $pdo->prepare("UPDATE quizzes SET is_published = ? WHERE quiz_id = ?");
                $stmt->execute([$status, $id]);
                $message = $status == 1 ? "Mission is now LIVE! 🚀" : "Mission unpublished to Drafts.";
            }
            $success = true;
        }

        // --- Question Management ---
        elseif ($action === 'add_question' || $action === 'edit_question' || $action === 'delete_question') {
            $quiz_id = $_POST['quiz_id'];
            $redirect_params = "?managed_quiz=" . $quiz_id;

            if ($action === 'delete_question') {
                $id = $_POST['question_id'];
                $stmt = $pdo->prepare("DELETE FROM questions WHERE question_id = ?");
                $stmt->execute([$id]);
                $message = "Question removed!";
            } else {
                $text = trim($_POST['question_text']);
                $opt_a = trim($_POST['option_a']);
                $opt_b = trim($_POST['option_b']);
                $opt_c = trim($_POST['option_c']);
                $opt_d = trim($_POST['option_d']);
                $correct = $_POST['correct_option'];
                $sol_text = trim($_POST['solution_text']);
                
                $q_img = $_POST['cropped_question_image'] ?? null;
                $s_img = $_POST['cropped_solution_image'] ?? null;
                
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

                $q_img_name = processAdminImg($q_img, $existing_q_img);
                $s_img_name = processAdminImg($s_img, $existing_s_img);

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
            }
            $success = true;
        }

        if ($success) {
            $_SESSION['admin_msg'] = $message;
            header("Location: admin_dashboard.php" . $redirect_params);
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

// --- Data Fetching ---
$levels = $pdo->query("SELECT * FROM levels ORDER BY difficulty_rank ASC")->fetchAll();
$classes = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM users u WHERE u.class_id = c.class_id AND u.role = 'student') as student_count FROM classes c ORDER BY class_name ASC")->fetchAll();
$quizzes = $pdo->query("SELECT q.*, l.level_name, (SELECT COUNT(*) FROM questions qn WHERE qn.quiz_id = q.quiz_id) as q_count FROM quizzes q JOIN levels l ON q.level_id = l.level_id ORDER BY q.created_at DESC")->fetchAll();

// Advanced Question Analytics
$question_stats = $pdo->query("
    SELECT qn.question_id, qn.question_text, q.quiz_name,
    COUNT(ua.answer_id) as total_responses,
    SUM(ua.is_correct) as correct_count
    FROM questions qn
    JOIN quizzes q ON qn.quiz_id = q.quiz_id
    LEFT JOIN user_answers ua ON qn.question_id = ua.question_id
    GROUP BY qn.question_id
    HAVING total_responses > 0
    ORDER BY q.quiz_id ASC, qn.question_id ASC
")->fetchAll();

// Class Intelligence Metrics
$class_metrics = $pdo->query("
    SELECT 
        c.class_id, 
        c.class_name,
        (SELECT COUNT(*) FROM users u WHERE u.class_id = c.class_id AND u.role = 'student') as total_students,
        (SELECT COUNT(DISTINCT qa.user_id) FROM quiz_attempts qa JOIN users u ON qa.user_id = u.user_id WHERE u.class_id = c.class_id) as active_students,
        AVG(CASE WHEN qa.total_questions > 0 THEN (qa.score / qa.total_questions * 100) ELSE 0 END) as avg_accuracy,
        AVG(qa.time_taken_seconds) as avg_time
    FROM classes c
    LEFT JOIN users u ON u.class_id = c.class_id
    LEFT JOIN quiz_attempts qa ON qa.user_id = u.user_id
    GROUP BY c.class_id, c.class_name
")->fetchAll();

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
        .kahoot-green { background-color: #26890c; }
        .kahoot-red { background-color: #e21b3c; }
        .kahoot-yellow { background-color: #ffa602; }
        .tab-btn.active { background-color: white; color: #46178f; box-shadow: 0 4px 0 0 #46178f; }
        #cropModal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 1000; align-items: center; justify-content: center; padding: 20px; }
        #notification { transition: opacity 0.5s ease-out; }
    </style>
</head>
<body class="pb-12">

    <!-- Cropper Modal -->
    <div id="cropModal">
        <div class="bg-white rounded-[2rem] p-8 w-full max-w-2xl">
            <h3 class="text-2xl font-extrabold mb-4 text-[#46178f]">Crop Asset</h3>
            <div class="overflow-hidden rounded-2xl bg-gray-100 mb-6 border-2 border-dashed border-gray-300">
                <img id="cropTarget" src="" class="max-w-full">
            </div>
            <div class="flex justify-end gap-4">
                <button onclick="closeCropModal()" class="px-8 py-3 rounded-2xl bg-gray-200 font-bold text-gray-600">Cancel</button>
                <button onclick="applyCrop()" class="px-8 py-3 rounded-2xl kahoot-purple text-white font-bold shadow-lg">Save Crop</button>
            </div>
        </div>
    </div>

    <nav class="kahoot-purple text-white shadow-xl p-5 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-2">
                <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center"><span class="text-[#46178f] font-black text-xl">P</span></div>
                <h1 class="text-2xl font-black italic tracking-tighter uppercase">PolyMath <span class="font-light text-fuchsia-300">Admin</span></h1>
            </div>
            <a href="logout.php" class="bg-red-500 hover:bg-red-600 px-6 py-2.5 rounded-xl text-sm font-black transition-all shadow-lg">LOGOUT</a>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto p-6 md:p-10">
        
        <?php if ($message): ?>
            <div id="notification" class="mb-8 p-5 rounded-[1.5rem] text-sm font-bold <?php echo $messageType === 'error' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?> border-2 shadow-sm animate-pulse">
                <?php echo ($messageType === 'error' ? '⚠️ ' : '✅ ') . $message; ?>
            </div>
            <script>setTimeout(() => { const n = document.getElementById('notification'); if(n) { n.style.opacity = '0'; setTimeout(() => n.remove(), 500); } }, 20000);</script>
        <?php endif; ?>

        <div id="navigation-tabs" class="flex bg-gray-200 p-2 rounded-2xl mb-10 w-fit mx-auto gap-2">
            <button onclick="showTab('quizzes')" class="tab-btn px-8 py-3 rounded-xl font-black text-sm active" id="tab-quizzes">QUIZZES</button>
            <button onclick="showTab('classes')" class="tab-btn px-8 py-3 rounded-xl font-black text-sm" id="tab-classes">CLASSES</button>
            <button onclick="showTab('analytics')" class="tab-btn px-8 py-3 rounded-xl font-black text-sm" id="tab-analytics">INTELLIGENCE</button>
        </div>

        <!-- QUIZZES TAB -->
        <section id="sect-quizzes" class="tab-content space-y-12">
            
            <div id="quiz-list-view" class="grid grid-cols-1 lg:grid-cols-3 gap-10">
                <!-- Create/Edit Form -->
                <div class="bg-white rounded-[2rem] p-8 shadow-xl border-b-8 border-indigo-600 h-fit">
                    <h2 class="text-2xl font-black mb-6 text-indigo-700" id="quiz-form-title">Create Quiz</h2>
                    <form action="admin_dashboard.php" method="POST" class="space-y-5">
                        <input type="hidden" name="action" value="create_quiz" id="quiz-action">
                        <input type="hidden" name="quiz_id" id="quiz-id">
                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase mb-2">Quiz Title</label>
                            <input type="text" name="quiz_name" id="quiz-name" required class="w-full px-5 py-3 rounded-2xl border-2 outline-none font-bold focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase mb-2">Difficulty</label>
                            <select name="level_id" id="quiz-level" class="w-full px-5 py-3 rounded-2xl border-2 font-bold">
                                <?php foreach($levels as $l): ?><option value="<?php echo $l['level_id']; ?>"><?php echo $l['level_name']; ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="p-4 bg-indigo-50 rounded-2xl border-2 border-indigo-100">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="is_timed" id="quiz-timed" class="w-5 h-5 accent-indigo-600" onchange="document.getElementById('limit-box').classList.toggle('hidden', !this.checked)">
                                <span class="text-sm font-black text-indigo-700">Enable Timer</span>
                            </label>
                            <div id="limit-box" class="hidden mt-4">
                                <label class="block text-[10px] font-black text-indigo-400 uppercase mb-1">Time Limit (Seconds)</label>
                                <input type="number" name="time_limit" id="quiz-limit" value="300" class="w-full px-4 py-2 rounded-xl border-2 font-bold">
                            </div>
                        </div>
                        <button type="submit" class="w-full kahoot-blue text-white py-4 rounded-2xl font-black shadow-lg uppercase tracking-widest">Save Quiz</button>
                        <button type="button" onclick="resetQuizForm()" id="quiz-cancel" class="hidden w-full text-xs font-bold text-gray-400 uppercase mt-2">Cancel Edit</button>
                    </form>
                </div>

                <!-- Table -->
                <div class="lg:col-span-2 bg-white rounded-[2rem] shadow-xl border-b-8 border-purple-700 overflow-hidden">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 text-[10px] uppercase font-black text-gray-400 tracking-widest">
                            <tr><th class="px-8 py-5">Mission</th><th class="px-8 py-5">Count</th><th class="px-8 py-5">Status</th><th class="px-8 py-5 text-right">Actions</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach($quizzes as $q): ?>
                            <tr class="hover:bg-gray-50 transition-all">
                                <td class="px-8 py-6 font-black text-gray-800"><?php echo htmlspecialchars($q['quiz_name']); ?></td>
                                <td class="px-8 py-6 font-black text-indigo-600"><?php echo $q['q_count']; ?>/10</td>
                                <td class="px-8 py-6">
                                    <?php if(isset($q['is_published']) && $q['is_published']): ?>
                                        <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-[10px] font-black uppercase">LIVE 🚀</span>
                                    <?php else: ?>
                                        <span class="bg-amber-100 text-amber-700 px-3 py-1 rounded-full text-[10px] font-black uppercase">DRAFT</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-8 py-6 text-right space-x-3">
                                    <button onclick='editQuiz(<?php echo json_encode($q); ?>)' class="text-indigo-600 font-black text-xs uppercase hover:underline">Edit</button>
                                    <form action="admin_dashboard.php" method="POST" class="inline" onsubmit="return confirm('Delete mission?')">
                                        <input type="hidden" name="action" value="delete_quiz"><input type="hidden" name="quiz_id" value="<?php echo $q['quiz_id']; ?>">
                                        <button type="submit" class="text-red-500 font-black text-xs uppercase hover:underline">Del</button>
                                    </form>
                                    <button onclick="manageQuestions(<?php echo $q['quiz_id']; ?>, '<?php echo addslashes($q['quiz_name']); ?>', <?php echo $q['q_count']; ?>, <?php echo isset($q['is_published']) ? $q['is_published'] : 0; ?>)" class="kahoot-purple text-white px-5 py-2.5 rounded-xl text-xs font-black shadow-md">QUESTIONS</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Focused Question Workspace -->
            <div id="question-workspace" class="hidden">
                <div class="bg-white rounded-[3rem] p-10 shadow-2xl border-4 border-[#46178f]">
                    <div class="flex justify-between items-center mb-10 pb-6 border-b-4 border-gray-50">
                        <div>
                            <h2 class="text-4xl font-black text-gray-800">Mission: <span id="managed-quiz-name" class="text-[#46178f]"></span></h2>
                            <p class="text-gray-400 font-bold uppercase tracking-widest text-[10px] mt-1">Content Editor</p>
                        </div>
                        <div class="flex gap-4">
                            <form action="admin_dashboard.php" method="POST" id="publish-toggle-form">
                                <input type="hidden" name="action" value="toggle_publish">
                                <input type="hidden" name="quiz_id" id="publish-quiz-id">
                                <input type="hidden" name="status" id="publish-status-val">
                                <button type="submit" id="publish-btn" class="hidden px-8 py-4 rounded-2xl font-black shadow-xl transition-all"></button>
                            </form>
                            <button onclick="exitWorkspace()" class="kahoot-yellow text-white px-8 py-4 rounded-2xl font-black shadow-xl transition-all hover:opacity-90">SAVE AS DRAFT</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 xl:grid-cols-3 gap-12">
                        <div class="space-y-6">
                            <div id="publish-status-widget" class="p-6 rounded-[2rem] border-2 text-center transition-all">
                                <span id="status-icon" class="text-4xl"></span>
                                <h4 id="status-text" class="font-black uppercase text-xs mt-2"></h4>
                                <div class="w-full bg-gray-200 h-2 rounded-full mt-4 overflow-hidden"><div id="status-bar" class="h-full transition-all duration-700"></div></div>
                                <p id="status-count" class="text-[10px] font-bold text-gray-400 mt-2 uppercase"></p>
                            </div>

                            <form action="admin_dashboard.php" id="q-form" method="POST" class="bg-gray-50 p-8 rounded-[2.5rem] space-y-5 border-2 border-gray-100">
                                <input type="hidden" name="action" value="add_question" id="q-action">
                                <input type="hidden" name="quiz_id" id="q-quiz-id">
                                <input type="hidden" name="question_id" id="q-id">
                                <input type="hidden" name="cropped_question_image" id="q-img-input">
                                <input type="hidden" name="cropped_solution_image" id="s-img-input">
                                <h3 class="text-lg font-black text-indigo-700" id="q-form-title">Add Challenge</h3>
                                <textarea name="question_text" id="q-text" placeholder="Write question..." required class="w-full px-5 py-4 rounded-2xl border-2 font-bold h-32 focus:border-indigo-500 outline-none"></textarea>
                                <div class="grid grid-cols-2 gap-3">
                                    <input type="text" name="option_a" id="q-a" placeholder="A" required class="px-4 py-3 rounded-xl border-2 font-bold text-red-600">
                                    <input type="text" name="option_b" id="q-b" placeholder="B" required class="px-4 py-3 rounded-xl border-2 font-bold text-blue-600">
                                    <input type="text" name="option_c" id="q-c" placeholder="C" required class="px-4 py-3 rounded-xl border-2 font-bold text-amber-500">
                                    <input type="text" name="option_d" id="q-d" placeholder="D" required class="px-4 py-3 rounded-xl border-2 font-bold text-green-600">
                                </div>
                                <select name="correct_option" id="q-correct" class="w-full px-5 py-3 rounded-2xl border-2 font-black text-indigo-600 bg-white">
                                    <option value="A">Answer A</option><option value="B">Answer B</option><option value="C">Answer C</option><option value="D">Answer D</option>
                                </select>
                                <div class="grid grid-cols-2 gap-4">
                                    <button type="button" onclick="document.getElementById('file-q').click()" class="p-4 bg-white rounded-2xl border-2 border-dashed flex flex-col items-center">
                                        <span class="text-[10px] font-black opacity-30">Q IMG</span>
                                        <input type="file" id="file-q" class="hidden" onchange="initCrop(this, 'q')">
                                        <div id="preview-q" class="h-12 hidden mt-2"><img src="" class="h-full rounded"></div>
                                    </button>
                                    <button type="button" onclick="document.getElementById('file-s').click()" class="p-4 bg-white rounded-2xl border-2 border-dashed flex flex-col items-center">
                                        <span class="text-[10px] font-black opacity-30">S IMG</span>
                                        <input type="file" id="file-s" class="hidden" onchange="initCrop(this, 's')">
                                        <div id="preview-s" class="h-12 hidden mt-2"><img src="" class="h-full rounded"></div>
                                    </button>
                                </div>
                                <textarea name="solution_text" id="q-sol" placeholder="Solution explanation..." class="w-full px-5 py-4 rounded-2xl border-2 font-bold h-24"></textarea>
                                <button type="submit" class="w-full kahoot-purple text-white py-4 rounded-2xl font-black shadow-xl uppercase tracking-widest">Save Question</button>
                                <button type="button" onclick="resetQForm()" id="q-cancel" class="hidden w-full text-[10px] font-black text-gray-400 uppercase mt-2">Discard</button>
                            </form>
                        </div>
                        <div class="xl:col-span-2 space-y-4 overflow-y-auto max-h-[900px] pr-4" id="questions-list"></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CLASSES TAB -->
        <section id="sect-classes" class="tab-content hidden space-y-10">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
                <div class="bg-white rounded-[2.5rem] p-8 shadow-xl border-b-8 border-green-600 h-fit">
                    <h2 class="text-2xl font-black mb-6 text-green-700" id="class-form-title">New Academic Group</h2>
                    <form action="admin_dashboard.php" method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="add_class" id="class-action">
                        <input type="hidden" name="class_id" id="class-id">
                        <input type="text" name="new_class_name" id="class-name" required placeholder="Group Name" class="w-full px-5 py-4 rounded-2xl border-2 font-bold focus:border-green-500 outline-none">
                        <button type="submit" class="w-full kahoot-green text-white py-4 rounded-2xl font-black shadow-lg">Save Class</button>
                        <button type="button" onclick="resetClassForm()" id="class-cancel-btn" class="hidden w-full text-xs font-bold text-gray-400 uppercase mt-2">Cancel</button>
                    </form>
                </div>
                <div class="lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach($classes as $c): ?>
                    <div class="bg-white rounded-[2rem] p-8 shadow-lg border border-gray-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-2xl font-black text-gray-800"><?php echo htmlspecialchars($c['class_name']); ?></h3>
                            <p class="text-xs font-black text-indigo-500 uppercase"><?php echo $c['student_count']; ?> Students</p>
                        </div>
                        <div class="flex gap-2">
                            <button onclick='editClass(<?php echo json_encode($c); ?>)' class="text-indigo-600 font-bold text-xs uppercase">Edit</button>
                            <form action="admin_dashboard.php" method="POST" onsubmit="return confirm('Delete group?')">
                                <input type="hidden" name="action" value="delete_class"><input type="hidden" name="class_id" value="<?php echo $c['class_id']; ?>">
                                <button type="submit" class="w-10 h-10 bg-red-50 text-red-500 rounded-xl flex items-center justify-center">🗑️</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- INTELLIGENCE TAB -->
        <section id="sect-analytics" class="tab-content hidden space-y-12">
            <div class="bg-white rounded-[3rem] p-12 shadow-2xl border-4 border-indigo-50">
                <h2 class="text-3xl font-black text-gray-800 italic uppercase mb-10">Intelligence Report</h2>
                <div class="space-y-16">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <?php foreach($class_metrics as $cm): 
                            $part_rate = ($cm['total_students'] > 0) ? round(($cm['active_students'] / $cm['total_students']) * 100) : 0;
                        ?>
                        <div class="bg-gray-50 rounded-[2.5rem] p-8 border-2 border-indigo-50 shadow-sm">
                            <h3 class="text-2xl font-black text-gray-800 mb-6"><?php echo htmlspecialchars($cm['class_name']); ?></h3>
                            <div class="grid grid-cols-2 gap-4 mb-8">
                                <div class="bg-white p-6 rounded-2xl border"><p class="text-[9px] font-black opacity-30 uppercase">Accuracy</p><p class="text-2xl font-black text-indigo-600"><?php echo round($cm['avg_accuracy'], 1); ?>%</p></div>
                                <div class="bg-white p-6 rounded-2xl border"><p class="text-[9px] font-black opacity-30 uppercase">Avg Speed</p><p class="text-2xl font-black text-amber-600"><?php echo round($cm['avg_time'], 0); ?>s</p></div>
                                <div class="bg-white p-6 rounded-2xl border"><p class="text-[9px] font-black opacity-30 uppercase">Engaged</p><p class="text-2xl font-black text-green-600"><?php echo $part_rate; ?>%</p></div>
                                <div class="bg-white p-6 rounded-2xl border"><p class="text-[9px] font-black opacity-30 uppercase">Enrolled</p><p class="text-2xl font-black"><?php echo $cm['total_students']; ?></p></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="bg-indigo-50 p-8 rounded-[2.5rem] border-2 border-indigo-100">
                        <h3 class="text-xl font-black mb-8 flex items-center gap-3">📊 Intelligence: Question Success Graph</h3>
                        <?php if (empty($question_stats)): ?>
                            <div class="text-center py-20 opacity-30 font-black uppercase">No Intelligence Data Collected</div>
                        <?php else: ?>
                            <div class="space-y-6">
                                <?php foreach($question_stats as $stat): 
                                    $acc = round(($stat['correct_count'] / $stat['total_responses']) * 100);
                                    $col = $acc > 70 ? 'bg-green-500' : ($acc > 40 ? 'bg-blue-500' : 'bg-red-500');
                                ?>
                                <div class="bg-white p-6 rounded-2xl shadow-sm">
                                    <div class="flex justify-between text-[10px] font-black uppercase text-gray-400 mb-2">
                                        <span><?php echo htmlspecialchars($stat['quiz_name']); ?></span>
                                        <span class="<?php echo str_replace('bg', 'text', $col); ?> font-black"><?php echo $acc; ?>% ACCURACY</span>
                                    </div>
                                    <p class="font-bold text-gray-700 mb-4 truncate">"<?php echo htmlspecialchars($stat['question_text']); ?>"</p>
                                    <div class="w-full h-3 bg-gray-100 rounded-full overflow-hidden shadow-inner"><div class="h-full <?php echo $col; ?> transition-all duration-1000" style="width: <?php echo $acc; ?>%"></div></div>
                                    <p class="text-[9px] font-bold text-gray-300 mt-2 uppercase"><?php echo $stat['total_responses']; ?> attempts recorded</p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <script>
        const urlParams = new URLSearchParams(window.location.search);
        const stickyQuizId = urlParams.get('managed_quiz');

        function showTab(tab) {
            document.querySelectorAll('.tab-content').forEach(s => s.classList.add('hidden'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('sect-' + tab).classList.remove('hidden');
            document.getElementById('tab-' + tab).classList.add('active');
            if(tab === 'quizzes') exitWorkspace();
        }

        function editQuiz(q) {
            document.getElementById('quiz-form-title').innerText = "Update Mission";
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
            document.getElementById('quiz-cancel').classList.add('hidden');
        }

        function editClass(c) {
            document.getElementById('class-form-title').innerText = "Edit Group";
            document.getElementById('class-action').value = "edit_class";
            document.getElementById('class-id').value = c.class_id;
            document.getElementById('class-name').value = c.class_name;
            document.getElementById('class-cancel-btn').classList.remove('hidden');
        }

        function resetClassForm() {
            document.getElementById('class-form-title').innerText = "New Academic Group";
            document.getElementById('class-action').value = "add_class";
            document.getElementById('class-id').value = "";
            document.getElementById('class-name').value = "";
            document.getElementById('class-cancel-btn').classList.add('hidden');
        }

        async function manageQuestions(quizId, quizName, qCount, isPublished) {
            document.getElementById('managed-quiz-name').innerText = quizName;
            document.getElementById('q-quiz-id').value = quizId;
            document.getElementById('publish-quiz-id').value = quizId;
            document.getElementById('quiz-list-view').classList.add('hidden');
            document.getElementById('navigation-tabs').classList.add('hidden');
            document.getElementById('question-workspace').classList.remove('hidden');
            
            const pubBtn = document.getElementById('publish-btn');
            const pubStatusVal = document.getElementById('publish-status-val');
            
            if (qCount >= 10) {
                pubBtn.classList.remove('hidden');
                if (isPublished) {
                    pubBtn.innerText = "UNPUBLISH TO DRAFTS";
                    pubBtn.className = "kahoot-red text-white px-8 py-4 rounded-2xl font-black shadow-xl transition-all hover:opacity-90";
                    pubStatusVal.value = 0;
                } else {
                    pubBtn.innerText = "PUBLISH MISSION LIVE";
                    pubBtn.className = "kahoot-green text-white px-8 py-4 rounded-2xl font-black shadow-xl transition-all hover:opacity-90";
                    pubStatusVal.value = 1;
                }
            } else {
                pubBtn.classList.add('hidden');
            }

            const newurl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?managed_quiz=' + quizId;
            window.history.pushState({path:newurl},'',newurl);

            resetQForm();
            refreshQuestionList(quizId);
        }

        function exitWorkspace() {
            document.getElementById('question-workspace').classList.add('hidden');
            document.getElementById('quiz-list-view').classList.remove('hidden');
            document.getElementById('navigation-tabs').classList.remove('hidden');
            const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
            window.history.pushState({path:cleanUrl},'',cleanUrl);
        }

        async function refreshQuestionList(quizId) {
            const list = document.getElementById('questions-list');
            list.innerHTML = "<p class='p-20 text-center opacity-30 font-black uppercase'>Analyzing Data...</p>";
            try {
                const res = await fetch(`get_questions.php?quiz_id=${quizId}`);
                const data = await res.json();
                list.innerHTML = "";
                updatePublishWidget(data.length);
                if (data.length === 0) {
                    list.innerHTML = "<div class='p-20 border-4 border-dashed rounded-[3rem] text-center opacity-20 font-black uppercase'>Quiz has no questions yet.</div>";
                    return;
                }
                data.forEach((q, i) => {
                    list.innerHTML += `
                        <div class="bg-white p-8 rounded-[2.5rem] border-2 shadow-sm flex flex-col md:flex-row gap-8 hover:border-indigo-400 transition-all">
                            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-700 flex items-center justify-center font-black text-xl flex-shrink-0">${i+1}</div>
                            <div class="flex-grow">
                                <p class="font-black text-gray-800 text-lg mb-6">${q.question_text}</p>
                                <div class="flex gap-4">
                                    <button onclick='loadQuestionForEdit(${JSON.stringify(q).replace(/'/g, "&apos;")})' class="bg-indigo-600 text-white px-6 py-2 rounded-xl text-[10px] font-black uppercase">Edit</button>
                                    <form action="admin_dashboard.php" method="POST" class="inline">
                                        <input type="hidden" name="action" value="delete_question"><input type="hidden" name="quiz_id" value="${quizId}"><input type="hidden" name="question_id" value="${q.question_id}">
                                        <button type="submit" class="bg-red-50 text-red-500 px-6 py-2 rounded-xl text-[10px] font-black uppercase" onclick="return confirm('Delete?')">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    `;
                });
            } catch (e) { list.innerHTML = "Sync Error."; }
        }

        function updatePublishWidget(count) {
            const widget = document.getElementById('publish-status-widget');
            const bar = document.getElementById('status-bar');
            const icon = document.getElementById('status-icon');
            const txt = document.getElementById('status-text');
            const countTxt = document.getElementById('status-count');
            const pubBtn = document.getElementById('publish-btn');
            const pct = Math.min((count / 10) * 100, 100);
            bar.style.width = pct + '%';
            countTxt.innerText = `${count} / 10 Challenges Added`;
            if(count >= 10) {
                widget.className = "p-6 rounded-[2rem] border-2 border-green-500 bg-green-50 text-center";
                icon.innerText = "🚀"; txt.innerText = "READY TO GO LIVE";
                bar.className = "h-full bg-green-500 rounded-full";
                pubBtn.classList.remove('hidden');
            } else {
                widget.className = "p-6 rounded-[2rem] border-2 border-amber-500 bg-amber-50 text-center";
                icon.innerText = "🛠️"; txt.innerText = "DRAFT MODE";
                bar.className = "h-full bg-amber-500 rounded-full";
                pubBtn.classList.add('hidden');
            }
        }

        function loadQuestionForEdit(q) {
            document.getElementById('q-form-title').innerText = "Edit Challenge";
            document.getElementById('q-action').value = "edit_question";
            document.getElementById('q-id').value = q.question_id;
            document.getElementById('q-text').value = q.question_text;
            document.getElementById('q-a').value = q.option_a;
            document.getElementById('q-b').value = q.option_b;
            document.getElementById('q-c').value = q.option_c;
            document.getElementById('q-d').value = q.option_d;
            document.getElementById('q-correct').value = q.correct_option;
            document.getElementById('q-sol').value = q.solution_text;
            document.getElementById('q-cancel').classList.remove('hidden');
            document.getElementById('q-form').scrollIntoView({behavior: 'smooth'});
        }

        function resetQForm() {
            document.getElementById('q-form-title').innerText = "Add Challenge";
            document.getElementById('q-action').value = "add_question";
            document.getElementById('q-id').value = "";
            document.getElementById('q-text').value = "";
            document.getElementById('q-a').value = ""; document.getElementById('q-b').value = "";
            document.getElementById('q-c').value = ""; document.getElementById('q-d').value = "";
            document.getElementById('q-img-input').value = "";
            document.getElementById('s-img-input').value = "";
            document.getElementById('preview-q').classList.add('hidden');
            document.getElementById('preview-s').classList.add('hidden');
            document.getElementById('q-cancel').classList.add('hidden');
        }

        let cropper; let currentImgType;
        function initCrop(input, type) {
            if (input.files && input.files[0]) {
                currentImgType = type;
                const reader = new FileReader();
                reader.onload = (e) => {
                    document.getElementById('cropTarget').src = e.target.result;
                    document.getElementById('cropModal').style.display = 'flex';
                    if(cropper) cropper.destroy();
                    cropper = new Cropper(document.getElementById('cropTarget'), {viewMode: 1});
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
            closeCropModal();
        }

        window.onload = function() {
            if (stickyQuizId) {
                const rows = document.querySelectorAll('tbody tr');
                let foundData = { name: "Mission", count: 0, pub: 0 };
                rows.forEach(r => {
                    if (r.innerHTML.includes(`manageQuestions(${stickyQuizId}`)) {
                        foundData.name = r.querySelector('td:first-child').innerText;
                        foundData.count = parseInt(r.querySelector('td:nth-child(2)').innerText);
                        foundData.pub = r.innerHTML.includes('LIVE') ? 1 : 0;
                    }
                });
                manageQuestions(stickyQuizId, foundData.name, foundData.count, foundData.pub);
            }
        };
    </script>
</body>
</html>