<?php
/**
 * =========================================================================================
 * ĐỒ ÁN MÔN HỌC: XÂY DỰNG WEBSITE MẠNG XÃ HỘI HỌC TẬP THE BUNNY
 * Tệp tin: trang-ca-nhan.php
 * Chức năng: Hiển thị hồ sơ cá nhân, dòng thời gian, tài liệu, sự kiện và bạn bè.
 * Xử lý Backend: Đăng bài, Sửa hồ sơ, Thêm tài liệu, Tạo sự kiện.
 * Xử lý Tương tác (Mới): Thích (Like), Bình luận (Comment), Chia sẻ (Share) bài viết.
 * Đã khắc phục: Mọi dữ liệu đều liên kết 100% với Database thực tế.
 * =========================================================================================
 */

// [GIẢI THÍCH PHP]: Bắt đầu phiên làm việc (Session). Hàm này bắt buộc phải gọi đầu tiên 
// ở dòng trên cùng của file PHP để hệ thống có thể truy cập được các biến lưu trong $_SESSION.
session_start();

// [GIẢI THÍCH PHP]: Gọi tệp cấu hình chứa các hằng số kết nối Cơ sở dữ liệu (DB_HOST, DB_NAME...).
// Dùng require_once để đảm bảo file cấu hình chỉ nạp 1 lần, tránh lỗi khai báo lại hàm/biến.
require_once '../config/config.php'; 

// =========================================================================================
// PHẦN 1: ĐỊNH NGHĨA CÁC HÀM TIỆN ÍCH (HELPER FUNCTIONS) XỬ LÝ NGHIỆP VỤ & BẢO MẬT
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
 * Hàm tính thời gian tương đối (Time Ago) như Facebook
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
 * TẠO CSRF TOKEN CHỐNG GIẢ MẠO REQUEST (Cross-Site Request Forgery)
 * [GIẢI THÍCH PHP]: Tạo một mã băm ngẫu nhiên 32 byte lưu vào phiên làm việc (Session).
 * Token này sẽ được chèn ẩn vào mọi Form. Nếu hacker giả mạo Form từ trang khác gửi đến,
 * do không có Token khớp với Session, request sẽ bị chặn lại lập tức.
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// =====================================================================================
// PHẦN 2: KHỞI TẠO BIẾN TRỐNG (MẶC ĐỊNH AN TOÀN - KHÔNG DÙNG DỮ LIỆU MẪU)
// =====================================================================================

$page_title          = "Trang cá nhân";

// Các biến lưu thông tin cơ bản định danh từ bảng `users`
$user_name           = "";
$user_type           = "";
$truong_hoc          = "";
$truong_dai_hoc      = "";
$is_verified         = false; // Cờ kiểm tra tài khoản đã xác thực chưa

// Biến thông tin mở rộng từ bảng `ho_so_ca_nhan`
$thong_tin_dinh_danh = "";

// Do CSDL thiết kế không có cột upload ảnh, ta gán ảnh dự phòng (Fallback Image)
$user_avatar         = "../assets/img/default-avatar.jpg";
$user_cover          = "../assets/img/default-cover.jpg";

// Khởi tạo các biến chứa dữ liệu số đếm bằng 0
$stats_xp            = 0;
$stats_buddies       = 0;
$stats_docs          = 0;

// Biến lưu trữ văn bản thông báo trả về (Alert)
$message_notify      = ""; 
// Biến xác định màu cảnh báo của Bootstrap (success: xanh, danger: đỏ, warning: vàng)
$message_type        = "success"; 

// [LƯU Ý PHP]: Lấy ID người dùng từ Session. Nếu chưa có chức năng đăng nhập,
// tạm thời hard-code là 1 (Tài khoản Admin đầu tiên) để có dữ liệu Test.
$current_user_id     = $_SESSION['user_id'] ?? 1; 

// Khởi tạo mảng rỗng để hứng dữ liệu từ CSDL, tránh văng lỗi nếu DB trống
$posts_data          = [];
$buddies_data        = [];
$docs_data           = [];
$events_data         = [];

// =====================================================================================
// PHẦN 3: THIẾT LẬP KẾT NỐI ĐẾN CƠ SỞ DỮ LIỆU (PDO MYSQL)
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

// =====================================================================================
// PHẦN 4: TIẾP NHẬN YÊU CẦU POST VÀ THỰC THI (ĐĂNG BÀI, THÊM XÓA SỬA VÀ TƯƠNG TÁC)
// =====================================================================================

