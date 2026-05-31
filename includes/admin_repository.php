<?php
require_once __DIR__ . '/admin_helpers.php';

function adminGetDashboardStats(PDO $pdo): array
{
    $stats = [
        'users_total'      => 0,
        'users_active'     => 0,
        'users_banned'     => 0,
        'users_pending'    => 0,
        'posts_total'      => 0,
        'posts_today'      => 0,
        'comments_total'   => 0,
        'reports_pending'  => 0,
        'hang_tho_total'   => 0,
        'hashtag_total'    => 0,
        'tai_lieu_total'   => 0,
        'cau_hoi_total'    => 0,
        'su_kien_total'    => 0,
    ];

    $stats['users_total'] = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $stats['users_active'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'Active'")->fetchColumn();
    $stats['users_banned'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'Banned'")->fetchColumn();
    $stats['users_pending'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'Pending'")->fetchColumn();
    $stats['posts_total'] = (int) $pdo->query('SELECT COUNT(*) FROM bai_dang')->fetchColumn();
    $stats['posts_today'] = (int) $pdo->query('SELECT COUNT(*) FROM bai_dang WHERE DATE(created_at) = CURDATE()')->fetchColumn();
    $stats['comments_total'] = (int) $pdo->query('SELECT COUNT(*) FROM binh_luan')->fetchColumn();

    try {
        $stats['reports_pending'] = (int) $pdo->query("SELECT COUNT(*) FROM bao_cao_vi_pham WHERE trang_thai = 'Pending'")->fetchColumn();
    } catch (Throwable) {
        $stats['reports_pending'] = 0;
    }

    try {
        $stats['hang_tho_total'] = (int) $pdo->query('SELECT COUNT(*) FROM hang_tho')->fetchColumn();
        $stats['hashtag_total'] = (int) $pdo->query('SELECT COUNT(*) FROM hashtag')->fetchColumn();
        $stats['tai_lieu_total'] = (int) $pdo->query('SELECT COUNT(*) FROM tai_lieu')->fetchColumn();
        $stats['cau_hoi_total'] = (int) $pdo->query('SELECT COUNT(*) FROM cau_hoi')->fetchColumn();
        $stats['su_kien_total'] = (int) $pdo->query('SELECT COUNT(*) FROM su_kien')->fetchColumn();
    } catch (Throwable) {
        // ignore missing tables
    }

    return $stats;
}

function adminListUsers(PDO $pdo, array $filters = [], int $page = 1, int $perPage = 15): array
{
    $page = max(1, $page);
    $perPage = max(5, min(50, $perPage));
    $offset = ($page - 1) * $perPage;

    $where = ['1=1'];
    $params = [];

    $q = trim($filters['q'] ?? '');
    if ($q !== '') {
        $where[] = '(u.username LIKE :q OR u.email LIKE :q2 OR h.thong_tin_dinh_danh LIKE :q3)';
        $like = '%' . $q . '%';
        $params['q'] = $like;
        $params['q2'] = $like;
        $params['q3'] = $like;
    }

    $status = trim($filters['status'] ?? '');
    if ($status !== '' && in_array($status, ['Active', 'Banned', 'Pending'], true)) {
        $where[] = 'u.status = :status';
        $params['status'] = $status;
    }

    $userType = trim($filters['user_type'] ?? '');
    if ($userType !== '' && in_array($userType, ['hoc_sinh', 'sinh_vien', 'giao_vien', 'quan_tri_vien'], true)) {
        $where[] = 'u.user_type = :user_type';
        $params['user_type'] = $userType;
    }

    $whereSql = implode(' AND ', $where);

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM users u
         LEFT JOIN ho_so_ca_nhan h ON h.user_id = u.id
         WHERE {$whereSql}"
    );
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $sql = "
        SELECT u.id, u.username, u.email, u.status, u.user_type, u.is_online,
               u.truong_hoc, u.truong_dai_hoc, u.created_at,
               h.thong_tin_dinh_danh,
               (SELECT COUNT(*) FROM bai_dang b WHERE b.user_id = u.id) AS post_count
        FROM users u
        LEFT JOIN ho_so_ca_nhan h ON h.user_id = u.id
        WHERE {$whereSql}
        ORDER BY u.created_at DESC
        LIMIT {$perPage} OFFSET {$offset}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $items = [];
    foreach ($rows as $row) {
        $items[] = [
            'id'         => (int) $row['id'],
            'username'   => $row['username'],
            'email'      => $row['email'],
            'status'     => $row['status'],
            'user_type'  => $row['user_type'],
            'user_type_label' => adminUserTypeLabel($row['user_type']),
            'is_online'  => (int) $row['is_online'] === 1,
            'school'     => $row['truong_dai_hoc'] ?: ($row['truong_hoc'] ?: '—'),
            'display_name' => bunnyDisplayName($row['username'], $row['thong_tin_dinh_danh']),
            'avatar'     => bunnyAvatar((int) $row['id']),
            'created_at' => adminFormatDate($row['created_at']),
            'post_count' => (int) $row['post_count'],
        ];
    }

    return [
        'items'    => $items,
        'total'    => $total,
        'page'     => $page,
        'per_page' => $perPage,
        'pages'    => max(1, (int) ceil($total / $perPage)),
    ];
}

function adminGetUser(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT u.*, h.thong_tin_dinh_danh
         FROM users u
         LEFT JOIN ho_so_ca_nhan h ON h.user_id = u.id
         WHERE u.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function adminUpdateUser(PDO $pdo, int $userId, array $data): void
{
    $user = adminGetUser($pdo, $userId);
    if (!$user) {
        throw new RuntimeException('Không tìm thấy người dùng.');
    }

    $status = $data['status'] ?? $user['status'];
    $userType = $data['user_type'] ?? $user['user_type'];
    $username = trim($data['username'] ?? $user['username']);
    $email = trim($data['email'] ?? $user['email']);

    if (!in_array($status, ['Active', 'Banned', 'Pending'], true)) {
        throw new InvalidArgumentException('Trạng thái không hợp lệ.');
    }
    if (!in_array($userType, ['hoc_sinh', 'sinh_vien', 'giao_vien', 'quan_tri_vien'], true)) {
        throw new InvalidArgumentException('Vai trò không hợp lệ.');
    }
    if ($username === '' || $email === '') {
        throw new InvalidArgumentException('Tên đăng nhập và email không được trống.');
    }

    $dup = $pdo->prepare(
        'SELECT id FROM users WHERE (username = :username OR email = :email) AND id <> :id LIMIT 1'
    );
    $dup->execute(['username' => $username, 'email' => $email, 'id' => $userId]);
    if ($dup->fetch()) {
        throw new RuntimeException('Username hoặc email đã tồn tại.');
    }

    $stmt = $pdo->prepare(
        'UPDATE users
         SET username = :username, email = :email, status = :status, user_type = :user_type
         WHERE id = :id'
    );
    $stmt->execute([
        'username'  => $username,
        'email'     => $email,
        'status'    => $status,
        'user_type' => $userType,
        'id'        => $userId,
    ]);

    if (!empty($data['password'])) {
        $hash = password_hash((string) $data['password'], PASSWORD_BCRYPT);
        $pwd = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $pwd->execute(['hash' => $hash, 'id' => $userId]);
    }
}

function adminDeleteUser(PDO $pdo, int $userId, int $currentAdminId): void
{
    if ($userId === $currentAdminId) {
        throw new RuntimeException('Không thể xóa chính tài khoản đang đăng nhập.');
    }

    $user = adminGetUser($pdo, $userId);
    if (!$user) {
        throw new RuntimeException('Không tìm thấy người dùng.');
    }

    $del = $pdo->prepare('DELETE FROM users WHERE id = :id');
    $del->execute(['id' => $userId]);
}

function adminListPosts(PDO $pdo, array $filters = [], int $page = 1, int $perPage = 15): array
{
    $page = max(1, $page);
    $perPage = max(5, min(50, $perPage));
    $offset = ($page - 1) * $perPage;

    $where = ['1=1'];
    $params = [];

    $q = trim($filters['q'] ?? '');
    if ($q !== '') {
        $where[] = '(b.noi_dung LIKE :q OR u.username LIKE :q2 OR u.email LIKE :q3)';
        $like = '%' . $q . '%';
        $params['q'] = $like;
        $params['q2'] = $like;
        $params['q3'] = $like;
    }

    $whereSql = implode(' AND ', $where);

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM bai_dang b
         INNER JOIN users u ON u.id = b.user_id
         WHERE {$whereSql}"
    );
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $sql = "
        SELECT b.id, b.user_id, b.noi_dung, b.created_at, b.updated_at,
               u.username, u.email, u.status AS author_status,
               h.thong_tin_dinh_danh,
               (SELECT COUNT(*) FROM luot_thich l WHERE l.bai_dang_id = b.id) AS like_count,
               (SELECT COUNT(*) FROM binh_luan c WHERE c.bai_dang_id = b.id) AS comment_count,
               (SELECT COUNT(*) FROM luot_chia_se s WHERE s.bai_dang_id = b.id) AS share_count
        FROM bai_dang b
        INNER JOIN users u ON u.id = b.user_id
        LEFT JOIN ho_so_ca_nhan h ON h.user_id = u.id
        WHERE {$whereSql}
        ORDER BY b.created_at DESC
        LIMIT {$perPage} OFFSET {$offset}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $items[] = [
            'id'            => (int) $row['id'],
            'user_id'       => (int) $row['user_id'],
            'author_name'   => bunnyDisplayName($row['username'], $row['thong_tin_dinh_danh']),
            'author_username'=> $row['username'],
            'author_email'  => $row['email'],
            'author_status' => $row['author_status'],
            'author_avatar' => bunnyAvatar((int) $row['user_id']),
            'noi_dung'      => $row['noi_dung'],
            'preview'       => adminTruncate($row['noi_dung'], 160),
            'created_at'    => adminFormatDate($row['created_at']),
            'updated_at'    => adminFormatDate($row['updated_at']),
            'like_count'    => (int) $row['like_count'],
            'comment_count' => (int) $row['comment_count'],
            'share_count'   => (int) $row['share_count'],
        ];
    }

    return [
        'items'    => $items,
        'total'    => $total,
        'page'     => $page,
        'per_page' => $perPage,
        'pages'    => max(1, (int) ceil($total / $perPage)),
    ];
}

function adminGetPost(PDO $pdo, int $postId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT b.*, u.username, u.email, h.thong_tin_dinh_danh
         FROM bai_dang b
         INNER JOIN users u ON u.id = b.user_id
         LEFT JOIN ho_so_ca_nhan h ON h.user_id = u.id
         WHERE b.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $postId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function adminUpdatePost(PDO $pdo, int $postId, string $content): void
{
    $content = trim($content);
    if ($content === '') {
        throw new InvalidArgumentException('Nội dung bài viết không được trống.');
    }

    if (!adminGetPost($pdo, $postId)) {
        throw new RuntimeException('Không tìm thấy bài viết.');
    }

    $stmt = $pdo->prepare('UPDATE bai_dang SET noi_dung = :content WHERE id = :id');
    $stmt->execute(['content' => $content, 'id' => $postId]);
}

function adminDeletePost(PDO $pdo, int $postId): void
{
    if (!adminGetPost($pdo, $postId)) {
        throw new RuntimeException('Không tìm thấy bài viết.');
    }

    $del = $pdo->prepare('DELETE FROM bai_dang WHERE id = :id');
    $del->execute(['id' => $postId]);
}

function adminHandleAction(PDO $pdo, int $adminId, string $action, array $input): array
{
    return match ($action) {
        'update_user' => (function () use ($pdo, $input) {
            adminUpdateUser($pdo, (int) ($input['user_id'] ?? 0), $input);
            return ['message' => 'Đã cập nhật người dùng.'];
        })(),
        'delete_user' => (function () use ($pdo, $adminId, $input) {
            adminDeleteUser($pdo, (int) ($input['user_id'] ?? 0), $adminId);
            return ['message' => 'Đã xóa người dùng.'];
        })(),
        'update_post' => (function () use ($pdo, $input) {
            adminUpdatePost($pdo, (int) ($input['post_id'] ?? 0), (string) ($input['noi_dung'] ?? ''));
            return ['message' => 'Đã cập nhật bài viết.'];
        })(),
        'delete_post' => (function () use ($pdo, $input) {
            adminDeletePost($pdo, (int) ($input['post_id'] ?? 0));
            return ['message' => 'Đã xóa bài viết.'];
        })(),
        'create_hang_tho', 'update_hang_tho', 'delete_hang_tho' => adminHandleHangThoAction($pdo, $action, $input),
        'create_hashtag', 'update_hashtag', 'delete_hashtag' => adminHandleHashtagAction($pdo, $action, $input),
        'create_tai_lieu', 'update_tai_lieu', 'delete_tai_lieu' => adminHandleTaiLieuAction($pdo, $action, $input),
        'create_cau_hoi', 'update_cau_hoi', 'delete_cau_hoi' => adminHandleCauHoiAction($pdo, $action, $input),
        'create_su_kien', 'update_su_kien', 'delete_su_kien' => adminHandleSuKienAction($pdo, $action, $input),
        'update_report', 'delete_report' => adminHandleReportAction($pdo, $action, $input),
        default => throw new InvalidArgumentException('Hành động không hợp lệ.'),
    };
}

function adminRecentUsers(PDO $pdo, int $limit = 5): array
{
    $stmt = $pdo->query(
        "SELECT u.id, u.username, u.email, u.status, u.user_type, u.created_at, h.thong_tin_dinh_danh
         FROM users u
         LEFT JOIN ho_so_ca_nhan h ON h.user_id = u.id
         ORDER BY u.created_at DESC
         LIMIT " . (int) $limit
    );

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $items[] = [
            'id'       => (int) $row['id'],
            'name'     => bunnyDisplayName($row['username'], $row['thong_tin_dinh_danh']),
            'email'    => $row['email'],
            'status'   => $row['status'],
            'created_at' => adminFormatDate($row['created_at']),
        ];
    }

    return $items;
}

function adminRecentPosts(PDO $pdo, int $limit = 5): array
{
    $stmt = $pdo->query(
        "SELECT b.id, b.noi_dung, b.created_at, u.username, h.thong_tin_dinh_danh
         FROM bai_dang b
         INNER JOIN users u ON u.id = b.user_id
         LEFT JOIN ho_so_ca_nhan h ON h.user_id = u.id
         ORDER BY b.created_at DESC
         LIMIT " . (int) $limit
    );

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $items[] = [
            'id'         => (int) $row['id'],
            'preview'    => adminTruncate($row['noi_dung'], 80),
            'author'     => bunnyDisplayName($row['username'], $row['thong_tin_dinh_danh']),
            'created_at' => adminFormatDate($row['created_at']),
        ];
    }

    return $items;
}

