<?php
session_start();
require_once '../config/config.php';
$user_avatar         = "../assets/img/default-avatar.jpg";
$stats_xp            = 0;

// Các hàm tiện ích (Copy từ file cũ sang để xài)
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;
    $string = array('y' => 'năm', 'm' => 'tháng', 'w' => 'tuần', 'd' => 'ngày', 'h' => 'giờ', 'i' => 'phút', 's' => 'giây');
    foreach ($string as $k => &$v) {
        if ($diff->$k) { $v = $diff->$k . ' ' . $v; } else { unset($string[$k]); }
    }
    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' trước' : 'Vừa xong';
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
$current_user_id = $_SESSION['user_id'] ?? 1;

// Kết nối CSDL
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    die("Lỗi CSDL: " . $e->getMessage());
}

// ---------------------------------------------------------
// BẮT ID TỪ URL VÀ KÉO ĐÚNG 1 BÀI VIẾT ĐÓ RA
// ---------------------------------------------------------
$post_id_url = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$posts_data = [];

if ($post_id_url > 0) {
    $sql_single_post = "
        SELECT 
            b.id AS post_id, b.noi_dung, b.created_at, u.username, u.giay_to_chung_minh,
            (SELECT COUNT(*) FROM luot_thich WHERE bai_dang_id = b.id) AS total_likes,
            (SELECT COUNT(*) FROM binh_luan WHERE bai_dang_id = b.id) AS total_comments,
            (SELECT COUNT(*) FROM luot_chia_se WHERE bai_dang_id = b.id) AS total_shares,
            (SELECT 1 FROM luot_thich WHERE bai_dang_id = b.id AND user_id = :uid) AS is_liked_by_me
        FROM bai_dang b 
        INNER JOIN users u ON b.user_id = u.id 
        WHERE b.id = :post_id
    ";
    $stmt = $pdo->prepare($sql_single_post);
    // Truyền ID bài viết lấy từ URL vào truy vấn
    $stmt->execute(['uid' => $current_user_id, 'post_id' => $post_id_url]);
    $posts_data = $stmt->fetchAll(); // Lấy về mảng chứa đúng 1 bài
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bài viết của Cộng đồng - The Bunny</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/trang-ca-nhan.css">
    <link href="../assets/css/responsive.css" rel="stylesheet" />
    <style>
        .btn-interaction { background-color: transparent; border: none; font-weight: bold; color: #6c757d; border-radius: 8px; transition: all 0.2s; }
        .btn-interaction:hover { background-color: #f1f3f5; color: #495057; }
        .btn-interaction.active-like { color: #0d6efd; } 
        .comment-box { background-color: #f8f9fa; border-radius: 12px; padding: 12px 16px; margin-bottom: 10px; }
        body { padding-top: 80px; } /* Cách navbar */
    </style>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top shadow-sm" style="z-index: 9999;">
    <div class="container">
        <a class="navbar-brand fw-bold" href="trang-chu.php">
            <i class="fa-solid fa-house me-2"></i> Trở về Trang chủ
        </a>
    </div>
</nav>
    <div class="container d-flex justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            
            <?php if (count($posts_data) > 0): ?>
                
                <h5 class="fw-bold mb-4 text-muted">Chi tiết Bài thảo luận</h5>
                <?php include '../includes/bai-dang.php'; ?>
                
            <?php else: ?>
                
                <div class="text-center py-5 bg-white shadow-sm rounded-4 border border-light mt-5">
                    <i class="fa-solid fa-triangle-exclamation fs-1 text-danger mb-3"></i>
                    <h4 class="text-dark fw-bold">Liên kết bị hỏng</h4>
                    <p class="text-muted fs-6">Bài viết này không tồn tại, đã bị tác giả gỡ bỏ hoặc giới hạn quyền riêng tư.</p>
                    <a href="trang-ca-nhan.php" class="btn btn-primary rounded-pill mt-3 px-4">Về Trang cá nhân</a>
                </div>
                
            <?php endif; ?>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Dán script copy link vào đây để Modal trong file UI hoạt động
        function copyPostLink(inputId) {
            var copyText = document.getElementById(inputId);
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(copyText.value).then(function() {
                alert("Đã sao chép liên kết vào bộ nhớ tạm!");
            });
        }
    </script>
</body>
</html>