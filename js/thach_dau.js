 // JS Xử lý chọn đáp án
        function selectOption(element) {
            const options = document.querySelectorAll('.mcq-option');
            options.forEach(opt => opt.classList.remove('selected'));
            element.classList.add('selected');
        }

        // JS Bắn Toast Thách Đấu
        function startBattle() {
            bootstrap.Modal.getInstance(document.getElementById('battleModal')).hide();
            document.getElementById('toastMessage').innerHTML = '<i class="fa-solid fa-paper-plane text-warning me-2"></i> Lời thách đấu đã được gửi đi!';
            new bootstrap.Toast(document.getElementById('systemToast')).show();
        }
