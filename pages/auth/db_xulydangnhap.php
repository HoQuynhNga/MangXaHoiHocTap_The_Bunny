<?php
require_once '../../config/config.php';

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

        if ($user['status'] === 'Banned') {
            echo "<script>alert('Tài khoản đã bị khóa. Liên hệ quản trị viên.'); window.history.back();</script>";
            exit;
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
    header('Location: ../../index.php');
    exit;
}
