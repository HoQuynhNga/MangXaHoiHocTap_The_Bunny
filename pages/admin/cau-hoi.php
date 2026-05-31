<?php
require_once '../../includes/admin_init.php';

[$pdo, $adminId] = adminInitPage('cau-hoi.php', ['q', 'bo_de_id', 'page']);

$filters = adminCollectFilters(['q', 'bo_de_id']);
$page = max(1, (int) ($_GET['page'] ?? 1));
$result = adminListCauHoi($pdo, $filters, $page);
$boDeList = adminListBoDe($pdo);

adminRenderLayoutStart('Quản lý câu hỏi', 'cau_hoi');
?>

<div class="admin-card">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <form method="get" class="admin-filters mb-0 flex-grow-1">
      <input type="search" name="q" class="form-control" placeholder="Tìm nội dung câu hỏi…" value="<?= htmlspecialchars($filters['q']) ?>" />
      <select name="bo_de_id" class="form-select">
        <option value="">Tất cả bộ đề</option>
        <?php foreach ($boDeList as $bd): ?>
        <option value="<?= (int) $bd['id'] ?>"<?= (string) $filters['bo_de_id'] === (string) $bd['id'] ? ' selected' : '' ?>>
          <?= htmlspecialchars($bd['ten_bo_de'] ?: ('Bộ đề #' . $bd['id'])) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i> Lọc</button>
      <a href="cau-hoi.php" class="btn btn-outline-secondary">Reset</a>
    </form>
    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createCauHoiModal"<?= $boDeList === [] ? ' disabled title="Cần có bộ đề trước"' : '' ?>>
      <i class="fa-solid fa-plus me-1"></i> Thêm câu hỏi
    </button>
  </div>

  <?php if ($boDeList === []): ?>
  <div class="alert alert-warning">Chưa có bộ đề trong hệ thống. Hãy tạo bộ đề qua Hang Thỏ hoặc import dữ liệu mẫu trước khi thêm câu hỏi.</div>
  <?php endif; ?>

  <div class="mb-3 text-muted"><?= number_format($result['total']) ?> câu hỏi</div>

  <div class="table-responsive">
    <table class="table admin-table align-middle">
      <thead>
        <tr>
          <th>Câu hỏi</th>
          <th>Bộ đề</th>
          <th>Đáp án</th>
          <th>Ngày tạo</th>
          <th>Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($result['items'] === []): ?>
        <tr><td colspan="5" class="text-center text-muted py-5">Chưa có câu hỏi</td></tr>
        <?php else: ?>
        <?php foreach ($result['items'] as $item): ?>
        <tr>
          <td>
            <div class="admin-post-preview" title="<?= htmlspecialchars($item['noi_dung']) ?>">
              #<?= $item['id'] ?> — <?= htmlspecialchars($item['preview']) ?>
            </div>
            <div class="small text-muted mt-1">
              A: <?= htmlspecialchars(adminTruncate($item['lua_chon_a'], 40)) ?> ·
              B: <?= htmlspecialchars(adminTruncate($item['lua_chon_b'], 40)) ?>
            </div>
          </td>
          <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($item['ten_bo_de']) ?></span></td>
          <td><span class="badge bg-success"><?= htmlspecialchars($item['dap_an_dung']) ?></span></td>
          <td><?= htmlspecialchars($item['created_at']) ?></td>
          <td>
            <div class="admin-actions">
              <button type="button" class="btn btn-sm btn-outline-primary btn-edit-cau-hoi" data-bs-toggle="modal" data-bs-target="#editCauHoiModal"
                data-id="<?= $item['id'] ?>"
                data-bo-de-id="<?= $item['bo_de_id'] ?>"
                data-noi-dung="<?= htmlspecialchars($item['noi_dung'], ENT_QUOTES) ?>"
                data-a="<?= htmlspecialchars($item['lua_chon_a'], ENT_QUOTES) ?>"
                data-b="<?= htmlspecialchars($item['lua_chon_b'], ENT_QUOTES) ?>"
                data-c="<?= htmlspecialchars($item['lua_chon_c'], ENT_QUOTES) ?>"
                data-d="<?= htmlspecialchars($item['lua_chon_d'], ENT_QUOTES) ?>"
                data-dap-an="<?= htmlspecialchars($item['dap_an_dung'], ENT_QUOTES) ?>">
                <i class="fa-solid fa-pen"></i>
              </button>
              <form method="post" class="d-inline" onsubmit="return confirm('Xóa câu hỏi này?');">
                <input type="hidden" name="action" value="delete_cau_hoi" />
                <input type="hidden" name="cau_hoi_id" value="<?= $item['id'] ?>" />
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

  <?= adminRenderPagination($result['page'], $result['pages'], array_filter($filters, fn($v) => $v !== '')) ?>
