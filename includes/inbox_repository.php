<?php
require_once __DIR__ . '/../config/db_module.php';
require_once __DIR__ . '/bunny_helpers.php';

function inboxAvatar(int $userId): string
{
    return bunnyAvatar($userId);
}

function inboxDisplayName(?string $username, ?string $thongTinDinhDanh): string
{
    return bunnyDisplayName($username, $thongTinDinhDanh);
}

function inboxTimeAgo(string $datetime): string
{
    return bunnyTimeAgo($datetime);
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

    $xp = (int) $row['xp_points'];

    return [
        'id'          => (int) $row['id'],
        'name'        => inboxDisplayName($row['username'], $row['thong_tin_dinh_danh']),
        'avatar'      => inboxAvatar((int) $row['id']),
        'xp'          => $xp,
        'xp_rank'     => bunnyXpRank($xp),
        'streak'      => 0,
        'profile_url' => 'trang-ca-nhan.php',
    ];
}

function inboxAreFriends(PDO $pdo, int $userId, int $peerId): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1 FROM ban_cung_tien
         WHERE status = 'Accepted'
           AND ((user_id = :me AND friend_user_id = :peer)
             OR (user_id = :peer2 AND friend_user_id = :me2))
         LIMIT 1"
    );
    $stmt->execute([
        'me'    => $userId,
        'peer'  => $peerId,
        'peer2' => $peerId,
        'me2'   => $userId,
    ]);

    return (bool) $stmt->fetchColumn();
}

function inboxFindConversationWithPeer(PDO $pdo, int $currentUserId, int $peerId): ?int
{
    $stmt = $pdo->prepare(
        'SELECT c.id
         FROM cuoc_tro_chuyen c
         INNER JOIN cuoc_tro_chuyen_thanh_vien ctv_me
            ON ctv_me.cuoc_tro_chuyen_id = c.id AND ctv_me.user_id = :me
         INNER JOIN cuoc_tro_chuyen_thanh_vien ctv_peer
            ON ctv_peer.cuoc_tro_chuyen_id = c.id AND ctv_peer.user_id = :peer
         WHERE (
             SELECT COUNT(*)
             FROM cuoc_tro_chuyen_thanh_vien x
             WHERE x.cuoc_tro_chuyen_id = c.id
         ) = 2
         LIMIT 1'
    );
    $stmt->execute(['me' => $currentUserId, 'peer' => $peerId]);
    $id = $stmt->fetchColumn();

    return $id !== false ? (int) $id : null;
}

