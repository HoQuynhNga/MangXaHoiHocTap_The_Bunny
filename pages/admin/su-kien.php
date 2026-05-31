<?php
require_once '../../includes/admin_init.php';

[$pdo, $adminId] = adminInitPage('su-kien.php', ['q', 'page']);

$filters = adminCollectFilters(['q']);
$page = max(1, (int) ($_GET['page'] ?? 1));
$result = adminListSuKien($pdo, $filters, $page);

adminRenderLayoutStart('Quản lý sự kiện', 'su_kien');
?>

<div class="admin-card">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <form method="get" class="admin-filters mb-0 flex-grow-1">
      <input type="search" name="q" class="form-control" placeholder="Tìm tiêu đề sự kiện…" value="<?= htmlspecialchars($filters['q']) ?>" />
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-search me-1"></i> Tìm</button>
      <a href="su-kien.php" class="btn btn-outline-secondary">Reset</a>
    </form>
    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createSuKienModal">
      <i class="fa-solid fa-plus me-1"></i> Tạo sự kiện
    </button>
  </div>

  <div class="mb-3 text-muted"><?= number_format($result['total']) ?> sự kiện</div>

  <div class="table-responsive">
    <table class="table admin-table align-middle">
      <thead>
        <tr>
          <th>ID</th>
          <th>Tiêu đề</th>
          <th>Thời gian</th>
          <th>Tham gia</th>
          <th>Ngày tạo</th>
          <th>Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($result['items'] === []): ?>
        <tr><td colspan="6" class="text-center text-muted py-5">Chưa có sự kiện</td></tr>
        <?php else: ?>
        <?php foreach ($result['items'] as $item): ?>
        <tr>
          <td class="fw-bold">#<?= $item['id'] ?></td>
          <td><?= htmlspecialchars($item['tieu_de']) ?></td>
          <td><?= htmlspecialchars($item['thoi_gian']) ?></td>
          <td><span class="badge bg-light text-dark border"><?= number_format($item['member_count']) ?> người</span></td>
          <td><?= htmlspecialchars($item['created_at']) ?></td>
          <td>
            <div class="admin-actions">
              <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editSuKienModal"
                data-id="<?= $item['id'] ?>"
                data-title="<?= htmlspecialchars($item['tieu_de'], ENT_QUOTES) ?>"
                data-time="<?= htmlspecialchars($item['thoi_gian_raw'], ENT_QUOTES) ?>">
                <i class="fa-solid fa-pen"></i>
              </button>
              <form method="post" class="d-inline" onsubmit="return confirm('Xóa sự kiện này? Mọi đăng ký tham gia cũng bị xóa.');">
                <input type="hidden" name="action" value="delete_su_kien" />
                <input type="hidden" name="su_kien_id" value="<?= $item['id'] ?>" />
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

<div class="modal fade" id="createSuKienModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <input type="hidden" name="action" value="create_su_kien" />
      <?= adminHiddenRedirects($filters, $page) ?>
      <div class="modal-header">
        <h5 class="modal-title">Tạo sự kiện mới</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Tiêu đề</label>
          <input type="text" name="tieu_de" class="form-control" required placeholder="VD: Workshop UI/UX 2026" />
        </div>
        <div class="mb-0">
          <label class="form-label">Thời gian diễn ra</label>
          <input type="datetime-local" name="thoi_gian" class="form-control" required />
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="submit" class="btn btn-success">Tạo</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="editSuKienModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <input type="hidden" name="action" value="update_su_kien" />
      <input type="hidden" name="su_kien_id" id="editSuKienId" />
      <?= adminHiddenRedirects($filters, $page) ?>
      <div class="modal-header">
        <h5 class="modal-title">Chỉnh sửa sự kiện</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Tiêu đề</label>
          <input type="text" name="tieu_de" id="editSuKienTitle" class="form-control" required />
        </div>
        <div class="mb-0">
          <label class="form-label">Thời gian diễn ra</label>
          <input type="datetime-local" name="thoi_gian" id="editSuKienTime" class="form-control" required />
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
document.getElementById('editSuKienModal').addEventListener('show.bs.modal', function (e) {
  var btn = e.relatedTarget;
  document.getElementById('editSuKienId').value = btn.getAttribute('data-id');
  document.getElementById('editSuKienTitle').value = btn.getAttribute('data-title');
  var raw = btn.getAttribute('data-time') || '';
  if (raw.length >= 16) {
    document.getElementById('editSuKienTime').value = raw.replace(' ', 'T').slice(0, 16);
  }
});
</script>

<?php adminRenderLayoutEnd(); ?>
