<?php
<<<<<<< HEAD:pages/auth/db_xulydangnhap.php
require_once '../../config/config.php';
=======
// Gọi tệp config.php (Lùi ra 2 cấp thư mục: từ auth -> pages -> gốc)
require_once '../config/config.php';
session_start();
>>>>>>> origin/main:models/db_xulydangnhap.php

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset(DB_CHARSET);

    if ($conn->connect_error) {
        die('Lỗi kết nối CSDL: ' . $conn->connect_error);
    }

    $stmt = $conn->prepare(
        'SELECT id, username, password_hash, user_type, status FROM users WHERE email = ? LIMIT 1'
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
<<<<<<< HEAD:pages/auth/db_xulydangnhap.php

        if ($user['status'] === 'Banned') {
            echo "<script>alert('Tài khoản đã bị khóa. Liên hệ quản trị viên.'); window.history.back();</script>";
            exit;
=======
        
        // Kiểm tra mật khẩu (So sánh mk nhập vào với chuỗi băm Bcrypt trong DB)
        if (password_verify($password, $user['password_hash'])) {
            // ĐĂNG NHẬP THÀNH CÔNG: Lưu định danh vào Session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['user_type']    = $user['user_type'];
            if ($user['user_type'] === 'quan_tri_vien') {
                header("Location: ../pages/admin.php");
            } else {
                header("Location: ../pages/trang-chu.php"); 
            }
            exit();
        } else {
            // Sai mật khẩu: Bật thông báo và đẩy về trang cũ
            echo "<script>alert('Mật khẩu không chính xác!'); window.history.back();</script>";
>>>>>>> origin/main:models/db_xulydangnhap.php
        }

        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_type'] = $user['user_type'];

            if ($user['user_type'] === 'quan_tri_vien') {
                header('Location: ../admin/index.php');
            } else {
                header('Location: ../trang-chu.php');
            }
            exit;
        }

        echo "<script>alert('Mật khẩu không chính xác!'); window.history.back();</script>";
    } else {
        echo "<script>alert('Tài khoản Email không tồn tại!'); window.history.back();</script>";
    }

    $stmt->close();
    $conn->close();
} else {
<<<<<<< HEAD:pages/auth/db_xulydangnhap.php
    header('Location: ../../index.php');
    exit;
}
=======
    // Chặn ai đó cố tình gõ đường dẫn login_action.php lên URL
    header("Location: ../index.php");
    exit();
}
?>
>>>>>>> origin/main:models/db_xulydangnhap.php
