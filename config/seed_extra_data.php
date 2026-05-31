<?php
/**
 * Bổ sung dữ liệu mẫu cho The Bunny — chạy nhiều lần an toàn (INSERT IGNORE / kiểm tra tồn tại).
 * Truy cập: http://localhost:8888/config/seed_extra_data.php
 * Hoặc CLI: php config/seed_extra_data.php
 */
require_once __DIR__ . '/config.php';

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    echo "<!DOCTYPE html><html lang='vi'><head><meta charset='UTF-8'><title>Seed Extra Data</title>";
    echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'></head>";
    echo "<body class='bg-light'><div class='container py-5'><div class='card shadow'><div class='card-header bg-success text-white'>";
    echo "<h4 class='mb-0'>Bổ sung dữ liệu mẫu — The Bunny</h4></div><div class='card-body'><ul class='list-group mb-3'>";
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset(DB_CHARSET);

if ($conn->connect_error) {
    die($isCli ? "DB error: {$conn->connect_error}\n" : "<li class='list-group-item list-group-item-danger'>Kết nối thất bại</li>");
}

$log = [];
$passwordHash = password_hash('123456', PASSWORD_BCRYPT);

function seedLog(array &$log, string $msg, bool $ok = true): void
{
    $log[] = ['ok' => $ok, 'msg' => $msg];
}

function seedUserId(mysqli $conn, string $email): ?int
{
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? (int) $row['id'] : null;
}

function seedPostIdByContent(mysqli $conn, string $content): ?int
{
    $stmt = $conn->prepare('SELECT id FROM bai_dang WHERE noi_dung = ? LIMIT 1');
    $stmt->bind_param('s', $content);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? (int) $row['id'] : null;
}

// ─── 1. THÊM NGƯỜI DÙNG ─────────────────────────────────────────────────────
$newUsers = [
    ['baoviet',   'baoviet@ueh.edu.vn',   'sinh_vien',     null,                    'UEH', 'SV31231025973'],
    ['buidat',    'dat.bui@ueh.edu.vn',   'sinh_vien',     null,                    'UEH', 'SV31221025667'],
    ['hoangthanh','hoang.thanh@ueh.edu.vn','giao_vien',     'THPT Chuyên Lê Hồng Phong', null, 'GV002'],
    ['linhpham',  'linh.pham@ueh.edu.vn', 'sinh_vien',     null,                    'UEH', null],
    ['khoatran',  'khoa.tran@student.vn', 'hoc_sinh',      'THPT Nguyễn Thị Minh Khai', null, null],
    ['mainguyen', 'mai.nguyen@ueh.edu.vn','sinh_vien',     null,                    'UEH', null],
    ['duyle',     'duy.le@ueh.edu.vn',    'sinh_vien',     null,                    'UEH', null],
    ['thuvo',     'thu.vo@ueh.edu.vn',    'sinh_vien',     null,                    'UEH', null],
    ['anhkhoa',   'anhkhoa@ueh.edu.vn',   'sinh_vien',     null,                    'UEH', null],
    ['mydang',    'my.dang@student.vn',   'hoc_sinh',      'THPT Lê Quý Đôn',       null, null],
];

$profiles = [
    'baoviet@ueh.edu.vn'    => 'Lê Viết Bảo — Frontend & UI',
    'dat.bui@ueh.edu.vn'    => 'Bùi Tấn Đạt — Backend PHP/MySQL',
    'hoang.thanh@ueh.edu.vn'=> 'TS. Đặng Ngọc Hoàng Thành — GV PTUD Web',
    'linh.pham@ueh.edu.vn'  => 'Phạm Linh — Thiết kế UX',
    'khoa.tran@student.vn'  => 'Trần Minh Khoa — Lớp 12 Tin',
    'mai.nguyen@ueh.edu.vn' => 'Nguyễn Mai — Marketing số',
    'duy.le@ueh.edu.vn'     => 'Lê Hoàng Duy — DevOps cơ bản',
    'thu.vo@ueh.edu.vn'     => 'Võ Minh Thư — Data & SQL',
    'anhkhoa@ueh.edu.vn'    => 'Nguyễn Anh Khoa — Mobile Flutter',
    'my.dang@student.vn'    => 'Đặng Thảo My — Học sinh giỏi Tin',
];

