<?php
require_once '../../config/config.php';
require_once '../../config/db_module.php';
require_once '../../includes/admin_helpers.php';
require_once '../../includes/admin_repository.php';

adminRequireRole();

$pdo = getPdo();
$adminId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        adminHandleAction($pdo, $adminId, $action, $_POST);
        adminFlashSet('success', 'Thao tác thành công.');
    } catch (Throwable $e) {
        adminFlashSet('danger', $e->getMessage());
    }
    $redirectQs = http_build_query(array_filter([
        'q'         => $_POST['redirect_q'] ?? '',
        'status'    => $_POST['redirect_status'] ?? '',
        'user_type' => $_POST['redirect_user_type'] ?? '',
        'page'      => $_POST['redirect_page'] ?? '',
    ]));
    adminRedirect('users.php' . ($redirectQs ? '?' . $redirectQs : ''));
}

$filters = [
    'q'         => trim($_GET['q'] ?? ''),
    'status'    => trim($_GET['status'] ?? ''),
    'user_type' => trim($_GET['user_type'] ?? ''),
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$result = adminListUsers($pdo, $filters, $page);

adminRenderLayoutStart('Quản lý người dùng', 'users');
?>

<div class="admin-card">
  <form method="get" class="admin-filters">
    <input type="search" name="q" class="form-control" placeholder="Tìm username, email…" value="<?= htmlspecialchars($filters['q']) ?>" />
    <select name="status" class="form-select">
      <option value="">Tất cả trạng thái</option>
      <?php foreach (['Active', 'Banned', 'Pending'] as $st): ?>
      <option value="<?= $st ?>"<?= $filters['status'] === $st ? ' selected' : '' ?>><?= $st ?></option>
      <?php endforeach; ?>
    </select>
    <select name="user_type" class="form-select">
      <option value="">Tất cả vai trò</option>
      <?php foreach (['hoc_sinh' => 'Học sinh', 'sinh_vien' => 'Sinh viên', 'giao_vien' => 'Giáo viên', 'quan_tri_vien' => 'Quản trị viên'] as $val => $label): ?>
      <option value="<?= $val ?>"<?= $filters['user_type'] === $val ? ' selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i> Lọc</button>
    <a href="users.php" class="btn btn-outline-secondary">Reset</a>
  </form>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted"><?= number_format($result['total']) ?> người dùng</span>
  </div>

  <div class="table-responsive">
    <table class="table admin-table align-middle">
      <thead>
        <tr>
          <th>Người dùng</th>
          <th>Vai trò</th>
          <th>Trạng thái</th>
          <th>Bài viết</th>
          <th>Ngày tạo</th>
          <th>Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($result['items'] === []): ?>
        <tr><td colspan="6" class="text-center text-muted py-5">Không tìm thấy người dùng</td></tr>
        <?php else: ?>
        <?php foreach ($result['items'] as $user): ?>
        <tr>
          <td>
            <div class="admin-user-cell">
              <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="" />
              <div class="meta">
                <div class="name"><?= htmlspecialchars($user['display_name']) ?></div>
                <div class="sub">@<?= htmlspecialchars($user['username']) ?> · <?= htmlspecialchars($user['email']) ?></div>
              </div>
            </div>
          </td>
          <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($user['user_type_label']) ?></span></td>
          <td>
            <span class="badge bg-<?= adminStatusBadge($user['status']) ?>">
              <?= htmlspecialchars($user['status']) ?>
              <?= $user['is_online'] ? ' · Online' : '' ?>
            </span>
          </td>
          <td><?= number_format($user['post_count']) ?></td>
          <td><?= htmlspecialchars($user['created_at']) ?></td>
          <td>
            <div class="admin-actions">
              <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editUserModal"
                data-id="<?= $user['id'] ?>"
                data-username="<?= htmlspecialchars($user['username'], ENT_QUOTES) ?>"
                data-email="<?= htmlspecialchars($user['email'], ENT_QUOTES) ?>"
                data-status="<?= htmlspecialchars($user['status'], ENT_QUOTES) ?>"
                data-user-type="<?= htmlspecialchars($user['user_type'], ENT_QUOTES) ?>">
                <i class="fa-solid fa-pen"></i>
              </button>
              <?php if ($user['id'] !== $adminId): ?>
              <form method="post" class="d-inline" onsubmit="return confirm('Xóa người dùng này? Hành động không thể hoàn tác.');">
                <input type="hidden" name="action" value="delete_user" />
                <input type="hidden" name="user_id" value="<?= $user['id'] ?>" />
                <input type="hidden" name="redirect_q" value="<?= htmlspecialchars($filters['q']) ?>" />
                <input type="hidden" name="redirect_status" value="<?= htmlspecialchars($filters['status']) ?>" />
                <input type="hidden" name="redirect_user_type" value="<?= htmlspecialchars($filters['user_type']) ?>" />
                <input type="hidden" name="redirect_page" value="<?= $page ?>" />
                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?= adminRenderPagination($result['page'], $result['pages'], array_filter($filters)) ?>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <input type="hidden" name="action" value="update_user" />
      <input type="hidden" name="user_id" id="editUserId" />
      <input type="hidden" name="redirect_q" value="<?= htmlspecialchars($filters['q']) ?>" />
      <input type="hidden" name="redirect_status" value="<?= htmlspecialchars($filters['status']) ?>" />
      <input type="hidden" name="redirect_user_type" value="<?= htmlspecialchars($filters['user_type']) ?>" />
      <input type="hidden" name="redirect_page" value="<?= $page ?>" />
      <div class="modal-header">
        <h5 class="modal-title">Chỉnh sửa người dùng</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input type="text" name="username" id="editUsername" class="form-control" required />
        </div>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" id="editEmail" class="form-control" required />
        </div>
        <div class="mb-3">
          <label class="form-label">Vai trò</label>
          <select name="user_type" id="editUserType" class="form-select">
            <option value="hoc_sinh">Học sinh</option>
            <option value="sinh_vien">Sinh viên</option>
            <option value="giao_vien">Giáo viên</option>
            <option value="quan_tri_vien">Quản trị viên</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Trạng thái</label>
          <select name="status" id="editStatus" class="form-select">
            <option value="Active">Active</option>
            <option value="Pending">Pending</option>
            <option value="Banned">Banned</option>
          </select>
        </div>
        <div class="mb-0">
          <label class="form-label">Mật khẩu mới (để trống nếu không đổi)</label>
          <input type="password" name="password" class="form-control" autocomplete="new-password" />
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('editUserModal').addEventListener('show.bs.modal', function (e) {
  var btn = e.relatedTarget;
  document.getElementById('editUserId').value = btn.getAttribute('data-id');
  document.getElementById('editUsername').value = btn.getAttribute('data-username');
  document.getElementById('editEmail').value = btn.getAttribute('data-email');
  document.getElementById('editStatus').value = btn.getAttribute('data-status');
  document.getElementById('editUserType').value = btn.getAttribute('data-user-type');
});
</script>

<?php adminRenderLayoutEnd(); ?>
