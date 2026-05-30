<?php
session_start();

require_once '../config/config.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id']))
{
    echo json_encode([
        'success' => false
    ]);
    exit;
}

$currentUser = $_SESSION['user_id'];

try
{
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";
        dbname=".DB_NAME.";
        charset=".DB_CHARSET,
        DB_USER,
        DB_PASS
    );

    $sql = "
        SELECT
            bi.id,
            bi.room_id,
            u.username AS sender_name
        FROM battle_invites bi

        JOIN users u
            ON bi.sender_id = u.id

        WHERE bi.receiver_id = :receiver
        AND bi.status = 'pending'

        ORDER BY bi.id DESC

        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        'receiver' => $currentUser
    ]);

    $invite = $stmt->fetch(PDO::FETCH_ASSOC);

    if($invite)
    {
        echo json_encode([
            'success' => true,
            'invite' => $invite
        ]);
    }
    else
    {
        echo json_encode([
            'success' => false
        ]);
    }
}
catch(PDOException $e)
{
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}