$stmtUser = $conn->prepare(
    "INSERT IGNORE INTO users (username, email, password_hash, status, is_online, user_type, truong_hoc, truong_dai_hoc, giay_to_chung_minh)
     VALUES (?, ?, ?, 'Active', ?, ?, ?, ?, ?)"
);

$onlineFlags = [1, 1, 0, 1, 0, 1, 0, 1, 1, 0];
$userCount = 0;
foreach ($newUsers as $i => $u) {
    [$username, $email, $type, $thcs, $dh, $giay] = $u;
    $online = $onlineFlags[$i] ?? 0;
    $stmtUser->bind_param('sssissss', $username, $email, $passwordHash, $online, $type, $thcs, $dh, $giay);
    if ($stmtUser->execute() && $stmtUser->affected_rows > 0) {
        $userCount++;
    }
}
seedLog($log, "Người dùng mới: {$userCount} tài khoản (mật khẩu: 123456)");

foreach ($profiles as $email => $profile) {
    $uid = seedUserId($conn, $email);
    if (!$uid) {
        continue;
    }
    $stmt = $conn->prepare(
        'INSERT INTO ho_so_ca_nhan (user_id, thong_tin_dinh_danh) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE thong_tin_dinh_danh = VALUES(thong_tin_dinh_danh)'
    );
    $stmt->bind_param('is', $uid, $profile);
    $stmt->execute();
}
seedLog($log, 'Đã cập nhật hồ sơ cá nhân cho user mới');

// Resolve IDs
$id = fn(string $email) => seedUserId($conn, $email);
$admin   = $id('admin@thebunny.vn') ?? 1;
$gvngoc  = $id('gv.ngoc@thebunny.vn') ?? 2;
$tienanh = $id('tienanh@student.vn') ?? 3;
$quynhnga= $id('quynhnga@student.vn') ?? 4;
$baoviet = $id('baoviet@ueh.edu.vn');
$datbui  = $id('dat.bui@ueh.edu.vn');
$hoangth = $id('hoang.thanh@ueh.edu.vn');
$linhpham= $id('linh.pham@ueh.edu.vn');
$mainguyen=$id('mai.nguyen@ueh.edu.vn');
$duyle   = $id('duy.le@ueh.edu.vn');
$thuvo   = $id('thu.vo@ueh.edu.vn');

// ─── 2. HANG THỎ & THÀNH VIÊN ─────────────────────────────────────────────
$hangThoNames = [
    'Hang Thỏ PTUD Web — Nhóm 10',
    'Cộng đồng MySQL & Database',
    'Ôn thi TOEIC 650+',
    'Figma & Design System',
    'Luyện code PHP thực chiến',
];
$hangAdded = 0;
foreach ($hangThoNames as $name) {
    $stmt = $conn->prepare('INSERT IGNORE INTO hang_tho (ten_hang_tho) SELECT ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM hang_tho WHERE ten_hang_tho = ?)');
    $stmt->bind_param('ss', $name, $name);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $hangAdded++;
    }
}
seedLog($log, "Hang Thỏ mới: {$hangAdded} nhóm");

$hangMap = [];
$res = $conn->query('SELECT id, ten_hang_tho FROM hang_tho');
while ($row = $res->fetch_assoc()) {
    $hangMap[$row['ten_hang_tho']] = (int) $row['id'];
}