function adminRenderPagination(int $page, int $pages, array $queryParams = []): string
{
    if ($pages <= 1) {
        return '';
    }

    $html = '<nav class="admin-pagination"><ul class="pagination mb-0">';
    for ($i = 1; $i <= $pages; $i++) {
        $queryParams['page'] = $i;
        $qs = http_build_query($queryParams);
        $active = $i === $page ? ' active' : '';
        $html .= '<li class="page-item' . $active . '"><a class="page-link" href="?' . htmlspecialchars($qs) . '">' . $i . '</a></li>';
    }
    $html .= '</ul></nav>';

    return $html;
}

// ─── Hang Thỏ ───────────────────────────────────────────────────────────────

function adminListHangTho(PDO $pdo, array $filters = [], int $page = 1, int $perPage = 15): array
{
    $page = max(1, $page);
    $perPage = max(5, min(50, $perPage));
    $offset = ($page - 1) * $perPage;

    $where = ['1=1'];
    $params = [];
    $q = trim($filters['q'] ?? '');
    if ($q !== '') {
        $where[] = 'h.ten_hang_tho LIKE :q';
        $params['q'] = '%' . $q . '%';
    }

    $whereSql = implode(' AND ', $where);
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM hang_tho h WHERE {$whereSql}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $sql = "
        SELECT h.id, h.ten_hang_tho, h.created_at,
               (SELECT COUNT(*) FROM user_hang_tho uht WHERE uht.hang_tho_id = h.id) AS member_count
        FROM hang_tho h
        WHERE {$whereSql}
        ORDER BY h.created_at DESC
        LIMIT {$perPage} OFFSET {$offset}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $items[] = [
            'id'           => (int) $row['id'],
            'ten_hang_tho' => $row['ten_hang_tho'],
            'member_count' => (int) $row['member_count'],
            'created_at'   => adminFormatDate($row['created_at']),
        ];
    }

    return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
}

