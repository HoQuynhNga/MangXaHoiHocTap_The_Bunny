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
    <title>The Bunny - Cổng Đăng Nhập</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/root.css">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        
        .auth-container {
            max-width: 1000px;
            background: #ffffff;
            border-radius: 1.5rem;
            box-shadow: 0 1rem 3rem rgba(0,0,0,0.08);
            overflow: hidden;
            margin: 2rem;
        }
        
        .auth-banner {
            background: var(--bunny-primary);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 3rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        /* Hiệu ứng mờ nền cho Banner */
        .auth-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 10%, transparent 60%);
            transform: rotate(30deg);
            pointer-events: none;
        }

        .auth-banner-icon {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            color: #ffc107; /* Màu cà rốt (warning) đặc trưng của The Bunny */
            filter: drop-shadow(0 0.5rem 1rem rgba(0,0,0,0.2));
        }

        .auth-form-wrap {
            padding: 3.5rem;
        }

        /* Tùy chỉnh Tabs */
        .nav-pills.custom-tabs {
            background-color: #f1f3f5;
            padding: 0.5rem;
            border-radius: 50rem;
        }
        
        .nav-pills.custom-tabs .nav-link {
            color: #6c757d;
            font-weight: 700;
            border-radius: 50rem;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
        }
        
        .nav-pills.custom-tabs .nav-link.active {
            background-color: #ffffff;
            color: #0d6efd;
            box-shadow: 0 0.25rem 0.5rem rgba(0,0,0,0.05);
        }

        /* Tùy chỉnh Input Form */
        .form-control:focus, .form-select:focus {
            box-shadow: none;
            border-color: #0d6efd;
        }
        
        .input-group-text {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="row auth-container mx-auto">
        
        <div class="col-lg-5 auth-banner d-none d-lg-flex">
            <i class="fa-solid fa-carrot auth-banner-icon"></i>
            <h2 class="fw-bold mb-3 text-white">The Bunny</h2>
            <p class="fs-6 text-white-50 mb-0 px-3">
                Mạng xã hội học tập hệ sinh thái mở. Nơi kết nối, chia sẻ tài liệu và quản lý tiến trình học tập cá nhân toàn diện.
            </p>
        </div>

        <div class="col-lg-7 auth-form-wrap bg-white">
            
            <div class="text-center d-lg-none mb-4">
                <i class="fa-solid fa-carrot fs-1 text-warning mb-2"></i>
                <h3 class="fw-bold text-dark">The Bunny</h3>
            </div>

            <ul class="nav nav-pills custom-tabs mb-5 d-flex" id="pills-tab" role="tablist">
                <li class="nav-item flex-fill text-center" role="presentation">
                    <button class="nav-link w-100 active" id="pills-login-tab" data-bs-toggle="pill" data-bs-target="#pills-login" type="button" role="tab">
                        <i class="fa-solid fa-right-to-bracket me-1"></i> Đăng Nhập
                    </button>
                </li>
                <li class="nav-item flex-fill text-center" role="presentation">
                    <button class="nav-link w-100" id="pills-register-tab" data-bs-toggle="pill" data-bs-target="#pills-register" type="button" role="tab">
                        <i class="fa-solid fa-user-plus me-1"></i> Đăng kí
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="pills-tabContent">
                
                <div class="tab-pane fade show active" id="pills-login" role="tabpanel">
                    <form action="./models/db_xulydangnhap.php" method="POST">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold text-muted small text-uppercase letter-spacing-1">Email truy cập</label>
                            <div class="input-group shadow-sm rounded-3 overflow-hidden border border-secondary-subtle">
                                <span class="input-group-text border-0"><i class="fa-solid fa-envelope text-muted"></i></span>
                                <input type="email" name="email" class="form-control border-0 py-2 px-3 fw-medium" placeholder="Nhập địa chỉ email..." required>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold text-muted small text-uppercase letter-spacing-1">Mật mã bảo mật</label>
                            <div class="input-group shadow-sm rounded-3 overflow-hidden border border-secondary-subtle">
                                <span class="input-group-text border-0"><i class="fa-solid fa-lock text-muted"></i></span>
                                <input type="password" name="password" class="form-control border-0 py-2 px-3 fw-medium" placeholder="Nhập mật khẩu..." required>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-5">
                            <div class="form-check">
                                <input class="form-check-input shadow-sm" type="checkbox" name="remember" id="rememberMe">
                                <label class="form-check-label text-muted fw-medium" for="rememberMe">Ghi nhớ đăng nhập</label>
                            </div>
                            <a href="#" class="text-decoration-none fw-bold text-primary">Quên mật khẩu?</a>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow-sm fs-5">
                            Truy cập hệ thống <i class="fa-solid fa-arrow-right ms-2"></i>
                        </button>
                    </form>
                </div>

                <div class="tab-pane fade" id="pills-register" role="tabpanel">
                    <form action="./models/db_xulydangky.php" method="POST">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold text-muted small text-uppercase letter-spacing-1">Định danh Họ Tên</label>
                            <div class="input-group shadow-sm rounded-3 overflow-hidden border border-secondary-subtle">
                                <span class="input-group-text border-0"><i class="fa-solid fa-id-card text-muted"></i></span>
                                <input type="text" name="fullname" class="form-control border-0 py-2 px-3 fw-medium" placeholder="Ví dụ: Nguyễn Văn A" required>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold text-muted small text-uppercase letter-spacing-1">Email hệ thống</label>
                            <div class="input-group shadow-sm rounded-3 overflow-hidden border border-secondary-subtle">
                                <span class="input-group-text border-0"><i class="fa-solid fa-at text-muted"></i></span>
                                <input type="email" name="email" class="form-control border-0 py-2 px-3 fw-medium" placeholder="Nhập email sinh viên hoặc cá nhân..." required>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold text-muted small text-uppercase letter-spacing-1">Khởi tạo mật khẩu</label>
                            <div class="input-group shadow-sm rounded-3 overflow-hidden border border-secondary-subtle">
                                <span class="input-group-text border-0"><i class="fa-solid fa-key text-muted"></i></span>
                                <input type="password" name="password" class="form-control border-0 py-2 px-3 fw-medium" placeholder="Thiết lập mật khẩu an toàn..." required>
                            </div>
                        </div>
                        
                        <div class="mb-5">
                            <label class="form-label fw-bold text-muted small text-uppercase letter-spacing-1">Phân quyền vai trò</label>
                            <div class="input-group shadow-sm rounded-3 overflow-hidden border border-secondary-subtle">
                                <span class="input-group-text border-0"><i class="fa-solid fa-user-graduate text-muted"></i></span>
                                <select name="role" class="form-select border-0 py-2 px-3 fw-medium text-dark" required>
                                    <option value="student" selected>Tôi là Học sinh / Sinh viên</option>
                                    <option value="teacher">Tôi là Giáo viên / Giảng viên</option>
                                </select>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100 py-3 rounded-pill fw-bold shadow-sm fs-5">
                            <i class="fa-solid fa-user-check me-2"></i> Kích hoạt Tài khoản
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>