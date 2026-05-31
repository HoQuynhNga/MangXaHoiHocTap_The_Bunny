document.addEventListener('DOMContentLoaded', function () {
    var toastElList = [].slice.call(document.querySelectorAll('.toast'));
    toastElList.map(function(toastEl) { return new bootstrap.Toast(toastEl, { delay: 5000 }).show(); });

    var savedTab = sessionStorage.getItem('bunny_hang_tho_tab');
    if (savedTab) {
        var btn = document.querySelector(`button[data-bs-target="${savedTab}"]`);
        if (btn) new bootstrap.Tab(btn).show();
    }
    document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(btn => {
        btn.addEventListener('shown.bs.tab', e => sessionStorage.setItem('bunny_hang_tho_tab', e.target.getAttribute('data-bs-target')));
    });

    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            let btn = this.querySelector('button[type="submit"]');
            if(btn) {
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Đang xử lý...';
                btn.classList.add('disabled');
            }
        });
    });
});