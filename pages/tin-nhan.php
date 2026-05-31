<?php
require_once '../config/config.php';
require_once '../config/db_module.php';
require_once '../includes/bunny_helpers.php';
require_once '../includes/inbox_repository.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$currentUserId = (int) $_SESSION['user_id'];

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
} catch (Throwable $e) {
    bunnyFatalError($e->getMessage());
}

// Sidebar phụ — giữ tĩnh (chỉ tin nhắn lấy từ DB)
$trendingTags = [
    ['tag' => '#BabeNobuli_Project', 'count' => '1.2k bài thảo luận', 'anchor' => 'tag-babenobuli'],
    ['tag' => '#Figma_Design_System', 'count' => '850 tài liệu mới', 'anchor' => 'tag-figma'],
    ['tag' => '#ĐềThiThử_Lý9', 'count' => '540 lượt thi hôm nay', 'anchor' => 'tag-ly9'],
];
$shortcuts = [
    ['label' => 'Thiết kế UI/UX', 'icon_type' => 'img', 'icon_src' => 'https://upload.wikimedia.org/wikipedia/commons/3/33/Figma-logo.svg', 'url' => '#'],
    ['label' => 'Ôn thi Vật Lý 9', 'icon_type' => 'fa', 'icon_fa' => 'fa-atom', 'icon_bg' => 'background:#E0F2FE;color:#0284C7;', 'url' => '#'],
    ['label' => 'Dự án Babe Nobuli', 'icon_type' => 'fa', 'icon_fa' => 'fa-chart-line', 'icon_bg' => 'background:#FEF3C7;color:#D97706;', 'url' => '#'],
];
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
    <link rel="stylesheet" href="inbox.css" />

    <style>
      /* THE BUNNY UI 2026 — shared with MXH.html & index.html */

/* Neo fragment: id trùng href trên cùng phần tử — cuộn đúng ô khi bấm */
.navbar-bunny a.btn-icon[id],
.navbar-bunny .nav-profile-link[id],
a.trending-tag[id],
a.friend-item[id] {
  scroll-margin-top: 84px;
}

:root {
  --bunny-primary: #8b5cf6;
  --bunny-primary-hover: #7c3aed;
  --bunny-secondary: #3b82f6;
  --bunny-accent: #f59e0b;
  --bg-body: #f3f4f6;
  --bg-card: #ffffff;
  --text-main: #111827;
  --text-muted: #6b7280;
  --border-color: #e5e7eb;
  --shadow-soft: 0 4px 20px rgba(0, 0, 0, 0.03);
  --shadow-hover: 0 10px 25px rgba(139, 92, 246, 0.1);
  --radius-lg: 16px;
  --radius-md: 12px;
  --radius-sm: 8px;
  --transition: all 0.3s ease;
}

*,
*::before,
*::after {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

body {
  background: var(--bg-body);
  font-family: "Inter", sans-serif;
  color: var(--text-main);
  -webkit-font-smoothing: antialiased;
}

a {
  text-decoration: none;
  color: inherit;
}

::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}

::-webkit-scrollbar-thumb {
  background: #d1d5db;
  border-radius: 10px;
}

/* Navbar */
.navbar-bunny {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  border-bottom: 1px solid var(--border-color);
  height: 64px;
  position: sticky;
  top: 0;
  z-index: 200;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
}

.brand-logo {
  font-size: 1.25rem;
  font-weight: 800;
  color: var(--bunny-primary);
  letter-spacing: -0.5px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.search-bar {
  background: #f3f4f6;
  border-radius: 20px;
  padding: 8px 16px;
  width: 300px;
  display: flex;
  align-items: center;
  gap: 10px;
  border: 1px solid transparent;
  transition: var(--transition);
}

.search-bar:focus-within {
  background: white;
  border-color: var(--bunny-primary);
  box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
}

.search-bar input {
  border: none;
  background: transparent;
  outline: none;
  width: 100%;
  font-size: 14px;
}

.nav-actions {
  display: flex;
  align-items: center;
  gap: 16px;
}

.btn-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #f3f4f6;
  border: none;
  color: var(--text-main);
  font-size: 1.1rem;
  transition: var(--transition);
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
}

a.btn-icon {
  text-decoration: none;
  color: inherit;
  box-sizing: border-box;
}

.btn-icon:hover {
  background: #e5e7eb;
  color: var(--bunny-primary);
}

a.btn-icon:focus-visible {
  outline: 2px solid var(--bunny-primary);
  outline-offset: 2px;
}