function adminHandleHangThoAction(PDO $pdo, string $action, array $input): array
{
    if ($action === 'create_hang_tho') {
        $name = trim($input['ten_hang_tho'] ?? '');
        if ($name === '') {
            throw new InvalidArgumentException('Tên Hang Thỏ không được trống.');
        }
        $stmt = $pdo->prepare('INSERT INTO hang_tho (ten_hang_tho) VALUES (:name)');
        $stmt->execute(['name' => $name]);
        return ['message' => 'Đã tạo Hang Thỏ.'];
    }

    $id = (int) ($input['hang_tho_id'] ?? 0);

    if ($action === 'update_hang_tho') {
        $name = trim($input['ten_hang_tho'] ?? '');
        if ($name === '') {
            throw new InvalidArgumentException('Tên Hang Thỏ không được trống.');
        }
        $stmt = $pdo->prepare('UPDATE hang_tho SET ten_hang_tho = :name WHERE id = :id');
        $stmt->execute(['name' => $name, 'id' => $id]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Không tìm thấy Hang Thỏ.');
        }
        return ['message' => 'Đã cập nhật Hang Thỏ.'];
    }

    $del = $pdo->prepare('DELETE FROM hang_tho WHERE id = :id');
    $del->execute(['id' => $id]);
    if ($del->rowCount() === 0) {
        throw new RuntimeException('Không tìm thấy Hang Thỏ.');
    }
    return ['message' => 'Đã xóa Hang Thỏ.'];
}

