<?php
/**
 * Trang tin nhắn đã chuyển sang pages/tin-nhan.php — giữ file này để redirect link cũ.
 */
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = 'pages/tin-nhan.php' . ($query !== '' ? '?' . $query : '');
header('Location: ' . $target, true, 302);
exit;