$memberships = [
    [$tienanh, 'Hang Thỏ PTUD Web — Nhóm 10'],
    [$quynhnga, 'Hang Thỏ PTUD Web — Nhóm 10'],
    [$baoviet, 'Hang Thỏ PTUD Web — Nhóm 10'],
    [$datbui, 'Hang Thỏ PTUD Web — Nhóm 10'],
    [$datbui, 'Cộng đồng MySQL & Database'],
    [$thuvo, 'Cộng đồng MySQL & Database'],
    [$linhpham, 'Figma & Design System'],
    [$quynhnga, 'Figma & Design System'],
    [$mainguyen, 'Ôn thi TOEIC 650+'],
    [$duyle, 'Luyện code PHP thực chiến'],
    [$baoviet, 'Luyện code PHP thực chiến'],
];
$memAdded = 0;
foreach ($memberships as [$uid, $hangName]) {
    if (!$uid || !isset($hangMap[$hangName])) {
        continue;
    }
    $hid = $hangMap[$hangName];
    $stmt = $conn->prepare('INSERT IGNORE INTO user_hang_tho (user_id, hang_tho_id) VALUES (?, ?)');
    $stmt->bind_param('ii', $uid, $hid);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $memAdded++;
    }
}
seedLog($log, "Thành viên Hang Thỏ mới: {$memAdded}");

// ─── 3. HASHTAG ─────────────────────────────────────────────────────────────
$tags = ['#PTUDWeb', '#UEH', '#MySQL', '#Bootstrap5', '#TOEIC', '#Figma', '#DoAnTotNghiep', '#PHP', '#Deadline', '#TheBunny'];
$tagAdded = 0;
foreach ($tags as $tag) {
    $stmt = $conn->prepare('INSERT IGNORE INTO hashtag (ten_hashtag) VALUES (?)');
    $stmt->bind_param('s', $tag);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $tagAdded++;
    }
}
seedLog($log, "Hashtag mới: {$tagAdded}");

// ─── 4. BÀI ĐĂNG + TƯƠNG TÁC ────────────────────────────────────────────────
$posts = [
    [$hoangth ?? $gvngoc, 'Tuần 12: các em ôn lại chương PDO, prepared statements và CSRF token trước khi nộp đồ án giữa kỳ nhé. #PTUDWeb #UEH'],
    [$gvngoc, 'Slide buổi 11 — MVC pattern trong PHP đã upload lên kho tài liệu. Ai chưa tải thì vào Hang Thỏ PTUD Web xem nhé.'],
    [$tienanh, 'Mình vừa fix xong lỗi session_start() gọi sau khi đã output HTML. Tip: luôn đặt session ở dòng đầu file PHP! #PHP'],
    [$quynhnga, 'Bootstrap 5 grid 12 cột thật sự cứu cánh layout responsive cho trang cá nhân. Ai cần template mình share file Figma. #Bootstrap5 #Figma'],
    [$baoviet, 'Nhóm mình đang làm module Thách đấu — ai rảnh test thử flow mời bạn vào phòng không? Cần 2-3 người. #TheBunny'],
    [$datbui, 'So sánh nhanh: mysqli vs PDO — PDO hỗ trợ named placeholder, dễ tái sử dụng hơn khi viết repository. #MySQL #PHP'],
    [$linhpham, 'Checklist UI trước khi demo: contrast đủ chưa, nút CTA rõ chưa, mobile menu có hoạt động không? #Figma'],
    [$mainguyen, 'Có ai đăng ký workshop TOEIC tháng 6 chưa? Nhóm mình học chung buổi tối thứ 4. #TOEIC'],
    [$duyle, 'Deploy PHP lên shared hosting: nhớ set display_errors=0 production, và kiểm tra quyền ghi thư mục uploads.'],
    [$thuvo, 'Câu query JOIN 3 bảng users + bai_dang + binh_luan hơi chậm — thêm index cho bai_dang_id giúp giảm từ 200ms xuống 15ms. #MySQL'],
    [$tienanh, 'Deadline nộp báo cáo tiến độ đồ án: 15/06. Ai cần review code cứ nhắn tin, mình rảnh tối nay. #DoAnTotNghiep #Deadline'],
    [$quynhnga, 'Vừa hoàn thiện trang Admin — quản lý user, bài viết, Hang Thỏ, hashtag. Feedback welcome! 🐰'],
];

