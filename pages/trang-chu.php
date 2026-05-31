<?php
session_start();

// ======================================================
// KẾT NỐI DATABASE
// ======================================================
require_once '../config/config.php'; 

// =====================================================================================
// THIẾT LẬP KẾT NỐI ĐẾN CƠ SỞ DỮ LIỆU (PDO MYSQL)
// =====================================================================================

try {
    // [GIẢI THÍCH PHP]: DSN (Data Source Name) là chuỗi chứa thông tin host, tên DB và bộ mã.
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    
    // Cấu hình các đặc tính vận hành cho PDO
    $options = [
        // Bắt lỗi dưới dạng Ngoại lệ (Exception) để dễ dàng bắt bằng try-catch
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
        // Trả về dữ liệu dạng mảng có key là tên cột (Associative Array)
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       
        // Vô hiệu hóa giả lập prepare để chống SQL Injection sâu từ lõi
        PDO::ATTR_EMULATE_PREPARES   => false,                  
    ];
    
    // Mở kết nối
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
} catch (PDOException $e) {
    // Nếu sai mật khẩu hoặc sập Database, dừng load trang và báo lỗi
    die("Hệ thống gián đoạn. Không thể kết nối cơ sở dữ liệu: " . $e->getMessage());
}

// =========================================================================================
// ĐỊNH NGHĨA CÁC HÀM TIỆN ÍCH XỬ LÝ NGHIỆP VỤ & BẢO MẬT
// =========================================================================================

/**
 * Hàm lọc dữ liệu đầu vào (Sanitize Input) chống XSS
 * [GIẢI THÍCH PHP]: Xóa khoảng trắng thừa, xóa backslash, và biến đổi các thẻ HTML 
 * nguy hiểm (như <script>) thành mã an toàn để tránh tin tặc tấn công XSS.
 */
function sanitize_input($data) {
    if (is_null($data)) return "";
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Hàm loại bỏ dấu Tiếng Việt cho chuỗi (Dùng để chuẩn hóa tên file)
 * [GIẢI THÍCH PHP]: Dùng Regex để quét và thay thế các ký tự có dấu thành không dấu.
 */
function remove_vietnamese_accents($str) {
    $unicode = array(
        'a'=>'á|à|ả|ã|ạ|ă|ắ|ặ|ằ|ẳ|ẵ|â|ấ|ầ|ẩ|ẫ|ậ',
        'd'=>'đ',
        'e'=>'é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ',
        'i'=>'í|ì|ỉ|ĩ|ị',
        'o'=>'ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ',
        'u'=>'ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự',
        'y'=>'ý|ỳ|ỷ|ỹ|ỵ',
        'A'=>'Á|À|Ả|Ã|Ạ|Ă|Ắ|Ặ|Ằ|Ẳ|Ẵ|Â|Ấ|Ầ|Ẩ|Ẫ|Ậ',
        'D'=>'Đ',
        'E'=>'É|È|Ẻ|Ẽ|Ẹ|Ê|Ế|Ề|Ể|Ễ|Ệ',
        'I'=>'Í|Ì|Ỉ|Ĩ|Ị',
        'O'=>'Ó|Ò|Ỏ|Õ|Ọ|Ô|Ố|Ồ|Ổ|Ỗ|Ộ|Ơ|Ớ|Ờ|Ở|Ỡ|Ợ',
        'U'=>'Ú|Ù|Ủ|Ũ|Ụ|Ư|Ứ|Ừ|Ử|Ữ|Ự',
        'Y'=>'Ý|Ỳ|Ỷ|Ỹ|Ỵ',
    );
    foreach($unicode as $nonUnicode=>$uni){
        $str = preg_replace("/($uni)/i", $nonUnicode, $str);
    }
    return $str;
}

/**
 * Hàm tính thời gian tương đối
 * [GIẢI THÍCH PHP]: Nhận vào chuỗi DATETIME từ DB, so sánh với giờ hiện tại của máy chủ,
 * và quy đổi ra định dạng "Vừa xong", "5 phút trước", "2 giờ trước"...
 */
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    // Tính toán số tuần
    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'năm',
        'm' => 'tháng',
        'w' => 'tuần',
        'd' => 'ngày',
        'h' => 'giờ',
        'i' => 'phút',
        's' => 'giây',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v;
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' trước' : 'Vừa xong';
}

