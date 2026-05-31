<?php
// 1. NẠP CẤU HÌNH VÀ KIỂM TRA QUYỀN TRUY CẬP
require_once '../config/config.php'; 

// Kiểm tra: Nếu chưa đăng nhập HOẶC vai trò không phải là 'admin' -> Đuổi ra ngoài
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'quan_tri_vien') {
    echo "<script>
        alert('LỖI BẢO MẬT: Bạn không có quyền truy cập vào khu vực Quản trị!');
        window.location.href = '../index.php';
    </script>";
    exit();
}

// 2. KHỞI TẠO KẾT NỐI PDO (Chuẩn an toàn mới)
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    die("Lỗi kết nối CSDL: " . $e->getMessage());
}

// 3. TRUY VẤN DỮ LIỆU THỐNG KÊ (DASHBOARD METRICS)
try {
    // Đếm tổng quan
    $total_users   = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_posts   = $pdo->query("SELECT COUNT(*) FROM bai_dang")->fetchColumn();
    $total_groups  = $pdo->query("SELECT COUNT(*) FROM hang_tho")->fetchColumn();
    $total_battles = $pdo->query("SELECT COUNT(*) FROM phong_thach_dau")->fetchColumn();

    // Lấy danh sách 5 người dùng mới nhất
    $recent_users = $pdo->query("SELECT id, username, email, user_type, created_at FROM users ORDER BY id DESC LIMIT 5")->fetchAll();
    
    // Lấy 5 bài viết mới nhất để kiểm duyệt
    $recent_posts = $pdo->query("
        SELECT b.id, b.noi_dung, b.created_at, u.username 
        FROM bai_dang b 
        JOIN users u ON b.user_id = u.id 
        ORDER BY b.id DESC LIMIT 5
    ")->fetchAll();

} catch (PDOException $e) {
    die("Lỗi truy vấn: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - The Bunny</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bunny-dark: #2C3E50;
            --bunny-admin: #E74C3C;
        }
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        /* Sidebar Styles */
        .sidebar {
            min-height: 100vh;
            background-color: var(--bunny-dark);
            color: white;
            padding-top: 20px;
        }
        .sidebar a {
            color: #bdc3c7;
            text-decoration: none;
            padding: 12px 20px;
            display: block;
            font-weight: 500;
            transition: 0.3s;
        }
        .sidebar a:hover, .sidebar a.active {
            background-color: #34495e;
            color: white;
            border-left: 4px solid var(--bunny-admin);
        }
        .sidebar .logo {
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 1px solid #34495e;
            margin-bottom: 20px;
            color: white;
        }
        /* Dashboard Cards */
        .stat-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .icon-box {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar d-none d-md-block">
            <div class="logo">
                <i class="fa-solid fa-carrot text-warning"></i> Admin Panel
            </div>
            <a href="admin_dashboard.php" class="active"><i class="fa-solid fa-gauge me-2"></i> Tổng quan</a>
            <a href="#"><i class="fa-solid fa-users me-2"></i> Quản lý Người dùng</a>
            <a href="#"><i class="fa-solid fa-signs-post me-2"></i> Kiểm duyệt Bài viết</a>
            <a href="#"><i class="fa-solid fa-layer-group me-2"></i> Quản lý Hang thỏ</a>
            <a href="#"><i class="fa-solid fa-medal me-2"></i> Quản lý Huy hiệu</a>
            <a href="#"><i class="fa-solid fa-gear me-2"></i> Cài đặt Hệ thống</a>
            
            <div class="mt-5">
                <a href="../models/db_xulydangxuat.php" class="text-danger"><i class="fa-solid fa-right-from-bracket me-2"></i> Đăng xuất</a>
            </div>
        </div>

        <div class="col-md-10 p-4">
            
            <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                <h3 class="fw-bold">Bảng điều khiển (Dashboard)</h3>
                <div class="d-flex align-items-center gap-3">
                    <span class="badge bg-danger px-3 py-2"><i class="fa-solid fa-shield-halved me-1"></i> Quyền: Quản Trị Viên</span>
                    <img src="https://ui-avatars.com/api/?name=Admin&background=E74C3C&color=fff" class="rounded-circle" width="40" alt="Admin">
                    <span class="fw-bold"><?php echo $_SESSION['username']; ?></span>
                </div>
            </div>

            <div class="row g-4 mb-5">
                <div class="col-md-3">
                    <div class="card stat-card h-100 p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1 fw-bold">Tổng Người Dùng</p>
                                <h2 class="fw-bold m-0"><?= number_format($total_users) ?></h2>
                            </div>
                            <div class="icon-box bg-primary"><i class="fa-solid fa-users"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card h-100 p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1 fw-bold">Bài Đăng</p>
                                <h2 class="fw-bold m-0"><?= number_format($total_posts) ?></h2>
                            </div>
                            <div class="icon-box bg-success"><i class="fa-solid fa-pen-to-square"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card h-100 p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1 fw-bold">Hang Thỏ</p>
                                <h2 class="fw-bold m-0"><?= number_format($total_groups) ?></h2>
                            </div>
                            <div class="icon-box bg-warning"><i class="fa-solid fa-layer-group"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card h-100 p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1 fw-bold">Trận Thách Đấu</p>
                                <h2 class="fw-bold m-0"><?= number_format($total_battles) ?></h2>
                            </div>
                            <div class="icon-box bg-danger"><i class="fa-solid fa-khanda"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card stat-card h-100">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                            <h6 class="fw-bold m-0">Người dùng mới đăng ký</h6>
                            <button class="btn btn-sm btn-outline-primary">Xem tất cả</button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle m-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3">ID</th>
                                            <th>Họ và Tên</th>
                                            <th>Vai trò</th>
                                            <th>Ngày tạo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($recent_users as $u): ?>
                                        <tr>
                                            <td class="ps-3 fw-bold">#<?= $u['id'] ?></td>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($u['username']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($u['email']) ?></small>
                                            </td>
                                            <td>
                                                <?php 
                                                    if($u['user_type'] == 'quan_tri_vien') echo '<span class="badge bg-danger">Admin</span>';
                                                    elseif($u['user_type'] == 'giao_vien') echo '<span class="badge bg-info">Giáo viên</span>';
                                                    else echo '<span class="badge bg-secondary">Học sinh</span>';
                                                ?>
                                            </td>
                                            <td><small><?= date('d/m/Y', strtotime($u['created_at'])) ?></small></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card stat-card h-100">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                            <h6 class="fw-bold m-0">Bài viết chờ duyệt / Mới nhất</h6>
                            <button class="btn btn-sm btn-outline-success">Đi tới Quản lý nội dung</button>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <?php foreach($recent_posts as $post): ?>
                                <li class="list-group-item px-0 py-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <strong class="text-primary"><?= htmlspecialchars($post['username']) ?></strong>
                                        <small class="text-muted"><?= date('H:i d/m', strtotime($post['created_at'])) ?></small>
                                    </div>
                                    <p class="m-0 text-truncate" style="max-width: 90%;"><?= htmlspecialchars($post['noi_dung']) ?></p>
                                    <div class="mt-2">
                                        <button class="btn btn-sm btn-light border text-danger"><i class="fa-solid fa-trash"></i> Xóa</button>
                                        <button class="btn btn-sm btn-light border text-warning"><i class="fa-solid fa-eye-slash"></i> Ẩn</button>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>