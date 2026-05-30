<?php
/**
 * Tệp tin: models/db_xulybaidang.php
 * Chức năng: Xử lý các Action POST liên quan đến bài đăng
 */

// ---> LUỒNG TƯƠNG TÁC 1: NHẤN NÚT THÍCH (LIKE / UNLIKE) <---
if ($action == 'like_post') {
    $post_id = (int)$_POST['post_id'];
    
    $check_like = $pdo->prepare("SELECT 1 FROM luot_thich WHERE bai_dang_id = :pid AND user_id = :uid");
    $check_like->execute(['pid' => $post_id, 'uid' => $current_user_id]);
    
    if ($check_like->rowCount() > 0) {
        $del_like = $pdo->prepare("DELETE FROM luot_thich WHERE bai_dang_id = :pid AND user_id = :uid");
        $del_like->execute(['pid' => $post_id, 'uid' => $current_user_id]);
        $message_notify = "Đã bỏ thích bài viết.";
    } else {
        $ins_like = $pdo->prepare("INSERT INTO luot_thich (bai_dang_id, user_id, created_at) VALUES (:pid, :uid, NOW())");
        $ins_like->execute(['pid' => $post_id, 'uid' => $current_user_id]);
        $message_notify = "Đã thích bài viết thành công!";
    }
    $message_type = "success";
}

// ---> LUỒNG TƯƠNG TÁC 2: GỬI BÌNH LUẬN (COMMENT) <---
elseif ($action == 'comment_post') {
    $post_id = (int)$_POST['post_id'];
    $comment_content = sanitize_input($_POST['noi_dung_binh_luan']);
    
    if (!empty($comment_content)) {
        $ins_cmt = $pdo->prepare("INSERT INTO binh_luan (bai_dang_id, user_id, noi_dung, created_at) VALUES (:pid, :uid, :content, NOW())");
        $ins_cmt->execute([
            'pid'     => $post_id, 
            'uid'     => $current_user_id, 
            'content' => $comment_content
        ]);
        $message_notify = "Đã gửi bình luận của bạn lên hệ thống!";
        $message_type = "success";
    } else {
        throw new Exception("Nội dung bình luận không được để trống.");
    }
}

// ---> LUỒNG TƯƠNG TÁC 3: CHIA SẺ BÀI VIẾT (SHARE) <---
elseif ($action == 'share_post') {
    $post_id = (int)$_POST['post_id'];
    
    $ins_share = $pdo->prepare("INSERT INTO luot_chia_se (bai_dang_id, user_id, created_at) VALUES (:pid, :uid, NOW())");
    $ins_share->execute(['pid' => $post_id, 'uid' => $current_user_id]);
    
    $message_notify = "Đã chia sẻ bài viết thành công!";
    $message_type = "success";
}

// ---> LUỒNG: ĐĂNG BÀI VIẾT MỚI LÊN TƯỜNG (DÒNG THỜI GIAN) <---
elseif ($action == 'add_post') {
    if (!empty(trim($_POST['noidung_post']))) {
        $content = sanitize_input($_POST['noidung_post']);
        $sql_insert_post = "INSERT INTO bai_dang (user_id, noi_dung, created_at) VALUES (:uid, :content, NOW())";
        $stmt = $pdo->prepare($sql_insert_post);
        $stmt->execute(['uid' => $current_user_id, 'content' => $content]);
        $message_notify = "Đã xuất bản bài đăng lên dòng thời gian!";
        $message_type   = "success";
    } else {
        throw new Exception("Trạng thái bài viết không được để trống.");
    }
}
?>