.nav-profile-link {
  display: inline-flex;
  border-radius: 50%;
  line-height: 0;
  flex-shrink: 0;
}

.nav-profile-link:hover .nav-profile-link__img {
  box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.35);
}

.nav-profile-link:focus-visible {
  outline: 2px solid var(--bunny-primary);
  outline-offset: 2px;
  border-radius: 50%;
}

.nav-profile-link__img {
  display: block;
}

.btn-icon .badge-dot {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 10px;
  height: 10px;
  background: #ef4444;
  border-radius: 50%;
  border: 2px solid white;
}

.menu-btn-mobile {
  display: none;
  background: none;
  border: none;
  font-size: 1.5rem;
  color: var(--text-main);
}

/* Layout */
.layout-container {
  max-width: 1400px;
  margin: 0 auto;
  display: flex;
  justify-content: space-between;
  padding: 20px 16px;
  gap: 24px;
}

.card-bunny {
  background: var(--bg-card);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-soft);
  padding: 20px;
  margin-bottom: 20px;
  border: 1px solid rgba(255, 255, 255, 0.5);
}

.sidebar-left {
  width: 280px;
  flex-shrink: 0;
  position: sticky;
  top: 84px;
  height: calc(100vh - 100px);
  overflow-y: auto;
  padding-right: 8px;
}

.user-mini {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  border-radius: var(--radius-md);
  transition: var(--transition);
  cursor: pointer;
  margin-bottom: 16px;
}

.user-mini:hover {
  background: #f3f4f6;
}

.user-mini img {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  object-fit: cover;
}

.user-mini .name {
  font-weight: 700;
  font-size: 0.95rem;
  line-height: 1.2;
  color: var(--text-main);
}

.user-mini .xp {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--bunny-accent);
  display: flex;
  align-items: center;
  gap: 4px;
}

.nav-menu {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border-radius: var(--radius-sm);
  font-weight: 600;
  color: var(--text-muted);
  transition: var(--transition);
}

.nav-item:hover {
  background: #f3f4f6;
  color: var(--text-main);
}

.nav-item.active {
  background: rgba(139, 92, 246, 0.1);
  color: var(--bunny-primary);
}

.nav-item i {
  font-size: 1.2rem;
  width: 24px;
  text-align: center;
}

.nav-item .badge-count {
  margin-left: auto;
  background: var(--bunny-primary);
  color: white;
  font-size: 0.75rem;
  padding: 2px 8px;
  border-radius: 12px;
}

.feed-main {
  flex: 1;
  max-width: 680px;
  margin: 0 auto;
}

/* Stories */
.stories-container {
  display: flex;
  gap: 12px;
  overflow-x: auto;
  padding-bottom: 12px;
  margin-bottom: 8px;
}

.story-card {
  width: 110px;
  height: 180px;
  border-radius: var(--radius-md);
  flex-shrink: 0;
  position: relative;
  overflow: hidden;
  cursor: pointer;
  box-shadow: var(--shadow-soft);
  transition: transform 0.2s;
}

.story-card:hover {
  transform: scale(1.03);
}

.story-card img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.story-card .overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(to bottom, rgba(0, 0, 0, 0.1), rgba(0, 0, 0, 0.8));
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 10px;
}

.story-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: 2px solid var(--bunny-primary);
}

.story-name {
  color: white;
  font-size: 0.75rem;
  font-weight: 600;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
}

.story-create {
  background: var(--bg-card);
}

.story-create-btn {
  height: 70%;
  background: #f3f4f6;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  color: var(--text-muted);
}

.story-create .overlay {
  background: none;
  justify-content: flex-end;
  padding: 12px;
}

.story-create .story-name {
  color: var(--text-main);
  text-align: center;
  text-shadow: none;
}

/* Composer */
.composer-input-area {
  display: flex;
  gap: 12px;
  margin-bottom: 16px;
}

.composer-input-area input {
  flex: 1;
  background: #f3f4f6;
  border: none;
  border-radius: 30px;
  padding: 10px 20px;
  font-size: 0.95rem;
  outline: none;
  transition: var(--transition);
  cursor: pointer;
}

.composer-input-area input:hover {
  background: #e5e7eb;
}

.composer-actions {
  display: flex;
  justify-content: space-between;
  border-top: 1px solid var(--border-color);
  padding-top: 12px;
}

.btn-composer {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: none;
  border: none;
  color: var(--text-muted);
  font-weight: 600;
  padding: 8px;
  border-radius: var(--radius-sm);
  transition: var(--transition);
}

.btn-composer:hover {
  background: #f3f4f6;
  color: var(--text-main);
}