$postAdded = 0;
$postIds = [];
foreach ($posts as [$uid, $content]) {
    if (!$uid) {
        continue;
    }
    if (seedPostIdByContent($conn, $content)) {
        $postIds[] = seedPostIdByContent($conn, $content);
        continue;
    }
    $stmt = $conn->prepare('INSERT INTO bai_dang (user_id, noi_dung, created_at) VALUES (?, ?, DATE_SUB(NOW(), INTERVAL FLOOR(RAND()*14) DAY))');
    $stmt->bind_param('is', $uid, $content);
    if ($stmt->execute()) {
        $postIds[] = $conn->insert_id;
        $postAdded++;
    }
}
seedLog($log, "Bài đăng mới: {$postAdded}");

// Bình luận mẫu
$comments = [
    ['Mình cũng gặp lỗi tương tự, cảm ơn bạn đã share!', $tienanh, 'Mình vừa fix xong lỗi session_start()'],
    ['Dạ em note lại ạ, cảm ơn thầy!', $baoviet, 'Tuần 12: các em ôn lại chương PDO'],
    ['PDO + try/catch là best practice, đồng ý!', $thuvo, 'So sánh nhanh: mysqli vs PDO'],
    ['Cho mình xin link Figma với!', $mainguyen, 'Bootstrap 5 grid 12 cột'],
    ['Tối nay rảnh, inbox mình nhé', $datbui, 'Nhóm mình đang làm module Thách đấu'],
    ['Index đã thêm, query nhanh hơn hẳn', $datbui, 'Câu query JOIN 3 bảng'],
    ['Admin panel nhìn pro đấy!', $duyle, 'Vừa hoàn thiện trang Admin'],
];
$cmtAdded = 0;
foreach ($comments as [$text, $uid, $postSnippet]) {
    if (!$uid) {
        continue;
    }
    $pid = null;
    $like = "%{$postSnippet}%";
    $stmt = $conn->prepare('SELECT id FROM bai_dang WHERE noi_dung LIKE ? LIMIT 1');
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        continue;
    }
    $pid = (int) $row['id'];
    $check = $conn->prepare('SELECT 1 FROM binh_luan WHERE bai_dang_id = ? AND user_id = ? AND noi_dung = ? LIMIT 1');
    $check->bind_param('iis', $pid, $uid, $text);
    $check->execute();
    if ($check->get_result()->fetch_row()) {
        continue;
    }
    $ins = $conn->prepare('INSERT INTO binh_luan (bai_dang_id, user_id, noi_dung) VALUES (?, ?, ?)');
    $ins->bind_param('iis', $pid, $uid, $text);
    if ($ins->execute()) {
        $cmtAdded++;
    }
}
seedLog($log, "Bình luận mới: {$cmtAdded}");

// Like & share
$likePairs = [
    [$tienanh, 'Tuần 12: các em ôn lại'],
    [$baoviet, 'Tuần 12: các em ôn lại'],
    [$datbui, 'Tuần 12: các em ôn lại'],
    [$quynhnga, 'Bootstrap 5 grid'],
    [$linhpham, 'Bootstrap 5 grid'],
    [$thuvo, 'So sánh nhanh: mysqli vs PDO'],
    [$mainguyen, 'Có ai đăng ký workshop TOEIC'],
    [$duyle, 'Vừa hoàn thiện trang Admin'],
    [$tienanh, 'Vừa hoàn thiện trang Admin'],
];
$likeAdded = 0;
foreach ($likePairs as [$uid, $snippet]) {
    if (!$uid) {
        continue;
    }
    $like = "%{$snippet}%";
    $stmt = $conn->prepare('SELECT id FROM bai_dang WHERE noi_dung LIKE ? LIMIT 1');
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        continue;
    }
    $pid = (int) $row['id'];
    $ins = $conn->prepare('INSERT IGNORE INTO luot_thich (bai_dang_id, user_id) VALUES (?, ?)');
    $ins->bind_param('ii', $pid, $uid);
    if ($ins->execute() && $ins->affected_rows > 0) {
        $likeAdded++;
    }
}
seedLog($log, "Lượt thích mới: {$likeAdded}");

