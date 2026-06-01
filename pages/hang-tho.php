<?php
ob_start(); // Bật bộ đệm đầu ra để lệnh header() chuyển trang mượt mà

/**
 * =========================================================================================
 * ĐỒ ÁN MÔN HỌC: XÂY DỰNG WEBSITE MẠNG XÃ HỘI HỌC TẬP THE BUNNY
 * TÊN TẬP TIN: hang-tho.php
 * =========================================================================================
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (file_exists('../config/config.php')) {
    require_once '../config/config.php';
} else {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'the_bunny_db');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_CHARSET', 'utf8mb4');
}

// =========================================================================================
// PHẦN 1: HỆ THỐNG HÀM TIỆN ÍCH LÕI
// =========================================================================================

if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
        if (is_null($data)) return "";
        return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('remove_vietnamese_accents')) {
    function remove_vietnamese_accents($str) {
        $unicode = array(
            'a' => 'á|à|ả|ã|ạ|ă|ắ|ặ|ằ|ẳ|ẵ|â|ấ|ầ|ẩ|ẫ|ậ', 'd' => 'đ',
            'e' => 'é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ', 'i' => 'í|ì|ỉ|ĩ|ị',
            'o' => 'ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ', 'u' => 'ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự',
            'y' => 'ý|ỳ|ỷ|ỹ|ỵ', 'A' => 'Á|À|Ả|Ã|Ạ|Ă|Ắ|Ặ|Ằ|Ẳ|Ẵ|Â|Ấ|Ầ|Ẩ|Ẫ|Ậ',
            'D' => 'Đ', 'E' => 'É|È|Ẻ|Ẽ|Ẹ|Ê|Ế|Ề|Ể|Ễ|Ệ', 'I' => 'Í|Ì|Ỉ|Ĩ|Ị',
            'O' => 'Ó|Ò|Ỏ|Õ|Ọ|Ô|Ố|Ồ|Ổ|Ỗ|Ộ|Ơ|Ớ|Ờ|Ở|Ỡ|Ợ', 'U' => 'Ú|Ù|Ủ|Ũ|Ụ|Ư|Ứ|Ừ|Ử|Ữ|Ự',
            'Y' => 'Ý|Ỳ|Ỷ|Ỹ|Ỵ',
        );
        foreach($unicode as $nonUnicode => $uni) {
            $str = preg_replace("/($uni)/i", $nonUnicode, $str);
        }
        return $str;
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// =====================================================================================
// PHẦN 2: KHỞI TẠO BIẾN TRẠNG THÁI
// =====================================================================================

$message_notify      = "";
$message_type        = "success";
$current_user_id     = $_SESSION['user_id'] ?? 1; 
$active_hang_tho_id  = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
$search_keyword      = isset($_GET['search_grp']) ? trim($_GET['search_grp']) : '';

$all_groups_list     = [];
$joined_group_ids    = [];
$active_group_info   = null;
$group_members_list  = [];
$group_matches_list  = [];
$group_docs_list     = [];
$all_bo_de_list      = []; 

$fallback_user_avatar = "../assets/img/default-avatar.jpg";
$stats_xp = 0; 

// =====================================================================================
// PHẦN 3: KẾT NỐI DATABASE BẰNG PDO
// =====================================================================================
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    die("<h3>Lỗi Hệ Thống Nghiêm Trọng</h3><p>Không thể kết nối CSDL: " . $e->getMessage() . "</p>");
}

// =====================================================================================
// PHẦN 4: HỆ THỐNG CONTROLLER - XỬ LÝ POST ACTION (DÙNG ĐÚNG SCHEMA)
// =====================================================================================
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception("Lỗi bảo mật: Token không hợp lệ. Vui lòng thử lại.");
        }

        $action = sanitize_input($_POST['action']);

        // -----------------------------------------------------------------------------
        // [NGHIỆP VỤ 1]: TẠO HANG THỎ MỚI (Bảng: hang_tho, user_hang_tho)
        // -----------------------------------------------------------------------------
        if ($action === 'create_new_hang_tho_premium') {
            $group_name_clean = sanitize_input($_POST['ten_hang_tho'] ?? '');
            if (empty($group_name_clean)) throw new Exception("Tên Hang Thỏ không được bỏ trống.");

            $pdo->beginTransaction();
            try {
                $pdo->prepare("INSERT INTO hang_tho (ten_hang_tho, created_at, updated_at) VALUES (:g, NOW(), NOW())")->execute(['g' => $group_name_clean]);
                $new_gid = $pdo->lastInsertId();
                
                $pdo->prepare("INSERT INTO user_hang_tho (user_id, hang_tho_id, created_at) VALUES (:u, :g, NOW())")->execute(['u' => $current_user_id, 'g' => $new_gid]);
                
                $pdo->commit();
                $message_notify = "Thành lập Hang Thỏ [{$group_name_clean}] thành công rực rỡ!";
                $message_type   = "success";
                $active_hang_tho_id = $new_gid; 
            } catch (Exception $ex) {
                $pdo->rollBack();
                throw new Exception("Hệ thống từ chối tạo nhóm: " . $ex->getMessage());
            }
        }

        // -----------------------------------------------------------------------------
        // [NGHIỆP VỤ 2 & 3]: GIA NHẬP VÀ RỜI HANG THỎ
        // -----------------------------------------------------------------------------
        elseif ($action === 'join_hang_tho_operation') {
            $target_group_id = (int)($_POST['group_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_hang_tho WHERE user_id = :u AND hang_tho_id = :g");
            $stmt->execute(['u' => $current_user_id, 'g' => $target_group_id]);
            
            if ($stmt->fetchColumn() == 0) {
                $pdo->prepare("INSERT INTO user_hang_tho (user_id, hang_tho_id, created_at) VALUES (:u, :g, NOW())")->execute(['u' => $current_user_id, 'g' => $target_group_id]);
                $message_notify = "Chào mừng bạn đã gia nhập mạng lưới tri thức này!";
            }
            $active_hang_tho_id = $target_group_id;
        }

        elseif ($action === 'leave_hang_tho_operation') {
            $target_group_id = (int)($_POST['group_id'] ?? 0);
            $pdo->prepare("DELETE FROM user_hang_tho WHERE user_id = :u AND hang_tho_id = :g")->execute(['u' => $current_user_id, 'g' => $target_group_id]);
            $message_notify = "Bạn đã rời khỏi Hang Thỏ thành công.";
            $message_type   = "warning";
            $active_hang_tho_id = $target_group_id; 
        }

        // -----------------------------------------------------------------------------
        // [NGHIỆP VỤ 4]: GÁN THÁCH ĐẤU 
        // Bảng sử dụng: phong_thach_dau, user_phong_thach_dau, tran_dau
        // -----------------------------------------------------------------------------
        elseif ($action === 'launch_custom_battle_room') {
            $opponent_id = (int)($_POST['opponent_id'] ?? 0);
            $bo_de_id = (int)($_POST['bo_de_id'] ?? 0);
            
            if ($opponent_id === $current_user_id) throw new Exception("Bạn không thể tự thách đấu chính mình.");

            $pdo->beginTransaction();
            try {
                // 1. Tạo phòng thách đấu
                $pdo->prepare("INSERT INTO phong_thach_dau (bo_de_id, created_at) VALUES (?, NOW())")->execute([$bo_de_id]);
                $phong_id = $pdo->lastInsertId();
                
                // 2. Kéo 2 user vào phòng
                $pdo->prepare("INSERT INTO user_phong_thach_dau (user_id, phong_thach_dau_id, created_at) VALUES (?, ?, NOW())")->execute([$current_user_id, $phong_id]);
                $pdo->prepare("INSERT INTO user_phong_thach_dau (user_id, phong_thach_dau_id, created_at) VALUES (?, ?, NOW())")->execute([$opponent_id, $phong_id]);

                // 3. Tạo record trận đấu ở trạng thái Pending
                $pdo->prepare("INSERT INTO tran_dau (phong_thach_dau_id, nguoi_choi_1_id, nguoi_choi_2_id, trang_thai, created_at) VALUES (?, ?, ?, 'Pending', NOW())")->execute([$phong_id, $current_user_id, $opponent_id]);

                $pdo->commit();
                
                // Chuyển hướng sang Sàn đấu (Dùng biến phong_id cho chuẩn Schema cũ)
                header("Location: battle_room.php?phong_id=" . $phong_id);
                exit;
            } catch (Exception $ex) {
                $pdo->rollBack();
                throw new Exception("Lỗi khi tạo trận đấu: " . $ex->getMessage());
            }
        }

        // -----------------------------------------------------------------------------
        // [NGHIỆP VỤ 5]: TỰ HỌC ĐỘC LẬP
        // Bảng sử dụng: phien_luyen_tap
        // -----------------------------------------------------------------------------
        elseif ($action === 'execute_solo_practice_run') {
            $bo_de_practice_id = (int)($_POST['practice_bo_de_id'] ?? 1); 
            
            // Khởi tạo phiên luyện tập cho user
            $pdo->prepare("INSERT INTO phien_luyen_tap (user_id, diem_so, created_at, updated_at) VALUES (?, 0, NOW(), NOW())")->execute([$current_user_id]);
            $phien_id = $pdo->lastInsertId();
            
            // Chuyển hướng (Kèm biến practice=1 để battle_room.php biết mà ẩn các nút PvP)
            header("Location: luyen-tap.php?phien_id=" . $phien_id . "&bo_de_id=" . $bo_de_practice_id . "&practice=1");
            exit;
        }

        // -----------------------------------------------------------------------------
        // [NGHIỆP VỤ 6]: TẢI LÊN TÀI LIỆU
        // -----------------------------------------------------------------------------
        elseif ($action === 'submit_group_document_premium') {
            $clean_doc_title = sanitize_input($_POST['ten_tai_lieu'] ?? 'Tài liệu không xác định');
            
            if (isset($_FILES['file_tai_lieu']) && $_FILES['file_tai_lieu']['error'] === UPLOAD_ERR_OK) {
                $dir = '../uploads/document/';
                if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
                
                $original_name = basename($_FILES['file_tai_lieu']['name']);
                $clean_file_name = preg_replace('/[^a-zA-Z0-9.\-_]/', '', remove_vietnamese_accents($original_name));
                $file = time() . '_' . $clean_file_name;
                $target_path = $dir . $file;
                
                if (move_uploaded_file($_FILES['file_tai_lieu']['tmp_name'], $target_path)) {
                    $doc_name_with_tag = "[HangTho_$active_hang_tho_id] " . $clean_doc_title;
                    $db_file_path = ltrim($target_path, '../'); 
                    
                    $pdo->prepare("INSERT INTO tai_lieu (user_id, ten_tai_lieu, file_url, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())")
                        ->execute([$current_user_id, $doc_name_with_tag, $db_file_path]);
                    
                    $message_notify = "Đã cống hiến tài liệu thành công!";
                    $message_type   = "success";
                } else {
                    throw new Exception("Lỗi hệ thống: Không thể lưu trữ tệp tin.");
                }
            } else {
                throw new Exception("Vui lòng chọn một tệp tin hợp lệ.");
            }
        }

        // PRG Redirect chống spam F5
        if ($message_notify !== "") {
            $safe_msg = urlencode($message_notify);
            $safe_type = urlencode($message_type);
            header("Location: hang-tho.php?group_id={$active_hang_tho_id}&msg={$safe_msg}&type={$safe_type}");
            exit;
        }
    }
} catch (Exception $e) {
    $message_notify = $e->getMessage();
    $message_type   = "danger";
}

if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message_notify = sanitize_input($_GET['msg']);
    $message_type   = sanitize_input($_GET['type']);
}

// =====================================================================================
// PHẦN 5: TRUY VẤN DỮ LIỆU ĐỂ RENDER UI
// =====================================================================================
try {
    // 5.1 KÉO BỘ ĐỀ (Cho form luyện tập/thách đấu)
    $all_bo_de_list = $pdo->query("SELECT id, ten_bo_de FROM bo_de ORDER BY created_at DESC")->fetchAll();

    // 5.2 KÉO DANH SÁCH NHÓM
    $sql_groups = "SELECT g.id, g.ten_hang_tho, (SELECT COUNT(*) FROM user_hang_tho WHERE hang_tho_id = g.id) AS member_count FROM hang_tho g";
    if (!empty($search_keyword)) {
        $stmt = $pdo->prepare($sql_groups . " WHERE g.ten_hang_tho LIKE :kw ORDER BY g.ten_hang_tho ASC");
        $stmt->execute(['kw' => "%$search_keyword%"]);
        $all_groups_list = $stmt->fetchAll();
    } else {
        $all_groups_list = $pdo->query($sql_groups . " ORDER BY g.ten_hang_tho ASC")->fetchAll();
    }

    // 5.3 KIỂM TRA NHÓM ĐÃ VÀO
    $stmt_joined = $pdo->prepare("SELECT hang_tho_id FROM user_hang_tho WHERE user_id = ?");
    $stmt_joined->execute([$current_user_id]);
    $joined_group_ids = $stmt_joined->fetchAll(PDO::FETCH_COLUMN);

    if ($active_hang_tho_id === 0 && count($all_groups_list) > 0) {
        $active_hang_tho_id = $all_groups_list[0]['id'];
    }

    // 5.4 LẤY CHI TIẾT NHÓM ĐANG XEM
    if ($active_hang_tho_id > 0) {
        $stmt_info = $pdo->prepare("SELECT id, ten_hang_tho, created_at FROM hang_tho WHERE id = ?");
        $stmt_info->execute([$active_hang_tho_id]);
        $active_group_info = $stmt_info->fetch();

        if ($active_group_info) {
            
            // Danh bạ đồng môn
            $stmt_mems = $pdo->prepare("
                SELECT u.id, u.username, u.user_type, u.truong_hoc, u.is_online 
                FROM user_hang_tho ug INNER JOIN users u ON ug.user_id = u.id 
                WHERE ug.hang_tho_id = ? ORDER BY u.is_online DESC, u.username ASC
            ");
            $stmt_mems->execute([$active_hang_tho_id]);
            $group_members_list = $stmt_mems->fetchAll();

            // Lịch sử Trận đấu (QUÉT TỪ BẢNG GỐC: tran_dau LỌC THEO THÀNH VIÊN TRONG NHÓM)
            $group_matches_list = $pdo->query("
                SELECT 
                    t.id AS match_id,
                    t.trang_thai, 
                    t.created_at, 
                    t.diem_nguoi_1,
                    t.diem_nguoi_2,
                    u1.username AS host_name, 
                    u2.username AS invitee_name
                FROM tran_dau t
                INNER JOIN users u1 ON t.nguoi_choi_1_id = u1.id
                INNER JOIN users u2 ON t.nguoi_choi_2_id = u2.id
                WHERE t.nguoi_choi_1_id IN (SELECT user_id FROM user_hang_tho WHERE hang_tho_id = $active_hang_tho_id)
                   OR t.nguoi_choi_2_id IN (SELECT user_id FROM user_hang_tho WHERE hang_tho_id = $active_hang_tho_id)
                ORDER BY t.created_at DESC LIMIT 15
            ")->fetchAll();

            // Tài liệu nhóm
            $stmt_docs = $pdo->prepare("
                SELECT t.file_url, t.ten_tai_lieu, t.created_at, u.username 
                FROM tai_lieu t INNER JOIN users u ON t.user_id = u.id 
                WHERE t.ten_tai_lieu LIKE ? ORDER BY t.created_at DESC
            ");
            $stmt_docs->execute(["[HangTho_$active_hang_tho_id]%"]);
            $group_docs_list = $stmt_docs->fetchAll();
        }
    }
} catch (PDOException $e) {
    die("<h3>Lỗi Truy Vấn CSDL</h3><p>" . $e->getMessage() . "</p>");
}

$is_member = false;
if ($active_group_info) {
    $is_member = in_array($active_hang_tho_id, $joined_group_ids);
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($active_group_info['ten_hang_tho'] ?? 'Không gian Hang Thỏ'); ?> - The Bunny</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/hang-tho.css">
    <link href="../assets/css/responsive.css" rel="stylesheet" />
    
    </style>
</head>

<body>

    <div class="position-fixed top-0 end-0 p-3" style="z-index: 9999; margin-top: 60px;">
        <?php if ($message_notify !== ""): ?>
            <div class="toast show align-items-center text-white bg-<?= $message_type === 'success' ? 'success' : ($message_type === 'warning' ? 'warning text-dark' : 'danger') ?> border-0 shadow-lg rounded-4" role="alert">
                <div class="d-flex p-2">
                    <div class="toast-body fw-bold fs-6 d-flex align-items-center gap-2">
                        <i class="fa-solid <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-bell' ?> fs-4"></i> <?= htmlspecialchars($message_notify); ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <nav class="sticky-top shadow-sm bg-white border-bottom" style="z-index: 1020;">
        <?php if (file_exists('../includes/header.php')) include '../includes/header.php'; ?>
    </nav>

    <div class="container-fluid p-0">
        <div class="row g-0">
            
            <div class="col-12 col-md-4 col-xl-3 sidebar-left-panel">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <h5 class="fw-black text-dark m-0 d-flex align-items-center gap-2">
                        <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-3"><i class="fa-solid fa-layer-group"></i></div> Các Hang Thỏ
                    </h5>
                    <button class="btn btn-primary btn-sm fw-bold rounded-pill shadow-sm px-3 py-2 d-flex align-items-center gap-1 hover-lift-effect" data-bs-toggle="modal" data-bs-target="#createNewGroupModal">
                        <i class="fa-solid fa-plus"></i> Tạo Nhóm
                    </button>
                </div>

                <form method="GET" class="mb-4">
                    <div class="input-group shadow-sm rounded-pill overflow-hidden border">
                        <span class="input-group-text border-0 bg-white text-muted ps-4"><i class="fa-solid fa-search"></i></span>
                        <input type="text" class="form-control border-0 bg-white py-2 fw-medium" name="search_grp" value="<?= htmlspecialchars($search_keyword); ?>" placeholder="Tìm tên Hang Thỏ...">
                    </div>
                </form>

                <div>
                    <?php if (count($all_groups_list) > 0): ?>
                        <?php foreach ($all_groups_list as $grp): 
                            $is_active_class = ($grp['id'] === $active_hang_tho_id) ? "is-currently-selected" : "";
                            $is_joined = in_array($grp['id'], $joined_group_ids);
                        ?>
                            <div class="hang-tho-card-item shadow-sm <?= $is_active_class; ?>" onclick="window.location.href='hang-tho.php?group_id=<?= $grp['id']; ?>';">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <strong class="text-dark fs-6 text-truncate" style="max-width: 70%; line-height: 1.4;"><?= htmlspecialchars($grp['ten_hang_tho']); ?></strong>
                                    <?php if ($is_joined): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2 py-1"><i class="fa-solid fa-check me-1"></i>Đã vào</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border rounded-pill px-2 py-1">Khách</span>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top border-light">
                                    <small class="text-muted fw-semibold"><i class="fa-solid fa-users"></i> <?= $grp['member_count']; ?> thành viên</small>
                                    <?php if(!$is_joined): ?>
                                        <form method="POST" class="m-0" onclick="event.stopPropagation();">
                                            <input type="hidden" name="action" value="join_hang_tho_operation">
                                            <input type="hidden" name="group_id" value="<?= $grp['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
                                            <button type="submit" class="btn btn-outline-primary btn-sm rounded-pill fw-bold px-3 py-1">Tham gia</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted"><i class="fa-solid fa-ghost fs-1 mb-3 opacity-25"></i><p class="fw-medium m-0">Không tìm thấy nhóm nào</p></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-12 col-md-8 col-xl-9 content-right-panel">
                
                <?php if ($active_group_info): ?>
                    
                    <div class="card glass-premium-card group-header-banner p-4 p-md-5 mb-4 position-relative overflow-hidden shadow-sm">
                        <i class="fa-solid fa-rabbit position-absolute end-0 top-50 translate-middle-y opacity-10" style="font-size: 14rem; transform: rotate(-15deg);"></i>
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center position-relative z-1 gap-4">
                            <div>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle rounded-pill mb-3 px-3 py-2 fw-bold"><i class="fa-solid fa-globe"></i> Mạng lưới Học thuật</span>
                                <h1 class="fw-black text-dark mb-2" style="font-size: 2.2rem;"><?= htmlspecialchars($active_group_info['ten_hang_tho']); ?></h1>
                                <p class="text-muted fw-medium fs-6 m-0"><i class="fa-regular fa-calendar-plus me-1"></i> Lập ngày: <?= date('d/m/Y', strtotime($active_group_info['created_at'])); ?> • <i class="fa-solid fa-users-viewfinder mx-1"></i> <?= count($group_members_list); ?> thành viên</p>
                            </div>
                            <div>
                                <?php if ($is_member): ?>
                                    <form method="POST" class="m-0" onsubmit="return confirm('Bạn thực sự muốn rời khỏi tổ chức này?');">
                                        <input type="hidden" name="action" value="leave_hang_tho_operation">
                                        <input type="hidden" name="group_id" value="<?= $active_hang_tho_id; ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
                                        <button type="submit" class="btn btn-white text-danger fw-bold rounded-pill px-4 py-2.5 shadow border hover-lift-effect"><i class="fa-solid fa-right-from-bracket"></i> Rời Nhóm</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" class="m-0">
                                        <input type="hidden" name="action" value="join_hang_tho_operation">
                                        <input type="hidden" name="group_id" value="<?= $active_hang_tho_id; ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
                                        <button type="submit" class="btn btn-primary fw-bold rounded-pill px-5 py-2.5 shadow-lg hover-lift-effect fs-5"><i class="fa-solid fa-handshake"></i> Tham gia ngay</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <ul class="nav nav-pills custom-pill-nav mb-4 w-100 overflow-auto flex-nowrap pb-1" id="groupTabs" role="tablist">
                        <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-arena"><i class="fa-solid fa-swords text-danger me-2"></i> Đấu Trường</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-members"><i class="fa-solid fa-address-book text-primary me-2"></i> Sổ Đồng Môn</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-docs"><i class="fa-solid fa-folder-open text-success me-2"></i> Tài Liệu Nhóm</button></li>
                    </ul>

                    <div class="tab-content">
                        
                        <div class="tab-pane fade show active" id="tab-arena">
                            <div class="row g-4 align-items-stretch">
                                
                                <div class="col-12 col-xl-5">
                                    <div class="card glass-premium-card h-100 border-2 border-warning border-opacity-50">
                                        <div class="card-body p-4 p-md-5 d-flex flex-column text-center">
                                            <div class="bg-warning bg-opacity-10 text-warning p-4 rounded-circle d-inline-flex mx-auto mb-4 border border-warning-subtle shadow-sm"><i class="fa-solid fa-brain fs-1"></i></div>
                                            <h4 class="fw-bold text-dark mb-2">Tự Học Độc Lập</h4>
                                            <p class="text-muted fw-medium mb-4 pb-3 border-bottom border-light">Giải đề để nhận XP. Phiên làm việc độc lập.</p>
                                            
                                            <div class="mt-auto">
                                                <?php if ($is_member): ?>
                                                    <form method="POST" class="text-start">
                                                        <input type="hidden" name="action" value="execute_solo_practice_run">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
                                                        <div class="mb-4">
                                                            <label class="form-label fw-bold text-dark"><i class="fa-solid fa-layer-group text-secondary"></i> Chọn ngân hàng đề thi:</label>
                                                            <select name="practice_bo_de_id" class="form-select border-secondary-subtle shadow-sm fw-medium bg-light" required>
                                                                <?php foreach ($all_bo_de_list as $ex): ?>
                                                                    <option value="<?= $ex['id']; ?>"><?= htmlspecialchars($ex['ten_bo_de']); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <button type="submit" class="btn btn-warning w-100 fw-bold rounded-pill text-dark shadow py-3 fs-5 hover-lift-effect"><i class="fa-solid fa-bolt-lightning"></i> Bắt Đầu Phiên Học</button>
                                                    </form>
                                                <?php else: ?>
                                                    <div class="alert alert-secondary border-0 fw-bold rounded-4 m-0 p-4"><i class="fa-solid fa-lock text-muted fs-4 mb-2 d-block"></i>Bạn cần tham gia nhóm để dùng tính năng này.</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-xl-7">
                                    <div class="card glass-premium-card h-100">
                                        <div class="card-body p-4 d-flex flex-column">
                                            <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom gap-3">
                                                <h5 class="fw-bold text-dark m-0"><div class="bg-primary bg-opacity-10 text-primary p-2 rounded-2 d-inline-block me-2"><i class="fa-solid fa-clock-rotate-left"></i></div> Lịch sử Trận đấu</h5>
                                                <?php if ($is_member): ?>
                                                    <button class="btn btn-danger fw-bold rounded-pill px-4 shadow-sm hover-lift-effect" data-bs-toggle="modal" data-bs-target="#modalAssignBattle"><i class="fa-solid fa-swords animate-pulse"></i> Gán Thách Đấu</button>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="table-responsive flex-grow-1">
                                                <table class="table table-hover align-middle table-borderless m-0">
                                                    <thead class="text-uppercase small fw-bold text-muted border-bottom border-light">
                                                        <tr><th>Thách Đấu</th><th class="text-center">Kết Quả</th><th class="text-end">Đối Thủ</th></tr>
                                                    </thead>
                                                    <tbody class="fs-6">
                                                        <?php if (!empty($group_matches_list)): ?>
                                                            <?php foreach ($group_matches_list as $match): ?>
                                                                <tr class="border-bottom border-light hover-bg-light transition">
                                                                    <td class="fw-bold text-dark"><i class="fa-solid fa-user-astronaut text-primary me-2"></i><?= htmlspecialchars($match['host_name']); ?></td>
                                                                    <td class="text-center">
                                                                        <?php if ($match['trang_thai'] === 'Finished'): ?>
                                                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle rounded-pill px-3 py-1 mb-1">Đã kết thúc</span>
                                                                            <div class="fw-black text-danger fs-6"><?= $match['diem_nguoi_1']; ?> - <?= $match['diem_nguoi_2']; ?></div>
                                                                        <?php elseif ($match['trang_thai'] === 'Ongoing'): ?>
                                                                            <span class="badge bg-success text-white rounded-pill px-3 py-1 animate-pulse"><i class="fa-solid fa-fire"></i> Đang đấu</span>
                                                                        <?php else: ?>
                                                                            <span class="badge bg-warning text-dark rounded-pill px-3 py-1 shadow-sm"><i class="fa-solid fa-hourglass-half"></i> Chờ xác nhận</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td class="text-end fw-bold text-dark"><?= htmlspecialchars($match['invitee_name']); ?> <i class="fa-solid fa-user-ninja text-danger ms-2"></i></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <tr><td colspan="3"><div class="text-center py-5"><i class="fa-solid fa-shield-halved fs-1 text-muted opacity-25 mb-3"></i><h6 class="text-dark fw-bold m-0">Sàn đấu yên ắng</h6></div></td></tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="tab-members">
                            <div class="card glass-premium-card p-4 p-md-5">
                                <h3 class="fw-bold text-dark mb-5 border-bottom pb-4"><i class="fa-solid fa-address-book text-success me-2"></i> Danh Bạ Đồng Môn</h3>
                                <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
                                    <?php foreach ($group_members_list as $mem): $is_me = ($mem['id'] === $current_user_id); ?>
                                        <div class="col">
                                            <div class="card border border-secondary-subtle rounded-4 h-100 bg-white hover-lift-effect shadow-sm overflow-hidden text-center">
                                                <div class="w-100 bg-<?= $mem['is_online'] ? 'success' : 'secondary' ?> bg-opacity-25" style="height: 60px;"></div>
                                                <div class="card-body px-4 pb-4 position-relative" style="margin-top: -40px;">
                                                    <div class="position-relative d-inline-block mb-3">
                                                        <img src="<?= $fallback_user_avatar; ?>" class="rounded-circle border border-4 border-white shadow-sm" width="80" height="80">
                                                        <span class="position-absolute bottom-0 end-0 status-indicator-dot <?= $mem['is_online'] ? 'dot-online' : 'dot-offline'; ?> border border-2 border-white" style="width:16px;height:16px; transform: translate(-5px,-5px);"></span>
                                                    </div>
                                                    <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($mem['username']); ?> <?= $is_me ? '<span class="badge bg-primary fs-6">Tôi</span>' : ''; ?></h5>
                                                    <p class="text-muted small fw-medium mb-3"><i class="fa-solid fa-graduation-cap"></i> <?= htmlspecialchars($mem['user_type']); ?></p>
                                                    
                                                    <div class="d-flex gap-2 mt-4 pt-4 border-top">
                                                        <a href="trang-ca-nhan.php?id=<?= $mem['id']; ?>" class="btn btn-light fw-bold rounded-pill flex-grow-1 border shadow-sm"><i class="fa-solid fa-user"></i> Hồ sơ</a>
                                                        <?php if(!$is_me && $is_member): ?>
                                                            <button class="btn btn-danger fw-bold rounded-pill flex-grow-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAssignBattle" onclick="document.getElementById('opponentSelect').value='<?= $mem['id']; ?>';"><i class="fa-solid fa-fire"></i> Đấu</button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="tab-docs">
                            <div class="card glass-premium-card p-4 p-md-5">
                                <div class="d-flex justify-content-between align-items-center mb-5 pb-4 border-bottom gap-4">
                                    <h3 class="fw-bold text-dark m-0"><i class="fa-solid fa-folder-open text-info me-2"></i> Thư Viện Tài Liệu</h3>
                                    <?php if ($is_member): ?>
                                        <button class="btn btn-info text-white fw-bold px-4 py-2 rounded-pill shadow-sm fs-5" data-bs-toggle="modal" data-bs-target="#modalUpload"><i class="fa-solid fa-cloud-arrow-up"></i> Đóng Góp</button>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="row row-cols-1 row-cols-lg-2 g-4">
                                    <?php if (!empty($group_docs_list)): ?>
                                        <?php foreach ($group_docs_list as $doc): ?>
                                            <div class="col">
                                                <div class="card border border-secondary-subtle rounded-4 h-100 hover-lift-effect shadow-sm p-4 d-flex flex-row align-items-center gap-3">
                                                    <div class="bg-danger bg-opacity-10 text-danger p-3 rounded-circle"><i class="fa-solid fa-file-pdf fs-2"></i></div>
                                                    <div class="flex-grow-1 overflow-hidden">
                                                        <h5 class="fw-bold text-dark text-truncate mb-2"><?= htmlspecialchars(str_replace("[HangTho_$active_hang_tho_id] ", "", $doc['ten_tai_lieu'])); ?></h5>
                                                        <small class="text-muted"><i class="fa-solid fa-user-pen"></i> <?= htmlspecialchars($doc['username']); ?> • <?= date('d/m/Y', strtotime($doc['created_at'])); ?></small>
                                                    </div>
                                                    <a href="<?= htmlspecialchars($doc['file_url']); ?>" target="_blank" class="btn btn-outline-info rounded-circle d-flex align-items-center justify-content-center border-2" style="width: 50px; height: 50px;"><i class="fa-solid fa-download fs-5"></i></a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="col-12 text-center py-5"><i class="fa-regular fa-folder-open fs-1 text-info opacity-50 mb-3"></i><h4 class="fw-bold text-dark">Thư viện trống</h4></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                    </div>
                <?php else: ?>
                    <div class="card glass-premium-card p-5 text-center shadow-sm rounded-4 h-100 d-flex flex-column justify-content-center align-items-center border-0" style="min-height: 70vh;">
                        <img src="https://cdn-icons-png.flaticon.com/512/3069/3069172.png" alt="Bunny Mascot" width="150" class="mb-4 opacity-75">
                        <h2 class="fw-bold text-dark mb-3">Khám Phá Vũ Trụ Tri Thức</h2>
                        <p class="text-muted fs-5 mb-5">Chọn Hang Thỏ bên trái hoặc tự lập tổ chức mới.</p>
                        <button class="btn btn-primary btn-lg fw-bold rounded-pill px-5 shadow-lg" data-bs-toggle="modal" data-bs-target="#createNewGroupModal"><i class="fa-solid fa-plus-circle"></i> Khởi Tạo Nhóm</button>
                    </div>
                <?php endif; ?>
                
            </div>
        </div>
    </div>

    <div class="modal fade" id="createNewGroupModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <input type="hidden" name="action" value="create_new_hang_tho_premium">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                <div class="modal-header bg-primary text-white border-0 py-4 px-4">
                    <h4 class="modal-title fw-bold"><i class="fa-solid fa-layer-group"></i> Tạo Hang Thỏ</h4>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <label class="form-label fw-bold">Định danh nhóm <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-lg bg-light fw-bold text-primary rounded-3" name="ten_hang_tho" required placeholder="VD: Hội Ôn Thi Khối A">
                </div>
                <div class="modal-footer bg-light border-top py-3">
                    <button type="button" class="btn btn-white text-muted fw-bold rounded-pill px-4 border" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary fw-bold rounded-pill px-5 shadow">Thành Lập</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="modalUpload" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <input type="hidden" name="action" value="submit_group_document_premium">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                <div class="modal-header bg-info text-white border-0 py-4 px-4">
                    <h4 class="modal-title fw-bold"><i class="fa-solid fa-cloud-arrow-up"></i> Upload Tài Liệu</h4>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <label class="form-label fw-bold">Tên tài liệu <span class="text-danger">*</span></label>
                    <input type="text" class="form-control mb-4 rounded-3" name="ten_tai_lieu" required>
                    <input type="file" class="form-control w-100 shadow-sm border-info text-info fw-bold" name="file_tai_lieu" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx" required>
                </div>
                <div class="modal-footer bg-light border-top py-3">
                    <button type="button" class="btn btn-white text-muted fw-bold rounded-pill px-4 border" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-info text-white fw-bold rounded-pill px-5 shadow">Tải Lên</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="modalAssignBattle" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="modal-content border-danger border-3 shadow-lg rounded-4 overflow-hidden">
                <input type="hidden" name="action" value="launch_custom_battle_room">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                <div class="modal-header bg-danger bg-opacity-10 border-bottom border-danger py-4 px-4">
                    <h4 class="modal-title fw-black text-danger"><i class="fa-solid fa-swords"></i> Gán Sàn Đấu</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark">Chọn Đối Thủ <span class="text-danger">*</span></label>
                        <select name="opponent_id" id="opponentSelect" class="form-select form-select-lg rounded-3 shadow-sm bg-light" required>
                            <option value="" disabled selected>-- Chọn ứng cử viên --</option>
                            <?php foreach($group_members_list as $mem): if($mem['id'] !== $current_user_id): ?>
                                <option value="<?= $mem['id'] ?>"><?= htmlspecialchars($mem['username']) ?></option>
                            <?php endif; endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark">Chọn Bộ Đề Thi <span class="text-danger">*</span></label>
                        <select name="bo_de_id" class="form-select form-select-lg border-secondary-subtle rounded-3 shadow-sm bg-light" required>
                            <?php if(!empty($all_bo_de_list)): ?>
                                <?php foreach($all_bo_de_list as $ex): ?>
                                    <option value="<?= $ex['id'] ?>"><?= htmlspecialchars($ex['ten_bo_de']) ?></option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="1">Đề mẫu mặc định</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top border-danger py-3">
                    <button type="button" class="btn btn-white fw-bold rounded-pill px-4 border" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-danger fw-bold rounded-pill px-5 shadow"><i class="fa-solid fa-fire"></i> Phát Động</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/hang-tho.js"></script>
</body>
</html>