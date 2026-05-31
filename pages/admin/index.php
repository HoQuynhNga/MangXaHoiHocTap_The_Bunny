<?php
require_once '../../config/config.php';
require_once '../../config/db_module.php';
require_once '../../includes/admin_helpers.php';
require_once '../../includes/admin_repository.php';

adminRequireRole();

$pdo = getPdo();
$stats = adminGetDashboardStats($pdo);
$recentUsers = adminRecentUsers($pdo);
$recentPosts = adminRecentPosts($pdo);

adminRenderLayoutStart('Tổng quan', 'dashboard');
?>

<div class="admin-stat-grid">
  <div class="admin-stat-card">
    <div class="admin-stat-card__label">Tổng người dùng</div>
    <div class="admin-stat-card__value purple"><?= number_format($stats['users_total']) ?></div>
  </div>
  <div class="admin-stat-card">
    <div class="admin-stat-card__label">Đang hoạt động</div>
    <div class="admin-stat-card__value green"><?= number_format($stats['users_active']) ?></div>
  </div>
  <div class="admin-stat-card">
    <div class="admin-stat-card__label">Bị khóa</div>
    <div class="admin-stat-card__value red"><?= number_format($stats['users_banned']) ?></div>
  </div>
  <div class="admin-stat-card">
    <div class="admin-stat-card__label">Chờ duyệt</div>
    <div class="admin-stat-card__value amber"><?= number_format($stats['users_pending']) ?></div>
  </div>
  <div class="admin-stat-card">
    <div class="admin-stat-card__label">Tổng bài viết</div>
    <div class="admin-stat-card__value purple"><?= number_format($stats['posts_total']) ?></div>
  </div>
  <div class="admin-stat-card">
    <div class="admin-stat-card__label">Bài viết hôm nay</div>
    <div class="admin-stat-card__value green"><?= number_format($stats['posts_today']) ?></div>
  </div>
  <div class="admin-stat-card">
    <div class="admin-stat-card__label">Bình luận</div>
    <div class="admin-stat-card__value purple"><?= number_format($stats['comments_total']) ?></div>
  </div>
  <div class="admin-stat-card">
    <div class="admin-stat-card__label">Báo cáo chờ xử lý</div>
    <div class="admin-stat-card__value amber"><?= number_format($stats['reports_pending']) ?></div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="admin-card h-100">
      <div class="admin-card__title d-flex justify-content-between align-items-center">
        <span>Người dùng mới</span>
        <a href="users.php" class="btn btn-sm btn-outline-primary">Xem tất cả</a>
      </div>
      <div class="table-responsive">
        <table class="table admin-table">
          <thead>
            <tr>
              <th>Tên</th>
              <th>Email</th>
              <th>Trạng thái</th>
              <th>Ngày tạo</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($recentUsers === []): ?>
            <tr><td colspan="4" class="text-muted text-center py-4">Chưa có dữ liệu</td></tr>
            <?php else: ?>
            <?php foreach ($recentUsers as $u): ?>
            <tr>
              <td><?= htmlspecialchars($u['name']) ?></td>
              <td><?= htmlspecialchars($u['email']) ?></td>
              <td><span class="badge bg-<?= adminStatusBadge($u['status']) ?>"><?= htmlspecialchars($u['status']) ?></span></td>
              <td><?= htmlspecialchars($u['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="admin-card h-100">
      <div class="admin-card__title d-flex justify-content-between align-items-center">
        <span>Bài viết mới</span>
        <a href="posts.php" class="btn btn-sm btn-outline-primary">Xem tất cả</a>
      </div>
      <div class="table-responsive">
        <table class="table admin-table">
          <thead>
            <tr>
              <th>Nội dung</th>
              <th>Tác giả</th>
              <th>Thời gian</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($recentPosts === []): ?>
            <tr><td colspan="3" class="text-muted text-center py-4">Chưa có bài viết</td></tr>
            <?php else: ?>
            <?php foreach ($recentPosts as $p): ?>
            <tr>
              <td class="admin-post-preview"><?= htmlspecialchars($p['preview']) ?></td>
              <td><?= htmlspecialchars($p['author']) ?></td>
              <td><?= htmlspecialchars($p['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php adminRenderLayoutEnd(); ?>