/**
 * TẠO CSRF TOKEN CHỐNG GIẢ MẠO REQUEST
 * [GIẢI THÍCH PHP]: Tạo một mã băm ngẫu nhiên 32 byte lưu vào phiên làm việc (Session).
 * Token này sẽ được chèn ẩn vào mọi Form. Nếu hacker giả mạo Form từ trang khác gửi đến,
 * do không có Token khớp với Session, request sẽ bị chặn lại lập tức.
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// =====================================================================================
// KHỞI TẠO BIẾN TRỐNG
// =====================================================================================

$page_title = "Trang chủ";

// Thông tin user
$user_name         = "Người dùng";
$user_type         = "";
$truong_hoc        = "";
$truong_dai_hoc    = "";
$is_verified       = false;

// Ảnh mặc định
$user_avatar = "../assets/img/default-avatar.jpg";
$user_cover  = "../assets/img/default-cover.jpg";

// Thống kê
$stats_xp       = 0;
$stats_buddies  = 0;
$stats_docs     = 0;

// Alert
$message_notify = "";
$message_type   = "success";

// =====================================================================================
// USER ĐĂNG NHẬP
// =====================================================================================

// Nếu chưa có login thì test user ID = 1
$current_user_id = $_SESSION['user_id'] ?? 1;

// =====================================================================================
// LẤY THÔNG TIN USER TỪ DATABASE
// =====================================================================================

try {

    $sql = "SELECT * FROM users WHERE id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$current_user_id]);

    $row_profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row_profile) {

        $user_name      = $row_profile['username'] ?? 'Người dùng';
        $user_type      = $row_profile['user_type'] ?? '';
        $truong_hoc     = $row_profile['truong_hoc'] ?? '';
        $truong_dai_hoc = $row_profile['truong_dai_hoc'] ?? '';

        // Nếu có giấy tờ chứng minh thì xem như verified
        $is_verified = !empty($row_profile['giay_to_chung_minh']);
    }

} catch (PDOException $e) {

    $message_notify = "Lỗi tải thông tin người dùng!";
    $message_type = "danger";
}

// ======================================================
// Lấy username
// ======================================================
$sql_profile = "
    SELECT
        u.username,
        u.giay_to_chung_minh
    FROM users u
    WHERE u.id = :id
";
// ======================================================
// LẤY DANH SÁCH TẤT CẢ BÀI ĐĂNG TRANG CHỦ
// ======================================================
$sql_posts = "
    SELECT 
        b.id AS post_id,
        b.noi_dung,
        b.created_at,
        b.user_id,

        u.username,
        u.giay_to_chung_minh,

        (
            SELECT COUNT(*)
            FROM luot_thich lt
            WHERE lt.bai_dang_id = b.id
        ) AS total_likes,

        (
            SELECT COUNT(*)
            FROM binh_luan bl
            WHERE bl.bai_dang_id = b.id
        ) AS total_comments,

        (
            SELECT COUNT(*)
            FROM luot_chia_se cs
            WHERE cs.bai_dang_id = b.id
        ) AS total_shares,

        (
            SELECT COUNT(*)
            FROM luot_thich lt2
            WHERE lt2.bai_dang_id = b.id
            AND lt2.user_id = :uid
        ) AS is_liked_by_me

    FROM bai_dang b
    INNER JOIN users u 
        ON b.user_id = u.id

    ORDER BY b.created_at DESC
";

$stmt_posts = $pdo->prepare($sql_posts);

$stmt_posts->execute([
    'uid' => $current_user_id
]);

$posts_data = $stmt_posts->fetchAll(PDO::FETCH_ASSOC) ?? [];


// ======================================================
// 6. TRENDING TAGS (TẠM ĐỂ RỖNG)
// ======================================================
$trendingTags = [];


// ======================================================
// 7. BẠN ONLINE (TẠM ĐỂ RỖNG)
// ======================================================
$onlineFriends = [];
?>
```
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>The Bunny - Mạng Xã Hội Học Tập</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/root.css" rel="stylesheet">
    <link href="../assets/css/trang-chu.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .btn-interaction { background-color: transparent; border: none; font-weight: bold; color: #6c757d; border-radius: 8px; transition: all 0.2s; }
        .btn-interaction:hover { background-color: #f1f3f5; color: #495057; }
        .btn-interaction.active-like { color: #0d6efd; } /* Nút Thích chuyển sang màu xanh dương nếu active */
        .btn-interaction.active-like:hover { background-color: #e7f1ff; }
        .comment-box { background-color: #f8f9fa; border-radius: 12px; padding: 12px 16px; margin-bottom: 10px; }
        .post-stats-text { font-size: 0.85rem; color: #6c757d; cursor: pointer; }
        .post-stats-text:hover { text-decoration: underline; }
    </style>
</head>
<body>

    <div class="mobile-overlay" id="mobileOverlay" onclick="toggleSidebar()"></div>

    <?php include '../includes/header.php'; ?>

    <div class="layout-container">

        <!-- ====== SIDEBAR TRÁI ====== -->
        <aside class="sidebar-left" id="sidebarLeft">
            <!-- users.full_name, users.avatar_url, users.xp_points, xp_rank -->
            <div class="user-mini d-none d-md-flex">
                <img 
                    src="<?= htmlspecialchars($user_avatar ?? '../assets/img/default-avatar.jpg') ?>"
                    alt="<?= htmlspecialchars($row_profile['username'] ?? 'Người dùng') ?>"
                >
            <div>
                <div class="name">
                    <?= htmlspecialchars($user_name) ?>
                </div>
            </div>
</div>

            <div class="nav-menu">
                <a href="trang-chu.php" class="nav-item active"><i class="fa-solid fa-house"></i> Bảng tin</a>
                <a href="hang-tho.php" class="nav-item"><i class="fa-solid fa-user-group"></i> Hang thỏ</a>
                <a href="ban-cung-tien.php" class="nav-item"><i class="fa-solid fa-child"></i>Bạn cùng tiến</a>
                <a href="thach-dau.php" class="nav-item"><i class="fa-solid fa-khanda"></i> Thách đấu <span class="badge-count" style="background: #EF4444;">Mới</span></a>
            </div>
            
            <hr class="my-3 text-muted opacity-25">

        </aside>

        <!-- ====== FEED CHÍNH ====== -->
        <main class="feed-main">

            <!-- Form đăng bài mới -->
<div class="card shadow-sm border-0 mb-4 rounded-4 bg-white">
    <div class="card-body p-4">
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
            
            <div class="d-flex gap-3">
                <img src="<?= htmlspecialchars($user_avatar); ?>" class="rounded-circle border border-2 border-light shadow-sm" width="55" height="55" style="object-fit: cover;" alt="Ảnh bạn">
                
                <textarea 
                    class="form-control border-secondary-subtle bg-light rounded-4 p-3 fs-6" 
                    name="noidung_post" 
                    rows="3" 
                    placeholder="Bạn muốn đăng tải nội dung học thuật gì lên mạng xã hội hôm nay?..." 
                    required 
                    style="resize: none;"
                ></textarea>
            </div>
            
            <hr class="text-muted border-dashed my-4 opacity-25">
            
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-danger btn-sm fw-bold rounded-pill px-3 shadow-sm" disabled title="Tạm bảo trì tính năng up PDF">
                        <i class="fa-solid fa-file-pdf me-1"></i> Gắn PDF
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm fw-bold rounded-pill px-3 shadow-sm" disabled title="Tạm bảo trì tính năng up ảnh">
                        <i class="fa-solid fa-image me-1"></i> Hình ảnh
                    </button>
                </div>
                
                <button type="submit" class="btn btn-primary fw-bold px-4 py-2 rounded-pill shadow">
                    <i class="fa-solid fa-paper-plane me-2"></i> Gửi bài viết
                </button>
            </div>
        </form>
    </div>
</div>

            <!-- BÀI ĐĂNG -->
            <?php include '../includes/danh-sach-bai-dang.php'; ?>

        </main>

        <!-- ====== SIDEBAR PHẢI ====== -->
        <aside class="sidebar-right">
            
            <!-- XU HƯỚNG: tags JOIN post_tags -->
            <div class="card-bunny p-3 mb-3">
                <div class="section-title">Xu hướng học tập</div>
                <?php foreach ($trendingTags as $tag): ?>
                <div class="trending-tag">
                    <!-- tags.tag_name -->
                    <span class="hash"><?= htmlspecialchars($tag['tag']) ?></span>
                    <!-- COUNT từ post_tags -->
                    <span class="count"><?= htmlspecialchars($tag['count']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- BẠN ONLINE: users JOIN user_sessions (presence) -->
            <div class="section-title ps-2 mb-2">Bạn cùng tiến online</div>
            <?php foreach ($onlineFriends as $friend): ?>
            <div class="friend-item">
                <div class="friend-avatar">
                    <!-- users.avatar_url -->
                    <img src="<?= htmlspecialchars($friend['avatar']) ?>"
                         alt="<?= htmlspecialchars($friend['name']) ?>">
                    <!-- user_sessions.status: 'online' | 'away' -->
                    <div class="online-dot <?= $friend['status'] === 'away' ? 'away-dot' : '' ?>"></div>
                </div>
                <!-- users.full_name -->
                <div class="fw-bold small <?= $friend['status'] === 'away' ? 'text-muted' : '' ?>">
                    <?= htmlspecialchars($friend['name']) ?>
                </div>
            </div>
            <?php endforeach; ?>
            
        </aside>

    </div>

    <!-- BOTTOM NAV (Mobile) -->
    <div class="bottom-nav">
        <button class="nav-btn-mobile active">
            <i class="fa-solid fa-house"></i><span>Trang chủ</span>
        </button>
        <a href="ban-cung-tien.php" class="nav-btn-mobile"><i class="fa-solid fa-user-group"></i><span>Bạn bè</span></a>
        <button class="nav-btn-mobile" style="color: var(--bunny-primary); margin-top: -15px;">
            <div class="bg-primary bg-opacity-10 rounded-circle p-2 shadow-sm"><i class="fa-solid fa-plus fs-4"></i></div>
        </button>
        <button class="nav-btn-mobile">
            <i class="fa-solid fa-bell"></i><span>Thông báo</span>
        </button>
        <button class="nav-btn-mobile">
            <i class="fa-solid fa-user"></i><span>Hồ sơ</span>
        </button>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script src='./assets/js/trang-chu.js'></script>
</body>
</html>
