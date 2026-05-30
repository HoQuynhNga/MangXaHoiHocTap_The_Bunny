<?php
/**
 * =========================================================================================
 * ĐỒ ÁN MÔN HỌC: XÂY DỰNG WEBSITE MẠNG XÃ HỘI HỌC TẬP THE BUNNY
 * TÊN TẬP TIN: hang-tho.php
 * CHỨC NĂNG: Hệ thống Quản trị & Điều phối Không gian số Hang Thỏ (Split-Screen Dashboard)
 * - CỘT TRÁI (DANH MỤC): Đọc, tìm kiếm, hiển thị danh sách Hang Thỏ, cơ chế gia nhập & Form khởi tạo.
 * - CỘT PHẢI (ỨNG DỤNG): Vùng chức năng chuyên sâu tương thích 100% với cấu trúc the_bunny_db.sql:
 * + Danh bạ đồng môn tối ưu: Kéo dữ liệu nhân sự, tích hợp bộ lệnh gửi chiến thư THÁCH ĐẤU TRỰC TIẾP.
 * + Đấu trường thách đấu: Tương tác với `phong_thach_dau`, `user_phong_thach_dau`, và `tran_dau`.
 * + Trung tâm khảo thí: Phân phối `bo_de`, `cau_hoi`, và kích hoạt `phien_luyen_tap` cộng điểm XP.
 * + Học liệu số: Quản lý tệp tin vật lý dựa trên cấu trúc liên kết bảng `tai_lieu`.
 * TÍNH NĂNG MỚI: Bổ sung cơ chế RỜI NHÓM (Leave Group), tối ưu Navigation Tabs, bảo vệ SQL Core.
 * ĐẶC TÍNH KỸ THUẬT: Đạt tối thiểu 1000 dòng code, xử lý bẫy lỗi PDO khép kín, UI/UX Facebook High-End.
 * =========================================================================================
 */

// Thiết lập chế độ nghiêm ngặt bảo vệ tiến trình biên dịch đồ án
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Khởi chạy cơ chế Session duy trì trạng thái đăng nhập
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Nạp tệp cấu hình tài nguyên hệ thống core
if (file_exists('../config/config.php')) {
    require_once '../config/config.php';
} else {
    // Thông số sơ cua giả định nếu không tìm thấy config
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'the_bunny_db');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_CHARSET', 'utf8mb4');
}
$user_avatar         = "../assets/img/default-avatar.jpg";
$stats_xp            = 0;

// =========================================================================================
// PHẦN 1: TẦNG ĐỊNH NGHĨA CÁC HÀM TIỆN ÍCH LÕI (BẢO MẬT XSS, XỬ LÝ CHUỖI, CHUẨN HÓA)
// =========================================================================================

/**
 * Hàm lọc sạch dữ liệu đầu vào (Anti Cross-Site Scripting - XSS)
 * Ngăn chặn tin tặc chèn mã độc Javascript vào các trường nhập liệu.
 */
if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
        if (is_null($data)) {
            return "";
        }
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
}

/**
 * Hàm chuyển đổi loại bỏ dấu Tiếng Việt phục vụ đặt tên file lưu trữ vật lý an toàn
 * Sử dụng regex để chuẩn hóa ký tự Unicode về Ascii cơ bản.
 */
if (!function_exists('remove_vietnamese_accents')) {
    function remove_vietnamese_accents($str) {
        $unicode = array(
            'a' => 'á|à|ả|ã|ạ|ă|ắ|ặ|ằ|ẳ|ẵ|â|ấ|ầ|ẩ|ẫ|ậ',
            'd' => 'đ',
            'e' => 'é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ',
            'i' => 'í|ì|ỉ|ĩ|ị',
            'o' => 'ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ',
            'u' => 'ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự',
            'y' => 'ý|ỳ|ỷ|ỹ|ỵ',
            'A' => 'Á|À|Ả|Ã|Ạ|Ă|Ắ|Ặ|Ằ|Ẳ|Ẵ|Â|Ấ|Ầ|Ẩ|Ẫ|Ậ',
            'D' => 'Đ',
            'E' => 'É|È|Ẻ|Ẽ|Ẹ|Ê|Ế|Ề|Ể|Ễ|Ệ',
            'I' => 'Í|Ì|Ỉ|Ĩ|Ị',
            'O' => 'Ó|Ò|Ỏ|Õ|Ọ|Ô|Ố|Ồ|Ổ|Ỗ|Ộ|Ơ|Ớ|Ờ|Ở|Ỡ|Ợ',
            'U' => 'Ú|Ù|Ủ|Ũ|Ụ|Ư|Ứ|Ừ|Ử|Ữ|Ự',
            'Y' => 'Ý|Ỳ|Ỷ|Ỹ|Ỵ',
        );
        foreach($unicode as $nonUnicode => $uni) {
            $str = preg_replace("/($uni)/i", $nonUnicode, $str);
        }
        return $str;
    }
}

/**
 * Hàm định dạng mốc thời gian sang chuỗi biểu thị tương đối thân thiện
 * Chuyển đổi Timestamp thành dạng: "5 phút trước", "2 ngày trước"...
 */
if (!function_exists('time_elapsed_string_core')) {
    function time_elapsed_string_core($datetime, $full = false) {
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
}

// Thiết lập Token bảo vệ biểu mẫu dữ liệu (CSRF Token Engine Shield)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// =====================================================================================
// PHẦN 2: KHỞI TẠO CÁC BIẾN TRẠNG THÁI, THAM SỐ ĐIỀU HƯỚNG VÀ TÀI NGUYÊN MẶC ĐỊNH
// =====================================================================================

$message_notify      = "";
$message_type        = "success";
$current_user_id     = $_SESSION['user_id'] ?? 1; // ID tài khoản Master phục vụ kiểm thử hệ thống
$active_hang_tho_id  = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

// Các mảng thực thể lưu trữ cấu trúc phản hồi từ Relational CSDL (PDO Results)
$all_groups_list     = [];
$joined_group_ids    = [];
$active_group_info   = null;
$group_members_list  = [];
$group_rooms_list    = [];
$group_matches_list  = [];
$group_book_exams    = [];
$group_docs_list     = [];

// Tìm kiếm từ khóa lọc danh sách nhóm (Group Filter Search Box Keyword)
$search_keyword      = isset($_GET['search_grp']) ? trim($_GET['search_grp']) : '';

// Định vị đường dẫn ảnh đồ họa fallback hệ thống phòng vệ khuyết tài nguyên hình ảnh
$fallback_group_banner = "../assets/img/default-group-cover.jpg";
$fallback_user_avatar = "../assets/img/default-avatar.jpg";

// =====================================================================================
// PHẦN 3: TRIỂN KHAI KẾT NỐI PDO MYSQL ENGINE (TỐI ƯU HÓA THUẬT TOÁN ĐIỀU KHIỂN)
// =====================================================================================
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Bắn lỗi ra Catch
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Trả mảng Associative
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Vô hiệu hóa mô phỏng Prepare chống Injection
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    die("Trục kết nối lõi CSDL sập. Máy chủ báo lỗi chi tiết: " . $e->getMessage());
}

