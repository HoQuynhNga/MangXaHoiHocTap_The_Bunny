<?php

session_start();

require_once '../config/config.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id']))
{
    echo json_encode([
        'success' => false,
        'message' => 'Chưa đăng nhập'
    ]);
    exit;
}

$currentUser =
    $_SESSION['user_id'];

$roomId =
    $_GET['room_id'] ?? 0;

$questionId =
    $_GET['question_id'] ?? 0;

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

    //------------------------------------------------
    // LẤY TẤT CẢ CÂU TRẢ LỜI
    //------------------------------------------------

    $stmt = $pdo->prepare("
        SELECT
            user_id,
            answer,
            is_correct
        FROM battle_answers
        WHERE room_id = ?
        AND question_id = ?
    ");

    $stmt->execute([
        $roomId,
        $questionId
    ]);

    $answers =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    //------------------------------------------------
    // CHƯA ĐỦ 2 NGƯỜI
    //------------------------------------------------

    if(count($answers) < 2)
    {
        echo json_encode([
            'success' => false
        ]);

        exit;
    }

    //------------------------------------------------
    // TÌM ĐÁP ÁN CỦA MÌNH VÀ ĐỐI THỦ
    //------------------------------------------------

    $myAnswer = null;
    $opponentAnswer = null;

    foreach($answers as $row)
    {
        if($row['user_id'] == $currentUser)
        {
            $myAnswer =
                $row;
        }
        else
        {
            $opponentAnswer =
                $row;
        }
    }

    //------------------------------------------------
    // TRẢ KẾT QUẢ
    //------------------------------------------------

    echo json_encode([
        'success' => true,

        'my_answer' =>
            $myAnswer['answer'],

        'my_correct' =>
            $myAnswer['is_correct'],

        'opponent_answer' =>
            $opponentAnswer['answer'],

        'opponent_correct' =>
            $opponentAnswer['is_correct']
    ]);
}
catch(PDOException $e)
{
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}