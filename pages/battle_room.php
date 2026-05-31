<?php

session_start();

require_once '../config/config.php';

if(!isset($_GET['room_id']))
{
    die("Thiếu room_id");
}

$currentQuestion =
    isset($_GET['question'])
    ?
    (int)$_GET['question']
    :
    1;

$roomId = (int)$_GET['room_id'];

try
{
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";
        dbname=".DB_NAME.";
        charset=".DB_CHARSET,
        DB_USER,
        DB_PASS
    );

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

}
catch(PDOException $e)
{
    die($e->getMessage());
}

$stmt = $pdo->prepare("
    SELECT *
    FROM battle_rooms
    WHERE room_id = ?
");

$stmt->execute([
    $roomId
]);

$room = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$room)
{
    die("Không tìm thấy phòng");
}

$examSetId = $room['exam_set_id'];

$stmtQuestion = $pdo->prepare("
    SELECT *
    FROM cau_hoi
    WHERE bo_de_id = ?
    ORDER BY id
");

$stmtQuestion->execute([
    $examSetId
]);

$questions =
    $stmtQuestion->fetchAll(
        PDO::FETCH_ASSOC
    );

$totalQuestions =
    count($questions);

if(
    $currentQuestion >
    $totalQuestions
)
{
    die("Đã hoàn thành trận đấu");
}

$question =
    $questions[
        $currentQuestion - 1
    ];

$nextQuestionUrl = "";

if(
    $currentQuestion
    <
    $totalQuestions
)
{
    $nextQuestionUrl =
        "battle_room.php?room_id="
        . $roomId
        . "&question="
        . ($currentQuestion + 1);
}

if(count($questions) == 0)
{
    die("Bộ đề chưa có câu hỏi");
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sàn Đấu Battle Room #<?= $roomId ?> - The Bunny</title>

    <!-- Bootstrap 5.3.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <!-- Google Font (Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Stylesheets -->
    <link href="../assets/css/root.css" rel="stylesheet">
    <link href="../assets/css/thach_dau.css" rel="stylesheet">
    <link href="../assets/css/battle_room.css" rel="stylesheet">

    <!-- Battle Room AJAX Javascript File -->
    <script src="../assets/js/battle_room.js"></script>

    <!-- Visual selection helper (adds visual 'selected' class alongside original logic) -->
    <script>
        function selectAnswerWithVisual(buttonElement, answerLetter) {
            // 1. Run your original AJAX logic safely
            selectAnswer(answerLetter);
            
            // 2. Apply styling visual selections
            document.querySelectorAll('.mcq-option').forEach(btn => {
                btn.classList.remove('selected');
            });
            buttonElement.classList.add('selected');
        }
    </script>
</head>
<body data-next-question="<?= $nextQuestionUrl ?>">

    <!-- 1. MAIN NAVBAR -->
    <nav class="navbar-bunny d-flex align-items-center justify-content-between px-3 px-md-4">
        <div class="d-flex align-items-center gap-4">
            <a href="trang-chu.php" class="brand-logo text-decoration-none"><i class="fa-solid fa-carrot"></i> THE BUNNY</a>
            <div class="search-bar d-none d-md-flex">
                <i class="fa-solid fa-search text-muted"></i>
                <input type="text" placeholder="Tìm kiếm tài liệu, bạn học..." />
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <a href="../thach-dau.php" class="btn-icon d-none d-md-flex text-decoration-none" title="Sàn đấu">
                <i class="fa-solid fa-khanda"></i>
            </a>
            <a href="../tin-nhan.php" class="btn-icon text-decoration-none" title="Tin nhắn">
                <i class="fa-brands fa-facebook-messenger"></i>
            </a>
            <a href="../notifications.php" class="btn-icon text-decoration-none" title="Thông báo">
                <i class="fa-solid fa-bell"></i>
            </a>
            <div class="d-flex align-items-center gap-2 bg-light px-3 py-1 rounded-pill border d-none d-md-flex">
                <i class="fa-solid fa-fire text-danger"></i>
                <span class="fw-bold fs-6">15</span>
            </div>
            <a href="trang-ca-nhan.php">
                <img src="https://i.pravatar.cc/150?img=12" class="rounded-circle border cursor-pointer" width="40" height="40" alt="Profile">
            </a>
        </div>
    </nav>

    <!-- 2. ROOM TOOLBAR -->
    <div class="room-toolbar">
        <div class="d-flex align-items-center gap-2 gap-md-3">
            <span class="fw-bold text-dark"><i class="fa-solid fa-bolt text-warning me-2"></i>Trận đấu Thách đấu #<?= $roomId ?></span>
            <span class="badge bg-primary bg-opacity-10 text-primary py-2 px-3 rounded-pill fw-bold">
                Câu hỏi <?= $currentQuestion ?> / <?= $totalQuestions ?>
            </span>
        </div>

        <div class="d-flex align-items-center gap-3">
            <div class="d-flex align-items-center gap-2">
                <span class="small fw-bold text-muted"><i class="fa-solid fa-circle text-danger animated pulse me-1"></i> Trực tiếp</span>
            </div>
        </div>
    </div>

    <!-- 3. MAIN WORKSPACE -->
    <main class="container-fluid flex-grow-1 p-3 overflow-hidden d-flex flex-column" id="main-workspace">
        <div class="row g-3 h-100 flex-nowrap flex-lg-wrap">
           
            <!-- Left Pane: Problem Details -->
            <div class="col-lg-6 h-100 mobile-stack-pane">
                <div class="bg-white rounded-4 border shadow-sm d-flex flex-column h-100 overflow-hidden">
                    <div class="pane-header d-flex align-items-center px-3 gap-3">
                        <div class="pane-tab active"><i class="fa-regular fa-file-lines me-2"></i>Nội dung câu hỏi</div>
                    </div>
                   
                    <div class="pane-content">
                        <h4 class="fw-bold mb-3">Yêu cầu đề bài</h4>
                       
                        <div class="d-flex gap-2 mb-4 flex-wrap">
                            <span class="badge bg-danger bg-opacity-10 text-danger py-2 px-3 rounded-pill">Trận đấu trực tiếp</span>
                            <span class="badge bg-light text-dark border py-2 px-3 rounded-pill">
                                <i class="fa-solid fa-hashtag me-1 text-muted"></i> ID câu hỏi: <?= $question['id'] ?>
                            </span>
                        </div>

                        <div class="reading-context">
                            <p class="m-0 fs-5 fw-semibold text-dark lh-base">
                                <?= htmlspecialchars($question['noi_dung']) ?>
                            </p>
                        </div>

                        <div class="bg-light p-4 rounded d-flex justify-content-center align-items-center mb-3">
                            <i class="fa-solid fa-graduation-cap text-secondary" style="font-size: 56px;"></i>
                        </div>
                        <p class="text-center text-muted small fw-bold mb-4">Hệ Thống Đấu Trường Học Tập Bunny</p>

                        <p class="fw-bold text-warning"><i class="fa-solid fa-circle-exclamation me-2"></i>Lưu ý: Bạn chỉ được chọn đáp án một lần duy nhất.</p>
                    </div>
                </div>
            </div>

            <!-- Right Pane: Answers and Live Status -->
            <div class="col-lg-6 h-100 mobile-stack-pane">
                <div class="rounded-4 border shadow-sm d-flex flex-column h-100 overflow-hidden" style="background-color: var(--bg-editor);">
                    <div class="pane-header bg-white d-flex align-items-center px-3 gap-3">
                        <div class="pane-tab active"><i class="fa-solid fa-pen-to-square me-2"></i>Chọn đáp án đúng</div>
                    </div>
                   
                    <div class="pane-content position-relative">
                        <h6 class="fw-bold mb-4 text-dark lh-base">Bấm chọn một trong các phương án:</h6>
                       
                        <!-- Keep question-container exact wrapping layout so JS operates safely -->
                        <div id="question-container">
                            <input type="hidden" id="room_id" value="<?= $roomId ?>">
                            <input type="hidden" id="question_id" value="<?= $question['id'] ?>">

                            <!-- Option A -->
                            <button class="mcq-option answer-btn" type="button" onclick="selectAnswerWithVisual(this, 'A')">
                                <span class="option-letter">A</span>
                                <span class="fw-semibold"><?= htmlspecialchars($question['lua_chon_a']) ?></span>
                            </button>

                            <!-- Option B -->
                            <button class="mcq-option answer-btn" type="button" onclick="selectAnswerWithVisual(this, 'B')">
                                <span class="option-letter">B</span>
                                <span class="fw-semibold"><?= htmlspecialchars($question['lua_chon_b']) ?></span>
                            </button>

                            <!-- Option C -->
                            <button class="mcq-option answer-btn" type="button" onclick="selectAnswerWithVisual(this, 'C')">
                                <span class="option-letter">C</span>
                                <span class="fw-semibold"><?= htmlspecialchars($question['lua_chon_c']) ?></span>
                            </button>

                            <!-- Option D -->
                            <button class="mcq-option answer-btn" type="button" onclick="selectAnswerWithVisual(this, 'D')">
                                <span class="option-letter">D</span>
                                <span class="fw-semibold"><?= htmlspecialchars($question['lua_chon_d']) ?></span>
                            </button>
                        </div>

                        <!-- Results container for AJAX updates -->
                        <div id="result"></div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Bootstrap Javascript Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>