// ─── Hashtag ────────────────────────────────────────────────────────────────

function adminNormalizeHashtag(string $tag): string
{
    $tag = trim($tag);
    if ($tag !== '' && $tag[0] !== '#') {
        $tag = '#' . $tag;
    }
    return $tag;
}

function adminListHashtags(PDO $pdo, array $filters = [], int $page = 1, int $perPage = 15): array
{
    $page = max(1, $page);
    $perPage = max(5, min(50, $perPage));
    $offset = ($page - 1) * $perPage;

    $where = ['1=1'];
    $params = [];
    $q = trim($filters['q'] ?? '');
    if ($q !== '') {
        $where[] = 'ten_hashtag LIKE :q';
        $params['q'] = '%' . $q . '%';
    }

    $whereSql = implode(' AND ', $where);
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM hashtag WHERE {$whereSql}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT id, ten_hashtag, created_at FROM hashtag WHERE {$whereSql} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}");
    $stmt->execute($params);

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $items[] = [
            'id'          => (int) $row['id'],
            'ten_hashtag' => $row['ten_hashtag'],
            'created_at'  => adminFormatDate($row['created_at']),
        ];
    }

    return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
}

function adminHandleHashtagAction(PDO $pdo, string $action, array $input): array
{
    if ($action === 'create_hashtag') {
        $tag = adminNormalizeHashtag((string) ($input['ten_hashtag'] ?? ''));
        if ($tag === '' || $tag === '#') {
            throw new InvalidArgumentException('Hashtag không được trống.');
        }
        $dup = $pdo->prepare('SELECT id FROM hashtag WHERE ten_hashtag = :tag LIMIT 1');
        $dup->execute(['tag' => $tag]);
        if ($dup->fetch()) {
            throw new RuntimeException('Hashtag đã tồn tại.');
        }
        $stmt = $pdo->prepare('INSERT INTO hashtag (ten_hashtag) VALUES (:tag)');
        $stmt->execute(['tag' => $tag]);
        return ['message' => 'Đã thêm hashtag.'];
    }

    $id = (int) ($input['hashtag_id'] ?? 0);

    if ($action === 'update_hashtag') {
        $tag = adminNormalizeHashtag((string) ($input['ten_hashtag'] ?? ''));
        if ($tag === '' || $tag === '#') {
            throw new InvalidArgumentException('Hashtag không được trống.');
        }
        $dup = $pdo->prepare('SELECT id FROM hashtag WHERE ten_hashtag = :tag AND id <> :id LIMIT 1');
        $dup->execute(['tag' => $tag, 'id' => $id]);
        if ($dup->fetch()) {
            throw new RuntimeException('Hashtag đã tồn tại.');
        }
        $stmt = $pdo->prepare('UPDATE hashtag SET ten_hashtag = :tag WHERE id = :id');
        $stmt->execute(['tag' => $tag, 'id' => $id]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Không tìm thấy hashtag.');
        }
        return ['message' => 'Đã cập nhật hashtag.'];
    }

    $del = $pdo->prepare('DELETE FROM hashtag WHERE id = :id');
    $del->execute(['id' => $id]);
    if ($del->rowCount() === 0) {
        throw new RuntimeException('Không tìm thấy hashtag.');
    }
    return ['message' => 'Đã xóa hashtag.'];
}

