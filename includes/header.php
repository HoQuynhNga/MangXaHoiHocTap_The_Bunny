<link rel="stylesheet" href="../assets/css/root.css">
<link rel="stylesheet" href="../assets/css/trang-chu.css">
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

        <div class="d-flex align-items-center gap-5">
            <a href="thach-dau.php" class="btn-icon d-none d-md-flex text-decoration-none" title="Thách đấu">
                <i class="fa-solid fa-khanda fa-2x"></i>
            </a>
            <a href="hang-tho.php" class="btn-icon text-decoration-none" title="Hang thỏ">
            <i class="fa-solid fa-people-group fa-2x"></i>
            </a>
            
            <a href="tin-nhan.php" class="btn-icon text-decoration-none" title="Tin nhắn">
                <i class="fa-brands fa-facebook-messenger fa-2x"></i>
            </a>
            
            <a href="notifications.php" class="btn-icon text-decoration-none" title="Thông báo">
                <i class="fa-solid fa-bell fa-2x"></i>
            </a>
            
            <!-- streak_days: users.streak_days -->
            <div class="d-flex align-items-center gap-2 bg-light px-3 py-1 rounded-pill border d-none d-md-flex">
                <i class="fa-solid fa-fire text-danger"></i>
                <span class="fw-bold fs-6"><?= htmlspecialchars($stats_xp) ?></span>
            </div>
            <div class="d-flex align-items-center gap-2 border-start ps-3 ms-1">
                <a href="trang-ca-nhan.php" title="Vào trang cá nhân">
                    <img src="<?= $user_avatar ?>" alt="Avatar" class="rounded-circle border border-2" width="40" height="40">
                </a>
                
                <a href="../models/db_xulydangxuat.php" class="btn btn-outline-danger btn-sm rounded-circle" title="Đăng xuất" style="width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </div>
        </div>
    </nav>
