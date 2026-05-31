<?php
// 1. Kiểm tra và khởi tạo Session nếu chưa có
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Kiểm tra xem trang gọi header này đã kết nối CSDL chưa
// Nếu chưa có biến $pdo, header sẽ tự động kết nối
if (!isset($pdo)) {
    require_once '../config/config.php'; 
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       
            PDO::ATTR_EMULATE_PREPARES   => false,                  
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        die("Không thể kết nối CSDL tại Header: " . $e->getMessage());
    }
}

// 3. Lấy ID người dùng đang đăng nhập (Giả định là 1 nếu chưa đăng nhập để bạn test)
$logged_in_user_id = $_SESSION['user_id'] ?? 1;

// 4. Khởi tạo giá trị mặc định
$header_stats_xp = 0;
// Do Database chưa có cột avatar nên tạm dùng ảnh mặc định
$header_user_avatar = "../assets/img/default-avatar.jpg"; 

// 5. Truy vấn SQL để lấy điểm XP của User Đang Đăng Nhập
try {
    $sql_header = "
        SELECT 
            (SELECT IFNULL(SUM(diem_so), 0) FROM phien_luyen_tap WHERE user_id = u.id) AS xp_carrots
        FROM users u
        WHERE u.id = :id
    ";
    $stmt_header = $pdo->prepare($sql_header);
    $stmt_header->execute(['id' => $logged_in_user_id]);
    
    if ($row_header = $stmt_header->fetch()) {
        $header_stats_xp = (int)$row_header['xp_carrots'];
    }
} catch (PDOException $e) {
    // Nếu lỗi chỉ ghi log, không làm sập giao diện web
    error_log("Lỗi truy vấn thông tin header: " . $e->getMessage());
}
?>

<link rel="stylesheet" href="../assets/css/root.css">
<link rel="stylesheet" href="../assets/css/trang-chu.css">
<nav class="navbar-bunny d-flex align-items-center justify-content-between px-3 px-md-4">
    <div class="d-flex align-items-center gap-4">
        <a href="trang-chu.php" class="brand-logo text-decoration-none">
            <i class="fa-solid fa-carrot"></i> THE BUNNY
        </a>
        
        <div class="search-bar d-none d-md-flex">
            <i class="fa-solid fa-search text-muted"></i>
            <input type="text" placeholder="Tìm kiếm tài liệu, bạn học..." />
        </div>
    </div>

    <div class="d-flex align-items-center gap-5">
        <a href="thach-dau.php" class="btn-icon d-none d-md-flex text-decoration-none" title="Thách đấu">
            <i class="fa-solid fa-khanda fa-2x"></i>
        </a>
        <a href="hang-tho.php" class="btn-icon text-decoration-none" title="Hang thỏ">
        <i class="fa-solid fa-people-group fa-2x"></i>
        </a>
        
        <a href="tin-nhan.php" class="btn-icon text-decoration-none" title="Tin nhắn">
            <i class="fa-brands fa-facebook-messenger fa-2x"></i>
        </a>
        
        <a href="thong-bao.php" class="btn-icon text-decoration-none" title="Thông báo">
            <i class="fa-solid fa-bell fa-2x"></i>
        </a>
        
        <div class="d-flex align-items-center gap-2 bg-light px-3 py-1 rounded-pill border d-none d-md-flex">
            <i class="fa-solid fa-fire text-danger"></i>
            <span class="fw-bold fs-6"><?= htmlspecialchars($header_stats_xp) ?></span>
        </div>
        <div class="d-flex align-items-center gap-2 border-start ps-3 ms-1">
            <a href="trang-ca-nhan.php" title="Vào trang cá nhân">
                <img src="<?= htmlspecialchars($header_user_avatar) ?>" alt="Avatar" class="rounded-circle border border-2" width="40" height="40">
            </a>
            
            <a href="../models/db_xulydangxuat.php" class="btn btn-outline-danger btn-sm rounded-circle" title="Đăng xuất" style="width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
    </div>
</nav>