try {
    // Kiểm tra xem luồng chạy có phải là thao tác đẩy dữ liệu (POST) không
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
        
        // [KIỂM TRA BẢO MẬT]: Xác thực CSRF Token
        // Hàm hash_equals dùng để so sánh chuỗi an toàn, chống tấn công timing attack.
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception("Lỗi bảo mật: CSRF Token không hợp lệ hoặc đã hết hạn. Vui lòng tải lại trang.");
        }
        
        // Trích xuất biến action để xem người dùng đang ấn nút nào
        $action = sanitize_input($_POST['action']);

        // [TÍNH NĂNG MỚI]: Xử lý Logic Tương Tác Mạng Xã Hội (Like, Comment, Share)
        // -----------------------------------------------------------------------------
        
        // ---> LUỒNG TƯƠNG TÁC 1: NHẤN NÚT THÍCH (LIKE / UNLIKE) <---
        if ($action == 'like_post') {
            // Lấy ID bài đăng từ form ẩn
            $post_id = (int)$_POST['post_id'];
            
            // Bước 1: Kiểm tra xem user này đã like bài này chưa
            $check_like = $pdo->prepare("SELECT id FROM luot_thich WHERE bai_dang_id = :pid AND user_id = :uid");
            $check_like->execute(['pid' => $post_id, 'uid' => $current_user_id]);
            
            if ($check_like->rowCount() > 0) {
                // Đã like rồi -> Bấm lần nữa là UNLIKE (Xóa bản ghi)
                $del_like = $pdo->prepare("DELETE FROM luot_thich WHERE bai_dang_id = :pid AND user_id = :uid");
                $del_like->execute(['pid' => $post_id, 'uid' => $current_user_id]);
                $message_notify = "Đã bỏ thích bài viết.";
            } else {
                // Chưa like -> Tiến hành LIKE (Thêm bản ghi)
                $ins_like = $pdo->prepare("INSERT INTO luot_thich (bai_dang_id, user_id, created_at) VALUES (:pid, :uid, NOW())");
                $ins_like->execute(['pid' => $post_id, 'uid' => $current_user_id]);
                $message_notify = "Đã thích bài viết thành công!";
            }
            $message_type = "success";
        }
        
        // ---> LUỒNG TƯƠNG TÁC 2: GỬI BÌNH LUẬN (COMMENT) <---
        elseif ($action == 'comment_post') {
            $post_id = (int)$_POST['post_id'];
            $comment_content = sanitize_input($_POST['noi_dung_binh_luan']);
            
            if (!empty($comment_content)) {
                // Insert thẳng dữ liệu vào bảng binh_luan
                $ins_cmt = $pdo->prepare("INSERT INTO binh_luan (bai_dang_id, user_id, noi_dung, created_at) VALUES (:pid, :uid, :content, NOW())");
                $ins_cmt->execute([
                    'pid'     => $post_id, 
                    'uid'     => $current_user_id, 
                    'content' => $comment_content
                ]);
                $message_notify = "Đã gửi bình luận của bạn lên hệ thống!";
                $message_type = "success";
            } else {
                throw new Exception("Nội dung bình luận không được để trống.");
            }
        }
        
        // ---> LUỒNG TƯƠNG TÁC 3: CHIA SẺ BÀI VIẾT (SHARE) <---
        elseif ($action == 'share_post') {
            $post_id = (int)$_POST['post_id'];
            
            // Insert dữ liệu vào bảng luot_chia_se
            $ins_share = $pdo->prepare("INSERT INTO luot_chia_se (bai_dang_id, user_id, created_at) VALUES (:pid, :uid, NOW())");
            $ins_share->execute(['pid' => $post_id, 'uid' => $current_user_id]);
            
            $message_notify = "Đã chia sẻ bài viết thành công!";
            $message_type = "success";
        }

        // -----------------------------------------------------------------------------
        // CÁC LUỒNG XỬ LÝ DỮ LIỆU CỐT LÕI CŨ (ĐĂNG BÀI, SỬA HỒ SƠ, UPLOAD FILE)
        // -----------------------------------------------------------------------------
        
        // LUỒNG: ĐĂNG BÀI VIẾT MỚI LÊN TƯỜNG (DÒNG THỜI GIAN)
        elseif ($action == 'add_post') {
            if (!empty(trim($_POST['noidung_post']))) {
                $content = sanitize_input($_POST['noidung_post']);
                // Ghi dữ liệu văn bản vào bảng bai_dang
                $sql_insert_post = "INSERT INTO bai_dang (user_id, noi_dung, created_at) VALUES (:uid, :content, NOW())";
                $stmt = $pdo->prepare($sql_insert_post);
                $stmt->execute(['uid' => $current_user_id, 'content' => $content]);
                $message_notify = "Đã xuất bản bài đăng lên dòng thời gian!";
                $message_type   = "success";
            } else {
                throw new Exception("Trạng thái bài viết không được để trống.");
            }
        }
        
        // LUỒNG: LƯU CẬP NHẬT CHỈNH SỬA HỒ SƠ (USERS & HO_SO_CA_NHAN)
        elseif ($action == 'edit_profile') {
            $username       = sanitize_input($_POST['username'] ?? '');
            $truong_hoc     = sanitize_input($_POST['truong_hoc'] ?? '');
            $truong_dai_hoc = sanitize_input($_POST['truong_dai_hoc'] ?? '');
            $tt_dinh_danh   = sanitize_input($_POST['thong_tin_dinh_danh'] ?? '');

            // [Lệnh 1]: Update dữ liệu bảng `users`
            $sql_update_users = "UPDATE users SET username = :uname, truong_hoc = :thoc, truong_dai_hoc = :tdhoc WHERE id = :id";
            $stmt1 = $pdo->prepare($sql_update_users);
            $stmt1->execute([
                'uname' => $username, 
                'thoc'  => $truong_hoc, 
                'tdhoc' => $truong_dai_hoc, 
                'id'    => $current_user_id
            ]);

            // [Lệnh 2]: Dùng thuật toán UPSERT cho bảng `ho_so_ca_nhan`
            $sql_upsert_profile = "INSERT INTO ho_so_ca_nhan (user_id, thong_tin_dinh_danh) VALUES (:id, :ttdd) ON DUPLICATE KEY UPDATE thong_tin_dinh_danh = VALUES(thong_tin_dinh_danh)";
            $stmt2 = $pdo->prepare($sql_upsert_profile);
            $stmt2->execute(['id' => $current_user_id, 'ttdd' => $tt_dinh_danh]);
            
            $message_notify = "Hồ sơ năng lực của bạn đã được cập nhật đồng bộ hệ thống!";
            $message_type   = "success";
        }

        // LUỒNG: TẢI LÊN TÀI LIỆU VẬT LÝ VÀ GHI DATABASE
        elseif ($action == 'add_document') {
            $doc_name = sanitize_input($_POST['ten_tai_lieu'] ?? 'Tài liệu không xác định');
            $file_path = ""; 
            
            // Xử lý File Upload trong PHP thông qua biến toàn cục $_FILES
            if (isset($_FILES['file_tai_lieu']) && $_FILES['file_tai_lieu']['error'] == UPLOAD_ERR_OK) {
                // Khai báo thư mục đích chứa file tài liệu
                $upload_dir = '../uploads/document/';
                
                // Thuật toán kiểm tra và tạo thư mục nếu máy chủ chưa có sẵn
                if (!is_dir($upload_dir)) {
                    @mkdir($upload_dir, 0777, true);
                }
                
                // Lấy tên gốc của tệp tin do người dùng tải lên
                $original_name = basename($_FILES['file_tai_lieu']['name']);
                
                // Gọi hàm làm sạch tên File (Xóa tiếng Việt có dấu, khoảng trắng thành gạch dưới)
                $clean_name = preg_replace('/[^a-zA-Z0-9.\-_]/', '', str_replace(' ', '_', remove_vietnamese_accents($original_name)));
                
                // Nối chuỗi timestamp để đảm bảo 100% không trùng tên file
                $file_name = time() . '_' . $clean_name;
                $target_file = $upload_dir . $file_name;
                
                // Thực thi di chuyển tệp tin từ vùng nhớ tạm của server vào thư mục dự án
                if (move_uploaded_file($_FILES['file_tai_lieu']['tmp_name'], $target_file)) {
                    // Cắt bỏ dấu ../ ở đầu để lưu đường dẫn tương đối chuẩn vào Database
                    $file_path = ltrim($target_file, '../');
                } else {
                    // Nếu lỗi do phân quyền trên Mac/Linux
                    throw new Exception("Lỗi máy chủ phân quyền: Không thể lưu file vật lý. Vui lòng cấp quyền (chmod 777) cho thư mục uploads.");
                }
            } else {
                throw new Exception("Bạn chưa chọn tệp tin hoặc tệp tin bị lỗi trong quá trình tải.");
            }
            
            // [GIẢI THÍCH SQL]: Lưu đường dẫn file vào cột `file_` của bảng `tai_lieu`
            $sql_insert_doc = "INSERT INTO tai_lieu (user_id, ten_tai_lieu, file_url, created_at) VALUES (:uid, :name, :filepath, NOW())";
            $stmt = $pdo->prepare($sql_insert_doc);
            $stmt->execute(['uid' => $current_user_id, 'name' => $doc_name, 'filepath' => $file_path]);
            
            $message_notify = "Đóng góp thư viện tài liệu thành công!";
            $message_type   = "success";
        }

        // LUỒNG: TẠO MỚI SỰ KIỆN LỊCH TRÌNH VÀ PHÂN QUYỀN
        elseif ($action == 'add_event') {
            $event_name = sanitize_input($_POST['tieu_de'] ?? 'Sự kiện Học tập');
            $event_date = !empty($_POST['thoi_gian']) ? $_POST['thoi_gian'] : date('Y-m-d H:i:s');
            
            // [BƯỚC 1]: Thêm tên và thời gian vào bảng su_kien gốc
            $sql_insert_event = "INSERT INTO su_kien (tieu_de, thoi_gian, created_at) VALUES (:title, :time, NOW())";
            $stmt = $pdo->prepare($sql_insert_event);
            $stmt->execute(['title' => $event_name, 'time'  => $event_date]);
            
            // [BƯỚC 2]: Trích xuất ID vừa tạo bằng hàm lastInsertId
            $new_event_id = $pdo->lastInsertId();
            
            // [BƯỚC 3]: Chèn User vào bảng trung gian thanh_vien_su_kien với quyền đã phê duyệt
            $sql_insert_member = "INSERT INTO thanh_vien_su_kien (su_kien_id, user_id, trang_thai_duyet, created_at) VALUES (:event_id, :uid, 'Approved', NOW())";
            $stmt_member = $pdo->prepare($sql_insert_member);
            $stmt_member->execute(['event_id' => $new_event_id, 'uid' => $current_user_id]);
            
            $message_notify = "Lên lịch sự kiện khai giảng khóa học thành công!";
            $message_type   = "success";
        }
        
        // Nếu không khớp luồng nào thì bắn lỗi bảo mật
        else {
            throw new Exception("Yêu cầu không được hệ thống hỗ trợ.");
        }
        
        // =============================================================================
        // CƠ CHẾ P.R.G (POST - REDIRECT - GET) CHỐNG SUBMIT TRÙNG DỮ LIỆU
        // =============================================================================
        // [GIẢI THÍCH PHP]: Sau khi POST data xong, nếu để nguyên trang thì khi người dùng 
        // bấm F5 trình duyệt sẽ hỏi "Confirm Form Resubmission" và có thể insert đúp vào DB.
        // Giải pháp là ép trang web tự chuyển hướng (Redirect) sang phương thức GET.
        if($message_notify != "" && $message_type == "success") {
            // Mã hóa chuỗi thông báo để truyền an toàn trên thanh địa chỉ URL
            $safe_msg = urlencode($message_notify);
            $safe_type = urlencode($message_type);
            // Dùng hàm header thực hiện vòng lặp chuyển hướng nội bộ
            header("Location: " . $_SERVER['PHP_SELF'] . "?msg=" . $safe_msg . "&type=" . $safe_type);
            // Lệnh exit giải phóng tài nguyên tiến trình lập tức
            exit;
        }
    }

} catch (Exception $e) {
    // Khối catch bao trùm bắt mọi lỗi ném ra từ Exception ở trên
    $message_notify = "Thao tác thất bại: " . $e->getMessage();
    $message_type   = "danger"; 
}

