<?php
require_once '../config/config.php';

/** Avatar mặc định theo user id (bảng users chưa có cột avatar) */
function inboxAvatar(int $userId): string
{
    return 'https://i.pravatar.cc/150?img=' . (($userId % 70) + 1);
}

/** Tên hiển thị: username hoặc dòng đầu hồ sơ cá nhân */
function inboxDisplayName(?string $username, ?string $thongTinDinhDanh): string
{
    $bio = trim((string) $thongTinDinhDanh);
    if ($bio !== '') {
        $line = preg_split('/\r\n|\r|\n/', $bio, 2)[0];
        if (mb_strlen($line) <= 80) {
            return $line;
        }
    }
    return (string) $username;
}

function inboxTimeAgo(string $datetime): string
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
        $m = (int) floor($diff / 60);
        return $m . ' phút trước';
    }
    if ($diff < 86400) {
        $h = (int) floor($diff / 3600);
        return $h . ' giờ trước';
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

function inboxLoadCurrentUser(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT u.id, u.username, h.thong_tin_dinh_danh,
                COALESCE((
                    SELECT SUM(p.diem_so) FROM phien_luyen_tap p WHERE p.user_id = u.id
                ), 0) AS xp_points
         FROM users u
         LEFT JOIN ho_so_ca_nhan h ON h.user_id = u.id
         WHERE u.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    return [
        'id'          => (int) $row['id'],
        'name'        => inboxDisplayName($row['username'], $row['thong_tin_dinh_danh']),
        'avatar'      => inboxAvatar((int) $row['id']),
        'xp'          => (int) $row['xp_points'],
        'xp_rank'     => 'Top 5%',
        'streak'      => 0,
        'profile_url' => 'trang-ca-nhan.php',
    ];
}

function inboxLoadConversations(PDO $pdo, int $currentUserId): array
{
    $sql = "
        SELECT
            c.id AS conv_id,
            peer.id AS peer_id,
            peer.username AS peer_username,
            hpeer.thong_tin_dinh_danh AS peer_bio,
            lm.noi_dung AS preview,
            lm.sender_user_id AS last_sender_id,
            lm.thoi_gian AS last_sent_at
        FROM cuoc_tro_chuyen c
        INNER JOIN cuoc_tro_chuyen_thanh_vien ctv_me
            ON ctv_me.cuoc_tro_chuyen_id = c.id AND ctv_me.user_id = :me
        INNER JOIN cuoc_tro_chuyen_thanh_vien ctv_peer
            ON ctv_peer.cuoc_tro_chuyen_id = c.id AND ctv_peer.user_id <> :me2
        INNER JOIN users peer ON peer.id = ctv_peer.user_id
        LEFT JOIN ho_so_ca_nhan hpeer ON hpeer.user_id = peer.id
        LEFT JOIN tin_nhan lm ON lm.id = (
            SELECT tn.id
            FROM tin_nhan tn
            WHERE tn.cuoc_tro_chuyen_id = c.id
            ORDER BY tn.thoi_gian DESC, tn.id DESC
            LIMIT 1
        )
        ORDER BY COALESCE(lm.thoi_gian, c.created_at) DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['me' => $currentUserId, 'me2' => $currentUserId]);

    $list = [];
    foreach ($stmt->fetchAll() as $row) {
        $convId = (int) $row['conv_id'];
        $list[] = [
            'id'               => (string) $convId,
            'name'             => inboxDisplayName($row['peer_username'], $row['peer_bio']),
            'avatar'           => inboxAvatar((int) $row['peer_id']),
            'preview'          => $row['preview'] ?? '',
            'preview_from_you' => (int) ($row['last_sender_id'] ?? 0) === $currentUserId,
            'time_ago'         => !empty($row['last_sent_at'])
                ? inboxTimeAgo($row['last_sent_at'])
                : '',
        ];
    }
    return $list;
}

