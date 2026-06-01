<?php
require_once '../config/config.php';
require_once '../config/db_module.php';
require_once '../includes/bunny_helpers.php';
require_once '../includes/buddies_repository.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$currentUserId = (int) $_SESSION['user_id'];
$searchQuery = trim($_GET['q'] ?? '');
$user_avatar         = "../assets/img/default-avatar.jpg";
$stats_xp            = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $action = $_POST['action'] ?? '';
        $result = buddiesHandleAction(getPdo(), $currentUserId, $action, $_POST);
        echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

try {
    $page = buddiesLoadPage(getPdo(), $currentUserId, $searchQuery);
    extract($page);
} catch (Throwable $e) {
    bunnyFatalError($e->getMessage());
}

function renderBuddyCard(array $user, string $mode, ?int $relationId = null): void
{
    $onlineClass = !empty($user['is_online']) ? '' : ' offline';
    ?>
    <article class="buddy-card" data-user-id="<?= (int) $user['id'] ?>">
      <div class="buddy-card__avatar-wrap">
        <img class="buddy-card__avatar" src="<?= htmlspecialchars($user['avatar']) ?>" alt="" width="84" height="84" />
        <span class="buddy-card__online<?= $onlineClass ?>" title="<?= !empty($user['is_online']) ? 'Đang online' : 'Offline' ?>"></span>
      </div>
      <div class="buddy-card__name"><?= htmlspecialchars($user['name']) ?></div>
      <div class="buddy-card__username">@<?= htmlspecialchars($user['username']) ?></div>
      <div class="buddy-card__meta">
        <span class="buddy-badge buddy-badge--primary"><?= htmlspecialchars($user['user_type']) ?></span>
        <span class="buddy-badge"><i class="fa-solid fa-school me-1"></i><?= htmlspecialchars($user['school']) ?></span>
        <span class="buddy-badge"><i class="fa-solid fa-fire text-danger me-1"></i><?= number_format($user['xp']) ?> XP</span>
      </div>
      <div class="buddy-card__actions">
        <?php if ($mode === 'accepted'): ?>
        <a class="btn btn-primary btn-sm" href="tin-nhan.php?peer_id=<?= (int) $user['id'] ?>">
          <i class="fa-solid fa-paper-plane me-1"></i> Nhắn tin
        </a>
        <button type="button" class="btn btn-outline-danger btn-sm" data-action="remove_friend" data-relation-id="<?= (int) $relationId ?>">
          <i class="fa-solid fa-user-xmark"></i>
        </button>
        <?php elseif ($mode === 'incoming'): ?>
        <button type="button" class="btn btn-success btn-sm" data-action="accept_request" data-relation-id="<?= (int) $relationId ?>">
          <i class="fa-solid fa-check me-1"></i> Chấp nhận
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-action="decline_request" data-relation-id="<?= (int) $relationId ?>">
          Từ chối
        </button>
        <?php elseif ($mode === 'outgoing'): ?>
        <button type="button" class="btn btn-outline-secondary btn-sm w-100" data-action="cancel_request" data-relation-id="<?= (int) $relationId ?>">
          <i class="fa-solid fa-clock me-1"></i> Hủy lời mời
        </button>
        <?php elseif ($mode === 'discover'): ?>
          <?php if (!empty($user['is_friend'])): ?>
          <span class="btn btn-light btn-sm w-100 disabled">Đã là bạn cùng tiến</span>
          <?php elseif (!empty($user['is_incoming'])): ?>
          <button type="button" class="btn btn-success btn-sm" data-action="accept_request" data-relation-id="<?= (int) $user['relation_id'] ?>">
            Chấp nhận lời mời
          </button>
          <?php elseif (!empty($user['is_outgoing'])): ?>
          <button type="button" class="btn btn-outline-secondary btn-sm w-100" data-action="cancel_request" data-relation-id="<?= (int) $user['relation_id'] ?>">
            Đã gửi lời mời
          </button>
          <?php else: ?>
          <button type="button" class="btn btn-primary btn-sm w-100" data-action="send_request" data-user-id="<?= (int) $user['id'] ?>">
            <i class="fa-solid fa-user-plus me-1"></i> Kết bạn
          </button>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </article>
    <?php
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Bạn cùng tiến — The Bunny</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="../assets/css/root.css" rel="stylesheet" />
  <link href="../assets/css/trang-chu.css" rel="stylesheet" />
  <link href="../assets/css/ban-cung-tien.css" rel="stylesheet" />
  <link href="../assets/css/responsive.css" rel="stylesheet" />
</head>
<body class="buddies-page">
  <div class="mobile-overlay" id="mobileOverlay" onclick="toggleSidebar()"></div>

  <nav class="sticky-top" style="z-index: 1030;">
        <?php include '../includes/header.php'; ?>
    </nav>

  <div class="layout-container">
    <aside class="sidebar-left" id="sidebarLeft">
      <div class="user-mini d-none d-md-flex">
        <img src="<?= htmlspecialchars($user_avatar) ?>" alt="" width="44" height="44" />
        <div>
          <div class="name"><?= htmlspecialchars($currentUser['username']) ?></div>
        </div>
      </div>
      <div class="nav-menu">
                <a href="trang-chu.php" class="nav-item"><i class="fa-solid fa-house"></i> Bảng tin</a>
                <a href="hang-tho.php" class="nav-item"><i class="fa-solid fa-user-group"></i> Hang thỏ</a>
                <a href="ban-cung-tien.php" class="nav-item active"><i class="fa-solid fa-child"></i>Bạn cùng tiến</a>
                <a href="thach-dau.php" class="nav-item"><i class="fa-solid fa-khanda"></i> Thách đấu <span class="badge-count" style="background: #EF4444;">Mới</span></a>
            </div>
    </aside>

    <main class="feed-main">
      <section class="buddies-hero card-bunny mb-0">
        <h1><i class="fa-solid fa-users-rays text-primary me-2"></i>Bạn cùng tiến</h1>
        <p class="text-muted mb-0">Kết nối, học cùng và hỗ trợ lẫn nhau trên hành trình học tập tại The Bunny.</p>
        <div class="buddies-stats">
          <span class="buddies-stat"><i class="fa-solid fa-user-check text-success me-1"></i> <?= $counts['accepted'] ?> bạn</span>
          <span class="buddies-stat"><i class="fa-solid fa-inbox text-warning me-1"></i> <?= $counts['incoming'] ?> lời mời đến</span>
          <span class="buddies-stat"><i class="fa-solid fa-paper-plane text-info me-1"></i> <?= $counts['outgoing'] ?> đã gửi</span>
        </div>
      </section>

      <div class="buddies-tabs mt-3" role="tablist">
        <button type="button" class="buddies-tab active" data-panel="friends">Bạn bè <?php if ($counts['accepted'] > 0): ?><span class="count"><?= $counts['accepted'] ?></span><?php endif; ?></button>
        <button type="button" class="buddies-tab" data-panel="incoming">Lời mời đến <?php if ($counts['incoming'] > 0): ?><span class="count"><?= $counts['incoming'] ?></span><?php endif; ?></button>
        <button type="button" class="buddies-tab" data-panel="outgoing">Đã gửi <?php if ($counts['outgoing'] > 0): ?><span class="count"><?= $counts['outgoing'] ?></span><?php endif; ?></button>
        <button type="button" class="buddies-tab" data-panel="discover">Khám phá</button>
      </div>

      <div class="buddies-panel active" id="panel-friends">
        <?php if ($acceptedFriends === []): ?>
        <div class="buddies-empty">
          <i class="fa-solid fa-user-group d-block"></i>
          <h2 class="h5 fw-bold">Chưa có bạn cùng tiến</h2>
          <p class="text-muted">Dùng tab Khám phá để tìm và gửi lời mời kết bạn.</p>
        </div>
        <?php else: ?>
        <div class="buddy-grid">
          <?php foreach ($acceptedFriends as $friend): ?>
            <?php renderBuddyCard($friend, 'accepted', $friend['relation_id']); ?>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="buddies-panel" id="panel-incoming">
        <?php if ($incomingRequests === []): ?>
        <div class="buddies-empty">
          <i class="fa-solid fa-inbox d-block"></i>
          <h2 class="h5 fw-bold">Không có lời mời mới</h2>
          <p class="text-muted">Khi ai đó gửi lời mời, bạn sẽ thấy ở đây.</p>
        </div>
        <?php else: ?>
        <div class="buddy-grid">
          <?php foreach ($incomingRequests as $req): ?>
            <?php renderBuddyCard($req, 'incoming', $req['relation_id']); ?>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="buddies-panel" id="panel-outgoing">
        <?php if ($outgoingRequests === []): ?>
        <div class="buddies-empty">
          <i class="fa-solid fa-paper-plane d-block"></i>
          <h2 class="h5 fw-bold">Chưa gửi lời mời nào</h2>
          <p class="text-muted">Tìm bạn mới ở tab Khám phá.</p>
        </div>
        <?php else: ?>
        <div class="buddy-grid">
          <?php foreach ($outgoingRequests as $req): ?>
            <?php renderBuddyCard($req, 'outgoing', $req['relation_id']); ?>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="buddies-panel" id="panel-discover">
        <form class="buddies-search-bar" id="discoverSearchForm">
          <input type="search" id="discoverSearchInput" placeholder="Tìm theo tên, email…" value="<?= htmlspecialchars($searchQuery) ?>" autocomplete="off" />
          <button type="submit"><i class="fa-solid fa-search me-1"></i> Tìm</button>
        </form>

        <?php
        $discoverList = $searchQuery !== '' ? $searchResults : $suggestions;
        $discoverTitle = $searchQuery !== '' ? 'Kết quả tìm kiếm' : 'Gợi ý kết bạn';
        ?>
        <h2 class="h6 fw-bold mb-3"><?= htmlspecialchars($discoverTitle) ?></h2>

        <?php if ($discoverList === []): ?>
        <div class="buddies-empty">
          <i class="fa-solid fa-magnifying-glass d-block"></i>
          <h2 class="h5 fw-bold">Không tìm thấy ai</h2>
          <p class="text-muted">Thử từ khóa khác hoặc quay lại sau.</p>
        </div>
        <?php else: ?>
        <div class="buddy-grid" id="discoverGrid">
          <?php foreach ($discoverList as $user): ?>
            <?php renderBuddyCard($user, 'discover'); ?>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </main>

    <aside class="sidebar-right d-none d-lg-block">
      <div class="card-bunny">
        <div class="section-title">Mẹo kết nối</div>
        <ul class="small text-muted ps-3 mb-0">
          <li class="mb-2">Tham gia Thách đấu để gặp bạn cùng môn.</li>
          <li class="mb-2">Bình luận bài đăng trên Bảng tin.</li>
          <li>Chấp nhận lời mời để nhắn tin riêng.</li>
        </ul>
      </div>
    </aside>
  </div>

  <div class="bottom-nav">
    <a href="trang-chu.php" class="nav-btn-mobile"><i class="fa-solid fa-house"></i><span>Trang chủ</span></a>
    <a href="ban-cung-tien.php" class="nav-btn-mobile active"><i class="fa-solid fa-user-group"></i><span>Bạn bè</span></a>
    <a href="tin-nhan.php" class="nav-btn-mobile"><i class="fa-brands fa-facebook-messenger"></i><span>Tin nhắn</span></a>
    <a href="trang-ca-nhan.php" class="nav-btn-mobile"><i class="fa-solid fa-user"></i><span>Hồ sơ</span></a>
  </div>

  <div class="buddies-toast" id="buddiesToast" role="status"></div>

  <script>
    function toggleSidebar() {
      document.getElementById("sidebarLeft").classList.toggle("open");
      document.getElementById("mobileOverlay").classList.toggle("show");
    }

    (function () {
      var toast = document.getElementById("buddiesToast");
      var toastTimer;

      function showToast(msg) {
        toast.textContent = msg;
        toast.classList.add("show");
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
          toast.classList.remove("show");
        }, 2800);
      }

      document.querySelectorAll(".buddies-tab").forEach(function (tab) {
        tab.addEventListener("click", function () {
          var panel = tab.getAttribute("data-panel");
          document.querySelectorAll(".buddies-tab").forEach(function (t) {
            t.classList.toggle("active", t === tab);
          });
          document.querySelectorAll(".buddies-panel").forEach(function (p) {
            p.classList.toggle("active", p.id === "panel-" + panel);
          });
        });
      });

      function postAction(action, data) {
        var body = new URLSearchParams();
        body.set("action", action);
        Object.keys(data).forEach(function (k) {
          body.set(k, data[k]);
        });
        return fetch("ban-cung-tien.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: body.toString(),
        }).then(function (res) {
          return res.json().then(function (json) {
            if (!res.ok || !json.ok) {
              throw new Error((json && json.error) || "Thao tác thất bại");
            }
            return json;
          });
        });
      }

      document.body.addEventListener("click", function (e) {
        var btn = e.target.closest("[data-action]");
        if (!btn) return;
        e.preventDefault();

        var action = btn.getAttribute("data-action");
        var payload = {};
        if (btn.getAttribute("data-relation-id")) {
          payload.relation_id = btn.getAttribute("data-relation-id");
        }
        if (btn.getAttribute("data-user-id")) {
          payload.user_id = btn.getAttribute("data-user-id");
        }

        btn.disabled = true;
        postAction(action, payload)
          .then(function (data) {
            showToast(data.message || "Thành công");
            setTimeout(function () {
              window.location.reload();
            }, 600);
          })
          .catch(function (err) {
            alert(err.message || "Có lỗi xảy ra");
            btn.disabled = false;
          });
      });

      document.getElementById("discoverSearchForm").addEventListener("submit", function (e) {
        e.preventDefault();
        var q = document.getElementById("discoverSearchInput").value.trim();
        if (q) {
          window.location.href = "ban-cung-tien.php?q=" + encodeURIComponent(q) + "#discover";
        }
      });

      if (window.location.hash === "#discover" || <?= json_encode($searchQuery !== '') ?>) {
        document.querySelector('.buddies-tab[data-panel="discover"]').click();
      }
    })();
  </script>
</body>
</html>
