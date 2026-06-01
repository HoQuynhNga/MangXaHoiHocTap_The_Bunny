<?php
require_once '../config/config.php';
require_once '../includes/bunny_helpers.php';
require_once '../includes/inbox_repository.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$currentUserId = (int) $_SESSION['user_id'];
$user_avatar         = "../assets/img/default-avatar.jpg";
$stats_xp            = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';

    try {
        $pdo = getPdo();

        if ($action === 'send_message') {
            $msg = inboxSendMessage(
                $pdo,
                $currentUserId,
                (string) ($_POST['chat_id'] ?? ''),
                (string) ($_POST['text'] ?? '')
            );
            echo json_encode(['ok' => true, 'message' => $msg], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'start_chat') {
            $peerId = (int) ($_POST['peer_id'] ?? 0);
            $payload = inboxStartChat($pdo, $currentUserId, $peerId);
            echo json_encode(['ok' => true] + $payload, JSON_UNESCAPED_UNICODE);
            exit;
        }

        throw new InvalidArgumentException('Hành động không hợp lệ.');
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

try {
    $page = inboxLoadPage(getPdo(), $currentUserId);
    extract($page);
    $pdo = getPdo();
} catch (Throwable $e) {
    bunnyFatalError($e->getMessage());
}

// Sidebar phụ — giữ tĩnh (chỉ tin nhắn lấy từ DB)
$trendingTags = [];
$shortcuts = [];
?>
<!DOCTYPE html>
<html lang="vi">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Tin nhắn — The Bunny</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/root.css" />
    <link rel="stylesheet" href="../assets/css/tin-nhan.css" />
  </head>
  <body>
<div class="mobile-overlay" id="mobileOverlay" onclick="toggleSidebar()"></div>

    <nav class="sticky-top" style="z-index: 1030;">
        <?php include '../includes/header.php'; ?>
    </nav>

    <div class="layout-container">
      <aside class="sidebar-left" id="sidebarLeft">
      <div class="user-mini d-none d-md-flex">
  <img src="<?= htmlspecialchars($currentUser['avatar'] ?? $user_avatar) ?>" alt="<?= htmlspecialchars($currentUser['username'] ?? 'Tài khoản') ?>" width="44" height="44" />
  <div>
    <div class="name"><?= htmlspecialchars($currentUser['username'] ?? 'Tài khoản') ?></div>
  </div>
</div>
        <div class="nav-menu">
                <a href="trang-chu.php" class="nav-item"><i class="fa-solid fa-house"></i> Bảng tin</a>
                <a href="hang-tho.php" class="nav-item"><i class="fa-solid fa-user-group"></i> Hang thỏ</a>
                <a href="ban-cung-tien.php" class="nav-item"><i class="fa-solid fa-child"></i>Bạn cùng tiến</a>
                <a href="thach-dau.php" class="nav-item"><i class="fa-solid fa-khanda"></i> Thách đấu <span class="badge-count" style="background: #EF4444;">Mới</span></a>
            </div>

        <hr class="my-3 text-muted opacity-25" />
        <div class="nav-menu">
          <?php foreach ($shortcuts as $sc): ?>
          <a href="<?= htmlspecialchars($sc['url']) ?>" class="nav-item py-2">
            <?php if ($sc['icon_type'] === 'img'): ?>
            <img src="<?= htmlspecialchars($sc['icon_src']) ?>" width="24" class="rounded" alt="" />
            <?php else: ?>
            <div class="btn-icon" style="width: 24px; height: 24px; <?= $sc['icon_bg'] ?>">
              <i class="fa-solid <?= htmlspecialchars($sc['icon_fa']) ?> fs-6"></i>
            </div>
            <?php endif; ?>
            <?= htmlspecialchars($sc['label']) ?>
          </a>
          <?php endforeach; ?>
        </div>
      </aside>

      <main class="feed-main feed-main--inbox">
        <div class="card-bunny inbox-card p-0 overflow-hidden mb-0">
          <div class="inbox-panel">
            <div class="inbox-panel__list">
              <div class="inbox-panel__head">
                <span class="inbox-panel__title">Hộp thư đến <span class="inbox-panel__count" id="inboxThreadCount">(<?= $inboxThreadCount ?>)</span></span>
              </div>
              <div class="inbox-panel__items" role="listbox" aria-label="Danh sách cuộc trò chuyện">
                <?php if ($conversations === []): ?>
                <p class="text-muted small px-3 py-4 mb-0">Chưa có cuộc trò chuyện. Bấm vào bạn cùng tiến bên phải để bắt đầu nhắn tin.</p>
                <?php else: ?>
                <?php foreach ($conversations as $conv): ?>
                <button
                  type="button"
                  class="msg-row"
                  role="option"
                  data-chat-id="<?= htmlspecialchars($conv['id']) ?>"
                  aria-selected="false"
                >
                  <img
                    class="msg-row__avatar"
                    src="<?= htmlspecialchars($user_avatar) ?>"
                    width="40"
                    height="40"
                    alt=""
                  />
                  <span class="msg-row__body">
                    <span class="msg-row__name"><?= htmlspecialchars($conv['username']) ?></span>
                    <?php if (!empty($conv['preview_from_you'])): ?>
                    <span class="msg-row__preview msg-row__preview--you">
                      <span class="msg-row__you-label">Bạn:</span> <?= htmlspecialchars($conv['preview']) ?>
                    </span>
                    <?php else: ?>
                    <span class="msg-row__preview"><?= htmlspecialchars($conv['preview']) ?></span>
                    <?php endif; ?>
                    <span class="msg-row__time"><?= htmlspecialchars($conv['time_ago']) ?></span>
                  </span>
                </button>
                <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
            <div class="inbox-panel__empty" id="inboxPlaceholder" aria-hidden="false">
              <div class="inbox-panel__envelope">
                <i class="fa-regular fa-envelope"></i>
              </div>
              <p class="inbox-panel__hint">Chọn một cuộc trò chuyện để xem chi tiết</p>
            </div>
            <div class="inbox-panel__conversation" id="inboxConversation" hidden>
              <div class="inbox-chat__toolbar">
                <button type="button" class="inbox-chat__back" id="inboxChatBack" aria-label="Quay lại danh sách">
                  <i class="fa-solid fa-arrow-left"></i>
                </button>
                <img class="inbox-chat__avatar" id="inboxChatHeaderAvatar" src="" width="40" height="40" alt="" />
                <div class="inbox-chat__meta">
                  <div class="inbox-chat__name" id="inboxChatHeaderName"></div>
                </div>
              </div>
              <div class="inbox-chat__messages" id="inboxChatMessages" tabindex="-1"></div>
              <form class="inbox-chat__composer" id="inboxChatForm" autocomplete="off">
                <label class="visually-hidden" for="inboxChatInput">Nội dung tin nhắn</label>
                <textarea
                  id="inboxChatInput"
                  class="inbox-chat__input"
                  rows="1"
                  placeholder="Nhập tin nhắn…"
                ></textarea>
                <button type="button" class="inbox-chat__send" id="inboxChatSend">
                  <i class="fa-solid fa-paper-plane"></i><span>Gửi</span>
                </button>
              </form>
            </div>
          </div>
        </div>
      </main>

      <aside class="sidebar-right">
        <div class="card-bunny p-3 mb-3">
        <div class="section-title ps-2 mb-2 d-flex justify-content-between align-items-center">
          <span>Bạn cùng tiến</span>
          <a href="ban-cung-tien.php" class="small text-primary fw-semibold">Quản lý</a>
        </div>
        <?php if ($onlineFriends === []): ?>
        <p class="text-muted small ps-2">Chưa có bạn cùng tiến. Kết bạn từ trang chủ để nhắn tin.</p>
        <?php endif; ?>
        <?php foreach ($onlineFriends as $friend): ?>
        <a
          href="#chat-<?= htmlspecialchars($friend['chat_id'] !== '' ? $friend['chat_id'] : ('peer-' . $friend['peer_id'])) ?>"
          id="chat-<?= htmlspecialchars($friend['chat_id'] !== '' ? $friend['chat_id'] : ('peer-' . $friend['peer_id'])) ?>"
          class="friend-item"
          data-open-chat="<?= htmlspecialchars($friend['chat_id']) ?>"
          data-peer-id="<?= (int) $friend['peer_id'] ?>"
        >
          <div class="friend-avatar"><img src="<?= htmlspecialchars($user_avatar) ?>" alt="" /><div class="online-dot<?= $friend['status'] === 'away' ? ' away-dot' : '' ?>"></div></div>
          <span class="fw-bold small<?= $friend['status'] === 'away' ? ' text-muted' : '' ?>"><?= htmlspecialchars($friend['username']) ?></span>
        </a>
        <?php endforeach; ?>
      </aside>
    </div>

    <footer class="app-footer">
      © The Bunny ·
      <a href="#">Điều khoản</a>
      ·
      <a href="#">Bảo mật</a>
    </footer>

    <div class="bottom-nav">
      <a href="trang-chu.php" class="nav-btn-mobile"><i class="fa-solid fa-house"></i><span>Trang chủ</span></a>
      <a href="ban-cung-tien.php" class="nav-btn-mobile"><i class="fa-solid fa-user-group"></i><span>Bạn bè</span></a>
      <button type="button" class="nav-btn-mobile" style="color: var(--bunny-primary); margin-top: -15px">
        <div class="bg-primary bg-opacity-10 rounded-circle p-2 shadow-sm"><i class="fa-solid fa-plus fs-4"></i></div>
      </button>
      <a href="tin-nhan.php" class="nav-btn-mobile active">
        <i class="fa-brands fa-facebook-messenger"></i><span>Tin nhắn</span>
      </a>
      <a href="<?= htmlspecialchars($currentUser['profile_url'] ?? 'trang-ca-nhan.php?id=' . $currentUserId) ?>" class="nav-btn-mobile"><i class="fa-solid fa-user"></i><span>Hồ sơ</span></a>
    </div>
    <script> 
    var CHATS = <?= json_encode($chats ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>
    <script src="../assets/js/tin-nhan.js"></script>
  </body>
</html>

