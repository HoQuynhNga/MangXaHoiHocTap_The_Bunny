<?php
// 1. GỌI TỆP CẤU HÌNH HỆ THỐNG
// Lệnh này sẽ tự động nạp kết nối CSDL và khởi tạo Session
require_once './config/config.php';

// 2. KIỂM TRA TRẠNG THÁI ĐĂNG NHẬP
// Nếu người dùng đã có session (đã đăng nhập), điều hướng thẳng vào Bảng tin
if (isset($_SESSION['user_id'])) {
    header("Location:./pages/trang-chu.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Bunny - Mạng Xã Hội Học Tập</title>
    <!-- Tích hợp Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f7f6;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .auth-container {
            max-width: 900px;
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .auth-banner {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 99%, #fecfef 100%);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            text-align: center;
        }
        .auth-banner img {
            width: 120px;
            margin-bottom: 20px;
        }
        .auth-form-wrap {
            padding: 40px;
        }
        .nav-pills .nav-link.active {
            background-color: #ff758c;
        }
        .nav-pills .nav-link {
            color: #495057;
            font-weight: 500;
        }
        .btn-primary-bunny {
            background-color: #ff758c;
            border: none;
        }
        .btn-primary-bunny:hover {
            background-color: #ff526c;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="row auth-container mx-auto">
        <!-- Phần Banner Giới thiệu -->
        <div class="col-md-5 auth-banner d-none d-md-flex">
            <!-- Bạn có thể thay src bằng đường dẫn logo The Bunny của nhóm -->
            <img src="assets/images/logo.png" alt="The Bunny Logo" onerror="this.src='https://cdn-icons-png.flaticon.com/512/3069/3069172.png'">
            <h3>Chào mừng đến với The Bunny!</h3>
            <p class="mt-3">Mạng xã hội học tập dành riêng cho sinh viên. Cùng nhau kết nối, thách đấu và chia sẻ kiến thức.</p>
        </div>

        <!-- Phần Form Đăng nhập / Đăng ký -->
        <div class="col-md-7 auth-form-wrap">
            
            <!-- Tabs chuyển đổi -->
            <ul class="nav nav-pills nav-justified mb-4" id="pills-tab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pills-login-tab" data-bs-toggle="pill" data-bs-target="#pills-login" type="button" role="tab">Đăng Nhập</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pills-register-tab" data-bs-toggle="pill" data-bs-target="#pills-register" type="button" role="tab">Đăng Ký</button>
                </li>
            </ul>

            <div class="tab-content" id="pills-tabContent">
                
                <!-- FORM ĐĂNG NHẬP -->
                <div class="tab-pane fade show active" id="pills-login" role="tabpanel">
                    <!-- Form sẽ gửi dữ liệu POST đến chính trang này hoặc 1 file action riêng -->
                    <form action="./pages/auth/db_xulydangnhap.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="Nhập email của bạn" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mật khẩu</label>
                            <input type="password" name="password" class="form-control" placeholder="Nhập mật khẩu" required>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remember" id="rememberMe">
                                <label class="form-check-label" for="rememberMe">Ghi nhớ đăng nhập</label>
                            </div>
                            <a href="#" class="text-decoration-none" style="color: #ff758c;">Quên mật khẩu?</a>
                        </div>
                        <button type="submit" class="btn btn-primary-bunny w-100 py-2">Đăng Nhập</button>
                    </form>
                </div>

                <!-- FORM ĐĂNG KÝ -->
                <div class="tab-pane fade" id="pills-register" role="tabpanel">
                    <form action="./pages/register/db_xulydangky.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Họ và Tên</label>
                            <input type="text" name="fullname" class="form-control" placeholder="Ví dụ: Nguyễn Văn A" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="Nhập email sinh viên/cá nhân" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mật khẩu</label>
                            <input type="password" name="password" class="form-control" placeholder="Tạo mật khẩu" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Vai trò của bạn</label>
                            <select name="role" class="form-select" required>
                                <option value="student" selected>Học sinh / Sinh viên</option>
                                <option value="teacher">Giáo viên</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary-bunny w-100 py-2">Đăng Ký Tài Khoản</button>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Tích hợp Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>