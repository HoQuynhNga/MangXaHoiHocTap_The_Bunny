<?php
// ==========================================
// CẤU HÌNH CƠ SỞ DỮ LIỆU (XAMPP LOCALHOST)
// ==========================================

// XAMPP thường sử dụng 'localhost' làm địa chỉ máy chủ mặc định
define('DB_HOST', 'localhost'); 

// Tên Database bạn đã tạo trong http://localhost/phpmyadmin/
define('DB_NAME', 'TheBunny_Nhom10'); 

// Tài khoản mặc định của MySQL trên XAMPP luôn là 'root'
define('DB_USER', 'root'); 

// Mật khẩu mặc định của XAMPP luôn để trống
define('DB_PASS', ''); 

// Chuẩn mã hóa hỗ trợ đầy đủ tiếng Việt có dấu và Emoji
define('DB_CHARSET', 'utf8mb4'); 


// ==========================================
// CẤU HÌNH HỆ THỐNG THE BUNNY
// ==========================================

define('DEFAULT_USER_ID', 1);

// Bật chế độ hiển thị lỗi (Rất cần thiết khi code trên Localhost để tìm bug)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Thiết lập múi giờ mặc định cho hệ thống (Tránh lệch giờ khi đăng bài, nhắn tin)
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Khởi tạo Session nếu chưa có (Bắt buộc cho hệ thống đăng nhập)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>