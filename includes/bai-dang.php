<?php
/**
 * Tệp tin: includes/bai-dang.php
 * Chức năng: Hiển thị giao diện Form đăng bài và danh sách bài viết
 */

// ---------------------------------------------------------
// VÙNG TRUY VẤN LẤY DANH SÁCH HASHTAG TỪ DB
// ---------------------------------------------------------
try {
    $sql_hashtags = "SELECT id, ten_hashtag FROM hashtag ORDER BY ten_hashtag ASC";
    $stmt_tags = $pdo->prepare($sql_hashtags);
    $stmt_tags->execute();
    $hashtags_list = $stmt_tags->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $hashtags_list = []; // Fallback nếu lỗi
}

// ---------------------------------------------------------
// VÙNG XỬ LÝ DỮ LIỆU TỪ FORM (BACKEND CODE ĐẶT TẠI ĐÂY)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'report_post') {
    
    // Kiểm tra CSRF Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Lỗi bảo mật: CSRF Token không hợp lệ hoặc đã hết hạn!");
    }

    $nguoi_bao_cao_id   = $_SESSION['user_id'] ?? 0; 
    $bai_dang_id        = filter_input(INPUT_POST, 'bai_dang_id', FILTER_VALIDATE_INT);
    $nguoi_bi_bao_cao_id = filter_input(INPUT_POST, 'nguoi_bi_bao_cao_id', FILTER_VALIDATE_INT);
    $ly_do              = trim($_POST['ly_do'] ?? '');

    if ($nguoi_bao_cao_id > 0 && $bai_dang_id && $nguoi_bi_bao_cao_id && !empty($ly_do)) {
        try {
            $sql_report = "
                INSERT INTO bao_cao_vi_pham (nguoi_bao_cao_id, bai_dang_id, nguoi_bi_bao_cao_id, ly_do) 
                VALUES (:nguoi_bao_cao_id, :bai_dang_id, :nguoi_bi_bao_cao_id, :ly_do)
            ";
            
            $stmt_report = $pdo->prepare($sql_report);
            $stmt_report->execute([
                ':nguoi_bao_cao_id'   => $nguoi_bao_cao_id,
                ':bai_dang_id'        => $bai_dang_id,
                ':nguoi_bi_bao_cao_id' => $nguoi_bi_bao_cao_id,
                ':ly_do'              => htmlspecialchars($ly_do)
            ]);

            // Hiển thị thông báo và tải lại trang để tránh gửi lại form khi nhấn F5
            echo "<script>alert('Hệ thống đã ghi nhận báo cáo của bạn. Quản trị viên sẽ xem xét sớm nhất!'); window.location.href = window.location.href;</script>";
            exit;

        } catch (PDOException $e) {
            error_log("Lỗi DB khi báo cáo bài viết: " . $e->getMessage());
            echo "<script>alert('Đã xảy ra sự cố từ máy chủ. Vui lòng thử lại sau!');</script>";
        }
    } else {
        echo "<script>alert('Dữ liệu không hợp lệ. Vui lòng kiểm tra lại!');</script>";
    }
}
?>

<!-- Form đăng bài mới -->
<div class="card shadow-sm border-0 mb-4 rounded-4 bg-white">
    <div class="card-body p-4">
        <!-- Chú ý: Thêm onsubmit để kích hoạt JS ghép hashtag trước khi gửi đi -->
        <form method="POST" action="" onsubmit="return appendHashtagToPost(this)">
            <input type="hidden" name="action" value="add_post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
            
            <div class="d-flex gap-3">
                <img src="<?= htmlspecialchars($user_avatar); ?>" class="rounded-circle border border-2 border-light shadow-sm" width="55" height="55" style="object-fit: cover;" alt="Ảnh bạn">
                
                <div class="w-100">
                    <textarea 
                        class="form-control border-secondary-subtle bg-light rounded-4 p-3 fs-6 mb-2" 
                        name="noidung_post" 
                        id="noidung_post_id"
                        rows="3" 
                        placeholder="Bạn muốn đăng tải nội dung học thuật gì lên mạng xã hội hôm nay?..." 
                        required 
                        style="resize: none;"
                    ></textarea>
                    
                    <!-- Khối chọn Hashtag đổ dữ liệu từ DB -->
                    <select id="hashtag_select" class="form-select form-select-sm border-secondary-subtle text-primary fw-bold w-auto d-inline-block shadow-sm rounded-pill px-3">
                        <option value="">+ Gắn Hashtag (Tùy chọn)</option>
                        <?php if(!empty($hashtags_list)): ?>
                            <?php foreach($hashtags_list as $tag): ?>
                                <option value="#<?= htmlspecialchars($tag['ten_hashtag']); ?>">
                                    #<?= htmlspecialchars($tag['ten_hashtag']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            
            <hr class="text-muted border-dashed my-4 opacity-25">
            
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-danger btn-sm fw-bold rounded-pill px-3 shadow-sm" disabled title="Tạm bảo trì tính năng up PDF">
                        <i class="fa-solid fa-file-pdf me-1"></i> Gắn PDF
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm fw-bold rounded-pill px-3 shadow-sm" disabled title="Tạm bảo trì tính năng up ảnh">
                        <i class="fa-solid fa-image me-1"></i> Hình ảnh
                    </button>
                </div>
                
                <button type="submit" class="btn btn-primary fw-bold px-4 py-2 rounded-pill shadow">
                    <i class="fa-solid fa-paper-plane me-2"></i> Gửi bài viết
                </button>
            </div>
        </form>
    </div>
