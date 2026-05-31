<?php
require_once '../../includes/admin_init.php';

[$pdo, $adminId] = adminInitPage('hang-tho.php', ['q', 'page']);

$filters = adminCollectFilters(['q']);
$page = max(1, (int) ($_GET['page'] ?? 1));
$result = adminListHangTho($pdo, $filters, $page);

adminRenderLayoutStart('Quản lý Hang Thỏ', 'hang_tho');
?>

<div class="admin-card">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <form method="get" class="admin-filters mb-0 flex-grow-1">
      <input type="search" name="q" class="form-control" placeholder="Tìm tên Hang Thỏ…" value="<?= htmlspecialchars($filters['q']) ?>" />
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-search me-1"></i> Tìm</button>
      <a href="hang-tho.php" class="btn btn-outline-secondary">Reset</a>
    </form>
    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createHangThoModal">
      <i class="fa-solid fa-plus me-1"></i> Tạo Hang Thỏ
    </button>
  </div>

  <div class="mb-3 text-muted"><?= number_format($result['total']) ?> nhóm</div>

  <div class="table-responsive">
    <table class="table admin-table align-middle">
      <thead>
        <tr>
          <th>ID</th>
          <th>Tên Hang Thỏ</th>
          <th>Thành viên</th>
          <th>Ngày tạo</th>
          <th>Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($result['items'] === []): ?>
        <tr><td colspan="5" class="text-center text-muted py-5">Chưa có Hang Thỏ</td></tr>
        <?php else: ?>
        <?php foreach ($result['items'] as $item): ?>
        <tr>
          <td class="fw-bold">#<?= $item['id'] ?></td>
          <td><?= htmlspecialchars($item['ten_hang_tho']) ?></td>
          <td><span class="badge bg-light text-dark border"><?= number_format($item['member_count']) ?></span></td>
          <td><?= htmlspecialchars($item['created_at']) ?></td>
          <td>
            <div class="admin-actions">
              <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editHangThoModal"
                data-id="<?= $item['id'] ?>"
                data-name="<?= htmlspecialchars($item['ten_hang_tho'], ENT_QUOTES) ?>">
                <i class="fa-solid fa-pen"></i>
              </button>
              <form method="post" class="d-inline" onsubmit="return confirm('Xóa Hang Thỏ này? Thành viên sẽ bị gỡ khỏi nhóm.');">
                <input type="hidden" name="action" value="delete_hang_tho" />
                <input type="hidden" name="hang_tho_id" value="<?= $item['id'] ?>" />
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

<div class="modal fade" id="createHangThoModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <input type="hidden" name="action" value="create_hang_tho" />
      <?= adminHiddenRedirects($filters, $page) ?>
      <div class="modal-header">
        <h5 class="modal-title">Tạo Hang Thỏ mới</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label">Tên Hang Thỏ</label>
        <input type="text" name="ten_hang_tho" class="form-control" required placeholder="VD: Hang Thỏ Vật Lý 12" />
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="submit" class="btn btn-success">Tạo</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="editHangThoModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <input type="hidden" name="action" value="update_hang_tho" />
      <input type="hidden" name="hang_tho_id" id="editHangThoId" />
      <?= adminHiddenRedirects($filters, $page) ?>
      <div class="modal-header">
        <h5 class="modal-title">Chỉnh sửa Hang Thỏ</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label">Tên Hang Thỏ</label>
        <input type="text" name="ten_hang_tho" id="editHangThoName" class="form-control" required />
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="submit" class="btn btn-primary">Lưu</button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('editHangThoModal').addEventListener('show.bs.modal', function (e) {
  var btn = e.relatedTarget;
  document.getElementById('editHangThoId').value = btn.getAttribute('data-id');
  document.getElementById('editHangThoName').value = btn.getAttribute('data-name');
});
</script>

<?php adminRenderLayoutEnd(); ?>