// ─── Tài liệu ───────────────────────────────────────────────────────────────

function adminListTaiLieu(PDO $pdo, array $filters = [], int $page = 1, int $perPage = 15): array
{
    $page = max(1, $page);
    $perPage = max(5, min(50, $perPage));
    $offset = ($page - 1) * $perPage;

    $where = ['1=1'];
    $params = [];
    $q = trim($filters['q'] ?? '');
    if ($q !== '') {
        $where[] = '(t.ten_tai_lieu LIKE :q OR u.username LIKE :q2)';
        $params['q'] = '%' . $q . '%';
        $params['q2'] = '%' . $q . '%';
    }

    $whereSql = implode(' AND ', $where);
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM tai_lieu t INNER JOIN users u ON u.id = t.user_id WHERE {$whereSql}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $sql = "
        SELECT t.id, t.user_id, t.ten_tai_lieu, t.file_url, t.created_at,
               u.username, h.thong_tin_dinh_danh
        FROM tai_lieu t
        INNER JOIN users u ON u.id = t.user_id
        LEFT JOIN ho_so_ca_nhan h ON h.user_id = u.id
        WHERE {$whereSql}
        ORDER BY t.created_at DESC
        LIMIT {$perPage} OFFSET {$offset}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $items[] = [
            'id'           => (int) $row['id'],
            'user_id'      => (int) $row['user_id'],
            'ten_tai_lieu' => $row['ten_tai_lieu'],
            'file_url'     => $row['file_url'],
            'author_name'  => bunnyDisplayName($row['username'], $row['thong_tin_dinh_danh']),
            'author_username' => $row['username'],
            'created_at'   => adminFormatDate($row['created_at']),
        ];
    }

    return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
}