function inboxLoadChats(PDO $pdo, int $currentUserId): array
{
    $sql = "
        SELECT
            c.id AS conv_id,
            peer.id AS peer_id,
            peer.username AS peer_username,
            hpeer.thong_tin_dinh_danh AS peer_bio,
            tn.sender_user_id,
            tn.noi_dung,
            tn.thoi_gian
        FROM cuoc_tro_chuyen c
        INNER JOIN cuoc_tro_chuyen_thanh_vien ctv_me
            ON ctv_me.cuoc_tro_chuyen_id = c.id AND ctv_me.user_id = :me
        INNER JOIN cuoc_tro_chuyen_thanh_vien ctv_peer
            ON ctv_peer.cuoc_tro_chuyen_id = c.id AND ctv_peer.user_id <> :me2
        INNER JOIN users peer ON peer.id = ctv_peer.user_id
        LEFT JOIN ho_so_ca_nhan hpeer ON hpeer.user_id = peer.id
        INNER JOIN tin_nhan tn ON tn.cuoc_tro_chuyen_id = c.id
        ORDER BY c.id ASC, tn.thoi_gian ASC, tn.id ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['me' => $currentUserId, 'me2' => $currentUserId]);

    $chats = [];
    foreach ($stmt->fetchAll() as $row) {
        $key = (string) (int) $row['conv_id'];
        if (!isset($chats[$key])) {
            $chats[$key] = [
                'name'     => inboxDisplayName($row['peer_username'], $row['peer_bio']),
                'avatar'   => inboxAvatar((int) $row['peer_id']),
                'messages' => [],
            ];
        }
        $chats[$key]['messages'][] = [
            'from' => (int) $row['sender_user_id'] === $currentUserId ? 'me' : 'them',
            'text' => $row['noi_dung'],
            'time' => inboxTimeAgo($row['thoi_gian']),
        ];
    }
    return $chats;
}

function inboxLoadOnlineFriends(PDO $pdo, int $currentUserId): array
{
    $stmt = $pdo->prepare(
        "SELECT DISTINCT
            u.id AS peer_id,
            u.username,
            h.thong_tin_dinh_danh,
            u.is_online,
            c.id AS conv_id
         FROM users u
         INNER JOIN cuoc_tro_chuyen_thanh_vien ctv_peer ON ctv_peer.user_id = u.id
         INNER JOIN cuoc_tro_chuyen c ON c.id = ctv_peer.cuoc_tro_chuyen_id
         INNER JOIN cuoc_tro_chuyen_thanh_vien ctv_me
            ON ctv_me.cuoc_tro_chuyen_id = c.id AND ctv_me.user_id = :me
         LEFT JOIN ho_so_ca_nhan h ON h.user_id = u.id
         WHERE u.id <> :me2
           AND u.is_online = 1
         ORDER BY u.username
         LIMIT 10"
    );
    $stmt->execute(['me' => $currentUserId, 'me2' => $currentUserId]);

    $friends = [];
    foreach ($stmt->fetchAll() as $row) {
        $friends[] = [
            'chat_id' => (string) (int) $row['conv_id'],
            'name'    => inboxDisplayName($row['username'], $row['thong_tin_dinh_danh']),
            'avatar'  => inboxAvatar((int) $row['peer_id']),
            'status'  => (int) $row['is_online'] === 1 ? 'online' : 'away',
        ];
    }
    return $friends;
}

function inboxLoadPage(PDO $pdo, int $currentUserId): array
{
    $currentUser = inboxLoadCurrentUser($pdo, $currentUserId);
    if ($currentUser === null) {
        throw new RuntimeException('Không tìm thấy user id=' . $currentUserId);
    }

    $conversations = inboxLoadConversations($pdo, $currentUserId);
    $chats = inboxLoadChats($pdo, $currentUserId);

    return [
        'currentUser'      => $currentUser,
        'conversations'    => $conversations,
        'chats'            => $chats,
        'onlineFriends'    => inboxLoadOnlineFriends($pdo, $currentUserId),
        'inboxThreadCount' => count($conversations),
    ];
}

function inboxSendMessage(PDO $pdo, int $currentUserId, string $chatId, string $text): array
{
    $text = trim($text);
    if ($text === '') {
        throw new InvalidArgumentException('Nội dung tin nhắn trống.');
    }

    $convId = (int) $chatId;
    if ($convId <= 0) {
        throw new InvalidArgumentException('Cuộc trò chuyện không hợp lệ.');
    }

    $stmt = $pdo->prepare(
        'SELECT c.id
         FROM cuoc_tro_chuyen c
         INNER JOIN cuoc_tro_chuyen_thanh_vien ctv
            ON ctv.cuoc_tro_chuyen_id = c.id AND ctv.user_id = :uid
         WHERE c.id = :cid
         LIMIT 1'
    );
    $stmt->execute(['uid' => $currentUserId, 'cid' => $convId]);
    if (!$stmt->fetch()) {
        throw new RuntimeException('Không tìm thấy cuộc trò chuyện hoặc bạn không có quyền gửi.');
    }

    $ins = $pdo->prepare(
        'INSERT INTO tin_nhan (cuoc_tro_chuyen_id, sender_user_id, noi_dung, thoi_gian)
         VALUES (:cid, :sid, :noi_dung, NOW())'
    );
    $ins->execute([
        'cid'      => $convId,
        'sid'      => $currentUserId,
        'noi_dung' => $text,
    ]);

    return [
        'from' => 'me',
        'text' => $text,
        'time' => 'Vừa xong',
    ];
}
