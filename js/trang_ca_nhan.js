        document.addEventListener('DOMContentLoaded', function() {
            // 1. Logic Sinh bản đồ Heatmap tự động
            const heatmapGrid = document.getElementById('heatmap-grid');
            if (heatmapGrid) {
                // Tạo 15 cột x 4 hàng = 60 ô
                for (let i = 0; i < 60; i++) {
                    const cell = document.createElement('div');
                    cell.className = 'heat-cell';
                    
                    const rand = Math.random();
                    if (rand > 0.9) cell.classList.add('heat-lvl-3');
                    else if (rand > 0.7) cell.classList.add('heat-lvl-2');
                    else if (rand > 0.4) cell.classList.add('heat-lvl-1');
                    
                    cell.title = `${Math.floor(Math.random() * 10)} đóng góp vào ngày này`;
                    heatmapGrid.appendChild(cell);
                }
            }

            // 2. Kích hoạt Tooltip Bootstrap
            const tooltipTriggerList = document.querySelectorAll('[title]');
            const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
        });

        // 3. Hàm kích hoạt Thông báo Thách đấu
        function triggerChallenge() {
            const toastLiveExample = document.getElementById('liveToast');
            const toast = new bootstrap.Toast(toastLiveExample);
            toast.show();
        }