/* Posts */
.post-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 12px;
}

.post-author {
  font-weight: 700;
  color: var(--text-main);
  display: flex;
  align-items: center;
  gap: 4px;
}

.post-time {
  font-size: 0.8rem;
  color: var(--text-muted);
}

.post-content {
  font-size: 0.95rem;
  line-height: 1.5;
  margin-bottom: 16px;
}

.post-tags {
  color: var(--bunny-primary);
  font-weight: 600;
  cursor: pointer;
}

.doc-preview {
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 16px;
  background: #f9fafb;
  cursor: pointer;
  transition: var(--transition);
  margin-bottom: 16px;
}

.doc-preview:hover {
  background: #f3f4f6;
  border-color: #d1d5db;
}

.doc-icon {
  width: 48px;
  height: 48px;
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
}

.post-stats {
  display: flex;
  justify-content: space-between;
  font-size: 0.85rem;
  color: var(--text-muted);
  padding-bottom: 12px;
  border-bottom: 1px solid var(--border-color);
  margin-bottom: 8px;
}

.reaction-btns {
  display: flex;
  justify-content: space-between;
}

.sidebar-right {
  width: 300px;
  flex-shrink: 0;
  position: sticky;
  top: 84px;
  height: calc(100vh - 100px);
  overflow-y: auto;
  padding-left: 8px;
}

.section-title {
  font-size: 1rem;
  font-weight: 700;
  color: var(--text-main);
  margin-bottom: 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.trending-tag {
  display: flex;
  flex-direction: column;
  margin-bottom: 12px;
  cursor: pointer;
  padding: 8px;
  border-radius: var(--radius-sm);
  transition: var(--transition);
}

a.trending-tag {
  text-decoration: none;
  color: inherit;
}

.trending-tag:hover {
  background: #f3f4f6;
}

a.trending-tag:focus-visible {
  outline: 2px solid var(--bunny-primary);
  outline-offset: 2px;
}

.trending-tag .hash {
  font-weight: 700;
  color: var(--text-main);
}

.trending-tag .count {
  font-size: 0.8rem;
  color: var(--text-muted);
}

.friend-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px;
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: var(--transition);
  margin-bottom: 4px;
}

a.friend-item {
  text-decoration: none;
  color: inherit;
}

.friend-item:hover {
  background: #f3f4f6;
}

a.friend-item:focus-visible {
  outline: 2px solid var(--bunny-primary);
  outline-offset: 2px;
}

.friend-avatar {
  position: relative;
  width: 40px;
  height: 40px;
}

.friend-avatar img {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  object-fit: cover;
}

.online-dot {
  position: absolute;
  bottom: 2px;
  right: 0;
  width: 10px;
  height: 10px;
  background: #10b981;
  border-radius: 50%;
  border: 2px solid white;
}

.away-dot {
  background: #f59e0b;
}

/* Mobile */
.mobile-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 1000;
  backdrop-filter: blur(2px);
}

.bottom-nav {
  display: none;
}

@media (max-width: 992px) {
  .sidebar-right {
    display: none;
  }
}

@media (max-width: 768px) {
  .layout-container {
    padding: 12px 0;
  }

  .feed-main {
    padding: 0 12px;
    margin-bottom: 70px;
  }

  .search-bar {
    display: none;
  }

  .menu-btn-mobile {
    display: block;
  }

  .sidebar-left {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    width: 280px;
    z-index: 1050;
    background: var(--bg-card);
    height: 100vh;
    padding: 20px;
    transform: translateX(-100%);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: none;
    border-right: 1px solid var(--border-color);
  }

  .sidebar-left.open {
    transform: translateX(0);
    box-shadow: 5px 0 25px rgba(0, 0, 0, 0.1);
  }

  .mobile-overlay.show {
    display: block;
  }

  .bottom-nav {
    display: flex;
    justify-content: space-around;
    align-items: center;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    height: 60px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-top: 1px solid var(--border-color);
    z-index: 900;
    padding-bottom: env(safe-area-inset-bottom);
  }

  .nav-btn-mobile {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    color: var(--text-muted);
    background: none;
    border: none;
    flex: 1;
    padding: 8px 0;
  }

  .nav-btn-mobile.active {
    color: var(--bunny-primary);
    font-weight: 700;
  }

  .nav-btn-mobile i {
    font-size: 1.25rem;
  }

  .nav-btn-mobile span {
    font-size: 0.65rem;
  }
}

/* Shared page footer (optional) */
.app-footer {
  max-width: 1400px;
  margin: 0 auto;
  padding: 20px 16px 28px;
  border-top: 1px solid var(--border-color);
  color: var(--text-muted);
  font-size: 0.8rem;
  text-align: center;
}

