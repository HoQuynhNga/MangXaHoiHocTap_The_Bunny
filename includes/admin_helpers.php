<?php
require_once __DIR__ . '/../config/db_module.php';
require_once __DIR__ . '/bunny_helpers.php';

function adminUserTypeLabel(string $type): string
{
    return match ($type) {
        'hoc_sinh'      => 'Học sinh',
        'sinh_vien'     => 'Sinh viên',
        'giao_vien'     => 'Giáo viên',
        'quan_tri_vien' => 'Quản trị viên',
        default         => $type,
    };
}

function adminIsAdmin(): bool
{
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'quan_tri_vien';
}

function adminRequireLogin(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../../index.php');
        exit;
    }
}

function adminRequireRole(): void
{
    adminRequireLogin();
    if (!adminIsAdmin()) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="vi"><head><meta charset="utf-8"><title>Từ chối truy cập</title>';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>';
        echo '<body class="bg-light"><div class="container py-5"><div class="alert alert-danger">';
        echo '<h1 class="h4">Không có quyền truy cập</h1>';
        echo '<p>Chỉ tài khoản quản trị viên mới vào được khu vực này.</p>';
        echo '<a href="../trang-chu.php" class="btn btn-primary">Về trang chủ</a>';
        echo '</div></div></body></html>';
        exit;
    }
}

function adminStatusBadge(string $status): string
{
    return match ($status) {
        'Active'  => 'success',
        'Banned'  => 'danger',
        'Pending' => 'warning',
        default   => 'secondary',
    };
}

function adminFlashGet(): ?array
{
    if (empty($_SESSION['admin_flash'])) {
        return null;
    }
    $flash = $_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);
    return $flash;
}

function adminFlashSet(string $type, string $message): void
{
    $_SESSION['admin_flash'] = ['type' => $type, 'message' => $message];
}

function adminRedirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function adminTruncate(string $text, int $length = 120): string
{
    $text = trim(strip_tags($text));
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . '…';
}

function adminFormatDate(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '—';
    }
    $ts = strtotime($datetime);
    return $ts ? date('d/m/Y H:i', $ts) : $datetime;
}

function adminRenderLayoutStart(string $title, string $activeNav): void
{
    $flash = adminFlashGet();
    ?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($title) ?> — Admin The Bunny</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="../../assets/css/admin.css" rel="stylesheet" />
</head>
<body class="admin-body">
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <a href="index.php" class="admin-brand"><i class="fa-solid fa-carrot"></i> The Bunny Admin</a>
      <nav class="admin-nav">
        <a href="index.php" class="admin-nav__link<?= $activeNav === 'dashboard' ? ' active' : '' ?>"><i class="fa-solid fa-gauge-high"></i> Tổng quan</a>
        <a href="users.php" class="admin-nav__link<?= $activeNav === 'users' ? ' active' : '' ?>"><i class="fa-solid fa-users"></i> Người dùng</a>
        <a href="posts.php" class="admin-nav__link<?= $activeNav === 'posts' ? ' active' : '' ?>"><i class="fa-solid fa-newspaper"></i> Bài viết</a>
      </nav>
      <div class="admin-sidebar__foot">
        <a href="../trang-chu.php" class="admin-nav__link"><i class="fa-solid fa-arrow-left"></i> Về website</a>
        <a href="../../models/db_xulydangxuat.php" class="admin-nav__link text-danger"><i class="fa-solid fa-right-from-bracket"></i> Đăng xuất</a>
      </div>
    </aside>
    <main class="admin-main">
      <header class="admin-topbar">
        <div>
          <h1 class="admin-topbar__title"><?= htmlspecialchars($title) ?></h1>
          <p class="admin-topbar__sub mb-0">Xin chào, <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></strong></p>
        </div>
      </header>
      <?php if ($flash): ?>
      <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show admin-flash" role="alert">
        <?= htmlspecialchars($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
      </div>
      <?php endif; ?>
    <?php
}

function adminRenderLayoutEnd(): void
{
    ?>
    </main>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
    <?php
}
