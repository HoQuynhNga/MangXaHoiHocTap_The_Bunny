<?php
/**
 * =========================================================================================
 * ĐỒ ÁN MÔN HỌC: XÂY DỰNG WEBSITE MẠNG XÃ HỘI HỌC TẬP THE BUNNY
 * Tệp tin: trang-ca-nhan.php
 * Chức năng: Hiển thị hồ sơ cá nhân, dòng thời gian, tài liệu, sự kiện và bạn bè.
 * Xử lý: Đăng bài, Cập nhật thông tin, Thêm tài liệu, Tạo sự kiện (Tương tác trực tiếp DB).
 * Khắc phục: Lỗi Parameter HY093, Lỗi Procedure bài đăng, Lỗi cột Tài liệu.
 * =========================================================================================
 */

// [GIẢI THÍCH PHP]: Bắt đầu phiên làm việc (Session). Hàm này bắt buộc phải gọi trước khi
// có bất kỳ output HTML nào được gửi về trình duyệt. Nó giúp truy xuất định danh người dùng.
session_start();

// [GIẢI THÍCH PHP]: Gọi tệp cấu hình chứa các hằng số kết nối Cơ sở dữ liệu.
// Sử dụng require_once để đảm bảo tệp cấu hình chỉ được gọi một lần duy nhất, tránh lỗi redeclare.
require_once '../config/config.php'; 

// =========================================================================================
// PHẦN 1: ĐỊNH NGHĨA CÁC HÀM TIỆN ÍCH (HELPER FUNCTIONS) XỬ LÝ NGHIỆP VỤ
// =========================================================================================

/**
 * Hàm lọc dữ liệu đầu vào (Sanitize Input)
 * [GIẢI THÍCH PHP]: Ngăn chặn tấn công XSS (Cross-Site Scripting) bằng cách loại bỏ
 * khoảng trắng thừa, xóa các thẻ HTML độc hại và chuyển đổi ký tự đặc biệt.
 */
