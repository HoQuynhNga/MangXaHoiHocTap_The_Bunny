
document.addEventListener('DOMContentLoaded', function () {
    // 1. KÍCH HOẠT TOOLTIP CỦA BOOTSTRAP
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // =========================================================
    // GIỮ NGUYÊN VỊ TRÍ CUỘN & TAB KHI TẢI LẠI
    // =========================================================

    // 2. XỬ LÝ NHỚ TAB (Dùng URL Hash)
    // Đọc hash từ URL (vd: #library) và active đúng tab đó
    var currentHash = window.location.hash;
    if (currentHash) {
        var targetTab = document.querySelector('#profileTabs button[data-bs-target="' + currentHash + '"]');
        if (targetTab) {
            var tab = new bootstrap.Tab(targetTab);
            tab.show();
        }
    }

    // Lắng nghe lúc người dùng chuyển tab thì đổi luôn hash trên URL (không làm load trang)
    var triggerTabList = [].slice.call(document.querySelectorAll('#profileTabs button[data-bs-toggle="tab"]'));
    triggerTabList.forEach(function (triggerEl) {
        triggerEl.addEventListener('shown.bs.tab', function (event) {
            var targetId = event.target.getAttribute('data-bs-target');
            history.replaceState(null, null, targetId);
        });
    });

    // 3. XỬ LÝ NHỚ TỌA ĐỘ THANH CUỘN (Dùng sessionStorage)
    // Phục hồi vị trí cuộn nếu có lưu trữ từ lần trước
    var savedScrollPos = sessionStorage.getItem('bunny_scroll_pos');
    if (savedScrollPos !== null) {
        // Tắt hiệu ứng cuộn mượt (smooth) để nó nhảy ngay lập tức không bị giật lag
        window.scrollTo({ top: parseInt(savedScrollPos), behavior: 'auto' });
        // Phục hồi xong thì xóa rác
        sessionStorage.removeItem('bunny_scroll_pos');
    }
});

// Bắt sự kiện ngay trước khi trang bị F5 hoặc Chuyển hướng do Form Submit
window.addEventListener('beforeunload', function () {
    // Lưu tọa độ hiện tại vào bộ nhớ tạm của trình duyệt
    sessionStorage.setItem('bunny_scroll_pos', window.scrollY);
});