// ─── 5. TÀI LIỆU ────────────────────────────────────────────────────────────
$docs = [
    [$gvngoc, 'Slide Buổi 11 — MVC PHP', '/uploads/slide-mvc-php.pdf'],
    [$hoangth ?? $gvngoc, 'Đề cương môn PTUD Web HK2', '/uploads/de-cuong-ptud-web.pdf'],
    [$datbui, 'Cheatsheet MySQL JOIN', '/uploads/mysql-join-cheatsheet.pdf'],
    [$thuvo, 'Bài tập PDO — có lời giải', '/uploads/bai-tap-pdo.pdf'],
    [$linhpham, 'Design System The Bunny — Figma', '/uploads/the-bunny-design-system.fig'],
    [$baoviet, 'Template Bootstrap 5 Admin', '/uploads/bootstrap-admin-template.zip'],
    [$mainguyen, '500 từ TOEIC thường gặp', '/uploads/toeic-500-words.pdf'],
    [$duyle, 'Hướng dẫn deploy PHP lên cPanel', '/uploads/deploy-php-cpanel.pdf'],
    [$tienanh, 'Checklist bảo mật PHP cơ bản', '/uploads/php-security-checklist.pdf'],
    [$quynhnga, 'Wireframe trang cá nhân MXH học tập', '/uploads/wireframe-trang-ca-nhan.pdf'],
];
$docAdded = 0;
foreach ($docs as [$uid, $name, $url]) {
    if (!$uid) {
        continue;
    }
    $check = $conn->prepare('SELECT 1 FROM tai_lieu WHERE user_id = ? AND ten_tai_lieu = ? LIMIT 1');
    $check->bind_param('is', $uid, $name);
    $check->execute();
    if ($check->get_result()->fetch_row()) {
        continue;
    }
    $ins = $conn->prepare('INSERT INTO tai_lieu (user_id, ten_tai_lieu, file_url) VALUES (?, ?, ?)');
    $ins->bind_param('iss', $uid, $name, $url);
    if ($ins->execute()) {
        $docAdded++;
    }
}
seedLog($log, "Tài liệu mới: {$docAdded}");

