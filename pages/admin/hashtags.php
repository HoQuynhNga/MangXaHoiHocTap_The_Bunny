<?php
require_once '../../includes/admin_init.php';

[$pdo, $adminId] = adminInitPage('hashtags.php', ['q', 'page']);

$filters = adminCollectFilters(['q']);
$page = max(1, (int) ($_GET['page'] ?? 1));
$result = adminListHashtags($pdo, $filters, $page);

adminRenderLayoutStart('Quản lý Hashtag', 'hashtags');
?>

<div class="admin-card">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <form method="get" class="admin-filters mb-0 flex-grow-1">
      <input type="search" name="q" class="form-control" placeholder="Tìm hashtag…" value="<?= htmlspecialchars($filters['q']) ?>" />
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-search me-1"></i> Tìm</button>
      <a href="hashtags.php" class="btn btn-outline-secondary">Reset</a>
    </form>
    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createHashtagModal">
      <i class="fa-solid fa-plus me-1"></i> Thêm hashtag
    </button>
  </div>

  <div class="mb-3 text-muted"><?= number_format($result['total']) ?> hashtag</div>

  <div class="table-responsive">
    <table class="table admin-table align-middle">
      <thead>
        <tr>
          <th>ID</th>
          <th>Hashtag</th>
          <th>Ngày tạo</th>
          <th>Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($result['items'] === []): ?>
        <tr><td colspan="4" class="text-center text-muted py-5">Chưa có hashtag</td></tr>
        <?php else: ?>
        <?php foreach ($result['items'] as $item): ?>
        <tr>
          <td class="fw-bold">#<?= $item['id'] ?></td>
          <td><span class="badge bg-primary bg-opacity-10 text-primary fs-6"><?= htmlspecialchars($item['ten_hashtag']) ?></span></td>
          <td><?= htmlspecialchars($item['created_at']) ?></td>
          <td>
            <div class="admin-actions">
              <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editHashtagModal"
                data-id="<?= $item['id'] ?>"
                data-tag="<?= htmlspecialchars($item['ten_hashtag'], ENT_QUOTES) ?>">
                <i class="fa-solid fa-pen"></i>
              </button>
              <form method="post" class="d-inline" onsubmit="return confirm('Xóa hashtag này?');">
                <input type="hidden" name="action" value="delete_hashtag" />
                <input type="hidden" name="hashtag_id" value="<?= $item['id'] ?>" />
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

<div class="modal fade" id="createHashtagModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <input type="hidden" name="action" value="create_hashtag" />
      <?= adminHiddenRedirects($filters, $page) ?>
      <div class="modal-header">
        <h5 class="modal-title">Thêm hashtag</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label">Tên hashtag</label>
        <input type="text" name="ten_hashtag" class="form-control" required placeholder="VD: #Toan12 hoặc Toan12" />
        <div class="form-text">Hệ thống tự thêm dấu # nếu thiếu.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="submit" class="btn btn-success">Thêm</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="editHashtagModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <input type="hidden" name="action" value="update_hashtag" />
      <input type="hidden" name="hashtag_id" id="editHashtagId" />
      <?= adminHiddenRedirects($filters, $page) ?>
      <div class="modal-header">
        <h5 class="modal-title">Chỉnh sửa hashtag</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label">Tên hashtag</label>
        <input type="text" name="ten_hashtag" id="editHashtagTag" class="form-control" required />
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="submit" class="btn btn-primary">Lưu</button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('editHashtagModal').addEventListener('show.bs.modal', function (e) {
  var btn = e.relatedTarget;
  document.getElementById('editHashtagId').value = btn.getAttribute('data-id');
  document.getElementById('editHashtagTag').value = btn.getAttribute('data-tag');
});
</script>

<?php adminRenderLayoutEnd(); ?>
