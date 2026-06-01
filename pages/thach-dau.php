<?php
session_start();




// =====================================================================================
// PHẦN 1: KẾT NỐI DATABASE VÀ CHUẨN BỊ BIẾN
// =====================================================================================




require_once '../config/config.php';




// ======================
// BIẾN MẶC ĐỊNH
// ======================




$page_title = "Phòng Practice - The Bunny";




$user_avatar         = "../assets/img/default-avatar.jpg";
$stats_xp            = 0;




$stats_fire = 15;




$current_user_id = $_SESSION['user_id'] ?? 1;
// =====================================================================================
// PHẦN 2: KẾT NỐI DATABASE
// =====================================================================================




try {




    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;




    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];




    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);




} catch (PDOException $e) {




    die("Lỗi kết nối PDO: " . $e->getMessage());
}




// =====================================================================================
// PHẦN 2.5: INLINE AJAX ROUTER (Dynamically queries selected user profiles)
// =====================================================================================
if (isset($_GET['get_user_info'])) {
    header('Content-Type: application/json');
    $userId = (int)$_GET['get_user_info'];
    try {
        $stmt = $pdo->prepare("SELECT id, username, is_online FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if ($user) {
            // Generate logical, deterministic mock stats based on user ID for layout design
            $xp = ($user['id'] * 120) % 1500 + 100;
            $level = floor($xp / 300) + 1;
            $carrots = ($user['id'] * 15) % 100 + 5;
            $status = $user['is_online'] ? "Đang online" : "Ngoại tuyến";
           
            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => htmlspecialchars($user['username']),
                    'avatar' => "../assets/img/default-avatar.jpg",
                    'status' => $status,
                    'xp' => $xp,
                    'level' => $level,
                    'carrots' => $carrots
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy người dùng']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}




// =====================================================================================
// PHẦN 3: LẤY THÔNG TIN USER
// =====================================================================================




$onlineUsers = [];




try
{
    // ====================================================
    // LẤY USER HIỆN TẠI
    // ====================================================




    $sqlUser = "
        SELECT
            username
        FROM users
        WHERE id = :id
    ";




    $stmt = $pdo->prepare($sqlUser);




    $stmt->execute([
        'id' => $current_user_id
    ]);




    if ($row = $stmt->fetch())
    {
        $user_name = $row['username'] ?? $user_name;








    }




    // ====================================================
    // LẤY DANH SÁCH USER ONLINE
    // ====================================================




    $sqlOnline = "
        SELECT
            id,
            username
        FROM users
        WHERE is_online = 1
        AND id != :current_user
    ";




    $stmtOnline = $pdo->prepare($sqlOnline);




    $stmtOnline->execute([
        'current_user' => $current_user_id
    ]);




    $onlineUsers = $stmtOnline->fetchAll();




}
catch(PDOException $e)
{
    echo "Lỗi truy vấn user: " . $e->getMessage();
}
?>












<!DOCTYPE html>
<html lang="vi">
<head>
        <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">




    <!-- Font Awesome    -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">




    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>




    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thách đấu - The Bunny</title>
   
    <link href="../assets/css/root.css" rel="stylesheet">
    <link href="../assets/css/thach_dau.css" rel="stylesheet">
    <link href="../assets/css/responsive.css" rel="stylesheet" />
    <script src="../assets/js/thach_dau.js"></script>
   
</head>
<body style="min-height: 100vh; display: flex; flex-direction: column;">




<nav class="sticky-top" style="z-index: 1030;">
        <?php include '../includes/header.php'; ?>
    </nav>
    <!-- 2-COLUMN TABLE WORKSPACE -->
    <main class="container-fluid flex-grow-1 p-3 overflow-hidden d-flex flex-column" id="main-workspace" style="height: calc(100vh - 128px); min-height: 450px;">
        <div class="row g-3 h-100 align-items-stretch">
           
            <!-- Left Card: Online Users Directory Table -->
            <div class="col-lg-6 h-100 d-flex flex-column">
                <div class="bg-white rounded-4 border shadow-sm d-flex flex-column h-100 overflow-hidden">
                   
                    <div class="pane-header d-flex align-items-center px-4 gap-3 bg-light bg-opacity-50">
                        <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="fa-solid fa-users text-primary"></i> Thành viên đang hoạt động (<?= count($onlineUsers) ?>)
                        </h6>
                    </div>
                   
                    <div class="pane-content p-0 overflow-auto flex-grow-1">
                        <?php if (count($onlineUsers) > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($onlineUsers as $user): ?>
                                    <button type="button"
                                            class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3 border-0 border-bottom hover-bg-light"
                                            onclick="showUserInfo(<?= $user['id'] ?>, this)">
                                        <div class="d-flex align-items-center gap-3">
                                            <img src="../assets/img/default-avatar.jpg" class="rounded-circle border" width="40" height="40" alt="Avatar">
                                            <div>
                                                <h6 class="m-0 fw-bold text-dark"><?= htmlspecialchars($user['username']) ?></h6>
                                                <small class="text-success d-flex align-items-center gap-1">
                                                    <i class="fa-solid fa-circle animate-pulse" style="font-size: 8px;"></i> Đang online
                                                </small>
                                            </div>
                                        </div>
                                        <i class="fa-solid fa-chevron-right text-muted fs-6 me-2"></i>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center p-5 text-muted h-100 d-flex flex-column justify-content-center align-items-center">
                                <i class="fa-solid fa-user-slash fs-2 mb-3 text-muted opacity-50"></i>
                                <p class="m-0 fw-bold">Không có thành viên nào đang online</p>
                            </div>
                        <?php endif; ?>
                    </div>


                </div>
            </div>




            <!-- Right Card: Selected User Info Card (Dynamic AJAX Data) -->
            <div class="col-lg-6 h-100 d-flex flex-column">
                <div class="bg-white rounded-4 border shadow-sm d-flex flex-column h-100 overflow-hidden">
                   
                    <div class="pane-header d-flex align-items-center px-4 gap-3 bg-light bg-opacity-50">
                        <h6 class="m-0 fw-bold text-dark d-flex align-items-center gap-2">
                            <i class="fa-solid fa-id-card text-success"></i> Thông tin chi tiết
                        </h6>
                    </div>
                   
                    <div class="pane-content d-flex flex-column justify-content-center align-items-center flex-grow-1 p-4" id="userInfoPane">
                        <!-- Placeholder State (Before Selection) -->
                        <div class="text-center p-5 text-muted" id="userPlaceholder">
                            <i class="fa-regular fa-id-card text-muted opacity-25 mb-3" style="font-size: 64px;"></i>
                            <h6 class="fw-bold text-dark mb-1">Chưa chọn thành viên</h6>
                            <p class="small text-muted m-0">Hãy bấm vào một thành viên ở danh sách bên trái để xem thông tin chi tiết.</p>
                        </div>
                       
                        <!-- Selected Profile UI Box (Hidden by default) -->
                        <div id="userDetailContent" class="w-100 h-100 d-none flex-column justify-content-between">
                            <div class="text-center mb-4 mt-3">
                                <img id="detailAvatar" src="" class="rounded-circle border mb-3 shadow-sm" width="100" height="100" alt="Avatar">
                                <h4 id="detailUsername" class="fw-bold text-dark m-0"></h4>
                                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1.5 fw-bold mt-2 d-inline-flex align-items-center" id="detailStatus">
                                    <i class="fa-solid fa-circle me-1" style="font-size: 8px;"></i> Online
                                </span>
                            </div>
                           
                            <div class="card border-0 bg-light p-4 rounded-4 mb-4 shadow-sm">
                                <div class="row g-3 text-center">
                                    <div class="col-4 border-end">
                                        <h6 class="text-muted small fw-bold text-uppercase mb-1">Cấp độ</h6>
                                        <h4 class="fw-bold text-primary m-0" id="detailLevel"></h4>
                                    </div>
                                    <div class="col-4 border-end">
                                        <h6 class="text-muted small fw-bold text-uppercase mb-1">XP</h6>
                                        <h4 class="fw-bold text-success m-0" id="detailXp"></h4>
                                    </div>
                                    <div class="col-4">
                                        <h6 class="text-muted small fw-bold text-uppercase mb-1">Cà rốt</h6>
                                        <h4 class="fw-bold text-warning m-0" id="detailCarrots"></h4>
                                    </div>
                                </div>
                            </div>
                           
                            <div class="d-grid mt-auto mb-3">
                                <button class="btn btn-bunny btn-lg py-2.5 rounded-3 fw-bold shadow-sm" onclick="challengeFromProfile()">
                                    <i class="fa-solid fa-bolt me-2 text-warning animate-pulse"></i> Thách Đấu Ngay
                                </button>
                            </div>
                        </div>
                       
                        <!-- AJAX Loading Spinner -->
                        <div id="userLoading" class="text-center d-none p-5">
                            <div class="spinner-border text-primary mb-3" role="status"></div>
                            <p class="text-muted fw-semibold small m-0">Đang tải thông tin...</p>
                        </div>
                    </div>


                </div>
            </div>




        </div>
    </main>




    <div class="offcanvas offcanvas-start border-0 shadow-lg rounded-end-4" tabindex="-1" id="problemListCanvas" style="width: 340px;">
       
        <div class="offcanvas-header border-bottom bg-light bg-opacity-50">
            <h5 class="offcanvas-title fw-bold text-dark d-flex align-items-center gap-2">
                <i class="fa-solid fa-layer-group text-primary"></i> Kho tài liệu
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
       
        <div class="offcanvas-body p-0 d-flex flex-column">
            <div class="p-3 border-bottom">
                <div class="search-bar w-100 bg-light border">
                    <i class="fa-solid fa-search text-muted"></i>
                    <input type="text" placeholder="Tìm tên bài, ID hoặc #tag..." class="bg-transparent border-0 outline-0 w-100">
                </div>
            </div>








            <div class="accordion accordion-flush flex-grow-1 overflow-auto" id="categoryAccordion">
               
                <div class="accordion-item border-bottom">
                    <h2 class="accordion-header">
                        <button class="accordion-button fw-bold text-dark bg-white" type="button" data-bs-toggle="collapse" data-bs-target="#cat-vanhoc">
                            <i class="fa-solid fa-book-open text-primary me-2"></i> Văn học (12)
                        </button>
                    </h2>
                    <div id="cat-vanhoc" class="accordion-collapse collapse show" data-bs-parent="#categoryAccordion">
                        <div class="list-group list-group-flush">
                            <a href="#" class="list-group-item list-group-item-action active bg-primary bg-opacity-10 text-primary border-0 fw-bold border-start border-4 border-primary px-4">
                                1. Phân tích Les Misérables
                            </a>
                            <a href="#" class="list-group-item list-group-item-action border-0 text-muted px-4 fw-medium hover-bg-light">
                                2. Thông điệp Chinh phụ ngâm
                            </a>
                            <a href="#" class="list-group-item list-group-item-action border-0 text-muted px-4 fw-medium hover-bg-light">
                                3. Nghệ thuật vị nhân sinh
                            </a>
                        </div>
                    </div>
                </div>








                <div class="accordion-item border-bottom">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold text-dark bg-white" type="button" data-bs-toggle="collapse" data-bs-target="#cat-vatly">
                            <i class="fa-solid fa-atom text-success me-2"></i> Vật Lý 9 (45)
                        </button>
                    </h2>
                    <div id="cat-vatly" class="accordion-collapse collapse" data-bs-parent="#categoryAccordion">
                        <div class="list-group list-group-flush">
                            <a href="#" class="list-group-item list-group-item-action border-0 text-muted px-4 fw-medium d-flex justify-content-between align-items-center">
                                <span>1. Điện từ học (Cơ bản)</span>
                                <i class="fa-solid fa-circle-check text-success small"></i>
                            </a>
                            <a href="#" class="list-group-item list-group-item-action border-0 text-muted px-4 fw-medium">
                                2. Bài tập Quang học
                            </a>
                            <a href="#" class="list-group-item list-group-item-action border-0 text-muted px-4 fw-medium d-flex justify-content-between align-items-center">
                                <span>3. Đề thi thử HK2</span>
                                <i class="fa-solid fa-lock text-warning small"></i>
                            </a>
                        </div>
                    </div>
                </div>








                <div class="accordion-item border-bottom">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold text-dark bg-white" type="button" data-bs-toggle="collapse" data-bs-target="#cat-kinhdoanh">
                            <i class="fa-solid fa-chart-line text-warning me-2"></i> Kinh doanh (8)
                        </button>
                    </h2>
                    <div id="cat-kinhdoanh" class="accordion-collapse collapse" data-bs-parent="#categoryAccordion">
                        <div class="list-group list-group-flush">
                            <a href="#" class="list-group-item list-group-item-action border-0 text-muted px-4 fw-medium">
                                1. Bài toán CLV
                            </a>
                            <a href="#" class="list-group-item list-group-item-action border-0 text-muted px-4 fw-medium">
                                2. Tối ưu Hybrid B2C & C2C
                            </a>
                        </div>
                    </div>
                </div>








            </div>
        </div>
    </div>








    <div class="modal fade" id="inviteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-plus text-primary me-2"></i>Mời bạn vào phòng Practice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="p-3 border-bottom">
                        <input type="text" class="form-control bg-light border-0" placeholder="Tìm kiếm tên bạn bè...">
                    </div>
                    <div class="list-group list-group-flush" style="max-height: 300px; overflow-y: auto;">
                        <?php if(count($onlineUsers) > 0): ?>








                    <?php foreach($onlineUsers as $user): ?>








                        <div class="list-group-item d-flex justify-content-between align-items-center p-3 border-0">








                            <div class="d-flex align-items-center gap-3">








                                <img src="https://i.pravatar.cc/150?img=9"
                                    class="rounded-circle"
                                    width="40">








                                <div>
                                    <h6 class="m-0 fw-bold">
                                        <?= htmlspecialchars($user['username']) ?>
                                    </h6>








                                    <small class="text-success">
                                        <i class="fa-solid fa-circle"
                                        style="font-size:8px;"></i>
                                        Đang online
                                    </small>
                                </div>








                            </div>








                            <button
                                class="btn btn-sm btn-outline-primary fw-bold rounded-pill px-3"
                                onclick="inviteUser(<?= $user['id'] ?>)">
                                Mời
                            </button>








                        </div>








                    <?php endforeach; ?>








                <?php else: ?>








                    <div class="text-center p-4 text-muted">
                        Không có người dùng nào đang online
                    </div>








                <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>








    <div class="modal fade" id="battleModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-warning border border-2 rounded-4">
                <div class="modal-header bg-warning bg-opacity-10 border-warning">
                    <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-bolt text-warning me-2"></i>Tạo Thách Đấu Trực Tiếp</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-4">Biến phòng luyện tập này thành sàn đấu gay cấn. 10 câu hỏi theo danh mục hiện tại.</p>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Đối thủ (Trong phòng)</label>
                            <label class="form-label fw-bold">
                                Bộ đề
                            </label>








                            <select
                                id="exam_set_id"
                                class="form-select"
                            >








                                <option value="1">
                                    Bộ đề Tin học
                                </option>








                                <option value="2">
                                    Bộ đề Toán
                                </option>








                                <option value="3">
                                    Bộ đề Tiếng Anh
                                </option>








                            </select>
                        <select
                            id="opponent_id"
                            class="form-select bg-light border-0 fw-bold"
                        >
                            <?php foreach($onlineUsers as $user): ?>








                                <option value="<?= $user['id'] ?>">
                                    <?= htmlspecialchars($user['username']) ?>
                                </option>








                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="alert alert-info border-0 bg-info bg-opacity-10 d-flex gap-3 m-0 rounded-3">
                        <i class="fa-solid fa-circle-info mt-1 text-info"></i>
                        <small class="text-dark fw-medium">Khi bắt đầu, đồng hồ sẽ đếm ngược 15 phút. Người nộp bài đúng và nhanh nhất sẽ giành toàn bộ Cà rốt.</small>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light fw-bold rounded-3" data-bs-dismiss="modal">Hủy</button>
                    <button type="button" class="btn btn-warning fw-bold px-4 rounded-3" onclick="startBattle()">Chiến luôn <i class="fa-solid fa-fire text-danger ms-1"></i></button>
                </div>
            </div>
        </div>
    </div>








    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1055">
        <div id="systemToast" class="toast align-items-center text-bg-dark border-0 rounded-3" role="alert">
            <div class="d-flex">
                <div class="toast-body fw-bold" id="toastMessage"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>
    <!-- UPDATED INVITATION MODAL -->
    <div class="modal fade" id="battleInviteModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold">
                        <i class="fa-solid fa-bell text-warning me-2"></i>Thư mời thách đấu
                    </h5>
                </div>
                <div class="modal-body p-4">
                    <p id="inviteText" class="fs-5 m-0 text-center"></p>
                </div>
                <div class="modal-footer border-0 d-flex justify-content-center gap-3 pb-4">
                    <button
                        class="btn btn-light border fw-bold px-4 rounded-pill"
                        onclick="declineInvite()">
                        Từ chối
                    </button>
                    <button
                        class="btn btn-warning fw-bold px-4 rounded-pill"
                        onclick="acceptInvite()">
                        Chấp nhận
                    </button>
                </div>
            </div>
        </div>
    </div>




    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>