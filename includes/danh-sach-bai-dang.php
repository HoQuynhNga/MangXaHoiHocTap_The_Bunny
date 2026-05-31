<!-- Danh sách bài đăng -->
<?php if (!empty($posts_data)): ?>
    <?php foreach ($posts_data as $post): ?>
    <article class="card shadow-sm border-0 mb-4 rounded-4 bg-white overflow-hidden">
        <div class="card-body p-4">
            
            <header class="d-flex align-items-center justify-content-between mb-3">
                <div class="d-flex align-items-center gap-3">
                    <img src="../assets/img/default-avatar.jpg" class="rounded-circle border shadow-sm" width="55" height="55" style="object-fit: cover;" alt="Tác giả bài đăng">
                    
                    <div>
                        <h6 class="m-0 fw-bold text-dark fs-5 d-flex align-items-center gap-2">
                            <?= htmlspecialchars($post['username']); ?>
                            
                            <?php if(!empty($post['giay_to_chung_minh'])): ?>
                                <i class="fa-solid fa-circle-check text-primary fs-6" title="Tài khoản học sinh xác minh hệ thống"></i>
                            <?php endif; ?>
                        </h6>
                        
                        <small class="text-muted fw-medium d-flex align-items-center gap-1">
                            <i class="fa-regular fa-clock"></i> 
                            <?= time_elapsed_string($post['created_at']); ?> 
                            <span class="mx-1">•</span> 
                            <i class="fa-solid fa-earth-americas" title="Phạm vi công khai toàn cầu"></i>
                        </small>
                    </div>
                </div>
                
                <button class="btn btn-light rounded-circle text-muted shadow-sm" style="width:40px; height:40px;" aria-label="Hiện menu Tùy chọn chức năng">
                    <i class="fa-solid fa-ellipsis"></i>
                </button>
            </header>
            
            <div class="post-content mt-3">
                <p class="m-0 fs-5 text-dark" style="line-height: 1.8; white-space: pre-line;">
                    <?= htmlspecialchars($post['noi_dung']); ?>
                </p>
            </div>
            
            <div class="d-flex justify-content-between align-items-center mt-4 border-bottom pb-2">
                <div class="d-flex align-items-center gap-2 post-stats-text">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 22px; height: 22px;">
                        <i class="fa-solid fa-thumbs-up" style="font-size: 10px;"></i>
                    </div>
                    <span class="fw-medium text-muted"><?= (int)$post['total_likes']; ?> lượt tương tác</span>
                </div>
                <div class="d-flex gap-3 text-muted fw-medium fs-6">
                    <span class="post-stats-text"><?= (int)$post['total_comments']; ?> bình luận</span>
                    <span class="post-stats-text"><?= (int)$post['total_shares']; ?> chia sẻ</span>
                </div>
            </div>
        </div>
        
        <!-- 3 nút tương tác với 3 cột -->
        <footer class="card-footer bg-white border-0 pb-3 px-3 pt-0">
            <div class="d-flex justify-content-between align-items-center w-100">
                
                <!-- Cột 1: Nút Thích -->
                <form method="POST" action="" class="flex-fill m-0 px-1">
                    <input type="hidden" name="action" value="like_post">
                    <input type="hidden" name="post_id" value="<?= $post['post_id']; ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                    
                    <button type="submit" class="btn btn-interaction w-100 py-2 d-flex align-items-center justify-content-center gap-2 <?= ($post['is_liked_by_me'] > 0) ? 'active-like' : ''; ?>">
                        <i class="fa-solid fa-thumbs-up fs-5"></i>
                        <span class="fw-bold"><?= ($post['is_liked_by_me'] > 0) ? 'Đã thích' : 'Thích'; ?></span>
                    </button>
                </form>
                
                <!-- Cột 2: Nút Bình luận -->
                <div class="flex-fill m-0 px-1">
                    <button 
                        type="button" 
                        class="btn btn-interaction w-100 py-2 d-flex align-items-center justify-content-center gap-2" 
                        data-bs-toggle="collapse" 
                        data-bs-target="#collapseComments-<?= $post['post_id']; ?>" 
                        aria-expanded="false"
                    >
                        <i class="fa-regular fa-comment fs-5"></i> 
                        <span class="fw-bold">Bình luận</span>
                    </button>
                </div>

                <!-- Cột 3: Nút Chia sẻ -->
                <div class="flex-fill m-0 px-1">
                    <button type="button" class="btn btn-interaction w-100 py-2 d-flex align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#shareModal-<?= $post['post_id']; ?>">
                        <i class="fa-solid fa-share fs-5"></i> 
                        <span class="fw-bold">Chia sẻ</span>
                    </button>
                </div>

            </div>
        </footer>

        <!-- Vùng bình luận -->
        <div class="collapse" id="collapseComments-<?= $post['post_id']; ?>">
            <div class="card-footer bg-light border-top border-light p-4">
                
                <?php
                    $sql_comments = "
                        SELECT c.noi_dung, c.created_at, u.username 
                        FROM binh_luan c 
                        INNER JOIN users u ON c.user_id = u.id 
                        WHERE c.bai_dang_id = :pid 
                        ORDER BY c.created_at ASC
                    ";
                    $stmt_cmt = $pdo->prepare($sql_comments);
                    $stmt_cmt->execute(['pid' => $post['post_id']]);
                    $comments = $stmt_cmt->fetchAll();
                ?>
                
                <?php if(count($comments) > 0): ?>
                    <div class="mb-4">
                        <h6 class="fw-bold text-muted small mb-3 text-uppercase letter-spacing-1">Phản hồi học thuật</h6>
                        <?php foreach($comments as $cmt): ?>
                            <div class="d-flex gap-3 mb-3">
                                <img src="../assets/img/default-avatar.jpg" class="rounded-circle border shadow-sm flex-shrink-0" width="40" height="40" alt="Avt Cmt">
                                <div class="comment-box w-100 shadow-sm border border-white">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <h6 class="fw-bold m-0 text-dark fs-6"><?= htmlspecialchars($cmt['username']); ?></h6>
                                        <small class="text-muted fw-medium" style="font-size: 0.75rem;"><?= time_elapsed_string($cmt['created_at']); ?></small>
                                    </div>
                                    <p class="m-0 text-dark" style="font-size: 0.95rem; line-height: 1.5;">
                                        <?= nl2br(htmlspecialchars($cmt['noi_dung'])); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="d-flex gap-3 align-items-start mt-2">
                    <input type="hidden" name="action" value="comment_post">
                    <input type="hidden" name="post_id" value="<?= $post['post_id']; ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                    
                    <img src="<?= htmlspecialchars($user_avatar); ?>" class="rounded-circle border shadow-sm flex-shrink-0" width="45" height="45" style="object-fit:cover;" alt="Avatar cá nhân">
                    
                    <div class="input-group shadow-sm rounded-pill overflow-hidden bg-white border border-light">
                        <input 
                            type="text" 
                            class="form-control border-0 px-4 py-2 fs-6 bg-transparent" 
                            name="noi_dung_binh_luan" 
                            placeholder="Đóng góp ý kiến của bạn vào cuộc thảo luận..." 
                            required
                        >
                        <button class="btn btn-primary px-4 fw-bold" type="submit">
                            <i class="fa-solid fa-paper-plane"></i>
                        </button>
                    </div>
                </form>

            </div>
        </div> 

    </article>

    <!-- Vùng chia sẻ -->
    <div class="modal fade" id="shareModal-<?= $post['post_id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-share-nodes text-primary me-2"></i>Chia sẻ bài viết</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted mb-2 fw-medium">Sao chép liên kết dưới đây để gửi cho bạn bè:</p>
                    
                    <!-- Khung Input chứa Link -->
                    <div class="input-group mb-4 shadow-sm rounded-3 overflow-hidden border border-secondary-subtle">
                        <?php 
                            // URL trỏ về bài viết đó
                            $share_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/chi-tiet-bai-dang.php?id=" . $post['post_id'];
                            ?>
                        <input type="text" class="form-control bg-light border-0 py-2 text-muted" id="linkPost-<?= $post['post_id']; ?>" value="<?= htmlspecialchars($share_link); ?>" readonly>
                        <button class="btn btn-primary fw-bold px-4" type="button" onclick="copyPostLink('linkPost-<?= $post['post_id']; ?>', <?= $post['post_id']; ?>, '<?= htmlspecialchars($csrf_token); ?>')">
                    </div>
                    
                    <hr class="border-light">
                    
                    <!-- Tính năng đăng lại trên tường  -->
                    <form method="POST" action="" class="m-0">
                        <input type="hidden" name="action" value="share_post">
                        <input type="hidden" name="post_id" value="<?= $post['post_id']; ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                        <button type="submit" class="btn btn-light border border-secondary-subtle w-100 fw-bold d-flex align-items-center justify-content-center gap-2 py-2 shadow-sm rounded-3">
                            <i class="fa-solid fa-retweet text-success fs-5"></i> Đăng tải lại lên tường nhà bạn
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- END MODAL CHIA SẺ -->

    <?php endforeach; ?>
    