// Bắt thông báo được thả xuống từ URL (Sau khi PRG Redirect hoàn tất)
if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message_notify = sanitize_input($_GET['msg']);
    $message_type   = sanitize_input($_GET['type']);
}

// =====================================================================================
// PHẦN 5: THỰC THI SELECT KÉO DỮ LIỆU ĐỂ RENDER (KẾT XUẤT) RA HTML GIAO DIỆN
// =====================================================================================

try {
    // ---------------------------------------------------------------------------------
    // [TRUY VẤN 1]: LẤY DỮ LIỆU ĐỊNH DANH VÀ THỐNG KÊ TỔNG QUAN
    // ---------------------------------------------------------------------------------
    // Dùng Left Join vì người dùng mới tạo acc có thể chưa điền thông tin bảng hồ sơ
    $sql_profile = "
        SELECT 
            u.username, u.user_type, u.truong_hoc, u.truong_dai_hoc, u.giay_to_chung_minh,
            h.thong_tin_dinh_danh,
            (SELECT IFNULL(SUM(diem_so), 0) FROM phien_luyen_tap WHERE user_id = u.id) AS xp_carrots,
            (SELECT COUNT(*) FROM ban_cung_tien WHERE (user_id = u.id OR friend_user_id = u.id) AND status = 'Accepted') AS buddy_count,
            (SELECT COUNT(*) FROM tai_lieu WHERE user_id = u.id) AS document_count
        FROM users u 
        LEFT JOIN ho_so_ca_nhan h ON u.id = h.user_id 
        WHERE u.id = :id
    ";
    
    $stmt_profile = $pdo->prepare($sql_profile);
    $stmt_profile->execute(['id' => $current_user_id]);
    
    // Gán dữ liệu kéo về được vào các biến toàn cục
    if ($row_profile = $stmt_profile->fetch()) {
        $user_name           = $row_profile['username'] ?? "Tài khoản ẩn danh";
        $page_title          = $user_name . " - Mạng xã hội học tập The Bunny"; 
        
        $user_type           = $row_profile['user_type'] ?? "";
        $truong_hoc          = $row_profile['truong_hoc'] ?? "";
        $truong_dai_hoc      = $row_profile['truong_dai_hoc'] ?? "";
        
        // Kiểm tra xem User này đã upload giấy tờ lên hệ thống chưa
        $is_verified         = (!empty($row_profile['giay_to_chung_minh'])) ? true : false;
        
        $thong_tin_dinh_danh = $row_profile['thong_tin_dinh_danh'] ?? ""; 
        
        $stats_xp            = (int)$row_profile['xp_carrots'];
        $stats_buddies       = (int)$row_profile['buddy_count'];
        $stats_docs          = (int)$row_profile['document_count'];
    }

    // ---------------------------------------------------------------------------------
    // [TRUY VẤN 2 NÂNG CẤP]: LẤY DANH SÁCH BÀI ĐĂNG KÈM THEO CHỈ SỐ TƯƠNG TÁC (LIKE/COMMENT)
    // ---------------------------------------------------------------------------------
    // [GIẢI THÍCH SQL]: Đây là truy vấn phức tạp nhất. Nó sử dụng Subquery (Truy vấn lồng)
    // để đếm tổng số Like, số Comment, số Share của từng bài viết.
    // Nó cũng kiểm tra xem User đang xem trang này (current_user_id) đã bấm Like bài này chưa
    // thông qua trường `is_liked_by_me` để tí nữa tô màu Xanh cho nút Like.
    $sql_posts = "
        SELECT 
            b.id AS post_id, 
            b.noi_dung, 
            b.created_at, 
            u.username, 
            u.giay_to_chung_minh,
            (SELECT COUNT(*) FROM luot_thich WHERE bai_dang_id = b.id) AS total_likes,
            (SELECT COUNT(*) FROM binh_luan WHERE bai_dang_id = b.id) AS total_comments,
            (SELECT COUNT(*) FROM luot_chia_se WHERE bai_dang_id = b.id) AS total_shares,
            (SELECT COUNT(*) FROM luot_thich WHERE bai_dang_id = b.id AND user_id = :uid) AS is_liked_by_me
        FROM 
            bai_dang b 
        INNER JOIN 
            users u ON b.user_id = u.id 
        WHERE 
            b.user_id = :profile_id 
        ORDER BY 
            b.created_at DESC
    ";
    $stmt_posts = $pdo->prepare($sql_posts);
    // Execute với 2 tham số: Người đang xem trang, và Chủ nhân của trang profile
    // (Trong ngữ cảnh này user đang xem chính trang của mình nên cả 2 tham số đều là $current_user_id)
    $stmt_posts->execute([
        'uid'        => $current_user_id,
        'profile_id' => $current_user_id
    ]);
    // Trả về mảng bài đăng
    $posts_data = $stmt_posts->fetchAll();

    // ---------------------------------------------------------------------------------
    // [TRUY VẤN 3]: LẤY DANH SÁCH BẠN BÈ VÀ KHẮC PHỤC LỖI HY093
    // ---------------------------------------------------------------------------------
    // Do PDO cấu hình không mô phỏng (emulate_prepares = false) bảo mật cực cao,
    // ta không được phép dùng 1 tên parameter `:id` nhiều lần trong SQL. 
    // Phải tách thành 3 parameter riêng biệt :id1, :id2, :id3.
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
            (f.user_id = :id1 OR f.friend_user_id = :id2) 
            AND f.status = 'Accepted' 
            AND u.id != :id3
    ";
    $stmt_buddies = $pdo->prepare($sql_buddies);
    // Gán tham số đầy đủ
    $stmt_buddies->execute([
        'id1' => $current_user_id,
        'id2' => $current_user_id,
        'id3' => $current_user_id
    ]);
    $buddies_data = $stmt_buddies->fetchAll();

    // ---------------------------------------------------------------------------------
    // [TRUY VẤN 4]: LẤY DANH SÁCH KHO TÀI LIỆU SỐ
    // ---------------------------------------------------------------------------------
    // Đã đồng bộ với bảng tai_lieu trong the_bunny_db.sql (Sử dụng cột file_)
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
    // [TRUY VẤN 5]: LẤY DANH SÁCH SỰ KIỆN HỌC THUẬT QUẢN LÝ QUA BẢNG TRUNG GIAN
    // ---------------------------------------------------------------------------------
    // Lấy thông qua liên kết (INNER JOIN) giữa su_kien và thanh_vien_su_kien
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
            AND tv.trang_thai_duyet = 'Approved'
        ORDER BY 
            sk.thoi_gian ASC
    ";
    
    $stmt_events = $pdo->prepare($sql_events);
    $stmt_events->execute(['id' => $current_user_id]);
    $events_data = $stmt_events->fetchAll();

} catch (PDOException $e) { 
    // Nếu có lỗi Cú pháp SQL (Sai tên bảng, lỗi type...) thì in ra đây
    $message_notify = "Lỗi kỹ thuật truy xuất Database: " . $e->getMessage();
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
        // Sự kiện DOMContentLoaded đảm bảo JS này chạy sau khi Bootstrap JS đã được nạp
        document.addEventListener('DOMContentLoaded', function () {
            // Quét và kích hoạt công cụ Tooltip (Nhãn dán nổi) cho biểu tượng Tích xanh
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>

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

<body class="bg-light">

    <?php if($message_notify != ""): ?>
        <div class="alert alert-<?= htmlspecialchars($message_type); ?> alert-dismissible fade show text-center m-0 rounded-0 shadow-sm border-0 position-fixed w-100" style="top: 0; z-index: 9999;" role="alert" aria-live="assertive">
            <i class="fa-solid <?= ($message_type == 'success') ? 'fa-check-circle' : 'fa-triangle-exclamation'; ?> me-2 fs-5 align-middle"></i>
            <strong class="fs-6 align-middle">Thông báo:</strong> <span class="fs-6 align-middle"><?= htmlspecialchars($message_notify); ?></span>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng cảnh báo"></button>
        </div>
        <div style="height: 58px;"></div>
    <?php endif; ?>

    <nav>
    <?php include '../includes/header.php'; ?>
    </nav>


    <div class="container mt-4 mb-5">
        
        <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4 bg-white">
            
            <div 
                class="card-img-top position-relative" 
                style="height: 320px; background: url('<?= htmlspecialchars($user_cover); ?>') center/cover no-repeat;"
                aria-label="Khối ảnh bìa hiển thị phía sau avatar"
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
                                alt="Ảnh chân dung của người dùng"
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
                                        title="Tài khoản đã cung cấp giấy tờ định danh"
                                        aria-label="Biểu tượng tài khoản đã xác thực cấp độ cao"
                                    ></i>
                                <?php endif; ?>
                            </h1>
                            
                            <span class="badge bg-secondary mb-2 text-uppercase letter-spacing-1 px-3 py-2 rounded-pill shadow-sm">
                                <?= htmlspecialchars($user_type ?: 'Thành viên mới'); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mt-4 mt-md-0 pb-3">
                        <button 
                            type="button"
                            class="btn btn-primary fw-bold px-4 py-2 rounded-pill shadow-sm d-flex align-items-center gap-2" 
                            data-bs-toggle="modal" 
                            data-bs-target="#editProfileModal"
                            aria-label="Nút nhấn mở cửa sổ điền thông tin thay đổi hồ sơ"
                        >
                            <i class="fa-solid fa-pen-to-square"></i> Cập nhật hồ sơ hệ thống
                        </button>
                    </div>
                </div>
                
                <div class="d-flex justify-content-center justify-content-md-start gap-4 gap-md-5 mt-5 pt-4 border-top border-light">
                    <div class="text-center text-md-start">
                        <h3 class="fw-bold m-0 text-warning d-flex align-items-center gap-2 justify-content-center justify-content-md-start">
                            <i class="fa-solid fa-carrot fs-4"></i> <?= number_format($stats_xp); ?>
                        </h3>
                        <span class="text-muted small text-uppercase fw-bold letter-spacing-1 opacity-75">Tích lũy Điểm (XP)</span>
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
                        <span class="text-muted small text-uppercase fw-bold letter-spacing-1 opacity-75">Kho Tài liệu cá nhân</span>
                    </div>
                </div>
            </div>
        </div> 

        <ul class="nav nav-pills bg-white p-2 rounded-4 shadow-sm gap-2 border flex-nowrap overflow-auto" id="profileTabs" role="tablist">
            
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
                    <i class="fa-solid fa-house"></i> <span>Dòng thời gian</span>
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
                    <i class="fa-solid fa-user-graduate"></i> <span>Hồ sơ định danh</span>
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
                    <i class="fa-solid fa-calendar-days"></i> <span>Kế hoạch Sự kiện</span>
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
                    <i class="fa-solid fa-user-group"></i> <span>Bạn đồng hành</span>
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
                                            <small class="text-muted d-block fw-medium mb-1">Trường Cơ sở / THPT</small>
                                            <strong class="text-dark"><?= htmlspecialchars($truong_hoc ?: 'Chưa cung cấp dữ liệu'); ?></strong>
                                        </div>
                                    </li>
                                    
                                    <li class="d-flex align-items-start gap-3">
                                        <div class="bg-success bg-opacity-10 p-2 rounded text-success">
                                            <i class="fa-solid fa-building-columns fs-5 w-20px text-center"></i>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block fw-medium mb-1">Đại học / Học viện liên kết</small>
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

                        <?php if (count($posts_data) > 0): ?>
                            
                            <?php foreach ($posts_data as $post): ?>
                            <article class="card shadow-sm border-0 mb-4 rounded-4 bg-white overflow-hidden">
                                <div class="card-body p-4">
                                    
                                    <header class="d-flex align-items-center justify-content-between mb-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <img src="../assets/img/default-avatar.jpg" class="rounded-circle border shadow-sm" width="55" height="55" style="object-fit: cover;" alt="Tác giả bài đăng">
                                            
                                            <div>
                                                <h6 class="m-0 fw-bold text-dark fs-5 d-flex align-items-center gap-2">
                                                    <?= htmlspecialchars($post['username']); ?>
                                                    
                                                    <?php if(!empty($post['giay_to_chung_minh'])): ?>
                                                        <i class="fa-solid fa-circle-check text-primary fs-6" title="Tài khoản học sinh xác minh hệ thống"></i>
                                                    <?php endif; ?>
                                                </h6>
                                                
                                                <small class="text-muted fw-medium d-flex align-items-center gap-1">
                                                    <i class="fa-regular fa-clock"></i> 
                                                    <?= time_elapsed_string($post['created_at']); ?> 
                                                    <span class="mx-1">•</span> 
                                                    <i class="fa-solid fa-earth-americas" title="Phạm vi công khai toàn cầu"></i>
                                                </small>
                                            </div>
                                        </div>
                                        
                                        <button class="btn btn-light rounded-circle text-muted shadow-sm" style="width:40px; height:40px;" aria-label="Hiện menu Tùy chọn chức năng">
                                            <i class="fa-solid fa-ellipsis"></i>
                                        </button>
                                    </header>
                                    
                                    <div class="post-content mt-3">
                                        <p class="m-0 fs-5 text-dark" style="line-height: 1.8; white-space: pre-line;">
                                            <?= htmlspecialchars($post['noi_dung']); ?>
                                        </p>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mt-4 border-bottom pb-3">
                                        <div class="d-flex align-items-center gap-2 post-stats-text">
                                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 22px; height: 22px;">
                                                <i class="fa-solid fa-thumbs-up" style="font-size: 10px;"></i>
                                            </div>
                                            <span class="fw-medium text-muted"><?= (int)$post['total_likes']; ?> lượt tương tác</span>
                                        </div>
                                        <div class="d-flex gap-3 text-muted fw-medium fs-6">
                                            <span class="post-stats-text"><?= (int)$post['total_comments']; ?> phản hồi</span>
                                            <span class="post-stats-text"><?= (int)$post['total_shares']; ?> chia sẻ</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <footer class="card-footer bg-white border-0 pb-3 px-4 pt-0">
                                    <div class="d-flex justify-content-between gap-2">
                                        
                                        <form method="POST" action="" class="flex-fill m-0">
                                            <input type="hidden" name="action" value="like_post">
                                            <input type="hidden" name="post_id" value="<?= $post['post_id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                                            
                                            <button type="submit" class="w-100 py-2 d-flex align-items-center justify-content-center gap-2 btn-interaction <?= ($post['is_liked_by_me'] > 0) ? 'active-like' : ''; ?>">
                                                <i class="fa-solid fa-thumbs-up fs-5"></i>
                                                <?= ($post['is_liked_by_me'] > 0) ? 'Đã thích' : 'Thích bài'; ?>
                                            </button>
                                        </form>
                                        
                                        <button 
                                            type="button" 
                                            class="w-100 py-2 d-flex align-items-center justify-content-center gap-2 btn-interaction flex-fill" 
                                            data-bs-toggle="collapse" 
                                            data-bs-target="#collapseComments-<?= $post['post_id']; ?>" 
                                            aria-expanded="false" 
                                            aria-controls="collapseComments-<?= $post['post_id']; ?>"
                                        >
                                            <i class="fa-regular fa-comment fs-5"></i> Viết luận
                                        </button>
                                        
                                        <form method="POST" action="" class="flex-fill m-0">
                                            <input type="hidden" name="action" value="share_post">
                                            <input type="hidden" name="post_id" value="<?= $post['post_id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                                            <button type="submit" class="w-100 py-2 d-flex align-items-center justify-content-center gap-2 btn-interaction">
                                                <i class="fa-solid fa-share fs-5"></i> Phân phối
                                            </button>
                                        </form>
                                    </div>
                                </footer>

                                <div class="collapse" id="collapseComments-<?= $post['post_id']; ?>">
                                    <div class="card-footer bg-light border-top border-light p-4">
                                        
                                        <?php
                                            $sql_comments = "
                                                SELECT c.noi_dung, c.created_at, u.username 
                                                FROM binh_luan c 
                                                INNER JOIN users u ON c.user_id = u.id 
                                                WHERE c.bai_dang_id = :pid 
                                                ORDER BY c.created_at ASC
                                            ";
                                            $stmt_cmt = $pdo->prepare($sql_comments);
                                            $stmt_cmt->execute(['pid' => $post['post_id']]);
                                            $comments = $stmt_cmt->fetchAll();
                                        ?>
                                        
                                        <?php if(count($comments) > 0): ?>
                                            <div class="mb-4">
                                                <h6 class="fw-bold text-muted small mb-3 text-uppercase letter-spacing-1">Phản hồi học thuật</h6>
                                                
                                                <?php foreach($comments as $cmt): ?>
                                                    <div class="d-flex gap-3 mb-3">
                                                        <img src="../assets/img/default-avatar.jpg" class="rounded-circle border shadow-sm flex-shrink-0" width="40" height="40" alt="Avt Cmt">
                                                        <div class="comment-box w-100 shadow-sm border border-white">
                                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                                <h6 class="fw-bold m-0 text-dark fs-6"><?= htmlspecialchars($cmt['username']); ?></h6>
                                                                <small class="text-muted fw-medium" style="font-size: 0.75rem;"><?= time_elapsed_string($cmt['created_at']); ?></small>
                                                            </div>
                                                            <p class="m-0 text-dark" style="font-size: 0.95rem; line-height: 1.5;">
                                                                <?= nl2br(htmlspecialchars($cmt['noi_dung'])); ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                        <form method="POST" action="" class="d-flex gap-3 align-items-start mt-2">
                                            <input type="hidden" name="action" value="comment_post">
                                            <input type="hidden" name="post_id" value="<?= $post['post_id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                                            
                                            <img src="<?= htmlspecialchars($user_avatar); ?>" class="rounded-circle border shadow-sm flex-shrink-0" width="45" height="45" style="object-fit:cover;" alt="Avatar cá nhân">
                                            
                                            <div class="input-group shadow-sm rounded-pill overflow-hidden bg-white border border-light">
                                                <input 
                                                    type="text" 
                                                    class="form-control border-0 px-4 py-2 fs-6 bg-transparent" 
                                                    name="noi_dung_binh_luan" 
                                                    placeholder="Đóng góp ý kiến của bạn vào cuộc thảo luận..." 
                                                    required
                                                >
                                                <button class="btn btn-primary px-4 fw-bold" type="submit">
                                                    <i class="fa-solid fa-paper-plane"></i>
                                                </button>
                                            </div>
                                        </form>

                                    </div>
                                </div> </article>
                            <?php endforeach; ?>
                            
                        <?php else: ?>
                            <div class="text-center py-5 bg-white shadow-sm rounded-4 border border-light">
                                <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 100px; height: 100px;">
                                    <i class="fa-regular fa-pen-to-square fs-1 text-primary opacity-75"></i>
                                </div>
                                <h4 class="text-dark fw-bold">Chưa có dấu ấn học thuật cá nhân</h4>
                                <p class="text-muted fs-6 m-0 px-4">Hãy chia sẻ trạng thái học tập đầu tiên của bạn để kết nối với những người dùng The Bunny khác nhé!</p>
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
                                <h5 class="fw-bold text-dark mb-4"><i class="fa-solid fa-server text-secondary me-2"></i> Dữ liệu Hệ thống Khung</h5>
                                
                                <dl class="row mb-0 fs-5">
                                    <dt class="col-sm-4 text-muted fw-normal mb-3">Tên tài khoản</dt>
                                    <dd class="col-sm-8 text-dark fw-bold mb-3"><?= htmlspecialchars($user_name); ?></dd>
                                    
                                    <dt class="col-sm-4 text-muted fw-normal mb-3">Nhóm quyền hạn</dt>
                                    <dd class="col-sm-8 mb-3"><span class="badge bg-primary fs-6 px-3 py-2"><?= htmlspecialchars($user_type ?: 'Chưa phân cấp hạng'); ?></span></dd>
                                    
                                    <dt class="col-sm-4 text-muted fw-normal mb-3">Trạng thái Căn cước</dt>
                                    <dd class="col-sm-8 mb-3">
                                        <?php if($is_verified): ?>
                                            <span class="text-success fw-bold"><i class="fa-solid fa-shield-check"></i> Đã nộp hồ sơ Chứng minh</span>
                                        <?php else: ?>
                                            <span class="text-danger fw-bold"><i class="fa-solid fa-triangle-exclamation"></i> Hệ thống chưa thể xác minh</span>
                                        <?php endif; ?>
                                    </dd>
                                </dl>
                            </div>
                            
                            <div class="col-md-6">
                                <h5 class="fw-bold text-dark mb-4"><i class="fa-solid fa-book-open-reader text-secondary me-2"></i> Thông tin Tự giới thiệu Bản thân</h5>
                                
                                <div class="bg-light p-4 rounded-4 border border-secondary-subtle h-100">
                                    <p class="m-0 fs-5 text-dark fw-medium" style="line-height: 1.8;">
                                        <?= nl2br(htmlspecialchars($thong_tin_dinh_danh ?: 'Thành viên này hiện tại chưa cập nhật bài luận mô tả chi tiết năng lực học thuật.')); ?>
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
                                            <div class="bg-danger bg-opacity-10 p-3 rounded-circle text-danger flex-shrink-0">
                                                <i class="fa-solid fa-file-pdf fs-3"></i>
                                            </div>
                                            
                                            <div class="overflow-hidden">
                                                <h5 class="m-0 fw-bold text-dark text-truncate mb-2" style="max-width: 250px;" title="<?= htmlspecialchars($doc['ten_tai_lieu']); ?>">
                                                    <?= htmlspecialchars($doc['ten_tai_lieu']); ?>
                                                </h5>
                                                
                                                <small class="text-muted fw-bold fs-6">
                                                    Đóng góp: <?= date('d/m/Y', strtotime($doc['created_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                        
                                        <a href="<?= htmlspecialchars($doc['file_url']); ?>" target="_blank" class="btn btn-outline-primary rounded-circle shadow-sm ms-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 50px; height: 50px;" aria-label="Tiến hành Tải tệp tin vật lý">
                                            <i class="fa-solid fa-download fs-5"></i>
                                        </a>
                                        
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12 text-center py-5">
                                    <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" alt="Kho tài liệu trống" width="120" class="opacity-50 mb-4">
                                    <h4 class="text-dark fw-bold">Không gian tài liệu đang trống</h4>
                                    <p class="text-muted fs-5">Bấm nút "Cống hiến tài liệu" phía trên để lưu trữ đề cương và bài giảng của bạn trên máy chủ The Bunny.</p>
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
                            <h3 class="fw-bold m-0 text-dark"><i class="fa-regular fa-calendar-check text-primary me-3"></i>Danh sách Lịch trình Nhóm học tập</h3>
                            <button 
                                class="btn btn-warning fw-bold text-dark px-4 py-2 rounded-pill shadow-sm d-flex align-items-center gap-2" 
                                data-bs-toggle="modal" 
                                data-bs-target="#addEventModal"
                            >
                                <i class="fa-solid fa-calendar-plus fs-5"></i> Tổ chức Lịch trình mới
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
                                                    Hẹn khởi chiếu: <?= date('H:i A', strtotime($event['thoi_gian'])); ?>
                                                </p>
                                            </div>
                                            
                                            <button class="btn btn-outline-primary w-100 fw-bold rounded-pill shadow-sm">Thành viên Ban tổ chức</button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12 text-center py-5">
                                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 100px; height: 100px;">
                                        <i class="fa-regular fa-calendar-xmark fs-1 text-warning opacity-75"></i>
                                    </div>
                                    <h4 class="text-dark fw-bold">Không có lịch học nào sắp tới</h4>
                                    <p class="text-muted fs-5 m-0 px-4">Đóng vai trò là một diễn giả kiến thức và tổ chức một nhóm thảo luận học tập trực tuyến mới ngay bây giờ.</p>
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
                            Quản lý Danh sách Bạn cùng tiến 
                            <span class="badge bg-light text-dark border fs-5 rounded-pill px-3 shadow-sm"><?= number_format($stats_buddies); ?> liên kết</span>
                        </h3>
                        
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                            <?php if (count($buddies_data) > 0): ?>
                                <?php foreach ($buddies_data as $buddy): ?>
                                <div class="col">
                                    <div class="border border-light rounded-4 p-4 d-flex flex-column align-items-center text-center bg-white shadow-sm h-100 hover-border-primary transition position-relative">
                                        
                                        <img src="../assets/img/default-avatar.jpg" class="rounded-circle border border-3 border-light shadow-sm mb-3" width="90" height="90" style="object-fit: cover;" alt="Hình đại diện mạng lưới của bạn bè">
                                        
                                        <div class="flex-grow-1 w-100 mb-3">
                                            <h5 class="fw-bold m-0 text-dark text-truncate px-2 mb-2" title="<?= htmlspecialchars($buddy['username']); ?>">
                                                <?= htmlspecialchars($buddy['username']); ?>
                                            </h5>
                                            <span class="badge bg-light text-secondary fw-bold text-wrap px-3 py-2 lh-base w-100 shadow-sm border border-light">
                                                <i class="fa-solid fa-school me-1 text-primary"></i> <?= htmlspecialchars($buddy['truong_hoc'] ?: 'Tự học tại hệ thống The Bunny'); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="d-flex gap-2 w-100">
                                            <button class="btn btn-primary flex-fill fw-bold rounded-pill shadow-sm" aria-label="Soạn tin nhắn riêng tư với liên kết này">
                                                <i class="fa-solid fa-paper-plane me-1"></i> Nhắn tin
                                            </button>
                                            <button class="btn btn-light rounded-pill border shadow-sm px-3 text-danger hover-bg-danger transition" aria-label="Xóa hoàn toàn khỏi danh sách bạn bè">
                                                <i class="fa-solid fa-user-xmark"></i>
                                            </button>
                                        </div>
                                        
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                
                            <?php else: ?>
                                <div class="col-12 text-center py-5">
                                    <img src="https://cdn-icons-png.flaticon.com/512/3394/3394785.png" alt="Danh sách mạng lưới hoàn toàn trống" width="120" class="opacity-50 mb-4">
                                    <h4 class="text-dark fw-bold">Hiện chưa có liên kết học tập đồng hành nào</h4>
                                    <p class="text-muted fs-5 m-0 px-4">Hãy khám phá tính năng Hang thỏ hoặc Bảng xếp hạng Thách đấu trên The Bunny để gửi lời mời và tìm kiếm những người bạn cùng chung năng lực học thuật.</p>
                                    <button class="btn btn-primary fw-bold px-5 py-3 rounded-pill mt-4 shadow-sm fs-5"><i class="fa-solid fa-magnifying-glass me-2"></i> Tiến hành kết nối bạn mới</button>
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
                            <i class="fa-solid fa-user-pen me-2"></i> Bảng Cập Nhật Thông Số Cá Nhân
                        </h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Lệnh đóng bảng hồ sơ"></button>
                    </div>
                    
                    <div class="modal-body px-5 py-4 bg-white">
                        
                        <div class="alert alert-info border-0 rounded-3 mb-4 d-flex align-items-center gap-3 shadow-sm">
                            <i class="fa-solid fa-circle-info fs-3"></i>
                            <div class="fs-6">
                                <strong>Khuyến nghị từ Hội đồng máy chủ:</strong> 
                                Các trường thông tin cơ sở cung cấp dưới đây sẽ liên kết chặt chẽ vào cơ sở dữ liệu hệ thống thông qua khóa ID. Dữ liệu này sẽ được lưu cố định và hiển thị công khai trên diện rộng.
                            </div>
                        </div>

                        <div class="row g-4">
                            
                            <div class="col-md-6">
                                <h6 class="fw-bold text-muted text-uppercase mb-3 border-bottom pb-2">Học Vụ Hệ Thống (Bảng Khung)</h6>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-dark fs-6">Định danh hiển thị (Tên hệ thống) <span class="text-danger">*</span></label>
                                    <input 
                                        type="text" 
                                        class="form-control border-secondary-subtle py-2 px-3 fw-medium rounded-3" 
                                        name="username" 
                                        value="<?= htmlspecialchars($user_name); ?>" 
                                        required
                                        placeholder="Ghi rõ Họ Tên thật của bạn"
                                    >
                                    <div class="form-text mt-2"><i class="fa-solid fa-shield-halved text-success"></i> Tên dùng để hệ thống tra cứu đồng bộ mạng lưới.</div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-dark fs-6">Tổ chức Cấp 3 / Trường Phổ Thông Cơ sở</label>
                                    <div class="input-group shadow-sm">
                                        <span class="input-group-text bg-light border-secondary-subtle"><i class="fa-solid fa-school text-muted"></i></span>
                                        <input 
                                            type="text" 
                                            class="form-control border-secondary-subtle py-2 px-3 fw-medium rounded-end-3" 
                                            name="truong_hoc" 
                                            value="<?= htmlspecialchars($truong_hoc); ?>" 
                                            placeholder="Ghi rõ tên trường THPT mà bạn đang theo học..."
                                        >
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-dark fs-6">Cơ sở Đào tạo Cao Đẳng / Đại Học / Học Viện</label>
                                    <div class="input-group shadow-sm">
                                        <span class="input-group-text bg-light border-secondary-subtle"><i class="fa-solid fa-building-columns text-muted"></i></span>
                                        <input 
                                            type="text" 
                                            class="form-control border-secondary-subtle py-2 px-3 fw-medium rounded-end-3" 
                                            name="truong_dai_hoc" 
                                            value="<?= htmlspecialchars($truong_dai_hoc); ?>" 
                                            placeholder="Tên trường Đại Học mơ ước hoặc đang học..."
                                        >
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 d-flex flex-column">
                                <h6 class="fw-bold text-muted text-uppercase mb-3 border-bottom pb-2">Bài Luận Tự Giới Thiệu (Nhóm Hồ Sơ Chi Tiết Mở Rộng)</h6>
                                
                                <div class="mb-3 flex-grow-1 d-flex flex-column">
                                    <label class="form-label fw-bold text-dark fs-6">Soạn thảo văn bản tuyên ngôn định danh chuyên sâu</label>
                                    <textarea 
                                        class="form-control border-secondary-subtle p-3 fw-medium rounded-3 flex-grow-1 shadow-sm" 
                                        name="thong_tin_dinh_danh" 
                                        placeholder="Hãy sử dụng khung nhập liệu tự do này để khai báo các đặc điểm mạnh mẽ không có khuôn khổ như: Sở thích học tập cá nhân, những châm ngôn lý tưởng, chuyên ngành dự định thi Đại Học hoặc các thành tích giải thưởng học sinh giỏi quốc gia bạn đã đạt được trong quá khứ... (Trình biên dịch máy chủ Backend sẽ tự động bảo toàn các cấu trúc khoảng trắng và xuống dòng của bạn)"
                                        style="resize: none; min-height: 250px;"
                                    ><?= htmlspecialchars($thong_tin_dinh_danh); ?></textarea>
                                </div>
                            </div>
                            
                        </div>
                    </div> 
                    
                    <div class="modal-footer bg-light border-top-0 py-4 px-5 rounded-bottom-4 justify-content-between">
                        <small class="text-muted fw-medium"><i class="fa-solid fa-lock text-success"></i> Truyền tải An toàn mã hóa qua cầu nối giao thức PDO chuẩn MySQL</small>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-light fw-bold px-4 py-2 rounded-pill shadow-sm border" data-bs-dismiss="modal">Từ chối thao tác</button>
                            <button type="submit" class="btn btn-primary fw-bold px-5 py-2 rounded-pill shadow-sm"><i class="fa-solid fa-cloud-arrow-up me-2"></i> Lưu Trữ Đồng Bộ Hóa</button>
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
            
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_document">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    
                    <div class="modal-header bg-success text-white border-0 py-4 px-4">
                        <h4 class="modal-title fw-bold" id="addDocModalLabel">
                            <i class="fa-solid fa-cloud-arrow-up me-2 fs-3 align-middle"></i> Khởi tạo Cổng Tải Học Liệu Lưu Trữ
                        </h4>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng cửa sổ Upload File Bộ Nhớ"></button>
                    </div>
                    
                    <div class="modal-body p-4 bg-white">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark fs-6">Định danh Tiêu đề Tài Liệu Lõi <span class="text-danger">*</span></label>
                            <input 
                                type="text" 
                                class="form-control form-control-lg border-secondary-subtle fw-medium fs-6 rounded-3 shadow-sm" 
                                name="ten_tai_lieu" 
                                placeholder="Viết rõ ràng để hệ thống AI đánh hashtag. VD: 15 Chuyên Đề Phân Tích Lõi Văn Học (Lớp 12)..." 
                                required
                            >
                        </div>
                        
                        <div class="mb-2 p-4 border border-dashed border-2 border-secondary-subtle rounded-3 text-center bg-light">
                            <i class="fa-solid fa-file-pdf fs-1 text-danger mb-3 d-block"></i>
                            <label class="form-label fw-bold text-dark fs-6 d-block mb-3">Tương tác thao tác chọn tệp từ máy tính Local nội bộ</label>
                            
                            <input 
                                type="file" 
                                class="form-control mx-auto w-100 shadow-sm border-success py-2 fw-bold text-success" 
                                name="file_tai_lieu" 
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx" 
                                required
                            >
                            
                            <div class="alert alert-info border-0 mt-4 text-start mb-0 small shadow-sm">
                                <i class="fa-solid fa-shield-check text-primary fw-bold me-1 fs-5 align-middle"></i> <strong>Kiểm soát hệ thống mở khóa File System (100% Khả dụng):</strong> 
                                Hệ thống bảo mật <code>move_uploaded_file()</code> thuộc lõi PHP đã được nạp tiến trình thành công. Các tệp tin được duyệt sẽ được máy chủ phân quyền rễ <code>chmod 0777</code> và cấp phát bộ nhớ vào thẳng thư mục Master <code>/DOAN/uploads/document/</code> của cấu trúc vật lý thực tế.
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer border-0 py-3 px-4 bg-light justify-content-between">
                        <small class="text-muted fw-bold"><i class="fa-solid fa-server text-success"></i> Limit: MAX_FILE_SIZE = 10MB</small>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-light fw-bold px-4 py-2 rounded-pill border shadow-sm" data-bs-dismiss="modal">Hủy bỏ tiến trình</button>
                            <button type="submit" class="btn btn-success fw-bold px-5 py-2 rounded-pill shadow-sm">Bắt đầu Streaming Dữ Liệu Lên</button>
                        </div>
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
                            <i class="fa-solid fa-calendar-plus me-2 fs-3 align-middle"></i> Setup Master Kế Hoạch Sự Kiện Giáo Dục
                        </h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng bảng Lập trình Lịch"></button>
                    </div>
                    
                    <div class="modal-body p-4 bg-white">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark fs-6">Định danh Trọng tâm của Buổi họp Hội đồng / Ôn thi <span class="text-danger">*</span></label>
                            <input 
                                type="text" 
                                class="form-control border-secondary-subtle py-2 px-3 fw-medium rounded-3 shadow-sm" 
                                name="tieu_de" 
                                placeholder="Ghi tiêu đề cực kỳ tường minh. VD: Giải Đề Thi Thử Toán Khối 12 Ban Tự Nhiên..." 
                                required
                            >
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark fs-6">Khai báo Thông số Thời Gian Bấm Giờ Trực Tiếp (Live Stream/Offline) <span class="text-danger">*</span></label>
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
                        
                        <div class="alert alert-warning border-0 rounded-3 mb-0 d-flex align-items-start gap-3 small shadow-sm bg-warning bg-opacity-25">
                            <i class="fa-solid fa-sitemap fs-4 mt-1 text-dark"></i>
                            <div class="text-dark fw-medium">
                                Tuân thủ thiết kế Relational Database, sau khi khởi tạo Object ảo trong Table <code>su_kien</code>, cấu trúc truy vấn vòng lặp Transaction của PHP sẽ tự động gọi Hàm khóa ngoại lấy ID của bạn Insert đè vào Table <code>thanh_vien_su_kien</code>, cấp quyền cho bạn trở thành Master Quản trị viên điều phối.
                            </div>
                        </div>
                        
                    </div>
                    
                    <div class="modal-footer border-top-0 py-4 px-4 bg-light justify-content-between">
                        <small class="text-muted fw-bold"><i class="fa-solid fa-tower-broadcast text-warning"></i> Cáp truyền tải Broadcast Thông báo toàn lưới bạn bè</small>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-light fw-bold px-4 py-2 rounded-pill border shadow-sm" data-bs-dismiss="modal">Thoát bảng sự kiện</button>
                            <button type="submit" class="btn btn-warning fw-bold px-5 py-2 rounded-pill shadow-sm text-dark">Kích hoạt lệnh Lên Lịch</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

</body>
</html>