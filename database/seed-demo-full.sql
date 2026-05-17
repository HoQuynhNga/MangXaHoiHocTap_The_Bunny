-- The Bunny — Dữ liệu mẫu đầy đủ (chạy SAU khi đã import schema class diagram)
-- Mật khẩu demo cho mọi user: password

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE bao_cao_thong_ke;
TRUNCATE TABLE thong_bao;
TRUNCATE TABLE thanh_vien_su_kien;
TRUNCATE TABLE su_kien;
TRUNCATE TABLE tin_nhan;
TRUNCATE TABLE cuoc_tro_chuyen_thanh_vien;
TRUNCATE TABLE cuoc_tro_chuyen;
TRUNCATE TABLE ban_cung_tien;
TRUNCATE TABLE bai_dang;
TRUNCATE TABLE tai_lieu;
TRUNCATE TABLE user_phong_thach_dau;
TRUNCATE TABLE phong_thach_dau;
TRUNCATE TABLE bo_de;
TRUNCATE TABLE phien_luyen_tap;
TRUNCATE TABLE user_hang_tho;
TRUNCATE TABLE hang_tho;
TRUNCATE TABLE ho_so_ca_nhan;
TRUNCATE TABLE users;

SET FOREIGN_KEY_CHECKS = 1;

SET @pwd = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

INSERT INTO users (id, username, email, password_hash, status, is_online, user_type, truong_hoc) VALUES
(1, 'alex_nguyen', 'alex@bunny.local', @pwd, 'Active', 1, 'hoc_sinh', 'THCS Bunny'),
(2, 'minh_tuan', 'minh@bunny.local', @pwd, 'Active', 0, 'hoc_sinh', 'THCS Bunny'),
(3, 'tran_phong', 'phong@bunny.local', @pwd, 'Active', 0, 'sinh_vien', 'THPT Sài Gòn'),
(4, 'hoang_oanh', 'oanh@bunny.local', @pwd, 'Active', 1, 'hoc_sinh', 'THCS Bunny'),
(5, 'chi_le', 'chi@bunny.local', @pwd, 'Active', 1, 'hoc_sinh', 'THCS Bunny'),
(6, 'meagan', 'meagan@bunny.local', @pwd, 'Active', 0, 'hoc_sinh', 'THPT ABC'),
(7, 'reba', 'reba@bunny.local', @pwd, 'Active', 0, 'sinh_vien', 'ĐH Kinh tế'),
(8, 'co_le', 'cole@bunny.local', @pwd, 'Active', 0, 'giao_vien', 'THCS Bunny'),
(9, 'admin_bunny', 'admin@bunny.local', @pwd, 'Active', 0, 'quan_tri_vien', NULL);

INSERT INTO ho_so_ca_nhan (user_id, thong_tin_dinh_danh) VALUES
(1, CONCAT('BIO: Định hướng UI/UX & Kinh doanh (Babe Nobuli)', CHAR(10),
 'QUOTE: "Kiến thức là nền tảng, thiết kế là giải pháp."', CHAR(10),
 'EDU: Đang học <b>Lớp 9</b> - Mục tiêu: Chuyên Lý', CHAR(10),
 'JOB: Founder & Thiết kế UI/UX tại <b>Babe Nobuli</b>', CHAR(10),
 'LOC: Sống tại <b>TP. Hồ Chí Minh</b>')),
(2, 'Lê Minh Tuấn — Ôn thi Lý 9'),
(3, 'Trần Phong — Phân tích kinh doanh'),
(4, 'Hoàng Oanh'),
(5, 'Chi Lê'),
(6, 'Meagan McLaughlin'),
(7, 'Reba Reynolds'),
(8, 'Cô Lê — Giáo viên Vật lý'),
(9, 'Quản trị hệ thống The Bunny');

INSERT INTO hang_tho (id, ten_hang_tho) VALUES
(1, 'Thiết kế UI/UX'),
(2, 'Ôn thi Vật Lý 9'),
(3, 'Dự án Babe Nobuli'),
(4, 'Toán Lý Hóa Khối 9');

INSERT INTO user_hang_tho (user_id, hang_tho_id) VALUES
(1, 1), (1, 2), (1, 3),
(2, 2), (2, 4),
(3, 3),
(4, 2), (4, 4),
(5, 2),
(1, 4);

