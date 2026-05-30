<?php
// 1. Bắt đầu phiên làm việc hiện tại để có thể thao tác với nó
session_start();

// 2. Xóa toàn bộ dữ liệu của biến $_SESSION (user_id, ho_ten, role,...)
$_SESSION = array();

// 3. Xóa cookie của Session hiện tại trên trình duyệt (Bảo mật tối đa)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Nếu bạn có làm tính năng "Ghi nhớ đăng nhập" (Remember me), xóa luôn cookie đó
if (isset($_COOKIE['remember_token'])) {
    setcookie("remember_token", "", time() - 3600, "/");
}

// 5. Hủy diệt hoàn toàn phiên làm việc trên máy chủ (Server)
session_destroy();

// 6. Điều hướng người dùng bay ra ngoài trang Đăng nhập (Lùi ra 2 cấp thư mục)
header("Location: ../index.php");
exit();
?>