<?php
session_start();

require_once '../config/config.php';

header('Content-Type: application/json');

$currentUser = $_SESSION['user_id'];

$inviteId = $_POST['id'] ?? 0;

try
{
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";
        dbname=".DB_NAME.";
        charset=".DB_CHARSET,
        DB_USER,
        DB_PASS
    );

    /*
        đổi trạng thái
    */

    $stmt = $pdo->prepare("
        UPDATE battle_invites
        SET status='accepted'
        WHERE id=:invite
    ");

    $stmt->execute([
        'invite' => $inviteId
    ]);

    /*
        lấy room
    */

    $stmt = $pdo->prepare("
        SELECT room_id
        FROM battle_invites
        WHERE id=:invite
    ");

    $stmt->execute([
        'invite' => $inviteId
    ]);

    $invite = $stmt->fetch();

    /*
        thêm người chơi
    */

    $stmt = $pdo->prepare("
        INSERT INTO battle_participants(
            room_id,
            user_id
        )
        VALUES(
            :room,
            :user
        )
    ");

    $stmt->execute([
        'room' => $invite['room_id'],
        'user' => $currentUser
    ]);

    echo json_encode([
        'success' => true,
        'room_id' => $invite['room_id']
    ]);
}
catch(PDOException $e)
{
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}