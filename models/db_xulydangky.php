<?php
/**
 * Tệp tin: pages/register/db_xulydangky.php
 * Chức năng: Đăng ký thành viên cho hệ thống The Bunny
 */

require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Lấy và làm sạch dữ liệu
    $username = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Logic ánh xạ vai trò từ Form sang Enum của CSDL
    $raw_role = $_POST['role'] ?? 'student'; 
    $user_type = ($raw_role == 'teacher') ? 'giao_vien' : 'hoc_sinh';

    if (empty($username) || empty($email) || empty($password)) {
        echo "<script>alert('Vui lòng điền đầy đủ thông tin!'); window.history.back();</script>";
        exit();
    }

    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        // 2. Kiểm tra trùng Email
        $stmt_check = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt_check->execute(['email' => $email]);
        
        if ($stmt_check->rowCount() > 0) {
            echo "<script>alert('Email này đã được đăng ký!'); window.history.back();</script>";
            exit();
        }

        // 3. Mã hóa mật khẩu (Lưu vào cột password_hash)
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // 4. Mã hóa mật khẩu
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // 5. Thêm người dùng mới
        $sql_insert = "INSERT INTO users (username, email, password_hash, user_type, status) 
                       VALUES (:username, :email, :password_hash, :user_type, 'Active')";
                       
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->execute([
            'username'      => $username,
            'email'         => $email,
            'password_hash' => $hashed_password, // Đã sửa tên biến cho khớp
            'user_type'     => $user_type
        ]);
        echo "<script>
            alert('Đăng ký tài khoản thành công! Chào mừng bạn đến với The Bunny.');
            window.location.href = '../index.php';
        </script>";

    } catch (PDOException $e) {
        // Ghi log lỗi để dev kiểm tra, không hiện thông báo quá chi tiết cho user
        error_log("Lỗi đăng ký: " . $e->getMessage());
        echo "<script>alert('Có lỗi xảy ra, vui lòng thử lại sau.'); window.history.back();</script>";
    }

} else {
    header("Location: ../index.php");
    exit();
}