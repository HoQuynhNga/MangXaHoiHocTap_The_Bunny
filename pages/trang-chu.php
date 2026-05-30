<?php
// PHẦN 1: KẾT NỐI DATABASE VÀ CHUẨN BỊ BIẾN
require_once '../config/config.php';
$currentUser = [
    'id'         => 1,                                      // users.user_id
    'name'       => 'Alex Nguyễn',                          // users.full_name
    'avatar'     => 'https://i.pravatar.cc/150?img=12',     // users.avatar_url
    'xp'         => 1520,                                   // users.xp_points
    'xp_rank'    => 'Top 5%',                               // Tính từ bảng xếp hạng
    'streak'     => 15,                                     // users.streak_days
    'profile_url'=> 'trang-ca-nhan.php',                    // Đường dẫn trang cá nhân
];

// --- STORIES / FLASHCARD SNAPS (stories) ---
$stories = [
    [
        'id'         => 1,                                  // stories.story_id
        'user_name'  => 'Minh Tuấn',                        // users.full_name
        'user_avatar'=> 'https://i.pravatar.cc/150?img=5',  // users.avatar_url
        'image'      => 'https://images.unsplash.com/photo-1517842645767-c639042777db?auto=format&fit=crop&w=300&q=80', // stories.media_url
        'caption'    => 'Mindmap Lý 9',                     // stories.caption
        'has_border' => true,                               // Hiệu ứng: story chưa xem
    ],
    [
        'id'         => 2,
        'user_name'  => 'Hoàng Oanh',
        'user_avatar'=> 'https://i.pravatar.cc/150?img=9',
        'image'      => 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?auto=format&fit=crop&w=300&q=80',
        'caption'    => 'Từ vựng IELTS',
        'has_border' => false,                              // Story đã xem
    ],
    [
        'id'         => 3,
        'user_name'  => 'Trần Phong',
        'user_avatar'=> 'https://i.pravatar.cc/150?img=11',
        'image'      => 'https://images.unsplash.com/photo-1531403009284-440f080d1e12?auto=format&fit=crop&w=300&q=80',
        'caption'    => 'Review Design',
        'has_border' => true,
    ],
];

// --- BÀI ĐĂNG TRÊN BẢNG TIN (posts + users + groups) ---
$posts = [
    [
        'id'            => 1,                               // posts.post_id
        'author_id'     => 2,                               // posts.user_id
        'author_name'   => 'Lê Minh Tuấn',                 // users.full_name
        'author_avatar' => 'https://i.pravatar.cc/150?img=5', // users.avatar_url
        'is_verified'   => true,                            // users.is_verified
        'time_ago'      => '2 giờ trước',                   // Tính từ posts.created_at
        'group_name'    => 'Toán Lý Hóa Khối 9',           // groups.group_name
        'group_url'     => '#',                             // groups.group_id → URL
        'content'       => 'Vừa tổng hợp xong đề cương ôn tập thi Học kỳ 2 môn Vật Lý phần Quang Học. Các bạn trong nhóm tham khảo để cuối tuần mình làm bài test thử trên Bunny luôn nha! 📚✨', // posts.content
        'tags'          => ['#VatLy9', '#OnThiHK2'],        // post_tags → tags.tag_name
        'type'          => 'document',                      // posts.post_type
        'document'      => [
            'name'      => 'DeCuong_QuangHoc_HK2.pdf',     // documents.file_name
            'size'      => '2.4 MB',                        // documents.file_size
            'url'       => '#',                             // documents.file_url
            'icon_class'=> 'fa-file-pdf',                   // Xác định từ documents.file_type
            'icon_color'=> 'text-danger',
            'icon_bg'   => 'bg-danger bg-opacity-10',
        ],
        'reactions_count'=> 145,                            // COUNT từ post_reactions
        'comments_count' => 24,                             // COUNT từ post_comments
        'downloads_count'=> 12,                             // documents.download_count
    ],
    [
        'id'            => 2,
        'author_id'     => 3,
        'author_name'   => 'Trần Phong',
        'author_avatar' => 'https://i.pravatar.cc/150?img=11',
        'is_verified'   => false,
        'time_ago'      => '5 giờ trước',
        'group_name'    => null,                            // Bài đăng công khai, không thuộc nhóm
        'privacy_icon'  => '🌍',                            // posts.privacy ('public')
        'content'       => 'Làm sao để tính toán giá trị vòng đời khách hàng (CLV) trong mô hình C2C khi dữ liệu mua lặp lại không ổn định mọi người nhỉ? Đang kẹt chỗ này trong dự án phân tích kinh doanh. Mọi người cho xin ý kiến với! 😰',
        'tags'          => ['#BabeNobuli', '#ECommerce', '#BusinessModel'],
        'type'          => 'question',                      // posts.post_type
        'document'      => null,
        'reactions_count'=> 32,
        'comments_count' => 18,
        'downloads_count'=> 0,
    ],
];

