<?php

session_start();

require_once '../config/config.php';

header('Content-Type: application/json');

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

    $hostId =
        $_SESSION['user_id'];

    $opponentId =
        $_POST['opponent_id'];

    $examSetId =
        $_POST['exam_set_id'];

    //------------------------------------------------
    // CREATE ROOM
    //------------------------------------------------

    $stmt = $pdo->prepare("
        INSERT INTO battle_rooms
        (
            host_id,
            exam_set_id,
            status
        )
        VALUES
        (
            ?,
            ?,
            'waiting'
        )
    ");

    $stmt->execute([
        $hostId,
        $examSetId
    ]);

    $roomId =
        $pdo->lastInsertId();

    //------------------------------------------------
    // CREATE INVITE
    //------------------------------------------------

    $stmtInvite = $pdo->prepare("
        INSERT INTO battle_invites
        (
            id,
            sender_id,
            receiver_id
        )
        VALUES
        (
            ?,
            ?,
            ?
        )
    ");

    $stmtInvite->execute([
        $roomId,
        $hostId,
        $opponentId
    ]);

    echo json_encode([
        'success' => true,
        'room_id' => $roomId,
        'message' =>
            'Đã gửi lời mời thách đấu'
    ]);
}
catch(Exception $e)
{
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}