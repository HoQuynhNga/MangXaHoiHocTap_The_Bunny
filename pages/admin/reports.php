<?php
require_once '../../includes/admin_init.php';

[$pdo, $adminId] = adminInitPage('reports.php', ['q', 'trang_thai', 'page']);

$filters = adminCollectFilters(['q', 'trang_thai']);
$page = max(1, (int) ($_GET['page'] ?? 1));
$result = adminListReports($pdo, $filters, $page);

adminRenderLayoutStart('Quản lý báo cáo khiếu nại', 'reports');
?>

<div class="admin-card">
  <form method="get" class="admin-filters">
    <input type="search" name="q" class="form-control" placeholder="Tìm lý do, người báo cáo…" value="<?= htmlspecialchars($filters['q']) ?>" />
    <select name="trang_thai" class="form-select">
      <option value="">Tất cả trạng thái</option>
      <?php foreach (['Pending' => 'Chờ xử lý', 'Reviewed' => 'Đang xem xét', 'Resolved' => 'Đã xử lý'] as $val => $label): ?>
      <option value="<?= $val ?>"<?= $filters['trang_thai'] === $val ? ' selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i> Lọc</button>
    <a href="reports.php" class="btn btn-outline-secondary">Reset</a>
  </form>

  <div class="mb-3 text-muted"><?= number_format($result['total']) ?> báo cáo</div>

  <div class="table-responsive">
    <table class="table admin-table align-middle">
      <thead>
        <tr>
          <th>Báo cáo</th>
          <th>Bài viết</th>
          <th>Người bị báo cáo</th>
          <th>Trạng thái</th>
          <th>Thời gian</th>
          <th>Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($result['items'] === []): ?>
        <tr><td colspan="6" class="text-center text-muted py-5">Không có báo cáo</td></tr>
        <?php else: ?>
        <?php foreach ($result['items'] as $item): ?>
        <tr>
          <td>
            <div class="fw-bold small text-muted">#<?= $item['id'] ?> · <?= htmlspecialchars($item['reporter_name']) ?></div>
            <div class="admin-post-preview"><?= htmlspecialchars($item['ly_do']) ?></div>
          </td>
          <td>
            <a href="../chi-tiet-bai-dang.php?id=<?= $item['bai_dang_id'] ?>" class="text-primary text-decoration-none" target="_blank">
              #<?= $item['bai_dang_id'] ?> — <?= htmlspecialchars($item['post_preview']) ?>
            </a>
          </td>
          <td><?= htmlspecialchars($item['target_name']) ?></td>
          <td>
            <span class="badge bg-<?= adminStatusBadge($item['trang_thai']) ?>">
              <?= htmlspecialchars(adminReportStatusLabel($item['trang_thai'])) ?>
            </span>
          </td>
          <td><?= htmlspecialchars($item['created_at']) ?></td>
          <td>
            <div class="admin-actions">
              <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editReportModal"
                data-id="<?= $item['id'] ?>"
                data-status="<?= htmlspecialchars($item['trang_thai'], ENT_QUOTES) ?>"
                data-ly-do="<?= htmlspecialchars($item['ly_do'], ENT_QUOTES) ?>">
                <i class="fa-solid fa-gavel"></i>
              </button>
              <form method="post" class="d-inline" onsubmit="return confirm('Xóa báo cáo này?');">
                <input type="hidden" name="action" value="delete_report" />
                <input type="hidden" name="report_id" value="<?= $item['id'] ?>" />
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

<div class="modal fade" id="editReportModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <input type="hidden" name="action" value="update_report" />
      <input type="hidden" name="report_id" id="editReportId" />
      <?= adminHiddenRedirects($filters, $page) ?>
      <div class="modal-header">
        <h5 class="modal-title">Xử lý báo cáo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Lý do báo cáo</label>
          <textarea id="editReportLyDo" class="form-control" rows="3" readonly></textarea>
        </div>
        <div class="mb-0">
          <label class="form-label">Trạng thái xử lý</label>
          <select name="trang_thai" id="editReportStatus" class="form-select" required>
            <option value="Pending">Chờ xử lý</option>
            <option value="Reviewed">Đang xem xét</option>
            <option value="Resolved">Đã xử lý</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="submit" class="btn btn-primary">Cập nhật</button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('editReportModal').addEventListener('show.bs.modal', function (e) {
  var btn = e.relatedTarget;
  document.getElementById('editReportId').value = btn.getAttribute('data-id');
  document.getElementById('editReportStatus').value = btn.getAttribute('data-status');
  document.getElementById('editReportLyDo').value = btn.getAttribute('data-ly-do');
});
</script>

<?php adminRenderLayoutEnd(); ?>
