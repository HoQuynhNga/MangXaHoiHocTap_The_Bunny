<?php

function bunnyAvatar(int $userId): string
{
    return 'https://i.pravatar.cc/150?img=' . (($userId % 70) + 1);
}

function bunnyDisplayName(?string $username, ?string $thongTinDinhDanh): string
{
    $bio = trim((string) $thongTinDinhDanh);
    if ($bio !== '') {
        foreach (preg_split('/\r\n|\r|\n/', $bio) as $line) {
            $line = trim($line);
            if ($line === '' || preg_match('/^(BIO|QUOTE|EDU|JOB|LOC):/i', $line)) {
                continue;
            }
            if (mb_strlen($line) <= 80) {
                return $line;
            }
        }
    }
    return (string) $username;
}

function bunnyTimeAgo(string $datetime): string
{
    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'Vừa xong';
    }
    if ($diff < 3600) {
        return (int) floor($diff / 60) . ' phút trước';
    }
    if ($diff < 86400) {
        return (int) floor($diff / 3600) . ' giờ trước';
    }
    if ($diff < 604800) {
        $d = (int) floor($diff / 86400);
        return $d === 1 ? 'Hôm qua' : ($d . ' ngày trước');
    }
    if ($diff < 2592000) {
        return 'Tuần trước';
    }
    return date('d/m/Y', $ts);
}

function bunnyXpRank(int $xp): string
{
    if ($xp >= 1500) {
        return 'Top 5%';
    }
    if ($xp >= 1000) {
        return 'Top 15%';
    }
    if ($xp >= 500) {
        return 'Top 30%';
    }
    return 'Đang leo rank';
}

/** Parse metadata trong ho_so_ca_nhan.thong_tin_dinh_danh */
function bunnyParseProfileMeta(?string $raw): array
{
    $meta = [
        'bio'   => '',
        'quote' => '',
        'edu'   => '',
        'job'   => '',
        'loc'   => '',
    ];
    foreach (preg_split('/\r\n|\r|\n/', (string) $raw) as $line) {
        $line = trim($line);
        if (preg_match('/^BIO:\s*(.+)$/i', $line, $m)) {
            $meta['bio'] = $m[1];
        } elseif (preg_match('/^QUOTE:\s*(.+)$/i', $line, $m)) {
            $meta['quote'] = $m[1];
        } elseif (preg_match('/^EDU:\s*(.+)$/i', $line, $m)) {
            $meta['edu'] = $m[1];
        } elseif (preg_match('/^JOB:\s*(.+)$/i', $line, $m)) {
            $meta['job'] = $m[1];
        } elseif (preg_match('/^LOC:\s*(.+)$/i', $line, $m)) {
            $meta['loc'] = $m[1];
        }
    }
    return $meta;
}

function bunnyFatalError(string $message, int $code = 500): void
{
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="vi"><head><meta charset="utf-8"><title>Lỗi — The Bunny</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>';
    echo '<body class="bg-light"><div class="container py-5"><div class="alert alert-danger">';
    echo '<h1 class="h4">Không thể tải trang</h1>';
    echo '<p class="mb-0">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<hr><p class="small text-muted mb-0">Kiểm tra <code>config.php</code>, MySQL/phpMyAdmin và file <code>database/seed-full.sql</code>.</p>';
    echo '</div></div></body></html>';
    exit;
}
