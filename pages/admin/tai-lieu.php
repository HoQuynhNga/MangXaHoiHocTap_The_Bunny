<?php
require_once '../../includes/admin_init.php';

[$pdo, $adminId] = adminInitPage('tai-lieu.php', ['q', 'page']);

$filters = adminCollectFilters(['q']);
$page = max(1, (int) ($_GET['page'] ?? 1));
$result = adminListTaiLieu($pdo, $filters, $page);
$userOptions = adminListUsersForSelect($pdo);

adminRenderLayoutStart('Quản lý kho tài liệu', 'tai_lieu');
?>

<div class="admin-card">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <form method="get" class="admin-filters mb-0 flex-grow-1">
      <input type="search" name="q" class="form-control" placeholder="Tìm tên tài liệu, người tải…" value="<?= htmlspecialchars($filters['q']) ?>" />
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-search me-1"></i> Tìm</button>
      <a href="tai-lieu.php" class="btn btn-outline-secondary">Reset</a>
    </form>
    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createTaiLieuModal">
      <i class="fa-solid fa-plus me-1"></i> Thêm tài liệu
    </button>
  </div>

  <div class="mb-3 text-muted"><?= number_format($result['total']) ?> tài liệu</div>

  <div class="table-responsive">
    <table class="table admin-table align-middle">
      <thead>
        <tr>
          <th>Tài liệu</th>
          <th>Người tải</th>
          <th>Liên kết</th>
          <th>Ngày tạo</th>
          <th>Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($result['items'] === []): ?>
        <tr><td colspan="5" class="text-center text-muted py-5">Chưa có tài liệu</td></tr>
        <?php else: ?>
        <?php foreach ($result['items'] as $item): ?>
        <tr>
          <td>
            <div class="fw-bold">#<?= $item['id'] ?> — <?= htmlspecialchars($item['ten_tai_lieu']) ?></div>
          </td>
          <td>
            <div class="name"><?= htmlspecialchars($item['author_name']) ?></div>
            <div class="sub text-muted small">@<?= htmlspecialchars($item['author_username']) ?></div>
          </td>
          <td>
            <a href="<?= htmlspecialchars($item['file_url']) ?>" target="_blank" rel="noopener" class="text-primary text-truncate d-inline-block" style="max-width:200px">
              <?= htmlspecialchars($item['file_url']) ?>
            </a>
          </td>
          <td><?= htmlspecialchars($item['created_at']) ?></td>
          <td>
            <div class="admin-actions">
              <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editTaiLieuModal"
                data-id="<?= $item['id'] ?>"
                data-name="<?= htmlspecialchars($item['ten_tai_lieu'], ENT_QUOTES) ?>"
                data-url="<?= htmlspecialchars($item['file_url'], ENT_QUOTES) ?>">
                <i class="fa-solid fa-pen"></i>
              </button>
              <form method="post" class="d-inline" onsubmit="return confirm('Xóa tài liệu này?');">
                <input type="hidden" name="action" value="delete_tai_lieu" />
                <input type="hidden" name="tai_lieu_id" value="<?= $item['id'] ?>" />
                <?= adminHiddenRedirects($filters, $page) ?>
                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
              </form>
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

<div class="modal fade" id="createTaiLieuModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <input type="hidden" name="action" value="create_tai_lieu" />
      <?= adminHiddenRedirects($filters, $page) ?>
      <div class="modal-header">
        <h5 class="modal-title">Thêm tài liệu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Người tải</label>
          <select name="user_id" class="form-select" required>
            <option value="">— Chọn người dùng —</option>
            <?php foreach ($userOptions as $u): ?>
            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Tên tài liệu</label>
          <input type="text" name="ten_tai_lieu" class="form-control" required />
        </div>
        <div class="mb-0">
          <label class="form-label">URL / đường dẫn file</label>
          <input type="text" name="file_url" class="form-control" required placeholder="../uploads/tai-lieu/file.pdf" />
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="submit" class="btn btn-success">Thêm</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="editTaiLieuModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <input type="hidden" name="action" value="update_tai_lieu" />
      <input type="hidden" name="tai_lieu_id" id="editTaiLieuId" />
      <?= adminHiddenRedirects($filters, $page) ?>
      <div class="modal-header">
        <h5 class="modal-title">Chỉnh sửa tài liệu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Tên tài liệu</label>
          <input type="text" name="ten_tai_lieu" id="editTaiLieuName" class="form-control" required />
        </div>
        <div class="mb-0">
          <label class="form-label">URL / đường dẫn file</label>
          <input type="text" name="file_url" id="editTaiLieuUrl" class="form-control" required />
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="submit" class="btn btn-primary">Lưu</button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('editTaiLieuModal').addEventListener('show.bs.modal', function (e) {
  var btn = e.relatedTarget;
  document.getElementById('editTaiLieuId').value = btn.getAttribute('data-id');
  document.getElementById('editTaiLieuName').value = btn.getAttribute('data-name');
  document.getElementById('editTaiLieuUrl').value = btn.getAttribute('data-url');
});
</script>

<?php adminRenderLayoutEnd(); ?>
