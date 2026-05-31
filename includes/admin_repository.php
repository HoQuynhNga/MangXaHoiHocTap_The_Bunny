<?php
require_once __DIR__ . '/admin_helpers.php';

function adminGetDashboardStats(PDO $pdo): array
{
    $stats = [
        'users_total'   => 0,
        'users_active'  => 0,
        'users_banned'  => 0,
        'users_pending' => 0,
        'posts_total'   => 0,
        'posts_today'   => 0,
        'comments_total'=> 0,
        'reports_pending' => 0,
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