function adminHandleTaiLieuAction(PDO $pdo, string $action, array $input): array
{
    if ($action === 'create_tai_lieu') {
        $userId = (int) ($input['user_id'] ?? 0);
        $name = trim($input['ten_tai_lieu'] ?? '');
        $url = trim($input['file_url'] ?? '');
        if ($userId <= 0 || $name === '' || $url === '') {
            throw new InvalidArgumentException('Người tải, tên và URL tài liệu là bắt buộc.');
        }
        $check = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
        $check->execute(['id' => $userId]);
        if (!$check->fetch()) {
            throw new RuntimeException('Người dùng không tồn tại.');
        }
        $stmt = $pdo->prepare('INSERT INTO tai_lieu (user_id, ten_tai_lieu, file_url) VALUES (:uid, :name, :url)');
        $stmt->execute(['uid' => $userId, 'name' => $name, 'url' => $url]);
        return ['message' => 'Đã thêm tài liệu.'];
    }

    $id = (int) ($input['tai_lieu_id'] ?? 0);

    if ($action === 'update_tai_lieu') {
        $name = trim($input['ten_tai_lieu'] ?? '');
        $url = trim($input['file_url'] ?? '');
        if ($name === '' || $url === '') {
            throw new InvalidArgumentException('Tên và URL tài liệu là bắt buộc.');
        }
        $stmt = $pdo->prepare('UPDATE tai_lieu SET ten_tai_lieu = :name, file_url = :url WHERE id = :id');
        $stmt->execute(['name' => $name, 'url' => $url, 'id' => $id]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Không tìm thấy tài liệu.');
        }
        return ['message' => 'Đã cập nhật tài liệu.'];
    }

    $del = $pdo->prepare('DELETE FROM tai_lieu WHERE id = :id');
    $del->execute(['id' => $id]);
    if ($del->rowCount() === 0) {
        throw new RuntimeException('Không tìm thấy tài liệu.');
    }
    return ['message' => 'Đã xóa tài liệu.'];
}

// ─── Câu hỏi & Bộ đề ────────────────────────────────────────────────────────

function adminListBoDe(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, ten_bo_de FROM bo_de ORDER BY id ASC');
    return $stmt->fetchAll();
}

function adminListCauHoi(PDO $pdo, array $filters = [], int $page = 1, int $perPage = 15): array
{
    $page = max(1, $page);
    $perPage = max(5, min(50, $perPage));
    $offset = ($page - 1) * $perPage;

    $where = ['1=1'];
    $params = [];
    $q = trim($filters['q'] ?? '');
    if ($q !== '') {
        $where[] = 'c.noi_dung LIKE :q';
        $params['q'] = '%' . $q . '%';
    }
    $boDeId = (int) ($filters['bo_de_id'] ?? 0);
    if ($boDeId > 0) {
        $where[] = 'c.bo_de_id = :bo_de_id';
        $params['bo_de_id'] = $boDeId;
    }

    $whereSql = implode(' AND ', $where);
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM cau_hoi c WHERE {$whereSql}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $sql = "
        SELECT c.*, b.ten_bo_de
        FROM cau_hoi c
        INNER JOIN bo_de b ON b.id = c.bo_de_id
        WHERE {$whereSql}
        ORDER BY c.id DESC
        LIMIT {$perPage} OFFSET {$offset}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $items[] = [
            'id'          => (int) $row['id'],
            'bo_de_id'    => (int) $row['bo_de_id'],
            'ten_bo_de'   => $row['ten_bo_de'] ?: ('Bộ đề #' . $row['bo_de_id']),
            'noi_dung'    => $row['noi_dung'],
            'preview'     => adminTruncate($row['noi_dung'], 100),
            'lua_chon_a'  => $row['lua_chon_a'],
            'lua_chon_b'  => $row['lua_chon_b'],
            'lua_chon_c'  => $row['lua_chon_c'],
            'lua_chon_d'  => $row['lua_chon_d'],
            'dap_an_dung' => strtoupper($row['dap_an_dung']),
            'created_at'  => adminFormatDate($row['created_at']),
        ];
    }

    return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
}

function adminValidateCauHoiInput(array $input): array
{
    $boDeId = (int) ($input['bo_de_id'] ?? 0);
    $noiDung = trim($input['noi_dung'] ?? '');
    $a = trim($input['lua_chon_a'] ?? '');
    $b = trim($input['lua_chon_b'] ?? '');
    $c = trim($input['lua_chon_c'] ?? '');
    $d = trim($input['lua_chon_d'] ?? '');
    $dapAn = strtoupper(trim($input['dap_an_dung'] ?? ''));

    if ($boDeId <= 0 || $noiDung === '' || $a === '' || $b === '' || $c === '' || $d === '') {
        throw new InvalidArgumentException('Vui lòng điền đầy đủ thông tin câu hỏi.');
    }
    if (!in_array($dapAn, ['A', 'B', 'C', 'D'], true)) {
        throw new InvalidArgumentException('Đáp án đúng phải là A, B, C hoặc D.');
    }

    return compact('boDeId', 'noiDung', 'a', 'b', 'c', 'd', 'dapAn');
}