INSERT INTO phien_luyen_tap (user_id, diem_so, created_at) VALUES
(1, 520, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(1, 1000, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, 480, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(3, 320, DATE_SUB(NOW(), INTERVAL 7 DAY)),
(4, 410, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(5, 290, DATE_SUB(NOW(), INTERVAL 8 DAY)),
(1, 120, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1, 80, DATE_SUB(NOW(), INTERVAL 2 DAY));

INSERT INTO bo_de (id, ten_bo_de) VALUES
(1, 'Đề thi thử Vật Lý 9 — HK2'),
(2, 'Quiz UI/UX cơ bản');

INSERT INTO phong_thach_dau (id, bo_de_id) VALUES
(1, 1),
(2, 2);

INSERT INTO user_phong_thach_dau (user_id, phong_thach_dau_id) VALUES
(1, 1), (2, 1), (4, 1), (1, 2);

INSERT INTO tai_lieu (id, user_id, ten_tai_lieu, file_url, created_at) VALUES
(1, 2, 'Mindmap Lý 9', 'https://images.unsplash.com/photo-1517842645767-c639042777db?auto=format&fit=crop&w=300&q=80', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 4, 'Từ vựng IELTS', 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?auto=format&fit=crop&w=300&q=80', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(3, 3, 'Review Design', 'https://images.unsplash.com/photo-1531403009284-440f080d1e12?auto=format&fit=crop&w=300&q=80', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(4, 2, 'DeCuong_QuangHoc_HK2.pdf', 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(5, 1, 'BabeNobuli_Flow_Final.fig', 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=800&q=80', DATE_SUB(NOW(), INTERVAL 2 HOUR));

INSERT INTO bai_dang (id, user_id, noi_dung, created_at) VALUES
(1, 2, 'Vừa tổng hợp xong đề cương ôn tập thi Học kỳ 2 môn Vật Lý phần Quang Học. Các bạn trong nhóm tham khảo để cuối tuần mình làm bài test thử trên Bunny luôn nha! 📚 #VatLy9 #OnThiHK2', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(2, 3, 'Làm sao để tính toán giá trị vòng đời khách hàng (CLV) trong mô hình C2C khi dữ liệu mua lặp lại không ổn định mọi người nhỉ? Đang kẹt chỗ này trong dự án phân tích kinh doanh. #BabeNobuli #ECommerce', DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(3, 1, 'Hoàn tất mô hình tài chính và luồng vận hành cho nền tảng Babe Nobuli. 🚀 Tập trung Hybrid B2C và C2C.', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(4, 1, 'Góc đính chính Văn học: Les Misérables — Jean Valjean là người chịu án tù, không phải Fantine. #VanHocNuocNgoai', DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO ban_cung_tien (user_id, friend_user_id, status) VALUES
(1, 2, 'Accepted'), (2, 1, 'Accepted'),
(1, 3, 'Accepted'), (3, 1, 'Accepted'),
(1, 4, 'Accepted'), (4, 1, 'Accepted'),
(1, 5, 'Accepted'), (5, 1, 'Accepted'),
(1, 6, 'Accepted'), (6, 1, 'Accepted'),
(1, 7, 'Accepted'), (7, 1, 'Accepted'),
(2, 4, 'Accepted'), (4, 2, 'Accepted');

INSERT INTO cuoc_tro_chuyen (id) VALUES (1), (2), (3), (4), (5);

INSERT INTO cuoc_tro_chuyen_thanh_vien (cuoc_tro_chuyen_id, user_id) VALUES
(1, 1), (1, 6),
(2, 1), (2, 7),
(3, 1), (3, 4),
(4, 1), (4, 3),
(5, 1), (5, 5);

INSERT INTO tin_nhan (cuoc_tro_chuyen_id, sender_user_id, noi_dung, thoi_gian) VALUES
(1, 6, 'This is a sample massage!', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(2, 1, 'Hi', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(2, 7, 'Chào bạn! Học nhóm chiều nay nhé?', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(3, 4, 'Nhóm mình học 8h tối nhé!', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(4, 1, 'Ok nhé', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(4, 3, 'Tài liệu mình gửi link drive rồi đó.', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(5, 5, 'Flashcard Lý gửi bạn nè', DATE_SUB(NOW(), INTERVAL 7 DAY));

INSERT INTO su_kien (id, tieu_de, thoi_gian) VALUES
(1, 'Hackathon The Bunny 2026', DATE_ADD(NOW(), INTERVAL 14 DAY)),
(2, 'Workshop Figma cho học sinh', DATE_ADD(NOW(), INTERVAL 7 DAY));

INSERT INTO thanh_vien_su_kien (su_kien_id, user_id, trang_thai_duyet) VALUES
(1, 1, 'Approved'), (1, 2, 'Approved'), (1, 4, 'Pending'),
(2, 1, 'Approved'), (2, 3, 'Approved');

INSERT INTO thong_bao (user_id, noi_dung, is_read) VALUES
(1, 'Chào mừng bạn đến với The Bunny!', 1),
(1, 'Hoàng Oanh đã gửi tin nhắn mới.', 0),
(2, 'Bài đăng của bạn được 10 lượt thích.', 0),
(4, 'Nhóm Ôn thi Vật Lý 9 có lịch học tối nay.', 0);

INSERT INTO bao_cao_thong_ke (quan_tri_user_id, loai_bao_cao, noi_dung_bao_cao) VALUES
(9, 'users_active', 'Báo cáo người dùng hoạt động tuần này: 9 tài khoản, 4 online.'),
(9, 'posts_summary', 'Tổng 4 bài đăng, 5 tài liệu, 5 cuộc trò chuyện.');