// ─── 6. CÂU HỎI (đáp án A/B/C/D) ──────────────────────────────────────────
$questions = [
    [1, 'Hàm nào dùng để mở kết nối PDO trong PHP?', 'mysqli_connect()', 'new PDO()', 'mysql_pconnect()', 'pg_connect()', 'B'],
    [1, 'Prepared Statement giúp chống tấn công nào?', 'XSS', 'CSRF', 'SQL Injection', 'DDoS', 'C'],
    [1, 'Session trong PHP được khởi tạo bằng hàm?', 'session_open()', 'session_start()', 'start_session()', 'init_session()', 'B'],
    [2, 'Thuộc tính HTML dùng để liên kết CSS ngoài?', '<style>', '<link rel="stylesheet">', '<css>', '<import>', 'B'],
    [2, 'Bootstrap 12-column grid dùng class nào cho container?', '.wrapper', '.container', '.grid-12', '.row-only', 'B'],
    [3, 'JavaScript ES6 khai báo biến không reassign được?', 'var', 'let', 'const', 'static', 'C'],
    [3, 'API fetch() trả về kiểu dữ liệu gì?', 'String', 'Object', 'Promise', 'Array', 'C'],
    [4, 'Lệnh SQL lấy dữ liệu từ bảng?', 'GET', 'SELECT', 'FETCH', 'READ', 'B'],
    [4, 'Khóa ngoại (FOREIGN KEY) dùng để?', 'Tăng tốc truy vấn', 'Liên kết bảng', 'Mã hóa dữ liệu', 'Sao lưu', 'B'],
    [4, 'UNIQUE KEY khác PRIMARY KEY ở điểm nào?', 'Không cho NULL', 'Một bảng có nhiều UNIQUE', 'Tự tăng', 'Không lập chỉ mục', 'B'],
];
$qAdded = 0;
foreach ($questions as [$boDe, $content, $a, $b, $c, $d, $ans]) {
    $check = $conn->prepare('SELECT 1 FROM cau_hoi WHERE bo_de_id = ? AND noi_dung = ? LIMIT 1');
    $check->bind_param('is', $boDe, $content);
    $check->execute();
    if ($check->get_result()->fetch_row()) {
        continue;
    }
    $ins = $conn->prepare(
        'INSERT INTO cau_hoi (bo_de_id, noi_dung, lua_chon_a, lua_chon_b, lua_chon_c, lua_chon_d, dap_an_dung) VALUES (?,?,?,?,?,?,?)'
    );
    $ins->bind_param('issssss', $boDe, $content, $a, $b, $c, $d, $ans);
    if ($ins->execute()) {
        $qAdded++;
    }
}
seedLog($log, "Câu hỏi mới: {$qAdded}");

// ─── 7. SỰ KIỆN ─────────────────────────────────────────────────────────────
$events = [
    ['Seminar: Thiết kế UX cho MXH học tập', '2026-06-20 14:00:00'],
    ['Offline: Code review đồ án nhóm 10', '2026-06-08 19:00:00'],
    ['Mock interview TOEIC Speaking', '2026-06-25 18:30:00'],
    ['Demo Day — The Bunny v1.0', '2026-07-10 09:00:00'],
    ['Workshop Git & GitHub cho người mới', '2026-06-12 15:00:00'],
];
$evAdded = 0;
foreach ($events as [$title, $time]) {
    $check = $conn->prepare('SELECT 1 FROM su_kien WHERE tieu_de = ? LIMIT 1');
    $check->bind_param('s', $title);
    $check->execute();
    if ($check->get_result()->fetch_row()) {
        continue;
    }
    $ins = $conn->prepare('INSERT INTO su_kien (tieu_de, thoi_gian) VALUES (?, ?)');
    $ins->bind_param('ss', $title, $time);
    if ($ins->execute()) {
        $evAdded++;
        $eid = $conn->insert_id;
        $participants = array_filter([$tienanh, $quynhnga, $baoviet, $datbui, $linhpham]);
        $statuses = ['Approved', 'Approved', 'Pending', 'Approved', 'Pending'];
        $i = 0;
        foreach ($participants as $puid) {
            $st = $statuses[$i++] ?? 'Pending';
            $p = $conn->prepare('INSERT IGNORE INTO thanh_vien_su_kien (su_kien_id, user_id, trang_thai_duyet) VALUES (?, ?, ?)');
            $p->bind_param('iis', $eid, $puid, $st);
            $p->execute();
        }
    }
}
seedLog($log, "Sự kiện mới: {$evAdded}");