.app-footer a {
  color: var(--bunny-primary);
  font-weight: 500;
}

.app-footer a:hover {
  text-decoration: underline;
}

/* =========================================
   Trang Tin nhắn (index.html)
   ========================================= */
.layout-container .feed-main.feed-main--inbox {
  flex: 1;
  max-width: min(920px, 100%);
  width: 100%;
  margin: 0 auto;
}

.inbox-card {
  border: 1px solid var(--border-color);
}

.inbox-panel {
  display: flex;
  min-height: 420px;
}

.inbox-panel__list {
  width: min(280px, 42%);
  flex-shrink: 0;
  border-right: 1px solid var(--border-color);
  display: flex;
  flex-direction: column;
  background: var(--bg-card);
}

.inbox-panel__head {
  padding: 16px 18px;
  border-bottom: 1px solid var(--border-color);
  background: #fafafa;
}

.inbox-panel__title {
  font-size: 1rem;
  font-weight: 700;
  color: var(--text-main);
}

.inbox-panel__count {
  color: var(--bunny-primary);
  font-weight: 800;
}

.inbox-panel__items {
  flex: 1;
  overflow: auto;
}

.msg-row {
  display: flex;
  width: 100%;
  text-align: left;
  gap: 12px;
  padding: 12px 14px;
  border: 0;
  border-bottom: 1px solid var(--border-color);
  background: var(--bg-card);
  cursor: pointer;
  font: inherit;
  color: inherit;
  align-items: flex-start;
  transition: var(--transition);
  touch-action: manipulation;
  -webkit-tap-highlight-color: transparent;
}

.msg-row:hover {
  background: #f3f4f6;
}

.msg-row.is-active {
  background: rgba(139, 92, 246, 0.08);
  box-shadow: inset 3px 0 0 var(--bunny-primary);
}

.msg-row:focus-visible {
  outline: 2px solid var(--bunny-primary);
  outline-offset: -2px;
}

.msg-row:active {
  transform: scale(0.995);
}

.msg-row__avatar {
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
}

.msg-row__body {
  flex: 1;
  min-width: 0;
  display: grid;
  grid-template-columns: 1fr auto;
  grid-template-rows: auto auto;
  column-gap: 8px;
  align-items: start;
}

.msg-row__name {
  grid-column: 1 / -1;
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--text-main);
}

.msg-row__preview {
  font-size: 0.85rem;
  color: var(--text-muted);
  grid-column: 1;
  line-height: 1.35;
}

.msg-row__preview--you {
  display: flex;
  align-items: baseline;
  gap: 4px;
}

.msg-row__you-label {
  font-weight: 700;
  color: var(--text-main);
}

.msg-row__time {
  grid-column: 2;
  grid-row: 2;
  font-size: 0.75rem;
  color: var(--text-muted);
  white-space: nowrap;
}

