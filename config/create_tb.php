<?php
// Nạp cấu hình hệ thống
require_once 'config.php';

echo "<!DOCTYPE html><html lang='vi'><head><meta charset='UTF-8'><title>Khởi tạo CSDL - The Bunny</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'></head><body class='bg-light'><div class='container mt-5'><div class='card shadow'><div class='card-header bg-primary text-white'><h4>Tiến trình khởi tạo Cơ sở dữ liệu</h4></div><div class='card-body'>";

// 1. KẾT NỐI ĐẾN MYSQL SERVER (Chưa chọn DB)
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

if ($conn->connect_error) {
    die("<div class='alert alert-danger'>Kết nối MySQL thất bại: " . $conn->connect_error . "</div>");
}

// 2. TẠO DATABASE NẾU CHƯA TỒN TẠI
$sql_create_db = "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql_create_db) === TRUE) {
    echo "<p class='text-success'>✅ Cơ sở dữ liệu <strong>" . DB_NAME . "</strong> đã sẵn sàng.</p>";
} else {
    die("<div class='alert alert-danger'>Lỗi tạo DB: " . $conn->error . "</div>");
}

// 3. CHỌN DATABASE ĐỂ THAO TÁC
$conn->select_db(DB_NAME);

// =====================================================
// 4. THUẬT TOÁN ĐỌC VÀ THỰC THI FILE SQL
// =====================================================

// Đường dẫn tới file .sql của bạn (Hãy sửa lại tên file cho đúng với file của nhóm)
$sql_file_path = 'the_bunny_db.sql';

if (!file_exists($sql_file_path)) {
    die("<div class='alert alert-danger'>❌ Không tìm thấy file SQL tại: <strong>$sql_file_path</strong>. Vui lòng kiểm tra lại đường dẫn!</div>");
}

// Đọc toàn bộ nội dung file SQL thành một mảng các dòng
$lines = file($sql_file_path);

$query = '';
$delimiter = ';'; // Delimiter mặc định

$success_count = 0;
$error_count = 0;

echo "<div class='p-3 bg-dark text-white rounded mb-3' style='max-height: 200px; overflow-y: auto; font-family: monospace;'>";

foreach ($lines as $line) {

    $trimmed_line = trim($line);

    // Bỏ qua các dòng trống hoặc dòng comment (-- hoặc /*)
    if (empty($trimmed_line) || strpos($trimmed_line, '--') === 0 || strpos($trimmed_line, '/*') === 0) {
        continue;
    }

    // Bắt sự kiện đổi Delimiter (Ví dụ: DELIMITER $$)
    if (preg_match('/^DELIMITER\s+(.*)$/i', $trimmed_line, $matches)) {
        $delimiter = $matches[1]; // Cập nhật delimiter mới (vd: $$)
        continue; // Bỏ qua dòng này không đưa vào query chạy
    }

    // Cộng dồn dòng hiện tại vào biến $query
    $query .= $line;

    // Nếu cuối chuỗi hiện tại có chứa delimiter --> Lệnh đã hoàn chỉnh, tiến hành thực thi
    if (substr($trimmed_line, -strlen($delimiter)) === $delimiter) {

        // Xóa delimiter ở cuối chuỗi để tránh lỗi cú pháp MySQL
        $query_to_execute = substr($query, 0, -strlen($delimiter));

        // Chạy câu lệnh SQL
        if ($conn->query($query_to_execute) === TRUE) {

            $success_count++;

            echo "<div class='text-success'>✔ Thành công</div>";

        } else {

            $error_count++;

            echo "<div class='text-danger'>❌ Lỗi: " . $conn->error . "</div>";
        }

        // Reset lại chuỗi để đọc lệnh tiếp theo
        $query = '';
    }
}

echo "</div>";

$conn->close();


// =====================================================
// 5. HIỂN THỊ KẾT QUẢ TỔNG QUÁT
// =====================================================

if ($error_count === 0 && $success_count > 0) {

    echo "<div class='alert alert-success'><h5>🎉 Import Database Thành Công!</h5>Đã thực thi <strong>$success_count</strong> khối lệnh (Bao gồm Bảng, Data, Trigger & Procedure).</div>";

} elseif ($error_count > 0) {

    echo "<div class='alert alert-warning'><h5>⚠ Import hoàn tất nhưng có lỗi</h5>Đã thực thi $success_count lệnh thành công, nhưng có <strong>$error_count</strong> lệnh bị lỗi. (Xem chi tiết trên khung đen).</div>";

} else {

    echo "<div class='alert alert-info'>Không có lệnh SQL nào được thực thi. Hãy kiểm tra lại nội dung file.</div>";
}

echo "<div class='text-center mt-4'>
        <a href='../index.php' class='btn btn-success fw-bold px-4'>Vào Trang Chủ / Đăng Nhập</a>
      </div>";

echo "</div></div></div></body></html>";
?>