function adminHandleCauHoiAction(PDO $pdo, string $action, array $input): array
{
    if ($action === 'create_cau_hoi') {
        $data = adminValidateCauHoiInput($input);
        $check = $pdo->prepare('SELECT id FROM bo_de WHERE id = :id LIMIT 1');
        $check->execute(['id' => $data['boDeId']]);
        if (!$check->fetch()) {
            throw new RuntimeException('Bộ đề không tồn tại.');
        }
        $stmt = $pdo->prepare(
            'INSERT INTO cau_hoi (bo_de_id, noi_dung, lua_chon_a, lua_chon_b, lua_chon_c, lua_chon_d, dap_an_dung)
             VALUES (:bo_de_id, :noi_dung, :a, :b, :c, :d, :dap_an)'
        );
        $stmt->execute([
            'bo_de_id' => $data['boDeId'],
            'noi_dung' => $data['noiDung'],
            'a'        => $data['a'],
            'b'        => $data['b'],
            'c'        => $data['c'],
            'd'        => $data['d'],
            'dap_an'   => $data['dapAn'],
        ]);
        return ['message' => 'Đã thêm câu hỏi.'];
    }

    $id = (int) ($input['cau_hoi_id'] ?? 0);

    if ($action === 'update_cau_hoi') {
        $data = adminValidateCauHoiInput($input);
        $stmt = $pdo->prepare(
            'UPDATE cau_hoi SET bo_de_id = :bo_de_id, noi_dung = :noi_dung,
             lua_chon_a = :a, lua_chon_b = :b, lua_chon_c = :c, lua_chon_d = :d, dap_an_dung = :dap_an
             WHERE id = :id'
        );
        $stmt->execute([
            'bo_de_id' => $data['boDeId'],
            'noi_dung' => $data['noiDung'],
            'a'        => $data['a'],
            'b'        => $data['b'],
            'c'        => $data['c'],
            'd'        => $data['d'],
            'dap_an'   => $data['dapAn'],
            'id'       => $id,
        ]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Không tìm thấy câu hỏi.');
        }
        return ['message' => 'Đã cập nhật câu hỏi.'];
    }

    $del = $pdo->prepare('DELETE FROM cau_hoi WHERE id = :id');
    $del->execute(['id' => $id]);
    if ($del->rowCount() === 0) {
        throw new RuntimeException('Không tìm thấy câu hỏi.');
    }
    return ['message' => 'Đã xóa câu hỏi.'];
}

// ─── Sự kiện ────────────────────────────────────────────────────────────────

function adminListSuKien(PDO $pdo, array $filters = [], int $page = 1, int $perPage = 15): array
{
    $page = max(1, $page);
    $perPage = max(5, min(50, $perPage));
    $offset = ($page - 1) * $perPage;

    $where = ['1=1'];
    $params = [];
    $q = trim($filters['q'] ?? '');
    if ($q !== '') {
        $where[] = 's.tieu_de LIKE :q';
        $params['q'] = '%' . $q . '%';
    }

    $whereSql = implode(' AND ', $where);
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM su_kien s WHERE {$whereSql}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $sql = "
        SELECT s.id, s.tieu_de, s.thoi_gian, s.created_at,
               (SELECT COUNT(*) FROM thanh_vien_su_kien tv WHERE tv.su_kien_id = s.id) AS member_count
        FROM su_kien s
        WHERE {$whereSql}
        ORDER BY s.thoi_gian DESC
        LIMIT {$perPage} OFFSET {$offset}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $items[] = [
            'id'           => (int) $row['id'],
            'tieu_de'      => $row['tieu_de'],
            'thoi_gian'    => adminFormatDate($row['thoi_gian']),
            'thoi_gian_raw'=> $row['thoi_gian'],
            'member_count' => (int) $row['member_count'],
            'created_at'   => adminFormatDate($row['created_at']),
        ];
    }

    return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
}

function adminHandleSuKienAction(PDO $pdo, string $action, array $input): array
{
    if ($action === 'create_su_kien') {
        $title = trim($input['tieu_de'] ?? '');
        $time = trim($input['thoi_gian'] ?? '');
        if ($title === '' || $time === '') {
            throw new InvalidArgumentException('Tiêu đề và thời gian sự kiện là bắt buộc.');
        }
        $stmt = $pdo->prepare('INSERT INTO su_kien (tieu_de, thoi_gian) VALUES (:title, :time)');
        $stmt->execute(['title' => $title, 'time' => $time]);
        return ['message' => 'Đã tạo sự kiện.'];
    }

    $id = (int) ($input['su_kien_id'] ?? 0);

    if ($action === 'update_su_kien') {
        $title = trim($input['tieu_de'] ?? '');
        $time = trim($input['thoi_gian'] ?? '');
        if ($title === '' || $time === '') {
            throw new InvalidArgumentException('Tiêu đề và thời gian sự kiện là bắt buộc.');
        }
        $stmt = $pdo->prepare('UPDATE su_kien SET tieu_de = :title, thoi_gian = :time WHERE id = :id');
        $stmt->execute(['title' => $title, 'time' => $time, 'id' => $id]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Không tìm thấy sự kiện.');
        }
        return ['message' => 'Đã cập nhật sự kiện.'];
    }

    $del = $pdo->prepare('DELETE FROM su_kien WHERE id = :id');
    $del->execute(['id' => $id]);
    if ($del->rowCount() === 0) {
        throw new RuntimeException('Không tìm thấy sự kiện.');
    }
    return ['message' => 'Đã xóa sự kiện.'];
}

