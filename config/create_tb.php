<?php
// Nạp cấu hình hệ thống
require_once 'config.php';

echo "<!DOCTYPE html><html lang='vi'><head><meta charset='UTF-8'><title>Khởi tạo CSDL - The Bunny</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'></head><body class='bg-light'><div class='container mt-5'><div class='card shadow'><div class='card-header bg-primary text-white'><h4>Tiến trình khởi tạo Cơ sở dữ liệu</h4></div><div class='card-body'>";

// 1. KẾT NỐI ĐẾN MYSQL SERVER (Chưa chọn DB)
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

if ($conn->connect_error) {
    die("<div class='alert alert-danger'>Kết nối MySQL thất bại: " . $conn->connect_error . "</div>");
}

// 2. TẠO DATABASE NẾU CHƯA TỒN TẠI
$sql_create_db = "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql_create_db) === TRUE) {
    echo "<p class='text-success'>✅ Cơ sở dữ liệu <strong>" . DB_NAME . "</strong> đã sẵn sàng.</p>";
} else {
    die("<div class='alert alert-danger'>Lỗi tạo DB: " . $conn->error . "</div>");
}

// 3. CHỌN DATABASE ĐỂ THAO TÁC
$conn->select_db(DB_NAME);

// 4. KHAI BÁO MẢNG CHỨA CÁC CÂU LỆNH TẠO BẢNG
$tables = [
    // Bảng Người dùng
    "users" => "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            ho_ten VARCHAR(255) NOT NULL,
            role ENUM('student', 'teacher', 'admin') DEFAULT 'student',
            avatar_url VARCHAR(255) DEFAULT NULL,
            cover_url VARCHAR(255) DEFAULT NULL,
            bio TEXT,
            remember_token VARCHAR(64) DEFAULT NULL,
            post_count INT DEFAULT 0,
            ranking_score INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    // Bảng Bài đăng (Liên kết khóa ngoại với users)
    "bai_dang" => "
        CREATE TABLE IF NOT EXISTS bai_dang (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            noi_dung TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    // Bảng Hang thỏ (Nhóm học tập)
    "hang_tho" => "
        CREATE TABLE IF NOT EXISTS hang_tho (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ten_nhom VARCHAR(255) NOT NULL,
            mo_ta TEXT,
            nguoi_tao_id INT NOT NULL,
            cover_url VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (nguoi_tao_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    // Bảng Phòng Thách Đấu
    "phong_thach_dau" => "
        CREATE TABLE IF NOT EXISTS phong_thach_dau (
            id INT AUTO_INCREMENT PRIMARY KEY,
            creator_id INT NOT NULL,
            acceptor_id INT DEFAULT NULL,
            status ENUM('pending', 'accepted', 'completed') DEFAULT 'pending',
            entry_fee INT DEFAULT 0,
            reward_pool INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (acceptor_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    "
];

// 5. THỰC THI LẦN LƯỢT CÁC LỆNH TẠO BẢNG
echo "<ul class='list-group mb-4'>";
foreach ($tables as $table_name => $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "<li class='list-group-item list-group-item-success'>✅ Tạo bảng <strong>{$table_name}</strong> thành công!</li>";
    } else {
        echo "<li class='list-group-item list-group-item-danger'>❌ Lỗi khi tạo bảng <strong>{$table_name}</strong>: " . $conn->error . "</li>";
    }
}
echo "</ul>";

$conn->close();

echo "<div class='text-center'>
        <a href='../index.php' class='btn btn-success'>Quay về Trang Chủ / Đăng Nhập</a>
      </div>";
echo "</div></div></div></body></html>";
?>