.inbox-panel__empty {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 24px;
  background: linear-gradient(180deg, #f9fafb 0%, #f3f4f6 100%);
}

.inbox-panel__envelope {
  width: 120px;
  height: 120px;
  border-radius: var(--radius-lg);
  background: rgba(139, 92, 246, 0.08);
  color: var(--bunny-primary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 3.5rem;
  opacity: 0.45;
}

.inbox-panel__hint {
  margin: 0;
  font-size: 0.9rem;
  color: var(--text-muted);
  text-align: center;
  max-width: 240px;
}

.inbox-panel__empty[hidden] {
  display: none !important;
}

.inbox-panel__conversation {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 0;
  background: var(--bg-card);
}

.inbox-panel__conversation[hidden] {
  display: none !important;
}

.inbox-chat__toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-bottom: 1px solid var(--border-color);
  flex-shrink: 0;
}

.inbox-chat__back {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  padding: 0;
  border: none;
  border-radius: 50%;
  background: #f3f4f6;
  color: var(--text-main);
  cursor: pointer;
  transition: var(--transition);
  flex-shrink: 0;
}

.inbox-chat__back:hover {
  background: #e5e7eb;
  color: var(--bunny-primary);
}

.inbox-chat__toolbar .inbox-chat__avatar {
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
}

.inbox-chat__meta {
  min-width: 0;
  flex: 1;
}

.inbox-chat__name {
  font-weight: 700;
  font-size: 0.95rem;
  color: var(--text-main);
}

.inbox-chat__status {
  font-size: 0.75rem;
  color: var(--text-muted);
}

.inbox-chat__messages {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  background: linear-gradient(180deg, #f9fafb 0%, #f3f4f6 100%);
  -webkit-overflow-scrolling: touch;
}

.inbox-chat__bubble-wrap {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
  max-width: 88%;
}

.inbox-chat__bubble-wrap--mine {
  align-self: flex-end;
  align-items: flex-end;
}

.inbox-chat__bubble {
  padding: 10px 14px;
  border-radius: var(--radius-md);
  background: #fff;
  border: 1px solid var(--border-color);
  font-size: 0.9rem;
  line-height: 1.45;
  word-break: break-word;
}

.inbox-chat__bubble-wrap--mine .inbox-chat__bubble {
  background: rgba(139, 92, 246, 0.12);
  border-color: rgba(139, 92, 246, 0.28);
}

.inbox-chat__bubble-time {
  font-size: 0.7rem;
  color: var(--text-muted);
  padding: 0 4px;
}

.inbox-chat__composer {
  display: flex;
  gap: 10px;
  padding: 12px 14px;
  border-top: 1px solid var(--border-color);
  background: var(--bg-card);
  align-items: flex-end;
  flex-shrink: 0;
}

.inbox-chat__input {
  flex: 1;
  border: 1px solid var(--border-color);
  border-radius: var(--radius-md);
  padding: 10px 12px;
  font: inherit;
  font-size: 0.9rem;
  resize: none;
  min-height: 44px;
  max-height: 120px;
  transition: var(--transition);
}

.inbox-chat__input:focus {
  outline: none;
  border-color: var(--bunny-primary);
  box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.12);
}

.inbox-chat__send {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px 16px;
  border: none;
  border-radius: var(--radius-md);
  background: var(--bunny-primary);
  color: #fff;
  font-weight: 600;
  font-size: 0.85rem;
  cursor: pointer;
  transition: var(--transition);
  flex-shrink: 0;
  white-space: nowrap;
}

.inbox-chat__send:hover {
  background: var(--bunny-primary-hover);
}

.inbox-chat__send:active {
  transform: scale(0.98);
}

@media (max-width: 768px) {
  .inbox-panel {
    flex-direction: column;
    min-height: 360px;
  }

  .inbox-panel__list {
    width: 100%;
    border-right: 0;
    border-bottom: 1px solid var(--border-color);
    max-height: 280px;
  }

  .inbox-panel__empty {
    min-height: 200px;
  }

  .layout-container .feed-main.feed-main--inbox {
    max-width: 100%;
  }

  .inbox-panel.inbox-panel--chat-open .inbox-panel__list {
    display: none;
  }

  .inbox-panel.inbox-panel--chat-open .inbox-panel__conversation {
    min-height: 55vh;
  }
}

    </style>
  </head>
  <body>
<div class="mobile-overlay" id="mobileOverlay" onclick="toggleSidebar()"></div>

     <nav class="navbar-bunny">
      <div class="d-flex align-items-center gap-3">
        <button class="menu-btn-mobile" type="button" onclick="toggleSidebar()" aria-label="Mở menu">
          <i class="fa-solid fa-bars"></i>
        </button>
        <a href="trang-chu.php" class="brand-logo"><i class="fa-solid fa-carrot"></i> THE BUNNY</a>
      </div>

      <div class="search-bar">
        <i class="fa-solid fa-search text-muted"></i>
        <input type="search" id="inboxToolbarSearch" placeholder="Tìm trong tin nhắn…" autocomplete="off" enterkeyhint="search" />
      </div>

      <div class="nav-actions">
        <a class="btn-icon d-none d-md-flex" id="ung-dung" href="#ung-dung" aria-label="Lưới ứng dụng">
          <i class="fa-solid fa-border-all"></i>
        </a>
     <div class="d-flex align-items-center gap-3">
            <a href="thach-dau.php" class="btn-icon d-none d-md-flex text-decoration-none" title="Sàn đấu">
                <i class="fa-solid fa-khanda"></i>
            </a>
        <a class="btn-icon" href="tin-nhan.php" aria-label="Tin nhắn">
          <i class="fa-brands fa-facebook-messenger"></i><span class="badge-dot"></span>
        </a>
        <a class="btn-icon" id="thong-bao" href="notifications.php" aria-label="Thông báo">
          <i class="fa-solid fa-bell"></i><span class="badge-dot"></span>
        </a>
        <a href="<?= htmlspecialchars($currentUser['profile_url']) ?>" id="ho-so" class="nav-profile-link d-none d-md-flex" aria-label="Hồ sơ của tôi">
          <img
            src="<?= htmlspecialchars($currentUser['avatar']) ?>"
            class="rounded-circle border nav-profile-link__img"
            width="40"
            height="40"
            alt="<?= htmlspecialchars($currentUser['name']) ?>"
          />
        </a>
      </div>
    </nav>

    <div class="layout-container">
      <aside class="sidebar-left" id="sidebarLeft">
        <div class="user-mini d-none d-md-flex">
          <img src="<?= htmlspecialchars($currentUser['avatar']) ?>" alt="<?= htmlspecialchars($currentUser['name']) ?>" width="44" height="44" />
          <div>
            <div class="name"><?= htmlspecialchars($currentUser['name']) ?></div>
            <div class="xp"><i class="fa-solid fa-fire text-danger"></i> <?= number_format($currentUser['xp']) ?> XP (<?= htmlspecialchars($currentUser['xp_rank']) ?>)</div>
          </div>
        </div>

        <div class="nav-menu">
          <a href="trang-chu.php" class="nav-item"><i class="fa-solid fa-house"></i> Bảng tin</a>
          <a href="ban-cung-tien.php" class="nav-item"><i class="fa-solid fa-user-group"></i> Bạn cùng tiến</a>
          <a href="#" class="nav-item"><i class="fa-solid fa-book-bookmark"></i> Kho Tài Liệu</a>
          <a href="#" class="nav-item"><i class="fa-solid fa-map-location-dot"></i> Lộ Trình Học</a>
          <a href="thach-dau.php" class="nav-item">
            <i class="fa-solid fa-khanda"></i> Thách Đấu
            <span class="badge-count" style="background: #ef4444">Mới</span>
          </a>
          <a href="#" class="nav-item"><i class="fa-solid fa-calendar-check"></i> Sự Kiện</a>
          <a href="tin-nhan.php" class="nav-item active">
            <i class="fa-brands fa-facebook-messenger"></i> Tin nhắn <span class="badge-count" id="sidebarInboxBadge"><?= $inboxThreadCount ?></span>
          </a>
          <a href="#" class="nav-item"><i class="fa-solid fa-bookmark"></i> Đã Lưu</a>
        </div>

        <hr class="my-3 text-muted opacity-25" />

        <h6 class="text-muted fw-bold small ms-3 mb-3 text-uppercase">Lối tắt của bạn</h6>
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
                    src="<?= htmlspecialchars($conv['avatar']) ?>"
                    width="40"
                    height="40"
                    alt=""
                  />
                  <span class="msg-row__body">
                    <span class="msg-row__name"><?= htmlspecialchars($conv['name']) ?></span>
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
                  <div class="inbox-chat__status">Đang hoạt động</div>
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
          <div class="section-title">Xu hướng học tập</div>
          <?php foreach ($trendingTags as $tag): ?>
          <a href="#<?= htmlspecialchars($tag['anchor']) ?>" id="<?= htmlspecialchars($tag['anchor']) ?>" class="trending-tag">
            <span class="hash"><?= htmlspecialchars($tag['tag']) ?></span>
            <span class="count"><?= htmlspecialchars($tag['count']) ?></span>
          </a>
          <?php endforeach; ?>
        </div>

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
          <div class="friend-avatar"><img src="<?= htmlspecialchars($friend['avatar']) ?>" alt="" /><div class="online-dot<?= $friend['status'] === 'away' ? ' away-dot' : '' ?>"></div></div>
          <span class="fw-bold small<?= $friend['status'] === 'away' ? ' text-muted' : '' ?>"><?= htmlspecialchars($friend['name']) ?></span>
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
      <a href="<?= htmlspecialchars($currentUser['profile_url']) ?>" class="nav-btn-mobile"><i class="fa-solid fa-user"></i><span>Hồ sơ</span></a>
    </div>

    <script>
      function toggleSidebar() {
  document.getElementById("sidebarLeft").classList.toggle("open");
  document.getElementById("mobileOverlay").classList.toggle("show");
}

(function () {
  var CHATS = <?= json_encode($chats, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

  var panel = document.querySelector(".inbox-panel");
  var placeholder = document.getElementById("inboxPlaceholder");
  var conversation = document.getElementById("inboxConversation");
  var listBox = document.querySelector(".inbox-panel__items");
  var headerName = document.getElementById("inboxChatHeaderName");
  var headerAvatar = document.getElementById("inboxChatHeaderAvatar");
  var messagesEl = document.getElementById("inboxChatMessages");
  var form = document.getElementById("inboxChatForm");
  var input = document.getElementById("inboxChatInput");
  var sendBtn = document.getElementById("inboxChatSend");
  var backBtn = document.getElementById("inboxChatBack");
  var toolbarSearch = document.getElementById("inboxToolbarSearch");
  var threadCountEl = document.getElementById("inboxThreadCount");

  var activeId = null;
  var draftTimer = null;

  function updateThreadCount() {
    var n = document.querySelectorAll(".msg-row[data-chat-id]").length;
    if (threadCountEl) threadCountEl.textContent = "(" + n + ")";
    var badge = document.getElementById("sidebarInboxBadge");
    if (badge) badge.textContent = String(n);
  }

  updateThreadCount();

  function bubbleHtml(msg) {
    var mine = msg.from === "me";
    return (
      '<div class="inbox-chat__bubble-wrap' +
      (mine ? " inbox-chat__bubble-wrap--mine" : "") +
      '">' +
      '<div class="inbox-chat__bubble">' +
      escapeHtml(msg.text) +
      "</div>" +
      '<span class="inbox-chat__bubble-time">' +
      escapeHtml(msg.time) +
      "</span></div>"
    );
  }

  function escapeHtml(s) {
    var d = document.createElement("div");
    d.textContent = s;
    return d.innerHTML;
  }

  function renderMessages(id) {
    var data = CHATS[id];
    if (!data) return;
    messagesEl.innerHTML = data.messages.map(bubbleHtml).join("");
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function openChat(id) {
    var data = CHATS[id];
    if (!data) return;
    activeId = id;
    document.querySelectorAll(".msg-row[data-chat-id]").forEach(function (row) {
      var sel = row.getAttribute("data-chat-id") === id;
      row.classList.toggle("is-active", sel);
      row.setAttribute("aria-selected", sel ? "true" : "false");
    });
    headerName.textContent = data.name;
    headerAvatar.src = data.avatar;
    headerAvatar.alt = data.name;
    renderMessages(id);
    var draft = sessionStorage.getItem("inbox-draft-" + id);
    input.value = draft || "";
    placeholder.hidden = true;
    placeholder.setAttribute("aria-hidden", "true");
    conversation.hidden = false;
    if (panel) panel.classList.add("inbox-panel--chat-open");
    setTimeout(function () {
      input.focus();
    }, 0);
  }

  function closeChat() {
    if (activeId) {
      sessionStorage.setItem("inbox-draft-" + activeId, input.value);
    }
    activeId = null;
    document.querySelectorAll(".msg-row[data-chat-id]").forEach(function (row) {
      row.classList.remove("is-active");
      row.setAttribute("aria-selected", "false");
    });
    conversation.hidden = true;
    placeholder.hidden = false;
    placeholder.setAttribute("aria-hidden", "false");
    if (panel) panel.classList.remove("inbox-panel--chat-open");
    input.value = "";
  }

  if (listBox) {
    listBox.addEventListener("click", function (e) {
      var row = e.target.closest(".msg-row[data-chat-id]");
      if (!row) return;
      openChat(row.getAttribute("data-chat-id"));
    });
  }

  document.querySelectorAll("a.friend-item[data-peer-id]").forEach(function (a) {
    a.addEventListener("click", function (e) {
      e.preventDefault();
      var id = a.getAttribute("data-open-chat");
      var peerId = a.getAttribute("data-peer-id");
      if (id && CHATS[id]) {
        var row = document.querySelector('.msg-row[data-chat-id="' + id + '"]');
        if (row && row.scrollIntoView) row.scrollIntoView({ behavior: "smooth", block: "nearest" });
        openChat(id);
        return;
      }
      if (!peerId) return;
      startChatWithPeer(peerId, a);
    });
  });

  function appendConversationRow(conv) {
    if (!listBox) return;
    var emptyHint = listBox.querySelector("p.text-muted");
    if (emptyHint) emptyHint.remove();

    if (document.querySelector('.msg-row[data-chat-id="' + conv.id + '"]')) {
      return;
    }

    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "msg-row";
    btn.setAttribute("role", "option");
    btn.setAttribute("data-chat-id", conv.id);
    btn.setAttribute("aria-selected", "false");
    btn.innerHTML =
      '<img class="msg-row__avatar" src="' +
      escapeHtml(conv.avatar) +
      '" width="40" height="40" alt="" />' +
      '<span class="msg-row__body">' +
      '<span class="msg-row__name">' +
      escapeHtml(conv.name) +
      "</span>" +
      '<span class="msg-row__preview">' +
      escapeHtml(conv.preview || "") +
      "</span>" +
      '<span class="msg-row__time">' +
      escapeHtml(conv.time_ago || "") +
      "</span></span>";
    listBox.insertBefore(btn, listBox.firstChild);
    updateThreadCount();
  }

  function startChatWithPeer(peerId, friendLink) {
    if (sendBusy) return;
    sendBusy = true;

    var body = new URLSearchParams();
    body.set("action", "start_chat");
    body.set("peer_id", peerId);

    fetch("tin-nhan.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString(),
    })
      .then(function (res) {
        return res.json().then(function (data) {
          if (!res.ok || !data.ok) {
            throw new Error((data && data.error) || "Không mở được cuộc trò chuyện");
          }
          return data;
        });
      })
      .then(function (data) {
        CHATS[data.chat_id] = data.chat;
        appendConversationRow(data.conversation);
        if (friendLink) {
          friendLink.setAttribute("data-open-chat", data.chat_id);
        }
        openChat(data.chat_id);
      })
      .catch(function (err) {
        alert(err.message || "Không mở được cuộc trò chuyện");
      })
      .finally(function () {
        sendBusy = false;
      });
  }

  if (toolbarSearch) {
    toolbarSearch.addEventListener("input", function () {
      var q = toolbarSearch.value.trim().toLowerCase();
      document.querySelectorAll(".msg-row[data-chat-id]").forEach(function (row) {
        var show = !q || row.textContent.toLowerCase().indexOf(q) !== -1;
        row.hidden = !show;
      });
    });
  }

  document.addEventListener("keydown", function (e) {
    if (e.key !== "Escape") return;
    if (toolbarSearch && document.activeElement === toolbarSearch && toolbarSearch.value) {
      toolbarSearch.value = "";
      toolbarSearch.dispatchEvent(new Event("input", { bubbles: true }));
      return;
    }
    if (conversation.hidden) return;
    closeChat();
  });

  input.addEventListener("input", function () {
    if (!activeId) return;
    clearTimeout(draftTimer);
    draftTimer = setTimeout(function () {
      sessionStorage.setItem("inbox-draft-" + activeId, input.value);
    }, 250);
  });

  backBtn.addEventListener("click", closeChat);

  var sendBusy = false;

  function sendMessage() {
    if (!activeId || sendBusy) return;
    var text = input.value.replace(/^\s+|\s+$/g, "");
    if (!text) return;
    sendBusy = true;

    var body = new URLSearchParams();
    body.set("action", "send_message");
    body.set("chat_id", activeId);
    body.set("text", text);

    fetch("tin-nhan.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString(),
    })
      .then(function (res) {
        return res.json().then(function (data) {
          if (!res.ok || !data.ok) {
            throw new Error((data && data.error) || "Gửi tin nhắn thất bại");
          }
          return data.message;
        });
      })
      .then(function (msg) {
        if (!CHATS[activeId]) return;
        CHATS[activeId].messages.push(msg);
        input.value = "";
        sessionStorage.removeItem("inbox-draft-" + activeId);
        renderMessages(activeId);
        var row = document.querySelector('.msg-row[data-chat-id="' + activeId + '"]');
        if (row) {
          var preview = row.querySelector(".msg-row__preview");
          if (preview) {
            preview.className = "msg-row__preview msg-row__preview--you";
            preview.innerHTML =
              '<span class="msg-row__you-label">Bạn:</span> ' + escapeHtml(msg.text);
          }
          var timeEl = row.querySelector(".msg-row__time");
          if (timeEl) timeEl.textContent = msg.time;
        }
      })
      .catch(function (err) {
        alert(err.message || "Không gửi được tin nhắn");
      })
      .finally(function () {
        sendBusy = false;
      });
  }

  form.addEventListener("submit", function (e) {
    e.preventDefault();
  });

  sendBtn.addEventListener("click", function (e) {
    e.preventDefault();
    if (e.detail < 1) return;
    sendMessage();
  });

  input.addEventListener("keydown", function (e) {
    if (e.key !== "Enter" || e.shiftKey) return;
    if (e.repeat) return;
    if (e.isComposing || e.keyCode === 229) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    sendMessage();
  });

  var urlPeerId = new URLSearchParams(window.location.search).get("peer_id");
  if (urlPeerId) {
    var friendLink = document.querySelector('a.friend-item[data-peer-id="' + urlPeerId + '"]');
    var existingChatId = friendLink ? friendLink.getAttribute("data-open-chat") : "";
    if (existingChatId && CHATS[existingChatId]) {
      openChat(existingChatId);
    } else {
      startChatWithPeer(urlPeerId, friendLink);
    }
  }
})();

    </script>
  </body>
</html>