// ─── Báo cáo khiếu nại ─────────────────────────────────────────────────────

function adminListReports(PDO $pdo, array $filters = [], int $page = 1, int $perPage = 15): array
{
    $page = max(1, $page);
    $perPage = max(5, min(50, $perPage));
    $offset = ($page - 1) * $perPage;

    $where = ['1=1'];
    $params = [];
    $q = trim($filters['q'] ?? '');
    if ($q !== '') {
        $where[] = '(bc.ly_do LIKE :q OR ur.username LIKE :q2 OR ut.username LIKE :q3)';
        $params['q'] = '%' . $q . '%';
        $params['q2'] = '%' . $q . '%';
        $params['q3'] = '%' . $q . '%';
    }
    $status = trim($filters['trang_thai'] ?? '');
    if ($status !== '' && in_array($status, ['Pending', 'Reviewed', 'Resolved'], true)) {
        $where[] = 'bc.trang_thai = :trang_thai';
        $params['trang_thai'] = $status;
    }

    $whereSql = implode(' AND ', $where);
    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM bao_cao_vi_pham bc
         INNER JOIN users ur ON ur.id = bc.nguoi_bao_cao_id
         INNER JOIN users ut ON ut.id = bc.nguoi_bi_bao_cao_id
         WHERE {$whereSql}"
    );
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $sql = "
        SELECT bc.*,
               ur.username AS reporter_username, hr.thong_tin_dinh_danh AS reporter_name,
               ut.username AS target_username, ht.thong_tin_dinh_danh AS target_name,
               b.noi_dung AS post_content
        FROM bao_cao_vi_pham bc
        INNER JOIN users ur ON ur.id = bc.nguoi_bao_cao_id
        LEFT JOIN ho_so_ca_nhan hr ON hr.user_id = ur.id
        INNER JOIN users ut ON ut.id = bc.nguoi_bi_bao_cao_id
        LEFT JOIN ho_so_ca_nhan ht ON ht.user_id = ut.id
        INNER JOIN bai_dang b ON b.id = bc.bai_dang_id
        WHERE {$whereSql}
        ORDER BY bc.created_at DESC
        LIMIT {$perPage} OFFSET {$offset}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $items[] = [
            'id'              => (int) $row['id'],
            'bai_dang_id'     => (int) $row['bai_dang_id'],
            'ly_do'           => $row['ly_do'],
            'trang_thai'      => $row['trang_thai'],
            'reporter_name'   => bunnyDisplayName($row['reporter_username'], $row['reporter_name']),
            'target_name'     => bunnyDisplayName($row['target_username'], $row['target_name']),
            'post_preview'    => adminTruncate($row['post_content'], 120),
            'created_at'      => adminFormatDate($row['created_at']),
        ];
    }

    return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))];
}

function adminHandleReportAction(PDO $pdo, string $action, array $input): array
{
    $id = (int) ($input['report_id'] ?? 0);

    if ($action === 'update_report') {
        $status = trim($input['trang_thai'] ?? '');
        if (!in_array($status, ['Pending', 'Reviewed', 'Resolved'], true)) {
            throw new InvalidArgumentException('Trạng thái báo cáo không hợp lệ.');
        }
        $stmt = $pdo->prepare('UPDATE bao_cao_vi_pham SET trang_thai = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Không tìm thấy báo cáo.');
        }
        return ['message' => 'Đã cập nhật trạng thái báo cáo.'];
    }

    $del = $pdo->prepare('DELETE FROM bao_cao_vi_pham WHERE id = :id');
    $del->execute(['id' => $id]);
    if ($del->rowCount() === 0) {
        throw new RuntimeException('Không tìm thấy báo cáo.');
    }
    return ['message' => 'Đã xóa báo cáo.'];
}

function adminListUsersForSelect(PDO $pdo, int $limit = 200): array
{
    $stmt = $pdo->query(
        "SELECT u.id, u.username, h.thong_tin_dinh_danh
         FROM users u
         LEFT JOIN ho_so_ca_nhan h ON h.user_id = u.id
         ORDER BY u.username ASC
         LIMIT " . (int) $limit
    );
    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $items[] = [
            'id'   => (int) $row['id'],
            'name' => bunnyDisplayName($row['username'], $row['thong_tin_dinh_danh']),
        ];
    }
    return $items;
}