<?php else: ?>
    <div class="text-center py-5 bg-white shadow-sm rounded-4 border border-light">
        <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 100px; height: 100px;">
            <i class="fa-regular fa-pen-to-square fs-1 text-primary opacity-75"></i>
        </div>
        <h4 class="text-dark fw-bold">Chưa có dấu ấn học thuật cá nhân</h4>
        <p class="text-muted fs-6 m-0 px-4">Hãy chia sẻ trạng thái học tập đầu tiên của bạn để kết nối với những người dùng The Bunny khác nhé!</p>
    </div>
<?php endif; ?>

<!-- SCRIPT HỖ TRỢ COPPY LINK -->
<script>
function copyPostLink(inputId, postId, csrfToken) {
    var copyText = document.getElementById(inputId);
    // Chọn và copy văn bản
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    
    navigator.clipboard.writeText(copyText.value).then(function() {
        alert("Đã sao chép liên kết vào bộ nhớ tạm!");
        
        // --- PHẦN AJAX THÊM MỚI: BẮN TÍN HIỆU NGẦM VỀ DATABASE ---
        
        // Đóng gói dữ liệu y hệt như một cái Form POST
        let formData = new FormData();
        formData.append('action', 'share_post');
        formData.append('post_id', postId);
        formData.append('csrf_token', csrfToken);

        // Gửi ngầm không làm load lại trang
        fetch('', { 
            method: 'POST',
            body: formData
        }).then(response => {
            console.log("Hệ thống đã ghi nhận 1 lượt chia sẻ mới từ hành vi Copy Link!");
            // Nếu muốn xịn hơn, bạn có thể dùng JS để tự động +1 vào con số hiển thị trên giao diện tại đây
        }).catch(error => {
            console.error("Lỗi tracking chia sẻ:", error);
        });
        
    }).catch(function(err) {
        alert("Lỗi! Trình duyệt của bạn không hỗ trợ copy tự động.");
    });
}
</script>