function sanitize_input($data) {
    if (is_null($data)) return "";
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Hàm tính thời gian trôi qua (Time Ago)
 * [GIẢI THÍCH PHP]: Chuyển đổi timestamp từ cơ sở dữ liệu thành chuỗi hiển thị
 * thân thiện với người dùng mạng xã hội (VD: "Vừa xong", "5 phút trước", "2 giờ trước").
 */
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

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
 * Tạo CSRF Token (Cross-Site Request Forgery)
 * [GIẢI THÍCH PHP]: Tạo một mã thông báo ngẫu nhiên lưu vào Session để đính kèm
 * vào các Form. Khi nhận POST request, server sẽ đối chiếu token này để đảm bảo
 * request thực sự xuất phát từ website The Bunny, ngăn chặn hacker giả mạo.
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// =====================================================================================
// PHẦN 2: KHỞI TẠO BIẾN TRỐNG (MẶC ĐỊNH LÀ RỖNG, KHÔNG SỬ DỤNG DỮ LIỆU MẪU)
// =====================================================================================

$page_title          = "Trang cá nhân - The Bunny";

// Biến thông tin định danh cơ bản (Bảng users)
$user_name           = "";
$user_type           = "";
$truong_hoc          = "";
$truong_dai_hoc      = "";
$is_verified         = false;

// Biến thông tin mở rộng (Bảng ho_so_ca_nhan)
$thong_tin_dinh_danh = "";

// Do cấu trúc DB của bạn không có cột lưu đường dẫn ảnh, ta gán đường dẫn ảnh tĩnh
$user_avatar         = "../assets/img/default-avatar.jpg";
$user_cover          = "../assets/img/default-cover.jpg";

// Khởi tạo các biến thống kê dạng số nguyên (Integer)
$stats_xp            = 0;
$stats_buddies       = 0;
$stats_docs          = 0;
$stats_fire          = 0;

// Khởi tạo biến lưu trữ thông báo và màu sắc thông báo của Bootstrap
$message_notify      = ""; 
$message_type        = "success"; 

// [GIẢI THÍCH PHP]: Lấy ID người dùng từ Session. Trong môi trường test chưa có chức năng
// đăng nhập hoàn chỉnh, biến này có thể null nên dùng toán tử ?? gán mặc định là 1.
$current_user_id     = $_SESSION['user_id'] ?? 1; 

// Khởi tạo các mảng dữ liệu rỗng để dùng cho vòng lặp ở giao diện
$posts_data          = [];
$buddies_data        = [];
$docs_data           = [];
$events_data         = [];

// =====================================================================================
// PHẦN 3: KẾT NỐI PDO ĐẾN HỆ QUẢN TRỊ CƠ SỞ DỮ LIỆU MYSQL
// =====================================================================================

try {
    // [GIẢI THÍCH PHP]: Xây dựng chuỗi DSN kết nối. DB_HOST, DB_NAME, DB_CHARSET 
    // đã được định nghĩa bằng hàm define() trong tệp config.php.
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    
    // [GIẢI THÍCH PHP]: Cấu hình các cờ cho PHP Data Objects (PDO)
    $options = [
        // Chuyển chế độ báo lỗi thành Exception để khối try-catch có thể bắt được.
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
        // Dữ liệu lấy ra luôn ở dạng mảng kết hợp (Associative Array).
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       
        // Vô hiệu hóa mô phỏng prepared statements, buộc MySQL tự chuẩn bị câu lệnh
        // giúp bảo mật tuyệt đối chống lại tấn công SQL Injection.
        PDO::ATTR_EMULATE_PREPARES   => false,                  
    ];
    
    // [GIẢI THÍCH PHP]: Tạo thực thể kết nối.
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
} catch (PDOException $e) {
    // [GIẢI THÍCH PHP]: Dừng toàn bộ hệ thống ngay lập tức nếu không thể kết nối tới DB.
    die("Hệ thống gián đoạn. Không thể kết nối cơ sở dữ liệu: " . $e->getMessage());
}

// =====================================================================================
// PHẦN 4: TIẾP NHẬN YÊU CẦU POST VÀ THỰC THI THÊM/SỬA DỮ LIỆU (INSERT/UPDATE)
// =====================================================================================

// [GIẢI THÍCH PHP]: Đặt toàn bộ luồng xử lý POST vào try-catch để kiểm soát lỗi cục bộ
try {
    // Lệnh IF kiểm tra phương thức HTTP có phải là POST và chứa thuộc tính action không
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
        
        // [KIỂM TRA BẢO MẬT]: Xác thực CSRF Token
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception("Lỗi bảo mật: Token không hợp lệ. Vui lòng tải lại trang.");
        }
        
        // Gán biến action để chia luồng xử lý bằng cấu trúc switch-case
        $action = sanitize_input($_POST['action']);

        switch ($action) {
            
            // -------------------------------------------------------------------------
            // LUỒNG 1: [ĐÃ FIX] XỬ LÝ ĐĂNG BÀI VIẾT BẰNG LỆNH INSERT TRỰC TIẾP
            // -------------------------------------------------------------------------
            case 'add_post':
                if (!empty($_POST['noidung_post'])) {
                    $content = sanitize_input($_POST['noidung_post']);
                    
                    // [GIẢI THÍCH SQL]: CSDL của bạn không có Procedure create_post. 
                    // Do đó, ta phải viết truy vấn INSERT trực tiếp vào bảng bai_dang.
                    $sql_insert_post = "
                        INSERT INTO bai_dang (user_id, noi_dung, created_at) 
                        VALUES (:uid, :content, NOW())
                    ";
                    $stmt = $pdo->prepare($sql_insert_post);
                    
                    // Thực thi Insert
                    $stmt->execute([
                        'uid'     => $current_user_id, 
                        'content' => $content
                    ]);
                    
                    // Ghi nhận phản hồi thành công
                    $message_notify = "Đã xuất bản bài đăng thành công!";
                    $message_type   = "success";
                } else {
                    throw new Exception("Nội dung bài viết không được để trống.");
                }
                break;
                
            // -------------------------------------------------------------------------
            // LUỒNG 2: XỬ LÝ CẬP NHẬT THÔNG TIN HỒ SƠ CHUNG (USERS + HO_SO_CA_NHAN)
            // -------------------------------------------------------------------------
            case 'edit_profile':
                // Tiến hành làm sạch dữ liệu từ Form gửi lên
                $username       = sanitize_input($_POST['username'] ?? '');
                $truong_hoc     = sanitize_input($_POST['truong_hoc'] ?? '');
                $truong_dai_hoc = sanitize_input($_POST['truong_dai_hoc'] ?? '');
                $tt_dinh_danh   = sanitize_input($_POST['thong_tin_dinh_danh'] ?? '');

                // [BƯỚC 1]: Cập nhật bảng 'users' (Chứa các thông tin cấu trúc cứng)
                $sql_update_users = "
                    UPDATE users 
                    SET 
                        username       = :uname, 
                        truong_hoc     = :thoc, 
                        truong_dai_hoc = :tdhoc 
                    WHERE id = :id
                ";
                $stmt1 = $pdo->prepare($sql_update_users);
                $stmt1->execute([
                    'uname' => $username, 
                    'thoc'  => $truong_hoc, 
                    'tdhoc' => $truong_dai_hoc, 
                    'id'    => $current_user_id
                ]);

                // [BƯỚC 2]: Cập nhật bảng 'ho_so_ca_nhan'
                // [GIẢI THÍCH SQL]: Cơ chế UPSERT. Nếu chưa có -> INSERT, có rồi -> UPDATE.
                $sql_upsert_profile = "
                    INSERT INTO ho_so_ca_nhan (user_id, thong_tin_dinh_danh) 
                    VALUES (:id, :ttdd) 
                    ON DUPLICATE KEY UPDATE 
                        thong_tin_dinh_danh = VALUES(thong_tin_dinh_danh)
                ";
                $stmt2 = $pdo->prepare($sql_upsert_profile);
                $stmt2->execute([
                    'id'   => $current_user_id, 
                    'ttdd' => $tt_dinh_danh
                ]);
                
                $message_notify = "Hồ sơ năng lực của bạn đã được cập nhật đồng bộ!";
                $message_type   = "success";
                break;

            // -------------------------------------------------------------------------
            // LUỒNG 3: [ĐÃ FIX] XỬ LÝ TẢI LÊN TÀI LIỆU (KHỚP THE_BUNNY_DB.SQL)
            // -------------------------------------------------------------------------
            case 'add_document':
                $doc_name = sanitize_input($_POST['ten_tai_lieu'] ?? 'Tài liệu vô danh');
                
                // Do form tải file chưa tích hợp hệ thống lưu trữ tệp tin vật lý, 
                // ta sẽ gán một chuỗi URL giả lập vào CSDL.
                $file_url = "uploads/docs/dinh_kem_hoc_tap.pdf"; 
                
                // [GIẢI THÍCH SQL]: Bảng tai_lieu theo schema thực tế chỉ có 
                // id, user_id, ten_tai_lieu, file_url, created_at, updated_at
                $sql_insert_doc = "
                    INSERT INTO tai_lieu 
                        (user_id, ten_tai_lieu, file_url, created_at) 
                    VALUES 
                        (:uid, :name, :url, NOW())
                ";
                $stmt = $pdo->prepare($sql_insert_doc);
                $stmt->execute([
                    'uid'  => $current_user_id, 
                    'name' => $doc_name, 
                    'url'  => $file_url
                ]);
                
                $message_notify = "Đóng góp tài liệu học tập thành công!";
                $message_type   = "success";
                break;

            // -------------------------------------------------------------------------
            // LUỒNG 4: [ĐÃ FIX] XỬ LÝ TẠO SỰ KIỆN LỊCH TRÌNH (SỬA LỖI HY093)
            // -------------------------------------------------------------------------
            case 'add_event':
                $event_name = sanitize_input($_POST['tieu_de'] ?? 'Sự kiện The Bunny');
                // Lấy thời gian từ Form, đảm bảo là chuỗi không rỗng
                $event_date = !empty($_POST['thoi_gian']) ? $_POST['thoi_gian'] : date('Y-m-d H:i:s');
                
                // [BƯỚC 1]: Thêm dữ liệu vào bảng su_kien.
                // CHÚ Ý: Bảng su_kien theo CSDL thực tế không có cột nguoi_tao_id và dia_diem.
                $sql_insert_event = "
                    INSERT INTO su_kien 
                        (tieu_de, thoi_gian, created_at) 
                    VALUES 
                        (:title, :time, NOW())
                ";
                $stmt = $pdo->prepare($sql_insert_event);
                
                // [GIẢI THÍCH FIX LỖI HY093]: Key trong mảng execute phải KHỚP TUYỆT ĐỐI 
                // với tên placeholder trong câu SQL (:title -> 'title', :time -> 'time').
                $stmt->execute([
                    'title' => $event_name, 
                    'time'  => $event_date
                ]);
                
                // [BƯỚC 2]: Lấy ID của sự kiện vừa được chèn thành công để liên kết Khóa Ngoại
                $new_event_id = $pdo->lastInsertId();
                
                // [BƯỚC 3]: Đăng ký User này vào bảng trung gian thanh_vien_su_kien với tư cách Approved
                // Do user là người tạo nên trạng thái duyệt auto là Approved.
                $sql_insert_member = "
                    INSERT INTO thanh_vien_su_kien 
                        (su_kien_id, user_id, trang_thai_duyet, created_at) 
                    VALUES 
                        (:event_id, :uid, 'Approved', NOW())
                ";
                $stmt_member = $pdo->prepare($sql_insert_member);
                $stmt_member->execute([
                    'event_id' => $new_event_id,
                    'uid'      => $current_user_id
                ]);
                
                $message_notify = "Lên lịch sự kiện học thuật thành công!";
                $message_type   = "success";
                break;
                
            default:
                throw new Exception("Hành động không được nhận diện bởi hệ thống bảo mật.");
        }
        
        // [GIẢI THÍCH PHP]: Kỹ thuật PRG (Post/Redirect/Get)
        // Nếu có thông báo thành công, ta mã hóa url và chuyển hướng trang.
        // Điều này ngăn chặn trình duyệt hiển thị hộp thoại "Confirm Form Resubmission"
        // khó chịu khi người dùng ấn nút F5 để tải lại trang.
        if($message_notify != "" && $message_type == "success") {
            $safe_msg = urlencode($message_notify);
            $safe_type = urlencode($message_type);
            // Dùng hàm header để điều hướng
            header("Location: " . $_SERVER['PHP_SELF'] . "?msg=" . $safe_msg . "&type=" . $safe_type);
            // Giải phóng tiến trình
            exit;
        }
    }

} catch (Exception $e) {
    // Bắt lỗi phát sinh trong quá trình thực thi POST
    $message_notify = "Lỗi xử lý hệ thống: " . $e->getMessage();
    $message_type   = "danger"; // Báo màu đỏ cho giao diện Bootstrap
}

