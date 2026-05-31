<?php
session_start();
require_once '../config/config.php';

/**
 * KẾT NỐI DATABASE
 */
try {

    $dsn = "mysql:host=" . DB_HOST .
        ";dbname=" . DB_NAME .
        ";charset=" . DB_CHARSET;

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        $options
    );

} catch (PDOException $e) {

    die(
        "Không thể kết nối database: " .
        $e->getMessage()
    );
}

/**
 * KIỂM TRA ĐĂNG NHẬP
 */
if (!isset($_SESSION['user_id'])) {
    header("Location: dang-nhap.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$user_avatar         = "../assets/img/default-avatar.jpg";
$stats_xp            = 0;

try {

    /**
     * QUERY THÔNG BÁO
     */
    $sql = "

    (
        SELECT 
            lt.created_at,
            u.username,
            u.id AS actor_id,
            'like' AS type,
            bd.id AS target_id,
            CONCAT(
                u.username,
                ' đã thích bài viết của bạn'
            ) AS message

        FROM luot_thich lt

        INNER JOIN bai_dang bd
            ON lt.bai_dang_id = bd.id

        INNER JOIN users u
            ON lt.user_id = u.id

        WHERE bd.user_id = ?
        AND lt.user_id != ?
    )

    UNION ALL

    (
        SELECT 
            bl.created_at,
            u.username,
            u.id AS actor_id,
            'comment' AS type,
            bd.id AS target_id,
            CONCAT(
                u.username,
                ' đã bình luận bài viết của bạn'
            ) AS message

        FROM binh_luan bl

        INNER JOIN bai_dang bd
            ON bl.bai_dang_id = bd.id

        INNER JOIN users u
            ON bl.user_id = u.id

        WHERE bd.user_id = ?
        AND bl.user_id != ?
    )

    UNION ALL

    (
        SELECT 
            cs.created_at,
            u.username,
            u.id AS actor_id,
            'share' AS type,
            bd.id AS target_id,
            CONCAT(
                u.username,
                ' đã chia sẻ bài viết của bạn'
            ) AS message

        FROM luot_chia_se cs

        INNER JOIN bai_dang bd
            ON cs.bai_dang_id = bd.id

        INNER JOIN users u
            ON cs.user_id = u.id

        WHERE bd.user_id = ?
        AND cs.user_id != ?
    )

    UNION ALL

    (
        SELECT 
            bct.created_at,
            u.username,
            u.id AS actor_id,
            'friend_request' AS type,
            0 AS target_id,
            CONCAT(
                u.username,
                ' đã gửi lời mời Bạn cùng tiến'
            ) AS message

        FROM ban_cung_tien bct

        INNER JOIN users u
            ON bct.user_id = u.id

        WHERE bct.friend_user_id = ?
        AND bct.status = 'Pending'
    )

    ORDER BY created_at DESC
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([

        // Like
        $current_user_id,
        $current_user_id,

        // Comment
        $current_user_id,
        $current_user_id,

        // Share
        $current_user_id,
        $current_user_id,

        // Friend request
        $current_user_id
    ]);

    $notifications = $stmt->fetchAll();

} catch (PDOException $e) {

    die(
        "Lỗi truy vấn thông báo: " .
        $e->getMessage()
    );
}

/**
 * FORMAT THỜI GIAN
 */
function timeAgo($datetime)
{
    $time = strtotime($datetime);

    if (!$time) {
        return "Không xác định";
    }

    $diff = time() - $time;

    if ($diff < 60) {
        return "Vừa xong";
    }

    if ($diff < 3600) {
        return floor($diff / 60)
            . " phút trước";
    }

    if ($diff < 86400) {
        return floor($diff / 3600)
            . " giờ trước";
    }

    if ($diff < 604800) {
        return floor($diff / 86400)
            . " ngày trước";
    }

    return date(
        "d/m/Y H:i",
        $time
    );
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Thông báo</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body{
            background:#f0f2f5;
        }

        .notification-wrapper{
            max-width:800px;
            margin:30px auto;
        }

        .notification-item{
            background:#fff;
            border-radius:12px;
            padding:16px;
            margin-bottom:12px;
            display:flex;
            align-items:center;
            text-decoration:none;
            color:#000;
            transition:.2s;
            border:1px solid #ddd;
        }

        .notification-item:hover{
            background:#f5f5f5;
            transform:translateY(-2px);
        }

        .avatar{
            width:55px;
            height:55px;
            border-radius:50%;
            background:#e4e6eb;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:24px;
            margin-right:15px;
            flex-shrink:0;
        }

        .notification-content{
            flex:1;
        }

        .notification-title{
            font-size:16px;
            margin-bottom:4px;
        }

        .notification-time{
            font-size:14px;
            color:#65676B;
        }

        .empty-box{
            background:#fff;
            padding:50px;
            border-radius:12px;
            text-align:center;
        }

    </style>

</head>

<body>
<nav class="sticky-top" style="z-index: 1030;">
        <?php include '../includes/header.php'; ?>
</nav>

<div class="container notification-wrapper">


    <?php if (!empty($notifications)): ?>

        <?php foreach ($notifications as $noti): ?>

            <?php

            /**
             * LINK
             */
            $link = "#";

            if (
                in_array(
                    $noti['type'],
                    ['like', 'comment', 'share']
                )
            ) {

                $link =
                    "chi-tiet-bai-dang.php?id="
                    . $noti['target_id'];
            }

            if (
                $noti['type']
                === 'friend_request'
            ) {

                $link =
                    "ban-cung-tien.php";
            }

            /**
             * ICON
             */
            $icon = "👤";

            switch ($noti['type']) {

                case 'like':
                    $icon = "❤️";
                    break;

                case 'comment':
                    $icon = "💬";
                    break;

                case 'share':
                    $icon = "📤";
                    break;

                case 'friend_request':
                    $icon = "🤝";
                    break;
            }

            ?>

            <a
                href="<?= htmlspecialchars($link) ?>"
                class="notification-item"
            >

                <div class="avatar">
                    <?= $icon ?>
                </div>

                <div class="notification-content">

                    <div class="notification-title">

                        <strong>
                            <?= htmlspecialchars(
                                $noti['username']
                            ) ?>
                        </strong>

                        <?= htmlspecialchars(
                            str_replace(
                                $noti['username'],
                                '',
                                $noti['message']
                            )
                        ) ?>

                    </div>

                    <div class="notification-time">

                        <?= timeAgo(
                            $noti['created_at']
                        ) ?>

                    </div>

                </div>

            </a>

        <?php endforeach; ?>

    <?php else: ?>

        <div class="empty-box">

            <h5>
                Chưa có thông báo nào
            </h5>

        </div>

    <?php endif; ?>

</div>

</body>
</html>
