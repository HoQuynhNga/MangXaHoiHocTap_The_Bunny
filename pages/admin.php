<?php
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'quan_tri_vien') {
    header('Location: ../index.php');
    exit;
}

header('Location: admin/index.php');
exit;