// ─── 8. BẠN CÙNG TIẾN ───────────────────────────────────────────────────────
$friends = [
    [$tienanh, $baoviet, 'Accepted'],
    [$tienanh, $datbui, 'Accepted'],
    [$quynhnga, $linhpham, 'Accepted'],
    [$baoviet, $datbui, 'Accepted'],
    [$mainguyen, $thuvo, 'Accepted'],
    [$duyle, $tienanh, 'Pending'],
    [$linhpham, $mainguyen, 'Pending'],
    [$thuvo, $quynhnga, 'Accepted'],
];
$frAdded = 0;
foreach ($friends as [$u1, $u2, $st]) {
    if (!$u1 || !$u2) {
        continue;
    }
    $ins = $conn->prepare('INSERT IGNORE INTO ban_cung_tien (user_id, friend_user_id, status) VALUES (?, ?, ?)');
    $ins->bind_param('iis', $u1, $u2, $st);
    if ($ins->execute() && $ins->affected_rows > 0) {
        $frAdded++;
    }
}
seedLog($log, "Lời mời bạn cùng tiến mới: {$frAdded}");

// ─── 9. TIN NHẮN ────────────────────────────────────────────────────────────
if ($tienanh && $baoviet) {
    $convExists = $conn->prepare(
        'SELECT c.id FROM cuoc_tro_chuyen c
         INNER JOIN cuoc_tro_chuyen_thanh_vien m1 ON m1.cuoc_tro_chuyen_id = c.id AND m1.user_id = ?
         INNER JOIN cuoc_tro_chuyen_thanh_vien m2 ON m2.cuoc_tro_chuyen_id = c.id AND m2.user_id = ?
         LIMIT 1'
    );
    $convExists->bind_param('ii', $tienanh, $baoviet);
    $convExists->execute();
    $hasConv = (bool) $convExists->get_result()->fetch_assoc();

    if (!$hasConv) {
        $conn->query('INSERT INTO cuoc_tro_chuyen () VALUES ()');
        $newConvId = $conn->insert_id;
        $stmt = $conn->prepare('INSERT IGNORE INTO cuoc_tro_chuyen_thanh_vien (cuoc_tro_chuyen_id, user_id) VALUES (?, ?)');
        $stmt->bind_param('ii', $newConvId, $tienanh);
        $stmt->execute();
        $stmt->bind_param('ii', $newConvId, $baoviet);
        $stmt->execute();

        $msgs = [
            [$tienanh, 'Ê Bảo, module admin xong chưa?'],
            [$baoviet, 'Xong rồi, đang test CRUD hashtag'],
            [$tienanh, 'Ok mai demo với thầy Hoàng Thành nhé'],
            [$baoviet, 'Roger 👍'],
        ];
        foreach ($msgs as [$sender, $text]) {
            $m = $conn->prepare('INSERT INTO tin_nhan (cuoc_tro_chuyen_id, sender_user_id, noi_dung) VALUES (?, ?, ?)');
            $m->bind_param('iis', $newConvId, $sender, $text);
            $m->execute();
        }
        seedLog($log, 'Đã tạo cuộc trò chuyện mẫu (Tiến Anh ↔ Bảo Việt)');
    } else {
        seedLog($log, 'Cuộc trò chuyện Tiến Anh ↔ Bảo Việt đã tồn tại — bỏ qua');
    }
}

// ─── 10. BÁO CÁO VI PHẠM ────────────────────────────────────────────────────
$reports = [
    [$tienanh, 'Spam quảng cáo khóa học lạ', 'Pending'],
    [$quynhnga, 'Copy bài tập không ghi nguồn', 'Pending'],
    [$datbui, 'Ngôn từ thiếu tôn trọng trong bình luận', 'Reviewed'],
    [$mainguyen, 'Chia sẻ link lừa đảo', 'Resolved'],
];
$repAdded = 0;
foreach ($reports as [$reporter, $reason, $status]) {
    if (!$reporter) {
        continue;
    }
    $postRes = $conn->query('SELECT id, user_id FROM bai_dang ORDER BY RAND() LIMIT 1');
    $post = $postRes->fetch_assoc();
    if (!$post) {
        break;
    }
    $check = $conn->prepare('SELECT 1 FROM bao_cao_vi_pham WHERE nguoi_bao_cao_id = ? AND ly_do = ? LIMIT 1');
    $check->bind_param('is', $reporter, $reason);
    $check->execute();
    if ($check->get_result()->fetch_row()) {
        continue;
    }
    $pid = (int) $post['id'];
    $target = (int) $post['user_id'];
    $ins = $conn->prepare(
        'INSERT INTO bao_cao_vi_pham (nguoi_bao_cao_id, bai_dang_id, nguoi_bi_bao_cao_id, ly_do, trang_thai) VALUES (?,?,?,?,?)'
    );
    $ins->bind_param('iiiss', $reporter, $pid, $target, $reason, $status);
    if ($ins->execute()) {
        $repAdded++;
    }
}
seedLog($log, "Báo cáo khiếu nại mới: {$repAdded}");