</div>

<?php
function adminRenderCauHoiFormFields(string $prefix, array $boDeList, ?array $defaults = null): void {
    $boDeId = $defaults['bo_de_id'] ?? '';
    $noiDung = $defaults['noi_dung'] ?? '';
    $a = $defaults['lua_chon_a'] ?? '';
    $b = $defaults['lua_chon_b'] ?? '';
    $c = $defaults['lua_chon_c'] ?? '';
    $d = $defaults['lua_chon_d'] ?? '';
    $dapAn = $defaults['dap_an_dung'] ?? 'A';
    ?>
    <div class="mb-3">
      <label class="form-label">Bộ đề</label>
      <select name="bo_de_id" id="<?= $prefix ?>BoDeId" class="form-select" required>
        <option value="">— Chọn bộ đề —</option>
        <?php foreach ($boDeList as $bd): ?>
        <option value="<?= (int) $bd['id'] ?>"<?= (string) $boDeId === (string) $bd['id'] ? ' selected' : '' ?>>
          <?= htmlspecialchars($bd['ten_bo_de'] ?: ('Bộ đề #' . $bd['id'])) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Nội dung câu hỏi</label>
      <textarea name="noi_dung" id="<?= $prefix ?>NoiDung" class="form-control" rows="3" required><?= htmlspecialchars($noiDung) ?></textarea>
    </div>
    <div class="row g-2 mb-3">
      <div class="col-md-6"><label class="form-label">Lựa chọn A</label><input type="text" name="lua_chon_a" id="<?= $prefix ?>A" class="form-control" required value="<?= htmlspecialchars($a) ?>" /></div>
      <div class="col-md-6"><label class="form-label">Lựa chọn B</label><input type="text" name="lua_chon_b" id="<?= $prefix ?>B" class="form-control" required value="<?= htmlspecialchars($b) ?>" /></div>
      <div class="col-md-6"><label class="form-label">Lựa chọn C</label><input type="text" name="lua_chon_c" id="<?= $prefix ?>C" class="form-control" required value="<?= htmlspecialchars($c) ?>" /></div>
      <div class="col-md-6"><label class="form-label">Lựa chọn D</label><input type="text" name="lua_chon_d" id="<?= $prefix ?>D" class="form-control" required value="<?= htmlspecialchars($d) ?>" /></div>
    </div>
    <div class="mb-0">
      <label class="form-label">Đáp án đúng</label>
      <select name="dap_an_dung" id="<?= $prefix ?>DapAn" class="form-select" required>
        <?php foreach (['A', 'B', 'C', 'D'] as $opt): ?>
        <option value="<?= $opt ?>"<?= $dapAn === $opt ? ' selected' : '' ?>><?= $opt ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php
}
?>

<div class="modal fade" id="createCauHoiModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="post" class="modal-content">
      <input type="hidden" name="action" value="create_cau_hoi" />
      <?= adminHiddenRedirects($filters, $page) ?>
      <div class="modal-header">
        <h5 class="modal-title">Thêm câu hỏi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body"><?php adminRenderCauHoiFormFields('create', $boDeList); ?></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="submit" class="btn btn-success">Thêm</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="editCauHoiModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="post" class="modal-content">
      <input type="hidden" name="action" value="update_cau_hoi" />
      <input type="hidden" name="cau_hoi_id" id="editCauHoiId" />
      <?= adminHiddenRedirects($filters, $page) ?>
      <div class="modal-header">
        <h5 class="modal-title">Chỉnh sửa câu hỏi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="editCauHoiBody">
        <?php adminRenderCauHoiFormFields('edit', $boDeList); ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="submit" class="btn btn-primary">Lưu</button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('editCauHoiModal').addEventListener('show.bs.modal', function (e) {
  var btn = e.relatedTarget;
  document.getElementById('editCauHoiId').value = btn.getAttribute('data-id');
  document.getElementById('editBoDeId').value = btn.getAttribute('data-bo-de-id');
  document.getElementById('editNoiDung').value = btn.getAttribute('data-noi-dung');
  document.getElementById('editA').value = btn.getAttribute('data-a');
  document.getElementById('editB').value = btn.getAttribute('data-b');
  document.getElementById('editC').value = btn.getAttribute('data-c');
  document.getElementById('editD').value = btn.getAttribute('data-d');
  document.getElementById('editDapAn').value = btn.getAttribute('data-dap-an');
});
</script>

<?php adminRenderLayoutEnd(); ?>
