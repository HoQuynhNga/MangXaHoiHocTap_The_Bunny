<?php
session_start();

require_once '../config/config.php';

header('Content-Type: application/json');

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

    $stmt = $pdo->prepare("
        UPDATE battle_invites
        SET status='declined'
        WHERE id=:invite
    ");

    $stmt->execute([
        'invite' => $inviteId
    ]);

    echo json_encode([
        'success' => true
    ]);
}
catch(PDOException $e)
{
    echo json_encode([
        'success' => false
    ]);
}