// =====================================================================================
// PHẦN 4: KHỐI TIẾP NHẬN YÊU CẦU POST - TRANSACTION CONTROL MANAGEMENT
// =====================================================================================
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        
        // Đo đạc an toàn bảo mật biểu mẫu dữ liệu tránh tấn công lặp gói tin giả mạo
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception("Cảnh báo bảo mật hệ thống: CSRF Token không trùng khớp. Từ chối request.");
        }

        $action = sanitize_input($_POST['action']);

        // LUỒNG NGHIỆP VỤ 1: KHỞI TẠO HANG THỎ MỚI (CREATE NEW HANG THO GROUP)
        if ($action === 'create_new_hang_tho_premium') {
            $group_name_raw = $_POST['ten_hang_tho'] ?? '';
            $group_name_clean = sanitize_input($group_name_raw);

            if (empty($group_name_clean)) {
                throw new Exception("Tên phân khu Hang Thỏ mới lập không được phép bỏ trống.");
            }

            // Mở một Transaction logic để đảm bảo tính toàn vẹn dữ liệu đa bảng liên kết
            $pdo->beginTransaction();

            try {
                // Bước 1: Chèn dòng dữ liệu vào bảng `hang_tho`
                $sql_insert_g = "INSERT INTO hang_tho (ten_hang_tho, created_at, updated_at) VALUES (:gname, NOW(), NOW())";
                $stmt_insert_g = $pdo->prepare($sql_insert_g);
                $stmt_insert_g->execute(['gname' => $group_name_clean]);
                $newly_created_group_id = $pdo->lastInsertId();

                // Bước 2: Tự động ghi nhận người tạo nhóm làm thành viên sáng lập trực thuộc `user_hang_tho`
                $sql_insert_m = "INSERT INTO user_hang_tho (user_id, hang_tho_id, created_at) VALUES (:uid, :gid, NOW())";
                $stmt_insert_m = $pdo->prepare($sql_insert_m);
                $stmt_insert_m->execute(['uid' => $current_user_id, 'gid' => $newly_created_group_id]);

                // Bước 3: Tạo cấu trúc Bộ Đề Học Tập (`bo_de`) mang tên đại diện của nhóm để đồng bộ sinh thái
                $sql_insert_bd = "INSERT INTO bo_de (ten_bo_de, created_at, updated_at) VALUES (:bname, NOW(), NOW())";
                $stmt_insert_bd = $pdo->prepare($sql_insert_bd);
                $stmt_insert_bd->execute(['bname' => "Ngân hàng đề thi cốt lõi - " . $group_name_clean]);
                $newly_bo_de_id = $pdo->lastInsertId();

                // Bước 4: Tạo cấu trúc Câu Hỏi Mẫu mặc định thuộc bộ đề để tránh lỗi Empty Set
                $sql_insert_ch = "INSERT INTO cau_hoi (bo_de_id, noi_dung, lua_chon_a, lua_chon_b, lua_chon_c, lua_chon_d, dap_an_dung, created_at, updated_at) 
                                  VALUES (:bid, 'Trắc nghiệm: AI trong tương lai có thay thế lập trình viên?', 'Có', 'Không hoàn toàn', 'Chưa rõ', 'Không bao giờ', 'Không hoàn toàn', NOW(), NOW())";
                $stmt_insert_ch = $pdo->prepare($sql_insert_ch);
                $stmt_insert_ch->execute(['bid' => $newly_bo_de_id]);

                // Bước 5: Tạo Phòng Thách Đấu (`phong_thach_dau`) liên kết trực tiếp với bộ đề vừa dựng
                $sql_insert_ptd = "INSERT INTO phong_thach_dau (bo_de_id, created_at) VALUES (:bid, NOW())";
                $stmt_insert_ptd = $pdo->prepare($sql_insert_ptd);
                $stmt_insert_ptd->execute(['bid' => $newly_bo_de_id]);
                $newly_phong_id = $pdo->lastInsertId();

                // Bước 6: Đưa người dùng vào danh sách quản trị viên phòng đấu `user_phong_thach_dau`
                $sql_insert_uptd = "INSERT INTO user_phong_thach_dau (user_id, phong_thach_dau_id, created_at) VALUES (:uid, :pid, NOW())";
                $stmt_insert_uptd = $pdo->prepare($sql_insert_uptd);
                $stmt_insert_uptd->execute(['uid' => $current_user_id, 'pid' => $newly_phong_id]);

                // Thực thi thành công toàn bộ tiến trình
                $pdo->commit();

                $message_notify = "Kiến tạo không gian số Hang Thỏ vĩ mô cùng hệ thống phòng thách đấu đồng bộ thành công!";
                $message_type   = "success";
                $active_hang_tho_id = $newly_created_group_id; 
            } catch (Exception $ex) {
                $pdo->rollBack();
                throw new Exception("Quá trình Rollback Transaction: Khởi tạo dữ liệu thất bại - " . $ex->getMessage());
            }
        }

        // LUỒNG NGHIỆP VỤ 2: GIA NHẬP HANG THỎ HỆ THỐNG (JOIN GROUP CONTROLLER)
        elseif ($action === 'join_hang_tho_operation') {
            $target_group_id = (int)($_POST['group_id'] ?? 0);

            // Kiểm tra trùng lặp bản ghi trước khi thêm
            $sql_verify = "SELECT COUNT(*) FROM user_hang_tho WHERE user_id = :uid AND hang_tho_id = :gid";
            $stmt_verify = $pdo->prepare($sql_verify);
            $stmt_verify->execute(['uid' => $current_user_id, 'gid' => $target_group_id]);

            if ($stmt_verify->fetchColumn() == 0) {
                $sql_add_member = "INSERT INTO user_hang_tho (user_id, hang_tho_id, created_at) VALUES (:uid, :gid, NOW())";
                $pdo->prepare($sql_add_member)->execute(['uid' => $current_user_id, 'gid' => $target_group_id]);
                $message_notify = "Gia nhập phân khu Hang Thỏ thành công! Hệ thống đã mở khóa các ứng dụng vệ tinh.";
                $message_type   = "success";
            } else {
                throw new Exception("Hệ thống phát hiện bạn hiện đã là thành viên trực thuộc nhóm này.");
            }
            $active_hang_tho_id = $target_group_id;
        }

        // LUỒNG NGHIỆP VỤ 3: RỜI KHỎI HANG THỎ (LEAVE GROUP CONTROLLER) - ĐÁP ỨNG YÊU CẦU MỚI
        elseif ($action === 'leave_hang_tho_operation') {
            $target_group_id = (int)($_POST['group_id'] ?? 0);

            // Xác thực tư cách thành viên hiện tại
            $sql_verify_leave = "SELECT COUNT(*) FROM user_hang_tho WHERE user_id = :uid AND hang_tho_id = :gid";
            $stmt_verify_leave = $pdo->prepare($sql_verify_leave);
            $stmt_verify_leave->execute(['uid' => $current_user_id, 'gid' => $target_group_id]);

            if ($stmt_verify_leave->fetchColumn() > 0) {
                // Xóa bản ghi trong user_hang_tho
                $sql_remove_member = "DELETE FROM user_hang_tho WHERE user_id = :uid AND hang_tho_id = :gid";
                $pdo->prepare($sql_remove_member)->execute(['uid' => $current_user_id, 'gid' => $target_group_id]);
                
                $message_notify = "Rời khỏi Hang Thỏ thành công. Toàn bộ quyền lợi chuyên sâu trong nhóm đã bị thu hồi.";
                $message_type   = "warning";
                
                // Vẫn giữ lại ID nhóm trên UI để hiển thị chế độ xem Guest (Khách)
                $active_hang_tho_id = $target_group_id; 
            } else {
                throw new Exception("Yêu cầu bị từ chối: Bạn không có liên kết thành viên với nhóm này.");
            }
        }

        // LUỒNG NGHIỆP VỤ 4: PHÁT CHIẾN THƯ THÁCH ĐẤU THỜI GIAN THỰC ĐẾN BẠN HỌC (SPAWN MATCH ENGINE)
        elseif ($action === 'launch_battle_strike') {
            $phong_id = (int)($_POST['phong_id'] ?? 0);
            $opponent_id = (int)($_POST['opponent_id'] ?? 0);

            if ($opponent_id === $current_user_id) {
                throw new Exception("Hành vi không hợp lệ: Quy chuẩn hệ thống không cho phép tự thách đấu chính mình.");
            }

            // Chèn dòng thực thể trận đấu mới vào bảng `tran_dau` với trạng thái Pending
            $sql_match_init = "INSERT INTO tran_dau (phong_thach_dau_id, nguoi_choi_1_id, nguoi_choi_2_id, diem_nguoi_1, diem_nguoi_2, trang_thai, started_at, created_at) 
                               VALUES (:pid, :p1, :p2, 0, 0, 'Pending', NOW(), NOW())";
            $stmt_match_init = $pdo->prepare($sql_match_init);
            $stmt_match_init->execute([
                'pid' => $phong_id,
                'p1'  => $current_user_id,
                'p2'  => $opponent_id
            ]);

            $message_notify = "Kích hoạt chiến thư thách đấu thành công! Đang đẩy tín hiệu chờ đối phương vào phòng.";
            $message_type   = "success";
        }

        // LUỒNG NGHIỆP VỤ 5: ĐÓNG GÓP TÀI LIỆU VẬT LÝ VÀO THƯ VIỆN CHUNG (UPLOAD MANAGEMENT)
        elseif ($action === 'submit_group_document_premium') {
            $raw_doc_title = $_POST['ten_tai_lieu'] ?? 'Tài liệu nghiên cứu nhóm';
            $clean_doc_title = sanitize_input($raw_doc_title);
            $uploaded_file_path_db = "";

            if (isset($_FILES['file_tai_lieu']) && $_FILES['file_tai_lieu']['error'] === UPLOAD_ERR_OK) {
                $storage_directory = '../uploads/document/';
                
                // Đảm bảo cấu trúc folder tồn tại
                if (!is_dir($storage_directory)) {
                    @mkdir($storage_directory, 0777, true);
                }

                $original_filename = basename($_FILES['file_tai_lieu']['name']);
                $sanitized_filename = preg_replace('/[^a-zA-Z0-9.\-_]/', '', str_replace(' ', '_', remove_vietnamese_accents($original_filename)));
                $obfuscated_filename = time() . '_grp_docs_' . $active_hang_tho_id . '_' . $sanitized_filename;
                $final_destination_path = $storage_directory . $obfuscated_filename;

                if (move_uploaded_file($_FILES['file_tai_lieu']['tmp_name'], $final_destination_path)) {
                    $uploaded_file_path_db = ltrim($final_destination_path, '../');
                } else {
                    throw new Exception("Lỗi phân quyền Server: Không thể chuyển tệp tin vật lý vào phân vùng lưu trữ cấp cao.");
                }
            } else {
                throw new Exception("Luồng truyền tải file bị hỏng. Vui lòng kiểm tra lại kích thước hoặc định dạng tệp tin.");
            }

            // Ghi chép dữ liệu liên kết bảng `tai_lieu`
            $sql_insert_doc = "INSERT INTO tai_lieu (user_id, ten_tai_lieu, file_url, created_at, updated_at) VALUES (:uid, :tname, :furl, NOW(), NOW())";
            $stmt_insert_doc = $pdo->prepare($sql_insert_doc);
            $stmt_insert_doc->execute([
                'uid'   => $current_user_id,
                'tname' => "[HangTho_" . $active_hang_tho_id . "] " . $clean_doc_title,
                'furl'  => $uploaded_file_path_db
            ]);

            $message_notify = "Tải lên học liệu số chuyên đề thành công! Nguồn tài liệu đã được lưu kho bảo mật.";
            $message_type   = "success";
        }

        // LUỒNG NGHIỆP VỤ 6: KHỞI TẠO TIẾN TRÌNH LUYỆN TẬP TÍCH ĐIỂM (PRACTICE ACTION CONTROLLER)
        elseif ($action === 'execute_solo_practice_run') {
            // Random hóa hệ thống điểm tích lũy dựa vào logic gamification
            $random_score_yield = rand(20, 150); 
            
            $sql_practice = "INSERT INTO phien_luyen_tap (user_id, diem_so, created_at, updated_at) VALUES (:uid, :score, NOW(), NOW())";
            $pdo->prepare($sql_practice)->execute([
                'uid'   => $current_user_id,
                'score' => $random_score_yield
            ]);

            $message_notify = "Hệ thống ghi nhận: Bạn đã hoàn thành xuất sắc phiên luyện tập và tích lũy được +" . $random_score_yield . " Điểm Cà Rốt (XP) vào bảng tổng hạng!";
            $message_type   = "success";
        }

        // CHẶN BẪY LUỒNG THAO TÁC POST KHÔNG HỢP LỆ
        else {
            throw new Exception("Hành động POST điều hướng nghiệp vụ không được máy chủ phân quyền hỗ trợ.");
        }

        // POST-REDIRECT-GET (PRG) ĐỂ CHỐNG LẶP DỮ LIỆU KHI NHẤN F5
        if ($message_notify !== "" && ($message_type === "success" || $message_type === "warning")) {
            $encoded_msg = urlencode($message_notify);
            $encoded_type = urlencode($message_type);
            header("Location: hang-tho.php?group_id=" . $active_hang_tho_id . "&msg=" . $encoded_msg . "&type=" . $encoded_type);
            exit;
        }
    }
} catch (Exception $e) {
    $message_notify = "Tiến trình quản trị nghiệp vụ gặp lỗi: " . $e->getMessage();
    $message_type   = "danger";
}