// ─── 11. THÔNG BÁO ──────────────────────────────────────────────────────────
$notifs = [
    [$baoviet, 'quynhnga đã chấp nhận lời mời Bạn cùng tiến', 0],
    [$tienanh, 'datbui đã thích bài viết của bạn', 0],
    [$quynhnga, 'Có 3 bình luận mới trên bài đăng của bạn', 1],
    [$datbui, 'Lời mời tham gia Hang Thỏ PTUD Web đã được duyệt', 1],
];
$nAdded = 0;
foreach ($notifs as [$uid, $text, $read]) {
    if (!$uid) {
        continue;
    }
    $check = $conn->prepare('SELECT 1 FROM thong_bao WHERE user_id = ? AND noi_dung = ? LIMIT 1');
    $check->bind_param('is', $uid, $text);
    $check->execute();
    if ($check->get_result()->fetch_row()) {
        continue;
    }
    $ins = $conn->prepare('INSERT INTO thong_bao (user_id, noi_dung, is_read) VALUES (?, ?, ?)');
    $ins->bind_param('isi', $uid, $text, $read);
    if ($ins->execute()) {
        $nAdded++;
    }
}
seedLog($log, "Thông báo mới: {$nAdded}");

// Dọn dữ liệu friendship trùng chiều (chỉ chạy khi seed — không gọi lúc đọc trang)
$repairSql = "
    DELETE b2 FROM ban_cung_tien b1
    INNER JOIN ban_cung_tien b2
        ON b1.user_id = b2.friend_user_id
       AND b1.friend_user_id = b2.user_id
       AND b1.status = b2.status
       AND b1.id < b2.id
";
if ($conn->query($repairSql) === true) {
    seedLog($log, 'Dọn friendship trùng chiều: ' . $conn->affected_rows . ' dòng');
}

$conn->close();

// ─── OUTPUT ─────────────────────────────────────────────────────────────────
if ($isCli) {
    foreach ($log as $entry) {
        echo ($entry['ok'] ? '✅ ' : '❌ ') . $entry['msg'] . "\n";
    }
    echo "\nTài khoản mới (mật khẩu 123456): baoviet@ueh.edu.vn, dat.bui@ueh.edu.vn, ...\n";
    exit(0);
}

foreach ($log as $entry) {
    $cls = $entry['ok'] ? 'list-group-item-success' : 'list-group-item-danger';
    echo "<li class='list-group-item {$cls}'>" . ($entry['ok'] ? '✅' : '❌') . ' ' . htmlspecialchars($entry['msg']) . '</li>';
}

echo "</ul>";
echo "<div class='alert alert-info'><strong>Tài khoản mới</strong> (mật khẩu <code>123456</code>):<br>";
echo "baoviet@ueh.edu.vn, dat.bui@ueh.edu.vn, linh.pham@ueh.edu.vn, mai.nguyen@ueh.edu.vn, ...</div>";
echo "<a href='../index.php' class='btn btn-primary me-2'>Vào trang chủ</a>";
echo "<a href='../pages/admin/index.php' class='btn btn-outline-secondary'>Admin panel</a>";
echo "</div></div></div></body></html>";
