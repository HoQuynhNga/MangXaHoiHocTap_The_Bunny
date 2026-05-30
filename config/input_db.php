<?php
require_once 'config.php';

echo "<!DOCTYPE html>
<html lang='vi'>
<head>
    <meta charset='UTF-8'>
    <title>Cấu hình data mẫu</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body class='bg-light'>
<div class='container mt-5'>
<div class='card shadow'>
<div class='card-header bg-primary text-white'>
    <h3>Seed Full Database - The Bunny</h3>
</div>
<div class='card-body'>";

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset(DB_CHARSET);

if ($conn->connect_error) {
    die("<div class='alert alert-danger'>
        Kết nối thất bại: " . $conn->connect_error . "
    </div>");
}

echo "<ul class='list-group mb-4'>";

function runQuery($conn, $sql, $successMessage, $errorTitle)
{
    if ($conn->query($sql) === TRUE) {
        echo "<li class='list-group-item list-group-item-success'>
            ✅ $successMessage
        </li>";
    } else {
        echo "<li class='list-group-item list-group-item-danger'>
            ❌ $errorTitle: " . $conn->error . "
        </li>";
    }
}

$password = password_hash('123456', PASSWORD_BCRYPT);

// =====================================================
// 1. USERS
// =====================================================
$sql = "
INSERT IGNORE INTO users
(username, email, password_hash, status, is_online, user_type, truong_hoc, truong_dai_hoc, giay_to_chung_minh, role_level)
VALUES
('admin', 'admin@thebunny.vn', '$password', 'Active', 1, 'quan_tri_vien', NULL, NULL, 'ADMIN001', 10),

('gvngoc', 'gv.ngoc@thebunny.vn', '$password', 'Active', 1, 'giao_vien', 'THPT Nguyễn Du', NULL, 'GV001', 5),

('tienanh', 'tienanh@student.vn', '$password', 'Active', 1, 'sinh_vien', NULL, 'UEH', NULL, NULL),

('quynhnga', 'quynhnga@student.vn', '$password', 'Active', 0, 'sinh_vien', NULL, 'UEH', NULL, NULL),

('minhquan', 'minhquan@student.vn', '$password', 'Active', 1, 'hoc_sinh', 'THPT Lê Quý Đôn', NULL, NULL, NULL)
";
runQuery($conn, $sql, "Đã tạo Users", "Lỗi Users");

// =====================================================
// 2. HO_SO_CA_NHAN
// =====================================================
$sql = "
INSERT IGNORE INTO ho_so_ca_nhan (user_id, thong_tin_dinh_danh)
VALUES
(1, 'Quản trị viên hệ thống'),
(2, 'Giáo viên Web Development'),
(3, 'Sinh viên CNTT'),
(4, 'Yêu thích UI UX'),
(5, 'Học sinh chuyên Tin')
";
runQuery($conn, $sql, "Đã tạo Hồ sơ cá nhân", "Lỗi Hồ sơ");

// =====================================================
// 3. HANG_THO
// =====================================================
$sql = "
INSERT IGNORE INTO hang_tho (ten_hang_tho)
VALUES
('Hội Web Development'),
('Góc Cày Deadline'),
('UI UX Community'),
('Luyện Thi TOEIC')
";
runQuery($conn, $sql, "Đã tạo Hang Thỏ", "Lỗi Hang Thỏ");

// =====================================================
// 4. USER_HANG_THO
// =====================================================
$sql = "
INSERT IGNORE INTO user_hang_tho (user_id, hang_tho_id)
VALUES
(3,1),
(4,1),
(4,3),
(5,4)
";
runQuery($conn, $sql, "Đã thêm User vào Hang Thỏ", "Lỗi User Hang");

// =====================================================
// 5. PHIEN_LUYEN_TAP
// =====================================================
$sql = "
INSERT INTO phien_luyen_tap (user_id, diem_so)
VALUES
(3, 85),
(4, 92),
(5, 70)
";
runQuery($conn, $sql, "Đã tạo Phiên luyện tập", "Lỗi Phiên luyện tập");

// =====================================================
// 6. BO_DE
// =====================================================
$sql = "
INSERT INTO bo_de (ten_bo_de)
VALUES
('Đề PHP'),
('Đề HTML CSS'),
('Đề JavaScript'),
('Đề SQL')
";
runQuery($conn, $sql, "Đã tạo Bộ đề", "Lỗi Bộ đề");

// =====================================================
// 7. PHONG_THACH_DAU
// =====================================================
$sql = "
INSERT INTO phong_thach_dau (bo_de_id)
VALUES
(1),
(2),
(3)
";
runQuery($conn, $sql, "Đã tạo Phòng thách đấu", "Lỗi Phòng");

// =====================================================
// 8. USER_PHONG_THACH_DAU
// =====================================================
$sql = "
INSERT INTO user_phong_thach_dau (user_id, phong_thach_dau_id)
VALUES
(3,1),
(4,1),
(5,2)
";
runQuery($conn, $sql, "Đã thêm User vào Phòng", "Lỗi User Phòng");

// =====================================================
// 9. TAI_LIEU
// =====================================================
$sql = "
INSERT INTO tai_lieu (user_id, ten_tai_lieu, file_url)
VALUES
(2, 'Slide PHP', '/files/php.pdf'),
(3, 'Tài liệu SQL', '/files/sql.pdf')
";
runQuery($conn, $sql, "Đã tạo Tài liệu", "Lỗi Tài liệu");

// =====================================================
// 10. BAI_DANG
// =====================================================
$sql = "
INSERT INTO bai_dang (user_id, noi_dung)
VALUES
(2, 'Tuần sau kiểm tra PHP nhé các em'),
(3, 'Ai biết fix lỗi port 80 không?'),
(4, 'Bootstrap grid quá tiện')
";
runQuery($conn, $sql, "Đã tạo Bài đăng", "Lỗi Bài đăng");

// =====================================================
// 11. BAN_CUNG_TIEN
// =====================================================
$sql = "
INSERT IGNORE INTO ban_cung_tien (user_id, friend_user_id, status)
VALUES
(3,4,'Accepted'),
(3,5,'Pending'),
(4,5,'Accepted')
";
runQuery($conn, $sql, "Đã tạo Bạn cùng tiến", "Lỗi Bạn cùng tiến");

// =====================================================
// 12. CUOC_TRO_CHUYEN
// =====================================================
$sql = "
INSERT INTO cuoc_tro_chuyen ()
VALUES (),(),()
";
runQuery($conn, $sql, "Đã tạo Cuộc trò chuyện", "Lỗi Chat");

// =====================================================
// 13. CUOC_TRO_CHUYEN_THANH_VIEN
// =====================================================
$sql = "
INSERT INTO cuoc_tro_chuyen_thanh_vien (cuoc_tro_chuyen_id, user_id)
VALUES
(1,3),
(1,4),
(2,4),
(2,5)
";
runQuery($conn, $sql, "Đã thêm Thành viên chat", "Lỗi Thành viên chat");

// =====================================================
// 14. TIN_NHAN
// =====================================================
$sql = "
INSERT INTO tin_nhan (cuoc_tro_chuyen_id, sender_user_id, noi_dung)
VALUES
(1,3,'Hello mọi người'),
(1,4,'Chào nhé'),
(2,5,'Deadline tới rồi')
";
runQuery($conn, $sql, "Đã tạo Tin nhắn", "Lỗi Tin nhắn");

// =====================================================
// 15. SU_KIEN
// =====================================================
$sql = "
INSERT INTO su_kien (tieu_de, thoi_gian)
VALUES
('Workshop PHP','2026-06-15 18:00:00'),
('Hackathon Web','2026-07-01 08:00:00')
";
runQuery($conn, $sql, "Đã tạo Sự kiện", "Lỗi Sự kiện");

// =====================================================
// 16. THANH_VIEN_SU_KIEN
// =====================================================
$sql = "
INSERT INTO thanh_vien_su_kien (su_kien_id, user_id, trang_thai_duyet)
VALUES
(1,3,'Approved'),
(1,4,'Approved'),
(2,5,'Pending')
";
runQuery($conn, $sql, "Đã thêm Thành viên sự kiện", "Lỗi TV sự kiện");

// =====================================================
// 17. THONG_BAO
// =====================================================
$sql = "
INSERT INTO thong_bao (user_id, noi_dung, is_read)
VALUES
(3,'Bạn có thông báo mới',0),
(4,'Có người thích bài viết của bạn',1)
";
runQuery($conn, $sql, "Đã tạo Thông báo", "Lỗi Thông báo");

// =====================================================
// 18. BAO_CAO_THONG_KE
// =====================================================
$sql = "
INSERT INTO bao_cao_thong_ke (quan_tri_user_id, loai_bao_cao, noi_dung_bao_cao)
VALUES
(1,'MONTHLY','Báo cáo tháng 6'),
(1,'USER','Thống kê người dùng')
";
runQuery($conn, $sql, "Đã tạo Báo cáo", "Lỗi Báo cáo");

// =====================================================
// 19. BINH_LUAN
// =====================================================
$sql = "
INSERT INTO binh_luan (bai_dang_id, user_id, noi_dung)
VALUES
(1,3,'Dạ vâng cô'),
(2,4,'Đổi port Apache thử xem')
";
runQuery($conn, $sql, "Đã tạo Bình luận", "Lỗi Bình luận");

// =====================================================
// 20. LUOT_THICH
// =====================================================
$sql = "
INSERT IGNORE INTO luot_thich (bai_dang_id, user_id)
VALUES
(1,3),
(1,4),
(2,5)
";
runQuery($conn, $sql, "Đã tạo Lượt thích", "Lỗi Like");

// =====================================================
// 21. LUOT_CHIA_SE
// =====================================================
$sql = "
INSERT INTO luot_chia_se (bai_dang_id, user_id)
VALUES
(1,4),
(2,3)
";
runQuery($conn, $sql, "Đã tạo Lượt chia sẻ", "Lỗi Share");

// =====================================================
// 22. TRAN_DAU
// =====================================================
$sql = "
INSERT INTO tran_dau
(phong_thach_dau_id, nguoi_choi_1_id, nguoi_choi_2_id, diem_nguoi_1, diem_nguoi_2, trang_thai)
VALUES
(1,3,4,8,10,'Finished'),
(2,4,5,5,2,'Ongoing')
";
runQuery($conn, $sql, "Đã tạo Trận đấu", "Lỗi Trận đấu");

// =====================================================
// 23. CAU_HOI
// =====================================================
$sql = "
INSERT INTO cau_hoi
(bo_de_id, noi_dung, lua_chon_a, lua_chon_b, lua_chon_c, lua_chon_d, dap_an_dung)
VALUES
(
1,
'PHP là gì?',
'Ngôn ngữ lập trình',
'Hệ điều hành',
'Database',
'Framework',
'Ngôn ngữ lập trình'
),
(
2,
'HTML dùng để làm gì?',
'Thiết kế giao diện',
'Server',
'Database',
'Machine Learning',
'Thiết kế giao diện'
)
";
runQuery($conn, $sql, "Đã tạo Câu hỏi", "Lỗi Câu hỏi");

// =====================================================
// 24. HASHTAG
// =====================================================
$sql = "
INSERT IGNORE INTO hashtag (ten_hashtag)
VALUES
('#php'),
('#webdev'),
('#ueh')
";
runQuery($conn, $sql, "Đã tạo Hashtag", "Lỗi Hashtag");

// =====================================================
// 25. BAO_CAO_VI_PHAM
// =====================================================
$sql = "
INSERT INTO bao_cao_vi_pham
(nguoi_bao_cao_id, bai_dang_id, nguoi_bi_bao_cao_id, ly_do, trang_thai)
VALUES
(3,1,2,'Spam nội dung','Pending'),
(4,2,3,'Ngôn từ không phù hợp','Reviewed')
";
runQuery($conn, $sql, "Đã tạo Báo cáo vi phạm", "Lỗi Báo cáo VP");

$conn->close();

echo "</ul>";

echo "
<div class='text-center'>
<div class='alert alert-info'>
<strong>Tài khoản test:</strong><br>
admin@thebunny.vn / 123456
</div>

<a href='../index.php' class='btn btn-primary'>
Vào Trang Chủ
</a>
</div>";

echo "
</div>
</div>
</div>
</body>
</html>";
?>
