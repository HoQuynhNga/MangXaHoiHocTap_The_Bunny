-- MySQL — schema theo class diagram (main.tex).
-- Khóa chính dùng INT tự tăng cho dễ INSERT trong bài tập.

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS bao_cao_thong_ke;
DROP TABLE IF EXISTS thong_bao;
DROP TABLE IF EXISTS thanh_vien_su_kien;
DROP TABLE IF EXISTS su_kien;
DROP TABLE IF EXISTS tin_nhan;
DROP TABLE IF EXISTS cuoc_tro_chuyen_thanh_vien;
DROP TABLE IF EXISTS cuoc_tro_chuyen;
DROP TABLE IF EXISTS ban_cung_tien;
DROP TABLE IF EXISTS bai_dang;
DROP TABLE IF EXISTS tai_lieu;
DROP TABLE IF EXISTS user_phong_thach_dau;
DROP TABLE IF EXISTS phong_thach_dau;
DROP TABLE IF EXISTS bo_de;
DROP TABLE IF EXISTS phien_luyen_tap;
DROP TABLE IF EXISTS user_hang_tho;
DROP TABLE IF EXISTS hang_tho;
DROP TABLE IF EXISTS ho_so_ca_nhan;
DROP TABLE IF EXISTS users;

-- User + các loại kế thừa (một bảng, phân biệt bằng user_type)
CREATE TABLE users (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username           VARCHAR(100) NOT NULL,
    email              VARCHAR(255) NOT NULL,
    password_hash      VARCHAR(255) NOT NULL,
    status             ENUM('Active', 'Banned', 'Pending') NOT NULL DEFAULT 'Pending',
    is_online          TINYINT(1) NOT NULL DEFAULT 0,
    user_type          ENUM('hoc_sinh', 'sinh_vien', 'giao_vien', 'quan_tri_vien') NOT NULL,
    truong_hoc         VARCHAR(255) DEFAULT NULL,
    truong_dai_hoc     VARCHAR(255) DEFAULT NULL,
    giay_to_chung_minh VARCHAR(255) DEFAULT NULL,
    role_level         INT DEFAULT NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_username (username),
    UNIQUE KEY uk_email (email)
);

-- 1-1 với User
CREATE TABLE ho_so_ca_nhan (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id             INT UNSIGNED NOT NULL,
    thong_tin_dinh_danh TEXT,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE hang_tho (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ten_hang_tho VARCHAR(255) NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE user_hang_tho (
    user_id     INT UNSIGNED NOT NULL,
    hang_tho_id INT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, hang_tho_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (hang_tho_id) REFERENCES hang_tho(id) ON DELETE CASCADE
);

CREATE TABLE phien_luyen_tap (
    id       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id  INT UNSIGNED NOT NULL,
    diem_so  INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE bo_de (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ten_bo_de VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE phong_thach_dau (
    id       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    bo_de_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bo_de_id) REFERENCES bo_de(id)
);

CREATE TABLE user_phong_thach_dau (
    user_id            INT UNSIGNED NOT NULL,
    phong_thach_dau_id INT UNSIGNED NOT NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, phong_thach_dau_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (phong_thach_dau_id) REFERENCES phong_thach_dau(id) ON DELETE CASCADE
);

CREATE TABLE tai_lieu (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    ten_tai_lieu VARCHAR(500) NOT NULL,
    file_url     VARCHAR(500) NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE bai_dang (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id   INT UNSIGNED NOT NULL,
    noi_dung  TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE ban_cung_tien (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED NOT NULL,
    friend_user_id INT UNSIGNED NOT NULL,
    status         ENUM('Pending', 'Accepted', 'Blocked') NOT NULL DEFAULT 'Pending',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (friend_user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uk_cap (user_id, friend_user_id)
);

CREATE TABLE cuoc_tro_chuyen (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE cuoc_tro_chuyen_thanh_vien (
    cuoc_tro_chuyen_id INT UNSIGNED NOT NULL,
    user_id            INT UNSIGNED NOT NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (cuoc_tro_chuyen_id, user_id),
    FOREIGN KEY (cuoc_tro_chuyen_id) REFERENCES cuoc_tro_chuyen(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE tin_nhan (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    cuoc_tro_chuyen_id INT UNSIGNED NOT NULL,
    sender_user_id     INT UNSIGNED NOT NULL,
    noi_dung           TEXT NOT NULL,
    thoi_gian          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cuoc_tro_chuyen_id) REFERENCES cuoc_tro_chuyen(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE su_kien (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tieu_de   VARCHAR(500) NOT NULL,
    thoi_gian DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE thanh_vien_su_kien (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    su_kien_id       INT UNSIGNED NOT NULL,
    user_id          INT UNSIGNED NOT NULL,
    trang_thai_duyet ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (su_kien_id) REFERENCES su_kien(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uk_su_kien_user (su_kien_id, user_id)
);

CREATE TABLE thong_bao (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id   INT UNSIGNED NOT NULL,
    noi_dung  TEXT NOT NULL,
    is_read   TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE bao_cao_thong_ke (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    quan_tri_user_id INT UNSIGNED NOT NULL,
    loai_bao_cao     VARCHAR(64) NOT NULL,
    noi_dung_bao_cao TEXT,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quan_tri_user_id) REFERENCES users(id) ON DELETE CASCADE
);

SET FOREIGN_KEY_CHECKS = 1;