// --- XU HƯỚNG HỌC TẬP / TRENDING TAGS (tags + post_tags) ---
$trendingTags = [
    [
        'tag'   => '#BabeNobuli_Project',                   // tags.tag_name
        'count' => '1.2k bài thảo luận',                   // COUNT post_tags
    ],
    [
        'tag'   => '#Figma_Design_System',
        'count' => '850 tài liệu mới',
    ],
    [
        'tag'   => '#ĐềThiThử_Lý9',
        'count' => '540 lượt thi hôm nay',
    ],
];

// --- TOP ĐÓNG GÓP (users + leaderboard) ---
$topContributors = [
    [
        'rank'         => 1,                                // leaderboard.rank
        'user_id'      => 1,                               // users.user_id
        'name'         => 'Alex Nguyễn',                   // users.full_name
        'avatar'       => 'https://i.pravatar.cc/150?img=12', // users.avatar_url
        'carrots'      => 520,                             // leaderboard.score / xp_points
        'border_class' => 'border-warning',                // Hạng 1 = vàng
    ],
    [
        'rank'         => 2,
        'user_id'      => 2,
        'name'         => 'Minh Tuấn',
        'avatar'       => 'https://i.pravatar.cc/150?img=5',
        'carrots'      => 480,
        'border_class' => 'border-secondary',              // Hạng 2 = bạc
    ],
];

// --- BẠN BÈ ĐANG ONLINE (users + user_sessions / presence) ---
$onlineFriends = [
    [
        'user_id'   => 4,                                  // users.user_id
        'name'      => 'Hoàng Oanh',                       // users.full_name
        'avatar'    => 'https://i.pravatar.cc/150?img=9',  // users.avatar_url
        'status'    => 'online',                           // user_sessions.status: 'online' | 'away'
    ],
    [
        'user_id'   => 3,
        'name'      => 'Trần Phong',
        'avatar'    => 'https://i.pravatar.cc/150?img=11',
        'status'    => 'away',
    ],
    [
        'user_id'   => 5,
        'name'      => 'Chi Lê',
        'avatar'    => 'https://i.pravatar.cc/150?img=4',
        'status'    => 'online',
    ],
];

// --- LỐI TẮT NHÓM / DỰ ÁN (user_shortcuts → groups / projects) ---
$shortcuts = [
    [
        'label'      => 'Thiết kế UI/UX',                  // groups.group_name
        'icon_type'  => 'img',
        'icon_src'   => 'https://upload.wikimedia.org/wikipedia/commons/3/33/Figma-logo.svg',
        'url'        => '#',                                // groups.group_id → URL
    ],
    [
        'label'      => 'Ôn thi Vật Lý 9',
        'icon_type'  => 'fa',
        'icon_fa'    => 'fa-atom',
        'icon_bg'    => 'background:#E0F2FE;color:#0284C7;',
        'url'        => '#',
    ],
    [
        'label'      => 'Dự án Babe Nobuli',
        'icon_type'  => 'fa',
        'icon_fa'    => 'fa-chart-line',
        'icon_bg'    => 'background:#FEF3C7;color:#D97706;',
        'url'        => '#',
    ],
];

