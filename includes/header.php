<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>The Bunny - Mạng Xã Hội Học Tập</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="./assets/css/root.css" rel="stylesheet">
    <link href="./assets/css/trang-chu.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<div class="mobile-overlay" id="mobileOverlay" onclick="toggleSidebar()"></div>

<!-- ====== NAVBAR ====== -->
<nav class="navbar-bunny d-flex align-items-center justify-content-between px-3 px-md-4">
    <div class="d-flex align-items-center gap-4">
        <a href="trang-chu.php" class="brand-logo text-decoration-none">
            <i class="fa-solid fa-carrot"></i> THE BUNNY
        </a>

        <div class="search-bar d-none d-md-flex">
            <i class="fa-solid fa-search text-muted"></i>
            <input type="text" placeholder="Tìm kiếm tài liệu, bạn học..." />
        </div>
    </div>

    <div class="d-flex align-items-center gap-3">
        <a href="thach-dau.php" class="btn-icon d-none d-md-flex text-decoration-none" title="Sàn đấu">
            <i class="fa-solid fa-khanda"></i>
        </a>

        <a href="tin-nhan.php" class="btn-icon text-decoration-none" title="Tin nhắn">
            <i class="fa-brands fa-facebook-messenger"></i>
        </a>

        <a href="notifications.php" class="btn-icon text-decoration-none" title="Thông báo">
            <i class="fa-solid fa-bell"></i>
        </a>

        <div class="d-flex align-items-center gap-2 bg-light px-3 py-1 rounded-pill border d-none d-md-flex">
            <i class="fa-solid fa-fire text-danger"></i>
            <span class="fw-bold fs-6">
                <?= htmlspecialchars($currentUser['streak']) ?>
            </span>
        </div>

        <a href="<?= htmlspecialchars($currentUser['profile_url']) ?>">
            <img src="<?= htmlspecialchars($currentUser['avatar']) ?>"
                 class="rounded-circle border cursor-pointer"
                 width="40" height="40"
                 alt="<?= htmlspecialchars($currentUser['name']) ?>">
        </a>
    </div>
</nav>

<div class="layout-container">

    <!-- ====== SIDEBAR TRÁI ====== -->
    <aside class="sidebar-left" id="sidebarLeft">

        <div class="user-mini d-none d-md-flex">
            <img src="<?= htmlspecialchars($currentUser['avatar']) ?>"
                 alt="<?= htmlspecialchars($currentUser['name']) ?>">
            <div>
                <div class="name">
                    <?= htmlspecialchars($currentUser['name']) ?>
                </div>
                <div class="xp">
                    <i class="fa-solid fa-fire text-danger"></i>
                    <?= number_format($currentUser['xp']) ?>
                    XP (<?= htmlspecialchars($currentUser['xp_rank']) ?>)
                </div>
            </div>
        </div>

        <div class="nav-menu">
            <a href="#" class="nav-item active">
                <i class="fa-solid fa-house"></i> Bảng tin
            </a>

            <a href="#" class="nav-item">
                <i class="fa-solid fa-user-group"></i>
                Hang Thỏ (Nhóm)
                <span class="badge-count">3</span>
            </a>

            <a href="#" class="nav-item">
                <i class="fa-solid fa-book-bookmark"></i>
                Kho Tài Liệu
            </a>

            <a href="#" class="nav-item">
                <i class="fa-solid fa-map-location-dot"></i>
                Lộ Trình Học
            </a>

            <a href="#" class="nav-item">
                <i class="fa-solid fa-khanda"></i>
                Thách Đấu
                <span class="badge-count" style="background:#EF4444;">
                    Mới
                </span>
            </a>

            <a href="#" class="nav-item">
                <i class="fa-solid fa-calendar-check"></i>
                Sự Kiện
            </a>

            <a href="#" class="nav-item">
                <i class="fa-solid fa-bookmark"></i>
                Đã Lưu
            </a>
        </div>

        <hr class="my-3 text-muted opacity-25">

        <h6 class="text-muted fw-bold small ms-3 mb-3 text-uppercase">
            Lối tắt của bạn
        </h6>

        <div class="nav-menu">
            <?php foreach ($shortcuts as $sc): ?>
                <a href="<?= htmlspecialchars($sc['url']) ?>"
                   class="nav-item py-2">

                    <?php if ($sc['icon_type'] === 'img'): ?>
                        <img src="<?= htmlspecialchars($sc['icon_src']) ?>"
                             width="24"
                             class="rounded">
                    <?php else: ?>
                        <div class="btn-icon"
                             style="width:24px;height:24px;<?= $sc['icon_bg'] ?>">
                            <i class="fa-solid <?= htmlspecialchars($sc['icon_fa']) ?> fs-6"></i>
                        </div>
                    <?php endif; ?>

                    <?= htmlspecialchars($sc['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>

    </aside>

    <!-- ====== FEED CHÍNH ====== -->
    <main class="feed-main">