// Bắt lấy thông báo từ cấu trúc URL (Nếu vừa trải qua quá trình PRG Redirect)
if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message_notify = sanitize_input($_GET['msg']);
    $message_type   = sanitize_input($_GET['type']);
}

// =====================================================================================
// PHẦN 5: THỰC THI TRUY VẤN LẤY DỮ LIỆU ĐỂ RENDER (KẾT XUẤT) RA GIAO DIỆN
// =====================================================================================

try {
    // ---------------------------------------------------------------------------------
    // [TRUY VẤN 1]: LẤY DỮ LIỆU ĐỊNH DANH VÀ THỐNG KÊ TỔNG QUAN
    // ---------------------------------------------------------------------------------
    $sql_profile = "
        SELECT 
            u.username, 
            u.user_type, 
            u.truong_hoc, 
            u.truong_dai_hoc, 
            u.giay_to_chung_minh,
            h.thong_tin_dinh_danh,
            (SELECT IFNULL(SUM(diem_so), 0) FROM phien_luyen_tap WHERE user_id = u.id) AS xp_carrots,
            (SELECT COUNT(*) FROM ban_cung_tien WHERE (user_id = u.id OR friend_user_id = u.id) AND status = 'Accepted') AS buddy_count,
            (SELECT COUNT(*) FROM tai_lieu WHERE user_id = u.id) AS document_count
        FROM 
            users u 
        LEFT JOIN 
            ho_so_ca_nhan h ON u.id = h.user_id 
        WHERE 
            u.id = :id
    ";
    
    // Nạp câu lệnh vào bộ chuẩn bị
    $stmt_profile = $pdo->prepare($sql_profile);
    // Gắn tham số ID động vào dấu :id
    $stmt_profile->execute(['id' => $current_user_id]);
    
    // Lấy 1 bản ghi duy nhất, gán đè vào các biến trống đã khai báo ở Phần 2
    if ($row_profile = $stmt_profile->fetch()) {
        $user_name           = $row_profile['username'] ?? "Tài khoản ẩn danh";
        $page_title          = $user_name . " - Mạng xã hội học tập The Bunny"; 
        
        $user_type           = $row_profile['user_type'] ?? "";
        $truong_hoc          = $row_profile['truong_hoc'] ?? "";
        $truong_dai_hoc      = $row_profile['truong_dai_hoc'] ?? "";
        
        // Cấp phát Tích xanh (is_verified) nếu cột giay_to_chung_minh có chứa dữ liệu
        $is_verified         = (!empty($row_profile['giay_to_chung_minh'])) ? true : false;
        
        $thong_tin_dinh_danh = $row_profile['thong_tin_dinh_danh'] ?? ""; 
        
        $stats_xp            = (int)$row_profile['xp_carrots'];
        $stats_buddies       = (int)$row_profile['buddy_count'];
        $stats_docs          = (int)$row_profile['document_count'];
    }

    // ---------------------------------------------------------------------------------
    // [TRUY VẤN 2]: LẤY DANH SÁCH DÒNG THỜI GIAN (BẢNG BAI_DANG)
    // ---------------------------------------------------------------------------------
    $sql_posts = "
        SELECT 
            b.id, 
            b.noi_dung, 
            b.created_at, 
            u.username, 
            u.giay_to_chung_minh 
        FROM 
            bai_dang b 
        INNER JOIN 
            users u ON b.user_id = u.id 
        WHERE 
            b.user_id = :id 
        ORDER BY 
            b.created_at DESC
    ";
    $stmt_posts = $pdo->prepare($sql_posts);
    $stmt_posts->execute(['id' => $current_user_id]);
    // Nạp tất cả kết quả vào mảng
    $posts_data = $stmt_posts->fetchAll();

    // ---------------------------------------------------------------------------------
    // [TRUY VẤN 3]: LẤY DANH SÁCH BẠN CÙNG TIẾN (BẢNG BAN_CUNG_TIEN VÀ USERS)
    // ---------------------------------------------------------------------------------
    // [GIẢI THÍCH SQL]: Mệnh đề INNER JOIN cực kỳ tinh tế. Do tài khoản của ta
    // có thể đóng vai trò là người gửi (user_id) hoặc người nhận (friend_user_id)
    // trong quan hệ bạn bè, ta phải dùng OR trong điều kiện ON để lấy đúng người còn lại.
    $sql_buddies = "
        SELECT 
            u.id, 
            u.username, 
            u.truong_hoc 
        FROM 
            ban_cung_tien f 
        INNER JOIN 
            users u ON (u.id = f.friend_user_id OR u.id = f.user_id) 
        WHERE 
            (f.user_id = :id OR f.friend_user_id = :id) 
            AND f.status = 'Accepted' 
            AND u.id != :id
    ";
    $stmt_buddies = $pdo->prepare($sql_buddies);
    $stmt_buddies->execute(['id' => $current_user_id]);
    $buddies_data = $stmt_buddies->fetchAll();

    // ---------------------------------------------------------------------------------
    // [TRUY VẤN 4]: [ĐÃ FIX] LẤY DANH SÁCH KHO TÀI LIỆU SỐ
    // ---------------------------------------------------------------------------------
    // Sửa lại tên cột file_ thành file_url theo đúng schema trong the_bunny_db.sql
    $sql_docs = "
        SELECT 
            id, 
            ten_tai_lieu, 
            file_url, 
            created_at 
        FROM 
            tai_lieu 
        WHERE 
            user_id = :id 
        ORDER BY 
            created_at DESC
    ";
    $stmt_docs = $pdo->prepare($sql_docs);
    $stmt_docs->execute(['id' => $current_user_id]);
    $docs_data = $stmt_docs->fetchAll();

    // ---------------------------------------------------------------------------------
    // [TRUY VẤN 5]: [ĐÃ FIX] LẤY DANH SÁCH SỰ KIỆN QUA BẢNG TRUNG GIAN
    // ---------------------------------------------------------------------------------
    // [GIẢI THÍCH SQL]: Dùng INNER JOIN nối bảng 'su_kien' (sk) với 'thanh_vien_su_kien' (tv).
    $sql_events = "
        SELECT 
            sk.id, 
            sk.tieu_de,  
            sk.thoi_gian 
        FROM 
            su_kien sk
        INNER JOIN 
            thanh_vien_su_kien tv ON sk.id = tv.su_kien_id
        WHERE 
            tv.user_id = :id 
        ORDER BY 
            sk.thoi_gian ASC
    ";
    
    $stmt_events = $pdo->prepare($sql_events);
    // Truyền ID của người dùng vào tham số truy vấn
    $stmt_events->execute(['id' => $current_user_id]);
    // Nạp toàn bộ sự kiện mà người dùng này tham gia vào mảng
    $events_data = $stmt_events->fetchAll();

} catch (PDOException $e) { 
    // Bắt lỗi ngoại lệ ở tầng SELECT dữ liệu nếu có
    $message_notify = "Lỗi kỹ thuật trong quá trình truy xuất thông tin hệ thống: " . $e->getMessage();
    $message_type = "danger";
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    
    <title><?= htmlspecialchars($page_title); ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link href="../assets/css/root.css" rel="stylesheet">
    <link href="../assets/css/trang-ca-nhan.css" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    <script src="../assets/js/trang-ca-nhan.js" defer></script>
    
    <script defer>
        document.addEventListener('DOMContentLoaded', function () {
            // Khởi tạo Tooltip (Nhãn dán nổi)
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</head>

<body class="bg-light">

    <?php if($message_notify != ""): ?>
        <div 
            class="alert alert-<?= htmlspecialchars($message_type); ?> alert-dismissible fade show text-center m-0 rounded-0 shadow-sm border-0" 
            role="alert"
            aria-live="assertive"
        >
            <i class="fa-solid fa-circle-info me-2"></i>
            <strong>Thông báo hệ thống:</strong> <?= htmlspecialchars($message_notify); ?>
            
            <button 
                type="button" 
                class="btn-close" 
                data-bs-dismiss="alert" 
                aria-label="Đóng thông báo"
            ></button>
        </div>
    <?php endif; ?>

    <nav class="navbar navbar-expand-lg bg-white shadow-sm sticky-top">
        <div class="container d-flex justify-content-between align-items-center">
            
            <a class="navbar-brand fw-bold text-primary fs-3 d-flex align-items-center gap-2" href="#">
                <i class="fa-solid fa-carrot text-warning"></i>
                <span>The Bunny</span>
            </a>
            
            <form class="d-none d-lg-flex w-50" role="search">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0" id="search-addon">
                        <i class="fa-solid fa-magnifying-glass text-muted"></i>
                    </span>
                    <input type="search" class="form-control bg-light border-start-0 ps-0" placeholder="Tìm kiếm bạn học, tài liệu..." aria-label="Search" aria-describedby="search-addon">
                </div>
            </form>
            
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light rounded-circle text-muted shadow-sm position-relative" aria-label="Thông báo">
                    <i class="fa-solid fa-bell"></i>
                    <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle">
                        <span class="visually-hidden">Thông báo mới</span>
                    </span>
                </button>
                <button class="btn btn-light rounded-circle text-muted shadow-sm" aria-label="Tin nhắn">
                    <i class="fa-solid fa-message"></i>
                </button>
                
                <div class="dropdown">
                    <button class="btn border-0 p-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?= htmlspecialchars($user_avatar); ?>" class="rounded-circle border shadow-sm" width="40" height="40" style="object-fit: cover;" alt="Menu người dùng">
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                        <li><a class="dropdown-item fw-medium" href="#"><i class="fa-solid fa-gear me-2"></i>Cài đặt tài khoản</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item fw-medium text-danger" href="#"><i class="fa-solid fa-right-from-bracket me-2"></i>Đăng xuất</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4 mb-5">
        
        <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4 bg-white">
            
            <div 
                class="card-img-top position-relative" 
                style="height: 320px; background: url('<?= htmlspecialchars($user_cover); ?>') center/cover no-repeat;"
                aria-label="Ảnh bìa hồ sơ"
            >
                <div class="position-absolute top-0 start-0 w-100 h-100 bg-dark opacity-25"></div>
            </div>
            
            <div class="card-body position-relative pt-0 px-md-5 pb-5">
                
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center align-items-md-end text-center text-md-start" style="margin-top: -50px; z-index: 2;">
                    
                    <div class="d-flex flex-column flex-md-row align-items-center align-items-md-end gap-4">
                        
                        <div class="position-relative">
                            <img 
                                src="<?= htmlspecialchars($user_avatar); ?>" 
                                class="rounded-circle border border-5 border-white bg-white shadow-lg" 
                                width="170" 
                                height="170" 
                                style="object-fit: cover;" 
                                alt="Ảnh đại diện của <?= htmlspecialchars($user_name); ?>"
                            >
                            <span class="position-absolute bottom-0 end-0 p-2 mb-2 me-3 bg-success border border-3 border-white rounded-circle" aria-label="Đang trực tuyến"></span>
                        </div>
                        
                        <div class="pb-3 mt-4 mt-md-4 pt-md-3">
                            <h1 class="fw-bold m-0 text-dark mb-1" style="font-size: 2rem;">
                                <?= htmlspecialchars($user_name); ?>
                                
                                <?php if($is_verified): ?>
                                    <i 
                                        class="fa-solid fa-circle-check text-primary fs-4 ms-2 align-middle" 
                                        data-bs-toggle="tooltip" 
                                        data-bs-placement="top" 
                                        title="Tài khoản hệ thống đã cung cấp đủ hồ sơ định danh hợp lệ"
                                        aria-label="Tài khoản đã xác thực"
                                    ></i>
                                <?php endif; ?>
                            </h1>
                            
                            <span class="badge bg-secondary mb-2 text-uppercase letter-spacing-1 px-3 py-2 rounded-pill shadow-sm">
                                <?= htmlspecialchars($user_type ?: 'Thành viên tập sự'); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mt-4 mt-md-0 pb-3">
                        <button 
                            type="button"
                            class="btn btn-primary fw-bold px-4 py-2 rounded-pill shadow-sm d-flex align-items-center gap-2" 
                            data-bs-toggle="modal" 
                            data-bs-target="#editProfileModal"
                            aria-label="Mở cửa sổ chỉnh sửa hồ sơ cá nhân"
                        >
                            <i class="fa-solid fa-pen-to-square"></i> Cập nhật hồ sơ
                        </button>
                    </div>
                </div>
                
                <div class="d-flex justify-content-center justify-content-md-start gap-4 gap-md-5 mt-5 pt-4 border-top border-light">
                    
                    <div class="text-center text-md-start">
                        <h3 class="fw-bold m-0 text-warning d-flex align-items-center gap-2 justify-content-center justify-content-md-start">
                            <i class="fa-solid fa-carrot fs-4"></i> <?= number_format($stats_xp); ?>
                        </h3>
                        <span class="text-muted small text-uppercase fw-bold letter-spacing-1 opacity-75">Điểm Cà Rốt</span>
                    </div>
                    
                    <div class="text-center text-md-start border-start ps-4 ps-md-5">
                        <h3 class="fw-bold m-0 text-dark d-flex align-items-center gap-2 justify-content-center justify-content-md-start">
                            <i class="fa-solid fa-users text-info fs-4"></i> <?= number_format($stats_buddies); ?>
                        </h3>
                        <span class="text-muted small text-uppercase fw-bold letter-spacing-1 opacity-75">Bạn cùng tiến</span>
                    </div>
                    
                    <div class="text-center text-md-start border-start ps-4 ps-md-5">
                        <h3 class="fw-bold m-0 text-dark d-flex align-items-center gap-2 justify-content-center justify-content-md-start">
                            <i class="fa-solid fa-book-open text-danger fs-4"></i> <?= number_format($stats_docs); ?>
                        </h3>
                        <span class="text-muted small text-uppercase fw-bold letter-spacing-1 opacity-75">Tài liệu</span>
                    </div>
                    
                </div>
            </div>
        </div> <ul class="nav nav-pills bg-white p-2 rounded-4 shadow-sm gap-2 border flex-nowrap overflow-auto" id="profileTabs" role="tablist">
            
            <li class="nav-item" role="presentation">
                <button 
                    class="nav-link active fw-bold px-4 py-2 rounded-pill d-flex align-items-center gap-2" 
                    id="timeline-tab" 
                    data-bs-toggle="tab" 
                    data-bs-target="#timeline" 
                    type="button" 
                    role="tab" 
                    aria-controls="timeline" 
                    aria-selected="true"
                >
                    <i class="fa-solid fa-house"></i> <span>Bảng tin</span>
                </button>
            </li>
            
            <li class="nav-item" role="presentation">
                <button 
                    class="nav-link fw-bold px-4 py-2 rounded-pill d-flex align-items-center gap-2" 
                    id="about-tab" 
                    data-bs-toggle="tab" 
                    data-bs-target="#about" 
                    type="button" 
                    role="tab" 
                    aria-controls="about" 
                    aria-selected="false"
                >
                    <i class="fa-solid fa-user-graduate"></i> <span>Giới thiệu</span>
                </button>
            </li>
            
            <li class="nav-item" role="presentation">
                <button 
                    class="nav-link fw-bold px-4 py-2 rounded-pill d-flex align-items-center gap-2" 
                    id="library-tab" 
                    data-bs-toggle="tab" 
                    data-bs-target="#library" 
                    type="button" 
                    role="tab" 
                    aria-controls="library" 
                    aria-selected="false"
                >
                    <i class="fa-solid fa-folder-open"></i> <span>Kho Tài liệu</span>
                </button>
            </li>
            
            <li class="nav-item" role="presentation">
                <button 
                    class="nav-link fw-bold px-4 py-2 rounded-pill d-flex align-items-center gap-2" 
                    id="events-tab" 
                    data-bs-toggle="tab" 
                    data-bs-target="#events" 
                    type="button" 
                    role="tab" 
                    aria-controls="events" 
                    aria-selected="false"
                >
                    <i class="fa-solid fa-calendar-days"></i> <span>Sự kiện</span>
                </button>
            </li>
            
            <li class="nav-item" role="presentation">
                <button 
                    class="nav-link fw-bold px-4 py-2 rounded-pill d-flex align-items-center gap-2" 
                    id="buddies-tab" 
                    data-bs-toggle="tab" 
                    data-bs-target="#buddies" 
                    type="button" 
                    role="tab" 
                    aria-controls="buddies" 
                    aria-selected="false"
                >
                    <i class="fa-solid fa-user-group"></i> <span>Bạn bè</span>
                </button>
            </li>
            
        </ul>

        <div class="tab-content mt-4" id="profileTabsContent">
            
            <div 
                class="tab-pane fade show active" 
                id="timeline" 
                role="tabpanel" 
                aria-labelledby="timeline-tab" 
                tabindex="0"
            >
                <div class="row g-4">
                    
                    <div class="col-12 col-lg-4">
                        
                        <div class="card shadow-sm border-0 rounded-4 bg-white sticky-top" style="top: 85px; z-index: 1;">
                            <div class="card-body p-4">
                                <h6 class="fw-bold mb-4 text-uppercase text-primary border-bottom pb-3"><i class="fa-solid fa-circle-info me-2"></i>Học vấn tóm tắt</h6>
                                
                                <ul class="list-unstyled d-flex flex-column gap-4 m-0 fs-6">
                                    <li class="d-flex align-items-start gap-3">
                                        <div class="bg-primary bg-opacity-10 p-2 rounded text-primary">
                                            <i class="fa-solid fa-school fs-5 w-20px text-center"></i>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block fw-medium mb-1">Trường THPT / Cấp 3</small>
                                            <strong class="text-dark"><?= htmlspecialchars($truong_hoc ?: 'Chưa cung cấp dữ liệu'); ?></strong>
                                        </div>
                                    </li>
                                    
                                    <li class="d-flex align-items-start gap-3">
                                        <div class="bg-success bg-opacity-10 p-2 rounded text-success">
                                            <i class="fa-solid fa-building-columns fs-5 w-20px text-center"></i>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block fw-medium mb-1">Đại học / Cao đẳng</small>
                                            <strong class="text-dark"><?= htmlspecialchars($truong_dai_hoc ?: 'Chưa cung cấp dữ liệu'); ?></strong>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-8">
                        
                        <div class="card shadow-sm border-0 mb-4 rounded-4 bg-white">
                            <div class="card-body p-4">
                                
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="add_post">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                                    
                                    <div class="d-flex gap-3">
                                        <img src="<?= htmlspecialchars($user_avatar); ?>" class="rounded-circle border border-2 border-light shadow-sm" width="55" height="55" style="object-fit: cover;" alt="Bạn">
                                        
                                        <textarea 
                                            class="form-control border-secondary-subtle bg-light rounded-4 p-3 fs-6" 
                                            name="noidung_post" 
                                            rows="3" 
                                            placeholder="Bạn muốn chia sẻ kiến thức, bài tập khó hay một câu chuyện học thuật hôm nay?..." 
                                            required 
                                            style="resize: none;"
                                        ></textarea>
                                    </div>
                                    
                                    <hr class="text-muted border-dashed my-4 opacity-25">
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-outline-danger btn-sm fw-bold rounded-pill px-3 shadow-sm" disabled title="Tính năng upload tệp đang bảo trì">
                                                <i class="fa-solid fa-file-pdf me-1"></i> File PDF
                                            </button>
                                            <button type="button" class="btn btn-outline-success btn-sm fw-bold rounded-pill px-3 shadow-sm" disabled title="Tính năng upload ảnh đang bảo trì">
                                                <i class="fa-solid fa-image me-1"></i> Hình ảnh
                                            </button>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary fw-bold px-4 py-2 rounded-pill shadow">
                                            <i class="fa-solid fa-paper-plane me-2"></i> Xuất bản bài viết
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <?php if (count($posts_data) > 0): ?>
                            
                            <?php foreach ($posts_data as $post): ?>
                            <article class="card shadow-sm border-0 mb-4 rounded-4 bg-white overflow-hidden">
                                <div class="card-body p-4">
                                    
                                    <header class="d-flex align-items-center justify-content-between mb-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <img src="../assets/img/default-avatar.jpg" class="rounded-circle border shadow-sm" width="55" height="55" style="object-fit: cover;" alt="Tác giả">
                                            
                                            <div>
                                                <h6 class="m-0 fw-bold text-dark fs-5 d-flex align-items-center gap-2">
                                                    <?= htmlspecialchars($post['username']); ?>
                                                    
                                                    <?php if(!empty($post['giay_to_chung_minh'])): ?>
                                                        <i class="fa-solid fa-circle-check text-primary fs-6" title="Xác minh học sinh thực"></i>
                                                    <?php endif; ?>
                                                </h6>
                                                
                                                <small class="text-muted fw-medium d-flex align-items-center gap-1">
                                                    <i class="fa-regular fa-clock"></i> 
                                                    <?= time_elapsed_string($post['created_at']); ?> 
                                                    <span class="mx-1">•</span> 
                                                    <i class="fa-solid fa-earth-americas" title="Công khai"></i>
                                                </small>
                                            </div>
                                        </div>
                                        
                                        <button class="btn btn-light rounded-circle text-muted shadow-sm" style="width:40px; height:40px;" aria-label="Tùy chọn bài viết">
                                            <i class="fa-solid fa-ellipsis"></i>
                                        </button>
                                    </header>
                                    
                                    <div class="post-content mt-3">
                                        <p class="m-0 fs-5 text-dark" style="line-height: 1.8; white-space: pre-line;">
                                            <?= htmlspecialchars($post['noi_dung']); ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <footer class="card-footer bg-light border-top border-light py-2 px-4">
                                    <div class="d-flex justify-content-between gap-2">
                                        <button class="btn btn-light flex-fill fw-bold text-secondary border-0 py-2 rounded-3 hover-bg-gray transition">
                                            <i class="fa-regular fa-thumbs-up fs-5 me-2"></i> Thích
                                        </button>
                                        <button class="btn btn-light flex-fill fw-bold text-secondary border-0 py-2 rounded-3 hover-bg-gray transition">
                                            <i class="fa-regular fa-comment fs-5 me-2"></i> Bình luận
                                        </button>
                                        <button class="btn btn-light flex-fill fw-bold text-secondary border-0 py-2 rounded-3 hover-bg-gray transition">
                                            <i class="fa-solid fa-share fs-5 me-2"></i> Chia sẻ
                                        </button>
                                    </div>
                                </footer>
                            </article>
                            <?php endforeach; ?>
                            
                        <?php else: ?>
                            <div class="text-center py-5 bg-white shadow-sm rounded-4 border border-light">
                                <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 100px; height: 100px;">
                                    <i class="fa-regular fa-pen-to-square fs-1 text-primary opacity-75"></i>
                                </div>
                                <h4 class="text-dark fw-bold">Chưa có dấu ấn học thuật</h4>
                                <p class="text-muted fs-6 m-0 px-4">Khu vực này dùng để lưu trữ và chia sẻ những bài đăng, câu hỏi và tiến độ học tập của bạn trên The Bunny.</p>
                            </div>
                        <?php endif; ?>
                        
                    </div>
                </div>
            </div> <div 
                class="tab-pane fade" 
                id="about" 
                role="tabpanel" 
                aria-labelledby="about-tab" 
                tabindex="0"
            >
                <div class="card shadow-sm border-0 p-md-5 rounded-4 bg-white">
                    <div class="card-body">
                        
                        <h3 class="fw-bold mb-4 border-bottom pb-4 text-primary"><i class="fa-solid fa-address-card me-3"></i>Hồ sơ Định danh Toàn diện</h3>
                        
                        <div class="row g-5">
                            <div class="col-md-6">
                                <h5 class="fw-bold text-dark mb-4"><i class="fa-solid fa-server text-secondary me-2"></i> Dữ liệu Hệ thống</h5>
                                
                                <dl class="row mb-0 fs-5">
                                    <dt class="col-sm-4 text-muted fw-normal mb-3">Tên tài khoản</dt>
                                    <dd class="col-sm-8 text-dark fw-bold mb-3"><?= htmlspecialchars($user_name); ?></dd>
                                    
                                    <dt class="col-sm-4 text-muted fw-normal mb-3">Nhóm quyền hạn</dt>
                                    <dd class="col-sm-8 mb-3"><span class="badge bg-primary fs-6 px-3 py-2"><?= htmlspecialchars($user_type ?: 'Chưa phân cấp'); ?></span></dd>
                                    
                                    <dt class="col-sm-4 text-muted fw-normal mb-3">Trạng thái Căn cước</dt>
                                    <dd class="col-sm-8 mb-3">
                                        <?php if($is_verified): ?>
                                            <span class="text-success fw-bold"><i class="fa-solid fa-shield-check"></i> Đã nộp Giấy tờ</span>
                                        <?php else: ?>
                                            <span class="text-danger fw-bold"><i class="fa-solid fa-triangle-exclamation"></i> Chưa xác minh</span>
                                        <?php endif; ?>
                                    </dd>
                                </dl>
                            </div>
                            
                            <div class="col-md-6">
                                <h5 class="fw-bold text-dark mb-4"><i class="fa-solid fa-book-open-reader text-secondary me-2"></i> Thông tin Tự giới thiệu</h5>
                                
                                <div class="bg-light p-4 rounded-4 border border-secondary-subtle h-100">
                                    <p class="m-0 fs-5 text-dark fw-medium" style="line-height: 1.8;">
                                        <?= nl2br(htmlspecialchars($thong_tin_dinh_danh ?: 'Học sinh này chưa cập nhật thông tin mô tả chi tiết năng lực học thuật.')); ?>
                                    </p>
                                </div>
                            </div>
                        </div> 
                        
                    </div>
                </div>
            </div> <div 
                class="tab-pane fade" 
                id="library" 
                role="tabpanel" 
                aria-labelledby="library-tab" 
                tabindex="0"
            >
                <div class="card shadow-sm border-0 p-md-5 rounded-4 bg-white">
                    <div class="card-body">
                        
                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-5 gap-3 border-bottom pb-4">
                            <h3 class="fw-bold m-0 text-dark"><i class="fa-solid fa-box-archive text-warning me-3"></i>Thư viện Học liệu số</h3>
                            <button 
                                class="btn btn-success fw-bold px-4 py-2 rounded-pill shadow-sm d-flex align-items-center gap-2" 
                                data-bs-toggle="modal" 
                                data-bs-target="#addDocModal"
                            >
                                <i class="fa-solid fa-cloud-arrow-up fs-5"></i> Cống hiến tài liệu
                            </button>
                        </div>
                        
                        <div class="row row-cols-1 row-cols-md-2 g-4">
                            
                            <?php if (count($docs_data) > 0): ?>
                                <?php foreach ($docs_data as $doc): ?>
                                <div class="col">
                                    <div class="border rounded-4 p-4 d-flex align-items-center justify-content-between bg-white shadow-sm h-100 border-start border-4 border-danger hover-transform transition">
                                        
                                        <div class="d-flex align-items-center gap-4 overflow-hidden">
                                            <div class="bg-danger bg-opacity-10 p-3 rounded-circle text-danger">
                                                <i class="fa-solid fa-file-pdf fs-3"></i>
                                            </div>
                                            
                                            <div class="overflow-hidden">
                                                <h5 class="m-0 fw-bold text-dark text-truncate mb-2" style="max-width: 250px;" title="<?= htmlspecialchars($doc['ten_tai_lieu']); ?>">
                                                    <?= htmlspecialchars($doc['ten_tai_lieu']); ?>
                                                </h5>
                                                
                                                <small class="text-muted fw-bold fs-6">
                                                    Tải lên: <?= date('d/m/Y', strtotime($doc['created_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                        
                                        <a href="<?= htmlspecialchars($doc['file_url']); ?>" target="_blank" class="btn btn-outline-primary rounded-circle shadow-sm ms-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;" aria-label="Tải file này">
                                            <i class="fa-solid fa-download fs-5"></i>
                                        </a>
                                        
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                
                            <?php else: ?>
                                <div class="col-12 text-center py-5">
                                    <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" alt="Empty" width="120" class="opacity-50 mb-4">
                                    <h4 class="text-dark fw-bold">Không gian tài liệu đang trống</h4>
                                    <p class="text-muted fs-5">Bấm nút "Cống hiến tài liệu" phía trên để lưu trữ đề cương và bài giảng của bạn trên The Bunny.</p>
                                </div>
                            <?php endif; ?>
                            
                        </div>
                    </div>
                </div>
            </div> <div 
                class="tab-pane fade" 
                id="events" 
                role="tabpanel" 
                aria-labelledby="events-tab" 
                tabindex="0"
            >
                <div class="card shadow-sm border-0 p-md-5 rounded-4 bg-white">
                    <div class="card-body">
                        
                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-5 gap-3 border-bottom pb-4">
                            <h3 class="fw-bold m-0 text-dark"><i class="fa-regular fa-calendar-check text-primary me-3"></i>Lịch trình Tổ chức Sự kiện</h3>
                            <button 
                                class="btn btn-warning fw-bold text-dark px-4 py-2 rounded-pill shadow-sm d-flex align-items-center gap-2" 
                                data-bs-toggle="modal" 
                                data-bs-target="#addEventModal"
                            >
                                <i class="fa-solid fa-calendar-plus fs-5"></i> Phát hành Lịch trình
                            </button>
                        </div>
                        
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                            
                            <?php if (count($events_data) > 0): ?>
                                <?php foreach ($events_data as $event): ?>
                                <div class="col">
                                    <div class="card h-100 border-0 shadow-sm bg-primary bg-opacity-10 rounded-4 overflow-hidden position-relative hover-lift transition">
                                        <div class="bg-primary w-100" style="height: 6px;"></div>
                                        <div class="card-body p-4">
                                            <div class="d-flex align-items-center gap-3 mb-4">
                                                
                                                <div class="bg-primary text-white text-center rounded-3 p-3 shadow-sm" style="min-width: 75px;">
                                                    <span class="d-block fw-bold fs-2 lh-1"><?= date('d', strtotime($event['thoi_gian'])); ?></span>
                                                    <span class="d-block small text-uppercase fw-bold opacity-75 mt-1">Tháng <?= date('m', strtotime($event['thoi_gian'])); ?></span>
                                                </div>
                                                
                                                <h5 class="fw-bold text-dark m-0 lh-base"><?= htmlspecialchars($event['tieu_de']); ?></h5>
                                                
                                            </div>
                                            
                                            <div class="bg-white bg-opacity-50 p-3 rounded-3 mb-3 text-center">
                                                <p class="text-dark fw-bold mb-2 fs-6">
                                                    <i class="fa-regular fa-clock w-20px text-primary text-center me-2"></i> 
                                                    Khởi chiếu: <?= date('H:i A', strtotime($event['thoi_gian'])); ?>
                                                </p>
                                            </div>
                                            
                                            <button class="btn btn-outline-primary w-100 fw-bold rounded-pill">Đã ghi danh tham dự</button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12 text-center py-5">
                                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 100px; height: 100px;">
                                        <i class="fa-regular fa-calendar-xmark fs-1 text-warning opacity-75"></i>
                                    </div>
                                    <h4 class="text-dark fw-bold">Không có lịch trình sắp tới</h4>
                                    <p class="text-muted fs-5 m-0 px-4">Hãy đóng vai trò là một diễn giả hoặc tổ chức một nhóm học tập trực tuyến mới ngay bây giờ.</p>
                                </div>
                            <?php endif; ?>
                            
                        </div>
                    </div>
                </div>
            </div> <div 
                class="tab-pane fade" 
                id="buddies" 
                role="tabpanel" 
                aria-labelledby="buddies-tab" 
                tabindex="0"
            >
                <div class="card shadow-sm border-0 p-md-5 rounded-4 bg-white">
                    <div class="card-body">
                        
                        <h3 class="fw-bold mb-5 border-bottom pb-4 text-dark d-flex align-items-center gap-3">
                            <i class="fa-solid fa-users-rays text-success fs-2"></i>
                            Mạng lưới Bạn cùng tiến 
                            <span class="badge bg-light text-dark border fs-5 rounded-pill px-3"><?= number_format($stats_buddies); ?></span>
                        </h3>
                        
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                            <?php if (count($buddies_data) > 0): ?>
                                <?php foreach ($buddies_data as $buddy): ?>
                                <div class="col">
                                    <div class="border border-light rounded-4 p-4 d-flex flex-column align-items-center text-center bg-white shadow-sm h-100 hover-border-primary transition">
                                        
                                        <img src="../assets/img/default-avatar.jpg" class="rounded-circle border border-3 border-light shadow-sm mb-3" width="90" height="90" style="object-fit: cover;" alt="Bạn bè">
                                        
                                        <div class="flex-grow-1 w-100 mb-3">
                                            <h5 class="fw-bold m-0 text-dark text-truncate px-2 mb-2" title="<?= htmlspecialchars($buddy['username']); ?>">
                                                <?= htmlspecialchars($buddy['username']); ?>
                                            </h5>
                                            <span class="badge bg-light text-secondary fw-bold text-wrap px-3 py-2 lh-base w-100">
                                                <i class="fa-solid fa-school me-1"></i> <?= htmlspecialchars($buddy['truong_hoc'] ?: 'Tự học tại The Bunny'); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="d-flex gap-2 w-100">
                                            <button class="btn btn-primary flex-fill fw-bold rounded-pill shadow-sm" aria-label="Gửi tin nhắn">
                                                <i class="fa-solid fa-comment-dots"></i> Nhắn tin
                                            </button>
                                            <button class="btn btn-light rounded-pill border shadow-sm px-3" aria-label="Xóa bạn bè">
                                                <i class="fa-solid fa-user-xmark text-danger"></i>
                                            </button>
                                        </div>
                                        
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                
                            <?php else: ?>
                                <div class="col-12 text-center py-5">
                                    <img src="https://cdn-icons-png.flaticon.com/512/3394/3394785.png" alt="No Friends" width="120" class="opacity-50 mb-4">
                                    <h4 class="text-dark fw-bold">Chưa kết nối học tập</h4>
                                    <p class="text-muted fs-5 m-0 px-4">Hãy khám phá Hang thỏ hoặc Bảng xếp hạng Thách đấu để tìm kiếm những người bạn cùng chung chí hướng.</p>
                                    <button class="btn btn-primary fw-bold px-5 py-2 rounded-pill mt-4">Tìm bạn học ngay</button>
                                </div>
                            <?php endif; ?>
                            
                        </div>
                    </div>
                </div>
            </div> </div> </div> <div 
        class="modal fade" 
        id="editProfileModal" 
        tabindex="-1" 
        aria-labelledby="editProfileModalLabel" 
        aria-hidden="true"
    >
        <div class="modal-dialog modal-dialog-centered modal-lg">
            
            <form method="POST" action="">
                
                <input type="hidden" name="action" value="edit_profile">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    
                    <div class="modal-header bg-primary bg-opacity-10 border-bottom-0 py-4 px-5">
                        <h4 class="modal-title fw-bold text-primary" id="editProfileModalLabel">
                            <i class="fa-solid fa-user-pen me-2"></i> Thiết lập Định danh Cá nhân
                        </h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng cửa sổ"></button>
                    </div>
                    
                    <div class="modal-body px-5 py-4 bg-white">
                        
                        <div class="alert alert-info border-0 rounded-3 mb-4 d-flex align-items-center gap-3">
                            <i class="fa-solid fa-circle-info fs-3"></i>
                            <div>
                                <strong>Lưu ý quan trọng:</strong> 
                                Các thông tin bạn cung cấp dưới đây sẽ liên kết chặt chẽ vào cơ sở dữ liệu hệ thống (Bảng <code>users</code> và <code>ho_so_ca_nhan</code>) và hiển thị công khai tới mọi người dùng khác.
                            </div>
                        </div>

                        <div class="row g-4">
                            
                            <div class="col-md-6">
                                <h6 class="fw-bold text-muted text-uppercase mb-3 border-bottom pb-2">Dữ liệu Học Vụ (Bảng Users)</h6>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-dark fs-6">Bí danh (Username) <span class="text-danger">*</span></label>
                                    <input 
                                        type="text" 
                                        class="form-control border-secondary-subtle py-2 px-3 fw-medium rounded-3" 
                                        name="username" 
                                        value="<?= htmlspecialchars($user_name); ?>" 
                                        required
                                        placeholder="Ví dụ: NguyenVanA_Khoi9"
                                    >
                                    <div class="form-text mt-2"><i class="fa-solid fa-shield-halved text-success"></i> Đây là tên để bạn bè dễ dàng tìm kiếm.</div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-dark fs-6">Trường Phổ thông Cơ sở/THPT</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-secondary-subtle"><i class="fa-solid fa-school text-muted"></i></span>
                                        <input 
                                            type="text" 
                                            class="form-control border-secondary-subtle py-2 px-3 fw-medium rounded-end-3" 
                                            name="truong_hoc" 
                                            value="<?= htmlspecialchars($truong_hoc); ?>" 
                                            placeholder="Gõ tên trường đầy đủ..."
                                        >
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-dark fs-6">Học viện / Đại học / Cao đẳng</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-secondary-subtle"><i class="fa-solid fa-building-columns text-muted"></i></span>
                                        <input 
                                            type="text" 
                                            class="form-control border-secondary-subtle py-2 px-3 fw-medium rounded-end-3" 
                                            name="truong_dai_hoc" 
                                            value="<?= htmlspecialchars($truong_dai_hoc); ?>" 
                                            placeholder="Gõ tên trường đại học..."
                                        >
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 d-flex flex-column">
                                <h6 class="fw-bold text-muted text-uppercase mb-3 border-bottom pb-2">Tự Giới Thiệu (Bảng Hồ Sơ)</h6>
                                
                                <div class="mb-3 flex-grow-1 d-flex flex-column">
                                    <label class="form-label fw-bold text-dark fs-6">Viết bài luận định danh về bản thân</label>
                                    <textarea 
                                        class="form-control border-secondary-subtle p-3 fw-medium rounded-3 flex-grow-1" 
                                        name="thong_tin_dinh_danh" 
                                        placeholder="Hãy dùng ô văn bản này để viết các thông tin mở rộng như: Sở thích học tập, châm ngôn sống, nơi sinh sống, hay các thành tích giải thưởng bạn đạt được... (Hệ thống sẽ giữ nguyên định dạng xuống dòng)"
                                        style="resize: none; min-height: 200px;"
                                    ><?= htmlspecialchars($thong_tin_dinh_danh); ?></textarea>
                                </div>
                            </div>
                            
                        </div>
                    </div> 
                    
                    <div class="modal-footer bg-light border-top-0 py-4 px-5 rounded-bottom-4 justify-content-between">
                        <small class="text-muted fw-medium"><i class="fa-solid fa-lock text-success"></i> Bảo mật bởi PDO MySQL</small>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-light fw-bold px-4 py-2 rounded-pill shadow-sm border" data-bs-dismiss="modal">Hủy tác vụ</button>
                            <button type="submit" class="btn btn-primary fw-bold px-5 py-2 rounded-pill shadow-sm">Đồng bộ Dữ liệu CSDL</button>
                        </div>
                    </div>
                </div> 
            </form>
        </div>
    </div>

    <div 
        class="modal fade" 
        id="addDocModal" 
        tabindex="-1" 
        aria-labelledby="addDocModalLabel" 
        aria-hidden="true"
    >
        <div class="modal-dialog modal-dialog-centered">
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_document">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    
                    <div class="modal-header bg-success text-white border-0 py-4 px-4">
                        <h4 class="modal-title fw-bold" id="addDocModalLabel">
                            <i class="fa-solid fa-cloud-arrow-up me-2 fs-3 align-middle"></i> Tải Tài liệu lên Máy chủ
                        </h4>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng cửa sổ"></button>
                    </div>
                    
                    <div class="modal-body p-4 bg-white">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark fs-6">Định danh Tên Tài Liệu <span class="text-danger">*</span></label>
                            <input 
                                type="text" 
                                class="form-control form-control-lg border-secondary-subtle fw-medium fs-6 rounded-3" 
                                name="ten_tai_lieu" 
                                placeholder="Ghi rõ bộ môn và chuyên đề. VD: Vật Lý 9 - Thấu kính hội tụ..." 
                                required
                            >
                        </div>
                        
                        <div class="mb-2 p-4 border border-dashed border-2 border-secondary-subtle rounded-3 text-center bg-light">
                            <i class="fa-solid fa-file-pdf fs-1 text-danger mb-3 d-block"></i>
                            <label class="form-label fw-bold text-dark fs-6 d-block mb-3">Kéo thả tệp tin vào đây hoặc chọn từ thiết bị</label>
                            <input type="file" class="form-control mx-auto w-75 shadow-sm" name="file_tai_lieu" disabled>
                            
                            <div class="alert alert-warning border-0 mt-4 text-start mb-0 small">
                                <i class="fa-solid fa-triangle-exclamation text-danger fw-bold me-1"></i> <strong>Chế độ Safe Mode:</strong> 
                                Để bảo vệ CSDL khỏi file chứa mã độc, luồng Upload tệp tin gốc đang tạm khóa. Hệ thống sẽ tự động gán đường dẫn giả lập vào CSDL.
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer border-0 py-3 px-4 bg-light">
                        <button type="button" class="btn btn-light fw-bold px-4 py-2 rounded-pill border shadow-sm" data-bs-dismiss="modal">Từ chối thao tác</button>
                        <button type="submit" class="btn btn-success fw-bold px-5 py-2 rounded-pill shadow-sm">Bắt đầu tải lên Database</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div 
        class="modal fade" 
        id="addEventModal" 
        tabindex="-1" 
        aria-labelledby="addEventModalLabel" 
        aria-hidden="true"
    >
        <div class="modal-dialog modal-dialog-centered">
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_event">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    
                    <div class="modal-header bg-warning border-0 py-4 px-4">
                        <h4 class="modal-title fw-bold text-dark" id="addEventModalLabel">
                            <i class="fa-solid fa-calendar-plus me-2 fs-3 align-middle"></i> Quản trị Lịch trình
                        </h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng cửa sổ"></button>
                    </div>
                    
                    <div class="modal-body p-4 bg-white">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark fs-6">Định danh Chủ đề Hội thảo / Buổi học <span class="text-danger">*</span></label>
                            <input 
                                type="text" 
                                class="form-control border-secondary-subtle py-2 px-3 fw-medium rounded-3" 
                                name="tieu_de" 
                                placeholder="Ghi rõ tên khóa học / sự kiện..." 
                                required
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark fs-6">Ấn định Thời gian Khởi chiếu (Live) <span class="text-danger">*</span></label>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text bg-primary text-white border-primary"><i class="fa-solid fa-clock"></i></span>
                                <input 
                                    type="datetime-local" 
                                    class="form-control border-secondary-subtle py-2 px-3 fw-medium" 
                                    name="thoi_gian" 
                                    required
                                >
                            </div>
                        </div>
                        
                    </div>
                    
                    <div class="modal-footer border-top-0 py-4 px-4 bg-light justify-content-between">
                        <small class="text-muted fw-bold"><i class="fa-solid fa-bell text-warning"></i> Bạn sẽ làm Quản trị viên (Host) sự kiện này.</small>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-light fw-bold px-4 py-2 rounded-pill border shadow-sm" data-bs-dismiss="modal">Trở về</button>
                            <button type="submit" class="btn btn-warning fw-bold px-5 py-2 rounded-pill shadow-sm text-dark">Phát hành Lịch</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

</body>
</html>