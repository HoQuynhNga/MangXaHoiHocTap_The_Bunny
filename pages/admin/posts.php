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
        'q'    => $_POST['redirect_q'] ?? '',
        'page' => $_POST['redirect_page'] ?? '',
    ]));
    adminRedirect('posts.php' . ($redirectQs ? '?' . $redirectQs : ''));
}

$filters = ['q' => trim($_GET['q'] ?? '')];
$page = max(1, (int) ($_GET['page'] ?? 1));
$result = adminListPosts($pdo, $filters, $page);

adminRenderLayoutStart('Quản lý bài viết', 'posts');
?>

<div class="admin-card">
  <form method="get" class="admin-filters">
    <input type="search" name="q" class="form-control" placeholder="Tìm nội dung, tác giả…" value="<?= htmlspecialchars($filters['q']) ?>" />
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-search me-1"></i> Tìm</button>
    <a href="posts.php" class="btn btn-outline-secondary">Reset</a>
  </form>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted"><?= number_format($result['total']) ?> bài viết</span>
  </div>

  <div class="table-responsive">
    <table class="table admin-table align-middle">
      <thead>
        <tr>
          <th>Bài viết</th>
          <th>Tác giả</th>
          <th>Tương tác</th>
          <th>Thời gian</th>
          <th>Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($result['items'] === []): ?>
        <tr><td colspan="5" class="text-center text-muted py-5">Không có bài viết</td></tr>
        <?php else: ?>
        <?php foreach ($result['items'] as $post): ?>
        <tr>
          <td>
            <div class="admin-post-preview" title="<?= htmlspecialchars($post['noi_dung']) ?>">
              #<?= $post['id'] ?> — <?= htmlspecialchars($post['preview']) ?>
            </div>
          </td>
          <td>
            <div class="admin-user-cell">
              <img src="<?= htmlspecialchars($post['author_avatar']) ?>" alt="" />
              <div class="meta">
                <div class="name"><?= htmlspecialchars($post['author_name']) ?></div>
                <div class="sub">@<?= htmlspecialchars($post['author_username']) ?></div>
              </div>
            </div>
          </td>
          <td>
            <span class="badge bg-light text-dark border me-1"><i class="fa-solid fa-heart text-danger"></i> <?= $post['like_count'] ?></span>
            <span class="badge bg-light text-dark border me-1"><i class="fa-solid fa-comment text-primary"></i> <?= $post['comment_count'] ?></span>
            <span class="badge bg-light text-dark border"><i class="fa-solid fa-share"></i> <?= $post['share_count'] ?></span>
          </td>
          <td><?= htmlspecialchars($post['created_at']) ?></td>
          <td>
            <div class="admin-actions">
              <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editPostModal"
                data-id="<?= $post['id'] ?>"
                data-content="<?= htmlspecialchars($post['noi_dung'], ENT_QUOTES) ?>"
                data-author="<?= htmlspecialchars($post['author_name'], ENT_QUOTES) ?>">
                <i class="fa-solid fa-pen"></i>
              </button>
              <form method="post" class="d-inline" onsubmit="return confirm('Xóa bài viết này?');">
                <input type="hidden" name="action" value="delete_post" />
                <input type="hidden" name="post_id" value="<?= $post['id'] ?>" />
                <input type="hidden" name="redirect_q" value="<?= htmlspecialchars($filters['q']) ?>" />
                <input type="hidden" name="redirect_page" value="<?= $page ?>" />
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

<div class="modal fade" id="editPostModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="post" class="modal-content">
      <input type="hidden" name="action" value="update_post" />
      <input type="hidden" name="post_id" id="editPostId" />
      <input type="hidden" name="redirect_q" value="<?= htmlspecialchars($filters['q']) ?>" />
      <input type="hidden" name="redirect_page" value="<?= $page ?>" />
      <div class="modal-header">
        <h5 class="modal-title">Chỉnh sửa bài viết — <span id="editPostAuthor"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label">Nội dung</label>
        <textarea name="noi_dung" id="editPostContent" class="form-control" rows="8" required></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="submit" class="btn btn-primary">Lưu bài viết</button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('editPostModal').addEventListener('show.bs.modal', function (e) {
  var btn = e.relatedTarget;
  document.getElementById('editPostId').value = btn.getAttribute('data-id');
  document.getElementById('editPostContent').value = btn.getAttribute('data-content');
  document.getElementById('editPostAuthor').textContent = btn.getAttribute('data-author');
});
</script>

<?php adminRenderLayoutEnd(); ?>
