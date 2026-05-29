    </main>

    <!-- ====== SIDEBAR PHẢI ====== -->
    <aside class="sidebar-right">

        <div class="card-bunny p-3 mb-3">
            <div class="section-title">
                Xu hướng học tập
            </div>

            <?php foreach ($trendingTags as $tag): ?>
                <div class="trending-tag">
                    <span class="hash">
                        <?= htmlspecialchars($tag['tag']) ?>
                    </span>

                    <span class="count">
                        <?= htmlspecialchars($tag['count']) ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card-bunny p-3 mb-3">
            <div class="section-title">
                Top Đóng Góp
                <i class="fa-solid fa-trophy text-warning"></i>
            </div>

            <?php foreach ($topContributors as $contributor): ?>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="fw-bold text-muted">
                        <?= $contributor['rank'] ?>
                    </div>

                    <img src="<?= htmlspecialchars($contributor['avatar']) ?>"
                         class="rounded-circle border <?= $contributor['border_class'] ?> border-2"
                         width="32"
                         alt="<?= htmlspecialchars($contributor['name']) ?>">

                    <div class="flex-grow-1 lh-1">
                        <span class="fw-bold text-sm">
                            <?= htmlspecialchars($contributor['name']) ?>
                        </span>
                    </div>

                    <span class="text-warning fw-bold small">
                        <?= number_format($contributor['carrots']) ?>
                        <i class="fa-solid fa-carrot"></i>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

    </aside>

</div>

<!-- ====== MOBILE NAV ====== -->
<div class="bottom-nav">
    <button class="nav-btn-mobile active">
        <i class="fa-solid fa-house"></i>
        <span>Trang chủ</span>
    </button>

    <button class="nav-btn-mobile">
        <i class="fa-solid fa-user-group"></i>
        <span>Nhóm</span>
    </button>

    <button class="nav-btn-mobile"
            style="color: var(--bunny-primary); margin-top: -15px;">
        <div class="bg-primary bg-opacity-10 rounded-circle p-2 shadow-sm">
            <i class="fa-solid fa-plus fs-4"></i>
        </div>
    </button>

    <button class="nav-btn-mobile">
        <i class="fa-solid fa-bell"></i>
        <span>Thông báo</span>
    </button>

    <button class="nav-btn-mobile">
        <i class="fa-solid fa-user"></i>
        <span>Hồ sơ</span>
    </button>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="./assets/js/trang-chu.js"></script>

</body>
</html>
