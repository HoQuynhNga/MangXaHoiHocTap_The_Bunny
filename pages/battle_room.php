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

    <title>Battle Room</title>

    <script src="../assets/js/battle_room.js"></script>

</head>
<body
    data-next-question="<?= $nextQuestionUrl ?>"
>
<h2>
    Battle Room #<?= $roomId ?>
</h2>

<div id="question-container">

<input
    type="hidden"
    id="room_id"
    value="<?= $roomId ?>"
>

<input
    type="hidden"
    id="question_id"
    value="<?= $question['id'] ?>"
>

<h3>

<?= htmlspecialchars(
    $question['noi_dung']
) ?>

</h3>

<button
    class="answer-btn"
    onclick="selectAnswer('A')"
>
    A.
    <?= htmlspecialchars($question['lua_chon_a']) ?>
</button>

<br><br>

<button
    class="answer-btn"
    onclick="selectAnswer('B')"
>
    B.
    <?= htmlspecialchars($question['lua_chon_b']) ?>
</button>

<br><br>

<button
    class="answer-btn"
    onclick="selectAnswer('C')"
>
    C.
    <?= htmlspecialchars($question['lua_chon_c']) ?>
</button>

<br><br>

<button
    class="answer-btn"
    onclick="selectAnswer('D')"
>
    D.
    <?= htmlspecialchars($question['lua_chon_d']) ?>
</button>

</div>

<div id="result"></div>

</body>
</html>

