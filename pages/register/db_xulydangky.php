<?php
// Gọi tệp config.php (Lùi ra 2 cấp thư mục: từ auth -> pages -> gốc)
require_once '../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Lấy dữ liệu từ form index.php gửi sang
    $ho_ten  = $_POST['fullname'] ?? '';
    $email   = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role    = $_POST['role'] ?? 'student'; // Mặc định là student

    // Loại bỏ khoảng trắng thừa ở đầu/cuối
    $ho_ten = trim($ho_ten);
    $email  = trim($email);

    // Kiểm tra dữ liệu rỗng (đề phòng user bypass HTML required)
    if (empty($ho_ten) || empty($email) || empty($password)) {
        echo "<script>alert('Vui lòng điền đầy đủ thông tin!'); window.history.back();</script>";
        exit();
    }

    // 2. Mở kết nối Database
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset(DB_CHARSET);

    if ($conn->connect_error) {
        die("Lỗi kết nối CSDL: " . $conn->connect_error);
    }

    // 3. Kiểm tra xem Email này đã tồn tại trong DB chưa
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt_check->bind_param("s", $email);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        // Nếu tìm thấy >= 1 dòng -> Báo lỗi trùng email
        echo "<script>alert('Email này đã được đăng ký! Vui lòng sử dụng email khác.'); window.history.back();</script>";
        $stmt_check->close();
        $conn->close();
        exit();
    }
    $stmt_check->close();

    // 4. Mã hóa mật khẩu an toàn bằng thuật toán Bcrypt
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // 5. Thêm người dùng mới vào DB
    $stmt_insert = $conn->prepare("INSERT INTO users (email, password, ho_ten, role) VALUES (?, ?, ?, ?)");
    $stmt_insert->bind_param("ssss", $email, $hashed_password, $ho_ten, $role);

    if ($stmt_insert->execute()) {
        // ✅ ĐĂNG KÝ THÀNH CÔNG
        // Hiển thị thông báo và điều hướng quay lại trang index.php để đăng nhập
        echo "<script>
            alert('Đăng ký tài khoản thành công! Chào mừng bạn đến với The Bunny.\\nVui lòng đăng nhập.');
            window.location.href = '../../index.php';
        </script>";
    } else {
        // ❌ Lỗi hệ thống khi Insert
        echo "<script>alert('Có lỗi xảy ra hệ thống. Vui lòng thử lại sau!'); window.history.back();</script>";
    }

    $stmt_insert->close();
    $conn->close();
} else {
    // Chặn ai đó cố tình gõ đường dẫn xulydangky.php lên thanh URL
    header("Location: ../../config/index.php");
    exit();
}
?>