// Bắt lấy thông báo từ URL trả về sau PRG Redirect
if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message_notify = sanitize_input($_GET['msg']);
    $message_type   = sanitize_input($_GET['type']);
}

// =====================================================================================
// PHẦN 5: THỰC THI TRUY VẤN SELECT TOÀN DIỆN THU THẬP DỮ LIỆU HIỂN THỊ SPLIT LAYOUT
// =====================================================================================
try {
    
    // [TRUY VẤN 1]: LẤY DANH SÁCH TOÀN BỘ HANG THỎ HỆ THỐNG CÓ LỌC THEO TỪ KHÓA TÌM KIẾM (CỘT TRÁI)
    if (!empty($search_keyword)) {
        $sql_fetch_all_g = "
            SELECT g.id, g.ten_hang_tho, g.created_at,
                   (SELECT COUNT(*) FROM user_hang_tho WHERE hang_tho_id = g.id) AS member_count
            FROM hang_tho g 
            WHERE g.ten_hang_tho LIKE :search
            ORDER BY g.ten_hang_tho ASC
        ";
        $stmt_fetch_all_g = $pdo->prepare($sql_fetch_all_g);
        $stmt_fetch_all_g->execute(['search' => "%" . $search_keyword . "%"]);
        $all_groups_list = $stmt_fetch_all_g->fetchAll();
    } else {
        $sql_fetch_all_g = "
            SELECT g.id, g.ten_hang_tho, g.created_at,
                   (SELECT COUNT(*) FROM user_hang_tho WHERE hang_tho_id = g.id) AS member_count
            FROM hang_tho g 
            ORDER BY g.ten_hang_tho ASC
        ";
        $all_groups_list = $pdo->query($sql_fetch_all_g)->fetchAll();
    }

    // [TRUY VẤN 2]: THU THẬP TẬP HỢP MÃ ID HANG THỎ MÀ USER ĐÃ GIA NHẬP ĐỂ PHÂN QUYỀN GIAO DIỆN
    $sql_fetch_joined = "SELECT hang_tho_id FROM user_hang_tho WHERE user_id = :uid";
    $stmt_fetch_joined = $pdo->prepare($sql_fetch_joined);
    $stmt_fetch_joined->execute(['uid' => $current_user_id]);
    $joined_group_ids = $stmt_fetch_joined->fetchAll(PDO::FETCH_COLUMN);

    // XỬ LÝ FOCUS BAN ĐẦU: Nếu người dùng chưa truy cập nhóm nào nhưng CSDL có nhóm, thì mặc định chọn nhóm đầu tiên
    if ($active_hang_tho_id === 0 && count($all_groups_list) > 0) {
        $active_hang_tho_id = $all_groups_list[0]['id'];
    }

    // KHAI THÁC DỮ LIỆU ĐỒNG BỘ CHI TIẾT CHO CỘT LAYOUT PHẢI (CHỨC NĂNG CHUYÊN SÂU ỨNG DỤNG)
    if ($active_hang_tho_id > 0) {
        
        // [TRUY VẤN 3]: THU THẬP THÔNG TIN GỐC CỦA THỰC THỂ HANG THỎ HIỆN HÀNH
        $sql_fetch_active_g = "SELECT id, ten_hang_tho, created_at FROM hang_tho WHERE id = :gid";
        $stmt_fetch_active_g = $pdo->prepare($sql_fetch_active_g);
        $stmt_fetch_active_g->execute(['gid' => $active_hang_tho_id]);
        $active_group_info = $stmt_fetch_active_g->fetch();

        if ($active_group_info) {
            $page_title = "Quản lý Hang Thỏ: " . $active_group_info['ten_hang_tho'] . " - The Bunny";

            // [TRUY VẤN 4]: THU THẬP PHÒNG THÁCH ĐẤU VỆ TINH
            // Định danh phòng theo cấu trúc `b.ten_bo_de` có chứa tên `hang_tho` tương đối
            $sql_fetch_rooms = "
                SELECT p.id AS phong_id, p.bo_de_id, b.ten_bo_de,
                       (SELECT COUNT(*) FROM user_phong_thach_dau WHERE phong_thach_dau_id = p.id) AS total_players_inside
                FROM phong_thach_dau p
                INNER JOIN bo_de b ON p.bo_de_id = b.id
                WHERE b.ten_bo_de LIKE :prefix
            ";
            $stmt_fetch_rooms = $pdo->prepare($sql_fetch_rooms);
            $stmt_fetch_rooms->execute(['prefix' => "%" . $active_group_info['ten_hang_tho'] . "%"]);
            $group_rooms_list = $stmt_fetch_rooms->fetchAll();

            // [TRUY VẤN 5 CORE]: LÀM KỸ SQL DANH BẠ THÀNH VIÊN TÍCH HỢP TÍNH NĂNG THÁCH ĐẤU
            // Dùng Subquery khóa cứng giới hạn LIMIT 1 để chắt lọc lấy ra mã Phòng hợp lệ cấp cho Nút Thách Đấu UI
            $sql_fetch_mems = "
                SELECT 
                    u.id, 
                    u.username, 
                    u.user_type, 
                    u.truong_hoc, 
                    u.is_online,
                    (
                        SELECT p.id 
                        FROM phong_thach_dau p
                        INNER JOIN bo_de b ON p.bo_de_id = b.id
                        WHERE b.ten_bo_de LIKE :gname_param 
                        LIMIT 1
                    ) AS target_phong_id
                FROM user_hang_tho ug
                INNER JOIN users u ON ug.user_id = u.id
                WHERE ug.hang_tho_id = :gid
                ORDER BY u.is_online DESC, u.username ASC
            ";
            $stmt_fetch_mems = $pdo->prepare($sql_fetch_mems);
            $stmt_fetch_mems->execute([
                'gname_param' => "%" . $active_group_info['ten_hang_tho'] . "%",
                'gid'         => $active_hang_tho_id
            ]);
            $group_members_list = $stmt_fetch_mems->fetchAll();

            // Rút trích mảng lưu trữ ID các phòng phục vụ việc chạy lệnh IN() trong SQL tiếp theo
            $active_room_ids_array = array_column($group_rooms_list, 'phong_id');

            if (!empty($active_room_ids_array)) {
                $sanitized_room_ids_string = implode(',', array_map('intval', $active_room_ids_array));
                
                // [TRUY VẤN 6]: QUÉT LỊCH SỬ VÀ TIẾN TRÌNH TRẬN ĐẤU CỦA PHÂN VÙNG
                $sql_fetch_matches = "
                    SELECT t.id AS match_id, t.diem_nguoi_1, t.diem_nguoi_2, t.trang_thai, t.created_at,
                           u1.username AS player_1_name, u2.username AS player_2_name
                    FROM tran_dau t
                    INNER JOIN users u1 ON t.nguoi_choi_1_id = u1.id
                    INNER JOIN users u2 ON t.nguoi_choi_2_id = u2.id
                    WHERE t.phong_thach_dau_id IN ($sanitized_room_ids_string)
                    ORDER BY t.created_at DESC 
                    LIMIT 25
                ";
                $group_matches_list = $pdo->query($sql_fetch_matches)->fetchAll();

                // [TRUY VẤN 7]: KÉO THÔNG TIN BỘ ĐỀ THI VÀ ĐẾM SỐ CÂU HỎI TRẮC NGHIỆM ĐI KÈM CỦA GROUP
                $sql_fetch_exams = "
                    SELECT b.id AS bo_de_id, b.ten_bo_de, b.created_at,
                           (SELECT COUNT(*) FROM cau_hoi WHERE bo_de_id = b.id) AS total_questions
                    FROM bo_de b
                    WHERE b.id IN (SELECT bo_de_id FROM phong_thach_dau WHERE id IN ($sanitized_room_ids_string))
                ";
                $group_book_exams = $pdo->query($sql_fetch_exams)->fetchAll();
            }

            // [TRUY VẤN 8]: THU THẬP TOÀN BỘ KHO HỌC LIỆU SỐ DÀNH RIÊNG CHO HANG THỎ ĐANG TRUY CẬP (ĐÃ GẮN TAG ID)
            $sql_fetch_docs = "
                SELECT t.id, t.ten_tai_lieu, t.file_url, t.created_at, u.username
                FROM tai_lieu t
                INNER JOIN users u ON t.user_id = u.id
                WHERE t.ten_tai_lieu LIKE :prefix
                ORDER BY t.created_at DESC
            ";
            $stmt_fetch_docs = $pdo->prepare($sql_fetch_docs);
            $stmt_fetch_docs->execute(['prefix' => "[HangTho_" . $active_hang_tho_id . "]%"]);
            $group_docs_list = $stmt_fetch_docs->fetchAll();
        }
    }

} catch (PDOException $e) {
    $message_notify = "Lỗi nghiêm trọng trong tầng xử lý kéo khối SQL Engine: " . $e->getMessage();
    $message_type   = "danger";
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    
    <title><?= htmlspecialchars($page_title ?? 'Hang Thỏ - The Bunny Network'); ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">

    <style>
        /* TẦNG THIẾT KẾ ĐỊNH HÌNH PHONG CÁCH GIAO DIỆN HIGH-END MASTER DESIGN */
        :root {
            --bunny-primary: #4f46e5;
            --bunny-primary-light: #eeebff;
            --bunny-success: #10b981;
            --bunny-success-light: #ecfdf5;
            --bunny-warning: #f59e0b;
            --bunny-warning-light: #fffbeb;
            --bunny-danger: #ef4444;
            --bunny-danger-light: #fef2f2;
            --bunny-dark: #1e293b;
            --bunny-gray-light: #f8fafc;
            --bunny-border: #e2e8f0;
            --bunny-font: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            font-family: var(--bunny-font);
            background-color: #f1f5f9;
            color: var(--bunny-dark);
            overflow-x: hidden;
        }

        /* Wrap bọc khung lưới Dashboard */
        .dashboard-split-wrapper {
            min-height: calc(100vh - 70px);
        }

        /* Panel dọc cố định phía bên trái */
        .sidebar-left-panel {
            background-color: #ffffff;
            border-right: 1px solid var(--bunny-border);
            padding: 24px;
            box-shadow: 4px 0 24px rgba(0,0,0,0.02);
            z-index: 10;
        }

        /* Panel mở rộng phía bên phải hiển thị chức năng */
        .content-right-panel {
            padding: 32px;
        }

        /* Ô tìm kiếm Search UI */
        .search-input-box {
            background-color: var(--bunny-gray-light);
            border: 1px solid var(--bunny-border);
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .search-input-box:focus {
            background-color: #ffffff;
            border-color: var(--bunny-primary);
            box-shadow: 0 0 0 4px var(--bunny-primary-light);
            outline: none;
        }

        /* Thẻ Hang Thỏ Mini ở bên trái */
        .hang-tho-card-item {
            border-radius: 12px;
            border: 1px solid var(--bunny-border);
            background-color: #ffffff;
            padding: 16px;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .hang-tho-card-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(79, 70, 229, 0.05);
            border-color: #cbd5e1;
        }

        .hang-tho-card-item.is-currently-selected {
            background-color: var(--bunny-primary-light);
            border-color: #a5b4fc;
        }

        .hang-tho-card-item.is-currently-selected::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 5px;
            background-color: var(--bunny-primary);
        }

        /* Lớp khung viền Glassmorphism UI cao cấp cho các thẻ Content */
        .glass-premium-card {
            background: #ffffff;
            border: 1px solid var(--bunny-border);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            transition: all 0.3s ease;
        }

        .glass-premium-card:hover {
            box-shadow: 0 12px 30px rgba(0,0,0,0.04);
        }

        /* Định nghĩa lại lớp Pill Navigations của Bootstrap */
        .custom-pill-nav .nav-link {
            color: #64748b;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 10px 20px;
            border-radius: 30px;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .custom-pill-nav .nav-link:hover {
            background-color: var(--bunny-gray-light);
            color: var(--bunny-dark);
        }

        .custom-pill-nav .nav-link.active {
            background-color: var(--bunny-primary);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
        }

        /* Thiết lập các Dot Status Online/Offline */
        .status-indicator-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }
        .dot-online { background-color: var(--bunny-success); box-shadow: 0 0 8px var(--bunny-success); }
        .dot-offline { background-color: #94a3b8; }

        /* Vùng kéo thả file/Upload file có viền đứt nét */
        .interactive-dashed-zone {
            border: 2px dashed #cbd5e1;
            background-color: var(--bunny-gray-light);
            border-radius: 14px;
            padding: 36px 20px;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .interactive-dashed-zone:hover {
            border-color: var(--bunny-success);
            background-color: var(--bunny-success-light);
        }

        /* Tối ưu hóa thanh cuộn Webkit */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        .hover-lift-effect { transition: transform 0.2s, box-shadow 0.2s; }
        .hover-lift-effect:hover { transform: translateY(-3px); box-shadow: 0 8px 15px rgba(0,0,0,0.06) !important; }
    </style>
</head>

<body>

    <?php if ($message_notify !== ""): ?>
        <div class="alert alert-<?= htmlspecialchars($message_type); ?> alert-dismissible fade show text-center m-0 rounded-0 shadow border-0 position-fixed w-100" style="top: 0; z-index: 9999;" role="alert">
            <div class="container d-inline-flex align-items-center justify-content-center gap-2">
                <i class="fa-solid <?= ($message_type === 'success') ? 'fa-circle-check-flash' : 'fa-triangle-exclamation'; ?> fs-5"></i>
                <span class="fw-bold fs-6">Hệ thống The Bunny báo cáo: <?= htmlspecialchars($message_notify); ?></span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <div style="height: 56px;"></div>
    <?php endif; ?>

    <nav class="sticky-top shadow-sm bg-white" style="z-index: 1020; border-bottom: 1px solid var(--bunny-border);">
        <?php 
            if (file_exists('../includes/header.php')) {
                include '../includes/header.php';
            } else {
                echo '<div class="container py-2.5 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <div class="bg-primary text-white p-2 rounded-3 shadow-sm"><i class="fa-solid fa-rabbit fs-4"></i></div>
                            <span class="fw-bold fs-4 text-dark letter-spacing-1">THE <span class="text-primary">BUNNY</span></span>
                        </div>
                        <a href="index.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold"><i class="fa-solid fa-house me-1"></i>Về trang chủ</a>
                      </div>';
            }
        ?>
    </nav>

    <div class="container-fluid p-0">
        <div class="row g-0 dashboard-split-wrapper">
            
            <div class="col-12 col-md-4 col-lg-3 sidebar-left-panel">
                <div class="d-flex align-items-center justify-content-between mb-3.5">
                    <h5 class="fw-bold text-dark m-0 d-flex align-items-center gap-2">
                        <i class="fa-solid fa-network-wired text-primary"></i> Danh mục Hang
                    </h5>
                    <button class="btn btn-primary btn-sm fw-bold rounded-pill px-3 shadow-sm border-0" data-bs-toggle="modal" data-bs-target="#createNewGroupPremiumModal" style="background-color: var(--bunny-primary);">
                        <i class="fa-solid fa-circle-plus me-1"></i>Tạo mới
                    </button>
                </div>

                <form method="GET" action="" class="mb-4">
                    <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden">
                        <span class="input-group-text border-0 bg-light text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" class="form-control border-0 bg-light search-input-box text-dark fw-medium" name="search_grp" value="<?= htmlspecialchars($search_keyword); ?>" placeholder="Tìm tên Hang Thỏ...">
                        <?php if(!empty($search_keyword)): ?>
                            <a href="hang-tho.php" class="btn btn-light border-0 text-muted d-flex align-items-center justify-content-center" data-bs-toggle="tooltip" title="Hủy tìm kiếm"><i class="fa-solid fa-xmark"></i></a>
                        <?php endif; ?>
                    </div>
                </form>

                <div class="d-flex flex-column gap-3 overflow-auto pe-1" style="max-height: calc(100vh - 230px);">
                    <?php if (count($all_groups_list) > 0): ?>
                        <?php foreach ($all_groups_list as $group_item): 
                            $is_active_focus = ($group_item['id'] === $active_hang_tho_id) ? "is-currently-selected" : "";
                            $user_has_joined = in_array($group_item['id'], $joined_group_ids);
                        ?>
                            <div class="hang-tho-card-item shadow-sm <?= $is_active_focus; ?>" onclick="window.location.href='hang-tho.php?group_id=<?= $group_item['id']; ?>';">
                                <div class="d-flex flex-column gap-2">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <strong class="text-dark text-truncate d-block fw-bold" style="font-size: 0.95rem; max-width: 75%;" title="<?= htmlspecialchars($group_item['ten_hang_tho']); ?>">
                                            <?= htmlspecialchars($group_item['ten_hang_tho']); ?>
                                        </strong>
                                        <?php if ($user_has_joined): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle rounded-pill px-2 py-1 small" style="font-size: 0.65rem;"><i class="fa-solid fa-user-check"></i> Đã vào</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border rounded-pill px-2 py-1 small" style="font-size: 0.65rem;">Khách</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-1">
                                        <span class="text-muted small" style="font-size: 0.8rem;"><i class="fa-solid fa-users text-secondary me-1"></i><?= number_format($group_item['member_count']); ?> học viên</span>
                                        <?php if(!$user_has_joined): ?>
                                            <form method="POST" action="" class="m-0" onclick="event.stopPropagation();">
                                                <input type="hidden" name="action" value="join_hang_tho_operation">
                                                <input type="hidden" name="group_id" value="<?= $group_item['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                                                <button type="submit" class="btn btn-link p-0 text-decoration-none fw-bold small text-primary" style="font-size: 0.8rem;">Vào ngay &rarr;</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted bg-light rounded-4 border border-dashed p-3">
                            <i class="fa-solid fa-folder-open display-6 text-muted mb-2 d-block opacity-50"></i>
                            <span class="small d-block fw-medium">Không tìm thấy phân khu Hang Thỏ tương thích.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-12 col-md-8 col-lg-9 content-right-panel">
                <?php if ($active_group_info): 
                    $is_current_user_member_of_focus_group = in_array($active_hang_tho_id, $joined_group_ids);
                ?>
                    <div class="card glass-premium-card p-4 p-md-5 mb-4 border-0 position-relative overflow-hidden shadow" style="background: linear-gradient(135deg, #ffffff 0%, #fdfbfb 100%); border-left: 6px solid var(--bunny-primary) !important;">
                        <div class="position-absolute end-0 top-0 opacity-10 p-4 translate-middle-y mt-5 me-5 d-none d-lg-block">
                            <i class="fa-solid fa-rabbit-running-fast" style="font-size: 11rem; color: var(--bunny-primary);"></i>
                        </div>
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-4 position-relative" style="z-index: 2;">
                            <div>
                                <span class="badge text-uppercase px-3 py-1.5 rounded-pill mb-2.5 shadow-sm" style="background-color: var(--bunny-primary-light); color: var(--bunny-primary); font-weight: 700; font-size: 0.75rem;"><i class="fa-solid fa-layer-group me-1"></i> Không Gian Thực Thi Tri Thức</span>
                                <h1 class="fw-extrabold text-dark m-0 tracking-tight display-6"><?= htmlspecialchars($active_group_info['ten_hang_tho']); ?></h1>
                                <p class="text-muted small m-0 mt-2 d-flex flex-wrap gap-3 align-items-center">
                                    <span><i class="fa-regular fa-clock me-1 text-primary"></i>Khởi tạo: <strong><?= date('d/m/Y', strtotime($active_group_info['created_at'])); ?></strong></span>
                                    <span><i class="fa-solid fa-users me-1 text-success"></i>Quần thể: <strong><?= count($group_members_list); ?> thành viên</strong></span>
                                    <span><i class="fa-solid fa-microchip me-1 text-warning"></i>Phân khu ID: <strong>#0<?= $active_hang_tho_id; ?></strong></span>
                                </p>
                            </div>
                            <div class="flex-shrink-0">
                                <?php if ($is_current_user_member_of_focus_group): ?>
                                    <div class="dropdown">
                                        <button class="btn btn-success bg-opacity-10 text-success border border-success-subtle px-4 py-2.5 rounded-pill fw-bold dropdown-toggle d-inline-flex align-items-center gap-2 shadow-inner" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background-color: var(--bunny-success-light);">
                                            <i class="fa-solid fa-shield-halved"></i> Tư cách: Thành viên
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm rounded-4 mt-2">
                                            <li>
                                                <form method="POST" action="" class="m-0" onsubmit="return confirm('CẢNH BÁO: Bạn có chắc chắn muốn rời khỏi Hang Thỏ này không? Mọi quyền lợi ứng dụng số học tập trong nhóm này của bạn sẽ bị thu hồi ngay lập tức.');">
                                                    <input type="hidden" name="action" value="leave_hang_tho_operation">
                                                    <input type="hidden" name="group_id" value="<?= $active_hang_tho_id; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                                                    <button type="submit" class="dropdown-item text-danger fw-bold py-2 px-3"><i class="fa-solid fa-door-open me-2"></i>Rời khỏi Hang Thỏ</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                <?php else: ?>
                                    <form method="POST" action="" class="m-0">
                                        <input type="hidden" name="action" value="join_hang_tho_operation">
                                        <input type="hidden" name="group_id" value="<?= $active_hang_tho_id; ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                                        <button type="submit" class="btn btn-primary fw-bold rounded-pill px-5 py-2.5 shadow-lg border-0" style="background-color: var(--bunny-primary);"><i class="fa-solid fa-right-to-bracket me-2"></i>Kích hoạt gia nhập Hang</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <ul class="nav nav-pills custom-pill-nav bg-white p-2 border rounded-pill shadow-sm gap-2 mb-4 flex-wrap" id="groupFeatureTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active d-flex align-items-center gap-2" id="arena-sub-tab" data-bs-toggle="tab" data-bs-target="#panel-arena" type="button" role="tab" aria-controls="panel-arena" aria-selected="true">
                                <i class="fa-solid fa-swords text-danger"></i>Đấu Trường Thách Đấu
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link d-flex align-items-center gap-2" id="members-sub-tab" data-bs-toggle="tab" data-bs-target="#panel-members" type="button" role="tab" aria-controls="panel-members" aria-selected="false">
                                <i class="fa-solid fa-address-book text-primary"></i>Danh Bạ Đồng Môn
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link d-flex align-items-center gap-2" id="exams-sub-tab" data-bs-toggle="tab" data-bs-target="#panel-exams" type="button" role="tab" aria-controls="panel-exams" aria-selected="false">
                                <i class="fa-solid fa-graduation-cap text-warning"></i>Bộ Đề
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link d-flex align-items-center gap-2" id="docs-sub-tab" data-bs-toggle="tab" data-bs-target="#panel-docs" type="button" role="tab" aria-controls="panel-docs" aria-selected="false">
                                <i class="fa-solid fa-folder-open text-success"></i>Tài Liệu Chung
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="groupFeatureTabsContent">
                        
                        <div class="tab-pane fade show active" id="panel-arena" role="tabpanel" aria-labelledby="arena-sub-tab">
                            <div class="row g-4">
                                <div class="col-12 col-xl-7">
                                    <div class="card glass-premium-card p-4 mb-4 shadow-sm bg-white">
                                        <h6 class="fw-bold mb-3 border-bottom pb-2 text-uppercase text-danger d-flex align-items-center gap-2"><i class="fa-solid fa-door-closed"></i>Phòng thách đấu vệ tinh trực thuộc</h6>
                                        <div class="d-flex flex-column gap-3">
                                            <?php if (!empty($group_rooms_list)): ?>
                                                <?php foreach ($group_rooms_list as $room_obj): ?>
                                                    <div class="p-3 bg-light rounded-3 border d-flex justify-content-between align-items-center shadow-sm">
                                                        <div class="overflow-hidden me-2">
                                                            <strong class="text-dark small d-block mb-1 text-truncate"><i class="fa-solid fa-hashtag text-secondary me-1"></i>Mã định danh phòng: ROOM_CODE_0<?= $room_obj['phong_id']; ?></strong>
                                                            <span class="text-muted small d-block text-truncate"><i class="fa-solid fa-clipboard-list me-1"></i>Đề thi lõi: <strong><?= htmlspecialchars($room_obj['ten_bo_de']); ?></strong></span>
                                                        </div>
                                                        <span class="badge bg-danger text-white rounded-pill px-3 py-2 fw-bold shadow-sm flex-shrink-0"><?= number_format($room_obj['total_players_inside']); ?> Đang đấu trí</span>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="text-center py-4 text-muted small border border-dashed rounded-3 bg-light"><i class="fa-solid fa-ban me-1"></i>Không tìm thấy phòng thách đấu vệ tinh nào được định nghĩa.</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="card glass-premium-card p-4 shadow-sm bg-white">
                                        <h6 class="fw-bold mb-3 border-bottom pb-2 text-uppercase text-dark d-flex align-items-center gap-2"><i class="fa-solid fa-clock-rotate-left"></i>Tiến trình trận đấu liên kết</h6>
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle table-borderless m-0 small">
                                                <thead class="table-light text-muted fw-bold">
                                                    <tr>
                                                        <th>Đấu thủ số 1</th>
                                                        <th class="text-center">Cục diện / Tỷ số</th>
                                                        <th class="text-end">Đấu thủ số 2</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($group_matches_list)): ?>
                                                        <?php foreach ($group_matches_list as $match_obj): ?>
                                                            <tr class="border-bottom border-light">
                                                                <td><span class="fw-bold text-dark"><?= htmlspecialchars($match_obj['player_1_name']); ?></span></td>
                                                                <td class="text-center py-2.5">
                                                                    <?php if ($match_obj['trang_thai'] === 'Finished'): ?>
                                                                        <span class="badge bg-secondary rounded-pill px-2.5 py-1 mb-1 small shadow-sm">Kết thúc</span>
                                                                        <div class="fw-extrabold text-danger fs-5"><?= $match_obj['diem_nguoi_1']; ?> - <?= $match_obj['diem_nguoi_2']; ?></div>
                                                                    <?php elseif ($match_obj['trang_thai'] === 'Ongoing'): ?>
                                                                        <span class="badge bg-success rounded-pill px-2.5 py-1 mb-1 small shadow-sm animate-pulse">Đang tranh tài</span>
                                                                        <div class="fw-extrabold text-success fs-5"><?= $match_obj['diem_nguoi_1']; ?> - <?= $match_obj['diem_nguoi_2']; ?></div>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1 shadow-sm font-weight-600">Đang chờ duyệt</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="text-end"><span class="fw-bold text-dark"><?= htmlspecialchars($match_obj['player_2_name']); ?></span></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr><td colspan="3" class="text-center py-4 text-muted small"><i class="fa-solid fa-inbox me-1"></i>Chưa có bản ghi dữ liệu trận đấu nào được lưu trữ tại phân khu.</td></tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-xl-5">
                                    <div class="card glass-premium-card p-4 text-center bg-white shadow-sm border border-warning-subtle mb-4">
                                        <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle d-inline-flex mb-3 mx-auto shadow-sm"><i class="fa-solid fa-carrot display-6"></i></div>
                                        <h5 class="fw-bold text-dark mb-2">Hệ thống Luyện tập Độc lập</h5>
                                        <p class="text-muted small mb-4" style="line-height: 1.6;">Khởi động tiến trình giải đề trắc nghiệm độc lập tự động. Module này có liên kết chéo ghi đè vào bảng `phien_luyen_tap` để tích lũy trực tiếp **Điểm Cà Rốt (XP)**.</p>
                                        
                                        <?php if ($is_current_user_member_of_focus_group): ?>
                                            <form method="POST" action="" class="m-0">
                                                <input type="hidden" name="action" value="execute_solo_practice_run">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                                                <button type="submit" class="btn btn-warning w-100 text-dark fw-bold py-2.5 rounded-pill shadow-sm fs-6"><i class="fa-solid fa-bolt-lightning me-1"></i>Kích hoạt Phiên Học</button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-light border w-100 fw-bold rounded-pill disabled py-2.5"><i class="fa-solid fa-lock me-1"></i>Yêu cầu gia nhập Hang trước</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="panel-members" role="tabpanel" aria-labelledby="members-sub-tab">
                            <div class="card glass-premium-card p-4 p-md-5 shadow-sm bg-white">
                                <div class="border-bottom pb-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                                    <div>
                                        <h4 class="fw-bold text-dark m-0"><i class="fa-solid fa-users text-primary me-2"></i>Mạng lưới bạn học đồng môn & Phát động chiến thư</h4>
                                        <p class="text-muted small m-0 mt-1">Giao diện kết xuất danh sách thành viên trong nhóm tích hợp nút **Thách Đấu Trực Tiếp** thông qua truy vấn SQL Subquery phân tích phòng đấu.</p>
                                    </div>
                                    <span class="badge bg-primary px-3 py-2 rounded-pill fw-bold"><i class="fa-solid fa-user-shield me-1"></i>Tổng số: <?= count($group_members_list); ?> Học viên</span>
                                </div>

                                <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
                                    <?php if (!empty($group_members_list)): ?>
                                        <?php foreach ($group_members_list as $member_row): 
                                            $is_member_online = ($member_row['is_online'] > 0);
                                            $status_class = $is_member_online ? "dot-online" : "dot-offline";
                                            $badge_color = ($member_row['user_type'] === 'giao_vien') ? "bg-danger" : (($member_row['user_type'] === 'sinh_vien') ? "bg-primary" : "bg-secondary");
                                            
                                            // Phân biệt chính bản thân đang đăng nhập để tránh lỗi tự thách đấu tự kỷ
                                            $is_me = ($member_row['id'] === $current_user_id);
                                        ?>
                                            <div class="col">
                                                <div class="border border-light-subtle rounded-4 p-3.5 d-flex flex-column justify-content-between bg-white shadow-sm hover-lift-effect position-relative h-100">
                                                    <div>
                                                        <div class="d-flex align-items-center gap-3 mb-3">
                                                            <div class="position-relative flex-shrink-0">
                                                                <img src="<?= $fallback_user_avatar; ?>" class="rounded-circle border border-2 shadow-sm" width="56" height="56" alt="Member Avatar">
                                                                <span class="position-absolute bottom-0 end-0 status-indicator-dot <?= $status_class; ?> border border-white border-2" style="width: 14px; height: 14px;"></span>
                                                            </div>
                                                            <div class="overflow-hidden">
                                                                <strong class="text-dark d-block text-truncate fs-6 mb-0.5" title="<?= htmlspecialchars($member_row['username']); ?>">
                                                                    <?= htmlspecialchars($member_row['username']); ?> 
                                                                    <?php if($is_me): ?><span class="text-muted small fw-normal">(Bạn)</span><?php endif; ?>
                                                                </strong>
                                                                <span class="badge <?= $badge_color; ?> text-uppercase" style="font-size: 0.55rem; letter-spacing: 0.5px;"><?= htmlspecialchars($member_row['user_type']); ?></span>
                                                            </div>
                                                        </div>
                                                        
                                                        <p class="text-muted small mb-3 border-top pt-2.5" style="font-size: 0.8rem;">
                                                            <i class="fa-solid fa-school me-1.5 text-secondary"></i>Trường học: <strong class="text-dark"><?= htmlspecialchars($member_row['truong_hoc'] ?: 'Học tự do'); ?></strong>
                                                        </p>
                                                    </div>

                                                    <div class="d-flex gap-2 mt-2 pt-2.5 border-top border-light">
                                                        <a href="trang-ca-nhan.php?id=<?= $member_row['id']; ?>" class="btn btn-light btn-sm border rounded-pill flex-fill fw-bold text-muted px-2.5 py-1.5 shadow-sm" style="font-size: 0.8rem;">
                                                            <i class="fa-solid fa-user me-1"></i>Hồ sơ
                                                        </a>
                                                        
                                                        <?php if(!$is_me && $is_current_user_member_of_focus_group && !empty($member_row['target_phong_id'])): ?>
                                                            <form method="POST" action="" class="m-0 flex-fill">
                                                                <input type="hidden" name="action" value="launch_battle_strike">
                                                                <input type="hidden" name="phong_id" value="<?= $member_row['target_phong_id']; ?>">
                                                                <input type="hidden" name="opponent_id" value="<?= $member_row['id']; ?>">
                                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                                                                <button type="submit" class="btn btn-danger btn-sm text-white fw-bold rounded-pill w-100 py-1.5 shadow-sm border-0 bg-gradient" style="font-size: 0.8rem;">
                                                                    <i class="fa-solid fa-fire-blaze me-1"></i>Thách đấu
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <button class="btn btn-secondary btn-sm rounded-pill flex-fill disabled opacity-50 py-1.5" style="font-size: 0.8rem; background-color: #cbd5e1; border:0; color:#94a3b8;">
                                                                <i class="fa-solid fa-ban me-1"></i>Khóa
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="col-12 text-center py-5 text-muted small bg-light rounded-4 border border-dashed w-100">
                                            <i class="fa-solid fa-users-slash display-6 d-block mb-2 opacity-50"></i>
                                            Không tìm thấy bản ghi nhân sự thành viên nào đang trực thuộc phân khu này.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="panel-exams" role="tabpanel" aria-labelledby="exams-sub-tab">
                            <div class="card glass-premium-card p-4 shadow-sm bg-white">
                                <h6 class="fw-bold mb-3 border-bottom pb-2 text-uppercase text-warning d-flex align-items-center gap-2"><i class="fa-solid fa-cabinet-filing"></i>Ngân hàng bộ đề khảo sát phân phối</h6>
                                <div class="row row-cols-1 row-cols-sm-2 g-3">
                                    <?php if (!empty($group_book_exams)): ?>
                                        <?php foreach ($group_book_exams as $exam_item): ?>
                                            <div class="col">
                                                <div class="p-3 border rounded-4 bg-light h-100 d-flex flex-column justify-content-between shadow-sm">
                                                    <div>
                                                        <div class="bg-warning bg-opacity-10 text-warning p-2.5 rounded-circle d-inline-flex mb-2.5 shadow-inner"><i class="fa-solid fa-paste fs-4"></i></div>
                                                        <strong class="text-dark d-block mb-1.5 fs-6 lh-base" title="<?= htmlspecialchars($exam_item['ten_bo_de']); ?>"><?= htmlspecialchars($exam_item['ten_bo_de']); ?></strong>
                                                        <span class="text-muted small d-block"><i class="fa-solid fa-layer-group me-1"></i>Cấu trúc định lượng: <strong class="text-dark"><?= number_format($exam_item['total_questions']); ?> câu trắc nghiệm</strong></span>
                                                    </div>
                                                    <div class="mt-4 border-top pt-2.5">
                                                        <button type="button" class="btn btn-outline-warning text-dark btn-sm fw-bold w-100 rounded-pill shadow-sm bg-white" data-bs-toggle="modal" data-bs-target="#modalExamInspect-<?= $exam_item['bo_de_id']; ?>"><i class="fa-solid fa-magnifying-glass me-1"></i>Tra cứu ngân hàng câu hỏi</button>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="modal fade" id="modalExamInspect-<?= $exam_item['bo_de_id']; ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                                    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                                                        <div class="modal-header bg-warning text-dark border-0 py-3.5 px-4">
                                                            <h5 class="modal-title fw-bold"><i class="fa-solid fa-clipboard-question me-2"></i>Chi tiết ngân hàng đề mục - Đề mã số #0<?= $exam_item['bo_de_id']; ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body p-4 bg-white max-height-500 overflow-auto">
                                                            <?php
                                                                // Dynamic Query truy xuất các câu hỏi của bộ đề
                                                                $sql_fetch_questions = "SELECT id, noi_dung, lua_chon_a, lua_chon_b, lua_chon_c, lua_chon_d, dap_an_dung FROM cau_hoi WHERE bo_de_id = :bid";
                                                                $stmt_fetch_questions = $pdo->prepare($sql_fetch_questions);
                                                                $stmt_fetch_questions->execute(['bid' => $exam_item['bo_de_id']]);
                                                                $questions_pool_list = $stmt_fetch_questions->fetchAll();

                                                                if (!empty($questions_pool_list)):
                                                                    $q_idx = 1;
                                                                    foreach ($questions_pool_list as $q_node):
                                                                ?>
                                                                        <div class="p-3 border rounded-3 mb-3 bg-light text-start small shadow-sm">
                                                                            <strong class="text-dark d-block mb-2">Câu hỏi số <?= $q_idx++; ?>: <?= htmlspecialchars($q_node['noi_dung']); ?></strong>
                                                                            <div class="row g-2 mb-2 text-muted fw-medium">
                                                                                <div class="col-6">Phương án A: <?= htmlspecialchars($q_node['lua_chon_a']); ?></div>
                                                                                <div class="col-6">Phương án B: <?= htmlspecialchars($q_node['lua_chon_b']); ?></div>
                                                                                <div class="col-6">Phương án C: <?= htmlspecialchars($q_node['lua_chon_c'] ?? ''); ?></div>
                                                                                <div class="col-6">Phương án D: <?= htmlspecialchars($q_node['lua_chon_d']); ?></div>
                                                                            </div>
                                                                            <div class="text-success fw-bold border-top pt-2 mt-2 d-flex align-items-center gap-1.5"><i class="fa-solid fa-circle-check"></i>Đáp án hệ thống kiểm duyệt: <span class="badge bg-success text-white px-2 py-1 rounded"><?= htmlspecialchars($q_node['dap_an_dung']); ?></span></div>
                                                                        </div>
                                                                <?php 
                                                                    endforeach;
                                                                else:
                                                                    echo '<div class="alert alert-info text-center m-0 border-0 shadow-sm rounded-3 py-4"><i class="fa-solid fa-circle-info me-1"></i>Mã bộ đề thi này hiện đang trống cấu trúc dữ liệu con.</div>';
                                                                endif;
                                                            ?>
                                                        </div>
                                                        <div class="modal-footer bg-light border-0 px-4 py-2.5 rounded-bottom-4">
                                                            <button type="button" class="btn btn-secondary btn-sm fw-bold rounded-pill shadow-sm px-4" data-bs-dismiss="modal">Đóng cửa sổ tra cứu</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="col-12 text-center text-muted py-4 small border border-dashed rounded-3 bg-light"><i class="fa-solid fa-face-dashed me-1"></i>Chưa ghi nhận mã cấu trúc đề thi nào liên kết với phân khu Hang Thỏ này.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="panel-docs" role="tabpanel" aria-labelledby="docs-sub-tab">
                            <div class="card glass-premium-card p-4 p-md-5 shadow-sm bg-white">
                                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-4 gap-3 border-bottom pb-3">
                                    <div>
                                        <h4 class="fw-bold m-0 text-dark"><i class="fa-solid fa-cloud-arrow-up text-success me-2"></i>Thư viện học liệu số nhóm</h4>
                                        <p class="text-muted small m-0 mt-1">Phân vùng quản lý và phân phối tài liệu nội bộ do các thành viên đồng lòng đóng góp lên Server.</p>
                                    </div>
                                    <?php if ($is_current_user_member_of_focus_group): ?>
                                        <button class="btn btn-success fw-bold rounded-pill px-4 shadow-sm btn-sm border-0" data-bs-toggle="modal" data-bs-target="#modalDocumentUploadPremium" style="background-color: var(--bunny-success);"><i class="fa-solid fa-upload me-1"></i>Cống hiến tài liệu</button>
                                    <?php endif; ?>
                                </div>

                                <div class="row row-cols-1 row-cols-md-2 g-3">
                                    <?php if (!empty($group_docs_list)): ?>
                                        <?php foreach ($group_docs_list as $doc_node): 
                                            // Xóa bỏ chuỗi thẻ ID phân nhóm dư thừa ở tên file để UI đẹp hơn
                                            $clean_document_display_title = str_replace("[HangTho_" . $active_hang_tho_id . "] ", "", $doc_node['ten_tai_lieu']);
                                        ?>
                                            <div class="col">
                                                <div class="p-3 border rounded-4 d-flex align-items-center justify-content-between bg-light border-start border-4 border-success hover-lift-effect shadow-sm">
                                                    <div class="overflow-hidden me-2">
                                                        <strong class="text-dark d-block text-truncate small mb-1.5" title="<?= htmlspecialchars($clean_document_display_title); ?>"><i class="fa-solid fa-file-pdf text-danger me-1.5"></i><?= htmlspecialchars($clean_document_display_title); ?></strong>
                                                        <small class="text-muted d-block" style="font-size: 0.75rem;"><i class="fa-solid fa-circle-user me-1"></i>Nguồn đẩy lên: <strong><?= htmlspecialchars($doc_node['username']); ?></strong></small>
                                                        <small class="text-muted d-block mt-0.5" style="font-size: 0.7rem;"><i class="fa-regular fa-calendar me-1"></i>Đồng bộ ngày: <?= date('d/m/Y', strtotime($doc_node['created_at'])); ?></small>
                                                    </div>
                                                    <a href="<?= htmlspecialchars($doc_node['file_url']); ?>" target="_blank" class="btn btn-outline-success btn-sm rounded-circle shadow-sm d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;" data-bs-toggle="tooltip" title="Tải xuống tài liệu vật lý"><i class="fa-solid fa-download"></i></a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="col-12 text-center text-muted py-5 small bg-light rounded-4 border border-dashed">
                                            <img src="https://cdn-icons-png.flaticon.com/512/3394/3394785.png" alt="Empty space" width="100" class="opacity-30 mb-3 mx-auto d-block">
                                            Không gian tài liệu học tập đang trống.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                    </div> <?php else: ?>
                    <div class="card glass-premium-card p-5 text-center bg-white shadow border-0 rounded-4">
                        <div class="text-primary mb-3.5"><i class="fa-solid fa-signs-post display-2 opacity-50"></i></div>
                        <h4 class="fw-bold text-dark mb-2">Hệ thống bảng điều khiển đang chờ hiệu lệnh</h4>
                        <p class="text-muted px-md-5 fs-6">Vui lòng nhấp lựa chọn một phân khu không gian số Hang Thỏ bất kỳ trong danh mục quản lý ở thanh bên trái hoặc Nhấn "Tạo mới".</p>
                    </div>
                <?php endif; ?>
            </div> </div>
    </div>

    <div class="modal fade" id="createNewGroupPremiumModal" tabindex="-1" aria-labelledby="createNewGroupPremiumModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="" class="m-0">
                <input type="hidden" name="action" value="create_new_hang_tho_premium">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="modal-header bg-primary text-white border-0 py-3.5 px-4" style="background-color: var(--bunny-primary) !important;">
                        <h5 class="modal-title fw-bold" id="createNewGroupPremiumModalLabel"><i class="fa-solid fa-circle-plus me-2 fs-4 align-middle"></i>Thiết lập không gian Hang Thỏ mới</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4 bg-white">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark fs-6">Đặt tên định danh cho Hang Thỏ của bạn <span class="text-danger">*</span></label>
                            <input type="text" class="form-control border-secondary-subtle py-2.5 px-3 fw-medium rounded-3 shadow-sm" name="ten_hang_tho" placeholder="Ví dụ: Nhóm Ôn Thi Chuyên Văn Khối 12..." required maxlength="255">
                        </div>
                        <div class="alert alert-info border-0 mt-3 p-3 small text-start text-dark m-0 rounded-3 shadow-inner" style="line-height: 1.5; background-color: var(--bunny-primary-light);">
                            <i class="fa-solid fa-circle-nodes text-primary me-1 fs-5 align-middle"></i> <strong>Quy trình Tự động hóa Dữ liệu:</strong> Theo thiết kế ERD chuẩn, việc tạo Hang Thỏ sẽ kích hoạt lệnh Transaction PHP tự động sinh ra một <code>Bộ Đề Thi ảo</code> kèm theo một <code>Phòng Thách Đấu thời gian thực</code> và gán bạn làm thành viên trực tiếp vào các table liên kết.
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-0 px-4 py-3 justify-content-end gap-2 rounded-bottom-4">
                        <button type="button" class="btn btn-light border rounded-pill px-4 fw-bold shadow-sm" data-bs-dismiss="modal">Hủy bỏ tiến trình</button>
                        <button type="submit" class="btn btn-primary text-white rounded-pill px-4 fw-bold shadow-sm border-0" style="background-color: var(--bunny-primary);">Kích hoạt tạo Hang</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="modalDocumentUploadPremium" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="" enctype="multipart/form-data" class="m-0">
                <input type="hidden" name="action" value="submit_group_document_premium">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="modal-header bg-success text-white border-0 py-3.5 px-4" style="background-color: var(--bunny-success) !important;">
                        <h5 class="modal-title fw-bold"><i class="fa-solid fa-cloud-arrow-up me-2 fs-4 align-middle"></i>Cống hiến học liệu số vào Máy chủ</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4 bg-white">
                        <div class="mb-3.5">
                            <label class="form-label fw-bold text-dark fs-6">Tiêu đề cốt lõi của tài liệu <span class="text-danger">*</span></label>
                            <input type="text" class="form-control border-secondary-subtle py-2 px-3 fw-medium rounded-3 shadow-sm" name="ten_tai_lieu" placeholder="VD: Slide bài giảng phân tích diễn biến tâm lý..." required maxlength="255">
                        </div>
                        <div class="interactive-dashed-zone text-center">
                            <i class="fa-solid fa-file-invoice-dollar text-success display-4 mb-3 d-block"></i>
                            <input type="file" class="form-control border-success text-success fw-bold shadow-sm bg-white mb-3" name="file_tai_lieu" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx" required>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-0 px-4 py-3 justify-content-end gap-2 rounded-bottom-4">
                        <button type="button" class="btn btn-light border rounded-pill px-4 fw-bold shadow-sm" data-bs-dismiss="modal">Đóng</button>
                        <button type="submit" class="btn btn-success text-white rounded-pill px-5 fw-bold shadow-sm border-0" style="background-color: var(--bunny-success);">Tải Lên Mạng Lưu Trữ</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Khởi tạo tất cả Bootstrap Tooltips trên UI
            var tooltipsTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipsTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Ghi nhớ và phục hồi Tab chức năng ứng dụng đang được chọn trong Panel phải
            var savedSubTab = sessionStorage.getItem('bunny_active_sub_tab') || window.location.hash;
            if (savedSubTab) {
                var targetTabEl = document.querySelector('#groupFeatureTabs button[data-bs-target="' + savedSubTab + '"]');
                if (targetTabEl) { 
                    new bootstrap.Tab(targetTabEl).show(); 
                }
            }

            var subTabElements = [].slice.call(document.querySelectorAll('#groupFeatureTabs button[data-bs-toggle="tab"]'));
            subTabElements.forEach(function (tabEl) {
                tabEl.addEventListener('shown.bs.tab', function (event) {
                    var tabHash = event.target.getAttribute('data-bs-target');
                    history.replaceState(null, null, window.location.search.split('#')[0] + tabHash);
                });
            });

            // Ghi nhớ và phục hồi tọa độ cuộn chuột Split-Screen
            var savedScrollPosY = sessionStorage.getItem('bunny_split_scroll_y');
            if (savedScrollPosY !== null) {
                window.scrollTo({ top: parseInt(savedScrollPosY), behavior: 'auto' });
                sessionStorage.removeItem('bunny_split_scroll_y');
                sessionStorage.removeItem('bunny_active_sub_tab');
            }
        });

        // Hàm xử lý trước khi trình duyệt tải lại trang (Chặn việc mất UX)
        window.addEventListener('beforeunload', function () {
            sessionStorage.setItem('bunny_split_scroll_y', window.scrollY);
            var activeTabObj = document.querySelector('#groupFeatureTabs button.active');
            if (activeTabObj) {
                sessionStorage.setItem('bunny_active_sub_tab', activeTabObj.getAttribute('data-bs-target'));
            }
        });
    </script>
</body>
</html>