function inboxGetOrCreateConversation(PDO $pdo, int $currentUserId, int $peerId): int
{
    if ($peerId <= 0 || $peerId === $currentUserId) {
        throw new InvalidArgumentException('Người nhận không hợp lệ.');
    }

    if (!inboxAreFriends($pdo, $currentUserId, $peerId)) {
        throw new RuntimeException('Bạn chỉ có thể nhắn tin với bạn cùng tiến đã kết bạn.');
    }

    $existing = inboxFindConversationWithPeer($pdo, $currentUserId, $peerId);
    if ($existing !== null) {
        return $existing;
    }

    $pdo->beginTransaction();
    try {
        $pdo->exec('INSERT INTO cuoc_tro_chuyen () VALUES ()');
        $convId = (int) $pdo->lastInsertId();

        $ins = $pdo->prepare(
            'INSERT INTO cuoc_tro_chuyen_thanh_vien (cuoc_tro_chuyen_id, user_id) VALUES (:cid, :uid)'
        );
        $ins->execute(['cid' => $convId, 'uid' => $currentUserId]);
        $ins->execute(['cid' => $convId, 'uid' => $peerId]);

        $pdo->commit();
        return $convId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function inboxLoadPeerMeta(PDO $pdo, int $peerId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT u.id, u.username, h.thong_tin_dinh_danh
         FROM users u
         LEFT JOIN ho_so_ca_nhan h ON h.user_id = u.id
         WHERE u.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $peerId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    return [
        'peer_id' => (int) $row['id'],
        'name'    => inboxDisplayName($row['username'], $row['thong_tin_dinh_danh']),
        'avatar'  => inboxAvatar((int) $row['id']),
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
        $preview = (string) ($row['preview'] ?? '');
        $list[] = [
            'id'               => (string) $convId,
            'peer_id'          => (int) $row['peer_id'],
            'name'             => inboxDisplayName($row['peer_username'], $row['peer_bio']),
            'avatar'           => inboxAvatar((int) $row['peer_id']),
            'preview'          => $preview,
            'preview_from_you' => (int) ($row['last_sender_id'] ?? 0) === $currentUserId,
            'time_ago'         => !empty($row['last_sent_at'])
                ? inboxTimeAgo($row['last_sent_at'])
                : '',
            'is_empty'         => $preview === '',
        ];
    }

    return $list;
}

function inboxLoadChats(PDO $pdo, int $currentUserId): array
{
    $chats = [];

    foreach (inboxLoadConversations($pdo, $currentUserId) as $conv) {
        $chats[$conv['id']] = [
            'name'     => $conv['name'],
            'avatar'   => $conv['avatar'],
            'peer_id'  => $conv['peer_id'],
            'messages' => [],
        ];
    }

    if ($chats === []) {
        return $chats;
    }

    $sql = "
        SELECT
            tn.cuoc_tro_chuyen_id AS conv_id,
            tn.sender_user_id,
            tn.noi_dung,
            tn.thoi_gian
        FROM tin_nhan tn
        INNER JOIN cuoc_tro_chuyen_thanh_vien ctv
            ON ctv.cuoc_tro_chuyen_id = tn.cuoc_tro_chuyen_id
           AND ctv.user_id = :me
        WHERE tn.cuoc_tro_chuyen_id IN (" . implode(',', array_map('intval', array_keys($chats))) . ")
        ORDER BY tn.thoi_gian ASC, tn.id ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['me' => $currentUserId]);

    foreach ($stmt->fetchAll() as $row) {
        $key = (string) (int) $row['conv_id'];
        if (!isset($chats[$key])) {
            continue;
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
        "SELECT
            peer.id AS peer_id,
            peer.username,
            h.thong_tin_dinh_danh,
            peer.is_online,
            (
                SELECT c.id
                FROM cuoc_tro_chuyen c
                INNER JOIN cuoc_tro_chuyen_thanh_vien ctv_me
                    ON ctv_me.cuoc_tro_chuyen_id = c.id AND ctv_me.user_id = :me_conv
                INNER JOIN cuoc_tro_chuyen_thanh_vien ctv_peer
                    ON ctv_peer.cuoc_tro_chuyen_id = c.id AND ctv_peer.user_id = peer.id
                WHERE (
                    SELECT COUNT(*)
                    FROM cuoc_tro_chuyen_thanh_vien x
                    WHERE x.cuoc_tro_chuyen_id = c.id
                ) = 2
                LIMIT 1
            ) AS conv_id
         FROM ban_cung_tien b
         INNER JOIN users peer ON peer.id = CASE
             WHEN b.user_id = :me THEN b.friend_user_id
             ELSE b.user_id
         END
         LEFT JOIN ho_so_ca_nhan h ON h.user_id = peer.id
         WHERE b.status = 'Accepted'
           AND (b.user_id = :me2 OR b.friend_user_id = :me3)
           AND peer.id <> :me4
         ORDER BY peer.is_online DESC, peer.username ASC
         LIMIT 12"
    );
    $stmt->execute([
        'me_conv' => $currentUserId,
        'me'      => $currentUserId,
        'me2'     => $currentUserId,
        'me3'     => $currentUserId,
        'me4'     => $currentUserId,
    ]);

    $friends = [];
    foreach ($stmt->fetchAll() as $row) {
        $friends[] = [
            'peer_id' => (int) $row['peer_id'],
            'chat_id' => $row['conv_id'] !== null ? (string) (int) $row['conv_id'] : '',
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

function inboxStartChat(PDO $pdo, int $currentUserId, int $peerId): array
{
    $convId = inboxGetOrCreateConversation($pdo, $currentUserId, $peerId);
    $peer = inboxLoadPeerMeta($pdo, $peerId);
    if ($peer === null) {
        throw new RuntimeException('Không tìm thấy người dùng.');
    }

    return [
        'chat_id' => (string) $convId,
        'chat'    => [
            'name'     => $peer['name'],
            'avatar'   => $peer['avatar'],
            'peer_id'  => $peer['peer_id'],
            'messages' => [],
        ],
        'conversation' => [
            'id'               => (string) $convId,
            'peer_id'          => $peer['peer_id'],
            'name'             => $peer['name'],
            'avatar'           => $peer['avatar'],
            'preview'          => '',
            'preview_from_you' => false,
            'time_ago'         => '',
            'is_empty'         => true,
        ],
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