</div>

<!-- CẦN THIẾT: Script nối Hashtag vào Nội dung bài đăng -->
<script>
function appendHashtagToPost(formElement) {
    var contentBox = formElement.querySelector('#noidung_post_id');
    var hashtagBox = formElement.querySelector('#hashtag_select');
    
    // Nếu người dùng có chọn 1 hashtag, tự động nối xuống cuối bài viết
    if (hashtagBox && hashtagBox.value !== '') {
        contentBox.value = contentBox.value + '\n\n' + hashtagBox.value;
    }
    
    return true; // Cho phép form tiếp tục Submit gửi đi
}
</script>

<!-- Danh sách bài đăng -->
<?php if (count($posts_data) > 0): ?>
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
                
                <!-- Bắt đầu nút Tùy chọn (Dropdown) -->
                <div class="dropdown">
                 <button class="btn btn-light rounded-circle text-muted shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width:40px; height:40px;" aria-label="Hiện menu Tùy chọn chức năng">
                    <i class="fa-solid fa-ellipsis"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3">
                 <li>
                    <!-- Nút gọi Modal báo cáo -->
                    <button class="dropdown-item text-danger fw-medium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#reportModal-<?= $post['post_id']; ?>">
                    <i class="fa-solid fa-flag"></i> Báo cáo vi phạm
                    </button>
                </li>
                </ul>
                </div>
                <!-- Kết thúc nút Tùy chọn -->
            </header>
            
            <div class="post-content mt-3">
                <!-- Đoạn hashtag sẽ tự động hiện ra ở đây do đã được ghép nối bằng JS trước khi lưu -->
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

        <!-- VÙNG MODAL BÁO CÁO VI PHẠM -->
        <div class="modal fade" id="reportModal-<?= $post['post_id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <form method="POST" action="">
                        <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                            <h5 class="modal-title fw-bold text-danger">
                                <i class="fa-solid fa-triangle-exclamation me-2"></i>Báo cáo bài viết
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        
                        <div class="modal-body p-4">
                            <input type="hidden" name="action" value="report_post">
                            <input type="hidden" name="bai_dang_id" value="<?= $post['post_id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="nguoi_bi_bao_cao_id" value="<?= $post['user_id']; ?>"> 
                            <p class="text-muted mb-2 fw-medium">Vui lòng mô tả chi tiết lý do bạn báo cáo bài viết này:</p>
                            <textarea 
                                class="form-control bg-light border-secondary-subtle rounded-3 p-3" 
                                name="ly_do" 
                                rows="4" 
                                placeholder="Nội dung này vi phạm tiêu chuẩn cộng đồng vì..." 
                                required
                            ></textarea>
                        </div>
                        
                        <div class="modal-footer border-top-0 px-4 pb-4">
                            <button type="button" class="btn btn-light fw-bold px-4 rounded-pill" data-bs-dismiss="modal">Hủy</button>
                            <button type="submit" class="btn btn-danger fw-bold px-4 rounded-pill shadow">Gửi báo cáo</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- END MODAL BÁO CÁO -->

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
                            $share_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/chi-tiet-bai-dang.php?id=" . $post['post_id'];
                        ?>
                        <input type="text" class="form-control bg-light border-0 py-2 text-muted" id="linkPost-<?= $post['post_id']; ?>" value="<?= htmlspecialchars($share_link); ?>" readonly>
                        <button class="btn btn-primary fw-bold px-4" type="button" onclick="copyPostLink('linkPost-<?= $post['post_id']; ?>', <?= $post['post_id']; ?>, '<?= htmlspecialchars($csrf_token); ?>')">
                            <i class="fa-regular fa-copy"></i>
                        </button>
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
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    
    navigator.clipboard.writeText(copyText.value).then(function() {
        alert("Đã sao chép liên kết vào bộ nhớ tạm!");
        
        let formData = new FormData();
        formData.append('action', 'share_post');
        formData.append('post_id', postId);
        formData.append('csrf_token', csrfToken);

        fetch('', { 
            method: 'POST',
            body: formData
        }).then(response => {
            console.log("Hệ thống đã ghi nhận 1 lượt chia sẻ mới!");
        }).catch(error => {
            console.error("Lỗi tracking chia sẻ:", error);
        });
        
    }).catch(function(err) {
        alert("Lỗi! Trình duyệt của bạn không hỗ trợ copy tự động.");
    });
}
</script>