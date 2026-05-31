<?php

session_start();

require_once '../config/config.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id']))
{
    echo json_encode([
        'success' => false,
        'message' => 'Bạn chưa đăng nhập'
    ]);
    exit;
}

$userId =
    $_SESSION['user_id'];

$roomId =
    $_POST['room_id'] ?? 0;

$questionId =
    $_POST['question_id'] ?? 0;

$answer =
    $_POST['answer'] ?? '';

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

    //-----------------------------------
    // CHECK DUPLICATE ANSWER
    //-----------------------------------

    $stmtCheck = $pdo->prepare("
        SELECT id
        FROM battle_answers
        WHERE room_id = ?
        AND user_id = ?
        AND question_id = ?
    ");

    $stmtCheck->execute([
        $roomId,
        $userId,
        $questionId
    ]);

    if($stmtCheck->fetch())
    {
        echo json_encode([
            'success' => false,
            'message' => 'Bạn đã trả lời câu hỏi này'
        ]);
        exit;
    }

    //-----------------------------------
    // GET CORRECT ANSWER
    //-----------------------------------

    $stmtQuestion = $pdo->prepare("
        SELECT dap_an_dung
        FROM cau_hoi
        WHERE id = ?
    ");

    $stmtQuestion->execute([
        $questionId
    ]);

    $question =
        $stmtQuestion->fetch(
            PDO::FETCH_ASSOC
        );

    if(!$question)
    {
        echo json_encode([
            'success' => false,
            'message' => 'Không tìm thấy câu hỏi'
        ]);
        exit;
    }

    //-----------------------------------
    // CALCULATE CORRECTNESS
    //-----------------------------------

    $isCorrect = 0;

    if(
        strtoupper(trim($answer))
        ==
        strtoupper(trim($question['dap_an_dung']))
    )
    {
        $isCorrect = 1;
    }

    //-----------------------------------
    // SAVE ANSWER
    //-----------------------------------

    $stmtQuestion = $pdo->prepare("
    SELECT dap_an_dung
    FROM cau_hoi
    WHERE id = ?
    ");

    $stmtQuestion->execute([
        $questionId
    ]);

    $question =
        $stmtQuestion->fetch(
            PDO::FETCH_ASSOC
        );

    $stmtInsert = $pdo->prepare("
        INSERT INTO battle_answers
        (
            room_id,
            user_id,
            question_id,
            answer,
            is_correct
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?
        )
    ");

    $stmtInsert->execute([
        $roomId,
        $userId,
        $questionId,
        $answer,
        $isCorrect
    ]);

    //-----------------------------------
    // SUCCESS
    //-----------------------------------

    echo json_encode([
        'success' => true,
        'correct' => $isCorrect,
        'message' => 'Đã lưu đáp án'
    ]);
}
catch(PDOException $e)
{
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}