<?php
// Nạp cấu hình hệ thống
require_once 'config.php';

echo "<!DOCTYPE html><html lang='vi'><head><meta charset='UTF-8'><title>Nhập dữ liệu mẫu - The Bunny</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'></head><body class='bg-light'><div class='container mt-5'><div class='card shadow'><div class='card-header bg-warning text-dark'><h4>Tiến trình Nhập Dữ liệu Mẫu (Seed Data)</h4></div><div class='card-body'>";

// 1. KẾT NỐI ĐẾN MYSQL SERVER
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset(DB_CHARSET);

if ($conn->connect_error) {
    die("<div class='alert alert-danger'>Kết nối MySQL thất bại: " . $conn->connect_error . "</div>");
}

echo "<ul class='list-group mb-4'>";

// ==========================================
// BƯỚC 1: THÊM TÀI KHOẢN NGƯỜI DÙNG
// ==========================================
$password_hashed = password_hash('123456', PASSWORD_BCRYPT); // Mật khẩu chung: 123456

$sql_users = "INSERT IGNORE INTO users (email, password, ho_ten, role, bio, ranking_score) VALUES 
    ('admin@thebunny.vn', '$password_hashed', 'Quản Trị Viên', 'admin', 'Người quản lý hệ thống The Bunny', 9999),
    ('gv.ngoc@thebunny.vn', '$password_hashed', 'Cô Ngọc (Giáo viên)', 'teacher', 'Giáo viên bộ môn Phát triển Web', 500),
    ('tienanh@student.vn', '$password_hashed', 'Nguyễn Tiến Anh', 'student', 'Sinh viên IT K47', 150),
    ('quynhnga@student.vn', '$password_hashed', 'Hồ Quỳnh Nga', 'student', 'Thích học thiết kế UI/UX', 200)
";

if ($conn->query($sql_users) === TRUE) {
    echo "<li class='list-group-item list-group-item-success'>✅ Đã tạo 4 tài khoản mẫu (Mật khẩu: 123456).</li>";
} else {
    echo "<li class='list-group-item list-group-item-danger'>❌ Lỗi tạo Users: " . $conn->error . "</li>";
}

// ==========================================
// BƯỚC 2: THÊM BÀI ĐĂNG (Dòng thời gian)
// ==========================================
// Sử dụng subquery để lấy ID thực tế của user thay vì gán cứng ID (tránh lỗi khóa ngoại)
$sql_posts = "INSERT INTO bai_dang (user_id, noi_dung) VALUES 
    ((SELECT id FROM users WHERE email='gv.ngoc@thebunny.vn'), 'Chào các em, tuần sau chúng ta sẽ có bài kiểm tra thực hành PHP nhé. Các em nhớ ôn tập!'),
    ((SELECT id FROM users WHERE email='tienanh@student.vn'), 'Có ai biết cách cấu hình XAMPP không, chỉ mình với, máy mình cứ báo lỗi cổng 80 :('),
    ((SELECT id FROM users WHERE email='quynhnga@student.vn'), 'Hôm nay học được cách dùng Bootstrap chia cột hay quá mọi người ạ!')
";

if ($conn->query($sql_posts) === TRUE) {
    echo "<li class='list-group-item list-group-item-success'>✅ Đã tạo 3 bài viết mẫu trên Bảng tin.</li>";
} else {
    echo "<li class='list-group-item list-group-item-danger'>❌ Lỗi tạo Bài đăng: " . $conn->error . "</li>";
}

// ==========================================
// BƯỚC 3: THÊM HANG THỎ (Nhóm học tập)
// ==========================================
$sql_groups = "INSERT INTO hang_tho (ten_nhom, mo_ta, nguoi_tao_id) VALUES 
    ('Hội Yêu Thích Lập Trình Web', 'Nơi trao đổi kiến thức về HTML, CSS, PHP và JS', (SELECT id FROM users WHERE email='gv.ngoc@thebunny.vn')),
    ('Góc Cày Đêm', 'Dành cho các cú đêm chạy deadline đồ án', (SELECT id FROM users WHERE email='tienanh@student.vn'))
";

if ($conn->query($sql_groups) === TRUE) {
    echo "<li class='list-group-item list-group-item-success'>✅ Đã tạo 2 Hang thỏ học tập.</li>";
} else {
    echo "<li class='list-group-item list-group-item-danger'>❌ Lỗi tạo Hang thỏ: " . $conn->error . "</li>";
}

// ==========================================
// BƯỚC 4: THÊM PHÒNG THÁCH ĐẤU
// ==========================================
$sql_battles = "INSERT INTO phong_thach_dau (creator_id, status, entry_fee, reward_pool) VALUES 
    ((SELECT id FROM users WHERE email='quynhnga@student.vn'), 'pending', 50, 100)
";

if ($conn->query($sql_battles) === TRUE) {
    echo "<li class='list-group-item list-group-item-success'>✅ Đã tạo 1 phòng Thách đấu (Đang chờ người nhận).</li>";
} else {
    echo "<li class='list-group-item list-group-item-danger'>❌ Lỗi tạo Thách đấu: " . $conn->error . "</li>";
}

echo "</ul>";
$conn->close();

echo "<div class='text-center'>
        <div class='alert alert-info'>
            <strong>Ghi chú:</strong> Bạn có thể đăng nhập ngay bằng email <b>tienanh@student.vn</b> và mật khẩu <b>123456</b>.
        </div>
        <a href='../index.php' class='btn btn-warning'>Quay về Trang Đăng Nhập</a>
      </div>";
echo "</div></div></div></body></html>";
?>