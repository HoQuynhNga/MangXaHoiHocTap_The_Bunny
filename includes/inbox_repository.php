<?php
require_once __DIR__ . '/../config/db_module.php';
require_once __DIR__ . '/bunny_helpers.php';

function inboxAvatar(int $userId): string
{
    return '../assets/img/default-avatar.jpg';
}

function inboxDisplayName(?string $username, ?string $thongTinDinhDanh): string
{
    return $username ?? 'Tài khoản ẩn danh';
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
        'username'    => $row['username'],
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
         ORDER BY (
             SELECT COALESCE(MAX(tn.thoi_gian), c.created_at)
             FROM tin_nhan tn
             WHERE tn.cuoc_tro_chuyen_id = c.id
         ) DESC, c.id ASC
         LIMIT 1'
    );
    $stmt->execute(['me' => $currentUserId, 'peer' => $peerId]);
    $id = $stmt->fetchColumn();

    return $id !== false ? (int) $id : null;
}

/**
 * Gộp các cuộc trò chuyện 1-1 trùng cặp user (do seed/import chạy nhiều lần).
 * Giữ cuộc có tin nhắn mới nhất; chuyển tin nhắn sang cuộc chính rồi xóa bản trùng.
 */
function inboxCleanupDuplicateConversations(PDO $pdo): int
{
    $pairs = $pdo->query(
        "SELECT LEAST(m1.user_id, m2.user_id) AS u1,
                GREATEST(m1.user_id, m2.user_id) AS u2,
                GROUP_CONCAT(DISTINCT c.id ORDER BY c.id) AS conv_ids
         FROM cuoc_tro_chuyen c
         INNER JOIN cuoc_tro_chuyen_thanh_vien m1 ON m1.cuoc_tro_chuyen_id = c.id
         INNER JOIN cuoc_tro_chuyen_thanh_vien m2
            ON m2.cuoc_tro_chuyen_id = c.id AND m2.user_id > m1.user_id
         WHERE (SELECT COUNT(*) FROM cuoc_tro_chuyen_thanh_vien x WHERE x.cuoc_tro_chuyen_id = c.id) = 2
         GROUP BY u1, u2
         HAVING COUNT(DISTINCT c.id) > 1"
    )->fetchAll();

    $merged = 0;
    foreach ($pairs as $pair) {
        $ids = array_map('intval', explode(',', $pair['conv_ids']));
        if (count($ids) < 2) {
            continue;
        }

        // Chọn cuộc chính: nhiều tin nhắn nhất, hoặc id nhỏ nhất
        $pick = $pdo->prepare(
            'SELECT c.id,
                    (SELECT COUNT(*) FROM tin_nhan tn WHERE tn.cuoc_tro_chuyen_id = c.id) AS msg_count,
                    (SELECT COALESCE(MAX(tn.thoi_gian), c.created_at) FROM tin_nhan tn WHERE tn.cuoc_tro_chuyen_id = c.id) AS last_at
             FROM cuoc_tro_chuyen c
             WHERE c.id IN (' . implode(',', $ids) . ')
             ORDER BY msg_count DESC, last_at DESC, c.id ASC
             LIMIT 1'
        );
        $pick->execute();
        $primaryId = (int) $pick->fetchColumn();
        if ($primaryId <= 0) {
            continue;
        }

        foreach ($ids as $dupId) {
            if ($dupId === $primaryId) {
                continue;
            }
            $pdo->prepare(
                'UPDATE tin_nhan SET cuoc_tro_chuyen_id = :primary WHERE cuoc_tro_chuyen_id = :dup'
            )->execute(['primary' => $primaryId, 'dup' => $dupId]);
            $pdo->prepare(
                'DELETE FROM cuoc_tro_chuyen_thanh_vien WHERE cuoc_tro_chuyen_id = :dup'
            )->execute(['dup' => $dupId]);
            $pdo->prepare('DELETE FROM cuoc_tro_chuyen WHERE id = :dup')->execute(['dup' => $dupId]);
            $merged++;
        }
    }

    return $merged;
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
        'username' => $row['username'],
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
    $seenPeers = [];
    foreach ($stmt->fetchAll() as $row) {
        $peerId = (int) $row['peer_id'];
        // Một người chỉ hiển thị một dòng — lấy cuộc có tin nhắn mới nhất (ORDER BY ở trên)
        if (isset($seenPeers[$peerId])) {
            continue;
        }
        $seenPeers[$peerId] = true;

        $convId = (int) $row['conv_id'];
        $preview = (string) ($row['preview'] ?? '');
        $list[] = [
            'id'               => (string) $convId,
            'peer_id'          => $peerId,
            'username'         => $row['peer_username'],
            'name'             => inboxDisplayName($row['peer_username'], $row['peer_bio']),
            'avatar'           => inboxAvatar($peerId),
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
    $conversations = inboxLoadConversations($pdo, $currentUserId);
    $chats = [];

    foreach ($conversations as $conv) {
        $chats[$conv['id']] = [
            'username' => $conv['username'],
            'name'     => $conv['name'],
            'avatar'   => $conv['avatar'],
            'peer_id'  => $conv['peer_id'],
            'messages' => [],
        ];
    }

    if ($chats === []) {
        return $chats;
    }

    // Lấy tin nhắn từ mọi cuộc 1-1 với cùng peer (phòng khi DB còn bản trùng chưa gộp)
    $convIds = array_map('intval', array_keys($chats));
    $peerIds = array_values(array_unique(array_column($conversations, 'peer_id')));

    $extraConvIds = [];
    if ($peerIds !== []) {
        $ph = implode(',', array_fill(0, count($peerIds), '?'));
        $extraStmt = $pdo->prepare(
            "SELECT DISTINCT c.id
             FROM cuoc_tro_chuyen c
             INNER JOIN cuoc_tro_chuyen_thanh_vien me ON me.cuoc_tro_chuyen_id = c.id AND me.user_id = ?
             INNER JOIN cuoc_tro_chuyen_thanh_vien peer ON peer.cuoc_tro_chuyen_id = c.id AND peer.user_id IN ($ph)
             WHERE (SELECT COUNT(*) FROM cuoc_tro_chuyen_thanh_vien x WHERE x.cuoc_tro_chuyen_id = c.id) = 2"
        );
        $extraStmt->execute(array_merge([$currentUserId], $peerIds));
        foreach ($extraStmt->fetchAll(PDO::FETCH_COLUMN) as $cid) {
            $extraConvIds[] = (int) $cid;
        }
    }

    $allConvIds = array_values(array_unique(array_merge($convIds, $extraConvIds)));
    $peerByConv = [];
    foreach ($conversations as $conv) {
        $peerByConv[(int) $conv['id']] = (int) $conv['peer_id'];
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
        WHERE tn.cuoc_tro_chuyen_id IN (" . implode(',', $allConvIds) . ")
        ORDER BY tn.thoi_gian ASC, tn.id ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['me' => $currentUserId]);

    // Gom tin nhắn theo peer_id
    $messagesByPeer = [];
    foreach ($stmt->fetchAll() as $row) {
        $cid = (int) $row['conv_id'];
        $peerId = $peerByConv[$cid] ?? null;
        if ($peerId === null) {
            // Resolve peer from conv members
            $pStmt = $pdo->prepare(
                'SELECT user_id FROM cuoc_tro_chuyen_thanh_vien WHERE cuoc_tro_chuyen_id = ? AND user_id <> ? LIMIT 1'
            );
            $pStmt->execute([$cid, $currentUserId]);
            $peerId = (int) $pStmt->fetchColumn();
        }
        if ($peerId <= 0) {
            continue;
        }
        $messagesByPeer[$peerId][] = [
            'from' => (int) $row['sender_user_id'] === $currentUserId ? 'me' : 'them',
            'text' => $row['noi_dung'],
            'time' => inboxTimeAgo($row['thoi_gian']),
            '_ts'  => strtotime($row['thoi_gian']),
        ];
    }

    foreach ($chats as $chatId => &$chat) {
        $peerId = (int) $chat['peer_id'];
        $msgs = $messagesByPeer[$peerId] ?? [];
        usort($msgs, fn($a, $b) => ($a['_ts'] ?? 0) <=> ($b['_ts'] ?? 0));
        $chat['messages'] = array_map(function ($m) {
            unset($m['_ts']);
            return $m;
        }, $msgs);
    }
    unset($chat);

    return $chats;
}

function inboxLoadOnlineFriends(PDO $pdo, int $currentUserId): array
{
    // Viết lại câu lệnh JOIN thuần túy, không phụ thuộc vào file buddies_repository.php để tránh lỗi PDO
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
         INNER JOIN users peer ON (
             (b.user_id = :me1 AND peer.id = b.friend_user_id) OR
             (b.friend_user_id = :me2 AND peer.id = b.user_id)
         )
         LEFT JOIN ho_so_ca_nhan h ON h.user_id = peer.id
         WHERE b.status = 'Accepted'
         ORDER BY peer.is_online DESC, peer.username ASC
         LIMIT 12"
    );
    
    // Gán chính xác 3 tham số
    $stmt->execute([
        'me_conv' => $currentUserId,
        'me1'     => $currentUserId,
        'me2'     => $currentUserId,
    ]);

    $friends = [];
    foreach ($stmt->fetchAll() as $row) {
        $friends[] = [
            'peer_id'  => (int) $row['peer_id'],
            'chat_id'  => $row['conv_id'] !== null ? (string) (int) $row['conv_id'] : '',
            'username' => $row['username'], // <--- THÊM KHÓA NÀY ĐỂ GIAO DIỆN NHẬN DIỆN
            'name'     => inboxDisplayName($row['username'], $row['thong_tin_dinh_danh']),
            'avatar'   => inboxAvatar((int) $row['peer_id']),
            'status'   => (int) $row['is_online'] === 1 ? 'online' : 'away',
        ];
    }

    return $friends;
}

function inboxLoadPage(PDO $pdo, int $currentUserId): array
{
    inboxCleanupDuplicateConversations($pdo);

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
            'username' => $peer['username'],
            'name'     => $peer['name'],
            'avatar'   => $peer['avatar'],
            'peer_id'  => $peer['peer_id'],
            'messages' => [],
        ],
        'conversation' => [
            'id'               => (string) $convId,
            'username'         => $peer['username'],
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