// ============================================================
// PHẦN 2: THỰC THI SQL (QUERY, PROCEDURE, TRIGGER)
// ============================================================
// Ví dụ khi kết nối DB thật (bỏ comment khi có config.php):
//
// $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
// $stmt->execute([$_SESSION['user_id']]);
// $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
//
// $posts = $pdo->query("
//     SELECT p.*, u.full_name, u.avatar_url, u.is_verified,
//            TIMESTAMPDIFF(HOUR, p.created_at, NOW()) as hours_ago
//     FROM posts p
//     JOIN users u ON p.user_id = u.user_id
//     ORDER BY p.created_at DESC
//     LIMIT 20
// ")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>The Bunny - Mạng Xã Hội Học Tập</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/root.css" rel="stylesheet">
    <link href="../assets/css/trang-chu.css" rel="stylesheet">
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
            
            <!-- streak_days: users.streak_days -->
            <div class="d-flex align-items-center gap-2 bg-light px-3 py-1 rounded-pill border d-none d-md-flex">
                <i class="fa-solid fa-fire text-danger"></i>
                <span class="fw-bold fs-6"><?= htmlspecialchars($currentUser['streak']) ?></span>
            </div>
            
            <!-- Avatar người dùng: users.avatar_url, users.full_name -->
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
            <!-- users.full_name, users.avatar_url, users.xp_points, xp_rank -->
            <div class="user-mini d-none d-md-flex">
                <img src="<?= htmlspecialchars($currentUser['avatar']) ?>"
                     alt="<?= htmlspecialchars($currentUser['name']) ?>">
                <div>
                    <div class="name"><?= htmlspecialchars($currentUser['name']) ?></div>
                    <div class="xp">
                        <i class="fa-solid fa-fire text-danger"></i>
                        <?= number_format($currentUser['xp']) ?> XP (<?= htmlspecialchars($currentUser['xp_rank']) ?>)
                    </div>
                </div>
            </div>

            <div class="nav-menu">
                <a href="#" class="nav-item active"><i class="fa-solid fa-house"></i> Bảng tin</a>
                <a href="ban-cung-tien.php" class="nav-item"><i class="fa-solid fa-user-group"></i> Bạn cùng tiến</a>
                <a href="#" class="nav-item"><i class="fa-solid fa-book-bookmark"></i> Kho Tài Liệu</a>
                <a href="#" class="nav-item"><i class="fa-solid fa-map-location-dot"></i> Lộ Trình Học</a>
                <a href="#" class="nav-item"><i class="fa-solid fa-khanda"></i> Thách Đấu <span class="badge-count" style="background: #EF4444;">Mới</span></a>
                <a href="#" class="nav-item"><i class="fa-solid fa-calendar-check"></i> Sự Kiện</a>
                <a href="#" class="nav-item"><i class="fa-solid fa-bookmark"></i> Đã Lưu</a>
            </div>
            
            <hr class="my-3 text-muted opacity-25">
            
            <h6 class="text-muted fw-bold small ms-3 mb-3 text-uppercase">Lối tắt của bạn</h6>
            <div class="nav-menu">
                <!-- Loop qua $shortcuts: user_shortcuts JOIN groups/projects -->
                <?php foreach ($shortcuts as $sc): ?>
                <a href="<?= htmlspecialchars($sc['url']) ?>" class="nav-item py-2">
                    <?php if ($sc['icon_type'] === 'img'): ?>
                        <img src="<?= htmlspecialchars($sc['icon_src']) ?>" width="24" class="rounded">
                    <?php else: ?>
                        <div class="btn-icon" style="width:24px;height:24px;<?= $sc['icon_bg'] ?>">
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
            
            <!-- STORIES: stories JOIN users -->
            <div class="stories-container">
                <!-- Nút tạo mới (tĩnh) -->
                <div class="story-card story-create">
                    <div class="story-create-btn"><i class="fa-solid fa-plus"></i></div>
                    <div class="overlay"><span class="story-name">Tạo Flashcard</span></div>
                </div>

                <!-- Loop qua $stories -->
                <?php foreach ($stories as $story): ?>
                <div class="story-card">
                    <!-- stories.media_url -->
                    <img src="<?= htmlspecialchars($story['image']) ?>"
                         alt="<?= htmlspecialchars($story['caption']) ?>">
                    <div class="overlay">
                        <!-- users.avatar_url, border nếu chưa xem -->
                        <img src="<?= htmlspecialchars($story['user_avatar']) ?>"
                             class="story-avatar"
                             <?= !$story['has_border'] ? 'style="border-color: transparent;"' : '' ?>>
                        <!-- stories.caption -->
                        <span class="story-name"><?= htmlspecialchars($story['caption']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- COMPOSER (ô đăng bài) -->
            <div class="card-bunny">
                <div class="composer-input-area">
                    <!-- users.avatar_url -->
                    <img src="<?= htmlspecialchars($currentUser['avatar']) ?>"
                         class="rounded-circle" width="40" height="40"
                         alt="<?= htmlspecialchars($currentUser['name']) ?>">
                    <input type="text" placeholder="Bạn muốn chia sẻ kiến thức hay tài liệu gì?">
                </div>
                <div class="composer-actions">
                    <button class="btn-composer text-danger"><i class="fa-solid fa-file-pdf"></i> <span class="d-none d-sm-inline">Tài liệu</span></button>
                    <button class="btn-composer text-primary"><i class="fa-solid fa-circle-question"></i> <span class="d-none d-sm-inline">Hỏi bài</span></button>
                    <button class="btn-composer text-success"><i class="fa-solid fa-list-check"></i> <span class="d-none d-sm-inline">Tạo Quiz</span></button>
                </div>
            </div>

            <!-- BÀI ĐĂNG: Loop qua $posts -->
            <?php foreach ($posts as $post): ?>
            <div class="card-bunny">
                <div class="post-header">
                    <div class="d-flex gap-3">
                        <!-- users.avatar_url -->
                        <img src="<?= htmlspecialchars($post['author_avatar']) ?>"
                             class="rounded-circle" width="44" height="44"
                             alt="<?= htmlspecialchars($post['author_name']) ?>">
                        <div>
                            <!-- users.full_name, users.is_verified -->
                            <div class="post-author">
                                <?= htmlspecialchars($post['author_name']) ?>
                                <?php if ($post['is_verified']): ?>
                                    <i class="fa-solid fa-circle-check text-primary fs-6" title="Xác thực"></i>
                                <?php endif; ?>
                            </div>
                            <!-- posts.created_at (tính khoảng cách), groups.group_name -->
                            <div class="post-time">
                                <?= htmlspecialchars($post['time_ago']) ?>
                                <?php if (!empty($post['group_name'])): ?>
                                    · Trong nhóm <strong><a href="<?= htmlspecialchars($post['group_url']) ?>"><?= htmlspecialchars($post['group_name']) ?></a></strong>
                                <?php elseif (!empty($post['privacy_icon'])): ?>
                                    · <?= $post['privacy_icon'] ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-light rounded-circle border-0"><i class="fa-solid fa-ellipsis"></i></button>
                </div>
                
                <!-- posts.content -->
                <div class="post-content">
                    <?= nl2br(htmlspecialchars($post['content'])) ?>
                    <?php if (!empty($post['tags'])): ?>
                    <br><br>
                    <span class="post-tags">
                        <?php foreach ($post['tags'] as $tag): ?>
                            <?= htmlspecialchars($tag) ?>&nbsp;
                        <?php endforeach; ?>
                    </span>
                    <?php endif; ?>
                </div>

                <!-- Nếu post_type = 'document': hiển thị documents -->
                <?php if ($post['type'] === 'document' && $post['document']): ?>
                <div class="doc-preview">
                    <!-- documents.file_type → icon -->
                    <div class="doc-icon <?= $post['document']['icon_bg'] ?> <?= $post['document']['icon_color'] ?>">
                        <i class="fa-solid <?= $post['document']['icon_class'] ?>"></i>
                    </div>
                    <div class="flex-grow-1">
                        <!-- documents.file_name -->
                        <div class="fw-bold text-dark"><?= htmlspecialchars($post['document']['name']) ?></div>
                        <!-- documents.file_size, documents.file_type -->
                        <div class="text-muted small">Tài liệu PDF · <?= htmlspecialchars($post['document']['size']) ?></div>
                    </div>
                    <!-- documents.file_url -->
                    <a href="<?= htmlspecialchars($post['document']['url']) ?>"
                       class="btn btn-light border rounded-circle"
                       style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-download"></i>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Thống kê: COUNT post_reactions, COUNT post_comments, documents.download_count -->
                <div class="post-stats">
                    <div>
                        <?php if ($post['type'] === 'document'): ?>
                            <i class="fa-solid fa-carrot text-warning bg-light p-1 rounded-circle"></i>
                            <i class="fa-solid fa-thumbs-up text-primary bg-light p-1 rounded-circle"></i>
                        <?php else: ?>
                            <i class="fa-solid fa-lightbulb text-warning bg-light p-1 rounded-circle"></i>
                        <?php endif; ?>
                        <span class="fw-bold ms-1 text-dark"><?= number_format($post['reactions_count']) ?></span>
                    </div>
                    <div>
                        <?php if ($post['type'] === 'document'): ?>
                            <?= number_format($post['comments_count']) ?> Bình luận · <?= number_format($post['downloads_count']) ?> Lượt tải
                        <?php else: ?>
                            <?= number_format($post['comments_count']) ?> Câu trả lời
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="reaction-btns">
                    <?php if ($post['type'] === 'document'): ?>
                        <button class="btn-composer"><i class="fa-regular fa-thumbs-up"></i> Hữu ích</button>
                        <button class="btn-composer"><i class="fa-regular fa-comment"></i> Thảo luận</button>
                        <button class="btn-composer"><i class="fa-regular fa-bookmark"></i> Lưu lại</button>
                    <?php else: ?>
                        <button class="btn-composer text-warning bg-warning bg-opacity-10"><i class="fa-solid fa-lightbulb"></i> Giải đáp</button>
                        <button class="btn-composer"><i class="fa-solid fa-share"></i> Chia sẻ</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

        </main>

        <!-- ====== SIDEBAR PHẢI ====== -->
        <aside class="sidebar-right">
            
            <!-- XU HƯỚNG: tags JOIN post_tags -->
            <div class="card-bunny p-3 mb-3">
                <div class="section-title">Xu hướng học tập</div>
                <?php foreach ($trendingTags as $tag): ?>
                <div class="trending-tag">
                    <!-- tags.tag_name -->
                    <span class="hash"><?= htmlspecialchars($tag['tag']) ?></span>
                    <!-- COUNT từ post_tags -->
                    <span class="count"><?= htmlspecialchars($tag['count']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- TOP ĐÓNG GÓP: leaderboard JOIN users -->
            <div class="card-bunny p-3 mb-3">
                <div class="section-title">Top Đóng Góp <i class="fa-solid fa-trophy text-warning"></i></div>
                <?php foreach ($topContributors as $contributor): ?>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <!-- leaderboard.rank -->
                    <div class="fw-bold text-muted"><?= $contributor['rank'] ?></div>
                    <!-- users.avatar_url -->
                    <img src="<?= htmlspecialchars($contributor['avatar']) ?>"
                         class="rounded-circle border <?= $contributor['border_class'] ?> border-2"
                         width="32"
                         alt="<?= htmlspecialchars($contributor['name']) ?>">
                    <!-- users.full_name -->
                    <div class="flex-grow-1 lh-1">
                        <span class="fw-bold text-sm"><?= htmlspecialchars($contributor['name']) ?></span>
                    </div>
                    <!-- leaderboard.score / users.xp_points -->
                    <span class="text-warning fw-bold small">
                        <?= number_format($contributor['carrots']) ?> <i class="fa-solid fa-carrot"></i>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- BẠN ONLINE: users JOIN user_sessions (presence) -->
            <div class="section-title ps-2 mb-2">Bạn cùng tiến online</div>
            <?php foreach ($onlineFriends as $friend): ?>
            <div class="friend-item">
                <div class="friend-avatar">
                    <!-- users.avatar_url -->
                    <img src="<?= htmlspecialchars($friend['avatar']) ?>"
                         alt="<?= htmlspecialchars($friend['name']) ?>">
                    <!-- user_sessions.status: 'online' | 'away' -->
                    <div class="online-dot <?= $friend['status'] === 'away' ? 'away-dot' : '' ?>"></div>
                </div>
                <!-- users.full_name -->
                <div class="fw-bold small <?= $friend['status'] === 'away' ? 'text-muted' : '' ?>">
                    <?= htmlspecialchars($friend['name']) ?>
                </div>
            </div>
            <?php endforeach; ?>
            
        </aside>

    </div>

    <!-- BOTTOM NAV (Mobile) -->
    <div class="bottom-nav">
        <button class="nav-btn-mobile active">
            <i class="fa-solid fa-house"></i><span>Trang chủ</span>
        </button>
        <a href="ban-cung-tien.php" class="nav-btn-mobile"><i class="fa-solid fa-user-group"></i><span>Bạn bè</span></a>
        <button class="nav-btn-mobile" style="color: var(--bunny-primary); margin-top: -15px;">
            <div class="bg-primary bg-opacity-10 rounded-circle p-2 shadow-sm"><i class="fa-solid fa-plus fs-4"></i></div>
        </button>
        <button class="nav-btn-mobile">
            <i class="fa-solid fa-bell"></i><span>Thông báo</span>
        </button>
        <button class="nav-btn-mobile">
            <i class="fa-solid fa-user"></i><span>Hồ sơ</span>
        </button>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script src='./assets/js/trang-chu.js'></script>
</body>
</html>
