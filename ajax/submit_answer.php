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
    // GET QUESTION DETAILS (Queries option letters and text for database compatibility)
    //-----------------------------------

    $stmtQuestion = $pdo->prepare("
        SELECT dap_an_dung, lua_chon_a, lua_chon_b, lua_chon_c, lua_chon_d
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
    $submittedLetter = strtoupper(trim($answer)); // e.g. "A", "B", "C", "D"
    $dbCorrectAnswer = trim($question['dap_an_dung']); // e.g. "B" or full text "Jean Valjean"

    // Rule 1: Match if the database stores the single letter directly (A, B, C, D)
    if ($submittedLetter === strtoupper($dbCorrectAnswer)) {
        $isCorrect = 1;
    } 
    // Rule 2: Match if the database stores the actual choice text
    else {
        $choiceText = '';
        if ($submittedLetter === 'A') $choiceText = $question['lua_chon_a'];
        elseif ($submittedLetter === 'B') $choiceText = $question['lua_chon_b'];
        elseif ($submittedLetter === 'C') $choiceText = $question['lua_chon_c'];
        elseif ($submittedLetter === 'D') $choiceText = $question['lua_chon_d'];

        if (strtoupper(trim($choiceText)) === strtoupper($dbCorrectAnswer)) {
            $isCorrect = 1;
        }
    }

    //-----------------------------------
    // SAVE ANSWER
    //-----------------------------------

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